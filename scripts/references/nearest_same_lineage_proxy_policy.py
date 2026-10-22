"""Owner-authorized nearest same-lineage PDF fallback for older exams.

This policy is limited to exam years 1396–1398. Exact exam-year editions
remain preferred. A proxy is selected only when the exact-year edition is
not usable from the verified private PDF inventory and a different official
year lists the same reference family. The returned row always retains its
actual official reference year. The downstream page reader must still verify
the current approved PDF, its matching chapter map, page and direct evidence.
"""
from __future__ import annotations

import re


BASIS = "nearest_same_lineage_proxy"
FIRST_PROXY_EXAM_YEAR = 1396
LAST_PROXY_EXAM_YEAR = 1398


def is_proxy_exam_year(exam_year: object) -> bool:
    return (isinstance(exam_year, int) and not isinstance(exam_year, bool)
            and FIRST_PROXY_EXAM_YEAR <= exam_year <= LAST_PROXY_EXAM_YEAR)


def _family(edition: str) -> str:
    return edition.partition("@")[0]


def _usable(edition: str, pdfs: dict, chapter_maps: dict) -> bool:
    pdf = pdfs.get(edition) or {}
    page_map = chapter_maps.get(edition) or {}
    digest = page_map.get("source_pdf_sha256")
    runs = page_map.get("runs")
    page_count = page_map.get("pdf_pages")
    previous = 0
    if not isinstance(runs, list) or not runs:
        return False
    for run in runs:
        if (not isinstance(run, list) or len(run) != 3
                or not isinstance(run[1], int) or isinstance(run[1], bool)
                or not isinstance(run[2], int) or isinstance(run[2], bool)
                or run[1] != previous + 1 or run[2] < run[1]):
            return False
        previous = run[2]
    # Older canonical page maps record a hash-bound contiguous run list but
    # omit an explicit page-count field. The direct PDF reader independently
    # checks the map against the current verified PDF's actual page count
    # before search, validation or audit. When a page count is present here,
    # still require the map to end on that exact page.
    return (
        pdf.get("pdf") == "verified-server-pdf"
        and isinstance(digest, str)
        and re.fullmatch(r"[a-f0-9]{64}", digest) is not None
        and (page_count is None or (
            isinstance(page_count, int) and not isinstance(page_count, bool)
            and page_count > 0 and previous == page_count
        ))
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

    When the exam-year list is absent, its nearest official subject list
    supplies the family set; each family then resolves to its nearest usable
    official edition. Ties prefer the earlier reference year, then the
    lexicographically smaller edition key. If no usable proxy exists, retain
    the closest official row as an unavailable pending candidate.
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
        usable_exact = [row for row in family_exact
                        if row.get("scope_chapters")
                        and _usable(row["edition"], pdfs, chapter_maps)]
        if usable_exact:
            chosen = min(usable_exact, key=lambda row: row["edition"])
            result = chosen.copy()
            result["_proxy"] = False
            result["_eligible"] = True
            result["_proxy_reason"] = None
            selected.append(result)
            continue

        proxy_rows = [row for row in family_rows
                      if row["year"] != exam_year
                      and row.get("scope_chapters")
                      and _usable(row["edition"], pdfs, chapter_maps)]
        if proxy_rows:
            chosen = min(proxy_rows,
                         key=lambda row: (abs(row["year"] - exam_year),
                                          row["year"], row["edition"]))
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
               and bool(row.get("scope_chapters"))
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
