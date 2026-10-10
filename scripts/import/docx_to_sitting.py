"""Turns one exam's consolidated question document (.docx) into a bank
sitting file and its images: a residency or national paper (every subject,
under subject headings) or one specialty's board or promotion paper.

The owner's per-year documents (one per exam, e.g. 1400.docx) list every
question in printed order under its subject heading: the stem (and any
figure right after it), the four options (الف..د, or a..d for English), and
an answer line ("پاسخ صحیح: گزینه ۲ (ب)", "پاسخ کلیدی: 3", "... گزینه‌های ۱ و ۳",
"... حذف شده"). English reading passages ("Passage 1 ...") precede their
questions and are attached to each of them. A final numeric key table ends
the document and is used only as a cross-check.

    python scripts/import/docx_to_sitting.py --docx=<file.docx> --year=1400 --out=<dir> [--form=A] [--workbook=<corpus.xlsx>] [--leave-out=22,187]
        [--type=residency|national|board|promotion] [--round=N] [--subject=<key>] [--expected=N]

--type and --round name the sitting (default residency, round 1); for board
and promotion the round is the specialty's stable slot (06 §1:
1 endodontics, 2 periodontics, 3 prosthodontics, 4 operative-dentistry,
5 oral-surgery, 6 oral-medicine, 7 oral-pathology, 8 oral-radiology,
9 orthodontics, 10 pediatric-dentistry). --subject files every question
under one subject (a specialty paper has no subject headings). --expected is
how many questions the paper has (250 residency, 240 national, 100 board
and promotion by default); absent numbers are reported against it.

Answer lines: "کلید اولیه" makes the answer preliminary; "نامشخص" (no
valid key) keeps the question with a disputed, empty answer, so it is in the
bank but never in a scored exam until a key is found. A line «منبع درج‌شده
در دفترچه: …» is kept as the question's booklet_source -- a hint for chapter
classification, never a source.

--leave-out names questions that cannot be answered as the document has them
(a figure it refers to is missing from the source); they are reported, not imported.

Keys: the document's own answer lines are NOT trusted where an official key
exists. With --workbook (the corpus workbook, whose final-key column applies
the official correction notices), every answer comes from the official final
key for that year and form, and each disagreement with the document is
reported. Without one (years with no official key on file), the document's
key is used and the answer's source says so.

Writes <out>/<type>-<year>-<round>.json (fanoos.bank.sitting/1) and the images
in <out>/assets/, and prints what it found and every irregularity: missing
or extra numbers, missing options, unnumbered questions skipped, answers
that disagree with the official key file. The question text is local data;
only this tool is in Git. Chapters are not assigned here.
"""
from __future__ import annotations

import json
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'
R = '{http://schemas.openxmlformats.org/officeDocument/2006/relationships}'
FA_DIGITS = str.maketrans('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', '01234567890123456789')
LETTERS = ['الف', 'ب', 'ج', 'د']
MISSING_CHOICE = '(این گزینه در نسخه‌ی منبع چاپ نشده است)'

# Subject headings, matched by a word each is sure to contain.
SUBJECT_WORDS = [
    ('اندو', 'endodontics'), ('پریو', 'periodontics'), ('پروتز', 'prosthodontics'), ('ترمیمی', 'operative-dentistry'),
    ('مواد', 'dental-materials'), ('جراحی', 'oral-surgery'), ('بیماری', 'oral-medicine'), ('آسیب', 'oral-pathology'),
    ('رادیولوژی', 'oral-radiology'), ('ارتو', 'orthodontics'), ('کودکان', 'pediatric-dentistry'),
    ('سلامت', 'community-dentistry'), ('اجتماعی', 'community-dentistry'), ('زبان', 'english'),
    # Older papers' names: «پاتولوژی دهان و فک», «دندانپزشکی تشخیصی», «دندانپزشکی جامعه نگر».
    ('پاتولوژی', 'oral-pathology'), ('تشخیص', 'oral-medicine'), ('جامعه', 'community-dentistry'),
    # The national exam's basic-science block, after English (from 1404).
    ('علوم پایه', 'basic-sciences'), ('بیوشیمی', 'basic-sciences'), ('باکتری', 'basic-sciences'), ('تشریح', 'basic-sciences'),
    ('فیزیولوژی', 'basic-sciences'), ('ژنتیک', 'basic-sciences'), ('ایمنی', 'basic-sciences'),
]

