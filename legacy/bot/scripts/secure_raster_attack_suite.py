from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from dent_bot.forensic_detector import detect  # noqa: E402
from dent_bot.pdf_fingerprint import (  # noqa: E402
    SECURE_RASTER_CHANNEL_VERSION,
    WATERMARK_VERSION,
    WatermarkIdentity,
    derive_fingerprint_material,
    file_sha256,
    watermark_pdf,
)
from dent_bot.state import BotState  # noqa: E402
from scripts.benchmark_booklet_pipeline import make_fixture  # noqa: E402
from scripts.forensic_attack_suite import _image_attacks, _render_page, _rewrite_pdf, _spatial_diff  # noqa: E402


SECRET = b"secure-raster-v9-regression-key!"
DOCUMENT_ID = "tgdoc_secure_raster_v9_fixture"


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


def _register(state: BotState) -> int:
    state.replace_protected_media_message(-1001234567890, 2821, [{
        "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT secure raster",
        "courseTag": "ent", "term": 7, "sessionNo": 4, "telegramMethod": "sendDocument",
        "fileId": "secure-source", "fileUniqueId": "secure-source-unique",
        "fileName": "fixture.pdf", "mimeType": "application/pdf", "caption": "secure regression",
    }])
    return int(state.protected_media_for(
        course_code="ENT", term=7, session_no=4, content_kind="booklet",
    )[0]["id"])


def _issue(state: BotState, source_id: int, source: Path, output: Path, issuance_id: str, user_id: int):
    source_hash = file_sha256(source)
    material = derive_fingerprint_material(
        SECRET, issuance_id=issuance_id, user_id=user_id, document_id=DOCUMENT_ID,
        source_hash=source_hash,
    )
    state.create_booklet_issuance(
        issuance_id=issuance_id, user_id=user_id, source_id=source_id,
        document_id=DOCUMENT_ID, trace_code=material.trace_code,
        fingerprint_hash=material.fingerprint_hash, watermark_version=WATERMARK_VERSION,
        source_hash=source_hash,
    )
    started = time.perf_counter()
    report = watermark_pdf(
        source, output,
        identity=WatermarkIdentity("کاربر آزمایشی امن", "0012345678", "09123456789"),
        material=material, font_path=_font(), qpdf_binary=shutil.which("qpdf") or "",
    )
    state.mark_booklet_issuance_sent(issuance_id, telegram_file_id=f"fixture-{user_id}")
    report["observedWallSeconds"] = round(time.perf_counter() - started, 4)
    report["inputBytes"] = source.stat().st_size
    report["outputBytes"] = output.stat().st_size
    report["sizeRatio"] = round(output.stat().st_size / max(1, source.stat().st_size), 4)
    return material, report


def _structure(path: Path, identity: WatermarkIdentity, trace_code: str) -> dict[str, object]:
    import pymupdf

    with pymupdf.open(str(path)) as document:
        text = "\n".join(page.get_text("text") for page in document)
        metadata = " ".join(str(value or "") for value in document.metadata.values())
        result = {
            "pages": document.page_count,
            "contentStreamsPerPage": [len(page.get_contents()) for page in document],
            "imagesPerPage": [len(page.get_images(full=True)) for page in document],
            "annotations": sum(1 for page in document for _annotation in (page.annots() or ())),
            "embeddedFiles": document.embfile_count(),
            "liveTextCharacters": len(text.strip()),
            "metadataKeywords": str(document.metadata.get("keywords") or ""),
            "metadataContainsPii": any(value in metadata for value in (
                identity.national_code, identity.phone_number, trace_code,
            )),
        }
    raw = path.read_bytes()
    result["rawPdfContainsPii"] = any(value.encode("ascii") in raw for value in (
        identity.national_code, identity.phone_number, trace_code,
    ))
    return result


def _pdftotext(path: Path, identity: WatermarkIdentity, trace_code: str) -> dict[str, object]:
    binary = shutil.which("pdftotext")
    if not binary:
        git_copy = Path("C:/Program Files/Git/mingw64/bin/pdftotext.exe")
        binary = str(git_copy) if git_copy.is_file() else ""
    if not binary:
        return {"available": False, "piiRecovered": None, "characters": 0}
    result = subprocess.run(
        [binary, "-enc", "UTF-8", str(path), "-"], capture_output=True, timeout=120, check=False,
    )
    text = result.stdout.decode("utf-8", errors="ignore")
    return {
        "available": True, "returnCode": result.returncode, "characters": len(text.strip()),
        "piiRecovered": any(value in text for value in (
            identity.national_code, identity.phone_number, trace_code,
        )),
    }


