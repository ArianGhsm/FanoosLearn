#!/usr/bin/env python3
"""Reject invalid UTF-8 and common irreversible Persian-text corruption."""

from __future__ import annotations

import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
TEXT_SUFFIXES = {".py", ".ps1", ".sh", ".md", ".json", ".toml", ".yaml", ".yml", ".service"}
SKIP_PARTS = {".git", ".codex-local", "__pycache__"}


def main() -> int:
    failures: list[str] = []
    checked = 0
    for path in ROOT.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in TEXT_SUFFIXES:
            continue
        if any(part in SKIP_PARTS for part in path.parts):
            continue
        checked += 1
        try:
            text = path.read_bytes().decode("utf-8")
        except UnicodeDecodeError:
            failures.append(f"{path.relative_to(ROOT)}: invalid UTF-8")
            continue
        for line_number, line in enumerate(text.splitlines(), 1):
            if "\ufffd" in line:
                failures.append(f"{path.relative_to(ROOT)}:{line_number}: replacement character")
            if "?" * 4 in line:
                failures.append(f"{path.relative_to(ROOT)}:{line_number}: repeated question-mark corruption")
            mojibake_markers = sum(line.count(chr(codepoint)) for codepoint in (0x00D8, 0x00D9, 0x00DA, 0x00DB))
            if mojibake_markers >= 3:
                failures.append(f"{path.relative_to(ROOT)}:{line_number}: likely UTF-8 mojibake")
    if failures:
        print("\n".join(failures), file=sys.stderr)
        return 1
    print(f"OK: {checked} integration text files passed UTF-8 integrity checks.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
