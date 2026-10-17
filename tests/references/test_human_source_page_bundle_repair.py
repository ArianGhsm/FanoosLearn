"""Page bundles update one human source and add only secondary source rows."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_human_source_page_bundle.php"


class HumanSourcePageBundleRepairContract(unittest.TestCase):
    def test_existing_human_row_update_and_secondary_insert_are_guarded(self):
        source = OPERATOR.read_text()
        updates = re.findall(r"UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE", source, re.I | re.S)
        inserts = re.findall(r"INSERT\s+INTO\s+bank_question_sources\s*\((.+?)\)\s*VALUES", source, re.I | re.S)
        self.assertEqual(len(updates), 1)
        self.assertEqual(re.findall(r"([a-z0-9_]+)\s*=", updates[0], re.I), ["node_id", "pdf_page", "pdf_sha256"])
        self.assertEqual(len(inserts), 1)
        self.assertIn("origin='human'", source)
        self.assertIn("FOR UPDATE", source)
        self.assertIn("count($sourceIds)!==1", source)
        self.assertIn("'origin'=>'ai'", source)
        self.assertIn("'is_primary'=>false", source)
        self.assertNotIn("UPDATE bank_questions", source)
        self.assertNotIn("UPDATE bank_official_answers", source)

    def test_each_page_and_immutable_fingerprint_is_rechecked(self):
        source = OPERATOR.read_text()
        for marker in [
            "VerifiedHumanSourcePageBundleGuard::auditMatches",
            "VerifiedHumanChapterPageRepairGuard::scopeAllows",
            "VerifiedHumanChapterPageRepairGuard::pageInMap",
            "PrintedBookPageEvidence::pageContainsEvidence", "expected_stem_sha256",
            "expected_choices_sha256", "expected_answer_sha256", "expected_anchor_sha256",
            "BackupManifest::verify", "$db->rollBack();", "$db->commit();",
            "human_source_provenance_changed'=>0", "human_review_metadata_changed'=>0",
        ]:
            self.assertIn(marker, source)
        self.assertIn("count($pageItem['evidence'])>4", source.replace(" ", ""))
        self.assertIn("secondary_source_rows_planned", source)


if __name__ == "__main__":
    unittest.main()
