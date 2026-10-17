from pathlib import Path
import re
import shutil
import subprocess
import unittest


ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_ai_source_pages.php"


class VerifiedAiSourcePageRepairContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = OPERATOR.read_text(encoding="utf-8")

    def test_only_existing_ai_source_page_and_confidence_fields_are_changed(self):
        mutations = re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)", self.source)
        self.assertEqual(mutations, ["bank_question_sources"])
        self.assertRegex(
            self.source,
            r"SET pdf_page=:page,pdf_sha256=:sha,confidence_page=:confidence",
        )
        self.assertNotRegex(self.source, r"UPDATE\s+bank_questions|UPDATE\s+bank_official_answers")
        self.assertIn("$db->rollBack();", self.source)
        self.assertIn("$db->commit();", self.source)
        self.assertIn("FOR UPDATE", self.source)

    def test_confidence_and_audit_are_fail_closed(self):
        for guard in (
            "SourceChapterCorrectionGuard::auditMatches",
            "$confidence < 0.85",
            "$confidence > 0.999",
            "(float) $confidence < (float) $s['confidence_page']",
            "confidence_page=:old_confidence",
            "expected_confidence_page",
            "audit_sha256",
        ):
            self.assertIn(guard, self.source)

    def test_source_identity_scope_original_page_and_content_are_pinned(self):
        for guard in (
            "v.is_official",
            "OfficialScopePageGuard::pageDisposition",
            "scope_chapters",
            "source_pdf_sha256",
            "PrintedBookPageEvidence::corroborates(",
            "PrintedBookPageEvidence::pageContainsEvidence(",
            "expected_anchor_sha256",
            "expected_stem_sha256",
            "expected_choices_sha256",
            "expected_answer_sha256",
            "reviewed_by_user_id",
            "reviewed_at",
            "s['origin'] !== 'ai'",
            "s['question_status'] !== 'published'",
            "['final','amended']",
        ):
            self.assertIn(guard, self.source)
        self.assertIn("$fullChapter", self.source)

    def test_fresh_backup_and_unique_private_receipt_are_required_for_apply(self):
        for guard in (
            "BackupManifest::verify",
            "4 * 3600",
            "database.sql",
            "posix_geteuid()",
            "fanoosupd",
            "file_exists($receipt)",
            "realpath(dirname($receipt)) !== $reports",
            "realpath(dirname($planPath)) !== $reports",
            "hash('sha256', $raw)",
        ):
            self.assertIn(guard, self.source)

    @unittest.skipUnless(shutil.which("php"), "PHP required")
    def test_php_syntax(self):
        result = subprocess.run(["php", "-l", str(OPERATOR)], text=True, capture_output=True, check=False)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)


if __name__ == "__main__":
    unittest.main()
