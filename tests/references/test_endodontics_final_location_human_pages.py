"""Endodontics 1398 official-book location contract (the 1404 page operator was applied and removed, see docs/ops/RESIDENCY_CLASSIFICATION_1398_1405.md)."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
PUBLISH = (ROOT / "scripts/references/import_verified_sources.php").read_text()
AUDIT = (ROOT / "scripts/references/audit_published_assessment.php").read_text()


class EndodonticsFinalizationContracts(unittest.TestCase):
    def test_1398_exact_location_stage(self):
        for code in [PUBLISH, AUDIT]:
            self.assertIn("endodontics-location-final", code)
            self.assertIn("$year === 1398 && $subject === 'endodontics'", code)
        for marker in ["W03-location-final-20261010", "final location audit stage is exclusive",
                       "realpath(dirname($auditPath)) !== $auditDir",
                       "hash_file('sha256', $validatedPath)",
                       "BackupManifest::verify"]:
            self.assertIn(marker, PUBLISH)


if __name__ == "__main__":
    unittest.main()
