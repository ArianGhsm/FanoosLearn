from __future__ import annotations

import argparse
import json

from dent_bot.bale_config import load_settings as load_bale_settings
from dent_bot.config import load_settings as load_telegram_settings
from dent_bot.site_api import SiteApiClient, SiteApiError


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--platform", choices=("telegram", "bale"), required=True)
    args = parser.parse_args()
    settings = load_telegram_settings() if args.platform == "telegram" else load_bale_settings()
    client = SiteApiClient(
        settings.site_api_url,
        settings.site_service_secret,
        platform=args.platform,
        timeout=settings.site_timeout_seconds,
        relay_secret=settings.site_relay_secret,
    )
    result: dict[str, object] = {"platform": args.platform}
    try:
        notifications = client.claim_notification_deliveries(settings.owner_id, limit=1)
        result["notificationContract"] = bool(notifications.get("success"))
        result["notificationClaimed"] = len(list(notifications.get("deliveries") or []))
    except SiteApiError as error:
        result["notificationError"] = {"code": error.code, "status": error.status}
    try:
        payments = client.claim_payment_result_deliveries(settings.owner_id, limit=1)
        deliveries = list(payments.get("deliveries") or [])
        result["paymentContract"] = payments.get("contractVersion") == "bot-payment-return-v1"
        result["paymentClaimed"] = len(deliveries)
        for delivery in deliveries:
            delivery_id = str(dict(delivery).get("deliveryId") or "")
            if delivery_id:
                client.ack_payment_result_delivery(
                    settings.owner_id,
                    delivery_id,
                    delivered=False,
                    reason_code="LIVE_VERIFY_REQUEUE",
                )
        batch = client.ack_payment_result_deliveries(settings.owner_id, [{
            "deliveryId": "prd-" + ("0" * 32),
            "delivered": False,
            "reasonCode": "LIVE_VERIFY_NOOP",
        }])
        batch_results = list(batch.get("results") or [])
        result["paymentBatchAckContract"] = (
            batch.get("contractVersion") == "bot-payment-return-v1"
            and len(batch_results) == 1
            and not bool(dict(batch_results[0]).get("found"))
        )
    except SiteApiError as error:
        result["paymentError"] = {"code": error.code, "status": error.status}
    print(json.dumps(result, ensure_ascii=True, separators=(",", ":")))
    return 0 if not any(key.endswith("Error") for key in result) else 1


if __name__ == "__main__":
    raise SystemExit(main())
