from __future__ import annotations

import tempfile
from dataclasses import dataclass
from pathlib import Path

from .api import FanoosApiClient
from .forensic_detector import Detection, detect

# Bounded the same way protected-media job inputs are bounded (worker.py's
# JobLimits default max_input_bytes). Original resource bytes are fetched
# once per distinct (object, resource_version) pair among the candidates and
# cached for the duration of one investigation.
MAX_ORIGINAL_SOURCE_BYTES = 50 * 1024 * 1024


def _normalize_candidate(item: dict) -> dict | None:
    if not isinstance(item, dict):
        return None
    issuance_id = str(item.get("issuance_id") or "")
    user_id = str(item.get("user_id") or "")
    document_id = str(item.get("document_id") or "")
    object_id = str(item.get("object_id") or "")
    resource_version_id = str(item.get("resource_version_id") or "")
    classification = str(item.get("classification") or "")
    if not (issuance_id and user_id and document_id and object_id and resource_version_id and classification):
        return None
    return {
        "issuanceId": issuance_id,
        "userId": user_id,
        "documentId": document_id,
        "objectId": object_id,
        "resourceVersionId": resource_version_id,
        "classification": classification,
    }


@dataclass(frozen=True)
class InvestigationOutcome:
    """Carries *why* detect() found nothing, not just that it found nothing.

    `candidates_found == 0` means the resource had no real deliveries to
    compare against at all (wrong resource picked, or nobody was ever
    issued it) -- the file was never examined. `candidates_found > 0` and
    `detections` empty means candidates existed and the file was actually
    compared against them, but no mark was recovered. format_result_fa
    renders these as two different sentences on purpose: "clean file" and
    "nothing to compare against" are not the same finding.
    """

    candidates_found: int
    detections: tuple[Detection, ...]


def investigate(
    api: FanoosApiClient,
    *,
    platform: str,
    subject: str,
    workspace_id: str,
    resource_id: str,
    evidence_path: Path,
    secret: bytes,
    deadline: float,
) -> InvestigationOutcome:
    """Owner-facing orchestration: fetch this resource's real delivery
    candidates and their original bytes from the platform (never bot-local
    state, never another workspace's deliveries -- both enforced server-side
    by ProtectedMediaForensicService), then run the detector against the
    evidence the owner uploaded. The fingerprint `secret` is read by the
    caller from this process's own environment and is never sent to the
    platform.
    """
    response = api.media_forensic_candidates(platform, subject, workspace_id, resource_id)
    raw_candidates = response.get("candidates") if isinstance(response, dict) else None
    candidates = [normalized for item in (raw_candidates or []) if (normalized := _normalize_candidate(item)) is not None]
    if not candidates:
        return InvestigationOutcome(candidates_found=0, detections=())

    with tempfile.TemporaryDirectory(prefix="fanoos-forensic-source-") as work:
        work_dir = Path(work)
        cache: dict[tuple[str, str], Path] = {}

        def fetch_original(candidate: dict) -> Path:
            # Any candidate job sharing (object, version) resolves to the
            # identical bytes server-side (ProtectedMediaForensicService
            # derives the object/version/classification from the job row
            # itself), so this candidate's own issuanceId is a valid key to
            # fetch the group's bytes exactly once.
            key = (candidate["objectId"], candidate["resourceVersionId"])
            cached = cache.get(key)
            if cached is not None:
                return cached
            data = api.media_forensic_source(platform, subject, workspace_id, candidate["issuanceId"], MAX_ORIGINAL_SOURCE_BYTES)
            path = work_dir / f"original-{len(cache)}.pdf"
            path.write_bytes(data)
            cache[key] = path
            return path

        detections = detect(evidence_path, candidates=candidates, secret=secret, fetch_original=fetch_original, deadline=deadline)
        return InvestigationOutcome(candidates_found=len(candidates), detections=tuple(detections))


_VERDICT_FA = {"attributed": "قطعی", "candidate": "احتمالی"}


def format_result_fa(outcome: InvestigationOutcome) -> str:
    """Persian summary for the owner. Never includes a phone number or any
    personal identifier -- only the platform user id, the verdict, the
    confidence, and which channels agreed (never just a verdict).

    Four distinguishable outcomes, deliberately worded differently so none
    of them can be mistaken for another:
      1. no candidates at all -- nothing to compare against, file unexamined;
      2. candidates existed, nothing decoded -- a clean/unmarked file;
      3. exactly one confident (crc-valid) attribution;
      4. two or more confident attributions -- a contradiction (at most one
         can be true), surfaced as a warning banner *first*, not a footnote,
         since a reader acts on the first name they see.
    """
    if outcome.candidates_found == 0:
        return (
            "🔍 برای این منبع هیچ دریافت‌کننده‌ای ثبت نشده است؛ چیزی برای مقایسه با فایل ارسالی وجود ندارد. "
            "منبع درستی را انتخاب کرده‌اید؟"
        )
    if not outcome.detections:
        return (
            "🔍 فایل با نشانه‌های دریافت‌کنندگان واقعی این منبع مقایسه شد و هیچ نشانه‌ای از "
            "علامت‌گذاری فانوس در آن پیدا نشد."
        )
    attributed = [item for item in outcome.detections if item.verdict == "attributed"]
    lines: list[str] = []
    if len(attributed) >= 2:
        lines.append(
            "⚠️ هشدار: این فایل به‌طور قطعی به بیش از یک نفر نسبت داده شد. حداکثر یکی از این نتیجه‌ها "
            "می‌تواند درست باشد؛ پیش از هر اقدامی این مورد را دستی بررسی کنید."
        )
    lines.append("🔍 نتیجه ردیابی نشت:")
    for item in outcome.detections[:5]:
        verdict_fa = _VERDICT_FA.get(item.verdict, item.verdict)
        channels = "، ".join(item.channels) if item.channels else "—"
        lines.append(
            f"— شناسه کاربر: {item.user_id}\n"
            f"  نتیجه: {verdict_fa} (اطمینان {item.confidence:.0%})\n"
            f"  کانال‌های موافق: {channels}"
        )
    if len(outcome.detections) > 1 and len(attributed) < 2:
        lines.append(f"({len(outcome.detections)} مورد یافت شد؛ {min(5, len(outcome.detections))} مورد نمایش داده شد.)")
    return "\n\n".join(lines)
