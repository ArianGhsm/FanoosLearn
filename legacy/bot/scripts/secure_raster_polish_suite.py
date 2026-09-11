from __future__ import annotations

import argparse
import json
import math
import os
import random
import shutil
import sys
import tempfile
import time
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from dent_bot.forensic_detector import verify_expected_secure_raster
from dent_bot.pdf_fingerprint import (
    WATERMARK_VERSION,
    WatermarkIdentity,
    _intersection_area,
    _rect_area,
    _secure_page_profile,
    derive_fingerprint_material,
    file_sha256,
    secure_page_seed,
    watermark_pdf,
)


SECRET = b"secure-raster-v9-polish-test-key"
ISSUANCE_ID = "iss_secure_raster_v9_polish"
DOCUMENT_ID = "tgdoc_secure_raster_v9_polish"
USER_ID = 9201


def _font() -> Path:
    configured = os.getenv("DENT_BOT_BOOKLET_WATERMARK_FONT", "").strip()
    candidates = (
        *([Path(configured)] if configured else []),
        ROOT / "dent_bot" / "assets" / "fonts" / "B_Nazanin_Bold.ttf",
        Path(r"D:\Arian's Documents\Lessons-Works-Projects\AI-Dev\Persian Fonts\B Nazanin Bold-.ttf"),
        Path("C:/Windows/Fonts/tahoma.ttf"),
        Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
    )
    return next(path for path in candidates if path.is_file())


def _scan_like_image(width: int, height: int, seed: int) -> bytes:
    """Create a deterministic CT/MRI-like fixture without a production dependency."""
    import pymupdf

    samples = bytearray(width * height * 3)
    center_x = width * (0.50 + (seed % 3 - 1) * 0.025)
    center_y = height * 0.49
    radius_x, radius_y = width * 0.34, height * 0.40
    for y in range(height):
        normalized_y = (y - center_y) / radius_y
        for x in range(width):
            normalized_x = (x - center_x) / radius_x
            radius = math.sqrt(normalized_x * normalized_x + normalized_y * normalized_y)
            noise = ((x * 17 + y * 29 + seed * 31) % 19) - 9
            if radius > 1.04:
                value = 15 + noise // 3
            elif radius > 0.91:
                value = 222 + noise
            elif radius > 0.72:
                value = 82 + noise
            else:
                value = 116 + int(24 * math.cos((normalized_x + normalized_y) * 12)) + noise
            lesion = ((x - center_x * 1.08) / (width * 0.075)) ** 2 + ((y - center_y * 0.92) / (height * 0.055)) ** 2
            if lesion < 1.0:
                value += int((1.0 - lesion) * 62)
            value = max(0, min(255, value))
            offset = (y * width + x) * 3
            samples[offset:offset + 3] = bytes((value, value, value))
    pixmap = pymupdf.Pixmap(pymupdf.csRGB, width, height, bytes(samples), False)
    return pixmap.tobytes("jpeg", jpg_quality=90)


