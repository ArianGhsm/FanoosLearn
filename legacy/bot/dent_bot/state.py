from __future__ import annotations

import sqlite3
import threading
import secrets
import json
import hashlib
import re
from datetime import datetime, timezone
from pathlib import Path

from .payments import effective_status, iso_utc, normalize_audience, normalize_student_number, product_eligibility
from .subscriptions import (
    DEFAULT_ACTIVE_FROM_JALALI,
    DEFAULT_MONTHLY_PRICE_RIALS,
    DEFAULT_TERM,
    SUBSCRIPTION_OFFER_REF_PREFIX,
    billing_period_for,
    billing_period_from_key,
    gregorian_to_jalali,
    jalali_midnight_utc,
    parse_jalali_date,
    policy_is_effective,
    subscription_identity_from_directory,
    tehran_timezone,
    utc_iso,
)


PAYMENT_OFFERS_SCHEMA = """
CREATE TABLE IF NOT EXISTS payment_offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ref TEXT NOT NULL UNIQUE,
    share_token TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    amount_rials INTEGER NOT NULL CHECK(amount_rials >= 10000),
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('draft','scheduled','active','paused','expired','archived')),
    audience_json TEXT NOT NULL DEFAULT '{"mode":"all","cohorts":[],"studentNumbers":[],"listRefs":[]}',
    available_from TEXT NOT NULL DEFAULT '',
    expires_at TEXT NOT NULL DEFAULT '',
    capacity INTEGER NOT NULL DEFAULT 0 CHECK(capacity >= 0),
    max_per_user INTEGER NOT NULL DEFAULT 1 CHECK(max_per_user >= 0),
    fulfillment_json TEXT NOT NULL DEFAULT '{}',
    version INTEGER NOT NULL DEFAULT 1 CHECK(version >= 1),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_payment_offers_status_updated
    ON payment_offers(status, updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_payment_offers_window
    ON payment_offers(status, available_from, expires_at);
CREATE TABLE IF NOT EXISTS payment_audiences (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ref TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    members_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS payment_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id INTEGER NOT NULL,
    actor_platform TEXT NOT NULL,
    action TEXT NOT NULL,
    offer_ref TEXT NOT NULL DEFAULT '',
    before_json TEXT NOT NULL DEFAULT '{}',
    after_json TEXT NOT NULL DEFAULT '{}',
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_payment_audit_offer_created
    ON payment_audit(offer_ref, created_at DESC);
CREATE TABLE IF NOT EXISTS payment_reminder_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ref TEXT NOT NULL UNIQUE,
    offer_ref TEXT NOT NULL,
    platform TEXT NOT NULL,
    recipient_hash TEXT NOT NULL,
    recipient_count INTEGER NOT NULL,
    status TEXT NOT NULL CHECK(status IN ('preview','sending','sent','failed','canceled')),
    sent_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS term_access_policies (
    term INTEGER PRIMARY KEY CHECK(term BETWEEN 1 AND 12),
    mode TEXT NOT NULL DEFAULT 'open' CHECK(mode IN ('open','subscription')),
    enabled INTEGER NOT NULL DEFAULT 0 CHECK(enabled IN (0,1)),
    monthly_price_rials INTEGER NOT NULL CHECK(monthly_price_rials >= 10000),
    active_from_jalali TEXT NOT NULL,
    renewal_reminders_enabled INTEGER NOT NULL DEFAULT 1 CHECK(renewal_reminders_enabled IN (0,1)),
    offer_ref TEXT NOT NULL UNIQUE,
    version INTEGER NOT NULL DEFAULT 1 CHECK(version >= 1),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS term_access_entitlements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_key TEXT NOT NULL,
    student_number TEXT NOT NULL DEFAULT '',
    display_name TEXT NOT NULL DEFAULT '',
    term INTEGER NOT NULL CHECK(term BETWEEN 1 AND 12),
    access_type TEXT NOT NULL CHECK(access_type IN ('paid_subscription','complimentary')),
    billing_period TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','expired','revoked')),
    granted_at TEXT NOT NULL,
    expires_at TEXT NOT NULL DEFAULT '',
    granted_by INTEGER NOT NULL DEFAULT 0,
    granted_by_platform TEXT NOT NULL DEFAULT 'system',
    payment_order_token TEXT NOT NULL DEFAULT '',
    payment_order_ref TEXT NOT NULL DEFAULT '',
    revoked_at TEXT NOT NULL DEFAULT '',
    revoked_by INTEGER NOT NULL DEFAULT 0,
    revoked_by_platform TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subject_key, term, access_type, billing_period)
);
CREATE INDEX IF NOT EXISTS idx_term_entitlement_access
    ON term_access_entitlements(subject_key, term, status, access_type, billing_period);
CREATE INDEX IF NOT EXISTS idx_term_entitlement_period
    ON term_access_entitlements(term, billing_period, access_type, status);
CREATE TABLE IF NOT EXISTS term_subscription_checkouts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id TEXT NOT NULL UNIQUE,
    platform TEXT NOT NULL,
    platform_user_id INTEGER NOT NULL,
    subject_key TEXT NOT NULL,
    student_number TEXT NOT NULL DEFAULT '',
    display_name TEXT NOT NULL DEFAULT '',
    term INTEGER NOT NULL CHECK(term BETWEEN 1 AND 12),
    billing_period TEXT NOT NULL,
    amount_rials INTEGER NOT NULL CHECK(amount_rials >= 10000),
    offer_ref TEXT NOT NULL,
    offer_version INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'created' CHECK(status IN ('created','bound','activated','failed')),
    order_token TEXT NOT NULL DEFAULT '',
    verified_delivery_id TEXT NOT NULL DEFAULT '',
    verified_at TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(platform, platform_user_id, term, billing_period),
    UNIQUE(order_token),
    UNIQUE(verified_delivery_id)
);
CREATE INDEX IF NOT EXISTS idx_term_checkout_subject
    ON term_subscription_checkouts(subject_key, term, billing_period, status);
CREATE TABLE IF NOT EXISTS term_access_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    term INTEGER NOT NULL DEFAULT 0,
    subject_key TEXT NOT NULL DEFAULT '',
    entitlement_id INTEGER NOT NULL DEFAULT 0,
    actor_user_id INTEGER NOT NULL DEFAULT 0,
    actor_platform TEXT NOT NULL DEFAULT 'system',
    before_json TEXT NOT NULL DEFAULT '{}',
    after_json TEXT NOT NULL DEFAULT '{}',
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_term_access_audit_created
    ON term_access_audit(term, created_at DESC);
CREATE TABLE IF NOT EXISTS term_subscription_renewal_notices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    term INTEGER NOT NULL CHECK(term BETWEEN 1 AND 12),
    billing_period TEXT NOT NULL,
    platform TEXT NOT NULL,
    platform_user_id INTEGER NOT NULL,
    subject_key TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'sending' CHECK(status IN ('sending','sent','failed')),
    attempts INTEGER NOT NULL DEFAULT 1,
    last_error TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(term, billing_period, platform, platform_user_id)
);
"""

PAYMENT_OFFER_COLUMNS = (
    "id,ref,share_token,title,description,amount_rials,status,audience_json,available_from,"
    "expires_at,capacity,max_per_user,fulfillment_json,version,created_at,updated_at"
)


def _migrate_payment_schema(connection: sqlite3.Connection) -> None:
    columns = {str(row[1]) for row in connection.execute("PRAGMA table_info(payment_offers)")}
    if not columns:
        connection.executescript(PAYMENT_OFFERS_SCHEMA)
        connection.commit()
        return
    if "share_token" in columns and "audience_json" in columns and "version" in columns:
        connection.executescript(PAYMENT_OFFERS_SCHEMA)
        connection.commit()
        return
    connection.execute("BEGIN IMMEDIATE")
    try:
        columns = {str(row[1]) for row in connection.execute("PRAGMA table_info(payment_offers)")}
        if "share_token" in columns and "audience_json" in columns and "version" in columns:
            connection.commit()
            connection.executescript(PAYMENT_OFFERS_SCHEMA)
            connection.commit()
            return
        rows = connection.execute(
            "SELECT id,ref,title,description,amount_rials,status,created_at,updated_at FROM payment_offers"
        ).fetchall()
        connection.execute("DROP INDEX IF EXISTS idx_payment_offers_status_updated")
        connection.execute("ALTER TABLE payment_offers RENAME TO payment_offers_legacy")
        connection.execute(
            "CREATE TABLE payment_offers ("
            "id INTEGER PRIMARY KEY AUTOINCREMENT,ref TEXT NOT NULL UNIQUE,share_token TEXT NOT NULL UNIQUE,"
            "title TEXT NOT NULL,description TEXT NOT NULL DEFAULT '',amount_rials INTEGER NOT NULL CHECK(amount_rials>=10000),"
            "status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('draft','scheduled','active','paused','expired','archived')),"
            "audience_json TEXT NOT NULL,available_from TEXT NOT NULL DEFAULT '',expires_at TEXT NOT NULL DEFAULT '',"
            "capacity INTEGER NOT NULL DEFAULT 0 CHECK(capacity>=0),max_per_user INTEGER NOT NULL DEFAULT 1 CHECK(max_per_user>=0),"
            "fulfillment_json TEXT NOT NULL DEFAULT '{}',version INTEGER NOT NULL DEFAULT 1 CHECK(version>=1),"
            "created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)"
        )
        for row in rows:
            legacy_status = str(row[5])
            status = {"active": "active", "inactive": "paused", "deleted": "archived"}.get(legacy_status, "draft")
            connection.execute(
                "INSERT INTO payment_offers(id,ref,share_token,title,description,amount_rials,status,audience_json,created_at,updated_at) "
                "VALUES(?,?,?,?,?,?,?,?,?,?)",
                (int(row[0]), str(row[1]), secrets.token_urlsafe(16), str(row[2]), str(row[3]), int(row[4]), status,
                 json.dumps(normalize_audience({"mode": "all"}), separators=(",", ":")), str(row[6]), str(row[7])),
            )
        connection.execute("DROP TABLE payment_offers_legacy")
        connection.commit()
    except Exception:
        connection.rollback()
        raise
    connection.executescript(PAYMENT_OFFERS_SCHEMA)
    connection.commit()


