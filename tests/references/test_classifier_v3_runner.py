import json
import os
import sys
import tempfile
import time
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts"))

from classification_v3 import orchestrator, runner  # noqa: E402


def U(n):
    return f"00000000-0000-0000-0000-{int(n):012d}"


def item(qid, year, lane="residency_dental_ready", subject="oral-pathology", eds=None,
         kind="residency", key="final", proxy=None, ordered=True):
    return {"question_id": U(qid) if str(qid).isdigit() else str(qid), "type": kind, "year": year, "subject": subject, "lane": lane,
            "official_key_status": key, "ready_editions": eds or [],
            "proxy_candidate_editions": proxy or [], "proxy_candidates_ordered": ordered}


class PromptKillTime(unittest.TestCase):
    def test_researcher_prompt_carries_absolute_kill_time(self):
        batch = {"batch_id": "b1", "subject": "orthodontics", "type": "residency",
                 "selected_editions": ["proffit-orthodontics@6e"], "proxy_basis": None,
                 "key_class": "final", "years": [1391]}
        text = runner.render_prompt(batch, Path("/x"), runner.PROMPT.read_text(), "2026-11-10T03:40:00Z")
        self.assertIn("killed at 2026-11-10T03:40:00Z", text)
        self.assertNotIn("{{", text)


class Planning(unittest.TestCase):
    def test_groups_across_years_by_selected_edition_set(self):
        items = [item(i, 1399 + i % 3, eds=["neville@5e"]) for i in range(1, 61)]
        batches, _ = runner.plan_batches(items)
        self.assertEqual([len(b["question_ids"]) for b in batches], [10] * 6)
        self.assertEqual(batches[0]["selected_editions"], ["neville@5e"])
        self.assertEqual(sorted({y for b in batches for y in b["years"]}), [1399, 1400, 1401])

    def test_newest_per_family_not_whole_candidate_bundle(self):
        it = item(1, 1393, lane="residency_dental_proxy_policy",
                  proxy=["neville@5e", "neville@4e", "regezi@7e"])
        self.assertEqual(runner.select_editions(it)[0], ["neville@5e", "regezi@7e"])
        b = orchestrator.plan_batches([it])
        self.assertEqual(b[0]["editions"], ["neville@5e", "regezi@7e"])

    def test_unordered_proxy_fails_closed(self):
        it = item(1, 1393, lane="residency_dental_proxy_policy", proxy=["a@1"], ordered=False)
        batches, blocked = runner.plan_batches([it])
        self.assertEqual(batches, [])
        self.assertEqual(blocked, {"proxy_order_unverified_regenerate_manifest": 1})

    def test_proxy_basis_and_voided_never_mixed(self):
        p = dict(lane="residency_dental_proxy_policy", proxy=["a@1"])
        items = [item(1, 1395, **p), item(2, 1396, **p), item(3, 1395, key="voided", **p)]
        batches, _ = runner.plan_batches(items)
        self.assertEqual(len(batches), 3)
        self.assertEqual({(b["proxy_basis"], b["key_class"]) for b in batches},
                         {("retrospective_proxy_1397_plus", "final"), ("same_lineage_proxy", "final"),
                          ("retrospective_proxy_1397_plus", "voided")})

    def test_national_only_when_residency_empty_and_never_english(self):
        nat = item(9, 1400, lane="national_dental_same_edition_ready", kind="national", eds=["a@1"])
        res = item(1, 1400, eds=["a@1"])
        eng = item(2, 1400, lane="residency_english_blocked_policy", subject="english")
        self.assertEqual([b["type"] for b in runner.plan_batches([nat, res, eng])[0]], ["residency"])
        self.assertEqual([b["type"] for b in runner.plan_batches([nat, eng])[0]], ["national"])
        self.assertEqual(runner.plan_batches([nat, res], {U(1)})[0][0]["type"], "national")

    def test_high_yield_one_book_outranks_single_question_five_books(self):
        one = item(1, 1391, eds=["a@1", "b@1", "c@1", "d@1", "e@1"])
        many = [item(i, 1392, lane="residency_dental_proxy_policy",
                     subject="endodontics", proxy=["torabinejad@6e"])
                for i in range(10, 24)]
        batches, _ = runner.plan_batches([one] + many)
        self.assertEqual(batches[0]["subject"], "endodontics")
        self.assertEqual(len(batches[0]["question_ids"]), runner.BATCH_MAX)

    def test_existing_partial_source_requires_repair(self):
        partial = item(1, 1400, eds=["sturdevant-operative@7e"])
        partial["existing_source_rows"] = 1
        fresh = item(2, 1396, lane="residency_dental_proxy_policy",
                     proxy=["mccracken-rpd@13e"])
        batches, blocked = runner.plan_batches([partial, fresh])
        self.assertEqual(blocked.get("existing_source_requires_repair"), 1)
        self.assertEqual(len(batches), 1)
        self.assertEqual(batches[0]["question_ids"], [U(2)])

    def test_default_model_is_opus_with_noninteractive_permissions(self):
        a = runner.parse(["--manifest", "/tmp/m.json", "--state-dir", "/tmp/st",
                          "--case-root", "/tmp/cases", "--queue", "/tmp/q",
                          "--receipts", "/tmp/r", "--dry-run"])
        self.assertIn("opus", a.session_cmd)
        self.assertIn("--permission-mode", a.session_cmd)
        self.assertIn("auto", a.session_cmd)

    def test_injection_ids_and_editions_refused(self):
        bad = [item("1;rm -rf /", 1400, eds=["a@1"]), item(2, 1400, eds=["a@1 $(id)"])]
        batches, blocked = runner.plan_batches(bad)
        self.assertEqual(batches, [])
        self.assertEqual(sum(blocked.values()), 2)
        with self.assertRaises(runner.Blocked):
            runner.live_absent(["1 OR 1=1"])


