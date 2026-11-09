"""Classifier v3 decision core: deterministic, no model per question, no DB writes.

Path: live study export -> eligible edition (policy) -> verified PDF SHA ->
single-pass grouped scan -> one-page evidence + same-edition chapter map ->
independent audit -> source-only handoff package (applied only by the
authorized fanoosupd operator after a fresh verified backup).
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import time
from dataclasses import dataclass, field
from pathlib import Path

MIN_CONFIDENCE = 0.85
MAX_QUOTE_WORDS = 40
SHA = re.compile(r"^[a-f0-9]{64}$")
SCOPE = {"in_scope", "outside_scope_exam_question", "scope_unverified"}
EXEMPT = "residency_english_official_key"


def classify_need(q: dict) -> str:
    """Return 'exempt', 'needs_source' or 'complete' for one live question row."""
    if q.get("exam_type") == "residency" and q.get("subject") == "english" \
            and q.get("key_status") in {"final", "amended"}:
        return "exempt"                       # official-key exemption stays source-free
    src = q.get("source") or {}
    if src.get("node_id") and int(src.get("pdf_page") or 0) > 0 and SHA.match(src.get("pdf_sha256") or ""):
        return "complete"
    return "needs_source"                     # voided questions are NOT exempt


def resolve_edition(q: dict, policy: dict) -> dict:
    """Pick the eligible edition from an explicit, versioned policy table.

    policy maps "type/year/subject" -> {"editions": [newest..oldest], "basis": str}.
    Residency 1391-1396 is latest-first: the caller searches editions in order
    and records newer_editions_searched. No match -> pending, never a guess.
    """
    key = f"{q['exam_type']}/{q['year']}/{q['subject']}"
    entry = policy.get(key)
    if not entry or not entry.get("editions"):
        return {"status": "pending", "reason": "no_official_or_rule_based_edition", "key": key}
    return {"status": "ok", "editions": list(entry["editions"]), "basis": entry.get("basis", "official_exam_year"),
            "key": key}


def group_by_edition(questions: list[dict], policy: dict) -> tuple[dict, list]:
    groups, pending, seen = {}, [], set()
    for q in questions:
        fp = question_fingerprint(q)
        if fp in seen:                        # duplicate suppression across sittings
            pending.append({"id": q["id"], "reason": "duplicate_in_batch"}); continue
        seen.add(fp)
        if classify_need(q) != "needs_source":
            continue
        r = resolve_edition(q, policy)
        if r["status"] != "ok":
            pending.append({"id": q["id"], "reason": r["reason"]}); continue
        groups.setdefault(r["editions"][0], []).append(q)
    return groups, pending


def question_fingerprint(q: dict) -> str:
    body = json.dumps([q.get("stem", ""), q.get("choices", {}), q.get("key")], ensure_ascii=False, sort_keys=True)
    return hashlib.sha256(body.encode()).hexdigest()


def chapter_for(runs: list, page: int):
    for chapter, first, last in runs:
        if first <= page <= last:
            return chapter
    return None


@dataclass
class Decision:
    question_id: int
    edition: str
    pdf_sha256: str
    pdf_page: int
    node_id: str | None
    quote: str
    confidence: float
    scope: str
    basis: str
    note_fa: str = ""
    reasons: list = field(default_factory=list)


def verify_decision(d: Decision, page_text: str, runs: list, approved_sha: str, page_count: int) -> list[str]:
    """Independent gate. Returns failure reasons; empty list == acceptable."""
    fail = []
    if not SHA.match(d.pdf_sha256) or d.pdf_sha256 != approved_sha:
        fail.append("sha_not_current_approved_edition")
    if not 1 <= d.pdf_page <= page_count:
        fail.append("pdf_page_out_of_range")
    mapped = chapter_for(runs, d.pdf_page) if runs else None
    if not runs:
        fail.append("edition_chapter_map_missing")
    elif mapped is None or str(mapped) != str(d.node_id):
        fail.append("chapter_not_from_same_edition_map")
    words = d.quote.split()
    if not words or len(words) > MAX_QUOTE_WORDS:
        fail.append("quote_length")
    elif _norm(d.quote) not in _norm(page_text):
        fail.append("quote_not_verbatim_on_page")
    if d.confidence < MIN_CONFIDENCE:
        fail.append("low_confidence")
    if d.scope not in SCOPE:
        fail.append("invalid_scope")
    if d.scope in {"outside_scope_exam_question", "scope_unverified"} and not d.note_fa.strip():
        fail.append("scope_note_required")
    if d.basis != "official_exam_year" and not d.note_fa.strip():
        fail.append("proxy_note_required")
    return fail


def _norm(s: str) -> str:
    s = re.sub(r"-\s*\n\s*", "", s.lower())
    return re.sub(r"\s+", " ", re.sub(r"[^\w\s]", " ", s)).strip()


def audit_packet(d: Decision) -> dict:
    """Packet for an independent auditor: no model reasoning, only checkable facts."""
    return {"question_id": d.question_id, "edition": d.edition, "pdf_sha256": d.pdf_sha256,
            "pdf_page": d.pdf_page, "node_id": d.node_id, "quote": d.quote, "scope": d.scope,
            "basis": d.basis, "confidence": d.confidence}


def accept(d: Decision, primary_fail: list, audit_fail: list) -> str:
    if primary_fail:
        return "pending:" + ",".join(primary_fail)
    if audit_fail:
        return "pending:audit:" + ",".join(audit_fail)
    return "accepted"


class Checkpoint:
    """Atomic JSON checkpoint with per-group leases (crash-safe resume, no double work)."""

    def __init__(self, path: Path, lease_seconds: int = 1800):
        self.path, self.lease = Path(path), lease_seconds
        self.state = json.loads(self.path.read_text()) if self.path.exists() else {"done": {}, "leases": {}}

    def claim(self, group: str, owner: str, now: float | None = None) -> bool:
        now = time.time() if now is None else now
        if group in self.state["done"]:
            return False
        lease = self.state["leases"].get(group)
        if lease and lease["owner"] != owner and lease["until"] > now:
            return False
        self.state["leases"][group] = {"owner": owner, "until": now + self.lease}
        self._save(); return True

    def complete(self, group: str, result: dict):
        self.state["done"][group] = result
        self.state["leases"].pop(group, None); self._save()

    def _save(self):
        tmp = self.path.with_suffix(".tmp")
        tmp.write_text(json.dumps(self.state, ensure_ascii=False, sort_keys=True))
        os.replace(tmp, self.path)


def handoff_package(accepted: list[Decision], study_sha256: str, batch_id: str) -> dict:
    """Source-only package for the fanoosupd operator. Never touches text/keys/attempts."""
    if len(accepted) > 40:
        raise ValueError("batch exceeds 40 questions")
    rows = [audit_packet(d) | {"note_fa": d.note_fa} for d in accepted]
    body = {"format": "fanoos-classifier-v3-source-only/1", "batch_id": batch_id,
            "study_export_sha256": study_sha256, "mutates": ["question_sources"],
            "never_mutates": ["stem", "choices", "answer_key", "attempts", "frozen_versions"],
            "requires": ["fresh_study_reexport_matches", "verified_full_backup_le_4h", "operator_fanoosupd",
                         "independent_readback"], "rows": rows}
    body["package_sha256"] = hashlib.sha256(json.dumps(body, sort_keys=True, ensure_ascii=False).encode()).hexdigest()
    return body
