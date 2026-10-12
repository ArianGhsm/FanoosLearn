"""Source-only gates for three official 1398 White & Pharoah original PDF pages."""
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
P=(ROOT/"apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
I=(ROOT/"scripts/references/import_verified_sources.php").read_text()
A=(ROOT/"scripts/references/audit_private_study_batches.py").read_text()
class NewOriginalRadiology1398Sources(unittest.TestCase):
    def test_exact_question_chapter_pdf_page_pairs(self):
        for src in (
            "213 => ['white-pharoah-radiology@7e', '2', 38]",
            "216 => ['white-pharoah-radiology@7e', '7', 109]",
            "220 => ['white-pharoah-radiology@7e', '20', 331]",
        ):
            self.assertIn(src,P)
        self.assertIn("$decision['page'] !== $expectedSource[2]",P)
        self.assertIn("scope_chapters",P)
    def test_only_protected_studies(self):
        for name in ("radiology-q213-q216-20261013","radiology-q220-20261013"):
            self.assertIn(name,I)
            self.assertIn(name,A)
        self.assertIn("hash_file('sha256', $decisionsPath)",I)
        self.assertIn("BackupManifest::verify",I)
        self.assertIn("SourceOnlyPublisher::run",I)
if __name__ == "__main__":
    unittest.main()
