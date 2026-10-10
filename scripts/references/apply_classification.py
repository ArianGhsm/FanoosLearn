#!/usr/bin/env python3
"""Check classification decisions against the books, and write them into a sitting.

A decision says where in an official reference a question comes from:

    {"number": 22, "edition": "torabinejad-endodontics@6e", "chapter": "20",
     "page": 446, "confidence": 0.97,
     "evidence": "the flap should be compressed with a saline soaked gauze ... formation of a hematoma under the flap"}

or that it has none:

    {"number": 12, "none": "the official list names no reference for this subject"}

Nothing is taken on trust. A decision is accepted only when
- the question exists in the sitting;
- the edition is one the official list names for this exam type, year and
  subject -- or the nearest edition data/bank/reference-texts.json names for
  a missing official one, in which case the chapter is carried over to the
  official edition by its title and the source cites the official edition;
- the chapter is a chapter of that edition in the catalog;
- the page lies inside that chapter (data/bank/reference-chapter-pages.json);
- every fragment of the evidence (split on "...", each of at least four
  words) is printed on that page of .local/references/<edition>.txt;
- the confidence is a number from 0 to 1;
- it does not replace a human-checked source with a different chapter (or
  with none) unless it says why in "override_human".
Every rejection is listed with its reason; with any rejection the sitting is
not written unless --partial is given.

The sitting gains, per question, sources[0] = {ref, page (as printed in the
book when the page carries its number, else "pdf N"), anchor (the evidence),
primary, origin "ai", confidence}. Re-importing it with import-bank.php
records them (docs/product/06_QUESTION_FORMAT.md).

    python scripts/references/apply_classification.py --sitting=<in.json> \\
        --decisions=<dir or file> --out=<out.json> [--partial]
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
BANK = REPO / 'data' / 'bank'
PAGE = re.compile(r'^=== PAGE (\d+) ===$', re.M)


def flat(text: str) -> str:
    # Some PDFs set ligatures (ff, fi, fl) as private-use glyphs; they print
    # as nothing, so a quote copied from the page has them missing too.
    text = ''.join(c for c in text if not 0xE000 <= ord(c) <= 0xF8FF)
    text = text.lower().replace('’', "'").replace('‘', "'").replace('“', '"').replace('”', '"')
    text = text.replace('ﬁ', 'fi').replace('ﬂ', 'fl').replace('¿', 'fi').replace('À', 'fl').replace('­', '')
    text = text.replace('ي', 'ی').replace('ى', 'ی').replace('ك', 'ک')
    text = re.sub(r'-\s*\n\s*', '', text)
    return re.sub(r'[^\w%.,;:()/+\-–]+', ' ', text, flags=re.UNICODE).strip()


def fragments(evidence: str) -> list[str]:
    return [f.strip() for f in re.split(r'\.\.\.|…', evidence) if f.strip()]


class Books:
    def __init__(self, local: Path):
        self.local = local
        self.pages: dict[str, dict[int, str]] = {}

    def page(self, edition: str, number: int) -> str | None:
        if edition not in self.pages:
            path = self.local / 'references' / f'{edition}.txt'
            if not path.exists():
                self.pages[edition] = {}
            else:
                raw = path.read_text(encoding='utf-8')
                marks = list(PAGE.finditer(raw))
                self.pages[edition] = {
                    int(m.group(1)): raw[m.end():nxt.start() if nxt else len(raw)]
                    for m, nxt in zip(marks, marks[1:] + [None])
                }
        return self.pages[edition].get(number)


def printed_page(text: str) -> str | None:
    lines = [l.strip() for l in text.splitlines() if l.strip()]
    for line in lines[:3] + lines[-3:]:
        m = re.match(r'^(\d{1,4})(\s|$)', line) or re.search(r'(?:^|\s)(\d{1,4})$', line)
        if m and len(line) < 90:
            return m.group(1)
    return None


def chapter_nodes(catalog: dict, edition: str) -> dict[str, dict]:
    ref, ed = edition.split('@')
    for r in catalog['references']:
        if r['key'] == ref:
            for e in r['editions']:
                if e['key'] == ed:
                    return {n['number']: n for n in e.get('nodes', []) if n.get('kind') == 'chapter'}
    return {}


def words(title: str) -> set[str]:
    return {w for w in re.findall(r'[a-z0-9]+', title.lower()) if len(w) > 2}


def carry_over(catalog: dict, source_edition: str, chapter: str, target_edition: str) -> dict | None:
    """The official edition's chapter with the same title as the nearest edition's chapter."""
    title = chapter_nodes(catalog, source_edition).get(chapter, {}).get('title', '')
    want = words(title)
    best, best_score = None, 0.0
    for node in chapter_nodes(catalog, target_edition).values():
        have = words(node['title'])
        score = len(want & have) / max(1, len(want | have))
        if score > best_score:
            best, best_score = node, score
    return best if best_score >= 0.6 else None


def human_guard(question: dict, new_ref: str | None, decision: dict) -> tuple[str | None, bool]:
    """A chapter a person has checked is never replaced silently.

    Returns (why it is rejected or None, whether to keep the existing human
    source as it is). A different chapter, or none, needs "override_human" with
    the reason. On 1404 an AI pass would otherwise have overwritten 49 checked
    chapters, 16 of them with weaker matches.
    """
    existing = [s for s in (question.get('sources') or []) if s.get('origin') == 'human']
    if not existing:
        return None, False
    if existing[0].get('ref') == new_ref:
        return None, True
    if str(decision.get('override_human', '')).strip():
        return None, False
    return (f'the question already has a human-checked chapter ({existing[0].get("ref")}); '
            'leave it undecided, or set "override_human" to the reason this one is better'), False


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--sitting', required=True)
    parser.add_argument('--decisions', required=True, help='a decisions file, or a directory of them')
    parser.add_argument('--out', required=True)
    parser.add_argument('--partial', action='store_true', help='write the accepted decisions even if some were rejected')
    parser.add_argument('--allow-nearest', action='store_true',
                        help='historical audit only: permit previously approved nearest-edition substitutions')
    parser.add_argument('--pdf-pages', action='store_true')
    parser.add_argument('--local', default=str(REPO / '.local'))
    args = parser.parse_args()
    sys.stdout.reconfigure(encoding='utf-8')

    sitting = json.loads(Path(args.sitting).read_text(encoding='utf-8'))
    year, exam_type = int(sitting['year']), sitting['exam_type']
    catalog = json.loads((BANK / 'catalog.json').read_text(encoding='utf-8'))
    texts = json.loads((BANK / 'reference-texts.json').read_text(encoding='utf-8'))['editions']
    runs = json.loads((BANK / 'reference-chapter-pages.json').read_text(encoding='utf-8'))['editions']
    books = Books(Path(args.local))

    official: dict[str, set[str]] = {}
    for v in catalog['validity']:
        if v.get('exam_type') == exam_type and int(v['year']) == year:
            official.setdefault(v['subject'], set()).add(v['edition'])

    source = Path(args.decisions)
    # A directory holds every year's decisions; only this sitting's are read
    # (<year>-<subject>.json, docs/product/09_CHAPTER_CLASSIFICATION.md).
    files = sorted(source.glob(f'{year}-*.json')) if source.is_dir() else [source]
    decisions = [d for f in files for d in json.loads(f.read_text(encoding='utf-8'))]
    questions = {q['number']: q for q in sitting['questions']}

    accepted, rejected, seen = {}, [], set()
    keep_human: set[int] = set()
    for d in decisions:
        n = d.get('number')
        why = None
        q = questions.get(n)
        if q is None:
            why = 'no such question in the sitting'
        elif n in seen:
            why = 'decided twice'
        seen.add(n)
        if why is None and 'none' in d:
            if official.get(q['subject']) and not str(d['none']).strip():
                why = 'a question with official references needs a reason to have none'
            else:
                accepted[n] = None
        elif why is None:
            edition, chapter = d.get('edition', ''), str(d.get('chapter', ''))
            names = official.get(q['subject'], set())
            cite, cite_chapter = edition, chapter
            if edition not in names and not args.allow_nearest:
                why = 'nearest-edition substitutions are paused; use exact official edition or keep pending'
            if edition not in names and why is None:
                stand_in_for = [e for e in names if texts.get(e, {}).get('nearest') == edition and 'missing' in texts.get(e, {})]
                if not stand_in_for:
                    why = f'{edition} is not an official {exam_type} {year} reference for {q["subject"]}'
                else:
                    cite = stand_in_for[0]
                    # Editions get reorganised (one chapter split in two, chapters
                    # renamed): the classifier may name the official edition's
                    # chapter itself; otherwise it is carried over by title.
                    named = str(d.get('official_chapter', '')).strip()
                    if named:
                        if named not in chapter_nodes(catalog, cite):
                            why = f'{cite} has no chapter {named}'
                        else:
                            cite_chapter = named
                    else:
                        node = carry_over(catalog, edition, chapter, cite)
                        if node is None:
                            why = f'chapter {chapter} of {edition} has no same-titled chapter in {cite}; name it with official_chapter'
                        else:
                            cite_chapter = node['number']
            nodes = chapter_nodes(catalog, edition)
            page = d.get('page')
            if why is None and chapter not in nodes:
                why = f'{edition} has no chapter {chapter}'
            if why is None:
                in_chapter = any(r[0] == chapter and r[1] <= int(page) <= r[2] for r in runs.get(edition, {}).get('runs', []))
                if not in_chapter:
                    why = f'page {page} is not in chapter {chapter} of {edition}'
            text = books.page(edition, int(page)) if why is None else None
            if why is None and text is None:
                why = f'{edition} has no text for page {page}'
            if why is None:
                pieces = fragments(str(d.get('evidence', '')))
                if not pieces or any(len(p.split()) < 4 for p in pieces):
                    why = 'evidence must be quoted fragments of at least four words each, joined by "..."'
                else:
                    page_text = flat(text)
                    missing = [p for p in pieces if flat(p) not in page_text]
                    if missing:
                        why = f'evidence not on page {page}: "{missing[0][:80]}"'
            confidence = d.get('confidence')
            if why is None and not (isinstance(confidence, (int, float)) and 0 <= confidence <= 1):
                why = 'confidence must be a number from 0 to 1'
            if why is None:
                cite_node = chapter_nodes(catalog, cite)[cite_chapter]
                printed = None if args.pdf_pages else printed_page(text)
                accepted[n] = {
                    'ref': f"{cite}#{cite_node['key']}",
                    'page': printed if printed else f'pdf {page}',
                    'anchor': str(d['evidence'])[:500],
                    'primary': True,
                    'origin': 'ai',
                    'confidence': {'source': confidence, 'node': confidence, 'page': confidence},
                }
                if cite != edition:
                    accepted[n]['found_in'] = f'{edition}#ch{chapter} (nearest edition; the official one was not available)'
        if why is None and n in accepted:
            why, keep = human_guard(q, None if accepted[n] is None else accepted[n]['ref'], d)
            if why:
                del accepted[n]
            elif keep:
                keep_human.add(n)
        if why:
            rejected.append((n, why))

    for n, why in rejected:
        print(f'REJECTED Q{n}: {why}')
    undecided = sorted(set(questions) - set(accepted) - {n for n, _ in rejected})
    print(f'{len(accepted)} accepted, {len(rejected)} rejected, {len(undecided)} not yet decided')
    if rejected and not args.partial:
        return 1

    for n, src in accepted.items():
        q = questions[n]
        if n in keep_human:
            continue  # the same chapter a person already checked: keep their source
        if src is None:
            q.pop('sources', None)
        else:
            found_in = src.pop('found_in', None)
            if found_in:
                # The page and quote belong to the nearest edition; the anchor says so.
                src['anchor'] = f'[{found_in}] {src["anchor"]}'[:500]
                src['page'] = None
            q['sources'] = [{k: v for k, v in src.items() if v is not None}]
    sitting['notes'] = (sitting.get('notes') or '') + f' Sources from {len(accepted)} checked classification decisions (scripts/references/apply_classification.py).'
    Path(args.out).write_text(json.dumps(sitting, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    print(f'wrote {args.out}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
