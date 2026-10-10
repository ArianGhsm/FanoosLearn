#!/usr/bin/env python3
"""Map every page directly from the exact approved reference PDF.

Reads one PDF page at a time from the verified server object and discards its
text after scoring that page. It retains only page numbers, chapter votes and
the final chapter runs; it never creates a text version or search index.
Together with the edition's chapter list (data/bank/reference-tocs.json), it
writes data/bank/reference-chapter-pages.json. Classification turns "the
answer is on page 412" into "chapter 14" with it.

Each page votes for its chapter from what the book prints on it:
- the running head ("CHAPTER 13", "17 • Nonpharmacologic…", "4. Fundamentals…");
- the chapter's opening: its number with its title in the first lines;
- the numbers of its figures, tables and boxes ("Fig. 44.7", "Table 16-2").
A page with no vote takes its neighbours' chapter only when both agree. A
page nobody can place stays unplaced -- it is reported, never guessed.

    python scripts/references/build_chapter_pages.py --only proffit-orthodontics@5e
"""
from __future__ import annotations

import argparse
import collections
import os
import json
import re
import sys
import stat
import tempfile
from pathlib import Path
from verified_reference_pdf import open_verified_reference

REPO = Path(__file__).resolve().parents[2]
TOCS = REPO / 'data' / 'bank' / 'reference-tocs.json'
PDFS = REPO / 'data' / 'bank' / 'reference-pdfs.json'
OUT = REPO / 'data' / 'bank' / 'reference-chapter-pages.json'
STOP = {'and', 'the', 'of', 'in', 'for', 'to', 'a', 'an', 'with', 'on', 'its', 'their', 'or'}

HEAD_PATTERNS = [
    re.compile(r'^\s*CHAPTER\s+(\d{1,3})\b', re.I),
    re.compile(r'^\s*(\d{1,3})\s*[•·]\s+\S'),
    re.compile(r'^\s*(\d{1,3})\.\s+[A-Z][a-z]'),
]
PERSIAN_HEAD = re.compile(r'^\s*(?:(\d{1,3})[\s\u200c]*فصل|فصل[\s\u200c]*(\d{1,3}))\s*$')
OPENING = re.compile(r'^\s*CHAPTER\s+(\d{1,3})\s*$', re.I)
BOOKMARK_NUMBER = re.compile(r'^\s*(?:ch(?:apter)?\s*)?(\d{1,3})\s*(?:[.:\-]|\s|$)', re.I)
FIGURE = re.compile(r'\b(?:e?Fig(?:ure)?s?|Tables?|Box(?:es)?)\.?\s*(\d{1,3})\s*[.\-–]\s*\d{1,3}', re.I)


def words(text: str) -> list[str]:
    return [w for w in re.findall(r'[a-z0-9]+', text.lower().replace('’', "'")) if w not in STOP]


def roman_front_matter(lines: list[str]) -> bool:
    return bool(lines and re.fullmatch(r'[ivxlcdm]{1,8}', lines[0].strip(), re.I))


def pages_of(pdf):
    """Yield a single PDF page's text at a time; callers must not retain it."""
    yield from pdf.iter_page_texts()


def votes(text: str, chapters: dict[str, list[str]]) -> collections.Counter:
    lines = [l for l in text.splitlines() if l.strip()]
    tally: collections.Counter = collections.Counter()
    for line in lines[:4] + lines[-3:]:
        for pattern in HEAD_PATTERNS:
            m = pattern.match(line)
            if m and m.group(1) in chapters:
                tally[m.group(1)] += 5
        m = PERSIAN_HEAD.match(line)
        if m and (m.group(1) or m.group(2)) in chapters:
            tally[m.group(1) or m.group(2)] += 5
    # Some PDFs extract a chapter's opening after its long local contents.
    # Accept the standalone label only when the following lines name that
    # chapter; references to another chapter do not provide this evidence.
    for index, line in enumerate(lines if not roman_front_matter(lines) else []):
        m = OPENING.match(line)
        if not m or m.group(1) not in chapters:
            continue
        title_words = chapters[m.group(1)]
        following = set(words(' '.join(lines[index + 1:index + 5])))
        if len(title_words) >= 2 and sum(w in following for w in title_words) / len(title_words) >= 0.75:
            tally[m.group(1)] += 12
    head = set(words(' '.join(lines[:12])))
    for number, title_words in chapters.items():
        if len(title_words) >= 2 and sum(w in head for w in title_words) / len(title_words) >= 0.9:
            if re.search(rf'\b{re.escape(number)}\b', ' '.join(lines[:12])):
                tally[number] += 4
    for m in FIGURE.finditer(text):
        if m.group(1) in chapters:
            tally[m.group(1)] += 1
    return tally