def make_polish_fixture(path: Path) -> None:
    import pymupdf

    scan_one = _scan_like_image(760, 720, 3)
    scan_two = _scan_like_image(520, 660, 7)
    scan_three = _scan_like_image(520, 660, 11)
    document = pymupdf.open()
    for page_index in range(10):
        page = document.new_page(width=595, height=842)
        page_number = page_index + 1
        if page_number == 1:
            page.draw_rect(page.rect, fill=(0.08, 0.19, 0.34), color=None)
            page.insert_text((62, 115), "Protected clinical booklet", fontsize=25, color=(1, 1, 1))
            page.insert_text((62, 155), "Recipient fingerprint placement fixture", fontsize=14, color=(0.82, 0.9, 1))
        elif page_number == 3:
            page.insert_text((52, 60), "CT figure — central diagnostic ROI", fontsize=18)
            page.insert_image(pymupdf.Rect(58, 96, 537, 690), stream=scan_one)
            page.insert_text((68, 720), "Figure 1. Central lesion; attribution belongs at the image periphery.", fontsize=9)
            page.insert_text((68, 752), "Clinical note: preserve the central 50% of the scan.", fontsize=10)
        elif page_number == 4:
            page.insert_text((52, 60), "MRI comparison — two diagnostic panels", fontsize=18)
            page.insert_image(pymupdf.Rect(48, 110, 286, 605), stream=scan_two)
            page.insert_image(pymupdf.Rect(309, 110, 547, 605), stream=scan_three)
            page.insert_text((55, 630), "Figure 2. Left and right panels have independent edge zones.", fontsize=9)
            page.insert_text((55, 670), "Interpretation", fontsize=14)
            for row in range(5):
                page.insert_text((62, 699 + row * 19), f"Comparison finding {row + 1}: diagnostically relevant summary.", fontsize=9)
        elif page_number in {7, 8}:
            page.insert_text((52, 60), f"Clinical figure page {page_number}", fontsize=18)
            page.insert_image(pymupdf.Rect(90, 135, 505, 645), stream=scan_two if page_number == 7 else scan_three)
            page.insert_text((94, 674), "Figure caption and legend area", fontsize=9)
            for row in range(4):
                page.insert_text((70, 720 + row * 18), f"Legend item {row + 1}: anatomy reference.", fontsize=8)
        elif page_number == 10:
            page.insert_text((52, 60), "Sparse appendix", fontsize=16)
            page.insert_text((52, 105), "A short note leaves most of this page as true whitespace.", fontsize=9)
            page.insert_text((430, 710), "Peripheral reference", fontsize=8, color=(0.45, 0.45, 0.45))
            page.insert_text((52, 790), "Sparse appendix page", fontsize=8, color=(0.65, 0.65, 0.65))
        else:
            page.insert_text((52, 60), f"Clinical heading for page {page_number}", fontsize=18)
            page.insert_text((52, 95), "Key findings", fontsize=14)
            y = 126
            for section in range(4):
                page.insert_text((52, y), f"Section {section + 1}: important first paragraph line", fontsize=10)
                y += 20
                for line in range(5):
                    page.insert_text((66, y), f"Evidence line {section + 1}.{line + 1} with explanatory clinical content.", fontsize=9)
                    y += 18
                y += 24
            page.insert_text((52, 785), f"Footer {page_number}", fontsize=7, color=(0.6, 0.6, 0.6))
    document.save(path, garbage=4, clean=True, deflate=True, use_objstms=1, no_new_id=True, reproducible=True)
    document.close()


def _ratio(rect, rects) -> float:
    return min(1.0, sum(_intersection_area(rect, item) for item in rects) / max(1.0, _rect_area(rect)))


