from __future__ import annotations

import argparse
import hashlib
import json
import math
import os
import shutil
import sys
import tempfile
import threading
import time
from pathlib import Path

try:
    import resource
except ImportError:  # pragma: no cover - production benchmark runs on Linux
    resource = None


ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from dent_bot.pdf_fingerprint import (  # noqa: E402
    WATERMARK_VERSION,
    WatermarkIdentity,
    derive_fingerprint_material,
    file_sha256,
    watermark_pdf,
)
from dent_bot.protected_media import DeliveryJob, ProtectedMediaDispatcher  # noqa: E402
from dent_bot.state import BotState  # noqa: E402


SECRET = b"benchmark-only-key-material-32b!"
SOURCE_CHAT_ID = -1001234567890


def _font() -> Path:
    configured = os.getenv("DENT_BOT_BOOKLET_WATERMARK_FONT", "").strip()
    candidates = (
        *([Path(configured)] if configured else []),
        ROOT / "dent_bot" / "assets" / "fonts" / "B_Nazanin_Bold.ttf",
        Path("/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"),
        Path("C:/Windows/Fonts/tahoma.ttf"),
        Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
    )
    for candidate in candidates:
        if candidate.is_file():
            return candidate
    raise RuntimeError("No benchmark font is available")


def _max_rss_mib() -> float:
    if resource is None:
        try:
            import psutil

            return float(psutil.Process().memory_info().rss) / (1024.0 * 1024.0)
        except (ImportError, OSError):
            return 0.0
    value = float(resource.getrusage(resource.RUSAGE_SELF).ru_maxrss)
    # Linux reports KiB; macOS reports bytes. The production benchmark is Linux.
    return value / (1024.0 * 1024.0) if sys.platform == "darwin" else value / 1024.0


def _percentile(values: list[float], fraction: float) -> float:
    if not values:
        return 0.0
    ordered = sorted(values)
    index = max(0, min(len(ordered) - 1, math.ceil(len(ordered) * fraction) - 1))
    return ordered[index]


def _directory_bytes(root: Path) -> int:
    total = 0
    for path in root.rglob("*") if root.exists() else ():
        try:
            if path.is_file():
                total += path.stat().st_size
        except OSError:
            continue
    return total


class DirectoryPeakSampler:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.peak = _directory_bytes(root)
        self._stop = threading.Event()
        self._thread = threading.Thread(target=self._run, daemon=True)

    def _run(self) -> None:
        while not self._stop.wait(0.02):
            self.peak = max(self.peak, _directory_bytes(self.root))

    def __enter__(self):
        self._thread.start()
        return self

    def __exit__(self, *_args) -> None:
        self._stop.set()
        self._thread.join(timeout=1)
        self.peak = max(self.peak, _directory_bytes(self.root))


class RssPeakSampler:
    """Cross-platform peak RSS sampler for the threaded load benchmark."""

    def __init__(self) -> None:
        self.peak_mib = _max_rss_mib()
        self._stop = threading.Event()
        self._thread = threading.Thread(target=self._run, daemon=True)

    def _run(self) -> None:
        while not self._stop.wait(0.02):
            self.peak_mib = max(self.peak_mib, _max_rss_mib())

    def __enter__(self):
        self._thread.start()
        return self

    def __exit__(self, *_args) -> None:
        self._stop.set()
        self._thread.join(timeout=1)
        self.peak_mib = max(self.peak_mib, _max_rss_mib())


