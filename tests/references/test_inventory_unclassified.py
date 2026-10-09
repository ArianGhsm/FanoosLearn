"""Synthetic fixtures only. No real exam text or answers in the repository."""
import importlib.util
import pathlib
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[2] / 'scripts' / 'references' / 'inventory_unclassified.py'
spec = importlib.util.spec_from_file_location('inventory_unclassified', SCRIPT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class InventoryTests(unittest.TestCase):
    def setUp(self):
        self.catalog = {'validity': [{'exam_type': 'residency', 'year': 1405,
                                     'subject': 'endodontics', 'edition': 'torabinejad-endodontics@6e'}]}
        self.sitting = {'exam_type': 'residency', 'year': 1405, 'round': 1, 'questions': [
            {'number': 7, 'subject': 'endodontics', 'sources': [{'origin': 'human', 'ref': 'r#ch1'}]},
            {'number': 8, 'subject': 'endodontics', 'sources': [{'origin': 'ai', 'ref': 'r#ch2'}]},
            {'number': 9, 'subject': 'endodontics'},
            {'number': 10, 'subject': 'oral-radiology', 'sources': []},
        ]}

    def test_splits_sources_and_does_not_infer_none(self):
        r = module.build_inventory(self.sitting, self.catalog)
        self.assertEqual(r['totals']['unclassified'], 2)
        self.assertEqual(r['totals']['classified_human'], 1)
        self.assertEqual(r['totals']['classified_ai'], 1)
        self.assertEqual(r['by_subject']['endodontics']['question_keys'], ['residency-1405-1-009'])
        self.assertEqual(r['by_subject']['oral-radiology']['needs_official_reference_review'], 1)
        self.assertEqual(r['by_subject']['oral-radiology']['question_keys'], ['residency-1405-1-010'])

    def test_duplicate_number_rejected(self):
        self.sitting['questions'].append({'number': 7, 'subject': 'endodontics'})
        with self.assertRaisesRegex(ValueError, 'duplicate'):
            module.build_inventory(self.sitting, self.catalog)

    def test_is_read_only(self):
        import copy
        before = copy.deepcopy(self.sitting)
        module.build_inventory(self.sitting, self.catalog)
        self.assertEqual(self.sitting, before)


if __name__ == '__main__':
    unittest.main()
