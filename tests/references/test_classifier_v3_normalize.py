import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts"))
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/classification_v3"))
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/references"))

from classification_v3 import normalize, prep  # noqa: E402

SHA = "a" * 64
QID = "01a12826-0000-7000-8000-000000000001"
QID2 = "01a12826-0000-7000-8000-000000000002"
BATCH = {"batch_id": "0123456789abcdef", "subject": "oral-medicine", "type": "residency",
         "key_class": "final", "proxy_basis": "retrospective_proxy_1397_plus",
         "selected_editions": ["little@10e"], "question_ids": [QID, QID2]}


def q(qid=QID, number=40, status="final", also=None):
    return {"id": qid, "type": "residency", "year": 1392, "round": 1, "number": number, "stem": "s",
            "choices": ["a", "b", "c", "d"], "study_choices": ["a", {"text": "b", "image": "i.png"}, "c", "d"],
            "answer": {"choice": 2, "also_correct": also or [], "status": status}, "source_rows": 0}


def row(**kw):
    return {"question_id": QID, "decision": "proposed", "edition": "little@10e", "pdf_sha256": SHA,
            "pdf_page": 40, "chapter_number": "2", "node_id": None, "quote": "four words of quote",
            "confidence": 0.9, "edition_basis": "retrospective_proxy_1397_plus", **kw}


def run(rows, questions=None, chapter="2", ref=1406):
    return normalize.normalize({"decisions": rows}, questions or [q()], BATCH,
                               lambda e, s, p: chapter, lambda e, s: ref)


class Normalize(unittest.TestCase):
    def test_fills_identity_policy_and_exact_retrospective_note(self):
        d, c = run([row()])
        (r,) = d["decisions"]
        self.assertEqual(c, {"proposed": 1, "demoted": 0})
        self.assertEqual((r["exam_type"], r["year"], r["round"], r["number"], r["subject"]),
                         ("residency", 1392, 1, 40, "oral-medicine"))
        self.assertEqual((r["key_class"], r["basis"], r["node_id"], r["reference_year"]),
                         ("final", "retrospective_proxy_1397_plus", "2", 1406))
        from retrospective_reference_policy import retrospective_note
        self.assertEqual(r["note_fa"], retrospective_note(1406, 1392))
        self.assertNotIn("edition_basis", r)

    def test_model_disagreement_demotes_instead_of_overwriting(self):
        for bad, reason in ((row(number=41), "identity_mismatch:number"),
                            (row(edition_basis="same_lineage_proxy"), "basis_mismatch"),
                            (row(chapter_number="3"), "chapter_disagrees_with_page_map"),
                            (row(edition="other@1e"), "edition_sha_page_not_on_verified_map"),
                            (row(question_id="01a12826-0000-7000-8000-00000000000f"), "id_not_in_batch_prep")):
            d, c = run([bad])
            self.assertEqual((d["decisions"][0]["decision"], d["decisions"][0]["pending_reason"]),
                             ("pending", reason))
            self.assertEqual(c["demoted"], 1)

    def test_unmapped_page_old_reference_year_and_multi_key_demote(self):
        self.assertEqual(run([row()], chapter=None)[0]["decisions"][0]["pending_reason"],
                         "edition_sha_page_not_on_verified_map")
        self.assertEqual(run([row()], ref=1396)[0]["decisions"][0]["pending_reason"], "reference_year_unavailable")
        self.assertEqual(run([row()], questions=[q(also=[3])])[0]["decisions"][0]["pending_reason"],
                         "official_key_not_single_final")

    def test_same_lineage_note_is_generated_and_passes_policy_check(self):
        from same_lineage_proxy_policy import valid_proxy_note
        batch = {**BATCH, "proxy_basis": "same_lineage_proxy"}
        model_row = row(edition_basis="same_lineage_proxy", note_fa="model wording without years")
        d, c = normalize.normalize({"decisions": [model_row]}, [{**q(), "year": 1396}], batch,
                                   lambda e, s, p: "2", lambda e, s: 1406)
        (r,) = d["decisions"]
        self.assertEqual(c, {"proposed": 1, "demoted": 0})
        self.assertEqual(r["note_fa"], normalize.same_lineage_note(1406, 1396))
        self.assertTrue(valid_proxy_note(r["note_fa"], 1396, 1406))

    def test_chapter_outside_official_scope_demotes_before_validator(self):
        calls = []

        def scope_of(year, edition):
            calls.append((year, edition))
            return {"1", "2"} if edition == "little@10e" else None
        ok = normalize.normalize({"decisions": [row()]}, [q()], BATCH, lambda e, s, p: "2",
                                 lambda e, s: 1406, scope_of)[0]["decisions"][0]
        self.assertEqual(ok["decision"], "proposed")
        out = normalize.normalize({"decisions": [row(chapter_number="7")]}, [q()], BATCH,
                                  lambda e, s, p: "7", lambda e, s: 1406, scope_of)[0]["decisions"][0]
        self.assertEqual((out["decision"], out["pending_reason"]), ("pending", "chapter_outside_official_scope"))
        unlisted = normalize.normalize({"decisions": [row(chapter_number="9")]}, [q()], BATCH, lambda e, s, p: "9",
                                       lambda e, s: 1406, lambda y, e: None)[0]["decisions"][0]
        self.assertEqual(unlisted["decision"], "proposed")
        self.assertEqual(calls[0], (1392, "little@10e"))

    def test_scope_chapters_from_validity_row(self):
        self.assertIsNone(normalize.scope_chapters({"scope_chapters": []}))
        self.assertIsNone(normalize.scope_chapters(None))
        self.assertEqual(normalize.scope_chapters({"scope_chapters": [{"number": 10}, {"number": "2"}]}),
                         ["2", "10"])

    def test_study_docs_match_exporter_shape_and_skip_sourced(self):
        docs = normalize.study_docs([q(), {**q(QID2, 50), "source_rows": 1}], BATCH)
        self.assertEqual(list(docs), [1392])
        doc = docs[1392]
        self.assertEqual(doc["format"], "fanoos.classification.study-only/1")
        self.assertEqual(doc["questions"], [{"number": 40, "subject": "oral-medicine", "stem": "s",
                                             "choices": ["a", {"text": "b", "image": "i.png"}, "c", "d"],
                                             "answer": {"choice": 2, "also_correct": [], "status": "final"}}])

    def test_normalize_case_writes_files_once_and_keeps_model_copy(self):
        with tempfile.TemporaryDirectory() as t:
            case = Path(t)
            (case / "questions.json").write_text(json.dumps({"questions": [q()]}))
            (case / "decisions.draft.json").write_text(json.dumps({"decisions": [row()]}))
            c = normalize.normalize_case(case, BATCH, lambda e, s, p: "2", lambda e, s: 1406)
            self.assertEqual(c["proposed"], 1)
            self.assertTrue((case / "study-1392.json").exists())
            self.assertEqual(json.loads((case / "decisions.model.json").read_text())["decisions"][0]["node_id"], None)
            self.assertEqual(normalize.normalize_case(case, BATCH, lambda *a: "2", lambda *a: 1406), {"already": True})


