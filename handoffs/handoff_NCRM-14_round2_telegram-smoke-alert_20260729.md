# Codex Handoff — NCRM-14 round 2: temporary Telegram smoke-alert for MONO/ПУМБ order-sync

Date: 2026-07-29 | Owner: Raccoon | Planner: Claude | Executor: Codex
Related: `handoffs/handoff_NCRM-14_order-sync-pumb-payment-types_20260726.md` (round 1, deployed 2026-07-28), `diagnostics/NCRM-14_order-sync-pumb-discount_report_20260726.md`

> ⚠️ HIGH-RISK in scope: this touches `ncrm/supabase/functions/order-sync/index.ts`, the live order-ingest path for every real OpenCart order. The addition itself is small and isolated, but the file is the same one that silently failed for real orders during the CHECKOUT-002/NCRM-10 incident (`handoffs/handoff_CHECKOUT002-NCRM10_silent-sync-failure-continuity_20260719.md`) — treat "doesn't throw locally" as insufficient proof; require the real-order QA in §8. See `bs-checkout-smoke` conventions for how this project treats payment-adjacent smoke tests.

## 1. Task ID
NCRM-14 round 2 — add a **temporary**, narrowly-scoped Telegram alert to `order-sync` so the owner gets pinged (success or failure) the moment a real MONO or ПУМБ installment order syncs, instead of having to check Supabase Function logs manually. This is diagnostic tooling to close out NCRM-14, not a permanent feature.

## 2. Context
NCRM-14 round 1 (ПУМБ payment types + `discount_total` fix) passed Claude review and was deployed 2026-07-28: migration `0014` pushed to cloud, `order-sync` Edge Function redeployed. The owner has no easy way to confirm a real MONO/ПУМБ order actually lands with the correct `payment_type_code` without opening Supabase Dashboard → Functions → Logs each time. Owner explicitly asked (2026-07-29) for a push notification instead, and — when offered a permanent-vs-temporary choice — chose **temporary**: just enough to get one confirmed MONO alert and one confirmed ПУМБ alert, then remove it.

The project already has a Telegram integration for order notifications, but it lives entirely on the OpenCart/PHP side (`extension/telegram_notify/catalog/controller/event/telegram.php`, called from `catalog/model/checkout/order.php`). `order-sync` is a separate Supabase Edge Function (Deno/TypeScript) with no code-sharing path to that PHP integration — this task adds an independent, minimal Telegram Bot API call from inside `order-sync` itself, reusing the **same bot/chat** the owner already uses (owner supplies the token/chat id as new Supabase secrets; Codex/Claude never see or log the value).

## 3. Goal
1. After `order-sync`'s existing `fn_ingest_opencart_order` RPC call resolves (success or error), if the computed `payment_type_code` matches `credit_mono_3/4/5` or `credit_pumb_3/4/5`, send exactly one Telegram message reporting: OpenCart order id, the matched `payment_type_code`, and success or the RPC error message.
2. Every other `payment_type_code` (`acquiring`, `bank_details`, `fop_control`, `postpay_monobazar`) produces **zero** Telegram messages — this is not a general order-notification feature.
3. A Telegram send failure (bad token, network error, rate limit) must never change `order-sync`'s existing behavior or response to OpenCart — fire-and-forget with its own try/catch, matching the "best-effort, never blocks the others" pattern already used for Telegram/CRM notifications elsewhere in this codebase (see `patches/CHECKOUT-002_async-order-side-effects_20260719.php`).
4. This is explicitly temporary. Do not build it as a permanent feature or generalize it — a follow-up round (on direct owner request, per standing Codex commit/push rules) removes this block entirely once the owner has what they need.

