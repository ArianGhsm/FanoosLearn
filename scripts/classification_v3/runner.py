"""Fail-closed, bounded, research-only Classifier V3 batch runner.

One invocation = at most --max-batches research sessions, each one bounded
`claude -p` session for one planned batch. The runner:

* holds an exclusive flock (no concurrent runners, no duplicate processing);
* leases question ids in a private state file (atomic writes, resumable);
* re-reads live source absence (SELECT-only anti-join) before dispatch;
* groups by ONE selected edition set (newest edition per reference family),
  same subject/lane/proxy basis/key class, across years, 8-10 ids per batch;
* takes national dental only when no residency dental batch is runnable;
* never touches English/basic lanes; voided keys are batched separately;
* stages only private draft proposals and an operator request; it never writes
  the DB, a backup or an audit receipt, and never runs as fanoosupd;
* counts progress ONLY from trusted operator status receipts (owned by a
  configured trusted uid, not writable by the runner). Its own decision count
  is recorded as `drafted`, never `accepted`.

Stops (fail-closed): disk < 3 GiB, provider retry-after backoff, 2 consecutive
operator-confirmed zero-accepted batches, or --max-unverified staged batches
without a trusted receipt.
"""
from __future__ import annotations

import argparse
import fcntl
import hashlib
import json
import os
import re
import shutil
import signal
import stat
import subprocess
import sys
import tempfile
import time
from collections import defaultdict
from datetime import datetime, timedelta, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
PROMPT = HERE / "prompts" / "researcher.md"
RESEARCH_TMP = Path("/srv/fanoos/shared/research/tmp")

RESIDENCY_LANES = ["residency_dental_ready", "residency_dental_proxy_policy"]
NATIONAL_LANES = ["national_dental_same_edition_ready", "national_dental_other_ready"]
NEVER_RUN = {"residency_english_blocked_policy", "national_english_separate",
             "national_basic_sciences_excluded"}
MIN_FREE_BYTES = 3 * 1024 ** 3
MAX_ZERO_ACCEPTED = 2
DEFAULT_MAX_UNVERIFIED = 2
# Owner charter 2026-11-10: 8-20 questions per bounded (<=8 min) Opus session;
# a 14-id session was killed at 691s with no output, so keep batches small.
BATCH_MIN, BATCH_MAX = 8, 10
LEASE_S = 3 * 3600
SESSION_TIMEOUT_S = 480
ID_RE = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$")  # UUID only
EDITION_RE = re.compile(r"^[a-z0-9][a-z0-9._-]{0,80}@[a-z0-9][a-z0-9._-]{0,40}$")
SUBJECT_RE = re.compile(r"^[a-z0-9][a-z0-9-]{0,60}$")
RECEIPT_FORMAT = "fanoos.classifier-v3.operator-status.v1"
REQUEST_FORMAT = "fanoos.classifier-v3.operator-request.v1"
ENV_KEEP = ("PATH", "HOME", "LANG", "LC_ALL", "TZ", "USER", "LOGNAME")
RETRY_RE = re.compile(r"retry[-_ ]after[\"':= ]+([0-9]{1,6})", re.I)
RATE_RE = re.compile(r"rate[_ -]?limit|usage[_ -]?limit(?:[_ -]?reached)?|session[_ -]?limit|overloaded|\b429\b", re.I)
RESET_UTC_RE = re.compile(r"resets?\s+(\d{1,2}):(\d{2})\s*(am|pm)\s*\(UTC\)", re.I)


class Blocked(RuntimeError):
    pass


# ---------------------------------------------------------------- planning
def family(edition: str) -> str:
    return edition.split("@", 1)[0]


def newest_per_family(editions: list[str]) -> list[str]:
    """Input must be newest-first; keep the first (newest) edition of each family."""
    seen, out = set(), []
    for e in editions:
        if family(e) not in seen:
            seen.add(family(e))
            out.append(e)
    return out


def proxy_basis(item: dict) -> str | None:
    if item["lane"] != "residency_dental_proxy_policy":
        return None
    return "same_lineage_proxy" if item["year"] == 1396 else "retrospective_proxy_1397_plus"


def key_class(item: dict) -> str:
    s = item.get("official_key_status", "none")
    return "voided" if s == "voided" else "final" if s in ("final", "amended") else "provisional"


_VALIDATOR_EDITIONS: dict = {}


def validator_editions(kind, year, subject) -> set[str]:
    """Eligible editions the pinned production validator selects for this sitting/subject.
    Empty set = unknown (catalog unreadable or no rows): the planner then keeps the manifest set."""
    key = (kind, year, subject)
    if key not in _VALIDATOR_EDITIONS:
        try:
            from classification_v3.normalize import official_rows
            rows = official_rows(str(kind), int(year), str(subject))
            _VALIDATOR_EDITIONS[key] = {e for e, r in rows.items() if r.get("_eligible", True) is not False}
        except Exception:
            _VALIDATOR_EDITIONS[key] = set()
    return _VALIDATOR_EDITIONS[key]


