"""The edition manifest names PDFs only and contains no extracted book text."""
import json
import re
import subprocess
import unittest
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]


class ReferencePdfsTest(unittest.TestCase):
    def test_every_official_edition_has_exactly_one_pdf_disposition(self):
        catalog = json.loads((REPO / "data/bank/catalog.json").read_text(encoding="utf-8"))
        listed = json.loads((REPO / "data/bank/reference-pdfs.json").read_text(encoding="utf-8"))["editions"]
        known = {f"{ref['key']}@{edition['key']}" for ref in catalog["references"]
                 for edition in ref.get("editions", [])}
        official = {row["edition"] for row in catalog["validity"]
                    if row.get("is_official", True)}

        self.assertEqual(sorted(official - set(listed)), [], "an official edition has no PDF disposition")
        self.assertEqual(sorted(set(listed) - known), [], "manifest names an unknown edition")
        for edition, entry in listed.items():
            kinds = [kind for kind in ("pdf", "missing") if kind in entry]
            self.assertEqual(len(kinds), 1, f"{edition} must be verified-PDF or pending")
            self.assertNotIn("text", entry)
            if "nearest" in entry:
                self.assertIn(entry["nearest"], listed)
                self.assertNotIn("missing", listed[entry["nearest"]])

    def test_manifest_prohibits_pdf_text_exports_and_indexes(self):
        manifest = json.loads((REPO / "data/bank/reference-pdfs.json").read_text(encoding="utf-8"))
        notes = " ".join(manifest["notes"]).lower()
        self.assertIn("never writes page text", notes)
        self.assertIn("search index", notes)

    def test_no_pdf_edition_text_files_are_tracked(self):
        result = subprocess.run(["git", "ls-files", "-z"], cwd=REPO, check=True, capture_output=True)
        tracked = [Path(path.decode()) for path in result.stdout.split(b"\0") if path]
        page_texts = [path.as_posix() for path in tracked
                      if path.suffix == ".txt" and re.search(r"@[^/]+\.txt$", path.name)]
        self.assertEqual(page_texts, [], "PDF-derived page-text copies must never be committed")

    def test_any_bound_chapter_map_names_a_pdf_sha256(self):
        maps = json.loads((REPO / "data/bank/reference-chapter-pages.json").read_text(encoding="utf-8"))["editions"]
        manifest = json.loads((REPO / "data/bank/reference-pdfs.json").read_text(encoding="utf-8"))["editions"]
        for edition, entry in maps.items():
            if "source_pdf_sha256" in entry:
                self.assertRegex(entry["source_pdf_sha256"], r"^[a-f0-9]{64}$")
                self.assertEqual(entry.get("source"), "verified-server-pdf")
                self.assertEqual(manifest[edition].get("pdf"), "verified-server-pdf")


if __name__ == "__main__":
    unittest.main()
