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
import sys
import tempfile

sys.path.insert(0, str(Path(__file__).resolve().parent))
from apply_classification import chapter_nodes, flat, fragments
from verified_reference_pdf import chapter_coverage, open_verified_reference
from retrospective_reference_policy import BASIS, is_retrospective
from updated_reference_proxy_policy import (
    UPDATED_PROXY_BASIS,
    UPDATED_PROXY_1397_EXAM_YEAR,
    UPDATED_PROXY_REFERENCE_YEAR,
    is_updated_reference_proxy,
    valid_updated_reference_note,
)

BATCH = re.compile(r"^(13[0-9]{2}|14[0-9]{2}):([a-z][a-z0-9-]*):([a-z][a-z0-9-]*)$")
CHAPTERS = Path(__file__).resolve().parents[2] / "data/bank/reference-chapter-pages.json"


def digest(path: Path) -> str:
    block_hash = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            block_hash.update(block)
    return block_hash.hexdigest()


def load(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))



def _printed_labels(text: str) -> set[int]:
    """Strictly recognize short numeric running heads/feet, not body numbers."""
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    numbers: set[int] = set()
    for line in lines[:3] + lines[-3:]:
        if len(line) >= 90:
            continue
        match = re.match(r"^(\d{1,4})(?:\s|$)", line) or re.search(r"(?:^|\s)(\d{1,4})$", line)
        if match:
            numbers.add(int(match.group(1)))
    return numbers


def _verify_pdf_printed_page(pdf, pdf_page: int, value: str) -> bool:
    """Accept book-printed labels only with actual-page AND adjacent-page evidence.

    Deliberately stricter than the primary text validator: a stray chapter
    number on one page may be mistaken for its printed page number. Require
    at least two independently adjacent page labels with matching offsets.
    """
    try:
        printed = int(value)
    except (TypeError, ValueError):
        return False
    if not 1 <= pdf_page or not 1 <= printed:
        return False
    if printed not in _printed_labels(pdf.page_text(pdf_page)):
        return False
    corroborated = sum(
        printed + step in _printed_labels(pdf.page_text(pdf_page + step))
        for step in (-2, -1, 1, 2)
        if 1 <= pdf_page + step <= pdf.page_count and printed + step > 0
    )
    return corroborated >= 2

