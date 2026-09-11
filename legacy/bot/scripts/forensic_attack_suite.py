from __future__ import annotations

import argparse
import hashlib
import json
import math
import os
import shutil
import subprocess
import sys
import tempfile
import threading
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from dent_bot.forensic_detector import detect  # noqa: E402
from dent_bot.pdf_fingerprint import (  # noqa: E402
    APPEND_HARDENED_WATERMARK_VERSION,
    CONTENT_TEXT_CHANNEL_VERSION,
    CONTENT_VISUAL_CHANNEL_VERSION,
    INTERLEAVED_WATERMARK_VERSION,
    PREVIOUS_WATERMARK_VERSION,
    WatermarkIdentity,
    _pdf_content_tokens,
    content_stream_signature,
    derive_fingerprint_material,
    file_sha256,
    micro_dot_positions,
    watermark_pdf,
)
from dent_bot.state import BotState  # noqa: E402
from scripts.benchmark_booklet_pipeline import make_fixture  # noqa: E402


SECRET = b"forensic-regression-key-material!"
DOCUMENT_ID = "tgdoc_forensic_regression"
SOURCE_CHAT_ID = -1001234567890


def _font() -> Path:
    configured = os.getenv("DENT_BOT_BOOKLET_WATERMARK_FONT", "").strip()
    candidates = (
        *([Path(configured)] if configured else []),
        ROOT / "dent_bot" / "assets" / "fonts" / "B_Nazanin_Bold.ttf",
        Path("/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"),
        Path("C:/Windows/Fonts/tahoma.ttf"),
        Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
    )
    return next(path for path in candidates if path.is_file())


def _rss_bytes() -> int:
    status = Path("/proc/self/status")
    if status.is_file():
        for line in status.read_text(encoding="ascii", errors="ignore").splitlines():
            if line.startswith("VmRSS:"):
                return int(line.split()[1]) * 1024
    try:
        import psutil

        return int(psutil.Process().memory_info().rss)
    except (ImportError, OSError):
        return 0


class PeakSampler:
    def __init__(self) -> None:
        self.peak = _rss_bytes()
        self._stop = threading.Event()
        self._thread = threading.Thread(target=self._run, daemon=True)

    def _run(self) -> None:
        while not self._stop.wait(0.01):
            self.peak = max(self.peak, _rss_bytes())

    def __enter__(self):
        self._thread.start()
        return self

    def __exit__(self, *_args) -> None:
        self._stop.set()
        self._thread.join(timeout=1)
        self.peak = max(self.peak, _rss_bytes())


def _register_source(state: BotState) -> int:
    state.replace_protected_media_message(SOURCE_CHAT_ID, 2821, [{
        "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT regression",
        "courseTag": "ent", "term": 7, "sessionNo": 4, "telegramMethod": "sendDocument",
        "fileId": "fixture-source", "fileUniqueId": "fixture-source-unique",
        "fileName": "fixture.pdf", "mimeType": "application/pdf", "caption": "regression",
    }])
    return int(state.protected_media_for(course_code="ENT", term=7, session_no=4, content_kind="booklet")[0]["id"])


def _issue(state: BotState, source_id: int, source: Path, output: Path, *, version: str, issuance_id: str, user_id: int):
    source_hash = file_sha256(source)
    material = derive_fingerprint_material(
        SECRET, issuance_id=issuance_id, user_id=user_id, document_id=DOCUMENT_ID,
        source_hash=source_hash, watermark_version=version,
    )
    state.create_booklet_issuance(
        issuance_id=issuance_id, user_id=user_id, source_id=source_id, document_id=DOCUMENT_ID,
        trace_code=material.trace_code, fingerprint_hash=material.fingerprint_hash,
        watermark_version=version, source_hash=source_hash,
    )
    started_wall, started_cpu = time.perf_counter(), time.process_time()
    baseline_rss = _rss_bytes()
    with PeakSampler() as sampler:
        report = watermark_pdf(
            source, output,
            identity=WatermarkIdentity("کاربر آزمایشی", "0012345678", "09123456789"),
            material=material, font_path=_font(), qpdf_binary=shutil.which("qpdf") or "",
        )
    state.mark_booklet_issuance_sent(issuance_id, telegram_file_id=f"fixture-{version}-{user_id}")
    return material, {
        **report,
        "wallSeconds": round(time.perf_counter() - started_wall, 4),
        "cpuSeconds": round(time.process_time() - started_cpu, 4),
        "peakRssBytes": sampler.peak,
        "peakRssDeltaBytes": max(0, sampler.peak - baseline_rss),
        "inputBytes": source.stat().st_size,
        "outputBytes": output.stat().st_size,
        "sizeRatio": round(output.stat().st_size / source.stat().st_size, 4),
    }


