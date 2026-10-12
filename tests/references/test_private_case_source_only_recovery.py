"""Guard contracts for isolated same-year source-only recovery packages."""
import re
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
CLI = ROOT / "scripts/references/import_verified_sources.php"
PUBLISHER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"


class ProtectedCaseRootContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.cli = CLI.read_text(encoding="utf8")
        cls.publisher = PUBLISHER.read_text(encoding="utf8")

    def test_only_direct_recovery_case_and_exact_year(self):
        for marker in (
            "isset($args['case-root'])",
            "isset($args['package-root']) && isset($args['case-root'])",
            "dirname($case) !== $privateRoot",
            "$requested !== $case",
            "$year < 1399",
            "is_link($requested)",
            "realpath($case . '/classification/reports')",
            "$receiptDir = $auditDir",
        ):
            self.assertIn(marker, self.cli)

    def test_private_digest_and_all_original_study_inputs_are_pinned(self):
        for marker in (
            "hash_equals($expectedAuditSha, hash_file('sha256', $auditPath))",
            "hash_file('sha256', $studyPath)",
            "hash_file('sha256', $validatedPath)",
            "hash_file('sha256', $decisionsPath)",
            "question_content_identical",
            "BackupManifest::verify",
            "source_rows",
            "questions_changed",
            "choices_changed",
            "answers_changed",
        ):
            self.assertIn(marker, self.cli)

    def test_only_missing_source_rows_are_inserted(self):
        for marker in (
            "SELECT id FROM bank_question_sources",
            "Source already exists",
            "FOR UPDATE",
            "q.status",
            "Published choices no longer equal reviewed choices",
            "Published answer differs from reviewed study",
            "Year-specific official syllabus scope",
            "PrintedBookPageEvidence::corroborates(",
            "$db->rollBack();",
        ):
            self.assertIn(marker, self.publisher)
        for table in ("bank_questions", "bank_question_choices", "bank_official_answers", "bank_exam_assessments"):
            self.assertNotRegex(self.publisher, rf"\\bUPDATE\\s+{table}\\b")
            self.assertNotRegex(self.publisher, rf"\\bDELETE\\s+FROM\\s+{table}\\b")


if __name__ == "__main__":
    unittest.main()
