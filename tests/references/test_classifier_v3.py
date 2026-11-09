import sys, tempfile, unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/classification_v3"))
import pipeline as P  # noqa: E402
from scanner import Query, count_page, rank  # noqa: E402

SHA = "a" * 64
RUNS = [[None, 1, 2], ["ch1", 3, 10], ["ch2", 11, 20]]
PAGE = "Pemphigus vulgaris shows intraepithelial acantholysis with a positive Nikolsky sign in most patients."


def dec(**kw):
    base = dict(question_id=1, edition="b@13e", pdf_sha256=SHA, pdf_page=5, node_id="ch1",
                quote="intraepithelial acantholysis with a positive Nikolsky sign", confidence=0.9,
                scope="in_scope", basis="official_exam_year")
    return P.Decision(**(base | kw))


class V3(unittest.TestCase):
    def test_accepts_exact_same_edition(self):
        self.assertEqual(P.verify_decision(dec(), PAGE, RUNS, SHA, 20), [])

    def test_rejects_wrong_sha_chapter_quote_conf(self):
        f = P.verify_decision(dec(pdf_sha256="b" * 64, node_id="ch2", quote="not on page", confidence=0.8),
                              PAGE, RUNS, SHA, 20)
        for r in ("sha_not_current_approved_edition", "chapter_not_from_same_edition_map",
                  "quote_not_verbatim_on_page", "low_confidence"):
            self.assertIn(r, f)

    def test_missing_map_and_page_range(self):
        f = P.verify_decision(dec(pdf_page=99), PAGE, [], SHA, 20)
        self.assertIn("edition_chapter_map_missing", f); self.assertIn("pdf_page_out_of_range", f)

    def test_scope_and_proxy_need_note(self):
        f = P.verify_decision(dec(scope="scope_unverified", basis="same_lineage_proxy"), PAGE, RUNS, SHA, 20)
        self.assertIn("scope_note_required", f); self.assertIn("proxy_note_required", f)
        self.assertEqual(P.verify_decision(dec(scope="outside_scope_exam_question", note_fa="یادداشت"),
                                           PAGE, RUNS, SHA, 20), [])

    def test_long_quote_rejected(self):
        self.assertIn("quote_length", P.verify_decision(dec(quote="w " * 41), PAGE, RUNS, SHA, 20))

    def test_need_english_exempt_voided_not(self):
        self.assertEqual(P.classify_need({"exam_type": "residency", "subject": "english", "key_status": "final"}), "exempt")
        self.assertEqual(P.classify_need({"exam_type": "residency", "subject": "english", "key_status": "voided"}), "needs_source")
        self.assertEqual(P.classify_need({"exam_type": "board", "subject": "x", "key_status": "voided"}), "needs_source")
        self.assertEqual(P.classify_need({"source": {"node_id": "c", "pdf_page": 3, "pdf_sha256": SHA}}), "complete")
        self.assertEqual(P.classify_need({"source": {"node_id": "c", "pdf_page": 0, "pdf_sha256": SHA}}), "needs_source")

    def test_grouping_pending_and_duplicates(self):
        qs = [dict(id=i, exam_type="board", year=1400, subject="om", stem="s%d" % (i % 2), choices={}, key="1")
              for i in range(3)] + [dict(id=9, exam_type="national", year=1399, subject="xx", stem="z", choices={}, key="1")]
        g, pend = P.group_by_edition(qs, {"board/1400/om": {"editions": ["b@13e"]}})
        self.assertEqual([q["id"] for q in g["b@13e"]], [0, 1])
        self.assertEqual({p["reason"] for p in pend}, {"duplicate_in_batch", "no_official_or_rule_based_edition"})

    def test_ranking_prefers_answer_page(self):
        q = Query.build("q", [("nikolsky sign", 1.6), ("acantholysis", 1.0)])
        phrases = {tuple(t): i for i, (t, _) in enumerate(q.groups)}
        filler = " ".join(["periodontal ligament fiber"] * 20)
        pages = {1: PAGE + " " + filler, 2: filler + " acantholysis", 3: filler * 2}
        pc, pl, noise = {}, {}, set()
        for p, t in pages.items():
            pc[p], pl[p], n = count_page(t, phrases)
        self.assertEqual(rank([q], pc, pl, noise, phrases)["q"][0][1], 1)

    def test_checkpoint_leases(self):
        with tempfile.TemporaryDirectory() as d:
            c = P.Checkpoint(Path(d) / "cp.json", lease_seconds=10)
            self.assertTrue(c.claim("g1", "a", now=0)); self.assertFalse(c.claim("g1", "b", now=5))
            self.assertTrue(c.claim("g1", "b", now=11)); c.complete("g1", {"n": 1})
            self.assertFalse(P.Checkpoint(Path(d) / "cp.json").claim("g1", "a"))

    def test_package_source_only_and_bounded(self):
        pkg = P.handoff_package([dec()], SHA, "b1")
        self.assertIn("answer_key", pkg["never_mutates"]); self.assertEqual(len(pkg["package_sha256"]), 64)
        with self.assertRaises(ValueError):
            P.handoff_package([dec()] * 41, SHA, "b1")


if __name__ == "__main__":
    unittest.main()