def _signature_entries(data: bytes) -> list[tuple[bytes, int, int]]:
    return [
        (kind.encode("ascii") + b":" + raw, start, end)
        for kind, raw, start, end in _pdf_content_tokens(data)
        if kind != "number"
    ]


def _source_chunks_from_personalized(original_streams: list[bytes], personalized_streams: list[bytes]):
    """Recover source-shaped chunks while retaining personalized numbers.

    This intentionally models an attacker who owns the original and strips all
    operators not structurally present in it. The returned chunks therefore
    remove visible/micro recipient operators but retain numeric perturbations
    injected into the source operators themselves.
    """
    targets = [(_signature_entries(data), data) for data in personalized_streams]
    cursors = [0 for _target in targets]
    recovered: list[bytes] = []
    matched = 0
    exact_original = 0
    for original in original_streams:
        signature = list(content_stream_signature(original))
        if not signature:
            continue
        found = None
        for target_index, (entries, data) in enumerate(targets):
            target_signature = [entry[0] for entry in entries]
            for offset in range(cursors[target_index], len(entries) - len(signature) + 1):
                if target_signature[offset:offset + len(signature)] == signature:
                    found = (target_index, offset, entries, data)
                    break
            if found is not None:
                break
        if found is None:
            continue
        target_index, offset, entries, data = found
        start = entries[offset][1]
        end = entries[offset + len(signature) - 1][2]
        chunk = data[start:end]
        recovered.append(chunk)
        matched += 1
        exact_original += int(chunk.strip() == original.strip())
        cursors[target_index] = offset + len(signature)
    return recovered, matched, exact_original


def strip_security_streams_by_original_structure(source: Path, personalized: Path, output: Path) -> dict[str, object]:
    import pymupdf

    matched = exact_original = original_count = 0
    with pymupdf.open(str(source)) as original, pymupdf.open(str(personalized)) as document:
        for page_index in range(min(original.page_count, document.page_count)):
            source_streams = [original.xref_stream(xref) for xref in original[page_index].get_contents()]
            target_xrefs = tuple(document[page_index].get_contents())
            target_streams = [document.xref_stream(xref) for xref in target_xrefs]
            chunks, page_matched, page_exact = _source_chunks_from_personalized(source_streams, target_streams)
            original_count += len(source_streams)
            matched += page_matched
            exact_original += page_exact
            if not chunks or not target_xrefs:
                continue
            keep_xref = int(target_xrefs[0])
            document.update_stream(keep_xref, b"\n".join(chunks))
            document.xref_set_key(document[page_index].xref, "Contents", f"{keep_xref} 0 R")
        document.save(str(output), garbage=4, clean=False, deflate=True, use_objstms=1)
    return {
        "available": True,
        "originalStreams": original_count,
        "matchedSourceChunks": matched,
        "exactOriginalChunks": exact_original,
    }