def select_editions(item: dict) -> tuple[list[str], str | None]:
    """Return (selected newest-first edition set, block reason)."""
    if item["lane"] == "residency_dental_proxy_policy":
        if not item.get("proxy_candidates_ordered"):
            return [], "proxy_order_unverified_regenerate_manifest"
        eds = item.get("proxy_candidate_editions") or []
    else:
        eds = item.get("ready_editions") or []  # official list, SQL newest-first
    eds = newest_per_family(eds)
    allowed = validator_editions(item.get("type", "residency"), item.get("year"), item.get("subject"))
    if allowed and eds:
        eds = [e for e in eds if e in allowed]
        if not eds:
            return [], "no_validator_selected_edition"
    if not eds:
        return [], "no_ready_edition"
    if any(not EDITION_RE.match(e) for e in eds):
        return [], "edition_key_invalid"
    return eds, None


def plan_batches(items: list[dict], exclude: set[str] = frozenset()) -> tuple[list[dict], dict]:
    """Group runnable items; residency first, national only if residency has none."""
    groups: dict[tuple, list[dict]] = defaultdict(list)
    blocked: dict[str, int] = defaultdict(int)
    for it in items:
        lane = it.get("lane")
        if lane in NEVER_RUN or lane not in RESIDENCY_LANES + NATIONAL_LANES:
            blocked[f"lane:{lane}"] += 1
            continue
        # The official source-free exporter omits questions that already have
        # a partial source row. They need a guarded *repair* pathway instead.
        if int(it.get("existing_source_rows") or 0) > 0:
            blocked["existing_source_requires_repair"] += 1
            continue
        qid = str(it["question_id"])
        if not ID_RE.match(qid) or not SUBJECT_RE.match(str(it.get("subject", ""))):
            blocked["invalid_id_or_subject"] += 1
            continue
        if qid in exclude:
            continue
        eds, why = select_editions(it)
        if why:
            blocked[why] += 1
            continue
        key = ((RESIDENCY_LANES + NATIONAL_LANES).index(lane), lane, it["type"], it["subject"],
               tuple(eds), proxy_basis(it) or "-", key_class(it))
        groups[key].append(it)
    batches = []
    for key in sorted(groups):
        _, lane, kind, subject, eds, basis, kcls = key
        members = sorted(groups[key], key=lambda i: (i["year"], str(i["question_id"])))
        for i in range(0, len(members), BATCH_MAX):
            chunk = members[i:i + BATCH_MAX]
            ids = [str(m["question_id"]) for m in chunk]
            bid = hashlib.sha256(json.dumps([lane, subject, list(eds), basis, kcls, ids])
                                 .encode()).hexdigest()[:16]
            batches.append({"batch_id": bid, "lane": lane, "type": kind, "subject": subject,
                            "selected_editions": list(eds), "proxy_basis": None if basis == "-" else basis,
                            "key_class": kcls, "years": sorted({m["year"] for m in chunk}),
                            "question_ids": ids, "undersized": len(ids) < BATCH_MIN})
    # Prefer high-yield batches over alphabetically first 1-Q / 5-book
    # batches. Keep residency ahead of national and final-key cohorts ahead
    # of voided/provisional cohorts; never change the scientific acceptance bar.
    batches.sort(key=lambda b: (
        b["type"] != "residency",
        b["key_class"] != "final",
        -len(b["question_ids"]) / max(1, len(b["selected_editions"])),
        -len(b["question_ids"]),
        len(b["selected_editions"]),
        b["subject"],
        b["batch_id"],
    ))
    residency = [b for b in batches if b["type"] == "residency"]
    return (residency or [b for b in batches if b["type"] == "national"]), dict(blocked)


# ---------------------------------------------------------------- state / receipts
def load_json(path: Path, default):
    return json.loads(path.read_text("utf-8")) if path.exists() else default