# «12. stem», «سؤال 001 | stem», «سؤال 1 (شماره 1 در درس ارتودنسی): stem»,
# or «سؤال 2» alone with its stem on the next line (number in group 1 or 3).
QUESTION = re.compile(r'^(?:(?:سؤال|سوال)?\s*([0-9]{1,3})\s*(?:\((?:شماره|سؤال|سوال)[^)]*\)\s*)?[.\-–—:)ـ|]\s*(.*)|(?:سؤال|سوال)\s*([0-9]{1,3})\s*)$')
# «منبع درج‌شده در دفترچه: کارانزا ۲۰۱۹» -- what the printed booklet names beside a question.
BOOKLET = re.compile(r'^منبع\s*(?:درج|ذکر)[‌\s]?شده(?:\s*در\s*دفترچه)?\s*:\s*(.+)$')
DEFAULT_EXPECTED = {'residency': 250, 'national': 240, 'board': 100, 'promotion': 100}
# «الف)», «الف ـ», and «الف(» (a PDF's right-to-left copy reverses the bracket).
CHOICE_FA = re.compile(r'^(الف|ب|ج|د)\s*[()\-–—.ـ]\s*(.*)$')
INLINE_CHOICE = re.compile(r'(?:^|\s)(الف|ب|ج|د)\s*[()]\s*')
# What follows «سؤال N:» in a correction list: «1 و 3», «حذف شده».
KEY_LINE = re.compile(r'^\s*(?:[1-4](?:\s*(?:و|،|,)\s*[1-4])*|حذف\s*شده)\s*$')
# «صفحه 5 PDF» -- where the question sat in the source file.
PAGE_MARK = re.compile(r'^صفحه\s*[0-9]+\s*PDF\s*', re.I)
# «1) ...» options: only in papers whose questions say «سؤال N», where a
# bare number cannot be the next question.
CHOICE_NUM = re.compile(r'^([1-4])\s*\)\s*(.*)$')
# A rule drawn between questions (────, ----).
RULE = re.compile(r'^[\s─━—–\-_=*]{3,}$')
CHOICE_EN = re.compile(r'^([a-dA-D])\s*[)\-–.]\s*(.*)$')
# An answer line -- not the closing «پاسخ‌نامه» (answer sheet) heading.
ANSWER = re.compile(r'^(?:پاسخ(?![\s‌]*نامه)|جواب|Correct answer|Answer)', re.I)
LETTER_ANSWER = {'الف': 1, 'ب': 2, 'ج': 3, 'د': 4}
# The closing key table's heading ("کلید عددی نهایی سؤالات", "پاسخ کلیدی عددی سؤالات").
KEY_TABLE = re.compile(r'کلید.*(?:سؤالات|سوالات)|^کلید')
# What opens an English reading or instruction block ("PART C. Reading ... — Passage 1", "Passage 2 (...)", "Directions: ...").
PASSAGE = re.compile(r'^(?:PART|Part|Passage|PASSAGE|Directions|Read the following|Reading Comprehension)|^.{0,40}(?:Read the following|درک مطلب)')
ABSENT = re.compile(r'وجود ندارد|موجود نیست')
UNNUMBERED = re.compile(r'^(?:سؤال|سوال)\s*بدون\s*شماره')


def norm(text: str) -> str:
    return text.translate(FA_DIGITS).replace('‌', '‌').strip()


def subject_of(line: str) -> str | None:
    """A short line that names a subject and nothing else is a heading."""
    if len(line) > 60 or QUESTION.match(line) or CHOICE_FA.match(line) or ANSWER.match(line):
        return None
    if line.rstrip().endswith(('؟', '?')):
        return None  # a short stem that names a subject («... درمان جراحی ...؟») is still a question
    for word, key in SUBJECT_WORDS:
        if word in line:
            return key
    return None


def paragraphs(docx: Path):
    """(text, [media path]) per paragraph, in document order."""
    z = zipfile.ZipFile(docx)
    rels = ET.fromstring(z.read('word/_rels/document.xml.rels'))
    targets = {r.get('Id'): r.get('Target') for r in rels}
    root = ET.fromstring(z.read('word/document.xml'))
    for p in root.iter(W + 'p'):
        text = ''.join(t.text or '' for t in p.iter(W + 't'))
        images = [targets[e.get(R + 'embed')] for e in p.iter() if e.tag.endswith('}blip') and e.get(R + 'embed') in targets]
        yield norm(text), images, z


