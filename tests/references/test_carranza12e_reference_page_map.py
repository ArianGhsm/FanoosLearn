"""Regression checks for the exact Carranza 12e private-source chapter map.

The full copyrighted page-marked book is verified on the authorized server;
CI checks only public metadata, not private book text.
"""
from __future__ import annotations

import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BANK = ROOT / "data" / "bank"
EDITION = "carranza-periodontology@12e"


class Carranza12ePageMapTests(unittest.TestCase):
    def test_complete_1766_pdf_pages_and_89_catalog_chapters(self) -> None:
        pages = json.loads((BANK / "reference-chapter-pages.json").read_text(encoding="utf-8"))
        runs = pages["editions"][EDITION]["runs"]
        chaptered = []
        previous = 0
        for chapter, start, end in runs:
            self.assertEqual(start, previous + 1)
            self.assertGreaterEqual(end, start)
            if chapter is not None:
                chaptered.append(str(chapter))
            previous = end
        self.assertEqual(previous, 1766)
        self.assertEqual(len(chaptered), 89)
        self.assertEqual(len(set(chaptered)), len(chaptered))
        catalog = json.loads((BANK / "catalog.json").read_text(encoding="utf-8"))
        ref = next(r for r in catalog["references"] if r["key"] == "carranza-periodontology")
        edition = next(e for e in ref["editions"] if e["key"] == "12e")
        official = {str(node["number"]) for node in edition["nodes"] if node["kind"] == "chapter"}
        self.assertEqual(set(chaptered), official)

    def test_scoped_opening_pages_and_exclusions(self) -> None:
        data = json.loads((BANK / "reference-chapter-pages.json").read_text(encoding="utf-8"))
        runs = data["editions"][EDITION]["runs"]
        by_chapter = {chapter: (start, end) for chapter, start, end in runs if chapter is not None}
        self.assertEqual(by_chapter["1"][0], 29)
        self.assertEqual(by_chapter["6"][0], 147)
        self.assertEqual(by_chapter["26"][1], 755)
        self.assertEqual(by_chapter["28"][0], 771)
        self.assertEqual(by_chapter["30"][0], 805)
        self.assertEqual(by_chapter["90"], (1696, 1709))
        self.assertIn([None, 1, 28], runs)
        self.assertIn([None, 756, 770], runs)  # ch27 absent from approved TOC
        self.assertIn([None, 1710, 1766], runs)  # index, not ch90
        self.assertNotIn("27", by_chapter)
        for row in data["editions"][EDITION]["runs"]:
            self.assertNotEqual(row[0], "Index")

    def test_exact_reference_is_server_pdf_and_has_no_text_entry(self) -> None:
        manifest = json.loads((BANK / "reference-pdfs.json").read_text(encoding="utf-8"))
        pages = json.loads((BANK / "reference-chapter-pages.json").read_text(encoding="utf-8"))
        entry = manifest["editions"][EDITION]
        self.assertEqual(entry["pdf"], "verified-server-pdf")
        self.assertNotIn("text", entry)
        self.assertTrue(all("text" not in item for item in manifest["editions"].values()))
        self.assertEqual(pages["editions"][EDITION]["source_pdf_sha256"],
                         "1332e1f92ec1dea1ee99c7382993ce3f191407099d7650ebb97c79b989553263")


if __name__ == "__main__":
    unittest.main()
