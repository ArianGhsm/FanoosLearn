from __future__ import annotations

import hashlib
import hmac
import json
import secrets
import socket
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any, Callable, Mapping


class FanoosContractError(RuntimeError):
    """Backend response is incompatible with the frozen internal-v1 contract."""


@dataclass(slots=True)
class FanoosApiError(RuntimeError):
    code: str
    message: str
    status: int
    request_id: str | None = None
    retry_after: float | None = None

    def __str__(self) -> str:
        return f"{self.code}: {self.message}"


Transport = Callable[[str, bytes, Mapping[str, str], float], tuple[int, bytes, Mapping[str, str]]]


class FanoosApiClient:
    """Exact-body signed client for the FANOOS internal-v1 service API."""

    API_VERSION = "internal-v1"
    RETRYABLE_STATUS = {429, 502, 503, 504}

    def __init__(
        self,
        origin: str,
        key_id: str,
        service_secret: str,
        *,
        timeout_seconds: float = 10.0,
        max_safe_attempts: int = 3,
        transport: Transport | None = None,
        clock: Callable[[], float] = time.time,
        nonce_factory: Callable[[], str] | None = None,
        sleeper: Callable[[float], None] = time.sleep,
    ) -> None:
        parsed = urllib.parse.urlsplit(origin.strip())
        if parsed.scheme not in {"https", "http"} or not parsed.netloc:
            raise ValueError("FANOOS API origin must be an absolute HTTP(S) origin")
        if parsed.path not in {"", "/"} or parsed.query or parsed.fragment:
            raise ValueError("FANOOS API origin must not contain a path, query, or fragment")
        if not key_id.strip() or len(key_id) > 160:
            raise ValueError("FANOOS service key id is invalid")
        if len(service_secret.encode("utf-8")) < 16:
            raise ValueError("FANOOS service secret is too short")
        if timeout_seconds <= 0 or max_safe_attempts < 1 or max_safe_attempts > 5:
            raise ValueError("Invalid FANOOS client timeout/retry configuration")

        self.origin = f"{parsed.scheme}://{parsed.netloc}"
        self.key_id = key_id.strip()
        self._secret = service_secret.encode("utf-8")
        self.timeout_seconds = timeout_seconds
        self.max_safe_attempts = max_safe_attempts
        self._transport = transport or self._urllib_transport
        self._clock = clock
        self._nonce_factory = nonce_factory or (lambda: secrets.token_urlsafe(18))
        self._sleeper = sleeper

    @staticmethod
    def encode_body(payload: Mapping[str, Any]) -> bytes:
        return json.dumps(
            payload,
            ensure_ascii=False,
            sort_keys=True,
            separators=(",", ":"),
            allow_nan=False,
        ).encode("utf-8")

    def signed_headers(self, method: str, path: str, body: bytes) -> dict[str, str]:
        method = method.upper()
        self._validate_path(path)
        timestamp = str(int(self._clock()))
        nonce = self._nonce_factory()
        if not nonce or len(nonce) > 160 or any(ch.isspace() for ch in nonce):
            raise ValueError("Nonce factory returned an invalid nonce")
        digest = hashlib.sha256(body).hexdigest()
        canonical = "\n".join(("fanoos-service-v1", method, path, timestamp, nonce, digest))
        signature = hmac.new(self._secret, canonical.encode("utf-8"), hashlib.sha256).hexdigest()
        return {
            "Accept": "application/json",
            "Content-Type": "application/json; charset=utf-8",
            "X-Fanoos-Key-Id": self.key_id,
            "X-Fanoos-Timestamp": timestamp,
            "X-Fanoos-Nonce": nonce,
            "X-Fanoos-Content-SHA256": digest,
            "X-Fanoos-Signature": signature,
        }

    def post(
        self,
        path: str,
        payload: Mapping[str, Any],
        *,
        safe_to_retry: bool = False,
    ) -> Any:
        self._validate_path(path)
        body = self.encode_body(payload)
        attempts = self.max_safe_attempts if safe_to_retry else 1
        last_network_error: Exception | None = None

        for attempt in range(1, attempts + 1):
            headers = self.signed_headers("POST", path, body)
            try:
                status, raw, response_headers = self._transport(
                    self.origin + path, body, headers, self.timeout_seconds
                )
            except (urllib.error.URLError, TimeoutError, socket.timeout, OSError) as exc:
                last_network_error = exc
                if attempt >= attempts:
                    raise FanoosApiError(
                        "backend_unavailable", "FANOOS backend is unavailable.", 503
                    ) from exc
                self._sleeper(self._backoff(attempt, None))
                continue

            envelope = self._decode_envelope(raw, status)
            request_id = self._request_id(envelope)
            if status < 400 and envelope.get("ok") is True:
                self._validate_success(envelope)
                return envelope["data"]

            error = self._api_error(envelope, status, response_headers)
            if safe_to_retry and status in self.RETRYABLE_STATUS and attempt < attempts:
                self._sleeper(self._backoff(attempt, error.retry_after))
                continue
            error.request_id = request_id
            raise error

        raise FanoosApiError(
            "backend_unavailable", "FANOOS backend is unavailable.", 503
        ) from last_network_error

    def consume_link(self, platform: str, subject: str, challenge_token: str) -> Any:
        return self.post(
            "/api/internal/v1/messaging/link-challenges/consume",
            {"platform": platform, "challenge_token": challenge_token, "subject": subject},
        )

    def list_workspaces(self, platform: str, subject: str) -> Any:
        return self.post(
            "/api/internal/v1/messaging/workspaces/list",
            {"platform": platform, "subject": subject},
            safe_to_retry=True,
        )

    def select_workspace(self, platform: str, subject: str, workspace_id: str) -> Any:
        return self.post(
            "/api/internal/v1/messaging/workspaces/select",
            {"platform": platform, "subject": subject, "workspace_id": workspace_id},
        )

    def create_order(
        self, platform: str, subject: str, workspace_id: str, product_id: str, idempotency_key: str
    ) -> Any:
        return self.post(
            "/api/internal/v1/commerce/orders",
            {
                "platform": platform,
                "subject": subject,
                "workspace_id": workspace_id,
                "product_id": product_id,
                "idempotency_key": idempotency_key,
            },
            safe_to_retry=True,
        )

    def order_status(self, platform: str, subject: str, workspace_id: str, order_id: str) -> Any:
        return self.post(
            "/api/internal/v1/commerce/orders/status",
            {"platform": platform, "subject": subject, "workspace_id": workspace_id, "order_id": order_id},
            safe_to_retry=True,
        )

    def project_notifications(self) -> Any:
        return self.post("/api/internal/v1/notifications/project", {}, safe_to_retry=False)

    def claim_notification(self, platform: str) -> Any:
        return self.post(
            "/api/internal/v1/notifications/claim", {"platform": platform}, safe_to_retry=False
        )

    def notification_receipt(self, payload: Mapping[str, Any]) -> Any:
        return self.post(
            "/api/internal/v1/notifications/receipt", payload, safe_to_retry=True
        )

    def issue_delivery(self, platform: str, subject: str, workspace_id: str, resource_id: str) -> Any:
        return self.post(
            "/api/internal/v1/deliveries/issue",
            {"platform": platform, "subject": subject, "workspace_id": workspace_id, "resource_id": resource_id},
        )

    def consume_delivery(
        self, platform: str, subject: str, workspace_id: str, delivery_token: str
    ) -> Any:
        return self.post(
            "/api/internal/v1/deliveries/consume",
            {
                "platform": platform,
                "subject": subject,
                "workspace_id": workspace_id,
                "delivery_token": delivery_token,
            },
        )

    def delivery_receipt(self, payload: Mapping[str, Any]) -> Any:
        return self.post("/api/internal/v1/deliveries/receipt", payload, safe_to_retry=True)

    def enqueue_protected_media(
        self,
        workspace_id: str,
        issuance_id: str,
        renderer_algorithm_version: str,
        limits: Mapping[str, int] | None = None,
    ) -> Any:
        return self.post(
            "/api/internal/v1/protected-media/enqueue",
            {
                "workspace_id": workspace_id,
                "issuance_id": issuance_id,
                "renderer_algorithm_version": renderer_algorithm_version,
                "limits": dict(limits or {}),
            },
            safe_to_retry=True,
        )

    def claim_protected_media(self) -> Any:
        return self.post("/api/internal/v1/protected-media/claim", {}, safe_to_retry=False)

    def complete_protected_media(self, payload: Mapping[str, Any]) -> Any:
        return self.post("/api/internal/v1/protected-media/complete", payload, safe_to_retry=True)

    def fail_protected_media(self, payload: Mapping[str, Any]) -> Any:
        return self.post("/api/internal/v1/protected-media/fail", payload, safe_to_retry=True)

    def request_deployment(
        self, subject: str, target_key: str, idempotency_key: str
    ) -> Any:
        return self.post(
            "/api/internal/v1/deployments/request",
            {
                "platform": "telegram",
                "subject": subject,
                "target_key": target_key,
                "idempotency_key": idempotency_key,
            },
            safe_to_retry=True,
        )

    def deployment_status(self, subject: str, request_id: str) -> Any:
        return self.post(
            "/api/internal/v1/deployments/status",
            {"platform": "telegram", "subject": subject, "request_id": request_id},
            safe_to_retry=True,
        )

    @staticmethod
    def _validate_path(path: str) -> None:
        if not path.startswith("/api/internal/v1/") or "?" in path or "#" in path or "\n" in path:
            raise ValueError("Internal API path is invalid")

    @classmethod
    def _decode_envelope(cls, raw: bytes, status: int) -> dict[str, Any]:
        try:
            value = json.loads(raw.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as exc:
            raise FanoosContractError(f"Backend returned non-JSON response (HTTP {status})") from exc
        if not isinstance(value, dict):
            raise FanoosContractError("Backend envelope must be a JSON object")
        meta = value.get("meta")
        if not isinstance(meta, dict) or meta.get("api_version") != cls.API_VERSION:
            raise FanoosContractError("Backend API version/envelope mismatch")
        return value

    @staticmethod
    def _request_id(envelope: Mapping[str, Any]) -> str | None:
        meta = envelope.get("meta")
        if isinstance(meta, Mapping) and isinstance(meta.get("request_id"), str):
            return str(meta["request_id"])
        return None

    @staticmethod
    def _validate_success(envelope: Mapping[str, Any]) -> None:
        if "data" not in envelope or envelope.get("ok") is not True:
            raise FanoosContractError("Malformed success envelope")

    @classmethod
    def _api_error(
        cls, envelope: Mapping[str, Any], status: int, headers: Mapping[str, str]
    ) -> FanoosApiError:
        error = envelope.get("error")
        if not isinstance(error, Mapping) or not isinstance(error.get("code"), str) or not isinstance(error.get("message"), str):
            raise FanoosContractError("Malformed error envelope")
        retry_after: float | None = None
        raw_retry = headers.get("Retry-After") or headers.get("retry-after")
        if raw_retry:
            try:
                retry_after = max(0.0, min(60.0, float(raw_retry)))
            except ValueError:
                retry_after = None
        return FanoosApiError(str(error["code"]), str(error["message"]), status, retry_after=retry_after)

    @staticmethod
    def _backoff(attempt: int, retry_after: float | None) -> float:
        if retry_after is not None:
            return retry_after
        return min(2.0, 0.2 * (2 ** (attempt - 1)))

    @staticmethod
    def _urllib_transport(
        url: str, body: bytes, headers: Mapping[str, str], timeout: float
    ) -> tuple[int, bytes, Mapping[str, str]]:
        request = urllib.request.Request(url, data=body, headers=dict(headers), method="POST")
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                return response.status, response.read(), dict(response.headers.items())
        except urllib.error.HTTPError as exc:
            return exc.code, exc.read(), dict(exc.headers.items())
