"""Reads an announced chapter scope («تمام فصول به جز ۴، ۸، ۱۸»,
«فصول 1،3–7؛ فصل 2 صص 18–47») into the chapters it covers.

    resolve(scope_text, chapter_numbers) -> list[{"number", "partial"?}] | None

None means the text cannot be read as a chapter list (a notice that only
adds or drops chapters, "selected chapters, see the PDF", a Part name);
the caller keeps the text and says so. A chapter is "partial" when the
announcement limits it to pages or topics; "partial" carries those words.
Standard library only.
"""
from __future__ import annotations

import re

DIGITS = str.maketrans('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', '01234567890123456789')
# Notices that only change an earlier list, or name no chapters at all.
UNREADABLE = ['حذف شد', 'حذف فصل', 'ویرایش‌های', 'اضافه شد', 'افزوده', 'part ', 'books ', 'مشخص نشده', 'معرفی', 'مفقود', 'فهرست کامل در pdf', 'فهرست فصل‌های منتخب']
# Words that limit a chapter to part of it.
PARTIAL = ['فقط', 'به جز ص', 'به‌جز ص', 'بجز ص', 'تا ص', 'تا ابتدای', 'از مبحث', 'از حدود', 'ادامه']
NUMBER = r'\d+(?:\.\d+)?'
RANGE = re.compile(rf'({NUMBER})\s*[–\-—]\s*({NUMBER})|({NUMBER})')
PAGES = re.compile(r'(?:صفحات|صفحه|صص|ص)\s*\.?\s*\d+(?:\s*[–\-—]\s*\d+)?(?:\s*و\s*\d+(?:\s*[–\-—]\s*\d+)?)*')


def _order(number: str) -> tuple[int, ...]:
    return tuple(int(part) for part in number.split('.'))


def _expand(text: str, known: list[str]) -> list[str]:
    """Chapter numbers and ranges in text, page numbers removed first."""
    text = PAGES.sub(' ', text)
    out: list[str] = []
    for m in RANGE.finditer(text):
        if m.group(3):
            out.append(m.group(3))
            continue
        lo, hi = m.group(1), m.group(2)
        if known and lo in known and hi in known:
            a, b = known.index(lo), known.index(hi)
            out.extend(known[a:b + 1])
        elif '.' not in lo and '.' not in hi and int(lo) <= int(hi) <= int(lo) + 200:
            out.extend(str(n) for n in range(int(lo), int(hi) + 1))
        else:
            out.extend([lo, hi])
    return out


