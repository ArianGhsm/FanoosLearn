import hashlib, sys, tempfile, unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/classification_v3"))
import pipeline as P  # noqa: E402
from scanner import Query, count_page, rank  # noqa: E402

SHA = "a" * 64
RUNS = [[None, 1, 2], ["ch1", 3, 10], ["ch2", 11, 20]]
PAGE = "Pemphigus vulgaris shows intraepithelial acantholysis with a positive Nikolsky sign in most patients."


def dec(**kw):
    base = dict(question_id="0b7f3c2e-uuid", producer="v3-precheck", edition="b@13e", pdf_sha256=SHA, pdf_page=5, node_id="ch1",
                quote="intraepithelial acantholysis with a positive Nikolsky sign", confidence=0.9,
                scope="in_scope", basis="official_exam_year")
    return P.Decision(**(base | kw))


class V3(unittest.TestCase):
    def test_accepts_exact_same_edition(self):
        self.assertEqual(P.verify_decision(dec(), PAGE, RUNS, SHA, 20), [])

    def test_rejects_wrong_sha_chapter_quote_conf(self):
        f = P.verify_decision(dec(pdf_sha256="b" * 64, node_id="ch2", quote="this sentence is not on page", confidence=0.8),
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

    def test_identical_content_across_exam_types_kept(self):  # regression A
        pol = {k: {"editions": ["b@13e"], "provenance": "official-list:x"} for k in
               ("board/1400/om", "promotion/1400/om", "residency/1400/om")}
        qs = [dict(id="u-%s" % t, workspace_id="w", exam_type=t, year=1400, subject="om", stem="same",
                   choices={"1": "a"}, key="1") for t in ("board", "promotion", "residency")]
        qs.append(dict(qs[0]))  # exact repeated row only
        g, pend = P.group_by_edition(qs, pol)
        self.assertEqual([q["id"] for q in g["b@13e"]], ["u-board", "u-promotion", "u-residency"])
        self.assertEqual(pend, [{"id": "u-board", "reason": "repeated_row_in_export"}])

    def test_policy_needs_provenance_no_fabrication(self):  # regression E
        q = dict(id="u1", exam_type="national", year=1399, subject="xx", stem="z", choices={}, key="1")
        self.assertEqual(P.resolve_edition(q, {})["reason"], "no_official_or_rule_based_edition")
        self.assertEqual(P.resolve_edition(q, {"national/1399/xx": {"editions": ["b@13e"]}})["reason"],
                         "edition_policy_without_provenance")

    def test_ranking_prefers_answer_page(self):
        q = Query.build("q", [("nikolsky sign", 1.6), ("acantholysis", 1.0)])
        phrases = {tuple(t): i for i, (t, _) in enumerate(q.groups)}
        filler = " ".join(["periodontal ligament fiber"] * 20)
        pages = {1: PAGE + " " + filler, 2: filler + " acantholysis", 3: filler * 2}
        pc, pl, noise = {}, {}, set()
        for p, t in pages.items():
            pc[p], pl[p], n = count_page(t, phrases)
        self.assertEqual(rank([q], pc, pl, noise, phrases)["q"][0][1], 1)

    def test_short_quote_rejected(self):  # regression D
        self.assertIn("quote_length", P.verify_decision(dec(quote="Nikolsky sign"), PAGE, RUNS, SHA, 20))
        self.assertIn("quote_length", P.verify_decision(dec(quote="positive Nikolsky sign"), PAGE, RUNS, SHA, 20))

    def test_precheck_never_approves(self):  # regression B
        self.assertEqual(P.precheck([]), "audit_pending")

    def _audit(self, d, root, **kw):
        rec = Path(root) / "r.json"; rec.write_text("{}")
        a = dict(tool="independent_page_reopen", auditor="opus-auditor", question_id=d.question_id,
                 pdf_sha256=d.pdf_sha256, pdf_page=d.pdf_page, node_id=d.node_id, verdict="accepted",
                 quote_sha256=hashlib.sha256(d.quote.encode()).hexdigest(), receipt="r.json",
                 receipt_sha256=hashlib.sha256(b"{}").hexdigest())
        return a | kw

    def test_package_rejects_unaudited_and_self_certified(self):  # regression B
        d = dec()
        with tempfile.TemporaryDirectory() as root:
            with self.assertRaises(ValueError):
                P.handoff_package([(d, {})], SHA, "b1", Path(root))
            with self.assertRaises(ValueError):
                P.handoff_package([(d, self._audit(d, root, auditor="v3-precheck"))], SHA, "b1", Path(root))
            with self.assertRaises(ValueError):
                P.handoff_package([(d, self._audit(d, root, receipt_sha256="0" * 64))], SHA, "b1", Path(root))
            with self.assertRaises(ValueError):
                P.handoff_package([(d, self._audit(d, root, pdf_page=6))], SHA, "b1", Path(root))
            pkg = P.handoff_package([(d, self._audit(d, root))], SHA, "b1", Path(root))
            self.assertIn("answer_key", pkg["never_mutates"]); self.assertEqual(len(pkg["package_sha256"]), 64)
            with self.assertRaises(ValueError):
                P.handoff_package([(d, self._audit(d, root))] * 41, SHA, "b1", Path(root))

    def test_checkpoint_leases(self):
        with tempfile.TemporaryDirectory() as d:
            c = P.Checkpoint(Path(d) / "cp.json", lease_seconds=10)
            self.assertTrue(c.claim("g1", "a", now=0)); self.assertFalse(c.claim("g1", "b", now=5))
            self.assertTrue(c.claim("g1", "b", now=11))
            self.assertFalse(c.complete("g1", "a", {"n": 1}))  # expired lease cannot complete
            self.assertTrue(c.complete("g1", "b", {"n": 1}))
            self.assertFalse(P.Checkpoint(Path(d) / "cp.json").claim("g1", "a"))

    def test_checkpoint_concurrent_single_winner(self):  # regression C
        import multiprocessing as mp
        with tempfile.TemporaryDirectory() as d:
            path = str(Path(d) / "cp.json")
            with mp.get_context("fork").Pool(8) as pool:
                wins = pool.starmap(_claim, [(path, "w%d" % i) for i in range(16)])
            self.assertEqual(sum(wins), 1)


def _claim(path, owner):
    return P.Checkpoint(Path(path)).claim("g", owner)


if __name__ == "__main__":
    unittest.main()
