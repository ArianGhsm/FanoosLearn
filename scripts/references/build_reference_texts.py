#!/usr/bin/env python3
"""Build private page-text indexes directly from verified server PDFs.

For each requested edition this wrapper calls extract_server_reference.py,
which resolves and verifies the current approved private PDF object and reads
it in place. The generated .txt is a regenerable search/validation index tied
to the PDF SHA-256; it is never accepted as an independent source. A standalone
text file, local corpus or chapter extract cannot be used to build an index.

Run only on the authorized FANOOS server account, for the editions needed by
the current batch. Missing or unusable exact editions are reported as pending.

    sudo -u fanoosupd python3 scripts/references/build_reference_texts.py \\
        --only proffit-orthodontics@6e --local=/srv/fanoos/shared/research
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
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


def from_text(edition: str, source: Path) -> tuple[str, int, int]:
    """Legacy page-marker normalizer; never used as a classification input."""
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
    parser.add_argument('--local', default='/srv/fanoos/shared/research', help='protected server research workspace')
    parser.add_argument('--only', action='append', required=True,
                        help='exact edition to extract from its verified server PDF (repeatable; required)')
    args = parser.parse_args()

    sys.stdout.reconfigure(encoding='utf-8')
    manifest = json.loads(MANIFEST.read_text(encoding='utf-8'))['editions']
    failed = 0
    for edition in dict.fromkeys(args.only):
        entry = manifest.get(edition)
        if not isinstance(entry, dict):
            failed += 1
            print(f'FAILED   {edition}: edition is not in the approved manifest')
            continue
        if entry.get('missing'):
            print(f'pending  {edition}: exact verified server PDF is not listed as available')
            continue
        if entry.get('pdf') != 'verified-server-pdf':
            failed += 1
            print(f'FAILED   {edition}: manifest does not require the verified server PDF')
            continue
        command = [
            sys.executable,
            str(REPO / 'scripts' / 'references' / 'extract_server_reference.py'),
            '--edition', edition,
            '--local', args.local,
            '--apply',
        ]
        result = subprocess.run(command, check=False)
        if result.returncode:
            failed += 1
            print(f'FAILED   {edition}: verified PDF extraction did not complete')
        else:
            print(f'built    {edition}: from current verified server PDF')
    return 1 if failed else 0


if __name__ == '__main__':
    raise SystemExit(main())