def openings(pages: list[tuple[int, str]], chapters: dict[str, list[str]]) -> dict[str, int]:
    """Find a chapter's first explicit opening in the book, not in its TOC.

    English openings have a standalone CHAPTER label followed by the title.
    Persian books can print their chapter number and فصل as a standalone line
    on the opening page and repeat it as a running head. The first such page
    is the opening; a contents line containing a page number is not a match.
    """
    found: dict[str, int] = {}
    for page, text in pages:
        lines = [line.strip() for line in text.splitlines() if line.strip()]
        english = []
        for index, line in enumerate(lines if not roman_front_matter(lines) else []):
            match = OPENING.match(line)
            if not match or match.group(1) not in chapters:
                continue
            title_words = chapters[match.group(1)]
            following = set(words(' '.join(lines[index + 1:index + 5])))
            if len(title_words) >= 2 and sum(word in following for word in title_words) / len(title_words) >= 0.75:
                english.append(match.group(1))
        # A contents page listing many chapters is not an opening page.
        if len(set(english)) == 1:
            found.setdefault(english[0], page)
        for line in lines:
            match = PERSIAN_HEAD.match(line)
            if match:
                number = match.group(1) or match.group(2)
                if number in chapters:
                    found.setdefault(number, page)
    return found


def bookmark_openings(toc: list[list], chapter_list: list[list[str]]) -> dict[str, int]:
    """Use a complete, ordered PDF outline when it identifies every chapter.

    Some books nest early and late chapters at a different level. The outline
    level with the most numbered chapters is primary; any gaps can be filled
    from another level only when its title agrees with the catalog title.
    """
    titles = {str(number): title for number, title in chapter_list}
    by_level: dict[int, dict[str, tuple[int, str]]] = {}
    for level, title, page in toc:
        match = BOOKMARK_NUMBER.match(title)
        if match and match.group(1) in titles:
            by_level.setdefault(level, {}).setdefault(match.group(1), (page, title[match.end():]))
    if not by_level:
        return {}
    dominant = max(by_level, key=lambda level: len(by_level[level]))
    found = {number: page for number, (page, _) in by_level[dominant].items()}
    for number, title in chapter_list:
        number = str(number)
        if number in found:
            continue
        want = set(words(title))
        for level, rows in by_level.items():
            if level == dominant or number not in rows:
                continue
            page, candidate_title = rows[number]
            have = set(words(candidate_title))
            if want and len(want & have) / len(want | have) >= 0.5:
                found[number] = page
                break
    ordered = [found.get(str(number)) for number, _ in chapter_list]
    if any(page is None for page in ordered) or any(a >= b for a, b in zip(ordered, ordered[1:])):
        return {}
    return found


def documented_openings(starts: dict[str, int], chapter_list: list[list[str]], page_count: int) -> dict[str, int]:
    """Validate chapter starts transcribed from the edition's own contents."""
    expected = [str(number) for number, _ in chapter_list]
    if set(starts) != set(expected):
        raise ValueError('documented chapter starts do not match the chapter catalog')
    ordered = [starts[number] for number in expected]
    if any(not isinstance(page, int) or isinstance(page, bool) or page < 1 or page > page_count for page in ordered):
        raise ValueError('documented chapter starts contain a page outside the PDF')
    if any(a >= b for a, b in zip(ordered, ordered[1:])):
        raise ValueError('documented chapter starts are not strictly increasing')
    return starts


