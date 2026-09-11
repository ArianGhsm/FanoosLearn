#!/usr/bin/env python3
"""Validate an extracted minimal VPS-state backup without exposing its data."""

from __future__ import annotations

import hashlib
import json
import sqlite3
import sys
from pathlib import Path


REQUIRED_FILES = {
    "runtime/dent-bot/state.sqlite3",
    "runtime/bale-bot/state.sqlite3",
    "runtime/shared/payment-offers.sqlite3",
    "config/dent-bot.env",
    "config/bale-bot.env",
    "config/deploy-notifier.env",
    "config/telegram-egress.json",
    "metadata/service-enabled.txt",
    "metadata/service-active.txt",
    "metadata/snapshot-id.txt",
}
BOT_REQUIRED_TABLES = {
    "runtime_state",
    "bot_users",
    "notification_delivery_receipts",
    "booklet_issuances",
}


def verify(root_value: Path) -> dict[str, int | bool]:
    root = root_value.resolve()
    checksum_file = root / "SHA256SUMS.json"
    checksums = json.loads(checksum_file.read_text(encoding="utf-8"))
    if not isinstance(checksums, dict) or not checksums:
        raise RuntimeError("Backup checksum manifest is empty")
    if REQUIRED_FILES - set(checksums):
        raise RuntimeError("Backup is missing required production state")
    for relative, expected in checksums.items():
        if not isinstance(relative, str) or not isinstance(expected, str):
            raise RuntimeError("Backup checksum manifest has invalid entries")
        if len(expected) != 64 or any(character not in "0123456789abcdef" for character in expected):
            raise RuntimeError("Backup checksum manifest has an invalid digest")
        path = (root / relative).resolve()
        if root not in path.parents or not path.is_file():
            raise RuntimeError("Backup manifest contains an invalid path")
        actual = hashlib.sha256(path.read_bytes()).hexdigest()
        if actual != expected:
            raise RuntimeError("Backup file checksum mismatch")

    actual_files = {
        path.relative_to(root).as_posix()
        for path in root.rglob("*")
        if path.is_file() and path.name != "SHA256SUMS.json"
    }
    if actual_files != set(checksums):
        raise RuntimeError("Backup contains files outside its checksum manifest")

    databases = sorted((root / "runtime").glob("*/*.sqlite3"))
    for database in databases:
        connection = sqlite3.connect(f"file:{database.as_posix()}?mode=ro", uri=True)
        try:
            result = connection.execute("PRAGMA integrity_check").fetchone()
            if result != ("ok",):
                raise RuntimeError("SQLite integrity check failed")
            relative = database.relative_to(root).as_posix()
            if relative in {
                "runtime/dent-bot/state.sqlite3",
                "runtime/bale-bot/state.sqlite3",
            }:
                tables = {
                    str(row[0])
                    for row in connection.execute("SELECT name FROM sqlite_master WHERE type='table'")
                }
                if not BOT_REQUIRED_TABLES <= tables:
                    raise RuntimeError("Bot runtime database is missing required tables")
        finally:
            connection.close()

    shared_offers = root / "runtime/shared/payment-offers.sqlite3"
    if shared_offers.is_file():
        connection = sqlite3.connect(f"file:{shared_offers.as_posix()}?mode=ro", uri=True)
        try:
            tables = {
                str(row[0])
                for row in connection.execute("SELECT name FROM sqlite_master WHERE type='table'")
            }
            if "payment_offers" not in tables:
                raise RuntimeError("Shared bot-commerce database is missing its payment table")
            term_tables = {
                "term_access_policies",
                "term_access_entitlements",
                "term_subscription_checkouts",
                "term_access_audit",
                "term_subscription_renewal_notices",
            }
            # Old pre-migration snapshots remain restorable. Once any part of
            # the additive term schema exists, however, a partial/corrupt
            # migration must fail backup verification rather than silently
            # becoming the disaster-recovery source.
            if tables & term_tables and not term_tables <= tables:
                raise RuntimeError("Shared bot-commerce database has an incomplete term-access schema")
            if term_tables <= tables:
                policy = connection.execute(
                    "SELECT mode,enabled,monthly_price_rials,active_from_jalali "
                    "FROM term_access_policies WHERE term=7"
                ).fetchone()
                if policy is None:
                    raise RuntimeError("Shared bot-commerce database is missing the Term 7 access policy")
        finally:
            connection.close()

    json_count = 0
    notifier = root / "runtime/deploy-notifier"
    for path in notifier.glob("*/*.json"):
        value = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(value, dict):
            raise RuntimeError("Notifier history contains a non-object JSON record")
        json_count += 1

    required_configs: set[str] = set()
    if (root / "runtime/dent-bot/state.sqlite3").is_file():
        required_configs.add("dent-bot.env")
    if (root / "runtime/bale-bot/state.sqlite3").is_file():
        required_configs.add("bale-bot.env")
    if (root / "runtime/shared/payment-offers.sqlite3").is_file():
        required_configs.update({"dent-bot.env", "bale-bot.env"})
    if any((root / "runtime/deploy-notifier").glob("*/*.json")):
        required_configs.add("deploy-notifier.env")
    available_configs = {path.name for path in (root / "config").glob("*.env")}
    if required_configs - available_configs:
        raise RuntimeError("Required runtime configuration is missing")

    for relative in REQUIRED_FILES:
        if (root / relative).stat().st_size <= 0:
            raise RuntimeError("Required backup file is empty")

    return {
        "valid": True,
        "sqlite_databases": len(databases),
        "notifier_records": json_count,
        "config_files": len(available_configs),
    }


def main() -> int:
    if len(sys.argv) != 2:
        raise SystemExit("usage: verify_vps_backup.py <extracted-root>")
    result = verify(Path(sys.argv[1]))

    print(json.dumps(result, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
