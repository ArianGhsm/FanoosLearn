from __future__ import annotations

import math
import re
import subprocess
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Callable

from .pdf_fingerprint import (
    LEGACY_WATERMARK_VERSION,
    PdfFingerprintError,
    SECURE_RASTER_CHANNEL_VERSION,
    WATERMARK_VERSION,
    _hamming12_decode,
    canonical_user_id,
    decode_micro_bits,
    derive_fingerprint_material,
    file_sha256,
    micro_redundant_bits,
    secure_page_prefix,
    secure_raster_symbol_layout,
)

# Ported from legacy/bot/dent_bot/forensic_detector.py. This round covers the
# PDF channels only, for the secure-raster watermark FANOOS's own worker
# produces (packages/python/fanoos_bot/pdf_fingerprint.py + the marker port in
# apps/workers/protected-media/worker.py). Deliberately NOT ported this round
# (all explicitly documented, not silently dropped):
#
# - `_perspective_normalize`, `_deskew`, `_align_image`, `_order_quad`,
#   `_image_micro_results`, `_image_hidden_score`, `_render_page`,
#   `_trace_from_image` -- these import numpy/OpenCV and treat evidence as an
#   arbitrary photograph needing perspective/homography correction. Out of
#   scope per the owner's explicit instruction: no step in this port may turn
#   the PDF into a photograph. `_secure_raster_micro_results` below carries
#   the same bit-recovery/ECC-fusion *algorithm* as `_image_micro_results`
#   (thresholds, weighted fusion, bounded correction -- unchanged numbers),
#   translated from numpy to pure Python/Pillow, and compares a native
#   pdftoppm render of the evidence against a native render of the original
#   with only proportional resizing (never perspective/rotation/homography).
# - `_pdf_micro_observations`/`_pdf_micro_results` (vector `page.get_drawings()`
#   extraction) and `_pdf_trace_channels`/`extract_visible_trace_from_pdf`
#   (PDF text-layer/content-stream string search): both are structurally dead
#   for FANOOS's own output. FANOOS's worker rasterizes every page to one
#   embedded image (no vector shapes, no text layer at all), which is exactly
#   what legacy's own `test_pdf_watermark_is_secure_raster_without_extractable_identity`
#   asserts about the same secure-raster scheme. Porting these verbatim would
#   add a channel that can never succeed against a genuine FANOOS file.
# - `_pdf_content_perturbation_results`: legacy's own guard
#   (`if version not in HARDENED_WATERMARK_VERSIONS: return ()`) already
#   means this never fires for the secure-raster version FANOOS uses.
# - `_pdf_digital_fingerprint_id` (a PDF metadata keyword) and the visible-OCR
#   trace channel: both need marker-side or dependency changes FANOOS does
#   not have yet (the marker writes no PDF metadata; OCR needs tesseract).
#   Left for a future round; noted here rather than silently absent.
#
# Candidates come from the platform's own delivery records
# (content_delivery_issuances / protected_media_jobs via
# ProtectedMediaForensicService), never from bot-local state like legacy's
# BotState -- see detect()'s `candidates` parameter.


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
    user_id: str
    document_id: str
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
        # Never include a raw phone number or any personal identifier here --
        # user_id is the platform's opaque account identifier, not PII, and
        # is the one thing this feature exists to report.
        return {
            "issuanceId": self.issuance_id,
            "userId": self.user_id,
            "documentId": self.document_id,
            "confidence": round(self.confidence, 4),
            "verdict": self.verdict,
            "channels": list(self.successful_channels),
            "failedChannels": list(self.failed_channels),
            "channelResults": [item.payload(self.issuance_id) for item in self.channel_results],
            "evidence": self.evidence,
            "watermarkVersion": self.watermark_version,
        }


class ForensicDetectorError(RuntimeError):
    def __init__(self, code: str, message: str):
        super().__init__(message)
        self.code = code


def _is_pdf(path: Path) -> bool:
    if path.suffix.lower() == ".pdf":
        return True
    with path.open("rb") as stream:
        return stream.read(5) == b"%PDF-"


