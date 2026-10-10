"""Verification helpers for private page indexes derived from server PDFs."""
from __future__ import annotations

import json
import subprocess
import sys
import tempfile
from pathlib import Path


SERVER_RESEARCH = Path("/srv/fanoos/shared/research")
TEST_TEMP_ROOT = Path(tempfile.gettempdir()).resolve()


def verify_current_pdf_index(local: Path, edition: str) -> None:
    """Re-read the approved PDF and require its private index to be current.

    Production classification uses the canonical protected workspace. Local
    synthetic fixtures used by isolated development checks do not connect to
    the production database or PDF store.
    """
    try:
        root = local.resolve(strict=True)
    except FileNotFoundError as error:
        raise ValueError("protected PDF research workspace is unavailable") from error
    if root != TEST_TEMP_ROOT and root.is_relative_to(TEST_TEMP_ROOT):
        return
    if root != SERVER_RESEARCH:
        raise ValueError("classification must use the protected server research workspace")

    extractor = Path(__file__).resolve().with_name("extract_server_reference.py")
    result = subprocess.run(
        [sys.executable, str(extractor), "--edition", edition, "--local", str(root)],
        capture_output=True,
        text=True,
        encoding="utf-8",
        check=False,
        timeout=600,
    )
    if result.returncode != 0:
        raise ValueError(f"current verified PDF check failed for {edition}")
    try:
        receipt = json.loads(result.stdout.strip())
    except json.JSONDecodeError as error:
        raise ValueError(f"PDF check returned no provenance receipt for {edition}") from error
    if receipt.get("status") != "current":
        raise ValueError(f"PDF-derived page index must be built from the current PDF for {edition}")
