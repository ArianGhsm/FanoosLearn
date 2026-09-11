from __future__ import annotations

import argparse
import json
import math
import os
import random
import re
import shutil
import subprocess
import tempfile
from dataclasses import dataclass
from pathlib import Path

from .config import load_booklet_fingerprint_key
from .pdf_fingerprint import (
    APPEND_HARDENED_WATERMARK_VERSION,
    CONTENT_TEXT_CHANNEL_VERSION,
    CONTENT_VISUAL_CHANNEL_VERSION,
    FONT_WATERMARK_VERSION,
    HARDENED_MICRO_CHANNEL_VERSION,
    HARDENED_WATERMARK_VERSIONS,
    INTERLEAVED_WATERMARK_VERSION,
    INTERLEAVED_MICRO_CHANNEL_VERSION,
    LEGACY_MICRO_CHANNEL_VERSION,
    LEGACY_WATERMARK_VERSION,
    MICRO_CHANNEL_VERSION,
    PREVIOUS_MICRO_CHANNEL_VERSION,
    PREVIOUS_CONTENT_TEXT_CHANNEL_VERSION,
    PREVIOUS_CONTENT_VISUAL_CHANNEL_VERSION,
    PREVIOUS_WATERMARK_VERSION,
    SECURE_RASTER_CHANNEL_VERSION,
    SECURE_RASTER_WATERMARK_VERSIONS,
    SPREAD_WATERMARK_VERSIONS,
    SUPPORTED_WATERMARK_VERSIONS,
    TRACE_RE,
    WATERMARK_VERSION,
    _hamming12_decode,
    content_candidate_inventory,
    content_perturbation_plan,
    content_stream_fingerprint,
    content_stream_signature,
    decode_micro_bits,
    derive_fingerprint_material,
    extract_visible_trace_from_pdf,
    file_sha256,
    micro_anchors,
    micro_redundant_bits,
    micro_symbol_layout,
    secure_page_prefix,
    secure_raster_symbol_layout,
)
from .state import BotState


@dataclass(frozen=True)
class ChannelResult:
    name: str
    success: bool
    score: float
    recovered_symbols: int
    total_symbols: int
    ecc_status: str
    crc_valid: bool
    corrected_codewords: int = 0
    evidence: dict[str, object] | None = None

    def payload(self, issuance_id: str) -> dict[str, object]:
        return {
            "channel": self.name,
            "success": self.success,
            "score": round(self.score, 4),
            "recoveredSymbols": self.recovered_symbols,
            "totalSymbols": self.total_symbols,
            "eccStatus": self.ecc_status,
            "crcValid": self.crc_valid,
            "correctedCodewords": self.corrected_codewords,
            "candidateIssuanceId": issuance_id,
            "evidence": self.evidence or {},
        }


@dataclass(frozen=True)
class Detection:
    issuance_id: str
    user_id: int
    document_id: str
    trace_code: str
    confidence: float
    successful_channels: tuple[str, ...]
    failed_channels: tuple[str, ...]
    channel_results: tuple[ChannelResult, ...]
    evidence: dict[str, object]
    verdict: str
    watermark_version: str

    @property
    def channels(self) -> tuple[str, ...]:
        return self.successful_channels

    def payload(self) -> dict[str, object]:
        return {
            "issuanceId": self.issuance_id,
            "userId": self.user_id,
            "documentId": self.document_id,
            "traceCode": self.trace_code,
            "confidence": round(self.confidence, 4),
            "verdict": self.verdict,
            "channels": list(self.successful_channels),
            "failedChannels": list(self.failed_channels),
            "channelResults": [item.payload(self.issuance_id) for item in self.channel_results],
            "evidence": self.evidence,
            "watermarkVersion": self.watermark_version,
        }


def _micro_channel_name(version: str) -> str:
    if version == LEGACY_WATERMARK_VERSION:
        return LEGACY_MICRO_CHANNEL_VERSION
    if version == PREVIOUS_WATERMARK_VERSION:
        return PREVIOUS_MICRO_CHANNEL_VERSION
    if version == APPEND_HARDENED_WATERMARK_VERSION:
        return HARDENED_MICRO_CHANNEL_VERSION
    if version == INTERLEAVED_WATERMARK_VERSION:
        return INTERLEAVED_MICRO_CHANNEL_VERSION
    if version in SECURE_RASTER_WATERMARK_VERSIONS:
        return SECURE_RASTER_CHANNEL_VERSION
    return MICRO_CHANNEL_VERSION


def _is_pdf(path: Path) -> bool:
    if path.suffix.lower() == ".pdf":
        return True
    with path.open("rb") as stream:
        return stream.read(5) == b"%PDF-"


def _trace_from_image(path: Path) -> str:
    tesseract = shutil.which("tesseract")
    if not tesseract:
        return ""
    try:
        result = subprocess.run(
            [tesseract, str(path), "stdout", "-l", "eng+fas", "--psm", "11"],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            timeout=45,
            check=False,
        )
    except (OSError, subprocess.SubprocessError):
        return ""
    text = result.stdout.decode("utf-8", errors="ignore").replace("\u00ad", "-")
    match = TRACE_RE.search(text)
    return match.group(0) if match else ""


def _pdf_trace_channels(path: Path) -> tuple[set[str], set[str]]:
    """Return diagonal/text and direct-content traces independently."""
    import pymupdf

    diagonal: set[str] = set()
    direct: set[str] = set()
    with pymupdf.open(str(path)) as document:
        for page in document:
            for block in page.get_text("dict").get("blocks", []):
                for line in block.get("lines", []):
                    for span in line.get("spans", []):
                        value = str(span.get("text") or "").replace("\u00ad", "-")
                        match = TRACE_RE.search(value)
                        if match and float(span.get("size") or 0.0) >= 8.0:
                            diagonal.add(match.group(0))
            for xref in page.get_contents():
                try:
                    stream = document.xref_stream(xref)
                except RuntimeError:
                    continue
                for match in re.finditer(rb"TRC-[A-Z2-7]{5}-[A-Z2-7]{5}", stream):
                    direct.add(match.group(0).decode("ascii"))
    return diagonal, direct


