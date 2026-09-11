#!/usr/bin/env python3
"""Select a working subscription node without logging node credentials."""

from __future__ import annotations

import argparse
import base64
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
from urllib.error import HTTPError, URLError
from urllib.parse import parse_qs, unquote, urlsplit
from urllib.request import ProxyHandler, Request, build_opener, urlopen


PROBE_URL = "https://api.telegram.org/bot0:invalid/getMe"


def _b64decode(value: str) -> bytes:
    normalized = value.strip().replace("-", "+").replace("_", "/")
    normalized += "=" * ((4 - len(normalized) % 4) % 4)
    return base64.b64decode(normalized, validate=True)


def _read_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip()
    return values


def _subscription_lines(raw: str) -> list[str]:
    candidate = raw.strip()
    if "://" not in candidate:
        try:
            candidate = _b64decode(candidate).decode("utf-8")
        except (ValueError, UnicodeError):
            return []
    return [line.strip() for line in candidate.splitlines() if line.strip()]


def _subscription_urls(values: dict[str, str]) -> list[str]:
    prefix = "DENT_TELEGRAM_EGRESS_SUBSCRIPTION_URL_B64"
    encoded = [
        value
        for key, value in sorted(values.items())
        if (key == prefix or key.startswith(prefix + "_")) and value
    ]
    if not encoded:
        raise ValueError("subscription-not-configured")
    urls: list[str] = []
    for value in encoded:
        try:
            url = _b64decode(value).decode("utf-8")
        except (ValueError, UnicodeError) as error:
            raise ValueError("subscription-configuration-invalid") from error
        if url not in urls:
            urls.append(url)
    return urls


def _fetch_subscription_links(values: dict[str, str]) -> tuple[list[str], int, int]:
    urls = _subscription_urls(values)
    links: list[str] = []
    seen: set[str] = set()
    fetched = 0
    failed = 0
    for subscription_url in urls:
        try:
            with urlopen(
                Request(subscription_url, headers={"User-Agent": "v2rayNG/1"}),
                timeout=20,
            ) as response:
                raw_subscription = response.read(2 * 1024 * 1024 + 1)
            if len(raw_subscription) > 2 * 1024 * 1024:
                failed += 1
                continue
            source_links = _subscription_lines(raw_subscription.decode("utf-8"))
        except (HTTPError, URLError, OSError, TimeoutError, UnicodeError):
            failed += 1
            continue
        fetched += 1
        for link in source_links:
            if link not in seen:
                seen.add(link)
                links.append(link)
    if fetched == 0:
        raise RuntimeError("all-subscription-fetches-failed")
    return links, fetched, failed


def _first(query: dict[str, list[str]], key: str, default: str = "") -> str:
    values = query.get(key, [])
    return values[0] if values else default


