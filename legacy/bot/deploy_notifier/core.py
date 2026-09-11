from __future__ import annotations

import contextlib
import dataclasses
import datetime as dt
import html
import json
import os
import re
import tempfile
import time
import uuid
from pathlib import Path
from typing import Iterator, Protocol

from dent_bot.persian_datetime import format_jalali_datetime


VALID_STATUSES = {"started", "succeeded", "failed", "rolled_back"}
VALID_SERVICES = {
    "website",
    "archive-worker",
    "telegram-bot",
    "bale-bot",
    "integrated-ops",
}
STATUS_LABELS = {
    "started": "در حال انتشار",
    "succeeded": "با موفقیت منتشر شد",
    "failed": "انتشار ناموفق بود",
    "rolled_back": "نسخه قبلی بازیابی شد",
}
STATUS_ICONS = {"started": "🔵", "succeeded": "🟢", "failed": "🔴", "rolled_back": "🟠"}
STATUS_TITLES = {
    "started": "استقرار آغاز شد",
    "succeeded": "استقرار موفق",
    "failed": "استقرار ناموفق",
    "rolled_back": "بازگشت نسخه",
}
SERVICE_LABELS = {
    "website": "وب‌سایت دندان‌پزشکی ۱۴۰۲",
    "archive-worker": "آرشیو جزوات و منابع",
    "telegram-bot": "دنت‌یار Telegram",
    "bale-bot": "دنت‌یار بله",
    "integrated-ops": "زیرساخت یکپارچه",
}
ENVIRONMENT_LABELS = {"production": "اصلی", "staging": "آزمایشی", "development": "توسعه"}
SAFE_ID_RE = re.compile(r"[^a-zA-Z0-9_.-]+")


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def _bounded_text(value: object, limit: int) -> str:
    text = " ".join(str(value or "").replace("\x00", "").split())
    return text[:limit]


def _safe_id(value: str, fallback: str) -> str:
    cleaned = SAFE_ID_RE.sub("-", value.strip()).strip("-._")
    return (cleaned or fallback)[:120]


def _tehran_time(value: str) -> str:
    return format_jalali_datetime(value) or "زمان نامعتبر"


@dataclasses.dataclass(frozen=True)
class DeployEvent:
    event_id: str
    service: str
    status: str
    environment: str
    version: str
    summary: str
    actor: str
    created_at: str

    @classmethod
    def create(
        cls,
        *,
        service: str,
        status: str,
        environment: str = "production",
        version: str = "",
        summary: str = "",
        actor: str = "automation",
        event_id: str = "",
    ) -> "DeployEvent":
        if status not in VALID_STATUSES:
            raise ValueError(f"Unsupported deploy status: {status}")
        if service not in VALID_SERVICES:
            raise ValueError(f"Unsupported deploy service: {service}")
        generated_id = f"deploy-{uuid.uuid4().hex}"
        return cls(
            event_id=_safe_id(event_id, generated_id),
            service=_safe_id(service, "unknown-service"),
            status=status,
            environment=_safe_id(environment, "production"),
            version=_bounded_text(version, 160),
            summary=_bounded_text(summary, 1200),
            actor=_bounded_text(actor, 120),
            created_at=utc_now(),
        )

    @classmethod
    def from_dict(cls, value: dict[str, object]) -> "DeployEvent":
        status = str(value.get("status", ""))
        if status not in VALID_STATUSES:
            raise ValueError("Stored deploy event has an invalid status")
        service = str(value.get("service", ""))
        if service not in VALID_SERVICES:
            raise ValueError("Stored deploy event has an invalid service")
        return cls(
            event_id=_safe_id(str(value.get("event_id", "")), "invalid-event"),
            service=service,
            status=status,
            environment=_safe_id(str(value.get("environment", "")), "production"),
            version=_bounded_text(value.get("version", ""), 160),
            summary=_bounded_text(value.get("summary", ""), 1200),
            actor=_bounded_text(value.get("actor", ""), 120),
            created_at=_bounded_text(value.get("created_at", ""), 80),
        )

    def to_dict(self) -> dict[str, str]:
        return dataclasses.asdict(self)

    def message(self, *, html_format: bool = False, platform: str = "") -> str:
        icon = STATUS_ICONS[self.status]
        title = STATUS_TITLES[self.status]
        service = SERVICE_LABELS.get(self.service, self.service)
        environment = ENVIRONMENT_LABELS.get(self.environment, self.environment)
        timestamp = _tehran_time(self.created_at)
        summary = _bounded_text(self.summary, 320)
        if not html_format:
            lines = [
                f"{icon} {title}",
                "",
                f"سرویس: {service}",
                f"وضعیت: {STATUS_LABELS[self.status]}",
                f"محیط: {environment}",
            ]
            if self.version:
                lines.append(f"نسخه: {self.version}")
            lines.append(f"زمان: {timestamp}")
            if summary:
                lines.extend(("", summary))
            lines.extend(("", f"شناسه پیگیری: {self.event_id}"))
            return "\n".join(lines)

        safe = lambda value: html.escape(str(value), quote=True)
        lines = [
            f"<b>{icon} {safe(title)}</b>",
            "",
            f"<b>سرویس:</b> {safe(service)}",
            f"<b>وضعیت:</b> {safe(STATUS_LABELS[self.status])}",
            f"<b>محیط:</b> {safe(environment)}",
        ]
        if self.version:
            lines.append(f"<b>نسخه:</b> <code>{safe(self.version)}</code>")
        lines.append(f"<b>زمان:</b> {safe(timestamp)}")
        if summary:
            lines.extend(("", f"<blockquote>{safe(summary)}</blockquote>"))
        lines.extend(("", f"<i>شناسه پیگیری</i>  <code>{safe(self.event_id)}</code>"))
        return "\n".join(lines)


