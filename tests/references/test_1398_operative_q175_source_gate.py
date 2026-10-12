"""Only an exact audited 1398 Summit 4e Q175 page may be source-linked."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
IMPORTER = (ROOT / "scripts/references/import_verified_sources.php").read_text()
AUDITOR = (ROOT / "scripts/references/audit_private_study_batches.py").read_text()
PUBLISHER = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()


class Operative1398SourceGate(unittest.TestCase):
    def test_exact_single_batch_and_decision_snapshot(self):
        self.assertIn("$subject === 'operative-dentistry' && $stem === 'operative-q175-20261012'", IMPORTER)
        self.assertIn("classification/decisions/1398-operative-q175-20261012.json", IMPORTER)
        self.assertIn('(1398, "operative-dentistry", "operative-q175-20261012")', AUDITOR)
        self.assertIn("1398-operative-q175-20261012.json", AUDITOR)

    def test_exact_official_reference_chapter_and_pdf_page(self):
        for guard in [
            "$subject === 'operative-dentistry'",
            "$n !== 175",
            "$decision['edition'] !== 'summit-operative@4e'",
            "(string) $decision['chapter'] !== '6'",
            "$decision['page'] !== 294",
            "1398 operative source gate permits only audited Summit 4e Q175",
            "v.is_official=1",
            "scope_chapters",
        ]:
            self.assertIn(guard, PUBLISHER)

    def test_only_source_only_atomic_changes_and_full_backup(self):
        self.assertIn("INSERT INTO bank_question_sources", PUBLISHER)
        self.assertIn("already exists", PUBLISHER)
        self.assertIn("$db->rollBack()", PUBLISHER)
        self.assertIn("$db->commit()", PUBLISHER)
        self.assertIn("BackupManifest::verify", IMPORTER)
        self.assertIn("question_content_identical", IMPORTER)
        self.assertIn("hash_file('sha256', $validatedPath)", IMPORTER)


if __name__ == "__main__":
    unittest.main()
