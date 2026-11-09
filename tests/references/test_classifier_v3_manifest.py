import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts"))

from classification_v3 import manifest, orchestrator  # noqa: E402

SHA = "a" * 64
PDFS = {"neville-oral-pathology@5e": SHA}
MAPS = {"neville-oral-pathology@5e": {"source_pdf_sha256": SHA, "runs": [["1", 1, 10]]}}
RES = {("oral-pathology", "neville-oral-pathology@5e")}


def row(qid, kind, year, subject, eds="", nsrc="0"):
    return [qid, kind, str(year), "1", subject, "final", eds, nsrc]


class ManifestLanes(unittest.TestCase):
    def build(self, rows):
        return {i["question_id"]: i for i in manifest.build(rows, PDFS, MAPS, RES)["items"]}

    def test_english_never_gets_a_book_lane(self):
        items = self.build([row("e1", "residency", 1400, "english", "neville-oral-pathology@5e"),
                            row("e2", "national", 1400, "english")])
        self.assertEqual(items["e1"]["lane"], "residency_english_blocked_policy")
        self.assertEqual(items["e2"]["lane"], "national_english_separate")
        batches = orchestrator.plan_batches(list(items.values()))
        self.assertEqual(batches, [])

    def test_basic_sciences_excluded_and_dental_lanes(self):
        items = self.build([
            row("b", "national", 1404, "basic-sciences"),
            row("r", "residency", 1400, "oral-pathology", "neville-oral-pathology@5e"),
            row("p", "residency", 1392, "oral-pathology"),
            row("n", "national", 1400, "oral-pathology", "neville-oral-pathology@5e"),
            row("u", "residency", 1400, "oral-pathology", "regezi@7e")])
        self.assertEqual(items["b"]["lane"], "national_basic_sciences_excluded")
        self.assertEqual(items["r"]["lane"], "residency_dental_ready")
        self.assertEqual(items["p"]["lane"], "residency_dental_proxy_policy")
        self.assertEqual(items["n"]["lane"], "national_dental_same_edition_ready")
        self.assertEqual(items["u"]["lane"], "residency_dental_blocked_pdf_map")

    def test_unbound_map_is_not_ready(self):
        maps = {"neville-oral-pathology@5e": {"source_pdf_sha256": "b" * 64, "runs": [["1", 1, 10]]}}
        self.assertEqual(manifest.ready_editions(["neville-oral-pathology@5e"], PDFS, maps), [])

    def test_residency_batches_first_and_idempotent_refresh(self):
        items = list(self.build([
            row("n", "national", 1400, "oral-pathology", "neville-oral-pathology@5e"),
            row("r1", "residency", 1400, "oral-pathology", "neville-oral-pathology@5e"),
            row("r2", "residency", 1400, "oral-pathology", "neville-oral-pathology@5e")]).values())
        batches = orchestrator.plan_batches(items)
        self.assertEqual(batches[0]["type"], "residency")
        again = orchestrator.plan_batches(items, completed={"r1"}, leased={"r2"})
        self.assertEqual([b["type"] for b in again], ["national"])


class Guards(unittest.TestCase):
    def test_zero_progress_hard_stop_and_quota(self):
        s = {}
        for _ in range(2):
            s = orchestrator.record_result(s, 0, None, 100.0)
        self.assertIn("hard_stop_zero_progress", orchestrator.guard(s, 100.0, 10 ** 12, 99.0))
        s = orchestrator.record_result(s, 1, 600, 100.0)
        reasons = orchestrator.guard(s, 200.0, 10 ** 12, 199.0)
        self.assertEqual(reasons, ["provider_quota_backoff"])

    def test_stale_backup_and_low_disk_block(self):
        r = orchestrator.guard({}, 5 * 3600.0, 1, 0.0)
        self.assertIn("disk_below_3GiB", r)
        self.assertIn("apply_requires_fresh_verified_backup", r)


if __name__ == "__main__":
    unittest.main()