def _vless_outbound(link: str) -> dict:
    parsed = urlsplit(link)
    node_id = unquote(parsed.username or "")
    if not node_id or not parsed.hostname or not parsed.port:
        raise ValueError("incomplete-vless")
    query = parse_qs(parsed.query, keep_blank_values=True)
    encryption = _first(query, "encryption", "none") or "none"
    user: dict[str, object] = {"id": node_id, "encryption": encryption}
    flow = _first(query, "flow")
    if flow:
        user["flow"] = flow
    outbound: dict[str, object] = {
        "tag": "telegram-egress",
        "protocol": "vless",
        "settings": {
            "vnext": [{
                "address": parsed.hostname,
                "port": parsed.port,
                "users": [user],
            }]
        },
    }

    network = _first(query, "type", "tcp").lower() or "tcp"
    security = _first(query, "security", "none").lower() or "none"
    stream: dict[str, object] = {"network": network, "security": security}
    fingerprint = _first(query, "fp", "chrome") or "chrome"
    server_name = _first(query, "sni")
    if security == "reality":
        settings: dict[str, object] = {
            "show": False,
            "fingerprint": fingerprint,
            "serverName": server_name,
            "publicKey": _first(query, "pbk"),
            "shortId": _first(query, "sid"),
            "spiderX": _first(query, "spx", "/") or "/",
        }
        stream["realitySettings"] = settings
    elif security == "tls":
        settings = {
            "serverName": server_name or parsed.hostname,
            "allowInsecure": _first(query, "allowInsecure", "0") in {"1", "true"},
            "fingerprint": fingerprint,
        }
        alpn = [item for item in _first(query, "alpn").split(",") if item]
        if alpn:
            settings["alpn"] = alpn
        stream["tlsSettings"] = settings

    path = unquote(_first(query, "path", "/") or "/")
    host = _first(query, "host")
    if network == "ws":
        stream["wsSettings"] = {"path": path, "headers": {"Host": host} if host else {}}
    elif network == "grpc":
        stream["grpcSettings"] = {
            "serviceName": unquote(_first(query, "serviceName") or path.lstrip("/")),
            "multiMode": _first(query, "mode").lower() == "multi",
        }
    elif network == "xhttp":
        xhttp: dict[str, object] = {"path": path}
        if host:
            xhttp["host"] = host
        mode = _first(query, "mode")
        if mode:
            xhttp["mode"] = mode
        extra = unquote(_first(query, "extra"))
        if extra:
            try:
                xhttp["extra"] = json.loads(extra)
            except json.JSONDecodeError:
                pass
        stream["xhttpSettings"] = xhttp
    elif network in {"tcp", "raw"} and _first(query, "headerType") == "http":
        stream["tcpSettings"] = {
            "header": {
                "type": "http",
                "request": {
                    "path": [path],
                    "headers": {"Host": [host]} if host else {},
                },
            }
        }
    outbound["streamSettings"] = stream
    return outbound


def _shadowsocks_outbound(link: str) -> dict:
    body = link.removeprefix("ss://").split("#", 1)[0]
    if "@" in body:
        userinfo, endpoint = body.rsplit("@", 1)
        decoded = _b64decode(userinfo).decode("utf-8") if ":" not in userinfo else unquote(userinfo)
        endpoint_parsed = urlsplit(f"ss://x@{endpoint}")
    else:
        decoded_full = _b64decode(body).decode("utf-8")
        userinfo, endpoint = decoded_full.rsplit("@", 1)
        decoded = userinfo
        endpoint_parsed = urlsplit(f"ss://x@{endpoint}")
    method, password = decoded.split(":", 1)
    if not endpoint_parsed.hostname or not endpoint_parsed.port:
        raise ValueError("incomplete-shadowsocks")
    return {
        "tag": "telegram-egress",
        "protocol": "shadowsocks",
        "settings": {
            "servers": [{
                "address": endpoint_parsed.hostname,
                "port": endpoint_parsed.port,
                "method": method,
                "password": password,
            }]
        },
    }


def _node_config(link: str, port: int) -> tuple[str, dict]:
    protocol = link.split(":", 1)[0].lower()
    if protocol == "vless":
        outbound = _vless_outbound(link)
    elif protocol == "ss":
        outbound = _shadowsocks_outbound(link)
    else:
        raise ValueError("unsupported-protocol")
    return protocol, {
        "log": {"loglevel": "none"},
        "inbounds": [{
            "listen": "127.0.0.1",
            "port": port,
            "protocol": "http",
            "settings": {"allowTransparent": False},
            "tag": "telegram-http-in",
        }],
        "outbounds": [outbound],
        "routing": {"rules": [{"type": "field", "inboundTag": ["telegram-http-in"], "outboundTag": "telegram-egress"}]},
    }


def _wait_for_port(port: int, process: subprocess.Popen[bytes], timeout: float = 2.5) -> bool:
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline and process.poll() is None:
        try:
            with socket.create_connection(("127.0.0.1", port), timeout=0.15):
                return True
        except OSError:
            time.sleep(0.05)
    return False


