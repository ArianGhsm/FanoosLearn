from __future__ import annotations

import base64
import binascii
import hashlib
import hmac
import html
import math
import os
import random
import re
import shutil
import subprocess
import time
from dataclasses import dataclass
from pathlib import Path

from .persian_datetime import to_persian_digits


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
PREVIOUS_CONTENT_TEXT_CHANNEL_VERSION = "content-text-perturb-hamming12-v1"
PREVIOUS_CONTENT_VISUAL_CHANNEL_VERSION = "content-visual-perturb-hamming12-v1"
CONTENT_TEXT_CHANNEL_VERSION = "content-text-perturb-hamming12-v2"
CONTENT_VISUAL_CHANNEL_VERSION = "content-visual-perturb-hamming12-v2"
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
MAX_CLOUD_BOT_DOWNLOAD_BYTES = 20 * 1024 * 1024
MAX_PERSONALIZED_PDF_BYTES = 49 * 1024 * 1024
TRACE_RE = re.compile(r"\bTRC-[A-Z2-7]{5}-[A-Z2-7]{5}\b")


class PdfFingerprintError(RuntimeError):
    pass


@dataclass(frozen=True)
class WatermarkIdentity:
    full_name: str
    national_code: str
    phone_number: str

    def validated(self) -> "WatermarkIdentity":
        full_name = " ".join(str(self.full_name).split())[:160]
        national_code = _ascii_digits(self.national_code)
        phone_number = _ascii_digits(self.phone_number)
        if len(full_name) < 3:
            raise PdfFingerprintError("Canonical full name is missing")
        if len(national_code) != 10:
            raise PdfFingerprintError("Canonical national code is missing")
        if len(phone_number) not in {10, 11, 12}:
            raise PdfFingerprintError("Canonical phone number is missing")
        return WatermarkIdentity(full_name, national_code, phone_number)


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


def _ascii_digits(value: object) -> str:
    return "".join(
        str(ord(char) - ord("۰")) if "۰" <= char <= "۹"
        else str(ord(char) - ord("٠")) if "٠" <= char <= "٩"
        else char
        for char in str(value or "")
        if char.isdigit()
    )


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


def _current_rss_bytes() -> int:
    status = Path("/proc/self/status")
    if status.is_file():
        try:
            for line in status.read_text(encoding="ascii", errors="ignore").splitlines():
                if line.startswith("VmRSS:"):
                    return int(line.split()[1]) * 1024
        except (OSError, ValueError, IndexError):
            return 0
    try:
        import psutil

        return int(psutil.Process().memory_info().rss)
    except (ImportError, OSError):
        return 0


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


@dataclass(frozen=True)
class ContentPerturbationCandidate:
    page_index: int
    stream_index: int
    xref: int
    candidate_index: int
    structural_index: int
    operator: str
    channel: str
    operand_index: int
    operand_count: int
    start: int
    end: int
    value: float
    peer_value: float


_PDF_NUMBER_RE = re.compile(rb"^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$")
_CONTENT_OPERATORS = {
    b"Tm": (6, "text"), b"Td": (2, "text"), b"TD": (2, "text"),
    b"cm": (6, "visual"), b"m": (2, "visual"), b"l": (2, "visual"),
    b"re": (4, "visual"),
}


def _pdf_content_tokens(data: bytes) -> tuple[tuple[str, bytes, int, int], ...]:
    """Tokenize decoded page content without interpreting strings as operators."""
    tokens: list[tuple[str, bytes, int, int]] = []
    length = len(data)
    index = 0
    whitespace = b"\x00\x09\x0a\x0c\x0d\x20"
    delimiters = b"()<>[]{}/%"
    while index < length:
        byte = data[index]
        if byte in whitespace:
            index += 1
            continue
        if byte == 0x25:  # comment
            end = data.find(b"\n", index + 1)
            index = length if end < 0 else end + 1
            continue
        start = index
        if byte == 0x28:  # literal string with balanced parentheses
            depth = 1
            index += 1
            while index < length and depth:
                if data[index] == 0x5C:
                    index += 2
                    continue
                depth += int(data[index] == 0x28)
                depth -= int(data[index] == 0x29)
                index += 1
            tokens.append(("literal", data[start:index], start, index))
            continue
        if byte == 0x3C and index + 1 < length and data[index + 1] != 0x3C:
            end = data.find(b">", index + 1)
            index = length if end < 0 else end + 1
            tokens.append(("hex", data[start:index], start, index))
            continue
        if byte == 0x2F:  # name
            index += 1
            while index < length and data[index] not in whitespace + delimiters:
                index += 1
            tokens.append(("name", data[start:index], start, index))
            continue
        if byte in b"<>[]{}":
            if index + 1 < length and data[index:index + 2] in {b"<<", b">>"}:
                index += 2
            else:
                index += 1
            tokens.append(("delimiter", data[start:index], start, index))
            continue
        index += 1
        while index < length and data[index] not in whitespace + delimiters:
            index += 1
        raw = data[start:index]
        tokens.append(("number" if _PDF_NUMBER_RE.fullmatch(raw) else "word", raw, start, index))
    return tuple(tokens)


def content_stream_fingerprint(data: bytes) -> str:
    """Fingerprint operator/string structure while ignoring numeric perturbations."""
    canonical = b"\x00".join(content_stream_signature(data))
    return hashlib.sha256(canonical).hexdigest()


def content_stream_signature(data: bytes) -> tuple[bytes, ...]:
    return tuple(
        kind.encode("ascii") + b":" + raw
        for kind, raw, _start, _end in _pdf_content_tokens(data)
        if kind != "number"
    )


def _stream_content_candidates(
    data: bytes,
    *,
    page_index: int,
    stream_index: int,
    xref: int,
    watermark_version: str,
) -> tuple[ContentPerturbationCandidate, ...]:
    candidates: list[ContentPerturbationCandidate] = []
    consecutive_numbers: list[tuple[str, bytes, int, int]] = []
    structural_index = -1
    for token in _pdf_content_tokens(data):
        kind, raw, _start, _end = token
        if kind == "number":
            consecutive_numbers.append(token)
            continue
        structural_index += 1
        if kind == "word" and raw in _CONTENT_OPERATORS:
            operand_count, channel = _CONTENT_OPERATORS[raw]
            if len(consecutive_numbers) >= operand_count:
                operands = consecutive_numbers[-operand_count:]
                # v5 used only the final translation operand. Preserve that
                # inventory exactly for old-file detection. v6 distributes
                # symbols across both text translations and all existing
                # vector/image matrix or path operands. Matrix coefficients
                # receive a much smaller epsilon than page coordinates.
                if watermark_version == APPEND_HARDENED_WATERMARK_VERSION:
                    operand_indices = (operand_count - 1,)
                elif channel == "text":
                    operand_indices = tuple(range(max(0, operand_count - 2), operand_count))
                else:
                    operand_indices = tuple(range(operand_count))
                for operand_index in operand_indices:
                    selected = operands[operand_index]
                    peer_index = operand_index - 1 if operand_index else min(1, operand_count - 1)
                    try:
                        value = float(selected[1].decode("ascii"))
                        peer_value = float(operands[peer_index][1].decode("ascii"))
                    except (UnicodeDecodeError, ValueError):
                        value = math.nan
                        peer_value = math.nan
                    if math.isfinite(value) and math.isfinite(peer_value) and abs(value) < 1_000_000:
                        candidates.append(ContentPerturbationCandidate(
                            page_index=page_index,
                            stream_index=stream_index,
                            xref=xref,
                            candidate_index=len(candidates),
                            structural_index=structural_index,
                            operator=raw.decode("ascii"),
                            channel=channel,
                            operand_index=operand_index,
                            operand_count=operand_count,
                            start=selected[2],
                            end=selected[3],
                            value=value,
                            peer_value=peer_value,
                        ))
        consecutive_numbers = []
    return tuple(candidates)


def content_candidate_inventory(
    document,
    watermark_version: str = WATERMARK_VERSION,
) -> tuple[ContentPerturbationCandidate, ...]:
    candidates: list[ContentPerturbationCandidate] = []
    for page_index, page in enumerate(document):
        for stream_index, xref in enumerate(page.get_contents()):
            try:
                data = document.xref_stream(xref)
            except RuntimeError:
                continue
            candidates.extend(_stream_content_candidates(
                data,
                page_index=page_index,
                stream_index=stream_index,
                xref=int(xref),
                watermark_version=watermark_version,
            ))
    return tuple(candidates)