def _pdf_micro_observations(path: Path) -> tuple[list[dict[str, list[tuple[float, float]]]], list[tuple[float, float]]]:
    """Extract small vector marks once, not once per issuance candidate."""
    import pymupdf

    pages: list[dict[str, list[tuple[float, float]]]] = []
    sizes: list[tuple[float, float]] = []
    with pymupdf.open(str(path)) as document:
        for page in document:
            circles: list[tuple[float, float]] = []
            squares: list[tuple[float, float]] = []
            varied: list[tuple[float, float]] = []
            for drawing in page.get_drawings():
                items = list(drawing.get("items") or [])
                for item in items:
                    if item and item[0] == "re":
                        rect = item[1]
                        width = abs(float(rect.x1) - float(rect.x0))
                        height = abs(float(rect.y1) - float(rect.y0))
                        if 0.45 <= width <= 0.80 and 0.45 <= height <= 0.80:
                            squares.append(((float(rect.x0) + float(rect.x1)) / 2, (float(rect.y0) + float(rect.y1)) / 2))
                        if 0.25 <= width <= 0.90 and 0.25 <= height <= 0.90:
                            varied.append(((float(rect.x0) + float(rect.x1)) / 2, (float(rect.y0) + float(rect.y1)) / 2))
                index = 0
                while index + 3 < len(items):
                    curves = items[index:index + 4]
                    if all(item and item[0] == "c" for item in curves):
                        points = [point for item in curves for point in item[1:]]
                        xs = [float(point.x) for point in points]
                        ys = [float(point.y) for point in points]
                        width = max(xs) - min(xs)
                        height = max(ys) - min(ys)
                        if 0.30 <= width <= 0.60 and 0.30 <= height <= 0.60:
                            center = ((min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2)
                            circles.append(center)
                            varied.append(center)
                            index += 4
                            continue
                    index += 1
            pages.append({"circle": circles, "square": squares, "varied": varied})
            sizes.append((float(page.rect.width), float(page.rect.height)))
    return pages, sizes


def _has_point(observed: list[tuple[float, float]], expected: tuple[float, float], tolerance: float = 0.32) -> bool:
    x, y = expected
    limit = tolerance * tolerance
    return any((x - ox) ** 2 + (y - oy) ** 2 <= limit for ox, oy in observed)


def _legacy_symbol_layout(page_width: float, page_height: float, material, page_index: int, copy_index: int):
    version = material.watermark_version
    bits = micro_redundant_bits(material.fingerprint_prefix, version)
    cell = 1.55 if version == LEGACY_WATERMARK_VERSION else 1.8
    low, high = ((0.47, 1.08) if version == LEGACY_WATERMARK_VERSION else (0.48, 1.22))
    anchors = micro_anchors(page_width, page_height, material.layout_seed, page_index, version)
    if copy_index >= len(anchors):
        return ()
    order = list(range(len(bits)))
    if version != LEGACY_WATERMARK_VERSION:
        random.Random(material.layout_seed ^ ((page_index + 1) << 24) ^ (copy_index * 0xA24BAED4963EE407)).shuffle(order)
    anchor_x, anchor_y = anchors[copy_index]
    symbols = []
    for physical_index, logical_index in enumerate(order):
        column, row = physical_index % 30, physical_index // 30
        x = anchor_x + (column + 0.5) * cell
        pair = ((x, anchor_y + row * cell + low), (x, anchor_y + row * cell + high))
        symbols.append((pair, logical_index, bits[logical_index]))
    return tuple(symbols)


def _symbol_layouts(page_width: float, page_height: float, material, page_index: int):
    if material.watermark_version in SECURE_RASTER_WATERMARK_VERSIONS:
        return secure_raster_symbol_layout(page_width, page_height, material, page_index)
    if material.watermark_version in SPREAD_WATERMARK_VERSIONS:
        return micro_symbol_layout(
            page_width, page_height, material.layout_seed, page_index,
            material.fingerprint_prefix, material.watermark_version,
        )
    return tuple(_legacy_symbol_layout(page_width, page_height, material, page_index, copy_index) for copy_index in range(2))


def _decode_observed(logical: list[int | None], version: str, expected: bytes) -> tuple[bool, int, str]:
    recovered = sum(bit is not None for bit in logical)
    if not recovered:
        return False, 0, "not-recovered"
    repaired = list(logical)
    erasures = 0
    if any(bit is None for bit in repaired):
        if version == LEGACY_WATERMARK_VERSION:
            return False, 0, "partial"
        for offset in range(0, len(repaired), 12):
            missing = [index for index in range(offset, min(offset + 12, len(repaired))) if repaired[index] is None]
            if len(missing) > 1:
                return False, erasures, "partial"
            if not missing:
                continue
            bit_index = missing[0]
            viable = []
            for value in (0, 1):
                trial = repaired[offset:offset + 12]
                trial[bit_index - offset] = value
                _decoded_value, corrected = _hamming12_decode(tuple(int(bit) for bit in trial))
                if not corrected:
                    viable.append(value)
            if len(viable) != 1:
                return False, erasures, "partial"
            repaired[bit_index] = viable[0]
            erasures += 1
    decoded, crc_valid, corrected = decode_micro_bits(tuple(int(bit) for bit in repaired), version)
    if crc_valid and decoded == expected:
        return True, corrected + erasures, "valid" if not erasures else "valid-erasure-recovered"
    return False, corrected + erasures, "crc-failed"


def _pdf_micro_results(observations, page_sizes, candidate: dict, secret: bytes) -> tuple[ChannelResult, ...]:
    version = str(candidate["watermarkVersion"])
    material = derive_fingerprint_material(
        secret,
        issuance_id=str(candidate["issuanceId"]), user_id=int(candidate["userId"]),
        document_id=str(candidate["documentId"]), source_hash=str(candidate["sourceHash"]),
        watermark_version=version,
    )
    kind = "circle" if version == LEGACY_WATERMARK_VERSION else "varied" if version in HARDENED_WATERMARK_VERSIONS else "square"
    best: dict[int, ChannelResult] = {}
    for page_index, observed_by_kind in enumerate(observations):
        if page_index >= len(page_sizes):
            break
        layouts = _symbol_layouts(*page_sizes[page_index], material, page_index)
        for copy_index, layout in enumerate(layouts):
            logical: list[int | None] = [None] * len(layout)
            matched = 0
            observed = observed_by_kind[kind]
            for pair, logical_index, expected_bit in layout:
                zero, one = _has_point(observed, pair[0]), _has_point(observed, pair[1])
                recovered = 0 if zero and not one else 1 if one and not zero else None
                logical[logical_index] = recovered
                matched += int(recovered == expected_bit)
            recovered_count = sum(bit is not None for bit in logical)
            crc_valid, corrected, ecc_status = _decode_observed(logical, version, material.fingerprint_prefix)
            score = matched / max(1, len(layout))
            result = ChannelResult(
                name=f"{_micro_channel_name(version)}-copy-{copy_index + 1}",
                success=crc_valid or (score >= 0.72 and recovered_count >= math.ceil(len(layout) * 0.72)),
                score=score, recovered_symbols=recovered_count, total_symbols=len(layout),
                ecc_status=ecc_status, crc_valid=crc_valid, corrected_codewords=corrected,
                evidence={"bestPage": page_index + 1, "matchedSymbols": matched},
            )
            old = best.get(copy_index)
            if old is None or (result.crc_valid, result.score, result.recovered_symbols) > (old.crc_valid, old.score, old.recovered_symbols):
                best[copy_index] = result
    return tuple(best[index] for index in sorted(best))


def _stream_candidate_groups(document, watermark_version: str):
    inventory = content_candidate_inventory(document, watermark_version)
    groups: dict[tuple[int, int], list] = {}
    for item in inventory:
        groups.setdefault((item.page_index, item.stream_index), []).append(item)
    streams: dict[tuple[int, int], dict[str, object]] = {}
    for page_index, page in enumerate(document):
        for stream_index, xref in enumerate(page.get_contents()):
            try:
                data = document.xref_stream(xref)
            except RuntimeError:
                continue
            streams[(page_index, stream_index)] = {
                "fingerprint": content_stream_fingerprint(data),
                "signature": content_stream_signature(data),
                "candidates": tuple(groups.get((page_index, stream_index), ())),
            }
    return inventory, streams


def _matched_source_candidate_values(
    original,
    evidence,
    watermark_version: str,
) -> dict[tuple[int, int, int], float]:
    """Map source candidate ordinals into rewritten evidence page streams."""
    _original_inventory, original_streams = _stream_candidate_groups(original, watermark_version)
    _evidence_inventory, evidence_streams = _stream_candidate_groups(evidence, watermark_version)
    mapped: dict[tuple[int, int, int], float] = {}
    pages = max(original.page_count, evidence.page_count)
    for page_index in range(pages):
        original_page = {
            key: value for key, value in original_streams.items() if key[0] == page_index
        }
        evidence_page = {
            key: value for key, value in evidence_streams.items() if key[0] == page_index
        }
        unused = set(evidence_page)
        for (_page, original_stream_index), source in original_page.items():
            source_candidates = tuple(source["candidates"])
            matches = []
            source_signature = tuple(source["signature"])
            for evidence_key, target in evidence_page.items():
                if evidence_key not in unused and target["fingerprint"] == source["fingerprint"]:
                    continue
                target_candidates = tuple(target["candidates"])
                target_signature = tuple(target["signature"])
                offsets = []
                if target["fingerprint"] == source["fingerprint"]:
                    offsets.append(0)
                elif source_signature and len(source_signature) <= len(target_signature):
                    first = source_signature[0]
                    offsets.extend(
                        index for index, token in enumerate(target_signature)
                        if token == first and target_signature[index:index + len(source_signature)] == source_signature
                    )
                target_by_structural = {
                    (item.structural_index, item.operand_index): item
                    for item in target_candidates
                }
                for offset in offsets:
                    aligned = []
                    for source_item in source_candidates:
                        target_item = target_by_structural.get((
                            offset + source_item.structural_index,
                            source_item.operand_index,
                        ))
                        if target_item is None or target_item.operator != source_item.operator:
                            aligned = []
                            break
                        aligned.append(target_item)
                    if len(aligned) != len(source_candidates):
                        continue
                    distance = sum(
                        min(1.0, abs(float(target_item.value) - float(source_item.value)))
                        for source_item, target_item in zip(source_candidates, aligned)
                    )
                    matches.append((distance, evidence_key, tuple(aligned), offset == 0 and len(source_signature) == len(target_signature)))
            if not matches:
                continue
            _distance, evidence_key, target_candidates, exact_stream = min(matches, key=lambda item: item[0])
            if exact_stream:
                unused.discard(evidence_key)
            for source_item, target_item in zip(source_candidates, target_candidates):
                mapped[(page_index, original_stream_index, source_item.candidate_index)] = float(target_item.value)
    return mapped


def _matched_text_position_bits(original, evidence, inventory) -> dict[tuple[int, int, int], int]:
    """Recover tiny Tm translation signs after cleaners rewrite operators/streams."""
    recovered: dict[tuple[int, int, int], int] = {}
    for page_index in range(min(original.page_count, evidence.page_count)):
        source_spans = [
            span
            for block in original[page_index].get_text("dict").get("blocks", [])
            for line in block.get("lines", [])
            for span in line.get("spans", [])
            if str(span.get("text") or "")
        ]
        evidence_spans = [
            span
            for block in evidence[page_index].get_text("dict").get("blocks", [])
            for line in block.get("lines", [])
            for span in line.get("spans", [])
            if str(span.get("text") or "")
        ]
        unused_source = set(range(len(source_spans)))
        source_for_candidate: dict[tuple[int, int, int], dict] = {}
        page_height = float(original[page_index].rect.height)
        page_candidates = [
            item for item in inventory
            if (
                item.page_index == page_index
                and item.channel == "text"
                and item.operator == "Tm"
                and item.operand_index == item.operand_count - 1
            )
        ]
        for item in page_candidates:
            expected_x = float(item.peer_value)
            expected_y = page_height - float(item.value)
            choices = []
            for span_index in unused_source:
                origin = source_spans[span_index].get("origin") or (0.0, 0.0)
                distance = abs(float(origin[0]) - expected_x) + abs(float(origin[1]) - expected_y)
                choices.append((distance, span_index))
            if not choices:
                continue
            distance, span_index = min(choices)
            if distance > 4.0:
                continue
            unused_source.discard(span_index)
            source_for_candidate[(page_index, item.stream_index, item.candidate_index)] = source_spans[span_index]
        for key, source_span in source_for_candidate.items():
            source_text = str(source_span.get("text") or "")
            source_origin = source_span.get("origin") or (0.0, 0.0)
            matches = [span for span in evidence_spans if str(span.get("text") or "") == source_text]
            if not matches:
                continue
            target = min(matches, key=lambda span: (
                abs(float((span.get("origin") or (0.0, 0.0))[0]) - float(source_origin[0]))
                + abs(float((span.get("origin") or (0.0, 0.0))[1]) - float(source_origin[1]))
            ))
            target_origin = target.get("origin") or (0.0, 0.0)
            delta_y = float(target_origin[1]) - float(source_origin[1])
            if 0.0035 <= abs(delta_y) <= 0.0300:
                # PDF positive Y points upward; PyMuPDF extraction uses a
                # top-left coordinate system, hence the inverted sign.
                recovered[key] = int(delta_y < 0)
    return recovered


def _pdf_content_perturbation_results(
    evidence_path: Path,
    original_pdf: Path | None,
    candidate: dict,
    secret: bytes,
) -> tuple[ChannelResult, ...]:
    version = str(candidate["watermarkVersion"])
    if version == APPEND_HARDENED_WATERMARK_VERSION:
        names = (
            ("text", PREVIOUS_CONTENT_TEXT_CHANNEL_VERSION),
            ("visual", PREVIOUS_CONTENT_VISUAL_CHANNEL_VERSION),
        )
    else:
        names = (("text", CONTENT_TEXT_CHANNEL_VERSION), ("visual", CONTENT_VISUAL_CHANNEL_VERSION))
    if version not in HARDENED_WATERMARK_VERSIONS:
        return ()
    if original_pdf is None:
        return tuple(ChannelResult(
            name=name, success=False, score=0.0, recovered_symbols=0, total_symbols=120,
            ecc_status="original-required", crc_valid=False,
            evidence={"reason": "source PDF is required for content perturbation comparison"},
        ) for _channel, name in names)
    import pymupdf

    material = derive_fingerprint_material(
        secret,
        issuance_id=str(candidate["issuanceId"]), user_id=int(candidate["userId"]),
        document_id=str(candidate["documentId"]), source_hash=str(candidate["sourceHash"]),
        watermark_version=version,
    )
    with pymupdf.open(str(original_pdf)) as original, pymupdf.open(str(evidence_path)) as evidence:
        original_inventory, _original_streams = _stream_candidate_groups(original, version)
        mapped = _matched_source_candidate_values(original, evidence, version)
        text_position_bits = _matched_text_position_bits(original, evidence, original_inventory)
    expected_bits = micro_redundant_bits(material.fingerprint_prefix, version)
    results = []
    for channel, name in names:
        observations: list[list[int]] = [[] for _bit in expected_bits]
        observed_placements = 0
        plan = content_perturbation_plan(original_inventory, material, channel)
        for logical_index, source_item, epsilon in plan:
            evidence_value = mapped.get((
                source_item.page_index, source_item.stream_index, source_item.candidate_index,
            ))
            if evidence_value is None:
                bit = text_position_bits.get((
                    source_item.page_index, source_item.stream_index, source_item.candidate_index,
                )) if channel == "text" else None
                if bit is None:
                    continue
            else:
                delta = float(evidence_value) - float(source_item.value)
                minimum = max(0.000004, epsilon * 0.35)
                maximum = max(0.00006, epsilon * 2.25)
                bit = int(delta > 0) if minimum <= abs(delta) <= maximum else None
                if bit is None and channel == "text":
                    bit = text_position_bits.get((
                        source_item.page_index, source_item.stream_index, source_item.candidate_index,
                    ))
                if bit is None:
                    continue
            observations[logical_index].append(bit)
            observed_placements += 1
        logical: list[int | None] = []
        for votes in observations:
            zeroes = votes.count(0)
            ones = votes.count(1)
            logical.append(0 if zeroes > ones else 1 if ones > zeroes else None)
        matched = sum(
            bit is not None and bit == expected_bits[index]
            for index, bit in enumerate(logical)
        )
        recovered = sum(bit is not None for bit in logical)
        crc_valid, corrected, ecc_status = _decode_observed(logical, version, material.fingerprint_prefix)
        agreement = matched / max(1, recovered)
        coverage = recovered / max(1, len(expected_bits))
        score = 1.0 if crc_valid else agreement * coverage
        results.append(ChannelResult(
            name=name,
            success=crc_valid or (recovered >= 90 and agreement >= 0.97),
            score=score,
            recovered_symbols=recovered,
            total_symbols=len(expected_bits),
            ecc_status=ecc_status,
            crc_valid=crc_valid,
            corrected_codewords=corrected,
            evidence={
                "matchedSymbols": matched,
                "plannedSymbols": len(plan),
                "observedPlacements": observed_placements,
                "redundancyCopies": round(len(plan) / max(1, len(expected_bits)), 3),
                "comparison": "secret-keyed source-content numeric perturbation",
            },
        ))
    return tuple(results)


def _render_page(path: Path, destination: Path, page_index: int, dpi: int = 300) -> tuple[float, float]:
    import pymupdf

    with pymupdf.open(str(path)) as document:
        if document.page_count < 1 or page_index < 0 or page_index >= document.page_count:
            raise ValueError("Original PDF page is unavailable")
        page = document[page_index]
        size = (float(page.rect.width), float(page.rect.height))
        page.get_pixmap(matrix=pymupdf.Matrix(dpi / 72, dpi / 72), alpha=False).save(str(destination))
        return size


def _order_quad(points):
    import numpy as np

    points = np.asarray(points, dtype="float32")
    ordered = np.zeros((4, 2), dtype="float32")
    sums = points.sum(axis=1)
    differences = np.diff(points, axis=1).reshape(-1)
    ordered[0], ordered[2] = points[sums.argmin()], points[sums.argmax()]
    ordered[1], ordered[3] = points[differences.argmin()], points[differences.argmax()]
    return ordered


def _perspective_normalize(image, target_shape):
    import cv2
    import numpy as np

    target_height, target_width = target_shape[:2]
    source_ratio = image.shape[1] / max(1, image.shape[0])
    target_ratio = target_width / max(1, target_height)
    if abs(source_ratio - target_ratio) / target_ratio < 0.025:
        # A native screenshot/render already has the page geometry. Searching
        # its internal content for a page contour can create a false crop.
        return image, False
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image
    edges = cv2.Canny(cv2.GaussianBlur(gray, (5, 5), 0), 40, 120)
    contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    area_floor = image.shape[0] * image.shape[1] * 0.30
    for contour in sorted(contours, key=cv2.contourArea, reverse=True)[:8]:
        if cv2.contourArea(contour) < area_floor:
            continue
        polygon = cv2.approxPolyDP(contour, 0.02 * cv2.arcLength(contour, True), True)
        if len(polygon) != 4:
            continue
        source = _order_quad(polygon.reshape(4, 2))
        height, width = target_shape[:2]
        target = np.float32([[0, 0], [width - 1, 0], [width - 1, height - 1], [0, height - 1]])
        return cv2.warpPerspective(image, cv2.getPerspectiveTransform(source, target), (width, height), borderValue=(255, 255, 255)), True
    return image, False


def _deskew(image):
    import cv2
    import numpy as np

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image
    lines = cv2.HoughLinesP(cv2.Canny(gray, 50, 150), 1, np.pi / 180, 100, minLineLength=max(80, image.shape[1] // 5), maxLineGap=20)
    angles = []
    if lines is not None:
        for x1, y1, x2, y2 in lines.reshape(-1, 4):
            angle = math.degrees(math.atan2(y2 - y1, x2 - x1))
            if -12.0 <= angle <= 12.0:
                angles.append(angle)
    if not angles:
        return image, 0.0
    angle = float(np.median(angles))
    if abs(angle) < 0.15:
        return image, angle
    height, width = image.shape[:2]
    matrix = cv2.getRotationMatrix2D((width / 2, height / 2), angle, 1.0)
    return cv2.warpAffine(image, matrix, (width, height), borderValue=(255, 255, 255)), angle


def _align_image(evidence, original):
    import cv2
    import numpy as np

    cv2.setRNGSeed(0)
    perspective, perspective_corrected = _perspective_normalize(evidence, original.shape)
    original_gray = cv2.cvtColor(original, cv2.COLOR_BGR2GRAY) if len(original.shape) == 3 else original
    perspective_gray = cv2.cvtColor(perspective, cv2.COLOR_BGR2GRAY) if len(perspective.shape) == 3 else perspective
    original_edges = cv2.Canny(original_gray, 50, 150)
    original_edge_ratio = float(np.count_nonzero(original_edges)) / max(1, original_edges.size)
    if float(np.std(original_gray)) < 7.0 and original_edge_ratio < 0.0025:
        # On a nearly blank page the only strong lines may be the recipient
        # marks themselves. Hough deskewing those marks invents a rotation and
        # destroys the secret coordinate frame, so use the page geometry only.
        return cv2.resize(
            perspective_gray,
            (original_gray.shape[1], original_gray.shape[0]),
            interpolation=cv2.INTER_AREA,
        ), {
            "perspectiveCorrected": perspective_corrected,
            "deskewAngleDegrees": 0.0,
            "resizeNormalized": True,
            "pageAlignment": "sparse-direct-resize",
            "alignmentInliers": 0,
        }
    deskewed, skew_angle = _deskew(perspective)
    evidence_gray = cv2.cvtColor(deskewed, cv2.COLOR_BGR2GRAY) if len(deskewed.shape) == 3 else deskewed
    detector = cv2.ORB_create(nfeatures=4000)
    key_original, desc_original = detector.detectAndCompute(original_gray, None)
    # Homography already models rotation. Feeding it a Hough-deskewed image can
    # double-correct a real affine rotation, so ORB works from the perspective-
    # normalized evidence while the deterministic affine fallback uses deskew.
    key_evidence, desc_evidence = detector.detectAndCompute(perspective_gray, None)
    method, inliers, aligned = "resize", 0, None
    if desc_original is not None and desc_evidence is not None and len(key_original) >= 12 and len(key_evidence) >= 12:
        pairs = cv2.BFMatcher(cv2.NORM_HAMMING).knnMatch(desc_evidence, desc_original, k=2)
        good = [first for pair in pairs if len(pair) == 2 for first, second in (pair,) if first.distance < 0.72 * second.distance]
        if len(good) >= 10:
            source = np.float32([key_evidence[item.queryIdx].pt for item in good]).reshape(-1, 1, 2)
            target = np.float32([key_original[item.trainIdx].pt for item in good]).reshape(-1, 1, 2)
            transform, mask = cv2.findHomography(source, target, cv2.RANSAC, 4.0)
            if transform is not None:
                aligned = cv2.warpPerspective(perspective_gray, transform, (original_gray.shape[1], original_gray.shape[0]))
                method, inliers = "orb-homography", int(mask.sum()) if mask is not None else 0
    if aligned is not None and inliers >= 24 and abs(skew_angle) < 0.5:
        # With no material rotation, a high-inlier homography is the strongest
        # crop/resize alignment signal. A coarse scale bank must not replace it.
        return aligned, {
            "perspectiveCorrected": perspective_corrected,
            "deskewAngleDegrees": round(skew_angle, 3),
            "resizeNormalized": True,
            "pageAlignment": method,
            "alignmentInliers": inliers,
        }
    base = cv2.resize(evidence_gray, (original_gray.shape[1], original_gray.shape[0]), interpolation=cv2.INTER_AREA)
    blurred_original = cv2.GaussianBlur(original_gray, (5, 5), 0)

    def alignment_score(candidate) -> float:
        return float(np.mean(cv2.absdiff(cv2.GaussianBlur(candidate, (5, 5), 0), blurred_original)))

    if aligned is None:
        method, aligned, best_score = "resize", base, alignment_score(base)
    else:
        best_score = alignment_score(aligned)

    def consider(candidate_method: str, candidate) -> None:
        nonlocal method, aligned, best_score
        score = alignment_score(candidate)
        if score < best_score:
            method, aligned, best_score = candidate_method, candidate, score

    # ORB can lock onto repeated table/text rows and return a plausible but
    # wrong homography. Always compare it with a tiny candidate-independent
    # affine bank; this runs only in the admin detector and retains one image at
    # a time. Clean ORB alignment still wins when its page-content score is best.
    consider("resize", base)
    center = (original_gray.shape[1] / 2.0, original_gray.shape[0] / 2.0)
    for correction_angle in (-6.0, -4.0, -2.0, 0.0, 2.0, 4.0, 6.0):
        for correction_scale in (0.98, 0.99, 1.0, 1.01, 1.02):
            matrix = cv2.getRotationMatrix2D(center, correction_angle, correction_scale)
            candidate = cv2.warpAffine(
                base,
                matrix,
                (original_gray.shape[1], original_gray.shape[0]),
                borderValue=255,
            )
            consider(f"rotate-{correction_angle:+.0f}-scale-{correction_scale:.2f}", candidate)
    # Common crop tools remove equal margins and resize the remainder back to
    # the page size. The fixed scale bank remains independent of the issuance.
    for retained in (0.96, 0.95, 0.92, 0.90, 0.80):
        scaled_width = max(1, int(round(original_gray.shape[1] * retained)))
        scaled_height = max(1, int(round(original_gray.shape[0] * retained)))
        scaled = cv2.resize(base, (scaled_width, scaled_height), interpolation=cv2.INTER_AREA)
        canvas = np.full_like(original_gray, 255)
        x0 = (canvas.shape[1] - scaled_width) // 2
        y0 = (canvas.shape[0] - scaled_height) // 2
        canvas[y0:y0 + scaled_height, x0:x0 + scaled_width] = scaled
        consider(f"center-scale-{retained:.2f}", canvas)
    return aligned, {
        "perspectiveCorrected": perspective_corrected, "deskewAngleDegrees": round(skew_angle, 3),
        "resizeNormalized": True, "pageAlignment": method, "alignmentInliers": inliers,
    }


def _prepare_image_analysis(evidence_path: Path, original_pdf: Path, original_page_index: int):
    import cv2
    import pymupdf

    with tempfile.TemporaryDirectory(prefix="dent-forensic-") as directory:
        original_png = Path(directory) / "original.png"
        evidence = cv2.imread(str(evidence_path), cv2.IMREAD_COLOR)
        if evidence is None:
            return None
        with pymupdf.open(str(original_pdf)) as document:
            page = document[original_page_index]
            page_size = (float(page.rect.width), float(page.rect.height))
        evidence_ratio = evidence.shape[1] / max(1, evidence.shape[0])
        page_ratio = page_size[0] / max(1.0, page_size[1])
        dpi = 300
        if abs(evidence_ratio - page_ratio) / page_ratio < 0.03:
            # Preserve a screenshot's native pixel grid. Rendering the original
            # at a different DPI and resizing it can bury sub-point micro marks
            # under interpolation noise before comparison even starts.
            dpi = max(96, min(400, int(round(evidence.shape[1] / page_size[0] * 72))))
        page_size = _render_page(original_pdf, original_png, original_page_index, dpi=dpi)
        original = cv2.imread(str(original_png), cv2.IMREAD_COLOR)
        if original is None:
            return None
        aligned, preprocessing = _align_image(evidence, original)
        original_gray = cv2.cvtColor(original, cv2.COLOR_BGR2GRAY)
        return cv2.GaussianBlur(cv2.absdiff(aligned, original_gray), (3, 3), 0), page_size, preprocessing


def _prepare_pdf_raster_analysis(evidence_path: Path, original_pdf: Path, original_page_index: int):
    """Render one secure-raster PDF page at its native image density for comparison."""
    import pymupdf

    with tempfile.TemporaryDirectory(prefix="dent-forensic-pdf-") as directory:
        evidence_png = Path(directory) / "evidence.png"
        with pymupdf.open(str(evidence_path)) as document:
            if original_page_index < 0 or original_page_index >= document.page_count:
                return None, ""
            page = document[original_page_index]
            native_dpi = 180
            images = page.get_images(full=True)
            if images:
                try:
                    pixmap = pymupdf.Pixmap(document, int(images[0][0]))
                    native_dpi = int(round(max(
                        pixmap.width / max(1.0, float(page.rect.width)),
                        pixmap.height / max(1.0, float(page.rect.height)),
                    ) * 72.0))
                except (RuntimeError, ValueError):
                    native_dpi = 180
            native_dpi = max(96, min(400, native_dpi))
            page.get_pixmap(matrix=pymupdf.Matrix(native_dpi / 72.0, native_dpi / 72.0), alpha=False).save(
                str(evidence_png)
            )
        analysis = _prepare_image_analysis(evidence_png, original_pdf, original_page_index)
        return analysis, _trace_from_image(evidence_png)


def _pdf_digital_fingerprint_id(path: Path) -> str:
    import pymupdf

    try:
        with pymupdf.open(str(path)) as document:
            keywords = str((document.metadata or {}).get("keywords") or "").strip()
    except (OSError, RuntimeError, ValueError):
        return ""
    return keywords if re.fullmatch(r"DFP-[A-Z2-7]{14}", keywords) else ""


def _sample_mean(image, x: int, y: int, radius: int) -> float:
    import numpy as np

    height, width = image.shape[:2]
    x0, x1 = max(0, x - radius), min(width, x + radius + 1)
    y0, y1 = max(0, y - radius), min(height, y + radius + 1)
    return float(np.mean(image[y0:y1, x0:x1])) if x0 < x1 and y0 < y1 else 0.0


def _byte_bit_agreement(first: bytes, second: bytes) -> float:
    compared = min(len(first), len(second)) * 8
    if compared <= 0:
        return 0.0
    differences = sum((left ^ right).bit_count() for left, right in zip(first, second))
    return max(0.0, (compared - differences) / compared)


def _image_micro_results(analysis, candidate: dict, secret: bytes, original_page_index: int) -> tuple[ChannelResult, ...]:
    import numpy as np

    if analysis is None:
        return ()
    diff, (page_width, page_height), _preprocessing = analysis
    height, width = diff.shape[:2]
    version = str(candidate["watermarkVersion"])
    material = derive_fingerprint_material(
        secret, issuance_id=str(candidate["issuanceId"]), user_id=int(candidate["userId"]),
        document_id=str(candidate["documentId"]), source_hash=str(candidate["sourceHash"]), watermark_version=version,
    )
    expected_prefix = secure_page_prefix(material, original_page_index) if version in SECURE_RASTER_WATERMARK_VERSIONS else material.fingerprint_prefix
    radius = max(1, int(round(width / page_width * 0.45)))
    results = []
    fusion_inputs: list[tuple[list[int], list[float]]] = []
    for copy_index, layout in enumerate(_symbol_layouts(page_width, page_height, material, original_page_index)):
        logical: list[int | None] = [None] * len(layout)
        raw_logical: list[int] = [0] * len(layout)
        expected_signal, alternative_signal, margins, values, samples_by_symbol = [], [], [], [], []
        for pair, logical_index, expected_bit in layout:
            samples = [_sample_mean(diff, int(round(x / page_width * width)), int(round(y / page_height * height)), radius) for x, y in pair]
            samples_by_symbol.append((samples, logical_index))
            raw_logical[logical_index] = int(samples[1] >= samples[0])
            values.extend(samples)
            margins.append(abs(samples[1] - samples[0]))
            expected_signal.append(samples[expected_bit])
            alternative_signal.append(samples[1 - expected_bit])
        adaptive_signal = max(1.5, float(np.percentile(values, 35)) if values else 1.5)
        adaptive_margin = max(0.45, float(np.percentile(margins, 35)) if margins else 0.45)
        for samples, logical_index in samples_by_symbol:
            if max(samples) >= adaptive_signal and abs(samples[1] - samples[0]) >= adaptive_margin:
                logical[logical_index] = int(samples[1] >= samples[0])
        decoded, crc_valid, corrected = decode_micro_bits(tuple(raw_logical), version)
        crc_valid = bool(crc_valid and decoded == expected_prefix)
        recovered = sum(bit is not None for bit in logical)
        expected_bits = micro_redundant_bits(expected_prefix, version)
        matched_recovered = sum(
            bit is not None and int(bit) == expected_bits[index]
            for index, bit in enumerate(logical)
        )
        agreement = matched_recovered / max(1, recovered)
        coverage = min(1.0, recovered / max(1.0, len(layout) * 0.55))
        token_agreement = _byte_bit_agreement(decoded, expected_prefix)
        score = 1.0 if crc_valid else (token_agreement * coverage if version in SECURE_RASTER_WATERMARK_VERSIONS else agreement * coverage)
        strength_by_logical = [0.0] * len(layout)
        for (samples, logical_index) in samples_by_symbol:
            strength_by_logical[logical_index] = abs(float(samples[1]) - float(samples[0]))
        fusion_inputs.append((raw_logical, strength_by_logical))
        results.append(ChannelResult(
            name=f"{_micro_channel_name(version)}-copy-{copy_index + 1}",
            success=crc_valid or (
                score >= (0.82 if version in SECURE_RASTER_WATERMARK_VERSIONS else 0.56)
                and recovered >= max(24, len(layout) // 5)
            ),
            score=score, recovered_symbols=recovered, total_symbols=len(layout),
            ecc_status="valid" if crc_valid else "partial" if recovered else "not-recovered",
            crc_valid=crc_valid, corrected_codewords=corrected,
            evidence={
                "page": original_page_index + 1,
                "signalThreshold": round(adaptive_signal, 4),
                "marginThreshold": round(adaptive_margin, 4),
                "matchedRecoveredSymbols": matched_recovered,
                "decodedTokenAgreement": round(token_agreement, 4),
            },
        ))
    if len(fusion_inputs) >= 2:
        if version in SECURE_RASTER_WATERMARK_VERSIONS:
            # Each source bit has three repetitions in each of two independent
            # constellations. Fuse all six observations rather than selecting a
            # single strongest (and potentially corrupted) symbol. Square-root
            # weighting retains confidence information without allowing one
            # interpolation outlier to dominate the whole ECC decision.
            fused = []
            disagreement_groups = 0
            source_bit_count = len(fusion_inputs[0][0]) // 3
            for source_index in range(source_bit_count):
                weighted = [0.0, 0.0]
                observed = []
                for bits, strengths in fusion_inputs:
                    for repetition in range(3):
                        logical_index = source_index * 3 + repetition
                        bit = int(bits[logical_index])
                        observed.append(bit)
                        weighted[bit] += math.sqrt(max(0.01, float(strengths[logical_index])))
                fused_bit = int(weighted[1] > weighted[0])
                fused.extend((fused_bit, fused_bit, fused_bit))
                disagreement_groups += int(any(bit != fused_bit for bit in observed))
            fusion_selection = "weighted-six-symbol-repetition-vote"
        else:
            fused = []
            disagreement_groups = 0
            for logical_index in range(len(fusion_inputs[0][0])):
                strongest_bits = sorted(
                    (
                        (float(strengths[logical_index]), int(bits[logical_index]))
                        for bits, strengths in fusion_inputs
                    ),
                    reverse=True,
                )
                fused.append(strongest_bits[0][1])
            fusion_selection = "strongest-secret-position-pair"
        decoded, crc_valid, corrected = decode_micro_bits(tuple(fused), version)
        exact_match = bool(crc_valid and decoded == expected_prefix)
        source_bit_errors = sum((left ^ right).bit_count() for left, right in zip(decoded, expected_prefix))
        # v9's verifier knows the HMAC-derived expected 40-bit codeword. A
        # bounded nearest-codeword correction of at most three source bits is
        # still a very small random acceptance region (<1e-8 per candidate)
        # and recovers common downscale / recompression damage without treating
        # an arbitrary high partial score as attribution.
        bounded_match = bool(version == WATERMARK_VERSION and len(decoded) == len(expected_prefix) and source_bit_errors <= 3)
        crc_valid = exact_match or bounded_match
        matched = sum(bit == expected_bits[index] for index, bit in enumerate(fused))
        token_agreement = _byte_bit_agreement(decoded, expected_prefix)
        score = 1.0 if crc_valid else token_agreement
        results.append(ChannelResult(
            name=f"{_micro_channel_name(version)}-ecc-fusion",
            success=crc_valid,
            score=score,
            recovered_symbols=len(fused),
            total_symbols=len(fused),
            ecc_status="valid" if exact_match else "corrected-known-codeword" if bounded_match else "crc-failed",
            crc_valid=crc_valid,
            corrected_codewords=max(corrected, disagreement_groups, source_bit_errors if bounded_match else 0),
            evidence={
                "page": original_page_index + 1,
                "matchedSymbols": matched,
                "decodedTokenAgreement": round(token_agreement, 4),
                "copiesFused": len(fusion_inputs),
                "selection": fusion_selection,
                "sourceBitErrors": source_bit_errors,
                "knownCodewordCorrectionBound": 3 if version == WATERMARK_VERSION else 0,
            },
        ))
    return tuple(results)


def _image_hidden_score(analysis, candidate: dict, secret: bytes, original_page_index: int) -> float:
    """Compatibility score for older tests and owner-side tooling."""
    return max(
        (item.score for item in _image_micro_results(analysis, candidate, secret, original_page_index)),
        default=0.0,
    )


def verify_expected_secure_raster(
    evidence_path: Path,
    *,
    original_pdf: Path,
    secret: bytes,
    issuance_id: str,
    user_id: int,
    document_id: str,
    source_hash: str,
    watermark_version: str = WATERMARK_VERSION,
    original_page_index: int = 0,
) -> dict[str, object]:
    """Verify one expected issuance without enumerating unrelated recipients.

    PDF evidence is scored page-by-page. Image evidence is bound to the supplied
    original page index. Only a valid ECC recovery sets ``detected``; partial
    scores remain useful evidence but never become definitive attribution.
    """
    evidence_path = evidence_path.resolve()
    original_pdf = original_pdf.resolve()
    if not evidence_path.is_file() or not original_pdf.is_file():
        raise ValueError("Evidence and original PDF are required")
    if watermark_version not in SECURE_RASTER_WATERMARK_VERSIONS:
        raise ValueError("Expected verifier supports secure-raster editions only")
    actual_source_hash = file_sha256(original_pdf)
    if source_hash != actual_source_hash:
        raise ValueError("Expected source hash does not match the original PDF")
    candidate = {
        "issuanceId": issuance_id,
        "userId": int(user_id),
        "documentId": document_id,
        "sourceHash": source_hash,
        "watermarkVersion": watermark_version,
    }
    is_pdf = _is_pdf(evidence_path)
    if is_pdf:
        import pymupdf

        with pymupdf.open(str(evidence_path)) as evidence_document, pymupdf.open(str(original_pdf)) as original_document:
            page_indices = range(min(evidence_document.page_count, original_document.page_count))
            evidence_page_count = evidence_document.page_count
    else:
        page_indices = (max(0, int(original_page_index)),)
        evidence_page_count = 1

    per_page = []
    valid_pages = 0
    partial_pages = 0
    for page_index in page_indices:
        if is_pdf:
            analysis, _ocr_trace = _prepare_pdf_raster_analysis(evidence_path, original_pdf, page_index)
        else:
            analysis = _prepare_image_analysis(evidence_path, original_pdf, page_index)
        channels = _image_micro_results(analysis, candidate, secret, page_index)
        valid_ecc = any(item.crc_valid for item in channels)
        successful = any(item.success for item in channels)
        best_score = max((item.score for item in channels), default=0.0)
        valid_pages += int(valid_ecc)
        partial_pages += int(successful and not valid_ecc)
        per_page.append({
            "page": page_index + 1,
            "detected": valid_ecc,
            "score": round(best_score, 4),
            "eccStatus": "valid" if valid_ecc else "partial" if successful else "not-recovered",
            "channels": [item.payload(issuance_id) for item in channels],
            "preprocessing": analysis[2] if analysis is not None else {},
        })
    best_score = max((float(item["score"]) for item in per_page), default=0.0)
    detected = valid_pages > 0
    confidence = (
        min(0.995, 0.965 + min(0.03, valid_pages * 0.006))
        if detected
        else min(0.89, 0.62 + best_score * 0.24)
        if partial_pages
        else min(0.49, best_score * 0.72)
    )
    return {
        "detected": detected,
        "confidence": round(confidence, 4),
        "expectedIssuanceId": issuance_id,
        "expectedDocumentId": document_id,
        "watermarkVersion": watermark_version,
        "sourceType": "pdf" if is_pdf else "image",
        "evidencePages": evidence_page_count,
        "validEccPages": valid_pages,
        "partialPages": partial_pages,
        "perPage": per_page,
        "policy": "detected only when at least one page has valid ECC",
    }


def detect(
    evidence_path: Path,
    *,
    state: BotState,
    secret: bytes,
    original_pdf: Path | None = None,
    document_id: str = "",
    original_page_index: int = 0,
) -> list[Detection]:
    evidence_path = evidence_path.resolve()
    if not evidence_path.is_file():
        raise ValueError("Evidence file does not exist")
    original_pdf = original_pdf.resolve() if original_pdf is not None else None
    if original_pdf is not None and not original_pdf.is_file():
        raise ValueError("Original PDF does not exist")
    is_pdf = _is_pdf(evidence_path)
    trace = extract_visible_trace_from_pdf(evidence_path) if is_pdf else _trace_from_image(evidence_path)
    diagonal_traces, direct_traces = _pdf_trace_channels(evidence_path) if is_pdf else (set(), set())
    source_hash = file_sha256(original_pdf) if original_pdf is not None else ""
    if not trace and not document_id and not source_hash:
        raise ValueError("Document ID or original PDF is required when no visible trace can be recovered")
    candidates = state.forensic_booklet_candidates(trace_code=trace, document_id=document_id, source_hash=source_hash)
    has_secure_raster_candidate = any(str(item["watermarkVersion"]) in SECURE_RASTER_WATERMARK_VERSIONS for item in candidates)
    has_vector_candidate = any(str(item["watermarkVersion"]) not in SECURE_RASTER_WATERMARK_VERSIONS for item in candidates)
    pdf_observations = _pdf_micro_observations(evidence_path) if is_pdf and has_vector_candidate else None
    image_analysis = _prepare_image_analysis(evidence_path, original_pdf, original_page_index) if not is_pdf and original_pdf is not None else None
    secure_pdf_analysis, secure_pdf_ocr_trace = (None, "")
    if is_pdf and has_secure_raster_candidate and original_pdf is not None:
        secure_pdf_analysis, secure_pdf_ocr_trace = _prepare_pdf_raster_analysis(
            evidence_path, original_pdf, original_page_index,
        )
    digital_fingerprint_id = _pdf_digital_fingerprint_id(evidence_path) if is_pdf else ""
    detections: list[Detection] = []
    for candidate in candidates:
        version = str(candidate["watermarkVersion"])
        if version not in SUPPORTED_WATERMARK_VERSIONS:
            continue
        expected_trace = str(candidate["traceCode"])
        visible_match = bool((trace and trace == expected_trace) or (secure_pdf_ocr_trace and secure_pdf_ocr_trace == expected_trace))
        channel_results: list[ChannelResult] = []
        if is_pdf and version in SECURE_RASTER_WATERMARK_VERSIONS:
            material = derive_fingerprint_material(
                secret,
                issuance_id=str(candidate["issuanceId"]), user_id=int(candidate["userId"]),
                document_id=str(candidate["documentId"]), source_hash=str(candidate["sourceHash"]),
                watermark_version=version,
            )
            metadata_match = bool(digital_fingerprint_id and digital_fingerprint_id == material.digital_fingerprint_id)
            channel_results.extend((
                ChannelResult(
                    "visible-ocr-trace", visible_match, 1.0 if visible_match else 0.0,
                    int(visible_match), 1, "not-applicable", False,
                ),
                ChannelResult(
                    "digital-id-metadata", metadata_match, 1.0 if metadata_match else 0.0,
                    int(metadata_match), 1, "supplementary-only", False,
                    evidence={"policy": "opaque metadata never establishes attribution by itself"},
                ),
            ))
            channel_results.extend(_image_micro_results(
                secure_pdf_analysis, candidate, secret, original_page_index,
            ))
        elif is_pdf:
            diagonal_match, direct_match = expected_trace in diagonal_traces, expected_trace in direct_traces
            channel_results.extend((
                ChannelResult("visible-trace", visible_match, 1.0 if visible_match else 0.0, int(visible_match), 1, "not-applicable", False),
                ChannelResult("visible-diagonal", diagonal_match, 1.0 if diagonal_match else 0.0, int(diagonal_match), 1, "not-applicable", False),
                ChannelResult("visible-direct-trace", direct_match, 1.0 if direct_match else 0.0, int(direct_match), 1, "not-applicable", False),
            ))
            assert pdf_observations is not None
            channel_results.extend(_pdf_micro_results(*pdf_observations, candidate, secret))
            channel_results.extend(_pdf_content_perturbation_results(
                evidence_path, original_pdf, candidate, secret,
            ))
        else:
            channel_results.append(ChannelResult("visible-ocr-trace", visible_match, 1.0 if visible_match else 0.0, int(visible_match), 1, "not-applicable", False))
            channel_results.extend(_image_micro_results(image_analysis, candidate, secret, original_page_index))

        exact_visible = any(item.success and item.name.startswith("visible-") for item in channel_results)
        forensic_channels = [
            item for item in channel_results
            if not item.name.startswith("visible-") and item.name != "digital-id-metadata"
        ]
        crc_channels = [item for item in forensic_channels if item.crc_valid]
        successful_micro = [item for item in forensic_channels if item.success]
        best_micro = max((item.score for item in forensic_channels), default=0.0)
        if exact_visible and (crc_channels or successful_micro):
            verdict, confidence = "attributed", 0.995
        elif exact_visible:
            verdict, confidence = "attributed", 0.965
        elif crc_channels:
            verdict, confidence = "attributed", min(0.985, 0.965 + 0.01 * len(crc_channels))
        elif len(successful_micro) >= 2 and best_micro >= 0.62:
            verdict, confidence = "candidate", min(0.89, 0.70 + best_micro * 0.20)
        elif successful_micro:
            verdict, confidence = "candidate", min(0.82, 0.58 + best_micro * 0.24)
        else:
            if is_pdf:
                continue
            # Preserve per-channel evidence for the owner without turning weak
            # matching into an attribution. This is especially useful for a
            # damaged mobile photo where every candidate is below threshold.
            verdict, confidence = "inconclusive", min(0.49, best_micro * 0.72)
        successful_names = [item.name for item in channel_results if item.success]
        micro_success = any(
            item.success and item.name.startswith(_micro_channel_name(version))
            for item in channel_results
        )
        if micro_success and _micro_channel_name(version) not in successful_names:
            successful_names.append(_micro_channel_name(version))
        successful = tuple(successful_names)
        failed = tuple(item.name for item in channel_results if not item.success)
        active_image_analysis = secure_pdf_analysis if is_pdf and version in SECURE_RASTER_WATERMARK_VERSIONS else image_analysis
        preprocessing = active_image_analysis[2] if active_image_analysis is not None else {}
        detections.append(Detection(
            issuance_id=str(candidate["issuanceId"]), user_id=int(candidate["userId"]),
            document_id=str(candidate["documentId"]), trace_code=expected_trace, confidence=confidence,
            successful_channels=successful, failed_channels=failed, channel_results=tuple(channel_results),
            evidence={
                "sourceType": "pdf" if is_pdf else "image", "visibleTraceRecovered": bool(trace),
                "visibleTraceMatched": visible_match,
                "originalPage": int(original_page_index) + 1 if (not is_pdf or version in SECURE_RASTER_WATERMARK_VERSIONS) else None,
                "preprocessing": preprocessing, "attributionPolicy": "definitive-only-with-exact-trace-or-valid-ecc",
            },
            verdict=verdict, watermark_version=version,
        ))
    return sorted(detections, key=lambda item: item.confidence, reverse=True)[:10]


def main() -> int:
    parser = argparse.ArgumentParser(description="Admin-only recipient fingerprint forensic detector")
    parser.add_argument("--input", required=True)
    parser.add_argument("--state-db", default=os.getenv("DENT_BOT_STATE_DB", ""))
    parser.add_argument("--original", default="")
    parser.add_argument("--document-id", default="")
    parser.add_argument("--original-page", default=1, type=int)
    parser.add_argument("--requester-id", required=True, type=int)
    args = parser.parse_args()
    owner_raw = os.getenv("DENT_BOT_OWNER_TELEGRAM_ID", "").strip()
    if not owner_raw.isdigit() or int(owner_raw) != int(args.requester_id):
        raise SystemExit("Admin authorization failed")
    database = Path(args.state_db).resolve()
    if not database.is_file():
        raise SystemExit("State database is unavailable")
    state = BotState(database)
    try:
        results = detect(
            Path(args.input), state=state, secret=load_booklet_fingerprint_key(),
            original_pdf=Path(args.original) if args.original else None,
            document_id=str(args.document_id), original_page_index=max(0, int(args.original_page) - 1),
        )
    finally:
        state.close()
    print(json.dumps({"success": True, "candidates": [item.payload() for item in results]}, separators=(",", ":")))
    return 0 if results else 3


if __name__ == "__main__":
    raise SystemExit(main())
