from __future__ import annotations

import os
import secrets
import sqlite3
import time
from pathlib import Path


class LocalState:
    """Disposable, bounded transport-local state; never authorization/domain truth."""

    PENDING_DELIVERY_RECEIPT_LIMIT = 500
    PROCESSED_UPDATE_LIMIT = 10_000

    def __init__(self, path: str | Path):
        self.path = Path(path)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        try:
            os.chmod(self.path.parent, 0o700)
        except OSError:
            pass

        self.db = sqlite3.connect(self.path)
        self.db.row_factory = sqlite3.Row
        self.db.executescript(
            """
            PRAGMA journal_mode=WAL;
            PRAGMA synchronous=FULL;
            CREATE TABLE IF NOT EXISTS kv(
              k TEXT PRIMARY KEY,
              v TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS confirmations(
              ref TEXT PRIMARY KEY,
              subject TEXT NOT NULL,
              target_key TEXT NOT NULL,
              idempotency_key TEXT NOT NULL,
              expires_at INTEGER NOT NULL,
              used_at INTEGER
            );
            CREATE TABLE IF NOT EXISTS deployments(
              subject TEXT NOT NULL,
              request_id TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              PRIMARY KEY(subject, request_id)
            );
            CREATE TABLE IF NOT EXISTS sent_deliveries(
              delivery_id TEXT PRIMARY KEY,
              provider_ref TEXT NOT NULL,
              created_at INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS file_cache(
              k TEXT PRIMARY KEY,
              file_id TEXT NOT NULL,
              updated_at INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS pending_delivery_receipts(
              idempotency_key TEXT PRIMARY KEY,
              platform TEXT NOT NULL,
              workspace_id TEXT NOT NULL,
              issuance_id TEXT NOT NULL,
              outcome TEXT NOT NULL,
              provider_ref TEXT,
              error_code TEXT,
              created_at INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS processed_updates(
              platform TEXT NOT NULL,
              event_id TEXT NOT NULL,
              provider_ref TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              PRIMARY KEY(platform, event_id)
            );
            """
        )
        self.db.commit()
        self.prune()
        try:
            os.chmod(self.path, 0o600)
        except OSError:
            pass

    def close(self):
        self.db.close()

    def get_offset(self, platform: str) -> int:
        row = self.db.execute("SELECT v FROM kv WHERE k=?", (f"offset:{platform}",)).fetchone()
        return int(row["v"]) if row else 0

    def set_offset(self, platform: str, value: int):
        self.db.execute(
            "INSERT INTO kv(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v",
            (f"offset:{platform}", str(int(value))),
        )
        self.db.commit()

    def create_confirmation(self, subject: str, target_key: str, ttl: int = 120, now: int | None = None):
        now = int(now or time.time())
        ref = secrets.token_hex(8)
        idem = "bot-update-" + secrets.token_hex(12)
        self.db.execute(
            "INSERT INTO confirmations VALUES(?,?,?,?,?,NULL)",
            (ref, str(subject), target_key, idem, now + ttl),
        )
        self.db.commit()
        return {"ref": ref, "idempotency_key": idem, "expires_at": now + ttl}

    def confirmation(self, ref: str, subject: str, now: int | None = None):
        now = int(now or time.time())
        row = self.db.execute(
            "SELECT * FROM confirmations WHERE ref=? AND subject=?",
            (ref, str(subject)),
        ).fetchone()
        if not row or row["used_at"] is not None or int(row["expires_at"]) < now:
            return None
        return dict(row)

    def cancel_confirmation(self, ref: str, subject: str):
        self.db.execute(
            "UPDATE confirmations SET expires_at=0 WHERE ref=? AND subject=? AND used_at IS NULL",
            (ref, str(subject)),
        )
        self.db.commit()

    def complete_confirmation(self, ref: str, subject: str):
        self.db.execute(
            "UPDATE confirmations SET used_at=? WHERE ref=? AND subject=? AND used_at IS NULL",
            (int(time.time()), ref, str(subject)),
        )
        self.db.commit()

    def record_deployment(self, subject: str, request_id: str):
        self.db.execute(
            "INSERT OR IGNORE INTO deployments VALUES(?,?,?)",
            (str(subject), request_id, int(time.time())),
        )
        self.db.commit()

    def latest_deployment(self, subject: str):
        row = self.db.execute(
            "SELECT request_id FROM deployments WHERE subject=? ORDER BY created_at DESC LIMIT 1",
            (str(subject),),
        ).fetchone()
        return row["request_id"] if row else None

    def remember_delivery(self, delivery_id: str, provider_ref: str):
        self.db.execute(
            "INSERT OR REPLACE INTO sent_deliveries VALUES(?,?,?)",
            (delivery_id, provider_ref, int(time.time())),
        )
        self.db.commit()

    def sent_delivery(self, delivery_id: str):
        row = self.db.execute(
            "SELECT provider_ref FROM sent_deliveries WHERE delivery_id=?",
            (delivery_id,),
        ).fetchone()
        return row["provider_ref"] if row else None

    def cache_file(self, key: str, file_id: str):
        self.db.execute(
            "INSERT OR REPLACE INTO file_cache VALUES(?,?,?)",
            (key, file_id, int(time.time())),
        )
        self.db.commit()

    def cached_file(self, key: str):
        row = self.db.execute("SELECT file_id FROM file_cache WHERE k=?", (key,)).fetchone()
        return row["file_id"] if row else None

    def delivery_receipt_capacity_available(self, idempotency_key: str) -> bool:
        existing = self.db.execute(
            "SELECT 1 FROM pending_delivery_receipts WHERE idempotency_key=?",
            (idempotency_key,),
        ).fetchone()
        if existing:
            return True
        count = self.db.execute("SELECT COUNT(*) AS n FROM pending_delivery_receipts").fetchone()["n"]
        return int(count) < self.PENDING_DELIVERY_RECEIPT_LIMIT

    def record_delivery_outcome(
        self,
        platform: str,
        workspace_id: str,
        issuance_id: str,
        idempotency_key: str,
        outcome: str,
        provider_ref: str | None = None,
        error_code: str | None = None,
        event_id: str | None = None,
    ) -> None:
        if outcome not in {"delivered", "failed"}:
            raise ValueError("unsupported delivery outcome")
        if not self.delivery_receipt_capacity_available(idempotency_key):
            raise RuntimeError("delivery receipt outbox is full")
        now = int(time.time())
        with self.db:
            self.db.execute(
                """
                INSERT INTO pending_delivery_receipts(
                  idempotency_key,platform,workspace_id,issuance_id,outcome,provider_ref,error_code,created_at
                ) VALUES(?,?,?,?,?,?,?,?)
                ON CONFLICT(idempotency_key) DO UPDATE SET
                  platform=excluded.platform,
                  workspace_id=excluded.workspace_id,
                  issuance_id=excluded.issuance_id,
                  outcome=excluded.outcome,
                  provider_ref=excluded.provider_ref,
                  error_code=excluded.error_code
                """,
                (
                    idempotency_key,
                    platform,
                    workspace_id,
                    issuance_id,
                    outcome,
                    provider_ref,
                    error_code,
                    now,
                ),
            )
            if outcome == "delivered" and event_id:
                self.db.execute(
                    """
                    INSERT INTO processed_updates(platform,event_id,provider_ref,created_at)
                    VALUES(?,?,?,?)
                    ON CONFLICT(platform,event_id) DO UPDATE SET
                      provider_ref=excluded.provider_ref,
                      created_at=excluded.created_at
                    """,
                    (platform, str(event_id), provider_ref or "sent", now),
                )
                self._bound_processed_updates(now)

    def pending_delivery_receipt(self, idempotency_key: str):
        row = self.db.execute(
            "SELECT * FROM pending_delivery_receipts WHERE idempotency_key=?",
            (idempotency_key,),
        ).fetchone()
        return dict(row) if row else None

    def pending_delivery_receipts(self, limit: int = 25):
        limit = max(1, min(int(limit), 100))
        rows = self.db.execute(
            "SELECT * FROM pending_delivery_receipts ORDER BY created_at ASC LIMIT ?",
            (limit,),
        ).fetchall()
        return [dict(row) for row in rows]

    def forget_delivery_receipt(self, idempotency_key: str) -> None:
        self.db.execute(
            "DELETE FROM pending_delivery_receipts WHERE idempotency_key=?",
            (idempotency_key,),
        )
        self.db.commit()

    def processed_update(self, platform: str, event_id: str | None):
        if not event_id:
            return None
        row = self.db.execute(
            "SELECT provider_ref FROM processed_updates WHERE platform=? AND event_id=?",
            (platform, str(event_id)),
        ).fetchone()
        return row["provider_ref"] if row else None

    def _bound_processed_updates(self, now: int) -> None:
        self.db.execute("DELETE FROM processed_updates WHERE created_at<?", (now - 7 * 86400,))
        row = self.db.execute("SELECT COUNT(*) AS n FROM processed_updates").fetchone()
        excess = max(0, int(row["n"]) - self.PROCESSED_UPDATE_LIMIT)
        if excess:
            self.db.execute(
                """
                DELETE FROM processed_updates
                WHERE rowid IN (
                  SELECT rowid FROM processed_updates ORDER BY created_at ASC LIMIT ?
                )
                """,
                (excess,),
            )

    def prune(self, max_age: int = 7 * 86400):
        cutoff = int(time.time()) - max_age
        with self.db:
            self.db.execute("DELETE FROM sent_deliveries WHERE created_at<?", (cutoff,))
            self.db.execute("DELETE FROM file_cache WHERE updated_at<?", (cutoff,))
            self.db.execute("DELETE FROM processed_updates WHERE created_at<?", (cutoff,))
