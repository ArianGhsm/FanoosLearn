#!/usr/bin/env python3
"""Trusted, non-model normalization of a staged V3 research draft (no DB writes).

The research session proposes edition/page/quote/confidence. Identity and
policy fields are NOT taken from the model: exam type/year/round/number,
subject, key class, proxy basis, chapter (node_id from the hash-bound page
map), reference year and the retrospective Persian note are derived here
from the SELECT-only prep (questions.json), the batch plan and the catalog.
A model value that disagrees demotes the row to pending instead of being
overwritten silently. Also writes study-<year>.json in the exact
export_server_candidates.php study-only/1 shape, which host QA and the
publisher compare against a fresh live export.
"""
from __future__ import annotations

import json
import os
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path[:0] = [str(HERE.parent), str(HERE), str(HERE.parent / "references")]

STUDY_NOTE = "Read-only DB export for reference research. NEVER send to bank importer."
SHA_RE = __import__("re").compile(r"^[0-9a-f]{64}$")


def study_docs(questions: list[dict], batch: dict) -> dict[int, dict]:
    """Group prep questions into exporter-identical study-only/1 documents per year."""
    docs: dict[int, dict] = {}
    for q in sorted(questions, key=lambda q: (q["year"], q["number"])):
        if q.get("answer", {}).get("status") in (None, "missing") or q.get("source_rows"):
            continue  # the exporter skips answerless or already-sourced questions
        doc = docs.setdefault(q["year"], {"format": "fanoos.classification.study-only/1",
                                          "exam_type": q["type"], "year": q["year"], "round": q["round"],
                                          "notes": STUDY_NOTE, "questions": []})
        if doc["round"] != q["round"] or doc["exam_type"] != q["type"]:
            raise ValueError("mixed_round_or_type_in_year")
        a = q["answer"]
        doc["questions"].append({"number": q["number"], "subject": batch["subject"], "stem": q["stem"],
                                 "choices": q.get("study_choices", q["choices"]),
                                 "answer": {"choice": a["choice"], "also_correct": a.get("also_correct") or [],
                                            "status": a["status"]}})
    return docs


def _demote(row: dict, reason: str) -> dict:
    return {**row, "decision": "pending", "pending_reason": reason}


def normalize(draft: dict, questions: list[dict], batch: dict, chapter_of, reference_year_of) -> tuple[dict, dict]:
    """Returns (normalized draft, counts). chapter_of(edition, sha, page) -> str|None;
    reference_year_of(edition, subject) -> int|None. Neither consults the model."""
    from retrospective_reference_policy import is_retrospective, retrospective_note
    by_id = {q["id"]: q for q in questions}
    out, counts = [], {"proposed": 0, "demoted": 0}
    for row in draft.get("decisions", []):
        if row.get("decision") != "proposed":
            out.append(row)
            continue
        q = by_id.get(row.get("question_id"))
        if q is None or row["question_id"] not in batch["question_ids"]:
            out.append(_demote(row, "id_not_in_batch_prep")); counts["demoted"] += 1; continue
        ident = {"exam_type": q["type"], "year": q["year"], "round": q["round"], "number": q["number"],
                 "subject": batch["subject"]}
        clash = [k for k, v in ident.items() if row.get(k) not in (None, v)]
        basis = batch["proxy_basis"]
        model_basis = row.get("basis", row.get("edition_basis"))
        a = q.get("answer") or {}
        key_class = "final" if a.get("status") == "final" and not a.get("also_correct") else None
        ed, sha, page = row.get("edition"), row.get("pdf_sha256"), row.get("pdf_page")
        chapter = chapter_of(ed, sha, page) if (ed in batch["selected_editions"] and isinstance(sha, str)
                                                and SHA_RE.match(sha) and isinstance(page, int) and page > 0) else None
        model_ch = row.get("node_id") if row.get("node_id") is not None else row.get("chapter_number")
        ref_year = reference_year_of(ed, batch["subject"]) if chapter else None
        reason = (f"identity_mismatch:{','.join(clash)}" if clash else
                  "basis_mismatch" if model_basis not in (None, basis) else
                  "official_key_not_single_final" if key_class != batch["key_class"] else
                  "edition_sha_page_not_on_verified_map" if not chapter else
                  "chapter_disagrees_with_page_map" if model_ch is not None and str(model_ch) != chapter else
                  "reference_year_unavailable" if not isinstance(ref_year, int) or ref_year < 1397 else None)
        if reason:
            out.append(_demote(row, reason)); counts["demoted"] += 1; continue
        norm = {**row, **ident, "key_class": key_class, "basis": basis, "node_id": chapter,
                "reference_year": ref_year,
                "newer_editions_searched": list(row.get("newer_editions_searched") or [])}
        if basis == "retrospective_proxy_1397_plus" and is_retrospective(q["year"]):
            norm["note_fa"] = retrospective_note(ref_year, q["year"])
        elif not (row.get("note_fa") or row.get("persian_note")):
            out.append(_demote(row, "proxy_note_missing")); counts["demoted"] += 1; continue
        else:
            norm["note_fa"] = row.get("note_fa") or row.get("persian_note")
        norm.pop("edition_basis", None)
        out.append(norm)
        counts["proposed"] += 1
    return {**draft, "batch_id": batch["batch_id"], "normalized_by": "classification_v3.normalize",
            "decisions": out}, counts


