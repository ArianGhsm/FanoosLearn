"""Safety contract for exactly twelve official-original Neville fifth-edition page placeholders."""
import pathlib
import re
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[2] / "scripts/references/correct_existing_neville5_pathology_placeholders.php"

class Neville5PageRepairContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.src = SCRIPT.read_text(encoding="utf-8")

    def test_only_source_page_may_change(self):
        sql = re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b", self.src, re.I)
        self.assertEqual(sql, ["bank_question_sources"])
        for text in ["SET page=:page", "page=:old_page", "origin='ai'", "reviewed_at IS NULL",
                     "reviewed_by_user_id IS NULL", "rowCount()!==1"]:
            self.assertIn(text, self.src)

    def test_immutable_original_inputs_are_sha_pinned(self):
        for sha in ["0bd1e9d6df79057c5b95b0fa225ca3d99a7ee014a42f2f10f9d7c9a28012590e",
                    "9ea8a2671673385c833ad1e0242dd6ee742f3760020f8e81cb4f5fd3cddd9db8",
                    "810c3d9d7cef512f295b919cf676fa9b7cacbe618321e8a361084dda6b66ffa0",
                    "348dafa50b9f53648dc5f7cad97547459ba70a227680ab921c5a04b1b63b6c40"]:
            self.assertIn(sha, self.src)
        self.assertIn("count($manifest['cases']??[])!==12", self.src)
        self.assertIn("count($cases)!==12", self.src)
        self.assertIn("count($sources)!==159", self.src)
        self.assertIn("count($answers)!==159", self.src)

    def test_original_pdf_answers_and_exam_scope_checked(self):
        for guard in ["PrintedBookPageEvidence::corroboratesPageMarkedText",
                      "Original answer-specific book evidence missing", "Not in official syllabus",
                      "bank_question_choices", "bank_official_answers",
                      "answer_correspondence_reviewed", "old_page"]:
            self.assertIn(guard, self.src)

    def test_atomic_with_verified_backup_and_exclusive_receipt(self):
        for guard in ["BackupManifest::verify", "4*3600", "GET_LOCK(", "beginTransaction()",
                      "->commit()", "->rollBack()", "fopen($receipt,'x')", "$committed=true"]:
            self.assertIn(guard, self.src)

if __name__ == "__main__":
    unittest.main()
