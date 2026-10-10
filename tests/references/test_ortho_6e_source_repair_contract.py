"""Contract: the only DML in the cross-year orthodontics repair updates existing AI source rows."""
from pathlib import Path
import re
import unittest

FILE=Path(__file__).resolve().parents[2]/"scripts/references/correct_ortho_6e_source_anomalies.php"

class OrthodonticsOriginal6eSourceRepairTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.code=FILE.read_text(encoding="utf-8")

    def test_repair_cannot_update_questions_keys_or_humans(self):
        targets=re.findall(r"(?:INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO)\s+(bank_[a-z_]+)\b",self.code,flags=re.I)
        self.assertEqual([x.lower() for x in targets],["bank_question_sources"])
        for guard in ["origin='ai'", "q.question_key", "hash('sha256',$q['stem'])",
                      "answer_status", "v.is_official=1", "scope_chapters",
                      "rowCount()!==1", "FOR UPDATE"]:
            self.assertIn(guard,self.code)

    def test_exact_eight_verified_original_book_inputs(self):
        for word in ["count($input['decisions'] ?? []) !== 8",
                     "29fedfae46b69b5b3d81439d0e929a12464b9ee1cdf41498ec526b272744976d",
                     "c2e9b985eb8bf9fb916dbb2ab763ac27bd30bad89748ef76685c362aeec8aa59",
                     "'1399:7'", "'1402:21'","'1404:11'","count($seen) !== 8"]:
            self.assertIn(word,self.code)

    def test_preserve_amended_official_status_of_1402_q23(self):
        self.assertIn("$k === '1402:23' ? 'amended' : 'final'", self.code)

    def test_production_requires_backup_receipt_and_transactional_atomicity(self):
        for word in ["BackupManifest::verify", "database.sql",
                     "posix_geteuid()", "if ($apply) $db->commit(); else $db->rollBack();",
                     "file_exists($receipt)", "fopen($receipt,'x')"]:
            self.assertIn(word,self.code)

if __name__=="__main__":
    unittest.main()
