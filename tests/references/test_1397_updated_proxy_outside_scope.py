"""Keep 1397's owner-approved unlisted chapter path explicit and labelled."""
import sys
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts/references"))
from apply_classification import scope_issue, whole_chapter_is_unlisted, valid_outside_scope_note


class UpdatedProxyOutsideScope1397(unittest.TestCase):
    def test_only_a_wholly_unlisted_chapter_can_use_the_note(self):
        validity = {"scope_chapters": [{"number": "1"}, {"number": "17"}]}
        note = "این صفحه از منبع رسمی ۱۴۰۶ است؛ فصل ۲۲ در سرفصل رسمی ۱۴۰۶ نیست و برای آزمون ۱۳۹۷ نیز خارج از محدودهٔ فهرست رسمی است."
        self.assertIsNotNone(scope_issue(validity, "22"))
        self.assertTrue(whole_chapter_is_unlisted(validity, "22"))
        self.assertTrue(valid_outside_scope_note(note))
        self.assertFalse(whole_chapter_is_unlisted(validity, "17"))

    def test_classifier_auditor_and_writer_require_explicit_outside_status(self):
        classifier = (ROOT / "scripts/references/apply_classification.py").read_text()
        auditor = (ROOT / "scripts/references/audit_private_study_batches.py").read_text()
        writer = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        self.assertIn("outside_note != proxy_note", classifier)
        self.assertIn("سرفصل رسمی ۱۴۰۶", classifier)
        self.assertIn("explicitly_outside_scope", auditor)
        self.assertIn("$explicitlyOutside1406Syllabus", writer)
        self.assertIn("official_scope_status", writer)
        self.assertIn("سرفصل رسمی ۱۴۰۶", writer)


if __name__ == "__main__":
    unittest.main()
