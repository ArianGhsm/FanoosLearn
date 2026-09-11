from __future__ import annotations

import logging
import signal
import threading
import time
from collections import deque
from concurrent.futures import Future, ThreadPoolExecutor, TimeoutError as FutureTimeoutError
from datetime import datetime

from .api import BotApiError
from .app import DentBotApp
from .state import BotState
from .site_api import SiteApiClient, SiteApiError
from .ui import (
    account_disconnect_notice_screen,
    navid_assignment_photo_caption,
    notification_push_screen,
    payment_success_push_screen,
    payment_owner_success_push_screen,
)
from .persian_datetime import to_persian_digits
from .navid import decode_image_data_uri, local_now, send_daily_challenge
from .protected_media import ProtectedMediaDispatcher


def configure_profile_safely(api, platform_name: str) -> bool:
    """Profile metadata is useful, but a transient network cut must not kill polling."""
    try:
        api.configure_profile()
        return True
    except BotApiError as error:
        if not error.transient:
            raise
        logging.warning("%s profile configuration unavailable; polling will continue", platform_name)
        return False


def dispatch_notification_batch(*, settings, api, state: BotState, site_api: SiteApiClient) -> dict[str, int]:
    counts = {"claimed": 0, "sent": 0, "acknowledged": 0, "failed": 0, "canceled": 0}
    result = site_api.claim_notification_deliveries(
        settings.owner_id,
        limit=int(getattr(settings, "notification_batch_size", 10)),
    )
    deliveries = [item for item in result.get("deliveries", []) if isinstance(item, dict)]
    counts["claimed"] = len(deliveries)
    for delivery in deliveries:
        delivery_id = str(delivery.get("deliveryId") or "")
        notification = dict(delivery.get("notification") or {})
        source = str(notification.get("source") or "").strip().lower()
        source_key = str(notification.get("sourceKey") or "").strip().lower()
        if source == "exams" or source_key.startswith("exam-resume-"):
            counts["canceled"] += 1
            if delivery_id:
                site_api.ack_notification_delivery(
                    settings.owner_id,
                    delivery_id,
                    delivered=False,
                    reason_code="EXAM_REMINDER_RETIRED",
                )
            continue
        try:
            chat_id = int(str(delivery.get("chatId") or ""))
        except ValueError:
            chat_id = 0
        if not delivery_id or chat_id <= 0 or not str(notification.get("id") or ""):
            counts["failed"] += 1
            if delivery_id:
                site_api.ack_notification_delivery(
                    settings.owner_id,
                    delivery_id,
                    delivered=False,
                    reason_code="INVALID_DELIVERY",
                )
            continue
        if state.has_notification_delivery(delivery_id):
            site_api.ack_notification_delivery(settings.owner_id, delivery_id, delivered=True)
            counts["acknowledged"] += 1
            continue
        ref = state.remember_notification(str(notification["id"]))
        screen = notification_push_screen(notification, ref, platform=settings.platform)
        try:
            api.send(chat_id, screen.text, screen.keyboard)
            state.mark_notification_delivery(delivery_id)
            counts["sent"] += 1
            site_api.ack_notification_delivery(settings.owner_id, delivery_id, delivered=True)
            counts["acknowledged"] += 1
        except BotApiError:
            counts["failed"] += 1
            try:
                site_api.ack_notification_delivery(
                    settings.owner_id,
                    delivery_id,
                    delivered=False,
                    reason_code="BOT_SEND_FAILED",
                )
            except SiteApiError:
                pass
    return counts


