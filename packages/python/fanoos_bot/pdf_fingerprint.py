from __future__ import annotations

import base64
import binascii
import hashlib
import hmac
import math
import random
import re
from dataclasses import dataclass
from pathlib import Path

# Ported from legacy/bot/dent_bot/pdf_fingerprint.py (docs/product/01_FRONT_DOOR.md
# names pdf_fingerprint.py/forensic_detector.py as a proven, deliberately-not-yet
# scheme; the owner's decision to bring it across regardless is recorded in the
# task that added this file). This is a byte-for-byte port of the pure,
# standard-library bit-encoding/geometry engine only: the HMAC-keyed material
# derivation, the Hamming(12,8) and triple-repetition redundancy, and the
# secret-keyed dot-position geometry. Every function body below is unchanged
# from the legacy source -- names, constants, bit layouts, anchor geometry,
# redundancy factors and seeding are all load-bearing and must not be "cleaned
# up": a mark that looks fine but decodes wrong is only discovered after a
# leaked file is already unattributable.
#
# Deliberately NOT ported (and why): the legacy functions that mutate a PDF
# with PyMuPDF (`watermark_pdf`, `_place_secure_visible_marks`,
# `_watermark_secure_raster_pdf`, `_place_secure_raster_constellation`,
# `_shape_rtl`, the content-stream-perturbation channel) require PyMuPDF,
# optionally pikepdf, and arabic-reshaper/python-bidi for RTL shaping -- none
# of which are standard library, none of which are already dependencies of
# this worker, and legacy's own test suite guards them with
# `@unittest.skipUnless(find_spec("pymupdf"), ...)`. Those functions also burn
# the recipient's full name, national code and phone number directly into a
# visible page mark, which FANOOS must not do (no personal identifier in a
# visible mark). FANOOS's worker already rasterizes each page to a Pillow
# image via pdftoppm; `apps/workers/protected-media/worker.py` draws the
# secure-raster micro-dot constellation computed by the functions in this
# module directly onto that raster image instead, so no PDF-vector editing
# library is needed. `WatermarkIdentity` is legacy's PII carrier for the
# visible stamp and is not used by anything ported here, so it is not carried
# across either.


LEGACY_WATERMARK_VERSION = "recipient-pdf-v1"
PREVIOUS_WATERMARK_VERSION = "recipient-pdf-v2"
SPREAD_WATERMARK_VERSION = "recipient-pdf-v3"
FONT_WATERMARK_VERSION = "recipient-pdf-v4"
APPEND_HARDENED_WATERMARK_VERSION = "recipient-pdf-v5"
INTERLEAVED_WATERMARK_VERSION = "recipient-pdf-v6"
SECURE_RASTER_WATERMARK_VERSION = "recipient-pdf-v7"
SECURE_RASTER_V8_WATERMARK_VERSION = "recipient-pdf-v8"
WATERMARK_VERSION = "recipient-pdf-v9"
LEGACY_MICRO_CHANNEL_VERSION = "microgrid-repetition-v1"
PREVIOUS_MICRO_CHANNEL_VERSION = "microgrid-hamming12-v2"
MICRO_CHANNEL_VERSION = "microspread-hamming12-v3"
HARDENED_MICRO_CHANNEL_VERSION = "microdistributed-hamming12-v4"
INTERLEAVED_MICRO_CHANNEL_VERSION = "microinterleaved-hamming12-v5"
SECURE_RASTER_CHANNEL_VERSION = "raster-constellation-repetition3-v1"
SUPPORTED_WATERMARK_VERSIONS = (
    LEGACY_WATERMARK_VERSION,
    PREVIOUS_WATERMARK_VERSION,
    SPREAD_WATERMARK_VERSION,
    FONT_WATERMARK_VERSION,
    APPEND_HARDENED_WATERMARK_VERSION,
    INTERLEAVED_WATERMARK_VERSION,
    SECURE_RASTER_WATERMARK_VERSION,
    SECURE_RASTER_V8_WATERMARK_VERSION,
    WATERMARK_VERSION,
)
SECURE_RASTER_WATERMARK_VERSIONS = (
    SECURE_RASTER_WATERMARK_VERSION,
    SECURE_RASTER_V8_WATERMARK_VERSION,
    WATERMARK_VERSION,
)
SPREAD_WATERMARK_VERSIONS = (
    SPREAD_WATERMARK_VERSION,
    FONT_WATERMARK_VERSION,
    APPEND_HARDENED_WATERMARK_VERSION,
    INTERLEAVED_WATERMARK_VERSION,
)
HARDENED_WATERMARK_VERSIONS = (APPEND_HARDENED_WATERMARK_VERSION, INTERLEAVED_WATERMARK_VERSION)
INTERLEAVED_WATERMARK_VERSIONS = (INTERLEAVED_WATERMARK_VERSION,)


