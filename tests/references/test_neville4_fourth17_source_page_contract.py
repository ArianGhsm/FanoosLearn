"""Strict contract: fourth batch of 17 original Neville fourth edition source pages."""
import pathlib,re,unittest
SCRIPT=pathlib.Path(__file__).resolve().parents[2]/"scripts/references/correct_existing_neville4_fourth17_pages.php"
class Fourth17PageSafety(unittest.TestCase):
 @classmethod
 def setUpClass(cls):cls.s=SCRIPT.read_text(encoding="utf-8")
 def test_only_existing_source_page_mutates(self):
  self.assertEqual(re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",self.s,re.I),["bank_question_sources"])
  for w in ["SET page=:page","page IS NULL","origin='ai'","reviewed_at IS NULL","reviewed_by_user_id IS NULL","rowCount()!==1"]:self.assertIn(w,self.s)
 def test_exact17_whole_bank_and_original_book_sha_pins(self):
  for w in ["89d848694c20953a423a809533f68a61fe009387d64e585b16dd14eacaecb179",
    "565b8f24e9a95cd9b9fc9d0cee07d60bdfa730bcbdd56a6ef10080b9cbca488e",
    "1ec7dace2e7e354c130d630f578db8dd1909e25b106b5671c49bef59d97b63ca",
    "b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a",
    "count($cases)!==17","count($sources)!==159","count($answers)!==159"]:self.assertIn(w,self.s)
 def test_printed_page_evidence_key_and_scope_guards(self):
  for w in ["PrintedBookPageEvidence::corroboratesPageMarkedText","Original answer-specific evidence missing",
    "Not in official syllabus","bank_question_choices","bank_official_answers","answer_correspondence_reviewed"]:self.assertIn(w,self.s)
 def test_recovery_transaction_and_unique_receipt(self):
  for w in ["BackupManifest::verify","4*3600","GET_LOCK(","beginTransaction()","->commit()","->rollBack()","fopen($receipt,'x')"]:self.assertIn(w,self.s)
if __name__=="__main__":unittest.main()
