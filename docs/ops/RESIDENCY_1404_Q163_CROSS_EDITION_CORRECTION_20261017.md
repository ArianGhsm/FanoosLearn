# Residency 1404 Q163 — guarded exact-edition correction

**Current state:** the evidence review and guarded operator are in progress on
an isolated branch. The operator has not yet been merged or deployed, and no
production source row has been changed by this work.

## Evidence decision

Q163's existing human-origin citation points to Sturdevant 7e, chapter 14,
which is not in the official 1404 operative-dentistry chapter list. The
official 1404 reference list also includes Summit 4e. Its verified private PDF
has SHA-256
`a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6`.
PDF page 338 is in the SHA-bound chapter 7 map (pages 309–378), and chapter 7
is fully in the official 1404 scope. The original page directly supports the
keyed carbon/hardness fact. The live question, four choices and final official
key were fingerprinted; the keyed position matches that fact.

The production source currently has no legacy page, printed-page label, PDF
page or PDF hash. The planned correction therefore binds the same existing
source row to Summit 4e, chapter 7, PDF page 338 and its exact SHA. The absent
legacy and printed-page values remain null; no printed-page number is inferred.

## Guarded workflow

`scripts/references/repair_verified_human_reference_chapter_page.php` accepts
only an independently SHA-pinned private audit and an exact protected plan.
It rechecks both editions' official year/subject validity, the target chapter
scope, the target SHA-bound chapter map, original-page evidence, question,
choices, official-key and human-source fingerprints. Rollback preview is the
default. Apply requires the authorized updater identity, a fresh verified full
backup, a unique private receipt and a separate live DB readback.

The only permitted updates are the existing source row's `edition_id`,
`node_id`, `pdf_page` and `pdf_sha256`. Its human origin, review metadata,
legacy page fields, question, choices, official answer, attempts and frozen
assessments remain unchanged.

## Whole-year status at the last verified census

The latest completed private census before this proposal recorded 224/230
same-row chapter/PDF-page/SHA pairs: 218 in-scope and six explicitly marked
outside-scope. Six book-based questions still lacked a complete pair:
29, 153, 163, 164, 192 and 206. Q163 remains in the missing group until the
protected apply and independent readback both succeed. The exam must not be
published and its closure issue must remain open while any whole-year gate is
unresolved.
