#!/usr/bin/env python3
"""Produce a non-mutating, non-content-bearing inventory of unclassified questions.

See docs/product/09_CHAPTER_CLASSIFICATION.md. Does not mark questions 'none',
change sources, or decide chapters. Accepts the exact site-matching sitting.
"""
from __future__ import annotations

import argparse
import json
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEFAULT_CATALOG = ROOT / 'data' / 'bank' / 'catalog.json'


def build_inventory(sitting: dict, catalog: dict) -> dict:
    exam_type, year, round_ = sitting['exam_type'], int(sitting['year']), int(sitting['round'])
    validity = defaultdict(list)
    for row in catalog.get('validity', []):
        if row.get('exam_type') == exam_type and int(row.get('year', 0)) == year:
            validity[row['subject']].append(row['edition'])
    by_subject = defaultdict(lambda: {'total': 0, 'classified_human': 0,
                                      'classified_ai': 0, 'classified_other': 0,
                                      'unclassified': 0, 'needs_official_reference_review': 0,
                                      'question_keys': []})
    seen = set()
    for q in sitting['questions']:
        number = int(q['number'])
        if number in seen:
            raise ValueError(f'duplicate question number: {number}')
        seen.add(number)
        subject = q['subject']
        entry = by_subject[subject]
        entry['total'] += 1
        sources = q.get('sources') or []
        if not isinstance(sources, list):
            raise ValueError(f'question {number}: sources must be an array')
        if sources:
            origins = {s.get('origin') for s in sources if isinstance(s, dict)}
            if 'human' in origins:
                entry['classified_human'] += 1
            elif 'ai' in origins:
                entry['classified_ai'] += 1
            else:
                entry['classified_other'] += 1
        else:
            entry['unclassified'] += 1
            key = f'{exam_type}-{year}-{round_}-{number:03d}'
            entry['question_keys'].append(key)
            if not validity.get(subject):
                entry['needs_official_reference_review'] += 1
    for subject, entry in by_subject.items():
        entry['question_keys'].sort()
        entry['official_editions'] = sorted(set(validity.get(subject, [])))
    totals = {field: sum(row[field] for row in by_subject.values())
              for field in ('total', 'classified_human', 'classified_ai',
                            'classified_other', 'unclassified',
                            'needs_official_reference_review')}
    return {'format': 'fanoos.classification-inventory/1', 'exam_type': exam_type,
            'year': year, 'round': round_, 'totals': totals,
            'by_subject': dict(sorted(by_subject.items()))}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--sitting', required=True, type=Path,
                        help='exact sitting that matches the deployed question text')
    parser.add_argument('--catalog', default=DEFAULT_CATALOG, type=Path)
    parser.add_argument('--out', type=Path, help='optional machine-readable report')
    args = parser.parse_args()
    report = build_inventory(json.loads(args.sitting.read_text(encoding='utf-8')),
                             json.loads(args.catalog.read_text(encoding='utf-8')))
    payload = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
    if args.out:
        args.out.parent.mkdir(parents=True, exist_ok=True)
        args.out.write_text(payload, encoding='utf-8')
    else:
        sys.stdout.write(payload)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
