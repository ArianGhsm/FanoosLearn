# Residency 1399 oral surgery — 16 Hupp 7e original chapter/PDF-page proofs (2026-10-11)

**Status: original book research + versioned guarded import proposal; not an applied source or frozen examination publication.** Preserve all official keys, question text, choices, human reviews, prior assessments and student attempts.

## Authority and original PDF

- Exact announced official residency 1399 oral surgery text: `hupp-oral-surgery@7e`; live `bank_reference_validity` returned **all chapters** for this year and exam type.
- Private approved Hupp 7e book PDF SHA-256: `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`. The `data/bank/reference-chapter-pages.json` map uses the same hash.
- Every page below was opened individually on production through the hash/map-gated `scripts/references/verified_reference_pdf.py --edition hupp-oral-surgery@7e --require-map --page N`. Each had the preserved existing official source chapter, a source quote found directly on the original page, the correct original printed-page label and two-neighbor proof `PrintedBookPageEvidence::corroborates(..., true)`. All 16 quote/neighbor validators eventually passed (after retrying individual command-tool blocks). No synthetic page offset was applied blindly, even though this book's observed conversion was consistently PDF page = printed page +14 for these 16 questions.

| Question | Chapter | Original PDF page | Printed page | Official final option | Short proof subject |
| ---: | ---: | ---: | ---: | ---: | --- |
| 131 | 25 | **536** | 522 | 2 | traumatic telecanthus |
| 132 | 25 | **545** | 531 | 1 | condylar fracture |
| 133 | 1 | **29** | 15 | 2 | perioperative usual dose instruction |
| 134 | 27 | **614** | 600 | 1 | nasal deformity anatomy |
| 135 | 13 | **249** | 235 | 2 | laser excision of epulis |
| 136 | 10 | **187** | 173 | 4 | age and bone density for removal |
| 137 | 4 | **62** | 48 | 1 | granulation tissue in second week |
| 139 | 11 | **212** | 198 | 4 | mandible support during extraction |
| 140 | 14 | **269** | 255 | 1 | dental implant abutment complications |
| 141 | 15 | **298** | 284 | 1 | bone morphogenetic proteins |
| 142 | 28 | **630** | 616 | 3 | timing of palatal cleft closure |
| 143 | 26 | **599** | 585 | 3 | surgical-assisted palatal expansion |
| 144 | 17 | **358** | 344 | 1 | buccinator and alveolar perforation |
| 148 | 30 | **659** | 645 | 3 | pain triggered by superficial stimulation |
| 149 | 22 | **478** | 464 | 3 | deep rather than broad incisional biopsy |
| 150 | 31 | **682** | 668 | 4 | disk displacement surgery |

At research time all sixteen had exactly the cited `hupp-oral-surgery@7e` chapter source, `origin='ai'`, `is_primary=1`, NULL `reviewed_at`, a stored printed page (not a physical PDF page), and a matching latest `bank_official_answers.status='final'` option. All 16 are covered by the exact-value whitelist added to the guarded `repair_residency_1399_verified_pdf_pages.php` source-metadata operator, with original PDF/book quote and printed-page checks, single-writer lock, latest bank/option/key fingerprint, four-hour sealed plan, verified full backup and source-only compare-and-swap. The code cannot modify chapter, reference, question, options, final answers, human reviews, attempts or frozen assessments.

## Four Malamed 6e questions explicitly held

Q138, Q145, Q146, Q147 originally have historical `malamed-local-anesthesia@6e` source chapters 13/6/1/14 respectively, but the old attached prose was generated from a *different, 7e* edition and was marked approximate: **do not use that prose to invent exact 6e pages**. Exact 6e PDF is present in the approved map (SHA-256 `2026eff83ef2d2543725becb3685e8cb492f1dcf9f5d7a7ae72a28024ef67bf6`). A four-query exact 6e page-locator research input was staged privately:
`/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/malamed6e-four-queries-20261011.json`.
The `find_in_books.py` page-at-a-time search was launched under the privileged but authorized `fanoosupd` user; review its *actual* result before granting original book chapter+page publication. Do not use a search score as citation proof: read the discovered original page and independently validate page label, evidence, answer and year scope first.

## Publication checklist and phase separation

At research start live strict same-row `node_id + pdf_page + pdf_sha256` count for 1399 was still **95/249**, including eight previously backed-up endo/perio source updates (their independently hash-audited private receipts and full backup are in `docs/ops/RESIDENCY_1399_EIGHT_ORIGINAL_PDF_PAGES_PUBLISHED_20261011.md`). A separate ten-row operative/materials/prostho guarded operator was already merged in PR #312 but was not confirmed deployed/applied at the last recheck. Never report **111/249** (95+16) or **121/249** (95+16+10) without successful separate backed-up production apply, exact live readback, and accounting for other agents.

Before a new apply, independently recheck `/health`, latest Git main, deployed release SHA, DB source/answer/human status, authorized deployment and no competing publisher; run `--subject=oral-surgery --prepare --plan=<new private path>` on a confirmed deployed release, record the plan SHA, verify a **fresh full signed backup**, `--apply` using that plan SHA and a unique receipt, read back all 16 PDF pages/hashes and live complete count; separately audit the **248-question frozen version-8 assessment** against the 249-question raw sitting and arrange sanctioned append-only republish without altering student attempts or official content.

The PDF-page evidence is original-book research. **Neither adding a GitHub operator nor merging this document applies live bank changes.**
