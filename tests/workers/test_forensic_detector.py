from __future__ import annotations

import hashlib
import shutil
import sys
import tempfile
import time
import unittest
from pathlib import Path

import fanoos_bot.forensic_detector as forensic_detector
from fanoos_bot.pdf_fingerprint import derive_fingerprint_material, file_sha256

WORKER_PATH = Path(__file__).resolve().parents[2] / "apps/workers/protected-media/worker.py"
import importlib.util
_spec = importlib.util.spec_from_file_location("fanoos_forensic_test_worker", WORKER_PATH)
worker = importlib.util.module_from_spec(_spec)
sys.modules[_spec.name] = worker
_spec.loader.exec_module(worker)

FINGERPRINT_KEY = b"forensic-test-key-32-bytes-long!"
HAS_BINARIES = bool(shutil.which("qpdf") and shutil.which("pdftoppm") and shutil.which("pdfinfo"))


def _fixture_pdf(path: Path, pages: int = 2) -> None:
    from PIL import Image, ImageDraw

    images = []
    for index in range(pages):
        image = Image.new("RGB", (1240, 1754), (255, 255, 255))
        draw = ImageDraw.Draw(image)
        draw.text((60, 60), f"Fixture page {index + 1}", fill=(0, 0, 0))
        for row in range(15):
            draw.line((60, 140 + row * 30, 1180, 140 + row * 30), fill=(220, 220, 220))
        images.append(image)
    images[0].save(path, "PDF", save_all=True, append_images=images[1:], resolution=180.0)


def _mark(source: Path, output: Path, *, job_id: str, user_id: str, resource_id: str, dpi: int = 180):
    """Marks `source` for one recipient using the real worker path
    (worker.draw_secure_raster_marks + worker.canonical_user_id), the same
    code path apps/workers/protected-media/worker.py uses in production.
    Returns the FingerprintMaterial used, so tests can assert against it.
    """
    from PIL import Image

    material = derive_fingerprint_material(
        FINGERPRINT_KEY,
        issuance_id=f"iss_{job_id}",
        user_id=worker.canonical_user_id(user_id),
        document_id=resource_id,
        source_hash=file_sha256(source),
    )
    pages = []
    with tempfile.TemporaryDirectory(prefix="fanoos-forensic-mark-") as raster_dir:
        import subprocess

        subprocess.run(["pdftoppm", "-png", "-r", str(dpi), str(source), str(Path(raster_dir) / "page")], check=True)
        for png in sorted(Path(raster_dir).glob("page-*.png")):
            image = Image.open(png).convert("RGB")
            pages.append(image)
    for page_index, image in enumerate(pages):
        worker.draw_secure_raster_marks(image, material, page_index, dpi)
    pages[0].save(output, "PDF", save_all=True, append_images=pages[1:], resolution=float(dpi))
    return material


def _candidate(job_id: str, user_id: str, resource_id: str) -> dict:
    return {"issuanceId": job_id, "userId": user_id, "documentId": resource_id, "objectId": "obj", "resourceVersionId": "v1"}


