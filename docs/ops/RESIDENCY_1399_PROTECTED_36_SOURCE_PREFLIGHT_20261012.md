# Residency 1399: verified read-only plans (2026-10-12)

**Production writes in this pass: 0.** Last strict same-row `node_id + pdf_page + pdf_sha256` count **95/249**, with **134 clinical pending** and 20 English exempt. Last `/health` was OK on deployed release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`, older than new CI-green source operators in GitHub main. The updater checkout is **not** a deployment.

## Five existing AI source metadata dry-runs — all PASSED

All files below are protected under `/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/`. Original official 1399 edition, chapter, final key, answer-supporting verified PDF text, 2-neighbor printed label, no human reviews, live original source fingerprints and source-only allowlist were independently checked:

| `--subject` | Q count | Private `--plan` filename | SHA256 |
|---|---:|---|---|
| `oral-surgery` | 16 | `oralsurgery-plan-20261012-01.json` | `1c60ac9327629e46433d81aa690f8dabb5058e47bf8e11c83b63d8aa9bc6e802` |
| `prosthodontics` | 9 | `prostho-plan-20261012-01.json` | `51c490bbd410e445eabbe8629fc6ded6f383fde66c821e046f3d1ca02505f117` |
| `dental-materials` | 5 | `materials-plan-20261012-01.json` | `c557717c3188e66cd5a8b72f708b66f5e2a80ff033cc3eb7643bc82759ad5c2b` |
| `operative-dentistry` | 1 | `operative-plan-20261012-02.json` | `a834dc2a18d4a07598b2301ac362a519dddfec4187102ebc27f2b5e98d9bc63f` |
| `endodontics-qmix` | 1 | `qmix-plan-20261012-02.json` | `93cb1ca26ec24b23df34f47d4450dff4718452cbce847b71108db0691683f49e` |

**32 source rows verified for possible later update; all returned `applied=false`.** Earlier parallel operative/QMix attempts hit DB lock timeout 1205; sequential independent -02 previews passed. Q97 correct original *answer-bearing* Zarb13e PDF page is 271 / printed257. Q31 QMix uses isolated alias rather than redoing previously applied endodontic Q25/Q30/Q32. Plans expire after four hours and must be regenerated if stale.

## Four new 1399 radiology insert previews — PASSED

The eight currently source-free questions Q191–195,Q206–208 were exported read-only from live 1399 bank: protected `radiology-study-20261012-pass1.json` SHA `fdd4ddd4afe1777f9e69bd683ce200f97beb7f4c6b03e94490fd2e3dc4d93162`. Exact original White & Pharoah8e PDF SHA `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`.

Original source decisions with exact chapters and physical PDF pages: Q191 ch10 PDF498, Q192 ch9 PDF439, Q193 ch4 PDF169, Q208 ch24 PDF1233. Q194/195/206/207 remain held, especially Q206 original-book gallium-vs-stored-technetium **official-key conflict**; no key was changed.

`apply_classification.py --pdf-pages` passed **8 decisions, 0 rejections** (four source, four explicit pending). A private W01 stage v1 source provenance audit correctly refused four `none` research decisions; **v1 preserved unchanged**. Distinct immutable coordinator stage `/srv/fanoos/shared/research/classification/coordinator/W01-radiology-next-20261012-v2` contains original live study, validated sitting, **only four source decisions** and exact provenance audit. `audit_private_study_batches.py` passed **8 questions, 4 accepted sources, 4 pending**, content unchanged. Audit SHA `6a20dbcd998a86821cb2a78d54e9d99a956a200f70d26a2d8a7b0914fb9e1061`.

CI-green merged PR #344 narrowly permits only this stage for `year=1399, subject=oral-radiology, stem=radiology-remain, expected=4` in `import_verified_sources.php`. Its read-only atomic source-only preview passed: `applied=false, source_rows=4, questions_changed=0, choices_changed=0, answers_changed=0`. Validated sitting SHA `734b5aac0881fff80bcab0aec5c5bad8e1faaf41c497a89f374c82d27f9ddad5`.

## Stop conditions and exact continuation

**No new source commit or student assessment republish** is confirmed. Prior frozen assessment was version 8 with 248 questions against 249 raw bank questions; never rewrite frozen versions/attempts/answers. Official same-year PDF, mapped chapter, exact page/hash and no human review remain mandatory.

Before every apply: recheck current main/deployed SHA/live bank; deploy only via the official audited updater with an **actually authorized** deployment-manage account, never borrowing an identity. Confirm updater CI, fresh full verified backup, retention, disk, site health. Regenerate expired 4-hour existing-row plans on the **deployed release**; apply each protected source batch separately with exact plan SHA and unique private receipt, then read back all bank rows and immutable question/choice/key hashes. For four radiology *new inserts* recheck immutable stage audit/hash and repeat source-only preview from deployed `import_verified_sources.php` using exact v2 `--package-root` and `--expected=4`, then apply only with verified fresh backup/unique receipt. Finally run the **whole frozen assessment** content-identical before/after audit and authorized append-only version publication.

Other holds: Malamed6e questions have historical 7e anchor provenance; Nowak6e pediatric legacy anchors cite McDonald11e, with Q176 ch36 and Q184 ch30 outside official 1399 scope. See `RESIDENCY_1399_RADIOLOGY_PEDIATRIC_PDF_AUDIT_20261012.md` and `RESIDENCY_1399_HUPP_16_ORIGINAL_PDF_PAGE_PROOFS_20261011.md`.
