# Codex Handoff — NCRM-10 round 4: fix order_id type mismatch in order-sync validation

Date: 2026-07-19 | Parent: NCRM-10 (round 1 + round 2 + round 3 live) | Evidence: owner-provided Supabase Invocations log + Claude code review

## 1. Task ID
NCRM-10 round 4 — the Edge Function rejects every real order with HTTP 400 `invalid OpenCart order identifier`, unrelated to the round-3 timeout change. Root cause: a type mismatch between what the PHP bridge sends and how the function reads it.

## 2. Context
Three real test orders were placed today through the actual storefront checkout:
- `#251` (07:57:40): Edge Function not deployed yet — clean `404`, `execution_time_ms: 115`. Resolved by deployment, not relevant anymore.
- `#254` (09:01:03, before round 3): owner queried Supabase Invocations directly — `POST | 400 | .../order-sync`, `execution_time_ms: 1273`, `request.headers.content_length: 1471` (request), `response.headers.content_length: 65` (response). This is a **fast, real response**, not a timeout or hang — the function received the request and explicitly rejected it in 1.27s.
- `#255` (09:47:24, after round 3's timeout bump to 3s/8s): same pattern in the function's own console log — `booted` then `shutdown` ~75s later (this gap is very likely the runtime's own idle-isolate teardown logging, not request-processing time — it was identical before and after the round-3 timeout change, which argues against the round-3 diagnosis). Invocations row for `#255` had not appeared yet at last check (row for `#254` also took time to become queryable, so this is most likely log-indexing lag, not proof of a hang).

Conclusion so far: round 3's "cold start needs more time" theory does not fit the evidence — `#254` proves the function answers quickly and explicitly rejects the payload. Claude reviewed the source and found a concrete type bug:

- `system/library/booster_crm_sync.php` → `buildPayload()` sends `'order_id' => (int) $order['order_id']` — this JSON-encodes as a **number** (e.g. `255`), not a string.
- `ncrm/supabase/functions/order-sync/index.ts` → `validatePayload()` derives `const orderId = text(payload.order_id);` where `text()` is defined as `typeof value === "string" ? value.trim() : ""` — for a JSON **number** input this always returns `""`.
- The very first check in `validatePayload()` is `if (!/^\d+$/.test(orderId) || ...)`. Since `orderId` is always `""` for a real order, this always fails and the function always returns `{"error":"invalid OpenCart order identifier"}` (400) — independent of cold start, independent of which order it is.

**Caveat, stated plainly:** the byte length of that exact error response is 45 bytes (`{"error":"invalid OpenCart order identifier"}`), but the logged `response.headers.content_length` for `#254` was 65 bytes. This is a real, unexplained mismatch — Supabase's Invocations log does not expose the literal response body, only metadata, so this could not be confirmed against the literal text. Treat the order_id type bug as a strong, code-grounded hypothesis, not a 100%-confirmed root cause — see acceptance criteria below for how to close this gap.

## 3. Goal
A real order, using the exact payload shape the live PHP bridge (`buildPayload()`) actually produces, passes `validatePayload()` in `index.ts`, reaches `fn_ingest_opencart_order`, and lands a matching row in `sales`/`sale_items` — with no `NCRM-10 order sync failed` line in `error_log` for that order.

## 4. What to change (scope)
- Target: `ncrm/supabase/functions/order-sync/index.ts` only.
- Fix the `orderId` derivation (currently `const orderId = text(payload.order_id);`, around line 169) so it correctly handles `payload.order_id` whether it arrives as a JSON number or a JSON string, then still produces a plain digit-only string for the existing regex checks and for what gets passed to the RPC as `opencart_order_id`.
- Keep the fix narrowly scoped to this one field. Do not change the shared `text()` helper's behavior for its other callers (`lastname`, `telephone`, `comment`, item `name`/`sku`/`model`, payment/shipping code fields, `order_key`, `event`) unless, after reviewing the full file, a shared fix is genuinely safer — if so, justify that explicitly in the report and confirm no other field's validation behavior changes as a side effect.
- Before finalizing, trace one full realistic payload — matching exactly the field names/types `buildPayload()` in `system/library/booster_crm_sync.php` actually produces (numeric `order_id`, string `order_key`/`order_no`, numeric `quantity`/`price` per item, etc.) — step by step through the fixed `validatePayload()` in the report, and confirm every check now passes.
- Explicitly address the 45-vs-65-byte discrepancy noted in Context: does this fix fully account for it, or does the report need to flag that a second, still-unidentified issue may remain? State this plainly either way — do not guess past what the evidence supports.

## 5. What NOT to touch
- `system/library/booster_crm_sync.php`, `catalog/model/checkout/order.php` — no PHP change this round. The payload PHP sends is valid; the bug is in how the function reads it.
- `ncrm/supabase/migrations/0011_opencart_order_sync.sql`, `0012_opencart_order_sync_partial_insert.sql`, the `fn_ingest_opencart_order` RPC — unaffected, untouched.
- `isTestOrder()` in `index.ts` — unrelated, untouched.
- Round 3's timeout values in `booster_crm_sync.php` (`CURLOPT_CONNECTTIMEOUT => 3`, `CURLOPT_TIMEOUT => 8`, stream `'timeout' => 8`) — leave as-is, not this round's concern.
- Apps Script, `ncrm/supabase/config.toml`, secrets, RLS policies — untouched.
- Standard protected zones, untouched and unaffected by this change: `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess`, the checkout/payment/fiscalization flow itself, Merchant feed, Product schema/JSON-LD. (This round changes order-ingestion validation only — checkout is not blocked by this sender either way, per round 1's best-effort design.)

## 6. Likely files/areas
- `ncrm/supabase/functions/order-sync/index.ts` (fix)
- `diagnostics/NCRM-10_round4_fix-order-id-type-check_report_<date>.md` (new)
- No `config.toml` change expected — flag explicitly in the report if something else genuinely needed touching.

## 7. Acceptance criteria
- [ ] `payload.order_id` sent as a JSON **number** (matching the real PHP bridge) now correctly derives a valid `orderId` string that passes `^\d+$`
- [ ] `payload.order_id` sent as a JSON **string** still also works (no regression)
- [ ] Full realistic payload (matching live `buildPayload()` shape) traced step by step through `validatePayload()` in the report, confirmed passing every check
- [ ] No other field's validation behavior changes, or any shared-helper change is explicitly justified and reviewed for side effects
- [ ] Report explicitly addresses the 45-vs-65-byte discrepancy from Context
- [ ] `git diff` shows only `index.ts` (plus the new report) — nothing else
- [ ] No secret values anywhere in diff/report

## 8. QA / smoke test (owner)
Risk, stated plainly: this changes validation logic in the live order-ingestion path that decides whether a real order lands in the new Supabase CRM. This is not a checkout/payment UI change and cannot block or slow down checkout itself (the sender is best-effort, per round 1's design) — but a mistake here could keep rejecting real orders, or in the worst case wrongly accept malformed data into `sales`. Smoke test after deploy:
- [ ] Place one real, non-test order through the actual storefront checkout.
- [ ] Confirm a matching `sales` row and `sale_items` appear in Supabase — should now be near-immediate, no need to wait minutes.
- [ ] Confirm `error_log` has no new `NCRM-10 order sync failed` line for that order.
- [ ] Re-run the test-filter check (test/TEST wording in product/comment) — confirm still correctly excluded, unaffected by this change.
- [ ] If it still fails: capture the same two diagnostics again — `error_log` tail and the Supabase Invocations row (status code + `execution_time_ms`) for that new order — so the next round isn't starting from zero.

## 9. Rollback note
Single-file TypeScript change on the Edge Function, redeployed (`npx supabase functions deploy order-sync` or dashboard). Rollback = redeploy the pre-round-4 version of `index.ts` from git history (current commit `cc0c11a`). No migration, PHP, or secret change this round, so nothing to restore on the OpenCart server.

## 10. Recommended status after execution
NCRM-10 stays `In progress`. Close only after the §8 real-order retest confirms a successful end-to-end sync with no `error_log` entry and a matching `sales`/`sale_items` row in Supabase.
