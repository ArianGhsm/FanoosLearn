"""Historical notice corrections and unresolved identities must survive generation."""
import importlib.util
import json
import sys
import unittest
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/import'))
SPEC = importlib.util.spec_from_file_location('historical_reference_map', ROOT / 'scripts/import/reference_map_to_catalog.py')
MAPPER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MAPPER)


class BoardNationalReferenceHistoryTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.catalog = json.loads((ROOT / 'data/bank/catalog.json').read_text())
        cls.rows = cls.catalog['validity']

    def row(self, exam, year, subject, edition):
        rows = [r for r in self.rows if (r['exam_type'], r['year'], r['subject'], r['edition'])
                == (exam, year, subject, edition)]
        self.assertEqual(len(rows), 1)
        return rows[0]

    def test_catalog_is_reproducible_from_the_notices(self):
        self.assertEqual(MAPPER.build(), self.catalog)

    def test_recovered_years_have_the_verified_source_counts(self):
        counts = Counter((r['exam_type'], r['year']) for r in self.rows)
        self.assertEqual({y: counts['board', y] for y in (1398, 1399, 1401, 1402, 1403, 1404)},
                         {1398: 49, 1399: 43, 1401: 60, 1402: 64, 1403: 64, 1404: 65})
        self.assertEqual({y: counts['national', y] for y in (1401, 1402, 1403)},
                         {1401: 19, 1402: 18, 1403: 18})

    def test_missing_years_are_not_filled_from_a_later_notice(self):
        self.assertFalse(any(r['exam_type'] == 'board' and r['year'] in (1396, 1397, 1400) for r in self.rows))
        self.assertFalse(any(r['exam_type'] == 'national' and 1396 <= r['year'] <= 1400 for r in self.rows))

    def test_pending_edition_identities_never_become_official_rows(self):
        pending = [(1398, 'pediatric-dentistry', 'nowak-pediatric@6e'),
                   (1399, 'endodontics', 'ingle-endodontics@7e'),
                   (1399, 'prosthodontics', 'rosenstiel-fixed@5e'),
                   (1399, 'prosthodontics', 'stewart-rpd@4e'),
                   (1402, 'oral-pathology', 'neville-oral-pathology@4e'),
                   (1402, 'oral-pathology', 'neville-oral-pathology@5e'),
                   (1402, 'oral-pathology', 'regezi-oral-pathology@7e'),
                   (1403, 'orthodontics', 'burstone-biomechanics@1e')]
        identities = {(r['year'], r['subject'], r['edition']) for r in self.rows if r['exam_type'] == 'board'}
        self.assertFalse(identities.intersection(pending))
        self.assertEqual(sum('pending:' in n for n in self.catalog['review_notes']), 7)

    def test_1404_pathology_and_operative_corrections_take_precedence(self):
        for edition in ('robbins-pathology@11e', 'woo-oral-pathology@3e'):
            row = self.row('board', 1404, 'oral-pathology', edition)
            self.assertEqual(row['source_document'], 'https://t.me/dental_k/333')
        misch = self.row('board', 1404, 'operative-dentistry', 'misch-implant-prosthetics@2e')
        self.assertEqual(misch['source_document'], 'https://t.me/dental_k/202')

    def test_board_1403_does_not_use_the_promotion_orthodontics_scope(self):
        nanda = self.row('board', 1403, 'orthodontics', 'nanda-esthetics-biomechanics@2e')
        self.assertIn('27', nanda['scope'])
        self.assertNotEqual(nanda['scope'], 'فصل 25')
        self.assertFalse(any(r['exam_type'] == 'promotion' and 1396 <= r['year'] <= 1404 for r in self.rows))

    def test_pediatric_transition_is_applied_to_the_stated_board_year(self):
        self.row('board', 1403, 'pediatric-dentistry', 'harris-primary-preventive@8e')
        self.row('board', 1404, 'pediatric-dentistry', 'splieth-preventive@1e')
        self.assertFalse(any(r['exam_type'] == 'board' and r['year'] == 1403
                             and r['edition'] == 'splieth-preventive@1e' for r in self.rows))

    def test_blank_and_conflicting_scopes_are_not_synthetic_all_chapters(self):
        for year in (1402, 1403):
            row = self.row('national', year, 'pediatric-dentistry', 'mcdonald-avery-pediatric@11e')
            self.assertIsNone(row.get('scope_chapters'))
            self.assertIn('فصول منتخب', row['scope'])
        row = self.row('board', 1403, 'oral-surgery', 'peterson-principles@4e')
        self.assertIsNone(row.get('scope_chapters'))
        self.assertIn('تعارض', row['scope'])


if __name__ == '__main__':
    unittest.main()