def content_perturbation_plan(
    candidates: tuple[ContentPerturbationCandidate, ...],
    material: FingerprintMaterial,
    channel: str,
) -> tuple[tuple[int, ContentPerturbationCandidate, float], ...]:
    """Return a secret-keyed, document-wide ECC symbol placement plan."""
    eligible = [candidate for candidate in candidates if candidate.channel == channel]
    legacy_plan = material.watermark_version == APPEND_HARDENED_WATERMARK_VERSION
    if legacy_plan:
        domain = b"content-text-v1" if channel == "text" else b"content-visual-v1"
    else:
        domain = b"content-text-v2" if channel == "text" else b"content-visual-v2"

    def ranked(candidate: ContentPerturbationCandidate) -> bytes:
        descriptor = (
            f"{candidate.page_index}:{candidate.stream_index}:"
            f"{candidate.candidate_index}:{candidate.operator}"
            + ("" if legacy_plan else f":{candidate.operand_index}")
        ).encode("ascii")
        return hashlib.sha256(domain + material.layout_seed.to_bytes(8, "big") + descriptor).digest()

    eligible.sort(key=ranked)
    bit_count = len(micro_redundant_bits(material.fingerprint_prefix, material.watermark_version))
    redundancy = 1 if legacy_plan else 2
    limit = min(bit_count * redundancy, len(eligible))
    plan = []
    for placement_index, candidate in enumerate(eligible[:limit]):
        logical_index = placement_index if legacy_plan else placement_index % bit_count
        digest = ranked(candidate)
        if (
            not legacy_plan
            and candidate.operator == "cm"
            and candidate.operand_index < candidate.operand_count - 2
        ):
            epsilon = 0.00001 + (digest[0] / 255.0) * 0.00001
        else:
            epsilon = 0.007 + (digest[0] / 255.0) * 0.007
        plan.append((logical_index, candidate, epsilon))
    return tuple(plan)


def _format_pdf_number(value: float) -> bytes:
    rendered = f"{value:.5f}".rstrip("0").rstrip(".")
    return (rendered if rendered not in {"", "-0"} else "0").encode("ascii")


def _clone_page_content_streams(document) -> tuple[tuple[int, ...], ...]:
    """Make page-local source streams so recipient perturbations never alter a shared stream."""
    pages: list[tuple[int, ...]] = []
    for page in document:
        clones: list[int] = []
        for source_xref in page.get_contents():
            data = document.xref_stream(source_xref)
            clone = document.get_new_xref()
            document.update_object(clone, "<<>>")
            document.update_stream(clone, data)
            clones.append(clone)
        if clones:
            document.xref_set_key(page.xref, "Contents", "[" + " ".join(f"{xref} 0 R" for xref in clones) + "]")
        pages.append(tuple(clones))
    return tuple(pages)


def _embed_content_perturbations(document, material: FingerprintMaterial) -> dict[str, object]:
    candidates = content_candidate_inventory(document, material.watermark_version)
    bits = micro_redundant_bits(material.fingerprint_prefix, material.watermark_version)
    replacements: dict[int, list[tuple[int, int, bytes]]] = {}
    embedded: dict[str, int] = {}
    available: dict[str, int] = {}
    for channel in ("text", "visual"):
        available[channel] = sum(candidate.channel == channel for candidate in candidates)
        plan = content_perturbation_plan(candidates, material, channel)
        embedded[channel] = len(plan)
        for logical_index, candidate, epsilon in plan:
            direction = 1.0 if bits[logical_index] else -1.0
            replacement = _format_pdf_number(candidate.value + direction * epsilon)
            replacements.setdefault(candidate.xref, []).append((candidate.start, candidate.end, replacement))
    for xref, edits in replacements.items():
        data = document.xref_stream(xref)
        for start, end, replacement in sorted(edits, reverse=True):
            data = data[:start] + replacement + data[end:]
        document.update_stream(xref, data)
    return {
        "textAvailable": available["text"],
        "textEmbeddedSymbols": embedded["text"],
        "visualAvailable": available["visual"],
        "visualEmbeddedSymbols": embedded["visual"],
        "totalSymbolsPerFullChannel": len(bits),
        "textRedundancyCopies": round(embedded["text"] / max(1, len(bits)), 3),
        "visualRedundancyCopies": round(embedded["visual"] / max(1, len(bits)), 3),
    }


def _coalesce_interleaved_page_contents(
    document,
    page,
    preferred_source_xrefs: tuple[int, ...],
) -> dict[str, int]:
    """Collapse source and recipient operators into one page content stream.

    The page order was already diversified by
    ``_place_hardened_visible_and_micro``. Joining those decoded streams keeps
    that operator order, while the source chunks already contain the
    secret-keyed numeric perturbations. After ``garbage=4`` the detached
    original/recipient stream objects are unreachable, so a stream-list attack
    cannot retain a clean source stream and drop only security streams.
    """
    contents = tuple(int(xref) for xref in page.get_contents())
    if not contents:
        return {"before": 0, "after": 0, "bytes": 0}
    preferred = next((xref for xref in preferred_source_xrefs if xref in contents), contents[0])
    payload = b"\n".join(document.xref_stream(xref) for xref in contents)
    document.update_stream(preferred, payload)
    document.xref_set_key(page.xref, "Contents", f"{preferred} 0 R")
    return {"before": len(contents), "after": 1, "bytes": len(payload)}


def _shape_rtl(value: str) -> str:
    try:
        import arabic_reshaper
        from bidi.algorithm import get_display
    except ImportError as error:
        raise PdfFingerprintError("Persian text shaping dependencies are not installed") from error
    return get_display(arabic_reshaper.reshape(value))


def _merge_instance_streams(document, keep_xref: int, merge_xrefs: tuple[int, ...], *, reverse: bool) -> None:
    streams = [document.xref_stream(xref) for xref in merge_xrefs]
    if reverse:
        streams.reverse()
    document.update_stream(keep_xref, b"\n".join(streams))
    for xref in merge_xrefs:
        if xref != keep_xref:
            document.update_stream(xref, b"")


def _attach_variable_micro_marks(page, target_xref: int, points, rng: random.Random) -> None:
    """Merge a small, varied symbol batch into one independent visible instance stream."""
    import pymupdf

    shape = page.new_shape()
    for x, y in points:
        if rng.random() < 0.34:
            radius = rng.uniform(0.18, 0.34)
            shape.draw_circle(pymupdf.Point(x, y), radius)
        else:
            half_width = rng.uniform(0.18, 0.39)
            half_height = rng.uniform(0.15, 0.36)
            shape.draw_rect(pymupdf.Rect(x - half_width, y - half_height, x + half_width, y + half_height))
    shape.finish(color=None, fill=(0.34, 0.30, 0.36), fill_opacity=rng.uniform(0.17, 0.23))
    shape.commit(overlay=True)
    micro_xref = page.get_contents()[-1]
    original = page.parent.xref_stream(target_xref)
    micro = page.parent.xref_stream(micro_xref)
    page.parent.update_stream(target_xref, micro + b"\n" + original if rng.random() < 0.5 else original + b"\n" + micro)
    page.parent.update_stream(micro_xref, b"")


