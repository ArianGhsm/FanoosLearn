#!/usr/bin/env python3
"""Researcher-driven, scope-bounded page finder and exact-quote check (read-only).

Why: prep leads come from a fixed Persian->English glossary. When they miss,
the researcher used to read only those lead pages, mark the question
"not located on leads" and the id was never retried. Here the researcher
supplies its OWN English terms per question; one pass over the edition's
official in-scope chapter pages ranks pages for every question in the batch.

Policy (docs/ops/PDF_REFERENCE_SOURCE_POLICY_20261010.md): pages are read one
at a time through verified_reference_pdf.py, tokenised in memory and discarded.
Only numeric per-page counts of the query terms are kept while ranking; nothing
is written to disk. Output (stdout) is page numbers, chapters, scores and one
short window per top page as a pointer; a window is NOT evidence and NOT an
exact quote. `quote` runs the production validator's exact-fragment check
(evidence_match.contains_exact_fragment, >=4 words per fragment) on one page.

  find_pages.py search --case DIR --edition ED --terms TERMS.json [--top 6]
  find_pages.py quote  --edition ED --page N --quote "..."
  find_pages.py check-draft --case DIR

TERMS.json: {"<question_id>": ["term", ["exact phrase", 2.0], ...], ...}
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path[:0] = [str(HERE), str(HERE.parent / "references")]
from scanner import Query, count_page, rank, stem, TOKEN, norm  # noqa: E402

MAP = HERE.parents[1] / "data/bank/reference-chapter-pages.json"
MAX_TERMS = 24
WINDOW_WORDS = 40
QUOTE_KEYS = ("quote", "evidence_quote", "evidence")


def load_runs(edition: str, map_path: Path = MAP) -> list:
    return json.loads(map_path.read_text("utf-8"))["editions"].get(edition, {}).get("runs", [])


def allowed_pages(runs: list, chapters: list | None) -> set[int]:
    """Pages of the listed chapters; None = every mapped chapter (front matter excluded)."""
    want = None if chapters is None else {str(c) for c in chapters}
    out = set()
    for chapter, first, last in runs:
        if chapter is None or (want is not None and str(chapter) not in want):
            continue
        out.update(range(int(first), int(last) + 1))
    return out


def chapter_of(runs: list, page: int):
    for chapter, first, last in runs:
        if first <= page <= last:
            return chapter
    return None


def build_queries(terms: dict) -> list[Query]:
    out = []
    for qid, items in terms.items():
        if not isinstance(items, list):
            raise ValueError(f"terms for {qid} must be a list")
        clean = []
        for t in items[:MAX_TERMS]:
            if isinstance(t, str) and t.strip():
                clean.append(t.strip())
            elif isinstance(t, list) and len(t) == 2 and isinstance(t[0], str) and isinstance(t[1], (int, float)):
                clean.append((t[0].strip(), max(0.1, min(5.0, float(t[1])))))
        q = Query.build(str(qid), clean)
        if q.groups:
            out.append(q)
    return out


def raw_window(text: str, q: Query, words: int = WINDOW_WORDS) -> str:
    """Original-case window with most query concepts (pointer only; columns may interleave)."""
    raw = text.split()
    st = [stem(w) for w in (" ".join(TOKEN.findall(norm(r))) or "_" for r in raw)]
    best, best_i = -1, 0
    for i in range(0, max(1, len(raw) - words + 1), 3):
        win = " " + " ".join(st[i:i + words]) + " "
        hits = sum(1 for toks, _ in q.groups if " " + " ".join(toks) + " " in win)
        if hits > best:
            best, best_i = hits, i
    return " ".join(raw[best_i:best_i + words])[:400]


def search(pdf, runs: list, questions: dict, terms: dict, edition: str, top: int = 6,
           all_chapters: bool = False, budget_s: float = 180.0, workers: int = 2) -> dict:
    """questions: {qid: official_scope_chapters_for_this_edition (list|None)}."""
    queries = [q for q in build_queries(terms) if q.key in questions]
    if not queries:
        return {"error": "no_usable_terms_for_batch_questions"}
    scope = {q.key: allowed_pages(runs, None if all_chapters else questions[q.key]) for q in queries}
    pages = sorted(set().union(*scope.values()) & set(range(1, pdf.page_count + 1)))
    phrases: dict[tuple, int] = {}
    for q in queries:
        for toks, _ in q.groups:
            phrases.setdefault(tuple(toks), len(phrases))
    counts, plen, noise, t0 = {}, {}, set(), time.monotonic()

    def one(p):
        text = pdf.page_text(p)
        res = count_page(text, phrases)
        del text
        return p, res

    scanned = 0
    with ThreadPoolExecutor(max_workers=max(1, workers)) as ex:
        for i in range(0, len(pages), 40):
            if time.monotonic() - t0 > budget_s:
                break
            for p, (c, n, is_noise) in ex.map(one, pages[i:i + 40]):
                counts[p], plen[p] = c, n
                if is_noise:
                    noise.add(p)
                scanned += 1
    results = {}
    for q in queries:
        sub = {p: counts[p] for p in counts if p in scope[q.key]}
        ranked = rank([q], sub, plen, noise, phrases, top).get(q.key, [])
        rows = []
        for score, p in ranked:
            text = pdf.page_text(p)
            rows.append({"page": p, "chapter": chapter_of(runs, p), "score": score,
                         "in_scope": questions[q.key] is None or str(chapter_of(runs, p)) in
                         {str(c) for c in questions[q.key]},
                         "window_not_exact": raw_window(text, q)})
            del text
        results[q.key] = rows or "no_lexical_hit_in_scope: try other English terms/synonyms"
    return {"edition": edition, "pdf_sha256": pdf.source_sha256, "pages_in_scope": len(pages),
            "pages_scanned": scanned, "partial": scanned < len(pages),
            "seconds": round(time.monotonic() - t0, 1), "results": results}


def quote_issue(page_text: str, quote: str) -> str | None:
    """Same rule as apply_classification: '...'-joined fragments, each >=4 words, exact on page."""
    from evidence_match import contains_exact_fragment, evidence_word_count, has_unreadable_glyph
    if not isinstance(quote, str) or not quote.strip():
        return "empty_quote"
    if len(quote) > 300:
        return "quote_longer_than_300_chars"
    pieces = [f.strip() for f in re.split(r"\.\.\.|…", quote) if f.strip()]
    if not pieces or any(evidence_word_count(p) < 4 for p in pieces):
        return "each_fragment_needs_at_least_4_words"
    if any(has_unreadable_glyph(p) for p in pieces):
        return "unreadable_glyph"
    if any(not contains_exact_fragment(page_text, p) for p in pieces):
        return "not_exact_case_punctuation_match_on_page"
    return None


def draft_quote(d: dict) -> str | None:
    for k in QUOTE_KEYS:
        if isinstance(d.get(k), str) and d[k].strip():
            return d[k]
    return None


def check_draft(case: Path, opener) -> dict:
    d = json.loads((case / "decisions.draft.json").read_text("utf-8"))
    rows = d.get("decisions", []) if isinstance(d, dict) else []
    pdfs, out = {}, []
    for r in rows:
        if not isinstance(r, dict) or r.get("decision") != "proposed":
            continue
        ed, page = r.get("edition"), r.get("pdf_page")
        try:
            page = int(page)
            pdf = pdfs.get(ed) or pdfs.setdefault(ed, opener(ed))
            text = pdf.page_text(page)
            issue = quote_issue(text, draft_quote(r))
            del text
        except Exception as exc:
            issue = f"unchecked:{type(exc).__name__}"
        out.append({"question_id": r.get("question_id"), "edition": ed, "pdf_page": page, "issue": issue})
    return {"proposed": len(out), "failing": [x for x in out if x["issue"]], "ok": sum(1 for x in out if not x["issue"])}


def batch_scope(case: Path, edition: str) -> dict:
    doc = json.loads((case / "questions.json").read_text("utf-8"))
    return {str(q["id"]): (q.get("official_scope_chapters") or {}).get(edition)
            for q in doc.get("questions", []) if not q.get("complete_source_rows")}


def main(argv=None) -> int:
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest="cmd", required=True)
    s = sub.add_parser("search")
    s.add_argument("--case", type=Path, required=True)
    s.add_argument("--edition", required=True)
    s.add_argument("--terms", type=Path, required=True)
    s.add_argument("--top", type=int, default=6)
    s.add_argument("--all-chapters", action="store_true",
                   help="also rank out-of-scope chapters (for the documented outside-scope check only)")
    s.add_argument("--budget-s", type=float, default=180.0)
    q = sub.add_parser("quote")
    q.add_argument("--edition", required=True)
    q.add_argument("--page", type=int, required=True)
    q.add_argument("--quote", required=True)
    c = sub.add_parser("check-draft")
    c.add_argument("--case", type=Path, required=True)
    a = ap.parse_args(argv)
    from verified_reference_pdf import open_verified_reference
    if a.cmd == "search":
        scope = batch_scope(a.case, a.edition)
        terms = json.loads(a.terms.read_text("utf-8"))
        res = search(open_verified_reference(a.edition), load_runs(a.edition), scope, terms, a.edition,
                     top=max(1, min(10, a.top)), all_chapters=a.all_chapters, budget_s=a.budget_s)
    elif a.cmd == "quote":
        pdf = open_verified_reference(a.edition)
        text = pdf.page_text(a.page)
        issue = quote_issue(text, a.quote)
        del text
        res = {"edition": a.edition, "pdf_page": a.page, "pdf_sha256": pdf.source_sha256,
               "chapter": chapter_of(load_runs(a.edition), a.page), "ok": issue is None, "issue": issue}
    else:
        res = check_draft(a.case, open_verified_reference)
    print(json.dumps(res, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
