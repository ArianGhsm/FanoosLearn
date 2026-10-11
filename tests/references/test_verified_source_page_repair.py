"""Contract tests for controlled repairs of legacy chaptered sources.

The actual page and official scope are verified against the approved production
PDF in the rollback preview, not by these source-structure tests.
"""
from pathlib import Path
import re
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_source_pages.php"


class ExistingSourcePageRepairContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = OPERATOR.read_text(encoding="utf-8")

    def test_only_mutable_fields_are_pdf_page_and_pdf_sha(self):
        mutations = re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)", self.source)
        self.assertEqual(mutations, ["bank_question_sources"])
        self.assertRegex(self.source, r"UPDATE bank_question_sources SET pdf_page=:page,pdf_sha256=:sha")
        self.assertNotRegex(self.source, r"UPDATE\s+bank_questions|UPDATE\s+bank_official_answers")
        self.assertIn("$db->rollBack();", self.source)
        self.assertIn("$db->commit();", self.source)
        self.assertIn("FOR UPDATE", self.source)

    def test_official_and_exact_original_pdf_guards(self):
        for guard in (
            "v.is_official", "scope_chapters", "empty($entry['partial'])",
            "source_pdf_sha256", "PrintedBookPageEvidence::corroborates(",
            "PrintedBookPageEvidence::pageContainsEvidence(", "expected_anchor_sha256",
            "reviewed_by_user_id", "reviewed_at", "s['origin'] !== 'ai'",
            "s['question_status'] !== 'published'", "is_official'] !== 1",
            "['final','amended']", "choice_position",
        ):
            self.assertIn(guard, self.source)

    def test_explicit_backup_idempotency_and_private_receipt(self):
        for guard in (
            "BackupManifest::verify", "4 * 3600", "database.sql",
            "posix_geteuid()", "fanoosupd", "file_exists($receipt)",
            "realpath(dirname($receipt)) !== $reports",
            "realpath(dirname($planPath)) !== $reports",
            "is_link($planPath)", "hash('sha256', $raw)",
            "pdf_page IS NULL AND pdf_sha256 IS NULL",
        ):
            self.assertIn(guard, self.source)

    @unittest.skipUnless(shutil.which("php"), "PHP required")
    def test_php_syntax(self):
        result = subprocess.run(["php", "-l", str(OPERATOR)], text=True,
                                capture_output=True, check=False)
        self.assertEqual(result.returncode, 0, result.stderr)


if __name__ == "__main__":
    unittest.main()
