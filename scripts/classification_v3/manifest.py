"""Read-only residency/national backlog manifest and lane queue for Classifier V3.

Reads the live DB (SELECT only), never question text. Each still-incomplete
question (no same-row node_id + pdf_page>0 + pdf_sha256 source) is assigned one
lane. Re-running is idempotent: completed questions drop out via the live
anti-join, so a queue never repeats an already-complete source row.

Lanes (priority order; residency always first):
  residency_dental_ready         exact-year official edition with approved PDF + bound map
  residency_dental_proxy_policy  1391-1396 without exact-year official list: needs the
                                 newest-first proxy resolver (retrospective/same-lineage)
  residency_dental_blocked_pdf_map  official edition lacks approved PDF or bound map
  residency_english_blocked_policy  no book/chapter; never fake a chapter
  national_dental_same_edition_ready / _other_ready / _blocked_pdf_map / _no_official
  national_english_separate / national_basic_sciences_excluded
"""
from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEFAULTS = Path("/etc/fanoos/mysql-migrator.cnf")
PROXY_YEARS = range(1391, 1397)

QUERY = """
SELECT q.id, t.type_key, st.exam_year, st.exam_round, s.subject_key,
  COALESCE((SELECT aa.status FROM bank_official_answers aa
     WHERE aa.question_id=q.id AND aa.workspace_id=q.workspace_id
     ORDER BY CASE WHEN aa.status IN ('final','amended') THEN 0 ELSE 1 END,
              aa.recorded_at DESC, aa.id DESC LIMIT 1), 'none'),
  COALESCE((SELECT GROUP_CONCAT(DISTINCT CONCAT(r.reference_key,'@',e.edition_key)
       ORDER BY e.published_year DESC SEPARATOR ',')
     FROM bank_reference_validity rv
     JOIN bank_reference_editions e ON e.id=rv.edition_id AND e.workspace_id=rv.workspace_id
     JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
     WHERE rv.workspace_id=q.workspace_id AND rv.exam_type_id=t.id
       AND rv.exam_year=st.exam_year AND rv.subject_id=q.subject_id
       AND rv.is_official=1), ''),
  (SELECT COUNT(*) FROM bank_question_sources x
     WHERE x.question_id=q.id AND x.workspace_id=q.workspace_id)
FROM bank_questions q
JOIN bank_exam_sittings st ON st.id=q.sitting_id AND st.workspace_id=q.workspace_id
JOIN bank_exam_types t ON t.id=st.exam_type_id AND t.workspace_id=q.workspace_id
JOIN bank_subjects s ON s.id=q.subject_id AND s.workspace_id=q.workspace_id
WHERE t.type_key IN ('residency','national')
  AND NOT EXISTS(SELECT 1 FROM bank_question_sources src
    WHERE src.question_id=q.id AND src.workspace_id=q.workspace_id
      AND src.node_id IS NOT NULL AND src.pdf_page>0 AND src.pdf_sha256 IS NOT NULL)
ORDER BY t.type_key DESC, st.exam_year, st.exam_round, s.subject_key, q.id
"""

TOTALS = """
SELECT t.type_key, COUNT(*) FROM bank_questions q
JOIN bank_exam_sittings st ON st.id=q.sitting_id AND st.workspace_id=q.workspace_id
JOIN bank_exam_types t ON t.id=st.exam_type_id AND t.workspace_id=q.workspace_id
WHERE t.type_key IN ('residency','national') GROUP BY t.type_key
"""

RESIDENCY_EDITIONS = """
SELECT DISTINCT s.subject_key, CONCAT(r.reference_key,'@',e.edition_key), e.published_year
FROM bank_reference_validity rv
JOIN bank_exam_types t ON t.id=rv.exam_type_id AND t.workspace_id=rv.workspace_id
JOIN bank_subjects s ON s.id=rv.subject_id AND s.workspace_id=rv.workspace_id
JOIN bank_reference_editions e ON e.id=rv.edition_id AND e.workspace_id=rv.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
WHERE t.type_key='residency' AND rv.is_official=1
ORDER BY e.published_year DESC
"""

APPROVED_PDFS = """
SELECT m.topic, LOWER(HEX(o.checksum_sha256))
FROM content_resources r
JOIN content_resource_metadata m ON m.resource_id=r.id AND m.workspace_id=r.workspace_id
JOIN content_resource_versions v ON v.resource_id=r.id AND v.workspace_id=r.workspace_id
  AND v.version_no=r.current_version_no
JOIN content_objects o ON o.id=v.object_id AND o.workspace_id=v.workspace_id
WHERE m.format_key='reference_pdf' AND m.access_level='private'
  AND r.lifecycle_status='published' AND r.deleted_at IS NULL AND r.archived_at IS NULL
  AND v.status='approved' AND o.status='verified' AND o.classification='private'
  AND o.detected_mime='application/pdf' AND o.deleted_at IS NULL
"""


def sql(query: str, defaults: Path = DEFAULTS, database: str = "fanoos_prod") -> list[list[str]]:
    proc = subprocess.run(["mysql", f"--defaults-extra-file={defaults}", "-N", "--batch",
                           "-D", database, "-e", query],
                          capture_output=True, check=False, timeout=120)
    if proc.returncode:
        raise RuntimeError("read-only manifest query failed")
    out = proc.stdout.decode("utf-8").strip()
    return [line.split("\t") for line in out.splitlines()] if out else []


def ready_editions(editions: list[str], pdfs: dict[str, str], maps: dict) -> list[str]:
    """Editions with exactly one approved PDF and a chapter map bound to that SHA."""
    return [e for e in editions
            if e in pdfs and maps.get(e, {}).get("source_pdf_sha256") == pdfs[e]
            and maps[e].get("runs")]