def _rewrite_pdf(source: Path, output: Path, mode: str, *, original_pdf: Path | None = None) -> dict[str, object]:
    import pymupdf

    if mode == "qpdf-rewrite":
        binary = shutil.which("qpdf")
        if not binary:
            return {"available": False, "reason": "qpdf unavailable"}
        result = subprocess.run([binary, str(source), str(output)], capture_output=True, timeout=90, check=False)
        return {"available": result.returncode == 0, "returnCode": result.returncode}
    if mode == "ghostscript-rewrite":
        binary = shutil.which("gs") or shutil.which("gswin64c") or shutil.which("gswin32c")
        if not binary:
            return {"available": False, "reason": "Ghostscript unavailable"}
        result = subprocess.run([
            binary, "-q", "-dSAFER", "-dBATCH", "-dNOPAUSE", "-sDEVICE=pdfwrite",
            "-dCompatibilityLevel=1.7", f"-sOutputFile={output}", str(source),
        ], capture_output=True, timeout=180, check=False)
        return {"available": result.returncode == 0, "returnCode": result.returncode}
    if mode == "pikepdf-rewrite":
        try:
            import pikepdf
        except ImportError:
            return {"available": False, "reason": "pikepdf unavailable"}
        with pikepdf.Pdf.open(source) as document:
            document.save(output, compress_streams=True, object_stream_mode=pikepdf.ObjectStreamMode.generate)
        return {"available": True, "parser": "pikepdf/libqpdf"}
    if mode == "keep-source-operators-drop-security":
        if original_pdf is None:
            raise ValueError("original_pdf is required")
        return strip_security_streams_by_original_structure(original_pdf, source, output)
    if mode == "remove-suspected-recipient-streams" and original_pdf is not None:
        # v6 intentionally has no detachable recipient stream. Model the
        # stronger original-guided attack by reconstructing only source-shaped
        # operator chunks from the composite page stream.
        return strip_security_streams_by_original_structure(original_pdf, source, output)
    with pymupdf.open(str(source)) as document:
        touched = 0
        if mode == "remove-annotations":
            for page in document:
                for annotation in list(page.annots() or ()):
                    page.delete_annot(annotation)
                    touched += 1
        elif mode == "neutralize-all-form-xobjects":
            seen: set[int] = set()
            for page in document:
                for item in page.get_xobjects():
                    xref = int(item[0])
                    if xref not in seen:
                        seen.add(xref)
                        try:
                            document.update_stream(xref, b"q Q")
                            touched += 1
                        except RuntimeError:
                            continue
        elif mode == "remove-content-tail":
            for page in document:
                streams = page.get_contents()
                if len(streams) > 1:
                    document.update_stream(streams[-1], b"q Q")
                    touched += 1
                elif streams:
                    xref = int(streams[0])
                    data = document.xref_stream(xref)
                    marker = data.rfind(b"/DentV5")
                    start = data.rfind(b"\nq\n", 0, marker) if marker >= 0 else -1
                    end = data.find(b"\nQ\n", marker) if marker >= 0 else -1
                    if start >= 0 and end >= 0:
                        document.update_stream(xref, data[:start] + b"\nq Q\n" + data[end + 3:])
                        touched += 1
        elif mode == "remove-all-micro-streams":
            for page in document:
                for xref in page.get_contents():
                    stream = document.xref_stream(xref)
                    if stream.count(b" re") >= 90:
                        document.update_stream(xref, b"q Q")
                        touched += 1
        elif mode == "remove-suspected-recipient-streams":
            for page in document:
                for xref in page.get_contents():
                    stream = document.xref_stream(xref)
                    if b"/DentV5" in stream:
                        document.update_stream(xref, b"q Q")
                        touched += 1
        else:
            raise ValueError(mode)
        document.save(str(output), garbage=4, clean=True, deflate=True, use_objstms=1)
    return {"available": True, "touchedObjects": touched}


def _render_page(pdf: Path, output: Path, dpi: int = 220) -> None:
    import pymupdf

    with pymupdf.open(str(pdf)) as document:
        document[0].get_pixmap(matrix=pymupdf.Matrix(dpi / 72, dpi / 72), alpha=False).save(str(output))


