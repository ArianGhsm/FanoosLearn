# FANOOS UI V2 — Telegram Presentation

Verification date: 2026-09-09.

The official Telegram Bot API currently reports Bot API 10.3 (2026-08-24). The verified surface includes Rich Messages, headings/lists/tables/quotations, RTL, inline keyboards, Rich drafts, Rich edits and `protect_content`.

## V2 use

- Semantic `title` → Rich heading.
- Breadcrumb → short Rich paragraph.
- Severity intro → quotation/status block.
- Facts/lists → Rich lists.
- Sections → section headings + paragraphs/lists.
- True matrices may use native Rich tables; bot product screens do not fabricate ASCII tables.
- `is_rtl=true` for Persian semantic screens.
- Inline keyboard remains the action layer.
- Callback data remains <=64 bytes.
- Navigation/status screens may edit when safe.

## Fallback invariant

Rich presentation is not a business operation. `send_screen()` may attempt:

```text
sendRichMessage
→ if presentation/provider failure
sendMessage with the same already-computed Screen
```

The application callback/use case is not replayed. Edit failure similarly becomes a new presentation send rather than a second business mutation.

## Activity

- Callback is acknowledged before application work.
- Short synchronous work uses chat action.
- Unknown-duration work may use one changing Rich draft after the configured threshold.
- Percent is shown only when a real measurable total exists.
- No ETA is fabricated.

## Protected content

Security overrides Rich preference. When the result is protected, the transport preserves the required protection contract and avoids an ambiguous Rich/plain dual operation. Personalized PDF delivery remains issue/redeem/receipt-authorized by the platform; cached provider file IDs never bypass reauthorization.
