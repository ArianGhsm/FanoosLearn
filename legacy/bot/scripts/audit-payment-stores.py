#!/usr/bin/env python3
"""Report integrity and offer counts for restored bot payment stores."""

from __future__ import annotations

import argparse
import sqlite3
from pathlib import Path


STORE_PATHS = (
    "runtime/shared/payment-offers.sqlite3",
    "runtime/dent-bot/state.sqlite3",
    "runtime/bale-bot/state.sqlite3",
)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("root", type=Path)
    args = parser.parse_args()
    root = args.root.resolve()
    for relative in STORE_PATHS:
        path = (root / relative).resolve()
        if root not in path.parents or not path.is_file():
            print(f"{relative}|missing")
            continue
        connection = sqlite3.connect(f"file:{path.as_posix()}?mode=ro", uri=True)
        try:
            integrity = connection.execute("PRAGMA integrity_check").fetchone()[0]
            table = connection.execute(
                "SELECT 1 FROM sqlite_master WHERE type='table' AND name='payment_offers'"
            ).fetchone()
            offers = connection.execute("SELECT COUNT(*) FROM payment_offers").fetchone()[0] if table else "no-table"
        finally:
            connection.close()
        print(f"{relative}|integrity={integrity}|offers={offers}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
