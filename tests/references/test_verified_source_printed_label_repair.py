"""Contract tests for printed-label-only repairs on bound AI source pages."""
from pathlib import Path
import re
import unittest


ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_source_printed_labels.php"


class VerifiedSourcePrintedLabelRepairContract(unittest.TestCase):
    def test_only_the_missing_printed_label_is_written(self):
        source = OPERATOR.read_text()
        updates = re.findall(r"UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE", source, re.I | re.S)
        self.assertEqual(len(updates), 1)
        fields = re.findall(r"([a-z0-9_]+)\s*=", updates[0], re.I)
        self.assertEqual(fields, ["printed_page"])
        self.assertIn("printed_page IS NULL", source)
        self.assertIn("pdf_page=:pdf_page AND pdf_sha256=:pdf_sha", source)
        self.assertIn("origin='ai'", source)
        self.assertNotIn("UPDATE bank_questions", source)
        self.assertNotIn("UPDATE bank_official_answers", source)

    def test_scope_label_backup_and_receipt_guards_are_present(self):
        source = OPERATOR.read_text()
        for marker in [
            "OfficialScopePageGuard::pageDisposition", "PrintedBookPageEvidence::corroborates",
            "VerifiedSourcePrintedLabelGuard::auditMatches", "expected_stem_sha256",
            "expected_choices_sha256", "expected_answer_sha256", "expected_anchor_sha256",
            "BackupManifest::verify", "4 * 3600", "database.sql", "$db->rollBack();",
            "$db->commit();",
        ]:
            self.assertIn(marker, source)
        self.assertIn("attempts_changed", source)
        self.assertIn("assessment_versions_changed", source)


if __name__ == "__main__":
    unittest.main()
