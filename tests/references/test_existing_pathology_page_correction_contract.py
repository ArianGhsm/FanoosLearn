"""The five-case original Neville5 page-only repair is fail-closed.

Static safety contract supplements the live database preview. It is not a
substitute for verifying the approved PDF, actual locked source rows, a fresh
full backup, or the production post-commit readback.
"""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/correct_existing_pathology_pages.php"


class ExactOriginalPathologyPageRepairContractTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.body = OPERATOR.read_text(encoding="utf-8")

    def test_exactly_five_exam_and_page_targets(self):
        cases = re.findall(r"'(140[45]:\d+)' => \['chapter' => '(\d+)', 'old' => '([^']+)', 'new' => '([^']+)', 'pdf' => (\d+)", self.body)
        self.assertEqual(cases, [
            ("1404:46", "10", "10", "367", "377"),
            ("1405:47", "10", "20", "434", "444"),
            ("1405:51", "12", "12", "533", "543"),
            ("1405:54", "14", "14", "639", "649"),
            ("1405:60", "16", "3", "770", "780"),
        ])

    def test_no_question_answer_provenance_or_chapter_mutation(self):
        mutations = re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b", self.body, flags=re.I)
        self.assertEqual(mutations, ["bank_question_sources"])
        match = re.search(r"UPDATE bank_question_sources\s+SET\s+(.+?)\s+WHERE\s+", self.body, flags=re.I | re.S)
        self.assertIsNotNone(match)
        self.assertEqual(match.group(1).strip(), "page=:new_page")
        self.assertIn("reviewed_by_user_id IS NULL", self.body)
        self.assertIn("reviewed_at IS NULL", self.body)
        self.assertIn("origin='ai'", self.body)
        self.assertIn("bank_official_answers", self.body)
        self.assertIn("bank_question_choices", self.body)
        self.assertIn("scope_chapters", self.body)
        self.assertIn("is_official", self.body)

    def test_actual_original_book_and_input_snapshot_sha_are_pinned(self):
        for digest in [
            "9d6b8efd99cb942e0f2b6488dcef0b2fd6e7ae2f01fb05534eca33efa435ac4b",
            "a2b78e2b3d2c0c9434c14adb8e438037e999d73b1b39787e2888fbbb615c7037",
            "348dafa50b9f53648dc5f7cad97547459ba70a227680ab921c5a04b1b63b6c40",
        ]:
            self.assertIn(digest, self.body)
        self.assertIn("hash_file('sha256'", self.body)
        self.assertIn("Original PDF page marker missing:", self.body)
        self.assertIn("Clinical answer-defining original passage absent:", self.body)

    def test_source_only_apply_requires_fresh_complete_backup(self):
        self.assertIn("BackupManifest::verify", self.body)
        self.assertIn("4 * 3600", self.body)
        self.assertIn("posix_geteuid()", self.body)
        self.assertIn("->beginTransaction()", self.body)
        self.assertIn("->rollBack()", self.body)
        self.assertIn("->commit()", self.body)
        self.assertIn("GET_LOCK(", self.body)
        self.assertIn("fopen($receiptName, 'x')", self.body)
        self.assertIn("->rowCount() !== 1", self.body)
        self.assertIn("$committed = true", self.body)


if __name__ == "__main__":
    unittest.main()
