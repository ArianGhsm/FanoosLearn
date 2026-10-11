"""Static safety contract for existing-source PDF-page-only repairs.

Runtime positive and rejection paths are additionally exercised by the
rollback-only production preview and a private exact-PDF provenance audit.
"""
from pathlib import Path
import re
import unittest

REPAIR = Path(__file__).resolve().parents[2] / "scripts/references/repair_existing_source_pdf_page.php"


class ExistingSourcePdfPageRepairContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = REPAIR.read_text(encoding="utf-8")

    def test_only_existing_source_pdf_columns_are_mutated(self):
        updates = re.findall(r"\bUPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE\s+", self.source, re.I | re.S)
        self.assertEqual(len(updates), 1)
        self.assertEqual(re.sub(r"\s+", " ", updates[0]).strip(), "pdf_page=:page, pdf_sha256=:sha")
        self.assertNotRegex(self.source, r"\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE)\s+(?:bank_questions|bank_official_answers|bank_question_choices|bank_exam_assessments)\b")
        self.assertNotRegex(self.source, r"\b(?:DELETE\s+FROM|TRUNCATE\s+TABLE)\s+bank_question_sources\b")

    def test_printed_pdf_offset_needs_explicit_and_independent_proof(self):
        self.assertIn("isset($args['printed-offset'])", self.source)
        self.assertIn("if ($printedOffset)", self.source)
        self.assertIn("PrintedBookPageEvidence::corroborates(", self.source)
        self.assertIn("PrintedBookPageEvidence::pageContainsEvidence(", self.source)
        self.assertIn("[(string) ($live['anchor_text'] ?? '')]", self.source)
        self.assertIn("abs($pdfPage - (int) $printedLabel) > 80", self.source)
        self.assertIn("(string) $live['page'] !== $printedLabel", self.source)
        self.assertIn("(string) $live['printed_page'] !== (string) $pdfPage", self.source)
        self.assertIn("source_pdf_provenance", self.source)
        self.assertIn("reviewed_by_user_id", self.source)

    def test_repair_fails_closed_and_dry_runs_by_default(self):
        for guard in (
            "BackupManifest::verify",
            "posix_geteuid()",
            "beginTransaction()",
            "rollBack()",
            "rowCount() !== 1",
            "source_pdf_provenance",
            "question_content_identical",
            "hash_file('sha256', $auditPath)",
            "reviewed_at",
            "scope_chapters",
            "approved_sha",
            "official",
            "pdf_page IS NULL AND pdf_sha256 IS NULL",
            "questions_updated",
        ):
            if guard == "questions_updated":
                self.assertIn("'question_rows_updated'=>0", self.source)
            else:
                self.assertIn(guard, self.source)


if __name__ == "__main__":
    unittest.main()
