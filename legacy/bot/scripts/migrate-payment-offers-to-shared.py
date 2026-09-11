#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sqlite3
import sys
import json
import secrets
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from dent_bot.state import PAYMENT_OFFERS_SCHEMA, PAYMENT_OFFER_COLUMNS
from dent_bot.payments import normalize_audience


LEGACY_COLUMNS = "ref,title,description,amount_rials,status,created_at,updated_at"


def read_offers(path: Path) -> dict[str, dict[str, object]]:
    connection = sqlite3.connect(f"file:{path}?mode=ro", uri=True, timeout=10)
    try:
        if connection.execute("PRAGMA integrity_check").fetchone() != ("ok",):
            raise RuntimeError(f"Source SQLite integrity failed: {path.name}")
        table = connection.execute(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='payment_offers'"
        ).fetchone()
        if table != (1,):
            return {}
        columns = {str(row[1]) for row in connection.execute("PRAGMA table_info(payment_offers)")}
        result: dict[str, dict[str, object]] = {}
        if {"share_token", "audience_json", "version"}.issubset(columns):
            names = PAYMENT_OFFER_COLUMNS.split(",")
            for row in connection.execute(f"SELECT {PAYMENT_OFFER_COLUMNS} FROM payment_offers"):
                value = dict(zip(names, row))
                result[str(value["ref"])] = value
            return result
        names = LEGACY_COLUMNS.split(",")
        for row in connection.execute(f"SELECT {LEGACY_COLUMNS} FROM payment_offers"):
            legacy = dict(zip(names, row))
            status = {"active": "active", "inactive": "paused", "deleted": "archived"}.get(str(legacy["status"]), "draft")
            result[str(legacy["ref"])] = {
                "id": None,
                "ref": str(legacy["ref"]),
                "share_token": "",
                "title": str(legacy["title"]),
                "description": str(legacy["description"]),
                "amount_rials": int(legacy["amount_rials"]),
                "status": status,
                "audience_json": json.dumps(normalize_audience({"mode": "all"}), separators=(",", ":")),
                "available_from": "", "expires_at": "", "capacity": 0, "max_per_user": 1,
                "fulfillment_json": "{}", "version": 1,
                "created_at": str(legacy["created_at"]), "updated_at": str(legacy["updated_at"]),
            }
        return result
    finally:
        connection.close()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--telegram", required=True, type=Path)
    parser.add_argument("--bale", required=True, type=Path)
    parser.add_argument("--target", required=True, type=Path)
    args = parser.parse_args()

    if args.target.exists():
        raise RuntimeError("Shared payment-offer database already exists")
    telegram = read_offers(args.telegram)
    bale = read_offers(args.bale)
    comparable = lambda value: {key: item for key, item in value.items() if key not in {"id", "share_token"}}
    conflicts = [ref for ref in set(telegram) & set(bale) if comparable(telegram[ref]) != comparable(bale[ref])]
    if conflicts:
        raise RuntimeError("Conflicting payment offers require manual owner review")
    merged = dict(telegram)
    merged.update(bale)

    args.target.parent.mkdir(parents=True, exist_ok=True)
    connection = sqlite3.connect(args.target, timeout=10)
    try:
        connection.execute("PRAGMA journal_mode=DELETE")
        connection.execute("PRAGMA synchronous=FULL")
        connection.executescript(PAYMENT_OFFERS_SCHEMA)
        insert_columns = [name for name in PAYMENT_OFFER_COLUMNS.split(",") if name != "id"]
        values = []
        used_tokens: set[str] = set()
        for key in sorted(merged):
            item = dict(merged[key])
            token = str(item.get("share_token") or "")
            while len(token) < 16 or token in used_tokens:
                token = secrets.token_urlsafe(16)
            item["share_token"] = token
            used_tokens.add(token)
            values.append(tuple(item[name] for name in insert_columns))
        placeholders = ",".join("?" for _ in insert_columns)
        connection.executemany(
            f"INSERT INTO payment_offers({','.join(insert_columns)}) VALUES({placeholders})",
            values,
        )
        connection.commit()
        if connection.execute("PRAGMA integrity_check").fetchone() != ("ok",):
            raise RuntimeError("Shared payment-offer SQLite integrity failed")
        if connection.execute("SELECT COUNT(*) FROM payment_offers").fetchone() != (len(merged),):
            raise RuntimeError("Shared payment-offer migration count mismatch")
    finally:
        connection.close()

    print(f"TELEGRAM_OFFERS={len(telegram)}")
    print(f"BALE_OFFERS={len(bale)}")
    print(f"SHARED_OFFERS={len(merged)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
