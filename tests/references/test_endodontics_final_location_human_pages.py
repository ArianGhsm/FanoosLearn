"""Endodontics 1398 official-book location and 1404 human-page-only contracts."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
PUBLISH = (ROOT / "scripts/references/import_verified_sources.php").read_text()
AUDIT = (ROOT / "scripts/references/audit_published_assessment.php").read_text()
HUMAN = (ROOT / "scripts/references/complete_endodontics_1404_human_pages.php").read_text()


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

    def test_1404_updates_only_human_page_with_exact_book_evidence(self):
        updates = re.findall(r"UPDATE\s+([a-z_]+)\s+SET\s+([a-z_]+)",
                             HUMAN, flags=re.I)
        self.assertEqual(updates, [("bank_question_sources", "page")])
        self.assertIn("page IS NULL AND origin='human'", HUMAN)
        self.assertIn("si.exam_year=1404", HUMAN)
        self.assertIn("q.number_in_sitting=:number", HUMAN)
        self.assertIn("e.edition_key", HUMAN)
        self.assertIn("torabinejad-endodontics", HUMAN)
        self.assertIn("'book_text_sha256' => $bookSha", HUMAN)
        self.assertIn("BackupManifest::verify", HUMAN)
        self.assertIn("$db->rollBack()", HUMAN)
        self.assertIn("$db->commit()", HUMAN)
        self.assertIn("fopen($receiptPath, 'x')", HUMAN)
        self.assertIn("original_human_origin_preserved", HUMAN)
        self.assertIn("pdf 246", HUMAN)
        self.assertIn("pdf 281", HUMAN)


if __name__ == "__main__":
    unittest.main()
