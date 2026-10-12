"""Official 1398 chapter boundaries must be physical pages from the original PDFs."""
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
MANIFEST = json.loads((ROOT / "data/bank/reference-pdfs.json").read_text())
MAPS = json.loads((ROOT / "data/bank/reference-chapter-pages.json").read_text())
CATALOG = json.loads((ROOT / "data/bank/catalog.json").read_text())
BUILDER = (ROOT / "scripts/references/build_chapter_pages.py").read_text()

BOOKS = {
    "mcdonald-avery-pediatric@10e": (
        "pediatric-dentistry", 710, 693,
        "33e3bad50e6cb5a595bd05f1dc50ef602a565191964f2af2551e96efc761bf8b",
        [11,27,49,90,97,120,130,148,165,187,195,216,231,253,284,296,313,
         338,359,385,400,425,489,508,523,550,573,613,637,655,663],
    ),
    "white-pharoah-radiology@7e": (
        "oral-radiology", 697, 670,
        "6fec290f733e5282284a722eeb44f3a99b35edd350711b683c87f103108a6169",
        [18,33,46,58,80,101,108,148,170,183,202,216,231,246,267,276,288,
         302,316,331,351,376,419,444,469,489,509,541,559,579,599,629,647,663],
    ),
}


class BoundOriginal1398ReferenceMaps(unittest.TestCase):
    def test_original_editions_have_full_source_bound_page_runs(self):
        for name, (subject, total, index, sha, starts) in BOOKS.items():
            with self.subTest(name=name):
                manifest = MANIFEST["editions"][name]
                mapped = MAPS["editions"][name]
                self.assertEqual(manifest["pdf"], "verified-server-pdf")
                self.assertEqual(mapped["source_pdf_sha256"], sha)
                self.assertEqual(manifest["chapter_pdf_starts"],
                                 {str(i): page for i, page in enumerate(starts, 1)})
                self.assertEqual(manifest["backmatter_pdf_start"], index)
                expected = [[None, 1, starts[0] - 1]]
                expected += [[str(i + 1), p, (starts[i + 1] - 1
                             if i + 1 < len(starts) else index - 1)]
                             for i, p in enumerate(starts)]
                expected.append([None, index, total])
                self.assertEqual(mapped["runs"], expected)
                self.assertEqual(
                    [v["subject"] for v in CATALOG["validity"]
                     if v["exam_type"] == "residency"
                     and v["year"] == 1398 and v["edition"] == name],
                    [subject],
                )
                self.assertEqual(len(mapped["runs"]), len(starts) + 2)

    def test_rebuild_checks_chapter_text_and_index_before_fixing_boundaries(self):
        for contract in (
            "chapter_pdf_starts",
            "backmatter_pdf_start",
            "pdf.page_text(opening)",
            "title_match >= 0.5",
            "is_index(end - 1)",
            "documented_runs",
            "pdf.source_sha256",
            "readable_page_ratio",
        ):
            self.assertIn(contract, BUILDER)

    def test_no_unapproved_nearest_edition_substitution(self):
        self.assertNotIn("nearest", BUILDER)
        for name in BOOKS:
            self.assertEqual(MAPS["editions"][name]["source"], "verified-server-pdf")


if __name__ == "__main__":
    unittest.main()