class PdfFingerprintError(RuntimeError):
    pass


@dataclass(frozen=True)
class FingerprintMaterial:
    issuance_id: str
    document_id: str
    trace_code: str
    fingerprint_hash: str
    fingerprint_prefix: bytes
    layout_seed: int
    watermark_version: str = WATERMARK_VERSION
    micro_channel_version: str = MICRO_CHANNEL_VERSION
    page_seed_key: bytes = b""
    digital_fingerprint_id: str = ""


def _b32(value: bytes) -> str:
    return base64.b32encode(value).decode("ascii").rstrip("=")


def derive_fingerprint_material(
    secret: bytes,
    *,
    issuance_id: str,
    user_id: int,
    document_id: str,
    source_hash: str,
    watermark_version: str = WATERMARK_VERSION,
) -> FingerprintMaterial:
    if len(secret) < 32:
        raise PdfFingerprintError("Fingerprint key is not configured")
    if not re.fullmatch(r"iss_[A-Za-z0-9_-]{16,80}", issuance_id):
        raise PdfFingerprintError("Issuance identifier is invalid")
    if user_id <= 0 or not document_id or not re.fullmatch(r"[a-f0-9]{64}", source_hash):
        raise PdfFingerprintError("Fingerprint binding is invalid")
    if watermark_version not in SUPPORTED_WATERMARK_VERSIONS:
        raise PdfFingerprintError("Unsupported watermark version")
    canonical = "\n".join((watermark_version, issuance_id, str(user_id), document_id, source_hash)).encode("utf-8")
    fingerprint = hmac.new(secret, b"fingerprint\x00" + canonical, hashlib.sha256).digest()
    # v2+ embeds only an opaque issuance token. It contains no raw or reversible
    # user identifier; backend correlation still requires the secret and DB row.
    if watermark_version == LEGACY_WATERMARK_VERSION:
        micro_token = fingerprint[:8]
    else:
        micro_domain = {
            PREVIOUS_WATERMARK_VERSION: b"micro-token-v2\x00",
            SPREAD_WATERMARK_VERSION: b"micro-token-v3\x00",
            FONT_WATERMARK_VERSION: b"micro-token-v4\x00",
            APPEND_HARDENED_WATERMARK_VERSION: b"micro-token-v5\x00",
            INTERLEAVED_WATERMARK_VERSION: b"micro-token-v6\x00",
            SECURE_RASTER_WATERMARK_VERSION: b"micro-token-v7\x00",
            SECURE_RASTER_V8_WATERMARK_VERSION: b"micro-token-v8\x00",
            WATERMARK_VERSION: b"micro-token-v9\x00",
        }[watermark_version]
        micro_token = hmac.new(
            secret,
            micro_domain + issuance_id.encode("ascii"),
            hashlib.sha256,
        ).digest()[:8]
    trace = _b32(hmac.new(secret, b"trace\x00" + issuance_id.encode("ascii"), hashlib.sha256).digest()[:7])[:10]
    layout = hmac.new(secret, b"layout\x00" + canonical, hashlib.sha256).digest()
    page_seed_key = hmac.new(secret, b"page-seed\x00" + canonical, hashlib.sha256).digest()
    digital_id = _b32(hmac.new(secret, b"digital-id\x00" + canonical, hashlib.sha256).digest()[:9])[:14]
    return FingerprintMaterial(
        issuance_id=issuance_id,
        document_id=document_id,
        trace_code=f"TRC-{trace[:5]}-{trace[5:]}",
        fingerprint_hash=hashlib.sha256(fingerprint).hexdigest(),
        fingerprint_prefix=micro_token,
        layout_seed=int.from_bytes(layout[:8], "big"),
        watermark_version=watermark_version,
        micro_channel_version=(
            LEGACY_MICRO_CHANNEL_VERSION
            if watermark_version == LEGACY_WATERMARK_VERSION
            else PREVIOUS_MICRO_CHANNEL_VERSION
            if watermark_version == PREVIOUS_WATERMARK_VERSION
            else HARDENED_MICRO_CHANNEL_VERSION
            if watermark_version == APPEND_HARDENED_WATERMARK_VERSION
            else INTERLEAVED_MICRO_CHANNEL_VERSION
            if watermark_version == INTERLEAVED_WATERMARK_VERSION
            else SECURE_RASTER_CHANNEL_VERSION
            if watermark_version in SECURE_RASTER_WATERMARK_VERSIONS
            else MICRO_CHANNEL_VERSION
        ),
        page_seed_key=page_seed_key,
        digital_fingerprint_id=f"DFP-{digital_id}",
    )


