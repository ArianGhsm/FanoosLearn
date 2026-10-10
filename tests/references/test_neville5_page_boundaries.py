"""Regression for approved original Neville fifth edition PDF page boundaries."""
import json
import unittest
from pathlib import Path

class Neville5OriginalPdfChapterMapTests(unittest.TestCase):
    def test_page_runs_are_contiguous_and_book_sections_correct(self):
        bank=Path(__file__).resolve().parents[2]/"data"/"bank"
        entry=json.loads((bank/"reference-chapter-pages.json").read_text())["editions"]["neville-oral-pathology@5e"]
        self.assertEqual(entry["verified_original_pdf_sha256"],"4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac")
        self.assertEqual(entry["original_pdf_pages"],983)
        runs=entry["runs"]
        self.assertEqual(runs[0],[None,1,10])
        self.assertEqual(runs[-1],[None,924,983])
        self.assertEqual(runs[-2],["19",891,923])
        for before,after in zip(runs,runs[1:]):
            self.assertEqual(before[2]+1,after[1])
        chapters=[row for row in runs if row[0] is not None]
        self.assertEqual([row[0] for row in chapters],list(map(str,range(1,20))))
        self.assertEqual([row[1] for row in chapters],[11,61,127,157,182,211,239,282,331,364,470,524,588,628,695,757,829,871,891])
    def test_syllabus_scope_kept_separate(self):
        bank=Path(__file__).resolve().parents[2]/"data"/"bank"
        cat=json.loads((bank/"catalog.json").read_text())
        for year in (1404,1405):
            entries=[e for e in cat["validity"] if e["exam_type"]=="residency" and e["year"]==year and e["subject"]=="oral-pathology" and e["edition"]=="neville-oral-pathology@5e"]
            self.assertEqual(len(entries),1)
            self.assertTrue(entries[0]["official"])
            allowed={x["number"] for x in entries[0]["scope_chapters"]}
            self.assertNotIn("7",allowed)
            self.assertNotIn("18",allowed)
            self.assertNotIn("19",allowed)

if __name__=="__main__":
    unittest.main()