def atomic_write(path: Path, obj) -> None:
    fd, tmp = tempfile.mkstemp(dir=path.parent, prefix=f".{path.name}.")
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as fh:
            json.dump(obj, fh, ensure_ascii=False, indent=1, sort_keys=True)
            fh.flush()
            os.fsync(fh.fileno())
        os.chmod(tmp, 0o640)
        os.replace(tmp, path)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def trusted_receipts(receipt_dir: Path, trusted_uids: set[int]) -> dict[str, dict]:
    """Operator status receipts; runner-owned or writable-by-others files are ignored."""
    out = {}
    if not receipt_dir.is_dir():
        return out
    me = os.getuid()
    root_stat = receipt_dir.lstat()
    fixed_operator_dir = Path("/srv/fanoos/shared/research/classification/v3-runner/operator-receipts")
    # Bubblewrap --unshare-user maps non-worker host UIDs to overflow UID 65534.
    # Trust this overflow UID ONLY under the exact protected, non-worker-writable
    # operator receipt directory, installed on the host as fanoosupd:worker 0750.
    protected_userns = (
        receipt_dir == fixed_operator_dir
        and not stat.S_ISLNK(root_stat.st_mode)
        and root_stat.st_uid == 65534
        and root_stat.st_uid != me
        and not root_stat.st_mode & (stat.S_IWGRP | stat.S_IWOTH)
        and 982 in trusted_uids
    )
    for p in sorted(receipt_dir.glob("*.json")):
        st = p.lstat()
        owner_ok = st.st_uid in trusted_uids or (protected_userns and st.st_uid == 65534)
        if (not stat.S_ISREG(st.st_mode) or st.st_uid == me or not owner_ok
                or st.st_mode & (stat.S_IWGRP | stat.S_IWOTH)):
            continue
        try:
            r = json.loads(p.read_text("utf-8"))
        except (OSError, ValueError):
            continue
        if (r.get("format") == RECEIPT_FORMAT and isinstance(r.get("batch_id"), str)
                and isinstance(r.get("accepted_source_rows"), int)
                and r.get("independent_qa") == "approved_or_rejected"
                and r.get("readback_verified") is True):
            out[r["batch_id"]] = r
    return out


def reconcile(state: dict, receipts: dict[str, dict], now: float) -> dict:
    """Fold trusted receipts into state; expire stale research leases."""
    state = dict(state)
    batches = state.setdefault("batches", {})
    for bid, b in batches.items():
        if b["status"] == "staged" and bid in receipts:
            b["status"] = "operator_confirmed"
            b["accepted"] = receipts[bid]["accepted_source_rows"]
        if b["status"] == "leased" and b.get("lease_expires", 0) < now:
            b["status"] = "lease_expired"
    confirmed = sorted((b for b in batches.values() if b["status"] == "operator_confirmed"),
                       key=lambda b: b["started"])
    streak = 0
    for b in confirmed:
        streak = 0 if b["accepted"] > 0 else streak + 1
    state["zero_accepted_streak"] = streak
    state["accepted_total"] = sum(b["accepted"] for b in confirmed)
    state["unverified_staged"] = sum(1 for b in batches.values() if b["status"] == "staged")
    return state


MAX_ATTEMPTS = 3


def excluded_ids(state: dict) -> set[str]:
    """Ids already leased/staged/confirmed are never re-dispatched by the runner.

    Exception: ids a finished session never reached (draft reason
    not_reached_in_session) return to the queue once their batch is terminal
    (operator_confirmed or no_proposals), at most MAX_ATTEMPTS sessions each.
    QA-rejected or researched-pending ids stay excluded.
    """
    attempts = state.get("attempts", {})
    out = set()
    for b in state.get("batches", {}).values():
        # "resubmitted" records are skipped: their successor carries the same ids.
        if b["status"] not in ("leased", "staged", "operator_confirmed", "failed", "no_proposals"):
            continue
        retry = set(b.get("unreached", [])) if b["status"] in ("operator_confirmed", "no_proposals") else set()
        out |= {q for q in b["question_ids"] if q not in retry or attempts.get(q, 1) >= MAX_ATTEMPTS}
    return out


def unreached_ids(case_dir: Path) -> list[str]:
    try:
        d = json.loads((case_dir / "decisions.draft.json").read_text("utf-8"))
    except (OSError, ValueError):
        return []
    rows = d.get("decisions", []) if isinstance(d, dict) else []
    return [str(r["question_id"]) for r in rows if isinstance(r, dict) and r.get("decision") == "pending"
            and r.get("reason") == "not_reached_in_session" and r.get("question_id")]


RESUBMIT_AFTER_S = 3600


def copy_case(case_root: Path, case: Path, new: str) -> tuple[Path, dict]:
    """Copy a research case to a fresh batch id (session.out kept for QA independence)."""
    new_case = case_root / f"v3-{new}"
    tmp_case = case_root / f".v3-{new}.tmp"
    shutil.rmtree(tmp_case, ignore_errors=True)
    tmp_case.mkdir(mode=0o750)
    try:
        for f in sorted(case.iterdir()):
            if f.is_file() and not f.is_symlink():
                shutil.copy2(f, tmp_case / f.name)
        batch = json.loads((tmp_case / "batch.json").read_text("utf-8"))
        batch["batch_id"] = new
        atomic_write(tmp_case / "batch.json", batch)
        d = json.loads((tmp_case / "decisions.draft.json").read_text("utf-8"))
        d["batch_id"] = new
        atomic_write(tmp_case / "decisions.draft.json", d)
        tmp_case.rename(new_case)
    finally:
        shutil.rmtree(tmp_case, ignore_errors=True)
    return new_case, batch


