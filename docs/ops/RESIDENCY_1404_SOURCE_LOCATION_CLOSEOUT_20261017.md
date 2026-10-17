# Residency 1404 source-location closeout — 2026-10-17

This records the completion of the remaining source-location writes and the
independent production readback. It does **not** authorize a new 1404 exam
publication: several official-scope and answer-evidence gates remain open.

## Deployment and recovery

- PR [#444](https://github.com/ArianGhsm/FanoosLearn/pull/444) merged as
  `ac0f08e151990566324321c52cc69e8566dbc1f7`. Its five CI jobs and the exact
  main-commit CI run `39015123582` passed.
- The official updater request was
  `01a148d9-662c-7f94-9e55-f6f1b7f62890` and finished `SUCCEEDED`.
  Production and updater checkout match `ac0f08e151990566324321c52cc69e8566dbc1f7`;
  `/health` is healthy and `check-sync.sh` is in sync.
- Verified pre-migration backup:
  `/var/backups/fanoos/20261017T075704Z-d429e6c5`, 114 files, manifest SHA-256
  `5bcb161bc03bfe0bab79476eb3c51deef9026425647ace0d672ee66b8af46042`.

## Four remaining questions

| Question | Recorded design location | Evidence disposition |
| --- | --- | --- |
| 29 | Torabinejad 6e, chapter 11, printed page 217, PDF page 224 | Location recorded. The exact passage conflicts with the amended official key. The key remains amended and unchanged; see the private conflict audit and apply receipt below. |
| 164 | Summit 4e, chapter 9, PDF page 438 | Exact page and PDF SHA recorded. The official chapter limit is printed page 217, but page 438 has no corroborated printed label. `scope_unverified=true` and a Persian note are stored; it is not guessed to be in-scope or out-of-scope. |
| 192 | White & Pharoah 8e, chapter 1, PDF page 44 | Location recorded. The source does not unambiguously support the keyed target-angle combination; preserve the final key and see the private conflict audit. |
| 206 | White & Pharoah 8e, chapter 23, PDF page 1170 | Location recorded. The page does not establish the keyed follicular-cortex statement; preserve the final key and see the private conflict audit. |

The source PDF SHA for Q29 is
`09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0`; the
White & Pharoah 8e SHA for Q192/Q206 is
`228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`;
the Summit 4e SHA for Q164 is
`a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6`.

The private records are under
`/srv/fanoos/shared/research/classification/reports/`:

- Q29 conflict audit SHA-256
  `737b98ab79ac458108491d4f68f9edf8069be61ee004ba8da0a13536367e6a6d`; apply
  receipt SHA-256
  `e47b10c8377189ad5c6629df7c9c3ab18eef8d1d4bf0532f1a414b5b2da1c944`.
- Q192/Q206 conflict audit SHA-256
  `a11042a87fd49c7878bda74431ad6a8f6132a2d8b30cf026733df4b72dc912e9`; apply
  receipt SHA-256
  `8ec0a0b3603f38419053e28140db9de527ca9d1502b1d2e625a930c9fbb89556`.
- Q164 scope audit SHA-256
  `35449ea05903d73d060963ccde2181bf2dc25562a7d99c0f6d1363d7ec139793`; plan
  SHA-256 `7dc524fb07bc2737e6dd6218794fd1c6ccb71db737b407852ba063ccb1cbac36`;
  rollback-preview SHA-256
  `53ac71fd7425019871cd68fa6db747189f75343903b67e1710bdb3ea68dcdd3c`; apply
  receipt SHA-256
  `7011c9393855033987d8ce9a633a8e1bf41e10e2511cf0995c1ef527b4d26975`.

Each guarded operation reports zero question, choice, and official-answer
changes. Q164's human origin and review metadata were also preserved.

## Community-dentistry human sources

All ten human-origin source rows for Q221–Q230 have accepted chapter/PDF-page
evidence and independently read back with the same-row PDF SHA. The Q225
CONSORT-extension location is chapter 11, PDF page 162. Q230 retains its
human chapter 12, PDF page 169: that page matches the stem's work/study/living
setting clue more directly. Chapter 2, PDF page 36, contains the broader
treatment-versus-prevention comparison in Table 2-1; it is recorded as related
context, not as a replacement or an official-key correction.

The accepted community page-audit SHA-256 is
`e5dc878e7bc0dd198599a885aab46a7a1059bcae989fd6b7e714da3dc8e26a2a`; the
source-only apply receipt SHA-256 is
`725a6a4a35eba0441bdf4b8d953df1aedcf3bfef5bf29edb6de521ed138b675c`. That
receipt reports zero question/choice/answer changes and zero human-source
provenance/review-metadata changes. The Q230 two-location reconciliation
report SHA-256 is
`cdc23460fef14234a113e71e04435537209cf798b6728a1bf3eb3a82360b4105`.

These results satisfy the scope of [issue #226](https://github.com/ArianGhsm/FanoosLearn/issues/226).

## Live census and publication state

The independent live readback report is
`/srv/fanoos/shared/research/classification/reports/residency-1404-source-location-independent-readback-20261017-v1.json`,
SHA-256 `35ed573dfe7ce4824ad590474f76880b45b5cbc2890e37db2189bdb33544993c`.
It confirms:

- 250 total questions: 230 book-based and 20 English exemptions, with valid
  final/amended keys for all 20 English questions.
- 230/230 book-based questions now have a same-row chapter, positive PDF page
  and source SHA; no book-based question is missing that raw location pair.
- 223 pairs are confirmed in-scope, six are explicitly outside-scope
  (Q44, Q74, Q129, Q130, Q154 and Q219), and Q164's scope is unverified.
- Q29, Q192 and Q206 retain documented source/key conflicts. No official key,
  question, choice, status, frozen assessment or attempt was changed.
- The current published assessment remains version 5 with 247 questions. No
  1404 republication occurred.

The official whole-year publication gate is still **red**: the six
outside-scope questions, Q164's unresolved scope, and the three recorded
source/key disputes cannot be represented as fully verified in-scope
citations. Keep the existing published version; do not close the year-level
release work until these gates are resolved under the official scope.

Issue #422 remains closed for its separate 1405 publication and login scope.
