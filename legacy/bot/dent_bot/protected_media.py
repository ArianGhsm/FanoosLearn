from __future__ import annotations

import hashlib
import html
import logging
import os
import queue
import re
import secrets
import shutil
import tempfile
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Callable

from .api import BotApiError
from .pdf_fingerprint import (
    MAX_CLOUD_BOT_DOWNLOAD_BYTES,
    WATERMARK_VERSION,
    PdfFingerprintError,
    WatermarkIdentity,
    derive_fingerprint_material,
    file_sha256,
    watermark_pdf,
)
from .state import BotState


def _error(message: str) -> str:
    return f"<b>⚠️ انجام نشد</b>\n\n{html.escape(message)}"


@dataclass(frozen=True)
class DeliveryJob:
    user_id: int
    source_id: int
    document_id: str
    enqueued_monotonic: float


class ProtectedMediaDispatcher:
    """Bounded Telegram delivery and recipient-fingerprinting queue.

    Non-PDF media stays server-side at Telegram. A PDF is downloaded only into
    one private job directory, personalized by secure page raster burn-in, uploaded with
    protect_content, cached by Telegram file_id and removed in every exit path.
    The VPS never keeps source or personalized PDF bytes after the job.
    """

    def __init__(
        self,
        *,
        api,
        state: BotState,
        authorize: Callable[[int, dict], bool],
        identity_provider: Callable[[int], dict] | None = None,
        fingerprint_key: bytes = b"",
        watermark_font: Path = Path(""),
        temp_root: Path = Path("/var/lib/integrated-dent/dent-bot/tmp/booklets"),
        qpdf_binary: str = "qpdf",
        max_download_bytes: int = MAX_CLOUD_BOT_DOWNLOAD_BYTES,
        workers: int = 1,
        max_queue: int = 24,
        pdf_normalizer: str = "none",
        raster_dpi: int = 180,
        raster_jpeg_quality: int = 88,
        max_output_bytes: int = 49 * 1024 * 1024,
        processing_timeout_seconds: int = 300,
        orphan_max_age_seconds: int = 3600,
        rate_window_seconds: int = 60,
        rate_max_requests: int = 12,
        same_document_cooldown_seconds: int = 3,
    ) -> None:
        self.api = api
        self.state = state
        self.authorize = authorize
        self.identity_provider = identity_provider
        self.fingerprint_key = bytes(fingerprint_key)
        self.watermark_font = Path(watermark_font)
        self.temp_root = Path(temp_root)
        self.qpdf_binary = str(qpdf_binary)
        self.max_download_bytes = min(MAX_CLOUD_BOT_DOWNLOAD_BYTES, max(1024 * 1024, int(max_download_bytes)))
        self.pdf_normalizer = str(pdf_normalizer).lower()
        if self.pdf_normalizer not in {"none", "pikepdf"}:
            raise ValueError("Unsupported PDF normalizer")
        self.raster_dpi = max(144, min(int(raster_dpi), 220))
        self.raster_jpeg_quality = max(72, min(int(raster_jpeg_quality), 94))
        self.max_output_bytes = max(5 * 1024 * 1024, min(int(max_output_bytes), 49 * 1024 * 1024))
        self.processing_timeout_seconds = max(30, min(int(processing_timeout_seconds), 900))
        self.orphan_max_age_seconds = max(600, min(int(orphan_max_age_seconds), 86400))
        self.rate_window_seconds = max(30, min(int(rate_window_seconds), 3600))
        self.rate_max_requests = max(3, min(int(rate_max_requests), 100))
        self.same_document_cooldown_seconds = max(1, min(int(same_document_cooldown_seconds), 60))
        self.queue: queue.Queue[DeliveryJob | None] = queue.Queue(maxsize=max(20, min(max_queue, 500)))
        self._pending: set[tuple[int, str, str]] = set()
        self._active_job_dirs: set[Path] = set()
        self._lock = threading.Lock()
        self._last_cleanup_monotonic = 0.0
        self._stop_event = threading.Event()
        self._prepare_temp_root()
        recovered = self.state.recover_stale_booklet_issuances(self.orphan_max_age_seconds)
        if recovered:
            logging.warning("booklet stale issuances recovered count=%s", recovered)
        self._threads = [
            threading.Thread(target=self._run, name=f"dent-protected-media-{index + 1}", daemon=True)
            for index in range(max(1, min(workers, 8)))
        ]
        for thread in self._threads:
            thread.start()
        self._cleanup_thread = threading.Thread(
            target=self._cleanup_loop,
            name="dent-protected-media-cleanup",
            daemon=True,
        )
        self._cleanup_thread.start()

    def _prepare_temp_root(self) -> None:
        self.temp_root.mkdir(parents=True, exist_ok=True)
        try:
            os.chmod(self.temp_root, 0o700)
        except OSError:
            pass
        self.cleanup_orphaned_temp()

    def cleanup_orphaned_temp(self, *, now_epoch: float | None = None) -> int:
        """Remove only old, real job directories; never touch an active rollout."""
        cutoff = float(now_epoch if now_epoch is not None else time.time()) - self.orphan_max_age_seconds
        removed = 0
        with self._lock:
            active = set(self._active_job_dirs)
        for stale in self.temp_root.glob("job-*"):
            try:
                stat = stale.lstat()
                if stale in active or stale.is_symlink() or not stale.is_dir() or stat.st_mtime >= cutoff:
                    continue
                match = re.fullmatch(r"job-(\d+)-.+", stale.name)
                if match and os.name == "posix" and Path(f"/proc/{int(match.group(1))}").exists():
                    continue
                shutil.rmtree(stale)
                removed += 1
            except (FileNotFoundError, OSError):
                continue
        self._last_cleanup_monotonic = time.monotonic()
        if removed:
            logging.info("booklet orphan cleanup removed=%s", removed)
        return removed

    def _cleanup_loop(self) -> None:
        while not self._stop_event.wait(900):
            self.cleanup_orphaned_temp()

    def enqueue(self, user_id: int, source_id: int) -> str:
        user_id = int(user_id)
        source_id = int(source_id)
        source = self.state.protected_media_source(source_id)
        if source is None:
            return "missing"
        if not self.authorize(user_id, source):
            return "denied"
        document_id = self._document_id(source)
        key = (user_id, document_id, WATERMARK_VERSION)
        with self._lock:
            if key in self._pending:
                return "duplicate"
            if self.queue.full():
                return "full"
            claim = self.state.claim_booklet_request(
                user_id,
                document_id,
                now_epoch=int(time.time()),
                window_seconds=self.rate_window_seconds,
                max_requests=self.rate_max_requests,
                cooldown_seconds=self.same_document_cooldown_seconds,
            )
            if claim != "claimed":
                return claim
            try:
                self.queue.put_nowait(DeliveryJob(user_id, source_id, document_id, time.monotonic()))
            except queue.Full:
                return "full"
            self._pending.add(key)
        logging.info("booklet request queued queue_depth=%s", self.queue.qsize())
        return "queued"

    def _finish(self, job: DeliveryJob) -> None:
        with self._lock:
            self._pending.discard((job.user_id, job.document_id, WATERMARK_VERSION))
        self.queue.task_done()

    @staticmethod
    def _is_pdf(source: dict) -> bool:
        return (
            str(source.get("telegramMethod") or "") == "sendDocument"
            and (
                str(source.get("mimeType") or "").lower() == "application/pdf"
                or str(source.get("fileName") or "").lower().endswith(".pdf")
            )
        )

    @staticmethod
    def _document_id(source: dict) -> str:
        stable = str(source.get("fileUniqueId") or "").strip()
        if not stable:
            stable = f"{int(source.get('sourceChatId') or 0)}:{int(source.get('sourceMessageId') or 0)}"
        return "tgdoc_" + hashlib.sha256(stable.encode("utf-8")).hexdigest()[:32]

    @staticmethod
    def _telegram_document_ids(result: dict) -> tuple[str, str]:
        document = result.get("document") if isinstance(result.get("document"), dict) else {}
        return str(document.get("file_id") or ""), str(document.get("file_unique_id") or "")

    def _watermark_identity(self, user_id: int) -> WatermarkIdentity:
        if self.identity_provider is None:
            raise PdfFingerprintError("Watermark identity provider is unavailable")
        result = self.identity_provider(user_id)
        identity = result.get("identity") if isinstance(result, dict) else None
        if not isinstance(identity, dict):
            raise PdfFingerprintError("Canonical watermark identity is unavailable")
        return WatermarkIdentity(
            full_name=str(identity.get("fullName") or ""),
            national_code=str(identity.get("nationalCode") or ""),
            phone_number=str(identity.get("phoneNumber") or ""),
        ).validated()

    def _send_pdf(self, job: DeliveryJob, source: dict) -> dict:
        document_id = job.document_id
        cached = self.state.sent_booklet_issuance(job.user_id, job.source_id, document_id, WATERMARK_VERSION)
        if cached is not None:
            try:
                if not self.authorize(job.user_id, source):
                    raise PermissionError("Booklet entitlement was revoked before cached delivery")
                result = self.api.send_protected_media(
                    job.user_id, source, personalized_file_id=str(cached.get("telegramFileId") or "")
                )
                logging.info(
                    "booklet cache hit queue_ms=%s",
                    int((time.monotonic() - job.enqueued_monotonic) * 1000),
                )
                return result
            except BotApiError as error:
                message = str(error).lower()
                invalid_file = not error.transient and any(
                    token in message for token in ("file identifier", "file_id", "wrong file")
                )
                if not invalid_file:
                    raise
                self.state.invalidate_booklet_issuance_file(str(cached["issuanceId"]))
                logging.warning("booklet cached Telegram file rejected; regenerating")
        source_file_id = str(source.get("fileId") or "")
        if not source_file_id:
            raise PdfFingerprintError("PDF source has no downloadable Telegram file identifier")
        identity = self._watermark_identity(job.user_id)
        job_dir = Path(tempfile.mkdtemp(prefix=f"job-{os.getpid()}-", dir=self.temp_root))
        with self._lock:
            self._active_job_dirs.add(job_dir)
        issuance: dict | None = None
        processing_claimed = False
        try:
            os.chmod(job_dir, 0o700)
            source_path = job_dir / "source.pdf"
            output_path = job_dir / "personalized.pdf"
            download = self.api.download_file(source_file_id, source_path, max_bytes=self.max_download_bytes)
            os.chmod(source_path, 0o600)
            source_hash = str(download.get("sha256") or "") if isinstance(download, dict) else ""
            if len(source_hash) != 64:
                source_hash = file_sha256(source_path)
            existing = self.state.booklet_issuance_for_source_hash(
                job.user_id, job.source_id, document_id, source_hash, WATERMARK_VERSION
            )
            if existing is not None and str(existing.get("telegramFileId") or ""):
                if not self.authorize(job.user_id, source):
                    raise PermissionError("Booklet entitlement was revoked before cached delivery")
                return self.api.send_protected_media(
                    job.user_id, source, personalized_file_id=str(existing["telegramFileId"])
                )
            if existing is None:
                issuance_id = "iss_" + secrets.token_urlsafe(24)
                material = derive_fingerprint_material(
                    self.fingerprint_key,
                    issuance_id=issuance_id,
                    user_id=job.user_id,
                    document_id=document_id,
                    source_hash=source_hash,
                )
                issuance = self.state.create_booklet_issuance(
                    issuance_id=material.issuance_id,
                    user_id=job.user_id,
                    source_id=job.source_id,
                    document_id=document_id,
                    trace_code=material.trace_code,
                    fingerprint_hash=material.fingerprint_hash,
                    watermark_version=material.watermark_version,
                    source_hash=source_hash,
                )
            else:
                issuance = existing
                material = derive_fingerprint_material(
                    self.fingerprint_key,
                    issuance_id=str(issuance["issuanceId"]),
                    user_id=job.user_id,
                    document_id=document_id,
                    source_hash=source_hash,
                    watermark_version=str(issuance["watermarkVersion"]),
                )
            processing_claimed = self.state.mark_booklet_issuance_processing(str(issuance["issuanceId"]))
            if not processing_claimed:
                raise PdfFingerprintError("PDF issuance is already being processed")
            processing_started = time.monotonic()
            report = watermark_pdf(
                source_path,
                output_path,
                identity=identity,
                material=material,
                font_path=self.watermark_font,
                qpdf_binary=self.qpdf_binary,
                normalizer=self.pdf_normalizer,
                deadline_monotonic=processing_started + self.processing_timeout_seconds,
                raster_dpi=self.raster_dpi,
                raster_jpeg_quality=self.raster_jpeg_quality,
                max_output_bytes=self.max_output_bytes,
            )
            if not self.authorize(job.user_id, source):
                raise PermissionError("Booklet entitlement was revoked before final delivery")
            result = self.api.send_protected_document_path(
                job.user_id,
                output_path,
                caption=f"🔐 نسخهٔ شخصی‌سازی‌شده\nTrace Code: <code>{material.trace_code}</code>",
                filename="dent1402-personalized.pdf",
            )
            file_id, file_unique_id = self._telegram_document_ids(result)
            if not file_id:
                raise BotApiError("Personalized Telegram upload returned no reusable file identifier")
            self.state.complete_booklet_issuance(
                str(issuance["issuanceId"]),
                telegram_file_id=file_id,
                telegram_file_unique_id=file_unique_id,
            )
            logging.info(
                "booklet generated queue_ms=%s generation_ms=%s cpu_ms=%s peak_rss_bytes=%s temp_peak_bytes=%s input_bytes=%s output_bytes=%s pages=%s normalizer=%s",
                int((processing_started - job.enqueued_monotonic) * 1000),
                int((time.monotonic() - processing_started) * 1000),
                int(float(report.get("cpuSeconds") or 0.0) * 1000),
                int(report.get("peakRssBytes") or 0),
                int(report.get("temporaryPeakBytes") or 0),
                int(download.get("bytes") or source_path.stat().st_size) if isinstance(download, dict) else source_path.stat().st_size,
                int(report.get("bytes") or output_path.stat().st_size),
                int(report.get("pageCount") or 0),
                self.pdf_normalizer,
            )
            return result
        except Exception:
            if issuance is not None and processing_claimed:
                self.state.mark_booklet_issuance_failed(str(issuance.get("issuanceId") or ""))
            raise
        finally:
            shutil.rmtree(job_dir, ignore_errors=True)
            with self._lock:
                self._active_job_dirs.discard(job_dir)

    def _run(self) -> None:
        while True:
            job = self.queue.get()
            if job is None:
                self.queue.task_done()
                return
            try:
                source = self.state.protected_media_source(job.source_id)
                if source is None or not self.authorize(job.user_id, source):
                    self.state.record_protected_media_delivery(job.user_id, job.source_id, "denied")
                    self.api.send(
                        job.user_id,
                        _error("مجوز این درس یا ترم تأیید نشد؛ فایل ارسال نشد."),
                        {"inline_keyboard": [[{"text": "🏠 منوی اصلی", "callback_data": "v1:home"}]]},
                    )
                    continue
                if self._is_pdf(source):
                    result = self._send_pdf(job, source)
                else:
                    if not self.authorize(job.user_id, source):
                        raise PermissionError("Booklet entitlement was revoked before media delivery")
                    result = self.api.send_protected_media(job.user_id, source)
                message_id = int(result.get("message_id") or 0)
                if message_id <= 0:
                    raise BotApiError("Protected media delivery returned no message identifier")
                self.state.record_protected_media_delivery(job.user_id, job.source_id, "sent", message_id=message_id)
            except PermissionError:
                self.state.record_protected_media_delivery(job.user_id, job.source_id, "denied")
                try:
                    self.api.send(
                        job.user_id,
                        _error("دسترسی این ترم دیگر فعال نیست؛ فایل ارسال نشد."),
                        {"inline_keyboard": [[{"text": "🔒 وضعیت اشتراک", "callback_data": "v1:term-subscription:7"}]]},
                    )
                except BotApiError:
                    pass
            except Exception as error:
                self.state.record_protected_media_delivery(job.user_id, job.source_id, "failed")
                logging.warning("protected media delivery failed type=%s", type(error).__name__)
                try:
                    self.api.send(
                        job.user_id,
                        _error("صدور نسخهٔ شخصی انجام نشد؛ مشخصات کامل حساب یا فایل منبع را بررسی کن و دوباره تلاش کن."),
                        {"inline_keyboard": [[{"text": "🏠 منوی اصلی", "callback_data": "v1:home"}]]},
                    )
                except BotApiError:
                    pass
            finally:
                self._finish(job)

    def close(self) -> None:
        self._stop_event.set()
        for _thread in self._threads:
            self.queue.put(None)
        for thread in self._threads:
            thread.join(timeout=5)
        self._cleanup_thread.join(timeout=5)