def revalidate_staged(state: dict, queue: Path, case_root: Path, now: float, prevalidate) -> list[tuple[str, str]]:
    """Run the publisher's own exact-PDF validator over staged drafts not yet checked.

    The publisher defers a whole group when any decision fails that validator,
    and its operator case is immutable, so such a batch can never import.
    A clean draft is left byte-identical (QA SHA pins stay valid). A draft with
    rejects is copied ONCE to a fresh id carrying only validator-accepted
    proposals (old request renamed, never deleted); an emptied draft is withdrawn.
    prevalidate(case_dir, write_if_clean) -> counts; raises leave the batch for a later run.
    """
    done = []
    batches = state.get("batches", {})
    for bid, b in list(batches.items()):
        if b["status"] != "staged" or b.get("prevalidated"):
            continue
        case = case_root / f"v3-{bid}"
        req = queue / f"{bid}.json"
        has_req = req.is_file() and not req.is_symlink()
        if not has_req:
            # Newly researched batch whose request was held back: validate in place.
            try:
                counts = prevalidate(case, True)
            except Exception as exc:
                done.append((bid, f"prevalidate_error:{type(exc).__name__}"))
                continue
            b["prevalidated"] = True
            sc = staged_counts(case)
            if sc["drafted"] == 0:
                b["status"], b["drafted"] = "no_proposals", 0
                done.append((bid, "validator_emptied"))
            else:
                b["drafted"] = sc["drafted"]
                write_request(queue, json.loads((case / "batch.json").read_text("utf-8")), case, sc, now)
                done.append((bid, f"requested:{counts.get('demoted_by_validator', 0)}_demoted"))
            continue
        new = hashlib.sha256(f"{bid}:validated".encode()).hexdigest()[:16]
        if new in batches or (case_root / f"v3-{new}").exists():
            continue
        new_case, batch = copy_case(case_root, case, new)
        try:
            counts = prevalidate(new_case, False)
        except Exception as exc:
            shutil.rmtree(new_case, ignore_errors=True)
            done.append((bid, f"prevalidate_error:{type(exc).__name__}"))
            continue
        if not counts.get("demoted_by_validator"):
            shutil.rmtree(new_case, ignore_errors=True)  # clean: keep the reviewed request as is
            b["prevalidated"] = True
            done.append((bid, "clean"))
            continue
        sc = staged_counts(new_case)
        b["status"] = "resubmitted"
        b["resubmitted_as"] = new
        if sc["drafted"] == 0:
            req.rename(queue / f"{bid}.json.withdrawn-validator-empty")
            batches[new] = {**b, "status": "no_proposals", "started": int(now), "drafted": 0,
                            "accepted": 0, "resubmitted_from": bid, "prevalidated": True}
            batches[new].pop("resubmitted_as", None)
            done.append((bid, f"{new}:emptied"))
            continue
        write_request(queue, batch, new_case, sc, now)
        req.rename(queue / f"{bid}.json.withdrawn-validator-{new}")
        batches[new] = {**b, "status": "staged", "started": int(now), "drafted": sc["drafted"],
                        "accepted": 0, "resubmitted_from": bid, "prevalidated": True}
        batches[new].pop("resubmitted_as", None)
        done.append((bid, new))
    state["unverified_staged"] = sum(1 for b in batches.values() if b["status"] == "staged")
    return done