def _symbol_layouts(page_width: float, page_height: float, material, page_index: int):
    """FANOOS only ever produces SECURE_RASTER_WATERMARK_VERSIONS candidates
    (worker.py always marks with WATERMARK_VERSION), so this collapses
    legacy's multi-scheme dispatcher to the one live path; the layout itself
    (secure_raster_symbol_layout) is the verbatim-ported function.
    """
    return secure_raster_symbol_layout(page_width, page_height, material, page_index)


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


def _percentile(values: list[float], q: float) -> float:
    """Pure-Python equivalent of numpy.percentile's default linear
    interpolation, so the thresholds ported from _image_micro_results (which
    were calibrated using numpy) behave identically without a numpy
    dependency.
    """
    if not values:
        return 0.0
    ordered = sorted(values)
    n = len(ordered)
    if n == 1:
        return float(ordered[0])
    rank = (q / 100.0) * (n - 1)
    lower = int(math.floor(rank))
    upper = int(math.ceil(rank))
    if lower == upper:
        return float(ordered[lower])
    fraction = rank - lower
    return float(ordered[lower]) + (float(ordered[upper]) - float(ordered[lower])) * fraction


def _sample_mean(image, x: int, y: int, radius: int) -> float:
    """Pure-Pillow equivalent of legacy's numpy-based _sample_mean: the mean
    pixel value in a (2*radius+1) square window centered on (x, y).
    """
    from PIL import ImageStat

    width, height = image.size
    x0, x1 = max(0, x - radius), min(width, x + radius + 1)
    y0, y1 = max(0, y - radius), min(height, y + radius + 1)
    if x0 >= x1 or y0 >= y1:
        return 0.0
    return float(ImageStat.Stat(image.crop((x0, y0, x1, y1))).mean[0])


def _byte_bit_agreement(first: bytes, second: bytes) -> float:
    compared = min(len(first), len(second)) * 8
    if compared <= 0:
        return 0.0
    differences = sum((left ^ right).bit_count() for left, right in zip(first, second))
    return max(0.0, (compared - differences) / compared)


