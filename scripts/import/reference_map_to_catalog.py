"""Builds the bank catalog (data/bank/catalog.json) from the research workbook
docs/research/dental-residency-reference-map-1396-1405.xlsx.

    python scripts/import/reference_map_to_catalog.py

Standard library only. The workbook was assembled by hand from yearly
announcements, so the same book appears under several spellings and some
edition labels disagree with their year. Every book is therefore matched
against the explicit table REFERENCES below, and every judgement the script
makes (an edition inferred from a year, a row that is not a book, a partial
notice) is written into the catalog's "review_notes" for the owner to
confirm. Re-running on the same workbook gives the same file.
"""
from __future__ import annotations

import json
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
WORKBOOK = ROOT / 'docs/research/dental-residency-reference-map-1396-1405.xlsx'
OUTPUT = ROOT / 'data/bank/catalog.json'
SHEET = 'نقشه منابع'

SUBJECTS = [
    ('endodontics', 'اندودانتیکس', 'Endodontics'),
    ('periodontics', 'پریودانتیکس', 'Periodontics'),
    ('prosthodontics', 'پروتزهای دندانی', 'Prosthodontics'),
    ('operative-dentistry', 'دندانپزشکی ترمیمی', 'Operative Dentistry'),
    ('dental-materials', 'مواد دندانی', 'Dental Materials'),
    ('oral-surgery', 'جراحی دهان و فک و صورت', 'Oral and Maxillofacial Surgery'),
    ('oral-medicine', 'بیماری‌های دهان و فک و صورت', 'Oral Medicine'),
    ('oral-pathology', 'آسیب‌شناسی دهان و فک و صورت', 'Oral and Maxillofacial Pathology'),
    ('oral-radiology', 'رادیولوژی دهان و فک و صورت', 'Oral and Maxillofacial Radiology'),
    ('orthodontics', 'ارتودانتیکس', 'Orthodontics'),
    ('pediatric-dentistry', 'دندانپزشکی کودکان', 'Pediatric Dentistry'),
    ('community-dentistry', 'سلامت دهان و دندانپزشکی اجتماعی', 'Community Oral Health'),
    ('english', 'زبان انگلیسی', 'English'),
]
SUBJECT_BY_NAME = {name.replace('‌', ''): key for key, name, _ in SUBJECTS}

