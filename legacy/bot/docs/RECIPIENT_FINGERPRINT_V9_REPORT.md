# Recipient Fingerprint v9 — targeted placement and verifier audit

## Scope

`recipient-pdf-v9` is a targeted polish of v8, not a renderer rewrite. The
secure-raster page-at-a-time renderer, adaptive DPI/JPEG policy, four-page
batches, one-worker production queue, 49 MiB ceiling, temporary lifecycle,
Telegram `protect_content` sends and `file_id` cache are unchanged.

The release changes three things:

1. Each source image gets a conservative central 50% ROI rectangle. The single
   compact image trace is ranked among edge/corner candidates across all image
   boxes and is smaller/fainter than a normal compact trace.
2. Full-mark candidates are scored from source line boxes, headings, paragraph
   starts, image boxes and ROI boxes. True whitespace and page distribution are
   rewarded; key-text/ROI overlap is strongly penalized.
3. `verify_expected_secure_raster` verifies one expected issuance without
   enumerating unrelated recipients and reports `detected`, confidence and
   per-page channel/ECC scores. A v9 known-codeword correction is bounded to
   three errors in the HMAC-derived 40-bit page token. Partial evidence outside
   that bound is not definitive.

## Placement result

The deterministic 10-page audit fixture contains one central CT image on page
3, two MRI panels on page 4, dense text on pages 5/6/9 and a whitespace-heavy
page. The retired v8 center placement and v9 placement were evaluated on the
same source and issuance.

| Page | Type | v8 compact ROI overlap | v9 compact ROI overlap | v9 key-text overlap | Full / compact |
|---:|---|---:|---:|---:|---:|
| 3 | CT | 1.0000 | 0.0000 | 0.0000 | 3 / 1 |
| 4 | MRI pair | 0.9917 | 0.0000 | 0.0000 | 3 / 1 |
| 5 | text | 0.0000 | 0.0000 | 0.0000 | 4 / 2 |
| 6 | text | 0.0000 | 0.0000 | 0.0000 | 4 / 2 |
| 9 | text | 0.0000 | 0.0000 | 0.0000 | 4 / 2 |

Visual review with both Poppler and PyMuPDF confirmed that the CT/MRI center is
clear, the figure trace stays at a peripheral edge, headings/paragraph starts
remain readable and the deterrent count did not increase.

## Numeric transformation verifier

Scores below are the best ECC channel score for the expected issuance. `1.000`
means valid or bounded-corrected ECC; `detected=false` is retained where the
evidence was not sufficient for definitive attribution.

| Transform | CT | MRI | Text | Whitespace |
|---|---:|---:|---:|---:|
| original | 1.000 | 1.000 | 1.000 | 1.000 |
| JPEG Q60 | 1.000 | 1.000 | 1.000 | 1.000 |
| grayscale | 1.000 | 1.000 | 1.000 | 1.000 |
| downscale 70% | 1.000 | 1.000 | 1.000 | 1.000 |
| screenshot resample | 1.000 | 1.000 | 1.000 | 1.000 |
| slight blur | 1.000 | 1.000 | 1.000 | 1.000 |
| 4% edge crop | 1.000 | 1.000 | 1.000 | 1.000 |
| 10% moderate crop | 0.525 / no | 0.850 / no | 1.000 | 1.000 |
| slight affine | 1.000 | 1.000 | 1.000 | 1.000 |
| print-scan simulation | 1.000 | 1.000 | 1.000 | 1.000 |

The complete PDF verifier recovered valid ECC from 9 of 10 pages with 0.995
confidence. Four decoy checks scored 0.300–0.550 and none was detected.

## Structure and resource delta

The final fixture has 10 pages, exactly one content stream and one image per
page, no live text, annotations, forms/OCG, attachment, JavaScript or embedded
original. The same-run comparison changed only placement logic:

| Placement | Wall | CPU | Peak RSS | Output |
|---|---:|---:|---:|---:|
| retired v8 placement | 10.96 s | 10.34 s | 103.64 MiB | 2,106,242 B |
| v9 placement | 10.52 s | 10.06 s | 111.30 MiB | 2,124,708 B |

The output increase is 18,466 bytes (0.88%). The observed peak-RSS difference
is 7.7 MiB and remained far below the one-worker production bound. Wall/CPU did
not regress in the same run. These are fixture measurements, not throughput
guarantees.

## Remaining limits

The ROI is geometry-based and cannot identify a lesion or anatomy semantically.
Moderate crop already made the CT/MRI pages inconclusive, and harsher crop,
perspective, destructive diff masking or deliberate pixel replacement may
remove attribution. The system raises removal cost and supports evidence-based
attribution; it is not non-removable, AI-proof or 100% traceable.
