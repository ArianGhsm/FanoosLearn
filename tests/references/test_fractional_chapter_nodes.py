"""Fractional Van Noort sections map to their exact catalog node keys."""
import json
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts/references"))
from apply_classification import chapter_nodes
from verified_reference_pdf import chapter_coverage


class FractionalChapterNodes(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.catalog = json.loads((ROOT / "data/bank/catalog.json").read_text())
        cls.maps = json.loads((ROOT / "data/bank/reference-chapter-pages.json").read_text())
        cls.publisher = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        cls.auditor = (ROOT / "scripts/references/audit_private_study_batches.py").read_text()

    def test_decimal_sections_have_exact_node_keys_and_bound_pdf_map(self):
        edition = "van-noort-materials@4e"
        nodes = chapter_nodes(self.catalog, edition)
        self.assertEqual(nodes["1.6"]["key"], "ch01.6")
        self.assertEqual(nodes["2.7"]["key"], "ch02.7")
        self.assertEqual(nodes["3.8"]["key"], "ch03.8")
        entry = self.maps["editions"][edition]
        self.assertEqual(entry["source_pdf_sha256"], "7e993e5c50979c3287b755e4ade2a1f42c27e444acd1ca4ac9cc9e791d9f203a")
        pages = entry["runs"][-1][2]
        self.assertEqual(chapter_coverage({"editions": {edition: entry}}, edition, pages)["mapped_pages"], pages)

    def test_source_writer_and_auditor_resolve_decimal_node_identity(self):
        self.assertIn("^([1-9][0-9]?)(?:\\.([1-9]))?$", self.publisher)
        self.assertIn("str_pad($chapterMatch[1], 2, '0', STR_PAD_LEFT)", self.publisher)
        self.assertIn("chapter_nodes(catalog, str(decision[\"edition\"]))", self.auditor)
        self.assertIn("expected_node.get(\"key\"", self.auditor)
        self.assertNotIn("str(decision[\"chapter\"]).zfill(2)", self.auditor)


if __name__ == "__main__":
    unittest.main()
