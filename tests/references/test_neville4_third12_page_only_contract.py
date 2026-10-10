"""Strict safeguard for third 12-case original Neville4e page-only correction."""
import pathlib,re,unittest
P=pathlib.Path(__file__).resolve().parents[2]/"scripts/references/correct_existing_neville4_third12_pages.php"
class Third12PageGuard(unittest.TestCase):
 @classmethod
 def setUpClass(cls):cls.source=P.read_text()
 def test_only_source_page(self):
  self.assertEqual(re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",self.source,re.I),["bank_question_sources"])
  for t in ["SET page=:page","page IS NULL","origin='ai'","reviewed_at IS NULL","reviewed_by_user_id IS NULL","rowCount()!==1"]:self.assertIn(t,self.source)
 def test_pinned_evidence_and_course(self):
  for t in ["90e4588a0ef98b434d3afb91ec67cf9a5f4f65f851d810cd13df7b47e72a150d",
            "1af3ac5c2bc0a0005786e147d54689041ab954cc9ea992459f86c25d39ca59b0",
            "3099f064e7d2d72a8929c05a9a465a27eeb8921620a76d880a7ebbda960e2cfb",
            "6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6",
            "count($cases)!==12","count($sources)!==159","count($answers)!==159"]:self.assertIn(t,self.source)
 def test_fact_scope_recovery(self):
  for t in ["PrintedBookPageEvidence::pageContainsEvidence","PrintedBookPageEvidence::corroborates",
            "Not in official syllabus","bank_question_choices","bank_official_answers",
            "BackupManifest::verify","4*3600","GET_LOCK(","beginTransaction()","->commit()","->rollBack()"]:self.assertIn(t,self.source)
if __name__=="__main__":unittest.main()