def dispatch_account_disconnect_batch(*, settings, api, state: BotState, site_api: SiteApiClient) -> dict[str, int]:
    """Send durable website-originated unlink notices on their original platform."""
    counts = {"claimed": 0, "sent": 0, "acknowledged": 0, "failed": 0}
    claim = getattr(site_api, "claim_account_disconnect_deliveries", None)
    if not callable(claim):
        return counts
    result = claim(
        settings.owner_id,
        limit=int(getattr(settings, "notification_batch_size", 10)),
    )
    deliveries = [item for item in result.get("deliveries", []) if isinstance(item, dict)]
    counts["claimed"] = len(deliveries)
    for delivery in deliveries:
        delivery_id = str(delivery.get("deliveryId") or "")
        try:
            chat_id = int(str(delivery.get("chatId") or ""))
        except ValueError:
            chat_id = 0
        valid = (
            bool(delivery_id)
            and chat_id > 0
            and str(delivery.get("platform") or "") == str(settings.platform)
            and bool(str(delivery.get("disconnectedAt") or ""))
        )
        receipt_id = f"account-disconnect:{delivery_id}"
        if not valid:
            counts["failed"] += 1
            if delivery_id:
                site_api.ack_account_disconnect_delivery(
                    settings.owner_id,
                    delivery_id,
                    delivered=False,
                    reason_code="INVALID_DELIVERY",
                )
            continue
        if state.has_notification_delivery(receipt_id):
            site_api.ack_account_disconnect_delivery(settings.owner_id, delivery_id, delivered=True)
            counts["acknowledged"] += 1
            continue
        try:
            screen = account_disconnect_notice_screen(
                delivery.get("disconnectedAt"),
                platform=settings.platform,
            )
            api.send(chat_id, screen.text, screen.keyboard)
            # Persist before ACK so a website timeout cannot duplicate the notice.
            state.mark_notification_delivery(receipt_id)
            counts["sent"] += 1
            site_api.ack_account_disconnect_delivery(settings.owner_id, delivery_id, delivered=True)
            counts["acknowledged"] += 1
        except BotApiError:
            counts["failed"] += 1
            try:
                site_api.ack_account_disconnect_delivery(
                    settings.owner_id,
                    delivery_id,
                    delivered=False,
                    reason_code="BOT_SEND_FAILED",
                )
            except SiteApiError:
                pass
    return counts


