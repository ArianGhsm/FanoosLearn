#!/usr/bin/env python3
"""Read selected pages directly from the exact approved private server PDF.

Every page request invokes Poppler for that single PDF page and captures only
that page in memory. No PDF text file, whole-book text stream, OCR export,
search index or other text derivative is written. The caller must discard page
text after checking that page; durable records contain only PDF provenance,
page/chapter references and the short evidence quote needed for a decision.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path

EDITION = re.compile(r"^[a-z0-9][a-z0-9-]*@[a-z0-9]+$")
MIN_PAGE_TEXT_CHARS = 40


def verified_row(edition: str, mysql_defaults: Path, database: str) -> tuple[str, str, int]:
    if not EDITION.fullmatch(edition):
        raise ValueError("invalid edition key")
    if not mysql_defaults.is_file():
        raise ValueError("database credentials file not available")
    sql = f"""
SELECT o.storage_key, LOWER(HEX(o.checksum_sha256)), o.byte_size
FROM content_resources r
JOIN content_resource_metadata m ON m.resource_id=r.id AND m.workspace_id=r.workspace_id
JOIN content_resource_versions v ON v.resource_id=r.id AND v.workspace_id=r.workspace_id
  AND v.version_no=r.current_version_no
JOIN content_objects o ON o.id=v.object_id AND o.workspace_id=v.workspace_id
WHERE m.topic='{edition}' AND m.format_key='reference_pdf'
  AND m.access_level='private' AND r.lifecycle_status='published'
  AND r.deleted_at IS NULL AND r.archived_at IS NULL
  AND v.status='approved' AND o.status='verified'
  AND o.classification='private' AND o.detected_mime='application/pdf'
  AND o.deleted_at IS NULL
LIMIT 2
"""
    proc = subprocess.run(
        ["mysql", f"--defaults-extra-file={mysql_defaults}", "-N", "--batch", "--raw",
         "-D", database, "-e", sql], capture_output=True, check=False, timeout=30)
    if proc.returncode:
        raise RuntimeError("could not read approved reference metadata")
    lines = proc.stdout.decode("utf-8").strip().splitlines()
    if len(lines) != 1:
        raise ValueError("exactly one verified private reference required")
    fields = lines[0].split("\t")
    if len(fields) != 3 or not re.fullmatch(r"[a-f0-9]{64}", fields[1]):
        raise ValueError("invalid reference metadata")
    return fields[0], fields[1], int(fields[2])


def verify_file(root: Path, key: str, expected_hash: str, expected_size: int) -> Path:
    if not key.startswith("private/"):
        raise ValueError("reference is not in protected storage")
    base = root.resolve(strict=True)
    source = root / key
    resolved = source.resolve(strict=True)
    if not resolved.is_relative_to(base / "private") or not resolved.is_file():
        raise ValueError("reference location is outside protected object storage")
    if source.is_symlink() or resolved.stat().st_size != expected_size:
        raise ValueError("reference source is a symlink or size mismatch")
    digest = hashlib.sha256()
    with resolved.open("rb") as stream:
        if stream.read(5) != b"%PDF-":
            raise ValueError("stored source is not a PDF")
        stream.seek(0)
        while block := stream.read(1024 * 1024):
            digest.update(block)
    if digest.hexdigest() != expected_hash:
        raise ValueError("reference SHA-256 does not match the verified database object")
    return resolved


def pdf_page_count(source: Path) -> int:
    result = subprocess.run(
        ["pdfinfo", str(source)], capture_output=True, check=True, timeout=40)
    match = re.search(r"^Pages:\s*(\d+)\s*$", result.stdout.decode("utf-8", "replace"), re.M)
    if not match:
        raise ValueError("PDF page count not available")
    count = int(match.group(1))
    if count < 1 or count > 6000:
        raise ValueError("unreasonable PDF page count")
    return count


@dataclass(frozen=True)
class VerifiedReferencePdf:
    edition: str
    source: Path
    source_sha256: str
    source_bytes: int
    page_count: int

    def page_text(self, page_number: int) -> str:
        """Return text for one page only; stdout is never redirected to a file."""
        if isinstance(page_number, bool) or not 1 <= page_number <= self.page_count:
            raise ValueError("PDF page is outside the verified document")
        result = subprocess.run(
            ["pdftotext", "-enc", "UTF-8", "-layout", "-f", str(page_number),
             "-l", str(page_number), str(self.source), "-"],
            capture_output=True, check=True, timeout=60)
        # Poppler writes only the requested page to stdout, sometimes with a
        # trailing form feed. Keep that one page in memory for the caller.
        return result.stdout.decode("utf-8", "replace").strip("\f\r\n")

    def iter_page_texts(self):
        """Yield one page at a time; callers must not retain the yielded text."""
        for page_number in range(1, self.page_count + 1):
            yield page_number, self.page_text(page_number)

    def readable_page_count(self, min_chars: int = MIN_PAGE_TEXT_CHARS) -> int:
        readable = 0
        for _, text in self.iter_page_texts():
            if len(re.sub(r"\s", "", text)) >= min_chars:
                readable += 1
            del text
        return readable

    def bookmarks(self) -> list[list]:
        """Read the PDF's outline metadata without making a text derivative."""
        try:
            import pymupdf
        except ImportError:
            return []
        with pymupdf.open(self.source) as pdf:
            if pdf.page_count != self.page_count:
                raise ValueError("PDF outline page count differs from the verified PDF")
            return [
                [int(level), str(title), int(page)]
                for level, title, page in pdf.get_toc()
                if isinstance(level, int) and isinstance(title, str)
                and isinstance(page, int) and 1 <= page <= self.page_count
            ]