def answer_of(line: str) -> dict | None:
    if 'حذف' in line:
        return {'choice': None, 'status': 'voided'}
    if 'نامشخص' in line or 'در دسترس نیست' in line:
        # No valid key: kept in the bank, never scored, until a key is found.
        return {'choice': None, 'status': 'disputed'}
    tail = re.split(r'گزینه(?:‌ها|ها)?(?:ی)?|:', line, maxsplit=1)
    digits = [int(d) for d in re.findall(r'(?<![0-9])([1-4])(?![0-9])', tail[-1] if len(tail) > 1 else line)]
    # "(ب)" style echoes repeat the same option as a letter; digits are what count.
    seen = []
    for d in digits:
        if d not in seen:
            seen.append(d)
    if not seen:
        # «پاسخ نهایی: ب» -- the option's letter alone.
        letters = re.findall(r'(?<![\w‌])(الف|ب|ج|د)(?![\w‌])', tail[-1] if len(tail) > 1 else line)
        for letter in letters:
            if LETTER_ANSWER[letter] not in seen:
                seen.append(LETTER_ANSWER[letter])
    if not seen:
        return None
    answer = {'choice': seen[0], 'status': 'preliminary' if 'اولیه' in line else 'final'}
    if len(seen) > 1 and ('گزینه‌های' in line or 'گزینه های' in line or ' و ' in line):
        answer['also_correct'] = seen[1:]
    return answer


def parse(docx: Path, year: int, expected: int = 250):
    questions: list[dict] = []
    warnings: list[str] = []
    subject = None
    current = None
    passage: list[str] = []
    in_passage = False
    skipping = False
    key_table: list[str] = []
    in_key = False
    zipped = None
    prefixed = False  # questions read «سؤال N»: a bare «N)» is an option

    def close():
        nonlocal current
        if current is not None:
            questions.append(current)
        current = None

    for text, images, z in paragraphs(docx):
        zipped = z
        if in_key:
            if text:
                key_table.append(text)
            continue
        if not text and not images:
            continue
        if text and RULE.match(text):
            continue
        if text and KEY_TABLE.search(text) and len(questions) >= expected * 0.8 and not QUESTION.match(text):
            close()
            in_key = True
            continue
        heading = subject_of(text) if text else None
        if heading == 'oral-pathology' and 'دهان' not in text and subject in ('english', 'basic-sciences'):
            heading = 'basic-sciences'  # «آسیب‌شناسی» after English is general pathology
        awaiting_stem = current is not None and current['stem'] == '' and not current['_choices']
        if heading and not awaiting_stem and (current is None or 'answer' in current or not current['_choices']) and not PASSAGE.match(text):
            close()
            subject, passage, in_passage, skipping = heading, [], False, False
            continue
        if text and UNNUMBERED.match(text):
            close()
            skipping = True
            warnings.append(f'skipped an unnumbered question: «{text[:60]}»')
            continue
        q = QUESTION.match(text) if text else None
        if q and prefixed and current is not None and CHOICE_NUM.match(text):
            q = None
        if q and q.group(2) is not None and KEY_LINE.match(q.group(2)):
            # «سؤال 221: 1 و 3» -- a correction list after the paper, not a question.
            warnings.append(f'a key line read as a key, not a question: «{text[:40]}»')
            continue
        if q:
            if text.startswith(('سؤال', 'سوال')):
                prefixed = True
            number = int(q.group(1) or q.group(3))
            last = questions[-1]['number'] if questions else (current['number'] if current else 0)
            if current is not None:
                last = current['number']
            if 0 < number - last <= 3:
                close()
                skipping = False
                if number - last > 1:
                    warnings.append(f'questions {last + 1}..{number - 1} are not in the document')
                stem = re.sub(r'\s*\((?:[^()]*?)\)\s*$', lambda m: '' if subject_of(m.group(0).strip(' ()')) else m.group(0), q.group(2) or '').strip()
                stem = PAGE_MARK.sub('', stem).strip()
                current = {'number': number, 'subject': subject, 'stem': stem, '_choices': {}, '_images': list(images),
                           '_passage': '\n'.join(passage) if passage and subject == 'english' else None}
                in_passage = False
                continue
        if skipping:
            continue
        if PASSAGE.match(text) and (current is None or 'answer' in current or subject == 'english'):
            close()
            subject, passage, in_passage = 'english', [text], True
            continue
        if in_passage and current is None:
            passage.append(text)
            continue
        if current is None:
            continue
        inline = INLINE_CHOICE.split(text) if text.startswith('الف') else []
        if len(inline) >= 5:
            # «الف( یک ب( دو ج( سه د( چهار» -- all options on one line.
            for label, choice in zip(inline[1::2], inline[2::2]):
                current['_choices'][LETTERS.index(label)] = choice.strip()
            continue
        c = CHOICE_FA.match(text) or CHOICE_EN.match(text) or (CHOICE_NUM.match(text) if prefixed else None)
        if c:
            label = c.group(1)
            index = LETTERS.index(label) if label in LETTERS else (int(label) - 1 if label.isdigit() else 'abcd'.index(label.lower()))
            current['_choices'][index] = c.group(2).strip()
            continue
        if images:
            current['_images'].extend(images)
        booklet = BOOKLET.match(text) if text else None
        if booklet:
            current['_booklet'] = booklet.group(1).strip()[:300]
            continue
        if text and text.startswith(('وضعیت سؤال', 'وضعیت سوال')) and 'حذف' in text:
            # «وضعیت سؤال: حذف شده (طبق کلید نهایی)» in place of an answer line.
            current['answer'] = {'choice': None, 'status': 'voided'}
            continue
        if text and ANSWER.match(text):
            answer = answer_of(text)
            if answer is None:
                warnings.append(f'{current["number"]}: answer line not understood: «{text[:60]}»')
            if answer is not None or current.get('answer') is None:
                # A later line that is not an answer never erases one already read.
                current['answer'] = answer
            continue
        if text and text.startswith(('یادداشت', 'توضیح')):
            current.setdefault('_notes', []).append(text)
            continue
        if text and not current['_choices']:
            current['stem'] = (current['stem'] + '\n' + text).strip()
        elif text and current['_choices'].get(max(current['_choices'])) == '':
            # An option whose words wrapped onto the next line («د(» then its text).
            current['_choices'][max(current['_choices'])] = text
        elif text:
            warnings.append(f'{current["number"]}: text after the options ignored: «{text[:60]}»')
    close()
    return questions, warnings, key_table, zipped


