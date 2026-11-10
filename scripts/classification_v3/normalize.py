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


_FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")


def same_lineage_note(reference_year: int, exam_year: int) -> str:
    """Policy label (not science): names both years and states the exam-year
    edition's official status is unverified, as valid_proxy_note requires."""
    return (f"این ارجاع پروکسی هم‌تبار از فهرست رسمی سال {str(int(reference_year)).translate(_FA_DIGITS)}"
            f" برای آزمون سال {str(int(exam_year)).translate(_FA_DIGITS)}"
            " است؛ رسمی بودن این ویرایش برای سال آزمون تأیید نشده است.")


def _demote(row: dict, reason: str) -> dict:
    return {**row, "decision": "pending", "pending_reason": reason}


def exact_quote(row: dict) -> str | None:
    """Canonicalize legacy model evidence_quote to publisher's exact quote key.

    No paraphrase, token reconstruction, or text substitution is allowed.
    The original model-provided evidence is copied byte-for-byte; the trusted
    source validator subsequently checks its literal presence on the PDF page.
    """
    canonical = row.get("quote")
    legacy = row.get("evidence_quote")
    if isinstance(canonical, str) and canonical.strip():
        value = canonical
    elif isinstance(legacy, str) and legacy.strip():
        value = legacy
    else:
        return None
    if not 4 <= len(value.split()) or len(value) > 300:
        return None
    return value


def normalize(draft: dict, questions: list[dict], batch: dict, chapter_of, reference_year_of,
              scope_of=None) -> tuple[dict, dict]:
    """Returns (normalized draft, counts). chapter_of(edition, sha, page) -> str|None;
    reference_year_of(edition, subject) -> int|None; optional scope_of(exam_year, edition)
    -> set of in-scope chapter numbers or None (no explicit list). None consults the model."""
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
                  "reference_year_unavailable" if not isinstance(ref_year, int) or ref_year < 1397 else
                  "chapter_outside_official_scope" if _outside_scope(scope_of, q["year"], ed, chapter) else None)
        if reason:
            out.append(_demote(row, reason)); counts["demoted"] += 1; continue
        quote = exact_quote(row)
        if quote is None:
            out.append(_demote(row, "exact_quote_missing_or_too_short")); counts["demoted"] += 1; continue
        norm = {**row, **ident, "quote": quote, "key_class": key_class, "basis": basis, "node_id": chapter,
                "reference_year": ref_year,
                "newer_editions_searched": list(row.get("newer_editions_searched") or [])}
        if basis == "retrospective_proxy_1397_plus" and is_retrospective(q["year"]):
            norm["note_fa"] = retrospective_note(ref_year, q["year"])
        elif basis == "same_lineage_proxy":
            norm["note_fa"] = same_lineage_note(ref_year, q["year"])
        elif not (row.get("note_fa") or row.get("persian_note")):
            out.append(_demote(row, "proxy_note_missing")); counts["demoted"] += 1; continue
        else:
            norm["note_fa"] = row.get("note_fa") or row.get("persian_note")
        norm.pop("edition_basis", None)
        out.append(norm)
        counts["proposed"] += 1
    return {**draft, "batch_id": batch["batch_id"], "normalized_by": "classification_v3.normalize",
            "decisions": out}, counts


def _outside_scope(scope_of, year, edition, chapter) -> bool:
    if scope_of is None:
        return False
    listed = scope_of(year, edition)
    return bool(listed) and str(chapter) not in listed


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
    try:
        scope_of = scope_lookup(batch.get("type", "residency"), batch["subject"])
    except Exception:  # the pinned validator still enforces scope downstream
        scope_of = None
    norm, counts = normalize(draft, questions, batch, chapter_of, reference_year_of, scope_of)
    _write(draft_p, norm)
    return counts


PROD = Path("/srv/fanoos/current")


def official_rows(exam_type: str, year: int, subject: str, bank: Path | None = None) -> dict:
    """{edition: validity row} chosen exactly as the pinned apply_classification.py does."""
    from retrospective_reference_policy import is_retrospective, selected_reference_rows
    from same_lineage_proxy_policy import is_proxy_exam_year, selected_non_residency_proxy_rows
    from same_lineage_proxy_policy import selected_reference_rows as same_lineage_rows
    bank = bank or PROD / "data/bank"
    catalog = json.loads((bank / "catalog.json").read_text("utf-8"))
    pdfs = json.loads((bank / "reference-pdfs.json").read_text("utf-8"))["editions"]
    runs = json.loads((bank / "reference-chapter-pages.json").read_text("utf-8"))["editions"]
    if is_retrospective(year):
        rows = selected_reference_rows(catalog, exam_type, year, subject, pdfs, runs)
    elif exam_type == "residency" and is_proxy_exam_year(year):
        rows = same_lineage_rows(catalog, exam_type, year, subject, pdfs, runs)
    elif exam_type in ("national", "board", "promotion"):
        rows = selected_non_residency_proxy_rows(catalog, exam_type, year, subject, pdfs, runs) or [
            v for v in catalog["validity"] if v.get("exam_type") == exam_type and int(v["year"]) == year
            and v.get("subject") == subject and v.get("official") is True]
    else:
        rows = [v for v in catalog["validity"] if v.get("exam_type") == exam_type
                and int(v["year"]) == year and v.get("subject") == subject]
    out: dict = {}
    for row in rows:
        out.setdefault(row["edition"], row)
    return out


