"""Pin Sturdevant 8e to the exact approved server PDF and direct TOC boundaries."""
import json
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
BANK = ROOT / "data" / "bank"
EDITION = "sturdevant-operative@8e"
SHA256 = "9b28d77abeb5b06e6cf148073b185b31ee91bec1686d39ac1ce113c2085cda6f"
STARTS = [56, 105, 180, 214, 245, 280, 328, 375, 401, 458, 514, 577, 602, 653, 679, 749, 777]


class Sturdevant8eReferencePageMapTests(unittest.TestCase):
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
        self.assertEqual(entry["pdf_pages"], 923)
        self.assertEqual(entry["readable_pages"], 904)
        self.assertEqual(entry["chapters_seen"], 17)
        self.assertEqual(entry["chapters_listed"], 17)
        self.assertEqual(entry["not_seen"], [])

        runs = entry["runs"]
        self.assertEqual(runs[0], [None, 1, 55])
        self.assertEqual(runs[-2], ["17", 777, 842])
        self.assertEqual(runs[-1], [None, 843, 923])
        previous_end = 0
        chapter_starts = {}
        for chapter, start, end in runs:
            self.assertEqual(start, previous_end + 1)
            self.assertGreaterEqual(end, start)
            if chapter is not None:
                chapter_starts[chapter] = start
            previous_end = end
        self.assertEqual(previous_end, 923)
        self.assertEqual([chapter_starts[str(i)] for i in range(1, 18)], STARTS)

    def test_starts_and_catalog_nodes_come_from_the_exact_edition_contents(self):
        pdf = self.pdfs["editions"][EDITION]
        self.assertEqual(pdf["pdf"], "verified-server-pdf")
        self.assertEqual(pdf["chapter_pdf_starts"], {str(i): page for i, page in enumerate(STARTS, 1)})
        self.assertEqual(pdf["backmatter_pdf_start"], 843)

        toc = self.tocs["editions"][EDITION]
        self.assertEqual(toc["source"], "verified server PDF table of contents, physical PDF pages 4-9")
        self.assertEqual([row[0] for row in toc["chapters"]], [str(i) for i in range(1, 18)])
        ref = next(r for r in self.catalog["references"] if r["key"] == "sturdevant-operative")
        edition = next(e for e in ref["editions"] if e["key"] == "8e")
        self.assertEqual([n["number"] for n in edition["nodes"]], [str(i) for i in range(1, 18)])

    def test_1405_official_scope_excludes_chapter_16(self):
        for exam_type in ("board", "promotion"):
            rows = [r for r in self.catalog["validity"]
                    if r["exam_type"] == exam_type and r["year"] == 1405
                    and r["subject"] == "operative-dentistry" and r["edition"] == EDITION]
            self.assertEqual(len(rows), 1)
            allowed = {x["number"] for x in rows[0]["scope_chapters"]}
            self.assertIn("15", allowed)
            self.assertNotIn("16", allowed)
            self.assertIn("17", allowed)


if __name__ == "__main__":
    unittest.main()
