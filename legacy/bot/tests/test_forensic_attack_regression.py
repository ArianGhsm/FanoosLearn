from __future__ import annotations

import hashlib
import tempfile
import unittest
from pathlib import Path

from dent_bot.forensic_detector import detect
from dent_bot.pdf_fingerprint import (
    APPEND_HARDENED_WATERMARK_VERSION,
    CONTENT_TEXT_CHANNEL_VERSION,
    INTERLEAVED_WATERMARK_VERSION,
    PREVIOUS_MICRO_CHANNEL_VERSION,
    PREVIOUS_WATERMARK_VERSION,
    SECURE_RASTER_V8_WATERMARK_VERSION,
    SECURE_RASTER_WATERMARK_VERSION,
    SPREAD_WATERMARK_VERSION,
    WATERMARK_VERSION,
    WatermarkIdentity,
    derive_fingerprint_material,
    watermark_pdf,
)
from dent_bot.state import BotState
from scripts.forensic_attack_suite import strip_security_streams_by_original_structure


@unittest.skipUnless(__import__("importlib").util.find_spec("pymupdf"), "PyMuPDF unavailable")
class ForensicAttackRegressionTests(unittest.TestCase):
    secret = b"forensic-regression-test-secret!"

    @staticmethod
    def _font() -> Path:
        return next(path for path in (
            Path("dent_bot/assets/fonts/B_Nazanin_Bold.ttf"),
            Path("C:/Windows/Fonts/tahoma.ttf"),
            Path("/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"),
            Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
        ) if path.is_file())

    @staticmethod
    def _source(path: Path) -> None:
        import pymupdf

        stamp = pymupdf.open()
        stamp_page = stamp.new_page(width=120, height=40)
        stamp_page.insert_text((8, 24), "shared source form", fontsize=8)
        with pymupdf.open() as document:
            for page_number in range(2):
                page = document.new_page(width=595, height=842)
                page.show_pdf_page(pymupdf.Rect(430, 20, 550, 60), stamp)
                page.insert_text((52, 70), f"Attack fixture page {page_number + 1}", fontsize=16)
                for row in range(70):
                    page.insert_text((52, 105 + row * 10), f"Alignment line {row + 1} with deterministic text", fontsize=8)
            document.save(path)
        stamp.close()

    @staticmethod
    def _state(root: Path, materials, source_hash: str) -> BotState:
        state = BotState(root / "state.sqlite3")
        state.replace_protected_media_message(-1001234567890, 55, [{
            "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
            "courseTag": "ent", "term": 7, "sessionNo": 4,
            "telegramMethod": "sendDocument", "fileId": "source", "fileUniqueId": "unique",
            "fileName": "source.pdf", "mimeType": "application/pdf", "caption": "test",
        }])
        source_id = int(state.protected_media_for(course_code="ENT", term=7, session_no=4, content_kind="booklet")[0]["id"])
        for user_id, material in materials:
            state.create_booklet_issuance(
                issuance_id=material.issuance_id, user_id=user_id, source_id=source_id,
                document_id=material.document_id, trace_code=material.trace_code,
                fingerprint_hash=material.fingerprint_hash, watermark_version=material.watermark_version,
                source_hash=source_hash,
            )
            state.mark_booklet_issuance_sent(material.issuance_id, telegram_file_id=f"personal-{user_id}")
        return state

    def test_v6_interleaved_content_survives_original_guided_stream_attack(self) -> None:
        import pymupdf

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source, output = root / "source.pdf", root / "current.pdf"
            self._source(source)
            source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
            material = derive_fingerprint_material(
                self.secret, issuance_id="iss_attackregression001", user_id=801,
                document_id="tgdoc_attack", source_hash=source_hash,
                watermark_version=INTERLEAVED_WATERMARK_VERSION,
            )
            watermark_pdf(
                source, output, identity=WatermarkIdentity("کاربر تست", "0012345678", "09123456789"),
                material=material, font_path=self._font(), qpdf_binary="",
            )
            state = self._state(root, [(801, material)], source_hash)
            try:
                with pymupdf.open(output) as document:
                    self.assertTrue(all(len(page.get_contents()) == 1 for page in document))
                    self.assertTrue(all(
                        b"/DentV5" in document.xref_stream(page.get_contents()[0])
                        for page in document
                    ))
                source_only = root / "source-operators-only.pdf"
                attack = strip_security_streams_by_original_structure(source, output, source_only)
                self.assertEqual(attack["matchedSourceChunks"], attack["originalStreams"])
                source_only_match = detect(
                    source_only, state=state, secret=self.secret,
                    original_pdf=source, document_id=material.document_id,
                )[0]
                self.assertEqual(source_only_match.verdict, "attributed")
                self.assertTrue(any(
                    item.name == CONTENT_TEXT_CHANNEL_VERSION and item.crc_valid
                    for item in source_only_match.channel_results
                ))

                with pymupdf.open(output) as document:
                    touched = set()
                    for page in document:
                        for item in page.get_xobjects():
                            xref = int(item[0])
                            if xref not in touched:
                                document.update_stream(xref, b"q Q")
                                touched.add(xref)
                    xobject = root / "xobject.pdf"
                    document.save(xobject, garbage=4, clean=False, deflate=True)
                xobject_match = detect(xobject, state=state, secret=self.secret, document_id=material.document_id)[0]
                self.assertIn("visible-direct-trace", xobject_match.channels)
                payload = xobject_match.payload()
                self.assertIn("channelResults", payload)
                self.assertTrue(all("recoveredSymbols" in item and "eccStatus" in item for item in payload["channelResults"]))

                stripped = root / "recipient-streams-removed.pdf"
                strip_security_streams_by_original_structure(source, output, stripped)
                stripped_match = detect(
                    stripped, state=state, secret=self.secret,
                    original_pdf=source, document_id=material.document_id,
                )[0]
                content = next(
                    item for item in stripped_match.channel_results
                    if item.name == CONTENT_TEXT_CHANNEL_VERSION
                )
                self.assertTrue(content.crc_valid)
                self.assertEqual(content.ecc_status, "valid")
            finally:
                state.close()

    def test_detector_preserves_v2_decoder(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source, output = root / "source.pdf", root / "previous.pdf"
            self._source(source)
            source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
            material = derive_fingerprint_material(
                self.secret, issuance_id="iss_previousregression1", user_id=802,
                document_id="tgdoc_previous", source_hash=source_hash,
                watermark_version=PREVIOUS_WATERMARK_VERSION,
            )
            watermark_pdf(
                source, output, identity=WatermarkIdentity("کاربر تست", "0012345678", "09123456789"),
                material=material, font_path=self._font(), qpdf_binary="",
            )
            state = self._state(root, [(802, material)], source_hash)
            try:
                result = detect(output, state=state, secret=self.secret)[0]
                self.assertEqual(result.watermark_version, PREVIOUS_WATERMARK_VERSION)
                self.assertIn(PREVIOUS_MICRO_CHANNEL_VERSION, result.channels)
                self.assertTrue(any(item.crc_valid for item in result.channel_results))
            finally:
                state.close()

    def test_detector_preserves_v3_decoder(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source, output = root / "source.pdf", root / "v3.pdf"
            self._source(source)
            source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
            material = derive_fingerprint_material(
                self.secret, issuance_id="iss_v3regression000001", user_id=803,
                document_id="tgdoc_v3", source_hash=source_hash,
                watermark_version=SPREAD_WATERMARK_VERSION,
            )
            watermark_pdf(
                source, output, identity=WatermarkIdentity("کاربر تست", "0012345678", "09123456789"),
                material=material, font_path=self._font(), qpdf_binary="",
            )
            state = self._state(root, [(803, material)], source_hash)
            try:
                result = detect(output, state=state, secret=self.secret)[0]
                self.assertEqual(result.watermark_version, SPREAD_WATERMARK_VERSION)
                self.assertTrue(any(item.crc_valid for item in result.channel_results))
            finally:
                state.close()

    def test_detector_preserves_v5_decoder(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source, output = root / "source.pdf", root / "v5.pdf"
            self._source(source)
            source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
            material = derive_fingerprint_material(
                self.secret, issuance_id="iss_v5regression000001", user_id=804,
                document_id="tgdoc_v5", source_hash=source_hash,
                watermark_version=APPEND_HARDENED_WATERMARK_VERSION,
            )
            watermark_pdf(
                source, output, identity=WatermarkIdentity("کاربر تست", "0012345678", "09123456789"),
                material=material, font_path=self._font(), qpdf_binary="",
            )
            state = self._state(root, [(804, material)], source_hash)
            try:
                result = detect(
                    output, state=state, secret=self.secret,
                    original_pdf=source, document_id=material.document_id,
                )[0]
                self.assertEqual(result.watermark_version, APPEND_HARDENED_WATERMARK_VERSION)
                self.assertTrue(any(item.crc_valid for item in result.channel_results))
            finally:
                state.close()

    def test_current_version_is_v9_and_previous_versions_remain_supported(self) -> None:
        self.assertEqual(WATERMARK_VERSION, "recipient-pdf-v9")
        self.assertEqual(SECURE_RASTER_V8_WATERMARK_VERSION, "recipient-pdf-v8")
        self.assertEqual(SECURE_RASTER_WATERMARK_VERSION, "recipient-pdf-v7")
        self.assertEqual(INTERLEAVED_WATERMARK_VERSION, "recipient-pdf-v6")


if __name__ == "__main__":
    unittest.main()
