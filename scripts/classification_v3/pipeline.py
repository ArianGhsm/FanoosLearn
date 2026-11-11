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
MIN_QUOTE_WORDS = 4
MAX_QUOTE_WORDS = 40
ELLIPSIS = re.compile(r"\.\.\.|…")
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
    if not entry.get("provenance"):            # official list id / owner-rule citation
        return {"status": "pending", "reason": "edition_policy_without_provenance", "key": key}
    return {"status": "ok", "editions": list(entry["editions"]), "basis": entry.get("basis", "official_exam_year"),
            "key": key}


def group_by_edition(questions: list[dict], policy: dict) -> tuple[dict, list]:
    """Group rows by edition. Only an exact repeated (workspace_id, id) is dropped;
    identical content in different rows/exam types is KEPT (it may share retrieval
    via question_fingerprint, but every bank row gets its own decision)."""
    groups, pending, seen = {}, [], set()
    for q in questions:
        rid = (str(q.get("workspace_id", "")), str(q["id"]))
        if rid in seen:
            pending.append({"id": rid[1], "reason": "repeated_row_in_export"}); continue
        seen.add(rid)
        if classify_need(q) != "needs_source":
            continue
        r = resolve_edition(q, policy)
        if r["status"] != "ok":
            pending.append({"id": rid[1], "reason": r["reason"]}); continue
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
    question_id: str  # live DB ids are UUID-like strings
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
    printed_page: str = ""   # corroborated via edition bridge when one exists
    producer: str = ""


def verify_decision(d: Decision, page_text: str, runs: list, approved_sha: str, page_count: int) -> list[str]:
    """PRECHECK (self-check, not an audit). Returns failure reasons."""
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
    # Same fragment rule as apply_classification: '...'-joined fragments, each
    # >=4 words and verbatim on the page (whole-string matching wrongly
    # rejected 20 such rows before QA).
    pieces = [f.strip() for f in ELLIPSIS.split(d.quote) if f.strip()]
    words = sum(len(p.split()) for p in pieces)
    if (not pieces or not MIN_QUOTE_WORDS <= words <= MAX_QUOTE_WORDS
            or any(len(p.split()) < MIN_QUOTE_WORDS for p in pieces)):
        fail.append("quote_length")
    elif any(_norm(p) not in _norm(page_text) for p in pieces):
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


def precheck(primary_fail: list) -> str:
    """Self-check only. NEVER approval: passing rows become audit_pending."""
    return "pending:" + ",".join(primary_fail) if primary_fail else "audit_pending"


AUDIT_TOOLS = {"audit_private_study_batches.py", "independent_page_reopen"}


def check_independent_audit(d: "Decision", audit: dict, receipt_root: Path,
                            producer_uid: int | None = None) -> list[str]:
    """An audit must come from a different auditor, a separate page reopen/tool run,
    and a private receipt file whose SHA-256 matches; fields must match the row.

    The declared `auditor`/`tool` strings are NOT trusted on their own: the
    receipt must be a real `provenance-audit/1` report in a protected
    classification/reports dir, not owned by the producing uid, whose mapped
    row equals this decision (audit_adapter). A self-written JSON fails."""
    import audit_adapter
    fail = []
    if not audit:
        return ["audit_missing"]
    if audit.get("tool") not in AUDIT_TOOLS:
        fail.append("audit_tool_not_independent")
    if not audit.get("auditor") or audit.get("auditor") == d.producer:
        fail.append("self_certification")
    for k in ("question_id", "pdf_sha256", "pdf_page", "node_id"):
        if str(audit.get(k)) != str(getattr(d, k)):
            fail.append("audit_mismatch_" + k)
    if audit.get("quote_sha256") != hashlib.sha256(d.quote.encode()).hexdigest():
        fail.append("audit_mismatch_quote")
    if audit.get("verdict") != "accepted":
        fail.append("audit_not_accepted")
    rp = receipt_root / str(audit.get("receipt", ""))
    if not audit.get("receipt") or ".." in str(audit.get("receipt")) or not rp.is_file():
        fail.append("audit_receipt_missing")
    elif hashlib.sha256(rp.read_bytes()).hexdigest() != audit.get("receipt_sha256"):
        fail.append("audit_receipt_hash_mismatch")
    else:
        real = audit_adapter.verify(rp, str(audit.get("batch", "")), int(audit.get("number") or 0),
                                    f"{d.edition}#{d.node_id}", d.pdf_page, d.pdf_sha256,
                                    root=Path(receipt_root), producer_uid=producer_uid)
        fail += ["real_" + f for f in real["failures"]]
    return fail


