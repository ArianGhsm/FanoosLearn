# Classifier V3 residency/national research runner (2026-11-10)

Status: **code + unit tests only. No model session has been executed; no unit is installed.**
Code: `scripts/classification_v3/runner.py`, prompts in `scripts/classification_v3/prompts/`,
tests `tests/references/test_classifier_v3_runner.py`, unit proposals in `ops/classifier-v3/`.

## What the runner does (research only)
1. `flock` on `<state-dir>/runner.lock`; it refuses root and `fanoosupd`.
2. Reconciles state with **trusted operator receipts** (format
   `fanoos.classifier-v3.operator-status.v1`, file owned by `--trusted-uid`, not owned by the
   runner and not group/world-writable, `readback_verified=true`). Only these receipts set
   `accepted`. The session's own count is `drafted`.
3. Guards: free disk >= 3 GiB, provider retry-after backoff (fallback `--default-backoff`),
   hard stop after 2 consecutive operator-confirmed zero-accepted batches, stop when
   `--max-unverified` (1..3) staged batches lack a receipt. No fixed session-per-hour limiter.
4. Plans batches by **one selected edition set** (newest edition per reference family),
   same lane/subject/proxy basis/key class, across years, <= 40 ids. Residency first; national
   only if no residency batch is runnable. English/basic lanes never run. Voided keys are
   batched separately. Proxy items need a manifest with `proxy_candidates_ordered=true`
   (newest-first by `published_year`). Otherwise they are blocked fail-closed.
5. Re-reads live source absence (SELECT-only) and leases ids (3 h), then runs one
   `claude -p --model claude-opus-5-5` session with the prompt on stdin. Arguments are a list
   (no shell). IDs must be UUIDs, edition/subject keys are regex-checked, and the environment
   is whitelisted. Output goes to `<case>/session.out` (0600). Logs carry ids/counts only. The
   process group is killed at `--timeout`, nice 10, and the temp dir is removed in `finally`.
6. On success it stages `decisions.draft.json` + `precheck.json` in the private case dir and
   writes `operator-requests/<batch>.json` (`awaiting_independent_qa`). A failed batch stays
   `failed` until an operator inspects it; it is never retried blindly.

## Required gates after each staged batch (NOT automated here)
1. Separate independent Opus QA session (`prompts/qa_review.md`) -> `qa.json`.
2. fanoosupd: trusted provenance audit; re-export the study from the live DB and compare;
   verified full backup <= 4 h; safe preview; guarded source-only import; independent readback.
3. fanoosupd writes the status receipt with `accepted_source_rows` from the readback.
The operator bridge (steps 2-3) is **not implemented**: it remains a queue + contract.

## Orchestrator fix
`orchestrator.plan_batches` used to group by a comma join of *all* candidate editions,
including several editions of one family. It now uses `runner.select_editions`.

## Planning on the 2026-11-09 snapshot (dry run)
- Residency ready lane: 1 question, 1 batch. Proxy lane: 210 blocked until
  `manifest.py --queue-out` is re-run (adds `published_year` ordering); then about 32 residency
  batches for 211 questions.
- National dental ready: 106 questions in 25 batches (after residency). 134 have no official
  list and stay blocked. English 239 (residency) and 200 national English, 156 basic: never run.

## Throughput is unproven
2-day dental goal needs >= 9.4 audited+committed Q/h; 1 day needs >= 18.8/h. Measure from
operator receipts only.

## Next steps
1. Regenerate the manifest.
2. Host operator reviews this PR.
3. Run one manual pilot: `--max-batches 1 --limit 2 --max-unverified 1`, with a case root
   that does not overlap the running scientific review.
4. Run QA + operator gates and write the receipt.
5. Only then consider enabling the timer.
