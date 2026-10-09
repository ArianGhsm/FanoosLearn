"""Synthetic private classification audit tests; no real exam content."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

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
        self.save()

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
