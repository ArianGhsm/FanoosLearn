"""The dated partial notice and later supplied four-page 1398 list together cover 12 clinical subjects."""
import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class ReferenceMap1398Test(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.catalog = json.loads((ROOT / 'data/bank/catalog.json').read_text(encoding='utf-8'))
        cls.rows = [r for r in cls.catalog['validity']
                    if r['exam_type'] == 'residency' and r['year'] == 1398]

    def test_complete_reference_list(self):
        official = [r for r in self.rows if r['official']]
        self.assertEqual(len(official), 19)
        self.assertEqual(len(self.rows), 19)
        self.assertEqual({r['subject'] for r in official}, {
            'endodontics', 'operative-dentistry', 'oral-medicine', 'oral-radiology',
            'oral-surgery', 'orthodontics', 'pediatric-dentistry', 'periodontics',
            'prosthodontics', 'community-dentistry', 'dental-materials', 'oral-pathology',
        })
        self.assertFalse(any(r['subject'] == 'english' for r in self.rows))
        earlier = [r for r in official if 'F3F304ED' in (r['source_document'] or '')]
        added = [r for r in official if 'E6409765' in (r['source_document'] or '')]
        self.assertEqual((len(earlier), len(added)), (13, 6))
        self.assertTrue(all(r.get('evidence') for r in added))

    def test_announced_chapter_exclusions_and_limits(self):
        by_edition = {r['edition']: r for r in self.rows}
        def numbers(key):
            return {c['number'] for c in by_edition[key]['scope_chapters']}

        self.assertEqual(numbers('neville-oral-pathology@4e'),
                         {str(n) for n in range(1, 20)} - {'4', '18', '19'})
        self.assertEqual(numbers('shillingburg-fixed@4e'),
                         {str(n) for n in range(1, 30)} - {'5', '11', '12'})
        self.assertEqual(numbers('mccracken-rpd@12e'),
                         {str(n) for n in range(1, 26)})
        self.assertEqual(numbers('zarb-edentulous@13e'),
                         {str(n) for n in range(1, 24)} - {'18', '19', '20', '21', '22'})
        self.assertEqual(numbers('national-oral-health@1394'),
                         {str(n) for n in range(1, 17)})
        self.assertEqual(len(numbers('van-noort-materials@4e')), 26)

        sturdevant = by_edition['sturdevant-operative@7e']
        self.assertIn('صفحات 1', sturdevant['scope_chapters'][0]['partial'])
        emergencies = by_edition['malamed-medical-emergencies@7e']
        self.assertEqual([c['number'] for c in emergencies['scope_chapters']],
                         ['1', '2', '3', *[str(n) for n in range(5, 14)], '30', '31'])


if __name__ == '__main__':
    unittest.main()
