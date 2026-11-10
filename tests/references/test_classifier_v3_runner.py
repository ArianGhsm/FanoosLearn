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


_ORIG_PREVALIDATE = runner._prevalidate


_ORIG_VALIDATOR_EDITIONS = runner.validator_editions


def setUpModule():
    # Session tests never reach the production validator; revalidation has its own tests.
    runner._prevalidate = lambda case, write_if_clean: {"demoted_by_validator": 0}
    # Planning fixtures use synthetic editions; the catalog filter has its own test.
    runner.validator_editions = lambda kind, year, subject: set()


def tearDownModule():
    runner._prevalidate = _ORIG_PREVALIDATE
    runner.validator_editions = _ORIG_VALIDATOR_EDITIONS


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


class ValidatorEditionFilter(unittest.TestCase):
    def setUp(self):
        self.addCleanup(setattr, runner, "validator_editions", runner.validator_editions)

    def item(self, eds):
        return {"lane": "residency_dental_proxy_policy", "type": "residency", "year": 1396,
                "subject": "pediatric-dentistry", "proxy_candidates_ordered": True,
                "proxy_candidate_editions": eds}

    def test_drops_editions_the_validator_never_selects(self):
        runner.validator_editions = lambda k, y, s: {"mcdonald-avery-pediatric@11e"}
        self.assertEqual(runner.select_editions(self.item(["mcdonald-avery-pediatric@11e", "nowak-pediatric@6e"])),
                         (["mcdonald-avery-pediatric@11e"], None))
        self.assertEqual(runner.select_editions(self.item(["nowak-pediatric@6e"])),
                         ([], "no_validator_selected_edition"))

    def test_unknown_catalog_keeps_manifest_set(self):
        runner.validator_editions = lambda k, y, s: set()
        self.assertEqual(runner.select_editions(self.item(["nowak-pediatric@6e"]))[0], ["nowak-pediatric@6e"])

    def test_real_catalog_lookup_fails_soft(self):
        self.assertEqual(_ORIG_VALIDATOR_EDITIONS("residency", "not-a-year", "x"), set())


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

    def _stale_case(self, d, created, draft_mtime):
        queue, cases = Path(d, "q"), Path(d, "c")
        case = cases / "v3-aaaaaaaaaaaaaaaa"
        case.mkdir(parents=True)
        (case / "batch.json").write_text(json.dumps({"batch_id": "aaaaaaaaaaaaaaaa", "question_ids": ["1"],
                                                     "selected_editions": ["e@1"], "proxy_basis": None,
                                                     "key_class": "final"}))
        (case / "session.out").write_text(json.dumps({"session_id": "s"}))
        draft = case / "decisions.draft.json"
        draft.write_text(json.dumps({"batch_id": "aaaaaaaaaaaaaaaa",
                                     "decisions": [{"question_id": "1", "decision": "proposed"}]}))
        os.utime(draft, (draft_mtime, draft_mtime))
        queue.mkdir()
        (queue / "aaaaaaaaaaaaaaaa.json").write_text(json.dumps({"created": created}))
        st = {"batches": {"aaaaaaaaaaaaaaaa": {"status": "staged", "started": 1, "question_ids": ["1"],
                                               "accepted": 0, "drafted": 1, "unreached": ["2"]},
                          "bbbbbbbbbbbbbbbb": {"status": "failed", "started": 1, "question_ids": ["3"],
                                               "accepted": 0}}}
        (queue / "bbbbbbbbbbbbbbbb.json").write_text("{}")
        return queue, cases, st

    def test_renormalized_stale_batch_resubmitted_once(self):
        now = time.time()
        with tempfile.TemporaryDirectory() as d:
            queue, cases, st = self._stale_case(d, now - 7200, now - 3600)
            done = runner.resubmit_stale(st, queue, cases, now)
            new = [n for o, n in done if o == "aaaaaaaaaaaaaaaa"][0]
            self.assertIn(("bbbbbbbbbbbbbbbb", "withdrawn_failed"), done)
            self.assertEqual(st["batches"]["aaaaaaaaaaaaaaaa"]["status"], "resubmitted")
            self.assertEqual(st["batches"][new]["resubmitted_from"], "aaaaaaaaaaaaaaaa")
            self.assertEqual(st["unverified_staged"], 1)
            self.assertEqual(sorted(p.name for p in queue.glob("*.json")), [f"{new}.json"])
            req = json.loads((queue / f"{new}.json").read_text())
            self.assertEqual((req["batch_id"], req["case_dir"]), (new, str(cases / f"v3-{new}")))
            for f in ("batch.json", "decisions.draft.json"):
                self.assertEqual(json.loads((cases / f"v3-{new}" / f).read_text())["batch_id"], new)
            self.assertTrue((cases / "v3-aaaaaaaaaaaaaaaa" / "session.out").exists())
            # successor never resubmitted again; ids stay excluded, unreached carried
            self.assertEqual(runner.resubmit_stale(st, queue, cases, now + 10 ** 5), [])
            self.assertIn("1", runner.excluded_ids(st))
            st["batches"][new]["status"] = "operator_confirmed"
            self.assertNotIn("2", runner.excluded_ids(st))

    def test_fresh_or_unchanged_batch_not_resubmitted(self):
        now = time.time()
        with tempfile.TemporaryDirectory() as d:
            queue, cases, st = self._stale_case(d, now - 7200, now - 7200)
            self.assertEqual([n for o, n in runner.resubmit_stale(st, queue, cases, now)
                              if o == "aaaaaaaaaaaaaaaa"], [])
        with tempfile.TemporaryDirectory() as d:
            queue, cases, st = self._stale_case(d, now - 600, now - 60)
            self.assertEqual([n for o, n in runner.resubmit_stale(st, queue, cases, now)
                              if o == "aaaaaaaaaaaaaaaa"], [])
            self.assertEqual(st["batches"]["aaaaaaaaaaaaaaaa"]["status"], "staged")

    def _staged(self, d, rows, with_request=True):
        queue, cases = Path(d, "q"), Path(d, "c")
        case = cases / "v3-aaaaaaaaaaaaaaaa"
        case.mkdir(parents=True)
        queue.mkdir()
        (case / "batch.json").write_text(json.dumps({"batch_id": "aaaaaaaaaaaaaaaa", "question_ids": ["1", "2"],
                                                     "selected_editions": ["e@1"], "proxy_basis": None,
                                                     "key_class": "final"}))
        (case / "session.out").write_text(json.dumps({"session_id": "s"}))
        (case / "decisions.draft.json").write_text(json.dumps({"batch_id": "aaaaaaaaaaaaaaaa", "decisions": rows}))
        if with_request:
            (queue / "aaaaaaaaaaaaaaaa.json").write_text(json.dumps({"created": 1}))
        st = {"batches": {"aaaaaaaaaaaaaaaa": {"status": "staged", "started": 1, "question_ids": ["1", "2"],
                                               "accepted": 0, "drafted": len(rows), "resubmitted_from": "x"}}}
        return queue, cases, case, st

    @staticmethod
    def _fake_validator(reject):
        def run(case, write_if_clean):
            p = case / "decisions.draft.json"
            d = json.loads(p.read_text())
            if not reject and not write_if_clean:
                return {"demoted_by_validator": 0}
            for r in d["decisions"]:
                if r["question_id"] in reject:
                    r["decision"] = "pending"
            d["prevalidated_by"] = "t"
            p.write_text(json.dumps(d))
            return {"demoted_by_validator": len(reject)}
        return run

    def test_validator_rejects_resubmit_valid_subset_once(self):
        rows = [{"question_id": "1", "decision": "proposed"}, {"question_id": "2", "decision": "proposed"}]
        with tempfile.TemporaryDirectory() as d:
            queue, cases, case, st = self._staged(d, rows)
            before = (case / "decisions.draft.json").read_bytes()
            done = runner.revalidate_staged(st, queue, cases, 100.0, self._fake_validator({"2"}))
            ((old, new),) = done
            self.assertEqual(old, "aaaaaaaaaaaaaaaa")
            self.assertEqual((case / "decisions.draft.json").read_bytes(), before)  # old case untouched
            self.assertEqual(st["batches"][old]["status"], "resubmitted")
            self.assertEqual((st["batches"][new]["status"], st["batches"][new]["drafted"]), ("staged", 1))
            self.assertEqual(sorted(p.name for p in queue.glob("*.json")), [f"{new}.json"])
            self.assertTrue((queue / f"{old}.json.withdrawn-validator-{new}").exists())
            self.assertEqual(st["unverified_staged"], 1)
            # bounded: the validated successor is never revalidated or stale-resubmitted
            self.assertEqual(runner.revalidate_staged(st, queue, cases, 200.0, self._fake_validator({"1"})), [])
            self.assertEqual([x for x in runner.resubmit_stale(st, queue, cases, 10 ** 9) if x[0] == new], [])

    def test_validator_clean_keeps_reviewed_request_byte_identical(self):
        rows = [{"question_id": "1", "decision": "proposed"}]
        with tempfile.TemporaryDirectory() as d:
            queue, cases, case, st = self._staged(d, rows)
            before = (case / "decisions.draft.json").read_bytes()
            self.assertEqual(runner.revalidate_staged(st, queue, cases, 100.0, self._fake_validator(set())),
                             [("aaaaaaaaaaaaaaaa", "clean")])
            self.assertEqual((case / "decisions.draft.json").read_bytes(), before)
            self.assertEqual([p.name for p in queue.iterdir()], ["aaaaaaaaaaaaaaaa.json"])
            self.assertEqual([p.name for p in cases.iterdir()], ["v3-aaaaaaaaaaaaaaaa"])
            self.assertTrue(st["batches"]["aaaaaaaaaaaaaaaa"]["prevalidated"])

    def test_validator_emptied_request_withdrawn(self):
        rows = [{"question_id": "1", "decision": "proposed"}]
        with tempfile.TemporaryDirectory() as d:
            queue, cases, case, st = self._staged(d, rows)
            ((_, out),) = runner.revalidate_staged(st, queue, cases, 100.0, self._fake_validator({"1"}))
            self.assertTrue(out.endswith(":emptied"))
            self.assertEqual(list(queue.glob("*.json")), [])
            self.assertEqual(st["unverified_staged"], 0)
            self.assertIn("1", runner.excluded_ids(st))

    def test_new_batch_request_only_after_validator_and_error_holds_it(self):
        rows = [{"question_id": "1", "decision": "proposed"}, {"question_id": "2", "decision": "proposed"}]
        with tempfile.TemporaryDirectory() as d:
            queue, cases, case, st = self._staged(d, rows, with_request=False)

            def boom(case, write_if_clean):
                raise RuntimeError("db down")
            self.assertEqual(runner.revalidate_staged(st, queue, cases, 100.0, boom),
                             [("aaaaaaaaaaaaaaaa", "prevalidate_error:RuntimeError")])
            self.assertEqual(list(queue.iterdir()), [])
            done = runner.revalidate_staged(st, queue, cases, 100.0, self._fake_validator({"2"}))
            self.assertEqual(done, [("aaaaaaaaaaaaaaaa", "requested:1_demoted")])
            req = json.loads((queue / "aaaaaaaaaaaaaaaa.json").read_text())
            self.assertEqual(req["drafted_not_accepted"], 1)

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
            orig, orig_prep = runner.live_absent, runner._prepare
            runner.live_absent = lambda ids: set(ids)
            prepped = []
            runner._prepare = lambda case, batch: prepped.append(batch["batch_id"]) or {"questions": 2}
            orig_norm, normalized = runner._normalize, []
            runner._normalize = lambda case, batch: normalized.append(batch["batch_id"]) or {"proposed": 1}
            try:
                args = ["--manifest", str(d / "m.json"), "--state-dir", str(d / "st"),
                        "--case-root", str(d / "cases"), "--queue", str(d / "q"),
                        "--receipts", str(d / "r"), "--trusted-uid", "982", "--tmp-root", str(d),
                        "--workdir", str(d), "--max-batches", "3", "--max-unverified", "1",
                        "--session-cmd", sys.executable, str(script), str(d / "cases")]
                (d / "cases").mkdir()
                self.assertEqual(runner.run(runner.parse(args)), 4)  # stops awaiting receipts
            finally:
                runner.live_absent, runner._prepare, runner._normalize = orig, orig_prep, orig_norm
            self.assertTrue(normalized)  # trusted normalization runs before the QA request
            self.assertTrue(prepped)  # non-model prep runs before every session
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
            orig, orig_prep = runner.live_absent, runner._prepare
            runner.live_absent = lambda ids: set(ids)
            prepped = []
            runner._prepare = lambda case, batch: prepped.append(batch["batch_id"]) or {"questions": 2}
            try:
                args = ["--manifest", str(d / "m.json"), "--state-dir", str(d / "st"),
                        "--case-root", str(d / "cases"), "--queue", str(d / "q"),
                        "--receipts", str(d / "r"), "--trusted-uid", "982", "--tmp-root", str(d),
                        "--workdir", str(d), "--max-batches", "1", "--max-unverified", "1",
                        "--session-cmd", sys.executable, str(script), str(d / "cases")]
                (d / "cases").mkdir()
                runner.run(runner.parse(args))
            finally:
                runner.live_absent, runner._prepare = orig, orig_prep
            self.assertTrue(prepped)  # non-model prep runs before every session
            st = json.loads((d / "st/state.json").read_text())
            (b,) = st["batches"].values()
            self.assertEqual((b["status"], b["drafted"]), ("no_proposals", 0))
            self.assertEqual(st.get("unverified_staged", 0), 0)
            self.assertEqual(list((d / "q").glob("*.json")) if (d / "q").exists() else [], [])
            self.assertTrue(set(b["question_ids"]) <= runner.excluded_ids(st))

    def test_prep_failure_still_runs_session_but_holds_batch_from_qa(self):
        with tempfile.TemporaryDirectory() as d:
            d = Path(d)
            (d / "m.json").write_text(json.dumps({"items": [item(1, 1400, eds=["a@1"])]}))
            script = d / "fake.py"
            script.write_text("import json,sys,glob,pathlib\nsys.stdin.read()\n"
                              "c=pathlib.Path(glob.glob(sys.argv[1]+'/v3-*')[0])\n"
                              "(c/'decisions.draft.json').write_text(json.dumps({'decisions':"
                              "[{'question_id':'a','decision':'proposed'}]}))\n")
            orig, orig_prep = runner.live_absent, runner._prepare
            runner.live_absent = lambda ids: set(ids)

            def boom(case, batch):
                raise RuntimeError("db down")
            runner._prepare = boom
            try:
                args = ["--manifest", str(d / "m.json"), "--state-dir", str(d / "st"),
                        "--case-root", str(d / "cases"), "--queue", str(d / "q"),
                        "--receipts", str(d / "r"), "--trusted-uid", "982", "--tmp-root", str(d),
                        "--workdir", str(d), "--max-batches", "1", "--max-unverified", "1",
                        "--session-cmd", sys.executable, str(script), str(d / "cases")]
                (d / "cases").mkdir()
                runner.run(runner.parse(args))
            finally:
                runner.live_absent, runner._prepare = orig, orig_prep
            (b,) = json.loads((d / "st/state.json").read_text())["batches"].values()
            # The session still ran (seconds recorded); without prep questions.json the
            # trusted normalization cannot build QA study files, so nothing reaches QA.
            self.assertIn("seconds", b)
            self.assertEqual(b["status"], "failed")
            self.assertFalse((d / "q").exists() and list((d / "q").glob("*.json")))

    def test_unreached_ids_return_after_terminal_batch_with_attempt_cap(self):
        st = {"batches": {"b": {"status": "no_proposals", "question_ids": ["x", "y"], "unreached": ["y"]},
                          "s": {"status": "staged", "question_ids": ["z"], "unreached": ["z"]}},
              "attempts": {"x": 1, "y": 1, "z": 1}}
        self.assertEqual(runner.excluded_ids(st) - runner.NEVER_DISPATCH, {"x", "z"})
        st["attempts"]["y"] = runner.MAX_ATTEMPTS
        self.assertEqual(runner.excluded_ids(st) - runner.NEVER_DISPATCH, {"x", "y", "z"})

    def test_unreached_ids_reads_only_not_reached_pending(self):
        with tempfile.TemporaryDirectory() as d:
            Path(d, "decisions.draft.json").write_text(json.dumps({"decisions": [
                {"question_id": "a", "decision": "pending", "reason": "not_reached_in_session"},
                {"question_id": "b", "decision": "pending", "reason": "answer_not_located"},
                {"question_id": "c", "decision": "proposed"}]}))
            self.assertEqual(runner.unreached_ids(Path(d)), ["a"])
            self.assertEqual(runner.unreached_ids(Path(d, "missing")), [])

    def test_retryable_ids_classify_pending_reasons(self):
        rows = [("a", "searched_leads_no_passage (McDonald p120)"),
                ("b", "trusted_validator_rejected: evidence is not an exact case/punctuation match"),
                ("c", "trusted_validator_rejected: same-lineage proxy requires a note naming years"),
                ("d", "key_conflict: official key contradicts Neville p571"),
                ("e", "low_confidence: p134 mapping unclear"),
                ("f", "trusted_validator_rejected: chapter 18 is outside the official year-specific scope"),
                ("g", "answer only in Fig 8.24 diagram; text does not state it"),
                ("h", "not_reached_in_session"),
                ("i", "finder_searched: not located in in-scope chapters"),
                ("j", "evidence_insufficient"),
                ("k", "not located in 6e (no cement trail passage).")]
        with tempfile.TemporaryDirectory() as d:
            Path(d, "decisions.draft.json").write_text(json.dumps({"decisions": [
                {"question_id": q, "decision": "pending", "reason": r} for q, r in rows]
                + [{"question_id": "p", "decision": "proposed", "reason": "not located"}]}))
            self.assertEqual(runner.retryable_ids(Path(d)), ["a", "b", "c", "k"])
            self.assertEqual(runner.retryable_ids(Path(d, "missing")), [])

    def test_retryable_ids_return_once_and_never_dispatch_is_permanent(self):
        forbidden = next(iter(runner.NEVER_DISPATCH))
        st = {"batches": {"b": {"status": "operator_confirmed", "question_ids": ["x", "r", forbidden],
                                "retryable": ["r", forbidden], "unreached": [forbidden]},
                          "s": {"status": "staged", "question_ids": ["z"], "retryable": ["z"]}},
              "attempts": {"x": 1, "r": 1, "z": 1, forbidden: 1}}
        self.assertEqual(runner.excluded_ids(st), {"x", "z", forbidden})
        st["attempts"]["r"] = runner.RETRY_MAX_ATTEMPTS
        self.assertIn("r", runner.excluded_ids(st))

    def test_backfill_retryable_only_terminal_batches_without_key(self):
        with tempfile.TemporaryDirectory() as d:
            for bid in ("t", "u", "w"):
                Path(d, f"v3-{bid}").mkdir()
                Path(d, f"v3-{bid}", "decisions.draft.json").write_text(json.dumps({"decisions": [
                    {"question_id": "q" + bid, "decision": "pending", "reason": "not_located on leads"},
                    {"question_id": "zz", "decision": "pending", "reason": "not_located on leads"}]}))
            st = {"batches": {"t": {"status": "no_proposals", "question_ids": ["qt"]},
                              "u": {"status": "staged", "question_ids": ["qu"]},
                              "w": {"status": "operator_confirmed", "question_ids": ["qw"], "retryable": []}}}
            self.assertEqual(runner.backfill_retryable(st, Path(d)), 1)
            self.assertEqual(st["batches"]["t"]["retryable"], ["qt"])   # foreign id zz dropped
            self.assertNotIn("retryable", st["batches"]["u"])
            self.assertEqual(st["batches"]["w"]["retryable"], [])


if __name__ == "__main__":
    unittest.main()
