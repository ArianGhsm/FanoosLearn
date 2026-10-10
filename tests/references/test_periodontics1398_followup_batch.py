"""Only a new protected, auditable exact-12e second pass may source 1398 perio."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
IMPORT = ROOT / "scripts/references/import_verified_sources.php"
AUDIT = ROOT / "scripts/references/audit_private_study_batches.py"
GUARD = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"

class Periodontics1398FollowupSafety(unittest.TestCase):
    def test_independent_immutable_decisions(self):
        importer = IMPORT.read_text()
        audit = AUDIT.read_text()
        self.assertIn("periodontics-followup", importer)
        self.assertIn("1398-periodontics-followup.json", importer)
        self.assertIn('year_n, subject, stem) == (1398, "periodontics", "periodontics-followup")', audit)
        self.assertIn("1398-periodontics-followup.json", audit)
        self.assertIn("hash_file('sha256', $decisionsPath)", importer)
        self.assertIn("question_content_identical", audit)
        self.assertIn("Duplicate or unsupported", audit.replace("duplicate or unsupported", "Duplicate or unsupported"))

    def test_first_batch_and_other_subject_guards_stay_in_effect(self):
        importer = IMPORT.read_text()
        self.assertIn("['periodontics', 'periodontics-followup']", importer)
        self.assertIn("endodontics-q9", importer)
        self.assertIn("endodontics-q1", importer)
        self.assertIn("$year < 1399", importer)
        self.assertIn("SourceOnlyPublisher::run", importer)
        svc = GUARD.read_text()
        self.assertIn("1398 periodontics requires exact announced Carranza 12e", svc)
        self.assertIn("already exists", svc)
        self.assertIn("realChoices !== $original", svc)

if __name__ == "__main__":
    unittest.main()
