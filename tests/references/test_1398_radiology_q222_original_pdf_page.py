"""1398 White and Pharoah 7e Q222 source-only proof must be chapter23 original PDF425."""
from pathlib import Path
import unittest
R=Path(__file__).resolve().parents[2]
P=(R/"apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
I=(R/"scripts/references/import_verified_sources.php").read_text()
A=(R/"scripts/references/audit_private_study_batches.py").read_text()
class OriginalRadiology222(unittest.TestCase):
    def test_exact_source_gate(self):
        self.assertIn("222 => ['white-pharoah-radiology@7e', '23', 425]",P)
        self.assertIn("$decision['page'] !== $expectedSource[2]",P)
        self.assertIn("scope_chapters",P)
    def test_private_immutable_batch(self):
        self.assertIn("radiology-q222-20261012",I)
        self.assertIn("radiology-q222-20261012",A)
        self.assertIn("BackupManifest::verify",I)
        self.assertIn("hash_file('sha256', $decisionsPath)",I)
        self.assertIn("SourceOnlyPublisher::run",I)
if __name__=="__main__":
    unittest.main()
