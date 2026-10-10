"""Regression contract for the narrowly authorized 1398 prosthodontics source-only gate."""
from pathlib import Path
import re
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
CLI = ROOT / "scripts/references/import_verified_sources.php"
PUBLISHER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
ASSESSMENT = ROOT / "scripts/references/audit_published_assessment.php"


class Prosthodontics1398SourceGateTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.cli = CLI.read_text(encoding="utf-8")
        cls.publisher = PUBLISHER.read_text(encoding="utf-8")
        cls.assessment = ASSESSMENT.read_text(encoding="utf-8")

    def test_1398_cli_exact_subject_and_sitting_stem(self):
        self.assertIn("$year === 1398 && $subject === 'prosthodontics' && $stem === 'prosthodontics'", self.cli)
        self.assertIn("$year < 1399", self.cli)
        self.assertIn("BackupManifest::verify", self.cli)
        self.assertIn("hash_file('sha256', $studyPath)", self.cli)

    def test_1398_frozen_snapshot_preflight_and_post_audit(self):
        self.assertIn("$year === 1398 && $subject === 'prosthodontics' && $stem === 'prosthodontics'", self.assessment)
        self.assertIn("question_content_identical", self.assessment)
        self.assertIn("Non-citation frozen assessment content changed", self.assessment)
        self.assertIn("$year < 1399", self.assessment)

    def test_three_and_only_three_official_1398_prosthodontic_editions(self):
        scope = re.search(
            r"if \(\$year === 1398 && \$subject === 'prosthodontics'\s*"
            r"&& !in_array\(\(string\) \$decision\['edition'\], \[(.*?)\], true\)\)",
            self.publisher,
            re.DOTALL,
        )
        self.assertIsNotNone(scope)
        self.assertEqual(set(re.findall(r"'([^']+)'", scope.group(1))), {
            "shillingburg-fixed@4e",
            "mccracken-rpd@12e",
            "zarb-edentulous@13e",
        })

    def test_source_only_no_mutation_and_scope_checks_unchanged(self):
        self.assertIn("v.is_official=1", self.publisher)
        self.assertIn("scope_chapters", self.publisher)
        self.assertIn("if (!in_array($src['chapter'], $numbers, true))", self.publisher)
        self.assertIn("Source already exists; reviewed sources are immutable", self.publisher)
        self.assertIn("A stem, choice or answer changed", self.publisher)
        statements = re.findall(
            r"(?<!FOR )(?:INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO)\s+([a-z_]+)",
            self.publisher,
            flags=re.IGNORECASE,
        )
        self.assertEqual([statement.lower() for statement in statements], ["bank_question_sources"])

    @unittest.skipUnless(shutil.which("php"), "PHP not installed in test environment")
    def test_php_lint_all_three_gates(self):
        for path in (CLI, PUBLISHER, ASSESSMENT):
            with self.subTest(path=str(path)):
                proc = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
                self.assertEqual(proc.returncode, 0, proc.stderr)


if __name__ == "__main__":
    unittest.main()
