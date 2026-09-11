# Recipient Fingerprint v6 — interleaved content report

Date: 2026-08-30

## Result

`recipient-pdf-v6` removes the page-level append-only split that allowed an
attacker to keep the original `/Contents` stream and discard every recipient
stream. Each final page now has one coalesced decoded content stream containing
source operators, the existing visible B Nazanin Bold watermark, and the
recipient channels. A garbage pass removes detached streams.

At least one secret-keyed forensic channel is embedded by changing numeric
operands of existing source operators. Text pages use tiny changes in both
translation operands of text matrices. Vector/image-heavy pages use tiny
changes in existing `cm`, `m`, `l`, and `re` operands. The Hamming+CRC payload is
an opaque issuance token, is redundantly distributed, and contains no raw user
identity. Full name, national code, mobile, and trace code remain visible in the
deterrent watermark as required.

The detector still reads v1–v5 evidence and adds independent v6 text and visual
channel reports, including recovered symbols, ECC status, candidate issuance,
and confidence. Low-confidence evidence is not reported as a definite owner.

## Measured before/after

The repeatable mixed eight-page fixture was processed on the local production
toolchain. Figures are measured process values, not estimates.

| Metric | v5 before | v6 after |
|---|---:|---:|
| Page content streams | 376 | 8 (one per page) |
| Wall time | 2.6113 s | 2.4094 s |
| Peak RSS | 72.25 MiB | 73.94 MiB |
| RSS increase during generation | 11.79 MiB | 5.33 MiB |
| Output/source size ratio | 1.1709× | 1.1024× |
| Output size | 1,071,786 B | 1,009,081 B |
| Text channel redundancy | 1.0× | 2.0× |
| Visual channel redundancy | 0.817× | 1.7× |

## Deliberate attack result

The regression attacker was given both the original and personalized PDF. It
parsed every page, retained operator chunks matching the original structure,
and discarded identifiable recipient-only operators. All 296 original chunks
were recovered. Despite that, the detector still attributed the issuance at
0.995 confidence: the interleaved text channel recovered 120/120 symbols with
valid CRC/ECC, and the interleaved visual channel recovered 120/120 symbols with
valid CRC/ECC. A pikepdf/libqpdf rewrite also preserved attribution.

The automated suite additionally covers annotation removal, XObject
neutralization, content-tail removal, crop, rotate, resize, JPEG compression,
screenshot, simulated print/scan, perspective/mobile-photo input, and
personalized-output diff concentration. The machine-readable run is stored in
`output/pdf/recipient-fingerprint-v6-report.json`.

## Queue and cache benchmark

Production default remains one real watermark worker. With 20 accepted requests:

- Same user/document: one generation and upload, 19 deduplicated requests,
  0 failures, 74.91 MiB peak RSS, 2.73 s wall time.
- Twenty distinct issuances: all 20 sent through one worker, 0 failures,
  80.06 MiB peak RSS, 48.97 s total wall time, no orphan job directories.
- Twenty cache hits: 0 downloads, 0 watermark runs, 0 uploads, 20 cached sends,
  31.90 MiB peak RSS, 0.145 s wall time.

The `user × document × watermark_version` lock, atomic Telegram `file_id`
storage, temporary lifecycle, and cache-hit path were not replaced.

## Boundaries

This increases the cost of stripping and preserves attribution after the tested
common manipulations. It does not claim that a PDF is impossible to sanitize or
that every print/scan, screenshot, collusion, or destructive edit is always
attributable. Full-page raster replacement and severe destructive processing
remain capable of degrading PDF-operator channels; image evidence then depends
on the independent rendered micro/visible channels and confidence thresholds.
