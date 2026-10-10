"""Strict source-only corrected page safety for Neville4e three exact proof cases."""
import pathlib,re,unittest
S=(pathlib.Path(__file__).resolve().parents[2]/"scripts/references/correct_existing_neville4_seventh3_pages.php").read_text()
class Seventh3Contract(unittest.TestCase):
 def test_ai_only_existing_source_page(self):
  self.assertEqual(re.findall(r"\b(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(bank_[a-z_]+)\b",S,re.I),["bank_question_sources"])
  for w in ["SET page=:page","page IS NULL","origin='ai'","reviewed_at IS NULL","reviewed_by_user_id IS NULL","rowCount()!==1"]:self.assertIn(w,S)
 def test_exact_original_and_pinned_full_bank(self):
  for w in ["73b3572ab81123f3cc1468acf633a3e60d426bf0849597057f0d21115d6a8c21",
  "6dfe854b523a1ad493614873ea5ead8e4e6975e228c423e9dd37688023367f41",
  "6983679eac074848286ae5f3a592e3d0e975ba9b64cab8154f6c74a706a33e23",
  "6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6",
  "source_pdf_sha256",
  "count($cases)!==3","count($sources)!==159","count($answers)!==159"]:self.assertIn(w,S)
 def test_answer_scope_book_and_backup(self):
  for w in ["PrintedBookPageEvidence::pageContainsEvidence","PrintedBookPageEvidence::corroborates","Not in official syllabus","bank_question_choices","bank_official_answers","BackupManifest::verify","GET_LOCK(","beginTransaction()","->commit()","->rollBack()","fopen($receipt,'x')"]:self.assertIn(w,S)
if __name__=="__main__":unittest.main()
