"""Safety contracts for exact-year source-only private recovery packages.

The runtime checks additionally compare original published questions, choices,
latest official answers and approved reference scope in SourceOnlyPublisher.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
CLI = ROOT / "scripts/references/import_verified_sources.php"
WRITER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"


class PrivateRecoverySourceOnlyContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.cli = CLI.read_text(encoding="utf-8")
        cls.writer = WRITER.read_text(encoding="utf-8")

    def test_private_case_is_not_an_arbitrary_dir_or_coordinator_package(self):
        for snippet in (
            "isset($args['case-root'])",
            "isset($args['package-root']) && isset($args['case-root'])",
            "realpath($root . '/classification')",
            "dirname($case) !== $privateRoot",
            "$requested !== $case",
            "is_link($requested)",
            "if ($year === false || $year < 1399",
            "recovery-' . $year . '-[a-z0-9-]+-20[0-9]{6}",
            "$inputRoot = $case",
            "$auditDir = realpath($case . '/classification/reports')",
            "$receiptDir = $auditDir",
        ):
            self.assertIn(snippet, self.cli)

    def test_case_audit_digest_and_original_data_are_immutable(self):
        for snippet in (
            "$expectedAuditSha",
            "hash_equals($expectedAuditSha, hash_file('sha256', $auditPath))",
            "hash_file('sha256', $studyPath)",
            "hash_file('sha256', $validatedPath)",
            "hash_file('sha256', $decisionsPath)",
            "($b['question_content_identical'] ?? false) !== true",
            "realpath(dirname($auditPath)) !== $auditDir",
            "realpath(dirname($receipt)) !== $receiptDir",
            "BackupManifest::verify",
            "posix_geteuid()",
            "SourceOnlyPublisher::run(",
        ):
            self.assertIn(snippet, self.cli)

    def test_writer_remains_strictly_source_only_and_rejects_existing_source(self):
        for snippet in (
            "A stem, choice or answer changed in the study",
            "Published choices no longer equal reviewed choices",
            "Published answer differs from reviewed study",
            "Source already exists; reviewed sources are immutable",
            "Year-specific official syllabus scope is missing this chapter",
            "PrintedBookPageEvidence::corroborates(",
            "v.is_official=1",
            "$db->rollBack();",
        ):
            self.assertIn(snippet, self.writer)
        self.assertIsNotNone(re.search(r"INSERT\s+INTO\s+bank_question_sources", self.writer))
        self.assertIsNone(re.search(
            r"(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:bank_questions|bank_question_choices|bank_official_answers)\b",
            self.writer, re.I,
        ))


if __name__ == "__main__":
    unittest.main()
