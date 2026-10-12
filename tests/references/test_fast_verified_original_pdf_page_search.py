"""Fast original-PDF single-page streaming search tests."""
import sys
import types
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts" / "references"))
from find_in_books import iter_original_pdf_pages


class FakePDF:
    source = Path("/private/exact-original.pdf")
    page_count = 2
    def iter_page_texts(self):
        yield 1, "one"
        yield 2, "two"


class FakeDoc:
    page_count = 2
    def __enter__(self): return self
    def __exit__(self, *args): return False
    def __getitem__(self, index):
        return types.SimpleNamespace(get_text=lambda *args, **kwargs: str(index + 1))


class VerifiedFastScanTest(unittest.TestCase):
    def test_standard_engine(self):
        self.assertEqual(list(iter_original_pdf_pages(FakePDF())), [(1, "one"), (2, "two")])

    def test_fast_engine_one_page_at_a_time(self):
        with patch.dict(sys.modules, {"pymupdf": types.SimpleNamespace(open=lambda _: FakeDoc())}):
            self.assertEqual(list(iter_original_pdf_pages(FakePDF(), "pymupdf")), [(1, "1"), (2, "2")])

    def test_fast_engine_rejects_page_count_mismatch(self):
        class Wrong(FakeDoc): page_count = 3
        with patch.dict(sys.modules, {"pymupdf": types.SimpleNamespace(open=lambda _: Wrong())}):
            with self.assertRaisesRegex(ValueError, "page count"):
                list(iter_original_pdf_pages(FakePDF(), "pymupdf"))

    def test_other_engine_rejected(self):
        with self.assertRaises(ValueError):
            list(iter_original_pdf_pages(FakePDF(), "fake"))

if __name__ == "__main__":
    unittest.main()
