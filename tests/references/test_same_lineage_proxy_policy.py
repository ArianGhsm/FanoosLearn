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
        "same_lineage_proxy_policy.py")
spec = importlib.util.spec_from_file_location("same_lineage_proxy_policy", FILE)
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

    def eligible(self, selected, family="daly"):
        return [(r["year"], r["edition"], r["_proxy"], r["_rank"])
                for r in selected if r["_family"] == family and r["_eligible"]]

    def test_newest_family_edition_ranks_first_and_actual_year_kept(self):
        selected = self.select(1396)
        self.assertEqual(self.eligible(selected),
                         [(1398, "daly@3e", True, 0), (1397, "daly@2e", True, 1)])
        self.assertEqual(self.eligible(selected, "other-book"),
                         [(1397, "other-book@1e", True, 0)])

    def test_exact_exam_year_edition_is_an_older_non_proxy_candidate(self):
        self.catalog["validity"].append(row(1396, "daly@1e"))
        self.pdfs["daly@1e"] = {"pdf": "verified-server-pdf"}
        self.maps["daly@1e"] = MAP
        self.assertEqual(self.eligible(self.select(1396)),
                         [(1398, "daly@3e", True, 0), (1397, "daly@2e", True, 1),
                          (1396, "daly@1e", False, 2)])

    def test_edition_listed_in_several_years_appears_once(self):
        self.catalog["validity"].append(row(1405, "daly@3e"))
        self.catalog["validity"].append(row(1396, "daly@3e"))
        self.assertEqual(self.eligible(self.select(1396)),
                         [(1396, "daly@3e", False, 0), (1397, "daly@2e", True, 1)])
        self.catalog["validity"].remove(row(1396, "daly@3e"))
        self.assertEqual(self.eligible(self.select(1396))[0], (1405, "daly@3e", True, 0))

    def test_unusable_newer_edition_is_pending_and_older_moves_up(self):
        for bad in ({"missing": "not in verified library"}, None):
            with self.subTest(bad=bad):
                if bad is None:
                    self.pdfs["daly@3e"] = {"pdf": "verified-server-pdf"}
                    self.maps["daly@3e"] = {"runs": [[None, 1, 20]], "pdf_pages": 20,
                                            "source_pdf_sha256": HASH}
                else:
                    self.pdfs["daly@3e"] = bad
                selected = self.select(1396)
                newest = next(r for r in selected if r["edition"] == "daly@3e")
                self.assertEqual((newest["_eligible"], newest["_rank"]), (False, None))
                self.assertEqual(self.eligible(selected), [(1397, "daly@2e", True, 0)])

    def test_bad_scope_or_map_is_pending(self):
        self.maps["daly@2e"] = {"runs": [[None, 1, 20]], "source_pdf_sha256": HASH}
        for bad_scope in ([], [{}], ["1"], [{"number": ""}]):
            with self.subTest(scope=bad_scope):
                self.catalog["validity"][1]["scope_chapters"] = bad_scope
                self.assertEqual(self.eligible(self.select(1396)), [])

    def test_no_exam_year_list_uses_nearest_subject_list_lineages(self):
        selected = self.select(1396)
        self.assertEqual({r["_family"] for r in selected}, {"daly", "other-book"})
        self.assertNotIn("board-only@1e", {r["edition"] for r in selected})
        self.assertNotIn("not-official@1e", {r["edition"] for r in selected})

    def test_unusable_maps_are_not_proxy_selectable(self):
        self.maps = {edition: {"runs": MAP["runs"]} for edition in self.pdfs}
        selected = self.select(1396)
        self.assertTrue(selected)
        self.assertTrue(all(row["_eligible"] is False for row in selected))
        self.assertFalse(p.is_selected_proxy(
            self.catalog, "residency", 1396, "community-dentistry",
            selected[0]["edition"], selected[0]["year"], p.BASIS,
            self.pdfs, self.maps))

    def test_older_edition_needs_exact_newer_search_attestation(self):
        selected = self.select(1396)
        self.assertIsNone(p.fallback_error(selected, "daly@3e", None))
        self.assertIsNone(p.fallback_error(selected, "daly@3e", []))
        self.assertIn("must not list", p.fallback_error(selected, "daly@3e", ["daly@2e"]))
        self.assertIn("newer_editions_searched", p.fallback_error(selected, "daly@2e", None))
        self.assertIn("newer_editions_searched", p.fallback_error(selected, "daly@2e", ["daly@1e"]))
        self.assertIsNone(p.fallback_error(selected, "daly@2e", ["daly@3e"]))
        self.assertIsNone(p.fallback_error(selected, "other-book@1e", None))
        self.assertIn("not an eligible", p.fallback_error(selected, "board-only@1e", None))

    def test_newest_first_order_is_enforced_for_unfinished_older_years(self):
        self.assertTrue(p.requires_newest_first(1391))
        self.assertTrue(p.requires_newest_first(1396))
        self.assertFalse(p.requires_newest_first(1397))
        self.assertFalse(p.requires_newest_first(1398))

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
                "PENDING, verified PDF has no usable hash-bound chapter map",
                stdout.getvalue(),
            )
            self.assertNotIn("legacy nearest", stdout.getvalue())

    def test_exact_pdf_map_failure_is_pending_while_newer_edition_is_searched(self):
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
            (bank / "catalog.json").write_text(json.dumps({"validity": [
                {"exam_type": "residency", "year": 1396,
                 "subject": "community-dentistry", "edition": "daly@1e",
                 "official": True, "scope": "all chapters",
                 "scope_chapters": [{"number": "1"}]},
                {"exam_type": "residency", "year": 1397,
                 "subject": "community-dentistry", "edition": "daly@2e",
                 "official": True, "scope": "all chapters",
                 "scope_chapters": [{"number": "1"}]},
            ]}))
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": {
                "daly@1e": {"pdf": "verified-server-pdf"},
                "daly@2e": {"pdf": "verified-server-pdf"},
            }}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": {
                "daly@1e": {"runs": [["1", 1, 20]]},
                "daly@2e": {"runs": MAP["runs"], "source_pdf_sha256": HASH,
                            "pdf_pages": 20},
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
                {"key": "222", "editions": ["daly@2e"], "terms": []},
            ])
            self.assertIn("daly@1e [PENDING, verified PDF has no usable", stdout.getvalue())
            self.assertIn("daly@2e RANK 0 in daly [PDF source listed]", stdout.getvalue())
            self.assertIn("ORDER: search RANK 0 (newest) first", stdout.getvalue())


if __name__ == "__main__":
    unittest.main()