def resolve(scope: str | None, chapters: list[str]) -> list[dict] | None:
    if not scope:
        return None
    text = re.sub(r'\s+', ' ', scope.translate(DIGITS)).strip()
    lower = text.lower()
    if any(marker in lower for marker in UNREADABLE):
        return None
    known = sorted(chapters, key=_order)
    # The official lists' own phrasing: «فصل 1 تا پایان فصل 9», «13 تا 26»,
    # «فصل های 4-10-12-14» (a list, not ranges), «کلیه فصول».
    text = text.replace('تا پایان فصل', 'تا')
    text = text.replace('تا آخر فصل', 'تا')
    text = re.sub(rf'{NUMBER}(?:\s*-\s*{NUMBER}){{2,}}', lambda m: '، '.join(re.findall(NUMBER, m.group(0))), text)
    text = re.sub(rf'({NUMBER})\s*تا\s*({NUMBER})', lambda m: f'{m.group(1)}–{m.group(2)}', text)
    # The specialty lists' wordings of the same: «همه‌ی فصول»، «همه کتاب بجز فصول»،
    # «کلیه فصول غیر از»، «All chapters, except for chapters 1-5», «Chapters: 4, 6».
    text = re.sub(r'^(?:کلیه|همه‌ی|همه)\s*(?:فصول|کتاب)(?:\s*کتاب)?', 'تمام فصول', text)
    text = re.sub(r'^all chapters,?', 'تمام فصول', text, flags=re.I)
    text = re.sub(r'^تمام فصول\s*(?:کتاب\s*)?(?:بجز|به‌جز|به غیر از|غیر از|except for|except)(?:\s*(?:فصل‌های|فصلهای|فصول|فصل|chapters?))?\s*',
                  'تمام فصول به جز ', text, flags=re.I)
    text = re.sub(r'^chapters?\s*:?\s*', 'فصول ', text, flags=re.I)
    text = re.sub(r'\s+', ' ', text).strip()
    # «فقط فصول …» means "only these chapters", not part of them; a parenthesis
    # after «کتاب» («(فقط مباحث ملاحظات دندانپزشکی)») qualifies the whole list.
    text = re.sub(r'^فقط\s+(?=فص)', '', text)
    text = re.sub(r'کتاب\s*\([^)]*\)', 'کتاب', text)
    lower = text.lower()
    # «فصول 1 (از صفحه 1 تا 15) و 2»: a parenthesis right after a chapter
    # number limits that chapter only.
    limited: dict[str, str] = {}

    def note_limit(m: re.Match) -> str:
        inner = m.group(2).strip()
        if re.search(r'فصل\s*\d', inner) or not (re.search(r'(?:از\s*)?(?:صفحات|صفحه|صص|ص)\s*\d', inner) or any(w in inner for w in PARTIAL)):
            return m.group(0)
        limited[m.group(1)] = inner
        return m.group(1)
    text = re.sub(rf'({NUMBER})\s*\(([^)]*)\)', note_limit, text)

    if lower.startswith('تمام فصول'):
        head, _, rest = text.partition('؛')
        excluded = set(_expand(head.split('به جز', 1)[1], known)) if 'به جز' in head else set()
        if not known:
            return None if not excluded else None
        result = [{'number': n} for n in known if n not in excluded]
        return result if rest.strip() == '' or not re.search(r'\d', rest) else None

    included: dict[str, str | None] = {}
    explicit: set[str] = set()  # dotted sections named one by one
    headings: set[str] = set()  # sections reached only through a bare «بخش 3»
    # Split into statements; «و فصل» starts a new one too.
    for segment in re.split(r'[؛;]|\s+و\s*(?=فصل)', text):
        segment = segment.strip(' .،,')
        if not segment or not re.search(r'\d', segment):
            continue  # a qualifier such as «فقط مباحث ملاحظات دندانپزشکی» applies to the whole scope
        note = None
        inner = re.findall(r'\(([^)]*)\)', segment)
        outer = re.sub(r'\([^)]*\)', ' ', segment)
        bare = PAGES.sub(' ', outer)
        numbers = _expand(re.sub(r'^.*?(?=\d)', '', bare, count=1), known) if re.search(r'\d', bare) else []
        # «بخش‌های 2.2،3،3.4»: a bare section number beside dotted ones is a heading;
        # alone («بخش 3») it means every section under it.
        if any('.' in c for c in known):
            dotted = [n for n in numbers if '.' in n]
            explicit.update(dotted)
            if dotted:
                numbers = dotted
            elif numbers:
                numbers = [c for c in known if c.split('.')[0] in numbers]
                headings.update(numbers)
        partial_here = any(word in outer for word in PARTIAL) or (len(numbers) == 1 and re.search(r'(?:صفحات|صفحه|صص|ص)\s*\d', outer))
        for part in inner:
            named = re.findall(r'فصل\s*(\d+)', part.translate(DIGITS))
            if named and any(word in part or 'ص' in part for word in PARTIAL):
                for n in named:
                    included[n] = part.strip()
            elif any(word in part for word in PARTIAL) or ('ص' in part and len(numbers) == 1):
                partial_here = True
        if partial_here:
            note = segment
        for n in numbers:
            if note is not None:
                included[n] = note
            else:
                included.setdefault(n, None)
    # «بخش 3؛ بخش‌های 3.4–3.7»: the named sections narrow the heading.
    narrowed = {n.split('.')[0] for n in explicit}
    for n in headings - explicit:
        if n.split('.')[0] in narrowed:
            included.pop(n, None)
    for n, inner in limited.items():
        if n in included:
            included[n] = inner
    if not included:
        return None
    ordered = sorted(included, key=_order)
    return [{'number': n, **({'partial': included[n]} if included[n] else {})} for n in ordered]