def _legacy_v8_metrics(profile: dict[str, object], material, page_index: int, *, return_plan: bool = False):
    """Evaluate the retired v8 positions with the new objective score."""
    import pymupdf

    width, height = float(profile["width"]), float(profile["height"])
    page_kind = str(profile["profile"])
    rng = random.Random(int.from_bytes(secure_page_seed(material, page_index, b"visible-layout")[:8], "big"))
    full_count = 3 if page_kind == "image-heavy" else 4
    if page_kind == "text-heavy" and int(profile["textCharacters"]) >= 1800 and height / max(1.0, width) >= 1.45:
        full_count = 5
    mark_width = min(width * 0.58, max(220.0, width * 0.46))
    mark_height = min(72.0, max(52.0, height * 0.075))
    legacy_content = (*profile["textRects"], *profile["imageRects"])

    def footprint(x: float, y: float):
        return pymupdf.Rect(x - mark_width / 2, y - mark_height / 2, x + mark_width / 2, y + mark_height / 2)

    def old_salience(x: float, y: float) -> float:
        overlap = _ratio(footprint(x, y), legacy_content)
        edge_penalty = 0.05 if 0.15 * width < x < 0.85 * width and 0.12 * height < y < 0.88 * height else 0.0
        return overlap + edge_penalty

    candidates = []
    for y_factor in (0.10, 0.27, 0.45, 0.64, 0.83, 0.93):
        for x_factor in (0.20, 0.50, 0.80):
            x = min(width - mark_width * 0.43, max(mark_width * 0.43, width * x_factor + rng.uniform(-0.035, 0.035) * width))
            y = min(height - mark_height * 0.42, max(mark_height * 0.42, height * y_factor + rng.uniform(-0.025, 0.025) * height))
            candidates.append((old_salience(x, y), x, y))
    edge_candidates = [item for item in candidates if item[2] <= height * 0.16 or item[2] >= height * 0.86]
    selected = [min(edge_candidates or candidates)]
    if page_kind != "image-heavy":
        selected.append((old_salience(width * 0.5, height * 0.52), width * 0.5, height * 0.52))
    for item in sorted(candidates):
        if len(selected) >= full_count:
            break
        _score, x, y = item
        if all((x - old_x) ** 2 + (y - old_y) ** 2 >= (height * 0.15) ** 2 for _old_score, old_x, old_y in selected):
            selected.append(item)
    while len(selected) < full_count:
        selected.append(sorted(candidates)[len(selected)])
    full_rects = [footprint(x, y) for _score, x, y in selected[:full_count]]
    image_roi_rects = tuple(profile.get("imageRoiRects") or ())
    key_rects = (*profile.get("headingRects", ()), *profile.get("firstLineRects", ()))
    compact_roi = 0.0
    dominant = None
    if page_kind == "image-heavy" and profile["imageRects"]:
        dominant = max(profile["imageRects"], key=_rect_area)
        compact = pymupdf.Rect(
            (dominant.x0 + dominant.x1) / 2 - 60, (dominant.y0 + dominant.y1) / 2 - 12,
            (dominant.x0 + dominant.x1) / 2 + 60, (dominant.y0 + dominant.y1) / 2 + 12,
        )
        compact_roi = _ratio(compact, image_roi_rects)
    metrics = {
        "maxKeyContentOverlap": round(max((_ratio(rect, key_rects) for rect in full_rects), default=0.0), 4),
        "maxImageRoiOverlap": round(max((_ratio(rect, image_roi_rects) for rect in full_rects), default=0.0), 4),
        "compactImageRoiOverlap": round(compact_roi, 4),
    }
    if not return_plan:
        return metrics
    full_marks = []
    for index, (_score, x, y) in enumerate(selected[:full_count]):
        is_center = page_kind != "image-heavy" and index == 1
        opacity_range = (0.16, 0.20) if is_center else (0.20, 0.27) if index == 0 else (0.18, 0.24)
        rect = footprint(x, y)
        full_marks.append({
            "x": x,
            "y": y,
            "angle": rng.choice((-1.0, 1.0)) * rng.uniform(15.0, 30.0),
            "opacity": rng.uniform(*opacity_range),
            "fontSize": rng.uniform(13.8, 16.2) * (1.04 if page_kind == "cover" else 1.0),
            "tone": rng.uniform(-0.018, 0.018),
            "placementScore": old_salience(x, y),
            "textOverlap": _ratio(rect, profile.get("lineRects") or profile["textRects"]),
            "keyOverlap": _ratio(rect, key_rects),
            "roiOverlap": _ratio(rect, image_roi_rects),
        })
    compact_count = 1 if page_kind == "image-heavy" else 2
    compact_marks = []
    for index in range(compact_count):
        image_anchor = dominant is not None and index == 0
        compact_marks.append({
            "x": (dominant.x0 + dominant.x1) / 2 if image_anchor else width * (0.50 if index == 0 else (0.22 if page_index % 2 else 0.78)),
            "y": (dominant.y0 + dominant.y1) / 2 if image_anchor else height * (0.52 if index == 0 else 0.91),
            "angle": rng.choice((-1.0, 1.0)) * rng.uniform(15.0, 27.0),
            "opacity": rng.uniform(0.14, 0.18) if image_anchor else rng.uniform(0.12, 0.18),
            "fontSize": rng.uniform(8.2, 9.6) if image_anchor else rng.uniform(7.5, 9.2),
            "contrastOutline": image_anchor,
            "roiOverlap": compact_roi if image_anchor else 0.0,
            "region": "image-center-v8" if image_anchor else "page",
        })
    return tuple(full_marks), tuple(compact_marks)