def _place_hardened_visible_and_micro(
    page,
    identity: WatermarkIdentity,
    material: FingerprintMaterial,
    font_path: Path,
    page_index: int,
    source_xrefs: tuple[int, ...],
) -> dict[str, int]:
    """Create diversified direct text streams and interleave them with source content."""
    import pymupdf

    if not font_path.is_file():
        raise PdfFingerprintError("Persian watermark font is unavailable")
    width, height = float(page.rect.width), float(page.rect.height)
    rng = random.Random(material.layout_seed ^ ((page_index + 1) * 0xD1B54A32D192ED03))
    # Full canonical PII is intentionally visible as a deterrent. Hidden
    # channels still carry only the opaque issuance token and never raw PII.
    line_one = _shape_rtl(
        f"{identity.full_name} - کد ملی: {to_persian_digits(identity.national_code)}"
    )
    line_two = _shape_rtl(f"موبایل: {to_persian_digits(identity.phone_number)}")
    trace_text = f"Dent1402 {material.trace_code}"
    metric_font = pymupdf.Font(fontfile=str(font_path))
    image_heavy = bool(page.get_images(full=True))
    instance_count = 10
    micro_channels = micro_dot_positions(
        width, height, material.layout_seed, page_index,
        material.fingerprint_prefix, material.watermark_version,
    )
    micro_points = [point for channel in micro_channels for point in channel]
    point_groups = [[] for _ in range(instance_count)]
    instances_per_copy = instance_count // max(1, len(micro_channels))
    for copy_index, channel_points in enumerate(micro_channels):
        shuffled = list(channel_points)
        rng.shuffle(shuffled)
        first_instance = copy_index * instances_per_copy
        for point_index, point in enumerate(shuffled):
            point_groups[first_instance + point_index % instances_per_copy].append(point)

    instance_xrefs: list[int] = []
    columns, rows = 2, 5
    for instance_index in range(instance_count):
        row, column = divmod(instance_index, columns)
        cell_width, cell_height = width / columns, height / rows
        center_x = (column + 0.5) * cell_width + rng.uniform(-0.10, 0.10) * cell_width
        center_y = (row + 0.5) * cell_height + rng.uniform(-0.12, 0.12) * cell_height
        angle = rng.choice((-26.0, -18.0, -12.0, 13.0, 19.0, 27.0)) + rng.uniform(-2.2, 2.2)
        radians = math.radians(angle)
        font_size = rng.uniform(15.2, 17.8)
        second_size = font_size * rng.uniform(0.82, 0.91)
        opacity = rng.uniform(0.34, 0.42) if image_heavy else rng.uniform(0.25, 0.34)
        alias = f"DentV5P{page_index + 1}{'A' if instance_index % 2 == 0 else 'B'}"
        matrix = pymupdf.Matrix(1, 1).prerotate(angle)

        line_one_width = metric_font.text_length(line_one, fontsize=font_size)
        start_one = pymupdf.Point(
            center_x - math.cos(radians) * line_one_width / 2,
            center_y + math.sin(radians) * line_one_width / 2,
        )
        before = set(page.get_contents())
        page.insert_text(
            start_one, line_one, fontsize=font_size, fontname=alias,
            fontfile=str(font_path), color=(0.30, 0.07, 0.12),
            fill_opacity=opacity, morph=(start_one, matrix), overlay=True,
        )
        first_xref = next(xref for xref in reversed(page.get_contents()) if xref not in before)

        line_two_width = metric_font.text_length(line_two, fontsize=second_size)
        offset = font_size * 1.10
        center_two_x = center_x + math.sin(radians) * offset
        center_two_y = center_y + math.cos(radians) * offset
        start_two = pymupdf.Point(
            center_two_x - math.cos(radians) * line_two_width / 2,
            center_two_y + math.sin(radians) * line_two_width / 2,
        )
        page.insert_text(
            start_two, line_two, fontsize=second_size, fontname=alias,
            fontfile=str(font_path), color=(0.30, 0.07, 0.12),
            fill_opacity=opacity, morph=(start_two, matrix), overlay=True,
        )
        second_xref = page.get_contents()[-1]

        trace_size = rng.uniform(8.4, 9.5)
        trace_font = ("helv", "cour", "tiro")[instance_index % 3]
        trace_width = pymupdf.get_text_length(trace_text, fontname=trace_font, fontsize=trace_size)
        trace_offset = font_size * 2.03
        center_trace_x = center_x + math.sin(radians) * trace_offset
        center_trace_y = center_y + math.cos(radians) * trace_offset
        trace_start = pymupdf.Point(
            center_trace_x - math.cos(radians) * trace_width / 2,
            center_trace_y + math.sin(radians) * trace_width / 2,
        )
        page.insert_text(
            trace_start, trace_text, fontsize=trace_size, fontname=trace_font,
            color=(0.30, 0.07, 0.12), fill_opacity=min(0.42, opacity + 0.06),
            morph=(trace_start, matrix), overlay=True,
        )
        trace_xref = page.get_contents()[-1]
        trace_stream = page.parent.xref_stream(trace_xref)
        hexadecimal = ("<" + trace_text.encode("ascii").hex() + ">").encode("ascii")
        if hexadecimal in trace_stream:
            page.parent.update_stream(
                trace_xref,
                trace_stream.replace(hexadecimal, b"(" + trace_text.encode("ascii") + b")", 1),
            )
        merge_xrefs = (first_xref, second_xref, trace_xref)
        _merge_instance_streams(page.parent, first_xref, merge_xrefs, reverse=bool(rng.getrandbits(1)))
        _attach_variable_micro_marks(page, first_xref, point_groups[instance_index], rng)
        instance_xrefs.append(first_xref)

    # The page content array itself is diversified: recipient streams are
    # spread before, between and after page-local source streams rather than
    # appended as one obvious tail block.
    if image_heavy:
        # Opaque diagnostic images must not paint over the deterrent. The ten
        # independent recipient streams stay separate, but all render after the
        # source image on image-heavy pages.
        shuffled_instances = list(instance_xrefs)
        rng.shuffle(shuffled_instances)
        ordered = [*source_xrefs, *shuffled_instances]
    else:
        source_items = [(float(index + 1) / (len(source_xrefs) + 1), 0, xref) for index, xref in enumerate(source_xrefs)]
        visible_items = [(rng.random(), 1, xref) for xref in instance_xrefs]
        ordered = [xref for _position, _kind, xref in sorted(source_items + visible_items)]
    page.parent.xref_set_key(page.xref, "Contents", "[" + " ".join(f"{xref} 0 R" for xref in ordered) + "]")
    return {"visibleInstances": len(instance_xrefs), "distributedMicroSymbols": len(micro_points)}


def _watermark_text(identity: WatermarkIdentity, trace_code: str) -> str:
    safe_name = html.escape(identity.full_name)
    national = html.escape(to_persian_digits(identity.national_code))
    phone = html.escape(to_persian_digits(identity.phone_number))
    trace = html.escape(trace_code)
    return (
        "<div dir='rtl'><b>" + safe_name + "</b> | کد ملی: " + national
        + "<br>موبایل: " + phone + " | Trace Code: <b>" + trace + "</b></div>"
    )


def _make_text_stamp(identity: WatermarkIdentity, material: FingerprintMaterial, font_path: Path):
    try:
        import pymupdf
    except ImportError as error:
        raise PdfFingerprintError("PyMuPDF is not installed") from error
    if not font_path.is_file():
        raise PdfFingerprintError("Persian watermark font is unavailable")
    stamp = pymupdf.open()
    page = stamp.new_page(width=720, height=78)
    if material.watermark_version != LEGACY_WATERMARK_VERSION:
        # A restrained translucent backing keeps identity text readable over
        # radiographs, slides and photos without rasterizing or hiding content.
        page.draw_rect(
            pymupdf.Rect(7, 5, 713, 73), color=None, fill=(1, 1, 1),
            fill_opacity=0.16, overlay=True,
        )
    css = (
        "@font-face{font-family:DentWM;src:url('" + font_path.name + "');font-style:normal;font-weight:700;}"
        "div{font-family:DentWM,sans-serif;font-size:19px;line-height:1.28;text-align:center;"
        "color:#4b1f27;font-weight:700;}b{font-weight:700;}"
    )
    archive = pymupdf.Archive(str(font_path.parent))
    spare, _scale = page.insert_htmlbox(
        pymupdf.Rect(8, 5, 712, 73),
        _watermark_text(identity, material.trace_code),
        css=css,
        archive=archive,
        opacity=0.68 if material.watermark_version != LEGACY_WATERMARK_VERSION else 0.31,
        overlay=True,
    )
    if spare < 0:
        stamp.close()
        raise PdfFingerprintError("Visible watermark did not fit its content stream")
    return stamp


def _place_visible_stamps(page, stamp, material: FingerprintMaterial, page_index: int) -> None:
    width = float(page.rect.width)
    height = float(page.rect.height)
    rng = random.Random(material.layout_seed ^ ((page_index + 1) * 0xD1B54A32D192ED03))
    stamp_width = min(500.0, max(330.0, width * 0.82))
    stamp_height = 112.0
    spacing = rng.uniform(138.0, 168.0)
    offset = rng.uniform(24.0, min(80.0, max(25.0, spacing - 30.0)))
    angle = rng.choice((22, 25, 28, 31))
    row = 0
    y = -20.0 + offset
    while y < height:
        x_jitter = rng.uniform(-18.0, 18.0)
        x = (width - stamp_width) / 2 + x_jitter + ((row % 2) - 0.5) * rng.uniform(20.0, 50.0)
        rect = page.rect.__class__(x, y, x + stamp_width, y + stamp_height)
        page.show_pdf_page(rect, stamp, pno=0, keep_proportion=True, overlay=True, rotate=angle)
        y += spacing
        row += 1


