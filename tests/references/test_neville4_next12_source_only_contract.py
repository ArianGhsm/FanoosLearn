"""Fail-closed contract for second 12-case original Neville 4e metadata repair."""
import pathlib
import re
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[2] / "scripts/references/correct_existing_neville4_next12_pages.php"

class Neville4SecondBatchSafety(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.body=SCRIPT.read_text(encoding="utf-8")

    def test_only_existing_ai_page_field_mutates(self):
        self.assertEqual(re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",
                                    self.body,re.I), ["bank_question_sources"])
        for v in ["SET page=:page","page IS NULL","origin='ai'","reviewed_at IS NULL",
                  "reviewed_by_user_id IS NULL","rowCount()!==1"]:
            self.assertIn(v,self.body)

    def test_sha_pins_full_original_and_course_inputs(self):
        for sha in ["addaa20964d777b1029c57340d9e7f8f46f35485592d085b5bdfd14663efa3c0",
                    "14d879ec081dd1d25b8e4d5eceb1b1232a38e08543d08aa2d254f62d73624290",
                    "e6c09bdb179f38b92659a54cdb8ec536be92f41f094f620d7b9c0137b638d966",
                    "b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a"]:
            self.assertIn(sha,self.body)
        self.assertIn("count($manifest['cases']??[])!==12",self.body)
        self.assertIn("count($cases)!==12",self.body)
        self.assertIn("count($sources)!==159",self.body)
        self.assertIn("count($answers)!==159",self.body)

    def test_original_chapter_answer_and_printed_page_guards(self):
        for v in ["PrintedBookPageEvidence::corroboratesPageMarkedText",
                  "Original answer-specific evidence missing",
                  "Not in official syllabus","bank_official_answers",
                  "bank_question_choices","answer_correspondence_reviewed"]:
            self.assertIn(v,self.body)

    def test_backup_rollback_transaction(self):
        for v in ["BackupManifest::verify","4*3600","GET_LOCK(","beginTransaction()",
                  "->rollBack()","->commit()","fopen($receipt,'x')"]:
            self.assertIn(v,self.body)

if __name__ == "__main__":
    unittest.main()