def make_fixture(path: Path, *, pages: int, profile: str) -> dict[str, object]:
    import pymupdf

    path.parent.mkdir(parents=True, exist_ok=True)
    document = pymupdf.open()
    image_xref = 0
    image_bytes = b""
    if profile in {"mixed", "image"}:
        # A deterministic, high-detail pixmap produces a representative compressed
        # image stream without adding Pillow to the production dependency set.
        width, height = 900, 1200
        samples = bytearray(width * height * 3)
        for y in range(height):
            for x in range(width):
                offset = (y * width + x) * 3
                samples[offset] = (x * 17 + y * 3) & 255
                samples[offset + 1] = (x * 5 + y * 11) & 255
                samples[offset + 2] = ((x ^ y) * 13) & 255
        pixmap = pymupdf.Pixmap(pymupdf.csRGB, width, height, bytes(samples), False)
        image_bytes = pixmap.tobytes("jpeg", jpg_quality=82)

    for page_index in range(pages):
        page = document.new_page(width=595, height=842)
        mode = page_index % 4 if profile == "mixed" else {"text": 0, "table": 1, "image": 2, "white": 3}[profile]
        if mode == 0:
            page.insert_text((52, 62), f"Benchmark text page {page_index + 1}", fontsize=18)
            for row in range(34):
                page.insert_text((52, 95 + row * 19), f"Clinical reference line {row + 1}: deterministic content for PDF profiling.", fontsize=9)
        elif mode == 1:
            page.insert_text((52, 62), f"Benchmark table page {page_index + 1}", fontsize=18)
            for row in range(18):
                y = 95 + row * 34
                page.draw_line((52, y), (543, y), color=(0.25, 0.3, 0.35), width=0.5)
            for column in range(6):
                x = 52 + column * 98.2
                page.draw_line((x, 95), (x, 673), color=(0.25, 0.3, 0.35), width=0.5)
            for row in range(17):
                for column in range(5):
                    page.insert_text((58 + column * 98.2, 116 + row * 34), f"R{row + 1} C{column + 1}", fontsize=8)
        elif mode == 2:
            rect = pymupdf.Rect(62, 82, 533, 710)
            if image_xref:
                page.insert_image(rect, xref=image_xref)
            else:
                image_xref = page.insert_image(rect, stream=image_bytes)
            page.insert_text((62, 748), f"Image-heavy reference page {page_index + 1}", fontsize=12)
        else:
            page.insert_text((52, 800), f"Intentionally sparse page {page_index + 1}", fontsize=7, color=(0.75, 0.75, 0.75))
    document.save(
        path,
        garbage=4,
        deflate=True,
        use_objstms=1,
        no_new_id=True,
        reproducible=True,
    )
    document.close()
    return {"path": str(path), "pages": pages, "profile": profile, "bytes": path.stat().st_size}


def benchmark_watermark(
    input_path: Path,
    output_path: Path,
    *,
    qpdf: str,
    label: str,
    normalizer: str = "none",
) -> dict[str, object]:
    source_hash = file_sha256(input_path)
    material = derive_fingerprint_material(
        SECRET,
        issuance_id="iss_benchmarkwatermark0001",
        user_id=123456789,
        document_id="tgdoc_benchmark",
        source_hash=source_hash,
    )
    started_wall = time.perf_counter()
    started_cpu = time.process_time()
    report = watermark_pdf(
        input_path,
        output_path,
        identity=WatermarkIdentity("کاربر معیار", "0012345678", "09123456789"),
        material=material,
        font_path=_font(),
        qpdf_binary=qpdf,
        normalizer=normalizer,
    )
    wall = time.perf_counter() - started_wall
    cpu = time.process_time() - started_cpu
    source_bytes = input_path.stat().st_size
    output_bytes = output_path.stat().st_size
    return {
        "label": label,
        "scenario": "watermark",
        "wallSeconds": round(wall, 4),
        "cpuSeconds": round(cpu, 4),
        "cpuPercentOfOneCore": round(cpu / wall * 100.0, 1) if wall else 0.0,
        "peakRssMiB": round(_max_rss_mib(), 2),
        "inputBytes": source_bytes,
        "outputBytes": output_bytes,
        "sizeRatio": round(output_bytes / source_bytes, 4),
        "normalizer": normalizer,
        **report,
    }


class BenchmarkApi:
    def __init__(self, source_path: Path, started: dict[int, float], finished: dict[int, float]) -> None:
        self.source_path = source_path
        self.started = started
        self.finished = finished
        self.downloads = 0
        self.uploads = 0
        self.cache_sends = 0
        self.messages = 0
        self._lock = threading.Lock()

    def download_file(self, _file_id, destination, *, max_bytes):
        with self._lock:
            self.downloads += 1
        if self.source_path.stat().st_size > max_bytes:
            raise RuntimeError("fixture exceeds benchmark download limit")
        shutil.copyfile(self.source_path, destination)
        return {"bytes": destination.stat().st_size}

    def send_protected_document_path(self, chat_id, document_path, *, caption, filename):
        if not document_path.is_file() or not caption or not filename:
            raise RuntimeError("invalid benchmark upload")
        with self._lock:
            self.uploads += 1
            sequence = self.uploads
            self.finished[int(chat_id)] = time.perf_counter()
        return {
            "message_id": 1000 + sequence,
            "document": {"file_id": f"benchmark-personal-{chat_id}", "file_unique_id": f"benchmark-unique-{chat_id}"},
        }

    def send_protected_media(self, chat_id, _source, *, personalized_file_id=""):
        if not personalized_file_id:
            raise RuntimeError("benchmark cache send had no file_id")
        with self._lock:
            self.cache_sends += 1
            sequence = self.cache_sends
            self.finished[int(chat_id)] = time.perf_counter()
        return {"message_id": 2000 + sequence}

    def send(self, _chat_id, _text, _keyboard):
        with self._lock:
            self.messages += 1
        return {"message_id": 3000 + self.messages}


