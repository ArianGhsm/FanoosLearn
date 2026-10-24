#!/usr/bin/env python3
"""Prepare query terms for one classification batch: one sitting, one subject.

Prints the subject's questions with their official answer, and the editions
the official reference list names for that exam type, year and subject.
Exact editions are preferred. Residency years 1396–1398, and non-residency
type/subject/year combinations with no exact official list, may use an
explicitly labelled same-lineage proxy; historical
`reference-pdfs.json` nearest searches still require `--include-nearest`.
The script writes a temporary query file for find_in_books.py with each
question's eligible exact PDF editions. The query file contains no PDF text
and must be removed after the search operation.

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
from retrospective_reference_policy import is_retrospective, selected_reference_rows
from same_lineage_proxy_policy import (
    BASIS as SAME_LINEAGE_PROXY_BASIS,
    is_proxy_exam_year,
    requires_newest_first,
    selected_non_residency_proxy_rows,
    selected_reference_rows as selected_same_lineage_rows,
)

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
    maps = json.loads((BANK / 'reference-chapter-pages.json').read_text(encoding='utf-8'))['editions']
    exam_type = args.exam_type or sitting['exam_type']

    rows = selected_reference_rows(catalog, exam_type, year, args.subject, pdfs, maps)
    same_lineage_selection = exam_type == 'residency' and is_proxy_exam_year(year)
    if same_lineage_selection:
        rows = selected_same_lineage_rows(catalog, exam_type, year, args.subject, pdfs, maps)
    elif exam_type in ('national', 'board', 'promotion'):
        proxy_rows = selected_non_residency_proxy_rows(
            catalog, exam_type, year, args.subject, pdfs, maps)
        if proxy_rows:
            rows = proxy_rows
            same_lineage_selection = True
    if is_retrospective(year):
        print(f'RETROSPECTIVE {exam_type} {year}: using verified 1397+ proxy reference years; NOT official for exam year')
    if (requires_newest_first(year)
            or (same_lineage_selection and exam_type != 'residency')):
        print('ORDER: search RANK 0 (newest) first; cite an older edition only after the newer ones lack the answer-bearing page, listing them in newer_editions_searched')
    search = []
    for row in rows:
        found = pdf_available(
            row['edition'], pdfs,
            args.include_nearest and not same_lineage_selection,
        )
        if same_lineage_selection and row.get('_eligible') is not True:
            found = None
        if found == row['edition']:
            note = 'PDF source listed'
        elif same_lineage_selection and row.get('_eligible') is not True:
            note = f"PENDING, {row.get('_proxy_reason') or 'no eligible verified PDF/map/scope candidate'}"
        elif same_lineage_selection and row.get('_proxy') is True:
            note = 'MISSING, pending usable same-lineage PDF and hash-bound chapter map'
        elif found:
            note = f'MISSING, legacy nearest {found}'
        else:
            note = 'MISSING, pending exact edition'
        proxy = is_retrospective(year) or (same_lineage_selection and row.get('_proxy') is True)
        label = f"PROXY_REFERENCE_YEAR_{row['year']}" if proxy else 'OFFICIAL'
        if same_lineage_selection and row.get('_proxy') is True:
            print(f"  BASIS {SAME_LINEAGE_PROXY_BASIS}: same reference family, actual reference year kept")
        rank = (f" RANK {row['_rank']} in {row['_family']}"
                if isinstance(row.get('_rank'), int) else '')
        print(f"{label} {row['edition']}{rank} [{note}] scope: {row.get('scope', '')}")
        if found and found not in search:
            search.append(found)
    if not rows:
        if is_retrospective(year):
            print(f'PENDING: no verified 1397+ proxy reference for {exam_type} {year} {args.subject}; do not assign chapter or page')
        elif same_lineage_selection:
            print(f'PENDING: no eligible same-lineage verified reference for {exam_type} {year} {args.subject}; do not assign chapter or page')
        else:
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
