"""How docx_to_sitting.py reads the lines of a consolidated year document."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts' / 'import'))
import json  # noqa: E402
import tempfile  # noqa: E402
import zipfile  # noqa: E402
from docx_to_sitting import BOOKLET, QUESTION, answer_of, build, key_from_table, norm, subject_of  # noqa: E402


def write_docx(path: Path, lines: list[str]) -> None:
    body = ''.join(f'<w:p><w:r><w:t xml:space="preserve">{line}</w:t></w:r></w:p>' for line in lines)
    with zipfile.ZipFile(path, 'w') as z:
        z.writestr('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' + body + '</w:body></w:document>')
        z.writestr('word/_rels/document.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>')


class DocxToSittingTest(unittest.TestCase):
    def test_answer_lines_in_every_shape(self):
        self.assertEqual(answer_of(norm('پاسخ صحیح: گزینه ۲ (ب)')), {'choice': 2, 'status': 'final'})
        self.assertEqual(answer_of('پاسخ کلیدی: 3'), {'choice': 3, 'status': 'final'})
        self.assertEqual(answer_of('Correct answer: option 4 (d)'), {'choice': 4, 'status': 'final'})
        self.assertEqual(answer_of(norm('پاسخ نهایی: گزینه‌های ۱ و ۳')), {'choice': 1, 'status': 'final', 'also_correct': [3]})
        self.assertEqual(answer_of('پاسخ نهایی: این سؤال حذف شده است'), {'choice': None, 'status': 'voided'})
        self.assertIsNone(answer_of('پاسخ'))
        self.assertEqual(answer_of('پاسخ کلیدی: گزینه 1 (الف) — کلید اولیه'), {'choice': 1, 'status': 'preliminary'})
        self.assertEqual(answer_of('پاسخ کلیدی: نامشخص (کلید کامل معتبر یافت نشده است)'), {'choice': None, 'status': 'disputed'})

    def test_question_numbers_and_booklet_sources_in_the_newer_layouts(self):
        for line in ['سؤال 1 ـ کدام صحیح است؟', 'سؤال 001 | کدام صحیح است؟', norm('۱. کدام صحیح است؟')]:
            self.assertEqual(int(QUESTION.match(line).group(1)), 1, line)
        self.assertEqual(BOOKLET.match('منبع درج‌شده در دفترچه: کارانزا ۲۰۱۹').group(1), 'کارانزا ۲۰۱۹')
        self.assertEqual(BOOKLET.match('منبع ذکرشده در دفترچه: JCP').group(1), 'JCP')

    def test_a_specialty_paper_becomes_its_own_sitting(self):
        with tempfile.TemporaryDirectory() as tmp:
            docx = Path(tmp) / 'board.docx'
            write_docx(docx, [
                'آزمون بورد تخصصی دندان‌پزشکی ۱۴۰۳', 'رشته: پریودانتیکس',
                'سؤال 1 ـ اولین سؤال؟', 'الف) یک', 'ب) دو', 'ج) سه', 'د) چهار', 'پاسخ کلیدی: نامشخص (کلید کامل معتبر یافت نشده است)',
                'منبع درج‌شده در دفترچه: کارانزا ۲۰۱۹',
                'سؤال 2 ـ دومین سؤال؟', 'الف) یک', 'ب) دو', 'ج) سه', 'د) چهار', 'پاسخ کلیدی: گزینه 2 (ب) — کلید اولیه',
            ])
            report = build(docx, 1403, Path(tmp), 'A', None, exam_type='board', round_=2, subject='periodontics', expected=2)
            sitting = json.loads((Path(tmp) / 'board-1403-2.json').read_text(encoding='utf-8'))
        self.assertEqual((sitting['exam_type'], sitting['year'], sitting['round'], sitting['answer_key_status']), ('board', 1403, 2, 'preliminary'))
        self.assertEqual([q['subject'] for q in sitting['questions']], ['periodontics', 'periodontics'])
        self.assertEqual(sitting['questions'][0]['answer']['status'], 'disputed')
        self.assertIsNone(sitting['questions'][0]['answer']['choice'])
        self.assertEqual(sitting['questions'][0]['booklet_source'], 'کارانزا 2019')  # digits normalised like the rest of the text
        self.assertEqual(sitting['questions'][1]['answer']['status'], 'preliminary')
        self.assertEqual((report['no_valid_key'], report['preliminary'], report['booklet_sources']), (1, 1, 1))
        self.assertFalse([w for w in report['warnings'] if 'absent' in w])

    def test_subject_headings_and_what_is_not_one(self):
        self.assertEqual(subject_of('بیماری های دهان، فک و صورت'), 'oral-medicine')
        self.assertEqual(subject_of('زیست مواد دندانی'), 'dental-materials')
        self.assertEqual(subject_of('دندان پزشکی ترمیمی'), 'operative-dentistry')
        self.assertEqual(subject_of('زبان تخصصی'), 'english')
        self.assertIsNone(subject_of('12. در درمان اندودانتیک کدام صحیح است؟'))
        self.assertIsNone(subject_of('الف) پریودنتیت مزمن'))

    def test_the_closing_key_table_in_its_layouts(self):
        self.assertEqual(key_from_table(['سؤال 50: 4', '100:1 و 3']), {50: [4], 100: [1, 3]})
        self.assertEqual(key_from_table(['189', '4', '191', '2']), {189: [4], 191: [2]})


if __name__ == '__main__':
    unittest.main()