def _image_attacks(pdf: Path, original_pdf: Path, root: Path) -> dict[str, Path]:
    import cv2
    import numpy as np

    screenshot = root / "screenshot.png"
    original_render = root / "original-render.png"
    _render_page(pdf, screenshot)
    _render_page(original_pdf, original_render)
    image = cv2.imread(str(screenshot), cv2.IMREAD_COLOR)
    original = cv2.imread(str(original_render), cv2.IMREAD_COLOR)
    if image is None or original is None:
        raise RuntimeError("fixture render failed")
    if original.shape != image.shape:
        original = cv2.resize(original, (image.shape[1], image.shape[0]))
    height, width = image.shape[:2]
    attacks = {"screenshot": screenshot}
    for quality in (60, 75, 90):
        path = root / f"jpeg-q{quality}.jpg"
        cv2.imwrite(str(path), image, [cv2.IMWRITE_JPEG_QUALITY, quality])
        attacks[f"jpeg-q{quality}"] = path
    for percent in (5, 10, 20):
        path = root / f"crop-{percent}.jpg"
        dy, dx = int(height * percent / 200), int(width * percent / 200)
        crop = image[dy:height - dy, dx:width - dx]
        cv2.imwrite(str(path), cv2.resize(crop, (width, height)), [cv2.IMWRITE_JPEG_QUALITY, 82])
        attacks[f"crop-{percent}"] = path
    rotated = root / "rotate.jpg"
    matrix = cv2.getRotationMatrix2D((width / 2, height / 2), 4.0, 0.98)
    cv2.imwrite(str(rotated), cv2.warpAffine(image, matrix, (width, height), borderValue=(255, 255, 255)), [cv2.IMWRITE_JPEG_QUALITY, 82])
    attacks["rotate"] = rotated
    resized = root / "resize.jpg"
    reduced = cv2.resize(image, (int(width * 0.62), int(height * 0.62)), interpolation=cv2.INTER_AREA)
    cv2.imwrite(str(resized), reduced, [cv2.IMWRITE_JPEG_QUALITY, 82])
    attacks["resize"] = resized
    scan = cv2.GaussianBlur(image, (3, 3), 0.7)
    noise = np.random.default_rng(1402).normal(0, 3.5, scan.shape).astype(np.int16)
    scan = np.clip(scan.astype(np.int16) + noise, 0, 255).astype(np.uint8)
    scan = cv2.convertScaleAbs(scan, alpha=0.94, beta=9)
    print_scan = root / "print-scan.jpg"
    cv2.imwrite(str(print_scan), scan, [cv2.IMWRITE_JPEG_QUALITY, 72])
    attacks["print-scan"] = print_scan
    canvas = np.full((height + 300, width + 320, 3), 225, dtype=np.uint8)
    source_points = np.float32([[0, 0], [width - 1, 0], [width - 1, height - 1], [0, height - 1]])
    target_points = np.float32([[190, 120], [width + 60, 35], [width + 145, height + 190], [70, height + 245]])
    transform = cv2.getPerspectiveTransform(source_points, target_points)
    photographed = cv2.warpPerspective(image, transform, (canvas.shape[1], canvas.shape[0]), dst=canvas, borderMode=cv2.BORDER_TRANSPARENT)
    mobile = root / "mobile-photo.jpg"
    cv2.imwrite(str(mobile), photographed, [cv2.IMWRITE_JPEG_QUALITY, 78])
    attacks["mobile-photo"] = mobile
    # Simulate an attacker who possesses the exact original. Small connected
    # components approximate targeted micro-mark removal; the full mask is the
    # destructive upper bound that replaces every changed pixel with original
    # content. These are measured limitations, not expected-proof claims.
    difference = cv2.cvtColor(cv2.absdiff(image, original), cv2.COLOR_BGR2GRAY)
    binary = (difference > 12).astype(np.uint8)
    components, labels, stats, _centroids = cv2.connectedComponentsWithStats(binary, 8)
    small_mask = np.zeros_like(binary)
    for label in range(1, components):
        if 1 <= int(stats[label, cv2.CC_STAT_AREA]) <= 28:
            small_mask[labels == label] = 1
    small_mask = cv2.dilate(small_mask, np.ones((3, 3), dtype=np.uint8), iterations=1).astype(bool)
    targeted = image.copy()
    targeted[small_mask] = original[small_mask]
    targeted_path = root / "diff-small-component-removal.png"
    cv2.imwrite(str(targeted_path), targeted)
    attacks["diff-small-component-removal"] = targeted_path
    full_mask = cv2.dilate(binary, np.ones((3, 3), dtype=np.uint8), iterations=1).astype(bool)
    destructive = image.copy()
    destructive[full_mask] = original[full_mask]
    destructive_path = root / "original-diff-mask.png"
    cv2.imwrite(str(destructive_path), destructive)
    attacks["original-diff-mask"] = destructive_path
    return attacks


