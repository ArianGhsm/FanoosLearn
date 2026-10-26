#!/usr/bin/env python3
"""Read-only provenance and source-map audit of private study batches.

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
from apply_classification import (
    chapter_nodes, flat, fragments, whole_chapter_is_unlisted,
    valid_non_residency_outside_scope_note,
    valid_residency_outside_scope_note,
)
from verified_reference_pdf import chapter_coverage, open_verified_reference
from retrospective_reference_policy import BASIS, is_retrospective
from retrospective_reference_policy import selected_reference_rows as selected_retrospective_rows
from updated_reference_proxy_policy import (
    UPDATED_PROXY_BASIS,
    UPDATED_PROXY_1397_EXAM_YEAR,
    UPDATED_PROXY_REFERENCE_YEAR,
    is_updated_reference_proxy,
    valid_updated_reference_note,
)
from same_lineage_proxy_policy import (
    BASIS as SAME_LINEAGE_PROXY_BASIS,
    is_proxy_exam_year,
    is_selected_proxy as is_selected_same_lineage_proxy,
    selected_non_residency_proxy_rows,
    selected_reference_rows as selected_same_lineage_rows,
    valid_proxy_note,
    fallback_error,
    requires_newest_first,
)

LEGACY_BATCH = re.compile(r"^(13[0-9]{2}|14[0-9]{2}):([a-z][a-z0-9-]*):([a-z][a-z0-9-]*)$")
TYPED_BATCH = re.compile(r"^(residency|national|board|promotion):(13[0-9]{2}|14[0-9]{2}):([1-9][0-9]{0,1}):([a-z][a-z0-9-]*):([a-z][a-z0-9-]*)$")
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
    typed = TYPED_BATCH.fullmatch(spec)
    legacy = LEGACY_BATCH.fullmatch(spec)
    if typed:
        exam_type, year, round_s, subject, stem = typed.groups()
        round_n = int(round_s)
        max_round = 99 if exam_type in {"board", "promotion"} else 9
        if round_n > max_round:
            raise ValueError(f"{spec}: round exceeds the supported limit for {exam_type}")
        if exam_type == "residency" and round_n != 1:
            raise ValueError(f"{spec}: residency batches currently support round 1 only")
    elif legacy:
        year, subject, stem = legacy.groups()
        exam_type, round_n = "residency", 1
    else:
        raise ValueError(f"Invalid batch spec {spec!r}: expected YEAR:subject:stem or TYPE:YEAR:ROUND:subject:stem")
    year_n = int(year)
    if year_n < (1300 if exam_type == "residency" else 1350) or year_n > 1500:
        raise ValueError(f"{spec}: year is outside the supported exam-type range")
    path_input = root / "bank-sittings" / year / f"{stem}-study.json"
    path_output = root / "classification" / "sittings" / f"{year}-{stem}-validated.json"
    path_decisions = (root / "classification" / "decisions" / f"{year}-{subject}.json"
                      if exam_type == "residency" else
                      root / "classification" / "decisions" / f"{exam_type}-{year}-{round_n}" / f"{year}-{subject}.json")
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
    source_type = source.get("exam_type", "residency")
    source_round = source.get("round", 1)
    result_type = result.get("exam_type", "residency")
    result_round = result.get("round", 1)
    if (source.get("year") != year_n or result.get("year") != year_n
            or source_type != exam_type or source_round != round_n
            or result_type != source_type or result_round != source_round):
        raise ValueError(f"{spec}: year or exam type mismatch")
    if (not isinstance(source_round, int) or isinstance(source_round, bool)
            or not isinstance(result_round, int) or isinstance(result_round, bool)):
        raise ValueError(f"{spec}: study and validated round must be integers")
    if exam_type != "residency" and ("exam_type" not in source or "round" not in source
                                     or "exam_type" not in result or "round" not in result):
        raise ValueError(f"{spec}: non-residency study and validated files must record exam_type and round")
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
    chapter_maps = json.loads(CHAPTERS.read_text(encoding="utf-8"))["editions"]
    catalog_path = CHAPTERS.parent / "catalog.json"
    pdf_catalog_path = CHAPTERS.parent / "reference-pdfs.json"
    catalog = json.loads(catalog_path.read_text(encoding="utf-8")) if catalog_path.is_file() else None
    pdf_catalog = json.loads(pdf_catalog_path.read_text(encoding="utf-8"))["editions"] if pdf_catalog_path.is_file() else {}
    is_residency = exam_type == "residency"
    retrospective = is_residency and is_retrospective(year_n)
    proxy_decisions = [d for d in wanted.values()
                       if d.get("classification_basis") == UPDATED_PROXY_BASIS]
    updated_proxy = bool(proxy_decisions)
    same_lineage_decisions = [d for d in wanted.values()
                              if d.get("classification_basis") == SAME_LINEAGE_PROXY_BASIS]
    same_lineage_proxy = bool(same_lineage_decisions)
    if not is_residency and (updated_proxy or any(
            "updated_reference_note" in d
            or d.get("classification_basis") not in (None, SAME_LINEAGE_PROXY_BASIS)
            or ("reference_year" in d
                and d.get("classification_basis") != SAME_LINEAGE_PROXY_BASIS)
            for d in wanted.values())):
        raise ValueError(f"{spec}: non-residency cross-year metadata requires the selected same-lineage proxy basis")
    if updated_proxy and same_lineage_proxy:
        raise ValueError(f"{spec}: updated-reference and same-lineage proxy bases cannot be mixed")
    if updated_proxy:
        if (not is_residency or year_n not in (UPDATED_PROXY_1397_EXAM_YEAR, 1398)
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
    elif same_lineage_proxy:
        if ((is_residency and not is_proxy_exam_year(year_n))
                or (not is_residency and exam_type not in ("national", "board", "promotion"))
                or len(same_lineage_decisions) != len(wanted)
                or catalog is None
                or any(not is_selected_same_lineage_proxy(
                    catalog, exam_type, year_n, subject, d.get("edition"),
                    d.get("reference_year"), d.get("classification_basis"),
                    pdf_catalog, chapter_maps)
                    or not valid_proxy_note(d.get("proxy_note"), year_n, d.get("reference_year"))
                    or not _same_lineage_proxy_scope_valid(
                        d, exam_type, year_n, subject, catalog, pdf_catalog,
                        chapter_maps, is_residency)
                    for d in wanted.values())):
            raise ValueError(f"{spec}: every same-lineage proxy decision must match the selected verified edition/year and carry an explicit proxy note")
        reference_years = sorted({d["reference_year"] for d in wanted.values()})
    else:
        if any("reference_year" in d or "classification_basis" in d
               for d in wanted.values()) and not retrospective:
            raise ValueError(f"{spec}: cross-year reference metadata is not allowed")
        reference_years = sorted({d.get("reference_year") for d in wanted.values()}) if retrospective else [year_n]
    if retrospective:
        for d in wanted.values():
            if (d.get("classification_basis") != BASIS
                    or not isinstance(d.get("reference_year"), int)
                    or isinstance(d.get("reference_year"), bool)
                    or d["reference_year"] < 1397):
                raise ValueError(f"{spec}: missing explicit 1397+ retrospective reference-year provenance")
    if ((is_residency and requires_newest_first(year_n))
            or (same_lineage_proxy and not is_residency)) and not updated_proxy:
        if catalog is None:
            raise ValueError(f"{spec}: catalog is required to verify the newest-first edition order")
        select = (selected_retrospective_rows if retrospective else
                  selected_non_residency_proxy_rows if not is_residency else
                  selected_same_lineage_rows)
        candidates = select(catalog, exam_type, year_n, subject, pdf_catalog, chapter_maps)
        for d in wanted.values():
            order_error = fallback_error(candidates, d.get("edition"), d.get("newer_editions_searched"))
            if order_error:
                raise ValueError(f"{spec}: Q{d['number']}: {order_error}")
    if not wanted.keys() <= original.keys():
        raise ValueError(f"{spec}: decision question not in exact sitting")
    source_pdf_provenance = {}
    verified_pdfs = {}
    if updated_proxy:
        if catalog is None:
            raise ValueError(f"{spec}: catalog is required to verify the updated proxy's official reference year")
        for decision in wanted.values():
            edition = str(decision["edition"])
            chapter = str(decision["chapter"])
            reference_subject = str(decision.get("reference_subject", subject))
            validity = [row for row in catalog.get("validity", [])
                        if row.get("exam_type") == exam_type
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
    if (not is_residency and not same_lineage_proxy and catalog is None
            and any("outside_scope_note" in decision for decision in wanted.values())):
        raise ValueError(f"{spec}: catalog is required to independently verify outside-scope claims")
    if not is_residency and not same_lineage_proxy and catalog is not None:
        for decision in wanted.values():
            edition = str(decision["edition"])
            rows = [row for row in catalog.get("validity", [])
                    if row.get("exam_type") == exam_type
                    and row.get("year") == year_n
                    and row.get("subject") == subject
                    and row.get("edition") == edition
                    and row.get("official") is True]
            if len(rows) != 1:
                raise ValueError(f"{spec}: exact non-residency citation lacks one official exam-year validity row")
            if "outside_scope_note" in decision:
                if (not whole_chapter_is_unlisted(rows[0], str(decision.get("chapter")))
                        or not valid_non_residency_outside_scope_note(
                            decision.get("outside_scope_note"), exam_type, year_n)):
                    raise ValueError(f"{spec}: exact outside-scope decision lacks a structured unlisted chapter and valid Persian note")
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
        if same_lineage_proxy and (
                item.get("classification_basis") != SAME_LINEAGE_PROXY_BASIS
                or item.get("reference_year") != decision.get("reference_year")
                or item.get("classification_note") != decision.get("proxy_note")
                or item.get("official_scope_status") != (
                    "outside_scope_exam_question" if "outside_scope_note" in decision else "in_scope")
                or item.get("official_scope_note") != decision.get("outside_scope_note")
                or item.get("scope_unverified") is not False):
            raise ValueError(f"{spec}: same-lineage proxy label/reference year differs for Q{n}")
        if is_residency and (retrospective or same_lineage_proxy):
            if "outside_scope_note" in decision:
                if (item.get("official_scope_status") != "outside_scope_exam_question"
                        or item.get("official_scope_note") != decision.get("outside_scope_note")
                        or not valid_residency_outside_scope_note(decision.get("outside_scope_note"), year_n)):
                    raise ValueError(f"{spec}: residency outside-scope status/note differs for Q{n}")
            elif item.get("official_scope_status", "in_scope") != "in_scope":
                raise ValueError(f"{spec}: unexplained outside-scope status for Q{n}")
        if not is_residency:
            if "outside_scope_note" in decision:
                if (item.get("official_scope_status") != "outside_scope_exam_question"
                        or item.get("official_scope_note") != decision.get("outside_scope_note")
                        or not valid_non_residency_outside_scope_note(
                            decision.get("outside_scope_note"), exam_type, year_n)):
                    raise ValueError(f"{spec}: outside-scope status/note differs for Q{n}")
            elif item.get("official_scope_status", "in_scope") != "in_scope":
                raise ValueError(f"{spec}: unexplained outside-scope status for Q{n}")
        if not updated_proxy and not same_lineage_proxy and retrospective:
            if (item.get("classification_basis") != BASIS
                    or item.get("reference_year") != decision.get("reference_year")):
                raise ValueError(f"{spec}: retrospective proxy label/reference year differs for Q{n}")
        if not updated_proxy and not same_lineage_proxy and not retrospective:
            if (item.get("classification_basis") != "official_exam_year"
                    or item.get("reference_year") != year_n):
                raise ValueError(f"{spec}: exact-year source label/reference year differs for Q{n}")
        pages[str(n)] = {
            "ref": item["ref"],
            "reference_subject": decision.get("reference_subject", subject),
            "reference_year": item.get("reference_year"),
            "classification_basis": item.get("classification_basis"),
            "pdf_page": int(decision["page"]),
            "confidence_source": round(float(confidence["source"]), 3),
            "confidence_node": round(float(confidence["node"]), 3),
            "confidence_page": round(float(confidence["page"]), 3),
        }
    result = {
        "batch": spec,
        "classification_basis": (BASIS if retrospective
                                  else UPDATED_PROXY_BASIS if updated_proxy
                                  else SAME_LINEAGE_PROXY_BASIS if same_lineage_proxy
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
    if not is_residency:
        result["exam_type"] = exam_type
        result["round"] = round_n
    return result


def _same_lineage_proxy_scope_valid(
    decision: dict, exam_type: str, year: int, subject: str,
    catalog: dict, pdf_catalog: dict, chapter_maps: dict, is_residency: bool,
) -> bool:
    """Check the cited proxy chapter against its selected official scope row."""
    rows = (selected_same_lineage_rows(catalog, exam_type, year, subject, pdf_catalog, chapter_maps)
            if is_residency else selected_non_residency_proxy_rows(
                catalog, exam_type, year, subject, pdf_catalog, chapter_maps))
    match = next((row for row in rows
                  if row.get("_proxy") is True
                  and row.get("edition") == decision.get("edition")
                  and row.get("year") == decision.get("reference_year")), None)
    if match is None:
        return False
    chapter = str(decision.get("chapter"))
    if chapter in {str(item.get("number")) for item in match.get("scope_chapters", [])
                   if isinstance(item, dict)}:
        return "outside_scope_note" not in decision
    if is_residency:
        return (
            "outside_scope_note" in decision
            and whole_chapter_is_unlisted(match, chapter)
            and valid_residency_outside_scope_note(decision.get("outside_scope_note"), year)
        )
    return (
        "outside_scope_note" in decision
        and whole_chapter_is_unlisted(match, chapter)
        and valid_non_residency_outside_scope_note(
            decision.get("outside_scope_note"), exam_type, year)
    )


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
                        help="YEAR:subject:stem for residency round 1, or TYPE:YEAR:ROUND:subject:stem; may repeat")
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