def classify(row: dict, pdfs: dict[str, str], maps: dict, residency_eds: set[tuple[str, str]]) -> str:
    kind, subject, year = row["type"], row["subject"], row["year"]
    editions = row["official_editions"]
    if subject == "english":
        return "residency_english_blocked_policy" if kind == "residency" else "national_english_separate"
    if kind == "national" and subject == "basic-sciences":
        return "national_basic_sciences_excluded"
    ready = ready_editions(editions, pdfs, maps)
    if kind == "residency":
        if ready:
            return "residency_dental_ready"
        if not editions and year in PROXY_YEARS:
            return "residency_dental_proxy_policy"
        return "residency_dental_blocked_pdf_map" if editions else "residency_dental_no_official"
    if not editions:
        return "national_dental_no_official"
    shared = any((subject, e) in residency_eds for e in editions)
    if not ready:
        return "national_dental_blocked_pdf_map"
    return "national_dental_same_edition_ready" if shared else "national_dental_other_ready"


PRIORITY = ["residency_dental_ready", "residency_dental_proxy_policy",
            "national_dental_same_edition_ready", "national_dental_other_ready"]


def build(rows: list[list[str]], pdfs: dict[str, str], maps: dict,
          residency_eds: set[tuple[str, str]], edition_years: dict[str, int] | None = None) -> dict:
    items = []
    for qid, kind, year, rnd, subject, key_status, eds, nsrc in rows:
        row = {"question_id": qid, "type": kind, "year": int(year), "round": int(rnd),
               "subject": subject, "official_key_status": key_status,
               "official_editions": [e for e in eds.split(",") if e],
               "existing_source_rows": int(nsrc)}
        row["lane"] = classify(row, pdfs, maps, residency_eds)
        row["ready_editions"] = ready_editions(row["official_editions"], pdfs, maps)
        row["shared_with_residency"] = any((row["subject"], e) in residency_eds
                                           for e in row["official_editions"])
        # Candidate-only: the reviewed proxy policy modules still choose the
        # newest eligible family edition and record newer_editions_searched.
        # Newest-first when published years are known (runner fails closed otherwise).
        cands = sorted({e for s, e in residency_eds if s == row["subject"]},
                       key=lambda e: (-(edition_years or {}).get(e, 0), e))
        row["proxy_candidate_editions"] = (ready_editions(cands, pdfs, maps)
            if row["lane"] == "residency_dental_proxy_policy" else [])
        row["proxy_candidates_ordered"] = bool(edition_years) and all(
            e in edition_years for e in row["proxy_candidate_editions"])
        items.append(row)
    items.sort(key=lambda r: (PRIORITY.index(r["lane"]) if r["lane"] in PRIORITY else 99,
                              r["year"], r["subject"], r["question_id"]))
    return {"items": items}


def summarize(items: list[dict]) -> dict:
    agg = lambda f: dict(sorted(Counter(f(i) for i in items).items()))
    return {
        "by_type": agg(lambda i: i["type"]),
        "by_lane": agg(lambda i: i["lane"]),
        "by_type_year": agg(lambda i: f"{i['type']}:{i['year']}:{'english' if i['subject']=='english' else 'dental' if i['subject']!='basic-sciences' else 'basic'}"),
        "by_type_subject": agg(lambda i: f"{i['type']}:{i['subject']}"),
        "by_key_status": agg(lambda i: f"{i['type']}:{i['official_key_status']}"),
        "national_dental_shared_edition": {str(k): v for k, v in Counter(
            i["shared_with_residency"] for i in items
            if i["type"] == "national" and i["subject"] not in ("english", "basic-sciences")).items()},
        "proxy_lane_with_ready_candidate": dict(Counter(
            f"{i['year']}:{i['subject']}:{'has_candidate' if i['proxy_candidate_editions'] else 'none'}"
            for i in items if i["lane"] == "residency_dental_proxy_policy")),
        "ready_by_edition": agg(lambda i: ",".join(i["ready_editions"]) or "-"),
    }


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    p.add_argument("--queue-out", type=Path, help="private queue JSON (protected storage only)")
    p.add_argument("--summary-only", action="store_true")
    a = p.parse_args(argv)
    maps = json.loads((ROOT / "data/bank/reference-chapter-pages.json").read_text("utf-8"))["editions"]
    pdf_rows = sql(APPROVED_PDFS)
    seen = Counter(t for t, _ in pdf_rows)
    pdfs = {t: h for t, h in pdf_rows if seen[t] == 1}
    ed_rows = sql(RESIDENCY_EDITIONS)
    residency_eds = {(s, e) for s, e, _ in ed_rows}
    years = {e: int(y) for _, e, y in ed_rows if y.isdigit()}
    built = build(sql(QUERY), pdfs, maps, residency_eds, years)
    totals = {k: int(v) for k, v in sql(TOTALS)}
    now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    summary = {"format": "fanoos.classifier-v3.backlog-manifest.v1", "generated_at": now,
               "totals": totals, "incomplete": len(built["items"]), **summarize(built["items"])}
    if a.queue_out and not a.summary_only:
        body = {**summary, "items": built["items"]}
        body["sha256_of_items"] = hashlib.sha256(
            json.dumps(built["items"], sort_keys=True).encode()).hexdigest()
        tmp = a.queue_out.with_suffix(".tmp")
        tmp.write_bytes(json.dumps(body, ensure_ascii=False, sort_keys=True, indent=1).encode())
        tmp.chmod(0o640)
        tmp.replace(a.queue_out)  # atomic; immutable snapshot named by timestamp
    json.dump(summary, sys.stdout, ensure_ascii=False, indent=1, sort_keys=True)
    print()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
