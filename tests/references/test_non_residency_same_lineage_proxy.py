"""Synthetic non-residency same-lineage proxy and outside-scope checks."""
import importlib.util
import json
from pathlib import Path
import sys
from tempfile import TemporaryDirectory
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts/references"))
import apply_classification
import classification_batch
import same_lineage_proxy_policy as policy

AUDIT_FILE = ROOT / "scripts/references/audit_private_study_batches.py"
AUDIT_SPEC = importlib.util.spec_from_file_location("non_res_proxy_audit", AUDIT_FILE)
AUDIT = importlib.util.module_from_spec(AUDIT_SPEC)
AUDIT_SPEC.loader.exec_module(AUDIT)

HASH = "a" * 64
FAMILY_OLD = "fictional-book@1e"
FAMILY_NEW = "fictional-book@2e"
OTHER = "unselected-book@1e"
EVIDENCE = "Synthetic direct page evidence for this source"
PROXY_NOTE = (
    "این آزمون ۱۳۹۹ از نسخهٔ پروکسیِ سال ۱۴۰۱ استفاده می‌کند؛ "
    "رسمی بودن آن برای سال آزمون تأیید نشده است."
)


def validity(exam_type, year, edition, scope=("2",)):
    return {
        "exam_type": exam_type, "year": year, "subject": "endodontics",
        "edition": edition, "official": True,
        "scope_chapters": [{"number": number} for number in scope],
    }


def fixture_catalog():
    return {
        "validity": [
            validity("national", 1397, OTHER),  # tie loser: 1399 is equally close to 1401
            validity("national", 1401, FAMILY_OLD),
            validity("national", 1403, FAMILY_NEW),
            validity("board", 1401, "board-book@1e"),
        ],
        "references": [{
            "key": "fictional-book", "editions": [
                {"key": "1e", "nodes": [
                    {"key": "ch02", "kind": "chapter", "number": "2", "title": "Synthetic chapter"},
                ]},
                {"key": "2e", "nodes": [
                    {"key": "ch02", "kind": "chapter", "number": "2", "title": "Synthetic newer chapter"},
                ]},
            ],
        }, {
            "key": "other-book", "editions": [{"key": "1e", "nodes": [
                {"key": "ch02", "kind": "chapter", "number": "2", "title": "Tie loser chapter"},
            ]}],
        }, {
            "key": "board-book", "editions": [{"key": "1e", "nodes": [
                {"key": "ch02", "kind": "chapter", "number": "2", "title": "Board chapter"},
            ]}],
        }],
    }


def pdf_catalog():
    return {edition: {"pdf": "verified-server-pdf"}
            for edition in (FAMILY_OLD, FAMILY_NEW, OTHER, "board-book@1e")}


def chapter_maps():
    return {edition: {
        "runs": [[None, 1, 2], ["2", 3, 20]], "pdf_pages": 20,
        "source_pdf_sha256": HASH,
    } for edition in (FAMILY_OLD, FAMILY_NEW, OTHER, "board-book@1e")}


class FakePdf:
    source_sha256 = HASH
    page_count = 20

    def page_text(self, page):
        return EVIDENCE


