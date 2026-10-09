#!/usr/bin/env python3
"""Build the one full-book text of every official reference edition.

The rule (docs/PROJECT_PRINCIPLES.md, decision 6): each edition in
data/bank/reference-texts.json has exactly one text file,
.local/references/<edition>.txt, holding the whole book from its first page
to its last, with a marker before every PDF page:

    === PAGE 12 ===

Chapter classification reads these files and nothing else -- no summaries,
no chapter-by-chapter extracts, no translations.

An edition is built from a *securely authorized and verified* original PDF
supplied from the server's private reference library (--library, or
FANOOS_BOOKS_DIR), or from a previously extracted full-book text (under
--local). The source bridge from protected object storage must be reviewed
and space-checked first. No laptop or full public PDF copy is required.
A missing or unready edition is reported, not invented.

    python scripts/references/build_reference_texts.py --library "/path/to/approved/server-reference-input" --local /srv/fanoos/shared/research
    python scripts/references/build_reference_texts.py --only proffit-orthodontics@6e
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
MANIFEST = REPO / 'data' / 'bank' / 'reference-texts.json'
LEGACY_PAGE = re.compile(r'^--- SOURCE PDF PAGE (\d+) ---$', re.M)


def header(edition: str, source: str, pages: int) -> str:
    return (
        f'# FANOOS reference text\n'
        f'# edition: {edition}\n'
        f'# source: {source}\n'
        f'# pages: {pages}\n'
    )


def from_pdf(edition: str, pdf: Path) -> tuple[str, int, int]:
    import pymupdf  # PyMuPDF; only needed when a PDF is read

    doc = pymupdf.open(pdf)
    parts = [header(edition, pdf.name, doc.page_count)]
    empty = 0
    for index, page in enumerate(doc, start=1):
        text = page.get_text().strip()
        if not text:
            empty += 1
        parts.append(f'\n=== PAGE {index} ===\n{text}\n')
    return ''.join(parts), doc.page_count, empty


def from_text(edition: str, source: Path) -> tuple[str, int, int]:
    raw = source.read_text(encoding='utf-8')
    markers = list(LEGACY_PAGE.finditer(raw))
    if not markers:
        raise ValueError(f'{source.name}: no page markers')
    parts = []
    pages = 0
    for current, following in zip(markers, markers[1:] + [None]):
        body = raw[current.end():following.start() if following else len(raw)].strip()
        parts.append(f'\n=== PAGE {current.group(1)} ===\n{body}\n')
        pages += 1
    return header(edition, source.name, pages) + ''.join(parts), pages, 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--library', default=os.environ.get('FANOOS_BOOKS_DIR'), help='approved protected server reference source (PDFs)')
    parser.add_argument('--local', default=str(REPO / '.local'), help='the git-ignored .local directory')
    parser.add_argument('--only', action='append', help='build just this edition (repeatable)')
    parser.add_argument('--force', action='store_true', help='rebuild files that already exist')
    args = parser.parse_args()

    sys.stdout.reconfigure(encoding='utf-8')
    manifest = json.loads(MANIFEST.read_text(encoding='utf-8'))['editions']
    local = Path(args.local)
    out_dir = local / 'references'
    out_dir.mkdir(parents=True, exist_ok=True)

    failed = 0
    for edition, entry in sorted(manifest.items()):
        if args.only and edition not in args.only:
            continue
        target = out_dir / f'{edition}.txt'
        if entry.get('missing'):
            nearest = entry.get('nearest')
            print(f'missing  {edition}' + (f'  (meanwhile: {nearest})' if nearest else ''))
            continue
        if target.exists() and not args.force:
            print(f'exists   {edition}')
            continue
        try:
            if 'pdf' in entry:
                if not args.library:
                    raise ValueError('needs --library or FANOOS_BOOKS_DIR')
                text, pages, empty = from_pdf(edition, Path(args.library) / entry['pdf'])
            else:
                text, pages, empty = from_text(edition, local / entry['text'])
        except Exception as error:  # report every edition, then fail
            failed += 1
            print(f'FAILED   {edition}: {error}')
            continue
        target.write_text(text, encoding='utf-8', newline='\n')
        print(f'built    {edition}: {pages} pages ({empty} without text), {len(text) // 1024} KB')
    return 1 if failed else 0


if __name__ == '__main__':
    raise SystemExit(main())
