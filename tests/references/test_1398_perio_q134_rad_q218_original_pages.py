"""Lock two verified residency 1398 sources to exact official PDF chapters/pages."""
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
P=(ROOT/"apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
I=(ROOT/"scripts/references/import_verified_sources.php").read_text()
A=(ROOT/"scripts/references/audit_private_study_batches.py").read_text()
class Original1398ExactPages(unittest.TestCase):
    def test_source_scopes(self):
        for t in ["$n === 134","'carranza-periodontology@12e'","$decision['page'] !== 664","218 => ['white-pharoah-radiology@7e', '10', 199]","$decision['page'] !== $expectedSource[2]","scope_chapters"]:
            self.assertIn(t,P)
    def test_permitted_private_batches_and_no_content_changes(self):
        for stem in ("periodontics-q134-20261012","radiology-q218-20261012"):
            self.assertIn(stem,I)
            self.assertIn(stem,A)
        self.assertIn("classification/decisions/1398-{$stem}.json",I)
        self.assertIn("hash_file('sha256', $validatedPath)",I)
        self.assertIn("BackupManifest::verify",I)
        self.assertIn("sourcePdfPage !== $pdfPage",P)
if __name__=="__main__":
    unittest.main()
