"""Cycle 3: v3 candidates bind only to REAL protected provenance audits."""
import hashlib
import json
import os
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/classification_v3"))
import audit_adapter as A  # noqa: E402

REAL = Path("/srv/fanoos/shared/research/classification/reports")
REAL_AUDIT = REAL / "1391-endodontics-four-independent-audit-20261012.json"
REAL_RECEIPT = REAL / "1391-endodontics-four-applied-20261012.json"
OTHER = os.geteuid() + 1


def audit_body(page=12, sha="c" * 64):
    return {"format": A.AUDIT_FORMAT, "research_only": True, "questions": 1, "accepted": 1, "pending": 0,
            "batches": [{"batch": "b", "questions": 1, "accepted": 1, "pending": 0,
                         "study_sha256": "1" * 64, "validated_sha256": "2" * 64, "decisions_sha256": "3" * 64,
                         "mapped": {"5": {"ref": "x@1e#ch2", "pdf_page": page}},
                         "source_pdf_provenance": {"x@1e": {"sha256": sha}}}]}


class AdapterTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(); self.root = Path(self.tmp.name)
        self.rep = self.root / "classification" / "reports"; self.rep.mkdir(parents=True)
        self.audit = self.rep / "a.json"; self.audit.write_text(json.dumps(audit_body()))
        self.sha = hashlib.sha256(self.audit.read_bytes()).hexdigest()

    def tearDown(self):
        self.tmp.cleanup()

    def receipt(self, **kw):
        r = {"format": A.RECEIPT_FORMAT, "audit_sha256": self.sha, "questions_changed": 0,
             "answers_changed": 0, "choices_changed": 0} | kw
        p = self.rep / "r.json"; p.write_text(json.dumps(r)); return p

    def v(self, uid=OTHER, page=12, receipt=None, path=None):
        return A.verify(path or self.audit, "b", 5, "x@1e#ch2", page, "c" * 64, receipt, self.root, uid)

    def test_self_written_audit_is_rejected(self):
        r = self.v(uid=os.geteuid())
        self.assertIn("audit_owned_by_producer", r["failures"]); self.assertFalse(r["import_authorised"])

    def test_operator_owned_audit_matches_exact_row_only(self):
        self.assertEqual(self.v()["status"], "independently_audited")
        self.assertIn("audit_pdf_page_mismatch", self.v(page=13)["failures"])
        self.assertIn("audit_pdf_sha_mismatch",
                      A.verify(self.audit, "b", 5, "x@1e#ch2", 12, "d" * 64, None, self.root, OTHER)["failures"])
        self.assertIn("audit_question_not_mapped",
                      A.verify(self.audit, "b", 6, "x@1e#ch2", 12, "c" * 64, None, self.root, OTHER)["failures"])

    def test_fake_receipts_rejected(self):
        self.assertIn("receipt_audit_sha_mismatch", self.v(receipt=self.receipt(audit_sha256="0" * 64))["failures"])
        self.assertIn("receipt_not_applied_verified",
                      self.v(receipt=self.receipt(status="manual_reconciliation_required"))["failures"])
        self.assertIn("receipt_content_changed", self.v(receipt=self.receipt(answers_changed=1))["failures"])
        self.assertEqual(self.v(receipt=self.receipt())["status"], "independently_audited_and_receipted")

    def test_wrong_format_symlink_and_location_rejected(self):
        bad = self.rep / "f.json"; bad.write_text(json.dumps(audit_body() | {"format": "v3-self-audit"}))
        self.assertIn("audit_format", self.v(path=bad)["failures"])
        link = self.rep / "l.json"; link.symlink_to(self.audit)
        self.assertIn("audit_symlink", self.v(path=link)["failures"])
        out = self.root / "a.json"; out.write_text(self.audit.read_text())
        self.assertIn("audit_outside_protected_reports", self.v(path=out)["failures"])

    @unittest.skipUnless(REAL_AUDIT.is_file() and REAL_RECEIPT.is_file(), "server-only real receipt fixture")
    def test_real_independent_audit_and_applied_receipt(self):
        audit, sha, fail = A.load_real_audit(REAL_AUDIT)
        self.assertEqual(fail, [])
        b = audit["batches"][0]; n, m = next(iter(b["mapped"].items()))
        pdf_sha = b["source_pdf_provenance"][m["ref"].split("#")[0]]["sha256"]
        ok = A.verify(REAL_AUDIT, b["batch"], int(n), m["ref"], m["pdf_page"], pdf_sha, REAL_RECEIPT)
        self.assertEqual(ok["status"], "independently_audited_and_receipted")
        self.assertFalse(ok["import_authorised"])
        off = A.verify(REAL_AUDIT, b["batch"], int(n), m["ref"], m["pdf_page"] + 1, pdf_sha, REAL_RECEIPT)
        self.assertIn("audit_pdf_page_mismatch", off["failures"])


if __name__ == "__main__":
    unittest.main()