## 4. What to change
**In `ncrm/supabase/functions/order-sync/index.ts`:**
- Add a small function, e.g. `notifyCreditOrderSync(orderId: string, paymentTypeCode: string, ok: boolean, errorMessage?: string): Promise<void>`, called once right after the existing `supabase.rpc("fn_ingest_opencart_order", ...)` call resolves — in both the `if (error)` branch and the success branch — gated by `/^credit_(mono|pumb)_[345]$/.test(paymentTypeCode)`.
- Read `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` from `Deno.env.get(...)` (new Supabase Function secrets — confirm these exact names aren't already used for something else in this project before assuming them; `currency-rates-fetch/index.ts` is the only other function and uses no Telegram vars today, so this is likely the first use).
- Send via a plain `fetch(\`https://api.telegram.org/bot${token}/sendMessage\`, { method: "POST", ... })` with `chat_id` and `text` — no new dependency/library needed.
- Wrap the whole notify call in try/catch; on failure, `console.warn` (same style as the existing `discount_total` fallback warning already in this file) and continue — never `throw`, never alter the JSON response already being returned to OpenCart.
- If `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID` are missing, log a warning once and skip sending — do not fail the function or the order sync itself.
- Message text (Ukrainian, plain, no secrets): OpenCart order id, matched `payment_type_code`, and either "OK" or the RPC `error.message`.

## 5. What NOT to touch
- Any other `payment_type_code` branch — no alert for `acquiring`/`bank_details`/`fop_control`/`postpay_monobazar`.
- `paymentTypeCode()`, `discountTotal()`, the `rpcPayload` shape, `validatePayload()` — already reviewed and live since 2026-07-28, unrelated to this task.
- `ncrm/supabase/migrations/0014_ncrm11_pumb_payment_types.sql` and all other migrations — no schema change in this task.
- The existing PHP Telegram integration (`extension/telegram_notify/...`, `catalog/model/checkout/order.php`, `system/library/booster_async_queue.php`) — separate runtime, completely out of scope.
- Do not generalize this to other payment types or make it permanent without a new, explicit owner request — this round's whole point is that it gets removed.
- Standard protected zones (untouched, listed for completeness): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout/payment UI itself, fiscalization, Merchant feed, storefront schema.org markup.

## 6. Likely files / areas
- `ncrm/supabase/functions/order-sync/index.ts` (extend only).
- New Supabase Function secrets `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` — owner adds these directly in Supabase (Dashboard → Edge Functions → Secrets, or `npx supabase secrets set ...`), **not** part of this diff. Codex should verify no existing secret with a conflicting name/purpose exists before assuming these names are free.

## 7. Acceptance criteria
- [ ] A real order with `payment_type_code` `credit_mono_3/4/5` triggers exactly one Telegram message with correct order id and outcome.
- [ ] A real order with `payment_type_code` `credit_pumb_3/4/5` does the same (verify once real ПУМБ traffic exists, post-PAY-002).
- [ ] A real order with any other `payment_type_code` produces zero Telegram messages.
- [ ] A deliberately invalid `TELEGRAM_BOT_TOKEN` (test only, not committed) does not change `order-sync`'s response or the RPC outcome — only a `console.warn` appears.
- [ ] `git diff` touches only `ncrm/supabase/functions/order-sync/index.ts`.
- [ ] No secret values appear anywhere in the diff, report, or commit message.

## 8. QA / smoke test (owner)
Per `bs-checkout-smoke` conventions — this touches the live order pipeline, so treat "no exception thrown locally" as unproven until a real order confirms it:
- [ ] Add `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` as Supabase Function secrets, using the same bot/chat already used for order notifications.
- [ ] Redeploy `order-sync` (`npx supabase functions deploy order-sync`).
- [ ] On the next real MONO order, confirm the Telegram message arrives with the correct order id and "OK".
- [ ] Confirm a normal non-credit order (COD / Hutko / bank transfer) does **not** produce a Telegram message.
- [ ] Once satisfied for MONO — and, whenever real ПУМБ traffic exists, for ПУМБ too — explicitly tell Codex to remove this block in a follow-up round. Do not leave it running indefinitely; this was an explicit temporary-only decision (2026-07-29).

## 9. Rollback note
Pure, isolated code addition inside `order-sync/index.ts` (one new function + two call sites). To remove: revert the file to its 2026-07-28 post-round-1 version and redeploy — no migration, no schema, no PHP-side change involved. If the Telegram secrets are missing or wrong, the function must fail open (log and continue) rather than fail the order sync — confirm this explicitly in the completion report.

## 10. Recommended status after execution
NCRM-14 stays **In progress**. This round does not close the task by itself — close NCRM-14 only after: (1) a real MONO order confirms correct sync via this alert or a manual log check, (2) the ПУМБ branch is verified on a real order once PAY-002 ships, and (3) this temporary alert code has been explicitly removed per the owner's decision above.