def secure_page_seed(material: FingerprintMaterial, page_index: int, domain: bytes) -> bytes:
    if not material.page_seed_key or page_index < 0:
        raise PdfFingerprintError("Secure page seed is unavailable")
    return hmac.new(
        material.page_seed_key,
        domain + b"\x00" + int(page_index).to_bytes(4, "big"),
        hashlib.sha256,
    ).digest()


def secure_page_prefix(material: FingerprintMaterial, page_index: int) -> bytes:
    # Secure-raster editions use a 40-bit opaque, page-specific token protected by triple
    # repetition. At the production candidate bound this still has ample
    # collision margin while correcting dispersed raster/scan bit errors far
    # better than the older byte-oriented Hamming payload.
    return secure_page_seed(material, page_index, b"page-payload")[:5]


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        while chunk := stream.read(1024 * 1024):
            digest.update(chunk)
    return digest.hexdigest()


def _micro_payload(prefix: bytes) -> bytes:
    if len(prefix) != 8:
        raise PdfFingerprintError("Fingerprint prefix is invalid")
    crc = binascii.crc_hqx(prefix, 0xFFFF).to_bytes(2, "big")
    return prefix + crc


def _hamming12_encode_byte(value: int) -> tuple[int, ...]:
    positions = [0] * 13
    data_positions = (3, 5, 6, 7, 9, 10, 11, 12)
    for bit_index, position in enumerate(data_positions):
        positions[position] = (value >> (7 - bit_index)) & 1
    for parity_position in (1, 2, 4, 8):
        positions[parity_position] = sum(
            positions[position]
            for position in range(1, 13)
            if position & parity_position and position != parity_position
        ) & 1
    return tuple(positions[1:])


def _hamming12_decode(bits: tuple[int, ...]) -> tuple[int, bool]:
    if len(bits) != 12:
        raise PdfFingerprintError("Hamming codeword is invalid")
    positions = [0] + [1 if bit else 0 for bit in bits]
    syndrome = 0
    for parity_position in (1, 2, 4, 8):
        if sum(positions[position] for position in range(1, 13) if position & parity_position) & 1:
            syndrome |= parity_position
    corrected = False
    if 1 <= syndrome <= 12:
        positions[syndrome] ^= 1
        corrected = True
    data_positions = (3, 5, 6, 7, 9, 10, 11, 12)
    value = 0
    for position in data_positions:
        value = (value << 1) | positions[position]
    return value, corrected


def micro_redundant_bits(
    prefix: bytes,
    watermark_version: str = WATERMARK_VERSION,
) -> tuple[int, ...]:
    if watermark_version in SECURE_RASTER_WATERMARK_VERSIONS:
        if len(prefix) < 5:
            raise PdfFingerprintError("Secure raster fingerprint prefix is invalid")
        payload_bits = tuple((byte >> shift) & 1 for byte in prefix[:5] for shift in range(7, -1, -1))
        return tuple(bit for source_bit in payload_bits for bit in (source_bit, source_bit, source_bit))
    raw_bits = tuple((byte >> shift) & 1 for byte in _micro_payload(prefix) for shift in range(7, -1, -1))
    if watermark_version == LEGACY_WATERMARK_VERSION:
        return tuple(bit for source_bit in raw_bits for bit in (source_bit, source_bit, source_bit))
    if watermark_version not in {PREVIOUS_WATERMARK_VERSION, *SPREAD_WATERMARK_VERSIONS, *SECURE_RASTER_WATERMARK_VERSIONS}:
        raise PdfFingerprintError("Unsupported watermark version")
    payload = _micro_payload(prefix)
    return tuple(bit for byte in payload for bit in _hamming12_encode_byte(byte))


