from __future__ import annotations

import argparse
import json
import os
import sys

from .config import configured_channel_health, load_settings, load_transports
from .core import DeployEvent, Notifier, Spool, VALID_STATUSES


def _notifier() -> tuple[Notifier, object]:
    settings = load_settings()
    return Notifier(Spool(settings.state_dir), load_transports(settings)), settings


def _delivery_summary(payload: dict[str, object]) -> dict[str, object]:
    event = dict(payload.get("event") or {})
    deliveries = dict(payload.get("deliveries") or {})
    return {
        "event_id": event.get("event_id", ""),
        "queued": bool(payload),
        "deliveries": {
            name: {
                "status": dict(value or {}).get("status", "unknown"),
                "attempts": int(dict(value or {}).get("attempts") or 0),
                "last_error": dict(value or {}).get("last_error", ""),
            }
            for name, value in deliveries.items()
        },
    }


def publish(args: argparse.Namespace) -> int:
    notifier, settings = _notifier()
    event = DeployEvent.create(
        service=args.service,
        status=args.status,
        environment=args.environment,
        version=args.version,
        summary=args.summary,
        actor=args.actor,
        event_id=args.event_id,
    )
    payload = notifier.publish(event, settings.channels)
    summary = _delivery_summary(payload)
    print(json.dumps(summary, ensure_ascii=False))
    if args.require_delivery:
        states = [item["status"] for item in summary["deliveries"].values()]
        return 0 if states and all(state == "delivered" for state in states) else 2
    return 0 if payload else 1


def publish_json(_args: argparse.Namespace) -> int:
    raw = sys.stdin.buffer.read(16385)
    if len(raw) > 16384:
        raise ValueError("Deploy event payload is too large")
    value = json.loads(raw.decode("utf-8-sig"))
    if not isinstance(value, dict):
        raise ValueError("Deploy event payload must be an object")
    allowed = {"service", "status", "environment", "version", "summary", "actor", "event_id"}
    if set(value) - allowed:
        raise ValueError("Deploy event payload contains unsupported fields")
    notifier, settings = _notifier()
    event = DeployEvent.create(
        service=str(value.get("service", "")),
        status=str(value.get("status", "")),
        environment=str(value.get("environment", "production")),
        version=str(value.get("version", "")),
        summary=str(value.get("summary", "")),
        actor=str(value.get("actor", "automation")),
        event_id=str(value.get("event_id", "")),
    )
    payload = notifier.publish(event, settings.channels)
    print(json.dumps(_delivery_summary(payload), ensure_ascii=False))
    return 0 if payload else 1


def flush(args: argparse.Namespace) -> int:
    notifier, _settings = _notifier()
    results = notifier.flush(limit=args.limit)
    summaries = [_delivery_summary(item) for item in results]
    print(json.dumps({"processed": len(summaries), "events": summaries}, ensure_ascii=False))
    return 0


def defer(args: argparse.Namespace) -> int:
    settings = load_settings()
    payload = Spool(settings.state_dir).defer(args.event_id, args.reason)
    event_id = dict(payload.get("event") or {}).get("event_id", "")
    print(json.dumps({"deferred": True, "event_id": event_id}))
    return 0


def health(args: argparse.Namespace) -> int:
    settings = load_settings()
    spool = Spool(settings.state_dir)
    channels = configured_channel_health(settings)
    result = {
        "ready": bool(channels) and all(channels.values()),
        "channels": channels,
        "pending_events": len(spool.pending_paths(limit=10000)),
        "state_directory_writable": os.access(spool.root, os.W_OK),
    }
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result["ready"] or not args.require_ready else 2


def parser() -> argparse.ArgumentParser:
    root = argparse.ArgumentParser(prog="deploy-notifier")
    commands = root.add_subparsers(dest="command", required=True)

    publish_parser = commands.add_parser("publish")
    publish_parser.add_argument("--service", required=True)
    publish_parser.add_argument("--status", required=True, choices=sorted(VALID_STATUSES))
    publish_parser.add_argument("--environment", default="production")
    publish_parser.add_argument("--version", default="")
    publish_parser.add_argument("--summary", default="")
    publish_parser.add_argument("--actor", default="automation")
    publish_parser.add_argument("--event-id", default="")
    publish_parser.add_argument("--require-delivery", action="store_true")
    publish_parser.set_defaults(handler=publish)

    publish_json_parser = commands.add_parser("publish-json")
    publish_json_parser.set_defaults(handler=publish_json)

    flush_parser = commands.add_parser("flush")
    flush_parser.add_argument("--limit", type=int, default=100)
    flush_parser.set_defaults(handler=flush)

    defer_parser = commands.add_parser("defer")
    defer_parser.add_argument("--event-id", required=True)
    defer_parser.add_argument("--reason", required=True)
    defer_parser.set_defaults(handler=defer)

    health_parser = commands.add_parser("health")
    health_parser.add_argument("--require-ready", action="store_true")
    health_parser.set_defaults(handler=health)
    return root


def main() -> int:
    args = parser().parse_args()
    try:
        return int(args.handler(args))
    except (OSError, ValueError, UnicodeError, json.JSONDecodeError) as error:
        print(json.dumps({"success": False, "error": error.__class__.__name__}), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