# (match: lowercase substrings that must all be in the title, key, title, authors, subject,
#  {publication year in the workbook: (edition key, label, year)})
REFERENCES = [
    (['oral and maxillofacial pathology'], 'neville-oral-pathology', 'Oral and Maxillofacial Pathology', 'Neville BW, Damm DD, Allen CM, Chi AC', 'oral-pathology',
     {'2016': ('4e', '4th edition', 2016), '2024': ('5e', '5th edition', 2024)}),
    (['contemporary orthodontics'], 'proffit-orthodontics', 'Contemporary Orthodontics', 'Proffit WR', 'orthodontics',
     {'2013': ('5e', '5th edition', 2013), '2019': ('6e', '6th edition', 2019)}),
    (['endodontics'], 'torabinejad-endodontics', 'Endodontics: Principles and Practice', 'Torabinejad M, Fouad AF, Shabahang S', 'endodontics',
     {'2015': ('5e', '5th edition', 2015), '2020': ('6e', '6th edition', 2021), '2021': ('6e', '6th edition', 2021)}),
    (['burket'], 'burket-oral-medicine', "Burket's Oral Medicine", 'Glick M', 'oral-medicine',
     {'2008': ('11e', '11th edition', 2008), '2015': ('12e', '12th edition', 2015), '2021': ('13e', '13th edition', 2021)}),
    (['dental management'], 'little-falace-dental-management', "Little and Falace's Dental Management of the Medically Compromised Patient", 'Little JW, Miller CS, Rhodus NL', 'oral-medicine',
     {'2013': ('8e', '8th edition', 2013), '2018': ('9e', '9th edition', 2018), '2024': ('10e', '10th edition', 2024)}),
    (['contemporary oral'], 'hupp-oral-surgery', 'Contemporary Oral and Maxillofacial Surgery', 'Hupp JR, Ellis E, Tucker MR', 'oral-surgery',
     {'2014': ('6e', '6th edition', 2014), '2019': ('7e', '7th edition', 2019)}),
    (['جراحی دهان و فک و صورت'], 'peterson-oral-surgery-fa', 'جراحی دهان و فک و صورت نوین (ترجمه‌ی Peterson)', 'Peterson؛ ترجمه‌ی مسعود یغمایی', 'oral-surgery',
     {'2008': ('5e-fa', 'ترجمه‌ی ویرایش پنجم', 2008)}),
    (['local anesthesia'], 'malamed-local-anesthesia', 'Handbook of Local Anesthesia', 'Malamed SF', 'oral-surgery',
     {'2013': ('6e', '6th edition', 2013), '2019': ('7e', '7th edition', 2019)}),
    (['medical emergencies'], 'malamed-medical-emergencies', 'Medical Emergencies in the Dental Office', 'Malamed SF', 'oral-surgery',
     {'2015': ('7e', '7th edition', 2015)}),
    (['craig'], 'craig-restorative-materials', "Craig's Restorative Dental Materials", 'Sakaguchi RL, Ferracane JL, Powers JM', 'dental-materials',
     {'2012': ('13e', '13th edition', 2012), '2018': ('14e', '14th edition', 2019), '2019': ('14e', '14th edition', 2019)}),
    (['goldstein'], 'goldstein-esthetics', "Goldstein's Esthetics in Dentistry", 'Goldstein RE', 'operative-dentistry',
     {'2018': ('3e', '3rd edition', 2018)}),
    (['sturdevant'], 'sturdevant-operative', "Sturdevant's Art and Science of Operative Dentistry", 'Ritter AV, Boushell LW, Walter R', 'operative-dentistry',
     {'2013': ('6e', '6th edition', 2013), '2018': ('7e', '7th edition', 2018)}),
    (['summit'], 'summit-operative', "Summit's Fundamentals of Operative Dentistry: A Contemporary Approach", 'Hilton TJ, Ferracane JL, Broome JC', 'operative-dentistry',
     {'2013': ('4e', '4th edition', 2013)}),
    (['child and adolescent'], 'mcdonald-avery-pediatric', "McDonald and Avery's Dentistry for the Child and Adolescent", 'Dean JA', 'pediatric-dentistry',
     {'2016': ('10e', '10th edition', 2016), '2021': ('11e', '11th edition', 2021)}),
    (['pediatric dentistry'], 'nowak-pediatric', 'Pediatric Dentistry: Infancy through Adolescence', 'Nowak AJ, Christensen JR, Mabry TR, Townsend JA, Wells MH', 'pediatric-dentistry',
     {'2019': ('6e', '6th edition', 2019)}),
    (['oral radiology'], 'white-pharoah-radiology', 'Oral Radiology: Principles and Interpretation', 'White SC, Pharoah MJ', 'oral-radiology',
     {'2014': ('7e', '7th edition', 2014), '2019': ('8e', '8th edition', 2019)}),
    (['active skills'], 'anderson-active-skills', 'Active Skills for Reading 3 & 4', 'Anderson NJ', 'english',
     {'2014': ('2014', '2014', 2014)}),
    (['dental hygiene'], 'noble-dental-hygiene', 'Clinical Textbook of Dental Hygiene and Therapy', 'Noble S (ed.)', 'english',
     {'2012': ('2e', '2nd edition', 2012)}),
    (['english for the students of dentistry'], 'tahririan-english-dentistry', 'English for the Students of Dentistry', 'Tahririan MH, Sadri E, Tahririan D', 'english',
     {'2016': ('2016', '2016', 2016)}),
    (['focus on vocabulary'], 'schmitt-focus-vocabulary', 'Focus on Vocabulary 2: Mastering the Academic Word List', 'Schmitt D, Schmitt N', 'english',
     {'2011': ('2011', '2011', 2011)}),
    (['essential dental public health'], 'daly-public-health', 'Essential Dental Public Health', 'Daly B, Batchelor P, Treasure ET, Watt RG', 'community-dentistry',
     {'2005': ('3e', '3rd edition', 2005)}),
    (['کتاب ملی سلامت دهان'], 'national-oral-health', 'کتاب ملی سلامت دهان و دندانپزشکی اجتماعی', 'گروه مؤلفین؛ جهاد دانشگاهی', 'community-dentistry',
     {'1394': ('1394', 'چاپ اول (۱۳۹۴)', None), '': ('1394', 'چاپ اول (۱۳۹۴)', None)}),
    (['applied dental materials'], 'mccabe-walls-materials', 'Applied Dental Materials', 'McCabe JF, Walls AWG', 'dental-materials',
     {'2008': ('9e', '9th edition', 2008)}),
    (['foundations and applications'], 'powers-wataha-materials', 'Dental Materials: Foundations and Applications', 'Powers JM, Wataha JC', 'dental-materials',
     {'2017': ('11e', '11th edition', 2017)}),
    (['introduction to dental materials'], 'van-noort-materials', 'Introduction to Dental Materials', 'van Noort R', 'dental-materials',
     {'2013': ('4e', '4th edition', 2013), '2024': ('5e', '5th edition', 2024)}),
    (['phillip'], 'phillips-materials', "Phillips' Science of Dental Materials", 'Shen C, Rawls HR, Esquivel-Upshaw JF', 'dental-materials',
     {'2022': ('13e', '13th edition', 2022)}),
    (['contemporary fixed'], 'rosenstiel-fixed', 'Contemporary Fixed Prosthodontics', 'Rosenstiel SF, Land MF, Walter R', 'prosthodontics',
     {'2023': ('6e', '6th edition', 2023)}),
    (['fundamentals of fixed'], 'shillingburg-fixed', 'Fundamentals of Fixed Prosthodontics', 'Shillingburg HT', 'prosthodontics',
     {'2012': ('4e', '4th edition', 2012)}),
    (['mccracken'], 'mccracken-rpd', "McCracken's Removable Partial Prosthodontics", 'Carr AB, Brown DT', 'prosthodontics',
     {'2011': ('12e', '12th edition', 2011), '2016': ('13e', '13th edition', 2016)}),
    (['edentulous'], 'zarb-edentulous', 'Prosthodontic Treatment for Edentulous Patients', 'Zarb G, Hobkirk JA, Eckert SE, Jacob RF', 'prosthodontics',
     {'2013': ('13e', '13th edition', 2013)}),
    (['periodontology'], 'carranza-periodontology', "Newman and Carranza's Clinical Periodontology", 'Newman MG, Takei HH, Klokkevold PR, Carranza FA', 'periodontics',
     {'2015': ('12e', '12th edition', 2015), '2019': ('13e', '13th edition', 2019), '2023': ('14e', '14th edition', 2023)}),
]

