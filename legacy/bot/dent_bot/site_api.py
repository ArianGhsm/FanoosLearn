from __future__ import annotations

import hashlib
import hmac
import json
import secrets
import time
import urllib.error
import urllib.request
import threading


class SiteApiError(RuntimeError):
    def __init__(self, message: str, *, code: str = "SITE_API_ERROR", status: int = 0) -> None:
        super().__init__(message[:240])
        self.code = code
        self.status = status


class SiteApiClient:
    def __init__(
        self,
        url: str,
        secret: bytes,
        *,
        platform: str,
        timeout: float = 12.0,
        relay_secret: str = "",
    ) -> None:
        self.url = url
        self.secret = secret
        self.platform = platform
        self.timeout = timeout
        self.relay_secret = relay_secret
        self._health_lock = threading.Lock()
        self._consecutive_failures = 0
        self._circuit_open_until = 0.0

    def request(self, action: str, platform_user_id: int, **fields) -> dict:
        with self._health_lock:
            if time.monotonic() < self._circuit_open_until:
                raise SiteApiError("ارتباط سایت موقتاً در حال بازیابی است؛ چند لحظه دیگر دوباره تلاش کن.", code="SITE_CIRCUIT_OPEN")
        payload = {"action": action, "platform": self.platform, "platformUserId": str(platform_user_id), **fields}
        body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        timestamp = str(int(time.time()))
        nonce = secrets.token_urlsafe(24)
        canonical = f"{timestamp}\n{nonce}\n{hashlib.sha256(body).hexdigest()}".encode("ascii")
        signature = hmac.new(self.secret, canonical, hashlib.sha256).hexdigest()
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-Dent-Timestamp": timestamp,
            "X-Dent-Nonce": nonce,
            "X-Dent-Signature": signature,
        }
        if self.relay_secret:
            headers["Authorization"] = f"Bearer {self.relay_secret}"
        request = urllib.request.Request(
            self.url,
            data=body,
            method="POST",
            headers=headers,
        )
        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as response:
                raw = response.read(256 * 1024)
                status = int(response.status)
        except urllib.error.HTTPError as error:
            raw = error.read(64 * 1024)
            status = int(error.code)
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            with self._health_lock:
                self._consecutive_failures += 1
                if self._consecutive_failures >= 3:
                    self._circuit_open_until = time.monotonic() + 20.0
            raise SiteApiError("ارتباط امن با سایت برقرار نشد.", code="SITE_UNAVAILABLE") from error
        try:
            decoded = json.loads(raw.decode("utf-8"))
        except (UnicodeError, json.JSONDecodeError) as error:
            raise SiteApiError("پاسخ سایت معتبر نبود.", code="INVALID_SITE_RESPONSE", status=status) from error
        if status >= 400 or not isinstance(decoded, dict) or decoded.get("success") is not True:
            message = str(decoded.get("error") or "درخواست سایت انجام نشد.") if isinstance(decoded, dict) else "درخواست سایت انجام نشد."
            code = str(decoded.get("code") or "SITE_API_ERROR") if isinstance(decoded, dict) else "SITE_API_ERROR"
            raise SiteApiError(message, code=code, status=status)
        with self._health_lock:
            self._consecutive_failures = 0
            self._circuit_open_until = 0.0
        return decoded

    def account(self, user_id: int) -> dict:
        return self.request("account", user_id)

    def booklet_watermark_identity(self, user_id: int) -> dict:
        return self.request(
            "bookletWatermarkIdentityV1",
            user_id,
            contractVersion="booklet-watermark-identity-v1",
        )

    def start_link(self, user_id: int, *, platform_profile: dict | None = None) -> dict:
        return self.request(
            "startLink",
            user_id,
            authVersion="bot-canonical-auth-v1",
            telegramProfile=dict(platform_profile or {}),
        )

    def onboarding_catalog(self, user_id: int) -> dict:
        return self.request("onboardingCatalogV1", user_id, contractVersion="bot-onboarding-v1")

    def onboarding_status(self, user_id: int) -> dict:
        return self.request("onboardingStatusV1", user_id, contractVersion="bot-onboarding-v1")

    def request_onboarding_otp(self, user_id: int, *, profile: dict, phone_number: str) -> dict:
        return self.request(
            "requestOnboardingOtpV1",
            user_id,
            contractVersion="bot-onboarding-v1",
            profile=profile,
            phoneNumber=phone_number,
        )

    def resend_onboarding_otp(self, user_id: int, *, challenge_ref: str) -> dict:
        return self.request(
            "resendOnboardingOtpV1",
            user_id,
            contractVersion="bot-onboarding-v1",
            challengeRef=challenge_ref,
        )

    def verify_onboarding_otp(self, user_id: int, *, challenge_ref: str, code: str) -> dict:
        return self.request(
            "verifyOnboardingOtpV1",
            user_id,
            contractVersion="bot-onboarding-v1",
            challengeRef=challenge_ref,
            code=code,
        )

    def start_class_auth_otp(
        self,
        user_id: int,
        *,
        student_number: str,
        platform_profile: dict | None = None,
    ) -> dict:
        return self.request(
            "classAuthOtpStartV1", user_id,
            contractVersion="bot-onboarding-v1", studentNumber=student_number,
            telegramProfile=dict(platform_profile or {}),
        )

    def verify_class_auth_otp(self, user_id: int, *, challenge_ref: str, code: str) -> dict:
        return self.request(
            "classAuthOtpVerifyV1", user_id,
            contractVersion="bot-onboarding-v1", challengeRef=challenge_ref, code=code,
        )

    def request_profile_edit(self, user_id: int, *, field: str, value: str) -> dict:
        return self.request(
            "requestProfileEditV1", user_id,
            contractVersion="bot-onboarding-v1", field=field, value=value,
        )

    def profile_edit_requests(self, owner_id: int) -> dict:
        return self.request("profileEditRequestsV1", owner_id, contractVersion="bot-onboarding-v1")

    def resolve_profile_edit(self, owner_id: int, *, request_ref: str, decision: str) -> dict:
        return self.request(
            "resolveProfileEditV1", owner_id,
            contractVersion="bot-onboarding-v1", requestRef=request_ref, decision=decision,
        )

    def normalize_identity_auth_v2(self, owner_id: int) -> dict:
        return self.request("normalizeIdentityAuthV2", owner_id)

    def identity_auth_v2_status(self, owner_id: int) -> dict:
        return self.request("identityAuthV2Status", owner_id)

    def identity_mappings(self, owner_id: int) -> dict:
        return self.request("identityMappings", owner_id)

    def delete_identity_mapping(self, owner_id: int, *, mapping_ref: str, reason: str) -> dict:
        return self.request("deleteIdentityMapping", owner_id, mappingRef=mapping_ref, reason=reason)

    def grades(self, user_id: int) -> dict:
        return self.request("grades", user_id)

    def notifications(self, user_id: int, *, limit: int = 20) -> dict:
        return self.request("notifications", user_id, limit=limit)

    def mark_notification_read(self, user_id: int, notification_id: str) -> dict:
        return self.request("markNotificationRead", user_id, notificationId=notification_id)

    def perform_notification_action(self, user_id: int, notification_id: str, action_ref: str) -> dict:
        return self.request(
            "performNotificationAction",
            user_id,
            notificationId=notification_id,
            actionRef=action_ref,
        )

    def notification_audience(self, user_id: int, notification_id: str) -> dict:
        return self.request("notificationAudience", user_id, notificationId=notification_id)

    def claim_notification_deliveries(self, owner_id: int, *, limit: int = 10) -> dict:
        return self.request("claimNotificationDeliveries", owner_id, limit=limit)

    def ack_notification_delivery(
        self,
        owner_id: int,
        delivery_id: str,
        *,
        delivered: bool,
        reason_code: str = "",
    ) -> dict:
        return self.request(
            "ackNotificationDelivery",
            owner_id,
            deliveryId=delivery_id,
            delivered=delivered,
            reasonCode=reason_code,
        )

    def claim_account_disconnect_deliveries(self, owner_id: int, *, limit: int = 10) -> dict:
        return self.request(
            "claimAccountDisconnectDeliveriesV1",
            owner_id,
            limit=limit,
        )

    def ack_account_disconnect_delivery(
        self,
        owner_id: int,
        delivery_id: str,
        *,
        delivered: bool,
        reason_code: str = "",
    ) -> dict:
        return self.request(
            "ackAccountDisconnectDeliveryV1",
            owner_id,
            deliveryId=delivery_id,
            delivered=delivered,
            reasonCode=reason_code,
        )

    def navid_daily_start(self, owner_id: int, *, date: str, refresh: bool = False) -> dict:
        return self.request("navidDailyStart", owner_id, date=date, refresh=refresh)

    def navid_status(self, owner_id: int) -> dict:
        return self.request("navidStatus", owner_id)

    def navid_daily_complete(self, owner_id: int, *, date: str, captcha_code: str) -> dict:
        return self.request("navidDailyComplete", owner_id, date=date, captchaCode=captcha_code)

    def claim_navid_group_deliveries(self, owner_id: int, *, limit: int = 3) -> dict:
        return self.request("claimNavidGroupDeliveriesV1", owner_id, limit=limit)

    def ack_navid_group_delivery(
        self,
        owner_id: int,
        delivery_id: str,
        *,
        delivered: bool,
        reason_code: str = "",
    ) -> dict:
        return self.request(
            "ackNavidGroupDeliveryV1",
            owner_id,
            deliveryId=delivery_id,
            delivered=delivered,
            reasonCode=reason_code,
        )

    def create_bot_payment(
        self,
        user_id: int,
        *,
        offer_ref: str,
        title: str,
        amount_rials: int,
        request_id: str,
        description: str = "",
        product_version: int = 1,
        available_from: str = "",
        expires_at: str = "",
        capacity: int = 0,
        max_per_user: int = 1,
        fulfillment: dict | None = None,
    ) -> dict:
        return self.request(
            "createBotPayment",
            user_id,
            contractVersion="bot-commerce-v2",
            offerRef=offer_ref,
            title=title,
            description=description,
            amountRials=amount_rials,
            requestId=request_id,
            productVersion=max(1, int(product_version)),
            availableFrom=available_from,
            expiresAt=expires_at,
            capacity=max(0, int(capacity)),
            maxPurchasesPerUser=max(0, int(max_per_user)),
            fulfillment=fulfillment or {},
        )

    def payment_status(self, user_id: int, order_token: str) -> dict:
        return self.request("paymentStatus", user_id, orderToken=order_token)

    def payment_product_states(self, user_id: int, offer_refs: list[str]) -> dict:
        return self.request(
            "paymentProductStatesV2", user_id, contractVersion="bot-commerce-v2", offerRefs=offer_refs[:200]
        )

    def payment_owner_dashboard(self, user_id: int) -> dict:
        return self.request("paymentOwnerDashboardV2", user_id, contractVersion="bot-commerce-v2")

    def payment_product_report(
        self, user_id: int, *, offer_ref: str, date_from: str = "", date_to: str = ""
    ) -> dict:
        return self.request(
            "paymentProductReportV2", user_id, contractVersion="bot-commerce-v2", offerRef=offer_ref,
            dateFrom=date_from, dateTo=date_to,
        )

    def payment_transactions(
        self,
        user_id: int,
        *,
        query: str = "",
        offer_ref: str = "",
        status: str = "",
        platform: str = "",
        gateway: str = "",
        date_from: str = "",
        date_to: str = "",
        page: int = 0,
        limit: int = 20,
    ) -> dict:
        return self.request(
            "paymentTransactionsV2", user_id, contractVersion="bot-commerce-v2", query=query,
            offerRef=offer_ref, status=status, originPlatform=platform, gateway=gateway,
            dateFrom=date_from, dateTo=date_to, page=max(0, int(page)), limit=max(1, min(100, int(limit))),
        )

    def payment_directory(self, user_id: int, *, query: str = "", limit: int = 100) -> dict:
        return self.request(
            "paymentDirectoryV2", user_id, contractVersion="bot-commerce-v2", query=query,
            limit=max(1, min(500, int(limit))),
        )

    def payment_transaction(self, user_id: int, *, order_id: int) -> dict:
        return self.request(
            "paymentTransactionV2", user_id, contractVersion="bot-commerce-v2", orderId=max(1, int(order_id))
        )

    def payment_update_transaction_status(
        self, user_id: int, *, order_id: int, status: str, note: str
    ) -> dict:
        return self.request(
            "paymentUpdateTransactionStatusV2", user_id, contractVersion="bot-commerce-v2",
            orderId=max(1, int(order_id)), status=status, note=note,
        )

    def student_assistant_summary(self, user_id: int) -> dict:
        return self.request(
            "studentAssistantSummaryV1",
            user_id,
            contractVersion="student-assistant-v1",
        )

    def perform_integration_action(self, user_id: int, *, action_ref: str, request_id: str) -> dict:
        return self.request(
            "performIntegrationActionV1",
            user_id,
            contractVersion="student-assistant-v1",
            actionRef=action_ref,
            requestId=request_id,
        )

    def integration_challenge_answer(
        self,
        user_id: int,
        *,
        challenge_ref: str,
        job_ref: str,
        answer: str,
        request_id: str,
    ) -> dict:
        return self.request(
            "integrationChallengeAnswerV1",
            user_id,
            contractVersion="student-assistant-v1",
            challengeRef=challenge_ref,
            jobRef=job_ref,
            answer=answer,
            requestId=request_id,
        )

    def claim_payment_result_deliveries(self, owner_id: int, *, limit: int = 10) -> dict:
        return self.request(
            "claimPaymentResultDeliveriesV1",
            owner_id,
            contractVersion="bot-payment-return-v1",
            limit=limit,
        )

    def ack_payment_result_delivery(
        self,
        owner_id: int,
        delivery_id: str,
        *,
        delivered: bool,
        reason_code: str = "",
    ) -> dict:
        return self.request(
            "ackPaymentResultDeliveryV1",
            owner_id,
            contractVersion="bot-payment-return-v1",
            deliveryId=delivery_id,
            delivered=delivered,
            reasonCode=reason_code,
        )

    def ack_payment_result_deliveries(self, owner_id: int, results: list[dict]) -> dict:
        return self.request(
            "ackPaymentResultDeliveriesV1",
            owner_id,
            contractVersion="bot-payment-return-v1",
            results=results,
        )

    def exam_hub(self, user_id: int) -> dict:
        """Return the canonical website-backed exam view for this linked user."""
        return self.request("examHubV1", user_id, contractVersion="exam-bot-v1")

    def exam_owner_hub(self, user_id: int) -> dict:
        return self.request("examOwnerHubV1", user_id, contractVersion="exam-bot-v1")

    def perform_exam_action(self, user_id: int, *, action_ref: str, request_id: str) -> dict:
        """Execute one opaque, user-bound action; no price/answer data comes from callbacks."""
        return self.request(
            "performExamActionV1",
            user_id,
            contractVersion="exam-bot-v1",
            actionRef=action_ref,
            requestId=request_id,
        )

    def set_grade(self, user_id: int, *, student_number: str, course_label: str, max_score: str, score: str) -> dict:
        return self.request(
            "setGrade",
            user_id,
            studentNumber=student_number,
            courseLabel=course_label,
            maxScore=max_score,
            score=score,
        )
