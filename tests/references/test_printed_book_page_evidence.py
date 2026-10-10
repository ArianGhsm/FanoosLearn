"""Regression tests for safe original-book printed-page corroboration."""
from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
VERIFIER = ROOT / "apps/platform/src/Bank/PrintedBookPageEvidence.php"
SOURCE_PUBLISHER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
SOURCE_CLI = ROOT / "scripts/references/import_verified_sources.php"


@unittest.skipUnless(shutil.which("php"), "PHP binary required for functional page check")
class PrintedBookPageEvidenceTests(unittest.TestCase):
    def verified(self, raw, pdf_page=120, printed="102"):
        code = (
            "require $argv[1];"
            "$raw=base64_decode($argv[2],true);"
            "echo Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::"
            "corroboratesPageMarkedText($raw,(int)$argv[3],$argv[4])?'1':'0';"
        )
        import base64
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER),
             base64.b64encode(raw.encode("utf-8")).decode("ascii"),
             str(pdf_page), printed],
            text=True, capture_output=True, check=True
        )
        return run.stdout == "1"

    def test_realistic_book_page_label_with_two_neighbors(self):
        pages = "".join(
            f"=== PAGE {n} ===\nSome book paragraph.\n{n-18}\n"
            for n in range(118, 123)
        )
        self.assertTrue(self.verified(pages))
        self.assertFalse(self.verified(pages, printed="103"))
        self.assertFalse(self.verified(pages, printed="120"))

    def test_rejects_single_neighbor(self):
        pages = (
            "=== PAGE 119 ===\n101\n"
            "=== PAGE 120 ===\n102\n"
            "=== PAGE 121 ===\nno matching label here\n"
        )
        self.assertFalse(self.verified(pages))

    def test_rejects_body_only_number(self):
        pages = "".join(
            f"=== PAGE {n} ===\nTitle\nfirst\nsecond\n"
            f"A cited number: {n-18}\nthird\nfourth\nfifth\n"
            for n in range(118, 123)
        )
        self.assertFalse(self.verified(pages))

    def test_refuses_missing_target_and_malformed_page(self):
        self.assertFalse(self.verified("=== PAGE 119 ===\n101\n"))
        self.assertFalse(self.verified("=== PAGE 120 ===\n102\n", printed="102x"))
        self.assertFalse(self.verified("=== PAGE 120 ===\n102\n", printed="-1"))


class SourceWriterPrintedPageContracts(unittest.TestCase):
    def test_guarded_fallback_does_not_replace_exact_decision(self):
        writer = SOURCE_PUBLISHER.read_text()
        cli = SOURCE_CLI.read_text()
        for marker in [
            "PrintedBookPageEvidence::corroborates",
            "$referenceTextRoot !== null",
            "|| !$validPage",
            "v.is_official=1",
            "scope_chapters",
            "override_human",
            "FOR UPDATE",
            "$db->rollBack();",
            "$db->commit();",
        ]:
            self.assertIn(marker, writer)
        self.assertIn("$inputRoot,", cli)
        self.assertIn("hash_file('sha256', $studyPath)", cli)
        self.assertIn("hash_file('sha256', $validatedPath)", cli)
        self.assertIn("hash_file('sha256', $decisionsPath)", cli)
        self.assertIn("BackupManifest::verify", cli)

    def test_helper_limits_book_text_to_protected_research(self):
        helper = VERIFIER.read_text()
        for marker in [
            "realpath('/srv/fanoos/shared/research')",
            "str_starts_with($path, $research . '/')",
            "filesize($path) > 15000000",
            "return $neighbors >= 2;",
            "in_array($printed, self::labels",
        ]:
            self.assertIn(marker, helper)


if __name__ == "__main__":
    unittest.main()
