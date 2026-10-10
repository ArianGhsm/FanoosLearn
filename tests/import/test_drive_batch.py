"""How drive_batch.py reads an exam paper's identity from its Drive path (the owner's real folder and file names)."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts' / 'import'))
from drive_batch import guess  # noqa: E402

TOP = 'سوالات ورد بورد／رزیدنتی／ارتقا'


class DriveBatchTest(unittest.TestCase):
    def test_board_papers_get_their_specialty_slot(self):
        self.assertEqual(
            {k: v for k, v in guess(f'{TOP}/۰۱ ـ بورد/۱۴۰۰/بورد پریودانتیکس 1400 - 100 سوال و کلید اولیه.docx', TOP).items() if k != 'drive_path'},
            {'type': 'board', 'year': 1400, 'round': 2, 'subject': 'periodontics'})
        english = guess(f'{TOP}/۰۱ ـ بورد/۱۴۰۳/Board_1403_08_Orthodontics_Complete_100Q.docx', TOP)
        self.assertEqual((english['type'], english['year'], english['round'], english['subject']), ('board', 1403, 9, 'orthodontics'))
        pathology = guess(f'{TOP}/۰۱ ـ بورد/۱۴۰۱/بورد آسیب‌شناسی دهان ۱۴۰۱ - کامل با کلید اولیه.docx', TOP)
        self.assertEqual((pathology['year'], pathology['subject'], pathology['round']), (1401, 'oral-pathology', 7))
        kids = guess(f'{TOP}/۰۱ ـ بورد/۱۴۰۱/بورد دندان‌پزشکی کودکان ۱۴۰۱ - ۱۰۰ سؤال و کلید اولیه.docx', TOP)
        self.assertEqual(kids['subject'], 'pediatric-dentistry')

    def test_national_papers_get_their_month(self):
        self.assertEqual(guess(f'{TOP}/۰۳ ـ ملی/۱۳۹۹/آزمون ملی مرداد ۱۳۹۹ - نسخه نهایی متنی ۳۴۰ سؤال.docx', TOP)['round'], 1)
        dey = guess(f'{TOP}/۰۳ ـ ملی/۱۴۰۲/آزمون ملی دندان‌پزشکی دی ۱۴۰۲ - کامل ۲۴۰ سؤال و کلید نهایی.docx', TOP)
        self.assertEqual((dey['type'], dey['year'], dey['round']), ('national', 1402, 2))
        self.assertIn('review', guess(f'{TOP}/۰۳ ـ ملی/۱۴۰۵/آزمون ملی ۱۴۰۵.docx', TOP))

    def test_second_forms_archives_and_partial_copies_are_skipped(self):
        self.assertIn('skip', guess(f'{TOP}/۰۳ ـ ملی/۱۳۹۶/آزمون ملی ۱۳۹۶ گروه B - تمام ۲۴۰ سؤال.docx', TOP))
        self.assertNotIn('skip', guess(f'{TOP}/۰۳ ـ ملی/۱۳۹۶/آزمون ملی ۱۳۹۶ - گروه A - نسخه نهایی و ویرایش‌شده - ۲۴۰ سؤال.docx', TOP))
        self.assertIn('skip', guess(f'{TOP}/۰۱ ـ بورد/۱۴۰۳/بایگانی نسخه‌های قدیمی بورد ۱۴۰۳/بورد ۱۴۰۳ - پریودانتیکس - ۱۰۰ سؤال.docx', TOP))
        self.assertIn('skip', guess(f'{TOP}/۹۰ ـ منابع و نسخه‌های ناقص/نسخه‌های ناقص دستیاری ۱۳۹۳/Dental_Residency_1393_Questions_001-050.docx', TOP))

    def test_residency_papers(self):
        old = guess(f'{TOP}/۰۴ ـ دستیاری/۱۳۹۵/دستیاری_دندانپزشکی_۱۳۹۵_نسخه_نهایی_۲۵۰_سوال.docx', TOP)
        self.assertEqual((old['type'], old['year'], old['round']), ('residency', 1395, 1))
        self.assertNotIn('review', old)


if __name__ == '__main__':
    unittest.main()