def _result(path: Path, state: BotState, original: Path, issuance_id: str) -> dict[str, object]:
    detections = detect(
        path, state=state, secret=SECRET, original_pdf=original, document_id=DOCUMENT_ID,
    )
    match = next((item for item in detections if item.issuance_id == issuance_id), None)
    return {
        "detected": match is not None,
        "verdict": match.verdict if match else "none",
        "confidence": round(match.confidence, 4) if match else 0.0,
        "validEccChannels": [item.name for item in match.channel_results if item.crc_valid] if match else [],
        "channelResults": [item.payload(issuance_id) for item in match.channel_results] if match else [],
    }


def _raster_rebuild(source: Path, output: Path) -> None:
    import pymupdf

    with pymupdf.open(str(source)) as original, pymupdf.open() as rebuilt:
        for page in original:
            pixmap = page.get_pixmap(matrix=pymupdf.Matrix(180 / 72, 180 / 72), alpha=False)
            encoded = pixmap.tobytes("jpeg", jpg_quality=82)
            target = rebuilt.new_page(width=float(page.rect.width), height=float(page.rect.height))
            target.insert_image(target.rect, stream=encoded, keep_proportion=False)
        rebuilt.save(str(output), garbage=4, clean=True, deflate=True, use_objstms=1)


def _create_montage(source_pdf: Path, destination: Path) -> None:
    import cv2
    import numpy as np
    import pymupdf

    panels = []
    with pymupdf.open(str(source_pdf)) as document:
        for page_index in (0, 1, 2):
            pixmap = document[page_index].get_pixmap(matrix=pymupdf.Matrix(105 / 72, 105 / 72), alpha=False)
            array = np.frombuffer(pixmap.samples, dtype=np.uint8).reshape(pixmap.height, pixmap.width, pixmap.n)
            panels.append(cv2.cvtColor(array[:, :, :3], cv2.COLOR_RGB2BGR))
    target_height = max(panel.shape[0] for panel in panels)
    normalized = [cv2.copyMakeBorder(panel, 0, target_height - panel.shape[0], 0, 0, cv2.BORDER_CONSTANT, value=(235, 235, 235)) for panel in panels]
    cv2.imwrite(str(destination), cv2.hconcat(normalized))


