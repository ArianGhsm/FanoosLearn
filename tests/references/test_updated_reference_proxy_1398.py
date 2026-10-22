"""Keep owner-approved updated references explicit, verifiable and year-labeled."""
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
        self.assertFalse(valid_updated_reference_note(note + ("x" * 300)))

    def test_1397_allows_only_listed_subjects_with_1406_and_clear_note(self):
        subjects = {
            "community-dentistry", "dental-materials", "endodontics",
            "operative-dentistry", "oral-medicine", "oral-pathology",
            "oral-radiology", "oral-surgery", "orthodontics",
            "pediatric-dentistry", "periodontics", "prosthodontics",
        }
        for subject in subjects:
            self.assertTrue(is_updated_reference_proxy(
                1397, subject, "some-current-book@6e", 1406, "updated_reference_proxy"))
        for args in [
            (1397, "unknown-subject", "some-current-book@6e", 1406, "updated_reference_proxy"),
            (1397, "oral-surgery", "bad edition", 1406, "updated_reference_proxy"),
            (1397, "oral-surgery", "hupp-oral-surgery@7e", 1405, "updated_reference_proxy"),
            (1396, "oral-surgery", "hupp-oral-surgery@7e", 1406, "updated_reference_proxy"),
        ]:
            self.assertFalse(is_updated_reference_proxy(*args))
        note = "این منبع رسمی دستیاری ۱۴۰۶ است؛ در فهرست رسمی ۱۳۹۷ خارج از محدوده آزمون و فقط برای یافتن محل مبحث استفاده شده است."
        self.assertTrue(valid_updated_reference_note(note, 1397))
        self.assertFalse(valid_updated_reference_note(note, 1398))
        self.assertFalse(valid_updated_reference_note(note + ("x" * 300), 1397))

    def test_1397_cross_subject_is_limited_to_endodontic_q95_and_hupp7(self):
        self.assertTrue(is_updated_reference_proxy(
            1397, "endodontics", "hupp-oral-surgery@7e", 1406,
            "updated_reference_proxy", "oral-surgery", 95))
        for args in [
            (1397, "endodontics", "hupp-oral-surgery@7e", 1406,
             "updated_reference_proxy", "oral-surgery", 94),
            (1397, "endodontics", "hupp-oral-surgery@7e", 1406,
             "updated_reference_proxy", "oral-surgery", 96),
            (1397, "endodontics", "torabinejad-endodontics@6e", 1406,
             "updated_reference_proxy", "oral-surgery", 95),
        ]:
            self.assertFalse(is_updated_reference_proxy(*args))

    def test_versioned_validator_auditor_writer_and_importer_keep_proxy_separate(self):
        classifier = (ROOT / "scripts/references/apply_classification.py").read_text()
        auditor = (ROOT / "scripts/references/audit_private_study_batches.py").read_text()
        writer = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        importer = (ROOT / "scripts/references/import_verified_sources.php").read_text()
        self.assertIn("--updated-reference-proxy", classifier)
        self.assertIn("updated_reference_proxy", classifier)
        self.assertIn("1397 residency sources officially listed in 1406", classifier)
        self.assertIn("UPDATED_PROXY_REFERENCE_YEAR", auditor)
        self.assertIn("reference_years = [UPDATED_PROXY_REFERENCE_YEAR]", auditor)
        self.assertIn("hupp-oral-surgery@7e", writer)
        self.assertIn("outside_scope_exam_question", writer)
        self.assertIn("official_scope_note", writer)
        self.assertIn("$year === 1397", writer)
        self.assertIn("$n === 95", writer)
        self.assertIn("$n === 88", writer)
        self.assertIn("official-endo-q88", importer)
        self.assertIn("recovery-1397-official-endo-q88-20261021", importer)
        self.assertIn("UPDATED_PROXY_REFERENCE_YEAR", auditor)
        self.assertIn("surgery-modern-hupp7-20261021", importer)
        self.assertIn("recovery-1397-updated-reference-proxy-20261021", importer)
        self.assertIn("$updated1397ProxyCounts", importer)
        self.assertIn("updated_reference_proxy", importer)


if __name__ == "__main__":
    unittest.main()
