#!/usr/bin/env python3
"""Read-only, fail-closed planner for one/two complete-subject source cycles.

This does not publish, update the DB, validate scientific evidence or create
a backup. Existing SHA-pinned guarded operators remain the only writers.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

FORMAT = "fanoos.subject-publication-cycle/1"
OPERATORS = {
    "repair_verified_source_pages.php": ("fanoos.verified-source-page-repair/1", 12),
    "repair_verified_source_chapter_pages.php": (
        "fanoos.verified-source-chapter-page-correction/2", 1
    ),
}
SUBJECT = re.compile(r"^[a-z][a-z0-9-]{1,59}$")
SHA = re.compile(r"^[0-9a-f]{64}$")


class Rejected(ValueError):
    """A cycle is incomplete or is inconsistent with its protected plans."""


def _numbers(values: object, label: str) -> list[int]:
    if not isinstance(values, list) or any(
        type(x) is not int or x <= 0 or x > 300 for x in values
    ):
        raise Rejected(f"{label}: expected positive question numbers")
    if len(values) != len(set(values)):
        raise Rejected(f"{label}: duplicate question numbers")
    return values


def plan_cycle(manifest: dict, reports_dir: Path) -> dict:
    if manifest.get("format") != FORMAT or manifest.get("exam_type") != "residency":
        raise Rejected("Wrong cycle/exam format")
    year = manifest.get("year")
    if type(year) is not int or not 1396 <= year <= 1500 or manifest.get("round") != 1:
        raise Rejected("Invalid year or round")
    snapshot = manifest.get("live_question_inventory_sha256")
    if not isinstance(snapshot, str) or SHA.fullmatch(snapshot) is None:
        raise Rejected("Missing pinned live subject inventory hash")
    subjects = manifest.get("subjects")
    if not isinstance(subjects, list) or not 1 <= len(subjects) <= 2:
        raise Rejected("Each cycle must cover exactly one or two whole subjects")
    if not reports_dir.is_dir():
        raise Rejected("Protected reports directory unavailable")
    root = reports_dir.resolve(strict=True)
    seen_subjects = set()
    output = []
    for item in subjects:
        if not isinstance(item, dict):
            raise Rejected("Subject must be an object")
        subject = item.get("subject")
        if not isinstance(subject, str) or SUBJECT.fullmatch(subject) is None or subject == "english":
            raise Rejected("Invalid or exempt subject")
        if subject in seen_subjects:
            raise Rejected("Subject repeated within cycle")
        seen_subjects.add(subject)
        inventory = _numbers(item.get("all_question_numbers"), "full subject inventory")
        if not inventory:
            raise Rejected("Empty course")
        already = _numbers(item.get("already_verified_and_bound"), "existing source rows")
        holds_raw = item.get("holds", [])
        if not isinstance(holds_raw, list):
            raise Rejected("holds must be a list")
        holds = []
        for hold in holds_raw:
            if not isinstance(hold, dict) or not isinstance(hold.get("reason"), str) or len(hold["reason"].strip()) < 8:
                raise Rejected("Every unresolved question requires a specific hold reason")
            n = hold.get("number")
            if type(n) is not int:
                raise Rejected("Invalid held question")
            holds.append(n)
        holds = _numbers(holds, "held question numbers")
        actions = item.get("plans", [])
        if not isinstance(actions, list):
            raise Rejected("plans must be a list")
        planned = []
        seen_paths = set()
        ready = []
        for action in actions:
            if not isinstance(action, dict):
                raise Rejected("Invalid publication-plan record")
            operator = action.get("operator")
            spec = OPERATORS.get(operator)
            if spec is None:
                raise Rejected("Unapproved source-only operator")
            raw_path = action.get("path")
            expected = action.get("sha256")
            if not isinstance(raw_path, str) or not isinstance(expected, str) or SHA.fullmatch(expected) is None:
                raise Rejected("Protected plan identity missing")
            path = Path(raw_path)
            if path.is_symlink() or not path.is_file() or path.parent.resolve() != root:
                raise Rejected("Plan must be a regular file directly in protected reports directory")
            if path in seen_paths:
                raise Rejected("A plan cannot appear twice")
            seen_paths.add(path)
            raw = path.read_bytes()
            if hashlib.sha256(raw).hexdigest() != expected:
                raise Rejected("Protected plan SHA-256 changed")
            try:
                plan = json.loads(raw)
            except (ValueError, UnicodeDecodeError) as exc:
                raise Rejected("Invalid protected plan JSON") from exc
            if (plan.get("format") != spec[0] or plan.get("exam_type") != "residency"
                or plan.get("year") != year or plan.get("subject") != subject
                or not isinstance(plan.get("entries"), list)
                or not 1 <= len(plan["entries"]) <= spec[1]):
                raise Rejected("Plan is not eligible for this year/subject/operator")
            numbers = _numbers([e.get("number") if isinstance(e, dict) else None for e in plan["entries"]], "plan entries")
            ready.extend(numbers)
            planned.append({"operator": operator, "path": str(path), "sha256": expected, "question_numbers": numbers})
        all_ids = already + holds + ready
        if len(all_ids) != len(set(all_ids)) or set(all_ids) != set(inventory):
            raise Rejected(f"{subject}: the entire course must be accounted for exactly once")
        output.append({
            "subject": subject,
            "total_question_count": len(inventory),
            "already_bound_count": len(already),
            "ready_source_count": len(ready),
            "held_count": len(holds),
            "held_numbers": sorted(holds),
            "plans": planned,
            "publishable_now": bool(ready),
        })
    return {
        "format": "fanoos.subject-publication-cycle-preview/1",
        "year": year, "round": 1,
        "live_question_inventory_sha256": snapshot,
        "subject_count": len(output),
        "source_plans_count": sum(len(x["plans"]) for x in output),
        "ready_source_count": sum(x["ready_source_count"] for x in output),
        "subjects": output,
        "mode": "READ_ONLY_PLANNING_NO_DATABASE_WRITES",
        "next_step": "Independently verify fresh live DB, official map, original-PDF audits and backup; preview EVERY protected plan before applying ANY; run guarded operators under authorized updater; verify each receipt/live readback and publish frozen assessment once after cycle.",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--manifest", type=Path, required=True)
    parser.add_argument("--reports-dir", type=Path, default=Path("/srv/fanoos/shared/research/classification/reports"))
    args = parser.parse_args()
    try:
        manifest = json.loads(args.manifest.read_text(encoding="utf-8"))
        result = plan_cycle(manifest, args.reports_dir)
    except (OSError, ValueError, Rejected) as exc:
        print(f"Whole-subject publication cycle refused: {exc}", file=sys.stderr)
        return 1
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