class TimedDispatcher(ProtectedMediaDispatcher):
    def __init__(self, *args, job_started: dict[int, float], **kwargs):
        self._benchmark_job_started = job_started
        super().__init__(*args, **kwargs)

    def _send_pdf(self, job: DeliveryJob, source: dict) -> dict:
        self._benchmark_job_started[int(job.user_id)] = time.perf_counter()
        return super()._send_pdf(job, source)


def _register_source(state: BotState, message_id: int, unique: str) -> int:
    state.replace_protected_media_message(SOURCE_CHAT_ID, message_id, [{
        "contentKind": "booklet",
        "courseCode": "ENT",
        "courseName": "ENT benchmark",
        "courseTag": "ent",
        "term": 7,
        "sessionNo": min(40, message_id),
        "telegramMethod": "sendDocument",
        "fileId": f"source-file-{unique}",
        "fileUniqueId": unique,
        "fileName": "fixture.pdf",
        "mimeType": "application/pdf",
        "caption": "benchmark",
    }])
    return int(state.protected_media_for(
        course_code="ENT", term=7, session_no=min(40, message_id), content_kind="booklet"
    )[-1]["id"])


def benchmark_load(
    input_path: Path,
    *,
    scenario: str,
    requests: int,
    workers: int,
    queue_size: int,
    qpdf: str,
    label: str,
    normalizer: str = "none",
) -> dict[str, object]:
    with tempfile.TemporaryDirectory(prefix="dent-booklet-load-") as directory:
        root = Path(directory)
        state = BotState(root / "state.sqlite3")
        dispatcher = None
        queued_at: dict[int, float] = {}
        started_at: dict[int, float] = {}
        finished_at: dict[int, float] = {}
        try:
            shared_source_id = _register_source(state, 1, "benchmark-shared-document")
            source_ids = [shared_source_id]
            if scenario == "distinct-documents":
                source_ids = [
                    _register_source(state, index + 1, f"benchmark-document-{index + 1}")
                    for index in range(requests)
                ]
            api = BenchmarkApi(input_path, started_at, finished_at)
            dispatcher = TimedDispatcher(
                api=api,
                state=state,
                authorize=lambda _user, _source: True,
                identity_provider=lambda _user: {"identity": {
                    "fullName": "کاربر معیار",
                    "nationalCode": "0012345678",
                    "phoneNumber": "09123456789",
                }},
                fingerprint_key=SECRET,
                watermark_font=_font(),
                temp_root=root / "jobs",
                qpdf_binary=qpdf,
                workers=workers,
                max_queue=queue_size,
                pdf_normalizer=normalizer,
                job_started=started_at,
            )
            users = [500000 + index for index in range(requests)]
            if scenario == "same-document":
                users = [500000] * requests
            if scenario == "cache-hit":
                source_hash = file_sha256(input_path)
                source = state.protected_media_source(shared_source_id)
                assert source is not None
                document_id = dispatcher._document_id(source)
                for user_id in users:
                    issuance_id = f"iss_benchmarkcache{user_id:012d}"
                    material = derive_fingerprint_material(
                        SECRET,
                        issuance_id=issuance_id,
                        user_id=user_id,
                        document_id=document_id,
                        source_hash=source_hash,
                    )
                    state.create_booklet_issuance(
                        issuance_id=issuance_id,
                        user_id=user_id,
                        source_id=shared_source_id,
                        document_id=document_id,
                        trace_code=material.trace_code,
                        fingerprint_hash=material.fingerprint_hash,
                        watermark_version=WATERMARK_VERSION,
                        source_hash=source_hash,
                    )
                    state.mark_booklet_issuance_sent(issuance_id, telegram_file_id=f"cached-{user_id}")

            started_wall = time.perf_counter()
            started_cpu = time.process_time()
            statuses: list[str] = []
            with DirectoryPeakSampler(root) as disk_sampler, RssPeakSampler() as rss_sampler:
                for index, user_id in enumerate(users):
                    source_id = source_ids[index] if scenario == "distinct-documents" else shared_source_id
                    queued_at.setdefault(user_id, time.perf_counter())
                    statuses.append(dispatcher.enqueue(user_id, source_id))
                dispatcher.queue.join()
            wall = time.perf_counter() - started_wall
            cpu = time.process_time() - started_cpu
            rows = state.connection.execute(
                "SELECT status,COUNT(*) FROM protected_media_deliveries GROUP BY status"
            ).fetchall()
            deliveries = {str(status): int(count) for status, count in rows}
            queue_delays = [started_at[user] - queued_at[user] for user in started_at if user in queued_at]
            latencies = [finished_at[user] - queued_at[user] for user in finished_at if user in queued_at]
            return {
                "label": label,
                "scenario": scenario,
                "requests": requests,
                "workers": workers,
                "queueSize": queue_size,
                "normalizer": normalizer,
                "statuses": {status: statuses.count(status) for status in sorted(set(statuses))},
                "deliveries": deliveries,
                "downloads": api.downloads,
                "uploads": api.uploads,
                "cacheSends": api.cache_sends,
                "wallSeconds": round(wall, 4),
                "cpuSeconds": round(cpu, 4),
                "cpuPercentOfOneCore": round(cpu / wall * 100.0, 1) if wall else 0.0,
                "peakRssMiB": round(rss_sampler.peak_mib, 2),
                "peakTemporaryDiskBytes": int(disk_sampler.peak),
                "queueDelayP50Seconds": round(_percentile(queue_delays, 0.50), 4),
                "queueDelayP95Seconds": round(_percentile(queue_delays, 0.95), 4),
                "latencyP50Seconds": round(_percentile(latencies, 0.50), 4),
                "latencyP95Seconds": round(_percentile(latencies, 0.95), 4),
                "failureRate": round(deliveries.get("failed", 0) / max(1, requests), 4),
                "orphanJobDirectories": len(list((root / "jobs").glob("job-*"))),
            }
        finally:
            if dispatcher is not None:
                dispatcher.close()
            state.close()


