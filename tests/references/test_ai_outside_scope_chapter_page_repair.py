"""Contract tests for guarded AI outside-scope chapter and page repairs."""
from pathlib import Path
import re
import unittest


ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_ai_outside_scope_chapter_pages.php"
GUARD = ROOT / "apps/platform/src/Bank/VerifiedAiOutsideScopeChapterPageGuard.php"


class AiOutsideScopeChapterPageRepairContract(unittest.TestCase):
    def test_write_is_limited_to_source_metadata_and_audited_confidence(self):
        source = OPERATOR.read_text()
        updates = re.findall(r"UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE", source, re.I | re.S)
        self.assertEqual(len(updates), 1)
        fields = re.findall(r"([a-z0-9_]+)\s*=", updates[0], re.I)
        self.assertEqual(fields, [
            "node_id", "page", "printed_page", "pdf_page", "pdf_sha256",
            "official_scope_status", "official_scope_note", "confidence_page",
        ])
        self.assertIn("origin='ai'", source)
        self.assertIn("reviewed_at IS NULL", source)
        self.assertIn("reviewed_by_user_id IS NULL", source)
        self.assertIn("confidence_page=:old_confidence", source)
        self.assertNotIn("UPDATE bank_questions", source)
        self.assertNotIn("UPDATE bank_question_choices", source)
        self.assertNotIn("UPDATE bank_official_answers", source)

    def test_exact_audit_scope_page_backup_and_transaction_are_required(self):
        source = OPERATOR.read_text()
        guard = GUARD.read_text()
        for marker in [
            "expected_stem_sha256", "expected_choices_sha256", "expected_answer_sha256",
            "expected_anchor_sha256", "expected_old_confidence_page", "new_confidence_page",
            "VerifiedAiOutsideScopeChapterPageGuard::auditMatches",
            "VerifiedAiOutsideScopeChapterPageGuard::chapterIsOutOfScope",
            "PrintedBookPageEvidence::corroborates", "PrintedBookPageEvidence::pageContainsEvidence",
            "BackupManifest::verify", "$db->rollBack();", "$db->commit();",
            "خارج از محدوده", "'attempts_changed'=>0",
        ]:
            self.assertIn(marker, source)
        self.assertIn("0.85", source)
        self.assertIn("count($plan['entries']) > 12", source)
        self.assertIn("scientifically_accepted", guard)
        self.assertIn("confidence_after_reaudit", guard)
        self.assertIn("partial official year scope", guard)


if __name__ == "__main__":
    unittest.main()
