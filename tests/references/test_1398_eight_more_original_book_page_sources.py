"""Eight locked residency 1398 exact original PDF chapter-page classifications."""
from pathlib import Path
import unittest
ROOT = Path(__file__).resolve().parents[2]
P = (ROOT/"apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
I = (ROOT/"scripts/references/import_verified_sources.php").read_text()
A = (ROOT/"scripts/references/audit_private_study_batches.py").read_text()

class Residency1398EightBoundPages(unittest.TestCase):
    def test_locked_source_data(self):
        refs = {
            152: ("mcdonald-avery-pediatric@10e", "13", 246),
            215: ("white-pharoah-radiology@7e", "4", 61),
            223: ("white-pharoah-radiology@7e", "25", 486),
            224: ("white-pharoah-radiology@7e", "22", 386),
            227: ("white-pharoah-radiology@7e", "20", 333),
            228: ("white-pharoah-radiology@7e", "25", 473),
            229: ("white-pharoah-radiology@7e", "23", 437),
            230: ("white-pharoah-radiology@7e", "24", 463),
        }
        for n,(ed,ch,pg) in refs.items():
            self.assertIn(f"{n} => ['{ed}', '{ch}', {pg}]", P)
        self.assertIn("$decision['page'] !== $expectedSource[2]", P)
        self.assertIn("scope_chapters", P)
        self.assertIn("pdf_sha256", P)

    def test_independent_immutable_verified_source_batches(self):
        for stem in ("pediatric-q152-20261012","radiology-seven-20261012"):
            self.assertIn(stem,I)
            self.assertIn(stem,A)
        self.assertIn("classification/decisions/1398-{$stem}.json",I)
        self.assertIn("hash_file('sha256', $studyPath)",I)
        self.assertIn("hash_file('sha256', $validatedPath)",I)
        self.assertIn("BackupManifest::verify",I)
        self.assertIn("SourceOnlyPublisher::run",I)
        self.assertIn("$db->rollBack()",P)

if __name__ == "__main__":
    unittest.main()
