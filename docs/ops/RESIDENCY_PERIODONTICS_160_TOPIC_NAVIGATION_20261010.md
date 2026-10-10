# Residency Periodontics — 160/160 latest-edition topic navigation crosswalk (2026-10-10)

**Owner request:** assign a topic/chapter across all 160 questions based on the latest available Carranza chapter structure, including legacy exams. This is a *separate study-navigation artifact*, not an amendment to official per-year source citations. It **cannot honestly be called original-page verified**. Original reference per exam year remains Carranza 12e (1398), Carranza 13e (1399–1403), Carranza 14e (1404–1405).

## Edition provenance and scientific limits

- The latest **chapter catalog** is Carranza 14e with 88 named chapters in `data/bank/reference-tocs.json`. It is usable as a *chapter taxonomy*; the registered private 14e object is only a 105-page supplement and fails the full-book PDF page-count and chapter-map check. **No full 14e book proof has been obtained.**
- Approved full Carranza 13e PDF (1,991 pages; SHA256 `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`) and Carranza 12e PDF (1,766 pages; SHA256 `1332e1f92ec1dea1ee99c7382993ce3f191407099d7650ebb97c79b989553263`) are available privately. Earlier full-book text derivatives were removed; any future book evidence must be read from selected PDF pages directly.
- Previous immutable scientific audit: **160** residency-periodontics questions; **144** existing source rows; **16** source-free 1398; **15** original-year approved printed-page citations; **129** historical source rows whose original-year exact page is not validated. The apparent 40 legacy 14e page numbers cannot count as validated.
- The topic map derives 1398 question subjects via explicit 20-item original12e-stem→14e-TOC review, 1399–1403 via explicit prior13e→14e *subject* concordance with **individual question overrides** for known misclassifications, and 1404–1405 via provisional existing14e chapters with independently reviewed semantic exceptions. It maps **all 160 to one of 88 14e named topic chapters**; by design the *citation* fields are never modified and **no printed page is generated**.
- The original 1398 source-free 16 have malformed, absent or scientifically disputed *recorded choices/official keys*. A topic label does not validate those answers. None may be published as a new original source on the basis of this crosswalk alone.
- Existing historical source-year chapters are not silently reinterpreted. The crosswalk stores `original_existing_source_chapter` beside `topic_chapter`, `method`, `rationale`, `needs_human_answer_review` and `publication_eligible_as_official_year_reference=false`.

## Reproducible protected workflow

Read-only production input snapshot (160 unique questions, year 1398–1405, 20 each), private:

`/srv/fanoos/shared/research/classification/reports/periodontics-14e-topic-overlay-20261010/all-160-live-source-and-answer-status-snapshot.json`

SHA256 `976cb162881419a1715f1c7c0333fb1be18bb7f3387f72c7e7722295986e5f76`.

Run the versioned, SHA-pinned local-only generator:

```bash
sudo -n -u fanoosupd python3 /srv/fanoos/updater-checkout/scripts/references/build_periodontics_14e_topic_overlay.py \
  --input=/srv/fanoos/shared/research/classification/reports/periodontics-14e-topic-overlay-20261010/all-160-live-source-and-answer-status-snapshot.json \
  --toc=/srv/fanoos/updater-checkout/data/bank/reference-tocs.json \
  --out=/srv/fanoos/shared/research/classification/reports/periodontics-14e-topic-overlay-20261010/topic-160-crosswalk.json
```

**Public 160-row chapter index (no question text or copyrighted book content):** [PERIODONTICS_160_14E_TOPIC_MAP_20261010.csv](data/PERIODONTICS_160_14E_TOPIC_MAP_20261010.csv). Each row identifies year, question number, the provisional latest Carranza14e topic chapter and title, the historical source chapter, official-year edition, original-book citation verification flag and 1398 answer-review status. The column `status=14e_TOC_TOPIC_ONLY_UNVERIFIED_FULL_BOOK` applies to the navigation label; the separate verified flag refers only to the 15 independently proven original-year citations. This CSV is **not** an importable official-source feed.

**Executed and independently verified** on the production host without writing to production: exactly **160/160** topics assigned, spanning **45 distinct** named 14e chapters, with each current question ID, stem hash, historical source identity, original chapter and page, and official answer status read back against live DB. The original-to-topic derivation comprises 20 source12e/topic14e reviews, 90 standard source13e→topic14e crosswalks, 17 question-specific semantic overrides and 33 retained provisional 14e chapter tags. All **160 source/question/answer records matched the live readback unchanged**.

Protected result `/srv/fanoos/shared/research/classification/reports/periodontics-14e-topic-overlay-20261010/topic-160-crosswalk.json`, SHA-256 `fc898502a159b6ac01e40a3659aae951cda09364ab493218c5a26e2beacc74ab`.

Protected independent readback report `/srv/fanoos/shared/research/classification/reports/periodontics-14e-topic-overlay-20261010/topic-160-independent-db-readback.json`, SHA-256 `86b9d5834048755c301e823f56ebb4d23b0fc249caf880a93d0370866414a80b`. **Production DB writes: 0.**

The output stays protected on the server and rejects clobbering an existing result. It is a **complete research/navigation record only**, not an import payload for `bank_question_sources` or site publication. Any future site UI for topic navigation must explicitly display `14e topic (provisional)` separately from `official question-year reference and page`, and must not overwrite old source, exact print page, human edits, assessment versions, student attempts or answer keys.

**Closure decision:** 160/160 *provisional topic navigation* can be prepared and archived; **scientifically approved chapter+source+page citation for all 160 remains open**. Closing that latter requirement would require complete original 14e (or authorized user decision to renounce original-year citation accuracy), a genuine original 13e page audit of 89 historical sources and human review of 16 1398 official answer problems. Do not claim a guessed chapter/page is exact.