class StateAndReceipts(unittest.TestCase):
    def test_own_decisions_never_count_and_unverified_stops(self):
        st = {"batches": {"b1": {"status": "staged", "started": 1, "question_ids": ["1"],
                                 "accepted": 0, "drafted": 30}}}
        st = runner.reconcile(st, {}, time.time())
        self.assertEqual(st["accepted_total"], 0)
        self.assertIn("awaiting_trusted_operator_receipt", runner.guard(st, time.time(), 10 ** 12, 1))

    def test_two_confirmed_zero_batches_hard_stop(self):
        b = lambda t: {"status": "staged", "started": t, "question_ids": [], "accepted": 0}
        st = {"batches": {"x": b(1), "y": b(2)}}
        rc = {k: {"accepted_source_rows": 0} for k in ("x", "y")}
        st = runner.reconcile(st, rc, time.time())
        self.assertIn("hard_stop_two_zero_accepted_batches", runner.guard(st, time.time(), 10 ** 12, 3))
        rc["y"]["accepted_source_rows"] = 5
        st["batches"]["y"]["status"] = "staged"
        st = runner.reconcile(st, {"y": rc["y"]}, time.time())
        self.assertEqual(st["zero_accepted_streak"], 0)

    def test_disk_and_backoff_guards(self):
        now = time.time()
        r = runner.guard({"quota_backoff_until": now + 60}, now, 2 * 1024 ** 3, 2)
        self.assertEqual(set(r), {"disk_below_3GiB", "provider_retry_after_backoff"})

    def test_runner_owned_receipts_untrusted(self):
        with tempfile.TemporaryDirectory() as d:
            p = Path(d, "b.json")
            p.write_text(json.dumps({"format": runner.RECEIPT_FORMAT, "batch_id": "b",
                                     "accepted_source_rows": 9, "independent_qa": "approved_or_rejected",
                                     "readback_verified": True}))
            os.chmod(p, 0o640)
            self.assertEqual(runner.trusted_receipts(Path(d), {os.getuid()}), {})

    def test_quota_scan(self):
        self.assertEqual(runner.scan_quota('{"error":"rate_limit","retry-after": 120}'), (True, 120))
        self.assertEqual(runner.scan_quota("ok"), (False, None))


