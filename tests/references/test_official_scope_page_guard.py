"""Static contract tests for selected and excluded page-level syllabus scope."""
from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[2]


class OfficialScopePageGuardContract(unittest.TestCase):
    def test_source_writers_use_printed_page_scope_disposition(self):
        paths = [
            ROOT / "scripts/references/repair_verified_source_pages.php",
            ROOT / "scripts/references/repair_verified_source_chapter_pages.php",
            ROOT / "scripts/references/mark_verified_outside_scope_sources.php",
            ROOT / "scripts/references/repair_verified_ai_outside_scope_chapter_pages.php",
        ]
        for path in paths:
            with self.subTest(operator=path.name):
                self.assertIn("OfficialScopePageGuard::pageDisposition", path.read_text())
        human_operator = (ROOT / "scripts/references/repair_verified_human_outside_scope_chapter_pages.php").read_text()
        human_guard = (ROOT / "apps/platform/src/Bank/VerifiedHumanOutsideScopeChapterPageGuard.php").read_text()
        self.assertIn("VerifiedHumanOutsideScopeChapterPageGuard::chapterIsOutOfScope", human_operator)
        self.assertIn("OfficialScopePageGuard::pageDisposition", human_guard)

    def test_whole_year_gate_requires_a_printed_page_for_partial_scope(self):
        source = (ROOT / "apps/platform/src/Bank/ResidencyYearPublicationGate.php").read_text()
        self.assertIn("s.printed_page", source)
        self.assertIn("OfficialScopePageGuard::pageDisposition", source)
        self.assertIn("$pageScope === null", source)
        self.assertIn("$pageScope === false", source)


if __name__ == "__main__":
    unittest.main()
