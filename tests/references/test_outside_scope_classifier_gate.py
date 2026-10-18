import sys
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts/references"))

from apply_classification import valid_outside_scope_note, whole_chapter_is_unlisted


class OutsideScopeClassifierGateTest(unittest.TestCase):
    def test_only_unlisted_chapters_from_structured_scope_can_use_exception(self):
        scope = [{"number": str(number)} for number in range(1, 26)]
        self.assertTrue(whole_chapter_is_unlisted({"scope_chapters": scope}, "26"))
        self.assertFalse(whole_chapter_is_unlisted({"scope_chapters": scope}, "25"))

    def test_missing_partial_or_malformed_scope_fails_closed(self):
        self.assertFalse(whole_chapter_is_unlisted({}, "26"))
        self.assertFalse(whole_chapter_is_unlisted({"scope_chapters": []}, "26"))
        self.assertFalse(whole_chapter_is_unlisted({"scope_chapters": [{"number": "26", "partial": "selected"}]}, "26"))
        self.assertFalse(whole_chapter_is_unlisted({"scope_chapters": [{"partial": "missing number"}]}, "26"))

    def test_outside_scope_note_must_be_explicit_and_long_enough(self):
        self.assertTrue(valid_outside_scope_note("فصل ۲۶ خارج از محدوده رسمی آزمون است."))
        for note in (None, "", "outside scope", "خارج از محدوده"):
            self.assertFalse(valid_outside_scope_note(note))


if __name__ == "__main__":
    unittest.main()