def _secure_raster_micro_results(analysis, material, page_index: int) -> tuple[ChannelResult, ...]:
    """Numpy-free port of legacy's `_image_micro_results`, secure-raster
    branch only. Every threshold, the adaptive-percentile signal/margin
    gates, the square-root-weighted six-symbol fusion and the bounded
    (<=3 source bit) nearest-codeword correction are unchanged from legacy --
    only the array backend (Pillow/pure Python instead of numpy) differs.
    """
    if analysis is None:
        return ()
    diff, (page_width, page_height) = analysis
    width, height = diff.size
    expected_prefix = secure_page_prefix(material, page_index)
    radius = max(1, int(round(width / page_width * 0.45)))
    results = []
    fusion_inputs: list[tuple[list[int], list[float]]] = []
    for copy_index, layout in enumerate(_symbol_layouts(page_width, page_height, material, page_index)):
        logical: list[int | None] = [None] * len(layout)
        raw_logical: list[int] = [0] * len(layout)
        margins, values, samples_by_symbol = [], [], []
        for pair, logical_index, _expected_bit in layout:
            samples = [_sample_mean(diff, int(round(x / page_width * width)), int(round(y / page_height * height)), radius) for x, y in pair]
            samples_by_symbol.append((samples, logical_index))
            raw_logical[logical_index] = int(samples[1] >= samples[0])
            values.extend(samples)
            margins.append(abs(samples[1] - samples[0]))
        adaptive_signal = max(1.5, _percentile(values, 35) if values else 1.5)
        adaptive_margin = max(0.45, _percentile(margins, 35) if margins else 0.45)
        for samples, logical_index in samples_by_symbol:
            if max(samples) >= adaptive_signal and abs(samples[1] - samples[0]) >= adaptive_margin:
                logical[logical_index] = int(samples[1] >= samples[0])
        decoded, crc_valid, corrected = decode_micro_bits(tuple(raw_logical), material.watermark_version)
        crc_valid = bool(crc_valid and decoded == expected_prefix)
        recovered = sum(bit is not None for bit in logical)
        expected_bits = micro_redundant_bits(expected_prefix, material.watermark_version)
        matched_recovered = sum(
            bit is not None and int(bit) == expected_bits[index]
            for index, bit in enumerate(logical)
        )
        agreement = matched_recovered / max(1, recovered)
        coverage = min(1.0, recovered / max(1.0, len(layout) * 0.55))
        token_agreement = _byte_bit_agreement(decoded, expected_prefix)
        score = 1.0 if crc_valid else token_agreement * coverage
        strength_by_logical = [0.0] * len(layout)
        for (samples, logical_index) in samples_by_symbol:
            strength_by_logical[logical_index] = abs(float(samples[1]) - float(samples[0]))
        fusion_inputs.append((raw_logical, strength_by_logical))
        results.append(ChannelResult(
            name=f"{SECURE_RASTER_CHANNEL_VERSION}-copy-{copy_index + 1}",
            success=crc_valid or (score >= 0.82 and recovered >= max(24, len(layout) // 5)),
            score=score, recovered_symbols=recovered, total_symbols=len(layout),
            ecc_status="valid" if crc_valid else "partial" if recovered else "not-recovered",
            crc_valid=crc_valid, corrected_codewords=corrected,
            evidence={
                "page": page_index + 1,
                "signalThreshold": round(adaptive_signal, 4),
                "marginThreshold": round(adaptive_margin, 4),
                "matchedRecoveredSymbols": matched_recovered,
                "decodedTokenAgreement": round(token_agreement, 4),
            },
        ))
        _ = agreement  # kept for parity with legacy's evidence surface; not otherwise used
    if len(fusion_inputs) >= 2:
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
        decoded, crc_valid, corrected = decode_micro_bits(tuple(fused), material.watermark_version)
        exact_match = bool(crc_valid and decoded == expected_prefix)
        source_bit_errors = sum((left ^ right).bit_count() for left, right in zip(decoded, expected_prefix))
        # v9's verifier knows the HMAC-derived expected 40-bit codeword. A
        # bounded nearest-codeword correction of at most three source bits is
        # still a very small random acceptance region (<1e-8 per candidate)
        # and recovers common downscale/recompression damage without treating
        # an arbitrary high partial score as attribution.
        bounded_match = bool(material.watermark_version == WATERMARK_VERSION and len(decoded) == len(expected_prefix) and source_bit_errors <= 3)
        crc_valid = exact_match or bounded_match
        expected_bits = micro_redundant_bits(expected_prefix, material.watermark_version)
        matched = sum(bit == expected_bits[index] for index, bit in enumerate(fused))
        token_agreement = _byte_bit_agreement(decoded, expected_prefix)
        score = 1.0 if crc_valid else token_agreement
        results.append(ChannelResult(
            name=f"{SECURE_RASTER_CHANNEL_VERSION}-ecc-fusion",
            success=crc_valid,
            score=score,
            recovered_symbols=len(fused),
            total_symbols=len(fused),
            ecc_status="valid" if exact_match else "corrected-known-codeword" if bounded_match else "crc-failed",
            crc_valid=crc_valid,
            corrected_codewords=max(corrected, disagreement_groups, source_bit_errors if bounded_match else 0),
            evidence={
                "page": page_index + 1,
                "matchedSymbols": matched,
                "decodedTokenAgreement": round(token_agreement, 4),
                "copiesFused": len(fusion_inputs),
                "selection": "weighted-six-symbol-repetition-vote",
                "sourceBitErrors": source_bit_errors,
                "knownCodewordCorrectionBound": 3 if material.watermark_version == WATERMARK_VERSION else 0,
            },
        ))
    return tuple(results)


def _run(command: list[str], deadline: float, *, timeout_message: str, failure_message: str) -> None:
    timeout = max(0.1, deadline - time.monotonic())
    try:
        subprocess.run(command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=timeout)
    except subprocess.TimeoutExpired as exc:
        raise ForensicDetectorError("time_limit", timeout_message) from exc
    except Exception as exc:
        raise ForensicDetectorError("input_unavailable", failure_message) from exc


def _pdf_page_count(path: Path, deadline: float, *, pdfinfo: str = "pdfinfo") -> int:
    timeout = max(0.1, deadline - time.monotonic())
    try:
        out = subprocess.check_output([pdfinfo, str(path)], text=True, stderr=subprocess.DEVNULL, timeout=timeout)
    except subprocess.TimeoutExpired as exc:
        raise ForensicDetectorError("time_limit", "PDF inspection timed out") from exc
    except Exception as exc:
        raise ForensicDetectorError("input_unavailable", "PDF inspection failed") from exc
    match = re.search(r"^Pages:\s+(\d+)", out, re.M)
    return int(match.group(1)) if match else 0


def _rasterize_page(path: Path, page_index: int, dpi: int, deadline: float, work_dir: Path, *, pdftoppm: str = "pdftoppm"):
    """Render one page natively (no perspective/rotation/deskew -- this
    treats the file as a PDF page, never as an arbitrary photograph) and
    return a grayscale Pillow image plus its page size in points.
    """
    from PIL import Image

    work_dir.mkdir(parents=True, exist_ok=True)
    prefix = work_dir / f"page-{page_index}"
    page_number = str(page_index + 1)
    _run(
        [pdftoppm, "-png", "-r", str(dpi), "-f", page_number, "-l", page_number, str(path), str(prefix)],
        deadline,
        timeout_message="PDF rasterization timed out",
        failure_message="PDF rasterization failed",
    )
    produced = sorted(work_dir.glob(f"page-{page_index}*.png"))
    if not produced:
        raise ForensicDetectorError("input_unavailable", "Requested page could not be rendered")
    image = Image.open(produced[0]).convert("L")
    width_pt, height_pt = image.width * 72.0 / dpi, image.height * 72.0 / dpi
    return image, (width_pt, height_pt)


def _prepare_secure_raster_analysis(evidence_path: Path, original_path: Path, page_index: int, dpi: int, deadline: float, work_dir: Path, *, pdftoppm: str = "pdftoppm"):
    """Render the evidence and original pages and diff them. Sizes are
    reconciled only by proportional resizing (never crop/rotate/perspective)
    -- this is a plain forwarded-file comparison, not photograph forensics.
    """
    from PIL import Image, ImageChops

    evidence_image, _evidence_size = _rasterize_page(evidence_path, page_index, dpi, deadline, work_dir / "evidence", pdftoppm=pdftoppm)
    original_image, page_size = _rasterize_page(original_path, page_index, dpi, deadline, work_dir / "original", pdftoppm=pdftoppm)
    if evidence_image.size != original_image.size:
        evidence_image = evidence_image.resize(original_image.size, Image.BICUBIC)
    diff = ImageChops.difference(evidence_image, original_image)
    return diff, page_size


def detect(
    evidence_path: Path,
    *,
    candidates: list[dict],
    secret: bytes,
    fetch_original: Callable[[dict], Path],
    deadline: float,
    dpi: int = 180,
    max_pages: int = 20,
    qpdf_binary: str = "qpdf",
    pdftoppm_binary: str = "pdftoppm",
    pdfinfo_binary: str = "pdfinfo",
) -> list[Detection]:
    """Attribute a suspected leaked PDF to one of the recipients it was
    actually issued to.

    `candidates` is the platform's own delivery record for one resource in
    one workspace (ProtectedMediaForensicService.candidates), never bot-local
    state and never every delivery in the system -- each item is
    {issuanceId, userId, documentId, objectId, resourceVersionId,
    classification}. `fetch_original(candidate)` returns a local path to that
    candidate's original source bytes (typically backed by
    ProtectedMediaForensicService.originalSource, grouped/cached by the
    caller so the same (object, version) is fetched once).
    """
    import tempfile

    if len(secret) < 32:
        raise ForensicDetectorError("internal_error", "Fingerprint key is not configured")
    if not candidates:
        # Nothing to compare against -- fail fast without even touching the
        # uploaded file (no point validating/rasterizing evidence that no
        # candidate delivery could ever match).
        return []
    evidence_path = evidence_path.resolve()
    if not evidence_path.is_file():
        raise ForensicDetectorError("input_unavailable", "Evidence file does not exist")
    with evidence_path.open("rb") as stream:
        if stream.read(5) != b"%PDF-":
            raise ForensicDetectorError("input_unavailable", "Evidence file is not a PDF")
    if not _is_pdf(evidence_path):
        raise ForensicDetectorError("input_unavailable", "Evidence file is not a PDF")
    _run(
        [qpdf_binary, "--check", str(evidence_path)], deadline,
        timeout_message="PDF validation timed out", failure_message="Evidence PDF is corrupt or invalid",
    )
    evidence_pages = min(_pdf_page_count(evidence_path, deadline, pdfinfo=pdfinfo_binary), max(1, max_pages))
    if evidence_pages < 1:
        raise ForensicDetectorError("input_unavailable", "Evidence page count is unavailable")

    with tempfile.TemporaryDirectory(prefix="fanoos-forensic-") as work:
        work_dir = Path(work)
        original_cache: dict[tuple[str, str], Path] = {}
        detections: list[Detection] = []
        for candidate in candidates:
            if time.monotonic() > deadline:
                raise ForensicDetectorError("time_limit", "Forensic analysis timed out")
            object_key = (str(candidate.get("objectId") or ""), str(candidate.get("resourceVersionId") or ""))
            original_path = original_cache.get(object_key)
            if original_path is None:
                original_path = fetch_original(candidate).resolve()
                if not original_path.is_file():
                    continue
                original_cache[object_key] = original_path
            source_hash = file_sha256(original_path)
            try:
                material = derive_fingerprint_material(
                    secret,
                    issuance_id=f"iss_{candidate['issuanceId']}",
                    user_id=canonical_user_id(str(candidate["userId"])),
                    document_id=str(candidate["documentId"]),
                    source_hash=source_hash,
                    watermark_version=WATERMARK_VERSION,
                )
            except PdfFingerprintError:
                continue
            best_channels: tuple[ChannelResult, ...] = ()
            best_score = -1.0
            best_page = 0
            original_pages = _pdf_page_count(original_path, deadline, pdfinfo=pdfinfo_binary)
            page_span = min(evidence_pages, max(1, original_pages), max_pages)
            for page_index in range(page_span):
                if time.monotonic() > deadline:
                    raise ForensicDetectorError("time_limit", "Forensic analysis timed out")
                analysis = _prepare_secure_raster_analysis(evidence_path, original_path, page_index, dpi, deadline, work_dir, pdftoppm=pdftoppm_binary)
                channels = _secure_raster_micro_results(analysis, material, page_index)
                score = max((item.score for item in channels), default=0.0)
                if score > best_score:
                    best_channels, best_score, best_page = channels, score, page_index
                if any(item.crc_valid for item in channels):
                    break
            crc_channels = [item for item in best_channels if item.crc_valid]
            successful = [item for item in best_channels if item.success]
            if crc_channels:
                verdict, confidence = "attributed", min(0.985, 0.965 + 0.01 * len(crc_channels))
            elif len(successful) >= 2 and best_score >= 0.62:
                verdict, confidence = "candidate", min(0.89, 0.70 + best_score * 0.20)
            elif successful:
                verdict, confidence = "candidate", min(0.82, 0.58 + best_score * 0.24)
            else:
                continue
            successful_names = tuple(item.name for item in best_channels if item.success)
            failed_names = tuple(item.name for item in best_channels if not item.success)
            detections.append(Detection(
                issuance_id=str(candidate["issuanceId"]), user_id=str(candidate["userId"]),
                document_id=str(candidate["documentId"]), confidence=confidence,
                successful_channels=successful_names, failed_channels=failed_names,
                channel_results=best_channels,
                evidence={"sourceType": "pdf", "bestPage": best_page + 1, "attributionPolicy": "definitive-only-with-valid-ecc"},
                verdict=verdict, watermark_version=material.watermark_version,
            ))
        return sorted(detections, key=lambda item: item.confidence, reverse=True)[:10]