def _place_direct_trace(page, material: FingerprintMaterial, page_index: int) -> None:
    """Add a cheap second visible channel directly to each page content stream.

    The full identity stays repeated in the diagonal stamps. This independent
    ASCII trace is deliberately not another reusable Form XObject, so deleting
    one shared stamp object does not remove every attribution channel.
    """
    if material.watermark_version == LEGACY_WATERMARK_VERSION:
        return
    width = float(page.rect.width)
    height = float(page.rect.height)
    y = 18.0 if page_index % 2 else max(18.0, height - 12.0)
    x = max(18.0, (width - 150.0) / 2.0)
    direct_text = f"Dent1402 {material.trace_code}"
    page.insert_text(
        (x, y), direct_text, fontsize=7.2,
        fontname="helv", color=(0.36, 0.08, 0.13), fill_opacity=0.68,
        overlay=True,
    )
    # PyMuPDF normally serializes text as hexadecimal glyph bytes. Rewrite only
    # this ASCII-safe token as a literal PDF string so the direct channel remains
    # independently searchable in its own content stream after object rewrites.
    xref = page.get_contents()[-1]
    stream = page.parent.xref_stream(xref)
    hexadecimal = ("<" + direct_text.encode("ascii").hex() + ">").encode("ascii")
    if hexadecimal in stream:
        page.parent.update_stream(xref, stream.replace(hexadecimal, b"(" + direct_text.encode("ascii") + b")", 1))


def _place_micro_channel(
    page,
    material: FingerprintMaterial,
    page_index: int,
    *,
    copy_indices: tuple[int, ...] | None = None,
) -> None:
    try:
        import pymupdf
    except ImportError as error:
        raise PdfFingerprintError("PyMuPDF is not installed") from error
    grids = micro_dot_positions(
        float(page.rect.width), float(page.rect.height), material.layout_seed, page_index,
        material.fingerprint_prefix, material.watermark_version,
    )
    selected = copy_indices if copy_indices is not None else tuple(range(len(grids)))
    for copy_index in selected:
        if copy_index < 0 or copy_index >= len(grids):
            raise PdfFingerprintError("Micro channel copy index is invalid")
        # One content stream per copy. Losing a single stream leaves the other
        # independently decodable copy intact.
        shape = page.new_shape()
        grid = grids[copy_index]
        for x, y in grid:
            if material.watermark_version == LEGACY_WATERMARK_VERSION:
                shape.draw_circle(pymupdf.Point(x, y), 0.22)
            else:
                shape.draw_rect(pymupdf.Rect(x - 0.30, y - 0.30, x + 0.30, y + 0.30))
        shape.finish(
            color=None,
            fill=(0.10, 0.08, 0.12),
            fill_opacity=0.24 if material.watermark_version == LEGACY_WATERMARK_VERSION else 0.22,
        )
        shape.commit(overlay=True)


def _rect_area(rect) -> float:
    return max(0.0, float(rect.x1 - rect.x0)) * max(0.0, float(rect.y1 - rect.y0))


def _intersection_area(first, second) -> float:
    x0, y0 = max(float(first.x0), float(second.x0)), max(float(first.y0), float(second.y0))
    x1, y1 = min(float(first.x1), float(second.x1)), min(float(first.y1), float(second.y1))
    return max(0.0, x1 - x0) * max(0.0, y1 - y0)


