"""Deterministic, explicitly non-historical reference selection for pre-1396 exams.

The owner's 2026-10-11 retrospective exception uses subject references officially
announced for 1397 onward; since 2026-10-23 the newest usable edition of each
book family is tried first. It never asserts those references were official in
the earlier exam year, or writes invented historical catalog validity rows.
This only selects candidates; the original server PDF, hash-bound chapter
coverage and quoted answer evidence MUST still pass the downstream validator.
"""
from __future__ import annotations

import re


BASIS = "retrospective_proxy_1397_plus"
FIRST_REFERENCE_YEAR = 1397
LAST_EARLY_EXAM_YEAR = 1395


def is_retrospective(exam_year: int) -> bool:
    return 1300 <= int(exam_year) <= LAST_EARLY_EXAM_YEAR


_PERSIAN_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")


def retrospective_note(reference_year: int, exam_year: int) -> str:
    """Visible proxy label; must equal SourceOnlyPublisher's retrospective note."""
    return (
        f"این ارجاع پروکسی از فهرست رسمی سال {str(int(reference_year)).translate(_PERSIAN_DIGITS)}"
        f" برای آزمون سال {str(int(exam_year)).translate(_PERSIAN_DIGITS)}"
        " است؛ رسمی بودن آن برای سال آزمون ادعا نمی‌شود."
    )


def _usable_pdf_and_map(edition: str, pdfs: dict, maps: dict) -> bool:
    record = pdfs.get(edition) or {}
    chapter_map = maps.get(edition) or {}
    # Availability and map provenance are necessary but not sufficient:
    # the live PDF bridge must rehash and independently validate coverage.
    return (
        record.get("pdf") == "verified-server-pdf"
        and isinstance(chapter_map.get("source_pdf_sha256"), str)
        and re.fullmatch(r"[0-9a-f]{64}", chapter_map["source_pdf_sha256"]) is not None
        and bool(chapter_map.get("runs"))
    )


def selected_reference_rows(
    catalog: dict, exam_type: str, exam_year: int, subject: str,
    pdfs: dict, chapter_maps: dict,
) -> list[dict]:
    """Select every usable 1397+ edition per book family, newest first.

    Owner rule (2026-10-23): cite the newest usable edition of a family; an
    older one only after the answer-bearing page was not found in the newer
    ones (`same_lineage_proxy_policy.fallback_error`). One row per edition,
    carrying its newest official year as the reference year. The returned
    copies retain actual validity 'year' as reference year; no caller may
    misreport it as the original exam's official reference.
    For non-retrospective sittings, leave all current official year-specific
    rows unchanged (including unavailable editions for pending reporting).
    """
    source = [v for v in catalog["validity"]
              if v.get("exam_type") == exam_type and v.get("subject") == subject
              and v.get("official", True) is True]
    if not is_retrospective(exam_year):
        return [v.copy() for v in source if int(v["year"]) == int(exam_year)]

    by_edition: dict[str, dict] = {}
    for v in sorted(source, key=lambda v: (int(v["year"]), str(v["edition"])), reverse=True):
        edition = str(v["edition"])
        if (int(v["year"]) < FIRST_REFERENCE_YEAR or edition in by_edition
                or not v.get('scope_chapters')
                or not _usable_pdf_and_map(edition, pdfs, chapter_maps)):
            continue
        by_edition[edition] = v.copy()
    ranks: dict[str, int] = {}
    selected = []
    for row in by_edition.values():
        family = str(row["edition"]).partition("@")[0]
        row["_family"] = family
        row["_rank"] = ranks.get(family, 0)
        row["_eligible"] = True
        row["_proxy"] = True
        ranks[family] = row["_rank"] + 1
        selected.append(row)
    return selected


def reference_year_for_decision(row: dict, exam_year: int) -> int:
    """The cited official year is explicit, never silently the old exam year."""
    reference_year = int(row["year"])
    if is_retrospective(exam_year):
        if reference_year < FIRST_REFERENCE_YEAR:
            raise ValueError("proxy reference year must be >=1397")
    elif reference_year != int(exam_year):
        raise ValueError("normal classification requires exam-year validity")
    return reference_year
