"""The checks apply_classification.py makes before a source is believed."""
import json
import sys
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest
from unittest.mock import patch

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO / 'scripts' / 'references'))
from apply_classification import Books, carry_over, chapter_nodes, flat, fragments, human_guard, printed_page, save_sitting  # noqa: E402


class ApplyClassificationTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.catalog = json.loads((REPO / 'data' / 'bank' / 'catalog.json').read_text(encoding='utf-8'))

    def test_cli_allows_unambiguous_original_pdf_page_labels(self):
        import subprocess
        result = subprocess.run(
            [sys.executable, str(REPO / "scripts/references/apply_classification.py"), "--help"],
            check=True, capture_output=True, text=True,
        )
        self.assertIn("--pdf-pages", result.stdout)

    def test_evidence_is_quoted_fragments(self):
        self.assertEqual(fragments('the flap should be compressed ... a hematoma under the flap'),
                         ['the flap should be compressed', 'a hematoma under the flap'])
        self.assertEqual(fragments('one … two'), ['one', 'two'])

    def test_a_quote_matches_across_line_breaks_hyphens_and_case(self):
        page = 'The VRF may mimic other con-\nditions, commonly Periodontal\ndisease or failed treatment.'
        self.assertIn(flat('the vrf may mimic other conditions, commonly periodontal disease'), flat(page))
        self.assertNotIn(flat('the vrf always mimics periodontal disease'), flat(page))

    def test_private_use_ligature_glyphs_are_dropped_on_both_sides(self):
        page = 'the color changes can be marginal, diuse, or patch-like'
        self.assertIn(flat('marginal, diuse, or patch-like'), flat(page))

    def test_the_printed_page_number_is_read_from_the_head_or_foot(self):
        self.assertEqual(printed_page('439\nCHAPTER 20 Apical Microsurgery\nbody text'), '439')
        self.assertEqual(printed_page('body text\nmore\nCHAPTER 3 Endodontic Radiology 50'), '50')
        self.assertIsNone(printed_page('no number here\nat all\nreally'))

    def test_a_chapter_found_in_the_nearest_edition_is_cited_in_the_official_one_by_title(self):
        node = carry_over(self.catalog, 'neville-oral-pathology@5e', '15', 'neville-oral-pathology@4e')
        self.assertIsNotNone(node)
        self.assertEqual(node['title'], chapter_nodes(self.catalog, 'neville-oral-pathology@5e')['15']['title'])

    def test_a_human_checked_chapter_is_never_replaced_silently(self):
        checked = {'sources': [{'ref': 'torabinejad-endodontics@6e#ch12', 'origin': 'human'}]}
        why, keep = human_guard(checked, 'torabinejad-endodontics@6e#ch13', {})
        self.assertIn('human-checked', why)
        why, keep = human_guard(checked, None, {'none': 'not found'})
        self.assertIn('human-checked', why)
        self.assertEqual(human_guard(checked, 'torabinejad-endodontics@6e#ch13', {'override_human': 'the page states it; ch12 only names it'}), (None, False))
        self.assertEqual(human_guard(checked, 'torabinejad-endodontics@6e#ch12', {}), (None, True))
        self.assertEqual(human_guard({'sources': [{'ref': 'x#ch1', 'origin': 'ai'}]}, 'x#ch2', {}), (None, False))

    def test_find_in_books_quotes_pass_the_evidence_check(self):
        from find_in_books import page_score, quote_page
        page = 'Apical patency is a technique that advocated the repeated placement of small hand files to or beyond the foramen. ' * 3
        quote = quote_page(page, ['apical patency', 'small hand files'])
        self.assertIsNotNone(quote)
        self.assertGreaterEqual(len(quote.split()), 4)
        self.assertIn(flat(quote), flat(page))
        self.assertGreater(page_score(page, ['apical patency', 'small hand files']), 0)

    def test_page_validation_requires_a_hash_bound_map_and_reads_the_pdf_page(self):
        class FakePdf:
            page_count = 2
            source_sha256 = 'a' * 64

            def page_text(self, page):
                return f'Synthetic PDF page {page}'

        with patch('apply_classification.open_verified_reference', return_value=FakePdf()):
            books = Books(Path('/unused/storage'), Path('/unused/mysql.cnf'), 'test')
            self.assertIsNone(books.page('fictional-book@1e', 1, {'runs': [['1', 1, 2]]}))
            self.assertIn('no verified PDF source hash', books.unavailable['fictional-book@1e'])

            books = Books(Path('/unused/storage'), Path('/unused/mysql.cnf'), 'test')
            valid = {'runs': [['1', 1, 2]], 'source_pdf_sha256': 'a' * 64}
            self.assertEqual(books.page('fictional-book@1e', 2, valid), 'Synthetic PDF page 2')

        with patch('apply_classification.open_verified_reference', return_value=FakePdf()):
            books = Books(Path('/unused/storage'), Path('/unused/mysql.cnf'), 'test')
            mismatched = {'runs': [['1', 1, 2]], 'source_pdf_sha256': 'b' * 64}
            self.assertIsNone(books.page('fictional-book@1e', 1, mismatched))
            self.assertIn('different PDF', books.unavailable['fictional-book@1e'])

        with patch('apply_classification.open_verified_reference', return_value=FakePdf()):
            books = Books(Path('/unused/storage'), Path('/unused/mysql.cnf'), 'test')
            gapped = {'runs': [[None, 1, 1], ['1', 2, 2]], 'source_pdf_sha256': 'a' * 64}
            gapped['runs'][1][1] = 3
            self.assertIsNone(books.page('fictional-book@1e', 1, gapped))
            self.assertIn('gap/overlap', books.unavailable['fictional-book@1e'])

    def test_validated_sitting_write_leaves_no_atomic_staging_file(self):
        with TemporaryDirectory() as tmp:
            output = Path(tmp) / 'validated.json'
            save_sitting(output, {'format': 'synthetic'})
            self.assertEqual(json.loads(output.read_text(encoding='utf-8')), {'format': 'synthetic'})
            self.assertEqual(list(Path(tmp).glob('*.tmp')), [])


if __name__ == '__main__':
    unittest.main()