def key_from_table(lines: list[str]) -> dict[int, list[int]]:
    """The closing key table: "سؤال N: k", "N:k", or a number line then an answer line."""
    keys: dict[int, list[int]] = {}
    pending = None
    for line in lines:
        pair = re.match(r'^(?:سؤال|سوال)?\s*([0-9]{1,3})\s*[:\-–]\s*(.+)$', line)
        if pair:
            keys[int(pair.group(1))] = [int(d) for d in re.findall(r'[1-4]', pair.group(2))] or []
            pending = None
        elif re.fullmatch(r'[0-9]{1,3}', line):
            if pending is None:
                pending = int(line)
            else:
                keys[pending] = [int(line)] if 1 <= int(line) <= 4 else []
                pending = None
    return keys


def build(docx: Path, year: int, out: Path, form: str, official: dict[int, dict] | None, leave_out: frozenset[int] = frozenset(),
          exam_type: str = 'residency', round_: int = 1, subject: str | None = None, expected: int | None = None) -> dict:
    expected = expected or DEFAULT_EXPECTED.get(exam_type, 250)
    questions, warnings, table, z = parse(docx, year, expected)
    if subject is not None:
        for q in questions:
            q['subject'] = subject
    assets = out / 'assets'
    assets.mkdir(parents=True, exist_ok=True)
    items = []
    for q in questions:
        n = q['number']
        if n in leave_out:
            warnings.append(f'{n}: left out as asked (--leave-out)')
            continue
        if q['subject'] is None:
            warnings.append(f'{n}: no subject heading before it')
            continue
        choices = [q['_choices'].get(i) for i in range(4)]
        if not any(choices) and q['_images']:
            # Graph options printed inside the figure (e.g. 1405 Q191): the figure is the options.
            choices = [f'نمودار {LETTERS[i]}' for i in range(4)]
        if not any(choices):
            # No option at all could be read: a question that cannot be
            # answered is not put on the site; the report lists it to fix.
            warnings.append(f'{n}: no option could be read; left out')
            continue
        for i, choice in enumerate(choices):
            if not choice:
                warnings.append(f'{n}: option {LETTERS[i]} is not in the document')
                choices[i] = MISSING_CHOICE
        if ABSENT.search(q['stem']) and not any(q['_choices'].values()):
            warnings.append(f'{n}: the document says the question itself is missing ("{q["stem"][:50]}"); left out')
            continue
        if q.get('answer') is None and official is None:
            warnings.append(f'{n}: no answer line; left out')
            continue
        stem = q['stem']
        if q.get('_passage'):
            stem = q['_passage'] + '\n\n' + stem
        answer = (q.get('answer') or {}) | {'source': {
            'disputed': f'کلید معتبری برای این سؤال در فایل {year} نیست',
            'preliminary': f'کلید اولیهٔ دفترچهٔ {year} (کلید نهایی در دسترس نبود)',
        }.get((q.get('answer') or {}).get('status'), f'کلید فایل سؤالات {year} (کلید رسمی در دسترس نبود)')}
        if official is not None:
            if n not in official:
                warnings.append(f'{n}: no official key for this question; left out')
                continue
            answer = official[n]
        item = {'number': n, 'subject': q['subject'], 'stem': stem[:4000], 'choices': choices, 'answer': answer,
                '_document_answer': q['answer']}
        if q.get('_booklet'):
            item['booklet_source'] = q['_booklet']
        if q['_images']:
            media = q['_images'][0]
            data = z.read('word/' + media.lstrip('/').removeprefix('word/'))
            name = f'{exam_type}-{year}-{round_}-q{n:03d}{Path(media).suffix.lower().replace(".jpeg", ".jpg")}'
            (assets / name).write_bytes(data)
            item['stem_image'] = name
            if len(q['_images']) > 1:
                warnings.append(f'{n}: {len(q["_images"])} images; only the first is kept')
        items.append(item)

    numbers = [i['number'] for i in items]
    missing = sorted(set(range(1, expected + 1)) - set(numbers) - set(leave_out))
    if missing:
        warnings.append(f'numbers absent: {missing}')
    table_keys = key_from_table(table)
    disagree = []
    as_list = lambda a: [] if a['choice'] is None else sorted([a['choice'], *a.get('also_correct', [])])
    for item in items:
        n, doc = item['number'], item.pop('_document_answer') or {'choice': None, 'status': 'missing'}
        if n in table_keys and table_keys[n] and sorted(table_keys[n]) != as_list(doc):
            disagree.append(f'{n}: the document answers {as_list(doc)} but its own key table says {table_keys[n]}')
        if official is not None and (as_list(doc), doc['status'] == 'voided') != (as_list(item['answer']), item['answer']['status'] == 'voided'):
            disagree.append(f'{n}: the document answers {"deleted" if doc["status"] == "voided" else as_list(doc)}, the official key {"deleted" if item["answer"]["status"] == "voided" else as_list(item["answer"])} (official used)')
    statuses = {i['answer']['status'] for i in items}
    sitting = {
        'format': 'fanoos.bank.sitting/1',
        'notes': f'{exam_type} {year} round {round_}, form {form}: questions, options, figures and keys from the consolidated document; chapters not yet assigned.',
        'exam_type': exam_type, 'year': year, 'round': round_,
        'answer_key_status': 'preliminary' if statuses & {'preliminary', 'disputed'} else 'final',
        'questions': items,
    }
    (out / f'{exam_type}-{year}-{round_}.json').write_text(json.dumps(sitting, ensure_ascii=False, indent=1), encoding='utf-8')
    subjects: dict[str, int] = {}
    for item in items:
        subjects[item['subject']] = subjects.get(item['subject'], 0) + 1
    return {
        'type': exam_type, 'year': year, 'round': round_, 'questions': len(items), 'voided': sum(1 for i in items if i['answer']['status'] == 'voided'),
        'no_valid_key': sum(1 for i in items if i['answer']['status'] == 'disputed'),
        'preliminary': sum(1 for i in items if i['answer']['status'] == 'preliminary'),
        'booklet_sources': sum(1 for i in items if 'booklet_source' in i),
        'several_accepted': sum(1 for i in items if 'also_correct' in i['answer']), 'images': sum(1 for i in items if 'stem_image' in i),
        'subjects': subjects, 'warnings': warnings, 'disagreements': disagree,
    }


