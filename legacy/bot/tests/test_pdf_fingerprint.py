from __future__ import annotations

import hashlib
import os
import tempfile
import unittest
from pathlib import Path

from dent_bot.pdf_fingerprint import (
    LEGACY_WATERMARK_VERSION,
    LEGACY_MICRO_CHANNEL_VERSION,
    INTERLEAVED_MICRO_CHANNEL_VERSION,
    INTERLEAVED_WATERMARK_VERSION,
    SECURE_RASTER_CHANNEL_VERSION,
    SECURE_RASTER_WATERMARK_VERSION,
    WATERMARK_VERSION,
    WatermarkIdentity,
    _secure_page_profile,
    _secure_visible_plan,
    decode_micro_bits,
    derive_fingerprint_material,
    extract_visible_trace_from_pdf,
    micro_redundant_bits,
    watermark_pdf,
)
from dent_bot.forensic_detector import detect, verify_expected_secure_raster
from dent_bot.state import BotState


@unittest.skipUnless(__import__("importlib").util.find_spec("pymupdf"), "PyMuPDF unavailable")
class PdfFingerprintTests(unittest.TestCase):
    @staticmethod
    def _font() -> Path:
        candidates = (
            Path("dent_bot/assets/fonts/B_Nazanin_Bold.ttf"),
            Path("C:/Windows/Fonts/tahoma.ttf"),
            Path("/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"),
            Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
        )
        return next(path for path in candidates if path.is_file())

    @staticmethod
    def _legacy_font() -> Path:
        candidates = (
            Path("C:/Windows/Fonts/tahoma.ttf"),
            Path("/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"),
            Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
        )
        return next(path for path in candidates if path.is_file())

    @staticmethod
    def _source(path: Path) -> None:
        import pymupdf

        document = pymupdf.open()
        for index in range(2):
            page = document.new_page(width=595, height=842)
            page.insert_text((72, 100), f"Synthetic source page {index + 1}", fontsize=18)
            page.draw_rect(pymupdf.Rect(60, 140, 535, 780), color=(0.2, 0.3, 0.4), width=1)
            for row in range(24):
                page.insert_text(
                    (76, 165 + row * 23),
                    f"Forensic alignment feature {row + 1}: clinical reference content.",
                    fontsize=8,
                )
        document.save(path)
        document.close()

    def test_hmac_material_is_bound_and_raw_identity_is_absent(self) -> None:
        source_hash = hashlib.sha256(b"source").hexdigest()
        one = derive_fingerprint_material(
            b"k" * 32,
            issuance_id="iss_abcdefghijklmnop",
            user_id=20,
            document_id="tgdoc_example",
            source_hash=source_hash,
        )
        two = derive_fingerprint_material(
            b"k" * 32,
            issuance_id="iss_abcdefghijklmnop",
            user_id=21,
            document_id="tgdoc_example",
            source_hash=source_hash,
        )
        self.assertNotEqual(one.fingerprint_hash, two.fingerprint_hash)
        self.assertEqual(one.fingerprint_prefix, two.fingerprint_prefix)
        self.assertNotEqual(one.fingerprint_prefix, b"20")
        self.assertRegex(one.trace_code, r"^TRC-[A-Z2-7]{5}-[A-Z2-7]{5}$")
        self.assertEqual(one.watermark_version, WATERMARK_VERSION)

    def test_secure_channel_uses_page_token_repetition_and_preserves_legacy(self) -> None:
        prefix = bytes.fromhex("0011223344556677")
        bits = micro_redundant_bits(prefix)
        self.assertEqual(len(bits), 5 * 8 * 3)
        damaged = list(bits)
        damaged[0] ^= 1
        decoded, valid, corrections = decode_micro_bits(tuple(damaged))
        self.assertEqual(decoded, prefix[:5])
        self.assertTrue(valid)
        self.assertEqual(corrections, 1)
        previous_secure = micro_redundant_bits(prefix, SECURE_RASTER_WATERMARK_VERSION)
        previous_decoded, previous_valid, _ = decode_micro_bits(previous_secure, SECURE_RASTER_WATERMARK_VERSION)
        self.assertEqual(previous_decoded, prefix[:5])
        self.assertTrue(previous_valid)
        legacy = micro_redundant_bits(prefix, LEGACY_WATERMARK_VERSION)
        self.assertEqual(len(legacy), 10 * 8 * 3)
        self.assertTrue(all(legacy[index] == legacy[index + 1] == legacy[index + 2] for index in range(0, len(legacy), 3)))

    def test_pdf_watermark_is_secure_raster_without_extractable_identity(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "source.pdf"
            output = root / "output.pdf"
            self._source(source)
            source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
            material = derive_fingerprint_material(
                b"s" * 32,
                issuance_id="iss_abcdefghijklmnop",
                user_id=20,
                document_id="tgdoc_example",
                source_hash=source_hash,
            )
            report = watermark_pdf(
                source,
                output,
                identity=WatermarkIdentity("آرین آزمون", "0012345678", "09123456789"),
                material=material,
                font_path=self._font(),
                qpdf_binary="",
            )
            self.assertEqual(report["pageCount"], 2)
            self.assertEqual(report["annotationCount"], 0)
            self.assertEqual(report["pageContentStreams"], [1, 1])
            self.assertTrue(report["secureRaster"])
            self.assertEqual(report["pageImageCounts"], [1, 1])
            self.assertEqual(report["liveTextCharacters"], 0)
            self.assertEqual(extract_visible_trace_from_pdf(output), "")
            self.assertGreaterEqual(report["visibleInstances"], 6)
            self.assertLessEqual(report["visibleInstances"], 10)
            self.assertNotEqual(hashlib.sha256(source.read_bytes()).digest(), hashlib.sha256(output.read_bytes()).digest())
            import pymupdf

            with pymupdf.open(output) as document:
                text = "\n".join(page.get_text("text") for page in document)
                self.assertEqual(text.strip(), "")
                self.assertTrue(all(len(page.get_images(full=True)) == 1 for page in document))
                metadata = " ".join(str(value or "") for value in document.metadata.values())
                self.assertNotIn("0012345678", metadata)
                self.assertNotIn("09123456789", metadata)
                self.assertNotIn(material.trace_code, metadata)
                self.assertEqual(document.metadata.get("keywords"), material.digital_fingerprint_id)
            raw = output.read_bytes()
            self.assertNotIn(b"0012345678", raw)
            self.assertNotIn(b"09123456789", raw)
            self.assertNotIn(material.trace_code.encode("ascii"), raw)
            self.assertFalse(any(path.name.endswith((".pymupdf", ".normalized")) for path in root.iterdir()))

    def test_admin_detector_recovers_independent_micro_channel_from_pdf(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "source.pdf"
            output = root / "output.pdf"
            self._source(source)
            source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
            secret = b"f" * 32
            material = derive_fingerprint_material(
                secret,
                issuance_id="iss_forensicexample1",
                user_id=41,
                document_id="tgdoc_forensic",
                source_hash=source_hash,
            )
            decoy_material = derive_fingerprint_material(
                secret,
                issuance_id="iss_forensicdecoy001",
                user_id=42,
                document_id="tgdoc_forensic",
                source_hash=source_hash,
            )
            watermark_pdf(
                source,
                output,
                identity=WatermarkIdentity("کاربر آزمایشی", "0012345678", "09123456789"),
                material=material,
                font_path=self._font(),
                qpdf_binary="",
            )
            expected = verify_expected_secure_raster(
                output,
                original_pdf=source,
                secret=secret,
                issuance_id=material.issuance_id,
                user_id=41,
                document_id=material.document_id,
                source_hash=source_hash,
                watermark_version=material.watermark_version,
            )
            self.assertTrue(expected["detected"])
            self.assertGreaterEqual(expected["validEccPages"], 1)
            self.assertEqual(len(expected["perPage"]), 2)
            decoy_expected = verify_expected_secure_raster(
                output,
                original_pdf=source,
                secret=secret,
                issuance_id=decoy_material.issuance_id,
                user_id=42,
                document_id=decoy_material.document_id,
                source_hash=source_hash,
                watermark_version=decoy_material.watermark_version,
            )
            self.assertFalse(decoy_expected["detected"])
            state = BotState(root / "state.sqlite3")
            try:
                state.replace_protected_media_message(-1001234567890, 7, [{
                    "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendDocument", "fileId": "source-file",
                    "fileUniqueId": "source-unique", "fileName": "source.pdf",
                    "mimeType": "application/pdf", "caption": "test",
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )[0]["id"])
                state.create_booklet_issuance(
                    issuance_id=material.issuance_id,
                    user_id=41,
                    source_id=source_id,
                    document_id=material.document_id,
                    trace_code=material.trace_code,
                    fingerprint_hash=material.fingerprint_hash,
                    watermark_version=material.watermark_version,
                    source_hash=source_hash,
                )
                state.mark_booklet_issuance_sent(material.issuance_id, telegram_file_id="personal-file")
                results = detect(
                    output, state=state, secret=secret,
                    original_pdf=source, document_id=material.document_id,
                )
                self.assertEqual(results[0].user_id, 41)
                self.assertIn(SECURE_RASTER_CHANNEL_VERSION, results[0].channels)
                self.assertGreater(results[0].confidence, 0.98)
                self.assertEqual(results[0].verdict, "attributed")
                self.assertIn("failedChannels", results[0].payload())
                self.assertIn("evidence", results[0].payload())
                import pymupdf

                screenshot = root / "screenshot.png"
                with pymupdf.open(output) as document:
                    document[0].get_pixmap(matrix=pymupdf.Matrix(300 / 72, 300 / 72), alpha=False).save(screenshot)
                image_results = detect(
                    screenshot,
                    state=state,
                    secret=secret,
                    original_pdf=source,
                    document_id=material.document_id,
                )
                self.assertTrue(image_results)
                self.assertEqual(image_results[0].user_id, 41)
                self.assertIn(SECURE_RASTER_CHANNEL_VERSION, image_results[0].channels)
                if __import__("importlib").util.find_spec("cv2"):
                    import cv2
                    import numpy as np

                    image = cv2.imread(str(screenshot), cv2.IMREAD_COLOR)
                    self.assertIsNotNone(image)
                    assert image is not None
                    height, width = image.shape[:2]
                    variants: list[Path] = []
                    jpeg = root / "compressed.jpg"
                    cv2.imwrite(str(jpeg), image, [cv2.IMWRITE_JPEG_QUALITY, 48])
                    variants.append(jpeg)
                    cropped = root / "cropped.jpg"
                    crop = image[int(height * 0.025):int(height * 0.975), int(width * 0.025):int(width * 0.975)]
                    cv2.imwrite(str(cropped), cv2.resize(crop, (width, height)), [cv2.IMWRITE_JPEG_QUALITY, 65])
                    variants.append(cropped)
                    rotated = root / "rotated.jpg"
                    rotation = cv2.getRotationMatrix2D((width / 2, height / 2), 2.2, 0.98)
                    rotated_image = cv2.warpAffine(image, rotation, (width, height), borderValue=(255, 255, 255))
                    cv2.imwrite(str(rotated), rotated_image, [cv2.IMWRITE_JPEG_QUALITY, 70])
                    variants.append(rotated)
                    perspective = root / "perspective.jpg"
                    source_points = np.float32([[0, 0], [width - 1, 0], [width - 1, height - 1], [0, height - 1]])
                    target_points = np.float32([
                        [width * 0.025, height * 0.015], [width * 0.97, 0],
                        [width - 1, height * 0.975], [0, height - 1],
                    ])
                    warped = cv2.warpPerspective(
                        image,
                        cv2.getPerspectiveTransform(source_points, target_points),
                        (width, height),
                        borderValue=(255, 255, 255),
                    )
                    cv2.imwrite(str(perspective), warped, [cv2.IMWRITE_JPEG_QUALITY, 70])
                    variants.append(perspective)
                    for variant in variants:
                        variant_results = detect(
                            variant,
                            state=state,
                            secret=secret,
                            original_pdf=source,
                            document_id=material.document_id,
                        )
                        from dent_bot.forensic_detector import _image_hidden_score, _prepare_image_analysis

                        analysis = _prepare_image_analysis(variant, source, 0)
                        def candidate(value, user_id):
                            return {
                                "issuanceId": value.issuance_id,
                                "userId": user_id,
                                "documentId": value.document_id,
                                "sourceHash": source_hash,
                                "watermarkVersion": value.watermark_version,
                            }
                        actual_score = _image_hidden_score(analysis, candidate(material, 41), secret, 0)
                        decoy_score = _image_hidden_score(analysis, candidate(decoy_material, 42), secret, 0)
                        self.assertGreaterEqual(actual_score, 0.55, f"{variant.name}: actual={actual_score:.4f}")
                        self.assertLess(decoy_score, 0.82, f"{variant.name}: decoy={decoy_score:.4f}")
                        self.assertGreater(actual_score, decoy_score, variant.name)
                        self.assertTrue(variant_results, f"{variant.name}: hiddenScore={actual_score:.4f}")
                        self.assertEqual(variant_results[0].user_id, 41, variant.name)
                        if SECURE_RASTER_CHANNEL_VERSION in variant_results[0].channels:
                            self.assertIn(SECURE_RASTER_CHANNEL_VERSION, variant_results[0].channels, variant.name)
                        else:
                            self.assertIn(variant_results[0].verdict, {"candidate", "inconclusive"}, variant.name)
                            self.assertFalse(
                                any(item.crc_valid for item in variant_results[0].channel_results),
                                variant.name,
                            )
            finally:
                state.close()

    def test_image_heavy_compact_trace_is_outside_central_roi(self) -> None:
        import pymupdf

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "image-page.pdf"
            samples = bytes([96, 96, 96]) * (320 * 420)
            pixmap = pymupdf.Pixmap(pymupdf.csRGB, 320, 420, samples, False)
            with pymupdf.open() as document:
                page = document.new_page(width=595, height=842)
                page.insert_text((52, 60), "Diagnostic image", fontsize=18)
                page.insert_image(pymupdf.Rect(62, 92, 533, 710), stream=pixmap.tobytes("jpeg", jpg_quality=85))
                page.insert_text((62, 740), "Figure caption", fontsize=9)
                document.save(source)
            with pymupdf.open(source) as document:
                profile = _secure_page_profile(document[0], 1)
            material = derive_fingerprint_material(
                b"p" * 32,
                issuance_id="iss_roi_abcdefghijkl",
                user_id=77,
                document_id="tgdoc_roi",
                source_hash=hashlib.sha256(source.read_bytes()).hexdigest(),
            )
            full_marks, compact_marks = _secure_visible_plan(profile, material, 1)
            self.assertEqual(profile["profile"], "image-heavy")
            self.assertEqual(len(full_marks), 3)
            self.assertEqual(len(compact_marks), 1)
            self.assertEqual(compact_marks[0]["roiOverlap"], 0.0)
            self.assertIn("periphery", compact_marks[0]["region"])

    def test_secure_raster_preserves_page_rotation_and_display_geometry(self) -> None:
        import pymupdf

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source, output = root / "rotated-source.pdf", root / "rotated-output.pdf"
            with pymupdf.open() as document:
                for rotation in (0, 90, 180, 270):
                    page = document.new_page(width=595, height=842)
                    page.insert_text((72, 100), f"rotation {rotation}")
                    page.set_rotation(rotation)
                document.save(source)
            material = derive_fingerprint_material(
                b"r" * 32,
                issuance_id="iss_rotationregression01",
                user_id=61,
                document_id="tgdoc_rotation",
                source_hash=hashlib.sha256(source.read_bytes()).hexdigest(),
            )
            watermark_pdf(
                source, output,
                identity=WatermarkIdentity("کاربر چرخش", "0012345678", "09123456789"),
                material=material, font_path=self._font(), qpdf_binary="",
            )
            with pymupdf.open(source) as original, pymupdf.open(output) as personalized:
                self.assertEqual(
                    [page.rotation for page in personalized],
                    [page.rotation for page in original],
                )
                self.assertEqual(
                    [(round(page.rect.width, 3), round(page.rect.height, 3)) for page in personalized],
                    [(round(page.rect.width, 3), round(page.rect.height, 3)) for page in original],
                )
                self.assertTrue(all(len(page.get_images(full=True)) == 1 for page in personalized))

    def test_detector_keeps_v1_compatibility_and_hidden_only_disambiguates_candidates(self) -> None:
        import pymupdf
        from dent_bot.pdf_fingerprint import _place_micro_channel

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "source.pdf"
            legacy_output = root / "legacy.pdf"
            hidden_only = root / "hidden-only.pdf"
            self._source(source)
            source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
            secret = b"g" * 32
            legacy = derive_fingerprint_material(
                secret,
                issuance_id="iss_legacyforensic001",
                user_id=51,
                document_id="tgdoc_legacy",
                source_hash=source_hash,
                watermark_version=LEGACY_WATERMARK_VERSION,
            )
            current = derive_fingerprint_material(
                secret,
                issuance_id="iss_hiddenforensic001",
                user_id=52,
                document_id="tgdoc_hidden",
                source_hash=source_hash,
                watermark_version=INTERLEAVED_WATERMARK_VERSION,
            )
            decoy = derive_fingerprint_material(
                secret,
                issuance_id="iss_hiddenforensic002",
                user_id=53,
                document_id="tgdoc_hidden",
                source_hash=source_hash,
                watermark_version=INTERLEAVED_WATERMARK_VERSION,
            )
            watermark_pdf(
                source,
                legacy_output,
                identity=WatermarkIdentity("کاربر قدیمی", "0012345678", "09123456789"),
                material=legacy,
                font_path=self._legacy_font(),
                qpdf_binary="",
            )
            with pymupdf.open(source) as document:
                for page_index, page in enumerate(document):
                    _place_micro_channel(page, current, page_index)
                document.save(hidden_only, garbage=4, clean=True, deflate=True, use_objstms=1)
            state = BotState(root / "state.sqlite3")
            try:
                state.replace_protected_media_message(-1001234567890, 8, [{
                    "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendDocument", "fileId": "source-file",
                    "fileUniqueId": "source-unique", "fileName": "source.pdf",
                    "mimeType": "application/pdf", "caption": "test",
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )[0]["id"])
                for material in (legacy, current, decoy):
                    state.create_booklet_issuance(
                        issuance_id=material.issuance_id,
                        user_id=51 if material is legacy else 52 if material is current else 53,
                        source_id=source_id,
                        document_id=material.document_id,
                        trace_code=material.trace_code,
                        fingerprint_hash=material.fingerprint_hash,
                        watermark_version=material.watermark_version,
                        source_hash=source_hash,
                    )
                    state.mark_booklet_issuance_sent(material.issuance_id, telegram_file_id="personal-file")
                legacy_results = detect(legacy_output, state=state, secret=secret)
                self.assertEqual(legacy_results[0].user_id, 51)
                self.assertIn(LEGACY_MICRO_CHANNEL_VERSION, legacy_results[0].channels)
                hidden_results = detect(
                    hidden_only,
                    state=state,
                    secret=secret,
                    document_id=current.document_id,
                )
                self.assertEqual([result.user_id for result in hidden_results], [52])
                self.assertEqual(hidden_results[0].verdict, "attributed")
                self.assertIn("visible-trace", hidden_results[0].failed_channels)
                self.assertIn(INTERLEAVED_MICRO_CHANNEL_VERSION, hidden_results[0].channels)
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
