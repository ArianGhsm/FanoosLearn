"""How the question-corpus builder (scripts/import/corpus_to_bank.py) reads a
reviewed chapter and reference against the catalog."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts' / 'import'))
from corpus_to_bank import answer, catalog_chapter, chapter, reference_for  # noqa: E402

CATALOG = {'references': [
    {'key': 'van-noort-materials', 'editions': [{'key': '5e', 'year': 2024, 'nodes': [
        {'key': 'ch03.5', 'kind': 'chapter', 'number': '3.5', 'title': 'Contemporary Dental Ceramics'},
        {'key': 'ch03.5.s01', 'kind': 'section', 'number': None, 'title': 'Introduction'}]}]},
    {'key': 'craig-restorative-materials', 'editions': [{'key': '14e', 'year': 2019, 'nodes': [
        {'key': 'ch10', 'kind': 'chapter', 'number': '10', 'title': 'Restorative Materials: Metals'}]}]},
]}


class CorpusToBankTest(unittest.TestCase):
    def test_chapter_numbers_whole_and_dotted(self):
        self.assertEqual(chapter('Chapter 10. Restorative Materials: Metals'), ('10', 'Restorative Materials: Metals'))
        self.assertEqual(chapter('Chapter 3.5. Contemporary Dental Ceramics'), ('3.5', 'Contemporary Dental Ceramics'))
        self.assertEqual(chapter('Chapter 3.5 Contemporary Dental Ceramics'), ('3.5', 'Contemporary Dental Ceramics'))
        self.assertIsNone(chapter('Chapter 3; Chapter 4'))
        self.assertIsNone(chapter(''))

    def test_catalog_chapter_is_the_cited_editions_node(self):
        self.assertEqual(catalog_chapter(CATALOG, ('van-noort-materials', '5e'), '3.5')['key'], 'ch03.5')
        self.assertIsNone(catalog_chapter(CATALOG, ('van-noort-materials', '5e'), '3.6'))
        self.assertIsNone(catalog_chapter(CATALOG, ('van-noort-materials', '4e'), '3.5'))

    def test_reviewed_reference_may_name_the_catalog_edition(self):
        self.assertEqual(reference_for('craig-restorative-materials@14e', CATALOG), ('craig-restorative-materials', '14e'))
        self.assertIsNone(reference_for('craig-restorative-materials@13e', CATALOG))
        self.assertIsNone(reference_for('unknown-book@1e', CATALOG))

    def test_a_key_that_accepts_several_options_keeps_them_all(self):
        both = answer({'کلید نهایی رسمی': 'A,B', 'کلید اولیه رسمی': 'A'})
        self.assertEqual((both['choice'], both['also_correct'], both['status']), (1, [2], 'amended'))
        single = answer({'کلید نهایی رسمی': 'C', 'کلید اولیه رسمی': 'C'})
        self.assertEqual((single['choice'], single['status']), (3, 'final'))
        self.assertNotIn('also_correct', single)
        self.assertEqual(answer({'کلید نهایی رسمی': 'حذف'})['status'], 'voided')


if __name__ == '__main__':
    unittest.main()
