"""Lock the two audited 1398 Malamed 7e source pages and their current PDF map."""
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class OralSurgery1398MalamedSources(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.catalog = json.loads((ROOT / "data/bank/catalog.json").read_text())
        cls.maps = json.loads((ROOT / "data/bank/reference-chapter-pages.json").read_text())
        cls.publisher = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        cls.importer = (ROOT / "scripts/references/import_verified_sources.php").read_text()
        cls.assessment = (ROOT / "scripts/references/audit_published_assessment.php").read_text()

    def test_current_approved_pdf_hash_and_full_page_coverage_are_pinned(self):
        entry = self.maps["editions"]["malamed-medical-emergencies@7e"]
        self.assertEqual(entry["source_pdf_sha256"], "cc2ca3fb90039656483f6f2ddc22b482331613a9ab8889c79407f1e28baa184c")
        self.assertEqual(entry["pdf_pages"], 561)
        self.assertEqual(entry["runs"][0], [None, 1, 10])
        self.assertEqual(entry["runs"][1], ["1", 11, 24])
        self.assertEqual(entry["runs"][2], ["2", 25, 71])
        self.assertEqual(entry["runs"][11], ["11", 196, 217])
        self.assertEqual(entry["runs"][-1], [None, 532, 561])

    def test_both_sources_are_in_the_official_1398_oral_surgery_scope(self):
        validity = [row for row in self.catalog["validity"]
                    if row.get("exam_type") == "residency" and row.get("year") == 1398
                    and row.get("subject") == "oral-surgery"
                    and row.get("edition") == "malamed-medical-emergencies@7e"]
        self.assertEqual(len(validity), 1)
        self.assertTrue(validity[0]["official"])
        allowed = {str(chapter["number"]) for chapter in validity[0]["scope_chapters"]}
        self.assertTrue({"3", "11"} <= allowed)
        self.assertIn("123 => ['malamed-medical-emergencies@7e', '3', 110]", self.publisher)
        self.assertIn("124 => ['malamed-medical-emergencies@7e', '11', 211]", self.publisher)

    def test_coordinator_and_frozen_assessment_audits_are_exactly_scoped(self):
        self.assertIn("recovery-1398-oral-surgery-malamed7-20261021", self.importer)
        self.assertIn("$stem === 'surgery' && $expected === 2", self.importer)
        self.assertIn("$year === 1398 && $subject === 'oral-surgery' && $stem === 'surgery'", self.assessment)


if __name__ == "__main__":
    unittest.main()
