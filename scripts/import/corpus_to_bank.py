"""Turns one exam year of the question corpus workbook into bank import files.

The corpus workbook (kept outside Git, in .local/exam-questions/...) has one
row per question: year, form, number, subject, the question text as the PDF's
text layer gave it, the official reference and edition, an AI-suggested
chapter with its confidence, and the official initial and final keys.

    # 1. A draft of every question's stem and choices, parsed from the text layer:
    python scripts/import/corpus_to_bank.py draft  --workbook=<xlsx> --year=1404 --form=A --out=<draft.json>
    # 2. A person checks every stem and option against the printed pages and
    #    saves it as the reviewed file ("checked": true on each question).
    #    Set "reference_checked" and "chapter_checked" only after checking
    #    the cited edition and its actual content. Set "image_checked" with a
    #    stem_image asset, or "image_not_applicable" after inspecting the page.
    # 3. The import files: a catalog supplement (the chapters and topics used)
    #    and the sitting itself:
    python scripts/import/corpus_to_bank.py build  --workbook=<xlsx> --year=1404 --form=A \
        --reviewed=<reviewed.json> --catalog-out=<chapters.json> --sitting-out=<sitting.json>

Why a reviewed file at all: the text layer of these PDFs is unreliable in
places -- glyphs swapped or doubled ("بره ترتیرب کردام" for "به ترتیب کدام"),
option letters lost -- while the printed page is right. A complete, checked
sitting is required; missing or unchecked questions stop the build.

Answers: a final key that differs from the initial one is "amended"; a
question the official notice voided is "voided"; one where the notice
accepts several options keeps the first as "choice" and the others in
"also_correct", and any of them scores.

A checked chapter ("Chapter 6. ..." or van Noort's "Chapter 3.5 ...") must
already be a chapter node of the cited edition in the catalog; the question
cites that node ("ch06", "ch03.5") and a topic-level concept per chapter,
both with the catalog's own title. A reviewed "reference" may name the
catalog edition directly ("craig-restorative-materials@14e").

Standard library only; reads the workbook with the same XML reader as
reference_map_to_catalog.py.
"""
from __future__ import annotations

import json
import re
import sys
import unicodedata
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from reference_map_to_catalog import REFERENCES, SUBJECT_BY_NAME, chapter_key  # noqa: E402

CATALOG = ROOT / 'data/bank/catalog.json'
NS = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
      'r': 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'}
LETTERS = {'A': 1, 'B': 2, 'C': 3, 'D': 4}
CHOICE_MARK = re.compile(r'^\s*\(?\s*(الف|ب|ج|د|[a-dA-D])\s*[\)\-–.]?\s*(?=\S)')
INLINE_ENGLISH = re.compile(r'(?<![A-Za-z])([a-d])\)\s*', re.I)
INLINE_PERSIAN = re.compile(r'(?<![\w])(الف|ب|ج|د)\s*\)?\s*(?=[A-Za-z])')
HEADER = re.compile(r'(سی|چهل)[^\n]*دوره[^\n]*امتحانات[^\n]*|[^\n]*گروه\s*«[^»]*»[^\n]*سال\s*\d{4}[^\n]*')


def read_rows(workbook: Path, sheet_name: str = 'Questions') -> list[dict[str, str]]:
    z = zipfile.ZipFile(workbook)
    shared = []
    if 'xl/sharedStrings.xml' in z.namelist():
        for si in ET.fromstring(z.read('xl/sharedStrings.xml')).findall('m:si', NS):
            shared.append(''.join(t.text or '' for t in si.iter('{%s}t' % NS['m'])))
    wb = ET.fromstring(z.read('xl/workbook.xml'))
    rels = {r.get('Id'): r.get('Target') for r in ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))}
    for s in wb.find('m:sheets', NS):
        if s.get('name') != sheet_name:
            continue
        target = rels[s.get('{%s}id' % NS['r'])].lstrip('/')
        target = target if target.startswith('xl/') else 'xl/' + target
        rows = []
        for row in ET.fromstring(z.read(target)).iter('{%s}row' % NS['m']):
            cells = {}
            for c in row.findall('m:c', NS):
                v = c.find('m:v', NS)
                kind = c.get('t')
                if kind == 'inlineStr':
                    val = ''.join(x.text or '' for x in c.iter('{%s}t' % NS['m']))
                elif v is None:
                    val = ''
                elif kind == 's':
                    val = shared[int(v.text)]
                else:
                    val = v.text or ''
                col = 0
                for ch in re.match(r'[A-Z]+', c.get('r')).group(0):
                    col = col * 26 + ord(ch) - 64
                cells[col - 1] = val
            rows.append([cells.get(i, '') for i in range(max(cells) + 1)] if cells else [])
        header = rows[0]
        return [dict(zip(header, r + [''] * (len(header) - len(r)))) for r in rows[1:] if r]
    raise SystemExit(f'No sheet named {sheet_name}')


