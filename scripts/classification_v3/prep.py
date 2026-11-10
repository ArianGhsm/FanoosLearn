#!/usr/bin/env python3
"""Non-model case preparation for a V3 research batch (SELECT-only, read-only PDFs).

Writes <case>/questions.json before the Opus session so the researcher spends
its time reading candidate pages, not exporting, mapping ids or converting
formats. Each question carries id, exam year/round/number, stem, choices,
latest official answer, existing source-row summary and scanner page leads
(page/score/chapter only; never page text). Leads are search hints, not
evidence.
"""
from __future__ import annotations

import json
import os
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path[:0] = [str(HERE.parent), str(HERE), str(HERE.parent / "references")]

# HEX keeps multi-line/tabbed Persian text intact through mysql --batch.
QUESTIONS_SQL = """SET SESSION group_concat_max_len=1048576;
SELECT q.id, et.type_key, si.exam_year, si.exam_round, q.number_in_sitting, HEX(q.stem),
 (SELECT GROUP_CONCAT(CONCAT(c.position, ':', HEX(IFNULL(c.text, '')), ':', IFNULL(HEX(c.image), 'N')) ORDER BY c.position SEPARATOR ',')
   FROM bank_question_choices c WHERE c.question_id=q.id AND c.workspace_id=q.workspace_id),
 (SELECT CONCAT_WS('|', IFNULL(a.choice_position,''), IFNULL(a.also_correct_positions,''), a.status)
   FROM bank_official_answers a WHERE a.question_id=q.id AND a.workspace_id=q.workspace_id
   ORDER BY a.recorded_at DESC, a.id DESC LIMIT 1),
 (SELECT COUNT(*) FROM bank_question_sources s WHERE s.question_id=q.id AND s.workspace_id=q.workspace_id),
 (SELECT COUNT(*) FROM bank_question_sources s WHERE s.question_id=q.id AND s.workspace_id=q.workspace_id
   AND s.node_id IS NOT NULL AND s.pdf_page>0 AND s.pdf_sha256 IS NOT NULL)
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
WHERE q.id IN ({ids}) ORDER BY si.exam_year, q.number_in_sitting"""


def parse_answer(raw: str) -> dict:
    choice, also, status = (raw.split("|", 2) + ["", "", ""])[:3] if raw else ("", "", "missing")
    try:
        also_list = json.loads(also) if also else []
    except ValueError:
        also_list = []
    return {"choice": int(choice) if choice.isdigit() else None, "also_correct": also_list, "status": status}


def rows_to_questions(rows: list[list[str]]) -> list[dict]:
    def dec(h: str) -> str:
        return bytes.fromhex(h).decode("utf-8") if h and h != "NULL" else ""
    out = []
    for r in rows:
        qid, typ, year, rnd, num, stem_hex, ch_hex, ans, n_src, n_full = r
        parts = [(c.split(":", 2) + ["N"])[:3] for c in ch_hex.split(",")] if ch_hex and ch_hex != "NULL" else []
        parts.sort(key=lambda x: int(x[0]))
        choices = [dec(h) for _, h, _ in parts]
        # Exporter shape (export_server_candidates.php): image choices are {text, image}.
        study_choices = [dec(h) if img == "N" else {"text": dec(h), "image": dec(img)} for _, h, img in parts]
        answer = parse_answer("" if ans == "NULL" else ans)
        out.append({"id": qid, "type": typ, "year": int(year), "round": int(rnd), "number": int(num),
                    "stem": dec(stem_hex), "choices": choices, "study_choices": study_choices, "answer": answer,
                    "key": None if answer["choice"] is None else str(answer["choice"]),
                    "source_rows": int(n_src), "complete_source_rows": int(n_full)})
    return out


def fetch_questions(ids: list[str]) -> list[dict]:
    from classification_v3 import manifest, runner
    if not ids or not all(runner.ID_RE.match(i) for i in ids):
        raise ValueError("invalid_question_id")
    return rows_to_questions(manifest.sql(QUESTIONS_SQL.format(ids=",".join(f"'{i}'" for i in ids))))


def scan_leads(questions: list[dict], editions: list[str], budget_s: float, top: int = 4) -> dict:
    """Per-edition page leads; stops starting new editions once budget is spent."""
    import cli
    from scanner import Query, scan_edition
    from terms import c1_terms
    from verified_reference_pdf import open_verified_reference
    rows = cli.study_rows(questions)
    queries = [Query.build(q["id"], *c1_terms(q["stem"], q["choices"], q["key"])) for q in rows]
    leads, start = {}, time.monotonic()
    for ed in editions:
        if time.monotonic() - start > budget_s:
            leads[ed] = {"skipped": "prep_time_budget"}
            continue
        try:
            pdf = open_verified_reference(ed)
            runs = cli.runs_for(ed)
            ranking, _ = scan_edition(pdf, queries, top=top)
            leads[ed] = {"pdf_sha256": pdf.source_sha256, "page_count": pdf.page_count,
                         "by_question": {k: [{"page": pg, "score": round(sc, 2), "chapter": cli.P.chapter_for(runs, pg)}
                                             for sc, pg in r] for k, r in ranking.items()}}
        except Exception as exc:  # leads are optional; the researcher can still read pages
            leads[ed] = {"error": type(exc).__name__}
    return leads


def prepare(case_dir: Path, batch: dict, budget_s: float = 150.0, fetch=fetch_questions, scan=scan_leads) -> dict:
    questions = fetch(batch["question_ids"])
    leads = scan(questions, batch["selected_editions"], budget_s) if questions else {}
    for q in questions:
        q["page_leads"] = {ed: v.get("by_question", {}).get(q["id"], []) for ed, v in leads.items()}
    doc = {"format": "fanoos.classification.v3-prep/1", "batch_id": batch["batch_id"],
           "notes": "Private SELECT-only prep. Page leads are scanner hints, not evidence. Never import.",
           "editions": {ed: {k: v for k, v in info.items() if k != "by_question"} for ed, info in leads.items()},
           "questions": questions}
    path = case_dir / "questions.json"
    tmp = path.with_name(".questions.json.tmp")
    fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o640)
    with os.fdopen(fd, "w", encoding="utf-8") as fh:
        json.dump(doc, fh, ensure_ascii=False, indent=1)
    tmp.replace(path)
    return {"questions": len(questions), "editions": {ed: ("ok" if "by_question" in v else next(iter(v.values()), "?"))
                                                      for ed, v in leads.items()}}


if __name__ == "__main__":
    case = Path(sys.argv[1])
    print(json.dumps(prepare(case, json.loads((case / "batch.json").read_text("utf-8")))))
