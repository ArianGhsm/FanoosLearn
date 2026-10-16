from pathlib import Path
import re
import unittest


ROOT = Path(__file__).resolve().parents[2]


class OutsideScopeSourceRepairContract(unittest.TestCase):
    def test_operator_only_updates_source_metadata(self):
        source = (ROOT / "scripts/references/mark_verified_outside_scope_sources.php").read_text()
        updates = re.findall(r"UPDATE\s+([a-z_]+)\s+SET\s+(.+?)\s+WHERE", source, re.I | re.S)
        self.assertEqual([table.lower() for table, _ in updates], ["bank_question_sources"])
        changed = updates[0][1]
        for field in ["page", "printed_page", "pdf_page", "pdf_sha256", "official_scope_status", "official_scope_note"]:
            self.assertRegex(changed, rf"\b{field}\s*=")
        self.assertNotRegex(source, r"\b(?:DELETE|TRUNCATE)\b")

    def test_gate_requires_an_explicit_note_for_an_exception(self):
        gate = (ROOT / "apps/platform/src/Bank/ResidencyYearPublicationGate.php").read_text()
        self.assertIn("outside_scope_exam_question", gate)
        self.assertIn("mb_strlen(trim($source['official_scope_note'])) < 12", gate)

    def test_unprinted_page_path_keeps_printed_label_null_and_records_pdf_page(self):
        source = (ROOT / "scripts/references/mark_verified_outside_scope_sources.php").read_text()
        evidence = (ROOT / "apps/platform/src/Bank/PrintedBookPageEvidence.php").read_text()
        self.assertIn("printed_page_label_unavailable", source)
        self.assertIn("hasNoPrintedPageLabel", source)
        self.assertIn("array_key_exists('printed_page', $item) && $item['printed_page'] === null", source)
        self.assertIn("'pdf ' . $item['pdf_page']", source)
        self.assertRegex(source, r"printed_page=[:][a-z_]+")
        self.assertIn("public static function hasNoPrintedPageLabel", evidence)
        self.assertIn("return $labels === [];", evidence)


if __name__ == "__main__":
    unittest.main()
