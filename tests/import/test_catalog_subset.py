"""scripts/import/catalog_subset.py keeps only what a catalog adds, and refuses a change it cannot carry."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts' / 'import'))
from catalog_subset import subset  # noqa: E402

BASE = {
    'format': 'fanoos.bank.catalog/1',
    'exam_types': [{'key': 'residency', 'name': 'دستیاری'}],
    'subjects': [{'key': 'endodontics', 'name': 'اندودانتیکس'}],
    'references': [{'key': 'torabinejad', 'title': 'Endodontics', 'subject': 'endodontics',
                    'editions': [{'key': '6e', 'label': '6th edition', 'nodes': [{'key': 'ch01'}]}]}],
    'validity': [{'exam_type': 'residency', 'year': 1405, 'subject': 'endodontics', 'edition': 'torabinejad@6e'}],
}


class CatalogSubsetTest(unittest.TestCase):
    def test_only_new_editions_and_rows(self):
        new = {**BASE,
               'exam_types': BASE['exam_types'] + [{'key': 'board', 'name': 'بورد'}],
               'references': [{**BASE['references'][0], 'editions': BASE['references'][0]['editions'] + [{'key': '7e', 'label': '7th edition'}]},
                              {'key': 'ingle', 'title': "Ingle's Endodontics", 'subject': 'endodontics', 'editions': [{'key': '7e', 'label': '7th edition'}]}],
               'validity': BASE['validity'] + [{'exam_type': 'board', 'year': 1405, 'subject': 'endodontics', 'edition': 'ingle@7e'}]}
        part = subset(BASE, new)
        self.assertEqual([(r['key'], [e['key'] for e in r['editions']]) for r in part['references']], [('torabinejad', ['7e']), ('ingle', ['7e'])])
        self.assertEqual(part['validity'], [{'exam_type': 'board', 'year': 1405, 'subject': 'endodontics', 'edition': 'ingle@7e'}])
        self.assertEqual(len(part['exam_types']), 2)

    def test_a_changed_row_needs_the_whole_catalog(self):
        new = {**BASE, 'validity': [{**BASE['validity'][0], 'scope': 'تمام فصول'}]}
        with self.assertRaises(SystemExit):
            subset(BASE, new)


if __name__ == '__main__':
    unittest.main()
