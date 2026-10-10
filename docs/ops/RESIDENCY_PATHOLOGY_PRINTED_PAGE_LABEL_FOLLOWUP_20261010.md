# Oral pathology: final follow-up on exact printed-page labels (not whole-subject closure)

**2026-10-10 — original same-year editions only.** Latest direct all-course readback confirms 159 sources, 159 questions unchanged since the 113 receipt+full-postdiff verified cases and **26 NULL original printed pages**. The verified original PDFs on the protected FANOOS host, their exact original editions and all original answer choices were checked.

Three additional narrowly scoped page-only review cases:

| Year/question | Edition | Chapter | Old source.page | Original PDF physical page | Correct book-printed page | Keyed-answer original evidence |
| --- | --- | ---: | --- | ---: | ---: | --- |
| 1400 Q66 | Neville 4e | 7 | NULL | 229 | 222 | HSV-infected cells show acantholysis, nuclear enlargement, and other viral nuclear findings; cytoplasmic granulation is the recorded excluded finding |
| 1405 Q48 | Neville 5e | 11 | `pdf 474` | 474 | 464 | Mucus retention/salivary duct cyst with true cuboidal epithelial lining |
| 1405 Q59 | Neville 5e | 16 | `pdf 760` | 760 | 750 | Hereditary benign intraepithelial dyskeratosis produces a cell-within-cell epithelial dyskeratotic pattern |

Private evidence is retained in `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/`:
- `nev4-eighth1-original-book-reviewed-20261010.json` SHA256 `3e1624fa03498576439e85bc3485cbc93e9a3476e5c665a1edfd65deeca3ec7a`.
- `nev5-printedlabels2-reviewed-20261010.json` SHA256 `cd5761bb7fbb7fffeff26dc109bfbe54f3ca6ad76eb356a1b0b6fcbf67bd0af6`.
- Preupdate entire 159-question source snapshot SHA256 `37622ae75e09609c169b69351b61d633ac05a7a856979e8f04da863e0ae47ebc`; 159-question stem/options/media/official answers SHA256 `114900e4bd7436c97f798f967b32fc60e911be043ba0b5314c843171872b387e`.
- Original Neville4e text SHA256 `b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a` (878 original PDF pages), Neville5e `348dafa50b9f53648dc5f7cad97547459ba70a227680ab921c5a04b1b63b6c40` (983 original PDF pages).
- Two source.page-only guarded operators in `scripts/references/`; **PR merge never mutates bank data**.

After CI green and official owner-authorized release, obtain a new independently verified complete project backup; run both operators' live no-write previews; perform two isolated guarded atomic transactions with unique protected receipts and compare-and-swap against the exact expected old source.page values. Export/read back all 159 sources and all 159 question/choice/official answers; compare full published exam definition hashes for 1400 and 1405 and other unaffected versions. Prove **only these three** source.page values changed. Only then increment independently verified corrections from 113 to 116 and reduce NULL source pages from 26 to 25. All nine+ original book/official answer disagreements, five out-of-year syllabus citations, the protected 1404 Q56 human-reviewed NULL source, other NULL pages and 5e populated fact-review cases remain separate; no answer keys or human decisions may be edited in this source classification workflow.

For context, the final official answer document for 1398 was released by the assessment authority but authentic same-year key data for the ten suspect cases has not been authenticated in this workflow; do not silently replace official answer key values with book-derived expectations.