class NonResidencyProxyPolicyTests(unittest.TestCase):
    def test_national_1399_uses_nearest_later_tie_list_and_keeps_type_isolation(self):
        selected = policy.selected_non_residency_proxy_rows(
            fixture_catalog(), "national", 1399, "endodontics", pdf_catalog(), chapter_maps())
        self.assertEqual(
            [(row["year"], row["edition"], row["_rank"])
             for row in selected if row["_eligible"]],
            [(1403, FAMILY_NEW, 0), (1401, FAMILY_OLD, 1)],
        )
        self.assertEqual({row["_family"] for row in selected}, {"fictional-book"})
        board = policy.selected_non_residency_proxy_rows(
            fixture_catalog(), "board", 1399, "endodontics", pdf_catalog(), chapter_maps())
        self.assertEqual([row["edition"] for row in board], ["board-book@1e"])
        self.assertFalse(policy.is_selected_proxy(
            fixture_catalog(), "national", 1399, "endodontics", "board-book@1e",
            1401, policy.BASIS, pdf_catalog(), chapter_maps()))

    def test_an_exact_non_residency_list_suppresses_every_proxy(self):
        catalog = fixture_catalog()
        catalog["validity"].append(validity("national", 1399, FAMILY_OLD))
        self.assertEqual(policy.selected_non_residency_proxy_rows(
            catalog, "national", 1399, "endodontics", pdf_catalog(), chapter_maps()), [])
        self.assertFalse(policy.is_selected_proxy(
            catalog, "national", 1399, "endodontics", FAMILY_OLD, 1401,
            policy.BASIS, pdf_catalog(), chapter_maps()))

    def test_owner_approved_scope_gap_borrows_the_named_year_of_the_same_edition(self):
        def ped(year, edition, scope):
            row = validity("national", year, edition, scope)
            row["subject"] = "pediatric-dentistry"
            if scope == ():
                row.pop("scope_chapters")
                row["scope"] = "فصول منتخب"
            return row
        catalog = fixture_catalog()
        catalog["validity"] += [
            ped(1401, OTHER, ("2",)),           # nearer list of another family: never used
            ped(1402, FAMILY_NEW, ()),
            ped(1403, FAMILY_NEW, ()),
            ped(1404, FAMILY_NEW, ("2",)),
            ped(1405, FAMILY_NEW, ("2",)),      # newer year of the same edition: not the named one
        ]
        for year in (1402, 1403):
            selected = policy.selected_non_residency_proxy_rows(
                catalog, "national", year, "pediatric-dentistry", pdf_catalog(), chapter_maps())
            self.assertEqual(
                [(row["year"], row["edition"], row["_rank"], row["_proxy"]) for row in selected],
                [(1404, FAMILY_NEW, 0, True)])
            self.assertTrue(policy.is_selected_proxy(
                catalog, "national", year, "pediatric-dentistry", FAMILY_NEW, 1404,
                policy.BASIS, pdf_catalog(), chapter_maps()))
            self.assertFalse(policy.is_selected_proxy(
                catalog, "national", year, "pediatric-dentistry", FAMILY_NEW, 1405,
                policy.BASIS, pdf_catalog(), chapter_maps()))

    def test_scope_gap_needs_the_owner_pair_and_an_unstructured_exact_list(self):
        def ped(year, edition, scope, exam_type="national"):
            row = validity(exam_type, year, edition, scope)
            row["subject"] = "pediatric-dentistry"
            if scope == ():
                row.pop("scope_chapters")
            return row
        structured = fixture_catalog()
        structured["validity"] += [ped(1402, FAMILY_NEW, ("2",)), ped(1404, FAMILY_NEW, ("2",))]
        self.assertEqual(policy.selected_non_residency_proxy_rows(
            structured, "national", 1402, "pediatric-dentistry", pdf_catalog(), chapter_maps()), [])
        other_pair = fixture_catalog()
        other_pair["validity"] += [ped(1401, FAMILY_NEW, ()), ped(1404, FAMILY_NEW, ("2",)),
                                   ped(1402, FAMILY_NEW, (), "board"), ped(1404, FAMILY_NEW, ("2",), "board")]
        self.assertEqual(policy.selected_non_residency_proxy_rows(
            other_pair, "national", 1401, "pediatric-dentistry", pdf_catalog(), chapter_maps()), [])
        self.assertEqual(policy.selected_non_residency_proxy_rows(
            other_pair, "board", 1402, "pediatric-dentistry", pdf_catalog(), chapter_maps()), [])

    def test_batch_prints_selected_national_proxy_candidates(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            bank = root / "bank"
            bank.mkdir()
            (bank / "catalog.json").write_text(json.dumps(fixture_catalog()))
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": pdf_catalog()}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": chapter_maps()}))
            sitting = root / "study.json"
            sitting.write_text(json.dumps({
                "year": 1399, "exam_type": "national", "round": 2,
                "questions": [{"number": 222, "subject": "endodontics", "stem": "Synthetic?",
                               "choices": ["a", "b"], "answer": {"choice": 2, "status": "final"}}],
            }))
            output = root / "queries.json"
            class Capture:
                def __init__(self): self.value = ""
                def reconfigure(self, **_kwargs): pass
                def write(self, value): self.value += value
            stdout = Capture()
            argv = ["classification_batch.py", "--sitting", str(sitting), "--subject", "endodontics",
                    "--out", str(output)]
            with patch.object(classification_batch, "BANK", bank), patch.object(sys, "argv", argv), \
                    patch.object(sys, "stdout", stdout):
                self.assertEqual(classification_batch.main(), 0)
            self.assertEqual(json.loads(output.read_text()), [
                {"key": "222", "editions": [FAMILY_NEW, FAMILY_OLD], "terms": []},
            ])
            self.assertIn("PROXY_REFERENCE_YEAR_1401 fictional-book@1e RANK 1", stdout.value)
            self.assertIn("newer_editions_searched", stdout.value)

    def test_national_1399_round_two_proxy_validation_records_1401(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            bank = root / "data/bank"
            bank.mkdir(parents=True)
            catalog = fixture_catalog()
            (bank / "catalog.json").write_text(json.dumps(catalog))
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": pdf_catalog()}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": chapter_maps()}))
            sitting = root / "study.json"
            question = {"number": 222, "subject": "endodontics", "stem": "Synthetic question?",
                        "choices": ["a", "b"], "answer": {"choice": 2, "status": "final"}}
            sitting.write_text(json.dumps({
                "format": "fanoos.classification.study-only/1", "exam_type": "national",
                "year": 1399, "round": 2, "questions": [question],
            }))
            decision = {"number": 222, "edition": FAMILY_OLD, "reference_year": 1401,
                        "classification_basis": "same_lineage_proxy", "proxy_note": PROXY_NOTE,
                        "newer_editions_searched": [FAMILY_NEW], "chapter": "2", "page": 17,
                        "confidence": .97, "evidence": EVIDENCE}
            decisions = root / "decisions.json"
            decisions.write_text(json.dumps([decision], ensure_ascii=False))
            output = root / "validated.json"
            with patch.object(apply_classification, "BANK", bank), \
                    patch.object(apply_classification, "open_verified_reference", return_value=FakePdf()), \
                    patch.object(sys, "argv", ["apply_classification.py", "--sitting", str(sitting),
                        "--decisions", str(decisions), "--out", str(output), "--pdf-pages"]):
                self.assertEqual(apply_classification.main(), 0)
            source = json.loads(output.read_text())["questions"][0]["sources"][0]
            self.assertEqual(source["reference_year"], 1401)
            self.assertEqual(source["classification_basis"], "same_lineage_proxy")
            self.assertEqual(source["classification_note"], PROXY_NOTE)
            self.assertEqual(source["official_scope_status"], "in_scope")
            self.assertGreaterEqual(source["confidence"]["page"], .85)

    def test_national_1404_exact_outside_scope_note_and_status(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            bank = root / "data/bank"
            bank.mkdir(parents=True)
            catalog = fixture_catalog()
            catalog["validity"] = [validity("national", 1404, FAMILY_OLD, scope=("1",))]
            (bank / "catalog.json").write_text(json.dumps(catalog))
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": pdf_catalog()}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": chapter_maps()}))
            sitting = root / "study.json"
            question = {"number": 222, "subject": "endodontics", "stem": "Synthetic question?",
                        "choices": ["a", "b"], "answer": {"choice": 2, "status": "final"}}
            sitting.write_text(json.dumps({
                "format": "fanoos.classification.study-only/1", "exam_type": "national",
                "year": 1404, "round": 1, "questions": [question],
            }))
            note = apply_classification.national_outside_scope_note(1404)
            decision = {"number": 222, "edition": FAMILY_OLD, "chapter": "2", "page": 17,
                        "confidence": .97, "evidence": EVIDENCE, "outside_scope_note": note}
            decisions = root / "decisions.json"
            decisions.write_text(json.dumps([decision], ensure_ascii=False))
            output = root / "validated.json"
            with patch.object(apply_classification, "BANK", bank), \
                    patch.object(apply_classification, "open_verified_reference", return_value=FakePdf()), \
                    patch.object(sys, "argv", ["apply_classification.py", "--sitting", str(sitting),
                        "--decisions", str(decisions), "--out", str(output), "--pdf-pages",
                        "--allow-outside-scope"]):
                self.assertEqual(apply_classification.main(), 0)
            source = json.loads(output.read_text())["questions"][0]["sources"][0]
            self.assertEqual(source["official_scope_status"], "outside_scope_exam_question")
            self.assertEqual(source["official_scope_note"], note)
            self.assertTrue(apply_classification.valid_non_residency_outside_scope_note(
                note, "national", 1404))


