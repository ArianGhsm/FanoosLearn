"""Regression for strict anchor comparison across discretionary PDF line hyphens.

Only word-end hyphens followed by a physical line break may be removed.
Source page SHA, official chapter scope, exact page labels, and live original
source-string equality are validated independently by guarded operators.
"""
import json
import shutil
import subprocess
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
VERIFIER = ROOT / "apps/platform/src/Bank/PrintedBookPageEvidence.php"


@unittest.skipUnless(shutil.which("php"), "PHP required")
class EndlineWordwrapQuoteTests(unittest.TestCase):
    def normalized(self, value):
        script = (
            "require $argv[1];"
            "$m = new ReflectionMethod("
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::class, 'normalize');"
            "echo json_encode($m->invoke(null, $argv[2]), JSON_UNESCAPED_UNICODE);"
        )
        run = subprocess.run(
            ["php", "-r", script, str(VERIFIER), value],
            text=True, capture_output=True, check=True,
        )
        return json.loads(run.stdout)

    def matches(self, quote, extracted_page):
        return self.normalized(quote) in self.normalized(extracted_page)

    def test_rejoins_only_hard_wrapped_words_from_the_same_page(self):
        self.assertTrue(self.matches(
            "very few are extracted for endodontic reasons",
            "Of these, very few are extracted for endodontic rea-\n"
            "           sons.",
        ))
        self.assertTrue(self.matches(
            "Four-flap operation for an extensive cleft.",
            "Four-flap operation for an exten-\n     sive cleft.",
        ))
        self.assertTrue(self.matches(
            "the pools of amyloid exhibit an apple-green birefringence",
            "the pools of amyloid exhibit an apple-green birefrin-\n"
            "    gence",
        ))

    def test_keeps_ordinary_hyphens_and_source_punctuation(self):
        self.assertTrue(self.matches("four-flap operation", "FOUR-FLAP   OPERATION"))
        self.assertFalse(self.matches("fourflap operation", "four-flap operation"))
        self.assertFalse(self.matches("oxygen dependent", "oxygen-independent"))
        self.assertFalse(self.matches("reported 1.2%", "reported 12%"))
        self.assertFalse(self.matches("unrelated finding", "unrelated different finding"))

    def test_requires_actual_line_ending_not_arbitrary_whitespace(self):
        self.assertFalse(self.matches("electronic", "electro-  nic"))
        self.assertTrue(self.matches("electronic", "electro-\r\n nic"))
        self.assertEqual(self.normalized("First\n  second"), "first second")


if __name__ == "__main__":
    unittest.main()
