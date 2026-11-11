"""Read-only adapter: bind a v3 candidate to a REAL protected provenance audit.

The real independent audit (`audit_private_study_batches.py`, format
`fanoos.classification.provenance-audit/1`) records, per batch, the pinned
study/validated/decisions SHA-256s, `mapped[<question number>] = {ref: "<edition>#<chapter>", pdf_page}`
and `source_pdf_provenance[ref].sha256`. It has NO auditor-name or quote-hash
field, so a self-declared `auditor` string (the old v3 packet field) proves
nothing. This adapter therefore never trusts JSON claims about who audited;
it checks properties the producing worker cannot forge:

* the audit file lives in a protected `classification/reports` directory under
  the research root, is a regular non-symlink file, and is NOT owned by the
  producing worker's uid (operator files appear as a different owner);
* when an applied receipt is given, it is a `fanoos.source-only-receipt/1`
  whose `audit_sha256` equals the audit file digest and whose
  questions/answers/choices change counts are all zero;
* the candidate's (question number, edition ref, pdf_page, pdf_sha256) equals
  the audit's mapped row and edition provenance.

It never writes, imports or touches the DB. Passing it is NOT a publication or
import authorisation: the source-only importer still needs fanoosupd, a fresh
(<=4 h) verified full backup, a live re-export, dry run and independent readback.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import stat
from pathlib import Path

AUDIT_FORMAT = "fanoos.classification.provenance-audit/1"
RECEIPT_FORMAT = "fanoos.source-only-receipt/1"
RESEARCH_ROOT = Path("/srv/fanoos/shared/research")
HEX64 = re.compile(r"^[0-9a-f]{64}$")


def _sha(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def _protected_file(path: Path, root: Path, producer_uid: int, fail: list, tag: str):
    if path.is_symlink():
        fail.append(f"{tag}_symlink"); return None
    try:
        real = path.resolve(strict=True)
        st = real.lstat()
    except OSError:
        fail.append(f"{tag}_missing"); return None
    if not stat.S_ISREG(st.st_mode):
        fail.append(f"{tag}_not_regular"); return None
    if real.parent.name != "reports" or real.parent.parent.name != "classification" \
            or root.resolve() not in real.parents:
        fail.append(f"{tag}_outside_protected_reports")
    if st.st_uid == producer_uid:
        fail.append(f"{tag}_owned_by_producer")      # self-certified file
    return real


def load_real_audit(path: Path, root: Path = RESEARCH_ROOT,
                    producer_uid: int | None = None) -> tuple[dict | None, str | None, list]:
    """Return (audit, sha256, failures). Schema + location + ownership only."""
    fail: list = []
    uid = os.geteuid() if producer_uid is None else producer_uid
    real = _protected_file(Path(path), root, uid, fail, "audit")
    if real is None:
        return None, None, fail
    try:
        audit = json.loads(real.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return None, None, fail + ["audit_unparseable"]
    if audit.get("format") != AUDIT_FORMAT or audit.get("research_only") is not True:
        fail.append("audit_format")
    batches = audit.get("batches")
    if not isinstance(batches, list) or not batches:
        fail.append("audit_no_batches")
    else:
        for b in batches:
            if not all(HEX64.match(str(b.get(k, ""))) for k in
                       ("study_sha256", "validated_sha256", "decisions_sha256")):
                fail.append("audit_batch_digest"); break
            if b.get("accepted", -1) + b.get("pending", -1) != b.get("questions"):
                fail.append("audit_batch_counts"); break
            if not isinstance(b.get("mapped"), dict):
                fail.append("audit_batch_mapped"); break
    return audit, _sha(real), fail


def check_applied_receipt(path: Path, audit_sha: str, root: Path = RESEARCH_ROOT,
                          producer_uid: int | None = None) -> list:
    fail: list = []
    uid = os.geteuid() if producer_uid is None else producer_uid
    real = _protected_file(Path(path), root, uid, fail, "receipt")
    if real is None:
        return fail
    try:
        r = json.loads(real.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return fail + ["receipt_unparseable"]
    if r.get("format") != RECEIPT_FORMAT:
        fail.append("receipt_format")
    if r.get("audit_sha256") != audit_sha:
        fail.append("receipt_audit_sha_mismatch")
    if any(r.get(k) != 0 for k in ("questions_changed", "answers_changed", "choices_changed")):
        fail.append("receipt_content_changed")
    if r.get("status") not in (None, "applied_verified"):
        fail.append("receipt_not_applied_verified")   # e.g. manual_reconciliation_required
    return fail


def match_candidate(audit: dict, batch: str, number: int, ref: str, pdf_page: int,
                    pdf_sha256: str) -> list:
    """Candidate must equal the audit's own mapped row; nothing is inferred."""
    rows = [b for b in audit.get("batches", []) if b.get("batch") == batch]
    if len(rows) != 1:
        return ["audit_batch_not_found"]
    b = rows[0]
    m = b.get("mapped", {}).get(str(number))
    if not isinstance(m, dict):
        return ["audit_question_not_mapped"]
    fail = []
    if m.get("ref") != ref:
        fail.append("audit_ref_mismatch")
    if m.get("pdf_page") != pdf_page or not isinstance(pdf_page, int) or pdf_page <= 0:
        fail.append("audit_pdf_page_mismatch")
    # Real audits key refs as "<edition>#<chapter node>"; provenance is per edition.
    prov = b.get("source_pdf_provenance", {}).get(str(ref).split("#", 1)[0], {})
    if not HEX64.match(str(pdf_sha256)) or prov.get("sha256") != pdf_sha256:
        fail.append("audit_pdf_sha_mismatch")
    return fail


def verify(audit_path: Path, batch: str, number: int, ref: str, pdf_page: int, pdf_sha256: str,
           receipt_path: Path | None = None, root: Path = RESEARCH_ROOT,
           producer_uid: int | None = None) -> dict:
    audit, sha, fail = load_real_audit(audit_path, root, producer_uid)
    if audit is not None and not fail:
        fail += match_candidate(audit, batch, number, ref, pdf_page, pdf_sha256)
    if receipt_path is not None and sha:
        fail += check_applied_receipt(receipt_path, sha, root, producer_uid)
    status = "independently_audited" if not fail else "pending:" + ",".join(sorted(set(fail)))
    if not fail and receipt_path is not None:
        status = "independently_audited_and_receipted"
    return {"status": status, "audit_sha256": sha, "failures": sorted(set(fail)),
            "import_authorised": False}   # never: importer gates run on the host operator
