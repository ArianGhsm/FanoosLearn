from pathlib import Path
import re
import shutil
import subprocess
import unittest


ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_source_chapter_pages.php"


class VerifiedAiSourceChapterPageCorrectionContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = OPERATOR.read_text(encoding="utf-8")

    def test_only_source_location_and_confidence_fields_are_mutated(self):
        mutations = re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)", self.source)
        self.assertEqual(mutations, ["bank_question_sources"])
        self.assertRegex(
            self.source,
            r"SET node_id=:new_node,printed_page=:printed,pdf_page=:page,pdf_sha256=:sha,\s*confidence_source=:confidence_source,confidence_node=:confidence_node,confidence_page=:confidence_page",
        )
        self.assertNotRegex(self.source, r"UPDATE\s+bank_questions|UPDATE\s+bank_official_answers")
        self.assertIn("FOR UPDATE", self.source)
        self.assertIn("$db->rollBack();", self.source)
        self.assertIn("$db->commit();", self.source)

    def test_year_scope_audit_and_confidence_vector_are_fail_closed(self):
        for guard in (
            "fanoos.verified-source-chapter-page-correction/2",
            "$plan['year'] < 1396",
            "$plan['year'] > 1500",
            "recovery-'",
            "SourceChapterCorrectionGuard::auditMatches",
            "confidence_source'] ?? null",
            "confidence_node'] ?? null",
            "confidence_page'] ?? null",
            "$confidence < 0.85",
            "$confidence > 1.0",
            "(float) $confidence < (float) $s['confidence_source']",
            "(float) $confidence < (float) $s['confidence_node']",
            "(float) $confidence < (float) $s['confidence_page']",
            "expected_confidence_source",
            "expected_confidence_node",
            "expected_confidence_page",
        ):
            self.assertIn(guard, self.source)

    def test_original_page_scope_content_key_and_backup_are_checked(self):
        for guard in (
            "OfficialScopePageGuard::pageDisposition",
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
            "BackupManifest::verify",
            "4 * 3600",
            "database.sql",
            "file_exists($receipt)",
        ):
            self.assertIn(guard, self.source)
        self.assertNotIn("($plan['year'] ?? null) !== 1405", self.source)

    def test_multi_fragment_evidence_is_page_checked_fragment_by_fragment(self):
        guard = (ROOT / "apps/platform/src/Bank/SourceChapterCorrectionGuard.php").read_text(encoding="utf-8")
        self.assertIn("public static function evidenceFragments(mixed $evidence): ?array", guard)
        self.assertIn("count($words) < 4", guard)
        self.assertIn("SourceChapterCorrectionGuard::evidenceFragments($quote)", self.source)
        self.assertIn("PrintedBookPageEvidence::pageContainsEvidence($edition, $page, $evidenceFragments, $sha)", self.source)

    @unittest.skipUnless(shutil.which("php"), "PHP required")
    def test_php_syntax(self):
        result = subprocess.run(["php", "-l", str(OPERATOR)], text=True, capture_output=True, check=False)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)


if __name__ == "__main__":
    unittest.main()
