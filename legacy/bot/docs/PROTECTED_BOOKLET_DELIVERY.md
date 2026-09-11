# Protected Booklet Delivery v9

## Scope and source of truth

The existing `جزوات` action in Dent1402Bot remains a reply-keyboard workflow:
course, syllabus session, then `ویس`, `پاور`, `جزوه`, or `رفرنس`. It is not a
Mini App and does not recreate the retired website Reader. The exact private
Telegram management channel is the source-file authority. SQLite stores only
caption-derived routing, Telegram identifiers, issuance/attribution state and
delivery receipts; source and personalized bytes are temporary.

Caption parsing accepts allowlisted course/term tags, sessions 1 through 40 and
the four supported content kinds. Every request passes canonical bot
authentication, live Telegram membership and current course/term permission at
entry, enqueue, dequeue, every cached resend and immediately before final
delivery. Term 7 uses the shared Persian-calendar entitlement contract in
`TERM_SUBSCRIPTION_ACCESS_V1.md` from 1 Mehr 1405; before that date its prior
authenticated-open behavior remains. The signed website contract
`bookletWatermarkIdentityV1` is the sole identity authority and returns full
name, national code and verified mobile only after canonical authentication.

## Queue, transport and cache

- Production uses one real PDF worker and a bounded 48-item queue. Twenty
  requests are accepted without creating twenty concurrent renderers.
- Duplicate `user × document × watermark version` requests are coalesced.
  Durable rate limits and a bounded processing deadline protect the queue.
- Every job gets a private `0700` directory. Source, output, page batches and
  scratch files are removed in `finally`; age-based orphan cleanup handles a
  process crash without deleting active jobs.
- Voice and non-PDF media are copied unchanged with `protect_content=true`.
  Both first PDF upload and cached `file_id` resend also set
  `protect_content=true`.
- The first successful upload atomically publishes its Telegram `file_id`.
  Cache hits perform no source download, rendering, watermarking or upload.
  An upload retry reuses the same issuance and fingerprint.
- A cached Telegram `file_id` is never permission. Paid/free entitlement and
  live membership are re-read immediately before each resend. PDF generation
  that loses permission before upload is discarded without delivery.

## Secure raster recipient edition (`recipient-pdf-v9`)

v9 retains v8's secure raster burn-in pipeline. It changes only visible-mark
placement and expected-issuance verification; the renderer, adaptive DPI,
batching, output structure and Telegram delivery path remain the same:

```text
private Telegram source
  -> render one source page
  -> composite visible identity and secret forensic constellations
  -> encode that page image
  -> write a four-page bounded-memory batch
  -> qpdf merge and validation
  -> protected Telegram upload
  -> immediate temporary cleanup
```

- The final PDF is image-only: one page image and one page content stream per
  page, zero annotations, zero embedded files and zero live recipient text.
  `pdftotext` returns no identity or trace. The original PDF is not embedded.
- Full canonical name, full national code, full mobile and Trace Code remain
  visibly burned into pixels as the explicit deterrent. Hidden channels contain
  no raw identity or platform ID.
- Layout is page-aware and HMAC-deterministic. Cover, text-heavy, image-heavy
  and sparse pages use 3–5 full identity blocks (never more than 5) and 1–2
  compact trace marks. Candidate scoring rewards real whitespace and
  distribution, penalizes headings and paragraph starts, and strongly rejects
  the central 50% ROI of each image. An image-heavy page keeps one smaller,
  faint compact trace at a figure edge/corner rather than its center. This is a
  conservative geometry heuristic, not medical-image segmentation. Angle,
  size, offset, tone and opacity vary per page without a white backing strip.
  Persian identity lines use bundled B Nazanin Bold.
- Every page derives an opaque 40-bit page token from the server-only HMAC key.
  Two independently positioned, secret-keyed 120-symbol constellations carry a
  repetition-3 error-correcting representation across the page. Symbols are
  raster pixels, not removable PDF annotations, text, Form/XObjects or a
  detachable security stream.
- An opaque `DFP-*` metadata ID is supplementary evidence only. It contains no
  PII and can never establish attribution by itself.
- The final file cannot be turned back into a clean original by keeping an
  original `/Contents` stream and deleting added streams: no original page
  stream exists in the edition. Deleting the sole page image destroys the page
  rather than revealing a clean source layer.

Rendering uses PyMuPDF. Four-page batches bound resident memory; production
qpdf merges and validates them. A pikepdf merge is a development fallback when
qpdf is unavailable, not a second production service. Page count and rotation
are preserved. The adaptive profile is 180 dpi for short documents, 170 dpi
above 20 pages, 165 dpi above 60 pages and 150 dpi/quality 78 above 120 pages.
Text/sparse pages use PNG for legibility; image-heavy pages use bounded-quality
JPEG. Output above 49 MiB fails closed before Telegram upload.

The previous v8 mixed-fixture capacity measurements remain the production
resource baseline because v9 does not change the renderer:

| Pages | DPI / JPEG | Wall time | Peak RSS | Output |
|---:|---:|---:|---:|---:|
| 10 | 180 / 88 | 4.88 s | 109 MiB | 4.70 MB |
| 50 | 170 / 86 | 22.84 s | 107 MiB | 22.65 MB |
| 150 | 150 / 78 | 61.77 s | 102 MiB | 48.98 MB |

The high-frequency mixed fixture is intentionally harder to compress than a
typical text booklet. A 1 GB profile retains one worker and a 300-second
deadline; changing worker count or raster defaults requires another constrained
benchmark.

## Forensic detector and honest limits

The owner-only detector is outside the delivery path. With the original source
page it performs resize normalization, sparse-page handling, deskew,
perspective correction and page alignment, then reports every channel
independently: recovered symbols, ECC status, candidate issuance, confidence
and evidence. The expected-issuance verifier returns an explicit
`detected/confidence` result plus per-page scores and requires valid ECC. v9 can
correct at most three source-bit errors against the HMAC-known 40-bit codeword;
partial scores beyond that bound remain non-definitive. Clean page, JPEG Q60,
grayscale, 70% downscale, screenshot resample, slight blur, 4% edge crop,
slight affine rotation and simulated print→scan passed all four audited page
profiles. Moderate 10% crop remained inconclusive on the CT and MRI fixtures
and is reported honestly rather than promoted to attribution.

v1–v8 decoders remain available for historical issuances. v9 does not claim to
be non-removable, AI-proof or 100% traceable. Its goal is to remove the trivial
PDF-object deletion path, materially raise removal cost and retain attribution
through common transformations.

## Recovery and release acceptance

DPAPI-encrypted laptop backups contain SQLite, the HMAC key and root-only source
configuration, with checksum, JSON and SQLite restore verification. Transient
PDF bytes are excluded; the private channel remains the source-file authority.

A release requires: local compile/tests; audited sample visual review;
`pdftotext`/structure/attack suite; a pre-deploy restore-verified laptop backup;
service restart with process CWD matching the active release; a real owner v9
issuance; protected cached resend without regeneration; qpdf validation; empty
temporary root; and a final restore-verified laptop backup.