def dispatch_payment_result_batch(*, settings, api, state: BotState, site_api: SiteApiClient) -> dict[str, int]:
    """Deliver canonical verified-success receipts only on the originating platform."""
    counts = {"claimed": 0, "sent": 0, "acknowledged": 0, "failed": 0, "activated": 0}
    if not bool(getattr(settings, "payment_result_push_enabled", False)):
        return counts
    result = site_api.claim_payment_result_deliveries(
        settings.owner_id,
        limit=int(getattr(settings, "payment_result_batch_size", 10)),
    )
    deliveries = [item for item in result.get("deliveries", []) if isinstance(item, dict)]
    counts["claimed"] = len(deliveries)
    acknowledgements: list[dict] = []
    for delivery in deliveries:
        delivery_id = str(delivery.get("deliveryId") or "")
        order = dict(delivery.get("order") or {})
        try:
            chat_id = int(str(delivery.get("chatId") or ""))
        except ValueError:
            chat_id = 0
        order_token = str(order.get("orderToken") or "")
        valid = (
            bool(delivery_id)
            and chat_id > 0
            and str(delivery.get("platform") or "") == str(settings.platform)
            and str(order.get("status") or "") == "success"
            and 20 <= len(order_token) <= 46
        )
        receipt_id = f"payment-result:{delivery_id}"
        if not valid:
            counts["failed"] += 1
            if delivery_id:
                acknowledgements.append({"deliveryId": delivery_id, "delivered": False, "reasonCode": "INVALID_DELIVERY"})
            continue
        checkout = state.term_subscription_checkout_by_order(order_token)
        if checkout is not None:
            delivery_kind = str(delivery.get("deliveryKind") or "user")
            # The website may fan the owner's audit receipt out to every linked
            # owner platform.  It is not the payer delivery and therefore must
            # not be validated against (or activate) the originating checkout.
            # The strict platform/chat/amount proof applies only to the user's
            # same-platform fulfillment delivery.
            if delivery_kind == "owner":
                checkout = None
        if checkout is not None:
            delivery_kind = str(delivery.get("deliveryKind") or "user")
            subscription_valid = (
                str(checkout.get("platform") or "") == str(settings.platform)
                and str(order.get("verifiedAt") or "").strip() != ""
                and int(order.get("amountRials") or 0) == int(checkout.get("amountRials") or -1)
                and int(checkout.get("platformUserId") or 0) == chat_id
            )
            if not subscription_valid:
                counts["failed"] += 1
                acknowledgements.append({"deliveryId": delivery_id, "delivered": False, "reasonCode": "INVALID_DELIVERY"})
                continue
            try:
                entitlement = state.activate_paid_term_subscription(
                    order_token=order_token, delivery_id=delivery_id,
                    platform=str(checkout["platform"]),
                    platform_user_id=int(checkout["platformUserId"]),
                    amount_rials=int(order["amountRials"]), verified_at=str(order["verifiedAt"]),
                    payment_order_ref=str(order.get("orderId") or order.get("trackingRef") or ""),
                )
                counts["activated"] += 1
                order["fulfillment"] = {
                    "text": (
                        f"اشتراک {entitlement.get('billingPeriod') or ''} فعال شد؛ "
                        "اکنون می‌توانی از بخش جزوات ادامه بدهی."
                    )
                }
            except (TypeError, ValueError, RuntimeError):
                counts["failed"] += 1
                acknowledgements.append({"deliveryId": delivery_id, "delivered": False, "reasonCode": "INVALID_DELIVERY"})
                continue
        if state.has_notification_delivery(receipt_id):
            acknowledgements.append({"deliveryId": delivery_id, "delivered": True, "reasonCode": ""})
            continue
        try:
            screen = (
                payment_owner_success_push_screen(order)
                if str(delivery.get("deliveryKind") or "user") == "owner"
                else payment_success_push_screen(order, platform=settings.platform)
            )
            api.send(chat_id, screen.text, screen.keyboard)
            # Persist before ACK: if the website ACK times out, the lease retry is
            # acknowledged without sending the financial confirmation twice.
            state.mark_notification_delivery(receipt_id)
            counts["sent"] += 1
            acknowledgements.append({"deliveryId": delivery_id, "delivered": True, "reasonCode": ""})
        except BotApiError:
            counts["failed"] += 1
            acknowledgements.append({"deliveryId": delivery_id, "delivered": False, "reasonCode": "BOT_SEND_FAILED"})
    if acknowledgements:
        if hasattr(site_api, "ack_payment_result_deliveries"):
            ack_result = site_api.ack_payment_result_deliveries(settings.owner_id, acknowledgements)
            acknowledged_ids = {
                str(item.get("deliveryId") or "")
                for item in ack_result.get("results", [])
                if isinstance(item, dict) and item.get("found") and item.get("delivered")
            }
            counts["acknowledged"] += len(acknowledged_ids)
        else:
            # Compatibility for old adapters/tests during rolling deployment.
            for item in acknowledgements:
                site_api.ack_payment_result_delivery(
                    settings.owner_id,
                    str(item["deliveryId"]),
                    delivered=bool(item["delivered"]),
                    reason_code=str(item["reasonCode"]),
                )
                if item["delivered"]:
                    counts["acknowledged"] += 1
    return counts