def clean(text: str) -> str:
    """Text-layer cleanup that is always safe: headers out, a run of ZWNJs used as spaces back to spaces."""
    text = HEADER.sub('', text)
    words = re.split(r'\s+', text)
    zwnj = text.count('‌')
    if zwnj > max(6, len(words) // 2):
        text = re.sub(r'‌{2,}', ' ', text)
        text = text.replace('‌', ' ')
    text = re.sub(r'[ \t]+', ' ', text)
    return '\n'.join(line.strip() for line in text.split('\n') if line.strip())


def split_question(text: str) -> tuple[str, list[str]]:
    """Stem and choices from a question's text; choices empty when they cannot be told apart."""
    text = clean(text)
    text = re.sub(r'^\s*\d{1,3}\s*[-ـ–]\s*', '', text)
    lines = text.split('\n')
    stem, choices = [], []
    for line in lines:
        # Four inline choices also occur in the English section and in some
        # Persian questions whose PDF text layer drops the closing brackets.
        for pattern, labels in ((INLINE_ENGLISH, ['a', 'b', 'c', 'd']),
                                (INLINE_PERSIAN, ['الف', 'ب', 'ج', 'د'])):
            parts = pattern.split(line)
            if len(parts) == 9 and [x.lower() for x in parts[1::2]] == labels:
                stem_part = parts[0].strip()
                if stem_part:
                    stem.append(stem_part)
                choices = [part.strip() for part in parts[2::2]]
                break
        else:
            parts = []
        if parts:
            continue
        # Choices printed on one line: "الف) 10 ب 20 ج 30 د 40".
        inline = re.findall(r'(?:^|\s)\(?(الف|ب|ج|د)\s*[\)]?\s+(.+?)(?=\s\(?(?:ب|ج|د)\s*\)?\s|$)', line)
        if len(inline) == 4 and [m[0] for m in inline] == ['الف', 'ب', 'ج', 'د']:
            choices = [m[1].strip() for m in inline]
            continue
        m = CHOICE_MARK.match(line)
        if m and (choices or len(stem) > 0) and len(choices) < 4:
            choices.append(line[m.end():].strip())
        elif choices and len(choices) < 4:
            choices[-1] += ' ' + line
        else:
            stem.append(line)
    if len(choices) != 4:
        return '\n'.join(stem + choices), []
    return '\n'.join(stem).strip(), choices


def reference_for(title_with_edition: str, catalog: dict) -> tuple[str, str] | None:
    """'Contemporary Orthodontics — 6th Ed. (2019)' -> ('proffit-orthodontics', '6e').
    A reviewed correction may name the catalog edition directly: 'craig-restorative-materials@14e'."""
    if not title_with_edition:
        return None
    direct = re.fullmatch(r'\s*([a-z0-9-]+)@([\w-]+)\s*', title_with_edition)
    if direct:
        ref = next((r for r in catalog['references'] if r['key'] == direct.group(1)), None)
        return (direct.group(1), direct.group(2)) if ref and any(e['key'] == direct.group(2) for e in ref['editions']) else None
    title, _, rest = title_with_edition.partition('—')
    low = title.lower().replace('’', "'")
    edition = re.search(r'(\d+)(?:st|nd|rd|th)\s*ed', rest, re.I)
    year = re.search(r'\((\d{4})\)', rest)
    for match, key, *_ in REFERENCES:
        if all(part in low for part in match):
            ref = next((r for r in catalog['references'] if r['key'] == key), None)
            if ref is None:
                return None
            for e in ref['editions']:
                if edition and e['key'] == f'{edition.group(1)}e' and (not year or e.get('year') == int(year.group(1))):
                    return key, e['key']
                if not edition and year and e['key'] == year.group(1):
                    return key, e['key']
            return None
    return None


def chapter(text: str) -> tuple[str, str] | None:
    if len(re.findall(r'\bChapter\b', text or '', re.I)) != 1:
        return None
    m = re.match(r'\s*Chapter\s+(\d+(?:\.\d+)?)(?:\.\s*|\s+)(.+)', text or '')
    return (m.group(1), m.group(2).strip()) if m else None


def catalog_chapter(catalog: dict, ref: tuple[str, str], number: str) -> dict | None:
    """The cited edition's chapter node with this number (van Noort's '3.5' included)."""
    for r in catalog['references']:
        if r['key'] == ref[0]:
            for e in r['editions']:
                if e['key'] == ref[1]:
                    return next((n for n in e.get('nodes', []) if n.get('kind') == 'chapter' and n.get('number') == number), None)
    return None


def answer(row: dict[str, str]) -> dict:
    final = (row.get('کلید نهایی رسمی') or '').strip()
    initial = (row.get('کلید اولیه رسمی') or '').strip()
    note = (row.get('وضعیت کلید نهایی') or '').strip()
    source = 'کلید نهایی رسمی سازمان سنجش'
    if 'حذف' in final:
        return {'choice': None, 'status': 'voided', 'source': source + ' (سؤال حذف شد)'}
    options = [LETTERS[x] for x in re.findall(r'[ABCD]', final)]
    if len(options) > 1:
        # The final key accepts several options: any of them scores.
        return {'choice': options[0], 'also_correct': options[1:], 'status': 'amended',
                'source': source + ' — گزینه‌های پذیرفته: ' + '، '.join(str(o) for o in options)}
    if len(options) != 1:
        raise ValueError(f'unreadable key {final!r}')
    status = 'amended' if initial and initial != final else 'final'
    return {'choice': options[0], 'status': status, 'source': source if status == 'final' else source + ' (اصلاحیه)'}


def rows_for(args: dict) -> list[dict[str, str]]:
    form = args.get('form', 'main' if args['year'] == '1405' else 'A')
    rows = [r for r in read_rows(Path(args['workbook'])) if r['سال آزمون'] == args['year'] and r['فرم'] == form]
    if len(rows) != 250:
        raise ValueError(f'{args["year"]} form {form}: expected 250 corpus rows, found {len(rows)}')
    return sorted(rows, key=lambda r: int(r['شماره سؤال']))


def draft(args: dict) -> None:
    out = []
    for r in rows_for(args):
        stem, choices = split_question(r['متن سؤال'] or '')
        out.append({'number': int(r['شماره سؤال']), 'page': r['صفحه آغاز PDF'], 'subject': r['درس'],
                    'stem': stem, 'choices': choices, 'checked': False})
    Path(args['out']).write_text(json.dumps(out, ensure_ascii=False, indent=1), encoding='utf-8')
    print(f"{len(out)} drafts; {sum(1 for q in out if len(q['choices']) == 4)} with four choices")


def audit(args: dict) -> None:
    """List unresolved corpus rows without treating PDF text as verified."""
    rows = read_rows(Path(args['workbook']))
    catalog = json.loads(CATALOG.read_text(encoding='utf-8'))
    figure_manifest_path = Path(args['workbook']).parent / 'figure-assets' / 'manifest.json'
    figure_reviews = {
        item['asset']: item.get('review_status', '')
        for item in json.loads(figure_manifest_path.read_text(encoding='utf-8'))
    } if figure_manifest_path.is_file() else {}
    report = []
    for row in rows:
        stem, choices = split_question(row['متن سؤال'] or '')
        issues = []
        if not (row['متن سؤال'] or '').strip():
            issues.append('missing_question_text')
        elif len(choices) != 4 or not stem or any(not choice for choice in choices):
            issues.append('stem_or_choices_need_review')
        text_status = row.get('وضعیت متن') or ''
        if 'independent check pending' in text_status or 'Visually transcribed' in text_status:
            issues.append('visual_transcription_needs_independent_check')
        if 'independent second reading' in (row.get('یادداشت') or ''):
            issues.append('specific_transcription_term_needs_second_reading')
        raw_text = row['متن سؤال'] or ''
        normalized_text = unicodedata.normalize('NFKC', raw_text)
        if len(re.findall(r'(?m)^\s*\d{1,3}\s*[-–ـ]\s+', raw_text)) > 1:
            issues.append('possible_next_question_spill')
        if len(re.findall(r'(?mi)^\s*(?:a|الف)\s*\)', raw_text)) > 1:
            issues.append('repeated_choice_set_possible_question_spill')
        if re.search(r'مرکز سنجش آموزش پزشکی|اطلاعیه کلید نهایی', normalized_text):
            issues.append('official_notice_in_question_text')
        if re.search(r'پذیرش دستیار(?:ی| تخصصی)', normalized_text):
            issues.append('page_header_in_question_text')
        figure_paths = [item.strip() for item in (row.get('تصاویر سؤال و گزینه‌ها') or '').split(' | ') if item.strip()]
        figure_required = ('diagram' in (row.get('وضعیت متن') or '').lower()
                or 'graph' in (row.get('یادداشت') or '').lower()
                or re.search(r'(?:نمودار|شکل|تصویر)\s*(?:روبرو|مقابل|زیر)', normalized_text))
        if figure_required and (not figure_paths or any(
                path not in figure_reviews or not (Path(args['workbook']).parent / path).is_file()
                for path in figure_paths)):
            issues.append('figure_asset_not_verified')
        if figure_paths and any('independent_second_review_pending' in figure_reviews.get(path, '') for path in figure_paths):
            issues.append('figure_crop_needs_independent_check')
        if row['درس'] == 'زبان انگلیسی' and re.search(r'\b(?:passage|above passage|writer|author)\b', raw_text, re.I) and len(raw_text) < 700:
            issues.append('reading_passage_context_not_attached')
        if row['سال آزمون'] == '1405' and not (
                'Visually transcribed' in text_status or 'Visually checked' in text_status):
            issues.append('corrupted_pdf_text_layer_needs_page_review')
        if not re.search(r'[ABCD]|حذف', row.get('کلید نهایی رسمی') or ''):
            issues.append('missing_official_final_key')
        if not (row.get('رفرنس منتخب سؤال') or '').strip():
            issues.append('missing_question_reference')
        elif reference_for(row['رفرنس منتخب سؤال'], catalog) is None:
            without_year = re.sub(r'\(\d{4}\)', '', row['رفرنس منتخب سؤال'])
            if reference_for(without_year, catalog):
                issues.append('reference_publication_year_conflicts_with_catalog')
            else:
                issues.append('reference_edition_not_in_catalog')
        candidate = row.get('فصل پیشنهادی') or ''
        if not candidate.strip() or candidate.strip() == 'نیازمند بازبینی؛ فصل قابل تأیید نیست':
            issues.append('missing_chapter_candidate')
        elif len(re.findall(r'\bChapter\b', candidate, re.I)) > 1:
            issues.append('multiple_chapter_candidate')
        elif candidate.lstrip().startswith('Section'):
            issues.append('section_without_verified_parent_chapter')
        elif chapter(candidate) is None:
            issues.append('unparseable_chapter_candidate')
        status = row.get('وضعیت فصل/مبحث') or ''
        if 'outside the official' in status or 'outside the official' in row.get('وضعیت تطبیق رفرنس', ''):
            issues.append('chapter_outside_official_scope')
        if 'هشدار' in status:
            issues.append('course_disagreement_in_source_notice')
        if chapter(candidate) and not status.startswith('Verified chapter in exact-edition book'):
            issues.append('chapter_content_match_not_verified')
        report.append({'year': int(row['سال آزمون']), 'form': row['فرم'],
                       'number': int(row['شماره سؤال']), 'issues': issues})
    Path(args['out']).write_text(json.dumps(report, ensure_ascii=False, indent=1), encoding='utf-8')
    from collections import Counter
    print(json.dumps({'rows': len(report), 'issues': dict(Counter(issue for item in report for issue in item['issues']))}, ensure_ascii=False))


def build(args: dict) -> None:
    form = args.get('form', 'main' if args['year'] == '1405' else 'A')
    if form not in ('A', 'main'):
        raise ValueError('alternate forms are permutations of the same sitting; import the canonical form A only')
    catalog = json.loads(CATALOG.read_text(encoding='utf-8'))
    reviewed_rows = json.loads(Path(args['reviewed']).read_text(encoding='utf-8'))
    reviewed = {q['number']: q for q in reviewed_rows}
    if len(reviewed) != len(reviewed_rows):
        raise ValueError('reviewed file has duplicate question numbers')
    year = int(args['year'])
    nodes: dict[tuple[str, str, str], dict[str, str]] = {}
    concepts: dict[str, dict] = {}
    questions, left_out = [], []
    for r in rows_for(args):
        n = int(r['شماره سؤال'])
        q = reviewed.get(n)
        if not q or not q.get('checked') or len(q.get('choices', [])) != 4 or not q.get('stem') or any(not c.strip() for c in q['choices']):
            left_out.append(f'{n}: stem and four choices not checked against the printed page')
            continue
        if not q.get('image_not_applicable') and not (q.get('image_checked') and q.get('stem_image')):
            left_out.append(f'{n}: page image/figure status not checked or required asset missing')
            continue
        subject = SUBJECT_BY_NAME.get(r['درس'].replace('‌', ''))
        if subject is None:
            left_out.append(f"{n}: unknown subject {r['درس']}")
            continue
        item = {'number': n, 'subject': subject, 'stem': q['stem'], 'choices': q['choices'], 'answer': answer(r)}
        if q.get('stem_image'):
            item['stem_image'] = q['stem_image']
        ref = reference_for(q.get('reference') or r.get('رفرنس منتخب سؤال', ''), catalog)
        ch = chapter(q.get('chapter') or r.get('فصل پیشنهادی', ''))
        if not q.get('reference_checked') and not q.get('reference_not_applicable'):
            left_out.append(f'{n}: reference not checked against the official list and edition')
            continue
        if q.get('reference_checked') and ref is None:
            left_out.append(f'{n}: checked reference does not resolve to the catalog')
            continue
        if not q.get('chapter_checked') and not q.get('chapter_not_applicable'):
            left_out.append(f'{n}: chapter not checked against the cited edition')
            continue
        if q.get('chapter_checked') and (ch is None or ref is None):
            left_out.append(f'{n}: checked chapter is ambiguous or lacks a resolvable edition')
            continue
        node = catalog_chapter(catalog, ref, ch[0]) if ch and ref and q.get('chapter_checked') else None
        if q.get('chapter_checked') and node is None:
            left_out.append(f'{n}: chapter {ch[0]} is not in the catalog for {ref[0]}@{ref[1]}')
            continue
        if ref and q.get('reference_checked'):
            source = {'ref': f'{ref[0]}@{ref[1]}', 'primary': True, 'origin': 'human', 'confidence': {'source': 1.0}}
            if node is not None:
                # The catalog's own node: its key (dotted chapters are ch03.5) and its title.
                node_key = chapter_key(node['number'])
                if node.get('key') != node_key:
                    raise ValueError(f'{n}: catalog node {node.get("key")} does not match chapter {node["number"]}')
                nodes[(ref[0], ref[1], node_key)] = {'number': node['number'], 'title': node['title']}
                source['ref'] += f'#{node_key}'
                source['confidence']['node'] = 1.0
                concept_key = f'{subject}/{ref[0]}-{node_key}'
                concepts[concept_key] = {'key': concept_key, 'subject': subject, 'level': 'topic', 'name': node['title'][:200]}
                item['concepts'] = [{'key': concept_key, 'primary': True, 'confidence': 1.0, 'origin': 'human'}]
            item['sources'] = [source]
        questions.append(item)

    if left_out:
        raise ValueError(f'{year}: {len(left_out)} questions block the build: ' + '; '.join(left_out))
    # The cited chapters are already nodes of the full catalog (checked above). They are
    # not repeated here: the importer would rewrite their order and page range from this
    # partial list. The supplement adds only the chapter-level topics.

    supplement = {
        'format': 'fanoos.bank.catalog/1',
        'notes': f'Chapter-level topics cited by the {year} residency questions, each chapter checked against the cited edition.',
        'exam_types': catalog['exam_types'],
        'subjects': catalog['subjects'],
        'concepts': sorted(concepts.values(), key=lambda c: c['key']),
    }
    sitting = {
        'format': 'fanoos.bank.sitting/1',
        'notes': f'Residency {year}, form {args.get("form", "A")}: stems and choices checked against the printed booklet; keys from the official final key.',
        'exam_type': 'residency',
        'year': year,
        'round': 1,
        'answer_key_status': 'final',
        'questions': questions,
    }
    Path(args['catalog_out']).write_text(json.dumps(supplement, ensure_ascii=False, indent=1), encoding='utf-8')
    Path(args['sitting_out']).write_text(json.dumps(sitting, ensure_ascii=False, indent=1), encoding='utf-8')
    print(json.dumps({'questions': len(questions), 'left_out': left_out, 'chapters': len(nodes), 'topics': len(concepts)}, ensure_ascii=False, indent=1))


if __name__ == '__main__':
    command = sys.argv[1] if len(sys.argv) > 1 else ''
    options = dict(a[2:].split('=', 1) for a in sys.argv[2:] if a.startswith('--') and '=' in a)
    options = {k.replace('-', '_'): v for k, v in options.items()}
    {'draft': draft, 'audit': audit, 'build': build}.get(command, lambda _: sys.exit(__doc__))(options)