@unittest.skipUnless(HAS_BINARIES, "qpdf/pdftoppm/pdfinfo are not installed on this machine; CI verifies this end-to-end path")
class ForensicDetectorAttributionTests(unittest.TestCase):
    def _deadline(self, seconds: float = 90) -> float:
        return time.monotonic() + seconds

    def test_marks_for_recipient_a_and_detector_names_recipient_a(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            root = Path(d)
            source = root / "source.pdf"
            marked = root / "marked.pdf"
            _fixture_pdf(source)
            resource_id = "resource-attribution"
            job_id = "11111111-1111-4111-8111-111111111111"
            user_a = "aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee"
            _mark(source, marked, job_id=job_id, user_id=user_a, resource_id=resource_id)

            def fetch_original(_candidate):
                return source

            results = forensic_detector.detect(
                marked,
                candidates=[_candidate(job_id, user_a, resource_id)],
                secret=FINGERPRINT_KEY,
                fetch_original=fetch_original,
                deadline=self._deadline(),
            )
            self.assertEqual(len(results), 1, f"expected exactly one detection, got {[r.payload() for r in results]}")
            self.assertEqual(results[0].user_id, user_a)
            self.assertEqual(results[0].verdict, "attributed")
            self.assertGreaterEqual(results[0].confidence, 0.9)
            self.assertTrue(any(item.crc_valid for item in results[0].channel_results))

    def test_recipient_b_is_not_returned_when_marked_for_a(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            root = Path(d)
            source = root / "source.pdf"
            marked = root / "marked.pdf"
            _fixture_pdf(source)
            resource_id = "resource-two-recipients"
            job_a = "22222222-2222-4222-8222-222222222222"
            job_b = "33333333-3333-4333-8333-333333333333"
            user_a = "aaaaaaaa-0000-4000-8000-000000000001"
            user_b = "bbbbbbbb-0000-4000-8000-000000000002"
            _mark(source, marked, job_id=job_a, user_id=user_a, resource_id=resource_id)

            def fetch_original(_candidate):
                return source

            results = forensic_detector.detect(
                marked,
                candidates=[_candidate(job_a, user_a, resource_id), _candidate(job_b, user_b, resource_id)],
                secret=FINGERPRINT_KEY,
                fetch_original=fetch_original,
                deadline=self._deadline(),
            )
            named_users = {item.user_id for item in results}
            self.assertIn(user_a, named_users)
            self.assertNotIn(user_b, named_users)

    def test_unmarked_file_returns_no_match_not_a_low_confidence_guess(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            root = Path(d)
            source = root / "source.pdf"
            _fixture_pdf(source)
            resource_id = "resource-unmarked"
            job_a = "44444444-4444-4444-8444-444444444444"
            user_a = "cccccccc-0000-4000-8000-000000000003"

            def fetch_original(_candidate):
                return source

            # `source` itself (never marked) stands in as the "evidence".
            results = forensic_detector.detect(
                source,
                candidates=[_candidate(job_a, user_a, resource_id)],
                secret=FINGERPRINT_KEY,
                fetch_original=fetch_original,
                deadline=self._deadline(),
            )
            self.assertEqual(results, [], "an unmarked file must return no detections, not a guess")

    def test_decoded_mark_with_no_matching_candidate_reports_no_match(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            root = Path(d)
            source = root / "source.pdf"
            marked = root / "marked.pdf"
            _fixture_pdf(source)
            resource_id = "resource-mismatch"
            job_real = "55555555-5555-4555-8555-555555555555"
            job_wrong = "66666666-6666-4666-8666-666666666666"
            user_real = "dddddddd-0000-4000-8000-000000000004"
            user_wrong = "eeeeeeee-0000-4000-8000-000000000005"
            _mark(source, marked, job_id=job_real, user_id=user_real, resource_id=resource_id)

            def fetch_original(_candidate):
                return source

            # The candidate list names a *different* job/user than the one the
            # file was actually marked for -- the mark decodes against the
            # secret, but must not be attributed to a candidate it doesn't match.
            results = forensic_detector.detect(
                marked,
                candidates=[_candidate(job_wrong, user_wrong, resource_id)],
                secret=FINGERPRINT_KEY,
                fetch_original=fetch_original,
                deadline=self._deadline(),
            )
            self.assertEqual(results, [], "a mark that matches no real candidate must not be attributed to an unrelated one")

    def test_corrupt_or_non_pdf_upload_fails_cleanly_within_deadline(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            bogus = Path(d) / "bogus.pdf"
            bogus.write_bytes(b"not a pdf at all")
            with self.assertRaises(forensic_detector.ForensicDetectorError) as ctx:
                forensic_detector.detect(
                    bogus, candidates=[_candidate("job", "user", "resource")], secret=FINGERPRINT_KEY,
                    fetch_original=lambda c: bogus, deadline=self._deadline(),
                )
            self.assertEqual(ctx.exception.code, "input_unavailable")

    def test_corrupt_pdf_with_valid_magic_bytes_fails_via_qpdf_check(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            corrupt = Path(d) / "corrupt.pdf"
            corrupt.write_bytes(b"%PDF-1.4\ngarbage that is not a real pdf structure")
            with self.assertRaises(forensic_detector.ForensicDetectorError) as ctx:
                forensic_detector.detect(
                    corrupt, candidates=[_candidate("job", "user", "resource")], secret=FINGERPRINT_KEY,
                    fetch_original=lambda c: corrupt, deadline=self._deadline(),
                )
            self.assertEqual(ctx.exception.code, "input_unavailable")


class ForensicDetectorAlgorithmTests(unittest.TestCase):
    """Exercises `_secure_raster_micro_results` (the numpy-free bit-recovery
    and ECC-fusion algorithm) directly against a real mark, bypassing
    pdftoppm/qpdf entirely -- this needs only Pillow, so it runs on every
    machine and proves the core attribution logic independent of whether the
    PDF-rasterization binaries are installed. The full pdftoppm/qpdf pipeline
    is covered separately by the skip-guarded ForensicDetectorAttributionTests
    above, which CI runs for real.
    """

    @staticmethod
    def _material(job_id: str, user_id: str, resource_id: str):
        return derive_fingerprint_material(
            FINGERPRINT_KEY, issuance_id=f"iss_{job_id}",
            user_id=worker.canonical_user_id(user_id), document_id=resource_id, source_hash="0" * 64,
        )

    @staticmethod
    def _blank_page():
        from PIL import Image

        return Image.new("RGB", (1240, 1754), (255, 255, 255))

    def _diff_against_blank(self, material, page_index: int, dpi: int = 180):
        from PIL import ImageChops

        original = self._blank_page().convert("L")
        marked = self._blank_page()
        worker.draw_secure_raster_marks(marked, material, page_index, dpi)
        diff = ImageChops.difference(marked.convert("L"), original)
        page_size = (marked.width * 72.0 / dpi, marked.height * 72.0 / dpi)
        return diff, page_size

    def test_algorithm_recovers_the_exact_payload_it_marked(self) -> None:
        material = self._material("11111111-1111-4111-8111-111111111111", "aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee", "resource-x")
        analysis = self._diff_against_blank(material, 0)
        channels = forensic_detector._secure_raster_micro_results(analysis, material, 0)
        fusion = next(item for item in channels if item.name.endswith("ecc-fusion"))
        self.assertTrue(fusion.crc_valid)
        self.assertTrue(fusion.success)

    def test_algorithm_rejects_a_candidate_that_never_received_this_mark(self) -> None:
        material_a = self._material("11111111-1111-4111-8111-111111111111", "aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee", "resource-x")
        material_b = self._material("22222222-2222-4222-8222-222222222222", "bbbbbbbb-0000-4000-8000-000000000002", "resource-x")
        analysis = self._diff_against_blank(material_a, 0)
        channels = forensic_detector._secure_raster_micro_results(analysis, material_b, 0)
        self.assertFalse(any(item.crc_valid for item in channels))
        self.assertFalse(any(item.success for item in channels))

    def test_algorithm_finds_nothing_on_an_unmarked_page(self) -> None:
        from PIL import ImageChops

        material = self._material("33333333-3333-4333-8333-333333333333", "cccccccc-0000-4000-8000-000000000003", "resource-x")
        blank = self._blank_page().convert("L")
        analysis = (ImageChops.difference(blank, blank), (blank.width * 72.0 / 180, blank.height * 72.0 / 180))
        channels = forensic_detector._secure_raster_micro_results(analysis, material, 0)
        self.assertFalse(any(item.crc_valid for item in channels))
        self.assertFalse(any(item.success for item in channels))


class ForensicDetectorCachingTests(unittest.TestCase):
    """Needs no real binaries: patches out the subprocess-backed helpers to
    prove the call-count behavior directly, since real pdftoppm timing isn't
    observable without the binaries this machine doesn't have.
    """

    def test_raster_analysis_is_shared_across_candidates_with_the_same_original(self) -> None:
        from unittest import mock
        from PIL import Image

        calls = {"count": 0}

        def fake_prepare(evidence_path, original_path, page_index, dpi, deadline, work_dir, *, pdftoppm="pdftoppm"):
            calls["count"] += 1
            return Image.new("L", (100, 100), 0), (100.0, 100.0)

        with tempfile.TemporaryDirectory() as d:
            evidence = Path(d) / "evidence.pdf"
            evidence.write_bytes(b"%PDF-1.4 fake, never really opened because qpdf/pdfinfo/pdftoppm are all patched")
            original = Path(d) / "original.pdf"
            original.write_bytes(b"%PDF-1.4 fake original, same story")

            candidates = [
                _candidate("77777777-7777-4777-8777-777777777771", "aaaaaaaa-0000-4000-8000-000000000001", "resource-shared"),
                _candidate("77777777-7777-4777-8777-777777777772", "bbbbbbbb-0000-4000-8000-000000000002", "resource-shared"),
                _candidate("77777777-7777-4777-8777-777777777773", "cccccccc-0000-4000-8000-000000000003", "resource-shared"),
            ]
            for candidate in candidates:
                candidate["objectId"] = "same-object"
                candidate["resourceVersionId"] = "same-version"

            with mock.patch.object(forensic_detector, "_run", lambda *a, **k: None), \
                 mock.patch.object(forensic_detector, "_pdf_page_count", lambda *a, **k: 1), \
                 mock.patch.object(forensic_detector, "_prepare_secure_raster_analysis", fake_prepare):
                forensic_detector.detect(
                    evidence, candidates=candidates, secret=FINGERPRINT_KEY,
                    fetch_original=lambda c: original, deadline=time.monotonic() + 30,
                )
            self.assertEqual(
                calls["count"], 1,
                "three candidates sharing one (object, version, page) must rasterize/diff only once, not once per candidate",
            )


class ForensicDetectorFailClosedTests(unittest.TestCase):
    """These do not need qpdf/pdftoppm since they fail before any subprocess call."""

    def test_unset_fingerprint_key_fails_closed(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            evidence = Path(d) / "evidence.pdf"
            evidence.write_bytes(b"%PDF-1.4 irrelevant, should fail before being read")
            with self.assertRaises(forensic_detector.ForensicDetectorError) as ctx:
                forensic_detector.detect(
                    evidence, candidates=[_candidate("job", "user", "resource")], secret=b"",
                    fetch_original=lambda c: evidence, deadline=time.monotonic() + 30,
                )
            self.assertEqual(ctx.exception.code, "internal_error")

    def test_short_fingerprint_key_fails_closed(self) -> None:
        with tempfile.TemporaryDirectory() as d:
            evidence = Path(d) / "evidence.pdf"
            evidence.write_bytes(b"%PDF-1.4 irrelevant")
            with self.assertRaises(forensic_detector.ForensicDetectorError) as ctx:
                forensic_detector.detect(
                    evidence, candidates=[_candidate("job", "user", "resource")], secret=b"short",
                    fetch_original=lambda c: evidence, deadline=time.monotonic() + 30,
                )
            self.assertEqual(ctx.exception.code, "internal_error")

    def test_no_candidates_returns_empty_without_touching_the_file(self) -> None:
        # Evidence is not even a real PDF; with zero candidates detect() must
        # short-circuit before ever validating or rasterizing it.
        with tempfile.TemporaryDirectory() as d:
            evidence = Path(d) / "evidence.pdf"
            evidence.write_bytes(b"not a pdf")
            results = forensic_detector.detect(
                evidence, candidates=[], secret=FINGERPRINT_KEY,
                fetch_original=lambda c: evidence, deadline=time.monotonic() + 30,
            )
            self.assertEqual(results, [])


if __name__ == "__main__":
    unittest.main()
