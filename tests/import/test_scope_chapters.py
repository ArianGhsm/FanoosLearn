"""The announced-scope reader (scripts/import/scope_chapters.py) on the
shapes the real reference lists use."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts' / 'import'))
from scope_chapters import resolve  # noqa: E402

TWENTY = [str(n) for n in range(1, 21)]


def numbers(result):
    return [c['number'] for c in result]


def partial(result):
    return [c['number'] for c in result if 'partial' in c]


class ScopeChaptersTest(unittest.TestCase):
    def test_whole_book_and_exclusions(self):
        self.assertEqual(numbers(resolve('تمام فصول', TWENTY)), TWENTY)
        self.assertEqual(numbers(resolve('تمام فصول به جز 4،8،18–19', TWENTY)),
                         [n for n in TWENTY if n not in {'4', '8', '18', '19'}])
        self.assertEqual(numbers(resolve('تمام فصول به جز ۱، ۲', TWENTY)), TWENTY[2:])

    def test_whole_book_without_a_chapter_list_is_unknown(self):
        self.assertIsNone(resolve('تمام فصول', []))

    def test_lists_ranges_and_page_limited_chapters(self):
        result = resolve('فصول 1،3–8،11–13؛ فصل2 صص18–47؛ فصل14 به جز صص462–465 و472–476', TWENTY)
        self.assertEqual(numbers(result), ['1', '2', '3', '4', '5', '6', '7', '8', '11', '12', '13', '14'])
        self.assertEqual(partial(result), ['2', '14'])

    def test_pages_are_not_chapters(self):
        result = resolve('فصول 9–10؛ فقط صص 171–178 از فصل 10؛ فصل 13', TWENTY)
        self.assertEqual(numbers(result), ['9', '10', '13'])
        self.assertEqual(partial(result), ['10'])

    def test_a_parenthesis_limits_only_the_chapter_it_names(self):
        result = resolve('فصول 4–7 (بافت نرم فصل 7 تا ص 161)؛ فصل 10 به جز صص 224–232', TWENTY)
        self.assertEqual(numbers(result), ['4', '5', '6', '7', '10'])
        self.assertEqual(partial(result), ['7', '10'])

    def test_a_qualifier_without_numbers_keeps_the_list(self):
        result = resolve('فصول 2،3،4،12؛ فقط مباحث ملاحظات دندانپزشکی', TWENTY)
        self.assertEqual(numbers(result), ['2', '3', '4', '12'])
        self.assertEqual(partial(result), [])

    def test_dotted_sections(self):
        sections = ['1.1', '1.2', '2.1', '2.2', '3.1', '3.2', '3.3', '3.4', '3.5', '3.6', '3.7', '3.8']
        self.assertEqual(numbers(resolve('بخش 3؛ بخش‌های 3.4–3.7', sections)), ['3.4', '3.5', '3.6', '3.7'])
        self.assertEqual(numbers(resolve('بخش‌های 2.2،3،3.4،3.5', sections)), ['2.2', '3.4', '3.5'])

    def test_the_official_lists_own_phrasing(self):
        self.assertEqual(numbers(resolve('فصول 4 و 5 و 13 تا 16 و 18 کتاب', TWENTY)), ['4', '5', '13', '14', '15', '16', '18'])
        self.assertEqual(numbers(resolve('فصل 1 تا پایان فصل 3 - فصل 12 - فصل 15 تا پایان فصل 17', TWENTY)),
                         ['1', '2', '3', '12', '15', '16', '17'])
        self.assertEqual(numbers(resolve('فصل های 4-10-12-14 کتاب', TWENTY)), ['4', '10', '12', '14'])
        self.assertEqual(numbers(resolve('کلیه فصول (به جز فصول 5 و 9 و 10) کتاب', TWENTY)),
                         [n for n in TWENTY if n not in {'5', '9', '10'}])

    def test_only_and_whole_list_qualifiers_are_not_partial(self):
        self.assertEqual(partial(resolve('فقط فصول 1 و 2 و 3', TWENTY)), [])
        self.assertEqual(partial(resolve('فصول 2 و 3 و 4 کتاب (فقط مباحث ملاحظات دندانپزشکی)', TWENTY)), [])

    def test_a_parenthesis_after_a_chapter_limits_that_chapter(self):
        result = resolve('فصول 1 (از صفحه 1 تا 15) و 2 و 3 و 13 (از صفحه 461 تا 484) کتاب', TWENTY)
        self.assertEqual(numbers(result), ['1', '2', '3', '13'])
        self.assertEqual(partial(result), ['1', '13'])
        self.assertEqual(partial(resolve('فصل 9 صفحات 276 تا 278 و 288 تا 291', TWENTY)), ['9'])

    def test_notices_that_only_change_a_list_are_unreadable(self):
        for text in ['فصول 4،18،19 از منابع حذف شدند.', 'فصول 10 و17 به منابع آزمون 98 اضافه شد.',
                     'Part II: Direct Restorative Materials', 'ذکر شده به عنوان منبع؛ فصل/صفحه مشخص نشده',
                     'ویرایش‌های Burket 2015 و Falace 2018؛ حذف فصل پیگمانتاسیون', None, '']:
            self.assertIsNone(resolve(text, TWENTY), text)


if __name__ == '__main__':
    unittest.main()