def audit_batch(root: Path, spec: str,
                storage_root: Path = Path("/srv/fanoos/shared/storage"),
                mysql_defaults: Path = Path("/etc/fanoos/mysql-migrator.cnf"),
                database: str = "fanoos_prod") -> dict:
    m = BATCH.fullmatch(spec)
    if not m:
        raise ValueError(f"Invalid batch spec {spec!r}: expected YEAR:subject:stem")
    year, subject, stem = m.groups()
    year_n = int(year)
    path_input = root / "bank-sittings" / year / f"{stem}-study.json"
    path_output = root / "classification" / "sittings" / f"{year}-{stem}-validated.json"
    path_decisions = root / "classification" / "decisions" / f"{year}-{subject}.json"
    if year_n == 1398 and subject == "periodontics" and stem in {"periodontics-followup", "periodontics-certified-20261012", "periodontics-q134-20261012"}:
        path_decisions = root / "classification" / "decisions" / f"1398-{stem}.json"
    if (year_n, subject, stem) == (1398, "operative-dentistry", "operative-q175-20261012"):
        path_decisions = root / "classification" / "decisions" / "1398-operative-q175-20261012.json"
    if year_n == 1398 and (subject, stem) in {('pediatric-dentistry', 'pediatric-q164-q168-20261012'), ('oral-radiology', 'radiology-four-20261012'), ('pediatric-dentistry', 'pediatric-q159-20261012'), ('oral-radiology', 'radiology-q219-q226-20261012'), ('pediatric-dentistry', 'pediatric-q152-20261012'), ('oral-radiology', 'radiology-seven-20261012'), ('oral-radiology', 'radiology-q218-20261012'), ('oral-radiology', 'radiology-q222-20261012'), ('oral-radiology', 'radiology-q213-q216-20261013'), ('oral-radiology', 'radiology-q220-20261013')}:
        path_decisions = root / 'classification' / 'decisions' / f'1398-{stem}.json'
    source = load(path_input)
    result = load(path_output)
    decisions = load(path_decisions)
    if source.get("format") != "fanoos.classification.study-only/1" or result.get("format") != source["format"]:
        raise ValueError(f"{spec}: only private study-only sittings permitted")
    if (source.get("year") != year_n or result.get("year") != year_n
            or source.get("exam_type") != "residency"
            or result.get("exam_type") != source["exam_type"]
            or result.get("round") != source.get("round")):
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
    if len(wanted) != len(decisions):
        raise ValueError(f"{spec}: duplicate or unsupported decisions")
    proxy_decisions = [d for d in wanted.values()
                       if d.get("classification_basis") == UPDATED_PROXY_BASIS]
    updated_proxy = bool(proxy_decisions)
    if updated_proxy:
        if (year_n not in (UPDATED_PROXY_1397_EXAM_YEAR, 1398)
                or len(proxy_decisions) != len(wanted)
                or any(not is_updated_reference_proxy(
                    year_n, subject, d.get("edition"), d.get("reference_year"),
                    d.get("classification_basis"), d.get("reference_subject"),
                    d.get("number"))
                    or not valid_updated_reference_note(d.get("updated_reference_note"), year_n)
                    or (d.get("reference_subject", subject) != subject
                        and "جراحی دهان" not in str(d.get("updated_reference_note", "")))
                    for d in wanted.values())):
            raise ValueError(f"{spec}: updated-reference proxy is restricted to the owner-authorized 1397/1398 cases and must label every decision")
        reference_years = [UPDATED_PROXY_REFERENCE_YEAR]
    else:
        if any("reference_year" in d or "classification_basis" in d
               for d in wanted.values()) and not is_retrospective(year_n):
            raise ValueError(f"{spec}: cross-year reference metadata is not allowed")
        reference_years = sorted({d.get("reference_year") for d in wanted.values()}) if is_retrospective(year_n) else [year_n]
    if is_retrospective(year_n):
        for d in wanted.values():
            if (d.get("classification_basis") != BASIS
                    or not isinstance(d.get("reference_year"), int)
                    or isinstance(d.get("reference_year"), bool)
                    or d["reference_year"] < 1397):
                raise ValueError(f"{spec}: missing explicit 1397+ retrospective reference-year provenance")
    if not wanted.keys() <= original.keys():
        raise ValueError(f"{spec}: decision question not in exact sitting")
    chapter_maps = json.loads(CHAPTERS.read_text(encoding="utf-8"))["editions"]
    source_pdf_provenance = {}
    verified_pdfs = {}
    catalog_path = CHAPTERS.parent / "catalog.json"
    catalog = json.loads(catalog_path.read_text(encoding="utf-8")) if catalog_path.is_file() else None
    if updated_proxy:
        if catalog is None:
            raise ValueError(f"{spec}: catalog is required to verify the updated proxy's official reference year")
        for decision in wanted.values():
            edition = str(decision["edition"])
            chapter = str(decision["chapter"])
            reference_subject = str(decision.get("reference_subject", subject))
            validity = [row for row in catalog.get("validity", [])
                        if row.get("exam_type") == "residency"
                        and row.get("year") == UPDATED_PROXY_REFERENCE_YEAR
                        and row.get("subject") == reference_subject
                        and row.get("edition") == edition
                        and row.get("official") is True]
            if len(validity) != 1:
                raise ValueError(f"{spec}: updated proxy edition lacks one exact official reference-year validity row")
            allowed = {str(item.get("number")) for item in validity[0].get("scope_chapters", [])
                       if isinstance(item, dict)}
            explicitly_outside_scope = (
                year_n == UPDATED_PROXY_1397_EXAM_YEAR
                and decision.get("outside_scope_note") == decision.get("updated_reference_note")
                and "سرفصل رسمی ۱۴۰۶" in str(decision.get("updated_reference_note", ""))
                and "خارج از محدوده" in str(decision.get("updated_reference_note", ""))
            )
            if chapter not in allowed and not explicitly_outside_scope:
                raise ValueError(f"{spec}: updated proxy chapter is outside the chosen reference-year syllabus")
    for edition in sorted({str(decision["edition"]) for decision in wanted.values()}):
        pdf = open_verified_reference(edition, storage_root, mysql_defaults, database)
        map_entry = chapter_maps.get(edition, {})
        chapter_coverage({"editions": {edition: map_entry}}, edition, pdf.page_count)
        mapped_source = map_entry.get("source_pdf_sha256")
        if not isinstance(mapped_source, str) or not re.fullmatch(r"[a-f0-9]{64}", mapped_source):
            raise ValueError(f"{spec}: chapter map for {edition} has no verified PDF source hash")
        if mapped_source != pdf.source_sha256:
            raise ValueError(f"{spec}: chapter map belongs to a different PDF for {edition}")
        verified_pdfs[edition] = pdf
        source_pdf_provenance[edition] = {
            "sha256": pdf.source_sha256,
            "pdf_pages": pdf.page_count,
            "source": "verified-server-pdf",
        }
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
        chapter_number = str(decision["chapter"])
        expected_node = (chapter_nodes(catalog, str(decision["edition"])).get(chapter_number)
                         if catalog is not None else None)
        if isinstance(expected_node, dict):
            expected_node_key = str(expected_node.get("key", ""))
        elif re.fullmatch(r"[1-9][0-9]?", chapter_number):
            expected_node_key = f"ch{int(chapter_number):02d}"
        else:
            raise ValueError(f"{spec}: catalog is required to resolve fractional chapter/section node for Q{n}")
        if not expected_node_key or item.get("ref") != f"{decision['edition']}#{expected_node_key}":
            raise ValueError(f"{spec}: wrong chapter map for Q{n}")
        map_entry = chapter_maps[str(decision["edition"])]
        if not any(isinstance(run, list) and len(run) == 3
                   and str(run[0]) == str(decision["chapter"])
                   and isinstance(run[1], int) and isinstance(run[2], int)
                   and run[1] <= int(decision["page"]) <= run[2]
                   for run in map_entry.get("runs", [])):
            raise ValueError(f"{spec}: PDF page is outside the exact mapped chapter or section for Q{n}")
        if item.get("origin") != "ai":
            raise ValueError(f"{spec}: wrong page or origin for Q{n}")
        label = str(item.get("page"))
        pdf_number = int(decision["page"])
        pdf = verified_pdfs[str(decision["edition"])]
        page_text = pdf.page_text(pdf_number)
        evidence_parts = fragments(str(decision.get("evidence", "")))
        if (not evidence_parts or any(len(part.split()) < 4 or flat(part) not in flat(page_text)
                                      for part in evidence_parts)):
            raise ValueError(f"{spec}: evidence quote is not printed on PDF page {pdf_number}")
        del page_text
        if label not in {str(pdf_number), f"pdf {pdf_number}"} and not _verify_pdf_printed_page(
                pdf, pdf_number, label):
            raise ValueError(f"{spec}: wrong page or origin for Q{n}")
        confidence = item.get("confidence", {})
        if (min(float(confidence.get(k, 0)) for k in ("source", "node", "page")) < 0.85
                or any(abs(float(confidence.get(k, 0)) - float(decision["confidence"])) > .0005
                       for k in ("source", "node", "page"))):
            raise ValueError(f"{spec}: unsupported confidence for Q{n}")
        if updated_proxy and (item.get("official_scope_status") != "outside_scope_exam_question"
                or item.get("official_scope_note") != decision.get("updated_reference_note")
                or item.get("reference_year") != UPDATED_PROXY_REFERENCE_YEAR
                or item.get("classification_basis") != UPDATED_PROXY_BASIS
                or item.get("reference_subject", subject) != decision.get("reference_subject", subject)
                or item.get("scope_unverified") is not False):
            raise ValueError(f"{spec}: updated proxy disposition or reference-year label differs for Q{n}")
        pages[str(n)] = {
            "ref": item["ref"],
            "reference_subject": decision.get("reference_subject", subject),
            "pdf_page": int(decision["page"]),
            "confidence_source": round(float(confidence["source"]), 3),
            "confidence_node": round(float(confidence["node"]), 3),
            "confidence_page": round(float(confidence["page"]), 3),
        }
    return {
        "batch": spec,
        "classification_basis": (BASIS if is_retrospective(year_n)
                                  else UPDATED_PROXY_BASIS if updated_proxy
                                  else "official_exam_year"),
        "reference_years": reference_years,
        "exam_year": year_n,
        "subject": subject,
        "questions": len(original),
        "accepted": len(wanted),
        "pending": len(original) - len(wanted),
        "question_content_identical": True,
        "study_sha256": digest(path_input),
        "decisions_sha256": digest(path_decisions),
        "validated_sha256": digest(path_output),
        "source_pdf_provenance": source_pdf_provenance,
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
    parser.add_argument("--storage-root", type=Path, default=Path("/srv/fanoos/shared/storage"))
    parser.add_argument("--mysql-defaults", type=Path, default=Path("/etc/fanoos/mysql-migrator.cnf"))
    parser.add_argument("--database", default="fanoos_prod")
    parser.add_argument("--batch", action="append", required=True,
                        help="YEAR:subject:stem; may repeat, e.g. 1405:oral-radiology:radiology")
    parser.add_argument("--out", type=Path, help="Private report path under classification/reports")
    args = parser.parse_args()
    if len(set(args.batch)) != len(args.batch):
        raise ValueError("Duplicate private audit batch requested")
    audited = [audit_batch(args.local, spec, args.storage_root, args.mysql_defaults, args.database)
               for spec in args.batch]
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
