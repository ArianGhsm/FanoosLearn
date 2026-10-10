"""Synthetic PDF reader checks; no book text or temporary text files."""
import hashlib
import importlib.util
import io
import json
from pathlib import Path
from tempfile import TemporaryDirectory
import sys
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location(
    "verified_reference_pdf", HERE / "scripts/references/verified_reference_pdf.py"
)
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)


class VerifiedReferencePdfTests(unittest.TestCase):
    def test_contiguous_full_book_map(self):
        mapping = {"editions": {"book@1e": {"runs": [
            [None, 1, 2], ["1", 3, 5], ["2", 6, 7]
        ]}}}
        self.assertEqual(module.chapter_coverage(mapping, "book@1e", 7),
                         {"mapped_chapters": 2, "mapped_pages": 7})
        with self.assertRaisesRegex(ValueError, "cover"):
            module.chapter_coverage(mapping, "book@1e", 8)

    def test_gap_is_refused(self):
        mapping = {"editions": {"book@1e": {"runs": [[None, 1, 2], ["1", 4, 7]]}}}
        with self.assertRaisesRegex(ValueError, "gap"):
            module.chapter_coverage(mapping, "book@1e", 7)

    def test_source_and_sha_are_verified(self):
        with TemporaryDirectory() as base:
            root = Path(base)
            source = root / "private" / "a.pdf"
            source.parent.mkdir()
            source.write_bytes(b"%PDF-" + b"synthetic")
            checksum = hashlib.sha256(source.read_bytes()).hexdigest()
            self.assertEqual(module.verify_file(root, "private/a.pdf", checksum, source.stat().st_size), source)
            with self.assertRaisesRegex(ValueError, "not in protected"):
                module.verify_file(root, "public/a.pdf", checksum, source.stat().st_size)
            with self.assertRaisesRegex(ValueError, "SHA-256"):
                module.verify_file(root, "private/a.pdf", "0" * 64, source.stat().st_size)

    def test_only_requested_pdf_page_is_sent_to_stdout_and_no_text_file_is_created(self):
        with TemporaryDirectory() as base:
            root = Path(base)
            source = root / "synthetic.pdf"
            source.write_bytes(b"%PDF- synthetic fixture")
            pdf = module.VerifiedReferencePdf("book@1e", source, "a" * 64, source.stat().st_size, 4)
            fake = type("Result", (), {"stdout": b"CHAPTER 1\nSynthetic page content.\n\f"})()
            with patch.object(module.subprocess, "run", return_value=fake) as run:
                self.assertEqual(pdf.page_text(2), "CHAPTER 1\nSynthetic page content.")
            command = run.call_args.args[0]
            self.assertEqual(command[command.index("-f") + 1], "2")
            self.assertEqual(command[command.index("-l") + 1], "2")
            self.assertEqual(command[-1], "-")
            self.assertEqual(sorted(path.name for path in root.iterdir()), ["synthetic.pdf"])

    def test_page_range_is_checked_before_poppler_runs(self):
        pdf = module.VerifiedReferencePdf("book@1e", Path("unused.pdf"), "a" * 64, 100, 2)
        with patch.object(module.subprocess, "run") as run:
            with self.assertRaisesRegex(ValueError, "outside"):
                pdf.page_text(3)
            run.assert_not_called()

    def test_cli_emits_one_page_only_for_the_hash_bound_map(self):
        class FakePdf:
            edition = "proffit-orthodontics@6e"
            source_sha256 = "5f18cc196553b691635b0c136f9761a4e7c478bf115424d1c7f26f04b5b50015"
            source_bytes = 123
            page_count = 746

            def bookmarks(self):
                return []

            def page_text(self, number):
                self.requested = number
                return "Synthetic single PDF page\n"

        class Capture(io.StringIO):
            def reconfigure(self, **_options):
                pass

        pdf = FakePdf()
        output = Capture()
        argv = ["verified_reference_pdf.py", "--edition", pdf.edition,
                "--page", "491", "--require-map",
                "--expected-sha256", pdf.source_sha256]
        with patch.object(module, "open_verified_reference", return_value=pdf), \
             patch.object(module.sys, "argv", argv), \
             patch.object(module.sys, "stdout", output):
            self.assertEqual(module.main(), 0)
        self.assertEqual(pdf.requested, 491)
        self.assertEqual(output.getvalue(), "Synthetic single PDF page\n")

        rejected_output = Capture()
        argv = ["verified_reference_pdf.py", "--edition", pdf.edition,
                "--page", "491", "--require-map",
                "--expected-sha256", "0" * 64]
        with patch.object(module, "open_verified_reference", return_value=pdf), \
             patch.object(module.sys, "argv", argv), \
             patch.object(module.sys, "stdout", rejected_output):
            with self.assertRaisesRegex(ValueError, "differs from the pinned"):
                module.main()
        self.assertEqual(rejected_output.getvalue(), "")

    def test_proffit_sixth_edition_boundaries_follow_its_pdf_pages(self):
        mapping = json.loads((HERE / "data/bank/reference-chapter-pages.json").read_text(encoding="utf-8"))
        edition = mapping["editions"]["proffit-orthodontics@6e"]
        self.assertEqual(edition["runs"][:3], [[None, 1, 11], ["1", 12, 27], ["2", 28, 69]])
        self.assertEqual(edition["runs"][-2:], [["20", 667, 719], [None, 720, 746]])
        self.assertEqual(module.chapter_coverage(mapping, "proffit-orthodontics@6e", 746),
                         {"mapped_chapters": 20, "mapped_pages": 746})
        self.assertRegex(edition["source_pdf_sha256"], r"^[a-f0-9]{64}$")


if __name__ == "__main__":
    unittest.main()
