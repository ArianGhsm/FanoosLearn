"""Safety regression tests for old-source PDF page binding repairs.

Actual original-PDF comparison and production transaction are mandatory at
operator runtime; these tests protect the source-only contract in CI.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_bound_pdf_pages.php"


class ExistingSourcePageRepairContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.code = OPERATOR.read_text(encoding="utf-8")

    def test_only_existing_source_page_identity_columns_mutate(self):
        sql = self.code.split("UPDATE bank_question_sources SET ", 1)[1].split("SQL);", 1)[0]
        self.assertIn("pdf_page=:page,pdf_sha256=:sha", sql)
        self.assertNotIn("node_id=", sql)
        self.assertNotIn("edition_id=", sql)
        self.assertNotIn("page=:", sql)
        self.assertNotIn("answer", sql.lower())
        self.assertNotIn("question", sql.lower())
        self.assertIn("reviewed_at IS NULL", sql)
        self.assertIn("pdf_page IS NULL AND pdf_sha256 IS NULL", sql)

    def test_live_version_and_scope_are_locked_and_matched(self):
        for guard in (
            "WHERE s.id=:source_id FOR UPDATE",
            "v.exam_year=1398 AND v.is_official=1",
            "et.type_key='residency'",
            "$source['kind'] !== 'chapter'",
            "$source['origin'] !== 'ai'",
            "$source['reviewed_at'] !== null",
            "$source['page'] !== ($row['prior_printed_page'] ?? null)",
            "count($scopes) !== 1",
            "array_column($allowed, 'number')",
        ):
            self.assertIn(guard, self.code)

    def test_original_pdf_and_edition_bound_chapter(self):
        for guard in (
            "--expected-sha256",
            "--require-map",
            "verified_reference_pdf.py",
            "repairVerifyPdfPage($edition, $page, $sha, $excerpt)",
            "map['source_pdf_sha256']",
            "run[1] <= $page && $page <= $run[2]",
            "str_contains(repairNormalize($pageText), repairNormalize($excerpt))",
        ):
            self.assertIn(guard, self.code)

    def test_backup_receipt_transaction_and_actor(self):
        for guard in (
            "BackupManifest::verify($backup)",
            "abs(time() - $timestamp) > 4 * 3600",
            "'fanoosupd'",
            "$db->beginTransaction()",
            "$db->rollBack()",
            "$db->commit()",
            "fopen($receipt, 'x')",
            "strlen($json)",
        ):
            self.assertIn(guard, self.code)


if __name__ == "__main__":
    unittest.main()
