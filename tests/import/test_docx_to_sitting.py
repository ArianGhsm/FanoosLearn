"""How docx_to_sitting.py reads the lines of a consolidated year document."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts' / 'import'))
from docx_to_sitting import answer_of, key_from_table, norm, subject_of  # noqa: E402


class DocxToSittingTest(unittest.TestCase):
    def test_answer_lines_in_every_shape(self):
        self.assertEqual(answer_of(norm('پاسخ صحیح: گزینه ۲ (ب)')), {'choice': 2, 'status': 'final'})
        self.assertEqual(answer_of('پاسخ کلیدی: 3'), {'choice': 3, 'status': 'final'})
        self.assertEqual(answer_of('Correct answer: option 4 (d)'), {'choice': 4, 'status': 'final'})
        self.assertEqual(answer_of(norm('پاسخ نهایی: گزینه‌های ۱ و ۳')), {'choice': 1, 'status': 'final', 'also_correct': [3]})
        self.assertEqual(answer_of('پاسخ نهایی: این سؤال حذف شده است'), {'choice': None, 'status': 'voided'})
        self.assertIsNone(answer_of('پاسخ'))

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
