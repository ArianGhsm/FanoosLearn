from __future__ import annotations

import tempfile
import threading
import time
import unittest
import os
from pathlib import Path

from dent_bot.api import BaleBotApi, BotApiError, TelegramBotApi
from dent_bot.app import DentBotApp
from dent_bot.booklets import (
    COURSES,
    ENT_SESSIONS,
    RESOURCE_LABELS,
    course_button,
    ordinal,
    parse_session_number,
    parse_source_caption,
    session_button,
    source_records_from_channel_post,
)
from dent_bot.state import BotState
from dent_bot.protected_media import ProtectedMediaDispatcher


SOURCE_CHAT_ID = -1003706539157
SOURCE_CAPTION = (
    "📓 جزوه رفرنس جلسه چهارم ورودی ۱۳۹۹ - تومورهای سینوس\n\n"
    "📚 گوش و حلق و بینی\n👨‍🏫 استاد ایرانی\n\n"
    "#گوش_حلق_بینی #ایرانی #ترم۷ #ورودی_۱۳۹۹"
)


class FakeApi:
    def __init__(self) -> None:
        self.sent = []
        self.edited = []
        self.answered = []
        self.reply_keyboards_removed = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id, message_id, text, keyboard):
        self.edited.append((chat_id, message_id, text, keyboard))
        return {"message_id": message_id}

    def answer_callback(self, callback_id, text="", *, show_alert=False):
        self.answered.append((callback_id, text, show_alert))
        return True

    def remove_reply_keyboard(self, chat_id):
        self.reply_keyboards_removed.append(chat_id)
        return {"message_id": 500}


class LinkedSite:
    def account(self, _user_id):
        return {
            "success": True,
            "linked": True,
            "authComplete": True,
            "user": {},
            "onboardingProfile": {},
        }


class Dispatcher:
    def __init__(self) -> None:
        self.jobs = []

    def enqueue(self, user_id, source_id):
        self.jobs.append((user_id, source_id))
        return "queued"


def message(user_id: int, text: str) -> dict:
    return {
        "message": {
            "message_id": 1,
            "text": text,
            "chat": {"id": user_id, "type": "private"},
            "from": {"id": user_id},
        }
    }


