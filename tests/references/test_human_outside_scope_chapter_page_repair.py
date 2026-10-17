"""Outside-scope source correction preserves the human question/key state."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_human_outside_scope_chapter_pages.php"
GUARD = ROOT / "apps/platform/src/Bank/VerifiedHumanOutsideScopeChapterPageGuard.php"


class HumanOutsideScopeChapterPageRepairContract(unittest.TestCase):
    def test_write_is_source_metadata_only_and_requires_human_origin(self):
        source = OPERATOR.read_text()
        updates = re.findall(r"UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE", source, re.I | re.S)
        self.assertEqual(len(updates), 1)
        fields = re.findall(r"([a-z0-9_]+)\s*=", updates[0], re.I)
        self.assertEqual(fields, [
            "node_id", "page", "printed_page", "pdf_page", "pdf_sha256",
            "official_scope_status", "official_scope_note",
        ])
        self.assertIn("origin='human'", source)
        self.assertIn("reviewed_at <=> :old_reviewed", source)
        self.assertIn("reviewed_by_user_id <=> :old_reviewer", source)
        self.assertNotIn("UPDATE bank_questions", source)
        self.assertNotIn("UPDATE bank_official_answers", source)

    def test_evidence_scope_print_label_and_backup_are_guarded(self):
        source = OPERATOR.read_text()
        guard = GUARD.read_text()
        for marker in [
            "expected_stem_sha256", "expected_choices_sha256", "expected_answer_sha256",
            "expected_anchor_sha256", "v.is_official", "chapterIsOutOfScope",
            "VerifiedHumanChapterPageRepairGuard::pageInMap",
            "PrintedBookPageEvidence::corroborates", "PrintedBookPageEvidence::pageContainsEvidence",
            "BackupManifest::verify", "$db->rollBack();", "$db->commit();",
            "خارج از محدوده آزمون", "'human_source_provenance_changed'=>0", "'human_review_metadata_changed'=>0",
        ]:
            self.assertIn(marker, source)
        self.assertIn("count($evidence)>4", source.replace(" ", ""))
        self.assertIn("partial official scope", guard)


if __name__ == "__main__":
    unittest.main()