def run(output_pdf: Path, report_path: Path, montage_path: Path) -> dict[str, object]:
    output_pdf.parent.mkdir(parents=True, exist_ok=True)
    report_path.parent.mkdir(parents=True, exist_ok=True)
    montage_path.parent.mkdir(parents=True, exist_ok=True)
    identity = WatermarkIdentity("کاربر آزمایشی امن", "0012345678", "09123456789")
    with tempfile.TemporaryDirectory(prefix="dent-secure-raster-v9-") as directory:
        root = Path(directory)
        source, second = root / "source.pdf", root / "second.pdf"
        make_fixture(source, pages=10, profile="mixed")
        state = BotState(root / "state.sqlite3")
        try:
            source_id = _register(state)
            material, generation = _issue(
                state, source_id, source, output_pdf.resolve(), "iss_secure_raster_v9_primary", 7101,
            )
            second_material, _second_generation = _issue(
                state, source_id, source, second, "iss_secure_raster_v9_decoy01", 7102,
            )
            structure = _structure(output_pdf, identity, material.trace_code)
            extraction = _pdftotext(output_pdf, identity, material.trace_code)
            clean = _result(output_pdf, state, source, material.issuance_id)

            pdf_attacks: dict[str, object] = {}
            for name in ("remove-annotations", "neutralize-all-form-xobjects", "remove-content-tail", "pikepdf-rewrite", "qpdf-rewrite", "ghostscript-rewrite"):
                attacked = root / f"{name}.pdf"
                attack = _rewrite_pdf(output_pdf, attacked, name, original_pdf=source)
                pdf_attacks[name] = {"attack": attack}
                if attack.get("available") and attacked.is_file():
                    pdf_attacks[name]["detection"] = _result(attacked, state, source, material.issuance_id)
            raster_rebuild = root / "raster-rebuild.pdf"
            _raster_rebuild(output_pdf, raster_rebuild)
            pdf_attacks["raster-rebuild"] = {
                "attack": {"available": True},
                "detection": _result(raster_rebuild, state, source, material.issuance_id),
            }

            attacks = _image_attacks(output_pdf, source, root)
            import cv2

            gray = root / "grayscale.jpg"
            cv2.imwrite(str(gray), cv2.imread(str(attacks["screenshot"]), cv2.IMREAD_GRAYSCALE), [cv2.IMWRITE_JPEG_QUALITY, 82])
            attacks["grayscale"] = gray
            image_attacks = {
                name: _result(path, state, source, material.issuance_id)
                for name, path in attacks.items()
            }
            decoy_checks = {
                name: _result(path, state, source, second_material.issuance_id)
                for name, path in attacks.items()
            }
            first_png, second_png = root / "first.png", root / "second.png"
            _render_page(output_pdf, first_png)
            _render_page(second, second_png)
            recipient_diff = _spatial_diff(first_png, second_png)
            _create_montage(output_pdf, montage_path)

            failures = []
            if clean["verdict"] != "attributed" or not clean["validEccChannels"]:
                failures.append("clean-ecc-attribution")
            if structure["liveTextCharacters"] or structure["rawPdfContainsPii"] or structure["metadataContainsPii"]:
                failures.append("extractable-recipient-data")
            if structure["annotations"] or structure["embeddedFiles"] or any(value != 1 for value in structure["imagesPerPage"]):
                failures.append("non-raster-security-object")
            if extraction.get("available") and (extraction.get("piiRecovered") or extraction.get("characters")):
                failures.append("pdftotext-recovery")
            for name in ("screenshot", "jpeg-q60", "jpeg-q75", "jpeg-q90", "crop-5", "crop-10", "rotate", "print-scan", "grayscale"):
                if image_attacks[name]["verdict"] != "attributed":
                    failures.append(f"attack-not-ecc-attributed:{name}")
            if pdf_attacks["raster-rebuild"]["detection"]["verdict"] != "attributed":
                failures.append("raster-rebuild-not-attributed")
            if recipient_diff.get("tileCoverage", 0.0) < 0.85:
                failures.append("recipient-diff-too-concentrated")
            if any(value["verdict"] == "attributed" for value in decoy_checks.values()):
                failures.append("decoy-false-attribution")
            for page in generation.get("pageProfiles", []):
                visible_count = int(page.get("visibleInstances", 0))
                compact_count = int(page.get("compactTraceInstances", 0))
                if visible_count < 3 or visible_count > 5:
                    failures.append(f"visible-count-out-of-range:page-{page.get('page')}")
                if compact_count < 1 or compact_count > 2:
                    failures.append(f"compact-count-out-of-range:page-{page.get('page')}")
                if page.get("profile") == "image-heavy" and compact_count != 1:
                    failures.append(f"image-page-compact-count:page-{page.get('page')}")

            report = {
                "success": not failures,
                "version": WATERMARK_VERSION,
                "forensicChannel": SECURE_RASTER_CHANNEL_VERSION,
                "fixture": {"pages": 10, "profile": "mixed", "sourceBytes": source.stat().st_size},
                "generation": generation,
                "structure": structure,
                "pdftotext": extraction,
                "cleanDetection": clean,
                "pdfAttacks": pdf_attacks,
                "imageAttacks": image_attacks,
                "decoyChecks": decoy_checks,
                "recipientDiff": recipient_diff,
                "failures": failures,
                "limitations": [
                    "Twenty-percent crop and strong perspective/mobile-photo damage may remain inconclusive rather than attributed.",
                    "An attacker with the exact original can destructively replace changed pixels; this damages or removes visible recipient evidence and is not claimed to be impossible.",
                    "The system increases removal cost and preserves attribution through common transformations; it is not AI-proof or guaranteed against every transformation.",
                ],
            }
            report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
            return report
        finally:
            state.close()


def main() -> int:
    parser = argparse.ArgumentParser(description="Secure raster recipient-fingerprint attack suite")
    parser.add_argument("--output-pdf", required=True)
    parser.add_argument("--report", required=True)
    parser.add_argument("--montage", required=True)
    args = parser.parse_args()
    report = run(Path(args.output_pdf), Path(args.report), Path(args.montage))
    print(json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(",", ":")))
    return 0 if report["success"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
