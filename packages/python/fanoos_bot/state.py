from __future__ import annotations

import json
import os
import re
import secrets
import sqlite3
import time
from pathlib import Path


_ROUTE_KIND = re.compile(r"^[a-z][a-z0-9_]{0,31}$")


class LocalState:
    """Disposable, bounded transport/presentation state; never domain authority."""

    PENDING_DELIVERY_RECEIPT_LIMIT = 500
    PROCESSED_UPDATE_LIMIT = 10_000
    PRESENTATION_ROUTE_LIMIT = 2_000
    PRESENTATION_ROUTE_MAX_BYTES = 4_096

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
            CREATE TABLE IF NOT EXISTS class_wizards(
              platform TEXT NOT NULL,
              subject TEXT NOT NULL,
              step_index INTEGER NOT NULL,
              answers_json TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              updated_at INTEGER NOT NULL,
              expires_at INTEGER NOT NULL,
              PRIMARY KEY(platform, subject)
            );
            CREATE TABLE IF NOT EXISTS join_wizards(
              platform TEXT NOT NULL,
              subject TEXT NOT NULL,
              step TEXT NOT NULL,
              history_json TEXT NOT NULL,
              answers_json TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              updated_at INTEGER NOT NULL,
              expires_at INTEGER NOT NULL,
              PRIMARY KEY(platform, subject)
            );
            CREATE TABLE IF NOT EXISTS appoint_wizards(
              platform TEXT NOT NULL,
              subject TEXT NOT NULL,
              step_index INTEGER NOT NULL,
              answers_json TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              updated_at INTEGER NOT NULL,
              expires_at INTEGER NOT NULL,
              PRIMARY KEY(platform, subject)
            );
            CREATE TABLE IF NOT EXISTS creq_wizards(
              platform TEXT NOT NULL,
              subject TEXT NOT NULL,
              step_index INTEGER NOT NULL,
              answers_json TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              updated_at INTEGER NOT NULL,
              expires_at INTEGER NOT NULL,
              PRIMARY KEY(platform, subject)
            );
            CREATE TABLE IF NOT EXISTS term_wizards(
              platform TEXT NOT NULL,
              subject TEXT NOT NULL,
              step_index INTEGER NOT NULL,
              answers_json TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              updated_at INTEGER NOT NULL,
              expires_at INTEGER NOT NULL,
              PRIMARY KEY(platform, subject)
            );
            CREATE TABLE IF NOT EXISTS announcement_wizards(
              platform TEXT NOT NULL,
              subject TEXT NOT NULL,
              step_index INTEGER NOT NULL,
              answers_json TEXT NOT NULL,
              created_at INTEGER NOT NULL,
              updated_at INTEGER NOT NULL,
              expires_at INTEGER NOT NULL,
              PRIMARY KEY(platform, subject)
            );
            CREATE TABLE IF NOT EXISTS presentation_routes(
              ref TEXT PRIMARY KEY,
              platform TEXT NOT NULL,
              subject TEXT NOT NULL,
              kind TEXT NOT NULL,
              payload_json TEXT NOT NULL,
              expires_at INTEGER NOT NULL,
              created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS presentation_routes_subject_idx
              ON presentation_routes(platform,subject,created_at);
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

    def create_route(
        self,
        platform: str,
        subject: str,
        kind: str,
        payload: dict,
        *,
        ttl: int = 24 * 3600,
        now: int | None = None,
    ) -> str:
        """Store restart-safe presentation correlation only.

        The returned payload must always be revalidated against the canonical
        backend before it is used for a sensitive read or any mutation.
        """
        if platform not in {"telegram", "bale"}:
            raise ValueError("unsupported route platform")
        if not _ROUTE_KIND.fullmatch(str(kind or "")):
            raise ValueError("invalid route kind")
        encoded = json.dumps(payload, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        if len(encoded.encode("utf-8")) > self.PRESENTATION_ROUTE_MAX_BYTES:
            raise ValueError("presentation route payload is too large")
        now = int(now or time.time())
        ttl = max(60, min(int(ttl), 7 * 86400))
        ref = secrets.token_hex(8)
        with self.db:
            self.db.execute(
                """
                INSERT INTO presentation_routes(ref,platform,subject,kind,payload_json,expires_at,created_at)
                VALUES(?,?,?,?,?,?,?)
                """,
                (ref, platform, str(subject), kind, encoded, now + ttl, now),
            )
            self.db.execute("DELETE FROM presentation_routes WHERE expires_at<?", (now,))
            count = int(self.db.execute("SELECT COUNT(*) AS n FROM presentation_routes").fetchone()["n"])
            excess = max(0, count - self.PRESENTATION_ROUTE_LIMIT)
            if excess:
                self.db.execute(
                    """
                    DELETE FROM presentation_routes
                    WHERE rowid IN (
                      SELECT rowid FROM presentation_routes ORDER BY created_at ASC LIMIT ?
                    )
                    """,
                    (excess,),
                )
        return ref

    def route(
        self,
        ref: str,
        platform: str,
        subject: str,
        *,
        kind: str | None = None,
        now: int | None = None,
    ) -> dict | None:
        now = int(now or time.time())
        row = self.db.execute(
            """
            SELECT kind,payload_json,expires_at FROM presentation_routes
            WHERE ref=? AND platform=? AND subject=?
            """,
            (str(ref), platform, str(subject)),
        ).fetchone()
        if not row or int(row["expires_at"]) < now:
            return None
        if kind is not None and str(row["kind"]) != kind:
            return None
        try:
            payload = json.loads(str(row["payload_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            return None
        if not isinstance(payload, dict):
            return None
        return {"kind": str(row["kind"]), "payload": payload}

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

    CLASS_WIZARD_TTL = 30 * 60

    def start_class_wizard(self, platform: str, subject: str, *, now: int | None = None) -> None:
        now = int(now or time.time())
        self.db.execute(
            """
            INSERT INTO class_wizards(platform,subject,step_index,answers_json,created_at,updated_at,expires_at)
            VALUES(?,?,0,'{}',?,?,?)
            ON CONFLICT(platform,subject) DO UPDATE SET
              step_index=0, answers_json='{}', updated_at=excluded.updated_at, expires_at=excluded.expires_at
            """,
            (platform, str(subject), now, now, now + self.CLASS_WIZARD_TTL),
        )
        self.db.commit()

    def class_wizard(self, platform: str, subject: str, now: int | None = None) -> dict | None:
        now = int(now or time.time())
        row = self.db.execute(
            "SELECT step_index,answers_json,expires_at FROM class_wizards WHERE platform=? AND subject=?",
            (platform, str(subject)),
        ).fetchone()
        if not row or int(row["expires_at"]) < now:
            return None
        try:
            answers = json.loads(str(row["answers_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            answers = {}
        if not isinstance(answers, dict):
            answers = {}
        return {"step_index": int(row["step_index"]), "answers": answers}

    def advance_class_wizard(
        self, platform: str, subject: str, step_index: int, answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        self.db.execute(
            """
            UPDATE class_wizards SET step_index=?, answers_json=?, updated_at=?, expires_at=?
            WHERE platform=? AND subject=?
            """,
            (int(step_index), encoded, now, now + self.CLASS_WIZARD_TTL, platform, str(subject)),
        )
        self.db.commit()

    def cancel_class_wizard(self, platform: str, subject: str) -> None:
        self.db.execute(
            "DELETE FROM class_wizards WHERE platform=? AND subject=?", (platform, str(subject))
        )
        self.db.commit()

    JOIN_WIZARD_TTL = 30 * 60

    def start_join_wizard(self, platform: str, subject: str, first_step: str, *, now: int | None = None) -> None:
        now = int(now or time.time())
        self.db.execute(
            """
            INSERT INTO join_wizards(platform,subject,step,history_json,answers_json,created_at,updated_at,expires_at)
            VALUES(?,?,?,'[]','{}',?,?,?)
            ON CONFLICT(platform,subject) DO UPDATE SET
              step=excluded.step, history_json='[]', answers_json='{}',
              updated_at=excluded.updated_at, expires_at=excluded.expires_at
            """,
            (platform, str(subject), first_step, now, now, now + self.JOIN_WIZARD_TTL),
        )
        self.db.commit()

    def join_wizard(self, platform: str, subject: str, now: int | None = None) -> dict | None:
        now = int(now or time.time())
        row = self.db.execute(
            "SELECT step,history_json,answers_json,expires_at FROM join_wizards WHERE platform=? AND subject=?",
            (platform, str(subject)),
        ).fetchone()
        if not row or int(row["expires_at"]) < now:
            return None
        try:
            answers = json.loads(str(row["answers_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            answers = {}
        if not isinstance(answers, dict):
            answers = {}
        try:
            history = json.loads(str(row["history_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            history = []
        if not isinstance(history, list) or not all(isinstance(item, str) for item in history):
            history = []
        return {"step": str(row["step"]), "history": history, "answers": answers}

    def advance_join_wizard(
        self, platform: str, subject: str, step: str, history: list[str], answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded_answers = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        encoded_history = json.dumps(list(history), ensure_ascii=False, separators=(",", ":"))
        self.db.execute(
            """
            UPDATE join_wizards SET step=?, history_json=?, answers_json=?, updated_at=?, expires_at=?
            WHERE platform=? AND subject=?
            """,
            (step, encoded_history, encoded_answers, now, now + self.JOIN_WIZARD_TTL, platform, str(subject)),
        )
        self.db.commit()

    def cancel_join_wizard(self, platform: str, subject: str) -> None:
        self.db.execute(
            "DELETE FROM join_wizards WHERE platform=? AND subject=?", (platform, str(subject))
        )
        self.db.commit()

    APPOINT_WIZARD_TTL = 30 * 60

    def start_appoint_wizard(self, platform: str, subject: str, *, now: int | None = None) -> None:
        now = int(now or time.time())
        self.db.execute(
            """
            INSERT INTO appoint_wizards(platform,subject,step_index,answers_json,created_at,updated_at,expires_at)
            VALUES(?,?,0,'{}',?,?,?)
            ON CONFLICT(platform,subject) DO UPDATE SET
              step_index=0, answers_json='{}', updated_at=excluded.updated_at, expires_at=excluded.expires_at
            """,
            (platform, str(subject), now, now, now + self.APPOINT_WIZARD_TTL),
        )
        self.db.commit()

    def appoint_wizard(self, platform: str, subject: str, now: int | None = None) -> dict | None:
        now = int(now or time.time())
        row = self.db.execute(
            "SELECT step_index,answers_json,expires_at FROM appoint_wizards WHERE platform=? AND subject=?",
            (platform, str(subject)),
        ).fetchone()
        if not row or int(row["expires_at"]) < now:
            return None
        try:
            answers = json.loads(str(row["answers_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            answers = {}
        if not isinstance(answers, dict):
            answers = {}
        return {"step_index": int(row["step_index"]), "answers": answers}

    def advance_appoint_wizard(
        self, platform: str, subject: str, step_index: int, answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        self.db.execute(
            """
            UPDATE appoint_wizards SET step_index=?, answers_json=?, updated_at=?, expires_at=?
            WHERE platform=? AND subject=?
            """,
            (int(step_index), encoded, now, now + self.APPOINT_WIZARD_TTL, platform, str(subject)),
        )
        self.db.commit()

    def cancel_appoint_wizard(self, platform: str, subject: str) -> None:
        self.db.execute(
            "DELETE FROM appoint_wizards WHERE platform=? AND subject=?", (platform, str(subject))
        )
        self.db.commit()

    CREQ_WIZARD_TTL = 30 * 60

    def start_creq_wizard(
        self, platform: str, subject: str, answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        self.db.execute(
            """
            INSERT INTO creq_wizards(platform,subject,step_index,answers_json,created_at,updated_at,expires_at)
            VALUES(?,?,0,?,?,?,?)
            ON CONFLICT(platform,subject) DO UPDATE SET
              step_index=0, answers_json=excluded.answers_json, updated_at=excluded.updated_at, expires_at=excluded.expires_at
            """,
            (platform, str(subject), encoded, now, now, now + self.CREQ_WIZARD_TTL),
        )
        self.db.commit()

    def creq_wizard(self, platform: str, subject: str, now: int | None = None) -> dict | None:
        now = int(now or time.time())
        row = self.db.execute(
            "SELECT step_index,answers_json,expires_at FROM creq_wizards WHERE platform=? AND subject=?",
            (platform, str(subject)),
        ).fetchone()
        if not row or int(row["expires_at"]) < now:
            return None
        try:
            answers = json.loads(str(row["answers_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            answers = {}
        if not isinstance(answers, dict):
            answers = {}
        return {"step_index": int(row["step_index"]), "answers": answers}

    def advance_creq_wizard(
        self, platform: str, subject: str, step_index: int, answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        self.db.execute(
            """
            UPDATE creq_wizards SET step_index=?, answers_json=?, updated_at=?, expires_at=?
            WHERE platform=? AND subject=?
            """,
            (int(step_index), encoded, now, now + self.CREQ_WIZARD_TTL, platform, str(subject)),
        )
        self.db.commit()

    def cancel_creq_wizard(self, platform: str, subject: str) -> None:
        self.db.execute(
            "DELETE FROM creq_wizards WHERE platform=? AND subject=?", (platform, str(subject))
        )
        self.db.commit()

    TERM_WIZARD_TTL = 30 * 60

    def start_term_wizard(
        self, platform: str, subject: str, answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        self.db.execute(
            """
            INSERT INTO term_wizards(platform,subject,step_index,answers_json,created_at,updated_at,expires_at)
            VALUES(?,?,0,?,?,?,?)
            ON CONFLICT(platform,subject) DO UPDATE SET
              step_index=0, answers_json=excluded.answers_json, updated_at=excluded.updated_at, expires_at=excluded.expires_at
            """,
            (platform, str(subject), encoded, now, now, now + self.TERM_WIZARD_TTL),
        )
        self.db.commit()

    def term_wizard(self, platform: str, subject: str, now: int | None = None) -> dict | None:
        now = int(now or time.time())
        row = self.db.execute(
            "SELECT step_index,answers_json,expires_at FROM term_wizards WHERE platform=? AND subject=?",
            (platform, str(subject)),
        ).fetchone()
        if not row or int(row["expires_at"]) < now:
            return None
        try:
            answers = json.loads(str(row["answers_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            answers = {}
        if not isinstance(answers, dict):
            answers = {}
        return {"step_index": int(row["step_index"]), "answers": answers}

    def advance_term_wizard(
        self, platform: str, subject: str, step_index: int, answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        self.db.execute(
            """
            UPDATE term_wizards SET step_index=?, answers_json=?, updated_at=?, expires_at=?
            WHERE platform=? AND subject=?
            """,
            (int(step_index), encoded, now, now + self.TERM_WIZARD_TTL, platform, str(subject)),
        )
        self.db.commit()

    def cancel_term_wizard(self, platform: str, subject: str) -> None:
        self.db.execute(
            "DELETE FROM term_wizards WHERE platform=? AND subject=?", (platform, str(subject))
        )
        self.db.commit()

    ANNOUNCEMENT_WIZARD_TTL = 30 * 60

    def start_announcement_wizard(self, platform: str, subject: str, *, now: int | None = None) -> None:
        now = int(now or time.time())
        self.db.execute(
            """
            INSERT INTO announcement_wizards(platform,subject,step_index,answers_json,created_at,updated_at,expires_at)
            VALUES(?,?,0,'{}',?,?,?)
            ON CONFLICT(platform,subject) DO UPDATE SET
              step_index=0, answers_json='{}', updated_at=excluded.updated_at, expires_at=excluded.expires_at
            """,
            (platform, str(subject), now, now, now + self.ANNOUNCEMENT_WIZARD_TTL),
        )
        self.db.commit()

    def announcement_wizard(self, platform: str, subject: str, now: int | None = None) -> dict | None:
        now = int(now or time.time())
        row = self.db.execute(
            "SELECT step_index,answers_json,expires_at FROM announcement_wizards WHERE platform=? AND subject=?",
            (platform, str(subject)),
        ).fetchone()
        if not row or int(row["expires_at"]) < now:
            return None
        try:
            answers = json.loads(str(row["answers_json"]))
        except (TypeError, ValueError, json.JSONDecodeError):
            answers = {}
        if not isinstance(answers, dict):
            answers = {}
        return {"step_index": int(row["step_index"]), "answers": answers}

    def advance_announcement_wizard(
        self, platform: str, subject: str, step_index: int, answers: dict, *, now: int | None = None
    ) -> None:
        now = int(now or time.time())
        encoded = json.dumps(answers, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        self.db.execute(
            """
            UPDATE announcement_wizards SET step_index=?, answers_json=?, updated_at=?, expires_at=?
            WHERE platform=? AND subject=?
            """,
            (int(step_index), encoded, now, now + self.ANNOUNCEMENT_WIZARD_TTL, platform, str(subject)),
        )
        self.db.commit()

    def cancel_announcement_wizard(self, platform: str, subject: str) -> None:
        self.db.execute(
            "DELETE FROM announcement_wizards WHERE platform=? AND subject=?", (platform, str(subject))
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
                self._record_processed_update_in_transaction(
                    platform, str(event_id), provider_ref or "sent", now
                )

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

    def record_processed_update(
        self,
        platform: str,
        event_id: str | None,
        provider_ref: str = "processed",
    ) -> None:
        """Persist update consumption even when presentation later fails.

        The event id is transport correlation, not domain authority. Recording
        it after application logic has run prevents a retry from replaying a
        mutation merely because the provider renderer/network failed. Receipt
        bearing deliveries continue to use ``record_delivery_outcome`` which
        records the same key atomically with the canonical receipt outbox.
        """
        if not event_id:
            return
        now = int(time.time())
        with self.db:
            self._record_processed_update_in_transaction(
                platform, str(event_id), str(provider_ref or "processed"), now
            )

    def _record_processed_update_in_transaction(
        self,
        platform: str,
        event_id: str,
        provider_ref: str,
        now: int,
    ) -> None:
        self.db.execute(
            """
            INSERT INTO processed_updates(platform,event_id,provider_ref,created_at)
            VALUES(?,?,?,?)
            ON CONFLICT(platform,event_id) DO UPDATE SET
              provider_ref=excluded.provider_ref,
              created_at=excluded.created_at
            """,
            (platform, event_id, provider_ref, now),
        )
        self._bound_processed_updates(now)

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
        now = int(time.time())
        with self.db:
            self.db.execute("DELETE FROM sent_deliveries WHERE created_at<?", (cutoff,))
            self.db.execute("DELETE FROM file_cache WHERE updated_at<?", (cutoff,))
            self.db.execute("DELETE FROM processed_updates WHERE created_at<?", (cutoff,))
            self.db.execute("DELETE FROM presentation_routes WHERE expires_at<?", (now,))
            self.db.execute("DELETE FROM class_wizards WHERE expires_at<?", (now,))
            self.db.execute("DELETE FROM join_wizards WHERE expires_at<?", (now,))
            self.db.execute("DELETE FROM appoint_wizards WHERE expires_at<?", (now,))
