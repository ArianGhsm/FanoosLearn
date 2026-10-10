"""Narrow 1398 orthodontics source row repair safety regression.

The production preview independently verifies real DB state; static checks
here guard the reviewable mutation boundary without containing private books.
"""
from pathlib import Path
import re
import unittest

FILE = Path(__file__).resolve().parents[2] / "scripts/references/correct_1398_orthodontics_ai_sources.php"


class OrthodonticsExistingSourceGuardTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.body = FILE.read_text(encoding="utf-8")

    def test_dml_touches_only_existing_question_sources(self):
        targets = re.findall(r"(?<!FOR )(?:INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO)\s+(bank_[a-z_]+)\b",
                             self.body, flags=re.I | re.M)
        self.assertEqual([t.lower() for t in targets], ["bank_question_sources"])

    def test_no_human_or_already_sourced_other_year_override(self):
        for marker in ["si.exam_year=1398", "sb.subject_key='orthodontics'",
                       "r.reference_key='proffit-orthodontics'", "e.edition_key='5e'",
                       "v.is_official=1", "origin='ai' AND page IS NULL",
                       "Existing source no longer exactly matches old AI-only defect",
                       "count($candidate) !== 19", "scope_chapters"]:
            self.assertIn(marker, self.body)

    def test_content_and_answer_guard(self):
        for marker in ["$q['stem'] !== $original[$num]['stem']",
                       "$actualChoices !== $original[$num]['choices']",
                       "$actualAnswer !== $original[$num]['answer']",
                       "$batch['question_content_identical']", "hash_file('sha256'",
                       "FOR UPDATE"]:
            self.assertIn(marker, self.body)

    def test_apply_backup_rollback_receipt_guard(self):
        for marker in ["BackupManifest::verify", "database.sql",
                       "posix_geteuid()", "if ($apply) { $db->commit(); } else { $db->rollBack(); }",
                       "realpath(dirname($receiptFile))", "fopen($receiptFile, 'x')"]:
            self.assertIn(marker, self.body)


if __name__ == "__main__":
    unittest.main()
