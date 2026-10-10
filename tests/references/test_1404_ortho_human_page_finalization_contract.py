"""Guard human-authored orthodontics 1404 page-only completion."""
from pathlib import Path
import re
import unittest

ROOT=Path(__file__).resolve().parents[2]
CLI=ROOT/"scripts/references/complete_1404_orthodontics_human_pages.php"

class Orthodontics1404HumanPageCompletionContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.s=CLI.read_text(encoding="utf-8")

    def test_only_human_page_metadata_can_be_updated(self):
        changes=re.findall(r"(?<!FOR )(?:INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO)\s+(bank_[a-z_]+)",self.s,re.I)
        self.assertEqual([x.lower() for x in changes],["bank_question_sources"])
        self.assertIn("UPDATE bank_question_sources SET page=:page",self.s)
        self.assertIn("origin='human' AND is_primary=1 AND page IS NULL",self.s)
        self.assertNotIn("SET origin=",self.s)
        self.assertNotIn("SET node_id=",self.s)

    def test_owner_approved_exact_scope_two_rows(self):
        for check in ["1404", "16 =>", "17 =>", "'pdf 448'", "'pdf 491'",
                      "'stem_sha256'", "'choices_sha256'", "'source_id'",
                      "q['origin'] !== 'human'", "is_official=1",
                      "scope_chapters", "rowCount() !== 1"]:
            self.assertIn(check,self.s)

    def test_dry_run_full_backup_receipt_and_human_originality(self):
        for check in ["BackupManifest::verify","database.sql","posix_geteuid()",
                      "if ($apply) $db->commit(); else $db->rollBack();",
                      "fopen($receipt,'x')","$q['anchor_text'] !== null",
                      "$q['answer_status'] !== 'final'", "key_geometry_review"]:
            self.assertIn(check,self.s)

if __name__=="__main__":
    unittest.main()
