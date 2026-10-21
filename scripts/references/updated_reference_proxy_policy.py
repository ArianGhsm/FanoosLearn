"""Narrow owner-approved bridges for explicitly labelled updated references."""

import re

UPDATED_PROXY_BASIS = "updated_reference_proxy"
UPDATED_PROXY_EXAM_YEAR = 1398
UPDATED_PROXY_REFERENCE_YEAR = 1406
UPDATED_PROXY_SUBJECT = "oral-surgery"
UPDATED_PROXY_EDITION = "hupp-oral-surgery@7e"
UPDATED_PROXY_1397_EXAM_YEAR = 1397
UPDATED_PROXY_1397_SUBJECTS = frozenset({
    "community-dentistry", "dental-materials", "endodontics",
    "operative-dentistry", "oral-medicine", "oral-pathology",
    "oral-radiology", "oral-surgery", "orthodontics",
    "pediatric-dentistry", "periodontics", "prosthodontics",
})


def is_updated_reference_proxy(
    exam_year: object,
    subject: object,
    edition: object,
    reference_year: object,
    basis: object,
    reference_subject: object | None = None,
    question_number: object | None = None,
) -> bool:
    if reference_year != UPDATED_PROXY_REFERENCE_YEAR or basis != UPDATED_PROXY_BASIS:
        return False
    if exam_year == UPDATED_PROXY_EXAM_YEAR:
        return subject == UPDATED_PROXY_SUBJECT and edition == UPDATED_PROXY_EDITION
    if (exam_year != UPDATED_PROXY_1397_EXAM_YEAR
            or subject not in UPDATED_PROXY_1397_SUBJECTS
            or not isinstance(edition, str)
            or re.fullmatch(r"[a-z0-9-]+@[a-z0-9-]+", edition) is None):
        return False
    cited_subject = subject if reference_subject is None else reference_subject
    if cited_subject == subject:
        return True
    # Owner-authorized one-question cross-subject source: the 1397
    # endodontics implant item is supported by Hupp 7e from the 1406
    # oral-surgery list. The source row must state this explicitly.
    return (
        exam_year == 1397
        and subject == "endodontics"
        and question_number == 95
        and cited_subject == "oral-surgery"
        and edition == "hupp-oral-surgery@7e"
    )


def valid_updated_reference_note(note: object, exam_year: int = UPDATED_PROXY_EXAM_YEAR) -> bool:
    exam_year_digits = {1397: "۱۳۹۷", 1398: "۱۳۹۸"}.get(exam_year)
    return (
        isinstance(note, str)
        and len(note.strip()) >= 60
        and "خارج از محدوده" in note
        and "فهرست رسمی" in note
        and exam_year_digits is not None
        and exam_year_digits in note
        and "۱۴۰۶" in note
    )
