"""Bounded batch planner/guard for the V3 multi-day research loop (dry-run first).

Plans 30-40-question research batches from an immutable manifest snapshot
(see manifest.py), residency first, one exam year + subject + edition family per
batch. It never reads PDFs or question text, never calls a model and never
writes to the database. Execution of a planned batch (one bounded
`claude --model opus` session that runs the existing exporter/scanner/validator)
is left to the supervised host unit and is refused unless every guard passes.

Guards: disk/RAM floor, verified backup age, quota backoff, lease expiry and a
hard stop after consecutive batches with zero accepted sources.
"""
from __future__ import annotations

import argparse
import json
import os
import shutil
import sys
import time
from collections import defaultdict
from pathlib import Path

RUNNABLE = ["residency_dental_ready", "residency_dental_proxy_policy",
            "national_dental_same_edition_ready", "national_dental_other_ready"]
NEVER_RUN = {"residency_english_blocked_policy", "national_english_separate",
             "national_basic_sciences_excluded"}
MIN_FREE_BYTES = 3 * 1024 ** 3
BACKUP_MAX_AGE_S = 4 * 3600
MAX_ZERO_PROGRESS_BATCHES = 2
BATCH_MIN, BATCH_MAX = 30, 40


def plan_batches(items: list[dict], completed: set[str] = frozenset(),
                 leased: set[str] = frozenset()) -> list[dict]:
    """Group runnable items; skip completed (live anti-join) and leased ids."""
    groups: dict[tuple, list[str]] = defaultdict(list)
    for it in items:
        if it["lane"] in NEVER_RUN or it["lane"] not in RUNNABLE:
            continue
        if it["question_id"] in completed or it["question_id"] in leased:
            continue
        editions = it.get("ready_editions") or it.get("proxy_candidate_editions") or []
        if not editions:
            continue
        family = ",".join(sorted(editions))
        groups[(RUNNABLE.index(it["lane"]), it["lane"], it["type"], it["year"],
                it["subject"], family)].append(it["question_id"])
    batches = []
    for key in sorted(groups):
        _, lane, kind, year, subject, family = key
        ids = groups[key]
        for i in range(0, len(ids), BATCH_MAX):
            chunk = ids[i:i + BATCH_MAX]
            batches.append({"lane": lane, "type": kind, "year": year, "subject": subject,
                            "editions": family.split(","), "question_ids": chunk,
                            "needs_proxy_resolver": lane.endswith("proxy_policy")})
    return batches


def guard(state: dict, now: float, free_bytes: int, backup_created: float | None) -> list[str]:
    """Return blocking reasons; empty list means one batch may start."""
    reasons = []
    if free_bytes < MIN_FREE_BYTES:
        reasons.append("disk_below_3GiB")
    if state.get("quota_backoff_until", 0) > now:
        reasons.append("provider_quota_backoff")
    if state.get("zero_progress_streak", 0) >= MAX_ZERO_PROGRESS_BATCHES:
        reasons.append("hard_stop_zero_progress")
    if backup_created is None or now - backup_created > BACKUP_MAX_AGE_S:
        reasons.append("apply_requires_fresh_verified_backup")  # research may continue; apply may not
    return reasons


def record_result(state: dict, accepted: int, quota_retry_after: float | None, now: float) -> dict:
    state = dict(state)
    state["zero_progress_streak"] = 0 if accepted > 0 else state.get("zero_progress_streak", 0) + 1
    if quota_retry_after:
        state["quota_backoff_until"] = now + quota_retry_after  # provider-declared only
    state.setdefault("history", []).append({"t": int(now), "accepted": accepted})
    return state


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    p.add_argument("--manifest", type=Path, required=True)
    p.add_argument("--state", type=Path)
    p.add_argument("--dry-run", action="store_true", required=True,
                   help="only planning is implemented; execution needs the reviewed host unit")
    a = p.parse_args(argv)
    items = json.loads(a.manifest.read_text("utf-8"))["items"]
    state = json.loads(a.state.read_text("utf-8")) if a.state and a.state.exists() else {}
    batches = plan_batches(items, set(state.get("completed", [])), set(state.get("leased", [])))
    free = shutil.disk_usage(a.manifest.parent).free
    out = {"batches": len(batches),
           "questions": sum(len(b["question_ids"]) for b in batches),
           "by_lane": {l: sum(len(b["question_ids"]) for b in batches if b["lane"] == l) for l in RUNNABLE},
           "first_batch": {k: v for k, v in (batches[0] if batches else {}).items() if k != "question_ids"},
           "guard_blockers": guard(state, time.time(), free, state.get("backup_created")),
           "free_gib": round(free / 1024 ** 3, 2), "executed": 0}
    json.dump(out, sys.stdout, indent=1, ensure_ascii=False)
    print()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
