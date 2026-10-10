#!/usr/bin/env python3
"""Brings exam papers from the owner's Google Drive into the bank, in three steps.

Run on the FANOOS host as root (the `gdrive` rclone remote is root's); every
database step runs as fanoosweb through scripts/import/import-bank.php.
docs/ops/QUESTION_IMPORT.md is the runbook.

  1. discover: list the .docx papers under a Drive folder and guess, from the
     folder and file names, each one's exam type, year, round (a national
     sitting's month, a specialty's slot) and subject. Writes a manifest to
     review and correct by hand; nothing is downloaded.

       python3 scripts/import/drive_batch.py discover --folder="<drive folder>" --workspace=<uuid> --out=<manifest.json>

  2. prepare: download each paper of the manifest into a private staging
     folder, convert it (docx_to_sitting.py), then `check` and
     `import --dry-run` it. Writes <staging>/report.json and prints one line
     per paper. Nothing is written to the bank.

       python3 scripts/import/drive_batch.py prepare --manifest=<manifest.json> --batch=<name> [--reuse=<earlier batch>]

     --reuse takes each paper's .docx from an earlier batch's folder when it
     is there (Drive downloads are slow from the host), instead of Drive.

  3. import: with a verified full backup, import every prepared paper that
     had no error, and with --publish put each on the site as an exam.
     Writes a receipt next to the report.

       python3 scripts/import/drive_batch.py import --batch=<name> --backup=/var/backups/fanoos/<id>
           [--publish --actor=<uuid> --reviewer=<uuid>] [--only=<regex of sittings>]

     --only imports a chosen part of the batch (a paper with no valid key,
     say, can stay out of the site while the rest goes on); each --only
     run writes its own receipt.

A paper that already exists and would change (questions_changed > 0) is
never imported by this tool: a wording correction of a live paper is a
separate, approved operation (06 §5).
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
sys.path.insert(0, str(HERE))
from docx_to_sitting import build  # noqa: E402

RCLONE = ['rclone', '--config', '/root/.config/rclone/rclone.conf']
STAGING = Path('/srv/fanoos/staging/question-import')
RELEASE = Path('/srv/fanoos/current')
WEB_USER = 'fanoosweb'
CONFIG = '/etc/fanoos/platform-config.php'
DIGITS = str.maketrans('۰۱۲۳۴۵۶۷۸۹', '0123456789')

# Folder words -> exam type.
TYPES = [('بورد', 'board'), ('board', 'board'), ('ارتقا', 'promotion'), ('ملی', 'national'), ('دستیاری', 'residency'), ('residency', 'residency')]
# A specialty's stable slot (06 §1) and the words its file names use.
SPECIALTIES = [
    (1, 'endodontics', ['اندو', 'endodont']),
    (2, 'periodontics', ['پریو', 'periodont']),
    (3, 'prosthodontics', ['پروتز', 'prosthodont']),
    (4, 'operative-dentistry', ['ترمیمی', 'restorative', 'operative']),
    (5, 'oral-surgery', ['جراحی', 'surgery']),
    (6, 'oral-medicine', ['بیماری', 'oral_medicine', 'oral medicine']),
    (7, 'oral-pathology', ['آسیب', 'پاتولوژی', 'pathology']),
    (8, 'oral-radiology', ['رادیولوژی', 'radiology']),
    (9, 'orthodontics', ['ارتو', 'orthodont']),
    (10, 'pediatric-dentistry', ['کودکان', 'pediatric']),
]
# A national exam is held in مرداد and دی: one round each.
MONTHS = [('مرداد', 1), ('دی', 2)]
SKIP_WORDS = ['بایگانی', 'ناقص', 'archive']


def guess(path: str, root: str = '') -> dict:
    """What a Drive path most likely is, read below the listed folder (root);
    anything unsure is marked for review."""
    entry: dict = {'drive_path': path}
    low = (path[len(root) + 1:] if root and path.startswith(root + '/') else path).lower().translate(DIGITS)
    if any(w in low for w in SKIP_WORDS):
        entry['skip'] = 'an archived or incomplete copy'
        return entry
    entry['type'] = next((t for word, t in TYPES if word in low), None)
    years = re.findall(r'(13[6-9]\d|14[0-2]\d)', low)
    entry['year'] = int(years[-1]) if years else None
    entry['round'] = 1
    if entry['type'] in ('board', 'promotion'):
        found = [(slot, key) for slot, key, words in SPECIALTIES if any(w in low.split('/')[-1] for w in words)]
        if len(found) == 1:
            entry['round'], entry['subject'] = found[0]
        else:
            entry['review'] = 'specialty not recognised from the file name; set subject and round (06 §1 slots)'
    elif entry['type'] == 'national':
        months = [r for word, r in MONTHS if re.search(rf'(^|[\s_/]){word}([\s_]|$)', low.split('/')[-1])]
        if len(months) == 1:
            entry['round'] = months[0]
        else:
            entry['review'] = 'month (مرداد/دی) not in the file name; set round 1 for مرداد, 2 for دی'
    if re.search(r'گروه[\s_]*(b|ب)\b|دفترچه[\s_]*(b|ب)\b', low.split('/')[-1]):
        entry['skip'] = 'form B of a paper whose form A is imported (same questions, other order)'
    if entry['type'] is None or entry['year'] is None:
        entry['review'] = 'exam type or year not recognised'
    return entry


def rclone(*args: str, timeout: int = 900) -> str:
    done = subprocess.run(RCLONE + list(args), capture_output=True, text=True, timeout=timeout)
    if done.returncode != 0:
        raise RuntimeError(done.stderr.strip()[-400:])
    return done.stdout


def bank(*args: str) -> tuple[int, str]:
    """import-bank.php as the web user, from the live release."""
    command = ['sudo', '-n', '-u', WEB_USER, 'env', f'FANOOS_CONFIG_FILE={CONFIG}', 'php', 'scripts/import/import-bank.php', *args]
    done = subprocess.run(command, capture_output=True, text=True, cwd=RELEASE, timeout=1800)
    return done.returncode, (done.stdout + done.stderr).strip()


def discover(args) -> None:
    listing = rclone('lsf', '-R', '--files-only', f'gdrive:{args.folder}')
    entries = [guess(f'{args.folder}/{line.strip()}', args.folder) for line in listing.splitlines() if line.strip().lower().endswith('.docx')]
    Path(args.out).write_text(json.dumps({'format': 'fanoos.drive-batch/1', 'workspace': args.workspace, 'papers': entries},
                                         ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    review = [e for e in entries if 'review' in e]
    print(f'{len(entries)} papers, {sum("skip" in e for e in entries)} skipped, {len(review)} to review -> {args.out}')
    for e in review:
        print(f'  REVIEW {e["drive_path"]}: {e["review"]}')


def prepare(args) -> None:
    manifest = json.loads(Path(args.manifest).read_text(encoding='utf-8'))
    workspace = manifest['workspace']
    batch = STAGING / args.batch
    if batch.exists():
        sys.exit(f'{batch} exists; choose a new batch name')
    batch.mkdir(parents=True)
    shutil.copy(args.manifest, batch / 'manifest.json')
    report = []
    for entry in manifest['papers']:
        if 'skip' in entry or 'review' in entry:
            report.append({**entry, 'status': 'skipped' if 'skip' in entry else 'needs review'})
            continue
        name = f"{entry['type']}-{entry['year']}-{entry['round']}"
        work = batch / name
        work.mkdir()
        row = {**entry, 'sitting': name}
        try:
            earlier = STAGING / args.reuse / name / 'paper.docx' if args.reuse else None
            if earlier is not None and earlier.is_file():
                shutil.copy(earlier, work / 'paper.docx')
            else:
                rclone('copyto', f"gdrive:{entry['drive_path']}", str(work / 'paper.docx'))
            converted = build(work / 'paper.docx', entry['year'], work, entry.get('form', 'A'), None, frozenset(entry.get('leave_out', [])),
                              entry['type'], entry['round'], entry.get('subject'), entry.get('expected'))
            row.update({k: converted[k] for k in ('questions', 'voided', 'no_valid_key', 'preliminary', 'booklet_sources', 'images')})
            row['warnings'] = converted['warnings'] + converted['disagreements']
        except Exception as error:  # noqa: BLE001 -- one bad paper must not stop the batch
            report.append({**row, 'status': 'error', 'error': str(error)[:400]})
            continue
        subprocess.run(['chown', '-R', f'{WEB_USER}:fanoosrt', str(batch)], check=True)
        sitting = work / f'{name}.json'
        code, out = bank('check', f'--workspace={workspace}', f'--file={sitting}', f'--assets={work / "assets"}')
        if code != 0:
            report.append({**row, 'status': 'error', 'error': out[-600:]})
            continue
        code, out = bank('import', '--dry-run', f'--workspace={workspace}', f'--file={sitting}', f'--assets={work / "assets"}')
        counts = json.loads(out[out.index('{'):]) if code == 0 and '{' in out else None
        if counts is None:
            report.append({**row, 'status': 'error', 'error': out[-600:]})
            continue
        written = counts.get('written', counts)
        row['dry_run'] = written
        if written.get('questions_changed', 0):
            row['status'] = 'exists-and-would-change'
        elif written.get('answers_recorded', 0) == 0 and written.get('questions', 0) > 0:
            row['status'] = 'already-in-bank'  # every question and answer is there already
        else:
            row['status'] = 'ready'
        report.append(row)
    subprocess.run(['chown', '-R', f'{WEB_USER}:fanoosrt', str(batch)], check=True)
    os.chmod(batch, 0o750)
    (batch / 'report.json').write_text(json.dumps({'workspace': workspace, 'papers': report}, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    for row in report:
        print(f"{row['status']:<26} {row.get('sitting', '-'):<22} q={row.get('questions', '-')} void={row.get('voided', '-')} "
              f"nokey={row.get('no_valid_key', '-')} prelim={row.get('preliminary', '-')} warn={len(row.get('warnings', []))}  {row['drive_path'][-60:]}")
    print(f"ready: {sum(r['status'] == 'ready' for r in report)} of {len(report)}; report: {batch / 'report.json'}")


def run_import(args) -> None:
    batch = STAGING / args.batch
    report = json.loads((batch / 'report.json').read_text(encoding='utf-8'))
    workspace = report['workspace']
    verify = subprocess.run(['sudo', '-n', '-u', 'fanoosupd', 'env', 'FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php', 'php',
                             'scripts/ops/verify-backup.php', args.backup], capture_output=True, text=True, cwd=RELEASE)
    if verify.returncode != 0:
        sys.exit(f'backup not verified: {verify.stdout}{verify.stderr}')
    if args.publish and not (args.actor and args.reviewer):
        sys.exit('--publish needs --actor and --reviewer')
    receipt_path = batch / (f'receipt-{hashlib.sha256(args.only.encode()).hexdigest()[:8]}.json' if args.only else 'receipt.json')
    if receipt_path.exists():
        sys.exit(f'{receipt_path} exists: this batch was imported already')
    done = []
    for row in report['papers']:
        if row['status'] != 'ready':
            continue
        if args.only and not re.fullmatch(args.only, row['sitting']):
            continue
        work = batch / row['sitting']
        code, out = bank('import', f'--workspace={workspace}', f"--file={work / (row['sitting'] + '.json')}", f"--assets={work / 'assets'}")
        result = {'sitting': row['sitting'], 'imported': code == 0, 'output': out[-400:]}
        if code == 0 and args.publish:
            code, out = bank('publish', f'--workspace={workspace}', f"--type={row['type']}", f"--year={row['year']}", f"--round={row['round']}",
                             f'--actor={args.actor}', f'--reviewer={args.reviewer}')
            result.update({'published': code == 0, 'publish_output': out[-400:]})
        done.append(result)
        print(f"{row['sitting']:<22} imported={result['imported']} published={result.get('published', '-')}")
        if not result['imported'] or result.get('published') is False:
            print('stopping at the first failure; the receipt lists what was done')
            break
    receipt_path.write_text(json.dumps({'backup': args.backup, 'papers': done}, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    print(f'receipt: {receipt_path}')


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    steps = parser.add_subparsers(dest='step', required=True)
    d = steps.add_parser('discover')
    d.add_argument('--folder', required=True)
    d.add_argument('--out', required=True)
    d.add_argument('--workspace', required=True)
    p = steps.add_parser('prepare')
    p.add_argument('--manifest', required=True)
    p.add_argument('--batch', required=True)
    p.add_argument('--reuse')
    i = steps.add_parser('import')
    i.add_argument('--batch', required=True)
    i.add_argument('--backup', required=True)
    i.add_argument('--publish', action='store_true')
    i.add_argument('--actor')
    i.add_argument('--reviewer')
    i.add_argument('--only', help='import only the ready papers whose sitting matches this regex (e.g. "board-1401-.*|national-1405-1")')
    args = parser.parse_args()
    if args.step != 'discover' and not re.fullmatch(r'[a-z0-9][a-z0-9-]{2,60}', args.batch):
        sys.exit('--batch must be a short lowercase name')
    {'discover': discover, 'prepare': prepare, 'import': run_import}[args.step](args)


if __name__ == '__main__':
    main()
