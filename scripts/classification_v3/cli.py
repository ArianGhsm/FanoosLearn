#!/usr/bin/env python3
"""Classifier v3 CLI (read-only). Subcommands: plan, scan, verify, package.

Outputs go only to a private /srv/fanoos/shared/research/tmp/<op>/ path given
by --out. No page text is persisted; verify keeps only short quotes.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path[:0] = [str(HERE), str(HERE.parent / "references")]
import pipeline as P  # noqa: E402
from scanner import Query, scan_edition  # noqa: E402
from terms import deterministic_terms  # noqa: E402

MAP = HERE.parents[1] / "data/bank/reference-chapter-pages.json"


def runs_for(edition):
    return json.load(open(MAP))["editions"].get(edition, {}).get("runs", [])


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(); sub = ap.add_subparsers(dest="cmd", required=True)
    p = sub.add_parser("plan"); p.add_argument("study"); p.add_argument("policy"); p.add_argument("--out", required=True)
    s = sub.add_parser("scan"); s.add_argument("study"); s.add_argument("--edition", required=True); s.add_argument("--out", required=True)
    v = sub.add_parser("verify"); v.add_argument("decisions"); v.add_argument("--out", required=True)
    k = sub.add_parser("package"); k.add_argument("verified"); k.add_argument("--study-sha", required=True)
    k.add_argument("--batch", required=True); k.add_argument("--out", required=True)
    a = ap.parse_args(argv)
    out = Path(a.out)
    if a.cmd == "plan":
        groups, pending = P.group_by_edition(json.load(open(a.study)), json.load(open(a.policy)))
        res = {"groups": {e: [q["id"] for q in qs] for e, qs in groups.items()}, "pending": pending,
               "map_missing": [e for e in groups if not runs_for(e)]}
    elif a.cmd == "scan":
        from verified_reference_pdf import open_verified_reference
        rows = [q for q in json.load(open(a.study))]
        queries = [Query.build(str(q["id"]), *deterministic_terms(q["stem"], q["choices"], q.get("key"))) for q in rows]
        pdf = open_verified_reference(a.edition); runs = runs_for(a.edition)
        ranking, stats = scan_edition(pdf, queries)
        res = {"edition": a.edition, "pdf_sha256": pdf.source_sha256, "stats": stats,
               "candidates": {k: [{"page": pg, "score": sc, "chapter": P.chapter_for(runs, pg)} for sc, pg in r]
                              for k, r in ranking.items()}}
    elif a.cmd == "verify":
        from verified_reference_pdf import open_verified_reference
        res, cache = {"accepted": [], "pending": []}, {}
        for raw in json.load(open(a.decisions)):
            d = P.Decision(**raw)
            pdf = cache.get(d.edition) or cache.setdefault(d.edition, open_verified_reference(d.edition))
            runs = runs_for(d.edition)
            text = pdf.page_text(d.pdf_page) if 1 <= d.pdf_page <= pdf.page_count else ""
            primary = P.verify_decision(d, text, runs, pdf.source_sha256, pdf.page_count)
            audit = P.verify_decision(P.Decision(**P.audit_packet(d) | {"note_fa": d.note_fa}), text, runs,
                                      pdf.source_sha256, pdf.page_count)   # independent re-read path
            del text
            verdict = P.accept(d, primary, audit)
            (res["accepted"] if verdict == "accepted" else res["pending"]).append(
                P.audit_packet(d) | {"note_fa": d.note_fa, "verdict": verdict})
    else:
        acc = [P.Decision(**{k: r[k] for k in P.Decision.__dataclass_fields__ if k in r})
               for r in json.load(open(a.verified))["accepted"]]
        res = P.handoff_package(acc, a.study_sha, a.batch)
    tmp = out.with_suffix(".tmp"); tmp.write_text(json.dumps(res, ensure_ascii=False, indent=1)); tmp.replace(out)
    print(json.dumps({k: (len(v) if isinstance(v, (list, dict)) else v) for k, v in res.items()}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