def main() -> int:
    parser = argparse.ArgumentParser(description="Repeatable protected-booklet PDF benchmark")
    subparsers = parser.add_subparsers(dest="command", required=True)
    fixture = subparsers.add_parser("fixture")
    fixture.add_argument("--output", required=True)
    fixture.add_argument("--pages", type=int, required=True)
    fixture.add_argument("--profile", choices=("mixed", "text", "table", "image", "white"), default="mixed")
    watermark = subparsers.add_parser("watermark")
    watermark.add_argument("--input", required=True)
    watermark.add_argument("--output", required=True)
    watermark.add_argument("--qpdf", default="")
    watermark.add_argument("--label", default="current")
    watermark.add_argument("--normalizer", choices=("none", "pikepdf"), default="none")
    load = subparsers.add_parser("load")
    load.add_argument("--input", required=True)
    load.add_argument("--scenario", choices=("cache-hit", "same-document", "distinct-documents"), required=True)
    load.add_argument("--requests", type=int, default=20)
    load.add_argument("--workers", type=int, default=1)
    load.add_argument("--queue-size", type=int, default=24)
    load.add_argument("--qpdf", default="")
    load.add_argument("--label", default="current")
    load.add_argument("--normalizer", choices=("none", "pikepdf"), default="none")
    args = parser.parse_args()

    if args.command == "fixture":
        result = make_fixture(Path(args.output), pages=max(1, min(args.pages, 300)), profile=args.profile)
    elif args.command == "watermark":
        result = benchmark_watermark(
            Path(args.input), Path(args.output), qpdf=args.qpdf,
            label=args.label, normalizer=args.normalizer,
        )
    else:
        result = benchmark_load(
            Path(args.input),
            scenario=args.scenario,
            requests=max(1, min(args.requests, 100)),
            workers=max(1, args.workers),
            queue_size=max(4, args.queue_size),
            qpdf=args.qpdf,
            label=args.label,
            normalizer=args.normalizer,
        )
    print(json.dumps(result, ensure_ascii=False, sort_keys=True, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
