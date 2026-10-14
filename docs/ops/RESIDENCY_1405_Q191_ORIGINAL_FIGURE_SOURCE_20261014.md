# Residency 1405 Q191 — original-figure source applied, 2026-10-14

This is a later production checkpoint than
`RESIDENCY_1404_1405_AI_CLASSIFICATION_20261013.md`. It supersedes that
file's Q191 hold and **109/250** count only; the 1404 count and other 1405
holds remain as recorded there.

## Independent figure and book evidence

The original 1405 exam booklet page showing Q191 was read visually from a
public mirror of the exam PDF, **not** used as a substitute for the reference
book. Mirror PDF SHA-256:
`0041846589d6f107f365d5aeb99fd821a9c51ee7db0fdc7e9a3ef8ad4eb938c9`;
physical PDF page **30**, printed booklet page **29**. Its diagram **ب**
shows the post-filtration beam with fewer low-energy photons and higher mean
energy than the pre-filtration beam. That matches the current live **final**
answer, choice **2**. The approved original White & Pharoah Radiology **8e**
PDF, SHA-256
`228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`,
physical PDF page **60**, chapter **1**, figure **1.18** directly depicts
the same pre/post filtration change. The approved 1,958-page PDF and its
hash-bound chapter map were verified before that exact page was rendered.

The concise private comparison receipt is
`/srv/fanoos/shared/research/classification/recovery-1405-radiology191-20261014/classification/reports/1405-radiology191-original-figure-check.json`,
SHA-256 `522e6793bfb97c921a53b7a23743a01c4ab0ca67cff97a51b08595773e6d47f0`.
The temporary booklet PDF and single-page images under
`/srv/fanoos/shared/research/tmp/q191-booklet-20261014/` were removed after
the receipt was read back; the exact directory no longer exists.

## Validation and live source-only write

A protected single-question study was isolated from the previously audited
four-question case without changing its original stem, four choices or final
key. Case root:
`/srv/fanoos/shared/research/classification/recovery-1405-radiology191-20261014/`.
`apply_classification.py --pdf-pages` returned **1 accepted, 0 rejected**;
`audit_private_study_batches.py` returned **1 accepted, 0 pending**.
Independent audit SHA-256:
`f1ee61692c15438ffe9f0052b4182aa49707737db6a2538b96df284a3768e455`.

The deployed `import_verified_sources.php` source-only operator was invoked
as `fanoosupd` for `--workspace=01a1037b-0322-7c8f-8904-7c9334fb9cbe`,
`--year=1405 --subject=oral-radiology --stem=radiologyone --expected=1`,
the exact case/audit paths and audit SHA above. The rollback-only preview
returned `source_rows=1` and
`questions_changed=choices_changed=answers_changed=0`. A new full backup
`/var/backups/fanoos/20261014T091524Z-856e712e` independently passed
`verify-backup.php` (**114 files**). The same command with `--apply`, that
backup and a unique case-local receipt committed one source row at
2026-10-14 09:17:32 UTC. Live receipt
`classification/reports/1405-radiologyone-live-source-20261014.json`
under the case root has SHA-256
`6b430daae3d56e70dc4743a6ab25562558207952f983b4fb07944d73c769e01c`.

Independent SQL readback found Q191 with official White & Pharoah 8e,
chapter **1**, `pdf_page=60`, exact approved PDF SHA and `origin=ai`.
The strict one-row chapter+PDF-page+SHA count rose **109 → 110/250**;
20 valid-key English questions are exempt, leaving **120/230 clinical
questions incomplete**. All 250 bank question rows, 1,000 choices and 250
answer-history rows had identical before/after digests, stored in the
private `1405-radiology191-immutable-before.json` and
`1405-radiology191-postapply-checkpoint.json` receipts. The latter has
SHA-256 `be0cf16ce761b093f16865f0eb2cbb4c4f52591f268211b0a897dba2ad8c47d2`.
Public `/health` remained OK on release
`427d14181a09eb753dced97ff910434ad5e350f1`. This source-only import
did not republish or edit the frozen assessment.

**Separate content defect:** the live question itself still lacks the exam
graph image. Source classification is now supported, but restoring the
question's graph requires a separate controlled content correction and
must not be silently bundled into source-only publication.

**Other holds:** Q207 has two verified in-scope source chapters and requires
a two-citation publisher; Carranza 14e is still represented by an incomplete
105-page server PDF. Neither has been completed by the Q191 operation.
