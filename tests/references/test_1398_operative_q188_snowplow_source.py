"""Pin 1398 Q188 to the in-scope Summit page and keep its official key intact."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
PUBLISHER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
IMPORTER = ROOT / "scripts/references/import_verified_sources.php"
ASSESSMENT = ROOT / "scripts/references/audit_published_assessment.php"


class OperativeQ188SnowplowSource(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.publisher = PUBLISHER.read_text(encoding="utf-8")
        cls.importer = IMPORTER.read_text(encoding="utf-8")
        cls.assessment = ASSESSMENT.read_text(encoding="utf-8")

    def test_exact_q188_case_and_page_are_required(self):
        self.assertIn("$n === 188", self.publisher)
        self.assertIn("$decision['edition'] !== 'summit-operative@4e'", self.publisher)
        self.assertIn("$decision['chapter'] !== '11'", self.publisher)
        self.assertIn("$decision['page'] !== 610", self.publisher)
        self.assertIn("recovery-1398-operative-q188-snowplow-20261021", self.importer)
        self.assertIn("$stem === 'operative-q188-snowplow' && $expected === 1", self.importer)
        self.assertIn("$subject === 'operative-dentistry' && in_array($stem, ['operative-q188-snowplow', 'operative-unsourced-20261012'], true)", self.assessment)

    def test_only_source_row_changes_and_answer_is_compared(self):
        statements = [line for line in self.publisher.splitlines()
                      if "INSERT INTO bank_question_sources" in line]
        self.assertEqual(len(statements), 1)
        self.assertIn("actual !== $original[$n]['answer']", self.publisher)
        self.assertIn("question_content_identical", self.importer)


if __name__ == "__main__":
    unittest.main()
