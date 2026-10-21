"""Narrow owner-approved bridge for the 1398 Hupp 6e PDF gap."""

UPDATED_PROXY_BASIS = "updated_reference_proxy"
UPDATED_PROXY_EXAM_YEAR = 1398
UPDATED_PROXY_REFERENCE_YEAR = 1406
UPDATED_PROXY_SUBJECT = "oral-surgery"
UPDATED_PROXY_EDITION = "hupp-oral-surgery@7e"


def is_updated_reference_proxy(
    exam_year: object,
    subject: object,
    edition: object,
    reference_year: object,
    basis: object,
) -> bool:
    return (
        exam_year == UPDATED_PROXY_EXAM_YEAR
        and subject == UPDATED_PROXY_SUBJECT
        and edition == UPDATED_PROXY_EDITION
        and reference_year == UPDATED_PROXY_REFERENCE_YEAR
        and basis == UPDATED_PROXY_BASIS
    )


def valid_updated_reference_note(note: object) -> bool:
    return (
        isinstance(note, str)
        and len(note.strip()) >= 60
        and "خارج از محدوده" in note
        and "۱۳۹۸" in note
        and "۱۴۰۶" in note
    )