NOT_A_BOOK = ['تمامی کتب مرجع', 'سطح upper intermediate']
EDITION_NUMBER = re.compile(r'(\d+)\s*(?:st|nd|rd|th)', re.I)


def read_sheet(path: Path, name: str) -> list[list[str | None]]:
    ns = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
          'r': 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'}
    z = zipfile.ZipFile(path)
    shared = []
    if 'xl/sharedStrings.xml' in z.namelist():
        for si in ET.fromstring(z.read('xl/sharedStrings.xml')).findall('m:si', ns):
            shared.append(''.join(t.text or '' for t in si.iter('{%s}t' % ns['m'])))
    rels = {r.get('Id'): r.get('Target') for r in ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))}
    for sheet in ET.fromstring(z.read('xl/workbook.xml')).find('m:sheets', ns):
        if sheet.get('name') != name:
            continue
        target = rels[sheet.get('{%s}id' % ns['r'])].lstrip('/')
        target = target if target.startswith('xl/') else 'xl/' + target
        rows = []
        for row in ET.fromstring(z.read(target)).iter('{%s}row' % ns['m']):
            cells = {}
            for c in row.findall('m:c', ns):
                col = 0
                for ch in re.match(r'[A-Z]+', c.get('r')).group(0):
                    col = col * 26 + ord(ch) - 64
                v = c.find('m:v', ns)
                if c.get('t') == 'inlineStr':
                    value = ''.join(x.text or '' for x in c.iter('{%s}t' % ns['m']))
                elif v is None:
                    value = None
                elif c.get('t') == 's':
                    value = shared[int(v.text)]
                else:
                    value = v.text
                cells[col - 1] = value
            rows.append([cells.get(i) for i in range(max(cells) + 1)] if cells else [])
        return rows
    raise SystemExit(f'sheet {name!r} not found')


def clean(value) -> str:
    return re.sub(r'\s+', ' ', str(value or '').replace('‌', '‌')).strip()


