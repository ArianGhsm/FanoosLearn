"""The reference-text list covers every official edition, and legacy texts convert page by page."""
import json
import sys
import tempfile
import unittest
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO / 'scripts' / 'references'))
from build_reference_texts import from_text  # noqa: E402


class ReferenceTextsTest(unittest.TestCase):
    def test_every_official_edition_is_listed_once(self):
        catalog = json.loads((REPO / 'data' / 'bank' / 'catalog.json').read_text(encoding='utf-8'))
        listed = json.loads((REPO / 'data' / 'bank' / 'reference-texts.json').read_text(encoding='utf-8'))['editions']
        known = {f"{ref['key']}@{edition['key']}" for ref in catalog['references'] for edition in ref.get('editions', [])}
        official = {row['edition'] for row in catalog['validity'] if 1398 <= int(row['year']) <= 1405}

        self.assertEqual(sorted(official - set(listed)), [], 'an official edition has no entry')
        self.assertEqual(sorted(set(listed) - known), [], 'an entry names an edition the catalog does not know')
        for edition, entry in listed.items():
            kinds = [kind for kind in ('pdf', 'text', 'missing') if kind in entry]
            self.assertEqual(len(kinds), 1, f'{edition} must be exactly one of pdf, text or missing')
            if 'nearest' in entry:
                self.assertIn(entry['nearest'], listed, f'{edition}: nearest edition is not listed')
                self.assertNotIn('missing', listed[entry['nearest']], f'{edition}: nearest edition is itself missing')

    def test_a_legacy_text_keeps_every_page_under_its_own_marker(self):
        with tempfile.TemporaryDirectory() as tmp:
            source = Path(tmp) / 'book.txt'
            source.write_text('Title: X\n\n--- SOURCE PDF PAGE 5 ---\nfirst\n\n--- SOURCE PDF PAGE 7 ---\nsecond\n', encoding='utf-8')
            text, pages, _ = from_text('x@1e', source)
        self.assertEqual(pages, 2)
        self.assertIn('# edition: x@1e', text)
        self.assertIn('=== PAGE 5 ===\nfirst\n', text)
        self.assertIn('=== PAGE 7 ===\nsecond\n', text)
        self.assertNotIn('SOURCE PDF PAGE', text)


if __name__ == '__main__':
    unittest.main()
