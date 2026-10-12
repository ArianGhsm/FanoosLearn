"""Three more original 1398 pediatric/radiology question sources have strict evidence gates."""
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
PUBLISHER=(ROOT/"apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
IMPORTER=(ROOT/"scripts/references/import_verified_sources.php").read_text()
AUDITOR=(ROOT/"scripts/references/audit_private_study_batches.py").read_text()
class More1398ExactSources(unittest.TestCase):
    def test_narrow_original_chapters_and_pages(self):
        self.assertIn("159 => ['mcdonald-avery-pediatric@10e', '19', 360]",PUBLISHER)
        self.assertIn("219 => ['white-pharoah-radiology@7e', '14', 260]",PUBLISHER)
        self.assertIn("226 => ['white-pharoah-radiology@7e', '21', 355]",PUBLISHER)
        self.assertIn("$year === 1398",PUBLISHER)
        self.assertIn("$decision['page'] !== $expectedSource[2]",PUBLISHER)
        self.assertIn("scope_chapters",PUBLISHER)
    def test_fixed_stages_preserve_original_provenance_and_backup(self):
        for stem in ["pediatric-q159-20261012","radiology-q219-q226-20261012"]:
            self.assertIn(stem,IMPORTER)
            self.assertIn(stem,AUDITOR)
        self.assertIn("classification/decisions/1398-{$stem}.json",IMPORTER)
        self.assertIn("BackupManifest::verify",IMPORTER)
        self.assertIn("hash_file('sha256', $studyPath)",IMPORTER)
        self.assertIn("hash_file('sha256', $validatedPath)",IMPORTER)
        self.assertIn("hash_file('sha256', $decisionsPath)",IMPORTER)
        self.assertIn("SourceOnlyPublisher::run",IMPORTER)
if __name__=="__main__":
    unittest.main()
