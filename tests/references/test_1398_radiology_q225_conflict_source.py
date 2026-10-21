"""Allow Q225's exact location while preserving its contradictory final key."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
PUBLISHER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
IMPORTER = ROOT / "scripts/references/import_verified_sources.php"
ASSESSMENT = ROOT / "scripts/references/audit_published_assessment.php"


class RadiologyQ225ConflictSource(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.publisher = PUBLISHER.read_text(encoding="utf-8")
        cls.importer = IMPORTER.read_text(encoding="utf-8")
        cls.assessment = ASSESSMENT.read_text(encoding="utf-8")

    def test_only_exact_white_pharoah_original_page_is_allowed(self):
        self.assertIn("225 => ['white-pharoah-radiology@7e', '24', 457]", self.publisher)
        self.assertIn("recovery-1398-radiology-q225-20261019", self.importer)
        self.assertIn("$stem === 'radiology-q219-q226-20261012' && $expected === 1", self.importer)
        self.assertIn("$subject === 'oral-radiology' && $stem === 'radiology-q219-q226-20261012'", self.assessment)

    def test_writer_remains_source_only_and_preserves_the_stored_answer(self):
        statements = [line for line in self.publisher.splitlines()
                      if "INSERT INTO bank_question_sources" in line]
        self.assertEqual(len(statements), 1)
        self.assertIn("actual !== $original[$n]['answer']", self.publisher)
        self.assertIn("A stem, choice or answer changed in the study.", self.publisher)
        self.assertIn("v.is_official=1", self.publisher)
        self.assertIn("scope_chapters", self.publisher)


if __name__ == "__main__":
    unittest.main()
