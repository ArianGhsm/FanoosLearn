#!/usr/bin/env python3
"""Read-only provenance and source-map audit of private residency study batches.

This is NOT a production importer. Audit works only on previously validated
study-only sittings; never export question stems or copyrighted page text.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import tempfile

BATCH = re.compile(r"^(13[0-9]{2}|14[0-9]{2}):([a-z][a-z0-9-]*):([a-z][a-z0-9-]*)$")


def digest(path: Path) -> str:
    block_hash = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            block_hash.update(block)
    return block_hash.hexdigest()


def load(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))


def audit_batch(root: Path, spec: str) -> dict:
    m = BATCH.fullmatch(spec)
    if not m:
        raise ValueError(f"Invalid batch spec {spec!r}: expected YEAR:subject:stem")
    year, subject, stem = m.groups()
    year_n = int(year)
    path_input = root / "bank-sittings" / year / f"{stem}-study.json"
    path_output = root / "classification" / "sittings" / f"{year}-{stem}-validated.json"
    path_decisions = root / "classification" / "decisions" / f"{year}-{subject}.json"
    source = load(path_input)
    result = load(path_output)
    decisions = load(path_decisions)
    if source.get("format") != "fanoos.classification.study-only/1" or result.get("format") != source["format"]:
        raise ValueError(f"{spec}: only private study-only sittings permitted")
    if source.get("year") != year_n or result.get("year") != year_n or source.get("exam_type") != "residency":
        raise ValueError(f"{spec}: year or exam type mismatch")
    original = {int(q["number"]): q for q in source["questions"]}
    mapped = {int(q["number"]): q for q in result["questions"]}
    if len(original) != len(source["questions"]) or original.keys() != mapped.keys():
        raise ValueError(f"{spec}: question identity/count changed")
    if any(q.get("sources") for q in source["questions"]):
        raise ValueError(f"{spec}: source sitting is not entirely unsourced")
    for number in original:
        without_new_sources = {k: v for k, v in mapped[number].items() if k != "sources"}
        if original[number] != without_new_sources:
            raise ValueError(f"{spec}: question or answer content changed, Q{number}")
    wanted = {int(d["number"]): d for d in decisions if "edition" in d}
    if len(wanted) != len(decisions) or len(wanted) != len(decisions):
        raise ValueError(f"{spec}: duplicate or unsupported decisions")
    if not wanted.keys() <= original.keys():
        raise ValueError(f"{spec}: decision question not in exact sitting")
    actual = {n for n, q in mapped.items() if q.get("sources")}
    if actual != wanted.keys():
        raise ValueError(f"{spec}: validated source set and decision set differ")
    pages = {}
    for n, decision in wanted.items():
        assigned = mapped[n]["sources"]
        if len(assigned) != 1:
            raise ValueError(f"{spec}: expected exactly one new source for Q{n}")
        item = assigned[0]
        expected_ref_prefix = str(decision["edition"]) + "#ch"
        if not str(item.get("ref", "")).startswith(expected_ref_prefix):
            raise ValueError(f"{spec}: wrong reference map for Q{n}")
        suffix = str(item["ref"]).removeprefix(expected_ref_prefix)
        if suffix != str(decision["chapter"]).zfill(2):
            raise ValueError(f"{spec}: wrong chapter map for Q{n}")
        if str(item.get("page")) not in {str(decision["page"]), f'pdf {decision["page"]}'} or item.get("origin") != "ai":
            raise ValueError(f"{spec}: wrong page or origin for Q{n}")
        confidence = item.get("confidence", {})
        if min(float(confidence.get(k, 0)) for k in ("source", "node", "page")) < 0.85:
            raise ValueError(f"{spec}: unsupported confidence for Q{n}")
        pages[str(n)] = {"ref": item["ref"], "pdf_page": int(decision["page"])}
    return {
        "batch": spec,
        "exam_year": year_n,
        "subject": subject,
        "questions": len(original),
        "accepted": len(wanted),
        "pending": len(original) - len(wanted),
        "question_content_identical": True,
        "study_sha256": digest(path_input),
        "decisions_sha256": digest(path_decisions),
        "validated_sha256": digest(path_output),
        "mapped": pages,
    }


def save_private(root: Path, out: Path, data: dict) -> None:
    allowed = (root / "classification" / "reports").resolve(strict=True)
    parent = out.parent.resolve(strict=True)
    if parent != allowed or out.is_symlink():
        raise ValueError("Report output must be in the protected classification/reports directory")
    payload = json.dumps(data, ensure_ascii=False, sort_keys=True, indent=2) + "\n"
    if out.exists():
        if out.read_text(encoding="utf-8") != payload:
            raise ValueError("Existing audit differs; choose a new checkpoint filename")
        return
    fd, temporary = tempfile.mkstemp(dir=parent, prefix=".audit-")
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as stream:
            os.fchmod(stream.fileno(), 0o600)
            stream.write(payload)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, out)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--local", type=Path, default=Path("/srv/fanoos/shared/research"))
    parser.add_argument("--batch", action="append", required=True,
                        help="YEAR:subject:stem; may repeat, e.g. 1405:oral-radiology:radiology")
    parser.add_argument("--out", type=Path, help="Private report path under classification/reports")
    args = parser.parse_args()
    audited = [audit_batch(args.local, spec) for spec in args.batch]
    total = {
        "format": "fanoos.classification.provenance-audit/1",
        "research_only": True,
        "batch_count": len(audited),
        "questions": sum(x["questions"] for x in audited),
        "accepted": sum(x["accepted"] for x in audited),
        "pending": sum(x["pending"] for x in audited),
        "batches": audited,
    }
    if args.out:
        save_private(args.local, args.out, total)
    print(json.dumps({k: v for k, v in total.items() if k != "batches"}, ensure_ascii=False))


if __name__ == "__main__":
    main()
