"""The first 1398 official release must override only its covered subjects."""
import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class ReferenceMap1398Test(unittest.TestCase):
    def test_partial_official_release(self):
        catalog = json.loads((ROOT / 'data/bank/catalog.json').read_text(encoding='utf-8'))
        rows = [r for r in catalog['validity'] if r['exam_type'] == 'residency' and r['year'] == 1398]
        official = [r for r in rows if r['official']]
        self.assertEqual(len(official), 13)
        self.assertEqual({r['subject'] for r in official}, {
            'endodontics', 'operative-dentistry', 'oral-medicine', 'oral-radiology',
            'oral-surgery', 'orthodontics', 'pediatric-dentistry', 'periodontics',
        })
        self.assertTrue(all(r['source_document'] and 'F3F304ED' in r['source_document'] for r in official))
        self.assertFalse(any(r['subject'] == 'prosthodontics' for r in rows))
        self.assertEqual({r['subject'] for r in rows if not r['official']}, {
            'community-dentistry', 'dental-materials', 'oral-pathology',
        })
        sturdevant = next(r for r in official if r['edition'] == 'sturdevant-operative@7e')
        self.assertIn('صفحات 1', sturdevant['scope_chapters'][0]['partial'])
        emergencies = next(r for r in official if r['edition'] == 'malamed-medical-emergencies@7e')
        self.assertEqual([c['number'] for c in emergencies['scope_chapters']],
                         ['1', '2', '3', *[str(n) for n in range(5, 14)], '30', '31'])


if __name__ == '__main__':
    unittest.main()
