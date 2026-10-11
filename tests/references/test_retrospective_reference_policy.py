"""Owner-authorized 1397+ retrospective selection for exams before 1396.

Only synthetic catalog/PDF metadata; no private question or book content.
"""
from __future__ import annotations

import importlib.util
from pathlib import Path
import unittest

FILE = (Path(__file__).resolve().parents[2] / "scripts" / "references" /
        "retrospective_reference_policy.py")
spec = importlib.util.spec_from_file_location("retrospective_reference_policy", FILE)
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)

HASH = "a" * 64
MAP = {"runs": [[None, 1, 10], ["1", 11, 42]],
       "source_pdf_sha256": HASH}


def row(year, edition, subject="endodontics", kind="residency", official=True):
    return {"year": year, "edition": edition, "subject": subject,
            "exam_type": kind, "official": official,
            "scope_chapters": [{"number": "1"}]}


class Pre1396ReferencePolicyTests(unittest.TestCase):
    def setUp(self):
        self.catalog = {"validity": [
            row(1395, "historical@1e"),
            row(1397, "book@1e"),
            row(1398, "book@2e"),
            row(1399, "book@3e"),
            row(1397, "other@1e"),
            row(1398, "other@2e"),
            row(1398, "not-official@1e", official=False),
            row(1397, "another-exam@1e", kind="board"),
            row(1396, "normal@1e"),
        ]}
        self.pdfs = {name: {"pdf": "verified-server-pdf"} for name in (
            "historical@1e", "book@2e", "book@3e", "other@1e", "other@2e",
            "not-official@1e", "another-exam@1e", "normal@1e")}
        self.maps = {name: MAP for name in self.pdfs}

    def select(self, year):
        return p.selected_reference_rows(self.catalog, "residency", year,
                                         "endodontics", self.pdfs, self.maps)

    def test_pre1396_select_earliest_ready_1397_plus_per_book_family(self):
        selected = self.select(1391)
        self.assertEqual([(x["year"], x["edition"]) for x in selected],
                         [(1397, "other@1e"), (1398, "book@2e")])
        self.assertTrue(all(x["year"] > 1396 for x in selected))
        self.assertTrue(all(x["official"] for x in selected))

    def test_1395_applies_and_1396_does_not(self):
        self.assertTrue(p.is_retrospective(1395))
        self.assertFalse(p.is_retrospective(1396))
        self.assertEqual([x["edition"] for x in self.select(1396)], ["normal@1e"])

    def test_missing_pdf_or_missing_hash_never_selected(self):
        self.maps["other@1e"] = {"runs": [[None, 1, 42]]}
        selected = self.select(1392)
        self.assertIn("other@2e", [x["edition"] for x in selected])
        self.assertNotIn("other@1e", [x["edition"] for x in selected])
        self.pdfs.pop("other@2e")
        selected = self.select(1392)
        self.assertNotIn("other@2e", [x["edition"] for x in selected])

    def test_reference_year_must_not_be_relabelled_exam_year(self):
        selected = self.select(1391)
        self.assertEqual(p.reference_year_for_decision(selected[0], 1391), 1397)
        with self.assertRaises(ValueError):
            p.reference_year_for_decision(row(1395, "wrong@1e"), 1391)
        self.assertEqual(p.reference_year_for_decision(row(1396, "normal@1e"), 1396), 1396)

    def test_publisher_requires_explicit_provenance_and_source_only(self):
        root = Path(__file__).resolve().parents[2]
        php = (root / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        cli = (root / "scripts/references/import_verified_sources.php").read_text()
        self.assertIn("retrospective_proxy_1397_plus", php)
        self.assertIn("'yr' => $src['reference_year']", php)
        self.assertIn("v.is_official=1", php)
        self.assertIn("bank_question_sources", php)
        self.assertIn("BackupManifest::verify", cli)
        self.assertIn("question_content_identical", cli)
        self.assertIn("$retrospective", cli)


if __name__ == "__main__":
    unittest.main()