@dataclasses.dataclass(frozen=True)
class DeliveryResult:
    ok: bool
    reason: str = ""


class Transport(Protocol):
    name: str

    def send(self, event: DeployEvent) -> DeliveryResult: ...


class Spool:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.pending = root / "pending"
        self.delivered = root / "delivered"
        self.deferred = root / "deferred"
        self.locks = root / "locks"
        for path in (self.root, self.pending, self.delivered, self.deferred, self.locks):
            path.mkdir(parents=True, exist_ok=True)
            with contextlib.suppress(OSError):
                path.chmod(0o700)

    def _path(self, event_id: str, *, delivered: bool = False) -> Path:
        directory = self.delivered if delivered else self.pending
        return directory / f"{_safe_id(event_id, 'invalid-event')}.json"

    def _atomic_write(self, path: Path, payload: dict[str, object]) -> None:
        encoded = json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
        fd, temporary = tempfile.mkstemp(prefix=f".{path.name}.", dir=str(path.parent))
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as handle:
                # ``fchmod`` is not available on Windows.  Keep it inside the
                # fd context as well, so even an unexpected permission error
                # cannot leak an open descriptor and strand a locked temp file.
                fchmod = getattr(os, "fchmod", None)
                if fchmod is not None:
                    fchmod(handle.fileno(), 0o600)
                handle.write(encoded)
                handle.flush()
                os.fsync(handle.fileno())
            with contextlib.suppress(OSError):
                os.chmod(temporary, 0o600)
            os.replace(temporary, path)
        finally:
            with contextlib.suppress(FileNotFoundError):
                os.unlink(temporary)

    def enqueue(self, event: DeployEvent, channels: tuple[str, ...]) -> dict[str, object]:
        path = self._path(event.event_id)
        delivered_path = self._path(event.event_id, delivered=True)
        if path.exists():
            return self.read(path)
        if delivered_path.exists():
            return self.read(delivered_path)
        payload: dict[str, object] = {
            "schema": 1,
            "event": event.to_dict(),
            "deliveries": {
                channel: {
                    "status": "pending",
                    "attempts": 0,
                    "last_attempt_at": "",
                    "last_error": "",
                    "next_attempt_at": "",
                }
                for channel in channels
            },
        }
        self._atomic_write(path, payload)
        return payload

    def read(self, path: Path) -> dict[str, object]:
        with path.open("r", encoding="utf-8") as handle:
            value = json.load(handle)
        if not isinstance(value, dict):
            raise ValueError("Invalid deploy notification spool record")
        return value

    def pending_paths(self, limit: int = 100) -> list[Path]:
        return sorted(self.pending.glob("*.json"), key=lambda item: item.stat().st_mtime)[:limit]

    @contextlib.contextmanager
    def lock(self, event_id: str, timeout_seconds: float = 5.0) -> Iterator[bool]:
        lock_path = self.locks / f"{_safe_id(event_id, 'invalid-event')}.lock"
        deadline = time.monotonic() + timeout_seconds
        acquired = False
        while time.monotonic() < deadline:
            try:
                descriptor = os.open(lock_path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
                os.close(descriptor)
                acquired = True
                break
            except FileExistsError:
                with contextlib.suppress(OSError):
                    if time.time() - lock_path.stat().st_mtime > 60:
                        lock_path.unlink()
                        continue
                time.sleep(0.05)
        try:
            yield acquired
        finally:
            if acquired:
                with contextlib.suppress(FileNotFoundError):
                    lock_path.unlink()

    def save_pending(self, event_id: str, payload: dict[str, object]) -> None:
        self._atomic_write(self._path(event_id), payload)

    def mark_delivered(self, event_id: str, payload: dict[str, object]) -> None:
        destination = self._path(event_id, delivered=True)
        self._atomic_write(destination, payload)
        with contextlib.suppress(FileNotFoundError):
            self._path(event_id).unlink()

    def defer(self, event_id: str, reason: str) -> dict[str, object]:
        path = self._path(event_id)
        if not path.exists():
            raise FileNotFoundError("Pending deploy event was not found")
        payload = self.read(path)
        payload["deferred_at"] = utc_now()
        payload["deferred_reason"] = _safe_id(reason, "manual-operational-deferral")
        destination = self.deferred / path.name
        self._atomic_write(destination, payload)
        path.unlink()
        return payload


class Notifier:
    def __init__(self, spool: Spool, transports: dict[str, Transport]) -> None:
        self.spool = spool
        self.transports = transports

    def publish(self, event: DeployEvent, channels: tuple[str, ...]) -> dict[str, object]:
        self.spool.enqueue(event, channels)
        return self.flush_event(event.event_id)

    def flush_event(self, event_id: str) -> dict[str, object]:
        path = self.spool._path(event_id)
        if not path.exists():
            delivered_path = self.spool._path(event_id, delivered=True)
            return self.spool.read(delivered_path) if delivered_path.exists() else {}

        with self.spool.lock(event_id) as acquired:
            if not acquired:
                return self.spool.read(path)
            payload = self.spool.read(path)
            event = DeployEvent.from_dict(dict(payload.get("event") or {}))
            deliveries = dict(payload.get("deliveries") or {})
            now = utc_now()
            now_epoch = time.time()

            for channel, raw_state in deliveries.items():
                state = dict(raw_state or {})
                if state.get("status") == "delivered":
                    continue
                next_attempt = str(state.get("next_attempt_at") or "")
                if next_attempt:
                    with contextlib.suppress(ValueError):
                        if dt.datetime.fromisoformat(next_attempt).timestamp() > now_epoch:
                            continue

                attempts = int(state.get("attempts") or 0) + 1
                transport = self.transports.get(channel)
                result = (
                    transport.send(event)
                    if transport is not None
                    else DeliveryResult(False, "channel-not-configured")
                )
                state.update(
                    {
                        "attempts": attempts,
                        "last_attempt_at": now,
                        "last_error": "" if result.ok else _bounded_text(result.reason, 120),
                    }
                )
                if result.ok:
                    state.update({"status": "delivered", "delivered_at": now, "next_attempt_at": ""})
                else:
                    delay = min(3600, max(30, 30 * (2 ** min(attempts - 1, 7))))
                    retry_at = dt.datetime.now(dt.timezone.utc) + dt.timedelta(seconds=delay)
                    state.update({"status": "pending", "next_attempt_at": retry_at.replace(microsecond=0).isoformat()})
                deliveries[channel] = state

            payload["deliveries"] = deliveries
            if deliveries and all(dict(item).get("status") == "delivered" for item in deliveries.values()):
                payload["completed_at"] = utc_now()
                self.spool.mark_delivered(event.event_id, payload)
            else:
                self.spool.save_pending(event.event_id, payload)
            return payload

    def flush(self, limit: int = 100) -> list[dict[str, object]]:
        results = []
        for path in self.spool.pending_paths(limit):
            results.append(self.flush_event(path.stem))
        return results