def resubmit_stale(state: dict, queue: Path, case_root: Path, now: float) -> list[tuple[str, str]]:
    """One-time resubmission of staged batches the host QA can no longer reach.

    Host QA counts failures per batch id and skips an id after three; it also
    reviews requests oldest-first. A request whose draft was rewritten AFTER
    the request (offline normalization of a pre-normalize packet) may have
    exhausted that counter on the old, unreviewable content with zero model
    spend. Such a batch, still without a receipt an hour later, is copied to a
    fresh id and the old request is withdrawn (renamed, never deleted).
    Bounded: a resubmitted batch is never resubmitted again. Requests of
    failed batches are withdrawn so they cannot hold the oldest-first slot.
    """
    done = []
    batches = state.get("batches", {})
    for bid, b in list(batches.items()):
        req = queue / f"{bid}.json"
        if not req.is_file() or req.is_symlink():
            continue
        if b["status"] == "failed":
            req.rename(queue / f"{bid}.json.withdrawn-failed")
            done.append((bid, "withdrawn_failed"))
            continue
        if b["status"] != "staged" or b.get("resubmitted_from"):
            continue
        case = case_root / f"v3-{bid}"
        draft = case / "decisions.draft.json"
        try:
            request = json.loads(req.read_text("utf-8"))
            changed_after = draft.stat().st_mtime > float(request["created"]) + 60
        except (OSError, ValueError, KeyError, TypeError):
            continue
        if not changed_after or now - float(request["created"]) < RESUBMIT_AFTER_S:
            continue
        new = hashlib.sha256(f"{bid}:resubmit".encode()).hexdigest()[:16]
        if new in batches or (case_root / f"v3-{new}").exists():
            continue
        new_case, batch = copy_case(case_root, case, new)
        counts = staged_counts(new_case)
        write_request(queue, batch, new_case, counts, now)
        req.rename(queue / f"{bid}.json.withdrawn-resubmitted-{new}")
        batches[new] = {**b, "status": "staged", "started": int(now), "drafted": counts["drafted"],
                        "accepted": 0, "resubmitted_from": bid}
        b["status"] = "resubmitted"
        b["resubmitted_as"] = new
        done.append((bid, new))
    state["unverified_staged"] = sum(1 for b in batches.values() if b["status"] == "staged")
    return done


def guard(state: dict, now: float, free_bytes: int, max_unverified: int) -> list[str]:
    reasons = []
    if free_bytes < MIN_FREE_BYTES:
        reasons.append("disk_below_3GiB")
    if state.get("quota_backoff_until", 0) > now:
        reasons.append("provider_retry_after_backoff")
    if state.get("zero_accepted_streak", 0) >= MAX_ZERO_ACCEPTED:
        reasons.append("hard_stop_two_zero_accepted_batches")
    if state.get("unverified_staged", 0) >= max_unverified:
        reasons.append("awaiting_trusted_operator_receipt")
    return reasons


# ---------------------------------------------------------------- live absence
LIVE_ABSENT = """SELECT q.id FROM bank_questions q WHERE q.id IN ({ids})
 AND NOT EXISTS(SELECT 1 FROM bank_question_sources s WHERE s.question_id=q.id
 AND s.workspace_id=q.workspace_id AND s.node_id IS NOT NULL AND s.pdf_page>0
 AND s.pdf_sha256 IS NOT NULL)"""


def live_absent(ids: list[str]) -> set[str]:
    from classification_v3 import manifest  # SELECT-only helper
    if not all(ID_RE.match(i) for i in ids):
        raise Blocked("invalid_question_id")
    return {r[0] for r in manifest.sql(LIVE_ABSENT.format(ids=",".join(f"'{i}'" for i in ids)))}


# ---------------------------------------------------------------- session
def render_prompt(batch: dict, case_dir: Path, template: str, kill_at_utc: str = "unknown") -> str:
    for e in batch["selected_editions"]:
        if not EDITION_RE.match(e):
            raise Blocked("edition_key_invalid")
    if not SUBJECT_RE.match(batch["subject"]):
        raise Blocked("subject_invalid")
    return (template.replace("{{BATCH_ID}}", batch["batch_id"])
            .replace("{{CASE_DIR}}", str(case_dir))
            .replace("{{SUBJECT}}", batch["subject"])
            .replace("{{EXAM_TYPE}}", batch["type"])
            .replace("{{EDITIONS}}", ", ".join(batch["selected_editions"]))
            .replace("{{PROXY_BASIS}}", batch["proxy_basis"] or "none (official exam-year list)")
            .replace("{{KEY_CLASS}}", batch["key_class"])
            .replace("{{YEARS}}", ", ".join(str(y) for y in batch["years"]))
            .replace("{{KILL_AT_UTC}}", kill_at_utc))


def scan_quota(text: str) -> tuple[bool, int | None]:
    """Detect actual provider limits including API JSON usage_limit_reached and reset clock."""
    m = RETRY_RE.search(text)
    if m:
        return True, max(60, min(int(m.group(1)), 48 * 3600))
    limited = bool(RATE_RE.search(text))
    reset = RESET_UTC_RE.search(text)
    if limited and reset:
        hr, minute = int(reset.group(1)), int(reset.group(2))
        if not (1 <= hr <= 12 and 0 <= minute <= 59):
            return True, None
        hour = hr % 12 + (12 if reset.group(3).lower() == "pm" else 0)
        now = datetime.now(timezone.utc)
        target = now.replace(hour=hour, minute=minute, second=0, microsecond=0)
        if target <= now:
            target += timedelta(days=1)
        return True, min(48 * 3600, max(60, int((target-now).total_seconds()) + 120))
    return limited, None