def dispatch_term_renewal_batch(*, settings, api, state: BotState, now: datetime | None = None) -> dict[str, int]:
    """Send one deduplicated, same-platform reminder near the start of a Jalali month."""
    counts = {"claimed": 0, "sent": 0, "failed": 0}
    notices = state.claim_term_renewal_notices(platform=str(settings.platform), now=now, limit=20)
    counts["claimed"] = len(notices)
    for notice in notices:
        period = notice.get("period")
        term = int(notice.get("term") or 0)
        user_id = int(notice.get("platformUserId") or 0)
        try:
            api.send(
                user_id,
                f"<b>🔔 تمدید اشتراک جزوات</b>\n\n"
                f"اشتراک ماه قبل پایان یافته است. برای دسترسی به جزوات ترم {to_persian_digits(term)} در "
                f"<b>{to_persian_digits(period.month_label if period is not None else '')}</b>، اشتراک این ماه را فعال کن.\n\n"
                "<blockquote>تمدید خودکار نیست و فقط با پرداخت جدید خودت انجام می‌شود.</blockquote>",
                {"inline_keyboard": [[{
                    "text": "💳 مشاهده و تمدید",
                    "callback_data": f"v1:term-subscription:{term}",
                }]]},
            )
            state.finish_term_renewal_notice(
                term=term, billing_period=str(notice.get("billingPeriod") or ""),
                platform=str(settings.platform), platform_user_id=user_id, sent=True,
            )
            counts["sent"] += 1
        except BotApiError as error:
            state.finish_term_renewal_notice(
                term=term, billing_period=str(notice.get("billingPeriod") or ""),
                platform=str(settings.platform), platform_user_id=user_id, sent=False,
                error=type(error).__name__,
            )
            counts["failed"] += 1
    return counts


def dispatch_navid_daily(*, settings, api, state: BotState, site_api: SiteApiClient, now: datetime | None = None) -> str:
    if not bool(getattr(settings, "navid_daily_enabled", False)):
        return "disabled"
    current = now or local_now(str(getattr(settings, "navid_daily_timezone", "Asia/Tehran")))
    daily_date = current.strftime("%Y-%m-%d")
    if current.hour < int(getattr(settings, "navid_daily_hour", 9)):
        return "not-due"
    if state.runtime_value("navid_completed_date") == daily_date:
        return "completed"
    last_date = state.runtime_value("navid_attempt_date")
    try:
        last_epoch = float(state.runtime_value("navid_attempt_epoch") or "0")
    except ValueError:
        last_epoch = 0.0
    retry_seconds = int(getattr(settings, "navid_retry_seconds", 3600))
    if last_date == daily_date and time.time() - last_epoch < retry_seconds:
        return "throttled"

    state.set_runtime_value("navid_attempt_date", daily_date)
    state.set_runtime_value("navid_attempt_epoch", str(int(time.time())))
    result = send_daily_challenge(
        api=api,
        state=state,
        site_api=site_api,
        owner_id=settings.owner_id,
        daily_date=daily_date,
        refresh=False,
    )
    return str(result.get("status") or "challenge-ready")


def dispatch_navid_group_batch(*, settings, api, state: BotState, site_api: SiteApiClient) -> dict[str, int]:
    counts = {"claimed": 0, "sent": 0, "acknowledged": 0, "failed": 0}
    if not bool(getattr(settings, "navid_group_enabled", False)):
        return counts
    chat_id = int(getattr(settings, "navid_group_chat_id", 0) or 0)
    if chat_id == 0:
        return counts
    result = site_api.claim_navid_group_deliveries(settings.owner_id, limit=3)
    deliveries = [item for item in result.get("deliveries", []) if isinstance(item, dict)]
    counts["claimed"] = len(deliveries)
    for delivery in deliveries:
        delivery_id = str(delivery.get("deliveryId") or "")
        receipt_id = f"navid-group:{delivery_id}"
        assignment = dict(delivery.get("assignment") or {})
        event_type = str(delivery.get("eventType") or "")
        if not delivery_id or event_type not in {"new", "week", "day"} or not assignment.get("title"):
            counts["failed"] += 1
            if delivery_id:
                site_api.ack_navid_group_delivery(
                    settings.owner_id, delivery_id, delivered=False, reason_code="INVALID_DELIVERY"
                )
            continue
        if state.has_notification_delivery(receipt_id):
            site_api.ack_navid_group_delivery(settings.owner_id, delivery_id, delivered=True)
            counts["acknowledged"] += 1
            continue
        try:
            image = decode_image_data_uri(delivery.get("screenshotDataUri"))
            api.send_photo_bytes(
                chat_id,
                image,
                caption=navid_assignment_photo_caption(
                    assignment, event_type=event_type, platform=settings.platform
                ),
                reply_markup={"inline_keyboard": []},
                filename="navid-assignment.png",
            )
            state.mark_notification_delivery(receipt_id)
            counts["sent"] += 1
            site_api.ack_navid_group_delivery(settings.owner_id, delivery_id, delivered=True)
            counts["acknowledged"] += 1
        except (BotApiError, SiteApiError):
            counts["failed"] += 1
            try:
                site_api.ack_navid_group_delivery(
                    settings.owner_id, delivery_id, delivered=False, reason_code="BOT_SEND_FAILED"
                )
            except SiteApiError:
                pass
    return counts