def _render_page(document_path: Path, page_index: int, destination: Path, dpi: int = 180) -> None:
    import pymupdf

    with pymupdf.open(document_path) as document:
        document[page_index].get_pixmap(matrix=pymupdf.Matrix(dpi / 72, dpi / 72), alpha=False).save(destination)


def _transforms(source_png: Path, root: Path) -> dict[str, Path]:
    import cv2
    import numpy as np

    image = cv2.imread(str(source_png), cv2.IMREAD_COLOR)
    if image is None:
        raise RuntimeError("Representative page render failed")
    height, width = image.shape[:2]
    results = {"original": source_png}

    def save(name: str, value, quality: int = 90) -> None:
        path = root / f"{name}.jpg"
        cv2.imwrite(str(path), value, [cv2.IMWRITE_JPEG_QUALITY, quality])
        results[name] = path

    save("jpeg-q60", image, 60)
    save("grayscale", cv2.cvtColor(image, cv2.COLOR_BGR2GRAY), 88)
    down = cv2.resize(image, (int(width * 0.70), int(height * 0.70)), interpolation=cv2.INTER_AREA)
    save("downscale-70", down, 88)
    screenshot = cv2.resize(cv2.resize(image, (int(width * 0.82), int(height * 0.82)), interpolation=cv2.INTER_AREA), (width, height), interpolation=cv2.INTER_LINEAR)
    save("screenshot-resample", screenshot, 82)
    save("slight-blur", cv2.GaussianBlur(image, (3, 3), 0.65), 88)
    for name, fraction in (("crop-edge-small", 0.04), ("crop-moderate", 0.10)):
        cropped = image[int(height * fraction):int(height * (1 - fraction)), int(width * fraction):int(width * (1 - fraction))]
        save(name, cv2.resize(cropped, (width, height), interpolation=cv2.INTER_LINEAR), 82)
    matrix = cv2.getRotationMatrix2D((width / 2, height / 2), 2.0, 0.99)
    save("slight-affine", cv2.warpAffine(image, matrix, (width, height), borderValue=(255, 255, 255)), 82)
    scan = cv2.resize(image, (int(width * 0.84), int(height * 0.84)), interpolation=cv2.INTER_AREA)
    scan = cv2.GaussianBlur(scan, (3, 3), 0.85)
    noise = np.random.default_rng(1402).normal(0, 2.8, scan.shape).astype(np.int16)
    scan = np.clip(scan.astype(np.int16) + noise, 0, 255).astype(np.uint8)
    scan = cv2.resize(scan, (width, height), interpolation=cv2.INTER_LINEAR)
    save("print-scan", scan, 68)
    return results


def _structure(path: Path, source: Path) -> dict[str, object]:
    import pymupdf

    with pymupdf.open(path) as document, pymupdf.open(source) as original:
        raw = path.read_bytes()
        return {
            "pageCount": document.page_count,
            "sourcePageCount": original.page_count,
            "contentStreamsPerPage": [len(page.get_contents()) for page in document],
            "imagesPerPage": [len(page.get_images(full=True)) for page in document],
            "liveTextCharacters": sum(len(page.get_text("text").strip()) for page in document),
            "annotations": sum(1 for page in document for _annotation in (page.annots() or ())),
            "embeddedFiles": document.embfile_count(),
            "hasForbiddenCatalogKeys": any(key in raw for key in (b"/AcroForm", b"/OpenAction", b"/JavaScript", b"/EmbeddedFiles", b"/OCProperties")),
            "containsExactOriginalBytes": source.read_bytes() in raw,
        }


