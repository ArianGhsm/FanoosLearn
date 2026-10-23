"""Owner-authorized newest-first same-lineage editions for older exams.

This policy covers residency exam years 1396–1398 (pre-1396 sittings use
`retrospective_reference_policy`, which applies the same newest-first order
to 1397+ lists). Owner rule 2026-10-23: classify against the newest available
official edition of each reference family; fall back to an older edition
only when the answer-bearing page is not found in the newer ones. Every
returned row retains its actual official reference year. The downstream page
reader must still verify the current approved PDF, its matching chapter map,
page and direct evidence.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

REFERENCE_TOOLS = str(Path(__file__).resolve().parent)
if REFERENCE_TOOLS not in sys.path:
    sys.path.insert(0, REFERENCE_TOOLS)

from verified_reference_pdf import chapter_coverage


BASIS = "same_lineage_proxy"
FIRST_PROXY_EXAM_YEAR = 1396
LAST_PROXY_EXAM_YEAR = 1398
# 1397 and 1398 are complete (named updated-reference exceptions); the
# newest-first fallback order is enforced for the unfinished older sittings.
LAST_NEWEST_FIRST_EXAM_YEAR = 1396


def is_proxy_exam_year(exam_year: object) -> bool:
    return (isinstance(exam_year, int) and not isinstance(exam_year, bool)
            and FIRST_PROXY_EXAM_YEAR <= exam_year <= LAST_PROXY_EXAM_YEAR)


def requires_newest_first(exam_year: object) -> bool:
    return (isinstance(exam_year, int) and not isinstance(exam_year, bool)
            and 1300 <= exam_year <= LAST_NEWEST_FIRST_EXAM_YEAR)


def _family(edition: str) -> str:
    return edition.partition("@")[0]


def _verified_pdf_available(edition: str, pdfs: dict) -> bool:
    return (pdfs.get(edition) or {}).get("pdf") == "verified-server-pdf"


def _usable_scope(row: dict) -> bool:
    chapters = row.get("scope_chapters")
    return (
        isinstance(chapters, list) and bool(chapters)
        and all(isinstance(item, dict)
                and isinstance(item.get("number"), str)
                and bool(item["number"].strip())
                for item in chapters)
    )


def _usable(edition: str, pdfs: dict, chapter_maps: dict) -> bool:
    page_map = chapter_maps.get(edition) or {}
    digest = page_map.get("source_pdf_sha256")
    runs = page_map.get("runs")
    page_count = page_map.get("pdf_pages")
    if not isinstance(runs, list) or not runs:
        return False
    for run in runs:
        if not isinstance(run, list) or len(run) != 3:
            return False
        chapter = run[0]
        if chapter is not None and (
            not isinstance(chapter, str) or not chapter.strip()
        ):
            return False
    # Older canonical maps omit pdf_pages. Use their declared contiguous end
    # for candidate selection; the direct reader rechecks it against the
    # current verified PDF's actual page count before any citation is accepted.
    if page_count is None:
        last = runs[-1]
        if (not isinstance(last[2], int) or isinstance(last[2], bool)
                or last[2] < 1):
            return False
        page_count = last[2]
    if not isinstance(page_count, int) or isinstance(page_count, bool) or page_count < 1:
        return False
    try:
        coverage = chapter_coverage({"editions": {edition: page_map}}, edition, page_count)
    except (IndexError, TypeError, ValueError):
        return False
    return (
        _verified_pdf_available(edition, pdfs)
        and isinstance(digest, str)
        and re.fullmatch(r"[a-f0-9]{64}", digest) is not None
        and coverage.get("mapped_pages") == page_count
        and coverage.get("mapped_chapters", 0) > 0
    )


def _official_rows(catalog: dict, exam_type: str, subject: str) -> list[dict]:
    return [row for row in catalog.get("validity", [])
            if row.get("exam_type") == exam_type
            and row.get("subject") == subject
            and row.get("official") is True
            and isinstance(row.get("year"), int)
            and isinstance(row.get("edition"), str)]


def _candidate(row: dict, exam_year: int, rank, eligible: bool, reason) -> dict:
    result = row.copy()
    result["_family"] = _family(row["edition"])
    result["_proxy"] = row["year"] != exam_year
    result["_rank"] = rank
    result["_eligible"] = eligible
    result["_proxy_reason"] = reason
    return result


def ordered_family_candidates(
    rows: list[dict], family: str, exam_year: int, pdfs: dict, chapter_maps: dict,
) -> list[dict]:
    """Every edition of one reference family, newest first.

    One row per edition: the exam-year row when that edition was official in
    the exam year (it is then not a proxy), otherwise its newest official
    year. Editions are ranked by the newest year in which they were listed,
    then by edition key, newest first. Only editions with a verified PDF,
    usable hash-bound map and structured scope are eligible and receive a
    `_rank` (0 = newest eligible); the others are returned as pending.
    """
    by_edition: dict[str, list[dict]] = {}
    for row in rows:
        if _family(row["edition"]) == family:
            by_edition.setdefault(row["edition"], []).append(row)
    ordered = sorted(
        by_edition.items(),
        key=lambda item: (max(r["year"] for r in item[1]), item[0]),
        reverse=True,
    )
    result: list[dict] = []
    rank = 0
    for edition, edition_rows in ordered:
        exact = [r for r in edition_rows if r["year"] == exam_year]
        chosen = exact[0] if exact else max(edition_rows, key=lambda r: r["year"])
        if not _verified_pdf_available(edition, pdfs):
            result.append(_candidate(chosen, exam_year, None, False,
                                     "no verified private PDF for this edition; pending"))
        elif not _usable_scope(chosen) or not _usable(edition, pdfs, chapter_maps):
            result.append(_candidate(
                chosen, exam_year, None, False,
                "verified PDF has no usable hash-bound chapter map or official scope; pending"))
        else:
            reason = (None if chosen["year"] == exam_year else
                      "newest usable official edition in the same reference family"
                      if rank == 0 else
                      "older same-family edition; usable only after the newer editions were searched")
            result.append(_candidate(chosen, exam_year, rank, True, reason))
            rank += 1
    return result


def selected_reference_rows(
    catalog: dict, exam_type: str, exam_year: int, subject: str,
    pdfs: dict, chapter_maps: dict,
) -> list[dict]:
    """Return every candidate edition per reference family, newest first.

    Owner rule (2026-10-23): an older sitting is classified against the
    newest available official edition of each book family; only when the
    answer-bearing page is not found there may an older edition be cited,
    one step at a time (see `fallback_error`). The exam-year edition is one
    candidate in that order and keeps its official, non-proxy status. The
    family set comes from the exam-year list, or the nearest official subject
    list when the exam-year list is absent. Unusable editions are returned
    with `_eligible=False` so batch reports can show them as pending.
    """
    if not is_proxy_exam_year(exam_year):
        return []
    rows = _official_rows(catalog, exam_type, subject)
    if not rows:
        return []

    exact = [row for row in rows if row["year"] == exam_year]
    if exact:
        families = {_family(row["edition"]) for row in exact}
    else:
        nearest_distance = min(abs(row["year"] - exam_year) for row in rows)
        families = {_family(row["edition"]) for row in rows
                    if abs(row["year"] - exam_year) == nearest_distance}

    selected: list[dict] = []
    for family in sorted(families):
        selected.extend(ordered_family_candidates(rows, family, exam_year, pdfs, chapter_maps))
    return selected


def fallback_error(candidates: list[dict], edition: object, searched: object) -> str | None:
    """Check the newest-first order for one cited edition.

    Citing the newest eligible edition of a family needs no attestation.
    Citing an older one requires `newer_editions_searched` to list exactly
    the newer eligible editions of the same family, newest first: the
    decision attests that the answer-bearing page was not found in them.
    """
    if not isinstance(edition, str):
        return "edition is missing"
    family = _family(edition)
    names = [row["edition"] for row in sorted(
        (row for row in candidates
         if row.get("_eligible") is True and row.get("_family", _family(row["edition"])) == family),
        key=lambda row: row["_rank"])]
    if edition not in names:
        return f"{edition} is not an eligible candidate edition"
    newer = names[:names.index(edition)]
    if not newer:
        if searched not in (None, []):
            return "the newest eligible edition must not list newer_editions_searched"
        return None
    if searched != newer:
        return ("an older edition may be cited only after the newer editions were searched; "
                f"newer_editions_searched must be {newer}")
    return None


def is_selected_proxy(
    catalog: dict, exam_type: str, exam_year: int, subject: str,
    edition: object, reference_year: object, basis: object,
    pdfs: dict, chapter_maps: dict,
) -> bool:
    if (not is_proxy_exam_year(exam_year) or basis != BASIS
            or not isinstance(reference_year, int) or isinstance(reference_year, bool)
            or not isinstance(edition, str)):
        return False
    return any(row.get("_proxy") is True
               and row.get("_eligible") is True
               and row.get("edition") == edition
               and row.get("year") == reference_year
               and _usable_scope(row)
               and _usable(str(row.get("edition")), pdfs, chapter_maps)
               for row in selected_reference_rows(
                   catalog, exam_type, exam_year, subject, pdfs, chapter_maps))


def valid_proxy_note(note: object, exam_year: int, reference_year: int) -> bool:
    if not isinstance(note, str):
        return False
    text = note.strip()
    normalized_digits = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
    exam_digits = str(exam_year)
    reference_digits = str(reference_year)
    return (
        45 <= len(text) <= 300
        and "پروکسی" in text
        and exam_digits in normalized_digits
        and reference_digits in normalized_digits
        and "رسمی" in text
        and any(phrase in text for phrase in (
            "تأیید نشده", "رسمی نیست", "رسمی نبوده", "رسمی اعلام نشده",
        ))
    )
