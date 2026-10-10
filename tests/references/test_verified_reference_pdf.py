"""Synthetic PDF reader checks; no book text or temporary text files."""
import hashlib
import importlib.util
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


if __name__ == "__main__":
    unittest.main()
