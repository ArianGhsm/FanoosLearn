"""The part of a new catalog that an earlier one lacks, as an importable catalog file.

    python scripts/import/catalog_subset.py <old catalog.json> <new catalog.json> <out.json>

Importing the whole data/bank/catalog.json rewrites every chapter node and
validity row already in production. When a change only adds -- an exam type,
references, editions, validity rows -- import this subset instead: the exam
types and subjects (upserted unchanged), the references with only their new
editions (with those editions' chapters), and the validity rows the old
catalog did not have. It stops if the new catalog changes or drops an old
validity row, because then the subset would not carry the whole change.
Standard library only.
"""
import json
import sys


def subset(old: dict, new: dict) -> dict:
    old_editions = {f"{r['key']}@{e['key']}" for r in old['references'] for e in r['editions']}
    references = []
    for reference in new['references']:
        editions = [e for e in reference['editions'] if f"{reference['key']}@{e['key']}" not in old_editions]
        if editions:
            references.append({**reference, 'editions': editions})
    row = lambda v: json.dumps(v, sort_keys=True, ensure_ascii=False)  # noqa: E731
    new_rows = {row(v) for v in new['validity']}
    changed = [v for v in old['validity'] if row(v) not in new_rows]
    if changed:
        raise SystemExit(f'{len(changed)} validity rows of the old catalog are changed or gone, e.g. {changed[0]}; import the whole catalog')
    old_rows = {row(v) for v in old['validity']}
    return {
        'format': new['format'],
        'notes': 'Subset of data/bank/catalog.json made by scripts/import/catalog_subset.py: only what the earlier catalog lacks.',
        'exam_types': new['exam_types'],
        'subjects': new['subjects'],
        'concepts': [],
        'references': references,
        'validity': [v for v in new['validity'] if row(v) not in old_rows],
    }


if __name__ == '__main__':
    old_file, new_file, out_file = sys.argv[1:4]
    part = subset(json.load(open(old_file, encoding='utf-8')), json.load(open(new_file, encoding='utf-8')))
    with open(out_file, 'w', encoding='utf-8') as out:
        json.dump(part, out, ensure_ascii=False, indent=1)
    print(f"{len(part['references'])} references, {sum(len(r['editions']) for r in part['references'])} editions, "
          f"{sum(len(e.get('nodes', [])) for r in part['references'] for e in r['editions'])} nodes, {len(part['validity'])} validity rows")
