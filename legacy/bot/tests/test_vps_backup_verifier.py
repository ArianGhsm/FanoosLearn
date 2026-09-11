from __future__ import annotations

import hashlib
import importlib.util
import json
import sqlite3
from pathlib import Path

import pytest


SCRIPT = Path(__file__).resolve().parents[1] / "scripts" / "verify_vps_backup.py"
SPEC = importlib.util.spec_from_file_location("verify_vps_backup", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
verifier = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(verifier)


def build_fixture(root: Path) -> None:
    for relative in (
        "config/dent-bot.env",
        "config/bale-bot.env",
        "config/deploy-notifier.env",
        "config/telegram-egress.json",
        "metadata/service-enabled.txt",
        "metadata/service-active.txt",
        "metadata/snapshot-id.txt",
    ):
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text("fixture\n", encoding="utf-8")
    (root / "runtime/deploy-notifier").mkdir(parents=True)
    for relative in ("runtime/dent-bot/state.sqlite3", "runtime/bale-bot/state.sqlite3"):
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        connection = sqlite3.connect(path)
        try:
            for table in verifier.BOT_REQUIRED_TABLES:
                connection.execute(f"CREATE TABLE {table} (id INTEGER PRIMARY KEY)")
            connection.commit()
        finally:
            connection.close()
    shared = root / "runtime/shared/payment-offers.sqlite3"
    shared.parent.mkdir(parents=True, exist_ok=True)
    connection = sqlite3.connect(shared)
    try:
        connection.execute("CREATE TABLE payment_offers (id TEXT PRIMARY KEY)")
        connection.commit()
    finally:
        connection.close()
    files = {
        path.relative_to(root).as_posix(): hashlib.sha256(path.read_bytes()).hexdigest()
        for path in root.rglob("*")
        if path.is_file() and path.name != "SHA256SUMS.json"
    }
    (root / "SHA256SUMS.json").write_text(json.dumps(files), encoding="utf-8")


def rewrite_manifest(root: Path) -> None:
    files = {
        path.relative_to(root).as_posix(): hashlib.sha256(path.read_bytes()).hexdigest()
        for path in root.rglob("*")
        if path.is_file() and path.name != "SHA256SUMS.json"
    }
    (root / "SHA256SUMS.json").write_text(json.dumps(files), encoding="utf-8")


def test_complete_snapshot_is_accepted(tmp_path: Path) -> None:
    build_fixture(tmp_path)
    result = verifier.verify(tmp_path)
    assert result["valid"] is True
    assert result["sqlite_databases"] == 3


def test_empty_marker_only_snapshot_is_rejected(tmp_path: Path) -> None:
    marker = tmp_path / "metadata/snapshot-id.txt"
    marker.parent.mkdir(parents=True)
    marker.write_text("fixture", encoding="utf-8")
    rewrite_manifest(tmp_path)
    with pytest.raises(RuntimeError, match="missing required production state"):
        verifier.verify(tmp_path)


def test_missing_bot_schema_is_rejected(tmp_path: Path) -> None:
    build_fixture(tmp_path)
    database = tmp_path / "runtime/dent-bot/state.sqlite3"
    connection = sqlite3.connect(database)
    try:
        connection.execute("DROP TABLE booklet_issuances")
        connection.commit()
    finally:
        connection.close()
    rewrite_manifest(tmp_path)
    with pytest.raises(RuntimeError, match="missing required tables"):
        verifier.verify(tmp_path)


def test_unchecksummed_extra_file_is_rejected(tmp_path: Path) -> None:
    build_fixture(tmp_path)
    extra = tmp_path / "runtime/untracked.bin"
    extra.write_bytes(b"unexpected")
    with pytest.raises(RuntimeError, match="outside its checksum manifest"):
        verifier.verify(tmp_path)