def run_service(*, settings, api, platform_name: str) -> int:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    state = BotState(settings.state_db, payment_offers_path=getattr(settings, "payment_offers_db", None))
    site_api = SiteApiClient(
        settings.site_api_url,
        settings.site_service_secret,
        platform=settings.platform,
        timeout=getattr(settings, "site_timeout_seconds", 6.0),
        relay_secret=getattr(settings, "site_relay_secret", ""),
    )
    background_site_api = SiteApiClient(
        settings.site_api_url,
        settings.site_service_secret,
        platform=settings.platform,
        timeout=getattr(settings, "site_timeout_seconds", 6.0),
        relay_secret=getattr(settings, "site_relay_secret", ""),
    )
    app = DentBotApp(
        api,
        state,
        owner_id=settings.owner_id,
        site_url=settings.site_url,
        site_api=site_api,
        platform=settings.platform,
        bot_username=getattr(settings, "bot_username", "Dent1402Bot"),
        exams_v1_enabled=bool(getattr(settings, "exams_v1_enabled", False)),
        payment_return_v1_enabled=bool(getattr(settings, "payment_result_push_enabled", False)),
        student_assistant_v1_enabled=bool(getattr(settings, "student_assistant_v1_enabled", False)),
        required_channel_username=str(getattr(settings, "required_channel_username", "")),
        booklet_source_channel_id=int(getattr(settings, "booklet_source_channel_id", 0)),
    )
    media_dispatcher = None
    if settings.platform == "telegram" and int(getattr(settings, "booklet_source_channel_id", 0)) < 0:
        media_dispatcher = ProtectedMediaDispatcher(
            api=api,
            state=state,
            authorize=app.booklet_access_allowed,
            identity_provider=site_api.booklet_watermark_identity,
            fingerprint_key=settings.booklet_fingerprint_key,
            watermark_font=settings.booklet_watermark_font,
            temp_root=settings.booklet_temp_root,
            qpdf_binary=settings.booklet_qpdf_binary,
            max_download_bytes=settings.booklet_max_download_bytes,
            workers=int(getattr(settings, "booklet_media_workers", 1)),
            max_queue=int(getattr(settings, "booklet_media_queue_size", 24)),
            pdf_normalizer=str(getattr(settings, "booklet_pdf_normalizer", "none")),
            raster_dpi=int(getattr(settings, "booklet_raster_dpi", 180)),
            raster_jpeg_quality=int(getattr(settings, "booklet_raster_jpeg_quality", 88)),
            max_output_bytes=int(getattr(settings, "booklet_max_output_bytes", 49 * 1024 * 1024)),
            processing_timeout_seconds=int(getattr(settings, "booklet_processing_timeout_seconds", 300)),
            orphan_max_age_seconds=int(getattr(settings, "booklet_orphan_max_age_seconds", 3600)),
            rate_window_seconds=int(getattr(settings, "booklet_rate_window_seconds", 60)),
            rate_max_requests=int(getattr(settings, "booklet_rate_max_requests", 12)),
            same_document_cooldown_seconds=int(
                getattr(settings, "booklet_same_document_cooldown_seconds", 3)
            ),
        )
        app.media_dispatcher = media_dispatcher
    stop_event = threading.Event()

    def stop(_signum, _frame) -> None:
        stop_event.set()

    previous_sigterm = signal.signal(signal.SIGTERM, stop)
    previous_sigint = signal.signal(signal.SIGINT, stop)
    configure_profile_safely(api, platform_name)
    logging.info("Dent1402Bot %s polling service started", platform_name)
    failures = 0
    background = threading.Thread(
        target=_run_background_tasks,
        kwargs={
            "settings": settings,
            "api": api,
            "state": state,
            "site_api": background_site_api,
            "platform_name": platform_name,
            "stop_event": stop_event,
        },
        name="dent-bot-background",
        daemon=True,
    )
    pending: deque[tuple[int, Future[None]]] = deque()
    fetch_offset = state.offset()
    background.start()
    try:
        with ThreadPoolExecutor(max_workers=getattr(settings, "update_workers", 8), thread_name_prefix="dent-bot-update") as executor:
            while not stop_event.is_set():
                _persist_completed_updates(pending, state, platform_name)
                while len(pending) >= 64 and not stop_event.is_set():
                    try:
                        pending[0][1].result(timeout=1)
                    except FutureTimeoutError:
                        continue
                    _persist_completed_updates(pending, state, platform_name)
                try:
                    updates = api.get_updates(fetch_offset, settings.poll_timeout)
                    for update in updates:
                        update_id = int(update.get("update_id", -1))
                        if update_id < fetch_offset:
                            continue
                        fetch_offset = update_id + 1
                        pending.append((update_id, executor.submit(app.handle, update)))
                    failures = 0
                except BotApiError:
                    failures += 1
                    delay = min(30, 2 ** min(failures, 5))
                    logging.warning("%s polling failed; retrying in %s seconds", platform_name, delay)
                    stop_event.wait(delay)
            for _update_id, future in pending:
                try:
                    future.result()
                except Exception:
                    pass
            _persist_completed_updates(pending, state, platform_name)
    finally:
        stop_event.set()
        background.join(timeout=5)
        if media_dispatcher is not None:
            media_dispatcher.close()
        state.close()
        close_api = getattr(api, "close", None)
        if callable(close_api):
            close_api()
        signal.signal(signal.SIGTERM, previous_sigterm)
        signal.signal(signal.SIGINT, previous_sigint)
        logging.info("Dent1402Bot %s polling service stopped", platform_name)
    return 0