class BookletDeliveryTests(unittest.TestCase):
    def test_session_parser_supports_every_ordinal_from_one_to_forty(self) -> None:
        for number in range(1, 41):
            with self.subTest(number=number):
                self.assertEqual(parse_session_number(f"جزوه جلسه {ordinal(number)} - تست"), number)
                self.assertEqual(parse_session_number(f"جزوه جلسه {number} - تست"), number)
        self.assertIsNone(parse_session_number("جزوه جلسه چهل و یکم"))

    def test_caption_routes_booklet_reference_to_both_sections(self) -> None:
        parsed = parse_source_caption(SOURCE_CAPTION)
        self.assertIsNotNone(parsed)
        assert parsed is not None
        self.assertEqual(parsed.course_code, "ENT")
        self.assertEqual(parsed.term, 7)
        self.assertEqual(parsed.session_no, 4)
        self.assertEqual(parsed.kinds, ("booklet", "reference"))
        records = source_records_from_channel_post({
            "caption": SOURCE_CAPTION,
            "document": {
                "file_id": "telegram-file-id",
                "file_unique_id": "unique-id",
                "file_name": "tumors-ent.pdf",
                "mime_type": "application/pdf",
            },
        })
        self.assertEqual({item["contentKind"] for item in records}, {"booklet", "reference"})
        self.assertTrue(all(item["telegramMethod"] == "sendDocument" for item in records))

    def test_source_catalog_stores_only_metadata_and_edit_can_deactivate_routes(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                records = source_records_from_channel_post({
                    "caption": SOURCE_CAPTION,
                    "document": {"file_id": "file-id", "file_name": "test.pdf"},
                })
                self.assertEqual(state.replace_protected_media_message(SOURCE_CHAT_ID, 4, records), 2)
                booklet = state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )
                self.assertEqual(len(booklet), 1)
                metadata_only = source_records_from_channel_post({
                    "caption": SOURCE_CAPTION,
                    "document": {"file_name": "test.pdf", "mime_type": "application/pdf"},
                })
                state.replace_protected_media_message(SOURCE_CHAT_ID, 4, metadata_only)
                preserved = state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )
                self.assertEqual(preserved[0]["fileId"], "file-id")
                self.assertEqual(
                    state.update_protected_media_file(
                        SOURCE_CHAT_ID, 4, file_id="hydrated-id", file_unique_id="hydrated-unique"
                    ),
                    2,
                )
                hydrated = state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )
                self.assertEqual(hydrated[0]["fileId"], "hydrated-id")
                columns = {
                    row[1] for row in state.connection.execute("PRAGMA table_info(protected_media_sources)")
                }
                self.assertFalse({"bytes", "blob", "local_path", "temporary_path"} & columns)
                state.replace_protected_media_message(SOURCE_CHAT_ID, 4, [])
                self.assertEqual(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                ), [])
            finally:
                state.close()

    def test_issuance_attribution_survives_source_catalog_removal(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                foreign_keys = state.connection.execute(
                    "PRAGMA foreign_key_list(booklet_issuances)"
                ).fetchall()
                self.assertEqual(foreign_keys, [])
            finally:
                state.close()

    def test_only_exact_private_source_channel_updates_catalog(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(
                    FakeApi(), state, owner_id=10, site_url="https://example.test",
                    site_api=LinkedSite(), booklet_source_channel_id=SOURCE_CHAT_ID,
                )
                post = {
                    "message_id": 4,
                    "chat": {"id": SOURCE_CHAT_ID, "type": "channel"},
                    "caption": SOURCE_CAPTION,
                    "document": {"file_id": "file-id", "file_name": "test.pdf"},
                }
                app.handle({"channel_post": dict(post)})
                self.assertEqual(len(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )), 1)
                post["message_id"] = 5
                post["chat"] = {"id": -1009999999999, "type": "channel"}
                app.handle({"channel_post": post})
                count = state.connection.execute("SELECT COUNT(*) FROM protected_media_sources").fetchone()[0]
                self.assertEqual(count, 2)
            finally:
                state.close()

    def test_notes_flow_uses_reply_keyboards_and_enqueues_registered_source(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            dispatcher = Dispatcher()
            try:
                records = source_records_from_channel_post({
                    "caption": SOURCE_CAPTION.replace("جزوه رفرنس", "جزوه"),
                    "document": {"file_id": "file-id", "file_name": "test.pdf"},
                })
                state.replace_protected_media_message(SOURCE_CHAT_ID, 4, records)
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=LinkedSite(), media_dispatcher=dispatcher,
                )
                app.handle({"callback_query": {
                    "id": "notes", "from": {"id": 20}, "data": "v1:notes",
                    "message": {"message_id": 7, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(state.dialog(20)["kind"], "booklets-v1")
                self.assertNotIn("inline_keyboard", api.sent[-1][2])
                ent = next(course for course in COURSES if course["code"] == "ENT")
                app.handle(message(20, course_button(ent)))
                fourth = next(session for session in ENT_SESSIONS if session[0] == 4)
                app.handle(message(20, session_button(fourth)))
                self.assertEqual(
                    {button["text"] for row in api.sent[-1][2]["keyboard"] for button in row}
                    & set(RESOURCE_LABELS.values()),
                    set(RESOURCE_LABELS.values()),
                )
                app.handle(message(20, RESOURCE_LABELS["booklet"]))
                self.assertEqual(len(dispatcher.jobs), 1)
                self.assertIn("فایل در صف امن", api.sent[-1][1])
            finally:
                state.close()

    def test_transport_always_protects_source_copy_and_personalized_file_id(self) -> None:
        class CapturingApi(TelegramBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=8):
                self.calls.append((method, dict(payload or {}), timeout))
                return {"message_id": 9}

        api = CapturingApi()
        source = {
            "sourceChatId": SOURCE_CHAT_ID,
            "sourceMessageId": 4,
            "telegramMethod": "sendDocument",
        }
        api.send_protected_media(20, source)
        self.assertEqual(api.calls[-1][0], "copyMessage")
        self.assertIs(api.calls[-1][1]["protect_content"], True)
        self.assertNotIn("file", str(api.calls[-1][1]).lower())
        api.send_protected_media(20, source, personalized_file_id="personal-file-id")
        self.assertEqual(api.calls[-1][0], "sendDocument")
        self.assertEqual(api.calls[-1][1]["document"], "personal-file-id")
        self.assertIs(api.calls[-1][1]["protect_content"], True)

        bale = object.__new__(BaleBotApi)
        with self.assertRaises(BotApiError):
            bale.send_protected_media(20, source)

    def test_initial_personalized_upload_sets_multipart_protect_content(self) -> None:
        class MultipartTransport:
            def post_multipart_file(self, path, **kwargs):
                self.path = path
                self.kwargs = kwargs
                return 200, b'{"ok":true,"result":{"message_id":11}}'

        with tempfile.TemporaryDirectory() as directory:
            document = Path(directory) / "personalized.pdf"
            document.write_bytes(b"%PDF-1.4\n%%EOF\n")
            api = object.__new__(TelegramBotApi)
            api._path = "/bot-test"
            api._transport = MultipartTransport()
            result = api.send_protected_document_path(20, document)
            self.assertEqual(result["message_id"], 11)
            self.assertEqual(api._transport.kwargs["fields"]["protect_content"], "true")
            self.assertEqual(api._transport.kwargs["content_type"], "application/pdf")

    def test_leaving_booklet_flow_removes_each_actually_active_reply_keyboard_once(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=LinkedSite(),
                )
                state.start_dialog(20, "booklets-v1", "course", {})
                app.handle(message(20, "/menu"))
                self.assertIsNone(state.dialog(20))
                self.assertEqual(api.reply_keyboards_removed, [20])

                state.start_dialog(20, "booklets-v1", "session", {"courseCode": "ENT"})
                state.mark_reply_keyboard_active(20)
                app.handle(message(20, "/start"))
                self.assertIsNone(state.dialog(20))
                self.assertEqual(api.reply_keyboards_removed, [20, 20])
            finally:
                state.close()

    @unittest.skipUnless(__import__("importlib").util.find_spec("pymupdf"), "PyMuPDF unavailable")
    def test_pdf_dispatcher_personalizes_once_caches_file_id_and_cleans_temp(self) -> None:
        import shutil
        import pymupdf

        class PdfApi:
            def __init__(self, source_path: Path) -> None:
                self.source_path = source_path
                self.downloads = 0
                self.uploads = 0
                self.cached_sends = []
                self.sent = []

            def download_file(self, _file_id, destination, *, max_bytes):
                self.downloads += 1
                shutil.copyfile(self.source_path, destination)
                return {"bytes": destination.stat().st_size}

            def send_protected_document_path(self, chat_id, document_path, *, caption, filename):
                self.uploads += 1
                self.assert_path = document_path
                self.assert_bytes = document_path.stat().st_size
                self.assert_filename = filename
                return {
                    "message_id": 700 + self.uploads,
                    "document": {"file_id": "personalized-file", "file_unique_id": "personalized-unique"},
                }

            def send_protected_media(self, chat_id, source, *, personalized_file_id=""):
                self.cached_sends.append(personalized_file_id)
                return {"message_id": 800 + len(self.cached_sends)}

            def send(self, chat_id, text, keyboard):
                self.sent.append((chat_id, text, keyboard))
                return {"message_id": 900}

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source_pdf = root / "source.pdf"
            document = pymupdf.open()
            page = document.new_page(width=595, height=842)
            page.insert_text((72, 100), "Protected delivery fixture")
            document.save(source_pdf)
            document.close()
            state = BotState(root / "state.sqlite3")
            dispatcher = None
            try:
                state.replace_protected_media_message(SOURCE_CHAT_ID, 9, [{
                    "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendDocument", "fileId": "source-file-id",
                    "fileUniqueId": "source-unique-id", "fileName": "source.pdf",
                    "mimeType": "application/pdf", "caption": SOURCE_CAPTION,
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )[0]["id"])
                font = next(path for path in (
                    Path("dent_bot/assets/fonts/B_Nazanin_Bold.ttf"),
                    Path("C:/Windows/Fonts/tahoma.ttf"),
                    Path("/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"),
                    Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
                ) if path.is_file())
                api = PdfApi(source_pdf)
                temp_root = root / "jobs"
                dispatcher = ProtectedMediaDispatcher(
                    api=api,
                    state=state,
                    authorize=lambda _user, _source: True,
                    identity_provider=lambda _user: {"identity": {
                        "fullName": "کاربر آزمایشی", "nationalCode": "0012345678",
                        "phoneNumber": "09123456789",
                    }},
                    fingerprint_key=b"k" * 32,
                    watermark_font=font,
                    temp_root=temp_root,
                    qpdf_binary="",
                    workers=1,
                    max_queue=24,
                    same_document_cooldown_seconds=1,
                )
                self.assertEqual(dispatcher.enqueue(20, source_id), "queued")
                dispatcher.queue.join()
                self.assertEqual(api.downloads, 1)
                self.assertEqual(api.uploads, 1)
                self.assertGreater(api.assert_bytes, source_pdf.stat().st_size)
                self.assertEqual(api.assert_filename, "dent1402-personalized.pdf")
                self.assertEqual(list(temp_root.glob("job-*")), [])
                candidates = state.forensic_booklet_candidates()
                self.assertEqual(len(candidates), 1)
                self.assertEqual(candidates[0]["telegramFileId"], "personalized-file")
                time.sleep(1.05)
                self.assertEqual(dispatcher.enqueue(20, source_id), "queued")
                dispatcher.queue.join()
                self.assertEqual(api.downloads, 1)
                self.assertEqual(api.uploads, 1)
                self.assertEqual(api.cached_sends, ["personalized-file"])
            finally:
                if dispatcher is not None:
                    dispatcher.close()
                state.close()

    def test_dispatcher_accepts_twenty_simultaneous_requests_without_spawning_heavy_workers(self) -> None:
        class QueueApi:
            def __init__(self) -> None:
                self.count = 0

            def send_protected_media(self, _chat_id, _source, *, personalized_file_id=""):
                gate.wait(3)
                self.count += 1
                return {"message_id": self.count}

            def send(self, *_args):
                return {"message_id": 999}

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            state = BotState(root / "state.sqlite3")
            dispatcher = None
            gate = threading.Event()
            try:
                state.replace_protected_media_message(SOURCE_CHAT_ID, 11, [{
                    "contentKind": "voice", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendVoice", "fileId": "voice-id",
                    "fileUniqueId": "voice-unique", "fileName": "voice.ogg",
                    "mimeType": "audio/ogg", "caption": "ویس جلسه چهارم #گوش_حلق_بینی #ترم۷",
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="voice"
                )[0]["id"])
                api = QueueApi()
                dispatcher = ProtectedMediaDispatcher(
                    api=api,
                    state=state,
                    authorize=lambda _user, _source: True,
                    temp_root=root / "jobs",
                    workers=1,
                    max_queue=24,
                )
                statuses = [dispatcher.enqueue(user_id, source_id) for user_id in range(100, 120)]
                self.assertEqual(statuses, ["queued"] * 20)
                self.assertEqual(len(dispatcher._threads), 1)
                gate.set()
                dispatcher.queue.join()
                self.assertEqual(api.count, 20)
            finally:
                gate.set()
                if dispatcher is not None:
                    dispatcher.close()
                state.close()

    def test_document_level_dedupe_covers_different_source_routes(self) -> None:
        gate = threading.Event()

        class QueueApi:
            def send_protected_media(self, _chat_id, _source, *, personalized_file_id=""):
                gate.wait(3)
                return {"message_id": 1}

            def send(self, *_args):
                return {"message_id": 2}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            dispatcher = None
            try:
                for message_id, kind in ((31, "voice"), (32, "reference")):
                    state.replace_protected_media_message(SOURCE_CHAT_ID, message_id, [{
                        "contentKind": kind, "courseCode": "ENT", "courseName": "ENT",
                        "courseTag": "ent", "term": 7, "sessionNo": 4,
                        "telegramMethod": "sendVoice" if kind == "voice" else "sendDocument",
                        "fileId": f"route-{message_id}", "fileUniqueId": "same-logical-document",
                        "fileName": "same.ogg" if kind == "voice" else "same.bin",
                        "mimeType": "audio/ogg" if kind == "voice" else "application/octet-stream",
                        "caption": "test",
                    }])
                first = int(state.protected_media_source(1)["id"])
                second = int(state.protected_media_source(2)["id"])
                dispatcher = ProtectedMediaDispatcher(
                    api=QueueApi(), state=state, authorize=lambda _user, _source: True,
                    temp_root=Path(directory) / "jobs", workers=1, max_queue=20,
                )
                self.assertEqual(dispatcher.enqueue(90, first), "queued")
                self.assertEqual(dispatcher.enqueue(90, second), "duplicate")
                gate.set()
                dispatcher.queue.join()
            finally:
                gate.set()
                if dispatcher is not None:
                    dispatcher.close()
                state.close()

    def test_rate_limit_atomic_completion_and_stale_recovery(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            state = BotState(root / "state.sqlite3")
            try:
                self.assertEqual(state.claim_booklet_request(
                    77, "doc", now_epoch=1000, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "claimed")
                self.assertEqual(state.claim_booklet_request(
                    77, "doc", now_epoch=1001, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "cooldown")
                self.assertEqual(state.claim_booklet_request(
                    77, "doc-2", now_epoch=1004, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "claimed")
                self.assertEqual(state.claim_booklet_request(
                    77, "doc-3", now_epoch=1005, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "rate-limited")
                state.replace_protected_media_message(SOURCE_CHAT_ID, 41, [{
                    "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendDocument", "fileId": "source",
                    "fileUniqueId": "atomic-document", "fileName": "source.pdf",
                    "mimeType": "application/pdf", "caption": "test",
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )[0]["id"])
                issuance = state.create_booklet_issuance(
                    issuance_id="iss_atomiccompletion001", user_id=77, source_id=source_id,
                    document_id="tgdoc_atomic", trace_code="TRC-ABCDE-FGHIJ",
                    fingerprint_hash="a" * 64, watermark_version="recipient-pdf-v2",
                    source_hash="b" * 64,
                )
                self.assertTrue(state.mark_booklet_issuance_processing(issuance["issuanceId"]))
                state.complete_booklet_issuance(
                    issuance["issuanceId"], telegram_file_id="atomic-file",
                    telegram_file_unique_id="atomic-unique",
                )
                self.assertEqual(state.sent_booklet_issuance(
                    77, source_id, "tgdoc_atomic", "recipient-pdf-v2"
                )["telegramFileId"], "atomic-file")
                self.assertEqual(state.personalized_media_file(77, source_id), "atomic-file")
                stale = state.create_booklet_issuance(
                    issuance_id="iss_staleprocessing001", user_id=78, source_id=source_id,
                    document_id="tgdoc_stale", trace_code="TRC-KLMNO-PQRST",
                    fingerprint_hash="c" * 64, watermark_version="recipient-pdf-v2",
                    source_hash="d" * 64,
                )
                self.assertTrue(state.mark_booklet_issuance_processing(stale["issuanceId"]))
                state.connection.execute(
                    "UPDATE booklet_issuances SET updated_at='2000-01-01 00:00:00' WHERE issuance_id=?",
                    (stale["issuanceId"],),
                )
                state.connection.commit()
                self.assertEqual(state.recover_stale_booklet_issuances(60), 1)
                self.assertEqual(state.booklet_issuance_for_source_hash(
                    78, source_id, "tgdoc_stale", "d" * 64, "recipient-pdf-v2"
                )["status"], "failed")
            finally:
                state.close()

    def test_orphan_cleanup_is_age_based(self) -> None:
        class NoopApi:
            pass

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            temp_root = root / "jobs"
            temp_root.mkdir()
            old = temp_root / "job-old"
            fresh = temp_root / "job-fresh"
            old.mkdir()
            fresh.mkdir()
            old_epoch = time.time() - 7200
            os.utime(old, (old_epoch, old_epoch))
            state = BotState(root / "state.sqlite3")
            dispatcher = None
            try:
                dispatcher = ProtectedMediaDispatcher(
                    api=NoopApi(), state=state, authorize=lambda _user, _source: True,
                    temp_root=temp_root, orphan_max_age_seconds=3600,
                )
                self.assertFalse(old.exists())
                self.assertTrue(fresh.exists())
            finally:
                if dispatcher is not None:
                    dispatcher.close()
                state.close()


if __name__ == "__main__":
    unittest.main()