def locate(pages, chapter_list: list[list[str]],
           bookmark_starts: dict[str, int] | None = None) -> dict:
    """Assign every page a chapter so that chapters run in book order.

    Votes alone are noisy (a page cites another chapter's figure, a running
    head is misread). The book's own order settles it: the best assignment in
    which the chapter never goes backwards, front matter before the first
    chapter and back matter after the last, is found by dynamic programming
    over pages x chapters, scoring each page by its votes for the chapter it
    is given.
    """
    chapters = {str(n): words(t) for n, t in chapter_list}
    keys = [str(n) for n, _ in chapter_list]
    k = len(keys)
    numbers: list[int] = []
    tallies: list[collections.Counter] = []
    page_openings: dict[str, int] = {}
    readable_pages = 0
    for page_number, page_text in pages:
        numbers.append(page_number)
        tallies.append(votes(page_text, chapters))
        if len(re.sub(r'\s', '', page_text)) >= 40:
            readable_pages += 1
        for chapter, opening in openings([(page_number, page_text)], chapters).items():
            page_openings.setdefault(chapter, opening)
        del page_text
    if numbers != list(range(1, len(numbers) + 1)):
        raise ValueError('PDF page reader did not return every page in order')

    # States: 0 = front matter, 1..k = chapters, k+1 = back matter.
    neg = float('-inf')
    score = [0.0] + [neg] * (k + 1)
    back: list[list[int]] = []
    for tally in tallies:
        emit = [0.0] + [float(tally.get(key, 0)) for key in keys] + [0.0]
        best_before = neg
        best_from = 0
        new_score = [neg] * (k + 2)
        pointers = [0] * (k + 2)
        for state in range(k + 2):
            # Stay in the same state, or arrive from any earlier one.
            stay = score[state]
            if best_before > stay:
                new_score[state], pointers[state] = best_before + emit[state], best_from
            else:
                new_score[state], pointers[state] = stay + emit[state], state
            if score[state] > best_before:
                best_before, best_from = score[state], state
        score = new_score
        back.append(pointers)

    state = max(range(k + 2), key=lambda s: score[s])
    path = []
    for pointers in reversed(back):
        path.append(state)
        state = pointers[state]
    path.reverse()

    # A chapter cannot begin before its own opening or after it. This also
    # stops figure references on earlier pages from shifting a boundary.
    starts = bookmark_starts if bookmark_starts else page_openings
    ordered = [(index + 1, starts[key]) for index, key in enumerate(keys) if key in starts]
    if all(before_page < after_page for (_, before_page), (_, after_page) in zip(ordered, ordered[1:])):
        for chapter_state, opening_page in ordered:
            for index, page in enumerate(numbers):
                if page < opening_page:
                    path[index] = min(path[index], chapter_state - 1)
                else:
                    path[index] = max(path[index], chapter_state)

    runs = []
    supported = collections.Counter()
    for page, state, tally in zip(numbers, path, tallies):
        chapter = keys[state - 1] if 1 <= state <= k else None
        if chapter is not None and tally.get(chapter, 0) > 0:
            supported[chapter] += 1
        if runs and runs[-1][0] == chapter:
            runs[-1][2] = page
        else:
            runs.append([chapter, page, page])
    seen = [r[0] for r in runs if r[0] is not None]
    return {
        'runs': runs,
        'pdf_pages': len(numbers),
        'readable_pages': readable_pages,
        'readable_page_ratio': round(readable_pages / len(numbers), 6) if numbers else 0,
        'chapters_seen': len(set(seen)),
        'chapters_listed': k,
        'not_seen': [key for key in keys if key not in set(seen)],
        'supported_pages': {r[0]: f"{supported[r[0]]}/{r[2] - r[1] + 1}" for r in runs if r[0] is not None},
    }


