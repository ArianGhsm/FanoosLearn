#!/usr/bin/env python3
"""Build a private page-text index directly from one verified server PDF.

Read-only against MySQL and the immutable private PDF. Writes only under the
operation-specific private research temp directory; no PDF copies, no public exports.
The index is a regenerable search/validation cache, never a source independent
of the exact PDF and SHA-256 in its provenance receipt. Use --apply after
reviewing the read-only report and disk space. A PDF whose pages are not
text-searchable is explicitly rejected, not OCR-invented.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
EDITION = re.compile(r"^[a-z0-9][a-z0-9-]*@[a-z0-9]+$")


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
    with resolved.open("rb") as f:
        if not f.read(5) == b"%PDF-":
            raise ValueError("stored source is not a PDF")
        f.seek(0)
        while block := f.read(1024 * 1024):
            digest.update(block)
    if digest.hexdigest() != expected_hash:
        raise ValueError("reference SHA-256 does not match the verified database object")
    return resolved


def chapter_coverage(mapping: dict, edition: str, pages: int) -> dict:
    runs = mapping.get("editions", {}).get(edition, {}).get("runs", [])
    if not runs:
        # A new verified PDF can be extracted before its chapter map is built.
        # It remains ineligible for classification until that map is complete.
        return {"mapped_chapters": 0, "mapped_pages": 0}
    previous_end = 0
    chapters = set()
    for item in runs:
        if len(item) != 3:
            raise ValueError("invalid chapter map tuple")
        chapter, first, last = item
        if not isinstance(first, int) or not isinstance(last, int) or first != previous_end + 1 or last < first:
            raise ValueError("chapter page map has gap/overlap/out-of-order range")
        previous_end = last
        if chapter is not None:
            chapters.add(str(chapter))
    if previous_end != pages or not chapters:
        raise ValueError("chapter map does not cover every PDF page")
    return {"mapped_chapters": len(chapters), "mapped_pages": previous_end}


def page_texts(source: Path, min_ratio: float) -> list[str]:
    info = subprocess.run(["pdfinfo", str(source)], capture_output=True, check=True, timeout=40)
    m = re.search(r"^Pages:\s*(\d+)\s*$", info.stdout.decode("utf-8", "replace"), re.M)
    if not m:
        raise ValueError("PDF page count not available")
    count = int(m.group(1))
    if count < 1 or count > 6000:
        raise ValueError("unreasonable PDF page count")
    extracted = subprocess.run(["pdftotext", "-enc", "UTF-8", "-layout", str(source), "-"],
                               capture_output=True, check=True, timeout=480)
    parts = extracted.stdout.decode("utf-8", "replace").split("\f")
    if parts and not parts[-1].strip():
        parts.pop()
    if len(parts) != count:
        raise ValueError(f"expected {count} pages; text extractor produced {len(parts)}")
    usable = sum(len(re.sub(r"\s", "", p)) >= 40 for p in parts)
    ratio = usable / count
    if ratio < min_ratio:
        raise ValueError(f"book is not fully searchable: {usable}/{count} usable pages")
    return [p.strip() for p in parts]


def pdf_bookmarks(source: Path, page_count: int) -> list[list]:
    """Read the original PDF outline in place when PyMuPDF is available."""
    try:
        import pymupdf
    except ImportError:
        return []
    with pymupdf.open(source) as pdf:
        if pdf.page_count != page_count:
            raise ValueError("PDF outline page count differs from extracted page count")
        return [
            [int(level), str(title), int(page)]
            for level, title, page in pdf.get_toc()
            if isinstance(level, int) and isinstance(title, str)
            and isinstance(page, int) and 1 <= page <= page_count
        ]


def create_text(edition: str, pages: list[str], source_hash: str) -> str:
    header = (f"# FANOOS reference text\n# edition: {edition}\n"
              f"# verified_source_sha256: {source_hash}\n# pages: {len(pages)}\n")
    return header + "".join(f"\n=== PAGE {i} ===\n{body}\n" for i, body in enumerate(pages, 1))


def validate_existing_index(target: Path, receipt_path: Path, edition: str,
                            source_hash: str, generated_text: str, page_count: int) -> bool:
    """Refuse stale, detached or hand-edited page text; return whether it exists."""
    if target.is_symlink() or receipt_path.is_symlink():
        raise ValueError("refusing a symbolic-link reference index or provenance receipt")
    if not target.exists():
        if receipt_path.exists():
            raise ValueError("orphaned provenance receipt exists without its PDF-derived index")
        return False

    current_bytes = target.read_bytes()
    current_hash = hashlib.sha256(current_bytes).hexdigest()
    expected_hash = hashlib.sha256(generated_text.encode("utf-8")).hexdigest()
    if current_hash != expected_hash:
        raise ValueError("existing edition index differs from the current verified PDF; investigate before replacement")
    if not receipt_path.is_file():
        raise ValueError("existing index has no provenance receipt tying it to a verified PDF")

    receipt = json.loads(receipt_path.read_text(encoding="utf-8"))
    if (receipt.get("format") != "fanoos.server-reference-extraction.v1"
            or receipt.get("edition") != edition
            or receipt.get("source_sha256") != source_hash
            or receipt.get("text_sha256") != current_hash
            or receipt.get("pdf_pages") != page_count
            or receipt.get("status") != "applied"):
        raise ValueError("existing index provenance does not match the current verified PDF")
    return True


def atomic_text(path: Path, body: str) -> None:
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    fd, tmp = tempfile.mkstemp(prefix=".fanoos-extract-", dir=str(path.parent))
    try:
        with os.fdopen(fd, "w", encoding="utf-8", newline="\n") as stream:
            os.fchmod(stream.fileno(), 0o600)
            stream.write(body)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(tmp, path)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("--edition", required=True)
    parser.add_argument("--local", type=Path, required=True,
                        help="operation-specific private temp directory under protected research/tmp")
    parser.add_argument("--storage-root", type=Path, default=Path("/srv/fanoos/shared/storage"))
    parser.add_argument("--mysql-defaults", type=Path, default=Path("/etc/fanoos/mysql-migrator.cnf"))
    parser.add_argument("--database", default="fanoos_prod")
    parser.add_argument("--apply", action="store_true", help="write temporary page text after all checks; default dry run")
    parser.add_argument("--min-ratio", type=float, default=0.80)
    args = parser.parse_args()

    if not (0.5 <= args.min_ratio <= 1):
        raise ValueError("invalid minimum searchable-page ratio")
    storage_key, sha, byte_size = verified_row(args.edition, args.mysql_defaults, args.database)
    book = verify_file(args.storage_root, storage_key, sha, byte_size)
    pages = page_texts(book, args.min_ratio)
    bookmarks = pdf_bookmarks(book, len(pages))
    mapping = json.loads((REPO / "data/bank/reference-chapter-pages.json").read_text(encoding="utf-8"))
    coverage = chapter_coverage(mapping, args.edition, len(pages))
    content = create_text(args.edition, pages, sha)
    target = args.local / "references" / f"{args.edition}.txt"
    receipt_path = target.with_suffix(".provenance.json")
    already_current = validate_existing_index(target, receipt_path, args.edition, sha, content, len(pages))
    details = {"format": "fanoos.server-reference-extraction.v1", "edition": args.edition,
               "source_sha256": sha, "source_bytes": byte_size,
               "pdf_pages": len(pages), **coverage,
               "pdf_bookmarks": bookmarks,
               "text_sha256": hashlib.sha256(content.encode("utf-8")).hexdigest(),
               "text_bytes": len(content.encode("utf-8")),
               "status": "applied" if args.apply else ("current" if already_current else "checked_only")}
    if args.apply:
        if not already_current:
            atomic_text(target, content)
        atomic_text(receipt_path, json.dumps(details, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps(details, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, RuntimeError, OSError, subprocess.SubprocessError) as error:
        print(f"REFERENCE EXTRACTION FAILED: {error}", file=sys.stderr)
        raise SystemExit(1)
