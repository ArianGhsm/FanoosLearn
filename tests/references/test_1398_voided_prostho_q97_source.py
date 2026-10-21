"""Permit a source-only insert on exactly the voided/draft 1398 prostho Q97."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
PUBLISHER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
IMPORTER = ROOT / "scripts/references/import_verified_sources.php"
ASSESSMENT = ROOT / "scripts/references/audit_published_assessment.php"


class VoidedProsthodonticsQ97SourceGate(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.publisher = PUBLISHER.read_text(encoding="utf-8")
        cls.importer = IMPORTER.read_text(encoding="utf-8")
        cls.assessment = ASSESSMENT.read_text(encoding="utf-8")

    def test_only_exact_official_q97_page_is_allowed(self):
        self.assertIn("$n === 97", self.publisher)
        self.assertIn("'shillingburg-fixed@4e'", self.publisher)
        self.assertIn("$decision['chapter'] !== '22'", self.publisher)
        self.assertIn("$decision['page'] !== 430", self.publisher)
        self.assertIn("recovery-1398-prostho-voided-q97-20261021", self.importer)
        self.assertIn("$stem === 'prostho-voided-q97' && $expected === 1", self.importer)
        self.assertIn("$subject === 'prosthodontics' && in_array($stem, ['prosthodontics', 'prostho-voided-q97'], true)", self.assessment)

    def test_only_draft_q97_with_null_choice_and_voided_answer_is_eligible(self):
        self.assertIn("$n === 97 && $q['status'] === 'draft'", self.publisher)
        self.assertIn("$actual['choice'] !== null || $actual['status'] !== 'voided'", self.publisher)
        self.assertIn("Only published questions and the exact voided 1398 prosthodontics Q97 draft may receive a source.", self.publisher)
        statements = re.findall(
            r"(?<!FOR )(?:INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO)\s+([a-z_]+)",
            self.publisher,
            flags=re.IGNORECASE,
        )
        self.assertEqual([statement.lower() for statement in statements], ["bank_question_sources"])


if __name__ == "__main__":
    unittest.main()
