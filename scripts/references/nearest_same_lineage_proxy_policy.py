"""Owner-authorized nearest same-lineage PDF fallback for older exams.

This policy is limited to exam years 1396–1398. Exact exam-year editions
remain preferred. A proxy is considered only when no exact-year edition in
that reference family has a verified private PDF. If a verified PDF exists
but its chapter map or official scope is absent or invalid, keep the case
pending; that map/scope failure never enables a proxy. The returned row
always retains its actual official reference year. The downstream page
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


BASIS = "nearest_same_lineage_proxy"
FIRST_PROXY_EXAM_YEAR = 1396
LAST_PROXY_EXAM_YEAR = 1398


def is_proxy_exam_year(exam_year: object) -> bool:
    return (isinstance(exam_year, int) and not isinstance(exam_year, bool)
            and FIRST_PROXY_EXAM_YEAR <= exam_year <= LAST_PROXY_EXAM_YEAR)


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


def selected_reference_rows(
    catalog: dict, exam_type: str, exam_year: int, subject: str,
    pdfs: dict, chapter_maps: dict,
) -> list[dict]:
    """Return exact usable rows and only the necessary same-family proxies.

    A verified exact-year PDF with a missing/invalid map or scope stays
    pending and blocks proxy selection for that family. When the exam-year
    list is absent, its nearest official subject list supplies the family
    set. A proxy can be selected only if the nearest reference-year candidate
    with a verified PDF also has a usable map and scope; a map failure on
    that verified candidate stays pending instead of searching a farther
    year. Ties prefer the earlier reference year, then the edition key.
    """
    if not is_proxy_exam_year(exam_year):
        return []
    rows = _official_rows(catalog, exam_type, subject)
    if not rows:
        return []

    exact = [row for row in rows if row["year"] == exam_year]
    if exact:
        families = {_family(row["edition"]) for row in exact}
        base_rows = exact
    else:
        nearest_distance = min(abs(row["year"] - exam_year) for row in rows)
        base_rows = [row for row in rows
                     if abs(row["year"] - exam_year) == nearest_distance]
        families = {_family(row["edition"]) for row in base_rows}

    selected: list[dict] = []
    for family in sorted(families):
        family_rows = [row for row in rows if _family(row["edition"]) == family]
        family_exact = [row for row in family_rows if row["year"] == exam_year]
        exact_pdf_rows = [row for row in family_exact
                          if _verified_pdf_available(row["edition"], pdfs)]
        usable_exact = [row for row in exact_pdf_rows
                        if _usable_scope(row)
                        and _usable(row["edition"], pdfs, chapter_maps)]
        if usable_exact:
            chosen = min(usable_exact, key=lambda row: row["edition"])
            result = chosen.copy()
            result["_proxy"] = False
            result["_eligible"] = True
            result["_proxy_reason"] = None
            selected.append(result)
            continue

        # A verified exact-year PDF with a bad/missing map is a pending
        # dependency. Do not relabel that same edition as unavailable merely
        # to activate a different-year fallback.
        if exact_pdf_rows:
            chosen = min(exact_pdf_rows, key=lambda row: row["edition"])
            result = chosen.copy()
            result["_proxy"] = False
            result["_eligible"] = False
            result["_proxy_reason"] = (
                "exact-year PDF is verified but its chapter map or official scope is unusable; keep pending"
            )
            selected.append(result)
            continue

        proxy_rows = sorted(
            (row for row in family_rows if row["year"] != exam_year),
            key=lambda row: (abs(row["year"] - exam_year), row["year"], row["edition"]),
        )
        # Missing PDFs may be skipped. The first verified PDF is the nearest
        # actually available candidate; if its map/scope fails, keep pending
        # and do not silently advance to a farther edition.
        verified_proxy_rows = [
            row for row in proxy_rows if _verified_pdf_available(row["edition"], pdfs)
        ]
        if verified_proxy_rows:
            chosen = verified_proxy_rows[0]
            if not _usable_scope(chosen) or not _usable(chosen["edition"], pdfs, chapter_maps):
                result = chosen.copy()
                result["_proxy"] = True
                result["_eligible"] = False
                result["_proxy_reason"] = (
                    "nearest verified proxy PDF has no usable hash-bound chapter map or official scope; keep pending"
                )
                selected.append(result)
                continue
            result = chosen.copy()
            result["_proxy"] = True
            result["_eligible"] = True
            result["_proxy_reason"] = (
                "exam-year edition absent or unavailable; nearest usable official edition in the same reference family"
            )
            selected.append(result)
            continue

        pending_rows = [row for row in base_rows if _family(row["edition"]) == family]
        if not pending_rows:
            pending_rows = family_exact or family_rows
        chosen = min(pending_rows,
                     key=lambda row: (abs(row["year"] - exam_year),
                                      row["year"], row["edition"]))
        result = chosen.copy()
        result["_proxy"] = chosen["year"] != exam_year
        result["_eligible"] = False
        result["_proxy_reason"] = "no usable verified PDF and hash-bound chapter map in this reference family"
        selected.append(result)
    return sorted(selected, key=lambda row: (row["year"], row["edition"]))


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
