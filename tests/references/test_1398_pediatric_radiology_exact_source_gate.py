"""Keep six specific 1398 original-book pediatric/radiology source-only inserts narrow."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
IMPORTER = (ROOT / "scripts/references/import_verified_sources.php").read_text()
AUDITOR = (ROOT / "scripts/references/audit_private_study_batches.py").read_text()
PUBLISHER = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()

class Residency1398PediatricRadiologySourceSafety(unittest.TestCase):
    def test_exact_immutable_protected_batch_paths(self):
        for stem in ("pediatric-q164-q168-20261012", "radiology-four-20261012"):
            self.assertIn(stem, IMPORTER)
            self.assertIn(stem, AUDITOR)
        self.assertIn("classification/decisions/1398-{$stem}.json", IMPORTER)
        self.assertIn("f'1398-{stem}.json'", AUDITOR)
        self.assertIn("hash_file('sha256', $studyPath)", IMPORTER)
        self.assertIn("hash_file('sha256', $validatedPath)", IMPORTER)
        self.assertIn("hash_file('sha256', $decisionsPath)", IMPORTER)

    def test_no_other_pediatric_or_radiology_year_case_allowed(self):
        for item in ("'pediatric-dentistry'", "'oral-radiology'",
                     "'mcdonald-avery-pediatric@10e'", "'white-pharoah-radiology@7e'",
                     "164 =>", "168 =>", "211 =>", "212 =>", "214 =>", "217 =>",
                     "$decision['page'] !== $expectedSource[2]",
                     "$decision['edition'] !== $expectedSource[0]",
                     "$policyYear === 1398", "$policyYear = $isResidency ? $year : 0;"):
            self.assertIn(item, PUBLISHER)
        self.assertIn("original PDF-page source gate refused", PUBLISHER)
        self.assertIn("v.is_official=1", PUBLISHER)
        self.assertIn("scope_chapters", PUBLISHER)

    def test_write_scope_and_backup_are_unchanged(self):
        self.assertIn("INSERT INTO bank_question_sources", PUBLISHER)
        self.assertIn("already exists", PUBLISHER)
        self.assertIn("$db->rollBack()", PUBLISHER)
        self.assertIn("$db->commit()", PUBLISHER)
        self.assertIn("realChoices !== $original", PUBLISHER)
        self.assertIn("BackupManifest::verify", IMPORTER)
        self.assertIn("sourcePdfPage !== $pdfPage", PUBLISHER)

if __name__ == "__main__":
    unittest.main()