def atomic_json(path: Path, value: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    edition_rows = ',\n'.join(
        '    ' + json.dumps(edition, ensure_ascii=False) + ': '
        + json.dumps(entry, ensure_ascii=False, separators=(',', ': '))
        for edition, entry in sorted(value['editions'].items())
    )
    payload = (
        '{\n'
        + '  "format": ' + json.dumps(value['format'], ensure_ascii=False) + ',\n'
        + '  "notes": ' + json.dumps(value['notes'], ensure_ascii=False) + ',\n'
        + '  "editions": {\n' + edition_rows + '\n  }\n}\n'
    )
    if path.is_symlink():
        raise ValueError('chapter map output must not be a symlink')
    previous = path.stat() if path.exists() else None
    fd, temporary = tempfile.mkstemp(prefix='.chapter-map-', suffix='.tmp', dir=path.parent)
    try:
        with os.fdopen(fd, 'w', encoding='utf-8', newline='\n') as stream:
            os.fchmod(stream.fileno(), stat.S_IMODE(previous.st_mode) if previous else 0o644)
            stream.write(payload)
            stream.flush()
            os.fsync(stream.fileno())
        if previous and os.geteuid() == 0:
            os.chown(temporary, previous.st_uid, previous.st_gid)
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--only', action='append', required=True,
                        help='rebuild only this edition (repeatable; required) and preserve all other page maps')
    parser.add_argument('--storage-root', type=Path, default=Path('/srv/fanoos/shared/storage'))
    parser.add_argument('--mysql-defaults', type=Path, default=Path('/etc/fanoos/mysql-migrator.cnf'))
    parser.add_argument('--database', default='fanoos_prod')
    parser.add_argument('--min-readable-ratio', type=float, default=0.80)
    args = parser.parse_args()
    if not 0.5 <= args.min_readable_ratio <= 1:
        raise ValueError('invalid minimum readable-page ratio')
    sys.stdout.reconfigure(encoding='utf-8')

    tocs = json.loads(TOCS.read_text(encoding='utf-8'))['editions']
    pdf_manifest = json.loads(PDFS.read_text(encoding='utf-8'))['editions']
    requested = set(args.only)
    result = json.loads(OUT.read_text(encoding='utf-8'))['editions'] if OUT.exists() else {}
    built = set()
    for edition in sorted(requested):
        entry = pdf_manifest.get(edition)
        if not isinstance(entry, dict) or entry.get('pdf') != 'verified-server-pdf':
            print(f'not eligible  {edition}: exact verified server PDF is not listed')
            continue
        chapter_list = tocs.get(edition, {}).get('chapters') or []
        if not chapter_list:
            print(f'no chapter list  {edition}')
            continue
        pdf = open_verified_reference(edition, args.storage_root, args.mysql_defaults, args.database)
        chapter_pairs = [[str(chapter[0]), chapter[1]] for chapter in chapter_list]
        starts = {}
        if 'chapter_pdf_starts' in entry:
            starts = documented_openings(entry['chapter_pdf_starts'], chapter_pairs, pdf.page_count)
        else:
            starts = bookmark_openings(pdf.bookmarks(), chapter_pairs)
        located = locate(pages_of(pdf), chapter_pairs, starts)
        if located['pdf_pages'] != pdf.page_count:
            raise ValueError(f'{edition}: direct PDF read did not cover the full document')
        if located['readable_page_ratio'] < args.min_readable_ratio:
            raise ValueError(
                f"{edition}: only {located['readable_pages']}/{pdf.page_count} PDF pages have a usable text layer; "
                'keep pending and review the original PDF manually; no OCR/export was created')
        located['source_pdf_sha256'] = pdf.source_sha256
        located['source'] = 'verified-server-pdf'
        result[edition] = located
        built.add(edition)
        weak = [c for c, s in located['supported_pages'].items() if int(s.split('/')[0]) * 3 < int(s.split('/')[1])]
        print(f"{edition:40} pages {pdf.page_count:5}  readable {located['readable_pages']:5}  chapters {located['chapters_seen']:3}/{located['chapters_listed']:3}  outline {'yes' if starts else 'no '}  not seen {located['not_seen'][:10]}  weakly supported {weak[:10]}")

    missing = requested - built
    if missing:
        print(f'not built: {sorted(missing)}')
        return 1

    atomic_json(OUT, {
        'format': 'fanoos.reference-chapter-pages.v1',
        'notes': ['runs: [chapter, first PDF page, last PDF page] from the exact verified server PDF, in book order; chapter null = front or back matter. Each page is read transiently and independently; no text version, OCR output or search index is created.'],
        'editions': result,
    })
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
