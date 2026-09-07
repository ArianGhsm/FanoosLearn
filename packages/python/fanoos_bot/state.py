from __future__ import annotations

import json
import os
import secrets
import sqlite3
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any


@dataclass(frozen=True, slots=True)
class Confirmation:
    ref: str
    subject: str
    action: str
    payload: dict[str, Any]
    idempotency_key: str
    expires_at: int
    completed_request_id: str | None


class LocalState:
    """Disposable transport-local state. It is never authorization/domain truth."""

    def __init__(self, path: str) -> None:
        self.path = Path(path)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        try:
            os.chmod(self.path.parent, 0o700)
        except OSError:
            pass
        self.db = sqlite3.connect(self.path, timeout=5, isolation_level=None)
        self.db.row_factory = sqlite3.Row
        self.db.execute("PRAGMA journal_mode=WAL")
        self.db.execute("PRAGMA synchronous=FULL")
        self._schema()
        try:
            os.chmod(self.path, 0o600)
        except OSError:
            pass

    def _schema(self) -> None:
        self.db.executescript(
            """
            CREATE TABLE IF NOT EXISTS kv (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at INTEGER NOT NULL);
            CREATE TABLE IF NOT EXISTS confirmations (
              ref TEXT PRIMARY KEY, subject TEXT NOT NULL, action TEXT NOT NULL,
              payload_json TEXT NOT NULL, idempotency_key TEXT NOT NULL,
              expires_at INTEGER NOT NULL, completed_request_id TEXT NULL, created_at INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS deployments (
              subject TEXT NOT NULL, request_id TEXT NOT NULL, idempotency_key TEXT NOT NULL,
              updated_at INTEGER NOT NULL, PRIMARY KEY(subject, request_id)
            );
            CREATE TABLE IF NOT EXISTS sent_deliveries (
              delivery_id TEXT PRIMARY KEY, provider_message_ref TEXT NOT NULL, created_at INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS file_cache (
              cache_key TEXT PRIMARY KEY, provider_file_id TEXT NOT NULL,
              expires_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
            );
            """
        )

    def close(self) -> None:
        self.db.close()

    def get_offset(self) -> int | None:
        row = self.db.execute("SELECT value FROM kv WHERE key='update_offset'").fetchone()
        return None if row is None else int(row[0])

    def set_offset(self, value: int) -> None:
        self.db.execute("INSERT INTO kv(key,value,updated_at) VALUES('update_offset',?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at", (str(value), int(time.time())))

    def create_confirmation(self, subject: str, action: str, payload: dict[str, Any], *, ttl_seconds: int = 120) -> Confirmation:
        now = int(time.time())
        ref = secrets.token_urlsafe(12).replace("-", "_")[:16]
        idem = "bot-update-" + secrets.token_hex(16)
        expires = now + max(30, min(ttl_seconds, 300))
        self.db.execute("INSERT INTO confirmations(ref,subject,action,payload_json,idempotency_key,expires_at,created_at) VALUES(?,?,?,?,?,?,?)", (ref, subject, action, json.dumps(payload, separators=(",", ":")), idem, expires, now))
        self.prune(now)
        return Confirmation(ref, subject, action, payload, idem, expires, None)

    def confirmation(self, ref: str, subject: str, *, now: int | None = None) -> Confirmation | None:
        now = int(time.time()) if now is None else now
        row = self.db.execute("SELECT * FROM confirmations WHERE ref=? AND subject=?", (ref, subject)).fetchone()
        if row is None or int(row["expires_at"]) < now:
            return None
        return Confirmation(ref=str(row["ref"]), subject=str(row["subject"]), action=str(row["action"]), payload=json.loads(str(row["payload_json"])), idempotency_key=str(row["idempotency_key"]), expires_at=int(row["expires_at"]), completed_request_id=None if row["completed_request_id"] is None else str(row["completed_request_id"]))

    def cancel_confirmation(self, ref: str, subject: str) -> None:
        self.db.execute("UPDATE confirmations SET expires_at=0 WHERE ref=? AND subject=?", (ref, subject))

    def complete_confirmation(self, ref: str, subject: str, request_id: str) -> None:
        self.db.execute("UPDATE confirmations SET completed_request_id=? WHERE ref=? AND subject=?", (request_id, ref, subject))
        row = self.db.execute("SELECT idempotency_key FROM confirmations WHERE ref=? AND subject=?", (ref, subject)).fetchone()
        if row is not None:
            self.record_deployment(subject, request_id, str(row[0]))

    def record_deployment(self, subject: str, request_id: str, idempotency_key: str) -> None:
        self.db.execute("INSERT INTO deployments(subject,request_id,idempotency_key,updated_at) VALUES(?,?,?,?) ON CONFLICT(subject,request_id) DO UPDATE SET updated_at=excluded.updated_at", (subject, request_id, idempotency_key, int(time.time())))

    def latest_deployment(self, subject: str) -> str | None:
        row = self.db.execute("SELECT request_id FROM deployments WHERE subject=? ORDER BY updated_at DESC LIMIT 1", (subject,)).fetchone()
        return None if row is None else str(row[0])

    def remember_sent_delivery(self, delivery_id: str, provider_message_ref: str) -> None:
        self.db.execute("INSERT OR REPLACE INTO sent_deliveries(delivery_id,provider_message_ref,created_at) VALUES(?,?,?)", (delivery_id, provider_message_ref, int(time.time())))

    def sent_delivery(self, delivery_id: str) -> str | None:
        row = self.db.execute("SELECT provider_message_ref FROM sent_deliveries WHERE delivery_id=?", (delivery_id,)).fetchone()
        return None if row is None else str(row[0])

    def cache_file(self, cache_key: str, provider_file_id: str, ttl_seconds: int = 86400) -> None:
        now = int(time.time())
        self.db.execute("INSERT INTO file_cache(cache_key,provider_file_id,expires_at,updated_at) VALUES(?,?,?,?) ON CONFLICT(cache_key) DO UPDATE SET provider_file_id=excluded.provider_file_id,expires_at=excluded.expires_at,updated_at=excluded.updated_at", (cache_key, provider_file_id, now + max(60, ttl_seconds), now))

    def cached_file(self, cache_key: str) -> str | None:
        now = int(time.time())
        row = self.db.execute("SELECT provider_file_id,expires_at FROM file_cache WHERE cache_key=?", (cache_key,)).fetchone()
        if row is None or int(row["expires_at"]) < now:
            self.db.execute("DELETE FROM file_cache WHERE cache_key=?", (cache_key,))
            return None
        return str(row["provider_file_id"])

    def prune(self, now: int | None = None) -> None:
        now = int(time.time()) if now is None else now
        self.db.execute("DELETE FROM confirmations WHERE expires_at < ?", (now - 3600,))
        self.db.execute("DELETE FROM sent_deliveries WHERE created_at < ?", (now - 7 * 86400,))
        self.db.execute("DELETE FROM file_cache WHERE expires_at < ?", (now,))
