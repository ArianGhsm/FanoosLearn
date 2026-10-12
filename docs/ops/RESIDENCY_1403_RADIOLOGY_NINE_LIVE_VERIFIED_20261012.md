# Residency 1403 — nine newly LIVE original-source PDF page records reconciled (2026-10-12)

The continuing 1403 campaign has **250** questions: **80** actually complete bank source rows with simultaneous `node_id`, `pdf_page`, `pdf_sha256`; **144** additional independently audited original-year chapter+physical PDF-page mappings **still not live**; **20 English official-final-key exempt**; **six** scientifically unresolved Q61,62,63,68,69,127. These four sets are disjoint and sum exactly to 250. **Do not represent 144 research-only mappings as live or claim assessment republication.**

This is an independent read-only reconciliation after concurrently operating workers published new source metadata. No source row was changed by the reconciliation. Previous official live baseline was **71**. The following **9** original White–Pharoah Radiology 8e citations are now present in the live production database and agree exactly with the earlier independently audited private decisions:

| Q | Official 1403 source | Chapter | Physical PDF page |
|---:|---|---:|---:|
| 192 | White–Pharoah 8e | 1 | 70 |
| 193 | White–Pharoah 8e | 3 | 136 |
| 196 | White–Pharoah 8e | 6 | 302 |
| 197 | White–Pharoah 8e | 15 | 754 |
| 203 | White–Pharoah 8e | 12 | 606 |
| 205 | White–Pharoah 8e | 22 | 1084 |
| 207 | White–Pharoah 8e | 25 | 1344 |
| 208 | White–Pharoah 8e | 26 | 1446 |
| 210 | White–Pharoah 8e | 28 | 1568 |

**Every one** has the approved original PDF SHA-256 `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a` on the same source row, and exact source edition/chapter/page agreement with the independent study receipt. The read-only reconciler also checks canonical sitting SHA, original 153-audit receipt SHA, 71 historical initial live source identities, zero English-source overlap and zero scientifically held-source overlap. No bank questions, options, official answers, attempts, human decisions or assessments were modified by this reconciliation.

Protected reproducibility evidence:
- Script: `/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/reconcile_live_80_radiology_9.py`
- SHA-pinned report: `/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/residency1403-live80-exact-original-matched-radiology9-20261012.json`
- Receipt SHA-256: `431dee001815f3b425238659ee43076ba9b5e0a97d51987e78b0586058e0ab97`
- Immutable canonical original 250 questions SHA-256: `5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7`
- Source-independent original 153 audit receipt SHA-256: `1285dd66b7de3c967534ec15b483e5e8f8a0c92b6ddc56022a4d85e755f6c3ab`

The prior 1403 master research list is `docs/ops/RESIDENCY_1403_153_VERIFIED_ORIGINAL_PDF_PAGES_20261012.md` (those 153 were originally distinct from 71 live at its timestamp); the safety and remaining six-review document is `docs/ops/RESIDENCY_1403_SIX_HOLDS_AND_SOURCE_REPAIR_PREFLIGHT_20261012.md`. This newer checkpoint supersedes only the **LIVE total** and the **remaining unpublished research count**, not the original 153 accepted decisions, the historical receipts or the scientific holds.

Mandatory next production controls: fresh complete independently restorable DB backup; exact running SHA-bound reference map/CI; exclusive coordination; source-only guarded dry-run; preserve human decisions and original questions/options/keys/history; read-back and assessment publication checks. Site /health OK at `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` at the most recent health check. **Live DB source rows and assessment publication are distinct facts**: this receipt proves the former for 9 records, not the latter.
