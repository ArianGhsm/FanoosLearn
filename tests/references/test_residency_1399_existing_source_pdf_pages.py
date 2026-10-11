"""Safety contract for 1399 exact original PDF existing AI source-page repairs.

The operator is deliberately fixed to eight cases and cannot write a question,
key, choice, history, attempt or assessment. Live page/backup checks happen
on the server, never in this CI contract test.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_residency_1399_verified_pdf_pages.php"


class Residency1399ExactExistingSourceRepairContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = OPERATOR.read_text(encoding="utf-8")

    def test_fixed_year_editions_questions_and_hashes(self):
        self.assertIn("si.exam_year=1399", self.text)
        self.assertIn("si.exam_round=1", self.text)
        for n in (25, 30, 32, 115, 118, 122, 123, 125):
            self.assertRegex(self.text, rf"(?m)^\s*{n}\s*=>")
        self.assertIn("torabinejad-endodontics@5e", self.text)
        self.assertIn("carranza-periodontology@13e", self.text)
        self.assertIn("PDF_TORABINEJAD_1399", self.text)
        self.assertIn("PDF_CARRANZA_1399", self.text)
        self.assertNotIn("nearest", self.text.lower())

    def test_atomic_book_page_is_independently_verified(self):
        for token in (
            "PrintedBookPageEvidence::pageContainsEvidence",
            "PrintedBookPageEvidence::corroborates",
            "scope_chapters", "is_official", "node_key",
            "pdf_page", "pdf_sha256", "printed_page",
            "source_pdf_sha256", "new_pdf_page", "new_printed_page"
        ):
            self.assertIn(token, self.text)

    def test_only_source_metadata_may_be_updated(self):
        sql_updates = re.findall(
            r"\bUPDATE\s+([a-z_]+)\s+SET\s+(.+?)\s+WHERE\b",
            self.text, flags=re.I | re.S,
        )
        self.assertEqual(len(sql_updates), 1)
        table, assignments = sql_updates[0]
        self.assertEqual(table.lower(), "bank_question_sources")
        keys = re.findall(r"(?:^|,)\s*([a-z_]+)\s*=", assignments, flags=re.I)
        self.assertEqual(
            set(map(str.lower, keys)),
            {"page", "printed_page", "pdf_page", "pdf_sha256"},
        )
        self.assertNotRegex(self.text, r"\b(?:DELETE\s+FROM|INSERT\s+INTO)\s+bank_")

    def test_human_integrity_and_fresh_backup_gates(self):
        for token in (
            "rowsFingerprint1399",
            "bank_question_choices", "bank_official_answers",
            "GET_LOCK(", "FOR UPDATE", "origin='ai'",
            "reviewed_at IS NULL", "reviewed_by_user_id IS NULL",
            "BackupManifest::verify", "plan-sha",
            "abs(time() - $created) > 14400",
            "Source metadata compare-and-swap failed",
            "fingerprint", "plan_sha256", "receipt", "readback",
        ):
            self.assertIn(token, self.text)

    def test_legacy_bad_page_is_fixed_only_for_q25(self):
        self.assertRegex(
            self.text,
            r"25\s*=>.*?'old_printed'\s*=>\s*'7'.*?'printed'\s*=>\s*'51'",
        )
        self.assertIn("'new_printed' => $row['new_printed_page']", self.text)


if __name__ == "__main__":
    unittest.main()
