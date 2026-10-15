"""Whole-subject publication planner safety contract tests (no DB writes)."""
import hashlib
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

path = Path(__file__).resolve().parents[2] / "scripts/references/plan_subject_publication_cycle.py"
spec = importlib.util.spec_from_file_location("subject_cycle", path)
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)


class SubjectCycleTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.cycle = {"format": mod.FORMAT, "exam_type": "residency", "year": 1405, "round": 1, "live_question_inventory_sha256": "a"*64, "subjects": []}

    def subject(self, name, ready, already=(), held=(), operator="repair_verified_source_pages.php"):
        doc = {"format": mod.OPERATORS[operator][0], "exam_type": "residency", "year": 1405, "subject": name, "entries": [{"number": n} for n in ready]}
        p = self.root / (name + ".json")
        p.write_text(json.dumps(doc), encoding="utf-8")
        plan = {"operator": operator, "path": str(p), "sha256": hashlib.sha256(p.read_bytes()).hexdigest()}
        item = {"subject": name, "all_question_numbers": sorted(set(ready) | set(already) | set(held)), "already_verified_and_bound": list(already), "holds": [{"number": n, "reason": "Awaiting original PDF evidence"} for n in held], "plans": [plan]}
        self.cycle["subjects"].append(item)
        return item

    def test_one_whole_subject_with_pending_cases(self):
        self.subject("endodontics", [22,23], [21], [24])
        v = mod.plan_cycle(self.cycle, self.root)
        self.assertEqual(v["ready_source_count"], 2)
        self.assertEqual(v["subjects"][0]["held_numbers"], [24])
        self.assertEqual(v["mode"], "READ_ONLY_PLANNING_NO_DATABASE_WRITES")

    def test_two_subjects(self):
        self.subject("endodontics", [22], [21])
        self.subject("oral-medicine", [62], [61], operator="repair_verified_source_chapter_pages.php")
        v = mod.plan_cycle(self.cycle, self.root)
        self.assertEqual((v["subject_count"],v["ready_source_count"]),(2,2))

    def test_gaps_and_duplicates_fail_closed(self):
        x = self.subject("endodontics", [22], [21])
        x["all_question_numbers"].append(23)
        with self.assertRaises(mod.Rejected):
            mod.plan_cycle(self.cycle, self.root)
        x["all_question_numbers"].pop()
        x["already_verified_and_bound"].append(22)
        with self.assertRaises(mod.Rejected):
            mod.plan_cycle(self.cycle, self.root)

    def test_tampered_plan_rejected(self):
        x = self.subject("endodontics", [22], [21])
        x["plans"][0]["sha256"] = "b"*64
        with self.assertRaisesRegex(mod.Rejected,"SHA-256"):
            mod.plan_cycle(self.cycle, self.root)

    def test_operator_caps_unchanged(self):
        self.subject("endodontics",list(range(21,34)))
        with self.assertRaises(mod.Rejected):
            mod.plan_cycle(self.cycle, self.root)

    def test_incorrect_chapter_plan_size_rejected(self):
        self.subject("endodontics",[21,22],operator="repair_verified_source_chapter_pages.php")
        with self.assertRaises(mod.Rejected):
            mod.plan_cycle(self.cycle, self.root)

    def test_three_subjects_and_english_rejected(self):
        for s in ("endodontics","oral-medicine","prosthodontics"):
            self.subject(s,[21])
        with self.assertRaises(mod.Rejected):
            mod.plan_cycle(self.cycle, self.root)
        self.cycle["subjects"] = []
        self.subject("english",[21])
        with self.assertRaises(mod.Rejected):
            mod.plan_cycle(self.cycle, self.root)

    def test_cross_subject_or_wrong_year_rejected(self):
        x = self.subject("endodontics",[22])
        p = Path(x["plans"][0]["path"])
        d = json.loads(p.read_text())
        d["subject"]="prosthodontics"
        p.write_text(json.dumps(d))
        x["plans"][0]["sha256"]=hashlib.sha256(p.read_bytes()).hexdigest()
        with self.assertRaises(mod.Rejected):
            mod.plan_cycle(self.cycle, self.root)


if __name__ == "__main__":
    unittest.main()
