# Classifier V3: residency + national-dental backlog manifest (2026-11-09 21:18 UTC)

Live read-only SQL (`scripts/classification_v3/manifest.py`), anti-join on same-row
`node_id + pdf_page>0 + pdf_sha256`. No question text stored. The private queue snapshot is in
the agent-queue directory; this file has totals only.

## Counts (reproduce the owner's figures exactly)
| Scope | Total | Incomplete | Breakdown |
|---|---|---|---|
| Residency | 3202 | 450 | 211 dental, 239 English |
| National | 2731 | 596 | 240 dental, 200 English, 156 basic sciences |

Residency incomplete per year: 1391 55d; 1392 81d+20e; 1395 25d+20e; 1396 49d+20e; 1397-1399 20e each;
1400 1d+20e; 1401 19e; 1402-1405 20e each. Official key status (residency): 437 final, 1 amended, 12 voided
(voided still need chapter+page). National: 453 final, 133 preliminary, 10 voided.

## Lanes
| Lane | Count | Status |
|---|---|---|
| residency_dental_ready (exact-year official, approved PDF + SHA-bound map) | 1 | runnable (1400 operative; already has a non-atomic source row) |
| residency_dental_proxy_policy (1391-1396, no exact-year official list) | 210 | every item has >=1 approved PDF+map candidate from a residency official family; must go through `retrospective_reference_policy` (<=1395) / `same_lineage_proxy_policy` (1396), newest first, with `newer_editions_searched` and Persian proxy note |
| residency_english_blocked_policy | 239 | BLOCKED: no official book, no validity rows; never a fake chapter. Needs an owner-approved exemption or language-skill taxonomy |
| national_dental_same_edition_ready | 106 | runnable after residency (or work-conserving while residency is stalled) |
| national_dental_no_official | 134 | BLOCKED: no exact-year official list for that national year/subject; only the nearest-official-list rule may apply, after review |
| national_english_separate | 200 | separate lane, ask the owner |
| national_basic_sciences_excluded | 156 | excluded by default |

Correction to the earlier memory: the residency dental blocker is the proxy resolver, not missing PDFs or maps.
All 210 have a usable candidate edition.

## Orchestrator (dry-run only)
`scripts/classification_v3/orchestrator.py --dry-run`: 78 batches covering 317 runnable questions (<=40 each,
grouped by year/subject/edition family, residency first). The run took 0.07 s. Guards: 3 GiB disk floor, a backup
<=4 h old for any apply, quota backoff only from the provider's Retry-After, and a hard stop after 2 consecutive
zero-accept batches. Completed and leased ids are skipped, so refreshes are idempotent. Proposed unit:
`ops/classifier-v3/fanoos-classifier-v3-plan.service` (planner only, no timer). Still missing: an executor that
runs one bounded `claude --model opus` research session per batch and writes a protected candidate packet for
the fanoosupd audit/apply bridge.

## Pilot this session
No new source was researched, accepted or imported this session (0). The time box went to the manifest,
lanes and planner. The verified bank baseline is unchanged: 1 source imported in the earlier pilot.

## Rate needed (fully verified and imported)
| Target | 3 days | 4 days | 5 days |
|---|---|---|---|
| 690 (all primary, includes 239 English that has no pathway yet) | 9.58/h | 7.19/h | 5.75/h |
| 451 dental textbook tasks | 6.26/h | 4.70/h | 3.76/h |
| 317 runnable now | 4.40/h | 3.30/h | 2.64/h |
Measured committed throughput so far is 2.56/h (one row; it includes setup). No deadline is demonstrated.
