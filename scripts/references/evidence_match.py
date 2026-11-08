"""Exact matching for short evidence copied from one original PDF page.

Search ranking has its own deliberately lossy normalization. Evidence matching
preserves visible letters, case, punctuation, hyphens and digits; only layout
whitespace and Unicode bidi display controls are ignored.
"""
from __future__ import annotations

import re
import unicodedata

BIDI_DISPLAY_CONTROLS = frozenset({
    0x061C, 0x200E, 0x200F,
    *range(0x202A, 0x202F),
    *range(0x2066, 0x206A),
})
_LAYOUT_ASCII_WHITESPACE = frozenset(" \t\n\v\f\r")
_NUMERIC_SEPARATORS = frozenset(".,/:\u066b\u066c'’·،⁄∕\u00b1\u2213\u207b\u208b")
_DECIMAL_START_SEPARATORS = frozenset(".,\u066b\u066c،")
_SIGNS = frozenset("+-\u2212\u00b1\u2213\u207b\u208b")
_NUMERIC_SUFFIXES = frozenset("%\u2030\u2031\u066a\uff05\ufe6a")
_NUMERIC_QUALIFIERS = frozenset("<>≤≥~≈≲≳≦≧∼～⩾⩽≃≅")
_NUMERIC_MULTIPLIERS = frozenset("×xX")
_MEASUREMENT_UNITS = frozenset({
    "mm", "cm", "m", "km", "um", "µm", "μm", "nm", "pm", "in", "inch", "inches", "ft",
    "mg", "mcg", "µg", "μg", "ng", "g", "kg", "ml", "milliliter", "millilitre", "l", "dl",
    "meq", "mmol", "mol", "mM", "μM", "µM", "n", "kn", "pa", "kpa", "mpa", "gpa", "psi",
    "mmhg", "cmh2o", "s", "sec", "min", "h", "hr", "rpm", "hz", "khz", "mhz", "v", "mv",
    "w", "kw", "j", "kj", "kcal", "°c", "°f", "angstrom", "å",
    "متر", "میلی‌متر", "میلیمتر", "سانتی‌متر", "سانتیمتر", "میکرومتر", "میکرون", "نانومتر",
    "گرم", "میلی‌گرم", "کیلوگرم", "میلی‌لیتر", "میلیلتر", "لیتر", "درصد", "درجه", "ثانیه",
    "دقیقه", "ساعت", "روز", "سال", "مول", "نیوتن", "پاسکال", "ميلي‌گرم", "كيلوگرم",
    "cent", "percent", "day", "days", "week", "weeks", "month", "months", "hour", "hours",
    "minute", "minutes", "second", "seconds", "year", "years", "cent", "cigarette", "cigarettes",
    "time", "times", "site", "sites", "tooth", "teeth", "implant", "implants", "unit", "units",
    "drop", "drops", "capsule", "capsules", "tablet", "tablets", "dose", "doses", "pack", "packs",
    "ppm", "cc", "iu", "درصد", "دندان", "دندان‌ها", "دندانه", "بار", "هفته", "ماه", "قطره", "قرص", "دوز", "واحد",
})
_EDGE_CONNECTORS = frozenset({
    "per", "to", "of", "in", "on", "by", "for", "at", "over", "under", "about", "up", "down",
    "the", "a", "an", "from", "between", "every", "than", "and", "or", "nor", "with", "versus", "vs",
    "در", "تا", "از", "به", "برای", "حدود", "کمتر", "بیشتر", "بین", "و", "هر", "یا", "با",
})
_RIGHT_SCAN_ABBREVIATIONS = frozenset({
    "vs", "approx", "ca", "cf", "i", "e", "g", "etc", "al", "dr", "mr", "mrs", "ms",
    "fig", "eq", "no", "ref", "vol", "ed", "min", "sec", "pp", "ch",
})
_EDGE_OPEN_ENDED = frozenset({
    "more", "less", "greater", "higher", "lower", "older", "younger", "longer", "shorter",
    "above", "below", "over", "under", "atleast", "upto", "بیشتر", "کمتر", "بالاتر", "پایین‌تر",
    "حداقل", "حداکثر", "بیش‌از", "کمتر‌از",
})


def _layout_space(char: str) -> bool:
    return char in _LAYOUT_ASCII_WHITESPACE or unicodedata.category(char) in {"Zs", "Zl", "Zp"}


def has_unreadable_glyph(text: str) -> bool:
    """Reject private-use, replacement, soft-hyphen and non-layout control glyphs."""
    for char in text:
        code = ord(char)
        category = unicodedata.category(char)
        if code in BIDI_DISPLAY_CONTROLS or _layout_space(char) or char in {"\u200c", "\u200d"}:
            continue
        if code in {0x00AD, 0x200B, 0x2060, 0xFEFF, 0xFFFC, 0xFFFD}:
            return True
        if category in {"Cc", "Cs", "Cn", "Co", "Cf"}:
            return True
    return False