class NonResidencyProxyAuditTests(unittest.TestCase):
    def test_national_1399_proxy_audit_checks_reference_year_and_newer_search(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            for directory in ("bank-sittings/1399", "classification/decisions/national-1399-2",
                              "classification/sittings", "classification/reports"):
                (root / directory).mkdir(parents=True, exist_ok=True)
            question = {"number": 222, "subject": "endodontics", "stem": "Synthetic question?",
                        "choices": ["a", "b"], "answer": {"choice": 2, "status": "final"}}
            study = {"format": "fanoos.classification.study-only/1", "exam_type": "national",
                     "year": 1399, "round": 2, "questions": [question]}
            decision = {"number": 222, "edition": FAMILY_OLD, "reference_year": 1401,
                        "classification_basis": "same_lineage_proxy", "proxy_note": PROXY_NOTE,
                        "newer_editions_searched": [FAMILY_NEW], "chapter": "2", "page": 17,
                        "confidence": .97, "evidence": EVIDENCE}
            source = {"ref": FAMILY_OLD + "#ch02", "page": "pdf 17", "pdf_page": 17,
                      "pdf_sha256": HASH, "anchor": EVIDENCE, "origin": "ai", "primary": True,
                      "confidence": {"source": .97, "node": .97, "page": .97},
                      "reference_year": 1401, "classification_basis": "same_lineage_proxy",
                      "classification_note": PROXY_NOTE, "official_scope_status": "in_scope",
                      "scope_unverified": False}
            stem = "same-lineage-proxy-q222"
            (root / f"bank-sittings/1399/{stem}-study.json").write_text(json.dumps(study))
            (root / "classification/decisions/national-1399-2/1399-endodontics.json").write_text(
                json.dumps([decision], ensure_ascii=False))
            (root / f"classification/sittings/1399-{stem}-validated.json").write_text(json.dumps({
                **study, "questions": [{**question, "sources": [source]}],
            }))
            (root / "catalog.json").write_text(json.dumps(fixture_catalog()))
            (root / "reference-pdfs.json").write_text(json.dumps({"editions": pdf_catalog()}))
            chapter_map_path = root / "reference-chapter-pages.json"
            chapter_map_path.write_text(json.dumps({"editions": chapter_maps()}))
            with patch.object(AUDIT, "CHAPTERS", chapter_map_path), \
                    patch.object(AUDIT, "open_verified_reference", return_value=FakePdf()):
                result = AUDIT.audit_batch(
                    root, f"national:1399:2:endodontics:{stem}")
            self.assertEqual(result["classification_basis"], "same_lineage_proxy")
            self.assertEqual(result["reference_years"], [1401])
            self.assertEqual(result["mapped"]["222"]["reference_year"], 1401)
            decision.pop("newer_editions_searched")
            (root / "classification/decisions/national-1399-2/1399-endodontics.json").write_text(
                json.dumps([decision], ensure_ascii=False))
            with patch.object(AUDIT, "CHAPTERS", chapter_map_path), \
                    patch.object(AUDIT, "open_verified_reference", return_value=FakePdf()):
                with self.assertRaisesRegex(ValueError, "newer_editions_searched"):
                    AUDIT.audit_batch(root, "national:1399:2:endodontics:same-lineage-proxy-q222")


if __name__ == "__main__":
    unittest.main()
