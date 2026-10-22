"""Synthetic tests for the owner-authorized older-exam proxy rule."""
import importlib.util
import io
import json
from pathlib import Path
import sys
from tempfile import TemporaryDirectory
import unittest
from unittest.mock import patch

FILE = (Path(__file__).resolve().parents[2] / "scripts" / "references" /
        "nearest_same_lineage_proxy_policy.py")
spec = importlib.util.spec_from_file_location("nearest_same_lineage_proxy_policy", FILE)
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)

HASH = "a" * 64
MAP = {"runs": [[None, 1, 4], ["1", 5, 20]], "pdf_pages": 20,
       "source_pdf_sha256": HASH}


def row(year, edition, subject="community-dentistry", exam_type="residency", official=True):
    return {"year": year, "edition": edition, "subject": subject,
            "exam_type": exam_type, "official": official,
            "scope_chapters": [{"number": "1"}]}


class NearestSameLineageProxyTests(unittest.TestCase):
    def setUp(self):
        self.catalog = {"validity": [
            row(1397, "daly@2e"), row(1398, "daly@3e"),
            row(1397, "other-book@1e"),
            row(1397, "not-official@1e", official=False),
            row(1397, "board-only@1e", exam_type="board"),
        ]}
        self.pdfs = {"daly@2e": {"pdf": "verified-server-pdf"},
                     "daly@3e": {"pdf": "verified-server-pdf"},
                     "other-book@1e": {"pdf": "verified-server-pdf"},
                     "not-official@1e": {"pdf": "verified-server-pdf"},
                     "board-only@1e": {"pdf": "verified-server-pdf"}}
        self.maps = {edition: MAP for edition in self.pdfs}

    def select(self, year=1396):
        return p.selected_reference_rows(self.catalog, "residency", year,
                                         "community-dentistry", self.pdfs, self.maps)

    def test_scope_is_limited_to_1396_through_1398(self):
        self.assertFalse(p.is_proxy_exam_year(1395))
        self.assertTrue(p.is_proxy_exam_year(1396))
        self.assertTrue(p.is_proxy_exam_year(1398))
        self.assertFalse(p.is_proxy_exam_year(1399))
        self.assertEqual(self.select(1395), [])

    def test_nearest_verified_same_family_is_selected_and_actual_year_kept(self):
        selected = self.select(1396)
        self.assertEqual([(r["year"], r["edition"], r["_proxy"]) for r in selected],
                         [(1397, "daly@2e", True), (1397, "other-book@1e", True)])
        self.assertTrue(all(r["_eligible"] for r in selected))

    def test_legacy_hash_bound_contiguous_map_without_page_count_is_eligible(self):
        self.maps["daly@2e"] = {
            "runs": [[None, 1, 4], ["1", 5, 20]],
            "source_pdf_sha256": HASH,
        }
        selected = self.select(1396)
        daly = next(r for r in selected if r["edition"].startswith("daly@"))
        self.assertEqual((daly["year"], daly["edition"], daly["_eligible"]),
                         (1397, "daly@2e", True))

    def test_exact_available_edition_always_wins(self):
        self.catalog["validity"].append(row(1396, "daly@1e"))
        self.pdfs["daly@1e"] = {"pdf": "verified-server-pdf"}
        self.maps["daly@1e"] = MAP
        selected = self.select(1396)
        self.assertEqual([(r["year"], r["edition"], r["_proxy"]) for r in selected],
                         [(1396, "daly@1e", False)])

    def test_missing_exact_edition_uses_nearest_available_same_family(self):
        self.catalog["validity"].append(row(1396, "daly@1e"))
        self.pdfs["daly@1e"] = {"missing": "not in verified library"}
        self.maps["daly@1e"] = MAP
        selected = self.select(1396)
        self.assertEqual([(r["year"], r["edition"], r["_proxy"]) for r in selected],
                         [(1397, "daly@2e", True)])
        self.assertNotIn("other-book@1e", [r["edition"] for r in selected])

    def test_unstructured_exact_scope_uses_nearest_structured_same_family(self):
        exact = row(1396, "daly@1e")
        exact["scope_chapters"] = []
        self.catalog["validity"].append(exact)
        self.pdfs["daly@1e"] = {"pdf": "verified-server-pdf"}
        self.maps["daly@1e"] = MAP
        selected = self.select(1396)
        self.assertEqual([(r["year"], r["edition"], r["_proxy"]) for r in selected],
                         [(1397, "daly@2e", True)])

    def test_no_exam_year_list_uses_nearest_subject_list_lineages(self):
        selected = self.select(1396)
        self.assertEqual({r["edition"] for r in selected}, {"daly@2e", "other-book@1e"})
        self.assertNotIn("board-only@1e", {r["edition"] for r in selected})
        self.assertNotIn("not-official@1e", {r["edition"] for r in selected})

    def test_hash_bound_map_is_required_and_ties_prefer_earlier_reference_year(self):
        self.catalog["validity"].append(row(1395, "daly@1e"))
        self.pdfs["daly@1e"] = {"pdf": "verified-server-pdf"}
        self.maps["daly@1e"] = MAP
        selected = self.select(1396)
        daly = next(r for r in selected if r["edition"].startswith("daly@"))
        self.assertEqual(daly["edition"], "daly@1e")
        self.assertEqual(daly["year"], 1395)

    def test_unbound_map_is_not_proxy_eligible(self):
        self.catalog["validity"].append(row(1395, "daly@1e"))
        self.pdfs["daly@1e"] = {"pdf": "verified-server-pdf"}
        self.maps["daly@1e"] = {"runs": [["1", 1, 20]]}
        selected = self.select(1396)
        daly = next(r for r in selected if r["edition"].startswith("daly@"))
        self.assertEqual((daly["year"], daly["edition"]), (1397, "daly@2e"))

    def test_unusable_fallback_remains_pending_and_not_proxy_selectable(self):
        self.maps = {edition: {"runs": MAP["runs"]} for edition in self.pdfs}
        selected = self.select(1396)
        self.assertTrue(selected)
        self.assertTrue(all(row["_eligible"] is False for row in selected))
        self.assertFalse(p.is_selected_proxy(
            self.catalog, "residency", 1396, "community-dentistry",
            selected[0]["edition"], selected[0]["year"], p.BASIS,
            self.pdfs, self.maps))

    def test_proxy_metadata_requires_explicit_non_official_note(self):
        note = "این آزمون ۱۳۹۶ از نسخهٔ پروکسیِ سال ۱۳۹۷ استفاده می‌کند؛ رسمی بودن آن برای سال آزمون تأیید نشده است."
        self.assertTrue(p.valid_proxy_note(note, 1396, 1397))
        self.assertFalse(p.valid_proxy_note("منبع سال ۱۳۹۷", 1396, 1397))
        self.assertFalse(p.valid_proxy_note(
            "این آزمون ۱۳۹۶ از نسخهٔ پروکسی سال ۱۳۹۷ استفاده می‌کند و منبع رسمی تأیید شده است.", 1396, 1397))
        self.assertTrue(p.is_selected_proxy(
            self.catalog, "residency", 1396, "community-dentistry",
            "daly@2e", 1397, p.BASIS, self.pdfs, self.maps))
        self.assertFalse(p.is_selected_proxy(
            self.catalog, "residency", 1396, "community-dentistry",
            "board-only@1e", 1397, p.BASIS, self.pdfs, self.maps))

    def test_legacy_nearest_flag_cannot_extend_proxy_eligibility(self):
        batch = (Path(__file__).resolve().parents[2] / "scripts/references" /
                 "classification_batch.py").read_text()
        self.assertIn("args.include_nearest and not is_proxy_exam_year(year)", batch)
        self.assertIn("row.get('_eligible') is not True", batch)