def decode_micro_bits(
    bits: tuple[int, ...],
    watermark_version: str = WATERMARK_VERSION,
) -> tuple[bytes, bool, int]:
    """Decode one logical micro payload and report CRC validity/corrections."""
    if watermark_version in SECURE_RASTER_WATERMARK_VERSIONS:
        if len(bits) != 120:
            return b"", False, 0
        decoded_bits = []
        corrections = 0
        for offset in range(0, len(bits), 3):
            triplet = bits[offset:offset + 3]
            decoded_bits.append(1 if sum(triplet) >= 2 else 0)
            corrections += int(not (triplet[0] == triplet[1] == triplet[2]))
        payload = bytes(
            sum(decoded_bits[offset + bit] << (7 - bit) for bit in range(8))
            for offset in range(0, 40, 8)
        )
        return payload, True, corrections
    if watermark_version == LEGACY_WATERMARK_VERSION:
        if len(bits) != 240:
            return b"", False, 0
        raw = tuple(1 if sum(bits[index:index + 3]) >= 2 else 0 for index in range(0, len(bits), 3))
        payload = bytes(sum(raw[offset + bit] << (7 - bit) for bit in range(8)) for offset in range(0, 80, 8))
        return payload[:8], payload[8:] == binascii.crc_hqx(payload[:8], 0xFFFF).to_bytes(2, "big"), 0
    if watermark_version not in {PREVIOUS_WATERMARK_VERSION, *SPREAD_WATERMARK_VERSIONS, *SECURE_RASTER_WATERMARK_VERSIONS} or len(bits) != 120:
        return b"", False, 0
    decoded = bytearray()
    corrections = 0
    for offset in range(0, len(bits), 12):
        value, corrected = _hamming12_decode(bits[offset:offset + 12])
        decoded.append(value)
        corrections += int(corrected)
    payload = bytes(decoded)
    valid = payload[8:] == binascii.crc_hqx(payload[:8], 0xFFFF).to_bytes(2, "big")
    return payload[:8], valid, corrections


def micro_anchors(
    page_width: float,
    page_height: float,
    layout_seed: int,
    page_index: int,
    watermark_version: str = WATERMARK_VERSION,
) -> tuple[tuple[float, float], ...]:
    if watermark_version in SPREAD_WATERMARK_VERSIONS:
        return ()
    rng = random.Random(layout_seed ^ ((page_index + 1) * 0x9E3779B185EBCA87))
    cell = 1.55 if watermark_version == LEGACY_WATERMARK_VERSION else 1.8
    columns = 30
    grid_width = columns * cell
    grid_height = math.ceil(len(micro_redundant_bits(b"12345678", watermark_version)) / columns) * cell
    max_x = max(25.0, page_width - grid_width - 25.0)
    upper_y = max(22.0, min(page_height * 0.28, page_height - grid_height - 22.0))
    lower_min = max(22.0, page_height * 0.67)
    lower_max = max(lower_min, page_height - grid_height - 22.0)
    return (
        (rng.uniform(25.0, max_x), rng.uniform(22.0, upper_y)),
        (rng.uniform(25.0, max_x), rng.uniform(lower_min, lower_max)),
    )


def micro_dot_positions(
    page_width: float,
    page_height: float,
    layout_seed: int,
    page_index: int,
    prefix: bytes,
    watermark_version: str = WATERMARK_VERSION,
) -> tuple[tuple[tuple[float, float], ...], ...]:
    if watermark_version in SPREAD_WATERMARK_VERSIONS:
        return tuple(
            tuple(pair[bit] for pair, bit in channel)
            for channel in micro_symbol_pairs(
                page_width, page_height, layout_seed, page_index, prefix, watermark_version
            )
        )
    cell = 1.55 if watermark_version == LEGACY_WATERMARK_VERSION else 1.8
    columns = 30
    bits = micro_redundant_bits(prefix, watermark_version)
    grids: list[tuple[tuple[float, float], ...]] = []
    for copy_index, (anchor_x, anchor_y) in enumerate(
        micro_anchors(page_width, page_height, layout_seed, page_index, watermark_version)
    ):
        order = list(range(len(bits)))
        if watermark_version != LEGACY_WATERMARK_VERSION:
            random.Random(layout_seed ^ ((page_index + 1) << 24) ^ (copy_index * 0xA24BAED4963EE407)).shuffle(order)
        points = []
        for index, bit_index in enumerate(order):
            bit = bits[bit_index]
            column = index % columns
            row = index // columns
            x = anchor_x + (column + 0.5) * cell
            y = anchor_y + row * cell + ((1.22 if bit else 0.48) if watermark_version != LEGACY_WATERMARK_VERSION else (1.08 if bit else 0.47))
            points.append((x, y))
        grids.append(tuple(points))
    return tuple(grids)


