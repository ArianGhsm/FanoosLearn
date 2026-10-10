"""Safety invariants for exact 1399 Carranza 13e source PAGE-only repairs."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "scripts/references/correct_existing_periodontics_next5_pages.php"


class Periodontics13eSecondFivePageRepairContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.src = SOURCE.read_text(encoding="utf-8")

    def test_exact_five_questions_and_original_book(self):
        for number in (115, 118, 122, 123, 125):
            self.assertRegex(self.src, rf"(?m)^\s*{number} =>")
        for marker in (
            "si.exam_year=1399", "sb.subject_key='periodontics'",
            "carranza-periodontology@13e", "PrintedBookPageEvidence::corroborates",
            "'source_page'", "source_reviewed_by", "reviewed_at IS NULL",
            "scope_chapters", "in_array($target['chapter'], $chapters, true)",
            "GET_LOCK(", "FOR UPDATE", "BackupManifest::verify",
            "hash_file('sha256', $snapshotFile)", "hash_file('sha256', $bookFile)",
            "question_option_answer_human_assessment_changes",
        ):
            self.assertIn(marker, self.src)

    def test_only_existing_source_page_can_be_modified(self):
        updates = re.findall(r"\bUPDATE\s+([a-z_]+)\s+SET\s+([^\n]+)", self.src, flags=re.I)
        self.assertEqual(len(updates), 1)
        self.assertEqual(updates[0][0], "bank_question_sources")
        self.assertEqual(updates[0][1].strip(), "page=:printed")
        self.assertNotRegex(self.src, r"\b(?:INSERT INTO|DELETE FROM|REPLACE INTO)\b")
        self.assertIn("page IS NULL AND origin='ai'", self.src)
        self.assertIn("reviewed_by_user_id IS NULL", self.src)
        self.assertIn("question_id=:qid", self.src)
        self.assertIn("$db->rollBack()", self.src)
        self.assertIn("$db->commit()", self.src)
        self.assertIn("$update->rowCount() !== 1", self.src)

    def test_exact_pages_have_answer_defining_evidence(self):
        for page, printed in ((506, 182), (882, 408), (1611, 721),
                              (1196, 559), (96, 46)):
            self.assertIn(f"'pdf' => {page}, 'printed' => '{printed}'", self.src)
        self.assertIn("preg_match('/^=== PAGE '", self.src)
        self.assertIn("str_contains($pageText, $flatten($proof))", self.src)
        self.assertIn("['final', 'amended']", self.src)
        self.assertIn("!== $saved['choices']", self.src)
        self.assertIn("!== $saved['source_anchor']", self.src)


if __name__ == "__main__":
    unittest.main()
