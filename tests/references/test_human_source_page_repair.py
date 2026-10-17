"""Source-only metadata repair must preserve human citations and reviews."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPERATOR = ROOT / "scripts/references/repair_verified_human_source_pages.php"


class HumanSourcePageRepairContract(unittest.TestCase):
    def test_update_is_limited_to_pdf_page_and_hash(self):
        source = OPERATOR.read_text()
        updates = re.findall(r"UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE", source, re.I | re.S)
        self.assertEqual(len(updates), 1)
        fields = re.findall(r"([a-z0-9_]+)\s*=", updates[0], re.I)
        self.assertEqual(fields, ["pdf_page", "pdf_sha256"])
        self.assertIn("AND origin='human'", source)
        self.assertIn("reviewed_at <=> :old_reviewed", source)
        self.assertIn("reviewed_by_user_id <=> :old_reviewer", source)

    def test_full_question_key_choice_page_and_official_scope_are_rechecked(self):
        source = OPERATOR.read_text()
        for marker in [
            "expected_stem_sha256", "expected_choices_sha256", "expected_answer_sha256",
            "expected_anchor_sha256", "scope_chapters", "v.is_official",
            "source_pdf_sha256", "PrintedBookPageEvidence::pageContainsEvidence",
            "BackupManifest::verify", "$db->rollBack();", "$db->commit();",
            "fanoos.human-source-pdf-page-audit/1", "research_only", "$draftVoided",
            "$matches[0]['expected_question_status']", "$matches[0]['expected_answer_status']",
        ]:
            self.assertIn(marker, source)
        self.assertIn("human_source_provenance_changed'=>0", source)
        self.assertIn("human_review_metadata_changed'=>0", source)
        self.assertIn("count($evidenceQuotes) > 4", source)
        self.assertIn("PrintedBookPageEvidence::pageContainsEvidence($edition,$page,$evidenceQuotes,$sha)", source)


if __name__ == "__main__":
    unittest.main()