def _write(path: Path, obj) -> None:
    tmp = path.with_name(f".{path.name}.tmp")
    fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o640)
    with os.fdopen(fd, "w", encoding="utf-8") as fh:
        json.dump(obj, fh, ensure_ascii=False, indent=1)
        fh.write("\n")
        fh.flush(); os.fsync(fh.fileno())
    tmp.replace(path)


def chapter_from_map(edition: str, sha: str, page: int):
    import cli
    m = json.load(open(cli.MAP))["editions"].get(edition) or {}
    if m.get("source_pdf_sha256") != sha:
        return None
    ch = cli.P.chapter_for(m.get("runs", []), page)
    return None if ch in (None, "") else str(ch)


REFERENCE_YEAR_SQL = """SELECT MAX(rv.exam_year) FROM bank_reference_validity rv
JOIN bank_exam_types t ON t.id=rv.exam_type_id AND t.workspace_id=rv.workspace_id
JOIN bank_subjects s ON s.id=rv.subject_id AND s.workspace_id=rv.workspace_id
JOIN bank_reference_editions e ON e.id=rv.edition_id AND e.workspace_id=rv.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
WHERE t.type_key='{typ}' AND rv.is_official=1 AND rv.exam_year>=1397 AND s.subject_key='{sub}'
  AND r.reference_key='{ref}' AND e.edition_key='{ed}'"""


def reference_year_from_db(edition: str, subject: str, typ: str = "residency"):
    import re
    from classification_v3 import manifest
    ref, _, ed = edition.partition("@")
    if not all(re.fullmatch(r"[a-z0-9][a-z0-9.-]{0,79}", x) for x in (ref, ed, subject)):
        return None
    rows = manifest.sql(REFERENCE_YEAR_SQL.format(typ=typ, sub=subject, ref=ref, ed=ed))
    return int(rows[0][0]) if rows and rows[0] and rows[0][0] not in (None, "NULL", "") else None


def normalize_case(case_dir: Path, batch: dict, chapter_of=chapter_from_map, reference_year_of=None) -> dict:
    """Normalize decisions.draft.json in place (original kept as decisions.model.json)."""
    prep_doc = json.loads((case_dir / "questions.json").read_text("utf-8"))
    questions = prep_doc["questions"]
    if reference_year_of is None:
        reference_year_of = lambda ed, sub: reference_year_from_db(ed, sub, batch.get("type", "residency"))  # noqa: E731
    for year, doc in study_docs(questions, batch).items():
        _write(case_dir / f"study-{year}.json", doc)
    draft_p = case_dir / "decisions.draft.json"
    draft = json.loads(draft_p.read_text("utf-8"))
    if draft.get("normalized_by"):
        return {"already": True}
    if not (case_dir / "decisions.model.json").exists():
        _write(case_dir / "decisions.model.json", draft)
    norm, counts = normalize(draft, questions, batch, chapter_of, reference_year_of)
    _write(draft_p, norm)
    return counts


if __name__ == "__main__":
    case = Path(sys.argv[1])
    print(json.dumps(normalize_case(case, json.loads((case / "batch.json").read_text("utf-8")))))
