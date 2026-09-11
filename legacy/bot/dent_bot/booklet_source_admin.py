from __future__ import annotations

import argparse
import base64
import json

from .api import TelegramBotApi
from .app import DentBotApp
from .booklets import source_records_from_channel_post
from .config import load_settings
from .protected_media import ProtectedMediaDispatcher
from .site_api import SiteApiClient
from .state import BotState


def _decode_caption(value: str) -> str:
    try:
        return base64.b64decode(value, validate=True).decode("utf-8")
    except (ValueError, UnicodeError) as error:
        raise ValueError("Caption must be strict UTF-8 Base64") from error


def main() -> int:
    parser = argparse.ArgumentParser(description="Administer caption-derived protected booklet sources.")
    subparsers = parser.add_subparsers(dest="command", required=True)
    subparsers.add_parser("probe")
    subparsers.add_parser("send-owner-test")
    hydrate = subparsers.add_parser("hydrate-existing")
    hydrate.add_argument("--message-id", required=True, type=int)
    register = subparsers.add_parser("register-existing")
    register.add_argument("--message-id", required=True, type=int)
    register.add_argument("--caption-base64", required=True)
    register.add_argument("--file-name", default="")
    register.add_argument("--mime-type", default="application/pdf")
    args = parser.parse_args()

    settings = load_settings()
    if settings.booklet_source_channel_id >= 0:
        raise ValueError("Protected booklet source channel is not configured")
    if args.command == "probe":
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
        try:
            me = dict(api.call("getMe") or {})
            chat = dict(api.call("getChat", {"chat_id": settings.booklet_source_channel_id}) or {})
            member = dict(api.call("getChatMember", {
                "chat_id": settings.booklet_source_channel_id,
                "user_id": int(me.get("id") or 0),
            }) or {})
            result = {
                "reachable": bool(chat.get("id")),
                "titleMatch": str(chat.get("title") or "") == settings.booklet_source_channel_title,
                "administrator": str(member.get("status") or "") in {"creator", "administrator"},
            }
            result["ready"] = all(result.values())
            print(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
            return 0 if result["ready"] else 2
        finally:
            api.close()

    if args.command == "send-owner-test":
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
        state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
        dispatcher = None
        try:
            sources = state.protected_media_for(
                course_code="ENT", term=7, session_no=4, content_kind="booklet"
            )
            sources = [
                item for item in sources
                if int(item.get("sourceChatId") or 0) == settings.booklet_source_channel_id
            ]
            if not sources:
                raise ValueError("ENT session 4 test source is not registered")
            source = sources[-1]
            site_api = SiteApiClient(
                settings.site_api_url,
                settings.site_service_secret,
                platform="telegram",
                timeout=settings.site_timeout_seconds,
                relay_secret=settings.site_relay_secret,
            )
            app = DentBotApp(
                api,
                state,
                owner_id=settings.owner_id,
                site_url=settings.site_url,
                site_api=site_api,
                platform="telegram",
                bot_username=settings.bot_username,
                required_channel_username=settings.required_channel_username,
                booklet_source_channel_id=settings.booklet_source_channel_id,
            )
            dispatcher = ProtectedMediaDispatcher(
                api=api,
                state=state,
                authorize=app.booklet_access_allowed,
                identity_provider=site_api.booklet_watermark_identity,
                fingerprint_key=settings.booklet_fingerprint_key,
                watermark_font=settings.booklet_watermark_font,
                temp_root=settings.booklet_temp_root,
                qpdf_binary=settings.booklet_qpdf_binary,
                max_download_bytes=settings.booklet_max_download_bytes,
                workers=1,
                max_queue=4,
                pdf_normalizer=settings.booklet_pdf_normalizer,
                raster_dpi=settings.booklet_raster_dpi,
                raster_jpeg_quality=settings.booklet_raster_jpeg_quality,
                max_output_bytes=settings.booklet_max_output_bytes,
                processing_timeout_seconds=settings.booklet_processing_timeout_seconds,
                orphan_max_age_seconds=settings.booklet_orphan_max_age_seconds,
                rate_window_seconds=settings.booklet_rate_window_seconds,
                rate_max_requests=settings.booklet_rate_max_requests,
                same_document_cooldown_seconds=settings.booklet_same_document_cooldown_seconds,
            )
            status = dispatcher.enqueue(settings.owner_id, int(source["id"]))
            if status != "queued":
                raise RuntimeError(f"Test delivery was not queued: {status}")
            dispatcher.queue.join()
            result = state.latest_protected_media_delivery(settings.owner_id, int(source["id"]))
            if not result or result.get("status") != "sent":
                raise RuntimeError("Protected owner test did not complete successfully")
            print(json.dumps({"success": True, **result}, separators=(",", ":")))
            return 0
        finally:
            if dispatcher is not None:
                dispatcher.close()
            state.close()
            api.close()

    if args.command == "hydrate-existing":
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
        state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
        temporary_message_id = 0
        try:
            forwarded = dict(api.call("forwardMessage", {
                "chat_id": settings.owner_id,
                "from_chat_id": settings.booklet_source_channel_id,
                "message_id": int(args.message_id),
                "protect_content": True,
                "disable_notification": True,
            }) or {})
            temporary_message_id = int(forwarded.get("message_id") or 0)
            media = dict(
                forwarded.get("document")
                or forwarded.get("audio")
                or forwarded.get("voice")
                or {}
            )
            file_id = str(media.get("file_id") or "")
            if not file_id:
                raise RuntimeError("Historical source did not expose reusable Bot API file metadata")
            if not temporary_message_id:
                raise RuntimeError("Historical source hydration did not return a temporary message")
            api.call("deleteMessage", {
                "chat_id": settings.owner_id,
                "message_id": temporary_message_id,
            })
            temporary_message_id = 0
            updated = state.update_protected_media_file(
                settings.booklet_source_channel_id,
                int(args.message_id),
                file_id=file_id,
                file_unique_id=str(media.get("file_unique_id") or ""),
                file_name=str(media.get("file_name") or ""),
                mime_type=str(media.get("mime_type") or ""),
            )
            if updated <= 0:
                raise RuntimeError("Historical source is not registered")
            print(json.dumps({"success": True, "routes": updated}, separators=(",", ":")))
            return 0
        finally:
            if temporary_message_id:
                try:
                    api.call("deleteMessage", {
                        "chat_id": settings.owner_id,
                        "message_id": temporary_message_id,
                    })
                except Exception:
                    pass
            state.close()
            api.close()

    caption = _decode_caption(args.caption_base64)
    records = source_records_from_channel_post({
        "caption": caption,
        "document": {
            "file_name": str(args.file_name),
            "mime_type": str(args.mime_type),
        },
    })
    if not records:
        raise ValueError("Existing source caption did not produce a valid route")
    state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
    try:
        count = state.replace_protected_media_message(
            settings.booklet_source_channel_id,
            int(args.message_id),
            records,
        )
    finally:
        state.close()
    print(json.dumps({"success": True, "routes": count}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