def open_verified_reference(edition: str,
                            storage_root: Path = Path("/srv/fanoos/shared/storage"),
                            mysql_defaults: Path = Path("/etc/fanoos/mysql-migrator.cnf"),
                            database: str = "fanoos_prod") -> VerifiedReferencePdf:
    key, expected_hash, expected_size = verified_row(edition, mysql_defaults, database)
    source = verify_file(storage_root, key, expected_hash, expected_size)
    return VerifiedReferencePdf(
        edition=edition,
        source=source,
        source_sha256=expected_hash,
        source_bytes=expected_size,
        page_count=pdf_page_count(source),
    )


def chapter_coverage(mapping: dict, edition: str, pages: int) -> dict:
    runs = mapping.get("editions", {}).get(edition, {}).get("runs", [])
    if not runs:
        return {"mapped_chapters": 0, "mapped_pages": 0}
    previous_end = 0
    chapters = set()
    for item in runs:
        if len(item) != 3:
            raise ValueError("invalid chapter map tuple")
        chapter, first, last = item
        if (not isinstance(first, int) or isinstance(first, bool)
                or not isinstance(last, int) or isinstance(last, bool)
                or first != previous_end + 1 or last < first):
            raise ValueError("chapter page map has gap/overlap/out-of-order range")
        previous_end = last
        if chapter is not None:
            chapters.add(str(chapter))
    if previous_end != pages or not chapters:
        raise ValueError("chapter map does not cover every PDF page")
    return {"mapped_chapters": len(chapters), "mapped_pages": previous_end}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("--edition", required=True)
    parser.add_argument("--storage-root", type=Path, default=Path("/srv/fanoos/shared/storage"))
    parser.add_argument("--mysql-defaults", type=Path, default=Path("/etc/fanoos/mysql-migrator.cnf"))
    parser.add_argument("--database", default="fanoos_prod")
    parser.add_argument("--check-readable", action="store_true",
                        help="scan each PDF page transiently in memory and report counts only")
    parser.add_argument("--min-ratio", type=float, default=0.80)
    args = parser.parse_args()
    if not 0.5 <= args.min_ratio <= 1:
        raise ValueError("invalid minimum searchable-page ratio")

    pdf = open_verified_reference(args.edition, args.storage_root, args.mysql_defaults, args.database)
    mapping = json.loads((Path(__file__).resolve().parents[2] /
                          "data/bank/reference-chapter-pages.json").read_text(encoding="utf-8"))
    map_entry = mapping.get("editions", {}).get(pdf.edition, {})
    try:
        coverage = chapter_coverage({"editions": {pdf.edition: map_entry}}, pdf.edition, pdf.page_count)
        chapter_map_valid = bool(map_entry.get("runs"))
        chapter_map_issue = None
    except ValueError as error:
        coverage = {"mapped_chapters": 0, "mapped_pages": 0}
        chapter_map_valid = False
        chapter_map_issue = str(error)
    result = {
        "format": "fanoos.verified-reference-pdf.v1",
        "edition": pdf.edition,
        "source_pdf_sha256": pdf.source_sha256,
        "source_pdf_bytes": pdf.source_bytes,
        "pdf_pages": pdf.page_count,
        **coverage,
        "chapter_map_valid": chapter_map_valid,
        "chapter_map_issue": chapter_map_issue,
        "pdf_bookmark_count": len(pdf.bookmarks()),
        "text_files_created": 0,
    }
    if args.check_readable:
        readable = pdf.readable_page_count()
        ratio = readable / pdf.page_count
        result.update({"readable_pages": readable, "readable_page_ratio": round(ratio, 6)})
        if ratio < args.min_ratio:
            raise ValueError(f"PDF text layer is incomplete: {readable}/{pdf.page_count} readable pages; no OCR/export was created")
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, RuntimeError, OSError, subprocess.SubprocessError) as error:
        print(f"VERIFIED PDF CHECK FAILED: {error}", file=sys.stderr)
        raise SystemExit(1)
