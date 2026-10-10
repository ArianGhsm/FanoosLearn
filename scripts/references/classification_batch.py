#!/usr/bin/env python3
"""Prepare query terms for one classification batch: one sitting, one subject.

Prints the subject's questions with their official answer, and the editions
the official reference list names for that exam type, year and subject.
Missing exact editions are skipped by default; historical nearest searches
require --include-nearest explicitly.
and writes a temporary query file for find_in_books.py with each question's
eligible exact PDF editions. The query file contains no PDF text and must be
removed after the search operation.

docs/product/09_CHAPTER_CLASSIFICATION.md is the procedure this belongs to.

    python scripts/references/classification_batch.py --sitting=<residency-1403-1.json> \\
        --exam-type=residency --subject=endodontics --out=<queries.json>
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import tempfile
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
BANK = REPO / 'data' / 'bank'


def save_query_file(path: Path, queries: list[dict]) -> None:
    parent = path.parent.resolve(strict=True)
    if path.is_symlink():
        raise ValueError('query output must not be a symlink')
    payload = json.dumps(queries, ensure_ascii=False, indent=1) + '\n'
    fd, temporary = tempfile.mkstemp(prefix='.classification-queries-', suffix='.tmp', dir=parent)
    try:
        with os.fdopen(fd, 'w', encoding='utf-8', newline='\n') as stream:
            os.fchmod(stream.fileno(), 0o600)
            stream.write(payload)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def official_editions(catalog: dict, exam_type: str, year: int, subject: str) -> list[dict]:
    return [v for v in catalog['validity'] if v.get('exam_type') == exam_type and int(v['year']) == year and v['subject'] == subject]


def pdf_available(edition: str, pdfs: dict, include_nearest: bool = False) -> str | None:
    """List only exact PDF-backed editions; the current PDF is checked at use time."""
    entry = pdfs.get(edition, {})
    if entry.get('pdf') == 'verified-server-pdf':
        return edition
    if not include_nearest or 'missing' not in entry:
        return None
    nearest = entry.get('nearest')
    return nearest if nearest and pdfs.get(nearest, {}).get('pdf') == 'verified-server-pdf' else None


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
    pdfs = json.loads((BANK / 'reference-pdfs.json').read_text(encoding='utf-8'))['editions']
    exam_type = args.exam_type or sitting['exam_type']

    rows = official_editions(catalog, exam_type, year, args.subject)
    search = []
    for row in rows:
        found = pdf_available(row['edition'], pdfs, args.include_nearest)
        note = 'PDF source listed' if found == row['edition'] else (f'MISSING, legacy nearest {found}' if found else 'MISSING, pending exact edition')
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
    save_query_file(Path(args.out), queries)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
