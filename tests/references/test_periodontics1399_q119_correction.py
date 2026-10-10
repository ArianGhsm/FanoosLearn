"""Guard 1399 Q119 corrected ch48->ch47 exact Carranza13e source-only metadata."""
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CODE = ROOT / "scripts/references/correct_periodontics_1399_q119_chapter_page.php"


class Periodontics1399Q119Correction(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.code = CODE.read_text(encoding="utf-8")

    def test_only_two_metadata_fields_update(self):
        sql = re.findall(r"UPDATE\s+bank_question_sources\s+SET\s+([^\n]+)", self.code, re.I)
        self.assertEqual(sql, ["node_id=:target_node,page='507'"])
        self.assertNotRegex(self.code, r"\b(?:INSERT INTO|DELETE FROM|REPLACE INTO)\b")
        self.assertIn("node_id=:old_node AND page IS NULL AND origin='ai'", self.code)
        self.assertIn("reviewed_by_user_id IS NULL", self.code)
        self.assertIn("$update->rowCount() !== 1", self.code)
        self.assertIn("GET_LOCK('fanoos:periodontics-existing-source-pages',0)", self.code)
        self.assertIn("FOR UPDATE", self.code)

    def test_exact_book_year_answer_and_human_protections(self):
        for marker in (
            "si.exam_year=1399", "sb.subject_key='periodontics'",
            "q.number_in_sitting=119", "carranza-periodontology@13e",
            "'old_chapter' => '48'", "'new_chapter' => '47'",
            "ch47", "1073", "'507'",
            "targeted oral hygiene", "is synonymous with the bass technique",
            "PrintedBookPageEvidence::pageContainsEvidence",
            "PrintedBookPageEvidence::corroborates", "source_pdf_sha256",
            "BackupManifest::verify",
            "if (!in_array('47', $scopeNumbers, true))",
            "Original private source snapshot changed",
            "choices", "answer", "reviewed_by_user_id",
            "question_option_answer_anchor_review_assessment_attempt_edits",
            "$db->rollBack()", "$db->commit()",
        ):
            self.assertIn(marker,self.code)

if __name__=="__main__":
    unittest.main()
