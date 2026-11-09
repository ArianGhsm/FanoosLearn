#!/usr/bin/env python3
"""Blind read-only retrieval benchmark for classifier v3.

Usage: python3 -B benchmark.py <edition> --per-type 8 --missing 4 --out <aggregate.json>

Queries are built only from stem, choices and the official key. Gold
node/pdf_page is fetched in a separate query and used only after the scan for
scoring. Only aggregate metrics are written; no question text, page text or
question IDs are printed or persisted. DB access is SELECT-only.
"""
from __future__ import annotations

import argparse, base64, json, resource, subprocess, sys, time
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path[:0] = [str(HERE), str(HERE.parent / "references")]
from scanner import Query, scan_edition  # noqa: E402
from terms import deterministic_terms  # noqa: E402

DB = ["mysql", "--defaults-extra-file=/etc/fanoos/mysql-migrator.cnf", "fanoos_prod", "-N", "-B", "-e"]
TYPES = ("board", "promotion", "national", "residency")
LETTERS = "abcdefgh"


def sql(q: str) -> list[list[str]]:
    out = subprocess.run(DB + [q], check=True, capture_output=True, text=True).stdout
    return [line.split("\t") for line in out.splitlines() if line]


def sample(edition: str, kind: str, n: int, atomic: bool) -> list[dict]:
    ref, ed = edition.split("@")
    cond = ("s.node_id IS NOT NULL AND s.pdf_page>0 AND s.pdf_sha256 IS NOT NULL" if atomic
            else "(s.pdf_page IS NULL OR s.pdf_page=0)")
    rows = sql(f"""
SELECT q.id, ANY_VALUE(REPLACE(TO_BASE64(q.stem),'\\n','')), ANY_VALUE(st.exam_year), ANY_VALUE(st.exam_round), ANY_VALUE(a.choice_position), ANY_VALUE(a.status)
FROM bank_question_sources s JOIN bank_questions q ON q.id=s.question_id
JOIN bank_exam_sittings st ON st.id=q.sitting_id JOIN bank_exam_types t ON t.id=st.exam_type_id
JOIN bank_reference_editions e ON e.id=s.edition_id JOIN bank_references r ON r.id=e.reference_id
LEFT JOIN bank_official_answers a ON a.question_id=q.id
WHERE r.reference_key='{ref}' AND e.edition_key='{ed}' AND t.type_key='{kind}' AND {cond}
GROUP BY q.id ORDER BY SHA2(q.id,256) LIMIT {n}""")
    out = []
    for qid, stem, year, rnd, pos, status in rows:
        ch = sql(f"SELECT position, REPLACE(TO_BASE64(COALESCE(text,'')),'\\n','') FROM bank_question_choices "
                 f"WHERE question_id='{qid}' ORDER BY position")
        choices = {LETTERS[int(p) - 1]: base64.b64decode(t).decode() for p, t in ch}
        key = LETTERS[int(pos) - 1] if pos not in (None, "NULL", "") else None
        out.append({"id": qid, "kind": kind, "year": year, "round": rnd, "status": status,
                    "stem": base64.b64decode(stem).decode(), "choices": choices, "key": key})
    return out


def sample_missing_officially_eligible(edition: str, kind: str, n: int) -> list[dict]:
    """Select questions with NO complete source, via exact-year official validity.

    The old sample(atomic=False) queried bank_question_sources and therefore
    silently excluded the most important backlog: questions with NO source row.
    This benchmark is not an edition-resolver: it samples only explicitly
    listed exact-year official editions, not same-lineage proxy candidates.
    """
    import re
    ref, ed = edition.split("@")
    if not re.fullmatch(r"[a-z0-9-]+", ref) or not re.fullmatch(r"[a-z0-9]+", ed):
        raise ValueError("unsafe edition label")
    if kind not in TYPES or not 0 <= n <= 100:
        raise ValueError("unsafe benchmark request")
    rows = sql(f"""
SELECT q.id, ANY_VALUE(REPLACE(TO_BASE64(q.stem),CHAR(10),'')),
       ANY_VALUE(st.exam_year), ANY_VALUE(st.exam_round),
       ANY_VALUE(a.choice_position), ANY_VALUE(a.status)
FROM bank_questions q
JOIN bank_exam_sittings st ON st.id=q.sitting_id AND st.workspace_id=q.workspace_id
JOIN bank_exam_types t ON t.id=st.exam_type_id AND t.workspace_id=q.workspace_id
JOIN bank_reference_validity rv ON rv.workspace_id=q.workspace_id
    AND rv.exam_type_id=t.id AND rv.exam_year=st.exam_year
    AND rv.subject_id=q.subject_id AND rv.is_official=1
JOIN bank_reference_editions e ON e.id=rv.edition_id AND e.workspace_id=rv.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
LEFT JOIN bank_official_answers a ON a.id=(
    SELECT aa.id FROM bank_official_answers aa
    WHERE aa.question_id=q.id AND aa.workspace_id=q.workspace_id
    ORDER BY CASE WHEN aa.status IN ('final','amended') THEN 0 ELSE 1 END,
             aa.recorded_at DESC, aa.id DESC LIMIT 1)
WHERE r.reference_key='{ref}' AND e.edition_key='{ed}' AND t.type_key='{kind}'
  AND NOT EXISTS(
    SELECT 1 FROM bank_question_sources src
    WHERE src.question_id=q.id AND src.workspace_id=q.workspace_id
      AND src.node_id IS NOT NULL AND src.pdf_page>0 AND src.pdf_sha256 IS NOT NULL
  )
GROUP BY q.id ORDER BY SHA2(q.id,256) LIMIT {n}""")
    result = []
    for qid, stem, year, rnd, pos, status in rows:
        choices_raw = sql(
            f"SELECT position, REPLACE(TO_BASE64(COALESCE(text,'')), CHAR(10), '') "
            f"FROM bank_question_choices WHERE question_id='{qid}' ORDER BY position"
        )
        choices = {LETTERS[int(k)-1]: base64.b64decode(v).decode()
                   for k,v in choices_raw if 1 <= int(k) <= len(LETTERS)}
        key = LETTERS[int(pos)-1] if pos not in ("NULL", "", None) and 1 <= int(pos) <= len(LETTERS) else None
        result.append({"id": qid, "kind": kind, "year": year, "round": rnd,
                       "status": status, "stem": base64.b64decode(stem).decode(),
                       "choices": choices, "key": key})
    return result