def build() -> dict:
    rows = read_sheet(WORKBOOK, SHEET)
    header, rows = rows[0], [r + [None] * (12 - len(r)) for r in rows[1:] if r]
    notes: list[str] = []
    validity: dict[tuple, dict] = {}
    used: dict[str, set] = {}

    for number, row in enumerate(rows, start=2):
        year, _period, subject_name, title, authors, pub_year, edition_text, scope, evidence, post, pdf, note = (row + [None] * 12)[:12]
        title_l = clean(title).lower()
        where = f'row {number} ({year}, {clean(subject_name)})'
        subject = SUBJECT_BY_NAME.get(clean(subject_name).replace('‌', ''))
        if subject is None:
            notes.append(f'{where}: unknown subject "{clean(subject_name)}", skipped')
            continue
        if any(marker in title_l for marker in NOT_A_BOOK):
            notes.append(f'{where}: "{clean(title)}" is a scope statement, not a book; kept out of the reference list')
            continue
        if ' / ' in clean(title):
            notes.append(f'{where}: "{clean(title)}" names two books in one partial notice; not recorded as validity')
            continue
        matches = [ref for ref in REFERENCES if all(m in title_l for m in ref[0])]
        # "Pediatric Dentistry" also appears inside other titles; prefer the most specific match.
        matches.sort(key=lambda ref: -sum(len(m) for m in ref[0]))
        if not matches:
            notes.append(f'{where}: no reference matches "{clean(title)}", skipped')
            continue
        ref = matches[0]
        key, _t, _a, _s, editions = ref[1], ref[2], ref[3], ref[4], ref[5]
        pub = clean(pub_year)
        if pub not in editions:
            notes.append(f'{where}: "{clean(title)}" has no known edition for year "{pub}", skipped')
            continue
        edition_key, label, _y = editions[pub]
        stated = EDITION_NUMBER.search(clean(edition_text))
        if stated and edition_key[0].isdigit() and not edition_key.startswith(stated.group(1)) and not edition_key.endswith('-fa'):
            notes.append(f'{where}: workbook says "{clean(edition_text)}" for {pub}; recorded as {label} — confirm')
        elif not stated and edition_key[0].isdigit() and edition_key.endswith('e'):
            notes.append(f'{where}: edition not stated; inferred {label} from the year {pub} — confirm')
        if pub == '2020' and key == 'torabinejad-endodontics':
            notes.append(f'{where}: 2020 printing of the 6th edition, recorded as the 6th edition')
        used.setdefault(key, set()).add(edition_key)

        partial = clean(evidence).lower().startswith('partial')
        record = {
            'exam_type': 'residency',
            'year': int(year),
            'subject': subject,
            'edition': f'{key}@{edition_key}',
            'official': not partial,
            'scope': clean(scope) or None,
            'evidence': ' · '.join(x for x in [clean(evidence), clean(post)] if x) or None,
            'source_document': clean(pdf) or None,
        }
        identity = (record['year'], subject, record['edition'])
        if identity in validity:
            previous = validity[identity]
            if previous['scope'] != record['scope'] and record['scope']:
                previous['scope'] = '; '.join(x for x in [previous['scope'], record['scope']] if x)
            previous['official'] = previous['official'] or record['official']
            continue
        validity[identity] = record

    references = []
    for _m, key, title, authors, subject, editions in REFERENCES:
        if key not in used:
            continue
        seen = {}
        for edition_key, label, year in editions.values():
            if edition_key in used[key] and edition_key not in seen:
                seen[edition_key] = {'key': edition_key, 'label': label, **({'year': year} if year else {})}
        references.append({'key': key, 'title': title, 'authors': authors, 'subject': subject,
                           'editions': sorted(seen.values(), key=lambda e: (e.get('year') or 0, e['key']))})

    return {
        'format': 'fanoos.bank.catalog/1',
        'notes': 'Generated by scripts/import/reference_map_to_catalog.py from '
                 'docs/research/dental-residency-reference-map-1396-1405.xlsx. Edit the script or the workbook, '
                 'not this file. Chapter trees and concepts are added as questions are classified.',
        'review_notes': notes,
        'exam_types': [
            {'key': 'residency', 'name': 'دستیاری', 'active': True, 'order': 0},
            {'key': 'board', 'name': 'بورد', 'active': False, 'order': 1},
            {'key': 'promotion', 'name': 'ارتقا', 'active': False, 'order': 2},
        ],
        'subjects': [{'key': k, 'name': n, 'name_en': e, 'order': i} for i, (k, n, e) in enumerate(SUBJECTS)],
        'concepts': [],
        'references': references,
        'validity': sorted(validity.values(), key=lambda v: (-v['year'], v['subject'], v['edition'])),
    }


if __name__ == '__main__':
    catalog = build()
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')
    sys.stdout.reconfigure(encoding='utf-8')
    print(f"{OUTPUT.relative_to(ROOT)}: {len(catalog['references'])} references, "
          f"{sum(len(r['editions']) for r in catalog['references'])} editions, "
          f"{len(catalog['validity'])} validity rows, {len(catalog['review_notes'])} notes to review")
