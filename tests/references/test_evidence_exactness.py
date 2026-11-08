"""Exact page evidence preserves visible wording; search normalization is candidate-only."""
import sys
import unittest
from pathlib import Path

REFERENCE_SCRIPTS = Path(__file__).resolve().parents[2] / "scripts" / "references"
sys.path.insert(0, str(REFERENCE_SCRIPTS))
from evidence_match import (contains_exact_fragment, evidence_key, evidence_word_count,
                             has_numeric_edge_word, has_unreadable_glyph,
                             _left_separator_is_internal, _right_separator_is_internal)
from apply_classification import Books, evidence_page_issue, printed_page
from find_in_books import quote_page


class ExactEvidenceTests(unittest.TestCase):
    def test_only_layout_whitespace_and_bidi_controls_are_ignored(self):
        page = "The\u200f result,\n  is exact."
        self.assertEqual(evidence_key(page), "The result, is exact.")
        self.assertTrue(contains_exact_fragment(page, "The result, is exact."))

    def test_case_punctuation_and_digits_are_exact(self):
        page = "Four-flap operation reported 1.2% of cases."
        self.assertTrue(contains_exact_fragment(page, "Four-flap operation reported 1.2% of cases."))
        self.assertFalse(contains_exact_fragment(page, "four-flap operation reported 1.2% of cases."))
        self.assertFalse(contains_exact_fragment(page, "Fourflap operation reported 1.2% of cases."))
        self.assertFalse(contains_exact_fragment(page, "Four-flap operation reported 12% of cases."))
        self.assertFalse(contains_exact_fragment("The patient’s risk is high.", "The patient's risk is high."))
        self.assertFalse(contains_exact_fragment("101\nIn the absence of disease.", "In the absence of disease."))
        self.assertTrue(contains_exact_fragment("101\nIn the absence of disease.", "In the absence of disease.", {"101"}))
        self.assertTrue(contains_exact_fragment("101\nThe tooth was involved.", "The tooth was involved.", {"101"}))
        self.assertFalse(contains_exact_fragment(
            "2 patients had 5 mm pockets and more signs.", "patients had 5 mm pockets and"))
        self.assertFalse(contains_exact_fragment("Results\n12\npatients had 5 mm pockets in the study.", "patients had 5 mm pockets in the study."))
        self.assertFalse(contains_exact_fragment("Results\n2\npatients were enrolled for the study.", "patients were enrolled for the study."))
        self.assertTrue(contains_exact_fragment("We saw the patient’s response.", "the patient’s response."))

    def test_line_end_hyphens_and_joiners_are_not_reconstructed(self):
        page = "A termi-\nnology fact is recorded."
        self.assertTrue(contains_exact_fragment(page, "A termi- nology fact is recorded."))
        self.assertFalse(contains_exact_fragment(page, "A terminology fact is recorded."))
        self.assertFalse(contains_exact_fragment("این\u200cکار درست است", "اینکار درست است"))
        self.assertFalse(contains_exact_fragment("word\u00ad wrap", "word wrap"))

    def test_joiners_and_combining_marks_cannot_be_cut_off(self):
        persian = "بیمار می\u200cشود و بهتر است."
        self.assertFalse(contains_exact_fragment(persian, "بیمار می"))
        self.assertFalse(contains_exact_fragment(persian, "شود و بهتر است."))
        self.assertFalse(contains_exact_fragment("cafe\u0301 is mentioned", "cafe"))
        self.assertFalse(contains_exact_fragment("کتابِ مفید است", "کتاب"))

    def test_numeric_values_cannot_be_cut_at_separators_or_signs(self):
        for page, quote in [
            ("1.2% of cases were observed", "2% of cases were observed"),
            ("1,200 patients were enrolled", "200 patients were enrolled"),
            ("1  200 patients were enrolled", "200 patients were enrolled"),
            ("1  200 patients were enrolled", "1"),
            ("1\u202f200 patients were enrolled", "200 patients were enrolled"),
            ("1\u2009200 patients were enrolled", "200 patients were enrolled"),
            ("1\u00a0200 patients were enrolled", "200 patients were enrolled"),
            ("1'200 patients were enrolled", "200 patients were enrolled"),
            ("1’200 patients were enrolled", "200 patients were enrolled"),
            ("1·200 patients were enrolled", "200 patients were enrolled"),
            ("1،200 patients were enrolled", "200 patients were enrolled"),
            ("1⁄2 sample was tested", "2 sample was tested"),
            ("1∕2 sample was tested", "2 sample was tested"),
            ("2+3 mm", "3 mm"),
            ("5±2 mm", "2 mm"),
            ("5∓2 mm", "2 mm"),
            ("2 – 5 mm was measured", "5 mm was measured"),
            ("2 — 5 mm was measured", "2"),
            ("2 to 5 mm thick in the sample", "5 mm thick in the sample"),
            ("less than 5 mm from the apex", "less than 5"),
            ("up to 5 mm from the apex", "up to 5"),
            ("≥ 5 mm from the apex", "5 mm from the apex"),
            ("about 12 × 10³ was recorded", "about 12"),
            ("about 12 x 10^3 was recorded", "about 12"),
            ("⩾ 5 mm from the apex", "5 mm from the apex"),
            ("⩽ 5 mm from the apex", "5 mm from the apex"),
            ("∼ 5 mm from the apex", "5 mm from the apex"),
            ("～ 5 mm from the apex", "5 mm from the apex"),
            ("≃ 5 mm from the apex", "5 mm from the apex"),
            ("≅ 5 mm from the apex", "5 mm from the apex"),
            ("2 ⋅ 10³ was recorded", "2"),
            ("2 ∙ 10³ was recorded", "2"),
            ("-5 mm was measured", "5 mm was measured"),
            ("10⁻³ M", "10"),
            ("12 % of patients", "12"),
            ("12٪ بیماران", "12"),
            ("12％ patients", "12"),
            ("12﹪ patients", "12"),
            ("12‱ patients", "12"),
            (".5 mm was measured", "5 mm was measured"),
            ("٫۵ میلی‌متر بود", "۵ میلی‌متر بود"),
        ]:
            with self.subTest(page=page, quote=quote):
                self.assertFalse(contains_exact_fragment(page, quote))
        self.assertTrue(contains_exact_fragment("The result - was clear", "The result"))

    def test_quotes_cannot_stop_at_a_line_end_hyphen_or_unreadable_neighbor(self):
        page = "A termi-\nnology finding appears here."
        self.assertFalse(contains_exact_fragment(page, "A termi"))
        self.assertFalse(contains_exact_fragment(page, "A termi-"))
        self.assertFalse(contains_exact_fragment(page, "nology finding appears here."))
        self.assertFalse(contains_exact_fragment("A termi\u00ad\nnology finding", "nology finding"))
        self.assertFalse(contains_exact_fragment("A termi－\nnology finding", "nology finding"))
        self.assertFalse(contains_exact_fragment("A \ue001nding appears in the text", "nding appears in the text"))
        self.assertNotEqual(evidence_key("A\x1cB"), evidence_key("A B"))
        self.assertFalse(contains_exact_fragment("A\x1cB", "A B"))
        self.assertTrue(has_unreadable_glyph("A\x01B"))
        self.assertTrue(has_unreadable_glyph("A\x85B"))
        self.assertTrue(has_unreadable_glyph("word\u00adwrap"))

    def test_unreadable_glyphs_are_not_evidence(self):
        self.assertTrue(has_unreadable_glyph("quoted \ue000 glyph"))
        self.assertTrue(has_unreadable_glyph("quoted \ufffd glyph"))
        self.assertTrue(has_unreadable_glyph("quoted " + chr(0xF0000) + " glyph"))
        self.assertTrue(has_unreadable_glyph("quoted " + chr(0x100000) + " glyph"))
        self.assertTrue(has_unreadable_glyph("quoted \ufffc glyph"))
        self.assertFalse(has_unreadable_glyph("readable quote"))
        self.assertFalse(contains_exact_fragment("A \ue000 glyph appears here.", "A \ue000 glyph appears here."))

    def test_numeric_separator_helpers_reject_truncated_context(self):
        left_cases = [
            ("1.2mm", 2), ("1  200", 3), ("2 – 5 mm", 4),
            ("≥ 5 mm", 2), ("⩾ 5 mm", 2), ("⩽ 5 mm", 2),
            ("∼ 5 mm", 2), ("～ 5 mm", 2), ("≃ 5 mm", 2),
            ("≅ 5 mm", 2), ("2 ⋅ 10³", 4), ("2 ∙ 10³", 4),
            (".5mm", 1), ("٫۵mm", 1),
            ("1⁄2", 2), ("2x10", 2),
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
                self.assertTrue(_left_separator_is_internal(page, boundary))
        for page, boundary in right_cases:
            with self.subTest(side="right", page=page):
                self.assertTrue(_right_separator_is_internal(page, boundary))

    def test_evidence_word_count_ignores_punctuation_and_keeps_joiners(self):
        self.assertEqual(evidence_word_count("alpha, beta; gamma delta"), 4)
        self.assertEqual(evidence_word_count("می\u200cشود سه واژه"), 3)
        self.assertEqual(evidence_word_count("میلی\u200cمتر"), 1)
        self.assertFalse(has_numeric_edge_word("میلی\u200cمتر"))
        self.assertEqual(evidence_word_count("alpha ... beta"), 2)
        self.assertTrue(has_numeric_edge_word("5 mm"))
        self.assertTrue(has_numeric_edge_word("5mm"))
        self.assertTrue(has_numeric_edge_word("from 5mm"))
        self.assertTrue(has_numeric_edge_word("less than 5"))
        self.assertTrue(has_numeric_edge_word("a probing depth of 5 mm"))
        self.assertFalse(has_numeric_edge_word("mm to 5 mm deep pocket"))
        self.assertFalse(has_numeric_edge_word("bone loss of 5 per cent"))
        self.assertFalse(has_numeric_edge_word("recession of 2 mm per year"))
        self.assertTrue(has_numeric_edge_word("pocket depths between 5 mm and 7 mm"))
        self.assertFalse(has_numeric_edge_word("smokers of 10 cigarettes per day"))
        self.assertFalse(has_numeric_edge_word("The sample included 2 widgets per day"))
        self.assertTrue(contains_exact_fragment(
            "The sample included 2 widgets per day.", "The sample included 2 widgets per day."))
        self.assertTrue(has_numeric_edge_word("2 sites per tooth"))
        self.assertFalse(has_numeric_edge_word("The patient had a tooth removed"))
        self.assertFalse(has_numeric_edge_word("دوز دارو ۵ ميلي\u200cگرم در كيلوگرم"))
        self.assertFalse(has_numeric_edge_word("In the absence of a measurable change"))
        self.assertFalse(has_numeric_edge_word("the patient’s"))
        self.assertFalse(contains_exact_fragment(
            "a probing depth of 5 mm to 7 mm", "a probing depth of 5 mm"))
        self.assertFalse(contains_exact_fragment(
            "from 2 mm to 5 mm deep pocket", "mm to 5 mm deep pocket"))
        self.assertFalse(contains_exact_fragment("2 mm measurement starts here", "mm measurement starts here"))
        self.assertFalse(contains_exact_fragment(
            "bone loss of 5 per cent to 10 per cent", "bone loss of 5 per cent"))
        self.assertFalse(contains_exact_fragment(
            "recession of 2 mm per year to 4 mm per year", "recession of 2 mm per year"))
        self.assertFalse(contains_exact_fragment(
            "pocket depths between 5 mm and 7 mm", "pocket depths between 5 mm and"))
        self.assertFalse(contains_exact_fragment(
            "smokers of 10 cigarettes per day or 20 cigarettes per day", "smokers of 10 cigarettes per day"))
        self.assertFalse(contains_exact_fragment(
            "The probing depth is 5 mm or more for disease risk.", "The probing depth is 5 mm or"))
        self.assertFalse(contains_exact_fragment(
            "the pocket depths of 5 mm at baseline vs. 7 mm in controls",
            "the pocket depths of 5 mm at baseline"))
        self.assertFalse(contains_exact_fragment(
            "pocket depths of 5 mm at baseline vs. 7 mm in controls",
            "pocket depths of 5 mm at baseline vs."))
        self.assertFalse(contains_exact_fragment(
            "The probe was ۵ میلی‌متر یا بیشتر.", "The probe was ۵ میلی‌متر یا"))
        self.assertTrue(contains_exact_fragment(
            "The depth was 5 mm in the first group. In 7 cases it increased.",
            "The depth was 5 mm in the first group."))
        self.assertFalse(contains_exact_fragment(
            "دوز دارو ۵ ميلي\u200cگرم در كيلوگرم تا ۱۰", "دوز دارو ۵ ميلي\u200cگرم در كيلوگرم"))
        self.assertFalse(contains_exact_fragment("patients aged 5 years or older were included", "years or older were included"))
        self.assertFalse(contains_exact_fragment("5 per cent of patients were affected", "per cent of patients were affected"))
        self.assertFalse(contains_exact_fragment("۵ درصد بیماران مبتلا بودند", "درصد بیماران مبتلا بودند"))
        self.assertFalse(contains_exact_fragment("12 teeth were extracted in the clinic", "teeth were extracted in the clinic"))
        self.assertFalse(contains_exact_fragment("10 mg twice daily or 20 mg twice daily", "10 mg twice daily"))
        self.assertFalse(contains_exact_fragment(
            "2 mm or 3 mm", "2 mm"))
        self.assertFalse(contains_exact_fragment(
            "bone loss of 5 per cent to 10 per cent", "bone loss of 5 per cent"))
        self.assertFalse(contains_exact_fragment(
            "recession of 2 mm per year to 4 mm per year", "recession of 2 mm per year"))
        self.assertFalse(contains_exact_fragment(
            "pocket depths between 5 mm and 7 mm", "pocket depths between 5 mm and"))
        self.assertTrue(has_numeric_edge_word("The 5mm length was measured"))
        self.assertFalse(has_numeric_edge_word("We measured the 5mm length very carefully today"))
        self.assertTrue(contains_exact_fragment(
            "We measured the 5mm length very carefully today.",
            "We measured the 5mm length very carefully today."))
        self.assertFalse(contains_exact_fragment(" 5", "5"))

    def test_classifier_rejects_case_punctuation_and_unreadable_glyphs(self):
        page = "The evidence is an exact synthetic statement."
        self.assertIsNone(evidence_page_issue(page, ["The evidence is an exact synthetic statement."]))
        self.assertEqual(evidence_page_issue(page, ["the evidence is an exact synthetic statement."]), "mismatch")
        self.assertEqual(evidence_page_issue(page, ["The evidence is an exact synthetic, statement."]), "mismatch")
        self.assertEqual(evidence_page_issue(page, ["The evidence is an exact synthetic statement.\ue000"]), "unreadable")

    def test_classifier_trusts_only_neighbour_correlated_page_labels(self):
        page = "101\nThe patient had a documented clinical finding."
        class SyntheticPdf:
            page_count = 20
            pages = {
                8: "99\nUnrelated background text.",
                9: "100\nUnrelated background text.",
                10: page,
                11: "102\nUnrelated background text.",
                12: "103\nUnrelated background text.",
            }

            def page_text(self, number):
                return self.pages.get(number, "No printed number shown here.")

        books = Books(Path("/no-storage"), Path("/no-sql"), "isolated_test")
        books.pdfs["fictional-book@1e"] = SyntheticPdf()
        verified = books.verified_printed_labels("fictional-book@1e", 10, page)
        self.assertEqual(verified, {"101"})
        self.assertEqual(
            evidence_page_issue(page, ["The patient had a documented clinical finding."]),
            "mismatch")
        self.assertIsNone(evidence_page_issue(
            page, ["The patient had a documented clinical finding."], verified))

        count_page = "Results\n12\npatients had 5 mm pockets in the study."
        self.assertEqual(
            books.verified_printed_labels("fictional-book@1e", 10, count_page), set())
        self.assertEqual(
            evidence_page_issue(
                count_page, ["patients had 5 mm pockets in the study."],
                books.verified_printed_labels("fictional-book@1e", 10, count_page)),
            "mismatch")

    def test_printed_page_labels_use_canonical_ascii_decimal(self):
        self.assertEqual(printed_page("245\nbody text"), "245")
        for label in ("0", "017", "٠١٧", "０１７"):
            with self.subTest(label=label):
                self.assertIsNone(printed_page(label + "\nbody text"))

    def test_search_candidate_quote_keeps_original_case_and_punctuation(self):
        page = "RESULTS: The Mixed-case, sample is directly associated with this synthetic finding."
        quote = quote_page(page, ["mixed case", "synthetic finding"])
        self.assertIsNotNone(quote)
        self.assertIn("Mixed-case,", quote)
        self.assertTrue(contains_exact_fragment(page, quote))
        self.assertFalse(contains_exact_fragment(page, quote.lower()))
        self.assertFalse(has_unreadable_glyph(quote))

    def test_search_does_not_export_a_general_page_snippet(self):
        import find_in_books
        self.assertFalse(hasattr(find_in_books, "snippet"))


if __name__ == "__main__":
    unittest.main()
