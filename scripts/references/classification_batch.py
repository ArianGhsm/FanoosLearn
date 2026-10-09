#!/usr/bin/env python3
"""Start one classification batch: one sitting, one subject.

Prints the subject's questions with their official answer, and the editions
the official reference list names for that exam type, year and subject --
with the nearest available edition for any edition whose text is missing --
and writes a query file for find_in_books.py with every question's editions
filled in and its terms left empty for the classifier to write.

docs/product/09_CHAPTER_CLASSIFICATION.md is the procedure this belongs to.

    python scripts/references/classification_batch.py --sitting=<residency-1403-1.json> \\
        --exam-type=residency --subject=endodontics --out=<queries.json>
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
BANK = REPO / 'data' / 'bank'


def official_editions(catalog: dict, exam_type: str, year: int, subject: str) -> list[dict]:
    return [v for v in catalog['validity'] if v.get('exam_type') == exam_type and int(v['year']) == year and v['subject'] == subject]


def searchable(edition: str, texts: dict, include_nearest: bool = False) -> str | None:
    """Exact edition by default; historical nearest substitutes require opt-in."""
    entry = texts.get(edition, {})
    if 'missing' not in entry:
        return edition
    if not include_nearest:
        return None
    nearest = entry.get('nearest')
    return nearest if nearest and 'missing' not in texts.get(nearest, {}) else None


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--sitting', required=True)
    parser.add_argument('--exam-type', help="defaults to the sitting's own exam_type")
    parser.add_argument('--subject', required=True)
    parser.add_argument('--out', required=True)
    parser.add_argument('--include-nearest', action='store_true',
                        help='historical audit only; default skips absent exact official editions')
    args = parser.parse_args()
    sys.stdout.reconfigure(encoding='utf-8')

    sitting = json.loads(Path(args.sitting).read_text(encoding='utf-8'))
    year = int(sitting['year'])
    catalog = json.loads((BANK / 'catalog.json').read_text(encoding='utf-8'))
    texts = json.loads((BANK / 'reference-texts.json').read_text(encoding='utf-8'))['editions']
    exam_type = args.exam_type or sitting['exam_type']

    rows = official_editions(catalog, exam_type, year, args.subject)
    search = []
    for row in rows:
        found = searchable(row['edition'], texts, args.include_nearest)
        note = 'text' if found == row['edition'] else (f'MISSING, legacy nearest {found}' if found else 'MISSING, pending exact edition')
        print(f"OFFICIAL {row['edition']} [{note}] scope: {row.get('scope', '')}")
        if found and found not in search:
            search.append(found)
    if not rows:
        print(f'no official reference for {exam_type} {year} {args.subject}: these questions get no source')

    queries = []
    for q in sitting['questions']:
        if q['subject'] != args.subject:
            continue
        choices = ' / '.join(c if isinstance(c, str) else c.get('text', '') for c in q['choices'])
        print(f"Q{q['number']} [{q['answer'].get('choice')}] {q['stem']} || {choices}")
        queries.append({'key': str(q['number']), 'editions': search, 'terms': []})
    Path(args.out).write_text(json.dumps(queries, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
