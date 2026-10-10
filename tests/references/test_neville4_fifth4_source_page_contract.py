"""Fail-closed safety contract for four additional original Neville4e pathology source-page fills."""
import pathlib,re,unittest
SCRIPT=pathlib.Path(__file__).resolve().parents[2]/"scripts/references/correct_existing_neville4_fifth4_pages.php"
class Fifth4Safety(unittest.TestCase):
 @classmethod
 def setUpClass(cls):cls.s=SCRIPT.read_text(encoding="utf-8")
 def test_page_only(self):
  self.assertEqual(re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",self.s,re.I),["bank_question_sources"])
  for w in ["SET page=:page","page IS NULL","origin='ai'","reviewed_at IS NULL","reviewed_by_user_id IS NULL","rowCount()!==1"]:self.assertIn(w,self.s)
 def test_pinned_research(self):
  for w in ["10a377e50f4b68404f88cad022dc5f8bb00ca21edcab4ba41079c77a74bf8620",
  "e9b599178b61d2472f28f3863339558736e0d5d8daf1f77ca26bf107c440df37",
  "291abee64dcc32092e34c155a4e95041191cd31601027940cc75b0d8032a6306",
  "6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6",
  "count($cases)!==4","count($sources)!==159","count($answers)!==159"]:self.assertIn(w,self.s)
 def test_atomic_backup_original_page_scope(self):
  for w in ["PrintedBookPageEvidence::pageContainsEvidence","PrintedBookPageEvidence::corroborates",
  "Not in official syllabus","bank_official_answers","bank_question_choices","BackupManifest::verify",
  "GET_LOCK(","beginTransaction()","->commit()","->rollBack()","fopen($receipt,'x')"]:self.assertIn(w,self.s)
if __name__=="__main__":unittest.main()
