from pathlib import Path
import re
import unittest


ROOT = Path(__file__).resolve().parents[2]


class AiOutsideScopeSourceInsertContract(unittest.TestCase):
    def test_operator_only_inserts_new_source_rows(self):
        source = (ROOT / "scripts/references/insert_verified_ai_outside_scope_sources.php").read_text()
        inserts = re.findall(r"INSERT\s+INTO\s+([a-z_]+)", source, re.I)
        self.assertEqual(inserts, ["bank_question_sources"])
        self.assertNotRegex(source, r"\bUPDATE\s+bank_question_sources\s+SET\b")
        self.assertNotRegex(source, r"\b(?:DELETE|TRUNCATE)\s+(?:FROM\s+)?bank_question_sources\b")
        self.assertIn("outside_scope_exam_question", source)
        self.assertIn("questions_changed'=>0", source)
        self.assertIn("assessment_versions_changed'=>0", source)
        self.assertIn("attempts_changed'=>0", source)

    def test_operator_binds_audit_immutable_fields_pdf_scope_backup_and_receipt(self):
        source = (ROOT / "scripts/references/insert_verified_ai_outside_scope_sources.php").read_text()
        for evidence in [
            "VerifiedAiOutsideScopeSourceInsertGuard::auditMatches",
            "chapterIsEntirelyOutsideScope",
            "PrintedBookPageEvidence::pageContainsEvidence",
            "PrintedBookPageEvidence::corroborates",
            "PrintedBookPageEvidence::hasNoPrintedPageLabel",
            "expected_stem_sha256",
            "expected_choices_sha256",
            "expected_answer_sha256",
            "BackupManifest::verify",
            "@fopen((string)$args[",
        ]:
            self.assertIn(evidence, source)

    def test_normal_source_insert_remains_a_distinct_path(self):
        writer = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        self.assertIn("in_array($src['chapter'], $numbers, true)", writer)
        self.assertIn("outside_scope_exam_question", (ROOT / "scripts/references/insert_verified_ai_outside_scope_sources.php").read_text())


if __name__ == "__main__":
    unittest.main()
