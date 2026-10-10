import pathlib
import re
import unittest

FILE = pathlib.Path(__file__).resolve().parents[2] / "scripts/references/correct_existing_neville4_pathology_pages.php"

class OriginalNeville4PageOperatorSafety(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.code = FILE.read_text()

    def test_only_source_page_metadata_updated(self):
        changes = re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b", self.code, re.I)
        self.assertEqual(changes, ["bank_question_sources"])
        for guard in ["SET page=:page", "page IS NULL", "origin='ai'", "reviewed_at IS NULL", "reviewed_by_user_id IS NULL", "rowCount()!==1"]:
            self.assertIn(guard, self.code)

    def test_exact_private_original_and_42_sources(self):
        for digest in ["06489c524c61e8fe221208117ce9cdc46e57b3d0a18e5f76215815ea1fc60f82",
                       "d8e96cf2e2517405cc6c17944f99fb1e6becc6e9aa1cecc70068c53a05f52291",
                       "62dbe2b7e06408c6d93a42ac023058fc31387eb8f6d80089f66614e40e5fe32e",
                       "b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a"]:
            self.assertIn(digest, self.code)
        for gate in ["count($cases)!==42", "count($sources)!==159", "count($answers)!==159",
                     "PrintedBookPageEvidence::corroboratesPageMarkedText",
                     "Original answer-specific evidence missing", "Not in official syllabus",
                     "bank_question_choices", "bank_official_answers"]:
            self.assertIn(gate, self.code)

    def test_recovery_and_transaction(self):
        for gate in ["BackupManifest::verify", "4*3600", "GET_LOCK(", "beginTransaction()",
                     "->commit()", "->rollBack()", "fopen($receipt,'x')"]:
            self.assertIn(gate, self.code)

if __name__ == "__main__":
    unittest.main()