def _persist_completed_updates(
    pending: deque[tuple[int, Future[None]]],
    state: BotState,
    platform_name: str,
) -> None:
    while pending and pending[0][1].done():
        update_id, future = pending.popleft()
        try:
            future.result()
        except BotApiError:
            logging.exception("%s update handling failed for update_id=%s", platform_name, update_id)
        except Exception:
            logging.exception("%s unexpected update failure for update_id=%s", platform_name, update_id)
        finally:
            state.save_offset(update_id + 1)


def _run_background_tasks(*, settings, api, state, site_api, platform_name: str, stop_event: threading.Event) -> None:
    next_account_disconnect_poll = 0.0
    next_notification_poll = 0.0
    next_payment_result_poll = 0.0
    next_term_renewal_poll = 0.0
    next_navid_check = 0.0
    next_navid_group_poll = 0.0
    while not stop_event.is_set():
        now = time.monotonic()
        if now >= next_account_disconnect_poll:
            try:
                disconnect_counts = dispatch_account_disconnect_batch(
                    settings=settings,
                    api=api,
                    state=state,
                    site_api=site_api,
                )
                if disconnect_counts["claimed"]:
                    logging.info(
                        "%s account disconnect claimed=%s sent=%s acknowledged=%s failed=%s",
                        platform_name,
                        disconnect_counts["claimed"],
                        disconnect_counts["sent"],
                        disconnect_counts["acknowledged"],
                        disconnect_counts["failed"],
                    )
            except SiteApiError as error:
                logging.warning("%s account disconnect dispatch unavailable code=%s", platform_name, error.code)
            next_account_disconnect_poll = time.monotonic() + int(
                getattr(settings, "notification_poll_seconds", 30)
            )
        now = time.monotonic()
        if now >= next_notification_poll:
            try:
                counts = dispatch_notification_batch(settings=settings, api=api, state=state, site_api=site_api)
                if counts["claimed"]:
                    logging.info(
                        "%s notification dispatch claimed=%s sent=%s acknowledged=%s failed=%s",
                        platform_name,
                        counts["claimed"],
                        counts["sent"],
                        counts["acknowledged"],
                        counts["failed"],
                    )
            except SiteApiError as error:
                logging.warning("%s notification dispatch unavailable code=%s", platform_name, error.code)
            next_notification_poll = time.monotonic() + int(getattr(settings, "notification_poll_seconds", 30))
        now = time.monotonic()
        if now >= next_payment_result_poll:
            try:
                payment_counts = dispatch_payment_result_batch(
                    settings=settings,
                    api=api,
                    state=state,
                    site_api=site_api,
                )
                if payment_counts["claimed"]:
                    logging.info(
                        "%s payment result claimed=%s sent=%s acknowledged=%s failed=%s",
                        platform_name,
                        payment_counts["claimed"],
                        payment_counts["sent"],
                        payment_counts["acknowledged"],
                        payment_counts["failed"],
                    )
            except SiteApiError as error:
                logging.warning("%s payment result unavailable code=%s", platform_name, error.code)
            next_payment_result_poll = time.monotonic() + int(
                getattr(settings, "payment_result_poll_seconds", 30)
            )
        now = time.monotonic()
        if now >= next_term_renewal_poll:
            try:
                renewal_counts = dispatch_term_renewal_batch(settings=settings, api=api, state=state)
                if renewal_counts["claimed"]:
                    logging.info(
                        "%s term renewal claimed=%s sent=%s failed=%s",
                        platform_name, renewal_counts["claimed"], renewal_counts["sent"], renewal_counts["failed"],
                    )
            except Exception:
                logging.exception("%s term renewal dispatch failed", platform_name)
            next_term_renewal_poll = time.monotonic() + 3600
        now = time.monotonic()
        if now >= next_navid_check:
            try:
                navid_status = dispatch_navid_daily(
                    settings=settings,
                    api=api,
                    state=state,
                    site_api=site_api,
                )
                if navid_status not in {"disabled", "not-due", "completed", "throttled"}:
                    logging.info("%s Navid daily status=%s", platform_name, navid_status)
            except (SiteApiError, BotApiError) as error:
                logging.warning("%s Navid daily unavailable type=%s", platform_name, type(error).__name__)
            next_navid_check = time.monotonic() + 60
        now = time.monotonic()
        if now >= next_navid_group_poll:
            try:
                group_counts = dispatch_navid_group_batch(
                    settings=settings, api=api, state=state, site_api=site_api
                )
                if group_counts["claimed"]:
                    logging.info(
                        "%s Navid group claimed=%s sent=%s acknowledged=%s failed=%s",
                        platform_name,
                        group_counts["claimed"],
                        group_counts["sent"],
                        group_counts["acknowledged"],
                        group_counts["failed"],
                    )
            except SiteApiError as error:
                logging.warning("%s Navid group unavailable code=%s", platform_name, error.code)
            next_navid_group_poll = time.monotonic() + int(
                getattr(settings, "navid_group_poll_seconds", 60)
            )
        stop_event.wait(1)
