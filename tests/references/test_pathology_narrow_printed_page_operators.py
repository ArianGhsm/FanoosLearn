"""Four- and fifth-edition pathology page metadata operators must never edit questions or official answers."""
import pathlib
import re
import unittest
ROOT = pathlib.Path(__file__).resolve().parents[2]
CASES = {
    "correct_existing_neville4_eighth1_page.php": (
        "3e1624fa03498576439e85bc3485cbc93e9a3476e5c665a1edfd65deeca3ec7a",
        "count($cases)!==1",
    ),
    "correct_existing_neville5_printed_labels2.php": (
        "cd5761bb7fbb7fffeff26dc109bfbe54f3ca6ad76eb356a1b0b6fcbf67bd0af6",
        "count($cases)!==2",
    ),
}
class ExactPageSourceOnly(unittest.TestCase):
    def test_both_scripts_guard_bank_answers_and_only_mutate_source_page(self):
        for filename,(sha,cnt) in CASES.items():
            with self.subTest(filename=filename):
                s=(ROOT/"scripts"/"references"/filename).read_text()
                self.assertEqual(
                    re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",s,re.I),
                    ["bank_question_sources"])
                for word in ("SET page=:page","origin='ai'","reviewed_at IS NULL",
                    "reviewed_by_user_id IS NULL","rowCount()!==1",sha,cnt,
                    "count($sources)!==159","count($answers)!==159",
                    "PrintedBookPageEvidence::corroboratesPageMarkedText",
                    "bank_official_answers","bank_question_choices",
                    "Not in official syllabus","BackupManifest::verify",
                    "GET_LOCK(","beginTransaction()","->commit()","->rollBack()"):
                    self.assertIn(word,s)
    def test_fresh_all_course_and_verified_original_hashes(self):
        for filename in CASES:
            s=(ROOT/"scripts"/"references"/filename).read_text()
            for word in ("37622ae75e09609c169b69351b61d633ac05a7a856979e8f04da863e0ae47ebc",
                "114900e4bd7436c97f798f967b32fc60e911be043ba0b5314c843171872b387e",
                "Original answer-specific", "fopen($receipt,'x')"):
                self.assertIn(word,s)
if __name__=="__main__":
    unittest.main()