class Session(unittest.TestCase):
    def test_timeout_kills_and_output_private(self):
        with tempfile.TemporaryDirectory() as d:
            out = Path(d, "s.out")
            res = runner.run_session([sys.executable, "-c", "import time;time.sleep(30)"], "x",
                                     Path(d), out, timeout=1)
            self.assertTrue(res["killed"])
            self.assertEqual(out.stat().st_mode & 0o777, 0o600)

    def test_env_filtered(self):
        os.environ["FANOOS_SECRET_TOKEN"] = "s3cret"
        try:
            with tempfile.TemporaryDirectory() as d:
                out = Path(d, "s.out")
                runner.run_session([sys.executable, "-c", "import os;print(sorted(os.environ))"],
                                   "", Path(d), out, timeout=10)
                self.assertNotIn("FANOOS_SECRET_TOKEN", out.read_text())
        finally:
            del os.environ["FANOOS_SECRET_TOKEN"]

    def test_end_to_end_fake_session_stages_but_never_accepts(self):
        with tempfile.TemporaryDirectory() as d:
            d = Path(d)
            (d / "m.json").write_text(json.dumps({"items": [item(i, 1400, eds=["a@1"]) for i in (1, 2)]}))
            script = d / "fake.py"
            script.write_text("import json,sys,glob,pathlib\nsys.stdin.read()\n"
                              "c=pathlib.Path(glob.glob(sys.argv[1]+'/v3-*')[0])\n"
                              "(c/'decisions.draft.json').write_text(json.dumps({'decisions':"
                              "[{'question_id':'a','decision':'proposed'},{'question_id':'b','decision':'pending'}]}))\n")
            orig = runner.live_absent
            runner.live_absent = lambda ids: set(ids)
            try:
                args = ["--manifest", str(d / "m.json"), "--state-dir", str(d / "st"),
                        "--case-root", str(d / "cases"), "--queue", str(d / "q"),
                        "--receipts", str(d / "r"), "--trusted-uid", "982", "--tmp-root", str(d),
                        "--workdir", str(d), "--max-batches", "3", "--max-unverified", "1",
                        "--session-cmd", sys.executable, str(script), str(d / "cases")]
                (d / "cases").mkdir()
                self.assertEqual(runner.run(runner.parse(args)), 4)  # stops awaiting receipts
            finally:
                runner.live_absent = orig
            st = json.loads((d / "st/state.json").read_text())
            self.assertEqual(st["accepted_total"], 0)
            (b,) = st["batches"].values()
            self.assertEqual((b["status"], b["drafted"]), ("staged", 1))
            (req,) = (d / "q").glob("*.json")
            self.assertEqual(json.loads(req.read_text())["status"], "awaiting_independent_qa")
            self.assertEqual(list(d.glob("v3-run-*")), [])  # temp cleaned


    def test_zero_proposal_session_is_not_queued_for_qa(self):
        with tempfile.TemporaryDirectory() as d:
            d = Path(d)
            (d / "m.json").write_text(json.dumps({"items": [item(i, 1400, eds=["a@1"]) for i in (1, 2)]}))
            script = d / "fake.py"
            script.write_text("import json,sys,glob,pathlib\nsys.stdin.read()\n"
                              "c=pathlib.Path(glob.glob(sys.argv[1]+'/v3-*')[0])\n"
                              "(c/'decisions.draft.json').write_text(json.dumps({'decisions':"
                              "[{'question_id':'a','decision':'pending'}]}))\n")
            orig = runner.live_absent
            runner.live_absent = lambda ids: set(ids)
            try:
                args = ["--manifest", str(d / "m.json"), "--state-dir", str(d / "st"),
                        "--case-root", str(d / "cases"), "--queue", str(d / "q"),
                        "--receipts", str(d / "r"), "--trusted-uid", "982", "--tmp-root", str(d),
                        "--workdir", str(d), "--max-batches", "1", "--max-unverified", "1",
                        "--session-cmd", sys.executable, str(script), str(d / "cases")]
                (d / "cases").mkdir()
                runner.run(runner.parse(args))
            finally:
                runner.live_absent = orig
            st = json.loads((d / "st/state.json").read_text())
            (b,) = st["batches"].values()
            self.assertEqual((b["status"], b["drafted"]), ("no_proposals", 0))
            self.assertEqual(st.get("unverified_staged", 0), 0)
            self.assertEqual(list((d / "q").glob("*.json")) if (d / "q").exists() else [], [])
            self.assertTrue(set(b["question_ids"]) <= runner.excluded_ids(st))


if __name__ == "__main__":
    unittest.main()