def _pdf_structure(path: Path) -> dict[str, object]:
    import pymupdf

    with pymupdf.open(str(path)) as document:
        form_xrefs: set[int] = set()
        direct_recipient_streams = 0
        traces_per_page = []
        for page in document:
            form_xrefs.update(int(item[0]) for item in page.get_xobjects())
            page_traces = 0
            for xref in page.get_contents():
                stream = document.xref_stream(xref)
                direct_recipient_streams += int(b"/DentV5" in stream)
                page_traces += len(__import__("re").findall(
                    rb"TRC-[A-Z2-7]{5}-[A-Z2-7]{5}", stream,
                ))
            traces_per_page.append(page_traces)
        return {
            "pages": document.page_count,
            "xrefObjects": document.xref_length(),
            "contentStreams": sum(len(page.get_contents()) for page in document),
            "xObjects": sum(len(page.get_xobjects()) for page in document),
            "formXObjectObjects": len(form_xrefs),
            "directRecipientStreams": direct_recipient_streams,
            "directTraceInstancesPerPage": traces_per_page,
            "annotations": sum(1 for page in document for _ in (page.annots() or ())),
        }


def _content_stream_diff(first: Path, second: Path) -> dict[str, object]:
    import pymupdf

    changed = 0
    compared = 0
    pages_changed: list[int] = []
    with pymupdf.open(str(first)) as left, pymupdf.open(str(second)) as right:
        for page_index in range(min(left.page_count, right.page_count)):
            left_hashes = [hashlib.sha256(left.xref_stream(xref)).digest() for xref in left[page_index].get_contents()]
            right_hashes = [hashlib.sha256(right.xref_stream(xref)).digest() for xref in right[page_index].get_contents()]
            total = max(len(left_hashes), len(right_hashes))
            page_changed = sum(
                index >= len(left_hashes) or index >= len(right_hashes) or left_hashes[index] != right_hashes[index]
                for index in range(total)
            )
            compared += total
            changed += page_changed
            if page_changed:
                pages_changed.append(page_index + 1)
    return {
        "comparedContentStreams": compared,
        "changedContentStreams": changed,
        "changedStreamRatio": round(changed / max(1, compared), 4),
        "pagesWithDifferences": pages_changed,
    }


def _result_for(path: Path, state: BotState, issuance_id: str, original: Path, *, image: bool) -> dict[str, object]:
    results = detect(
        path, state=state, secret=SECRET,
        original_pdf=original,
        document_id=DOCUMENT_ID,
    )
    match = next((item for item in results if item.issuance_id == issuance_id), None)
    return {
        "detected": match is not None,
        "verdict": match.verdict if match else "none",
        "confidence": round(match.confidence, 4) if match else 0.0,
        "channels": [item.payload(issuance_id) for item in match.channel_results] if match else [],
    }


