# Residency 1405 Q207 — TWO exact official source chapters and PDF pages (2026-10-13)

Q207 is one of five previously source-free oral-radiology questions (the other four Q191–Q194 already have separately audited one-source chapter/PDF-page decisions, original published question/answer preservation, and guarded source-only rollback preview). The live Q207 final official answer is **option 4**, naming **glandular odontogenic cyst and aneurysmal bone cyst** as two lesions needing post-treatment recurrence imaging.

The exact official 1405 reference is **White & Pharoah, 8th edition**, `white-pharoah-radiology@8e`, with current approved private PDF SHA-256 `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`. Both relevant chapters were independently checked inside the *explicit official 1405 syllabus* and within the approved SHA-bound chapter map.

| Correct answer lesion | Official chapter | Actual source-PDF page | Verbatim source support |
|---|---:|---:|---|
| Glandular odontogenic cyst | **23** | **1161** | “Treated cases should be followed with periodic radiographic examinations to assess for recurrence.” |
| Aneurysmal bone cyst | **24** | **1294** | “The recurrence rate ranges from 19% to about 50% after curettage, and approximately 11% after resection. Careful follow-up is needed.” |

Both exact phrases were looked up in the actual current edition PDF page text; neither page number is a guessed offset, title index or printed-book page. Actual official-year validity allows both ch23 and ch24. The original published question stem, four choices and latest final answer were compared read-only to the official key; **no bank_question_sources row exists** yet, and no answer or original options were changed.

Protected immutable scientific research report:
`/srv/fanoos/shared/research/classification/recovery-1405-radiology207-20261013/classification/reports/1405-radiology207-two-chapter-evidence.json`

Report SHA-256 `2b1f59080f1a41b4935ee1824502de84aa9705f0c5377c2ec0b9859528b1a8bc`. Original live question/choices/key snapshot SHA-256 `c876a35a823a3405e7f1f19dcaa138bbb003311aaabc7a149b209bdd84c1793c`.

## Important blocker; DO NOT publish only one half as if complete

`SourceOnlyPublisher::run` currently enforces **exactly one source per accepted question** (`count($sources) !== 1` fails closed), whereas Q207's correct official option cites two distinct conditions described in **different official chapters**. Recording only one chapter with one page as a complete citation would hide essential correct-answer support, conflicting with the requirement that **every chosen chapter must have its actual PDF page**. Therefore Q207 is a **scientifically verified TWO-CHAPTER research hold**, not a completed bank source or publication. Any eventual source-only workflow should support a reviewed two-source mapping, independently audit both exact pages, preserve the original question/key/choices and frozen assessment attempts, and obtain unique write receipts plus independent live readback.

This is not a request to change the official answer or rewrite a published exam. The radiology question is *not* marked fully sourced pending a safe two-citation publisher and evidence audit.

## 2026-10-16 guarded two-source route

`scripts/references/insert_verified_two_sources.php` adds a separate atomic
source-only route for one currently source-free, published residency question.
It requires exactly two distinct official-year chapters, a SHA-pinned private
independent original-PDF audit, a protected plan, unchanged stem/four choices/
latest final answer hashes, both actual PDF-page quotes, a deployed SHA-bound
chapter map, and an explicit official-year scope check for each chapter.
The first source is primary; the second is supporting. Both receive physical
`pdf_page` and the same approved original PDF SHA. A preview rolls back its
transaction. Apply requires `fanoosupd`, a verified full backup no older than
four hours, and a new private receipt. A failed precondition commits neither
source. Frozen versions and grades are never written by this operator.

For Q207 the protected plan uses format `fanoos.two-source-citation-plan/1`,
`exam_type=residency`, `year=1405`, `subject=oral-radiology`, `number=207`,
SHA-256 of the original stem, ordered four-choice rows, and latest official
answer row, `audit_path` and `audit_sha256`, plus exactly two `sources` matching
the audit's chapter/page/quote and approved PDF SHA. Physical page labels may
be stored as `pdf N` where no printed label has been independently verified.
This document and the merged code do **not** claim a production Q207 source
until a rollback preview, backed-up apply, unique receipt and independent
readback actually succeed. The year remains unpublished while any other
scientific or source-field holds persist.

## 2026-10-16 production result

PR **#425** passed all five CI jobs, was merged as
`e7a194b580c66532f24740356380223552a87dad`, and that exact `main` commit
passed all five post-merge CI jobs. Official updater request
`01a143a8-ff04-7d2f-880c-019169d06b99` succeeded. The live release and
updater checkout both matched `e7a194b`; public `/health` returned `status=ok`.
The updater-created backup `/var/backups/fanoos/20261016T074431Z-1e18d94e`
independently verified **114 files** before the data apply.

The private plan
`/srv/fanoos/shared/research/classification/reports/1405-radiology207-two-source-plan-20261016.json`
has SHA-256 `87a2fa24d4865c27ae70c22eda4835fefefb2f07120beb0e256d9cc4e91ee3f2`.
Its exact transaction first passed rollback preview, then applied both rows
atomically under `fanoosupd`. The protected applied receipt is
`/srv/fanoos/shared/research/classification/reports/1405-radiology207-two-source-applied-20261016.json`,
SHA-256 `eb687a706c91316e6c9865d659c73777e27d6f4fde19fcb055122c8feee88ea3`.

Independent live SQL readback matched both new source IDs: primary chapter 23
/ physical PDF 1161 and supporting chapter 24 / physical PDF 1294, both bound
to approved White/Pharoah 8e SHA
`228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`.
The current stem, ordered four choices and latest `final` answer position 4
still match the immutable hashes bound into the plan. The strict source-field
count is now **226/230 book-based**, plus **20/20 English exemptions**.

Four source-field gaps remain: Q44, Q81, Q99 and Q218. Q44 and Q218 retain
outside-year-scope historical chapters without a proven in-scope replacement;
Q81 still needs the original exam figure reconciled to the official key; Q99's
stored ellipsis evidence still fails the deployed exact-page guard and its
write remains safety-blocked. Q217 is separately field-complete but its chapter
is outside official 1405 scope. Therefore the whole-year scientific gate still
fails: no new frozen 1405 exam version was published and Issue #422 remains
open.

After verifying GitHub PR head identities, the already squash-merged branches
for PRs #419, #420, #421, #423 and #424 were deleted with exact SHA leases.
The unique unmerged 1400 documentation branch and draft PR #388 remain intact.
