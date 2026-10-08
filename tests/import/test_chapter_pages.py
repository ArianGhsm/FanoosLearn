"""Chapter page runs follow evidence from the book's own opening pages."""
import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO / 'scripts' / 'references'))
import build_chapter_pages as chapter_pages  # noqa: E402
from build_chapter_pages import bookmark_openings, documented_openings, locate, openings, words  # noqa: E402
from apply_classification import flat  # noqa: E402
from find_in_books import Book  # noqa: E402


class ChapterPagesTest(unittest.TestCase):
    def test_persian_running_heads_anchor_every_chapter(self):
        pages = [
            (1, 'فهرست\n19........: فصل 1'),
            (2, '1فصل\nتاریخچه دندانپزشکی\nمتن'),
            (3, '1 فصل\nمتن'),
            (4, '2\u200cفصل\nسلامت و بیماری\nمتن'),
            (5, '2 فصل\nمتن'),
        ]
        result = locate(pages, [['1', 'تاریخچه دندانپزشکی'], ['2', 'سلامت و بیماری']])
        self.assertEqual(result['runs'], [[None, 1, 1], ['1', 2, 3], ['2', 4, 5]])
        self.assertEqual(result['not_seen'], [])

    def test_opening_after_local_contents_sets_exact_boundary(self):
        pages = [
            (1, 'CHAPTER 1\nFirst Topic\ntext'),
            (2, 'First Topic\nA reference to Figure 2.1 is here.'),
            (3, '\n'.join(['Local contents'] * 50 + ['CHaPter 2', 'Second Topic', 'text'])),
            (4, 'Second Topic\nA reference to Figure 3.1 is here.'),
            (5, 'CHAPTER 3\nThird Topic\ntext'),
        ]
        result = locate(pages, [['1', 'First Topic'], ['2', 'Second Topic'], ['3', 'Third Topic']])
        self.assertEqual(result['runs'], [['1', 1, 2], ['2', 3, 4], ['3', 5, 5]])

    def test_roman_numbered_summary_is_not_a_chapter_opening(self):
        pages = [
            (1, 'xviii\nDENTAL MANAGEMENT: A SUMMARY\nChapter 2\nSecond Topic'),
            (2, 'CHAPTER 1\nFirst Topic'),
            (3, 'First Topic\ntext'),
            (4, 'CHAPTER 2\nSecond Topic'),
        ]
        chapters = {'1': words('First Topic'), '2': words('Second Topic')}
        self.assertEqual(openings(pages, chapters), {'1': 2, '2': 4})

    def test_only_rebuild_preserves_existing_editions(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            local = root / '.local'
            (local / 'references').mkdir(parents=True)
            (local / 'references' / 'new@1e.txt').write_text(
                '=== PAGE 1 ===\nCHAPTER 1\nFirst Topic\ntext\n', encoding='utf-8')
            toc = root / 'tocs.json'
            toc.write_text(json.dumps({'editions': {'new@1e': {'chapters': [['1', 'First Topic']]}}}), encoding='utf-8')
            output = root / 'chapter-pages.json'
            previous = {'runs': [['7', 1, 9]], 'chapters_seen': 1, 'chapters_listed': 1,
                        'not_seen': [], 'supported_pages': {'7': '2/9'}}
            output.write_text(json.dumps({'editions': {'old@1e': previous}}), encoding='utf-8')
            argv = ['build_chapter_pages.py', '--local', str(local), '--only', 'new@1e']
            with patch.object(chapter_pages, 'TOCS', toc), patch.object(chapter_pages, 'OUT', output), patch.object(sys, 'argv', argv):
                self.assertEqual(chapter_pages.main(), 0)
            result = json.loads(output.read_text(encoding='utf-8'))['editions']
            self.assertEqual(result['old@1e'], previous)
            self.assertEqual(result['new@1e']['runs'], [['1', 1, 1]])

    def test_outline_uses_ordered_chapters_across_levels(self):
        toc = [[1, '1 A Section', 1], [2, '1 First Topic', 5],
               [3, '2 Second Topic', 11], [2, '3 Third Topic', 20]]
        chapters = [['1', 'First Topic'], ['2', 'Second Topic'], ['3', 'Third Topic']]
        self.assertEqual(bookmark_openings(toc, chapters), {'1': 5, '2': 11, '3': 20})

    def test_incomplete_or_unordered_outline_is_not_used(self):
        chapters = [['1', 'First Topic'], ['2', 'Second Topic']]
        self.assertEqual(bookmark_openings([[1, '1 First Topic', 5]], chapters), {})
        self.assertEqual(bookmark_openings([[1, '1 First Topic', 8], [1, '2 Second Topic', 7]], chapters), {})

    def test_documented_starts_require_complete_ordered_pdf_pages(self):
        chapters = [['1', 'First Topic'], ['2', 'Second Topic']]
        self.assertEqual(documented_openings({'1': 3, '2': 8}, chapters, 10), {'1': 3, '2': 8})
        for starts in ({'1': 3}, {'1': 8, '2': 3}, {'1': 3, '2': 11}):
            with self.assertRaises(ValueError):
                documented_openings(starts, chapters, 10)

    def test_persian_quote_requires_actual_words_on_the_page(self):
        page = 'شاخص سلامت دهان و دندان در جامعه اندازه گیری می شود.'
        self.assertIn(flat('شاخص سلامت دهان و دندان'), flat(page))
        self.assertNotIn(flat('مراقبت ویژه سالمندان و کودکان'), flat(page))

    def test_persian_book_search_returns_a_verifiable_quote(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / 'persian.txt'
            path.write_text('=== PAGE 1 ===\nسلامت دهان و دندان در جامعه مهم است.\n'
                            'بهداشت باید رعایت شود.\nاین متن صفحه اول است.\nو جمله پایانی.\n'
                            '=== PAGE 2 ===\nشاخص سلامت دهان و دندان در جامعه برای غربالگری به کار می رود.\n'
                            'آزمایش با این شاخص انجام می شود.\nاین متن صفحه دوم است.\nو جمله پایانی.\n',
                            encoding='utf-8')
            book = Book(path, [['1', 1, 2]])
            matches = book.search(['غربالگری', 'شاخص سلامت'], 2)
            page_index = matches[0][1]
            self.assertEqual(book.pages[page_index][0], 2)
            quote = book.quote(page_index, ['غربالگری', 'شاخص سلامت'])
            self.assertIsNotNone(quote)
            self.assertIn(flat(quote), flat(book.pages[page_index][1]))


if __name__ == '__main__':
    unittest.main()
