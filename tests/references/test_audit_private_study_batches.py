"""Synthetic private classification audit tests; no real exam content."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

FILE = Path(__file__).resolve().parents[2] / "scripts/references/audit_private_study_batches.py"
spec = importlib.util.spec_from_file_location("audit_private_study_batches", FILE)
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)


class AuditTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        for name in ("bank-sittings/1402", "classification/decisions",
                     "classification/sittings", "classification/reports"):
            (self.root / name).mkdir(parents=True, exist_ok=True)
        self.src = self.root / "bank-sittings/1402/community-study.json"
        self.out = self.root / "classification/sittings/1402-community-validated.json"
        self.dec = self.root / "classification/decisions/1402-community-dentistry.json"
        self.chapter_map = self.root / "reference-chapter-pages.json"
        self.question = {"number": 222, "stem": "Synthetic sample question?",
                         "choices": ["alpha", "beta"], "answer": {"choice": 2}}
        self.body = {"format": "fanoos.classification.study-only/1",
                     "year": 1402, "exam_type": "residency", "questions": [self.question]}
        self.source = {"ref": "fictional-book@1e#ch04", "page": "pdf 17",
                       "origin": "ai",
                       "confidence": {"source": .97, "node": .97, "page": .97}}
        self.decision = {"number": 222, "edition": "fictional-book@1e",
                         "chapter": "4", "page": 17, "confidence": .97,
                         "evidence": "this is a clearly synthetic page quote"}
        self.fake_pdf = self.FakePdf()
        self.chapter_map.write_text(json.dumps({"editions": {"fictional-book@1e": {
            "runs": [[None, 1, 1], ["4", 2, 120]],
            "source_pdf_sha256": self.fake_pdf.source_sha256,
        }}}))
        self.chapter_patch = patch.object(audit, "CHAPTERS", self.chapter_map)
        self.chapter_patch.start()
        self.addCleanup(self.chapter_patch.stop)
        self.pdf_patch = patch.object(audit, "open_verified_reference", return_value=self.fake_pdf)
        self.pdf_patch.start()
        self.addCleanup(self.pdf_patch.stop)
        self.save()

    class FakePdf:
        source_sha256 = "a" * 64
        page_count = 120

        def __init__(self):
            self.pages = {}

        def page_text(self, number):
            if number in self.pages:
                return self.pages[number]
            labels = {116: "100", 117: "101", 118: "102", 119: "103"}
            return f"{labels.get(number, '')}\nThis is a clearly synthetic page quote.\nSynthetic source page with more words."

    def save(self):
        self.src.write_text(json.dumps(self.body))
        self.out.write_text(json.dumps({**self.body, "questions": [
            {**self.question, "sources": [self.source]}]}))
        self.dec.write_text(json.dumps([self.decision]))

    def test_accepted_page_map_and_unchanged_question(self):
        result = audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.assertEqual((result["questions"], result["accepted"], result["pending"]), (1, 1, 0))
        self.assertTrue(result["question_content_identical"])
        self.source["page"] = "17"
        self.save()
        self.assertEqual(audit.audit_batch(self.root, "1402:community-dentistry:community")["accepted"], 1)

    def test_changed_answer_rejected(self):
        self.body["questions"][0]["answer"] = {"choice": 1}
        self.src.write_text(json.dumps(self.body))
        with self.assertRaisesRegex(ValueError, "question or answer content changed"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_wrong_chapter_or_origin_rejected(self):
        self.source["ref"] = "fictional-book@1e#ch05"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong chapter map"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.source["ref"] = "fictional-book@1e#ch04"
        self.source["origin"] = "human"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_confidence_drift_rejected(self):
        self.source["confidence"]["node"] = 0.95
        self.save()
        with self.assertRaisesRegex(ValueError, "unsupported confidence"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")


    def test_printed_page_from_original_pdf_requires_neighbor_support(self):
        """Book printed 101 on PDF 117; printed-label mismatch is legitimate."""
        self.fake_pdf.pages = {
            116: "100\nSynthetic page text with enough content for this test.",
            117: "101\nThis is a clearly synthetic page quote.\nSynthetic source page with more words.",
            118: "102\nSynthetic page text with enough content for this test.",
            119: "103\nSynthetic page text with enough content for this test.",
        }
        self.decision["page"] = 117
        self.source["page"] = "101"
        self.save()
        result = audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.assertEqual(result["accepted"], 1)
        # A chapter/table number on one page may look like a printed page.
        self.source["page"] = "10"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_printed_page_without_two_consistent_neighbors_rejected(self):
        self.fake_pdf.pages = {
            116: "100\nSynthetic page text with enough content for this test.",
            117: "101\nThis is a clearly synthetic page quote.\nSynthetic source page with more words.",
            118: "Page footer not readable\nSynthetic page text with enough content for this test.",
            119: "No footer\nSynthetic page text with enough content for this test.",
        }
        self.decision["page"] = 117
        self.source["page"] = "101"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_printed_page_without_pdf_label_rejected(self):
        self.fake_pdf.pages = {
            116: "No numeric label\nSynthetic page text with enough content for this test.",
            117: "No numeric label\nThis is a clearly synthetic page quote.\nSynthetic source page with more words.",
            118: "No numeric label\nSynthetic page text with enough content for this test.",
            119: "No numeric label\nSynthetic page text with enough content for this test.",
        }
        self.decision["page"] = 117
        self.source["page"] = "101"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_save_private_location_and_idempotence(self):
        out = self.root / "classification/reports/audit.json"
        audit.save_private(self.root, out, {"safe": True})
        audit.save_private(self.root, out, {"safe": True})
        with self.assertRaisesRegex(ValueError, "differs"):
            audit.save_private(self.root, out, {"safe": False})
        with self.assertRaisesRegex(ValueError, "protected"):
            audit.save_private(self.root, self.root / "public-report.json", {"safe": True})


if __name__ == "__main__":
    unittest.main()