class SameLineageProxyBatchTests(unittest.TestCase):
    def test_unusable_proxy_map_is_pending_and_never_uses_legacy_nearest(self):
        references = str(Path(__file__).resolve().parents[2] / "scripts" / "references")
        if references not in sys.path:
            sys.path.insert(0, references)
        import classification_batch

        class Capture(io.StringIO):
            def reconfigure(self, **_kwargs):
                pass

        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            bank = root / "bank"
            bank.mkdir()
            (bank / "catalog.json").write_text(json.dumps({"validity": [{
                "exam_type": "residency", "year": 1397,
                "subject": "community-dentistry", "edition": "daly@2e",
                "official": True, "scope": "all chapters",
                "scope_chapters": [{"number": "1"}],
            }]}))
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": {
                "daly@2e": {"pdf": "verified-server-pdf"},
            }}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": {
                "daly@2e": {"runs": [["1", 1, 20]]},
            }}))
            sitting = root / "study.json"
            sitting.write_text(json.dumps({
                "year": 1396, "exam_type": "residency", "round": 1,
                "questions": [{"number": 222, "subject": "community-dentistry",
                               "stem": "Synthetic question?", "choices": ["a", "b"],
                               "answer": {"choice": 2, "status": "final"}}],
            }))
            output = root / "queries.json"
            stdout = Capture()
            argv = ["classification_batch.py", "--sitting", str(sitting),
                    "--subject", "community-dentistry", "--out", str(output),
                    "--include-nearest"]
            with patch.object(classification_batch, "BANK", bank), \
                    patch.object(sys, "argv", argv), \
                    patch.object(sys, "stdout", stdout):
                self.assertEqual(classification_batch.main(), 0)

            self.assertEqual(json.loads(output.read_text()), [
                {"key": "222", "editions": [], "terms": []},
            ])
            self.assertIn(
                "MISSING, pending usable same-lineage PDF and hash-bound chapter map",
                stdout.getvalue(),
            )
            self.assertNotIn("legacy nearest", stdout.getvalue())


if __name__ == "__main__":
    unittest.main()
