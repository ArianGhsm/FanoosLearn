import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts"))
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/classification_v3"))

from classification_v3 import prep  # noqa: E402
import cli  # noqa: E402


def hx(s):
    return s.encode("utf-8").hex().upper()


class StudyRows(unittest.TestCase):
    def test_exporter_envelope_maps_number_answer_and_list_choices(self):
        study = {"format": "fanoos.classification.study-only/1", "questions": [
            {"number": 7, "stem": "s", "choices": ["a", {"text": "b", "image": "x"}, "c", "d"],
             "answer": {"choice": 2, "also_correct": [], "status": "final"}}]}
        (r,) = cli.study_rows(study)
        self.assertEqual((r["id"], r["key"]), ("7", "2"))
        self.assertEqual(r["choices"], {"1": "a", "2": "b", "3": "c", "4": "d"})

    def test_voided_answer_has_no_key_and_bare_list_still_works(self):
        (v,) = cli.study_rows({"questions": [{"number": 1, "stem": "s", "choices": [],
                                              "answer": {"choice": None, "status": "voided"}}]})
        self.assertIsNone(v["key"])
        (b,) = cli.study_rows([{"id": "q1", "stem": "s", "choices": {"1": "a"}, "key": "1"}])
        self.assertEqual((b["id"], b["key"]), ("q1", "1"))


class Prep(unittest.TestCase):
    ROW = ["01a12826-0000-7000-8000-000000000001", "residency", "1391", "1", "202", hx("ساقه\tدو\nخط"),
           f"2:{hx('دوم')},1:{hx('اول')},3:,4:{hx('چهارم')}", '3|[1]|amended', "1", "0"]

    def test_rows_decode_hex_order_choices_and_parse_answer(self):
        (q,) = prep.rows_to_questions([self.ROW])
        self.assertEqual(q["stem"], "ساقه\tدو\nخط")
        self.assertEqual(q["choices"], ["اول", "دوم", "", "چهارم"])
        self.assertEqual(q["answer"], {"choice": 3, "also_correct": [1], "status": "amended"})
        self.assertEqual((q["year"], q["number"], q["key"], q["complete_source_rows"]), (1391, 202, "3", 0))

    def test_voided_answer(self):
        row = self.ROW[:7] + ["|null|voided", "0", "0"]
        (q,) = prep.rows_to_questions([row])
        self.assertEqual((q["answer"]["choice"], q["answer"]["status"], q["key"]), (None, "voided", None))

    def test_prepare_writes_private_questions_with_leads_only(self):
        qs = prep.rows_to_questions([self.ROW])
        leads = {"a@1": {"pdf_sha256": "f" * 64, "page_count": 9,
                         "by_question": {qs[0]["id"]: [{"page": 4, "score": 3.0, "chapter": "2"}]}},
                 "b@2": {"skipped": "prep_time_budget"}}
        with tempfile.TemporaryDirectory() as d:
            res = prep.prepare(Path(d), {"batch_id": "b", "question_ids": [qs[0]["id"]],
                                         "selected_editions": ["a@1", "b@2"]},
                               fetch=lambda ids: qs, scan=lambda q, e, b: leads)
            path = Path(d, "questions.json")
            doc = json.loads(path.read_text())
            self.assertEqual(path.stat().st_mode & 0o027, 0)  # never group-writable or world-readable
            self.assertEqual(doc["questions"][0]["page_leads"]["a@1"][0]["page"], 4)
            self.assertEqual(doc["questions"][0]["page_leads"]["b@2"], [])
            self.assertNotIn("by_question", doc["editions"]["a@1"])
            self.assertEqual(res["editions"], {"a@1": "ok", "b@2": "prep_time_budget"})
            self.assertEqual(sorted(p.name for p in Path(d).iterdir()), ["questions.json"])

    def test_invalid_ids_refused_before_sql(self):
        with self.assertRaises(ValueError):
            prep.fetch_questions(["1; DROP TABLE x"])


if __name__ == "__main__":
    unittest.main()