class Prevalidate(unittest.TestCase):
    ROW = {"decision": "proposed", "question_id": "q", "year": 1392, "number": 7, "edition": "e@1",
           "node_id": "3", "pdf_page": 40, "confidence": 0.9, "quote": "four words of evidence",
           "reference_year": 1406, "basis": "retrospective_proxy_1397_plus", "note_fa": "n"}

    def _case(self, d, rows):
        case = Path(d)
        (case / "decisions.draft.json").write_text(json.dumps({"decisions": rows}))
        return case

    def test_publisher_shape_and_demotion(self):
        rows = [dict(self.ROW), dict(self.ROW, number=8, question_id="r"), {"decision": "pending", "question_id": "s"}]
        seen = []

        def validator(study, decisions, tmp):
            seen.append((study.name, decisions))
            return {7}, {8: "evidence is not an exact case/punctuation match on page 40"}
        with tempfile.TemporaryDirectory() as d, tempfile.TemporaryDirectory() as t:
            import os
            os.environ["V3_TMP_ROOT"] = t
            try:
                case = self._case(d, rows)
                self.assertEqual(normalize.prevalidate_case(case, validator),
                                 {"validated": 1, "demoted_by_validator": 1})
                self.assertEqual(normalize.prevalidate_case(case, validator), {"already": True})
            finally:
                del os.environ["V3_TMP_ROOT"]
            self.assertEqual(list(Path(t).iterdir()), [])  # scratch removed
            out = json.loads((case / "decisions.draft.json").read_text())
            self.assertEqual([r["decision"] for r in out["decisions"]], ["proposed", "pending", "pending"])
            self.assertTrue(out["decisions"][1]["pending_reason"].startswith("trusted_validator_rejected: evidence"))
        (name, decs), = seen
        self.assertEqual(name, "study-1392.json")
        self.assertEqual(decs[0], {"number": 7, "edition": "e@1", "chapter": "3", "page": 40, "confidence": 0.9,
                                   "evidence": "four words of evidence", "reference_year": 1406,
                                   "classification_basis": "retrospective_proxy_1397_plus", "proxy_note": "n"})

    def test_legacy_evidence_quote_is_preserved_exactly_and_validated(self):
        """Production old drafts used evidence_quote; cannot throw KeyError forever."""
        legacy = dict(self.ROW)
        legacy.pop("quote")
        legacy["evidence_quote"] = "original PDF words present here exactly"
        seen = []
        def validator(study, decisions, tmp):
            seen.extend(decisions)
            return {7}, {}
        with tempfile.TemporaryDirectory() as d, tempfile.TemporaryDirectory() as t:
            import os
            os.environ["V3_TMP_ROOT"] = t
            try:
                case = self._case(d, [legacy])
                out = normalize.prevalidate_case(case, validator)
                self.assertEqual(out, {"validated": 1, "demoted_by_validator": 0})
                stored = json.loads((case / "decisions.draft.json").read_text())
                self.assertEqual(stored["decisions"][0]["quote"],
                                 legacy["evidence_quote"])
                self.assertEqual(stored["decisions"][0]["evidence_quote"],
                                 legacy["evidence_quote"])
                self.assertEqual(seen[0]["evidence"],legacy["evidence_quote"])
                self.assertEqual(list(Path(t).iterdir()), [])
            finally:
                del os.environ["V3_TMP_ROOT"]

    def test_legacy_short_quote_demoted_without_any_validator_invocation(self):
        legacy = dict(self.ROW)
        legacy.pop("quote")
        legacy["evidence_quote"] = "only three words"
        def must_not_call(*args):
            raise AssertionError("should never validate unsupported quote")
        with tempfile.TemporaryDirectory() as d, tempfile.TemporaryDirectory() as t:
            import os
            os.environ["V3_TMP_ROOT"] = t
            try:
                case = self._case(d, [legacy])
                counts = normalize.prevalidate_case(case, must_not_call)
                self.assertEqual(counts, {"validated": 0, "demoted_by_validator": 1})
                out = json.loads((case / "decisions.draft.json").read_text())
                self.assertEqual(out["decisions"][0]["decision"], "pending")
                self.assertIn("exact_quote_missing_or_too_short",out["decisions"][0]["pending_reason"])
            finally:
                del os.environ["V3_TMP_ROOT"]

    def test_normalize_legacy_quote_field_to_canonical_proposal(self):
        legacy = row()
        legacy["evidence_quote"] = legacy.pop("quote")
        d,c=run([legacy])
        self.assertEqual(c, {"proposed": 1, "demoted": 0})
        self.assertEqual(d["decisions"][0]["quote"],"four words of quote")

    def test_model_missing_exact_evidence_is_scientifically_pending(self):
        incomplete = row()
        incomplete.pop("quote")
        d,c=run([incomplete])
        self.assertEqual(c, {"proposed": 0, "demoted": 1})
        self.assertEqual(d["decisions"][0]["pending_reason"],
                         "exact_quote_missing_or_too_short")

    def test_clean_without_write_and_error_leave_bytes(self):
        with tempfile.TemporaryDirectory() as d, tempfile.TemporaryDirectory() as t:
            import os
            os.environ["V3_TMP_ROOT"] = t
            try:
                case = self._case(d, [dict(self.ROW)])
                before = (case / "decisions.draft.json").read_bytes()
                normalize.prevalidate_case(case, lambda s, dd, tmp: ({7}, {}), write_if_clean=False)
                self.assertEqual((case / "decisions.draft.json").read_bytes(), before)

                def boom(s, dd, tmp):
                    raise RuntimeError("validator_exit_2")
                with self.assertRaises(RuntimeError):
                    normalize.prevalidate_case(case, boom)
                self.assertEqual((case / "decisions.draft.json").read_bytes(), before)
            finally:
                del os.environ["V3_TMP_ROOT"]


class PrepImages(unittest.TestCase):
    def test_choice_image_hex_becomes_exporter_dict(self):
        hx = lambda s: s.encode().hex().upper()  # noqa: E731
        row = [QID, "residency", "1392", "1", "40", hx("s"), f"2:{hx('b')}:{hx('i.png')},1:{hx('a')}:N",
               "2||final", "0", "0"]
        (r,) = prep.rows_to_questions([row])
        self.assertEqual(r["choices"], ["a", "b"])
        self.assertEqual(r["study_choices"], ["a", {"text": "b", "image": "i.png"}])


if __name__ == "__main__":
    unittest.main()
