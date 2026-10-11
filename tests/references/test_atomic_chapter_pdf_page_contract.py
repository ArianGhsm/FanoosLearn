"""Keep chapter assignment inseparable from a bound original PDF page."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class AtomicChapterPdfPageContract(unittest.TestCase):
    def read(self, path):
        return (ROOT / path).read_text(encoding="utf-8")

    def test_all_source_writers_require_bound_pdf_page(self):
        importer = self.read("apps/platform/src/Bank/BankImporter.php")
        publisher = self.read("apps/platform/src/Bank/SourceOnlyPublisher.php")
        classifier = self.read("scripts/references/apply_classification.py")
        self.assertIn("a chapter requires pdf_page and pdf_sha256", importer)
        self.assertIn("a new chapter source requires pdf_page", importer)
        self.assertIn("sourcePdfPage !== $pdfPage", publisher)
        self.assertIn("pdf_page,printed_page,pdf_sha256", publisher)
        self.assertIn("a chapter decision requires the exact positive PDF page number", classifier)
        self.assertIn("nearest-edition page cannot verify the cited official edition", classifier)

    def test_browsing_and_publication_do_not_call_old_chapter_only_rows_completed(self):
        facts = self.read("apps/platform/src/Bank/BankQuestionFacts.php")
        browsing = self.read("apps/platform/src/Bank/BankBrowseService.php")
        publishing = self.read("apps/platform/src/Bank/BankPublisher.php")
        for source in [facts, browsing, publishing]:
            self.assertIn("link.pdf_page IS NOT NULL AND link.pdf_sha256 IS NOT NULL", source)
        self.assertIn("THEN link.node_id ELSE NULL END AS node_id", browsing)

    def test_example_sources_use_same_row_for_page_and_hash(self):
        import json
        sitting = json.loads(self.read("contracts/bank/examples/sitting.example.json"))
        for question in sitting["questions"]:
            for source in question.get("sources", []):
                if "#" in source["ref"]:
                    self.assertIsInstance(source.get("pdf_page"), int)
                    self.assertGreater(source["pdf_page"], 0)
                    self.assertEqual(len(source.get("pdf_sha256", "")), 64)


if __name__ == "__main__":
    unittest.main()
