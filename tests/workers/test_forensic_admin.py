from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot import forensic_admin
from fanoos_bot.forensic_detector import ChannelResult, Detection


class FakeApi:
    """Mirrors the real contract: media_forensic_source takes only a job_id
    (issuanceId) -- the platform derives object/version/classification from
    that job's own row, never from caller-supplied values.
    """

    def __init__(self, candidates: list[dict], sources_by_job: dict[str, bytes]):
        self._candidates = candidates
        self._sources_by_job = sources_by_job
        self.candidate_calls = 0
        self.source_calls: list[str] = []

    def media_forensic_candidates(self, platform, subject, workspace_id, resource_id):
        self.candidate_calls += 1
        return {"candidates": self._candidates}

    def media_forensic_source(self, platform, subject, workspace_id, job_id, max_bytes):
        self.source_calls.append(job_id)
        return self._sources_by_job[job_id]


class ForensicAdminTests(unittest.TestCase):
    def test_normalize_candidate_drops_incomplete_rows(self) -> None:
        self.assertIsNone(forensic_admin._normalize_candidate({"issuance_id": "job"}))
        self.assertIsNone(forensic_admin._normalize_candidate("not-a-dict"))
        full = {
            "issuance_id": "job-1", "user_id": "user-1", "document_id": "resource-1",
            "object_id": "object-1", "resource_version_id": "version-1", "classification": "private",
        }
        normalized = forensic_admin._normalize_candidate(full)
        self.assertEqual(normalized, {
            "issuanceId": "job-1", "userId": "user-1", "documentId": "resource-1",
            "objectId": "object-1", "resourceVersionId": "version-1", "classification": "private",
        })

    def test_investigate_fetches_each_distinct_original_only_once(self) -> None:
        candidates = [
            {"issuance_id": "job-1", "user_id": "user-1", "document_id": "resource-1", "object_id": "object-a", "resource_version_id": "version-1", "classification": "private"},
            {"issuance_id": "job-2", "user_id": "user-2", "document_id": "resource-1", "object_id": "object-a", "resource_version_id": "version-1", "classification": "private"},
        ]
        api = FakeApi(candidates, {"job-1": b"%PDF-1.4 not a real pdf but never opened in this fake"})
        with tempfile.TemporaryDirectory() as d:
            evidence = Path(d) / "evidence.pdf"
            evidence.write_bytes(b"not actually validated in this test")
            captured = {}

            def fake_detect(evidence_path, *, candidates, secret, fetch_original, deadline):
                captured["candidates"] = candidates
                # exercise the caching fetch_original exactly like detect() would
                first = fetch_original(candidates[0])
                second = fetch_original(candidates[1])
                self.assertEqual(first, second, "same (object, version) must resolve to the same cached path")
                return []

            original_detect = forensic_admin.detect
            forensic_admin.detect = fake_detect
            try:
                forensic_admin.investigate(
                    api, platform="bale", subject="12345", workspace_id="workspace-1",
                    resource_id="resource-1", evidence_path=evidence, secret=b"k" * 32, deadline=0.0,
                )
            finally:
                forensic_admin.detect = original_detect
            self.assertEqual(api.candidate_calls, 1)
            self.assertEqual(len(api.source_calls), 1, "the source endpoint must be called once per distinct (object, version), not once per candidate")
            self.assertEqual(len(captured["candidates"]), 2)

    def test_format_result_fa_no_candidates_differs_from_clean_file(self) -> None:
        no_candidates = forensic_admin.format_result_fa(
            forensic_admin.InvestigationOutcome(candidates_found=0, detections=())
        )
        no_match = forensic_admin.format_result_fa(
            forensic_admin.InvestigationOutcome(candidates_found=2, detections=())
        )
        self.assertNotEqual(no_candidates, no_match, "no deliveries to compare against must read differently from a clean file")
        self.assertIn("هیچ دریافت‌کننده‌ای", no_candidates)
        self.assertIn("هیچ نشانه‌ای", no_match)
        self.assertNotIn("هیچ نشانه‌ای", no_candidates)
        self.assertNotIn("هیچ دریافت‌کننده‌ای", no_match)

    def test_format_result_fa_distinguishes_no_match_single_and_multi_match(self) -> None:
        no_match = forensic_admin.format_result_fa(
            forensic_admin.InvestigationOutcome(candidates_found=1, detections=())
        )
        self.assertIn("هیچ نشانه‌ای", no_match)

        single = Detection(
            issuance_id="job-1", user_id="user-1", document_id="resource-1", confidence=0.98,
            successful_channels=("raster-constellation-repetition3-v1-ecc-fusion",), failed_channels=(),
            channel_results=(ChannelResult("x", True, 1.0, 120, 120, "valid", True),),
            evidence={}, verdict="attributed", watermark_version="recipient-pdf-v9",
        )
        single_text = forensic_admin.format_result_fa(
            forensic_admin.InvestigationOutcome(candidates_found=1, detections=(single,))
        )
        self.assertIn("user-1", single_text)
        self.assertIn("قطعی", single_text)
        self.assertNotIn("هشدار", single_text, "a single confident attribution must not be flagged as contradictory")

        other = Detection(
            issuance_id="job-2", user_id="user-2", document_id="resource-1", confidence=0.75,
            successful_channels=("raster-constellation-repetition3-v1-copy-1",), failed_channels=(),
            channel_results=(ChannelResult("y", True, 0.8, 90, 120, "partial", False),),
            evidence={}, verdict="candidate", watermark_version="recipient-pdf-v9",
        )
        multi_text = forensic_admin.format_result_fa(
            forensic_admin.InvestigationOutcome(candidates_found=2, detections=(single, other))
        )
        self.assertIn("user-1", multi_text)
        self.assertIn("user-2", multi_text)
        self.assertNotIn("هشدار", multi_text, "a single attributed result plus a mere candidate is not a contradiction")

    def test_format_result_fa_multi_attribution_is_a_warning_banner_at_the_top(self) -> None:
        first = Detection(
            issuance_id="job-1", user_id="user-1", document_id="resource-1", confidence=0.97,
            successful_channels=("raster-constellation-repetition3-v1-ecc-fusion",), failed_channels=(),
            channel_results=(ChannelResult("x", True, 1.0, 120, 120, "valid", True),),
            evidence={}, verdict="attributed", watermark_version="recipient-pdf-v9",
        )
        second = Detection(
            issuance_id="job-2", user_id="user-2", document_id="resource-1", confidence=0.95,
            successful_channels=("raster-constellation-repetition3-v1-ecc-fusion",), failed_channels=(),
            channel_results=(ChannelResult("x", True, 1.0, 120, 120, "valid", True),),
            evidence={}, verdict="attributed", watermark_version="recipient-pdf-v9",
        )
        text = forensic_admin.format_result_fa(
            forensic_admin.InvestigationOutcome(candidates_found=2, detections=(first, second))
        )
        self.assertIn("هشدار", text)
        self.assertLess(
            text.index("هشدار"), text.index("user-1"),
            "the contradiction warning must appear before any attributed name, not as a trailing footnote",
        )
        self.assertIn("user-1", text)
        self.assertIn("user-2", text)


if __name__ == "__main__":
    unittest.main()
