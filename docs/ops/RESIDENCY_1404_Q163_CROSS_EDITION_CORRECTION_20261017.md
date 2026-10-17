# Residency 1404 — Q153/Q163 corrections and remaining gates

**Historical checkpoint (before the final source-location writes):** 226/230 book-based questions have a
same-row chapter, original PDF page and PDF SHA; 220 are in-scope. Six are
explicitly marked outside the announced syllabus, and four still lack a
verified source/page pair. The 20 English questions meet the official-key
exemption. This count is not current. The live status, final receipts and
remaining publication gates are recorded in the
[source-location closeout](RESIDENCY_1404_SOURCE_LOCATION_CLOSEOUT_20261017.md).

## Source corrections completed

Both changes used guarded, source-only operators with rollback previews,
verified backups, unique private receipts and independent live readbacks.
Question text, choices, official keys, statuses, human provenance/review
metadata, attempts and frozen assessments were preserved.

| Question | Verified source after correction | Changed fields |
| --- | --- | --- |
| Q153 | Sturdevant 7e, chapter 3, PDF page 123, SHA-256 `4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec` | `node_id`, `pdf_page`, `pdf_sha256` |
| Q163 | Summit 4e, chapter 7, PDF page 338, SHA-256 `a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6` | `edition_id`, `node_id`, `pdf_page`, `pdf_sha256` |

Q153's page is inside the full official Sturdevant chapter 3 scope. Its
legacy and stored printed-page fields remain null. Q163's former Sturdevant
7e chapter 14 citation was outside the 1404 list; the replacement uses the
official Summit 4e chapter 7 scope. Its absent printed-page fields remain
null. Both original sources retain `origin=human` and their existing review
metadata.

Private operation records:

- Q153 plan SHA-256 `df5bc84f2220c962f0e5bc02ccf0569784d9dcc193dc1a84035d98c838257e4a`; accepted rollback-preview SHA-256 `77b2a70f6a210d304c1621db01e3b80e52170267ae76ac9bb733f3b2584811b`; apply receipt SHA-256 `c74dc9ac502e62e00baa06625b5b6a9b7d963b56a585acc547d6e96a55bdc55e`; independent readback SHA-256 `8b008d69ff2c2cd28735bd7f02d04f2037a66263afd34e6a4094b146febded44`. Backup `20261017T055744Z-6c094e18` verified with 114 files.
- Q163 audit SHA-256 `cebf2a6eaae7a30aa36f32acb04246c162c70cd5395ffbc772b4c5893175db44`; plan SHA-256 `8bb223c78b4eacbac8b1f94f04aad39318763b81c08987e5322cfeb5e2b4264c`; accepted rollback-preview SHA-256 `7bd3615081775f9d96823ddfb3ceafdc61460e657439b7ebd22478dbca69ac73`; apply receipt SHA-256 `d1f3ed3e0bb06ea56edb7f55c8a8ab6c191191a3b1aac885dd71a6a13d70e97c`; independent readback SHA-256 `3c26397bc5c3e2aca648d5933695035c14f54a1cdb06a7a0da95da836b058fee`. Backup `20261017T062643Z-519372e2` verified with 114 files; manifest SHA-256 `7d91ff010a6b35b219d78ab1b29eb4ad3661beca211ddbb47d51ccc5de5a8881`.
- Combined remaining-gates readback: `/srv/fanoos/shared/research/classification/reports/residency-1404-open-gates-independent-review-20261017-v1.json`, SHA-256 `0390891a99bff3e19c9750790f91cacb5b52823a703906f60020d674db07d804`.

The initial Q153 preview rejected a workspace ID that did not match the live
source row; no write occurred. The ID was corrected from the source row, the
rollback preview passed, and only then was the apply run.

## Code delivery and production

[PR #442](https://github.com/ArianGhsm/FanoosLearn/pull/442) added the
cross-edition source-only guard, audit binding and tests. PR CI passed 5/5;
merge commit `f6505356ee3eaaaa7605ec8bab92b9a2e94d961f` also passed 5/5 in CI
run `39009386612`. Official updater request
`01a14886-a4ca-789f-82be-f4613e2e5cd5` succeeded. The live release and updater
checkout are both `f6505356ee3eaaaa7605ec8bab92b9a2e94d961f`; `/health` is OK
and `check-sync.sh` reports in sync.

## Four unresolved source gates

These questions remain unchanged. Their official answers and source
provenance must be preserved until the listed evidence gaps are resolved.

| Question | Current evidence and hold |
| --- | --- |
| Q29 | The 1404 amended key conflicts with Torabinejad 6e trauma guidance on PDF page 224 (printed page 217). Keep the key and source unmodified pending authoritative resolution. Private hold report: `/srv/fanoos/shared/research/classification/reports/residency-1404-scientific-holds-q29-q126-20261016.json`, SHA-256 `2ae2c54c5da33e899366706b1dd43ae057b36af9d70eaa96d9eb13e9accfa798`. |
| Q164 | Summit 4e chapter 9, PDF page 438, supports the keyed solvent property. Chapter 9 is official only through printed page 217, but PDF page 438 has no corroborated printed-page label; its inclusion in the partial scope cannot be established. Do not guess or mark it outside-scope without that evidence. |
| Q192 | White & Pharoah 8e page 44 describes focal-spot projection and heat dissipation; page 287 (chapter 6, also in the official list) describes the target-angle relation in a way that conflicts with the currently keyed combination. Preserve the final key pending resolution of the angle convention. |
| Q206 | White & Pharoah 8e page 1170 (chapter 23) describes simple-bone-cyst scalloping, cortical thinning and intact lamina dura, but does not establish the keyed follicular-cortex statement. Preserve the final key. |

The six existing complete but outside-syllabus source pairs are Q44, Q74,
Q129, Q130, Q154 and Q219. Each retains its exact original PDF page/SHA and
the explicit `outside_scope_exam_question` status and Persian note. This flag
does not satisfy the whole-year in-scope publication gate.

## Closure and publication

No 1404 assessment publication or issue closure occurred. The whole-year
gate remains blocked by four missing pairs and six outside-scope cases.
Issue [#422](https://github.com/ArianGhsm/FanoosLearn/issues/422) was already
closed on 2026-10-16 after its separate 1405 publication/login acceptance
criteria were recorded complete; this 1404 work did not change that issue.