def _spatial_diff(first: Path, second: Path) -> dict[str, object]:
    import cv2
    import numpy as np

    a = cv2.imread(str(first), cv2.IMREAD_GRAYSCALE)
    b = cv2.imread(str(second), cv2.IMREAD_GRAYSCALE)
    if a is None or b is None:
        return {}
    if a.shape != b.shape:
        b = cv2.resize(b, (a.shape[1], a.shape[0]))
    mask = cv2.absdiff(a, b) > 12
    ys, xs = np.where(mask)
    if not len(xs):
        return {"changedPixels": 0, "tileCoverage": 0.0, "boundingBoxCoverage": 0.0}
    tile_hits = 0
    rows = columns = 10
    for row in range(rows):
        for column in range(columns):
            region = mask[row * a.shape[0] // rows:(row + 1) * a.shape[0] // rows, column * a.shape[1] // columns:(column + 1) * a.shape[1] // columns]
            tile_hits += int(region.any())
    bbox_area = (xs.max() - xs.min() + 1) * (ys.max() - ys.min() + 1)
    return {
        "changedPixels": int(mask.sum()),
        "changedPixelRatio": round(float(mask.mean()), 6),
        "tileCoverage": round(tile_hits / (rows * columns), 4),
        "boundingBoxCoverage": round(bbox_area / mask.size, 4),
    }


def _hidden_spread(material, width: float = 595, height: float = 842) -> dict[str, object]:
    channels = micro_dot_positions(width, height, material.layout_seed, 0, material.fingerprint_prefix, material.watermark_version)
    points = [point for channel in channels for point in channel]
    xs, ys = [point[0] for point in points], [point[1] for point in points]
    tiles = {(min(9, int(x / width * 10)), min(9, int(y / height * 10))) for x, y in points}
    return {
        "widthCoverage": round((max(xs) - min(xs)) / width, 4),
        "heightCoverage": round((max(ys) - min(ys)) / height, 4),
        "tileCoverage": round(len(tiles) / 100, 4),
    }


def run(output_pdf: Path, report_path: Path) -> dict[str, object]:
    output_pdf.parent.mkdir(parents=True, exist_ok=True)
    report_path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="dent-forensic-suite-") as directory:
        root = Path(directory)
        source = root / "source.pdf"
        baseline_pdf = root / "recipient-v5.pdf"
        current_pdf = output_pdf.resolve()
        second_pdf = root / "recipient-current-second.pdf"
        make_fixture(source, pages=8, profile="mixed")
        state = BotState(root / "state.sqlite3")
        try:
            source_id = _register_source(state)
            baseline, baseline_metrics = _issue(
                state, source_id, source, baseline_pdf, version=APPEND_HARDENED_WATERMARK_VERSION,
                issuance_id="iss_regressionbaseline01", user_id=7001,
            )
            current, current_metrics = _issue(
                state, source_id, source, current_pdf, version=INTERLEAVED_WATERMARK_VERSION,
                issuance_id="iss_regressioncurrent001", user_id=7002,
            )
            second, _second_metrics = _issue(
                state, source_id, source, second_pdf, version=INTERLEAVED_WATERMARK_VERSION,
                issuance_id="iss_regressioncurrent002", user_id=7003,
            )

            pdf_results = {}
            for name in (
                "remove-annotations", "neutralize-all-form-xobjects", "remove-content-tail",
                "remove-all-micro-streams", "remove-suspected-recipient-streams",
                "keep-source-operators-drop-security", "pikepdf-rewrite",
                "qpdf-rewrite", "ghostscript-rewrite",
            ):
                attacked = root / f"{name}.pdf"
                attack = _rewrite_pdf(current_pdf, attacked, name, original_pdf=source)
                pdf_results[name] = {"attack": attack}
                if attack.get("available") and attacked.is_file():
                    pdf_results[name]["detection"] = _result_for(attacked, state, current.issuance_id, source, image=False)

            image_attack_paths = _image_attacks(current_pdf, source, root)
            image_results = {
                name: _result_for(path, state, current.issuance_id, source, image=True)
                for name, path in image_attack_paths.items()
            }
            first_png, second_png, source_png = root / "first.png", root / "second.png", root / "source.png"
            _render_page(current_pdf, first_png)
            _render_page(second_pdf, second_png)
            _render_page(source, source_png)
            comparison = {
                "personalizedPair": _spatial_diff(first_png, second_png),
                "personalizedPairContentStreams": _content_stream_diff(current_pdf, second_pdf),
                "originalVsCurrent": _spatial_diff(source_png, first_png),
                "hiddenSpreadBefore": _hidden_spread(baseline),
                "hiddenSpreadAfter": _hidden_spread(current),
                "pdfStructureBefore": _pdf_structure(baseline_pdf),
                "pdfStructureAfter": _pdf_structure(current_pdf),
                "smallComponentRemovalFootprint": _spatial_diff(
                    image_attack_paths["screenshot"], image_attack_paths["diff-small-component-removal"]
                ),
                "fullOriginalDiffRemovalFootprint": _spatial_diff(
                    image_attack_paths["screenshot"], image_attack_paths["original-diff-mask"]
                ),
            }
            clean = _result_for(current_pdf, state, current.issuance_id, source, image=False)
            required_pdf = (
                "remove-annotations", "neutralize-all-form-xobjects", "remove-content-tail",
                "remove-suspected-recipient-streams", "keep-source-operators-drop-security",
                "pikepdf-rewrite", "qpdf-rewrite",
            )
            failures = [name for name in required_pdf if pdf_results[name].get("attack", {}).get("available") and not pdf_results[name].get("detection", {}).get("detected")]
            if not clean["detected"] or clean["verdict"] != "attributed":
                failures.append("clean-current")
            form_channels = pdf_results.get("neutralize-all-form-xobjects", {}).get("detection", {}).get("channels", [])
            if not any(item.get("channel") == "visible-direct-trace" and item.get("success") for item in form_channels):
                failures.append("visible-after-form-neutralization")
            stripped_channels = pdf_results.get("remove-suspected-recipient-streams", {}).get("detection", {}).get("channels", [])
            if not any(
                item.get("channel") == CONTENT_TEXT_CHANNEL_VERSION and item.get("crcValid")
                for item in stripped_channels
            ):
                failures.append("content-channel-after-recipient-stream-removal")
            source_only_channels = pdf_results.get("keep-source-operators-drop-security", {}).get("detection", {}).get("channels", [])
            if not any(
                item.get("channel") in {CONTENT_TEXT_CHANNEL_VERSION, CONTENT_VISUAL_CHANNEL_VERSION}
                and item.get("crcValid")
                for item in source_only_channels
            ):
                failures.append("content-channel-after-original-guided-stream-removal")
            if comparison["pdfStructureAfter"]["contentStreams"] != comparison["pdfStructureAfter"]["pages"]:
                failures.append("append-only-page-contents-remain")
            if comparison["hiddenSpreadAfter"]["tileCoverage"] < 0.95:
                failures.append("hidden-spatial-dispersion")
            if comparison["personalizedPair"].get("tileCoverage", 0.0) < 0.80:
                failures.append("recipient-diff-spatial-concentration")
            if comparison["personalizedPairContentStreams"].get("changedStreamRatio", 0.0) < 0.50:
                failures.append("recipient-diff-object-concentration")
            report = {
                "success": not failures,
                "watermarkVersions": {"before": APPEND_HARDENED_WATERMARK_VERSION, "after": INTERLEAVED_WATERMARK_VERSION},
                "fixture": {"pages": 8, "profile": "mixed", "sourceBytes": source.stat().st_size},
                "generation": {"before": baseline_metrics, "after": current_metrics},
                "cleanDetection": clean,
                "pdfAttacks": pdf_results,
                "imageAttacks": image_results,
                "diffResistance": comparison,
                "failures": failures,
                "limitations": [
                    "An attacker holding the exact original can use destructive pixel-level diff masking; the spread layout raises the damaged area and editing cost but does not make removal impossible.",
                    "Print-scan and mobile-photo findings are probabilistic candidates unless OCR recovers the exact trace or ECC validates.",
                ],
            }
            report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
            return report
        finally:
            state.close()


def main() -> int:
    parser = argparse.ArgumentParser(description="Recipient fingerprint attack and resource regression suite")
    parser.add_argument("--output-pdf", required=True)
    parser.add_argument("--report", required=True)
    args = parser.parse_args()
    report = run(Path(args.output_pdf), Path(args.report))
    print(json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(",", ":")))
    return 0 if report["success"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
