from __future__ import annotations

import tempfile
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
) -> list[Detection]:
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

    with tempfile.TemporaryDirectory(prefix="fanoos-forensic-source-") as work:
        work_dir = Path(work)
        cache: dict[tuple[str, str], Path] = {}

        def fetch_original(candidate: dict) -> Path:
            key = (candidate["objectId"], candidate["resourceVersionId"])
            cached = cache.get(key)
            if cached is not None:
                return cached
            data = api.media_forensic_source(
                platform, subject, workspace_id,
                candidate["objectId"], candidate["resourceVersionId"], candidate["classification"],
                MAX_ORIGINAL_SOURCE_BYTES,
            )
            path = work_dir / f"original-{len(cache)}.pdf"
            path.write_bytes(data)
            cache[key] = path
            return path

        return detect(evidence_path, candidates=candidates, secret=secret, fetch_original=fetch_original, deadline=deadline)


_VERDICT_FA = {"attributed": "قطعی", "candidate": "احتمالی"}


def format_result_fa(results: list[Detection]) -> str:
    """Persian summary for the owner. Never includes a phone number or any
    personal identifier -- only the platform user id, the verdict, the
    confidence, and which channels agreed (never just a verdict).
    """
    if not results:
        return (
            "🔍 هیچ نشانه‌ای از علامت‌گذاری فانوس در این فایل پیدا نشد، "
            "یا با هیچ‌یک از دریافت‌کنندگان واقعی این منبع مطابقت نداشت."
        )
    lines = ["🔍 نتیجه ردیابی نشت:"]
    for item in results[:5]:
        verdict_fa = _VERDICT_FA.get(item.verdict, item.verdict)
        channels = "، ".join(item.channels) if item.channels else "—"
        lines.append(
            f"— شناسه کاربر: {item.user_id}\n"
            f"  نتیجه: {verdict_fa} (اطمینان {item.confidence:.0%})\n"
            f"  کانال‌های موافق: {channels}"
        )
    if len(results) > 1:
        lines.append(f"({len(results)} مورد یافت شد؛ {min(5, len(results))} مورد نمایش داده شد.)")
    return "\n\n".join(lines)
