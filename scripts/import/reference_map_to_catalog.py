"""Builds the bank catalog (data/bank/catalog.json) from the research workbook
docs/research/dental-residency-reference-map-1396-1405.xlsx and the later
official lists transcribed in docs/research/dental-*-references-<year>.json
(residency, national, and the specialty board and promotion exams).

    python scripts/import/reference_map_to_catalog.py

Standard library only. The workbook was assembled by hand from yearly
announcements, so the same book appears under several spellings and some
edition labels disagree with their year. Every book is therefore matched
against the explicit table REFERENCES below, and every judgement the script
makes (an edition inferred from a year, a row that is not a book, a partial
notice) is written into the catalog's "review_notes" for the owner to
confirm. Judgements already checked against the publishers' edition dates are
listed in CONFIRMED, SPLIT_NOTICES and MISSING_YEAR below; they go into
"decisions" instead, with the reason. Re-running on the same workbook gives
the same file.

Each edition's chapters come from data/bank/reference-tocs.json (the
publisher's table of contents); chapter n becomes node "ch0n", the key the
question imports cite as reference@edition#ch0n.
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
# Later official lists, transcribed in the workbook's columns (one file per exam and year).
# A file names its exam types ("exam_types", residency when absent; a row may name its
# own) and may hold for further years ("also_years": {year: why}).
EXTRA_YEARS = sorted((ROOT / 'docs/research').glob('dental-*-references-*.json'), key=lambda p: ('-residency-' not in p.name, p.name))
OUTPUT = ROOT / 'data/bank/catalog.json'
TOCS = ROOT / 'data/bank/reference-tocs.json'
TOCS_FA = ROOT / 'data/bank/reference-tocs.fa.json'
SECTIONS = ROOT / 'data/bank/reference-sections.json'

sys.path.insert(0, str(Path(__file__).resolve().parent))
from scope_chapters import resolve as resolve_scope  # noqa: E402
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
    # The national exam's basic-science block (from 1404: تشریح، فیزیولوژی، بیوشیمی، ...).
    ('basic-sciences', 'علوم پایه', 'Basic Sciences'),
]
SUBJECT_BY_NAME = {name.replace('‌', ''): key for key, name, _ in SUBJECTS}

# (match: lowercase substrings that must all be in the title, key, title, authors, subject,
#  {publication year in the workbook: (edition key, label, year)})
REFERENCES = [
    (['oral and maxillofacial pathology'], 'neville-oral-pathology', 'Oral and Maxillofacial Pathology', 'Neville BW, Damm DD, Allen CM, Chi AC', 'oral-pathology',
     {'2016': ('4e', '4th edition', 2016), '2024': ('5e', '5th edition', 2024)}),
    (['contemporary orthodontics'], 'proffit-orthodontics', 'Contemporary Orthodontics', 'Proffit WR', 'orthodontics',
     {'2013': ('5e', '5th edition', 2013), '2019': ('6e', '6th edition', 2019), '2026': ('7e', '7th edition', 2026)}),
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
     {'2013': ('6e', '6th edition', 2013), '2018': ('7e', '7th edition', 2018), '2026': ('8e', '8th edition', 2026)}),
    (['summit'], 'summit-operative', "Summit's Fundamentals of Operative Dentistry: A Contemporary Approach", 'Hilton TJ, Ferracane JL, Broome JC', 'operative-dentistry',
     {'2013': ('4e', '4th edition', 2013)}),
    (['child and adolescent'], 'mcdonald-avery-pediatric', "McDonald and Avery's Dentistry for the Child and Adolescent", 'Dean JA', 'pediatric-dentistry',
     {'2016': ('10e', '10th edition', 2016), '2021': ('11e', '11th edition', 2021)}),
    (['pediatric dentistry'], 'nowak-pediatric', 'Pediatric Dentistry: Infancy through Adolescence', 'Nowak AJ, Christensen JR, Mabry TR, Townsend JA, Wells MH', 'pediatric-dentistry',
     {'2019': ('6e', '6th edition', 2019)}),
    (['oral radiology'], 'white-pharoah-radiology', 'Oral Radiology: Principles and Interpretation', 'White SC, Pharoah MJ', 'oral-radiology',
     {'2014': ('7e', '7th edition', 2014), '2019': ('8e', '8th edition', 2019), '2026': ('9e', '9th edition', 2026)}),
    (['active skills'], 'anderson-active-skills', 'Active Skills for Reading 3 & 4', 'Anderson NJ', 'english',
     {'2014': ('2014', '2014', 2014)}),
    (['dental hygiene'], 'noble-dental-hygiene', 'Clinical Textbook of Dental Hygiene and Therapy', 'Noble S (ed.)', 'english',
     {'2012': ('2e', '2nd edition', 2012)}),
    (['english for the students of dentistry'], 'tahririan-english-dentistry', 'English for the Students of Dentistry', 'Tahririan MH, Sadri E, Tahririan D', 'english',
     {'2016': ('2016', '2016', 2016)}),
    (['focus on vocabulary'], 'schmitt-focus-vocabulary', 'Focus on Vocabulary 2: Mastering the Academic Word List', 'Schmitt D, Schmitt N', 'english',
     {'2011': ('2011', '2011', 2011)}),
    (['essential dental public health'], 'daly-public-health', 'Essential Dental Public Health', 'Daly B, Batchelor P, Treasure ET, Watt RG', 'community-dentistry',
     {'2005': ('2e', '2nd edition', 2013)}),
    (['کتاب ملی سلامت دهان'], 'national-oral-health', 'کتاب ملی سلامت دهان و دندانپزشکی اجتماعی', 'گروه مؤلفین؛ جهاد دانشگاهی', 'community-dentistry',
     {'1394': ('1394', 'چاپ اول (۱۳۹۴)', None), '': ('1394', 'چاپ اول (۱۳۹۴)', None)}),
    (['applied dental materials'], 'mccabe-walls-materials', 'Applied Dental Materials', 'McCabe JF, Walls AWG', 'dental-materials',
     {'2008': ('9e', '9th edition', 2008)}),
    (['foundations and applications'], 'powers-wataha-materials', 'Dental Materials: Foundations and Applications', 'Powers JM, Wataha JC', 'dental-materials',
     {'2017': ('11e', '11th edition', 2017)}),
    (['introduction to dental materials'], 'van-noort-materials', 'Introduction to Dental Materials', 'van Noort R', 'dental-materials',
     {'2013': ('4e', '4th edition', 2013), '2014': ('4e', '4th edition', 2013), '2024': ('5e', '5th edition', 2024)}),
    (['phillip'], 'phillips-materials', "Phillips' Science of Dental Materials", 'Shen C, Rawls HR, Esquivel-Upshaw JF', 'dental-materials',
     {'2022': ('13e', '13th edition', 2022)}),
    (['contemporary fixed'], 'rosenstiel-fixed', 'Contemporary Fixed Prosthodontics', 'Rosenstiel SF, Land MF, Walter R', 'prosthodontics',
     {'2023': ('6e', '6th edition', 2023)}),
    (['fundamentals of fixed'], 'shillingburg-fixed', 'Fundamentals of Fixed Prosthodontics', 'Shillingburg HT', 'prosthodontics',
     {'2012': ('4e', '4th edition', 2012)}),
    (['mccracken'], 'mccracken-rpd', "McCracken's Removable Partial Prosthodontics", 'Carr AB, Brown DT', 'prosthodontics',
     {'2011': ('12e', '12th edition', 2011), '2016': ('13e', '13th edition', 2016)}),
    (['edentulous'], 'zarb-edentulous', 'Prosthodontic Treatment for Edentulous Patients', 'Zarb G, Hobkirk JA, Eckert SE, Jacob RF', 'prosthodontics',
     {'2013': ('13e', '13th edition', 2013), '2017': ('14e', '14th edition', 2017)}),
    (['periodontology'], 'carranza-periodontology', "Newman and Carranza's Clinical Periodontology", 'Newman MG, Takei HH, Klokkevold PR, Carranza FA', 'periodontics',
     {'2015': ('12e', '12th edition', 2015), '2019': ('13e', '13th edition', 2019), '2023': ('14e', '14th edition', 2023)}),
]

REFERENCES += [
    (['ingle', 'endodontics'], 'ingle-endodontics', "Ingle's Endodontics", 'Rotstein I, Ingle JI', 'endodontics',
     {'2019': ('7e', '7th edition', 2019)}),
    (['pathways of the pulp'], 'cohen-pathways', "Cohen's Pathways of the Pulp", 'Berman LH, Hargreaves KM', 'endodontics',
     {'2020': ('12e', '12th edition', 2020)}),
    (['microsurgery in endodontics'], 'kim-microsurgery', 'Microsurgery in Endodontics', 'Kim S, Kratchman S, Karabucak B, Kohli M, Setzer F', 'endodontics',
     {'2017': ('1e', '1st edition', 2017)}),
    (['endodontic advances'], 'ahmed-dummer-endodontic-advances', 'Endodontic Advances and Evidence-Based Clinical Guidelines', 'Ahmed HMA, Dummer PMH', 'endodontics',
     {'2022': ('1e', '1st edition', 2022)}),
    (['traumatic injuries to the teeth'], 'andreasen-traumatic-injuries', 'Textbook and Color Atlas of Traumatic Injuries to the Teeth', 'Andreasen JO, Andreasen FM, Andersson L', 'endodontics',
     {'2019': ('5e', '5th edition', 2019)}),
    (['lindhe', 'implant dentistry'], 'lindhe-clinical-periodontology', 'Clinical Periodontology and Implant Dentistry', 'Berglundh T, Giannobile WV, Lang NP, Sanz M', 'periodontics',
     {'2021': ('7e', '7th edition', 2021)}),
    (['contemporary implant dentistry'], 'misch-contemporary-implant', "Misch's Contemporary Implant Dentistry", 'Resnik RR', 'periodontics',
     {'2020': ('4e', '4th edition', 2020), '2021': ('4e', '4th edition', 2020)}),
    (['غشاهای سدکننده'], 'lotfazar-barrier-membranes-fa', 'غشاهای سدکننده در جراحی‌های رژنراتیو پریودنتال و ایمپلنت', 'دکتر مهرداد لطف‌آذر', 'periodontics',
     {'': ('2', 'ویرایش جدید، چاپ دوم', None)}),
    (['functional occlusion'], 'dawson-functional-occlusion', 'Functional Occlusion: From TMJ to Smile Design', 'Dawson PE', 'prosthodontics',
     {'2007': ('1e', '1st edition', 2007)}),
    (['temporomandibular disorders and occlusion'], 'okeson-tmd', 'Management of Temporomandibular Disorders and Occlusion', 'Okeson JP', 'prosthodontics',
     {'2020': ('8e', '8th edition', 2020)}),
    (["stewart's clinical removable"], 'stewart-rpd', "Stewart's Clinical Removable Partial Prosthodontics", 'Phoenix RD, Cagna DR, DeFreest CF', 'prosthodontics',
     {'2008': ('4e', '4th edition', 2008)}),
    (['clinical maxillofacial prosthetics'], 'taylor-maxillofacial-prosthetics', 'Clinical Maxillofacial Prosthetics', 'Taylor TD', 'prosthodontics',
     {'2000': ('1e', '1st edition', 2000)}),
    (['dental implant prosthetics'], 'misch-implant-prosthetics', 'Dental Implant Prosthetics', 'Misch CE', 'prosthodontics',
     {'2015': ('2e', '2nd edition', 2015)}),
    (['oral and maxillofacial surgery (fonseca)'], 'fonseca-oral-surgery', 'Oral and Maxillofacial Surgery', 'Fonseca RJ', 'oral-surgery',
     {'2017': ('3e', '3rd edition', 2017)}),
    (["peterson's principles"], 'peterson-principles', "Peterson's Principles of Oral and Maxillofacial Surgery", 'Miloro M, Ghali GE, Larsen PE, Waite PD', 'oral-surgery',
     {'2022': ('4e', '4th edition', 2022)}),
    (["schwartz's principles of surgery"], 'schwartz-surgery', "Schwartz's Principles of Surgery", 'Brunicardi FC', 'oral-surgery',
     {'2019': ('11e', '11th edition', 2019)}),
    (['oral and maxillofacial trauma'], 'fonseca-trauma', 'Oral and Maxillofacial Trauma', 'Fonseca RJ, Walker RV, Barber HD, Powers MP, Frost DE', 'oral-surgery',
     {'2013': ('4e', '4th edition', 2013)}),
    (['clinical pathologic correlations'], 'regezi-oral-pathology', 'Oral Pathology: Clinical Pathologic Correlations', 'Regezi JA, Sciubba JJ, Jordan RCK', 'oral-pathology',
     {'2017': ('7e', '7th edition', 2017)}),
    (["ten cate's oral histology"], 'ten-cate-histology', "Ten Cate's Oral Histology", 'Nanci A', 'oral-pathology',
     {'2024': ('10e', '10th edition', 2024)}),
    (['pathologic basis of disease'], 'robbins-pathology', 'Robbins, Cotran and Kumar Pathologic Basis of Disease', 'Kumar V, Abbas AK, Aster JC', 'oral-pathology',
     {'2024': ('11e', '11th edition', 2024)}),
    (['oral pathology (woo)'], 'woo-oral-pathology', 'Oral Pathology: A Comprehensive Atlas and Text', 'Woo SB', 'oral-pathology',
     {'2024': ('3e', '3rd edition', 2024)}),
    (['head and neck imaging'], 'som-head-neck-imaging', 'Head and Neck Imaging', 'Som PM, Curtin HD', 'oral-radiology',
     {'2011': ('5e', '5th edition', 2011)}),
    (['differential diagnosis of oral and maxillofacial lesions'], 'wood-goaz-differential', 'Differential Diagnosis of Oral and Maxillofacial Lesions', 'Wood NK, Goaz PW', 'oral-radiology',
     {'1997': ('5e', '5th edition', 1997)}),
    (['radiologic science for technologists'], 'bushong-radiologic-science', 'Radiologic Science for Technologists', 'Bushong SC', 'oral-radiology',
     {'2021': ('12e', '12th edition', 2021)}),
    (['principles of dental imaging'], 'langland-dental-imaging', 'Principles of Dental Imaging', 'Langland OE, Langlais RP', 'oral-radiology',
     {'2002': ('2e', '2nd edition', 2002)}),
    (['diagnostic imaging: oral and maxillofacial'], 'koenig-diagnostic-imaging', 'Diagnostic Imaging: Oral and Maxillofacial', 'Koenig LJ', 'oral-radiology',
     {'2017': ('2e', '2nd edition', 2017)}),
    (['ultrasonography of the head and neck'], 'welkoborsky-ultrasonography', 'Ultrasonography of the Head and Neck: An Imaging Atlas', 'Welkoborsky HJ, Jecker P', 'oral-radiology',
     {'2019': ('1e', '1st edition', 2019)}),
    (['dentofacial deformity'], 'proffit-dentofacial-deformity', 'Contemporary Treatment of Dentofacial Deformity', 'Proffit WR, White RP, Sarver DM', 'orthodontics',
     {'2003': ('1e', '1st edition', 2003)}),
    (['current principles and techniques'], 'graber-orthodontics', 'Orthodontics: Current Principles and Techniques', 'Graber LW, Vanarsdall RL, Vig KWL, Huang GJ', 'orthodontics',
     {'2023': ('7e', '7th edition', 2023)}),
    (['biomechanical foundation'], 'burstone-biomechanics', 'The Biomechanical Foundation of Clinical Orthodontics', 'Burstone CJ, Choy K', 'orthodontics',
     {'2022': ('1e', '1st edition', 2022)}),
    (['orthognathic surgery'], 'naini-orthognathic', 'Orthognathic Surgery: Principles, Planning and Practice', 'Naini FB, Gill DS', 'orthodontics',
     {'2017': ('1e', '1st edition', 2017)}),
    (['esthetics and biomechanics'], 'nanda-esthetics-biomechanics', 'Esthetics and Biomechanics in Orthodontics', 'Nanda R', 'orthodontics',
     {'2015': ('2e', '2nd edition', 2015)}),
    (['temporary anchorage devices'], 'nanda-tads', 'Temporary Anchorage Devices in Orthodontics', 'Nanda R, Uribe FA, Yadav S', 'orthodontics',
     {'2019': ('2e', '2nd edition', 2019)}),
    (['aligner treatment'], 'nanda-aligners', 'Principles and Biomechanics of Aligner Treatment', 'Nanda R, Castroflorio T, Garino F, Ojima K', 'orthodontics',
     {'': ('1e', '1st edition', 2021)}),
    (['impacted teeth'], 'becker-impacted-teeth', 'Orthodontic Treatment of Impacted Teeth', 'Becker A', 'orthodontics',
     {'2022': ('4e', '4th edition', 2022)}),
    (['innovations in preventive dentistry'], 'splieth-preventive', 'Innovations in Preventive Dentistry', 'Splieth CH', 'pediatric-dentistry',
     {'2021': ('1e', '1st edition', 2021)}),
]

# The journals and named articles a specialty list adds, one row per subject and
# year: recorded as that subject's «announced articles» with the year as edition.
ARTICLES_TITLE = 'مقاله‌ها و ژورنال‌های اعلام‌شده'

NOT_A_BOOK = ['تمامی کتب مرجع', 'سطح upper intermediate']

# Edition judgements checked against the publishers' edition dates:
# (reference key, publication year in the workbook) -> why the edition is right.
CONFIRMED = {
    ('torabinejad-endodontics', '2020'): 'the 6th edition was printed in 2020 with a 2021 copyright; 2020 is that printing',
    ('little-falace-dental-management', '2018'): 'the 2018 edition is the 9th (Little, Miller, Rhodus); the book has no 12th edition, so "12th Ed." in the workbook is a typo',
    ('nowak-pediatric', '2019'): 'the 2019 edition of Pediatric Dentistry: Infancy through Adolescence is the 6th',
    ('neville-oral-pathology', '2016'): 'the 2016 edition is the 4th; the 5th is 2024',
    ('proffit-orthodontics', '2013'): 'the 2013 edition is the 5th; the 6th is 2019',
    ('malamed-local-anesthesia', '2013'): 'the 2013 edition is the 6th; the 7th is 2019',
    ('burket-oral-medicine', '2015'): 'the 2015 edition is the 12th; the 13th is 2021',
    ('sturdevant-operative', '2018'): 'the 2018 edition is the 7th (Ritter, Boushell, Walter)',
    ('craig-restorative-materials', '2018'): 'the 14th edition was published in 2018 with a 2019 copyright',
    ('van-noort-materials', '2013'): 'the 2013 edition is the 4th; the 5th is 2024',
    ('van-noort-materials', '2014'): 'the supplied 1398 notice prints 2014 for the 4th edition; this identifies the canonical 4e rather than a new edition',
    ('proffit-orthodontics', '2026'): 'the promotion-exam notice moves Proffit from 2019 to 2026, the year of the 7th edition',
    ('white-pharoah-radiology', '2026'): 'the specialty list prints "9th Ed. 2026"',
    ('sturdevant-operative', '2026'): 'the specialty list prints "8th Ed. 2026"',
    ('zarb-edentulous', '2017'): 'the specialty list prints "14th Edition. 2017"',
    ('misch-contemporary-implant', '2021'): 'the prosthodontics list prints "4th edition 2021" for the edition the periodontics list dates 2020 (copyright 2021)',
    ('daly-public-health', '2005'): 'the owner confirmed 1397 meant the 2013 edition (the 2nd, Oxford University Press); the book has no 3rd edition, so "3rd Ed., 2005" in the workbook is a slip',
}

# Partial notices that name two books on one row: (exam year, subject) ->
# the books as (title to match, publication year), and where the years come from.
SPLIT_NOTICES = {
    ('1398', 'oral-medicine'): (
        [("Burket's Oral Medicine", '2015'), ('Dental Management of the Medically Compromised Patient', '2018')],
        'the notice scope names "Burket 2015" and "Falace 2018"'),
    ('1398', 'operative-dentistry'): (
        [("Sturdevant's Art and Science of Operative Dentistry", '2018'), ("Craig's Restorative Dental Materials", '2018')],
        'the notice labels them "Art 2018" and "Craig 2018"'),
}

# Scopes that name a part of the book rather than chapters:
# (exam year, reference@edition) -> (the chapters, from the publisher's contents).
SCOPE_PARTS = {
    (1403, 'phillips-materials@13e'): ('فصول 5–8', 'Part II (Direct Restorative Materials) is chapters 5–8 in the publisher\'s contents'),
}

# Rows the workbook carries that are not part of the medical education assessment
# center's (سنجش پزشکی) list: (exam year, subject) -> why.
NOT_IN_OFFICIAL_LIST = {
    ('1401', 'english'): 'the four English books come from page 5 of the 1401 file, an appended page in another '
                         'format; the official table itself (page 4) only says the English questions are at Upper Intermediate level',
}

# Rows without a publication year: (exam year, reference key) -> (publication year, why).
MISSING_YEAR = {
    ('1398', 'van-noort-materials'): ('2013', 'in 1398 the newest edition was the 4th (2013); the 5th appeared in 2024'),
}
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


def fold(title: str) -> str:
    """The key reference-tocs.fa.json uses for an English title."""
    return ' '.join(title.split()).casefold()


def persian_title(title: str, language: str | None, persian: dict) -> dict:
    """A Persian book's own title, or the translation of an English one."""
    if language == 'fa':
        return {'title_fa': title, 'title_fa_origin': 'human'}
    if fold(title) in persian['titles']:
        return {'title_fa': persian['titles'][fold(title)], 'title_fa_origin': persian['origin']}
    return {}


