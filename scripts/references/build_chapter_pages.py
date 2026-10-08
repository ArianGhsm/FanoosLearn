#!/usr/bin/env python3
"""Work out which chapter every page of every reference edition belongs to.

Reads .local/references/<edition>.txt (build_reference_texts.py) and the
edition's chapter list (data/bank/reference-tocs.json), and writes
data/bank/reference-chapter-pages.json: for each edition, the runs of PDF
pages that belong to each chapter. Classification turns "the answer is on
page 412" into "chapter 14" with it.

Each page votes for its chapter from what the book prints on it:
- the running head ("CHAPTER 13", "17 • Nonpharmacologic…", "4. Fundamentals…");
- the chapter's opening: its number with its title in the first lines;
- the numbers of its figures, tables and boxes ("Fig. 44.7", "Table 16-2").
A page with no vote takes its neighbours' chapter only when both agree. A
page nobody can place stays unplaced -- it is reported, never guessed.

    python scripts/references/build_chapter_pages.py
"""
from __future__ import annotations

import argparse
import collections
import json
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
TOCS = REPO / 'data' / 'bank' / 'reference-tocs.json'
OUT = REPO / 'data' / 'bank' / 'reference-chapter-pages.json'
PAGE = re.compile(r'^=== PAGE (\d+) ===$', re.M)
STOP = {'and', 'the', 'of', 'in', 'for', 'to', 'a', 'an', 'with', 'on', 'its', 'their', 'or'}

HEAD_PATTERNS = [
    re.compile(r'^\s*CHAPTER\s+(\d{1,3})\b', re.I),
    re.compile(r'^\s*(\d{1,3})\s*[•·]\s+\S'),
    re.compile(r'^\s*(\d{1,3})\.\s+[A-Z][a-z]'),
]
FIGURE = re.compile(r'\b(?:e?Fig(?:ure)?s?|Tables?|Box(?:es)?)\.?\s*(\d{1,3})\s*[.\-–]\s*\d{1,3}', re.I)


def words(text: str) -> list[str]:
    return [w for w in re.findall(r'[a-z0-9]+', text.lower().replace('’', "'")) if w not in STOP]


def pages_of(path: Path) -> list[tuple[int, str]]:
    raw = path.read_text(encoding='utf-8')
    marks = list(PAGE.finditer(raw))
    return [
        (int(m.group(1)), raw[m.end():nxt.start() if nxt else len(raw)])
        for m, nxt in zip(marks, marks[1:] + [None])
    ]


def votes(text: str, chapters: dict[str, list[str]]) -> collections.Counter:
    lines = [l for l in text.splitlines() if l.strip()]
    tally: collections.Counter = collections.Counter()
    for line in lines[:4] + lines[-3:]:
        for pattern in HEAD_PATTERNS:
            m = pattern.match(line)
            if m and m.group(1) in chapters:
                tally[m.group(1)] += 5
    head = set(words(' '.join(lines[:12])))
    for number, title_words in chapters.items():
        if len(title_words) >= 2 and sum(w in head for w in title_words) / len(title_words) >= 0.9:
            if re.search(rf'\b{re.escape(number)}\b', ' '.join(lines[:12])):
                tally[number] += 4
    for m in FIGURE.finditer(text):
        if m.group(1) in chapters:
            tally[m.group(1)] += 1
    return tally


def locate(pages: list[tuple[int, str]], chapter_list: list[list[str]]) -> dict:
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
    numbers = [p for p, _ in pages]
    tallies = [votes(text, chapters) for _, text in pages]

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
        'chapters_seen': len(set(seen)),
        'chapters_listed': k,
        'not_seen': [key for key in keys if key not in set(seen)],
        'supported_pages': {r[0]: f"{supported[r[0]]}/{r[2] - r[1] + 1}" for r in runs if r[0] is not None},
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--local', default=str(REPO / '.local'))
    args = parser.parse_args()
    sys.stdout.reconfigure(encoding='utf-8')

    tocs = json.loads(TOCS.read_text(encoding='utf-8'))['editions']
    result = {}
    for path in sorted((Path(args.local) / 'references').glob('*.txt')):
        edition = path.stem
        chapter_list = tocs.get(edition, {}).get('chapters') or []
        if not chapter_list:
            print(f'no chapter list  {edition}')
            continue
        pages = pages_of(path)
        located = locate(pages, [[str(c[0]), c[1]] for c in chapter_list])
        result[edition] = located
        weak = [c for c, s in located['supported_pages'].items() if int(s.split('/')[0]) * 3 < int(s.split('/')[1])]
        print(f"{edition:40} pages {len(pages):5}  chapters {located['chapters_seen']:3}/{located['chapters_listed']:3}  not seen {located['not_seen'][:10]}  weakly supported {weak[:10]}")

    OUT.write_text(json.dumps({
        'format': 'fanoos.reference-chapter-pages.v1',
        'notes': ['runs: [chapter, first PDF page, last PDF page] in .local/references/<edition>.txt, in book order; chapter null = front or back matter. supported_pages: pages in the run whose own text voted for the chapter. Built by scripts/references/build_chapter_pages.py.'],
        'editions': result,
    }, ensure_ascii=False) + '\n', encoding='utf-8', newline='\n')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
