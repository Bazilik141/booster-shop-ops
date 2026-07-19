# Codex Handoff — NCRM-10 round 3: increase order-sync HTTP timeout

Date: 2026-07-19 | Parent: NCRM-10 (round 1 + round 2 live) | Evidence: owner-provided `error_log` + Supabase `function_edge_logs` query (see Context)

## 1. Task ID
NCRM-10 round 3 — the live order-sync hook's HTTP call to the Edge Function times out before a cold-started function can respond. One numeric change in already-deployed code, no logic change.

## 2. Context
Round 1's PHP hook is live on the storefront (`catalog/model/checkout/order.php` calls `ncrmOrderSync()`, which calls `NcrmOrderSync::syncOrder()` in `system/library/booster_crm_sync.php`, which POSTs via `postJson()` with `CURLOPT_CONNECTTIMEOUT => 1` and `CURLOPT_TIMEOUT => 2` — see round-1 report for the full method).

Owner evidence from two real test orders today, both placed through the actual storefront checkout:
- Order `#251` (07:57:40 Kyiv, before the Edge Function was deployed): `error_log` shows `NCRM-10 order sync failed order_id=251`. Confirmed via Supabase log query: a `function_edge_logs` row for that exact timestamp shows `POST | 404 | .../order-sync`, `execution_time_ms: 115` — the function genuinely didn't exist yet, fast clean 404.
- Order `#254` (09:01:03 Kyiv, after the Edge Function was deployed): `error_log` again shows `NCRM-10 order sync failed order_id=254`. This time the function's own console logs show `booted (time: 30ms)` at that exact timestamp, then nothing, then `shutdown` ~75 seconds later — **no corresponding row in `function_edge_logs` at all** for that invocation (confirmed: owner queried the full last-24h table, the only row present is the earlier 404). The function started but never produced a logged completed response.

Conclusion: the Edge Function is reachable and functional (round 1/2 code review already confirmed the logic), but a cold-started invocation (first call after deploy/idle — imports `@supabase/supabase-js`, ~732 kB bundle) does not reliably complete within the current 1s connect / 2s total budget. PHP gives up and closes the connection before the function can respond, so the sync is lost even though the function was likely still working.

## 3. Goal
The same real order flow succeeds end-to-end after this change: order placed on the storefront → hook POSTs to Edge Function → function has enough time to cold-start and respond → row lands in `sales`/`sale_items`. Checkout itself must still never hang indefinitely — this is a bigger timeout, not an unbounded one.

## 4. What to change (scope)
- New patch `patches/NCRM-10_round3_increase-sync-timeout_20260719.php`, following the same patch-runner conventions as round 1 (file-exists check, anchor pre-check with expected count, backup to `_patch_backups/`, `php -l`, idempotent marker, self-delete).
- Target: the **already-live** `system/library/booster_crm_sync.php` (contains the `NcrmOrderSync` class from round 1 — do not touch `catalog/model/checkout/order.php`, nothing changes there this round).
- In `postJson()`, change both timeout paths:
  - `CURLOPT_CONNECTTIMEOUT => 1` → `3`
  - `CURLOPT_TIMEOUT => 2` → `8`
  - The `stream_context_create` fallback branch's `'timeout' => 2` → `8` (keep both paths consistent — the fallback only runs if `curl_init` doesn't exist, but should match).
- Anchor on the exact current numeric values so the patch fails loudly (not silently) if round 1's code has since changed rather than accidentally patching the wrong thing.
- Sanity-check against the live PHP `max_execution_time` (commonly 30s on shared hosting, but verify — do not assume) before finalizing 8s: the sync call is one part of the total request; leave comfortable headroom under whatever the real limit is, or reduce the number if the ceiling is tighter than expected. State the checked value in the report.

## 5. What NOT to touch
- `catalog/model/checkout/order.php` — unrelated to this round, no changes.
- `ncrm/supabase/functions/order-sync/index.ts`, migrations `0011`/`0012`, `config.toml`, RLS, Apps Script — all unrelated, untouched.
- Test-filter logic (`isTestOrder()`), payload shape, secret handling — unchanged, this round is timeouts only.
- Do not add retry logic, a queue, or async dispatch — out of scope; a straightforward timeout bump only. If a genuinely better fix (e.g. a keep-warm ping) seems obviously worth flagging, note it in the report as a suggestion, don't implement it unasked.

## 6. Likely files/areas
- `patches/NCRM-10_round3_increase-sync-timeout_20260719.php` (new)
- `system/library/booster_crm_sync.php` (patch target on live server)
- `diagnostics/NCRM-10_round3_increase-sync-timeout_report_<date>.md` (new)

## 7. Acceptance criteria
- [ ] Patch changes exactly the three timeout values named above, nothing else in the file
- [ ] Anchor pre-check targets the exact current values (`1` and two occurrences of `2`) — fails clearly if they don't match, doesn't guess
- [ ] `php -l` passes pre- and post-write
- [ ] Report states the checked live `max_execution_time` (or explicitly notes it couldn't be verified and why)
- [ ] No change to any other file

## 8. QA / smoke test (owner)
Risk: this changes code in the live order-creation path, and specifically increases how long a customer's checkout request can take in the (uncommon) cold-start case. Not a payment/fiscalization change, but still checkout-adjacent — re-run after deploy:
- [ ] Place one real order (no test-filter data) through the actual storefront checkout, ideally when the function has been idle a few minutes (worst-case cold-start scenario) — confirm it still appears in `sales`/`sale_items`, and note how long checkout felt (should not feel broken/hung even in the slow case)
- [ ] Confirm `error_log` has no new `NCRM-10 order sync failed` line for that order
- [ ] Re-run the original test-filter check (Леусенко/phone/TEST) — confirm still correctly excluded, unaffected by this change

## 9. Rollback note
Same file, same backup mechanism as round 1 (`_patch_backups/`). Rollback = restore the pre-round-3 copy of `system/library/booster_crm_sync.php` from the new backup directory this patch creates. No schema/Edge Function changes in this round.

## 10. Recommended status after execution
NCRM-10 stays `In progress`. Close only after the §8 real-order retest confirms a successful end-to-end sync with no error_log entry.
