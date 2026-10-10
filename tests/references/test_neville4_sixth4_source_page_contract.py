"""Safety contract for exact four Neville4e sixth-pass source page corrections."""
import pathlib,re,unittest
S=(pathlib.Path(__file__).resolve().parents[2]/"scripts/references/correct_existing_neville4_sixth4_pages.php").read_text()
class Sixth4SourcePageSafety(unittest.TestCase):
 def test_mutation_scope(self):
  self.assertEqual(re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",S,re.I),["bank_question_sources"])
  for t in ["SET page=:page","page IS NULL","origin='ai'","reviewed_at IS NULL","reviewed_by_user_id IS NULL","rowCount()!==1"]:self.assertIn(t,S)
 def test_full_snapshots_and_original_books(self):
  for t in ["44aae015fec9bc301fc60d1a9c47795b01966f4699207dfd8dce3402fed5fb82","1784a930935d2dd23e348f2120486c6375507d02c7007222f60cb8fed5717f77","776f561da178195ea0b62eb54b3ffb4d63a9eef7ec1cf36230a03620fd37c421","b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a","count($cases)!==4","count($sources)!==159","count($answers)!==159"]:self.assertIn(t,S)
 def test_scope_key_book_page_and_backup(self):
  for t in ["PrintedBookPageEvidence::corroboratesPageMarkedText","Original answer-specific evidence missing","Not in official syllabus","bank_official_answers","bank_question_choices","BackupManifest::verify","GET_LOCK(","beginTransaction()","->commit()","->rollBack()","fopen($receipt,'x')"]:self.assertIn(t,S)
if __name__=="__main__":unittest.main()
