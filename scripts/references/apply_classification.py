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
  subject, or, only for exam years 1396–1398, the owner-authorized nearest
  verified edition in the same reference family when the exact edition/PDF
  is unavailable;
- the chapter is a chapter of that edition in the catalog;
- the chapter is inside the official year-specific syllabus when its chapter
  list is recorded; an unstructured selected/excluded scope stays pending;
- the page lies inside that chapter (data/bank/reference-chapter-pages.json);
- every fragment of the evidence (split on "...", each of at least four
  words) is printed on that page of the current exact verified server PDF;
  only the requested page is read transiently in memory, with no text file or
  index written;
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
import os
import re
import sys
import tempfile
from pathlib import Path
from verified_reference_pdf import chapter_coverage, open_verified_reference
from retrospective_reference_policy import BASIS, is_retrospective, retrospective_note, selected_reference_rows
from updated_reference_proxy_policy import (
    UPDATED_PROXY_BASIS,
    UPDATED_PROXY_EDITION,
    UPDATED_PROXY_EXAM_YEAR,
    UPDATED_PROXY_1397_EXAM_YEAR,
    UPDATED_PROXY_REFERENCE_YEAR,
    UPDATED_PROXY_SUBJECT,
    is_updated_reference_proxy,
    valid_updated_reference_note,
)
from same_lineage_proxy_policy import (
    BASIS as SAME_LINEAGE_PROXY_BASIS,
    is_proxy_exam_year,
    is_selected_proxy as is_selected_same_lineage_proxy,
    selected_reference_rows as selected_same_lineage_rows,
    valid_proxy_note,
    fallback_error,
    requires_newest_first,
)

REPO = Path(__file__).resolve().parents[2]
BANK = REPO / 'data' / 'bank'


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


def save_sitting(path: Path, sitting: dict) -> None:
    parent = path.parent.resolve(strict=True)
    if path.is_symlink():
        raise ValueError('validated sitting output must not be a symlink')
    payload = json.dumps(sitting, ensure_ascii=False, indent=1) + '\n'
    fd, temporary = tempfile.mkstemp(prefix='.validated-sitting-', suffix='.tmp', dir=parent)
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


class Books:
    def __init__(self, storage_root: Path, mysql_defaults: Path, database: str):
        self.storage_root = storage_root
        self.mysql_defaults = mysql_defaults
        self.database = database
        self.pdfs = {}
        self.unavailable: dict[str, str] = {}

    def page(self, edition: str, number: int, map_entry: dict) -> str | None:
        if edition in self.unavailable:
            return None
        if edition not in self.pdfs:
            try:
                pdf = open_verified_reference(
                    edition, self.storage_root, self.mysql_defaults, self.database)
                page_runs = map_entry.get('runs', [])
                chapter_coverage({'editions': {edition: map_entry}}, edition, pdf.page_count)
                map_hash = map_entry.get('source_pdf_sha256')
                if not isinstance(map_hash, str) or not re.fullmatch(r'[a-f0-9]{64}', map_hash):
                    raise ValueError('chapter map has no verified PDF source hash; rebuild it from the current PDF')
                if map_hash != pdf.source_sha256:
                    raise ValueError('chapter map belongs to a different PDF')
                self.pdfs[edition] = pdf
            except (OSError, RuntimeError, ValueError) as error:
                self.unavailable[edition] = str(error)
                return None
        try:
            return self.pdfs[edition].page_text(number)
        except (OSError, RuntimeError, ValueError):
            self.unavailable[edition] = 'current approved PDF page could not be read'
            return None


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


def scope_issue(validity: dict, chapter: str) -> str | None:
    """Fail closed on a chapter outside an explicit official syllabus."""
    listed = validity.get('scope_chapters') or []
    if listed:
        if chapter not in {str(item['number']) for item in listed}:
            return f'chapter {chapter} is outside the official year-specific scope'
        return None
    scope = str(validity.get('scope') or '')
    if any(marker in scope for marker in ('منتخب', 'به جز', 'بجز', 'غیر از', 'except', 'excluding')):
        return 'official selected/excluded chapter scope is not structured; keep pending'
    return None


def whole_chapter_is_unlisted(validity: dict, chapter: str) -> bool:
    """Only the explicit outside-scope path may cite an unlisted full chapter."""
    listed = validity.get('scope_chapters')
    if not isinstance(listed, list) or not listed:
        return False
    numbers = set()
    for item in listed:
        if not isinstance(item, dict) or not isinstance(item.get('number'), (str, int)):
            return False
        numbers.add(str(item['number']))
    return chapter not in numbers