def micro_symbol_pairs(
    page_width: float,
    page_height: float,
    layout_seed: int,
    page_index: int,
    prefix: bytes,
    watermark_version: str = WATERMARK_VERSION,
) -> tuple:
    """Return two secret layouts as ((zero_position, one_position), bit) symbols.

    v3 deliberately distributes each independent copy across most of the page.
    A diff can still reveal changed pixels, but those changes no longer form one
    compact removable block and masking them broadly damages useful page content.
    """
    if watermark_version not in SPREAD_WATERMARK_VERSIONS:
        raise PdfFingerprintError("Symbol pairs are available only for the spread micro channel")
    bits = micro_redundant_bits(prefix, watermark_version)
    columns = 12
    rows = math.ceil(len(bits) / columns)
    margin_x = min(34.0, max(22.0, page_width * 0.045))
    margin_y = min(42.0, max(28.0, page_height * 0.045))
    usable_width = max(120.0, page_width - 2 * margin_x)
    usable_height = max(160.0, page_height - 2 * margin_y)
    cell_width = usable_width / columns
    cell_height = usable_height / rows
    channels = []
    for copy_index in range(2):
        rng = random.Random(
            layout_seed
            ^ ((page_index + 1) * 0x9E3779B185EBCA87)
            ^ ((copy_index + 1) * 0xA24BAED4963EE407)
        )
        logical_order = list(range(len(bits)))
        rng.shuffle(logical_order)
        symbols = []
        for physical_index, logical_index in enumerate(logical_order):
            row, column = divmod(physical_index, columns)
            # Each copy occupies the full page but gets unrelated jitter and
            # bit-axis orientation. The two candidate points remain close enough
            # for low-cost raster sampling after page alignment.
            center_x = margin_x + (column + 0.5) * cell_width + rng.uniform(-0.28, 0.28) * cell_width
            center_y = margin_y + (row + 0.5) * cell_height + rng.uniform(-0.28, 0.28) * cell_height
            angle = rng.uniform(0.0, math.tau)
            delta = 0.82
            dx, dy = math.cos(angle) * delta, math.sin(angle) * delta
            pair = ((center_x - dx, center_y - dy), (center_x + dx, center_y + dy))
            symbols.append((pair, bits[logical_index]))
        channels.append(tuple(symbols))
    return tuple(channels)


def micro_symbol_layout(
    page_width: float,
    page_height: float,
    layout_seed: int,
    page_index: int,
    prefix: bytes,
    watermark_version: str = WATERMARK_VERSION,
) -> tuple:
    """Expose the spread physical layout with logical indexes for the secret detector."""
    if watermark_version not in SPREAD_WATERMARK_VERSIONS:
        raise PdfFingerprintError("Symbol layout is unavailable for this watermark version")
    bits = micro_redundant_bits(prefix, watermark_version)
    pairs = micro_symbol_pairs(
        page_width, page_height, layout_seed, page_index, prefix, watermark_version
    )
    channels = []
    for copy_index, symbols in enumerate(pairs):
        order = list(range(len(bits)))
        random.Random(
            layout_seed
            ^ ((page_index + 1) * 0x9E3779B185EBCA87)
            ^ ((copy_index + 1) * 0xA24BAED4963EE407)
        ).shuffle(order)
        channels.append(tuple((pair, order[index], bit) for index, (pair, bit) in enumerate(symbols)))
    return tuple(channels)


def secure_raster_symbol_layout(
    page_width: float,
    page_height: float,
    material: FingerprintMaterial,
    page_index: int,
) -> tuple:
    """Return two page-specific secret constellations for the raster edition."""
    if material.watermark_version not in SECURE_RASTER_WATERMARK_VERSIONS:
        raise PdfFingerprintError("Secure raster layout requires the current watermark version")
    prefix = secure_page_prefix(material, page_index)
    bits = micro_redundant_bits(prefix, material.watermark_version)
    margin_x = min(32.0, max(20.0, page_width * 0.04))
    margin_y = min(38.0, max(24.0, page_height * 0.04))
    columns = 12
    rows = math.ceil(len(bits) / columns)
    cell_width = max(8.0, (page_width - 2 * margin_x) / columns)
    cell_height = max(8.0, (page_height - 2 * margin_y) / rows)
    channels = []
    for copy_index in range(2):
        seed = secure_page_seed(material, page_index, f"constellation-{copy_index}".encode("ascii"))
        rng = random.Random(int.from_bytes(seed[:8], "big"))
        order = list(range(len(bits)))
        rng.shuffle(order)
        symbols = []
        for physical_index, logical_index in enumerate(order):
            row, column = divmod(physical_index, columns)
            center_x = margin_x + (column + 0.5) * cell_width + rng.uniform(-0.23, 0.23) * cell_width
            center_y = margin_y + (row + 0.5) * cell_height + rng.uniform(-0.23, 0.23) * cell_height
            angle = rng.uniform(0.0, math.tau)
            separation = rng.uniform(1.65, 2.15)
            dx, dy = math.cos(angle) * separation, math.sin(angle) * separation
            pair = ((center_x - dx, center_y - dy), (center_x + dx, center_y + dy))
            symbols.append((pair, logical_index, bits[logical_index]))
        channels.append(tuple(symbols))
    return tuple(channels)
