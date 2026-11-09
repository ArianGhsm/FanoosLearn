# Classifier V3: live 40-question end-to-end pilot (2026-11-10)

## Real stage counts
- Selected 40 source-incomplete oral-pathology questions (28 board 1404 round 7; 12 promotion 1405 round 7), against an exact-year officially eligible Neville 5e PDF.
- 983 physical PDF pages scanned in **111.79 seconds** (one page at a time, RAM-only); 40/40 had a lexical candidate page.
- Lead Opus adjudicated all 40 and provisionally proposed four sources.
- An independent second Opus scientific review found only **one of those four** fully supported the complete official answer. Three were rejected for negative-answer or two-clause evidence gaps and remain pending.
- The official protected provenance auditor independently accepted the one-row corrected batch; the authorized source-only publisher preview returned 1 source and 0 question/choice/answer changes.
- A fresh verified full backup preceded the authorized source-only apply. The source-only importer **committed 1 row**, returned applied_verified and independently read back the same source fields, PDF page/SHA and unchanged sitting-content fingerprint.
- Read-only SQL confirmed the bank's incomplete atomic sources decreased **3,732 -> 3,731**, with board **1,384 -> 1,383**. No public sitting/year publication occurred.
- All temporary raw exports, PDF scans and scripts from the pilot were deleted; secure historical audit and backup material was retained.

## Time and performance
- Scan-only 40 lexical candidate queries: ~112 sec.
- Entire launch-to-final independent DB verification: ~1,405 sec (23.4 min), including initial setup, all 40 scientific adjudications, separate critical review, fresh verified backup, a protected audit, source-only dry run, apply and readback.
- **One-off fully committed throughput: 2.56 valid sources/hour.** This includes significant setup overhead and is not a steady-state benchmark; equally, 40 lexical hits are not 40 valid sources.
- Required sustained pace to complete 3,731 remaining in five days: ~31.1 independently accepted and committed/hour; four days: ~38.9/hour. Goal is **NOT achieved nor demonstrated**.
- Most pending questions need other official reference editions, approved chapter maps or stronger direct answer-bearing evidence. Improving lexical recall alone will not meet the four/five day requirement.

## Integrity and privacy
The trusted operator separately verified the immutable study, approved source edition/chapter/PDF hash and short evidence, performed independent provenance audit, and used the guarded production source-only publisher with backup/readback. The worker was not given elevated database privileges. No new full-text/OCR/search index, private PDF copies, question content or private case paths are recorded here. Full receipts and decision details stay in protected server research storage.

**Status: proof of one real end-to-end source link; NO-GO for bulk automation.**