def evidence_key_with_line_breaks(text: str) -> tuple[str, set[int]]:
    """Collapse layout whitespace and retain which spaces came from line breaks."""
    result: list[str] = []
    line_breaks: set[int] = set()
    pending_space = False
    pending_line_break = False
    for char in text:
        if ord(char) in BIDI_DISPLAY_CONTROLS:
            continue
        if _layout_space(char):
            if result:
                pending_space = True
                pending_line_break = pending_line_break or char in {"\n", "\r", "\u2028", "\u2029"}
            continue
        if pending_space:
            if pending_line_break:
                line_breaks.add(len(result))
            result.append(" ")
            pending_space = False
            pending_line_break = False
        result.append(char)
    return "".join(result), line_breaks


def evidence_key(text: str) -> str:
    """Remove only bidi display controls and collapse actual layout whitespace."""
    return evidence_key_with_line_breaks(text)[0]


def _word_char(char: str) -> bool:
    if not char:
        return False
    category = unicodedata.category(char)
    return char == "_" or ord(char) in {0xFFFC, 0xFFFD} or category[0] in {"L", "N", "M", "C"}


def _number_char(char: str) -> bool:
    return bool(char) and unicodedata.category(char)[0] == "N"


def evidence_word_count(text: str) -> int:
    """Count lexical words while keeping combining marks and joiners attached."""
    return len(evidence_words(text))


def _previous_nonspace(text: str, index: int) -> int:
    while index >= 0 and _layout_space(text[index]):
        index -= 1
    return index


def _next_nonspace(text: str, index: int) -> int:
    while index < len(text) and _layout_space(text[index]):
        index += 1
    return index


def _is_dash(char: str) -> bool:
    return bool(char) and (unicodedata.category(char) == "Pd" or char in {"\u2212", "\u00ad"})


def _is_math_operator(char: str) -> bool:
    return bool(char) and unicodedata.category(char) == "Sm"


def evidence_words(text: str) -> list[str]:
    words: list[str] = []
    current: list[str] = []
    for char in evidence_key(text):
        category = unicodedata.category(char)
        if category[0] in {"L", "N"}:
            current.append(char)
        elif (category[0] == "M" or char in {"\u200c", "\u200d"}) and current:
            current.append(char)
        elif current:
            words.append("".join(current))
            current = []
    if current:
        words.append("".join(current))
    return words


def _unit_key(word: str) -> str:
    return word.casefold().translate(str.maketrans({"ي": "ی", "ى": "ی", "ك": "ک"}))


def _left_boundary_follows_number(
    page: str, start: int, line_breaks: set[int], ignored_page_labels: set[str],
) -> bool:
    previous = _previous_nonspace(page, start - 1)
    if previous < 0 or not _number_char(page[previous]):
        return False
    boundary = next((index for index in line_breaks if previous < index < start), None)
    if boundary is None:
        return True
    previous_line_start = max((index + 1 for index in line_breaks if index < previous), default=0)
    previous_line = page[previous_line_start:boundary].strip(" ")
    line_number = sum(index < previous_line_start for index in line_breaks)
    total_lines = len(line_breaks) + 1
    # Numeric content cannot be discounted solely because it resembles a page label.
    # Caller must first corroborate the candidate with independent adjacent pages.
    is_page_label = (previous_line in ignored_page_labels
                     and (line_number < 3 or line_number >= total_lines - 3))
    return not is_page_label


def has_numeric_edge_word(text: str) -> bool:
    """Reject detached numbers and unit phrases at either evidence edge."""
    words = evidence_words(text)
    if not words:
        return True
    edges = words[:2] + words[-2:]
    return any(any(_number_char(char) for char in word) for word in edges)


def _quote_ends_with_abbreviation(text: str) -> bool:
    if not text.endswith("."):
        return False
    words = evidence_words(text[:-1])
    return bool(words) and _unit_key(words[-1]) in _RIGHT_SCAN_ABBREVIATIONS


def _first_sentence_boundary(text: str) -> int | None:
    for match in re.finditer(r"[.!?;؛؟](?=\s|$)", text):
        if match.group(0) == ".":
            words = evidence_words(text[:match.start()])
            if words and _unit_key(words[-1]) in _RIGHT_SCAN_ABBREVIATIONS:
                continue
        return match.start()
    return None


def _right_unit_run_continues_to_number(page: str, end: int, fragment: str) -> bool:
    words = evidence_words(fragment)
    if not words or not any(_number_char(char) for word in words for char in word):
        return False
    quote_key = evidence_key(fragment)
    if quote_key and quote_key[-1] in ".;!?؛؟":
        if quote_key[-1] != "." or not _quote_ends_with_abbreviation(quote_key):
            return False
    suffix = page[end:]
    sentence_boundary = _first_sentence_boundary(suffix)
    if sentence_boundary is not None:
        suffix = suffix[:sentence_boundary]
    following = evidence_words(suffix)
    if not following:
        return False
    tail = _unit_key(words[-1])
    saw_connector = tail in _EDGE_CONNECTORS
    per_denominator = False
    for word in following:
        key = _unit_key(word)
        if saw_connector and key in _EDGE_OPEN_ENDED:
            return True
        if key in _EDGE_CONNECTORS:
            saw_connector = True
            per_denominator = key == "per"
            continue
        if key in _MEASUREMENT_UNITS:
            if per_denominator:
                return True
            per_denominator = False
            continue
        return saw_connector and any(_number_char(char) for char in word)
    return False


