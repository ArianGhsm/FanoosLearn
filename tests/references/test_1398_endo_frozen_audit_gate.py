"""Regression guard: frozen-assessment audit permits three 1398 evidence-reviewed subjects."""

from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
AUDIT = ROOT / "scripts/references/audit_published_assessment.php"


class Endodontics1398FrozenAuditGate(unittest.TestCase):
    def test_1398_only_prevalidated_subjects_are_allowed(self):
        code = AUDIT.read_text(encoding="utf-8")
        self.assertIn("$year < 1399", code)
        self.assertIn(
            "$year === 1398 && $subject === 'endodontics' && in_array($stem, ['endodontics', 'endodontics-q9', 'endodontics-q1', 'endodontics-location-final'], true)",
            code,
        )
        self.assertIn("$subject === 'periodontics' && $stem === 'periodontics'", code)
        self.assertIn("$subject === 'community-dentistry' && $stem === 'community'", code)
        self.assertIn("question_content_identical", code)
        self.assertIn("Non-citation frozen assessment content changed", code)
        self.assertIn("Published official answer missing", (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text(encoding="utf-8"))


if __name__ == "__main__":
    unittest.main()
