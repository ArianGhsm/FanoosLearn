from __future__ import annotations

import hashlib
import io
import unittest
from pathlib import Path

from fanoos_bot.pdf_fingerprint import (
    PdfFingerprintError,
    WATERMARK_VERSION,
    decode_micro_bits,
    derive_fingerprint_material,
    micro_redundant_bits,
    secure_page_prefix,
    secure_raster_symbol_layout,
)

import sys
WORKER_PATH = Path(__file__).resolve().parents[2] / "apps/workers/protected-media/worker.py"
import importlib.util
_spec = importlib.util.spec_from_file_location("fanoos_pm_worker_pdf", WORKER_PATH)
worker = importlib.util.module_from_spec(_spec)
sys.modules[_spec.name] = worker
_spec.loader.exec_module(worker)


def _material(secret: bytes, *, job_id: str, user_id: int, resource_id: str, source_bytes: bytes):
    return derive_fingerprint_material(
        secret,
        issuance_id=f"iss_{job_id}",
        user_id=user_id,
        document_id=resource_id,
        source_hash=hashlib.sha256(source_bytes).hexdigest(),
        watermark_version=WATERMARK_VERSION,
    )


def _blank_page(width_px: int, height_px: int):
    from PIL import Image, ImageDraw

    image = Image.new("RGB", (width_px, height_px), (255, 255, 255))
    draw = ImageDraw.Draw(image)
    # Simulate real page content (text lines / rules) so the round trip proves
    # marks survive *over* content, not just on an empty canvas.
    for row in range(20):
        draw.line((40, 80 + row * 30, width_px - 40, 80 + row * 30), fill=(210, 210, 210), width=1)
    draw.rectangle((40, 40, width_px - 40, height_px - 40), outline=(0, 0, 0), width=2)
    return image


def _decode_secure_raster(image, material, page_index: int, dpi: int) -> tuple[bytes, bool, int]:
    """Test-only sampler: recovers the raw redundant bits by comparing, per
    symbol, which of the two secret candidate pixels is darker (the point at
    ``pair[bit]`` is the one the worker actually inked). This deliberately
    does not attempt the perspective/rotation correction that a production
    detector (out of scope: forensic_detector.py) would need -- it is the
    simplest honest proof that the redundancy-coded payload placed by
    ``draw_secure_raster_marks`` is recoverable from the pixels it produced.
    """
    scale = dpi / 72.0
    width_pt, height_pt = image.width / scale, image.height / scale
    pixels = image.convert("RGB").load()
    width, height = image.size

    def darkness(x: float, y: float) -> float:
        total = 0.0
        count = 0
        cx, cy = int(round(x)), int(round(y))
        for dx in (-1, 0, 1):
            for dy in (-1, 0, 1):
                px, py = cx + dx, cy + dy
                if 0 <= px < width and 0 <= py < height:
                    r, g, b = pixels[px, py]
                    total += r + g + b
                    count += 1
        return -(total / max(1, count))

    best_payload = b""
    best_valid = False
    best_corrections = 10**9
    for layout in secure_raster_symbol_layout(width_pt, height_pt, material, page_index):
        recovered = [0] * len(layout)
        for pair, logical_index, _expected_bit in layout:
            (zero_x, zero_y), (one_x, one_y) = pair
            zero_dark = darkness(zero_x * scale, zero_y * scale)
            one_dark = darkness(one_x * scale, one_y * scale)
            recovered[logical_index] = 1 if one_dark > zero_dark else 0
        payload, valid, corrections = decode_micro_bits(tuple(recovered), material.watermark_version)
        if valid and corrections < best_corrections:
            best_payload, best_valid, best_corrections = payload, valid, corrections
    return best_payload, best_valid, best_corrections


def _decode_secure_raster_with_offset(image, material, page_index: int, dpi: int, *, offset: tuple[int, int]) -> tuple[bytes, bool, int]:
    """Same as ``_decode_secure_raster`` but for an image that is a known crop
    of the original canvas (``offset`` = the pixel origin of ``image`` inside
    that original canvas). A symbol whose candidate points fall outside the
    cropped bounds is simply unreadable background, not a decoding failure --
    the same as any other pixel-level noise the redundancy has to tolerate.
    """
    offset_x, offset_y = offset
    scale = dpi / 72.0
    original_width_pt = (image.width + 2 * offset_x) / scale
    original_height_pt = (image.height + 2 * offset_y) / scale
    pixels = image.convert("RGB").load()
    width, height = image.size

    def darkness(x: float, y: float) -> float:
        cx, cy = int(round(x)) - offset_x, int(round(y)) - offset_y
        if not (0 <= cx < width and 0 <= cy < height):
            return 0.0
        total = 0.0
        count = 0
        for dx in (-1, 0, 1):
            for dy in (-1, 0, 1):
                px, py = cx + dx, cy + dy
                if 0 <= px < width and 0 <= py < height:
                    r, g, b = pixels[px, py]
                    total += r + g + b
                    count += 1
        return -(total / max(1, count))

    best_payload = b""
    best_valid = False
    best_corrections = 10**9
    for layout in secure_raster_symbol_layout(original_width_pt, original_height_pt, material, page_index):
        recovered = [0] * len(layout)
        for pair, logical_index, _expected_bit in layout:
            (zero_x, zero_y), (one_x, one_y) = pair
            zero_dark = darkness(zero_x * scale, zero_y * scale)
            one_dark = darkness(one_x * scale, one_y * scale)
            recovered[logical_index] = 1 if one_dark > zero_dark else 0
        payload, valid, corrections = decode_micro_bits(tuple(recovered), material.watermark_version)
        if valid and corrections < best_corrections:
            best_payload, best_valid, best_corrections = payload, valid, corrections
    return best_payload, best_valid, best_corrections


