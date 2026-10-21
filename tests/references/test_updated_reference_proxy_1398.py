"""Keep the owner-approved Hupp7 bridge explicit, verifiable and year-labeled."""
import sys
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts/references"))
from updated_reference_proxy_policy import is_updated_reference_proxy, valid_updated_reference_note


class UpdatedReferenceProxy1398(unittest.TestCase):
    def test_only_the_exact_hupp7_1406_proxy_is_allowed(self):
        self.assertTrue(is_updated_reference_proxy(
            1398, "oral-surgery", "hupp-oral-surgery@7e", 1406, "updated_reference_proxy"))
        for args in [
            (1398, "oral-surgery", "hupp-oral-surgery@6e", 1406, "updated_reference_proxy"),
            (1398, "pediatric-dentistry", "hupp-oral-surgery@7e", 1406, "updated_reference_proxy"),
            (1398, "oral-surgery", "hupp-oral-surgery@7e", 1405, "updated_reference_proxy"),
            (1405, "oral-surgery", "hupp-oral-surgery@7e", 1406, "updated_reference_proxy"),
        ]:
            self.assertFalse(is_updated_reference_proxy(*args))

    def test_note_discloses_both_exam_and_reference_years(self):
        note = "این ویرایش ۷ منبع رسمی دستیاری ۱۴۰۶ است و در فهرست رسمی ۱۳۹۸ خارج از محدوده بوده؛ به درخواست مالک برای تعیین صفحه استفاده شد."
        self.assertTrue(valid_updated_reference_note(note))
        self.assertFalse(valid_updated_reference_note("ویرایش جدیدتر استفاده شد."))

    def test_versioned_validator_auditor_writer_and_importer_keep_proxy_separate(self):
        classifier = (ROOT / "scripts/references/apply_classification.py").read_text()
        auditor = (ROOT / "scripts/references/audit_private_study_batches.py").read_text()
        writer = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        importer = (ROOT / "scripts/references/import_verified_sources.php").read_text()
        self.assertIn("--updated-reference-proxy", classifier)
        self.assertIn("updated_reference_proxy", classifier)
        self.assertIn("UPDATED_PROXY_REFERENCE_YEAR", auditor)
        self.assertIn("reference_years = [UPDATED_PROXY_REFERENCE_YEAR]", auditor)
        self.assertIn("hupp-oral-surgery@7e", writer)
        self.assertIn("outside_scope_exam_question", writer)
        self.assertIn("official_scope_note", writer)
        self.assertIn("surgery-modern-hupp7-20261021", importer)
        self.assertIn("updated_reference_proxy", importer)


if __name__ == "__main__":
    unittest.main()