def run_session(cmd: list[str], prompt: str, cwd: Path, out_path: Path,
                timeout: int) -> dict:
    """Run one bounded session; raw output goes only to a private 0600 file."""
    env = {k: os.environ[k] for k in ENV_KEEP if k in os.environ}
    fd = os.open(out_path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    t0 = time.time()
    with os.fdopen(fd, "wb") as out:
        proc = subprocess.Popen(cmd, stdin=subprocess.PIPE, stdout=out, stderr=subprocess.STDOUT,
                                cwd=cwd, env=env, start_new_session=True,
                                preexec_fn=lambda: os.nice(10))
        try:
            proc.communicate(prompt.encode(), timeout=timeout)
            rc, killed = proc.returncode, False
        except subprocess.TimeoutExpired:
            os.killpg(proc.pid, signal.SIGTERM)
            try:
                proc.wait(30)
            except subprocess.TimeoutExpired:
                os.killpg(proc.pid, signal.SIGKILL)
                proc.wait()
            rc, killed = -9, True
    tail = out_path.read_bytes()[-8192:].decode("utf-8", "replace")
    quota, retry = scan_quota(tail)
    return {"rc": rc, "killed": killed, "seconds": round(time.time() - t0),
            "output_bytes": out_path.stat().st_size, "quota_signal": quota, "retry_after": retry}


def staged_counts(case_dir: Path) -> dict:
    """Counts drafted (NOT accepted) proposals the session staged privately."""
    p = case_dir / "decisions.draft.json"
    try:
        d = json.loads(p.read_text("utf-8"))
        rows = d.get("decisions", []) if isinstance(d, dict) else []
        return {"drafted": sum(1 for r in rows if r.get("decision") == "proposed"),
                "pending": sum(1 for r in rows if r.get("decision") != "proposed"),
                "precheck_present": (case_dir / "precheck.json").exists()}
    except (OSError, ValueError):
        return {"drafted": 0, "pending": 0, "precheck_present": False, "draft_missing": True}


def write_request(queue: Path, batch: dict, case_dir: Path, counts: dict, now: float) -> Path:
    req = {"format": REQUEST_FORMAT, "batch_id": batch["batch_id"], "created": int(now),
           "case_dir": str(case_dir), "status": "awaiting_independent_qa",
           "drafted_not_accepted": counts.get("drafted", 0),
           "selected_editions": batch["selected_editions"], "proxy_basis": batch["proxy_basis"],
           "key_class": batch["key_class"], "question_count": len(batch["question_ids"]),
           "required_steps": [
               "independent_opus_qa_review(prompts/qa_review.md) -> qa.json approved",
               "fanoosupd: trusted provenance audit of case_dir",
               "fanoosupd: re-export study from live DB and compare with pinned package",
               "fanoosupd: verified full backup <= 4h",
               "fanoosupd: safe preview (dry run)",
               "fanoosupd: guarded source-only import + independent readback",
               f"fanoosupd: write {RECEIPT_FORMAT} receipt (accepted_source_rows from readback)"]}
    queue.mkdir(parents=True, exist_ok=True)
    path = queue / f"{batch['batch_id']}.json"
    atomic_write(path, req)
    return path


# ---------------------------------------------------------------- main loop
def log(event: str, **kw) -> None:
    """Filtered log: ids/counts/codes only, never stems, PDF text or model output."""
    safe = {k: v for k, v in kw.items() if isinstance(v, (int, float, bool, type(None)))
            or (isinstance(v, str) and len(v) <= 120 and "\n" not in v)}
    print(json.dumps({"t": int(time.time()), "event": event, **safe}, sort_keys=True), flush=True)


def run(a) -> int:
    if os.geteuid() == 0 or a.forbidden_user_check():
        log("refused", reason="privileged_or_operator_identity")
        return 2
    a.state_dir.mkdir(parents=True, exist_ok=True, mode=0o750)
    lock_fh = open(a.state_dir / "runner.lock", "w")
    try:
        fcntl.flock(lock_fh, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        log("refused", reason="another_runner_holds_lock")
        return 3
    state_path = a.state_dir / "state.json"
    items = json.loads(a.manifest.read_text("utf-8"))["items"]
    template = PROMPT.read_text("utf-8")
    executed = 0
    for _ in range(a.max_batches):
        now = time.time()
        state = reconcile(load_json(state_path, {}), trusted_receipts(a.receipts, a.trusted_uids), now)
        if not a.dry_run:
            for old_id, outcome in revalidate_staged(state, a.queue, a.case_root, now, a.prevalidate):
                log("revalidate", batch_id=old_id, outcome=outcome)
            for old_id, outcome in resubmit_stale(state, a.queue, a.case_root, now):
                log("resubmit", batch_id=old_id, outcome=outcome)
        atomic_write(state_path, state)
        free = shutil.disk_usage(a.case_root).free
        blockers = guard(state, now, free, a.max_unverified)
        if a.not_before_utc and now < a.not_before_utc.timestamp():
            blockers.append("before_provider_reset")
        if a.deadline_utc and now >= a.deadline_utc.timestamp():
            blockers.append("48h_deadline_reached")
        batches, blocked = plan_batches(items, excluded_ids(state))
        log("plan", batches=len(batches), questions=sum(len(b["question_ids"]) for b in batches),
            lane=batches[0]["lane"] if batches else None, blocked=json.dumps(blocked)[:120],
            free_gib=round(free / 1024 ** 3, 2), blockers=",".join(blockers) or None,
            accepted_total=state.get("accepted_total", 0), unverified=state.get("unverified_staged", 0))
        if a.dry_run:
            if batches:
                b = batches[0]
                log("dry_run_first_batch", batch_id=b["batch_id"], lane=b["lane"], subject=b["subject"],
                    editions=",".join(b["selected_editions"])[:120], basis=b["proxy_basis"],
                    key_class=b["key_class"], n=len(b["question_ids"]))
            return 0
        if blockers or not batches:
            log("stop", reason=",".join(blockers) or "no_runnable_batch")
            if set(blockers).issubset({"before_provider_reset", "48h_deadline_reached"}):
                return 0
            return 0 if not blockers else 4
        batch = batches[0]
        if a.limit:
            batch = {**batch, "question_ids": batch["question_ids"][:a.limit]}
        planned = set(batch["question_ids"])
        absent = live_absent(batch["question_ids"])
        batch["question_ids"] = [q for q in batch["question_ids"] if q in absent]
        if not batch["question_ids"]:
            log("skip", batch_id=batch["batch_id"], reason="already_sourced_live")
            items = [i for i in items if str(i["question_id"]) not in planned]
            continue
        bid = batch["batch_id"]
        case_dir = a.case_root / f"v3-{bid}"
        if bid in state["batches"]:  # same id set re-dispatched: keep the old record and case
            stamp = int(now)
            state.setdefault("superseded", []).append({"batch_id": bid, "at": stamp, **state["batches"][bid]})
            if case_dir.exists():
                case_dir.rename(a.case_root / f".prev-v3-{bid}-{stamp}")
        case_dir.mkdir(parents=True, exist_ok=True, mode=0o750)
        for q in batch["question_ids"]:
            state.setdefault("attempts", {})[q] = state.get("attempts", {}).get(q, 0) + 1
        atomic_write(case_dir / "batch.json", batch)
        state["batches"][bid] = {"status": "leased", "started": int(now), "lease_expires": now + LEASE_S,
                                 "question_ids": batch["question_ids"], "accepted": 0}
        atomic_write(state_path, state)
        tmp = Path(tempfile.mkdtemp(prefix=f"v3-run-{bid}-", dir=a.tmp_root))
        try:
            # Non-model prep: SELECT-only questions + page leads, so the session
            # starts reading pages. A prep failure never blocks the session.
            try:
                log("prep", batch_id=bid, **{k: json.dumps(v)[:200] for k, v in getattr(a, "prepare", lambda *_: {})(case_dir, batch).items()})
            except Exception as exc:
                log("prep_failed", batch_id=bid, error=type(exc).__name__)
            remaining = int(a.deadline_utc.timestamp() - time.time()) if a.deadline_utc else a.timeout
            if remaining < 10:
                raise Blocked("48h_deadline_reached_before_model")
            limit = min(a.timeout, remaining)
            kill_at = datetime.fromtimestamp(time.time() + limit, timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
            res = run_session(a.session_cmd, render_prompt(batch, case_dir, template, kill_at),
                              a.workdir, case_dir / "session.out", limit)
        finally:
            shutil.rmtree(tmp, ignore_errors=True)
        counts = staged_counts(case_dir)
        rec = state["batches"][bid]
        if res["quota_signal"]:
            state["quota_backoff_until"] = now + (res["retry_after"] or a.default_backoff)
            rec["status"] = "lease_expired"  # requeue after backoff; nothing staged as accepted
        elif res["rc"] != 0 or res["killed"] or counts.get("draft_missing"):
            rec["status"] = "failed"  # held for operator inspection, never retried blindly
        elif counts["drafted"] == 0:
            # Nothing for QA: do not consume an unverified slot or invite a
            # zero-accepted receipt. Ids stay excluded until an operator re-plans.
            rec["status"] = "no_proposals"
            rec["drafted"] = 0
        else:
            # Trusted non-model normalization: identity/policy fields from prep + catalog,
            # exporter-identical study files for host QA. Failure leaves the batch unstaged.
            try:
                log("normalize", batch_id=bid, **{k: v for k, v in getattr(a, "normalize", lambda *_: {})(case_dir, batch).items()})
                counts = staged_counts(case_dir)
            except Exception as exc:
                log("normalize_failed", batch_id=bid, error=type(exc).__name__)
                counts = {**counts, "normalize_failed": True}
            if counts.get("normalize_failed"):
                rec["status"] = "failed"
            elif counts["drafted"] == 0:
                rec["status"] = "no_proposals"
                rec["drafted"] = 0
            else:
                rec["status"] = "staged"
                rec["drafted"] = counts["drafted"]
                # Request is written only after the publisher's validator passes.
                for _, outcome in revalidate_staged({"batches": {bid: rec}}, a.queue, a.case_root, now, a.prevalidate):
                    log("revalidate", batch_id=bid, outcome=outcome)
        rec["unreached"] = [q for q in unreached_ids(case_dir) if q in set(batch["question_ids"])]
        rec.update({k: res[k] for k in ("rc", "killed", "seconds")})
        atomic_write(state_path, state)
        executed += 1
        log("batch_done", batch_id=bid, status=rec["status"], drafted_not_accepted=counts.get("drafted", 0),
            rc=res["rc"], killed=res["killed"], seconds=res["seconds"], quota=res["quota_signal"])
    log("max_batches_reached", executed=executed)
    return 0


def parse(argv=None):
    p = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    p.add_argument("--manifest", type=Path, required=True)
    p.add_argument("--state-dir", type=Path, required=True)
    p.add_argument("--case-root", type=Path, required=True, help="private research case root")
    p.add_argument("--queue", type=Path, required=True, help="operator request queue dir")
    p.add_argument("--receipts", type=Path, required=True, help="trusted operator receipt dir")
    p.add_argument("--trusted-uid", type=int, action="append", default=[])
    p.add_argument("--tmp-root", type=Path, default=RESEARCH_TMP)
    p.add_argument("--workdir", type=Path, default=ROOT)
    p.add_argument("--max-batches", type=int, default=1)
    p.add_argument("--max-unverified", type=int, default=DEFAULT_MAX_UNVERIFIED)
    p.add_argument("--limit", type=int, default=0, help="pilot: cap ids in the first batch")
    p.add_argument("--timeout", type=int, default=SESSION_TIMEOUT_S)
    p.add_argument("--default-backoff", type=int, default=1800)
    p.add_argument("--not-before-utc", type=lambda s: datetime.fromisoformat(s.replace("Z", "+00:00")))
    p.add_argument("--deadline-utc", type=lambda s: datetime.fromisoformat(s.replace("Z", "+00:00")))
    p.add_argument("--dry-run", action="store_true")
    p.add_argument("--session-cmd", nargs="+",
                   default=["/mnt/workspace/home/bin/claude", "--print", "--model", "opus",
                            "--effort", "medium", "--permission-mode", "auto",
                            "--tools", "Bash,Read,Write,Edit,Glob,Grep",
                            "--add-dir", "/srv/fanoos/shared/research",
                            "/srv/fanoos/current",
                            "--output-format", "json"])
    a = p.parse_args(argv)
    if not 1 <= a.max_batches <= 6 or not 1 <= a.max_unverified <= 3 or a.limit < 0:
        p.error("bounds: max-batches 1..6, max-unverified 1..3")
    a.trusted_uids = set(a.trusted_uid)
    if not a.dry_run and not a.trusted_uids:
        p.error("--trusted-uid (operator receipt owner) is required for execution")
    a.forbidden_user_check = lambda: _username() == "fanoosupd"
    a.prepare = lambda case, batch: _prepare(case, batch)  # late-bound for tests
    a.normalize = lambda case, batch: _normalize(case, batch)
    a.prevalidate = lambda case, write_if_clean: _prevalidate(case, write_if_clean)
    return a


def _prepare(case_dir: Path, batch: dict) -> dict:
    from classification_v3 import prep
    return prep.prepare(case_dir, batch)


def _normalize(case_dir: Path, batch: dict) -> dict:
    from classification_v3 import normalize
    return normalize.normalize_case(case_dir, batch)


def _prevalidate(case_dir: Path, write_if_clean: bool) -> dict:
    from classification_v3 import normalize
    return normalize.prevalidate_case(case_dir, write_if_clean=write_if_clean)


def _username() -> str:
    try:
        import pwd
        return pwd.getpwuid(os.getuid()).pw_name
    except KeyError:
        return ""


def main(argv=None) -> int:
    sys.path.insert(0, str(HERE.parent))
    return run(parse(argv))


if __name__ == "__main__":
    raise SystemExit(main())
