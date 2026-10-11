# Residency 1399 endodontics Q31 — original QMix page and safe isolated import

**Evidence validated, not yet applied to the production bank.** Source and book are original official `torabinejad-endodontics@5e`, chapter 16, question 31, latest official final choice 3.

- Exact private original approved PDF hash `71f21c26a4f1e5d038ff6aa408669a5cc040e90c94eea0bf2e00478997710146`.
- Direct `verified_reference_pdf.py --edition torabinejad-endodontics@5e --require-map --page 293` read the page marked **printed book page 281** inside physical chapter 16 (map PDF pages 285–312).
- Direct page contains original quote **“QMix has the potential not only to remove the smear”** and explains an antibiofilm effect and the irrigant components. PHP `PrintedBookPageEvidence::pageContainsEvidence` returned `YES`; `corroborates` with printed 281, two neighboring labels and bound exact original PDF hash returned `YES`.
- Existing AI source row is chapter 16, stored printed 281 but no original physical PDF-page+hash (as last read). Never infer source completeness merely because `page=281`.

Earlier Q25/30/32 of the **same canonical subject** were independently successfully updated, with private verified backup and receipts documented in `RESIDENCY_1399_EIGHT_ORIGINAL_PDF_PAGES_PUBLISHED_20261011.md`. Therefore adding Q31 to the original `--subject=endodontics` bulk operator would fail or attempt to reprocess already committed source rows. Versioned operator uses a strict dedicated alias `--subject=endodontics-qmix` that maps **only its DB query** to canonical `endodontics` and can mutate only **Q31** with the same exclusive lock, source/answer/human immutable fingerprints, four-hour sealed private plan, exact original-page quotes and neighbor checks, backup verification, metadata-only CAS, receipt and readback.

Requires authorized fresh CI green deployment, unique protected private `--prepare --plan=...`, verified fresh full backup and `--apply --plan-sha=... --backup=... --receipt=...` with `--subject=endodontics-qmix`. Do not treat as production completion until source row and frozen student assessment are audited separately. No human source, original question, choices, official answers, examination version or student attempts may change.
