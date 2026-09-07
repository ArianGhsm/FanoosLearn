# Stage 7 — Telegram / Bale Feature Parity

| Feature | Telegram | Bale | Status |
| --- | --- | --- | --- |
| secure account-link consume | implemented | implemented | IMPLEMENTED |
| workspace list/select | implemented | implemented | IMPLEMENTED |
| payment order/status | implemented | implemented | IMPLEMENTED |
| notification lease/receipt | implemented | implemented | IMPLEMENTED |
| protected structured delivery | `protect_content` + receipt | fail closed when protection required | IMPLEMENTED / UNSUPPORTED_OFFICIAL_API |
| protected PDF derivative | enqueue only; final artifact contract missing | fail closed | PARTIAL |
| schedule/grades/announcements direct read | internal projection absent | internal projection absent | PARTIAL |
| resources list | web fallback; internal projection absent | web fallback | PARTIAL |
| `/start` account-link payload | bounded opaque payload | not assumed | IMPLEMENTED / UNSUPPORTED_OFFICIAL_API |
| Update Server | private Telegram + two-step + canonical control plane | intentionally absent | IMPLEMENTED / NOT ENABLED |
| live messenger smoke | scripts provided | scripts provided | RUNTIME_VALIDATION_REQUIRED |

`file_id`, prior send success and chat-visible payment text never grant access. Every protected delivery is freshly issued and consumed before send.
