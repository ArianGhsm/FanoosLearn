"""Pin White/Pharoah 9e to its exact approved PDF and contents-page boundaries."""
import json
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
BANK = ROOT / "data" / "bank"
EDITION = "white-pharoah-radiology@9e"
SHA256 = "ec775fdee27cd375b6d13a046cbcaed5e73ba6851180c3b876cfec83a7de184d"
STARTS = [15, 31, 43, 59, 99, 107, 139, 151, 169, 199, 217, 231, 235, 271, 285,
          293, 309, 323, 337, 369, 389, 411, 451, 481, 507, 533, 553, 565, 597,
          611, 625, 651, 659]


class WhitePharoah9eReferencePageMapTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.maps = json.loads((BANK / "reference-chapter-pages.json").read_text(encoding="utf-8"))
        cls.tocs = json.loads((BANK / "reference-tocs.json").read_text(encoding="utf-8"))
        cls.pdfs = json.loads((BANK / "reference-pdfs.json").read_text(encoding="utf-8"))
        cls.catalog = json.loads((BANK / "catalog.json").read_text(encoding="utf-8"))
        cls.entry = cls.maps["editions"][EDITION]

    def test_map_matches_exact_approved_pdf_and_covers_every_page(self):
        entry = self.entry
        self.assertEqual(entry["source"], "verified-server-pdf")
        self.assertEqual(entry["source_pdf_sha256"], SHA256)
        self.assertEqual(entry["pdf_pages"], 690)
        self.assertEqual(entry["readable_pages"], 681)
        self.assertEqual(entry["chapters_seen"], 33)
        self.assertEqual(entry["chapters_listed"], 33)
        self.assertEqual(entry["not_seen"], [])

        runs = entry["runs"]
        self.assertEqual(runs[0], [None, 1, 14])
        self.assertEqual(runs[-2], ["33", 659, 663])
        self.assertEqual(runs[-1], [None, 664, 690])
        previous_end = 0
        chapter_starts = {}
        for chapter, start, end in runs:
            self.assertEqual(start, previous_end + 1)
            self.assertGreaterEqual(end, start)
            if chapter is not None:
                chapter_starts[chapter] = start
            previous_end = end
        self.assertEqual(previous_end, 690)
        self.assertEqual([chapter_starts[str(i)] for i in range(1, 34)], STARTS)

    def test_contents_page_and_catalog_scope_match_the_edition(self):
        pdf = self.pdfs["editions"][EDITION]
        self.assertEqual(pdf["pdf"], "verified-server-pdf")
        self.assertEqual(pdf["chapter_pdf_starts"], {str(i): page for i, page in enumerate(STARTS, 1)})
        self.assertEqual(pdf["backmatter_pdf_start"], 664)
        toc = self.tocs["editions"][EDITION]
        self.assertEqual(toc["source"], "verified server PDF table of contents, physical PDF page 11")
        self.assertEqual([row[0] for row in toc["chapters"]], [str(i) for i in range(1, 34)])

        editions = [e for r in self.catalog["references"] for e in r["editions"]
                    if r["key"] == "white-pharoah-radiology" and e["key"] == "9e"]
        self.assertEqual(len(editions), 1)
        self.assertEqual([n["number"] for n in editions[0]["nodes"]], [str(i) for i in range(1, 34)])

    def test_1405_board_and_promotion_scope_names_all_chapters(self):
        for exam_type in ("board", "promotion"):
            rows = [r for r in self.catalog["validity"]
                    if r["exam_type"] == exam_type and r["year"] == 1405
                    and r["subject"] == "oral-radiology" and r["edition"] == EDITION]
            self.assertEqual(len(rows), 1)
            self.assertEqual([x["number"] for x in rows[0]["scope_chapters"]],
                             [str(i) for i in range(1, 34)])


if __name__ == "__main__":
    unittest.main()
