#!/usr/bin/env python3
"""Check exact server PDFs without writing extracted text or search indexes.

The tool resolves each edition to its current approved private PDF and reports
only hashes, page counts and readability counts. Page text is requested from
Poppler one page at a time in memory and immediately discarded.
"""
from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
MANIFEST = REPO / "data/bank/reference-pdfs.json"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("--only", action="append", required=True,
                        help="exact edition to verify; repeat for multiple editions")
    parser.add_argument("--check-readable", action="store_true",
                        help="inspect each page transiently and report counts only")
    parser.add_argument("--storage-root", type=Path, default=Path("/srv/fanoos/shared/storage"))
    parser.add_argument("--mysql-defaults", type=Path, default=Path("/etc/fanoos/mysql-migrator.cnf"))
    parser.add_argument("--database", default="fanoos_prod")
    parser.add_argument("--min-ratio", type=float, default=0.80)
    args = parser.parse_args()
    if not 0.5 <= args.min_ratio <= 1:
        raise ValueError("invalid minimum readable-page ratio")

    editions = json.loads(MANIFEST.read_text(encoding="utf-8"))["editions"]
    failed = 0
    for edition in dict.fromkeys(args.only):
        entry = editions.get(edition)
        if not isinstance(entry, dict):
            failed += 1
            print(f"FAILED   {edition}: edition is not in the official PDF manifest")
            continue
        if entry.get("missing"):
            print(f"pending  {edition}: exact verified server PDF is unavailable")
            continue
        if entry.get("pdf") != "verified-server-pdf":
            failed += 1
            print(f"FAILED   {edition}: manifest does not require its exact verified PDF")
            continue
        command = [
            sys.executable, "-B", str(REPO / "scripts/references/verified_reference_pdf.py"),
            "--edition", edition, "--storage-root", str(args.storage_root),
            "--mysql-defaults", str(args.mysql_defaults), "--database", args.database,
        ]
        if args.check_readable:
            command.extend(["--check-readable", "--min-ratio", str(args.min_ratio)])
        try:
            result = subprocess.run(command, check=False, text=True, capture_output=True, timeout=1800)
        except subprocess.TimeoutExpired:
            failed += 1
            print(f"FAILED   {edition}: PDF verification timed out")
            continue
        if result.returncode:
            failed += 1
            print(f"FAILED   {edition}: verified PDF check failed")
            if result.stderr.strip():
                print(result.stderr.strip())
            continue
        try:
            receipt = json.loads(result.stdout.strip())
        except json.JSONDecodeError:
            failed += 1
            print(f"FAILED   {edition}: PDF check returned no JSON receipt")
            continue
        print(json.dumps(receipt, ensure_ascii=False, sort_keys=True))
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