def scope_chapters(row: dict | None):
    """Sorted explicit in-scope chapter numbers, or None when the row lists none."""
    listed = (row or {}).get("scope_chapters") or []
    return sorted({str(item["number"]) for item in listed}, key=lambda c: (len(c), c)) or None


def scope_lookup(exam_type: str, subject: str, bank: Path | None = None):
    cache: dict = {}

    def scope_of(year, edition):
        if year not in cache:
            cache[year] = official_rows(exam_type, int(year), subject, bank)
        found = scope_chapters(cache[year].get(edition))
        return set(found) if found else None
    return scope_of
REJECTED_RE = __import__("re").compile(r"^REJECTED Q(\d+): (.{1,160})", __import__("re").M)


def publisher_decision(row: dict) -> dict:
    """Exactly the decision shape operator_publisher.process_group hands the validator."""
    val = {"number": row["number"], "edition": row["edition"], "chapter": str(row["node_id"]),
           "page": row["pdf_page"], "confidence": float(row["confidence"]), "evidence": row["quote"],
           "reference_year": row["reference_year"], "classification_basis": row["basis"],
           "proxy_note": row.get("note_fa") or ""}
    if row.get("newer_editions_searched"):
        val["newer_editions_searched"] = row["newer_editions_searched"]
    return val


def trusted_validator(study: Path, decisions: list[dict], tmp: Path) -> tuple[set[int], dict[int, str]]:
    """Run the pinned production apply_classification.py (read-only, exact PDF page)."""
    import subprocess
    dec, out = tmp / "decisions.json", tmp / "validated.json"
    dec.write_text(json.dumps(decisions, ensure_ascii=False), "utf-8")
    proc = subprocess.run(["python3", "-B", str(PROD / "scripts/references/apply_classification.py"),
                           f"--sitting={study}", f"--decisions={dec}", f"--out={out}",
                           "--partial", "--pdf-pages"], cwd=str(PROD), capture_output=True, text=True,
                          timeout=200, env={**os.environ, "PYTHONDONTWRITEBYTECODE": "1"})
    if proc.returncode:
        raise RuntimeError(f"validator_exit_{proc.returncode}")
    good = {q["number"] for q in json.loads(out.read_text("utf-8"))["questions"] if q.get("sources")}
    return good, {int(n): why for n, why in REJECTED_RE.findall(proc.stdout)}


def prevalidate_case(case_dir: Path, validator=trusted_validator, write_if_clean: bool = True) -> dict:
    """Demote proposals the publisher's own validator would reject, before QA.

    The publisher imports a group only if EVERY decision passes the pinned
    exact-PDF validator; one inexact quote or out-of-scope chapter defers the
    whole batch forever (its operator case is immutable). Running the same
    read-only validator here keeps QA effort and the import to rows that can
    actually land. A validator error leaves the draft untouched (fail closed).
    """
    import shutil
    import tempfile
    draft_p = case_dir / "decisions.draft.json"
    draft = json.loads(draft_p.read_text("utf-8"))
    if draft.get("prevalidated_by"):
        return {"already": True}
    rows = draft.get("decisions", [])
    by_year: dict[int, list[dict]] = {}
    for r in rows:
        if r.get("decision") == "proposed":
            by_year.setdefault(r["year"], []).append(r)
    rejected: dict[tuple[int, int], str] = {}
    # Prior to the schema correction, some existing staged drafts had only
    # evidence_quote and no quote, which caused KeyError on EVERY timed pass.
    # Read model evidence exactly as written; never invent missing evidence.
    fixed_rows = []
    for row in rows:
        if row.get("decision") != "proposed":
            fixed_rows.append(row)
            continue
        quote = exact_quote(row)
        if quote is None:
            rejected[(row["year"], row["number"])] = "exact_quote_missing_or_too_short"
            fixed_rows.append(row)
        else:
            fixed_rows.append({**row, "quote": quote})
    valid_by_year: dict[int, list[dict]] = {}
    for row in fixed_rows:
        if row.get("decision") == "proposed" and (row["year"], row["number"]) not in rejected:
            valid_by_year.setdefault(row["year"], []).append(row)
    tmp_root = Path(tempfile.mkdtemp(prefix="v3-prevalidate-", dir=os.environ.get("V3_TMP_ROOT", "/srv/fanoos/shared/research/tmp")))
    try:
        for year, group in sorted(valid_by_year.items()):
            tmp = tmp_root / str(year)
            tmp.mkdir()
            good, why = validator(case_dir / f"study-{year}.json", [publisher_decision(r) for r in group], tmp)
            for r in group:
                if r["number"] not in good:
                    rejected[(year, r["number"])] = why.get(r["number"], "not_accepted_by_validator")
    finally:
        shutil.rmtree(tmp_root, ignore_errors=True)
    if not rejected and not write_if_clean:
        return {"validated": sum(len(g) for g in by_year.values()), "demoted_by_validator": 0}
    out = [(_demote(r, "trusted_validator_rejected: " + rejected[(r["year"], r["number"])])
            if r.get("decision") == "proposed" and (r["year"], r["number"]) in rejected else r) for r in fixed_rows]
    _write(draft_p, {**draft, "decisions": out, "prevalidated_by": "classification_v3.normalize.prevalidate_case"})
    return {"validated": sum(len(g) for g in by_year.values()) - len(rejected), "demoted_by_validator": len(rejected)}


if __name__ == "__main__":
    case = Path(sys.argv[1])
    print(json.dumps(normalize_case(case, json.loads((case / "batch.json").read_text("utf-8")))))
