"""Synthetic private classification audit tests; no real exam content."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

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
        self.chapter_map = self.root / "reference-chapter-pages.json"
        self.question = {"number": 222, "stem": "Synthetic sample question?",
                         "choices": ["alpha", "beta"], "answer": {"choice": 2}}
        self.body = {"format": "fanoos.classification.study-only/1",
                     "year": 1402, "exam_type": "residency", "questions": [self.question]}
        self.source = {"ref": "fictional-book@1e#ch04", "page": "pdf 17",
                       "pdf_page": 17, "pdf_sha256": "a" * 64,
                       "anchor": "This is a clearly synthetic page quote.",
                       "reference_year": 1402,
                       "classification_basis": "official_exam_year",
                       "classification_note": None,
                       "origin": "ai",
                       "confidence": {"source": .97, "node": .97, "page": .97}}
        self.decision = {"number": 222, "edition": "fictional-book@1e",
                         "chapter": "4", "page": 17, "confidence": .97,
                         "evidence": "This is a clearly synthetic page quote."}
        self.fake_pdf = self.FakePdf()
        self.chapter_map.write_text(json.dumps({"editions": {"fictional-book@1e": {
            "runs": [[None, 1, 1], ["4", 2, 120]],
            "source_pdf_sha256": self.fake_pdf.source_sha256,
        }}}))
        self.chapter_patch = patch.object(audit, "CHAPTERS", self.chapter_map)
        self.chapter_patch.start()
        self.addCleanup(self.chapter_patch.stop)
        self.pdf_patch = patch.object(audit, "open_verified_reference", return_value=self.fake_pdf)
        self.pdf_patch.start()
        self.addCleanup(self.pdf_patch.stop)
        self.save()

    class FakePdf:
        source_sha256 = "a" * 64
        page_count = 120

        def __init__(self):
            self.pages = {}

        def page_text(self, number):
            if number in self.pages:
                return self.pages[number]
            labels = {116: "100", 117: "101", 118: "102", 119: "103"}
            return f"{labels.get(number, '')}\nThis is a clearly synthetic page quote.\nSynthetic source page with more words."

    def save(self):
        self.src.write_text(json.dumps(self.body))
        self.out.write_text(json.dumps({**self.body, "questions": [
            {**self.question, "sources": [self.source]}]}))
        self.dec.write_text(json.dumps([self.decision]))

    def test_accepted_page_map_and_unchanged_question(self):
        result = audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.assertEqual((result["questions"], result["accepted"], result["pending"]), (1, 1, 0))
        self.assertTrue(result["question_content_identical"])
        self.assertEqual(result["mapped"]["222"]["confidence_source"], .97)
        self.assertEqual(result["mapped"]["222"]["confidence_node"], .97)
        self.assertEqual(result["mapped"]["222"]["confidence_page"], .97)
        self.assertEqual(result["mapped"]["222"]["pdf_page"], 17)
        self.assertEqual(result["mapped"]["222"]["pdf_sha256"], "a" * 64)
        self.source["page"] = "17"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_evidence_case_and_punctuation_mismatches_rejected(self):
        for evidence in (
            "this is a clearly synthetic page quote.",
            "This is a clearly synthetic page quotation.",
            "This is a clearly synthetic page, quote.",
        ):
            self.decision["evidence"] = evidence
            self.source["anchor"] = evidence
            self.save()
            with self.assertRaisesRegex(ValueError, "evidence quote is not printed on PDF page"):
                audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.decision["evidence"] = "This is a clearly synthetic page quote."
        self.source["anchor"] = self.decision["evidence"]
        self.save()

    def test_audit_numeric_boundary_helpers_reject_truncated_context(self):
        left_cases = [
            ("1.2mm", 2), ("1  200", 3), ("2 – 5 mm", 4),
            ("⩾ 5 mm", 2), ("⩽ 5 mm", 2), ("∼ 5 mm", 2),
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
                self.assertTrue(audit._audit_attached(page, boundary, True))
        for page, boundary in right_cases:
            with self.subTest(side="right", page=page):
                self.assertTrue(audit._audit_attached(page, boundary, False))

    def test_audit_matcher_rejects_unicode_and_numeric_truncation(self):
        rejected = [
            ("بیمار می\u200cشود و خوب است", "بیمار می"),
            ("بیمار می\u200cشود و خوب است", "شود و خوب است"),
            ("cafe\u0301 is noted", "cafe"),
            ("کتابِ مفید است", "کتاب"),
            ("1.2% of patients", "2% of patients"),
            ("1,200 patients", "200 patients"),
            ("1  200 patients", "200 patients"),
            ("1  200 patients", "1"),
            ("1\u202f200 patients", "200 patients"),
            ("1\u2009200 patients", "200 patients"),
            ("1\u00a0200 patients", "200 patients"),
            ("1'200 patients", "200 patients"),
            ("1’200 patients", "200 patients"),
            ("1·200 patients", "200 patients"),
            ("1،200 patients", "200 patients"),
            ("1⁄2 sample", "2 sample"),
            ("2+3 mm", "3 mm"),
            ("5±2 mm", "2 mm"),
            ("5∓2 mm", "2 mm"),
            ("2 – 5 mm", "5 mm"),
            ("2 — 5 mm", "2"),
            ("≥ 5 mm from the apex", "5 mm from the apex"),
            ("about 12 × 10³ was recorded", "about 12"),
            ("~ 5 mm was measured", "5 mm was measured"),
            ("≈ 5 mm was measured", "5 mm was measured"),
            ("about 12 x 10^3 was recorded", "about 12"),
            ("⩾ 5 mm from the apex", "5 mm from the apex"),
            ("⩽ 5 mm from the apex", "5 mm from the apex"),
            ("∼ 5 mm from the apex", "5 mm from the apex"),
            ("～ 5 mm from the apex", "5 mm from the apex"),
            ("≃ 5 mm from the apex", "5 mm from the apex"),
            ("≅ 5 mm from the apex", "5 mm from the apex"),
            ("2 ⋅ 10³ was recorded", "2"),
            ("2 ∙ 10³ was recorded", "2"),
            ("2 to 5 mm thick in the sample", "5 mm thick in the sample"),
            ("less than 5 mm from the apex", "less than 5"),
            ("up to 5 mm from the apex", "up to 5"),
            ("a probing depth of 5 mm to 7 mm", "a probing depth of 5 mm"),
            ("from 2 mm to 5 mm deep pocket", "mm to 5 mm deep pocket"),
            ("bone loss of 5 per cent to 10 per cent", "bone loss of 5 per cent"),
            ("recession of 2 mm per year to 4 mm per year", "recession of 2 mm per year"),
            ("pocket depths between 5 mm and 7 mm", "pocket depths between 5 mm and"),
            ("a 5 mm or 7 mm deep pocket", "mm or 7 mm deep pocket"),
            ("smokers of 10 cigarettes per day or 20 cigarettes per day", "smokers of 10 cigarettes per day"),
            ("دوز دارو ۵ ميلي\u200cگرم در كيلوگرم تا ۱۰", "دوز دارو ۵ ميلي\u200cگرم در كيلوگرم"),
            ("2 mm measurement starts here", "mm measurement starts here"),
            ("patients aged 5 years or older were included", "years or older were included"),
            ("5 per cent of patients were affected", "per cent of patients were affected"),
            ("۵ درصد بیماران مبتلا بودند", "درصد بیماران مبتلا بودند"),
            ("12 teeth were extracted in the clinic", "teeth were extracted in the clinic"),
            ("patients were prescribed 10 mg twice daily or 20 mg twice daily", "patients were prescribed 10 mg twice daily"),
            ("The probing depth is 5 mm or more for disease risk.", "The probing depth is 5 mm or"),
            ("the pocket depths of 5 mm at baseline vs. 7 mm in controls", "the pocket depths of 5 mm at baseline"),
            ("pocket depths of 5 mm at baseline vs. 7 mm in controls", "pocket depths of 5 mm at baseline vs."),
            ("The probe was ۵ میلی‌متر یا بیشتر.", "The probe was ۵ میلی‌متر یا"),
            ("-5 mm measured", "5 mm measured"),
            ("10⁻³ M", "10"),
            ("12٪ of patients", "12"),
            ("12 % of patients", "12"),
            (".5 mm", "5 mm"),
            ("٫۵ میلی‌متر", "۵ میلی‌متر"),
            ("A termi-\nnology result", "A termi-"),
            ("A termi\u00ad\nnology result", "nology result"),
            ("A termi－\nnology result", "nology result"),
            ("A \ue001nding result", "nding result"),
            ("A\x1cB", "A B"),
        ]
        for page, quote in rejected:
            with self.subTest(page=repr(page), quote=repr(quote)):
                self.assertFalse(audit._audit_evidence_matches(page, quote))
        self.assertTrue(audit._audit_evidence_matches("The result - was clear", "The result"))
        self.assertTrue(audit._audit_evidence_matches(
            "The sample included 2 widgets per day.", "The sample included 2 widgets per day."))
        self.assertFalse(audit._audit_evidence_matches("101\nIn the absence of disease.", "In the absence of disease."))
        self.assertTrue(audit._audit_evidence_matches("101\nIn the absence of disease.", "In the absence of disease.", {"101"}))
        self.assertTrue(audit._audit_evidence_matches("101\nThe tooth was involved.", "The tooth was involved.", {"101"}))
        self.assertFalse(audit._audit_evidence_matches(
            "2 patients had 5 mm pockets and more signs.", "patients had 5 mm pockets and"))
        self.assertFalse(audit._audit_evidence_matches("Results\n12\npatients had 5 mm pockets in the study.", "patients had 5 mm pockets in the study."))
        self.assertTrue(audit._audit_evidence_matches("We saw the patient’s response.", "the patient’s response."))
        self.assertTrue(audit._audit_evidence_matches("The\u200f exact quote, here.", "The exact quote, here."))

    def test_validated_source_anchor_page_and_sha_must_match_decision(self):
        self.source["anchor"] = "this is a clearly synthetic page quote."
        self.save()
        with self.assertRaisesRegex(ValueError, "validated source anchor differs"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.source["anchor"] = self.decision["evidence"]
        self.source["pdf_page"] = 18
        self.save()
        with self.assertRaisesRegex(ValueError, "validated source PDF page/SHA differs"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.source["pdf_page"] = 17
        self.source["pdf_sha256"] = "b" * 64
        self.save()
        with self.assertRaisesRegex(ValueError, "validated source PDF page/SHA differs"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_independent_audit_matcher_obeys_exact_text_boundaries(self):
        cases = [
            ("The exact page quote, here.", "The exact page quote, here.", True),
            ("The sample included 2 widgets per day.", "The sample included 2 widgets per day.", True),
            ("The depth was 5 mm in the first group. In 7 cases it increased.", "The depth was 5 mm in the first group.", True),
            ("The\u200f exact page\n quote, here.", "The exact page quote, here.", True),
            ("The exact page quote, here.", "the exact page quote, here.", False),
            ("The exact page quote, here.", "The exact page quote here.", False),
            ("1.2% of patients", "2% of patients", False),
            ("1,200 patients", "200 patients", False),
            ("1  200 patients", "200 patients", False),
            ("1  200 patients", "1", False),
            ("2 – 5 mm", "5 mm", False),
            ("2 — 5 mm", "2", False),
            ("≥ 5 mm from the apex", "5 mm from the apex", False),
            ("about 12 × 10³ was recorded", "about 12", False),
            ("⩾ 5 mm from the apex", "5 mm from the apex", False),
            ("2 ⋅ 10³ was recorded", "2", False),
            ("2 to 5 mm thick in the sample", "5 mm thick in the sample", False),
            ("less than 5 mm from the apex", "less than 5", False),
            ("~ 5 mm was measured", "5 mm was measured", False),
            ("≈ 5 mm was measured", "5 mm was measured", False),
            (" 5", "5", False),
            ("-5 mm measured", "5 mm measured", False),
            ("بیمار می\u200cشود و خوب است", "بیمار می", False),
            ("cafe\u0301 is noted", "cafe", False),
            ("A termi-\nnology result", "A termi", False),
            ("A \ue001nding result", "nding result", False),
            ("A\x1cB", "A B", False),
        ]
        for page, quote, expected in cases:
            with self.subTest(page=repr(page), quote=repr(quote)):
                self.assertEqual(audit._audit_evidence_matches(page, quote), expected)

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


    def test_printed_page_from_original_pdf_requires_neighbor_support(self):
        """Book printed 101 on PDF 117; printed-label mismatch is legitimate."""
        self.fake_pdf.pages = {
            116: "100\nSynthetic page text with enough content for this test.",
            117: "This is a clearly synthetic page quote.\nSynthetic source page with more words.\n101",
            118: "102\nSynthetic page text with enough content for this test.",
            119: "103\nSynthetic page text with enough content for this test.",
        }
        self.decision["page"] = 117
        self.source["pdf_page"] = 117
        self.source["page"] = "101"
        self.save()
        result = audit.audit_batch(self.root, "1402:community-dentistry:community")
        self.assertEqual(result["accepted"], 1)
        # A chapter/table number on one page may look like a printed page.
        self.source["page"] = "10"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_printed_page_without_two_consistent_neighbors_rejected(self):
        self.fake_pdf.pages = {
            116: "100\nSynthetic page text with enough content for this test.",
            117: "This is a clearly synthetic page quote.\nSynthetic source page with more words.\n101",
            118: "Page footer not readable\nSynthetic page text with enough content for this test.",
            119: "No footer\nSynthetic page text with enough content for this test.",
        }
        self.decision["page"] = 117
        self.source["pdf_page"] = 117
        self.source["page"] = "101"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_printed_page_without_pdf_label_rejected(self):
        self.fake_pdf.pages = {
            116: "No numeric label\nSynthetic page text with enough content for this test.",
            117: "No numeric label\nThis is a clearly synthetic page quote.\nSynthetic source page with more words.",
            118: "No numeric label\nSynthetic page text with enough content for this test.",
            119: "No numeric label\nSynthetic page text with enough content for this test.",
        }
        self.decision["page"] = 117
        self.source["pdf_page"] = 117
        self.source["page"] = "101"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_printed_page_equal_to_pdf_page_still_needs_neighbor_proof(self):
        self.fake_pdf.pages = {
            17: "This is a clearly synthetic page quote.\nSynthetic source page with more words.\n17",
        }
        self.source["page"] = "17"
        self.save()
        with self.assertRaisesRegex(ValueError, "wrong page or origin"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

        self.fake_pdf.pages = {
            15: "15\nSynthetic page text with enough content for this test.",
            16: "16\nSynthetic page text with enough content for this test.",
            17: "This is a clearly synthetic page quote.\nSynthetic source page with more words.\n17",
            18: "18\nSynthetic page text with enough content for this test.",
            19: "19\nSynthetic page text with enough content for this test.",
        }
        self.source["printed_page"] = "17"
        self.save()
        self.assertEqual(audit.audit_batch(self.root, "1402:community-dentistry:community")["accepted"], 1)

    def test_printed_page_values_require_canonical_ascii_decimal(self):
        self.fake_pdf.pages = {
            15: "15\nSynthetic page text with enough content for this test.",
            16: "16\nSynthetic page text with enough content for this test.",
            17: "This is a clearly synthetic page quote.\nSynthetic source page with more words.\n17",
            18: "18\nSynthetic page text with enough content for this test.",
            19: "19\nSynthetic page text with enough content for this test.",
        }
        self.assertTrue(audit._verify_pdf_printed_page(self.fake_pdf, 17, "17"))
        for value in ("017", " 17", "1_7", "١٧", "１７"):
            with self.subTest(value=value):
                self.assertFalse(audit._verify_pdf_printed_page(self.fake_pdf, 17, value))
        self.assertEqual(audit._printed_labels("0\n017\n١٧\n１７\n17"), {17})

    def test_validated_printed_page_must_match_page_label(self):
        self.source["page"] = "pdf 17"
        self.source["printed_page"] = "18"
        self.save()
        with self.assertRaisesRegex(ValueError, "printed_page differs"):
            audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_printed_page_field_is_rejected_when_redundant_or_not_text(self):
        self.source["page"] = "pdf 17"
        for value in ("17", "pdf 17", 17):
            self.source["printed_page"] = value
            self.save()
            with self.subTest(printed_page=value):
                with self.assertRaisesRegex(ValueError, "printed_page differs"):
                    audit.audit_batch(self.root, "1402:community-dentistry:community")

    def test_save_private_location_and_idempotence(self):
        out = self.root / "classification/reports/audit.json"
        audit.save_private(self.root, out, {"safe": True})
        audit.save_private(self.root, out, {"safe": True})
        with self.assertRaisesRegex(ValueError, "differs"):
            audit.save_private(self.root, out, {"safe": False})
        with self.assertRaisesRegex(ValueError, "protected"):
            audit.save_private(self.root, self.root / "public-report.json", {"safe": True})

    def test_national_round_two_uses_type_prefixed_batch_and_study_scope(self):
        (self.root / "bank-sittings/1404").mkdir(parents=True, exist_ok=True)
        national_decisions = self.root / "classification/decisions/national-1404-2"
        national_decisions.mkdir(parents=True, exist_ok=True)
        self.src = self.root / "bank-sittings/1404/national-round-two-study.json"
        self.out = self.root / "classification/sittings/1404-national-round-two-validated.json"
        self.dec = national_decisions / "1404-community-dentistry.json"
        self.body.update({"year": 1404, "exam_type": "national", "round": 2})
        self.source.update({"reference_year": 1404, "classification_basis": "official_exam_year"})
        self.decision.pop("reference_year", None)
        self.decision.pop("classification_basis", None)
        self.save()

        result = audit.audit_batch(
            self.root, "national:1404:2:community-dentistry:national-round-two",
        )
        self.assertEqual(result["exam_type"], "national")
        self.assertEqual(result["round"], 2)
        self.assertEqual(result["classification_basis"], "official_exam_year")
        self.body["round"] = 1
        self.src.write_text(json.dumps(self.body))
        with self.assertRaisesRegex(ValueError, "year or exam type mismatch"):
            audit.audit_batch(
                self.root, "national:1404:2:community-dentistry:national-round-two",
            )


class NearestProxyAuditTests(unittest.TestCase):
    def test_same_lineage_proxy_records_actual_year_and_checks_scope(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for name in ("bank-sittings/1396", "classification/decisions",
                         "classification/sittings", "classification/reports"):
                (root / name).mkdir(parents=True, exist_ok=True)
            digest = "a" * 64
            edition = "fictional-book@1e"
            evidence = "Synthetic direct page evidence for a proxy source"
            proxy_note = "این آزمون ۱۳۹۶ از نسخهٔ پروکسیِ سال ۱۳۹۷ استفاده می‌کند؛ رسمی بودن آن برای سال آزمون تأیید نشده است."
            question = {"number": 222, "stem": "Synthetic question?",
                        "choices": ["alpha", "beta"], "answer": {"choice": 2, "status": "final"}}
            (root / "bank-sittings/1396/community-study.json").write_text(json.dumps({
                "format": "fanoos.classification.study-only/1", "year": 1396,
                "exam_type": "residency", "round": 1, "questions": [question],
            }))
            decision = {"number": 222, "edition": edition, "reference_year": 1397,
                        "classification_basis": "same_lineage_proxy", "proxy_note": proxy_note,
                        "chapter": "1", "page": 17, "confidence": .97, "evidence": evidence}
            (root / "classification/decisions/1396-community-dentistry.json").write_text(json.dumps([decision]))
            source = {"ref": edition + "#ch01", "page": "pdf 17", "pdf_page": 17,
                      "pdf_sha256": digest, "anchor": evidence, "origin": "ai", "primary": True,
                      "confidence": {"source": .97, "node": .97, "page": .97},
                      "reference_year": 1397, "classification_basis": "same_lineage_proxy",
                      "classification_note": proxy_note, "official_scope_status": "in_scope",
                      "scope_unverified": False}
            (root / "classification/sittings/1396-community-validated.json").write_text(json.dumps({
                "format": "fanoos.classification.study-only/1", "year": 1396,
                "exam_type": "residency", "round": 1,
                "questions": [{**question, "sources": [source]}],
            }))
            chapter_map = root / "reference-chapter-pages.json"
            chapter_map.write_text(json.dumps({"editions": {edition: {
                "runs": [["1", 1, 20]], "pdf_pages": 20, "source_pdf_sha256": digest,
            }}}))
            (root / "catalog.json").write_text(json.dumps({
                "validity": [{"exam_type": "residency", "year": 1397,
                              "subject": "community-dentistry", "edition": edition,
                              "official": True, "scope_chapters": [{"number": "1"}]}],
                "references": [{"key": "fictional-book", "editions": [{"key": "1e", "nodes": [
                    {"key": "ch01", "kind": "chapter", "number": "1", "title": "Synthetic chapter"},
                ]}]}],
            }))
            (root / "reference-pdfs.json").write_text(json.dumps({"editions": {
                edition: {"pdf": "verified-server-pdf"},
            }}))

            class FakePdf:
                def __init__(self):
                    self.source_sha256 = digest
                    self.page_count = 20

                def page_text(self, number):
                    return evidence

            with patch.object(audit, "CHAPTERS", chapter_map), \
                    patch.object(audit, "open_verified_reference", return_value=FakePdf()):
                result = audit.audit_batch(root, "1396:community-dentistry:community")
            self.assertEqual(result["classification_basis"], "same_lineage_proxy")
            self.assertEqual(result["reference_years"], [1397])
            self.assertEqual(result["mapped"]["222"]["reference_year"], 1397)


if __name__ == "__main__":
    unittest.main()
