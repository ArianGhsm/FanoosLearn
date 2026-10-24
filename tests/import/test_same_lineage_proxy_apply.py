"""End-to-end source validation for a synthetic same-lineage proxy."""
import json
from pathlib import Path
import sys
from tempfile import TemporaryDirectory
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts" / "references"))
import apply_classification

HASH = "a" * 64
EDITION = "fictional-book@1e"
EVIDENCE = "Synthetic direct page evidence for a proxy source"
NOTE = "این آزمون ۱۳۹۶ از نسخهٔ پروکسیِ سال ۱۳۹۷ استفاده می‌کند؛ رسمی بودن آن برای سال آزمون تأیید نشده است."


class FakePdf:
    source_sha256 = HASH
    page_count = 20

    def page_text(self, page):
        return EVIDENCE


def catalog():
    return {
        "validity": [{
            "exam_type": "residency", "year": 1397,
            "subject": "community-dentistry", "edition": EDITION,
            "official": True, "scope_chapters": [{"number": "1"}],
        }],
        "references": [{"key": "fictional-book", "editions": [{
            "key": "1e", "nodes": [{"key": "ch01", "kind": "chapter",
                                       "number": "1", "title": "Synthetic chapter"}],
        }]}],
    }


class SameLineageApplyTests(unittest.TestCase):
    def test_selected_proxy_requires_note_and_writes_actual_metadata(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            bank = root / "data" / "bank"
            bank.mkdir(parents=True)
            (bank / "catalog.json").write_text(json.dumps(catalog()))
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": {
                EDITION: {"pdf": "verified-server-pdf"},
            }}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": {
                EDITION: {"runs": [[None, 1, 4], ["1", 5, 20]],
                          "pdf_pages": 20, "source_pdf_sha256": HASH},
            }}))
            sitting = root / "study.json"
            sitting.write_text(json.dumps({
                "format": "fanoos.classification.study-only/1", "exam_type": "residency",
                "year": 1396, "round": 1,
                "questions": [{"number": 222, "subject": "community-dentistry",
                               "stem": "Synthetic question?", "choices": ["a", "b"],
                               "answer": {"choice": 2, "status": "final"}}],
            }))
            decision = {"number": 222, "edition": EDITION, "reference_year": 1397,
                        "classification_basis": "same_lineage_proxy", "proxy_note": NOTE,
                        "chapter": "1", "page": 17, "confidence": .97, "evidence": EVIDENCE}
            decisions = root / "decisions.json"
            decisions.write_text(json.dumps([decision], ensure_ascii=False))
            output = root / "validated.json"
            with patch.object(apply_classification, "BANK", bank), \
                    patch.object(apply_classification, "open_verified_reference", return_value=FakePdf()), \
                    patch.object(sys, "argv", ["apply_classification.py", "--sitting", str(sitting),
                        "--decisions", str(decisions), "--out", str(output), "--pdf-pages"]):
                self.assertEqual(apply_classification.main(), 0)
            source = json.loads(output.read_text())["questions"][0]["sources"][0]
            self.assertEqual(source["reference_year"], 1397)
            self.assertEqual(source["classification_basis"], "same_lineage_proxy")
            self.assertEqual(source["classification_note"], NOTE)
            self.assertEqual(source["pdf_page"], 17)
            self.assertEqual(source["pdf_sha256"], HASH)
            self.assertEqual(source["official_scope_status"], "in_scope")
            self.assertIs(source["scope_unverified"], False)

    def test_wrong_or_unlabeled_reference_year_is_rejected(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            bank = root / "data" / "bank"
            bank.mkdir(parents=True)
            (bank / "catalog.json").write_text(json.dumps(catalog()))
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": {
                EDITION: {"pdf": "verified-server-pdf"},
            }}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": {
                EDITION: {"runs": [[None, 1, 4], ["1", 5, 20]],
                          "pdf_pages": 20, "source_pdf_sha256": HASH},
            }}))
            sitting = root / "study.json"
            sitting.write_text(json.dumps({
                "format": "fanoos.classification.study-only/1", "exam_type": "residency",
                "year": 1396, "round": 1,
                "questions": [{"number": 222, "subject": "community-dentistry",
                               "stem": "Synthetic question?", "choices": ["a", "b"],
                               "answer": {"choice": 2, "status": "final"}}],
            }))
            decision = {"number": 222, "edition": EDITION, "reference_year": 1398,
                        "classification_basis": "same_lineage_proxy", "proxy_note": NOTE,
                        "chapter": "1", "page": 17, "confidence": .97, "evidence": EVIDENCE}
            decisions = root / "decisions.json"
            decisions.write_text(json.dumps([decision], ensure_ascii=False))
            output = root / "validated.json"
            with patch.object(apply_classification, "BANK", bank), \
                    patch.object(apply_classification, "open_verified_reference", return_value=FakePdf()), \
                    patch.object(sys, "argv", ["apply_classification.py", "--sitting", str(sitting),
                        "--decisions", str(decisions), "--out", str(output), "--pdf-pages"]):
                self.assertEqual(apply_classification.main(), 1)
            self.assertFalse(output.exists())


    def run_with_newer_edition(self, searched):
        newer = "fictional-book@2e"
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            bank = root / "data" / "bank"
            bank.mkdir(parents=True)
            data = catalog()
            data["validity"].append(dict(data["validity"][0], year=1398, edition=newer))
            data["references"][0]["editions"].append(
                dict(data["references"][0]["editions"][0], key="2e"))
            (bank / "catalog.json").write_text(json.dumps(data))
            run = {"runs": [[None, 1, 4], ["1", 5, 20]], "pdf_pages": 20,
                   "source_pdf_sha256": HASH}
            (bank / "reference-pdfs.json").write_text(json.dumps({"editions": {
                EDITION: {"pdf": "verified-server-pdf"},
                newer: {"pdf": "verified-server-pdf"},
            }}))
            (bank / "reference-chapter-pages.json").write_text(json.dumps({"editions": {
                EDITION: run, newer: run,
            }}))
            sitting = root / "study.json"
            sitting.write_text(json.dumps({
                "format": "fanoos.classification.study-only/1", "exam_type": "residency",
                "year": 1396, "round": 1,
                "questions": [{"number": 222, "subject": "community-dentistry",
                               "stem": "Synthetic question?", "choices": ["a", "b"],
                               "answer": {"choice": 2, "status": "final"}}],
            }))
            decision = {"number": 222, "edition": EDITION, "reference_year": 1397,
                        "classification_basis": "same_lineage_proxy", "proxy_note": NOTE,
                        "chapter": "1", "page": 17, "confidence": .97, "evidence": EVIDENCE}
            if searched is not None:
                decision["newer_editions_searched"] = searched
            decisions = root / "decisions.json"
            decisions.write_text(json.dumps([decision], ensure_ascii=False))
            output = root / "validated.json"
            with patch.object(apply_classification, "BANK", bank), \
                    patch.object(apply_classification, "open_verified_reference", return_value=FakePdf()), \
                    patch.object(sys, "argv", ["apply_classification.py", "--sitting", str(sitting),
                        "--decisions", str(decisions), "--out", str(output), "--pdf-pages"]):
                return apply_classification.main()

    def test_older_edition_needs_newer_search_attestation(self):
        self.assertEqual(self.run_with_newer_edition(None), 1)
        self.assertEqual(self.run_with_newer_edition(["other-book@1e"]), 1)
        self.assertEqual(self.run_with_newer_edition(["fictional-book@2e"]), 0)


if __name__ == "__main__":
    unittest.main()
