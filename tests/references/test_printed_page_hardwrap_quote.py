"""Evidence matches preserve visible page text and normalize layout whitespace only."""
import json
import shutil
import subprocess
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
VERIFIER = ROOT / "apps/platform/src/Bank/PrintedBookPageEvidence.php"


@unittest.skipUnless(shutil.which("php"), "PHP required")
class ExactPageEvidenceTests(unittest.TestCase):
    def invoke(self, method, *values):
        args = ",".join(
            ("json_decode($argv[" + str(index + 2) + "], true)"
             if isinstance(value, list) else "$argv[" + str(index + 2) + "]")
            for index, value in enumerate(values)
        )
        script = (
            "require $argv[1];"
            "echo json_encode((new ReflectionMethod("
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::class, '" + method + "'))"
            "->invoke(null," + args + "), JSON_UNESCAPED_UNICODE);"
        )
        run = subprocess.run(
            ["php", "-r", script, str(VERIFIER),
             *(json.dumps(value) if isinstance(value, list) else value for value in values)],
            text=True, capture_output=True, check=True,
        )
        return json.loads(run.stdout)

    def contains(self, page, quote, verified_labels=None):
        if verified_labels is None:
            return self.invoke("containsExactFragment", page, quote)
        return self.invoke("containsExactFragment", page, quote, list(verified_labels))

    def invoke_attached_separator(self, page, boundary, left):
        script = (
            "require $argv[1];"
            "echo json_encode((new ReflectionMethod("
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::class, 'attachedSeparator'))"
            "->invoke(null, $argv[2], (int) $argv[3], $argv[4] === '1'), JSON_UNESCAPED_UNICODE);"
        )
        run = subprocess.run(
            ["php", "-r", script, str(VERIFIER), page, str(boundary), "1" if left else "0"],
            text=True, capture_output=True, check=True,
        )
        return json.loads(run.stdout)

    def test_case_and_visible_punctuation_are_exact(self):
        self.assertTrue(self.contains("Four-flap operation", "Four-flap operation"))
        self.assertFalse(self.contains("FOUR-FLAP operation", "Four-flap operation"))
        self.assertFalse(self.contains("Four-flap operation", "Fourflap operation"))
        self.assertFalse(self.contains("reported 1.2%", "reported 12%"))
        self.assertFalse(self.contains("the patient’s risk", "the patient's risk"))
        self.assertFalse(self.contains("The result, therefore", "The result therefore"))

    def test_layout_whitespace_and_bidi_controls_are_ignored(self):
        page = "Start\u200f of\u202b the finding,\n  with evidence."
        quote = "Start of the finding, with evidence."
        self.assertTrue(self.contains(page, quote))
        self.assertEqual(self.invoke("normalize", page), quote)

    def test_line_end_hyphen_and_zwnj_are_preserved(self):
        self.assertTrue(self.contains("rea-\n sons were observed", "rea- sons were observed"))
        self.assertFalse(self.contains("rea-\n sons were observed", "reasons were observed"))
        self.assertFalse(self.contains("این\u200cکار درست است", "اینکار درست است"))
        self.assertFalse(self.contains("suffice\u00ad now", "suffice now"))
        self.assertFalse(self.contains("کتابِ مفید است", "کتاب"))

    def test_joiners_marks_numbers_and_unreadable_boundaries_are_exact(self):
        self.assertFalse(self.contains("بیمار می\u200cشود و خوب است", "بیمار می"))
        self.assertFalse(self.contains("cafe\u0301 is noted", "cafe"))
        self.assertFalse(self.contains("1.2% of patients", "2% of patients"))
        self.assertFalse(self.contains("1,200 patients", "200 patients"))
        self.assertFalse(self.contains("1  200 patients", "200 patients"))
        self.assertFalse(self.contains("1  200 patients", "1"))
        self.assertFalse(self.contains("-5 mm measured", "5 mm measured"))
        self.assertFalse(self.contains("1/2 sample", "2 sample"))
        self.assertFalse(self.contains("2 – 5 mm was measured", "5 mm was measured"))
        self.assertFalse(self.contains("2 — 5 mm was measured", "2"))
        self.assertFalse(self.contains("≥ 5 mm from the apex", "5 mm from the apex"))
        self.assertFalse(self.contains("about 12 × 10³ was recorded", "about 12"))
        self.assertFalse(self.contains("~ 5 mm was measured", "5 mm was measured"))
        self.assertFalse(self.contains("≈ 5 mm was measured", "5 mm was measured"))
        self.assertFalse(self.contains("about 12 x 10^3 was recorded", "about 12"))
        self.assertFalse(self.contains("⩾ 5 mm from the apex", "5 mm from the apex"))
        self.assertFalse(self.contains("⩽ 5 mm from the apex", "5 mm from the apex"))
        self.assertFalse(self.contains("～ 5 mm from the apex", "5 mm from the apex"))
        self.assertFalse(self.contains("≃ 5 mm from the apex", "5 mm from the apex"))
        self.assertFalse(self.contains("≅ 5 mm from the apex", "5 mm from the apex"))
        self.assertFalse(self.contains("5±2 mm was measured", "2 mm was measured"))
        self.assertFalse(self.contains(".5mm loss was observed", "5mm loss was observed"))
        self.assertFalse(self.contains("٫۵mm loss was observed", "۵mm loss was observed"))
        self.assertFalse(self.contains("1⁄2 sample was tested", "2 sample was tested"))
        self.assertFalse(self.contains("∼ 5 mm from the apex", "5 mm from the apex"))
        self.assertFalse(self.contains("2 ⋅ 10³ was recorded", "2"))
        self.assertFalse(self.contains("2 ∙ 10³ was recorded", "2"))
        self.assertFalse(self.contains("2 to 5 mm thick in the sample", "5 mm thick in the sample"))
        self.assertFalse(self.contains("less than 5 mm from the apex", "less than 5"))
        self.assertFalse(self.contains("up to 5 mm from the apex", "up to 5"))
        self.assertFalse(self.contains("A \ue001nding is shown", "nding is shown"))
        self.assertFalse(self.contains("A\x1cB", "A B"))
        self.assertTrue(self.contains("The result - was clear", "The result"))

    def test_php_numeric_boundary_helpers_reject_truncated_context(self):
        left_cases = [
            ("1.2mm", 2), ("1  200", 3), ("2 – 5 mm", 4),
            ("⩽ 5 mm", 2), ("⩾ 5 mm", 2), ("∼ 5 mm", 2),
            ("～ 5 mm", 2), ("≃ 5 mm", 2), ("≅ 5 mm", 2),
            ("2 ⋅ 10³", 4), ("2 ∙ 10³", 4), (".5mm", 1),
            ("٫۵mm", 1), ("1⁄2", 2),
        ]
        right_cases = [
            ("1.2mm", 1), ("1  200", 1), ("1\u00a0200", 1),
            ("1\u2009200", 1), ("2 – 5 mm", 1), ("2 ≤ 5 mm", 1),
            ("2 ⩾ 5 mm", 1), ("2 ⩽ 5 mm", 1), ("2 ～ 5 mm", 1),
            ("2 ≃ 5 mm", 1), ("2 ≅ 5 mm", 1), ("2 ⋅ 10³", 1),
            ("2 ∙ 10³", 1),
            ("1⁄2", 1), ("5±2 mm", 1), ("12 %", 2),
        ]
        for page, boundary in left_cases:
            with self.subTest(side="left", page=page):
                self.assertTrue(self.invoke_attached_separator(page, boundary, True))
        for page, boundary in right_cases:
            with self.subTest(side="right", page=page):
                self.assertTrue(self.invoke_attached_separator(page, boundary, False))

    def test_numeric_edge_words_include_attached_units(self):
        self.assertTrue(self.invoke("hasNumericEdgeWord", "5mm"))
        self.assertTrue(self.invoke("hasNumericEdgeWord", "from 5mm"))
        self.assertTrue(self.invoke("hasNumericEdgeWord", "The 5mm length was measured"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "We measured the 5mm length very carefully today"))
        self.assertTrue(self.invoke("hasNumericEdgeWord", "a probing depth of 5 mm"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "mm to 5 mm deep pocket"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "bone loss of 5 per cent"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "recession of 2 mm per year"))
        self.assertTrue(self.invoke("hasNumericEdgeWord", "pocket depths between 5 mm and 7 mm"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "smokers of 10 cigarettes per day"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "The sample included 2 widgets per day"))
        self.assertTrue(self.invoke("hasNumericEdgeWord", "2 sites per tooth"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "The patient had a tooth removed"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "دوز دارو ۵ ميلي\u200cگرم در كيلوگرم"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "In the absence of a measurable change"))
        self.assertFalse(self.invoke("hasNumericEdgeWord", "the patient’s"))
        self.assertFalse(self.contains("a probing depth of 5 mm to 7 mm", "a probing depth of 5 mm"))
        self.assertFalse(self.contains("from 2 mm to 5 mm deep pocket", "mm to 5 mm deep pocket"))
        self.assertFalse(self.contains("2 mm measurement starts here", "mm measurement starts here"))
        self.assertFalse(self.contains("bone loss of 5 per cent to 10 per cent", "bone loss of 5 per cent"))
        self.assertFalse(self.contains("recession of 2 mm per year to 4 mm per year", "recession of 2 mm per year"))
        self.assertFalse(self.contains("pocket depths between 5 mm and 7 mm", "pocket depths between 5 mm and"))
        self.assertFalse(self.contains("smokers of 10 cigarettes per day or 20 cigarettes per day", "smokers of 10 cigarettes per day"))
        self.assertFalse(self.contains("The probing depth is 5 mm or more for disease risk.", "The probing depth is 5 mm or"))
        self.assertFalse(self.contains("the pocket depths of 5 mm at baseline vs. 7 mm in controls", "the pocket depths of 5 mm at baseline"))
        self.assertFalse(self.contains("pocket depths of 5 mm at baseline vs. 7 mm in controls", "pocket depths of 5 mm at baseline vs."))
        self.assertFalse(self.contains("The probe was ۵ میلی‌متر یا بیشتر.", "The probe was ۵ میلی‌متر یا"))
        self.assertTrue(self.contains("The depth was 5 mm in the first group. In 7 cases it increased.", "The depth was 5 mm in the first group."))
        self.assertFalse(self.contains("2 mm or 3 mm", "2 mm"))
        self.assertFalse(self.contains("دوز دارو ۵ ميلي\u200cگرم در كيلوگرم تا ۱۰", "دوز دارو ۵ ميلي\u200cگرم در كيلوگرم"))
        self.assertFalse(self.contains("patients aged 5 years or older were included", "years or older were included"))
        self.assertFalse(self.contains("5 per cent of patients were affected", "per cent of patients were affected"))
        self.assertFalse(self.contains("۵ درصد بیماران مبتلا بودند", "درصد بیماران مبتلا بودند"))
        self.assertFalse(self.contains("12 teeth were extracted in the clinic", "teeth were extracted in the clinic"))
        self.assertFalse(self.contains("patients were prescribed 10 mg twice daily or 20 mg twice daily", "patients were prescribed 10 mg twice daily"))
        self.assertFalse(self.contains("bone loss of 5 per cent to 10 per cent", "bone loss of 5 per cent"))
        self.assertFalse(self.contains("recession of 2 mm per year to 4 mm per year", "recession of 2 mm per year"))
        self.assertFalse(self.contains("pocket depths between 5 mm and 7 mm", "pocket depths between 5 mm and"))

    def test_quote_cannot_match_inside_a_longer_word(self):
        self.assertFalse(self.contains("preclinical effects are clear", "clinical effects are clear"))
        self.assertFalse(self.contains("101\nIn the absence of disease.", "In the absence of disease."))
        self.assertTrue(self.contains("101\nIn the absence of disease.", "In the absence of disease.", {"101"}))
        self.assertTrue(self.contains("101\nThe tooth was involved.", "The tooth was involved.", {"101"}))
        self.assertFalse(self.contains("2 patients had 5 mm pockets and more signs.", "patients had 5 mm pockets and"))
        self.assertTrue(self.contains("The sample included 2 widgets per day.", "The sample included 2 widgets per day."))
        self.assertTrue(self.contains(
            "The sample included 2 widgets per day.", "The sample included 2 widgets per day."))
        self.assertFalse(self.contains("Results\n12\npatients had 5 mm pockets in the study.", "patients had 5 mm pockets in the study."))
        self.assertTrue(self.contains("We saw the patient’s response.", "the patient’s response."))
        self.assertTrue(self.contains("the clinical effects are clear", "clinical effects are clear"))

    def invoke_evidence_fragments(self, method, value):
        script = (
            "require $argv[1];"
            "$input = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);"
            "echo json_encode((new ReflectionMethod("
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::class, '" + method + "'))"
            "->invoke(null, $input), JSON_UNESCAPED_UNICODE);"
        )
        run = subprocess.run(
            ["php", "-r", script, str(VERIFIER), json.dumps(value, ensure_ascii=False)],
            text=True, capture_output=True, check=True,
        )
        return json.loads(run.stdout)

    def invoke_page_evidence(self, edition, page, evidence):
        script = (
            "require $argv[1];"
            "$evidence = json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR);"
            "echo json_encode((new ReflectionMethod("
            "Fanoos\\Platform\\Bank\\PrintedBookPageEvidence::class, 'pageContainsEvidence'))"
            "->invoke(null, $argv[2], (int) $argv[3], $evidence), JSON_UNESCAPED_UNICODE);"
        )
        run = subprocess.run(
            ["php", "-r", script, str(VERIFIER), edition, str(page), json.dumps(evidence, ensure_ascii=False)],
            text=True, capture_output=True, check=True,
        )
        return json.loads(run.stdout)

    def test_page_evidence_requires_four_words_per_fragment(self):
        self.assertEqual(
            self.invoke_evidence_fragments("exactEvidenceFragments", [
                "The first fragment has four words...The second fragment has four words.",
            ]),
            ["The first fragment has four words", "The second fragment has four words."],
        )
        self.assertIsNone(self.invoke_evidence_fragments("exactEvidenceFragments", ["tooth."]))
        self.assertIsNone(self.invoke_evidence_fragments("exactEvidenceFragments", [
            "Only three words...The second fragment has four words.",
        ]))
        self.assertFalse(self.invoke_page_evidence("fanoos-book@1e", 1, ["tooth."]))

    def test_unreadable_glyphs_are_rejected(self):
        self.assertTrue(self.invoke("hasUnreadableEvidenceGlyph", "quote \ue000 text"))
        self.assertTrue(self.invoke("hasUnreadableEvidenceGlyph", "quote \ufffd text"))
        self.assertTrue(self.invoke("hasUnreadableEvidenceGlyph", "quote " + chr(0xF0000) + " text"))
        self.assertTrue(self.invoke("hasUnreadableEvidenceGlyph", "quote \ufffc text"))
        self.assertFalse(self.invoke("hasUnreadableEvidenceGlyph", "quote with readable text"))


if __name__ == "__main__":
    unittest.main()
