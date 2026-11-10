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
