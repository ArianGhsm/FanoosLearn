# Stage 7 — Telegram / Bale Feature Parity

Status: **SOURCE INTEGRATION COMPLETE / RUNTIME VALIDATION REQUIRED**

| Feature | Telegram | Bale | Status |
| --- | --- | --- | --- |
| secure account-link consume | implemented | implemented | IMPLEMENTED |
| signed subject unlink/revoke | implemented | implemented | IMPLEMENTED |
| workspace list/select | implemented | implemented | IMPLEMENTED |
| schedule today/tomorrow | native canonical read | native canonical read | IMPLEMENTED |
| self grades | native canonical read | native canonical read | IMPLEMENTED |
| announcements | native canonical read | native canonical read | IMPLEMENTED |
| resources list | native authorized catalog | native authorized catalog | IMPLEMENTED |
| payment order/status | implemented | implemented | IMPLEMENTED |
| notification lease/receipt | implemented | implemented | IMPLEMENTED |
| protected structured delivery | `protect_content` + receipt | fail closed when protection required | IMPLEMENTED / UNSUPPORTED_OFFICIAL_API |
| protected PDF derivative | canonical enqueue → worker → derivative issue/redeem → protected document send | fail closed when forward protection is required | IMPLEMENTED / UNSUPPORTED_OFFICIAL_API |
| `/start` account-link payload | bounded opaque payload | not assumed | IMPLEMENTED / UNSUPPORTED_OFFICIAL_API |
| Update Server overview/request/status | private Telegram, permission-aware overview + two-step request | intentionally absent | IMPLEMENTED / NOT ENABLED |
| live messenger/worker smoke | source scripts/templates ready | source scripts/templates ready | RUNTIME_VALIDATION_REQUIRED |

## Canonical boundaries

Bots hold only disposable transport correlation such as update offsets, receipt retry state, deployment request references and a bounded `job_id → issuance/workspace` media correlation. None of those grant access. Every workspace/read/delivery/derivative operation is re-authorized by the Platform API.

Protected PDF delivery never receives a storage path, bucket key or unrestricted object credential. The worker redeems only the leased source capability, authorizes exact checksum/size/MIME, uploads exact HMAC-signed PDF bytes, and completes with the Platform-generated `pma:<uuid>` artifact reference. Bot retrieval uses a short-lived user/workspace/platform-bound artifact capability. There is no original-source fallback.

`file_id`, prior provider send success, local SQLite state and chat-visible payment text never grant entitlement.
