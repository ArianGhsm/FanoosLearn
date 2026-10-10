"""Source-page only Neville fifth-edition two-record atomic repair contract."""
import pathlib,re,unittest
S=(pathlib.Path(__file__).resolve().parents[2]/"scripts/references/correct_existing_neville5_extra2_pages.php").read_text()
class Original5TwoPageContract(unittest.TestCase):
 def test_only_one_source_column_mutates(self):
  self.assertEqual(re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",S,re.I),["bank_question_sources"])
  for t in ["SET page=:page","page=:old_page","origin='ai'","reviewed_at IS NULL","reviewed_by_user_id IS NULL","rowCount()!==1"]:self.assertIn(t,S)
 def test_full_bank_original_sha_pinned(self):
  for t in ["af9c7418f9f87ab4e2fc38e88ef97548369ccc0728750357d6187631acad4f94",
  "564a318cbea7e4f295ec75c291a9cba49ee1dfde35b7a91e74633d175ab2d8af",
  "ee5b162b01a27339966207e44acff85d3623ea35493b8742ebc1b2abe028b5c7",
  "4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac",
  "source_pdf_sha256",
  "count($cases)!==2","count($manifest['cases']??[])!==2","count($sources)!==159","count($answers)!==159"]:self.assertIn(t,S)
 def test_fail_closed_book_answer_scope_transaction(self):
  for t in ["PrintedBookPageEvidence::pageContainsEvidence","PrintedBookPageEvidence::corroborates",
  "Not in official syllabus","bank_official_answers","bank_question_choices","BackupManifest::verify",
  "GET_LOCK(","beginTransaction()","->commit()","->rollBack()","fopen($receipt,'x')"]:self.assertIn(t,S)
if __name__=="__main__":unittest.main()