def _left_separator_is_internal(page: str, start: int) -> bool:
    separator = _previous_nonspace(page, start - 1)
    if (start > 0 and separator >= 0 and separator < start - 1
            and _number_char(page[separator]) and _number_char(page[start])):
        return True
    if separator < 0:
        return False
    symbol = page[separator]
    before_adjacent = page[separator - 1] if separator else ""
    before_index = _previous_nonspace(page, separator - 1)
    before = page[before_index] if before_index >= 0 else ""
    current = page[start]
    if _is_dash(symbol) and _word_char(before_adjacent) and _word_char(current):
        return True
    if _is_dash(symbol) and _number_char(before) and _number_char(current):
        return True
    if symbol in _NUMERIC_MULTIPLIERS and _number_char(before) and _number_char(current):
        return True
    if (symbol in _NUMERIC_QUALIFIERS or _is_math_operator(symbol)) and _number_char(current):
        return True
    if symbol in _NUMERIC_SEPARATORS and _number_char(before) and _number_char(current):
        return True
    if symbol in _DECIMAL_START_SEPARATORS and _number_char(current) and (not before or not _word_char(before)):
        return True
    if symbol in _SIGNS:
        return _number_char(before) and _number_char(current) or (
            _number_char(current) and (not before or not _word_char(before)))
    return False


def _right_separator_is_internal(page: str, end: int) -> bool:
    if end >= len(page):
        return False
    before = page[end - 1] if end else ""
    if _is_dash(before) and end < len(page) and _layout_space(page[end]):
        previous_word = page[end - 2] if end > 1 else ""
        continuation = _next_nonspace(page, end)
        if _word_char(previous_word) and continuation < len(page) and _word_char(page[continuation]):
            return True
    if _number_char(before):
        suffix = _next_nonspace(page, end)
        if suffix < len(page) and page[suffix] in _NUMERIC_SUFFIXES:
            return True
        if (end < len(page) and _layout_space(page[end])
                and suffix < len(page) and _number_char(page[suffix])):
            return True
        if suffix < len(page) and page[suffix] in _NUMERIC_MULTIPLIERS:
            operand = _next_nonspace(page, suffix + 1)
            if operand < len(page) and _number_char(page[operand]):
                return True
    if _is_dash(page[end]):
        after = _next_nonspace(page, end + 1)
        return _word_char(before) and after < len(page) and _word_char(page[after])
    separator = _next_nonspace(page, end)
    if separator >= len(page):
        return False
    symbol = page[separator]
    before_index = _previous_nonspace(page, end - 1)
    number_before = page[before_index] if before_index >= 0 else ""
    after_index = _next_nonspace(page, separator + 1)
    number_after = page[after_index] if after_index < len(page) else ""
    if (_is_dash(symbol) or symbol in _NUMERIC_MULTIPLIERS or symbol in _NUMERIC_QUALIFIERS
            or _is_math_operator(symbol)) and _number_char(number_before) and _number_char(number_after):
        return True
    if symbol in _NUMERIC_SEPARATORS and _number_char(number_before) and _number_char(number_after):
        return True
    if symbol in _DECIMAL_START_SEPARATORS and _number_char(number_after) and (not number_before or not _word_char(number_before)):
        return True
    if symbol in _SIGNS and _number_char(number_before) and _number_char(number_after):
        return True
    return False


def contains_exact_fragment(
    page_text: str, fragment: str, ignored_page_labels: set[str] | None = None,
) -> bool:
    """Case- and punctuation-sensitive page match with safe token boundaries."""
    if not isinstance(page_text, str) or not isinstance(fragment, str) or has_unreadable_glyph(fragment):
        return False
    page, line_breaks = evidence_key_with_line_breaks(page_text)
    quote = evidence_key(fragment)
    ignored_page_labels = ignored_page_labels or set()
    if not quote or has_numeric_edge_word(quote):
        return False
    offset = 0
    while True:
        position = page.find(quote, offset)
        if position < 0:
            return False
        end = position + len(quote)
        left_is_word = position > 0 and _word_char(page[position - 1])
        right_is_word = end < len(page) and _word_char(page[end])
        if (not left_is_word and not right_is_word
                and not _left_boundary_follows_number(page, position, line_breaks, ignored_page_labels)
                and not _left_separator_is_internal(page, position)
                and not _right_separator_is_internal(page, end)
                and not _right_unit_run_continues_to_number(page, end, quote)):
            return True
        offset = position + 1
