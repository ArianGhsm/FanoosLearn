"""Regression checks for PDF-page-only printed-label validation."""
import json
from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
VERIFIER = ROOT / "apps/platform/src/Bank/PrintedBookPageEvidence.php"
SOURCE_PUBLISHER = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
SOURCE_CLI = ROOT / "scripts/references/import_verified_sources.php"
PDF_BRIDGE = ROOT / "scripts/references/verified_reference_pdf.py"


@unittest.skipUnless(shutil.which("php"), "PHP binary required for functional page check")
class PrintedBookPageEvidenceTests(unittest.TestCase):
    def verified(self, pages, pdf_page=120, printed="102"):
        code = (
            "require $argv[1];"
            "$pages=json_decode($argv[2],true,32,JSON_THROW_ON_ERROR);"
            "echo Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::"
            "corroboratesPageLabels($pages,(int)$argv[3],$argv[4])?'1':'0';"
        )
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER), json.dumps(pages), str(pdf_page), printed],
            text=True, capture_output=True, check=True
        )
        return run.stdout == "1"

    def test_realistic_book_page_label_with_two_neighbors(self):
        pages = {page: [page - 18] for page in range(118, 123)}
        self.assertTrue(self.verified(pages))
        self.assertFalse(self.verified(pages, printed="103"))
        self.assertFalse(self.verified(pages, printed="120"))

    def test_rejects_single_neighbor_and_body_only_label(self):
        pages = {119: [101], 120: [102], 121: []}
        self.assertFalse(self.verified(pages))
        page_text = "Title\nfirst\nsecond\nA cited number: 102\nthird\nfourth\nfifth"
        code = (
            "require $argv[1];"
            "echo json_encode(Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::"
            "pageLabelsFromText($argv[2]));"
        )
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER), page_text],
            text=True, capture_output=True, check=True
        )
        self.assertEqual(json.loads(run.stdout), [])

    def test_rejects_missing_or_malformed_label(self):
        pages = {120: [102]}
        self.assertFalse(self.verified(pages, printed="102x"))
        self.assertFalse(self.verified(pages, printed="-1"))

    def test_quote_normalization_ignores_only_directional_format_controls(self):
        text = "س\u200c\u202bلام\u202c, x-ray"
        code = (
            "require $argv[1];"
            "$method=new ReflectionMethod(Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::class,'normalize');"
            "echo $method->invoke(null,$argv[2]);"
        )
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER), text],
            text=True, capture_output=True, check=True
        )
        self.assertEqual(run.stdout, "سلام, x-ray")

    def test_accepts_unlabelled_figure_page_only_with_exact_adjacent_sequence(self):
        self.assertTrue(self.verified({119: [101], 120: [], 121: [103]}, printed="102"))
        self.assertTrue(self.verified({119: [101], 120: [3, 4], 121: [103]}, printed="102"))
        self.assertFalse(self.verified({119: [101], 120: [], 121: [104]}, printed="102"))
        self.assertFalse(self.verified({119: [101], 120: [100], 121: [103]}, printed="102"))

    def test_numbered_chapter_title_running_header_exposes_printed_label(self):
        text = "14. Digital Imaging and Processing for Restorations          299\nbody"
        code = (
            "require $argv[1];"
            "echo json_encode(Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::"
            "pageLabelsFromText($argv[2]));"
        )
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER), text],
            text=True, capture_output=True, check=True
        )
        self.assertEqual(json.loads(run.stdout), [299])

    def test_watermark_then_spaced_chapter_header_exposes_printed_label(self):
        text = "www.example.invalid\n44      C H A P T E R 2     Dental Caries\nbody"
        code = (
            "require $argv[1];"
            "echo json_encode(Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::"
            "pageLabelsFromText($argv[2]));"
        )
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER), text],
            text=True, capture_output=True, check=True
        )
        self.assertEqual(json.loads(run.stdout), [44])

    def test_table_zero_is_not_a_printed_page_label(self):
        code = (
            "require $argv[1];"
            "echo json_encode(["
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::hasNoPrintedPageLabels([0]),"
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::hasNoPrintedPageLabels([0, 221]),"
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::hasNoPrintedPageLabels(['736.e2'])"
            "]);"
        )
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER)],
            text=True, capture_output=True, check=True
        )
        self.assertEqual(json.loads(run.stdout), [True, False, False])

    def test_long_running_header_needs_neighbor_corroboration(self):
        long_header = "Long running section title " * 5 + "549"
        code = (
            "require $argv[1];"
            "echo json_encode(["
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::pageLabelsFromText($argv[2]),"
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::pageLabelsFromText($argv[2],true)"
            "]);"
        )
        run = subprocess.run(
            ["php", "-r", code, str(VERIFIER), long_header],
            text=True, capture_output=True, check=True
        )
        self.assertEqual(json.loads(run.stdout), [[], [549]])
        self.assertTrue(self.verified({1180: [547], 1181: [548], 1182: [549],
                                      1183: [550], 1184: [551]},
                                     pdf_page=1182, printed="549"))


class PdfOnlySourceWriterContracts(unittest.TestCase):
    def test_writer_rechecks_printed_page_from_exact_verified_pdf(self):
        writer = SOURCE_PUBLISHER.read_text()
        cli = SOURCE_CLI.read_text()
        for marker in [
            "PrintedBookPageEvidence::corroborates(",
            "|| !$validPage",
            "v.is_official=1",
            "scope_chapters",
            "override_human",
            "FOR UPDATE",
            "$db->rollBack();",
            "$db->commit();",
        ]:
            self.assertIn(marker, writer)
        self.assertIn("hash_file('sha256', $studyPath)", cli)
        self.assertIn("hash_file('sha256', $validatedPath)", cli)
        self.assertIn("hash_file('sha256', $decisionsPath)", cli)
        self.assertIn("BackupManifest::verify", cli)
        self.assertNotIn("referenceTextRoot", writer)

    def test_php_reads_one_page_through_verified_pdf_bridge_without_files(self):
        helper = VERIFIER.read_text()
        bridge = PDF_BRIDGE.read_text()
        self.assertIn("verified_reference_pdf.py", helper)
        self.assertIn("'--page'", helper)
        self.assertIn("'--require-map'", helper)
        self.assertIn("'--expected-sha256'", helper)
        self.assertIn("parser.add_argument(\"--page\"", bridge)
        self.assertIn("sys.stdout.write(pdf.page_text(args.page))", bridge)
        self.assertNotIn(".txt", helper)
        self.assertNotIn("file_get_contents($path)", helper)


if __name__ == "__main__":
    unittest.main()