class Checkpoint:
    """Cross-process checkpoint: flock-serialized re-read/modify/fsync/atomic replace."""

    def __init__(self, path: Path, lease_seconds: int = 1800):
        self.path, self.lease = Path(path), lease_seconds
        self.lock = self.path.with_suffix(".lock")

    def _locked(self, fn):
        import fcntl
        with open(self.lock, "a") as lk:
            fcntl.flock(lk, fcntl.LOCK_EX)
            try:
                state = json.loads(self.path.read_text()) if self.path.exists() else {"done": {}, "leases": {}}
                result, changed = fn(state)
                if changed:
                    tmp = self.path.with_suffix(".tmp")
                    with open(tmp, "w") as f:
                        f.write(json.dumps(state, ensure_ascii=False, sort_keys=True)); f.flush(); os.fsync(f.fileno())
                    os.replace(tmp, self.path)
                    dfd = os.open(self.path.parent, os.O_RDONLY); os.fsync(dfd); os.close(dfd)
                return result
            finally:
                fcntl.flock(lk, fcntl.LOCK_UN)

    def claim(self, group: str, owner: str, now: float | None = None) -> bool:
        now = time.time() if now is None else now

        def fn(s):
            if group in s["done"]:
                return False, False
            lease = s["leases"].get(group)
            if lease and lease["owner"] != owner and lease["until"] > now:
                return False, False
            s["leases"][group] = {"owner": owner, "until": now + self.lease}
            return True, True
        return self._locked(fn)

    def complete(self, group: str, owner: str, result: dict) -> bool:
        def fn(s):
            if s["leases"].get(group, {}).get("owner") != owner:
                return False, False          # lease lost/expired: do not record
            s["done"][group] = result; s["leases"].pop(group, None)
            return True, True
        return self._locked(fn)


def handoff_package(rows: list[tuple["Decision", dict]], study_sha256: str, batch_id: str,
                    receipt_root: Path, producer_uid: int | None = None) -> dict:
    """Source-only package for the fanoosupd operator. Refuses any row without a
    valid independent audit receipt. Never touches text/keys/attempts."""
    if len(rows) > 40:
        raise ValueError("batch exceeds 40 questions")
    out = []
    for d, audit in rows:
        fail = check_independent_audit(d, audit, Path(receipt_root), producer_uid)
        if fail:
            raise ValueError(f"unaudited row {d.question_id}: {','.join(fail)}")
        out.append(audit_packet(d) | {"note_fa": d.note_fa, "audit_receipt_sha256": audit["receipt_sha256"]})
    body = {"format": "fanoos-classifier-v3-source-only/2", "batch_id": batch_id,
            "study_export_sha256": study_sha256, "mutates": ["question_sources"],
            "never_mutates": ["stem", "choices", "answer_key", "attempts", "frozen_versions"],
            "requires": ["fresh_study_reexport_matches", "verified_full_backup_le_4h", "operator_fanoosupd",
                         "independent_readback"], "publication": "never; whole-year gate is separate", "rows": out}
    body["package_sha256"] = hashlib.sha256(json.dumps(body, sort_keys=True, ensure_ascii=False).encode()).hexdigest()
    return body