def gold_pages(edition: str, qids: list[str]) -> dict[str, set[int]]:
    ref, ed = edition.split("@")
    ids = ",".join(f"'{i}'" for i in qids)
    g: dict[str, set[int]] = {}
    for qid, page in sql(f"""
SELECT s.question_id, s.pdf_page FROM bank_question_sources s
JOIN bank_reference_editions e ON e.id=s.edition_id JOIN bank_references r ON r.id=e.reference_id
WHERE r.reference_key='{ref}' AND e.edition_key='{ed}' AND s.pdf_page>0 AND s.question_id IN ({ids})"""):
        g.setdefault(qid, set()).add(int(page))
    return g


def main(argv=None) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("edition"); ap.add_argument("--per-type", type=int, default=8)
    ap.add_argument("--missing", type=int, default=4); ap.add_argument("--out", required=True)
    a = ap.parse_args(argv)
    from verified_reference_pdf import open_verified_reference

    gold_rows = [q for k in TYPES for q in sample(a.edition, k, a.per_type, True)]
    miss_rows = [q for k in TYPES for q in sample_missing_officially_eligible(a.edition, k, a.missing)]
    rows = gold_rows + miss_rows
    queries = [Query.build(q["id"], *deterministic_terms(q["stem"], q["choices"], q["key"])) for q in rows]
    for q in rows:  # drop raw text before scan; nothing textual is kept
        q.pop("stem"); q.pop("choices")

    t0, c0 = time.time(), time.process_time()
    pdf = open_verified_reference(a.edition)
    ranking, stats = scan_edition(pdf, queries)
    wall, cpu = time.time() - t0, time.process_time() - c0

    gold = gold_pages(a.edition, [q["id"] for q in gold_rows])  # scoring only, after retrieval
    per: dict[str, dict] = {}
    for q in rows:
        m = per.setdefault(q["kind"], {"gold": 0, "top1": 0, "top3": 0, "top5": 0, "fp_top5": 0,
                                       "missing": 0, "missing_with_candidates": 0, "no_key": 0,
                                       "zero_lexical_pending": 0})
        cands = [pg for _, pg in ranking.get(q["id"], [])]
        m["zero_lexical_pending"] += not cands
        if q["key"] is None:
            m["no_key"] += 1
        if q in gold_rows:
            g = gold.get(q["id"], set()); m["gold"] += 1
            for k in (1, 3, 5):
                m[f"top{k}"] += bool(g & set(cands[:k]))
            m["fp_top5"] += sum(pg not in g for pg in cands[:5])
        else:
            m["missing"] += 1; m["missing_with_candidates"] += bool(cands)
    tot = {k: sum(v[k] for v in per.values()) for k in next(iter(per.values()))}
    res = {"edition": a.edition, "pdf_sha256": pdf.source_sha256, "page_count": pdf.page_count,
           "scan": stats, "wall_seconds": round(wall, 2), "cpu_seconds": round(cpu, 2),
           "pages_per_sec": round(stats["pages_scanned"] / max(stats["scan_seconds"], 1e-9), 2),
           "max_rss_mib": round(resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024, 1),
           "questions_in_group": len(rows), "per_type": per, "total": tot,
           "verified_committed": 0,
           "missing_sample_scope": "exact-year official bank_reference_validity only; no inferred proxies",
           "note": "candidate pages only; not accepted classifications"}
    out = Path(a.out); tmp = out.with_suffix(".tmp")
    tmp.write_text(json.dumps(res, indent=1)); tmp.replace(out)
    print(json.dumps({"total": tot, "wall_seconds": res["wall_seconds"], "pages_per_sec": res["pages_per_sec"]}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
