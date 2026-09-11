# Recipient Fingerprint v8 — secure raster audit

Date: 2026-08-31

## Outcome

`recipient-pdf-v8` retains the secure-raster removal of the critical clean-layer
recovery path introduced by v7 and fixes the image-heavy visual profile. The
personalized PDF no longer contains the original vector/text page plus added
recipient objects. Each final page is one burned-in image; keeping an original
content stream or deleting added security streams cannot reveal a clean source
because neither exists in the edition.

The visible design was deliberately reduced from ten full identity blocks to
3–5 page-aware blocks plus 1–2 compact trace marks. Full name, full national
code and full mobile remain visible in B Nazanin Bold. Image-heavy pages keep
full identity blocks in lower-salience regions and put at most one faint
stroke-and-fill compact trace on the dominant image, without a background
rectangle. Hidden payloads contain only a secret-derived page token and never
identity data.

## Final architecture

- PyMuPDF renders and composites one page at a time.
- Two secret HMAC constellations per page carry a 40-bit opaque page token with
  repetition-3 error correction (240 physical symbols total).
- Four pages are held per batch. Production qpdf merges and validates batches;
  pikepdf is the local fallback. This reduced measured peak RSS by about 73% on
  the 50-page fixture.
- Final structure: one content stream and one image per page; no annotations,
  embedded files, live text, original PDF or recipient Form/XObject.
- Output metadata carries only an opaque `DFP-*` correlation value. The
  detector treats it as supplementary, never definitive.
- The existing single worker, bounded queue, idempotent issuance lock, atomic
  Telegram `file_id` cache and `finally` cleanup remain intact.

## Benchmark before/after

The v6 baseline is the previous vector/interleaved implementation. v8 is the
final batched raster implementation. Fixtures are synthetic mixed pages with a
high-frequency image that is harder to compress than ordinary text notes.

| Pages | v6 wall | v6 peak RSS | v6 output | v8 wall | v8 peak RSS | v8 output |
|---:|---:|---:|---:|---:|---:|---:|
| 10 | 2.62 s | 63.31 MiB | 1.03 MB | 4.88 s | 109 MiB | 4.70 MB |
| 50 | 14.09 s | 64.71 MiB | 1.49 MB | 22.84 s | 107 MiB | 22.65 MB |
| 150 | 62.54 s | 65.85 MiB | 2.61 MB | 61.77 s | 102 MiB | 48.98 MB |

The added CPU, disk and output size buy actual pixel burn-in rather than a
cosmetic PDF-object rearrangement. The first unbatched v7 prototype reached
about 402 MiB at 50 pages. Four-page batching reduced it to about 107 MiB. The
reproducible final v8 fixture completes the same 50-page case in 22.84 seconds.
The 150-page profile uses
150 dpi/JPEG 78 and stays below the 49 MiB application ceiling. Production keeps
one worker and a 300-second deadline.

Twenty-request load results with one worker:

| Scenario | Accepted/deduplicated | Wall | Peak RSS | Failure | Orphans |
|---|---:|---:|---:|---:|---:|
| cached `file_id` | 20 / 0 | 0.043 s | 31.54 MiB | 0% | 0 |
| same user/document | 1 / 19 | 0.72 s | 79.16 MiB | 0% | 0 |
| 20 distinct one-page documents | 20 / 0 | 10.55 s | 97.91 MiB | 0% | 0 |

## Structure and extraction audit

The audited ten-page output has ten page images, ten content streams, zero
annotations, zero attachments and zero extractable characters. Poppler
`pdftotext` returned an empty result with exit code 0. Neither raw PDF bytes nor
metadata contain the test national code, mobile or Trace Code. The identity is
still visibly present in rendered pixels.

Two personalized versions differ across all 100 spatial tiles; differences are
not concentrated in one PDF object or one page region. Neutralizing all Form
XObjects touched zero objects. Annotation removal and content-tail removal
touched zero security objects. A pikepdf/libqpdf rewrite and a complete
image-based raster rebuild retained valid forensic attribution. Local qpdf and
Ghostscript executables were unavailable. The deployed production release
passed its qpdf validation/rewrite gate and Poppler extraction check.

## Detector regression

On the audited fixture, clean PDF, screenshot, JPEG Q60/Q75/Q90, 5% and 10%
crop, rotation, resize, grayscale, raster rebuild and simulated print→scan
produced a valid ECC channel and definitive attribution. A 20% crop, strong
mobile perspective, targeted small-component removal and destructive
original-diff replacement were inconclusive. A decoy issuance never received
definitive attribution.

The detector reports each copy and ECC fusion separately with recovered symbol
count, token agreement, correction count, ECC state and confidence. Metadata
alone cannot produce attribution. v1–v7 decoding remains covered by regression
tests. Its original-guided fallback evaluates a small angle/scale bank one
grayscale candidate at a time, avoiding both deskew instability on dense images
and a large bank of retained full-page buffers.

The final production release is `telegram-20260831-081406`. Its real cached
owner PDF passed qpdf, empty Poppler `pdftotext`, one-image/one-stream and
no-extractable-PII gates. The service runs one worker with queue size 48 and
measured 39,476 KiB idle RSS. A first protected send created and cached the
personalized Telegram `file_id`; the immediate second send reused it.

## Limits

This design is not non-removable, AI-proof or guaranteed to identify every
leak. A person holding the exact original can destructively replace changed
pixels, and aggressive crop/photograph transformations can remove enough signal
to make the result inconclusive. The achieved goal is narrower and measurable:
the trivial PDF-object deletion path is gone, visible deterrence is less
intrusive, common transformations retain attribution, and weak evidence is not
reported as a certain owner.