class BotState:
    def __init__(self, path: Path, *, payment_offers_path: Path | None = None) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        self._lock = threading.RLock()
        self.connection = sqlite3.connect(path, timeout=10, check_same_thread=False)
        self.connection.execute("PRAGMA journal_mode=WAL")
        self.connection.execute("PRAGMA busy_timeout=10000")
        self.connection.executescript(
            """
            CREATE TABLE IF NOT EXISTS runtime_state (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS bot_users (
                telegram_user_id INTEGER PRIMARY KEY,
                first_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_section TEXT NOT NULL DEFAULT 'home'
            );
            CREATE TABLE IF NOT EXISTS notification_refs (
                ref TEXT PRIMARY KEY,
                notification_id TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_delivery_receipts (
                delivery_id TEXT PRIMARY KEY,
                sent_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS navid_captcha_challenge (
                singleton INTEGER PRIMARY KEY CHECK(singleton = 1),
                daily_date TEXT NOT NULL,
                message_id INTEGER NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS integration_captcha_challenges (
                user_id INTEGER PRIMARY KEY,
                challenge_ref TEXT NOT NULL,
                job_ref TEXT NOT NULL,
                connector TEXT NOT NULL,
                message_id INTEGER NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS bot_dialogs (
                user_id INTEGER PRIMARY KEY,
                kind TEXT NOT NULL,
                step TEXT NOT NULL,
                payload_json TEXT NOT NULL DEFAULT '{}',
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS reply_keyboard_state (
                user_id INTEGER PRIMARY KEY,
                active INTEGER NOT NULL DEFAULT 0 CHECK(active IN (0,1)),
                cleanup_version TEXT NOT NULL DEFAULT '',
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS protected_media_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_chat_id INTEGER NOT NULL,
                source_message_id INTEGER NOT NULL,
                course_code TEXT NOT NULL,
                course_name TEXT NOT NULL,
                course_tag TEXT NOT NULL,
                term INTEGER NOT NULL CHECK(term BETWEEN 1 AND 12),
                session_no INTEGER NOT NULL CHECK(session_no BETWEEN 1 AND 40),
                content_kind TEXT NOT NULL CHECK(content_kind IN ('voice','power','booklet','reference')),
                telegram_method TEXT NOT NULL CHECK(telegram_method IN ('sendDocument','sendAudio','sendVoice')),
                file_id TEXT NOT NULL DEFAULT '',
                file_unique_id TEXT NOT NULL DEFAULT '',
                file_name TEXT NOT NULL DEFAULT '',
                mime_type TEXT NOT NULL DEFAULT '',
                caption TEXT NOT NULL DEFAULT '',
                active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(source_chat_id, source_message_id, content_kind)
            );
            CREATE INDEX IF NOT EXISTS idx_protected_media_lookup
                ON protected_media_sources(course_code, term, session_no, content_kind, active);
            CREATE TABLE IF NOT EXISTS personalized_media_cache (
                user_id INTEGER NOT NULL,
                source_id INTEGER NOT NULL,
                file_id TEXT NOT NULL,
                file_unique_id TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(user_id, source_id)
            );
            CREATE TABLE IF NOT EXISTS booklet_issuances (
                issuance_id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                source_id INTEGER NOT NULL,
                document_id TEXT NOT NULL,
                trace_code TEXT NOT NULL UNIQUE,
                fingerprint_hash TEXT NOT NULL,
                watermark_version TEXT NOT NULL,
                source_hash TEXT NOT NULL,
                status TEXT NOT NULL CHECK(status IN ('queued','processing','sent','failed')),
                issued_at TEXT NOT NULL DEFAULT '',
                telegram_file_id TEXT NOT NULL DEFAULT '',
                telegram_file_unique_id TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, document_id, source_hash, watermark_version)
            );
            CREATE INDEX IF NOT EXISTS idx_booklet_issuance_lookup
                ON booklet_issuances(user_id, source_id, document_id, watermark_version, status);
            CREATE INDEX IF NOT EXISTS idx_booklet_issuance_forensic
                ON booklet_issuances(document_id, source_hash, watermark_version);
            CREATE TABLE IF NOT EXISTS booklet_request_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                document_id TEXT NOT NULL,
                requested_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_booklet_request_rate
                ON booklet_request_events(user_id, requested_at);
            CREATE INDEX IF NOT EXISTS idx_booklet_request_document
                ON booklet_request_events(user_id, document_id, requested_at);
            CREATE TABLE IF NOT EXISTS protected_media_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                source_id INTEGER NOT NULL,
                status TEXT NOT NULL CHECK(status IN ('sent','failed','denied')),
                message_id INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            """
        )
        self.connection.commit()
        _migrate_payment_schema(self.connection)
        self._payment_connection_owned = payment_offers_path is not None and payment_offers_path != path
        if self._payment_connection_owned:
            assert payment_offers_path is not None
            payment_offers_path.parent.mkdir(parents=True, exist_ok=True)
            self.payment_connection = sqlite3.connect(payment_offers_path, timeout=10, check_same_thread=False)
            self.payment_connection.execute("PRAGMA journal_mode=WAL")
            self.payment_connection.execute("PRAGMA busy_timeout=10000")
            _migrate_payment_schema(self.payment_connection)
        else:
            self.payment_connection = self.connection
        self._ensure_default_term_access_policy()

    @staticmethod
    def _offer_payload(row: tuple | None) -> dict | None:
        if row is None:
            return None
        try:
            audience = normalize_audience(json.loads(str(row[7]) or "{}"))
        except (ValueError, TypeError, json.JSONDecodeError):
            audience = normalize_audience({"mode": "all"})
        try:
            fulfillment = json.loads(str(row[12]) or "{}")
        except json.JSONDecodeError:
            fulfillment = {}
        if not isinstance(fulfillment, dict):
            fulfillment = {}
        value = {
            "id": int(row[0]),
            "ref": str(row[1]),
            "shareToken": str(row[2]),
            "title": str(row[3]),
            "description": str(row[4]),
            "amountRials": int(row[5]),
            "status": str(row[6]),
            "audience": audience,
            "availableFrom": str(row[8]),
            "expiresAt": str(row[9]),
            "capacity": int(row[10]),
            "maxPurchasesPerUser": int(row[11]),
            "fulfillment": fulfillment,
            "version": int(row[13]),
            "createdAt": str(row[14]),
            "updatedAt": str(row[15]),
        }
        value["effectiveStatus"] = effective_status(value)
        return value

    def _payment_audit(
        self,
        action: str,
        *,
        actor_user_id: int,
        actor_platform: str,
        offer_ref: str = "",
        before: dict | None = None,
        after: dict | None = None,
        note: str = "",
    ) -> None:
        self.payment_connection.execute(
            "INSERT INTO payment_audit(actor_user_id,actor_platform,action,offer_ref,before_json,after_json,note) "
            "VALUES(?,?,?,?,?,?,?)",
            (
                int(actor_user_id), str(actor_platform)[:16], str(action)[:64], str(offer_ref)[:80],
                json.dumps(before or {}, ensure_ascii=False, separators=(",", ":")),
                json.dumps(after or {}, ensure_ascii=False, separators=(",", ":")),
                " ".join(str(note).split())[:240],
            ),
        )

    @staticmethod
    def _term_policy_payload(row: tuple | None) -> dict | None:
        if row is None:
            return None
        return {
            "term": int(row[0]),
            "mode": str(row[1]),
            "enabled": bool(row[2]),
            "monthlyPriceRials": int(row[3]),
            "activeFromJalali": str(row[4]),
            "renewalRemindersEnabled": bool(row[5]),
            "offerRef": str(row[6]),
            "version": int(row[7]),
            "createdAt": str(row[8]),
            "updatedAt": str(row[9]),
        }

    @staticmethod
    def _term_entitlement_payload(row: tuple | None) -> dict | None:
        if row is None:
            return None
        return {
            "id": int(row[0]), "subjectKey": str(row[1]), "studentNumber": str(row[2]),
            "displayName": str(row[3]), "term": int(row[4]), "accessType": str(row[5]),
            "billingPeriod": str(row[6]), "status": str(row[7]), "grantedAt": str(row[8]),
            "expiresAt": str(row[9]), "grantedBy": int(row[10]), "grantedByPlatform": str(row[11]),
            "paymentOrderToken": str(row[12]), "paymentOrderRef": str(row[13]),
            "revokedAt": str(row[14]), "revokedBy": int(row[15]),
            "revokedByPlatform": str(row[16]), "note": str(row[17]),
            "createdAt": str(row[18]), "updatedAt": str(row[19]),
        }

    @staticmethod
    def _term_checkout_payload(row: tuple | None) -> dict | None:
        if row is None:
            return None
        return {
            "id": int(row[0]), "requestId": str(row[1]), "platform": str(row[2]),
            "platformUserId": int(row[3]), "subjectKey": str(row[4]), "studentNumber": str(row[5]),
            "displayName": str(row[6]), "term": int(row[7]), "billingPeriod": str(row[8]),
            "amountRials": int(row[9]), "offerRef": str(row[10]), "offerVersion": int(row[11]),
            "status": str(row[12]), "orderToken": str(row[13]),
            "verifiedDeliveryId": str(row[14]), "verifiedAt": str(row[15]),
            "createdAt": str(row[16]), "updatedAt": str(row[17]),
        }

    def _term_access_audit(
        self,
        action: str,
        *,
        term: int = 0,
        subject_key: str = "",
        entitlement_id: int = 0,
        actor_user_id: int = 0,
        actor_platform: str = "system",
        before: dict | None = None,
        after: dict | None = None,
        note: str = "",
    ) -> None:
        self.payment_connection.execute(
            "INSERT INTO term_access_audit(action,term,subject_key,entitlement_id,actor_user_id,actor_platform,"
            "before_json,after_json,note) VALUES(?,?,?,?,?,?,?,?,?)",
            (
                str(action)[:64], max(0, int(term)), str(subject_key)[:96], max(0, int(entitlement_id)),
                max(0, int(actor_user_id)), str(actor_platform)[:16],
                json.dumps(before or {}, ensure_ascii=False, separators=(",", ":")),
                json.dumps(after or {}, ensure_ascii=False, separators=(",", ":")),
                " ".join(str(note).split())[:240],
            ),
        )

    def _ensure_default_term_access_policy(self) -> None:
        offer_ref = f"{SUBSCRIPTION_OFFER_REF_PREFIX}{DEFAULT_TERM}"
        start_year, start_month, start_day = parse_jalali_date(DEFAULT_ACTIVE_FROM_JALALI)
        available_from = utc_iso(jalali_midnight_utc(start_year, start_month, start_day))
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                inserted = self.payment_connection.execute(
                    "INSERT OR IGNORE INTO term_access_policies(term,mode,enabled,monthly_price_rials,"
                    "active_from_jalali,renewal_reminders_enabled,offer_ref) VALUES(?,?,?,?,?,?,?)",
                    (
                        DEFAULT_TERM, "subscription", 1, DEFAULT_MONTHLY_PRICE_RIALS,
                        DEFAULT_ACTIVE_FROM_JALALI, 1, offer_ref,
                    ),
                ).rowcount
                self.payment_connection.execute(
                    "INSERT OR IGNORE INTO payment_offers(ref,share_token,title,description,amount_rials,status,"
                    "audience_json,available_from,expires_at,capacity,max_per_user,fulfillment_json) "
                    "VALUES(?,?,?,?,?,'scheduled',?,?, '',0,0,?)",
                    (
                        offer_ref, secrets.token_urlsafe(16), "اشتراک جزوات ترم هفتم",
                        "دسترسی ماهانه بر پایهٔ ماه شمسی؛ خرید وسط ماه تا پایان همان ماه معتبر است.",
                        DEFAULT_MONTHLY_PRICE_RIALS,
                        json.dumps(normalize_audience({"mode": "all"}), separators=(",", ":")),
                        available_from,
                        json.dumps({"kind": "term_subscription", "term": DEFAULT_TERM}, separators=(",", ":")),
                    ),
                )
                if inserted:
                    self._term_access_audit(
                        "policy-created", term=DEFAULT_TERM,
                        after={"mode": "subscription", "enabled": True, "priceRials": DEFAULT_MONTHLY_PRICE_RIALS,
                               "activeFromJalali": DEFAULT_ACTIVE_FROM_JALALI},
                    )
                self.payment_connection.commit()
            except Exception:
                self.payment_connection.rollback()
                raise

    def term_access_policy(self, term: int) -> dict | None:
        with self._lock:
            row = self.payment_connection.execute(
                "SELECT term,mode,enabled,monthly_price_rials,active_from_jalali,renewal_reminders_enabled,"
                "offer_ref,version,created_at,updated_at FROM term_access_policies WHERE term=?",
                (int(term),),
            ).fetchone()
        return self._term_policy_payload(row)

    def term_access_policies(self) -> list[dict]:
        with self._lock:
            rows = self.payment_connection.execute(
                "SELECT term,mode,enabled,monthly_price_rials,active_from_jalali,renewal_reminders_enabled,"
                "offer_ref,version,created_at,updated_at FROM term_access_policies ORDER BY term"
            ).fetchall()
        return [dict(value) for row in rows if (value := self._term_policy_payload(row)) is not None]

    def update_term_access_policy(
        self,
        term: int,
        changes: dict,
        *,
        actor_user_id: int,
        actor_platform: str,
        note: str = "",
    ) -> dict:
        term = int(term)
        if not 1 <= term <= 12:
            raise ValueError("Invalid term")
        current = self.term_access_policy(term)
        if current is None:
            offer_ref = f"{SUBSCRIPTION_OFFER_REF_PREFIX}{term}"
            with self._lock:
                self.payment_connection.execute(
                    "INSERT INTO term_access_policies(term,mode,enabled,monthly_price_rials,active_from_jalali,"
                    "renewal_reminders_enabled,offer_ref) VALUES(?,?,?,?,?,?,?)",
                    (term, "open", 0, DEFAULT_MONTHLY_PRICE_RIALS, DEFAULT_ACTIVE_FROM_JALALI, 1, offer_ref),
                )
                self.payment_connection.commit()
            current = self.term_access_policy(term)
        assert current is not None
        updated = dict(current)
        if "mode" in changes:
            mode = str(changes["mode"])
            if mode not in {"open", "subscription"}:
                raise ValueError("Invalid access mode")
            updated["mode"] = mode
        if "enabled" in changes:
            updated["enabled"] = bool(changes["enabled"])
        if "monthlyPriceRials" in changes:
            amount = int(changes["monthlyPriceRials"])
            if not 10000 <= amount <= 100_000_000_000:
                raise ValueError("Invalid subscription amount")
            updated["monthlyPriceRials"] = amount
        if "activeFromJalali" in changes:
            value = str(changes["activeFromJalali"])
            parse_jalali_date(value)
            updated["activeFromJalali"] = value
        if "renewalRemindersEnabled" in changes:
            updated["renewalRemindersEnabled"] = bool(changes["renewalRemindersEnabled"])
        year, month, day = parse_jalali_date(updated["activeFromJalali"])
        available_from = utc_iso(jalali_midnight_utc(year, month, day))
        offer_ref = str(updated["offerRef"])
        offer_status = "scheduled" if updated["enabled"] and updated["mode"] == "subscription" else "paused"
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                self.payment_connection.execute(
                    "UPDATE term_access_policies SET mode=?,enabled=?,monthly_price_rials=?,active_from_jalali=?,"
                    "renewal_reminders_enabled=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE term=?",
                    (
                        updated["mode"], int(updated["enabled"]), int(updated["monthlyPriceRials"]),
                        updated["activeFromJalali"], int(updated["renewalRemindersEnabled"]), term,
                    ),
                )
                self.payment_connection.execute(
                    "INSERT INTO payment_offers(ref,share_token,title,description,amount_rials,status,audience_json,"
                    "available_from,expires_at,capacity,max_per_user,fulfillment_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) "
                    "ON CONFLICT(ref) DO UPDATE SET amount_rials=excluded.amount_rials,status=excluded.status,"
                    "available_from=excluded.available_from,fulfillment_json=excluded.fulfillment_json,"
                    "version=payment_offers.version+1,updated_at=CURRENT_TIMESTAMP",
                    (
                        offer_ref, secrets.token_urlsafe(16), f"اشتراک جزوات ترم {term}",
                        "دسترسی ماهانه بر پایهٔ ماه شمسی؛ خرید وسط ماه تا پایان همان ماه معتبر است.",
                        int(updated["monthlyPriceRials"]), offer_status,
                        json.dumps(normalize_audience({"mode": "all"}), separators=(",", ":")),
                        available_from, "", 0, 0,
                        json.dumps({"kind": "term_subscription", "term": term}, separators=(",", ":")),
                    ),
                )
                self._term_access_audit(
                    "policy-updated", term=term, actor_user_id=actor_user_id, actor_platform=actor_platform,
                    before=current, after=updated, note=note,
                )
                self.payment_connection.commit()
            except Exception:
                self.payment_connection.rollback()
                raise
        result = self.term_access_policy(term)
        if result is None:
            raise RuntimeError("Term access policy update was not persisted")
        return result

    def begin_term_subscription_checkout(
        self,
        *,
        request_id: str,
        platform: str,
        platform_user_id: int,
        subject_key: str,
        student_number: str,
        display_name: str,
        term: int,
        billing_period: str,
        amount_rials: int,
        offer_ref: str,
        offer_version: int,
    ) -> dict:
        period = billing_period_from_key(billing_period)
        if period.term != int(term) or not subject_key or int(platform_user_id) <= 0:
            raise ValueError("Invalid subscription checkout")
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                self.payment_connection.execute(
                    "INSERT OR IGNORE INTO term_subscription_checkouts(request_id,platform,platform_user_id,subject_key,"
                    "student_number,display_name,term,billing_period,amount_rials,offer_ref,offer_version) "
                    "VALUES(?,?,?,?,?,?,?,?,?,?,?)",
                    (
                        str(request_id)[:80], str(platform)[:16], int(platform_user_id), str(subject_key)[:96],
                        normalize_student_number(student_number), " ".join(str(display_name).split())[:160], int(term),
                        period.key, int(amount_rials), str(offer_ref)[:80], max(1, int(offer_version)),
                    ),
                )
                row = self.payment_connection.execute(
                    "SELECT id,request_id,platform,platform_user_id,subject_key,student_number,display_name,term,"
                    "billing_period,amount_rials,offer_ref,offer_version,status,order_token,verified_delivery_id,"
                    "verified_at,created_at,updated_at FROM term_subscription_checkouts "
                    "WHERE platform=? AND platform_user_id=? AND term=? AND billing_period=?",
                    (str(platform)[:16], int(platform_user_id), int(term), period.key),
                ).fetchone()
                checkout = self._term_checkout_payload(row)
                if checkout is None or checkout["subjectKey"] != str(subject_key):
                    raise ValueError("Subscription checkout identity conflict")
                self.payment_connection.commit()
                return checkout
            except Exception:
                self.payment_connection.rollback()
                raise

    def bind_term_subscription_order(self, request_id: str, order_token: str) -> dict:
        token = str(order_token).strip()
        if not re.fullmatch(r"[A-Za-z0-9_-]{20,46}", token):
            raise ValueError("Invalid subscription order token")
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                row = self.payment_connection.execute(
                    "SELECT order_token FROM term_subscription_checkouts WHERE request_id=?", (str(request_id),)
                ).fetchone()
                if row is None or (str(row[0]) and str(row[0]) != token):
                    raise ValueError("Subscription order binding conflict")
                self.payment_connection.execute(
                    "UPDATE term_subscription_checkouts SET order_token=?,status=CASE WHEN status='created' THEN 'bound' "
                    "ELSE status END,updated_at=CURRENT_TIMESTAMP WHERE request_id=?",
                    (token, str(request_id)),
                )
                row = self.payment_connection.execute(
                    "SELECT id,request_id,platform,platform_user_id,subject_key,student_number,display_name,term,"
                    "billing_period,amount_rials,offer_ref,offer_version,status,order_token,verified_delivery_id,"
                    "verified_at,created_at,updated_at FROM term_subscription_checkouts WHERE request_id=?",
                    (str(request_id),),
                ).fetchone()
                self.payment_connection.commit()
            except Exception:
                self.payment_connection.rollback()
                raise
        result = self._term_checkout_payload(row)
        if result is None:
            raise RuntimeError("Subscription checkout binding was not persisted")
        return result

    def term_subscription_checkout_by_order(self, order_token: str) -> dict | None:
        with self._lock:
            row = self.payment_connection.execute(
                "SELECT id,request_id,platform,platform_user_id,subject_key,student_number,display_name,term,"
                "billing_period,amount_rials,offer_ref,offer_version,status,order_token,verified_delivery_id,"
                "verified_at,created_at,updated_at FROM term_subscription_checkouts WHERE order_token=?",
                (str(order_token),),
            ).fetchone()
        return self._term_checkout_payload(row)

    def activate_paid_term_subscription(
        self,
        *,
        order_token: str,
        delivery_id: str,
        platform: str,
        platform_user_id: int,
        amount_rials: int,
        verified_at: str,
        payment_order_ref: str = "",
    ) -> dict:
        verified = iso_utc(verified_at, allow_empty=False)
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                row = self.payment_connection.execute(
                    "SELECT id,request_id,platform,platform_user_id,subject_key,student_number,display_name,term,"
                    "billing_period,amount_rials,offer_ref,offer_version,status,order_token,verified_delivery_id,"
                    "verified_at,created_at,updated_at FROM term_subscription_checkouts WHERE order_token=?",
                    (str(order_token),),
                ).fetchone()
                checkout = self._term_checkout_payload(row)
                if checkout is None:
                    raise ValueError("Subscription checkout not found")
                if (
                    checkout["platform"] != str(platform)
                    or checkout["platformUserId"] != int(platform_user_id)
                    or checkout["amountRials"] != int(amount_rials)
                    or (
                        checkout["verifiedDeliveryId"]
                        and checkout["verifiedDeliveryId"] != str(delivery_id)
                        and checkout["status"] != "activated"
                    )
                ):
                    raise ValueError("Subscription payment snapshot mismatch")
                period = billing_period_from_key(checkout["billingPeriod"])
                entitlement_status = "active" if period.expires_at > datetime.now(timezone.utc) else "expired"
                cursor = self.payment_connection.execute(
                    "INSERT OR IGNORE INTO term_access_entitlements(subject_key,student_number,display_name,term,"
                    "access_type,billing_period,status,granted_at,expires_at,granted_by,granted_by_platform,"
                    "payment_order_token,payment_order_ref,note) VALUES(?,?,?,?,? ,?,?,?,?,0,'system',?,?,?)",
                    (
                        checkout["subjectKey"], checkout["studentNumber"], checkout["displayName"], checkout["term"],
                        "paid_subscription", checkout["billingPeriod"], entitlement_status, verified,
                        utc_iso(period.expires_at), str(order_token), str(payment_order_ref)[:80],
                        "provider-verified bot commerce payment",
                    ),
                )
                entitlement = self.payment_connection.execute(
                    "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                    "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                    "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE subject_key=? "
                    "AND term=? AND access_type='paid_subscription' AND billing_period=?",
                    (checkout["subjectKey"], checkout["term"], checkout["billingPeriod"]),
                ).fetchone()
                payload = self._term_entitlement_payload(entitlement)
                if payload is None:
                    raise RuntimeError("Paid subscription entitlement was not persisted")
                self.payment_connection.execute(
                    "UPDATE term_subscription_checkouts SET status='activated',"
                    "verified_delivery_id=CASE WHEN verified_delivery_id='' THEN ? ELSE verified_delivery_id END,verified_at=?,"
                    "updated_at=CURRENT_TIMESTAMP WHERE id=?",
                    (str(delivery_id)[:100], verified, checkout["id"]),
                )
                if cursor.rowcount:
                    self._term_access_audit(
                        "subscription-payment", term=checkout["term"], subject_key=checkout["subjectKey"],
                        entitlement_id=payload["id"], after=payload,
                        note=f"period:{checkout['billingPeriod']}",
                    )
                    self._term_access_audit(
                        "entitlement-activated", term=checkout["term"], subject_key=checkout["subjectKey"],
                        entitlement_id=payload["id"], after=payload,
                    )
                self.payment_connection.commit()
                return payload
            except Exception:
                self.payment_connection.rollback()
                raise

    def expire_term_entitlements(self, *, now: datetime | None = None) -> int:
        current = now or datetime.now(timezone.utc)
        threshold = utc_iso(current)
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                rows = self.payment_connection.execute(
                    "SELECT id,subject_key,term FROM term_access_entitlements WHERE status='active' AND expires_at!='' "
                    "AND expires_at<=?", (threshold,),
                ).fetchall()
                for entitlement_id, subject_key, term in rows:
                    self.payment_connection.execute(
                        "UPDATE term_access_entitlements SET status='expired',updated_at=CURRENT_TIMESTAMP WHERE id=?",
                        (int(entitlement_id),),
                    )
                    self._term_access_audit(
                        "entitlement-expired", term=int(term), subject_key=str(subject_key),
                        entitlement_id=int(entitlement_id),
                    )
                self.payment_connection.commit()
                return len(rows)
            except Exception:
                self.payment_connection.rollback()
                raise

    def term_access_decision(self, subject_key: str, term: int, *, now: datetime | None = None) -> dict:
        policy = self.term_access_policy(term)
        current = now or datetime.now(timezone.utc)
        if policy is None or not policy_is_effective(policy, current):
            return {"allowed": True, "reason": "open-policy", "accessPath": "open", "policy": policy}
        if not subject_key:
            return {"allowed": False, "reason": "canonical-identity-required", "accessPath": "none", "policy": policy}
        self.expire_term_entitlements(now=current)
        period = billing_period_for(term, current)
        with self._lock:
            paid = self.payment_connection.execute(
                "SELECT 1 FROM term_access_entitlements WHERE subject_key=? AND term=? AND access_type='paid_subscription' "
                "AND billing_period=? AND status='active' AND expires_at>? LIMIT 1",
                (str(subject_key), int(term), period.key, utc_iso(current)),
            ).fetchone() is not None
            complimentary = self.payment_connection.execute(
                "SELECT 1 FROM term_access_entitlements WHERE subject_key=? AND term=? AND access_type='complimentary' "
                "AND status='active' AND (expires_at='' OR expires_at>?) LIMIT 1",
                (str(subject_key), int(term), utc_iso(current)),
            ).fetchone() is not None
            latest_payment_row = self.payment_connection.execute(
                "SELECT billing_period,granted_at,expires_at,payment_order_ref FROM term_access_entitlements "
                "WHERE subject_key=? AND term=? AND access_type='paid_subscription' "
                "ORDER BY granted_at DESC,id DESC LIMIT 1",
                (str(subject_key), int(term)),
            ).fetchone()
        latest_payment = None
        if latest_payment_row is not None:
            latest_payment = {
                "billingPeriod": str(latest_payment_row[0]),
                "paidAt": str(latest_payment_row[1]),
                "expiresAt": str(latest_payment_row[2]),
                "paymentOrderRef": str(latest_payment_row[3]),
            }
        access_path = "both" if paid and complimentary else "paid" if paid else "complimentary" if complimentary else "none"
        return {
            "allowed": bool(paid or complimentary), "reason": "entitled" if paid or complimentary else "subscription-required",
            "accessPath": access_path, "paidValid": paid, "complimentaryValid": complimentary,
            "billingPeriod": period.key, "period": period, "policy": policy,
            "latestPayment": latest_payment,
        }

    def term_subject_entitlement_status(
        self, *, student_number: str, term: int, now: datetime | None = None
    ) -> dict:
        identity = subscription_identity_from_directory({"studentNumber": student_number})
        if identity is None:
            return {"paidValid": False, "complimentaryValid": False, "accessPath": "none"}
        current = now or datetime.now(timezone.utc)
        self.expire_term_entitlements(now=current)
        period = billing_period_for(term, current)
        with self._lock:
            paid = self.payment_connection.execute(
                "SELECT 1 FROM term_access_entitlements WHERE subject_key=? AND term=? AND access_type='paid_subscription' "
                "AND billing_period=? AND status='active' AND expires_at>? LIMIT 1",
                (identity.subject_key, int(term), period.key, utc_iso(current)),
            ).fetchone() is not None
            complimentary = self.payment_connection.execute(
                "SELECT 1 FROM term_access_entitlements WHERE subject_key=? AND term=? AND access_type='complimentary' "
                "AND status='active' AND (expires_at='' OR expires_at>?) LIMIT 1",
                (identity.subject_key, int(term), utc_iso(current)),
            ).fetchone() is not None
        return {
            "paidValid": paid, "complimentaryValid": complimentary,
            "accessPath": "both" if paid and complimentary else "paid" if paid else "complimentary" if complimentary else "none",
            "billingPeriod": period.key,
        }

    def grant_complimentary_term_access(
        self,
        *,
        term: int,
        student_number: str,
        display_name: str,
        actor_user_id: int,
        actor_platform: str,
        note: str = "",
        expires_at: str = "",
    ) -> dict:
        identity = subscription_identity_from_directory({"studentNumber": student_number, "name": display_name})
        if identity is None:
            raise ValueError("Canonical student number is required")
        expiry = iso_utc(expires_at) if expires_at else ""
        granted = utc_iso(datetime.now(timezone.utc))
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                previous = self.payment_connection.execute(
                    "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                    "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                    "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE subject_key=? "
                    "AND term=? AND access_type='complimentary' AND billing_period=''",
                    (identity.subject_key, int(term)),
                ).fetchone()
                before = self._term_entitlement_payload(previous)
                self.payment_connection.execute(
                    "INSERT INTO term_access_entitlements(subject_key,student_number,display_name,term,access_type,"
                    "billing_period,status,granted_at,expires_at,granted_by,granted_by_platform,note) "
                    "VALUES(?,?,?,?, 'complimentary','', 'active',?,?,?,?,?) ON CONFLICT(subject_key,term,access_type,billing_period) "
                    "DO UPDATE SET student_number=excluded.student_number,display_name=excluded.display_name,status='active',"
                    "granted_at=excluded.granted_at,expires_at=excluded.expires_at,granted_by=excluded.granted_by,"
                    "granted_by_platform=excluded.granted_by_platform,revoked_at='',revoked_by=0,revoked_by_platform='',"
                    "note=excluded.note,updated_at=CURRENT_TIMESTAMP",
                    (
                        identity.subject_key, identity.student_number, identity.display_name, int(term), granted, expiry,
                        int(actor_user_id), str(actor_platform)[:16], " ".join(str(note).split())[:240],
                    ),
                )
                row = self.payment_connection.execute(
                    "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                    "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                    "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE subject_key=? "
                    "AND term=? AND access_type='complimentary' AND billing_period=''",
                    (identity.subject_key, int(term)),
                ).fetchone()
                after = self._term_entitlement_payload(row)
                assert after is not None
                self._term_access_audit(
                    "complimentary-grant", term=int(term), subject_key=identity.subject_key,
                    entitlement_id=after["id"], actor_user_id=actor_user_id, actor_platform=actor_platform,
                    before=before, after=after, note=note,
                )
                self.payment_connection.commit()
                return after
            except Exception:
                self.payment_connection.rollback()
                raise

    def complimentary_term_access(self, term: int, *, include_revoked: bool = False) -> list[dict]:
        clause = "" if include_revoked else " AND status='active'"
        with self._lock:
            rows = self.payment_connection.execute(
                "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE term=? "
                "AND access_type='complimentary'" + clause + " ORDER BY updated_at DESC",
                (int(term),),
            ).fetchall()
        return [dict(value) for row in rows if (value := self._term_entitlement_payload(row)) is not None]

    def complimentary_term_access_by_id(self, entitlement_id: int) -> dict | None:
        with self._lock:
            row = self.payment_connection.execute(
                "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE id=? "
                "AND access_type='complimentary' AND status='active'",
                (int(entitlement_id),),
            ).fetchone()
        return self._term_entitlement_payload(row)

    def revoke_complimentary_term_access(
        self,
        entitlement_id: int,
        *,
        actor_user_id: int,
        actor_platform: str,
        note: str = "",
    ) -> dict | None:
        with self._lock:
            try:
                self.payment_connection.execute("BEGIN IMMEDIATE")
                row = self.payment_connection.execute(
                    "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                    "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                    "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE id=? "
                    "AND access_type='complimentary'", (int(entitlement_id),),
                ).fetchone()
                before = self._term_entitlement_payload(row)
                if before is None:
                    self.payment_connection.rollback()
                    return None
                self.payment_connection.execute(
                    "UPDATE term_access_entitlements SET status='revoked',revoked_at=CURRENT_TIMESTAMP,revoked_by=?,"
                    "revoked_by_platform=?,note=CASE WHEN ?!='' THEN ? ELSE note END,updated_at=CURRENT_TIMESTAMP WHERE id=?",
                    (int(actor_user_id), str(actor_platform)[:16], str(note), " ".join(str(note).split())[:240], int(entitlement_id)),
                )
                row = self.payment_connection.execute(
                    "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                    "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                    "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE id=?",
                    (int(entitlement_id),),
                ).fetchone()
                after = self._term_entitlement_payload(row)
                assert after is not None
                self._term_access_audit(
                    "complimentary-revoke", term=after["term"], subject_key=after["subjectKey"],
                    entitlement_id=after["id"], actor_user_id=actor_user_id, actor_platform=actor_platform,
                    before=before, after=after, note=note,
                )
                self.payment_connection.commit()
                return after
            except Exception:
                self.payment_connection.rollback()
                raise

    def term_subscription_report(self, term: int, *, billing_period: str = "", now: datetime | None = None) -> dict:
        current = now or datetime.now(timezone.utc)
        period = billing_period_from_key(billing_period) if billing_period else billing_period_for(term, current)
        previous_key = period.previous_key
        with self._lock:
            paid_subjects = {str(row[0]) for row in self.payment_connection.execute(
                "SELECT subject_key FROM term_access_entitlements WHERE term=? AND access_type='paid_subscription' "
                "AND billing_period=? AND status IN ('active','expired')", (int(term), period.key),
            ).fetchall()}
            previous_subjects = {str(row[0]) for row in self.payment_connection.execute(
                "SELECT subject_key FROM term_access_entitlements WHERE term=? AND access_type='paid_subscription' "
                "AND billing_period=?", (int(term), previous_key),
            ).fetchall()}
            complimentary_subjects = {str(row[0]) for row in self.payment_connection.execute(
                "SELECT subject_key FROM term_access_entitlements WHERE term=? AND access_type='complimentary' "
                "AND status='active' AND (expires_at='' OR expires_at>?)", (int(term), utc_iso(current)),
            ).fetchall()}
            revenue = int(self.payment_connection.execute(
                "SELECT COALESCE(SUM(amount_rials),0) FROM term_subscription_checkouts WHERE term=? "
                "AND billing_period=? AND status='activated'", (int(term), period.key),
            ).fetchone()[0])
            revoked = int(self.payment_connection.execute(
                "SELECT COUNT(*) FROM term_access_entitlements WHERE term=? AND access_type='complimentary' AND status='revoked'",
                (int(term),),
            ).fetchone()[0])
            first_time = 0
            for subject in paid_subjects:
                earlier = self.payment_connection.execute(
                    "SELECT 1 FROM term_access_entitlements WHERE subject_key=? AND term=? AND access_type='paid_subscription' "
                    "AND billing_period<? LIMIT 1", (subject, int(term), period.key),
                ).fetchone()
                first_time += int(earlier is None)
        renewed = len(paid_subjects & previous_subjects)
        return {
            "term": int(term), "billingPeriod": period.key, "period": period,
            "paidSubscribers": len(paid_subjects), "complimentary": len(complimentary_subjects),
            "totalActiveAccess": len(paid_subjects | complimentary_subjects), "revenueRials": revenue,
            "unpaidPreviousSubscribers": len(previous_subjects - paid_subjects - complimentary_subjects),
            "renewalRate": (renewed / len(previous_subjects)) if previous_subjects else 0.0,
            "newSubscribers": first_time, "revokedComplimentary": revoked,
        }

    def claim_term_renewal_notices(
        self, *, platform: str, now: datetime | None = None, limit: int = 20
    ) -> list[dict]:
        current = now or datetime.now(timezone.utc)
        local_jalali = billing_period_for(DEFAULT_TERM, current)
        # Renewal reminders are intentionally bounded to the first three Solar
        # Hijri days instead of running throughout the month.
        _year, _month, local_day = gregorian_to_jalali(current.astimezone(tehran_timezone()).date())
        if not 1 <= local_day <= 3:
            return []
        claimed: list[dict] = []
        for policy in self.term_access_policies():
            if len(claimed) >= max(1, int(limit)) or not policy_is_effective(policy, current):
                continue
            if not policy.get("renewalRemindersEnabled"):
                continue
            period = billing_period_for(int(policy["term"]), current)
            with self._lock:
                rows = self.payment_connection.execute(
                    "SELECT DISTINCT c.platform_user_id,c.subject_key,c.display_name FROM term_subscription_checkouts c "
                    "WHERE c.platform=? AND c.term=? AND c.billing_period=? AND c.status='activated' "
                    "AND NOT EXISTS(SELECT 1 FROM term_access_entitlements e WHERE e.subject_key=c.subject_key AND e.term=c.term "
                    "AND e.status='active' AND ((e.access_type='paid_subscription' AND e.billing_period=?) "
                    "OR (e.access_type='complimentary' AND (e.expires_at='' OR e.expires_at>?)))) LIMIT ?",
                    (
                        str(platform), int(policy["term"]), period.previous_key, period.key, utc_iso(current),
                        max(1, int(limit)) - len(claimed),
                    ),
                ).fetchall()
                for platform_user_id, subject_key, display_name in rows:
                    cursor = self.payment_connection.execute(
                        "INSERT OR IGNORE INTO term_subscription_renewal_notices(term,billing_period,platform,"
                        "platform_user_id,subject_key) VALUES(?,?,?,?,?)",
                        (int(policy["term"]), period.key, str(platform), int(platform_user_id), str(subject_key)),
                    )
                    if not cursor.rowcount:
                        cursor = self.payment_connection.execute(
                            "UPDATE term_subscription_renewal_notices SET status='sending',attempts=attempts+1,"
                            "last_error='',updated_at=CURRENT_TIMESTAMP WHERE term=? AND billing_period=? AND platform=? "
                            "AND platform_user_id=? AND status='failed' AND attempts<5 "
                            "AND updated_at<datetime('now','-30 minutes')",
                            (int(policy["term"]), period.key, str(platform), int(platform_user_id)),
                        )
                    if cursor.rowcount:
                        claimed.append({
                            "term": int(policy["term"]), "billingPeriod": period.key, "period": period,
                            "platformUserId": int(platform_user_id), "subjectKey": str(subject_key),
                            "displayName": str(display_name),
                        })
                self.payment_connection.commit()
        return claimed

    def finish_term_renewal_notice(
        self, *, term: int, billing_period: str, platform: str, platform_user_id: int,
        sent: bool, error: str = "",
    ) -> None:
        with self._lock:
            self.payment_connection.execute(
                "UPDATE term_subscription_renewal_notices SET status=?,last_error=?,updated_at=CURRENT_TIMESTAMP "
                "WHERE term=? AND billing_period=? AND platform=? AND platform_user_id=?",
                (
                    "sent" if sent else "failed", "" if sent else str(error)[:80], int(term),
                    str(billing_period), str(platform), int(platform_user_id),
                ),
            )
            self.payment_connection.commit()

    def term_access_export_rows(self, term: int, *, now: datetime | None = None) -> list[dict]:
        current = now or datetime.now(timezone.utc)
        period = billing_period_for(term, current)
        with self._lock:
            rows = self.payment_connection.execute(
                "SELECT id,subject_key,student_number,display_name,term,access_type,billing_period,status,granted_at,"
                "expires_at,granted_by,granted_by_platform,payment_order_token,payment_order_ref,revoked_at,revoked_by,"
                "revoked_by_platform,note,created_at,updated_at FROM term_access_entitlements WHERE term=? AND "
                "((access_type='paid_subscription' AND billing_period=?) OR access_type='complimentary') "
                "ORDER BY access_type,display_name,student_number",
                (int(term), period.key),
            ).fetchall()
        return [dict(value) for row in rows if (value := self._term_entitlement_payload(row)) is not None]

    def create_payment_offer(
        self,
        title: str,
        amount_rials: int,
        description: str = "",
        *,
        status: str = "active",
        audience: dict | None = None,
        available_from: str = "",
        expires_at: str = "",
        capacity: int = 0,
        max_per_user: int = 1,
        fulfillment: dict | None = None,
        actor_user_id: int = 0,
        actor_platform: str = "system",
    ) -> dict:
        clean_title = " ".join(title.split())[:160]
        clean_description = " ".join(description.split())[:360]
        if not clean_title or amount_rials < 10000 or amount_rials > 100000000000:
            raise ValueError("Invalid payment offer")
        normalized_status = {"inactive": "paused", "deleted": "archived"}.get(status, status)
        if normalized_status not in {"draft", "scheduled", "active", "paused", "expired", "archived"}:
            raise ValueError("Invalid payment offer status")
        normalized_audience = normalize_audience(audience or {"mode": "all"})
        available = iso_utc(available_from)
        expires = iso_utc(expires_at)
        if available and expires and available >= expires:
            raise ValueError("Invalid product availability window")
        capacity = max(0, min(1_000_000, int(capacity)))
        max_per_user = max(0, min(10_000, int(max_per_user)))
        offer_ref = secrets.token_urlsafe(18)
        share_token = secrets.token_urlsafe(16)
        with self._lock:
            cursor = self.payment_connection.execute(
                "INSERT INTO payment_offers(ref,share_token,title,description,amount_rials,status,audience_json,"
                "available_from,expires_at,capacity,max_per_user,fulfillment_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
                (
                    offer_ref, share_token, clean_title, clean_description, int(amount_rials), normalized_status,
                    json.dumps(normalized_audience, separators=(",", ":")), available, expires, capacity,
                    max_per_user, json.dumps(fulfillment or {}, ensure_ascii=False, separators=(",", ":")),
                ),
            )
            row = self.payment_connection.execute(
                f"SELECT {PAYMENT_OFFER_COLUMNS} FROM payment_offers WHERE id=?",
                (cursor.lastrowid,),
            ).fetchone()
            item = dict(self._offer_payload(row) or {})
            self._payment_audit(
                "product-created", actor_user_id=actor_user_id, actor_platform=actor_platform,
                offer_ref=offer_ref, after=item,
            )
            self.payment_connection.commit()
        return item

    def payment_offers(self, *, include_inactive: bool = False) -> list[dict]:
        query = (
            f"SELECT {PAYMENT_OFFER_COLUMNS} FROM payment_offers "
            + ("WHERE status!='archived' " if include_inactive else "WHERE status IN ('active','scheduled','expired') ")
            + "ORDER BY id DESC LIMIT 500"
        )
        with self._lock:
            rows = self.payment_connection.execute(query).fetchall()
        items = [dict(value) for row in rows if (value := self._offer_payload(row)) is not None]
        return items if include_inactive else [item for item in items if item["effectiveStatus"] == "active"]

    def payment_offer(self, offer_ref: str, *, require_active: bool = True) -> dict | None:
        query = (
            f"SELECT {PAYMENT_OFFER_COLUMNS} FROM payment_offers WHERE ref=?"
        )
        query += " AND status!='archived'"
        with self._lock:
            row = self.payment_connection.execute(query, (offer_ref,)).fetchone()
        item = self._offer_payload(row)
        return item if item is not None and (not require_active or item["effectiveStatus"] == "active") else None

    def payment_offer_by_share_token(self, token: str) -> dict | None:
        if len(str(token)) < 16:
            return None
        with self._lock:
            row = self.payment_connection.execute(
                f"SELECT {PAYMENT_OFFER_COLUMNS} FROM payment_offers WHERE share_token=? AND status!='archived'",
                (str(token),),
            ).fetchone()
        return self._offer_payload(row)

    def saved_audience_members(self, refs: list[str]) -> list[str]:
        cleaned = [str(value) for value in refs if str(value)]
        if not cleaned:
            return []
        placeholders = ",".join("?" for _ in cleaned)
        with self._lock:
            rows = self.payment_connection.execute(
                f"SELECT members_json FROM payment_audiences WHERE ref IN ({placeholders})", cleaned
            ).fetchall()
        members: list[str] = []
        for row in rows:
            try:
                values = json.loads(str(row[0]))
            except json.JSONDecodeError:
                values = []
            for value in values if isinstance(values, list) else []:
                student = normalize_student_number(value)
                if student and student not in members:
                    members.append(student)
        return members

    def eligible_payment_offers(self, identity: dict, *, via_link: bool = False) -> list[dict]:
        result: list[dict] = []
        for item in self.payment_offers(include_inactive=True):
            if str(dict(item.get("fulfillment") or {}).get("kind") or "") == "term_subscription":
                continue
            audience = dict(item.get("audience") or {})
            members = self.saved_audience_members(list(audience.get("listRefs") or []))
            if product_eligibility(item, identity, via_link=via_link, saved_members=members).allowed:
                result.append(item)
        return result

    def payment_offer_for_user(self, offer_ref: str, identity: dict, *, via_link: bool = False) -> dict | None:
        item = self.payment_offer(offer_ref, require_active=False)
        if item is None or str(dict(item.get("fulfillment") or {}).get("kind") or "") == "term_subscription":
            return None
        audience = dict(item.get("audience") or {})
        members = self.saved_audience_members(list(audience.get("listRefs") or []))
        decision = product_eligibility(item, identity, via_link=via_link, saved_members=members)
        return item if decision.allowed else None

    def set_payment_offer_status(
        self,
        offer_ref: str,
        status: str,
        *,
        actor_user_id: int = 0,
        actor_platform: str = "system",
    ) -> dict | None:
        status = {"inactive": "paused", "deleted": "archived"}.get(status, status)
        if status not in {"draft", "scheduled", "active", "paused", "expired", "archived"}:
            raise ValueError("Invalid payment offer status")
        with self._lock:
            before = self.payment_offer(offer_ref, require_active=False)
            if before is None or str(dict(before.get("fulfillment") or {}).get("kind") or "") == "term_subscription":
                return None
            self.payment_connection.execute(
                "UPDATE payment_offers SET status=?,version=version+1,updated_at=CURRENT_TIMESTAMP "
                "WHERE ref=? AND status!='archived'",
                (status, offer_ref),
            )
            after = self.payment_offer(offer_ref, require_active=False)
            self._payment_audit(
                "product-status-changed", actor_user_id=actor_user_id, actor_platform=actor_platform,
                offer_ref=offer_ref, before=before, after=after,
            )
            self.payment_connection.commit()
        return after

    def update_payment_offer(
        self,
        offer_ref: str,
        changes: dict,
        *,
        actor_user_id: int,
        actor_platform: str,
    ) -> dict | None:
        allowed = {
            "title": "title", "description": "description", "amountRials": "amount_rials",
            "audience": "audience_json", "availableFrom": "available_from", "expiresAt": "expires_at",
            "capacity": "capacity", "maxPurchasesPerUser": "max_per_user", "fulfillment": "fulfillment_json",
        }
        values: dict[str, object] = {}
        for key, column in allowed.items():
            if key not in changes:
                continue
            value = changes[key]
            if key == "title":
                value = " ".join(str(value).split())[:160]
                if len(str(value)) < 3:
                    raise ValueError("Invalid title")
            elif key == "description":
                value = " ".join(str(value).split())[:360]
            elif key == "amountRials":
                value = int(value)
                if int(value) < 10000 or int(value) > 100000000000:
                    raise ValueError("Invalid amount")
            elif key == "audience":
                value = json.dumps(normalize_audience(value), separators=(",", ":"))
            elif key in {"availableFrom", "expiresAt"}:
                value = iso_utc(str(value or ""))
            elif key in {"capacity", "maxPurchasesPerUser"}:
                value = max(0, min(1_000_000, int(value)))
            elif key == "fulfillment":
                value = json.dumps(value if isinstance(value, dict) else {}, ensure_ascii=False, separators=(",", ":"))
            values[column] = value
        if not values:
            return self.payment_offer(offer_ref, require_active=False)
        with self._lock:
            before = self.payment_offer(offer_ref, require_active=False)
            if before is None or str(dict(before.get("fulfillment") or {}).get("kind") or "") == "term_subscription":
                return None
            candidate_available = str(values.get("available_from", before.get("availableFrom") or ""))
            candidate_expires = str(values.get("expires_at", before.get("expiresAt") or ""))
            if candidate_available and candidate_expires and candidate_available >= candidate_expires:
                raise ValueError("Invalid availability window")
            assignments = ",".join(f"{column}=?" for column in values)
            self.payment_connection.execute(
                f"UPDATE payment_offers SET {assignments},version=version+1,updated_at=CURRENT_TIMESTAMP "
                "WHERE ref=? AND status!='archived'",
                (*values.values(), offer_ref),
            )
            after = self.payment_offer(offer_ref, require_active=False)
            self._payment_audit(
                "product-updated", actor_user_id=actor_user_id, actor_platform=actor_platform,
                offer_ref=offer_ref, before=before, after=after,
            )
            self.payment_connection.commit()
        return after

    def duplicate_payment_offer(self, offer_ref: str, *, actor_user_id: int, actor_platform: str) -> dict | None:
        source = self.payment_offer(offer_ref, require_active=False)
        if source is None or str(dict(source.get("fulfillment") or {}).get("kind") or "") == "term_subscription":
            return None
        return self.create_payment_offer(
            f"کپی {source['title']}"[:160], int(source["amountRials"]), str(source.get("description") or ""),
            status="draft", audience=dict(source.get("audience") or {}),
            available_from="", expires_at="", capacity=int(source.get("capacity") or 0),
            max_per_user=int(source.get("maxPurchasesPerUser") or 1),
            fulfillment=dict(source.get("fulfillment") or {}), actor_user_id=actor_user_id,
            actor_platform=actor_platform,
        )

    def rotate_payment_share_token(self, offer_ref: str, *, actor_user_id: int, actor_platform: str) -> dict | None:
        with self._lock:
            before = self.payment_offer(offer_ref, require_active=False)
            if before is None or str(dict(before.get("fulfillment") or {}).get("kind") or "") == "term_subscription":
                return None
            self.payment_connection.execute(
                "UPDATE payment_offers SET share_token=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE ref=?",
                (secrets.token_urlsafe(16), offer_ref),
            )
            after = self.payment_offer(offer_ref, require_active=False)
            self._payment_audit(
                "share-token-rotated", actor_user_id=actor_user_id, actor_platform=actor_platform,
                offer_ref=offer_ref, before={"version": before["version"]}, after={"version": after["version"] if after else 0},
            )
            self.payment_connection.commit()
        return after

    def payment_product_summary(self) -> dict:
        items = self.payment_offers(include_inactive=True)
        return {
            "total": len(items),
            "active": sum(1 for item in items if item["effectiveStatus"] == "active"),
            "scheduled": sum(1 for item in items if item["effectiveStatus"] == "scheduled"),
            "paused": sum(1 for item in items if item["status"] == "paused"),
            "draft": sum(1 for item in items if item["status"] == "draft"),
        }

    def saved_payment_audiences(self) -> list[dict]:
        with self._lock:
            rows = self.payment_connection.execute(
                "SELECT ref,name,members_json,created_at,updated_at FROM payment_audiences ORDER BY id DESC"
            ).fetchall()
        result = []
        for row in rows:
            try:
                members = json.loads(str(row[2]))
            except json.JSONDecodeError:
                members = []
            normalized = [value for item in members if (value := normalize_student_number(item))] if isinstance(members, list) else []
            result.append({"ref": str(row[0]), "name": str(row[1]), "studentNumbers": normalized, "createdAt": str(row[3]), "updatedAt": str(row[4])})
        return result

    def save_payment_audience(
        self,
        name: str,
        student_numbers: list[str],
        *,
        actor_user_id: int,
        actor_platform: str,
        audience_ref: str = "",
    ) -> dict:
        clean_name = " ".join(str(name).split())[:80]
        members = list(dict.fromkeys(value for item in student_numbers if (value := normalize_student_number(item))))[:500]
        if len(clean_name) < 2 or not members:
            raise ValueError("Invalid saved audience")
        ref = audience_ref or secrets.token_urlsafe(12)
        with self._lock:
            self.payment_connection.execute(
                "INSERT INTO payment_audiences(ref,name,members_json) VALUES(?,?,?) "
                "ON CONFLICT(ref) DO UPDATE SET name=excluded.name,members_json=excluded.members_json,updated_at=CURRENT_TIMESTAMP",
                (ref, clean_name, json.dumps(members, separators=(",", ":"))),
            )
            self._payment_audit(
                "audience-saved", actor_user_id=actor_user_id, actor_platform=actor_platform,
                note=f"{clean_name}:{len(members)}",
            )
            self.payment_connection.commit()
        return next(item for item in self.saved_payment_audiences() if item["ref"] == ref)

    def create_payment_reminder_preview(
        self, offer_ref: str, platform: str, student_numbers: list[str], *, purpose: str = "reminder"
    ) -> dict:
        members = sorted(set(value for item in student_numbers if (value := normalize_student_number(item))))
        clean_purpose = purpose if purpose in {"reminder", "product-share"} else "reminder"
        digest = hashlib.sha256((clean_purpose + "|" + offer_ref + "|" + platform + "|" + "|".join(members)).encode("utf-8")).hexdigest()
        ref = secrets.token_urlsafe(14)
        with self._lock:
            recent = self.payment_connection.execute(
                "SELECT ref,status,created_at FROM payment_reminder_batches WHERE offer_ref=? AND platform=? "
                "AND recipient_hash=? AND created_at>=datetime('now','-1 day') AND status IN ('sending','sent') LIMIT 1",
                (offer_ref, platform, digest),
            ).fetchone()
            if recent:
                return {"ref": str(recent[0]), "status": str(recent[1]), "duplicate": True, "recipientCount": len(members)}
            self.payment_connection.execute(
                "INSERT INTO payment_reminder_batches(ref,offer_ref,platform,recipient_hash,recipient_count,status) "
                "VALUES(?,?,?,?,?,'preview')",
                (ref, offer_ref, platform, digest, len(members)),
            )
            self.payment_connection.commit()
        return {"ref": ref, "status": "preview", "duplicate": False, "recipientCount": len(members)}

    def finish_payment_reminder(self, ref: str, *, status: str, sent_count: int) -> None:
        if status not in {"sending", "sent", "failed", "canceled"}:
            raise ValueError("Invalid reminder status")
        with self._lock:
            self.payment_connection.execute(
                "UPDATE payment_reminder_batches SET status=?,sent_count=?,updated_at=CURRENT_TIMESTAMP WHERE ref=?",
                (status, max(0, int(sent_count)), ref),
            )
            self.payment_connection.commit()

    def payment_reminder(self, ref: str) -> dict | None:
        with self._lock:
            row = self.payment_connection.execute(
                "SELECT ref,offer_ref,platform,recipient_count,status,sent_count,created_at,updated_at "
                "FROM payment_reminder_batches WHERE ref=?",
                (str(ref),),
            ).fetchone()
        if row is None:
            return None
        return {
            "ref": str(row[0]), "offerRef": str(row[1]), "platform": str(row[2]),
            "recipientCount": int(row[3]), "status": str(row[4]), "sentCount": int(row[5]),
            "createdAt": str(row[6]), "updatedAt": str(row[7]),
        }

    def record_payment_action(
        self,
        action: str,
        *,
        actor_user_id: int,
        actor_platform: str,
        offer_ref: str = "",
        note: str = "",
    ) -> None:
        with self._lock:
            self._payment_audit(
                action, actor_user_id=actor_user_id, actor_platform=actor_platform,
                offer_ref=offer_ref, note=note,
            )
            self.payment_connection.commit()

    def start_dialog(self, user_id: int, kind: str, step: str, payload: dict | None = None) -> dict:
        encoded = json.dumps(payload or {}, ensure_ascii=False, separators=(",", ":"))
        with self._lock:
            self.connection.execute(
                "INSERT INTO bot_dialogs(user_id,kind,step,payload_json) VALUES(?,?,?,?) "
                "ON CONFLICT(user_id) DO UPDATE SET kind=excluded.kind,step=excluded.step,"
                "payload_json=excluded.payload_json,updated_at=CURRENT_TIMESTAMP",
                (user_id, kind, step, encoded),
            )
            self.connection.commit()
        return {"kind": kind, "step": step, "payload": dict(payload or {})}

    def dialog(self, user_id: int) -> dict | None:
        with self._lock:
            row = self.connection.execute(
                "SELECT kind,step,payload_json,updated_at FROM bot_dialogs "
                "WHERE user_id=? AND ("
                "(kind IN ('onboarding-v1','class-auth-v1') AND updated_at >= datetime('now','-1 day')) OR "
                "(kind NOT IN ('onboarding-v1','class-auth-v1') AND updated_at >= datetime('now','-30 minutes'))"
                ")",
                (user_id,),
            ).fetchone()
            if row is None:
                self.connection.execute("DELETE FROM bot_dialogs WHERE user_id=?", (user_id,))
                self.connection.commit()
        if row is None:
            return None
        try:
            payload = json.loads(str(row[2]))
        except json.JSONDecodeError:
            payload = {}
        return {
            "kind": str(row[0]),
            "step": str(row[1]),
            "payload": payload if isinstance(payload, dict) else {},
            "updatedAt": str(row[3]),
        }

    def update_dialog(self, user_id: int, *, step: str, payload: dict) -> dict | None:
        encoded = json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
        with self._lock:
            cursor = self.connection.execute(
                "UPDATE bot_dialogs SET step=?,payload_json=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?",
                (step, encoded, user_id),
            )
            self.connection.commit()
        return self.dialog(user_id) if cursor.rowcount else None

    def clear_dialog(self, user_id: int) -> None:
        with self._lock:
            self.connection.execute("DELETE FROM bot_dialogs WHERE user_id=?", (user_id,))
            self.connection.commit()

    def mark_reply_keyboard_active(self, user_id: int) -> None:
        with self._lock:
            self.connection.execute(
                "INSERT INTO reply_keyboard_state(user_id,active,cleanup_version) VALUES(?,1,'') "
                "ON CONFLICT(user_id) DO UPDATE SET active=1,cleanup_version='',updated_at=CURRENT_TIMESTAMP",
                (int(user_id),),
            )
            self.connection.commit()

    def mark_reply_keyboard_removed(self, user_id: int, cleanup_version: str) -> None:
        if not cleanup_version:
            raise ValueError("Reply-keyboard cleanup version is required")
        with self._lock:
            self.connection.execute(
                "INSERT INTO reply_keyboard_state(user_id,active,cleanup_version) VALUES(?,0,?) "
                "ON CONFLICT(user_id) DO UPDATE SET active=0,cleanup_version=excluded.cleanup_version,"
                "updated_at=CURRENT_TIMESTAMP",
                (int(user_id), cleanup_version[:80]),
            )
            self.connection.commit()

    def reply_keyboard_needs_removal(self, user_id: int, cleanup_version: str) -> bool:
        with self._lock:
            row = self.connection.execute(
                "SELECT active,cleanup_version FROM reply_keyboard_state WHERE user_id=?",
                (int(user_id),),
            ).fetchone()
        return row is None or bool(int(row[0])) or str(row[1]) != cleanup_version

    def runtime_value(self, key: str) -> str:
        with self._lock:
            row = self.connection.execute("SELECT value FROM runtime_state WHERE key=?", (key,)).fetchone()
        return str(row[0]) if row else ""

    def set_runtime_value(self, key: str, value: str) -> None:
        with self._lock:
            self.connection.execute(
                "INSERT INTO runtime_state(key,value) VALUES(?,?) "
                "ON CONFLICT(key) DO UPDATE SET value=excluded.value",
                (key, value),
            )
            self.connection.commit()

    @staticmethod
    def _protected_media_payload(row: tuple | None) -> dict | None:
        if row is None:
            return None
        return {
            "id": int(row[0]),
            "sourceChatId": int(row[1]),
            "sourceMessageId": int(row[2]),
            "courseCode": str(row[3]),
            "courseName": str(row[4]),
            "courseTag": str(row[5]),
            "term": int(row[6]),
            "sessionNo": int(row[7]),
            "contentKind": str(row[8]),
            "telegramMethod": str(row[9]),
            "fileId": str(row[10]),
            "fileUniqueId": str(row[11]),
            "fileName": str(row[12]),
            "mimeType": str(row[13]),
            "caption": str(row[14]),
        }

    def replace_protected_media_message(
        self,
        source_chat_id: int,
        source_message_id: int,
        records: list[dict],
    ) -> int:
        """Atomically replace caption-derived routes without storing file bytes."""
        if source_chat_id >= 0 or source_message_id <= 0:
            raise ValueError("Invalid Telegram source message")
        active_kinds = {str(item.get("contentKind") or "") for item in records}
        with self._lock:
            self.connection.execute(
                "UPDATE protected_media_sources SET active=0,updated_at=CURRENT_TIMESTAMP "
                "WHERE source_chat_id=? AND source_message_id=?",
                (source_chat_id, source_message_id),
            )
            for item in records:
                kind = str(item.get("contentKind") or "")
                if kind not in {"voice", "power", "booklet", "reference"}:
                    raise ValueError("Invalid protected media kind")
                self.connection.execute(
                    "INSERT INTO protected_media_sources("
                    "source_chat_id,source_message_id,course_code,course_name,course_tag,term,session_no,"
                    "content_kind,telegram_method,file_id,file_unique_id,file_name,mime_type,caption,active"
                    ") VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1) "
                    "ON CONFLICT(source_chat_id,source_message_id,content_kind) DO UPDATE SET "
                    "course_code=excluded.course_code,course_name=excluded.course_name,"
                    "course_tag=excluded.course_tag,term=excluded.term,session_no=excluded.session_no,"
                    "telegram_method=excluded.telegram_method,"
                    "file_id=CASE WHEN excluded.file_id!='' THEN excluded.file_id ELSE protected_media_sources.file_id END,"
                    "file_unique_id=CASE WHEN excluded.file_unique_id!='' THEN excluded.file_unique_id ELSE protected_media_sources.file_unique_id END,"
                    "file_name=CASE WHEN excluded.file_name!='' THEN excluded.file_name ELSE protected_media_sources.file_name END,"
                    "mime_type=excluded.mime_type,caption=excluded.caption,active=1,updated_at=CURRENT_TIMESTAMP",
                    (
                        source_chat_id, source_message_id, str(item.get("courseCode") or "")[:80],
                        str(item.get("courseName") or "")[:160], str(item.get("courseTag") or "")[:80],
                        int(item.get("term") or 0), int(item.get("sessionNo") or 0), kind,
                        str(item.get("telegramMethod") or ""), str(item.get("fileId") or "")[:300],
                        str(item.get("fileUniqueId") or "")[:200], str(item.get("fileName") or "")[:240],
                        str(item.get("mimeType") or "")[:120], str(item.get("caption") or "")[:3000],
                    ),
                )
            if active_kinds:
                placeholders = ",".join("?" for _ in active_kinds)
                self.connection.execute(
                    f"UPDATE protected_media_sources SET active=0,updated_at=CURRENT_TIMESTAMP "
                    f"WHERE source_chat_id=? AND source_message_id=? AND content_kind NOT IN ({placeholders})",
                    (source_chat_id, source_message_id, *sorted(active_kinds)),
                )
            self.connection.commit()
        return len(records)

    def update_protected_media_file(
        self,
        source_chat_id: int,
        source_message_id: int,
        *,
        file_id: str,
        file_unique_id: str = "",
        file_name: str = "",
        mime_type: str = "",
    ) -> int:
        """Hydrate Bot API file metadata for an already registered source message."""
        if source_chat_id >= 0 or source_message_id <= 0 or not str(file_id).strip():
            raise ValueError("Invalid protected media file metadata")
        with self._lock:
            cursor = self.connection.execute(
                "UPDATE protected_media_sources SET file_id=?,file_unique_id=?,"
                "file_name=CASE WHEN ?!='' THEN ? ELSE file_name END,"
                "mime_type=CASE WHEN ?!='' THEN ? ELSE mime_type END,updated_at=CURRENT_TIMESTAMP "
                "WHERE source_chat_id=? AND source_message_id=? AND active=1",
                (
                    str(file_id)[:300], str(file_unique_id)[:200],
                    str(file_name)[:240], str(file_name)[:240],
                    str(mime_type)[:120], str(mime_type)[:120],
                    source_chat_id, source_message_id,
                ),
            )
            self.connection.commit()
            return int(cursor.rowcount)

    def protected_media_for(
        self,
        *,
        course_code: str,
        term: int,
        session_no: int,
        content_kind: str,
    ) -> list[dict]:
        with self._lock:
            rows = self.connection.execute(
                "SELECT id,source_chat_id,source_message_id,course_code,course_name,course_tag,term,"
                "session_no,content_kind,telegram_method,file_id,file_unique_id,file_name,mime_type,caption "
                "FROM protected_media_sources WHERE course_code=? AND term=? AND session_no=? "
                "AND content_kind=? AND active=1 ORDER BY source_message_id",
                (course_code, term, session_no, content_kind),
            ).fetchall()
        return [dict(payload) for row in rows if (payload := self._protected_media_payload(row)) is not None]

    def protected_media_source(self, source_id: int) -> dict | None:
        with self._lock:
            row = self.connection.execute(
                "SELECT id,source_chat_id,source_message_id,course_code,course_name,course_tag,term,"
                "session_no,content_kind,telegram_method,file_id,file_unique_id,file_name,mime_type,caption "
                "FROM protected_media_sources WHERE id=? AND active=1",
                (source_id,),
            ).fetchone()
        return self._protected_media_payload(row)

    def cache_personalized_media_file(
        self,
        user_id: int,
        source_id: int,
        file_id: str,
        *,
        file_unique_id: str = "",
    ) -> None:
        if user_id <= 0 or source_id <= 0 or not file_id:
            raise ValueError("Invalid personalized media cache entry")
        with self._lock:
            self.connection.execute(
                "INSERT INTO personalized_media_cache(user_id,source_id,file_id,file_unique_id) VALUES(?,?,?,?) "
                "ON CONFLICT(user_id,source_id) DO UPDATE SET file_id=excluded.file_id,"
                "file_unique_id=excluded.file_unique_id,created_at=CURRENT_TIMESTAMP",
                (user_id, source_id, file_id[:300], file_unique_id[:200]),
            )
            self.connection.commit()

    def personalized_media_file(self, user_id: int, source_id: int) -> str:
        with self._lock:
            row = self.connection.execute(
                "SELECT file_id FROM personalized_media_cache WHERE user_id=? AND source_id=?",
                (user_id, source_id),
            ).fetchone()
        return str(row[0]) if row else ""

    @staticmethod
    def _booklet_issuance_payload(row: tuple | None) -> dict | None:
        if row is None:
            return None
        return {
            "issuanceId": str(row[0]),
            "userId": int(row[1]),
            "sourceId": int(row[2]),
            "documentId": str(row[3]),
            "traceCode": str(row[4]),
            "fingerprintHash": str(row[5]),
            "watermarkVersion": str(row[6]),
            "sourceHash": str(row[7]),
            "status": str(row[8]),
            "issuedAt": str(row[9]),
            "telegramFileId": str(row[10]),
            "telegramFileUniqueId": str(row[11]),
        }

    def sent_booklet_issuance(
        self,
        user_id: int,
        source_id: int,
        document_id: str,
        watermark_version: str,
    ) -> dict | None:
        with self._lock:
            row = self.connection.execute(
                "SELECT issuance_id,user_id,source_id,document_id,trace_code,fingerprint_hash,"
                "watermark_version,source_hash,status,issued_at,telegram_file_id,telegram_file_unique_id "
                "FROM booklet_issuances WHERE user_id=? AND document_id=? "
                "AND watermark_version=? AND status='sent' AND telegram_file_id!='' "
                "ORDER BY updated_at DESC LIMIT 1",
                (int(user_id), str(document_id), str(watermark_version)),
            ).fetchone()
        return self._booklet_issuance_payload(row)

    def booklet_issuance_for_source_hash(
        self,
        user_id: int,
        source_id: int,
        document_id: str,
        source_hash: str,
        watermark_version: str,
    ) -> dict | None:
        with self._lock:
            row = self.connection.execute(
                "SELECT issuance_id,user_id,source_id,document_id,trace_code,fingerprint_hash,"
                "watermark_version,source_hash,status,issued_at,telegram_file_id,telegram_file_unique_id "
                "FROM booklet_issuances WHERE user_id=? AND document_id=? "
                "AND source_hash=? AND watermark_version=? LIMIT 1",
                (int(user_id), str(document_id), str(source_hash), str(watermark_version)),
            ).fetchone()
        return self._booklet_issuance_payload(row)

    def create_booklet_issuance(
        self,
        *,
        issuance_id: str,
        user_id: int,
        source_id: int,
        document_id: str,
        trace_code: str,
        fingerprint_hash: str,
        watermark_version: str,
        source_hash: str,
    ) -> dict:
        values = (
            str(issuance_id), int(user_id), int(source_id), str(document_id)[:240],
            str(trace_code)[:32], str(fingerprint_hash)[:64], str(watermark_version)[:80],
            str(source_hash)[:64],
        )
        if values[1] <= 0 or values[2] <= 0 or len(values[5]) != 64 or len(values[7]) != 64:
            raise ValueError("Invalid booklet issuance")
        with self._lock:
            self.connection.execute(
                "INSERT OR IGNORE INTO booklet_issuances(issuance_id,user_id,source_id,document_id,trace_code,"
                "fingerprint_hash,watermark_version,source_hash,status) VALUES(?,?,?,?,?,?,?,?, 'queued')",
                values,
            )
            self.connection.commit()
        result = self.booklet_issuance_for_source_hash(
            user_id, source_id, document_id, source_hash, watermark_version
        )
        if result is None:
            raise RuntimeError("Booklet issuance was not persisted")
        return result

    def mark_booklet_issuance_processing(self, issuance_id: str) -> bool:
        with self._lock:
            cursor = self.connection.execute(
                "UPDATE booklet_issuances SET status='processing',updated_at=CURRENT_TIMESTAMP "
                "WHERE issuance_id=? AND status IN ('queued','failed')",
                (str(issuance_id),),
            )
            self.connection.commit()
            return bool(cursor.rowcount)

    def mark_booklet_issuance_sent(
        self,
        issuance_id: str,
        *,
        telegram_file_id: str,
        telegram_file_unique_id: str = "",
    ) -> None:
        if not telegram_file_id:
            raise ValueError("Telegram file ID is required")
        with self._lock:
            self.connection.execute(
                "UPDATE booklet_issuances SET status='sent',telegram_file_id=?,telegram_file_unique_id=?,"
                "issued_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE issuance_id=?",
                (str(telegram_file_id)[:300], str(telegram_file_unique_id)[:200], str(issuance_id)),
            )
            self.connection.commit()

    def complete_booklet_issuance(
        self,
        issuance_id: str,
        *,
        telegram_file_id: str,
        telegram_file_unique_id: str = "",
    ) -> None:
        """Atomically publish a reusable Telegram file and its source cache."""
        if not telegram_file_id:
            raise ValueError("Telegram file ID is required")
        with self._lock:
            try:
                self.connection.execute("BEGIN IMMEDIATE")
                row = self.connection.execute(
                    "SELECT user_id,source_id FROM booklet_issuances WHERE issuance_id=?",
                    (str(issuance_id),),
                ).fetchone()
                if row is None:
                    raise ValueError("Booklet issuance does not exist")
                self.connection.execute(
                    "UPDATE booklet_issuances SET status='sent',telegram_file_id=?,telegram_file_unique_id=?,"
                    "issued_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE issuance_id=?",
                    (
                        str(telegram_file_id)[:300], str(telegram_file_unique_id)[:200],
                        str(issuance_id),
                    ),
                )
                self.connection.execute(
                    "INSERT INTO personalized_media_cache(user_id,source_id,file_id,file_unique_id) "
                    "VALUES(?,?,?,?) ON CONFLICT(user_id,source_id) DO UPDATE SET "
                    "file_id=excluded.file_id,file_unique_id=excluded.file_unique_id,"
                    "created_at=CURRENT_TIMESTAMP",
                    (
                        int(row[0]), int(row[1]), str(telegram_file_id)[:300],
                        str(telegram_file_unique_id)[:200],
                    ),
                )
                self.connection.commit()
            except Exception:
                self.connection.rollback()
                raise

    def invalidate_booklet_issuance_file(self, issuance_id: str) -> None:
        """Forget only an explicitly rejected Telegram file_id, retaining attribution."""
        with self._lock:
            try:
                self.connection.execute("BEGIN IMMEDIATE")
                row = self.connection.execute(
                    "SELECT user_id,source_id FROM booklet_issuances WHERE issuance_id=?",
                    (str(issuance_id),),
                ).fetchone()
                self.connection.execute(
                    "UPDATE booklet_issuances SET status='failed',telegram_file_id='',"
                    "telegram_file_unique_id='',updated_at=CURRENT_TIMESTAMP WHERE issuance_id=?",
                    (str(issuance_id),),
                )
                if row is not None:
                    self.connection.execute(
                        "DELETE FROM personalized_media_cache WHERE user_id=? AND source_id=?",
                        (int(row[0]), int(row[1])),
                    )
                self.connection.commit()
            except Exception:
                self.connection.rollback()
                raise

    def recover_stale_booklet_issuances(self, max_age_seconds: int) -> int:
        modifier = f"-{max(60, int(max_age_seconds))} seconds"
        with self._lock:
            cursor = self.connection.execute(
                "UPDATE booklet_issuances SET status='failed',updated_at=CURRENT_TIMESTAMP "
                "WHERE status='processing' AND updated_at < datetime('now', ?)",
                (modifier,),
            )
            self.connection.commit()
            return int(cursor.rowcount)

    def claim_booklet_request(
        self,
        user_id: int,
        document_id: str,
        *,
        now_epoch: int,
        window_seconds: int,
        max_requests: int,
        cooldown_seconds: int,
    ) -> str:
        """Durable rate limit used before a request consumes queue capacity."""
        user_id = int(user_id)
        document_id = str(document_id)[:240]
        if user_id <= 0 or not document_id:
            return "invalid"
        cutoff = int(now_epoch) - max(1, int(window_seconds))
        with self._lock:
            try:
                self.connection.execute("BEGIN IMMEDIATE")
                self.connection.execute(
                    "DELETE FROM booklet_request_events WHERE requested_at < ?",
                    (cutoff,),
                )
                recent_same = self.connection.execute(
                    "SELECT MAX(requested_at) FROM booklet_request_events "
                    "WHERE user_id=? AND document_id=?",
                    (user_id, document_id),
                ).fetchone()
                if recent_same and recent_same[0] is not None and int(now_epoch) - int(recent_same[0]) < int(cooldown_seconds):
                    self.connection.rollback()
                    return "cooldown"
                count = int(self.connection.execute(
                    "SELECT COUNT(*) FROM booklet_request_events WHERE user_id=? AND requested_at>=?",
                    (user_id, cutoff),
                ).fetchone()[0])
                if count >= max(1, int(max_requests)):
                    self.connection.rollback()
                    return "rate-limited"
                self.connection.execute(
                    "INSERT INTO booklet_request_events(user_id,document_id,requested_at) VALUES(?,?,?)",
                    (user_id, document_id, int(now_epoch)),
                )
                self.connection.commit()
                return "claimed"
            except Exception:
                self.connection.rollback()
                raise

    def mark_booklet_issuance_failed(self, issuance_id: str) -> None:
        with self._lock:
            self.connection.execute(
                "UPDATE booklet_issuances SET status='failed',updated_at=CURRENT_TIMESTAMP WHERE issuance_id=?",
                (str(issuance_id),),
            )
            self.connection.commit()

    def forensic_booklet_candidates(
        self,
        *,
        trace_code: str = "",
        document_id: str = "",
        source_hash: str = "",
        watermark_version: str = "",
        limit: int = 5000,
    ) -> list[dict]:
        clauses = ["status='sent'"]
        values: list[object] = []
        if trace_code:
            clauses.append("trace_code=?")
            values.append(str(trace_code))
        if document_id:
            clauses.append("document_id=?")
            values.append(str(document_id))
        if source_hash:
            clauses.append("source_hash=?")
            values.append(str(source_hash))
        if watermark_version:
            clauses.append("watermark_version=?")
            values.append(str(watermark_version))
        values.append(max(1, min(int(limit), 5000)))
        with self._lock:
            rows = self.connection.execute(
                "SELECT issuance_id,user_id,source_id,document_id,trace_code,fingerprint_hash,"
                "watermark_version,source_hash,status,issued_at,telegram_file_id,telegram_file_unique_id "
                "FROM booklet_issuances WHERE " + " AND ".join(clauses) + " ORDER BY issued_at DESC LIMIT ?",
                tuple(values),
            ).fetchall()
        return [dict(payload) for row in rows if (payload := self._booklet_issuance_payload(row)) is not None]

    def record_protected_media_delivery(
        self,
        user_id: int,
        source_id: int,
        status: str,
        *,
        message_id: int = 0,
    ) -> None:
        if status not in {"sent", "failed", "denied"}:
            raise ValueError("Invalid delivery status")
        with self._lock:
            self.connection.execute(
                "INSERT INTO protected_media_deliveries(user_id,source_id,status,message_id) VALUES(?,?,?,?)",
                (user_id, source_id, status, max(0, int(message_id))),
            )
            self.connection.commit()

    def latest_protected_media_delivery(self, user_id: int, source_id: int) -> dict | None:
        with self._lock:
            row = self.connection.execute(
                "SELECT status,message_id,created_at FROM protected_media_deliveries "
                "WHERE user_id=? AND source_id=? ORDER BY id DESC LIMIT 1",
                (int(user_id), int(source_id)),
            ).fetchone()
        if row is None:
            return None
        return {
            "status": str(row[0]),
            "messageIdPresent": int(row[1] or 0) > 0,
            "createdAtPresent": bool(str(row[2] or "")),
        }

    def set_navid_challenge(self, daily_date: str, message_id: int, expires_at: str) -> None:
        with self._lock:
            self.connection.execute(
                "INSERT INTO navid_captcha_challenge(singleton,daily_date,message_id,expires_at) VALUES(1,?,?,?) "
                "ON CONFLICT(singleton) DO UPDATE SET daily_date=excluded.daily_date,message_id=excluded.message_id,"
                "expires_at=excluded.expires_at,created_at=CURRENT_TIMESTAMP",
                (daily_date, message_id, expires_at),
            )
            self.connection.commit()

    def navid_challenge(self) -> dict | None:
        with self._lock:
            row = self.connection.execute(
                "SELECT daily_date,message_id,expires_at FROM navid_captcha_challenge WHERE singleton=1"
            ).fetchone()
        return {"date": str(row[0]), "message_id": int(row[1]), "expires_at": str(row[2])} if row else None

    def clear_navid_challenge(self) -> None:
        with self._lock:
            self.connection.execute("DELETE FROM navid_captcha_challenge WHERE singleton=1")
            self.connection.commit()

    def set_integration_challenge(
        self,
        user_id: int,
        *,
        challenge_ref: str,
        job_ref: str,
        connector: str,
        message_id: int,
        expires_at: str,
    ) -> None:
        if user_id <= 0 or message_id <= 0:
            raise ValueError("Invalid integration challenge binding")
        with self._lock:
            self.connection.execute(
                "INSERT INTO integration_captcha_challenges("
                "user_id,challenge_ref,job_ref,connector,message_id,expires_at"
                ") VALUES(?,?,?,?,?,?) "
                "ON CONFLICT(user_id) DO UPDATE SET "
                "challenge_ref=excluded.challenge_ref,job_ref=excluded.job_ref,"
                "connector=excluded.connector,message_id=excluded.message_id,"
                "expires_at=excluded.expires_at,created_at=CURRENT_TIMESTAMP",
                (user_id, challenge_ref, job_ref, connector, message_id, expires_at),
            )
            self.connection.commit()

    def integration_challenge(self, user_id: int) -> dict | None:
        with self._lock:
            row = self.connection.execute(
                "SELECT challenge_ref,job_ref,connector,message_id,expires_at "
                "FROM integration_captcha_challenges WHERE user_id=?",
                (user_id,),
            ).fetchone()
        if row is None:
            return None
        return {
            "challenge_ref": str(row[0]),
            "job_ref": str(row[1]),
            "connector": str(row[2]),
            "message_id": int(row[3]),
            "expires_at": str(row[4]),
        }

    def take_integration_challenge(self, user_id: int, *, message_id: int) -> dict | None:
        """Atomically consume the exact reply binding without storing its answer."""
        with self._lock:
            row = self.connection.execute(
                "SELECT challenge_ref,job_ref,connector,message_id,expires_at "
                "FROM integration_captcha_challenges WHERE user_id=? AND message_id=?",
                (user_id, message_id),
            ).fetchone()
            if row is not None:
                self.connection.execute(
                    "DELETE FROM integration_captcha_challenges WHERE user_id=? AND message_id=?",
                    (user_id, message_id),
                )
                self.connection.commit()
        if row is None:
            return None
        return {
            "challenge_ref": str(row[0]),
            "job_ref": str(row[1]),
            "connector": str(row[2]),
            "message_id": int(row[3]),
            "expires_at": str(row[4]),
        }

    def clear_integration_challenge(self, user_id: int) -> None:
        with self._lock:
            self.connection.execute("DELETE FROM integration_captcha_challenges WHERE user_id=?", (user_id,))
            self.connection.commit()

    def offset(self) -> int:
        with self._lock:
            row = self.connection.execute("SELECT value FROM runtime_state WHERE key='update_offset'").fetchone()
        return int(row[0]) if row else 0

    def save_offset(self, offset: int) -> None:
        with self._lock:
            self.connection.execute(
                "INSERT INTO runtime_state(key,value) VALUES('update_offset',?) "
                "ON CONFLICT(key) DO UPDATE SET value=excluded.value",
                (str(offset),),
            )
            self.connection.commit()

    def touch_user(self, user_id: int, section: str = "home") -> None:
        with self._lock:
            self.connection.execute(
                "INSERT INTO bot_users(telegram_user_id,last_section) VALUES(?,?) "
                "ON CONFLICT(telegram_user_id) DO UPDATE SET "
                "last_seen_at=CURRENT_TIMESTAMP,last_section=excluded.last_section",
                (user_id, section),
            )
            self.connection.commit()

    def remember_notification(self, notification_id: str) -> str:
        import hashlib

        ref = hashlib.sha256(notification_id.encode("utf-8")).hexdigest()[:16]
        with self._lock:
            self.connection.execute(
                "INSERT INTO notification_refs(ref,notification_id) VALUES(?,?) "
                "ON CONFLICT(ref) DO UPDATE SET notification_id=excluded.notification_id,updated_at=CURRENT_TIMESTAMP",
                (ref, notification_id),
            )
            self.connection.commit()
        return ref

    def notification_id(self, ref: str) -> str:
        with self._lock:
            row = self.connection.execute(
                "SELECT notification_id FROM notification_refs WHERE ref=?",
                (ref,),
            ).fetchone()
        return str(row[0]) if row else ""

    def has_notification_delivery(self, delivery_id: str) -> bool:
        with self._lock:
            row = self.connection.execute(
                "SELECT 1 FROM notification_delivery_receipts WHERE delivery_id=?",
                (delivery_id,),
            ).fetchone()
        return row is not None

    def mark_notification_delivery(self, delivery_id: str) -> None:
        with self._lock:
            self.connection.execute(
                "INSERT OR IGNORE INTO notification_delivery_receipts(delivery_id) VALUES(?)",
                (delivery_id,),
            )
            self.connection.commit()

    def close(self) -> None:
        with self._lock:
            if self._payment_connection_owned:
                self.payment_connection.close()
            self.connection.close()