def valid_outside_scope_note(note: object) -> bool:
    return isinstance(note, str) and len(note.strip()) >= 24 and 'خارج از محدوده' in note


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
    parser.add_argument('--allow-outside-scope', action='store_true',
                        help='private research only: validate an unlisted whole chapter with an explicit Persian scope note; production writes still require the dedicated guarded operator')
    parser.add_argument('--updated-reference-proxy', action='store_true',
                        help='owner-approved updated-reference cases: 1398 oral-surgery Hupp 7e, or 1397 residency sources officially listed in 1406; always mark outside the exam-year reference list')
    parser.add_argument('--pdf-pages', action='store_true',
                        help='store PDF page numbers rather than printed page labels')
    parser.add_argument('--storage-root', type=Path, default=Path('/srv/fanoos/shared/storage'))
    parser.add_argument('--mysql-defaults', type=Path, default=Path('/etc/fanoos/mysql-migrator.cnf'))
    parser.add_argument('--database', default='fanoos_prod')
    args = parser.parse_args()
    sys.stdout.reconfigure(encoding='utf-8')

    sitting = json.loads(Path(args.sitting).read_text(encoding='utf-8'))
    year, exam_type = int(sitting['year']), sitting['exam_type']
    if args.updated_reference_proxy and (year not in (UPDATED_PROXY_1397_EXAM_YEAR, UPDATED_PROXY_EXAM_YEAR)
            or exam_type != 'residency'):
        raise ValueError('Updated-reference proxy mode is restricted to the owner-approved residency 1397 and 1398 cases.')
    catalog = json.loads((BANK / 'catalog.json').read_text(encoding='utf-8'))
    pdfs = json.loads((BANK / 'reference-pdfs.json').read_text(encoding='utf-8'))['editions']
    runs = json.loads((BANK / 'reference-chapter-pages.json').read_text(encoding='utf-8'))['editions']
    books = Books(args.storage_root, args.mysql_defaults, args.database)

    official: dict[str, dict[str, dict]] = {}
    if is_retrospective(year):
        for subject in sorted({q['subject'] for q in sitting['questions']}):
            for row in selected_reference_rows(catalog, exam_type, year, subject, pdfs, runs):
                official.setdefault(subject, {})[row['edition']] = row
    elif is_proxy_exam_year(year):
        for subject in sorted({q['subject'] for q in sitting['questions']}):
            for row in selected_same_lineage_rows(catalog, exam_type, year, subject, pdfs, runs):
                official.setdefault(subject, {})[row['edition']] = row
    else:
        for v in catalog['validity']:
            if v.get('exam_type') == exam_type and int(v['year']) == year:
                official.setdefault(v['subject'], {})[v['edition']] = v

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
        outside_scope = False
        q = questions.get(n)
        if q is None:
            why = 'no such question in the sitting'
        elif n in seen:
            why = 'decided twice'
        seen.add(n)
        if why is None and (is_retrospective(year) or is_proxy_exam_year(year)) and (
                q['answer'].get('status') not in ('final', 'amended')
                or q['answer'].get('choice') is None):
            why = 'voided/disputed or missing official answer cannot support older-exam chapter/page'
        if why is None and 'none' in d:
            if is_retrospective(year) or is_proxy_exam_year(year):
                why = 'older-exam missing reference remains pending; none is not a chapter+page decision'
            elif official.get(q['subject']) and not str(d['none']).strip():
                why = 'a question with official references needs a reason to have none'
            else:
                accepted[n] = None
        elif why is None:
            edition, chapter = d.get('edition', ''), str(d.get('chapter', ''))
            proxy_reference_year = d.get('reference_year')
            proxy_basis = d.get('classification_basis')
            proxy_note = d.get('updated_reference_note')
            same_lineage_note = d.get('proxy_note')
            has_proxy_metadata = any(key in d for key in (
                'reference_year', 'classification_basis', 'updated_reference_note', 'proxy_note'))
            updated_proxy = is_updated_reference_proxy(
                year, q['subject'], edition, proxy_reference_year, proxy_basis,
                d.get('reference_subject'), n)
            same_lineage_proxy = is_selected_same_lineage_proxy(
                catalog, exam_type, year, q['subject'], edition,
                proxy_reference_year, proxy_basis, pdfs, runs)
            names = official.get(q['subject'], {})
            if args.updated_reference_proxy and not updated_proxy:
                why = 'updated-reference mode requires the exact owner-approved edition, subject and reference year'
            elif has_proxy_metadata and not updated_proxy and not same_lineage_proxy and not is_retrospective(year):
                why = 'cross-year metadata requires an explicitly authorized proxy basis and selected reference edition'
            elif updated_proxy:
                if not args.updated_reference_proxy:
                    why = 'updated-reference proxy requires the explicit owner-approved validator mode'
                elif not valid_updated_reference_note(proxy_note, year):
                    why = f'updated-reference proxy requires an outside-scope note naming the 1397/1398 exam and 1406 reference years'
                elif d.get('reference_subject', q['subject']) != q['subject'] and 'جراحی دهان' not in str(proxy_note):
                    why = 'the 1397 Q95 cross-subject proxy note must name the oral-surgery reference subject'
                else:
                    reference_subject = d.get('reference_subject', q['subject'])
                    matches = [v for v in catalog.get('validity', [])
                               if v.get('exam_type') == exam_type
                               and int(v.get('year', 0)) == UPDATED_PROXY_REFERENCE_YEAR
                               and v.get('subject') == reference_subject
                               and v.get('edition') == edition
                               and v.get('official') is True]
                    if len(matches) != 1:
                        why = 'exact official 1406 reference-year validity is unavailable or ambiguous for this subject and edition'
                    else:
                        names = dict(names)
                        names[edition] = matches[0]
            elif same_lineage_proxy:
                if not valid_proxy_note(same_lineage_note, year, int(proxy_reference_year)):
                    why = 'same-lineage proxy requires a note naming both years and disclosing that it is not confirmed official for the exam year'
                elif edition not in names or int(names[edition].get('year', 0)) != proxy_reference_year:
                    why = 'proxy edition and actual official reference year do not match a selected same-lineage candidate'
            elif is_proxy_exam_year(year) and edition in names and names[edition].get('_proxy') is True:
                why = 'selected same-lineage proxy requires actual reference_year, proxy basis and an explicit proxy note'
            if is_retrospective(year) and edition in names:
                expected_year = int(names[edition]['year'])
                if d.get('reference_year') != expected_year or d.get('classification_basis') != BASIS:
                    why = (f'retrospective decision must explicitly name reference_year '
                           f'{expected_year} and classification_basis {BASIS}')
            if why is None and requires_newest_first(year) and not updated_proxy and edition in names:
                why = fallback_error(list(names.values()), edition, d.get('newer_editions_searched'))
            cite, cite_chapter = edition, chapter
            if edition not in names and not same_lineage_proxy and not args.allow_nearest:
                why = 'nearest-edition substitutions are paused; use exact official edition or keep pending'
            if edition not in names and why is None:
                # Keep explicitly recorded historical nearest-edition audits
                # possible even after the exact edition's PDF is later added.
                stand_in_for = [e for e in names
                                if pdfs.get(e, {}).get('nearest') == edition
                                and 'missing' in pdfs.get(e, {})]
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
            if why is None and (not isinstance(page, int) or isinstance(page, bool) or page < 1):
                why = 'a chapter decision requires the exact positive PDF page number'
            if why is None and cite != edition:
                why = 'nearest-edition page cannot verify the cited official edition; keep chapter and page pending'
            if why is None and chapter not in nodes:
                why = f'{edition} has no chapter {chapter}'
            if why is None and cite in names:
                scope_error = scope_issue(names[cite], cite_chapter)
                outside_note = d.get('outside_scope_note')
                if scope_error is not None:
                    updated_proxy_outside_reference_scope = updated_proxy and year == UPDATED_PROXY_1397_EXAM_YEAR
                    if (updated_proxy_outside_reference_scope
                        and (outside_note != proxy_note
                             or not isinstance(proxy_note, str)
                             or 'سرفصل رسمی ۱۴۰۶' not in proxy_note)):
                        why = 'an unlisted 1406 proxy chapter requires the same explicit note in both proxy fields'
                    elif (args.allow_outside_scope and cite == edition
                        and whole_chapter_is_unlisted(names[cite], cite_chapter)
                        and valid_outside_scope_note(outside_note)):
                        outside_scope = True
                    else:
                        why = scope_error
                elif outside_note is not None:
                    why = 'outside-scope note is only allowed for an unlisted whole chapter'
            if why is None:
                in_chapter = any(r[0] == chapter and r[1] <= int(page) <= r[2] for r in runs.get(edition, {}).get('runs', []))
                if not in_chapter:
                    why = f'page {page} is not in chapter {chapter} of {edition}'
            text = books.page(edition, int(page), runs.get(edition, {})) if why is None else None
            # The printed label is read now: the page text is discarded below once checked.
            printed = None if (text is None or args.pdf_pages) else printed_page(text)
            if why is None and text is None:
                why = f'{edition} has no readable page {page} in its current approved PDF'
            if why is None:
                pieces = fragments(str(d.get('evidence', '')))
                if not pieces or any(len(p.split()) < 4 for p in pieces):
                    why = 'evidence must be quoted fragments of at least four words each, joined by "..."'
                    del text
                else:
                    page_text = flat(text)
                    del text
                    missing = [p for p in pieces if flat(p) not in page_text]
                    del page_text
                    if missing:
                        why = f'evidence not on page {page}: "{missing[0][:80]}"'
            confidence = d.get('confidence')
            if why is None and not (isinstance(confidence, (int, float)) and 0 <= confidence <= 1):
                why = 'confidence must be a number from 0 to 1'
            if why is None:
                cite_node = chapter_nodes(catalog, cite)[cite_chapter]
                accepted[n] = {
                    'ref': f"{cite}#{cite_node['key']}",
                    'page': printed if printed else f'pdf {page}',
                    # The exact book page (migration 0043): the PDF page, its printed label, and the file.
                    # (Only for the cited edition's own file; a historical nearest-edition page is not its page.)
                    **({'pdf_page': int(page)} if cite == edition else {}),
                    **({'printed_page': printed} if printed and cite == edition else {}),
                    **({'pdf_sha256': books.pdfs[edition].source_sha256} if cite == edition and edition in books.pdfs else {}),
                    'anchor': str(d['evidence'])[:500],
                    'primary': True,
                    'origin': 'ai',
                    'confidence': {'source': confidence, 'node': confidence, 'page': confidence},
                }
                if updated_proxy:
                    accepted[n]['classification_basis'] = UPDATED_PROXY_BASIS
                    accepted[n]['reference_year'] = UPDATED_PROXY_REFERENCE_YEAR
                    accepted[n]['classification_note'] = str(proxy_note).strip()
                elif same_lineage_proxy:
                    accepted[n]['classification_basis'] = SAME_LINEAGE_PROXY_BASIS
                    accepted[n]['reference_year'] = int(proxy_reference_year)
                    accepted[n]['classification_note'] = str(same_lineage_note).strip()
                elif is_retrospective(year):
                    reference_year = int(names[edition]['year'])
                    accepted[n]['classification_basis'] = BASIS
                    accepted[n]['reference_year'] = reference_year
                    accepted[n]['classification_note'] = retrospective_note(reference_year, year)
                else:
                    accepted[n]['classification_basis'] = 'official_exam_year'
                    accepted[n]['reference_year'] = year
                if same_lineage_proxy and not outside_scope:
                    # The publisher and provenance audit require an explicit
                    # in-scope label on every same-lineage proxy citation.
                    accepted[n]['official_scope_status'] = 'in_scope'
                    accepted[n]['scope_unverified'] = False
                if outside_scope:
                    accepted[n]['official_scope_status'] = 'outside_scope_exam_question'
                    accepted[n]['official_scope_note'] = str(d['outside_scope_note']).strip()
                if updated_proxy:
                    accepted[n]['official_scope_status'] = 'outside_scope_exam_question'
                    accepted[n]['official_scope_note'] = str(proxy_note).strip()
                    accepted[n]['scope_unverified'] = False
                    reference_subject = d.get('reference_subject', q['subject'])
                    if reference_subject != q['subject']:
                        accepted[n]['reference_subject'] = reference_subject
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
    if is_retrospective(year):
        sitting['notes'] += (f' {BASIS}: 1397+ exact PDF citations refer to later official '
                             'reference-year lists, not original exam-year official books.')
    if is_proxy_exam_year(year) and any(
            isinstance(source, dict)
            and source.get('classification_basis') == SAME_LINEAGE_PROXY_BASIS
            for source in accepted.values()):
        sitting['notes'] += (' same_lineage_proxy: each citation retains its actual '
                             'official reference year and is not asserted official for the exam year.')
    save_sitting(Path(args.out), sitting)
    print(f'wrote {args.out}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
