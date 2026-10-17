"""Chapter-and-page correction stays source-only on human-origin citations."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_human_source_chapter_pages.php"
GUARD = ROOT / "apps/platform/src/Bank/VerifiedHumanChapterPageRepairGuard.php"


class HumanSourceChapterPageRepairContract(unittest.TestCase):
    def test_update_is_limited_to_chapter_and_pdf_binding(self):
        source = OPERATOR.read_text()
        updates = re.findall(r"UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE", source, re.I | re.S)
        self.assertEqual(len(updates), 1)
        fields = re.findall(r"([a-z0-9_]+)\s*=", updates[0], re.I)
        self.assertEqual(fields, ["node_id", "pdf_page", "pdf_sha256"])
        self.assertIn("AND origin='human'", source)
        self.assertIn("reviewed_at <=> :old_reviewed", source)
        self.assertIn("reviewed_by_user_id <=> :old_reviewer", source)
        self.assertNotIn("SET printed_page", source)
        self.assertNotIn("UPDATE bank_questions", source)
        self.assertNotIn("UPDATE bank_official_answers", source)

    def test_exact_question_audit_scope_page_map_and_backup_are_rechecked(self):
        source = OPERATOR.read_text()
        guard = GUARD.read_text()
        for marker in [
            "expected_stem_sha256", "expected_choices_sha256", "expected_answer_sha256",
            "expected_anchor_sha256", "scope_chapters", "v.is_official",
            "VerifiedHumanChapterPageRepairGuard::auditMatches",
            "VerifiedHumanChapterPageRepairGuard::scopeAllows",
            "VerifiedHumanChapterPageRepairGuard::pageInMap",
            "PrintedBookPageEvidence::pageContainsEvidence", "BackupManifest::verify",
            "$db->rollBack();", "$db->commit();", "human_source_provenance_changed'=>0",
            "human_review_metadata_changed'=>0", "count($evidence) > 4",
        ]:
            self.assertIn(marker, source)
        self.assertIn("source_pdf_sha256", guard)
        self.assertIn("VerifiedHumanChapterPageRepairGuard::pageInMap", source)


if __name__ == "__main__":
    unittest.main()