def _probe(port: int, timeout: float) -> bool:
    proxy = f"http://127.0.0.1:{port}"
    opener = build_opener(ProxyHandler({"http": proxy, "https": proxy}))
    request = Request(PROBE_URL, headers={"Accept": "application/json", "User-Agent": "IntegratedDent-egress-check/1"})
    try:
        response = opener.open(request, timeout=timeout)
        body = response.read(8192)
    except HTTPError as error:
        body = error.read(8192)
    except (OSError, TimeoutError, URLError):
        return False
    try:
        payload = json.loads(body.decode("utf-8"))
    except (UnicodeError, json.JSONDecodeError):
        return False
    return isinstance(payload, dict) and payload.get("ok") is False and isinstance(payload.get("error_code"), int)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--env-file", type=Path, required=True)
    parser.add_argument("--xray", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--port", type=int, default=11080)
    parser.add_argument("--probe-port", type=int, default=11081)
    parser.add_argument("--timeout", type=float, default=7.0)
    parser.add_argument("--max-nodes", type=int, default=512)
    args = parser.parse_args()

    values = _read_env(args.env_file)
    try:
        links, fetched_sources, failed_sources = _fetch_subscription_links(values)
    except (ValueError, RuntimeError) as error:
        raise SystemExit(str(error)) from error
    if not links:
        raise SystemExit("Fetched subscriptions contained no nodes")
    if len(links) > args.max_nodes:
        raise SystemExit("Subscription node count exceeded the configured bound")

    results: list[tuple[float, int, str, dict]] = []
    invalid = 0
    with tempfile.TemporaryDirectory(prefix="integrated-dent-xray-probe-") as directory:
        config_path = Path(directory) / "config.json"
        for index, link in enumerate(links):
            try:
                protocol, config = _node_config(link, args.probe_port)
                config_path.write_text(json.dumps(config, ensure_ascii=False), encoding="utf-8")
                checked = subprocess.run(
                    [str(args.xray), "run", "-test", "-c", str(config_path)],
                    stdout=subprocess.DEVNULL,
                    stderr=subprocess.DEVNULL,
                    timeout=5,
                    check=False,
                )
                if checked.returncode != 0:
                    invalid += 1
                    continue
            except (ValueError, OSError, subprocess.SubprocessError):
                invalid += 1
                continue
            process = subprocess.Popen(
                [str(args.xray), "run", "-c", str(config_path)],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )
            started = time.monotonic()
            try:
                reachable = _wait_for_port(args.probe_port, process) and _probe(args.probe_port, args.timeout)
                elapsed = time.monotonic() - started
                if reachable:
                    results.append((elapsed, index, protocol, config))
            finally:
                process.terminate()
                try:
                    process.wait(timeout=2)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=2)

    if not results:
        print(json.dumps({"success": False, "sourcesFetched": fetched_sources, "sourcesFailed": failed_sources, "tested": len(links), "reachable": 0, "invalid": invalid}, separators=(",", ":")))
        return 2
    results.sort(key=lambda item: item[0])
    stable: tuple[float, int, str, dict] | None = None
    with tempfile.TemporaryDirectory(prefix="integrated-dent-xray-confirm-") as directory:
        config_path = Path(directory) / "config.json"
        for candidate in results:
            config_path.write_text(json.dumps(candidate[3], ensure_ascii=False), encoding="utf-8")
            process = subprocess.Popen(
                [str(args.xray), "run", "-c", str(config_path)],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )
            try:
                confirmed = _wait_for_port(args.probe_port, process) and all(
                    _probe(args.probe_port, args.timeout) for _attempt in range(2)
                )
                if confirmed:
                    stable = candidate
                    break
            finally:
                process.terminate()
                try:
                    process.wait(timeout=2)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=2)
    if stable is None:
        print(json.dumps({"success": False, "sourcesFetched": fetched_sources, "sourcesFailed": failed_sources, "tested": len(links), "reachable": len(results), "stable": 0, "invalid": invalid}, separators=(",", ":")))
        return 2
    elapsed, index, protocol, selected_probe = stable
    _selected_protocol, selected = _node_config(links[index], args.port)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    temporary_output = args.output.with_suffix(args.output.suffix + ".new")
    temporary_output.write_text(json.dumps(selected, ensure_ascii=False, indent=2), encoding="utf-8")
    os.chmod(temporary_output, 0o640)
    temporary_output.replace(args.output)
    print(json.dumps({
        "success": True,
        "sourcesFetched": fetched_sources,
        "sourcesFailed": failed_sources,
        "tested": len(links),
        "reachable": len(results),
        "invalid": invalid,
        "selectedIndex": index,
        "selectedProtocol": protocol,
        "latencyMs": round(elapsed * 1000),
    }, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