def section_nodes(chapter: str, headings: list, persian: dict) -> dict:
    """A chapter's headings as children: ch04.s03 (section), ch04.s03.02 (subsection)."""
    if not headings:
        return {}
    children = []
    for i, (title, subtitles) in enumerate(headings, 1):
        node = {'key': f'{chapter}.s{i:02d}', 'kind': 'section', 'title': title[:300], **persian_title(title, None, persian)}
        if subtitles:
            node['children'] = [{'key': f'{chapter}.s{i:02d}.{j:02d}', 'kind': 'subsection', 'title': sub[:300],
                                 **persian_title(sub, None, persian)} for j, sub in enumerate(subtitles, 1)]
        children.append(node)
    return {'children': children}


def chapter_key(number: str) -> str:
    """Chapter 2 -> ch02, van Noort's chapter 1.3 -> ch01.3."""
    first, *rest = number.split('.')
    return 'ch' + '.'.join([f'{int(first):02d}', *rest])


def clean(value) -> str:
    return re.sub(r'\s+', ' ', str(value or '').replace('‌', '‌')).strip()


def build() -> dict:
    rows = read_sheet(WORKBOOK, SHEET)
    header, rows = rows[0], [(r + [None] * (12 - len(r)))[:12] + [['residency']] for r in rows[1:] if r]
    for extra in EXTRA_YEARS:
        data = json.loads(extra.read_text(encoding='utf-8'))
        for r in data['rows']:
            for year in [r['year'], *data.get('also_years', {})]:
                rows.append([year, r.get('period'), r['subject'], r['title'], r.get('authors'), r.get('pub_year'), r.get('edition'),
                             r.get('scope'), r.get('evidence', data.get('evidence')), None,
                             r.get('source_document', data.get('source_document')), None,
                             r.get('exam_types', data.get('exam_types', ['residency']))])
    notes: list[str] = []
    validity: dict[tuple, dict] = {}
    used: dict[str, set] = {}

    decisions: list[str] = []

    articles: dict[str, set] = {}

    def add(where: str, year, subject: str, title: str, pub: str, edition_text: str, scope, evidence, post, pdf, exam_type: str = 'residency') -> None:
        if title == ARTICLES_TITLE:
            articles.setdefault(subject, set()).add(clean(year))
            keep(exam_type, year, subject, f'announced-articles-{subject}@{clean(year)}', True, scope, evidence, post, pdf)
            return
        title_l = title.lower()
        matches = [ref for ref in REFERENCES if all(m in title_l for m in ref[0])]
        # "Pediatric Dentistry" also appears inside other titles; prefer the most specific match.
        matches.sort(key=lambda ref: -sum(len(m) for m in ref[0]))
        if not matches:
            notes.append(f'{where}: no reference matches "{title}", skipped')
            return
        ref = matches[0]
        key, editions = ref[1], ref[5]
        if pub == '' and (clean(year), key) in MISSING_YEAR:
            pub, why = MISSING_YEAR[(clean(year), key)]
            decisions.append(f'{where}: "{title}" has no publication year; recorded as {editions[pub][1]} because {why}')
        if pub not in editions:
            notes.append(f'{where}: "{title}" has no known edition for year "{pub}", skipped')
            return
        edition_key, label, _y = editions[pub]
        stated = EDITION_NUMBER.search(edition_text)
        judgement = None
        if stated and edition_key[0].isdigit() and not edition_key.startswith(stated.group(1)) and not edition_key.endswith('-fa'):
            judgement = f'workbook says "{edition_text}" for {pub}; recorded as {label}'
        elif not stated and edition_key[0].isdigit() and edition_key.endswith('e'):
            judgement = f'edition not stated; recorded as {label} from the year {pub}'
        elif pub == '2020' and key == 'torabinejad-endodontics':
            judgement = f'2020 printing, recorded as the {label}'
        if judgement and (key, pub) in CONFIRMED:
            decisions.append(f'{where}: {judgement} — {CONFIRMED[(key, pub)]}')
        elif judgement:
            notes.append(f'{where}: {judgement} — confirm')
        used.setdefault(key, set()).add(edition_key)

        keep(exam_type, year, subject, f'{key}@{edition_key}', not clean(evidence).lower().startswith('partial'), scope, evidence, post, pdf)

    def keep(exam_type: str, year, subject: str, edition: str, official: bool, scope, evidence, post, pdf) -> None:
        record = {
            'exam_type': exam_type,
            'year': int(year),
            'subject': subject,
            'edition': edition,
            'official': official,
            'scope': clean(scope) or None,
            'evidence': ' · '.join(x for x in [clean(evidence), clean(post)] if x) or None,
            'source_document': clean(pdf) or None,
        }
        identity = (exam_type, record['year'], subject, record['edition'])
        if identity in validity:
            previous = validity[identity]
            # An official row transcribed from the dated PDF supersedes an
            # earlier partial notice for the same edition. Combining their
            # scopes would falsely make the notice's shorthand official.
            if record['official'] and not previous['official']:
                validity[identity] = record
                return
            if previous['scope'] != record['scope'] and record['scope']:
                previous['scope'] = '; '.join(x for x in [previous['scope'], record['scope']] if x)
            previous['official'] = previous['official'] or record['official']
            return
        validity[identity] = record

    def read_row(number, exam_type, year, subject_name, title, pub_year, edition_text, scope, evidence, post, pdf) -> None:
        exam = '' if exam_type == 'residency' else f'{exam_type} '
        where = f'row {number} ({exam}{year}, {clean(subject_name)})'
        subject = SUBJECT_BY_NAME.get(clean(subject_name).replace('‌', ''))
        if subject is None:
            notes.append(f'{where}: unknown subject "{clean(subject_name)}", skipped')
            return
        if (clean(year), subject) in NOT_IN_OFFICIAL_LIST:
            decisions.append(f'{where}: "{clean(title)}" left out — {NOT_IN_OFFICIAL_LIST[(clean(year), subject)]}')
            return
        if any(marker in clean(title).lower() for marker in NOT_A_BOOK):
            decisions.append(f'{where}: "{clean(title)}" is a scope statement, not a book; kept out of the reference list')
            return
        if ' / ' in clean(title):
            split = SPLIT_NOTICES.get((clean(year), subject))
            if split is None:
                notes.append(f'{where}: "{clean(title)}" names two books in one partial notice; not recorded as validity')
                return
            books, why = split
            decisions.append(f'{where}: "{clean(title)}" names two books; recorded as '
                             + ' and '.join(f'{t} ({y})' for t, y in books) + f' because {why}')
            for book_title, book_year in books:
                add(where, year, subject, book_title, book_year, '', scope, evidence, post, pdf, exam_type)
            return
        add(where, year, subject, clean(title), clean(pub_year), clean(edition_text), scope, evidence, post, pdf, exam_type)

    for number, row in enumerate(rows, start=2):
        year, _period, subject_name, title, authors, pub_year, edition_text, scope, evidence, post, pdf, note, exam_types = row
        for exam_type in exam_types:
            read_row(number, exam_type, year, subject_name, title, pub_year, edition_text, scope, evidence, post, pdf)

    tocs = json.loads(TOCS.read_text(encoding='utf-8'))['editions']
    persian = json.loads(TOCS_FA.read_text(encoding='utf-8'))
    sections = json.loads(SECTIONS.read_text(encoding='utf-8'))['editions']
    for record in validity.values():
        known = [number for number, _ in tocs.get(record['edition'], {}).get('chapters', [])]
        where = f"{'' if record['exam_type'] == 'residency' else record['exam_type'] + ' '}{record['year']} {record['subject']} {record['edition']}"
        chapters = resolve_scope(record['scope'], known)
        if chapters is None and (record['year'], record['edition']) in SCOPE_PARTS:
            numbers, why = SCOPE_PARTS[(record['year'], record['edition'])]
            chapters = resolve_scope(numbers, known)
            decisions.append(f'{where}: scope "{record["scope"]}" read as chapters {numbers} — {why}')
        if chapters is None:
            if record['scope']:
                decisions.append(f'{where}: scope "{record["scope"]}" names no chapter list; kept as text only')
            continue
        unknown = [c['number'] for c in chapters if known and c['number'] not in known]
        if unknown:
            notes.append(f'{where}: scope names chapters {", ".join(unknown)} that the edition\'s table of contents lacks — confirm')
        record['scope_chapters'] = chapters
    references = []
    for _m, key, title, authors, subject, editions in REFERENCES:
        if key not in used:
            continue
        seen = {}
        for edition_key, label, year in editions.values():
            if edition_key in used[key] and edition_key not in seen:
                seen[edition_key] = {'key': edition_key, 'label': label, **({'year': year} if year else {})}
                toc = tocs.get(f'{key}@{edition_key}', {})
                if toc.get('chapters'):
                    inside = sections.get(f'{key}@{edition_key}', {}).get('chapters', {})
                    seen[edition_key]['nodes'] = [
                        {'key': chapter_key(number), 'kind': 'chapter', 'number': number, 'title': name,
                         **persian_title(name, toc.get('language'), persian),
                         **section_nodes(chapter_key(number), inside.get(number, []), persian)}
                        for number, name in toc['chapters']]
        references.append({'key': key, 'title': title, 'authors': authors, 'subject': subject,
                           'editions': sorted(seen.values(), key=lambda e: (e.get('year') or 0, e['key']))})
    for subject, years in sorted(articles.items()):
        references.append({'key': f'announced-articles-{subject}', 'title': ARTICLES_TITLE, 'subject': subject,
                           'editions': [{'key': year, 'label': 'فهرست ' + year.translate(str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹'))} for year in sorted(years)]})

    return {
        'format': 'fanoos.bank.catalog/1',
        'notes': 'Generated by scripts/import/reference_map_to_catalog.py from '
                 'docs/research/dental-residency-reference-map-1396-1405.xlsx. Edit the script or the workbook, '
                 'not this file. Chapters come from data/bank/reference-tocs.json; concepts are added as questions are classified.',
        'review_notes': notes,
        'decisions': decisions,
        'exam_types': [
            {'key': 'residency', 'name': 'دستیاری', 'active': True, 'order': 0},
            {'key': 'board', 'name': 'بورد', 'active': True, 'order': 1},
            {'key': 'promotion', 'name': 'ارتقا', 'active': True, 'order': 2},
            {'key': 'national', 'name': 'آزمون ملی', 'active': True, 'order': 3},
        ],
        'subjects': [{'key': k, 'name': n, 'name_en': e, 'order': i} for i, (k, n, e) in enumerate(SUBJECTS)],
        'concepts': [],
        'references': references,
        'validity': sorted(validity.values(), key=lambda v: (-v['year'], v['exam_type'] != 'residency', v['exam_type'], v['subject'], v['edition'])),
    }


if __name__ == '__main__':
    catalog = build()
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')
    sys.stdout.reconfigure(encoding='utf-8')
    print(f"{OUTPUT.relative_to(ROOT)}: {len(catalog['references'])} references, "
          f"{sum(len(r['editions']) for r in catalog['references'])} editions, "
          f"{len(catalog['validity'])} validity rows, {len(catalog['decisions'])} decisions, "
          f"{len(catalog['review_notes'])} notes to review")
