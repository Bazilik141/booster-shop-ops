# Codex Report — NCRM-14 round 2: temporary Telegram smoke alert

Date: 2026-07-29
Codex config: model=Sol · effort=xhigh

## Scope

Implemented the round-2 handoff as a temporary, payment-type-gated Telegram alert in the existing Supabase `order-sync` Edge Function.

- Alerts only for `credit_mono_3/4/5` and `credit_pumb_3/4/5`.
- Reports OpenCart order ID, matched `payment_type_code`, and RPC success or error.
- Uses `EdgeRuntime.waitUntil(...)` so notification delivery is a background task and does not delay the OpenCart response.
- Missing secrets, Telegram HTTP failures, network errors, or background-task registration errors warn and fail open.
- No migration, PHP integration, payment mapping, `rpcPayload` field set, deployment, secret value, Notion property, or roadmap status was changed.

## Files touched

```text
ncrm/supabase/functions/order-sync/index.ts
diagnostics/NCRM-14_telegram-smoke-alert_report_20260729.md
```

The implementation diff is limited to `index.ts`; this report is the required risky-handoff evidence artifact.

## Secret-name preflight

Repository search found no existing Supabase Edge Function use of `TELEGRAM_BOT_TOKEN` or `TELEGRAM_CHAT_ID`. `TELEGRAM_BOT_TOKEN` exists in the separate Google Apps Script runtime, where it has the same bot-token purpose; `TELEGRAM_CHAT_ID` is new. No secret values were read or written.

## Local checks

```text
TypeScript 6.0.3 transpile syntax check: OK
Focused mocked order-sync smoke: OK (6 scenarios)
git diff --check: OK
```

Smoke scenarios:

1. MONO success: exactly one Telegram request; message contains order ID, `credit_mono_3`, and `OK`.
2. PUMB RPC failure: exactly one Telegram request; message contains `credit_pumb_4` and the RPC error message; HTTP response remains 422 as before.
3. Non-credit order: zero Telegram requests.
4. Telegram HTTP 401: order-sync response remains successful; one warning only.
5. Missing Telegram secrets: zero Telegram requests; order-sync response remains successful; one warning only.
6. Telegram network exception: order-sync response remains successful; warning does not expose the bot token.

No Deno/Supabase local-runtime or production smoke was run. This environment has no Deno executable, and no deployment was authorized.

## PHP syntax

Not applicable; no PHP file was changed.

## Idempotency

The code schedules at most one alert per eligible `order-sync` invocation and zero alerts for other payment types. It does not deduplicate repeated invocations of the same OpenCart order; a repeated sync can produce another temporary diagnostic alert.

## Rollback

Remove `notifyCreditOrderSync`, `scheduleCreditOrderSyncNotification`, `syncedPaymentTypeCode`, and the two notification call sites, restoring the 2026-07-28 round-1 `index.ts`, then redeploy `order-sync`. No database rollback is required. Telegram secrets may be removed separately after the temporary alert is retired.

## Owner deployment gate

1. Add `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` in Supabase Edge Function secrets, using the existing order-notification bot/chat. Do not paste values into Git, diagnostics, or chat.
2. From the repository's `ncrm` directory, deploy only the Edge Function:

```bash
npx supabase functions deploy order-sync
```

## Post-deploy QA checklist

- [ ] A real MONO 3/4/5 installment order produces exactly one alert with the correct order ID, code, and `OK`.
- [ ] A real non-credit order produces no alert.
- [ ] A temporary invalid-token test changes only the warning log, not the RPC result or OpenCart response; restore the valid secret immediately.
- [ ] When real PUMB traffic exists, a PUMB 3/4/5 order produces exactly one correct alert.
- [ ] After MONO and PUMB evidence is captured, explicitly authorize removal of this temporary block and its secrets.

## Side effects and unresolved risks

- Real Supabase background execution and Telegram delivery remain unverified until owner deployment and real-order QA.
- The round-1 PUMB `payment_method_code` heuristic remains unconfirmed until the first real PAY-002 order.
- Telegram delivery is best-effort; Edge Function limits or Telegram outages can still prevent an alert, while order ingestion continues.
- This diagnostic block is intentionally temporary and must not be treated as task closure or a permanent notification feature.
