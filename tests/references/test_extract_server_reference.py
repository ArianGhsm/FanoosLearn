"""Synthetic checks for protected-server PDF extraction; no real book content."""
import hashlib
import importlib.util
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest

HERE = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location(
    "extract_server_reference", HERE / "scripts/references/extract_server_reference.py"
)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class PrivateBookExtractionTests(unittest.TestCase):
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

    def test_proffit_sixth_edition_frontmatter_boundary(self):
        import json
        mapping = json.loads((HERE / "data/bank/reference-chapter-pages.json").read_text(encoding="utf-8"))
        runs = mapping["editions"]["proffit-orthodontics@6e"]["runs"]
        self.assertEqual(runs[:3], [[None, 1, 11], ["1", 12, 27], ["2", 28, 69]])
        self.assertEqual(runs[-2:], [["20", 667, 719], [None, 720, 746]])
        self.assertEqual(module.chapter_coverage(mapping, "proffit-orthodontics@6e", 746),
                         {"mapped_chapters": 20, "mapped_pages": 746})

    def test_shillingburg_pdf_chapter_boundaries(self):
        import json
        mapping = json.loads((HERE / "data/bank/reference-chapter-pages.json").read_text())
        runs = mapping["editions"]["shillingburg-fixed@4e"]["runs"]
        self.assertEqual(module.chapter_coverage(mapping, "shillingburg-fixed@4e", 585),
                         {"mapped_chapters": 29, "mapped_pages": 585})
        self.assertIn([None, 1, 1], runs)
        self.assertIn([None, 55, 55], runs)
        self.assertIn(["8", 110, 141], runs)
        self.assertIn(["9", 142, 159], runs)

    def test_page_markers(self):
        result = module.create_text("book@1e", ["hello", "world"], "a" * 64)
        self.assertIn("=== PAGE 1 ===\nhello", result)
        self.assertIn("=== PAGE 2 ===\nworld", result)

    def test_private_source_and_sha(self):
        with TemporaryDirectory() as base:
            root = Path(base)
            src = root / "private" / "a.bin"
            src.parent.mkdir()
            src.write_bytes(b"%PDF-" + b"mock")
            checksum = hashlib.sha256(src.read_bytes()).hexdigest()
            self.assertEqual(module.verify_file(root, "private/a.bin", checksum, src.stat().st_size), src)
            with self.assertRaisesRegex(ValueError, "not in protected"):
                module.verify_file(root, "public/a.bin", checksum, src.stat().st_size)
            with self.assertRaisesRegex(ValueError, "SHA-256"):
                module.verify_file(root, "private/a.bin", "0" * 64, src.stat().st_size)

    def test_atomic_text(self):
        with TemporaryDirectory() as base:
            target = Path(base) / "references" / "book@1e.txt"
            module.atomic_text(target, "original\n")
            self.assertEqual(target.read_text(), "original\n")
            self.assertEqual(target.stat().st_mode & 0o777, 0o600)


if __name__ == "__main__":
    unittest.main()