if __name__ == '__main__':
    sys.stdout.reconfigure(encoding='utf-8')
    args = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
    if not {'docx', 'year', 'out'} <= args.keys():
        sys.exit(__doc__)
    year = int(args['year'])
    official = None
    if 'workbook' in args:
        sys.path.insert(0, str(Path(__file__).resolve().parent))
        from corpus_to_bank import answer as official_answer, read_rows  # noqa: E402
        form = args.get('form', 'main' if year == 1405 else 'A')
        official = {}
        for row in read_rows(Path(args['workbook'])):
            if row['سال آزمون'] == str(year) and row['فرم'] == form:
                try:
                    official[int(row['شماره سؤال'])] = official_answer(row)
                except ValueError:
                    pass  # an unreadable official key: the question is left out and reported
        if not official:
            sys.exit(f'no official keys for {year} form {form} in the workbook')
    leave_out = frozenset(int(n) for n in args.get('leave-out', '').split(',') if n.strip())
    report = build(Path(args['docx']), year, Path(args['out']), args.get('form', 'A'), official, leave_out,
                   args.get('type', 'residency'), int(args.get('round', '1')), args.get('subject'),
                   int(args['expected']) if 'expected' in args else None)
    print(json.dumps(report, ensure_ascii=False, indent=1))