def _secure_page_profile(page, page_index: int) -> dict[str, object]:
    """Classify one source page using its real text/image geometry."""
    import pymupdf

    crop = page.cropbox
    width, height = float(crop.width), float(crop.height)
    page_area = max(1.0, width * height)
    text_rects = []
    line_rects = []
    first_line_candidates: list[tuple[object, int, float]] = []
    line_descriptors: list[tuple[object, float]] = []
    span_sizes: list[float] = []
    text_characters = 0
    for block in page.get_text("dict").get("blocks", ()):
        if int(block.get("type", -1)) != 0:
            continue
        populated_lines = []
        for line in block.get("lines", ()):
            text = "".join(str(span.get("text") or "") for span in line.get("spans", ())).strip()
            if not text:
                continue
            rect = pymupdf.Rect(*map(float, line.get("bbox", (0, 0, 0, 0))))
            sizes = [float(span.get("size") or 0.0) for span in line.get("spans", ()) if span.get("text")]
            max_size = max(sizes, default=0.0)
            span_sizes.extend(size for size in sizes if size > 0)
            line_rects.append(rect)
            line_descriptors.append((rect, max_size))
            populated_lines.append(rect)
            text_characters += len(text)
        if populated_lines:
            first_line_candidates.append((populated_lines[0], len(populated_lines), float(populated_lines[-1].y1)))
            text_rects.append(pymupdf.Rect(block.get("bbox", populated_lines[0])))
    image_rects = []
    for item in page.get_image_info(xrefs=True):
        bbox = item.get("bbox")
        if bbox and len(bbox) == 4:
            rect = pymupdf.Rect(*map(float, bbox)) & pymupdf.Rect(0, 0, width, height)
            if not rect.is_empty:
                image_rects.append(rect)
    ordered_sizes = sorted(span_sizes)
    body_size = ordered_sizes[len(ordered_sizes) // 2] if ordered_sizes else 0.0
    heading_threshold = max(12.0, body_size * 1.24)
    heading_rects = [rect for rect, max_size in line_descriptors if max_size >= heading_threshold]
    first_line_rects = []
    previous_bottom = None
    for rect, block_line_count, block_bottom in sorted(first_line_candidates, key=lambda item: float(item[0].y0)):
        gap = float(rect.y0) - previous_bottom if previous_bottom is not None else float("inf")
        if block_line_count >= 2 or gap >= max(10.0, body_size * 1.35):
            first_line_rects.append(rect)
        previous_bottom = max(block_bottom, previous_bottom or block_bottom)
    image_roi_rects = [
        pymupdf.Rect(
            rect.x0 + rect.width * 0.25,
            rect.y0 + rect.height * 0.25,
            rect.x1 - rect.width * 0.25,
            rect.y1 - rect.height * 0.25,
        )
        for rect in image_rects
        if rect.width >= 36 and rect.height >= 36
    ]
    caption_rects = []
    for line_rect in line_rects:
        for image_rect in image_rects:
            horizontal_overlap = max(0.0, min(line_rect.x1, image_rect.x1) - max(line_rect.x0, image_rect.x0))
            horizontal_ratio = horizontal_overlap / max(1.0, min(line_rect.width, image_rect.width))
            vertical_gap = min(abs(line_rect.y0 - image_rect.y1), abs(image_rect.y0 - line_rect.y1))
            if horizontal_ratio >= 0.28 and vertical_gap <= 34.0:
                caption_rects.append(line_rect)
                break
    text_coverage = min(1.0, sum(_rect_area(rect) for rect in text_rects) / page_area)
    image_coverage = min(1.0, sum(_rect_area(rect) for rect in image_rects) / page_area)
    if page_index == 0:
        profile = "cover"
    elif image_coverage >= 0.28 or (image_coverage >= 0.16 and image_coverage > text_coverage * 1.35):
        profile = "image-heavy"
    elif text_characters >= 500 or text_coverage >= 0.12:
        profile = "text-heavy"
    else:
        profile = "sparse"
    return {
        "profile": profile,
        "width": width,
        "height": height,
        "rotation": int(page.rotation or 0) % 360,
        "textRects": tuple(text_rects),
        "lineRects": tuple(line_rects),
        "firstLineRects": tuple(first_line_rects),
        "headingRects": tuple(heading_rects),
        "captionRects": tuple(caption_rects),
        "imageRects": tuple(image_rects),
        "imageRoiRects": tuple(image_roi_rects),
        "textCharacters": text_characters,
        "textCoverage": text_coverage,
        "imageCoverage": image_coverage,
    }


def _secure_visible_plan(profile: dict[str, object], material: FingerprintMaterial, page_index: int):
    """Rank visible marks by whitespace, key-content avoidance and crop resilience."""
    import pymupdf

    width, height = float(profile["width"]), float(profile["height"])
    page_kind = str(profile["profile"])
    rng = random.Random(int.from_bytes(secure_page_seed(material, page_index, b"visible-layout")[:8], "big"))
    full_count = 3 if page_kind == "image-heavy" else 4
    if page_kind == "text-heavy" and int(profile["textCharacters"]) >= 1800 and height / max(1.0, width) >= 1.45:
        full_count = 5
    mark_width = min(width * 0.58, max(220.0, width * 0.46))
    mark_height = min(72.0, max(52.0, height * 0.075))
    line_rects = tuple(profile.get("lineRects") or profile["textRects"])
    heading_rects = tuple(profile.get("headingRects") or ())
    first_line_rects = tuple(profile.get("firstLineRects") or ())
    key_rects = (*heading_rects, *first_line_rects)
    image_rects = tuple(profile["imageRects"])
    roi_rects = tuple(profile.get("imageRoiRects") or ())

    def footprint(center_x: float, center_y: float):
        return pymupdf.Rect(
            center_x - mark_width / 2,
            center_y - mark_height / 2,
            center_x + mark_width / 2,
            center_y + mark_height / 2,
        )

    def overlap_ratio(rect, rects) -> float:
        return min(1.0, sum(_intersection_area(rect, item) for item in rects) / max(1.0, _rect_area(rect)))

    def candidate(center_x: float, center_y: float) -> dict[str, float]:
        rect = footprint(center_x, center_y)
        text_overlap = overlap_ratio(rect, line_rects)
        key_overlap = overlap_ratio(rect, key_rects)
        image_overlap = overlap_ratio(rect, image_rects)
        roi_overlap = overlap_ratio(rect, roi_rects)
        edge_distance = min(rect.x0, rect.y0, width - rect.x1, height - rect.y1)
        close_edge_penalty = max(0.0, 12.0 - edge_distance) / 120.0
        crop_resilience_bonus = 0.08 if 0.18 * width <= center_x <= 0.82 * width and 0.16 * height <= center_y <= 0.84 * height else 0.0
        score = (
            text_overlap * 2.2
            + key_overlap * 7.0
            + image_overlap * 0.85
            + roi_overlap * 11.0
            + close_edge_penalty
            - crop_resilience_bonus
        )
        return {
            "score": score,
            "x": center_x,
            "y": center_y,
            "textOverlap": text_overlap,
            "keyOverlap": key_overlap,
            "imageOverlap": image_overlap,
            "roiOverlap": roi_overlap,
        }

    candidates = []
    for y_factor in (0.075, 0.15, 0.26, 0.38, 0.50, 0.63, 0.76, 0.87, 0.94):
        for x_factor in (0.18, 0.36, 0.50, 0.64, 0.82):
            x = min(width - mark_width * 0.43, max(mark_width * 0.43, width * x_factor + rng.uniform(-0.035, 0.035) * width))
            y = min(height - mark_height * 0.42, max(mark_height * 0.42, height * y_factor + rng.uniform(-0.025, 0.025) * height))
            candidates.append(candidate(x, y))
    # Real gaps between text lines / blocks are valuable candidates that a
    # fixed grid can miss, especially on lecture slides with uneven spacing.
    ordered_lines = sorted(line_rects, key=lambda rect: (float(rect.y0), float(rect.x0)))
    vertical_edges = [mark_height * 0.45, *(float(rect.y1) for rect in ordered_lines), height - mark_height * 0.45]
    vertical_starts = [mark_height * 0.45, *(float(rect.y0) for rect in ordered_lines), height - mark_height * 0.45]
    for previous_end, next_start in zip(vertical_edges, vertical_starts[1:]):
        if next_start - previous_end < mark_height * 0.72:
            continue
        y = (previous_end + next_start) / 2
        for x_factor in (0.24, 0.50, 0.76):
            x = min(width - mark_width * 0.43, max(mark_width * 0.43, width * x_factor))
            candidates.append(candidate(x, y))

    edge_candidates = [item for item in candidates if item["y"] <= height * 0.17 or item["y"] >= height * 0.85]
    selected = [min(edge_candidates or candidates, key=lambda item: item["score"])]
    while len(selected) < full_count:
        ranked = []
        for item in candidates:
            if item in selected:
                continue
            min_distance = min(
                math.hypot(float(item["x"]) - float(old["x"]), float(item["y"]) - float(old["y"]))
                for old in selected
            )
            proximity_penalty = max(0.0, 1.0 - min_distance / max(1.0, height * 0.22)) * 1.25
            same_band_penalty = 0.18 if any(int(float(old["y"]) / max(1.0, height / 3)) == int(float(item["y"]) / max(1.0, height / 3)) for old in selected) else 0.0
            ranked.append((float(item["score"]) + proximity_penalty + same_band_penalty, item))
        selected.append(min(ranked, key=lambda entry: entry[0])[1])

    full_marks = []
    for index, item in enumerate(selected[:full_count]):
        x, y = float(item["x"]), float(item["y"])
        content_sensitive = float(item["keyOverlap"]) > 0.01 or float(item["textOverlap"]) > 0.16 or float(item["roiOverlap"]) > 0.0
        opacity_range = (
            (0.13, 0.17) if content_sensitive
            else (0.20, 0.27) if index == 0
            else (0.18, 0.24)
        )
        full_marks.append({
            "x": x,
            "y": y,
            "angle": rng.choice((-1.0, 1.0)) * rng.uniform(15.0, 30.0),
            "opacity": rng.uniform(*opacity_range),
            "fontSize": rng.uniform(13.8, 16.2) * (1.04 if page_kind == "cover" else 1.0),
            "tone": rng.uniform(-0.018, 0.018),
            "placementScore": float(item["score"]),
            "textOverlap": float(item["textOverlap"]),
            "keyOverlap": float(item["keyOverlap"]),
            "roiOverlap": float(item["roiOverlap"]),
        })

    compact_count = 1 if page_kind == "image-heavy" else 2
    compact_marks = []
    for index in range(compact_count):
        image_anchor = page_kind == "image-heavy" and bool(image_rects) and index == 0
        compact_roi_overlap = 0.0
        compact_region = "page"
        if image_anchor:
            compact_width, compact_height = min(150.0, max(92.0, width * 0.20)), 24.0
            image_candidates = []
            for image_index, image_rect in enumerate(image_rects):
                inset_x = min(max(9.0, image_rect.width * 0.08), image_rect.width * 0.20)
                inset_y = min(max(8.0, image_rect.height * 0.07), image_rect.height * 0.18)
                anchors = (
                    (image_rect.x0 + inset_x, image_rect.y0 + inset_y),
                    (image_rect.x1 - inset_x, image_rect.y0 + inset_y),
                    (image_rect.x0 + inset_x, image_rect.y1 - inset_y),
                    (image_rect.x1 - inset_x, image_rect.y1 - inset_y),
                    (image_rect.x0 + inset_x, (image_rect.y0 + image_rect.y1) / 2),
                    (image_rect.x1 - inset_x, (image_rect.y0 + image_rect.y1) / 2),
                )
                for anchor_x, anchor_y in anchors:
                    compact_rect = pymupdf.Rect(
                        anchor_x - compact_width / 2, anchor_y - compact_height / 2,
                        anchor_x + compact_width / 2, anchor_y + compact_height / 2,
                    )
                    roi_overlap = overlap_ratio(compact_rect, roi_rects)
                    key_overlap = overlap_ratio(compact_rect, key_rects)
                    text_overlap = overlap_ratio(compact_rect, line_rects)
                    image_candidates.append((
                        roi_overlap * 20.0 + key_overlap * 8.0 + text_overlap * 3.0,
                        anchor_x, anchor_y, roi_overlap, image_index,
                    ))
            _compact_score, compact_x, compact_y, compact_roi_overlap, image_index = min(image_candidates)
            compact_region = f"image-{image_index + 1}-periphery"
        else:
            compact_x = width * (0.50 if index == 0 else (0.22 if page_index % 2 else 0.78))
            compact_y = height * (0.52 if index == 0 else 0.91)
        compact_marks.append({
            "x": compact_x,
            "y": compact_y,
            "angle": rng.choice((-1.0, 1.0)) * rng.uniform(15.0, 27.0),
            "opacity": rng.uniform(0.10, 0.135) if image_anchor else rng.uniform(0.12, 0.18),
            "fontSize": rng.uniform(7.1, 8.3) if image_anchor else rng.uniform(7.5, 9.2),
            "contrastOutline": image_anchor,
            "roiOverlap": compact_roi_overlap,
            "region": compact_region,
        })
    return tuple(full_marks), tuple(compact_marks)


def _insert_centered_rotated_text(page, text: str, *, center_x: float, center_y: float, angle: float,
                                  font_size: float, font_name: str, font_path: Path | None,
                                  color: tuple[float, float, float], opacity: float,
                                  outline_color: tuple[float, float, float] | None = None,
                                  outline_opacity: float = 1.0) -> None:
    import pymupdf

    metric = pymupdf.Font(fontfile=str(font_path)) if font_path is not None else None
    width = metric.text_length(text, fontsize=font_size) if metric is not None else pymupdf.get_text_length(
        text, fontname=font_name, fontsize=font_size,
    )
    radians = math.radians(angle)
    start = pymupdf.Point(
        center_x - math.cos(radians) * width / 2,
        center_y + math.sin(radians) * width / 2,
    )
    arguments: dict[str, object] = {
        "fontsize": font_size,
        "fontname": font_name,
        "color": color,
        "fill_opacity": opacity,
        "morph": (start, pymupdf.Matrix(1, 1).prerotate(angle)),
        "overlay": True,
    }
    if font_path is not None:
        arguments["fontfile"] = str(font_path)
    if outline_color is not None:
        # Stroke-and-fill text remains readable over both light radiographs and
        # dark / high-frequency medical imagery without adding an opaque box.
        arguments.update({
            "render_mode": 2,
            "color": outline_color,
            "fill": color,
            "border_width": max(0.28, font_size * 0.026),
            "stroke_opacity": outline_opacity,
        })
    page.insert_text(start, text, **arguments)


def _place_secure_visible_marks(page, identity: WatermarkIdentity, material: FingerprintMaterial,
                                font_path: Path, page_index: int, profile: dict[str, object]) -> dict[str, int]:
    full_marks, compact_marks = _secure_visible_plan(profile, material, page_index)
    name = _shape_rtl(identity.full_name)
    national = _shape_rtl(f"کد ملی: {to_persian_digits(identity.national_code)}")
    phone = _shape_rtl(f"موبایل: {to_persian_digits(identity.phone_number)}")
    trace = f"Dent1402 {material.trace_code}"
    for index, mark in enumerate(full_marks):
        angle, size = float(mark["angle"]), float(mark["fontSize"])
        radians = math.radians(angle)
        tone = float(mark["tone"])
        color = (max(0.18, 0.30 + tone), max(0.03, 0.07 + tone / 3), max(0.07, 0.12 + tone / 2))
        perpendicular_x, perpendicular_y = math.sin(radians), math.cos(radians)
        for line_index, (line, line_size) in enumerate(((name, size), (national, size * 0.86), (phone, size * 0.86), (trace, size * 0.58))):
            offset = (line_index - 1.5) * size * 1.02
            is_identity_line = line_index < 3
            _insert_centered_rotated_text(
                page,
                line,
                center_x=float(mark["x"]) + perpendicular_x * offset,
                center_y=float(mark["y"]) + perpendicular_y * offset,
                angle=angle,
                font_size=line_size,
                font_name=f"DentSecureP{page_index + 1}{index % 2}" if is_identity_line else "cour",
                font_path=font_path if is_identity_line else None,
                color=color,
                opacity=float(mark["opacity"]),
            )
    for mark in compact_marks:
        _insert_centered_rotated_text(
            page,
            trace,
            center_x=float(mark["x"]),
            center_y=float(mark["y"]),
            angle=float(mark["angle"]),
            font_size=float(mark["fontSize"]),
            font_name="cour",
            font_path=None,
            color=(0.31, 0.08, 0.13),
            opacity=float(mark["opacity"]),
            outline_color=(0.97, 0.95, 0.94) if bool(mark.get("contrastOutline")) else None,
            outline_opacity=min(0.34, float(mark["opacity"]) + 0.13),
        )
    return {
        "visibleInstances": len(full_marks),
        "compactTraceInstances": len(compact_marks),
        "placementScoreMean": round(sum(float(mark["placementScore"]) for mark in full_marks) / max(1, len(full_marks)), 4),
        "maxKeyContentOverlap": round(max((float(mark["keyOverlap"]) for mark in full_marks), default=0.0), 4),
        "maxImageRoiOverlap": round(max((float(mark["roiOverlap"]) for mark in full_marks), default=0.0), 4),
        "compactImageRoiOverlap": round(max((float(mark["roiOverlap"]) for mark in compact_marks), default=0.0), 4),
        "compactRegions": [str(mark["region"]) for mark in compact_marks],
    }


def _place_secure_raster_constellation(page, material: FingerprintMaterial, page_index: int) -> int:
    import pymupdf

    layouts = secure_raster_symbol_layout(float(page.rect.width), float(page.rect.height), material, page_index)
    total = 0
    for copy_index, layout in enumerate(layouts):
        rng = random.Random(int.from_bytes(secure_page_seed(material, page_index, f"mark-style-{copy_index}".encode("ascii"))[:8], "big"))
        shape = page.new_shape()
        for pair, _logical_index, bit in layout:
            x, y = pair[int(bit)]
            if rng.random() < 0.72:
                shape.draw_circle(pymupdf.Point(x, y), rng.uniform(0.38, 0.54))
            else:
                half = rng.uniform(0.34, 0.50)
                shape.draw_line(pymupdf.Point(x - half, y), pymupdf.Point(x + half, y))
                shape.draw_line(pymupdf.Point(x, y - half), pymupdf.Point(x, y + half))
            total += 1
        shape.finish(
            color=(0.20, 0.17, 0.22),
            fill=(0.20, 0.17, 0.22),
            width=0.34,
            stroke_opacity=rng.uniform(0.19, 0.24),
            fill_opacity=rng.uniform(0.19, 0.24),
        )
        shape.commit(overlay=True)
    return total


def _secure_raster_settings(page_count: int, base_dpi: int, base_quality: int) -> tuple[int, int]:
    dpi = min(180, max(150, int(base_dpi)))
    quality = min(90, max(78, int(base_quality)))
    if page_count > 120:
        return min(dpi, 150), min(quality, 78)
    if page_count > 60:
        return min(dpi, 165), min(quality, 84)
    if page_count > 20:
        return min(dpi, 170), min(quality, 86)
    return min(dpi, 180), min(quality, 88)


def _merge_secure_raster_batches(batch_paths: list[Path], output_path: Path, qpdf_binary: str) -> str:
    if not batch_paths:
        raise PdfFingerprintError("Secure raster pipeline produced no page batches")
    binary = shutil.which(qpdf_binary) if qpdf_binary and not Path(qpdf_binary).is_file() else qpdf_binary
    if binary:
        command = [str(binary), "--empty", "--pages"]
        for batch in batch_paths:
            command.extend((str(batch), "1-z"))
        command.extend(("--", str(output_path)))
        process = subprocess.run(command, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=180, check=False)
        if process.returncode != 0:
            raise PdfFingerprintError("Secure raster PDF batch merge failed")
        return "qpdf"
    try:
        import pikepdf
    except ImportError as error:
        raise PdfFingerprintError("qpdf or pikepdf is required for bounded-memory PDF assembly") from error
    merged = pikepdf.Pdf.new()
    try:
        for batch in batch_paths:
            with pikepdf.Pdf.open(batch) as fragment:
                merged.pages.extend(fragment.pages)
        merged.save(
            output_path,
            compress_streams=True,
            object_stream_mode=pikepdf.ObjectStreamMode.generate,
        )
    finally:
        merged.close()
    return "pikepdf"


def _watermark_secure_raster_pdf(
    input_path: Path,
    output_path: Path,
    *,
    identity: WatermarkIdentity,
    material: FingerprintMaterial,
    font_path: Path,
    qpdf_binary: str,
    deadline_monotonic: float | None,
    raster_dpi: int,
    raster_jpeg_quality: int,
    max_output_bytes: int,
) -> dict[str, object]:
    """Build a pixel-composited secure edition with no live watermark text."""
    import pymupdf

    started_wall, started_cpu = time.perf_counter(), time.process_time()
    baseline_rss = _current_rss_bytes()
    peak_rss = baseline_rss
    py_output = output_path.with_suffix(output_path.suffix + ".secure-raster")
    source = None
    result = None
    page_reports: list[dict[str, object]] = []
    raster_bytes = 0
    batch_paths: list[Path] = []
    batch_size = 4
    temporary_peak_bytes = input_path.stat().st_size
    try:
        _run_qpdf_check(input_path, qpdf_binary)
        source = pymupdf.open(str(input_path))
        if source.is_encrypted or source.needs_pass or source.page_count < 1:
            raise PdfFingerprintError("Encrypted or empty PDFs are not supported")
        dpi, jpeg_quality = _secure_raster_settings(source.page_count, raster_dpi, raster_jpeg_quality)
        result = pymupdf.open()
        for page_index, source_page in enumerate(source):
            if deadline_monotonic is not None and time.monotonic() > deadline_monotonic:
                raise PdfFingerprintError("PDF personalization timed out")
            profile = _secure_page_profile(source_page, page_index)
            working = pymupdf.open()
            try:
                canvas = working.new_page(width=float(profile["width"]), height=float(profile["height"]))
                canvas.show_pdf_page(canvas.rect, source, page_index, keep_proportion=False, rotate=0)
                visible = _place_secure_visible_marks(canvas, identity, material, font_path, page_index, profile)
                micro_count = _place_secure_raster_constellation(canvas, material, page_index)
                pixmap = canvas.get_pixmap(
                    matrix=pymupdf.Matrix(dpi / 72.0, dpi / 72.0),
                    colorspace=pymupdf.csRGB,
                    alpha=False,
                )
                peak_rss = max(peak_rss, _current_rss_bytes())
                image_heavy = str(profile["profile"]) == "image-heavy" or float(profile["imageCoverage"]) >= 0.16
                image_format = "jpeg" if image_heavy else "png"
                image_bytes = pixmap.tobytes(image_format, jpg_quality=jpeg_quality) if image_heavy else pixmap.tobytes("png")
                del pixmap
                encoded_bytes = len(image_bytes)
                raster_bytes += encoded_bytes
                output_page = result.new_page(width=float(profile["width"]), height=float(profile["height"]))
                output_page.insert_image(output_page.rect, stream=image_bytes, keep_proportion=False, overlay=True)
                if int(profile["rotation"]):
                    output_page.set_rotation(int(profile["rotation"]))
                del image_bytes
                page_reports.append({
                    "page": page_index + 1,
                    "profile": str(profile["profile"]),
                    "visibleInstances": visible["visibleInstances"],
                    "compactTraceInstances": visible["compactTraceInstances"],
                    "microSymbols": micro_count,
                    "format": image_format,
                    "encodedBytes": encoded_bytes,
                    "textCoverage": round(float(profile["textCoverage"]), 4),
                    "imageCoverage": round(float(profile["imageCoverage"]), 4),
                    "placementScoreMean": visible["placementScoreMean"],
                    "maxKeyContentOverlap": visible["maxKeyContentOverlap"],
                    "maxImageRoiOverlap": visible["maxImageRoiOverlap"],
                    "compactImageRoiOverlap": visible["compactImageRoiOverlap"],
                    "compactRegions": visible["compactRegions"],
                })
                batch_complete = result.page_count >= batch_size or page_index + 1 == source.page_count
                if batch_complete:
                    batch_path = output_path.with_suffix(output_path.suffix + f".batch-{len(batch_paths) + 1:04d}")
                    result.save(str(batch_path), garbage=4, clean=True, deflate=True, use_objstms=1)
                    result.close()
                    result = None
                    batch_paths.append(batch_path)
                    batch_bytes = sum(path.stat().st_size for path in batch_paths)
                    temporary_peak_bytes = max(temporary_peak_bytes, input_path.stat().st_size + batch_bytes)
                    if batch_bytes > max(1, int(max_output_bytes)):
                        raise PdfFingerprintError("Personalized PDF exceeds the protected Telegram delivery limit")
                    if page_index + 1 < source.page_count:
                        result = pymupdf.open()
            finally:
                working.close()
            peak_rss = max(peak_rss, _current_rss_bytes())
        assembler = _merge_secure_raster_batches(batch_paths, py_output, qpdf_binary)
        with pymupdf.open(str(py_output)) as merged:
            merged.set_metadata({
                "title": "Dent1402 Protected Edition",
                "author": "",
                "subject": "Private recipient edition",
                "keywords": material.digital_fingerprint_id,
                "creator": "Dent1402 secure raster pipeline",
                "producer": "PyMuPDF",
            })
            merged.saveIncr()
        temporary_peak_bytes = max(
            temporary_peak_bytes,
            input_path.stat().st_size + sum(path.stat().st_size for path in batch_paths) + py_output.stat().st_size,
        )
        if py_output.stat().st_size > max(1, int(max_output_bytes)):
            raise PdfFingerprintError("Personalized PDF exceeds the protected Telegram delivery limit")
        os.replace(py_output, output_path)
        os.chmod(output_path, 0o600)
        _run_qpdf_check(output_path, qpdf_binary)
        source_rotations = [int(page.rotation or 0) % 360 for page in source]
        source_dimensions = [(round(float(page.rect.width), 3), round(float(page.rect.height), 3)) for page in source]
        with pymupdf.open(str(output_path)) as verified:
            output_rotations = [int(page.rotation or 0) % 360 for page in verified]
            output_dimensions = [(round(float(page.rect.width), 3), round(float(page.rect.height), 3)) for page in verified]
            annotation_count = sum(1 for page in verified for _annotation in (page.annots() or ()))
            text = "\n".join(page.get_text("text") for page in verified)
            image_counts = [len(page.get_images(full=True)) for page in verified]
            if verified.page_count != source.page_count or output_rotations != source_rotations or output_dimensions != source_dimensions:
                raise PdfFingerprintError("Secure raster page geometry verification failed")
            if annotation_count or text.strip() or any(count != 1 for count in image_counts):
                raise PdfFingerprintError("Secure raster structure verification failed")
            if verified.embfile_count() != 0:
                raise PdfFingerprintError("Secure raster output unexpectedly contains an attachment")
        peak_rss = max(peak_rss, _current_rss_bytes())
        return {
            "pageCount": source.page_count,
            "bytes": output_path.stat().st_size,
            "annotationCount": annotation_count,
            "traceCode": material.trace_code,
            "watermarkVersion": material.watermark_version,
            "microChannelVersion": material.micro_channel_version,
            "secureRaster": True,
            "rasterDpi": dpi,
            "jpegQuality": jpeg_quality,
            "pageProfiles": page_reports,
            "visibleInstances": sum(int(item["visibleInstances"]) for item in page_reports),
            "compactTraceInstances": sum(int(item["compactTraceInstances"]) for item in page_reports),
            "distributedMicroSymbols": sum(int(item["microSymbols"]) for item in page_reports),
            "pageContentStreams": [1] * source.page_count,
            "pageImageCounts": image_counts,
            "liveTextCharacters": 0,
            "digitalFingerprintId": material.digital_fingerprint_id,
            "assemblyMethod": assembler,
            "batchSizePages": batch_size,
            "batchCount": len(batch_paths),
            "temporaryPeakBytes": int(temporary_peak_bytes),
            "encodedRasterBytes": int(raster_bytes),
            "wallSeconds": round(time.perf_counter() - started_wall, 4),
            "cpuSeconds": round(time.process_time() - started_cpu, 4),
            "peakRssBytes": int(peak_rss),
            "peakRssDeltaBytes": max(0, int(peak_rss - baseline_rss)),
        }
    except (OSError, ValueError, RuntimeError) as error:
        if isinstance(error, PdfFingerprintError):
            raise
        raise PdfFingerprintError("Secure raster personalization failed") from error
    finally:
        if result is not None:
            result.close()
        if source is not None:
            source.close()
        try:
            py_output.unlink(missing_ok=True)
        except OSError:
            pass
        for batch_path in batch_paths:
            try:
                batch_path.unlink(missing_ok=True)
            except OSError:
                pass


def _run_qpdf_check(path: Path, qpdf_binary: str) -> None:
    if not qpdf_binary:
        return
    binary = shutil.which(qpdf_binary) if not Path(qpdf_binary).is_file() else qpdf_binary
    if not binary:
        raise PdfFingerprintError("qpdf is not installed")
    result = subprocess.run(
        [str(binary), "--check", str(path)],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        timeout=45,
        check=False,
    )
    if result.returncode != 0:
        raise PdfFingerprintError("PDF validation failed")


def watermark_pdf(
    input_path: Path,
    output_path: Path,
    *,
    identity: WatermarkIdentity,
    material: FingerprintMaterial,
    font_path: Path,
    qpdf_binary: str = "qpdf",
    normalizer: str = "none",
    deadline_monotonic: float | None = None,
    raster_dpi: int = 180,
    raster_jpeg_quality: int = 88,
    max_output_bytes: int = MAX_PERSONALIZED_PDF_BYTES,
) -> dict[str, object]:
    """Create one versioned personalized PDF.

    The current version burns every recipient-specific channel into page pixels
    and rebuilds an image-only PDF. Historical versions remain available only
    for detector/regression compatibility. Source and output remain caller-owned
    temporary files and this function never persists recipient PII.
    """
    identity = identity.validated()
    input_path = input_path.resolve()
    output_path = output_path.resolve()
    if not input_path.is_file() or input_path.stat().st_size <= 0:
        raise PdfFingerprintError("Source PDF is unavailable")
    if input_path.stat().st_size > MAX_CLOUD_BOT_DOWNLOAD_BYTES:
        raise PdfFingerprintError("Source PDF exceeds the Telegram Bot API download limit")
    output_path.parent.mkdir(parents=True, exist_ok=True)
    os.chmod(output_path.parent, 0o700)
    if material.watermark_version in SECURE_RASTER_WATERMARK_VERSIONS:
        if normalizer != "none":
            raise PdfFingerprintError("Secure raster PDFs do not use a secondary normalizer")
        return _watermark_secure_raster_pdf(
            input_path,
            output_path,
            identity=identity,
            material=material,
            font_path=font_path,
            qpdf_binary=qpdf_binary,
            deadline_monotonic=deadline_monotonic,
            raster_dpi=raster_dpi,
            raster_jpeg_quality=raster_jpeg_quality,
            max_output_bytes=max_output_bytes,
        )
    py_output = output_path.with_suffix(output_path.suffix + ".pymupdf")
    normalized_output = output_path.with_suffix(output_path.suffix + ".normalized")
    try:
        import pymupdf
    except ImportError as error:
        raise PdfFingerprintError("PDF dependencies are not installed") from error
    pikepdf = None
    if normalizer == "pikepdf":
        try:
            import pikepdf as pikepdf_module
        except ImportError as error:
            raise PdfFingerprintError("pikepdf is not installed") from error
        pikepdf = pikepdf_module
    elif normalizer != "none":
        raise PdfFingerprintError("Unsupported PDF normalizer")
    document = None
    stamp = None
    temporary_peak_bytes = input_path.stat().st_size
    content_channel_report: dict[str, object] = {}
    hardened_page_report: list[dict[str, int]] = []
    interleaved_content_report: list[dict[str, int]] = []
    started_wall = time.perf_counter()
    started_cpu = time.process_time()
    baseline_rss = _current_rss_bytes()
    peak_rss = baseline_rss
    try:
        _run_qpdf_check(input_path, qpdf_binary)
        document = pymupdf.open(str(input_path))
        if document.is_encrypted or document.needs_pass or document.page_count < 1:
            raise PdfFingerprintError("Encrypted or empty PDFs are not supported")
        source_streams = _clone_page_content_streams(document)
        if material.watermark_version in HARDENED_WATERMARK_VERSIONS:
            content_channel_report = _embed_content_perturbations(document, material)
        else:
            stamp = _make_text_stamp(identity, material, font_path)
        for page_index, page in enumerate(document):
            if deadline_monotonic is not None and time.monotonic() > deadline_monotonic:
                raise PdfFingerprintError("PDF personalization timed out")
            if material.watermark_version in HARDENED_WATERMARK_VERSIONS:
                hardened_page_report.append(_place_hardened_visible_and_micro(
                    page,
                    identity,
                    material,
                    font_path,
                    page_index,
                    source_streams[page_index],
                ))
                if material.watermark_version in INTERLEAVED_WATERMARK_VERSIONS:
                    interleaved_content_report.append(_coalesce_interleaved_page_contents(
                        page.parent,
                        page,
                        source_streams[page_index],
                    ))
                peak_rss = max(peak_rss, _current_rss_bytes())
                continue
            if material.watermark_version in SPREAD_WATERMARK_VERSIONS:
                # Put independent micro copies on opposite sides of the visible
                # channels in the page stream list. Removing the last stream or
                # one Form/XObject cannot erase every forensic channel.
                _place_micro_channel(page, material, page_index, copy_indices=(0,))
            _place_visible_stamps(page, stamp, material, page_index)
            _place_direct_trace(page, material, page_index)
            _place_micro_channel(
                page,
                material,
                page_index,
                copy_indices=(1,) if material.watermark_version in SPREAD_WATERMARK_VERSIONS else None,
            )
            peak_rss = max(peak_rss, _current_rss_bytes())
        if deadline_monotonic is not None and time.monotonic() > deadline_monotonic:
            raise PdfFingerprintError("PDF personalization timed out")
        # v6 has already coalesced each page into one diversified stream after
        # perturbing existing source operators. Older versions retain their
        # historical stream layout for decoder/regression compatibility.
        document.save(str(py_output), garbage=4, clean=False, deflate=True, use_objstms=1)
        temporary_peak_bytes = max(
            temporary_peak_bytes,
            input_path.stat().st_size + py_output.stat().st_size,
        )
        peak_rss = max(peak_rss, _current_rss_bytes())
        document.close()
        document = None
        if stamp is not None:
            stamp.close()
            stamp = None
        if pikepdf is not None:
            with pikepdf.Pdf.open(py_output) as normalized:
                normalized.save(
                    normalized_output,
                    compress_streams=True,
                    object_stream_mode=pikepdf.ObjectStreamMode.generate,
                )
            temporary_peak_bytes = max(
                temporary_peak_bytes,
                input_path.stat().st_size + py_output.stat().st_size + normalized_output.stat().st_size,
            )
            os.replace(normalized_output, output_path)
        else:
            os.replace(py_output, output_path)
        os.chmod(output_path, 0o600)
        _run_qpdf_check(output_path, qpdf_binary)
        with pymupdf.open(str(output_path)) as verified:
            if verified.page_count < 1:
                raise PdfFingerprintError("Personalized PDF verification failed")
            annotation_count = sum(1 for page in verified for _annotation in (page.annots() or ()))
            page_content_stream_counts = [len(page.get_contents()) for page in verified]
            if (
                material.watermark_version in INTERLEAVED_WATERMARK_VERSIONS
                and any(count != 1 for count in page_content_stream_counts)
            ):
                raise PdfFingerprintError("Interleaved page content verification failed")
        return {
            "pageCount": int(document.page_count) if document is not None else _page_count(output_path),
            "bytes": output_path.stat().st_size,
            "annotationCount": annotation_count,
            "traceCode": material.trace_code,
            "watermarkVersion": material.watermark_version,
            "microChannelVersion": material.micro_channel_version,
            "contentChannels": content_channel_report,
            "pageContentStreams": page_content_stream_counts,
            "interleavedContent": {
                "pages": len(interleaved_content_report),
                "streamsBefore": sum(item["before"] for item in interleaved_content_report),
                "streamsAfter": sum(item["after"] for item in interleaved_content_report),
                "decodedBytes": sum(item["bytes"] for item in interleaved_content_report),
            },
            "visibleInstances": sum(item.get("visibleInstances", 0) for item in hardened_page_report),
            "distributedMicroSymbols": sum(item.get("distributedMicroSymbols", 0) for item in hardened_page_report),
            "temporaryPeakBytes": int(temporary_peak_bytes),
            "wallSeconds": round(time.perf_counter() - started_wall, 4),
            "cpuSeconds": round(time.process_time() - started_cpu, 4),
            "peakRssBytes": int(peak_rss),
            "peakRssDeltaBytes": max(0, int(peak_rss - baseline_rss)),
        }
    except (OSError, ValueError, RuntimeError) as error:
        if isinstance(error, PdfFingerprintError):
            raise
        raise PdfFingerprintError("PDF personalization failed") from error
    finally:
        if stamp is not None:
            stamp.close()
        if document is not None:
            document.close()
        for temporary in (py_output, normalized_output):
            try:
                temporary.unlink(missing_ok=True)
            except OSError:
                pass


def _page_count(path: Path) -> int:
    import pymupdf

    with pymupdf.open(str(path)) as document:
        return int(document.page_count)


def extract_visible_trace_from_pdf(path: Path) -> str:
    try:
        import pymupdf
    except ImportError as error:
        raise PdfFingerprintError("PyMuPDF is not installed") from error
    with pymupdf.open(str(path)) as document:
        for page in document:
            searchable = page.get_text("text").replace("\u00ad", "-").replace("\u2010", "-")
            match = TRACE_RE.search(searchable)
            if match:
                return match.group(0)
    return ""