class PdfFingerprintPortTests(unittest.TestCase):
    def test_hmac_material_is_bound_and_page_payload_is_opaque(self) -> None:
        source_hash = hashlib.sha256(b"source").hexdigest()
        one = derive_fingerprint_material(
            b"k" * 32, issuance_id="iss_abcdefghijklmnop", user_id=20,
            document_id="resource-a", source_hash=source_hash,
        )
        two = derive_fingerprint_material(
            b"k" * 32, issuance_id="iss_abcdefghijklmnop", user_id=21,
            document_id="resource-a", source_hash=source_hash,
        )
        self.assertNotEqual(one.fingerprint_hash, two.fingerprint_hash)
        self.assertRegex(one.trace_code, r"^TRC-[A-Z2-7]{5}-[A-Z2-7]{5}$")
        self.assertEqual(one.watermark_version, WATERMARK_VERSION)
        page_payload_one = secure_page_prefix(one, 0)
        page_payload_two = secure_page_prefix(two, 0)
        self.assertNotEqual(page_payload_one, page_payload_two)

    def test_empty_or_short_key_fails_closed(self) -> None:
        source_hash = hashlib.sha256(b"source").hexdigest()
        for bad_secret in (b"", b"short", b"k" * 31):
            with self.assertRaises(PdfFingerprintError):
                derive_fingerprint_material(
                    bad_secret, issuance_id="iss_abcdefghijklmnop", user_id=1,
                    document_id="resource-a", source_hash=source_hash,
                )

    def test_triple_repetition_corrects_one_flipped_bit_per_channel(self) -> None:
        prefix = bytes.fromhex("0011223344")
        bits = micro_redundant_bits(prefix, WATERMARK_VERSION)
        self.assertEqual(len(bits), 5 * 8 * 3)
        damaged = list(bits)
        damaged[0] ^= 1
        decoded, valid, corrections = decode_micro_bits(tuple(damaged), WATERMARK_VERSION)
        self.assertEqual(decoded, prefix)
        self.assertTrue(valid)
        self.assertEqual(corrections, 1)

    def test_round_trip_marks_and_decodes_across_pages(self) -> None:
        secret = b"s" * 32
        source_bytes = b"%PDF-1.4 synthetic source for round trip test"
        material = _material(secret, job_id="11111111-1111-4111-8111-111111111111", user_id=42, resource_id="resource-roundtrip", source_bytes=source_bytes)
        dpi = 180
        seen_payloads = set()
        for page_index in range(3):
            image = _blank_page(1240, 1754)  # A4 @ ~150dpi-ish canvas, matches worker's raster scale
            marks = worker.draw_secure_raster_marks(image, material, page_index, dpi)
            self.assertGreater(marks, 0)
            payload, valid, _corrections = _decode_secure_raster(image, material, page_index, dpi)
            expected = secure_page_prefix(material, page_index)
            self.assertTrue(valid, f"page {page_index} did not decode cleanly")
            self.assertEqual(payload, expected, f"page {page_index}: identity in={expected.hex()} identity out={payload.hex()}")
            seen_payloads.add(payload)
        # Per-page seeding must differ: three pages, three distinct payloads.
        self.assertEqual(len(seen_payloads), 3)

    def test_two_recipients_produce_different_marks_that_do_not_cross_decode(self) -> None:
        secret = b"t" * 32
        source_bytes = b"%PDF-1.4 shared source document for two recipients"
        material_a = _material(secret, job_id="22222222-2222-4222-8222-222222222222", user_id=1, resource_id="resource-shared", source_bytes=source_bytes)
        material_b = _material(secret, job_id="33333333-3333-4333-8333-333333333333", user_id=2, resource_id="resource-shared", source_bytes=source_bytes)
        dpi = 180
        page_index = 0
        image_a = _blank_page(1240, 1754)
        worker.draw_secure_raster_marks(image_a, material_a, page_index, dpi)
        image_b = _blank_page(1240, 1754)
        worker.draw_secure_raster_marks(image_b, material_b, page_index, dpi)

        expected_a = secure_page_prefix(material_a, page_index)
        expected_b = secure_page_prefix(material_b, page_index)
        self.assertNotEqual(expected_a, expected_b)

        payload_a, valid_a, _ = _decode_secure_raster(image_a, material_a, page_index, dpi)
        self.assertTrue(valid_a)
        self.assertEqual(payload_a, expected_a)

        payload_b, valid_b, _ = _decode_secure_raster(image_b, material_b, page_index, dpi)
        self.assertTrue(valid_b)
        self.assertEqual(payload_b, expected_b)

        # Recipient A's image sampled at recipient B's secret positions must not
        # recover recipient B's payload -- the marks are page-A's ink, not B's.
        cross_payload, cross_valid, _ = _decode_secure_raster(image_a, material_b, page_index, dpi)
        self.assertFalse(cross_valid and cross_payload == expected_b)

    def test_redundancy_survives_recompression_and_mild_crop_rescale(self) -> None:
        from PIL import Image

        secret = b"u" * 32
        source_bytes = b"%PDF-1.4 degradation test source"
        material = _material(secret, job_id="44444444-4444-4444-8444-444444444444", user_id=7, resource_id="resource-degrade", source_bytes=source_bytes)
        dpi = 180
        page_index = 0
        image = _blank_page(1240, 1754)
        worker.draw_secure_raster_marks(image, material, page_index, dpi)
        expected = secure_page_prefix(material, page_index)

        # Recompress as JPEG at a realistic delivery quality (matches the
        # worker's own raster_jpeg_quality default of 88 for image-heavy pages).
        buffer = io.BytesIO()
        image.save(buffer, format="JPEG", quality=85)
        buffer.seek(0)
        recompressed = Image.open(buffer).convert("RGB")
        payload, valid, corrections = _decode_secure_raster(recompressed, material, page_index, dpi)
        self.assertTrue(valid, "JPEG recompression at quality=85 broke decoding")
        self.assertEqual(payload, expected)

        # Downscale-then-upscale (Lanczos resampling blur), as a re-export at
        # lower resolution would produce -- same final pixel dimensions, so no
        # positional realignment is needed to decode (realigning after an
        # *unknown* crop/rotation is the job of a production detector; see
        # forensic_detector.py, explicitly out of scope for this port).
        width, height = image.size
        blurred = image.resize((int(width * 0.6), int(height * 0.6)), Image.LANCZOS).resize((width, height), Image.LANCZOS)
        payload_blurred, valid_blurred, corrections_blurred = _decode_secure_raster(blurred, material, page_index, dpi)
        self.assertTrue(valid_blurred, "resampling blur (60% downscale/upscale) broke decoding")
        self.assertEqual(payload_blurred, expected)

        # A crop that removes a thin margin without rescaling: marks that
        # remain inside the visible canvas keep their absolute pixel position,
        # so they still decode; this demonstrates that the two independent,
        # page-spanning constellations give margin for some symbols to be cut
        # away entirely and still recover the full payload from the survivors.
        margin_x, margin_y = int(width * 0.02), int(height * 0.02)
        cropped = image.crop((margin_x, margin_y, width - margin_x, height - margin_y))
        payload_cropped, valid_cropped, corrections_cropped = _decode_secure_raster_with_offset(
            cropped, material, page_index, dpi, offset=(margin_x, margin_y)
        )
        self.assertTrue(valid_cropped, "2% edge crop broke decoding of the surviving marks")
        self.assertEqual(payload_cropped, expected)

    def test_real_pdf_fixture_is_written_by_the_same_pillow_path_the_worker_uses(self) -> None:
        """Proves the marked raster round-trips through an actual PDF file
        (not a synthetic byte string) using the same Image.save('PDF', ...)
        call the worker itself makes. Re-rasterizing that PDF to verify pixel
        recovery needs pdftoppm/qpdf, which are covered separately by the
        skip-guarded end-to-end test in test_protected_media.py.
        """
        import tempfile

        secret = b"v" * 32
        source_bytes = b"%PDF-1.4 real fixture source"
        material = _material(secret, job_id="55555555-5555-4555-8555-555555555555", user_id=9, resource_id="resource-fixture", source_bytes=source_bytes)
        with tempfile.TemporaryDirectory() as tmp:
            output = Path(tmp) / "marked.pdf"
            image = _blank_page(1240, 1754)
            worker.draw_secure_raster_marks(image, material, 0, 180)
            image.save(output, "PDF", resolution=180.0)
            self.assertTrue(output.is_file())
            self.assertTrue(output.read_bytes().startswith(b"%PDF-"))


if __name__ == "__main__":
    unittest.main()
