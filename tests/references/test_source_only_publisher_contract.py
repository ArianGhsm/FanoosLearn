"""Guard production source-only publisher against accidental bank question writes."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
SERVICE = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
CLI = ROOT / "scripts/references/import_verified_sources.php"


class PublisherSafetyContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.service = SERVICE.read_text()
        cls.cli = CLI.read_text()

    def test_only_source_row_insert(self):
        statements = re.findall(r"(?:INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO)\s+([a-z_]+)",
                                self.service, flags=re.IGNORECASE | re.MULTILINE)
        self.assertEqual([s.lower() for s in statements], ["bank_question_sources"])

    def test_explicit_scope_gate_and_no_human_override(self):
        self.assertIn("v.is_official=1", self.service)
        self.assertIn("scope_chapters", self.service)
        self.assertIn("override_human", self.service)
        self.assertIn("already exists", self.service)
        self.assertIn("FOR UPDATE", self.service)

    def test_question_and_answer_preflight(self):
        for marker in ["question !== $original", "realChoices !== $original",
                       "actual !== $original", "status'] !== 'published'"]:
            self.assertIn(marker, self.service)

    def test_dry_run_rolls_back(self):
        self.assertIn("$db->rollBack();", self.service)
        self.assertIn("$db->commit();", self.service)
        self.assertIn("if ($apply)", self.service)

    def test_apply_requires_fresh_verified_backup_and_receipt(self):
        for marker in ["BackupManifest::verify", "database.sql",
                       "posix_geteuid", "research", "receipt",
                       "hash_file('sha256'", "year < 1399"]:
            self.assertIn(marker, self.cli)


if __name__ == "__main__":
    unittest.main()