def run(output_pdf: Path, report_path: Path) -> dict[str, object]:
    output_pdf.parent.mkdir(parents=True, exist_ok=True)
    report_path.parent.mkdir(parents=True, exist_ok=True)
    identity = WatermarkIdentity("کاربر آزمایشی امن", "0012345678", "09123456789")
    with tempfile.TemporaryDirectory(prefix="dent-secure-raster-v9-polish-") as directory:
        root = Path(directory)
        source = root / "polish-source.pdf"
        make_polish_fixture(source)
        source_hash = file_sha256(source)
        material = derive_fingerprint_material(
            SECRET,
            issuance_id=ISSUANCE_ID,
            user_id=USER_ID,
            document_id=DOCUMENT_ID,
            source_hash=source_hash,
        )
        decoy = derive_fingerprint_material(
            SECRET,
            issuance_id="iss_secure_raster_v9_decoy1",
            user_id=USER_ID + 1,
            document_id=DOCUMENT_ID,
            source_hash=source_hash,
        )
        import pymupdf

        with pymupdf.open(source) as document:
            profiles = [_secure_page_profile(page, index) for index, page in enumerate(document)]
        legacy_metrics = {
            str(index + 1): _legacy_v8_metrics(profile, material, index)
            for index, profile in enumerate(profiles)
            if index + 1 in {3, 4, 5, 6, 9}
        }
        # Same renderer, source, identity and environment; only the retired v8
        # placement function is substituted for a fair performance/size delta.
        import dent_bot.pdf_fingerprint as fingerprint_module

        legacy_output = root / "legacy-placement.pdf"
        active_plan = fingerprint_module._secure_visible_plan
        fingerprint_module._secure_visible_plan = lambda profile, value, index: _legacy_v8_metrics(
            profile, value, index, return_plan=True,
        )
        try:
            legacy_generation = fingerprint_module.watermark_pdf(
                source,
                legacy_output,
                identity=identity,
                material=material,
                font_path=_font(),
                qpdf_binary=shutil.which("qpdf") or "",
            )
        finally:
            fingerprint_module._secure_visible_plan = active_plan
        started = time.perf_counter()
        generation = watermark_pdf(
            source,
            output_pdf,
            identity=identity,
            material=material,
            font_path=_font(),
            qpdf_binary=shutil.which("qpdf") or "",
        )
        observed_wall = time.perf_counter() - started
        structure = _structure(output_pdf, source)
        page_profiles = {str(item["page"]): item for item in generation["pageProfiles"]}
        representative_pages = {"ct": 3, "mri": 4, "text": 5, "whitespace": 10}
        transformations: dict[str, object] = {}
        false_attribution = {}
        for label, page_number in representative_pages.items():
            page_root = root / f"page-{page_number}"
            page_root.mkdir()
            rendered = page_root / "original.png"
            _render_page(output_pdf, page_number - 1, rendered)
            transformations[label] = {}
            for name, evidence in _transforms(rendered, page_root).items():
                verification = verify_expected_secure_raster(
                    evidence,
                    original_pdf=source,
                    secret=SECRET,
                    issuance_id=material.issuance_id,
                    user_id=USER_ID,
                    document_id=DOCUMENT_ID,
                    source_hash=source_hash,
                    watermark_version=WATERMARK_VERSION,
                    original_page_index=page_number - 1,
                )
                transformations[label][name] = {
                    "detected": verification["detected"],
                    "score": verification["perPage"][0]["score"],
                    "confidence": verification["confidence"],
                    "eccStatus": verification["perPage"][0]["eccStatus"],
                }
            decoy_result = verify_expected_secure_raster(
                rendered,
                original_pdf=source,
                secret=SECRET,
                issuance_id=decoy.issuance_id,
                user_id=USER_ID + 1,
                document_id=DOCUMENT_ID,
                source_hash=source_hash,
                watermark_version=WATERMARK_VERSION,
                original_page_index=page_number - 1,
            )
            false_attribution[label] = {
                "detected": decoy_result["detected"],
                "score": decoy_result["perPage"][0]["score"],
                "confidence": decoy_result["confidence"],
            }
        full_pdf_verification = verify_expected_secure_raster(
            output_pdf,
            original_pdf=source,
            secret=SECRET,
            issuance_id=material.issuance_id,
            user_id=USER_ID,
            document_id=DOCUMENT_ID,
            source_hash=source_hash,
            watermark_version=WATERMARK_VERSION,
        )
        temp_orphans = [
            path.name for path in output_pdf.parent.iterdir()
            if path.name.startswith(output_pdf.name + ".") and path.suffix != ".pdf"
        ]
        failures = []
        for page_number in (3, 4):
            if float(page_profiles[str(page_number)]["compactImageRoiOverlap"]) > 0.001:
                failures.append(f"compact-trace-crosses-roi:page-{page_number}")
        for page_number in (5, 6, 9):
            # A tiny geometric edge touch is harmless and can move by a pixel
            # after font/image compression. More than 3% of a full mark over a
            # heading or paragraph start is a placement regression.
            if float(page_profiles[str(page_number)]["maxKeyContentOverlap"]) > 0.03:
                failures.append(f"key-content-overlap-too-high:page-{page_number}")
        if not full_pdf_verification["detected"] or full_pdf_verification["validEccPages"] < 8:
            failures.append("full-pdf-ecc-verification")
        light_names = ("original", "jpeg-q60", "grayscale", "downscale-70", "screenshot-resample", "slight-blur", "crop-edge-small", "slight-affine")
        for label, outcomes in transformations.items():
            for name in light_names:
                if not outcomes[name]["detected"]:
                    failures.append(f"light-transform-not-detected:{label}:{name}")
        if any(item["detected"] for item in false_attribution.values()):
            failures.append("decoy-false-attribution")
        if structure["pageCount"] != structure["sourcePageCount"]:
            failures.append("page-count")
        if any(value != 1 for value in structure["contentStreamsPerPage"]) or any(value != 1 for value in structure["imagesPerPage"]):
            failures.append("secure-raster-structure")
        if structure["liveTextCharacters"] or structure["annotations"] or structure["embeddedFiles"] or structure["hasForbiddenCatalogKeys"] or structure["containsExactOriginalBytes"]:
            failures.append("forbidden-pdf-content")
        if temp_orphans:
            failures.append("temporary-orphans")
        report = {
            "success": not failures,
            "version": WATERMARK_VERSION,
            "scope": "ROI-aware visible placement, text candidate scoring, numeric expected-issuance verifier",
            "generation": generation,
            "sameRunLegacyPlacementGeneration": {
                "wallSeconds": legacy_generation["wallSeconds"],
                "cpuSeconds": legacy_generation["cpuSeconds"],
                "peakRssBytes": legacy_generation["peakRssBytes"],
                "bytes": legacy_generation["bytes"],
            },
            "observedWallSeconds": round(observed_wall, 4),
            "structure": structure,
            "placementBeforeV8": legacy_metrics,
            "placementAfterV9": {key: page_profiles[key] for key in ("3", "4", "5", "6", "9")},
            "representativePages": representative_pages,
            "fullPdfVerification": full_pdf_verification,
            "transformations": transformations,
            "decoyChecks": false_attribution,
            "temporaryOrphans": temp_orphans,
            "failures": failures,
            "limitations": [
                "The central image box is a conservative geometric ROI heuristic, not medical-image segmentation.",
                "Moderate crop and print-scan outcomes are reported numerically and may become inconclusive under harsher damage.",
                "A valid ECC match supports attribution; partial scores alone never produce a definitive detected result.",
            ],
        }
        report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
        return report


def main() -> int:
    parser = argparse.ArgumentParser(description="Targeted secure-raster placement and verifier regression suite")
    parser.add_argument("--output-pdf", required=True)
    parser.add_argument("--report", required=True)
    args = parser.parse_args()
    report = run(Path(args.output_pdf).resolve(), Path(args.report).resolve())
    print(json.dumps({
        "success": report["success"],
        "version": report["version"],
        "failures": report["failures"],
        "observedWallSeconds": report["observedWallSeconds"],
        "bytes": report["generation"]["bytes"],
    }, ensure_ascii=False, separators=(",", ":")))
    return 0 if report["success"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
