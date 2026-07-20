# Continuity Handoff — CHECKOUT-002 / NCRM-10: orders 260/261 silently fail on all three notification channels

Date: 2026-07-19 | Purpose: bridge document for a new Claude conversation AND a Codex handoff. Read this whole file before doing anything else — it replaces re-reading the prior chat thread.

## 1. Conclusion

Two live test orders (#260, #261), placed right after CHECKOUT-002 was deployed, produced a fast/normal-looking checkout redirect but landed in **none** of: Telegram notification, the old Apps Script CRM (Sheets), the new Supabase CRM (`sales` table). No trace in PHP `error_log` either. Orders #256-#259 (placed just before CHECKOUT-002, on round 4 + round 5 code) all synced correctly to the new CRM. Root cause is not yet 100% confirmed. Leading hypothesis: an uncaught exception inside `telegram.php`'s `sendOrderNotification()` (a pre-existing, currently-unprotected method, not new CHECKOUT-002 code) aborts `addHistory()` in `order.php` before it ever reaches the Booster CRM / NCRM dispatch calls. This is unconfirmed pending one specific check the owner has not yet completed: OpenCart's own internal log (Admin → Reports → Error Log, or `storage/logs/*.log` on the server) — NOT the PHP `error_log` file, which has already been checked and is empty for these orders.

## 2. Task type

Bug diagnosis + fix, spanning two roadmap threads: **NCRM-10** (OpenCart → Supabase CRM sync pipeline, rounds 1-5 so far) and **CHECKOUT-002** (async order side-effects / checkout redirect latency). As of this document, they are entangled: CHECKOUT-002's deploy is the only thing that changed between the last known-good order (#259) and the first broken ones (#260, #261).

## 3. Owner

Mixed — Claude (continuity, coordination, code review), Codex (once root cause is pinned down, implement the fix), Manual/owner (check the OpenCart internal log — this is the one blocking action right now).

## 4. Status

**Blocked on one piece of evidence.** Everything else in this document is either confirmed fact (from real server files/data the owner uploaded) or a clearly-labeled open hypothesis. Do not mark CHECKOUT-002 or any NCRM-10 round "Готово"/closed — round 4 works, round 5 and CHECKOUT-002 are live but round 260/261's total silence is unexplained.

## 5. Next action

1. Owner opens OpenCart Admin → Reports → Error Log (or pulls `storage/logs/*.log` from the server) and looks for anything around the time orders #260/#261 were placed — specifically strings like `Telegram notification queue failed:` or `Booster CRM sync queued`. This is a **different log** from the PHP `error_log` file already checked three times in the prior conversation (that one is empty for #260/#261).
2. Paste that log content (or "it's empty too") into the new conversation.
3. Depending on the answer, either harden the confirmed weak spot (see §6) or, if the internal log shows something else entirely, use the evidence trail in §9 below to keep narrowing it — don't restart the investigation from scratch, everything ruled out is listed there.

## 6. Codex handoff (technical brief)

### 6.1 Task ID
CHECKOUT-002 follow-up — orders silently vanish across Telegram + old CRM + new CRM simultaneously, no log trace anywhere, immediately after CHECKOUT-002 went live. Not yet reproduced in isolation; this handoff gives Codex everything needed to reason about it from the real live source, without server access.

### 6.2 Context
See §9 (full evidence trail) and §10 (files) below — read both before proposing a fix. Short version: `catalog/model/checkout/order.php`'s `addHistory()` method (real live copy in `live-snapshots/20260719_checkout002-silent-failure/order.php`, relevant range lines 979-992) does, in order, on every status write: insert the history row → call Telegram's `sendOrderNotification()` directly (**no try/catch at this call site**) → call `boosterCrmSync()` (registers a shutdown callback) → call `ncrmOrderSync()` (registers a shutdown callback, round 5). Telegram's `sendOrderNotification()` (live copy: `live-snapshots/20260719_checkout002-silent-failure/telegram.php`, lines 14-64) has a history-count gate (`total !== 1` → return), then a large block — `$this->load->model('checkout/order')`, `$this->model_checkout_order->getOrder()`, `$this->currency->format()`, template substitution — with **zero error handling**, before it reaches the one part CHECKOUT-002 did add proper try/catch around (`sendMessage()`, lines 67-93). If that unprotected block throws for any reason, the exception is uncaught, propagates through the unprotected call site in `order.php`, and **addHistory() aborts before reaching `boosterCrmSync()`/`ncrmOrderSync()` at all** — which would explain total, traceless silence across all three channels simultaneously. This code is not new (CHECKOUT-002 did not touch it), so if this is the real cause, CHECKOUT-002 exposed a pre-existing fragility rather than introducing a new bug — don't assume it's a CHECKOUT-002 regression without the log evidence confirming it.

Two other facts already ruled in/out — see §9 for the full list, don't re-derive:
- `system/library/booster_async_queue.php` exists on the live server, is syntactically correct, and matches the intended design (host allowlist, atomic writes, private permissions). Not the problem by itself.
- `DIR_STORAGE/booster_async_http_queue/` does **not** exist on the server (owner-confirmed) — meaning `BoosterAsyncHttpQueue::enqueue()` has never successfully completed even once since CHECKOUT-002 deployed, for any of the three senders. This is consistent with the "aborts before reaching any sender" theory, but doesn't by itself prove it (a DIR_STORAGE permission failure would also produce this, but *should* still log via each sender's own catch block — and none of those logs have been checked yet, hence §5's blocking action).

### 6.3 Goal
A real order reliably reaches Telegram, the old CRM, and the new CRM (or fails loudly with a log entry every single time — never silently). Checkout redirect speed achieved by round 5 / CHECKOUT-002 must not regress.

### 6.4 What to change (scope)
Depends on what the internal log (§5) shows. Two branches:
- **If the internal log is empty for #260/#261 too** (strongly supports the leading hypothesis): wrap the risky block inside `telegram.php`'s `sendOrderNotification()` (between the `total !== 1` gate and the `sendMessage()` call) in its own try/catch, logging failures the same way `sendMessage()` already does. Separately, wrap the call site in `order.php` line 985 (`$this->load->controller('extension/telegram_notify/event/telegram.sendOrderNotification', ...)`) in try/catch too, so a Telegram-side failure can never again take down `boosterCrmSync()`/`ncrmOrderSync()` with it — those three side effects should be independent of each other, matching the "best-effort, never blocks the others" design already used everywhere else in this pipeline.
- **If the internal log shows something** (e.g. `DIR_STORAGE` permission errors, host-allowlist rejections, etc.): fix that specific cause in `booster_async_queue.php`/`queueDir()`, and separately still consider adding the defensive try/catch above anyway, since an unprotected side-effect call site next to two protected ones is a latent bug regardless of whether it's the one firing today.

### 6.5 What NOT to touch
- `ncrm/supabase/functions/order-sync/index.ts` — round 4's order_id/unit_price fix is confirmed working (orders 256-259), don't touch.
- `ncrm/supabase/migrations/0011...sql`, `0012...sql` — unrelated, untouched.
- Round 3's timeout constants in `NcrmOrderSync::postJson()` (`system/library/booster_crm_sync.php`) — unrelated to this bug, leave as-is (`3`/`8`/`8`).
- `isTestOrder()` in both `NcrmOrderSync` (PHP, matches Latin `leusenko`) and `index.ts` (matches Cyrillic `леусенко`) — already confirmed correctly *not* matching orders 260/261's test name ("Євгейй Леусенвіко" — deliberately near-miss per owner's own test methodology, not a filter bug), do not touch this round.
- The known, separately-tracked `discount_total` bug (`diagnostics/NCRM-10_discount-total-not-mapped_note_20260719.md`) — real, confirmed, but explicitly a different, already-logged task. Don't fold it into this fix.
- Standard protected zones, untouched and unaffected: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout/payment/fiscalization UI itself, Merchant feed, schema.

### 6.6 Likely files/areas
- `extension/telegram_notify/catalog/controller/event/telegram.php` (most likely fix target)
- `catalog/model/checkout/order.php` (call-site try/catch, if going that route)
- `system/library/booster_async_queue.php` / `system/library/booster_crm_sync.php` (only if the internal log points here instead)
- Real live copies of all four are saved at `live-snapshots/20260719_checkout002-silent-failure/*.php` in this repo — use those as ground truth, they are freshly pulled from the server as of 2026-07-19 ~20:1x. (`booster_crm_sync.php`'s `SECRET_TOKEN` constant is redacted in the saved copy — never paste real secret values into any report, diagnostics file, or chat.)

### 6.7 Acceptance criteria
- [ ] A real order (non-test, matching how the owner has been testing — see §9's note on test methodology) reaches all three channels reliably, OR fails with a clear, non-silent log entry
- [ ] Orders 260/261's exact failure mode is either reproduced-then-fixed, or explained by the internal-log evidence and fixed accordingly
- [ ] Checkout redirect stays fast (no regression to the pre-round-5 multi-second wait)
- [ ] `git diff` scoped only to the files named in §6.6, no drift into protected zones
- [ ] No secret values anywhere in diff/report

### 6.8 QA / smoke test (owner)
Risk, stated plainly: this touches the live order-notification path for every real order going forward (Telegram + both CRMs), and we've just proven it can fail 100% silently — treat any fix here as unverified until proven otherwise, don't just trust "no errors" as success.
- [ ] Place one real, non-test order through the actual storefront checkout
- [ ] Confirm Telegram fires within the normal delay (was instant before CHECKOUT-002; may now be up to ~1 minute if queued via cron — confirm which is expected post-fix and note it)
- [ ] Confirm a matching row lands in the old CRM sheet
- [ ] Confirm a matching `sales`/`sale_items` row lands in Supabase
- [ ] Confirm the cron job (`booster_async_queue_worker.php`, once-per-minute) is actually installed and running — check `booster-async-order-sync.log`; `delivered` should increment, `retry` should stay near zero
- [ ] Re-run the test-filter check with the owner's actual test-naming method (garbled Cyrillic names, deliberately chosen to bypass filters for real pipeline testing — see §9) to confirm it's excluded only when actually intended

### 6.9 Rollback note
Each file's patch runner (round 1, round 5, CHECKOUT-002) created its own timestamped backup under `_patch_backups/` on the live server with the exact restore command in its own report — see `diagnostics/CHECKOUT-002_async-order-side-effects_report_20260719.md` and `diagnostics/NCRM-10_round5_defer-order-sync-after-response_report_20260719.md` for the precise `cp` commands. No database/migration rollback implied by anything in this document.

### 6.10 Recommended status after execution
CHECKOUT-002 and NCRM-10 both stay "In progress." Close only after §6.8's real-order retest confirms all three channels succeed reliably, with no silent failure mode remaining.

## 7. QA checklist (for this handoff itself)
- [x] Original evidence reviewed (full prior conversation, condensed below in §9)
- [x] High-risk area flagged explicitly (checkout-adjacent, CRM-touching — see §8)
- [x] Rollback note included (§6.9, points to existing per-round backups)
- [x] No scope creep — discount_total bug kept separate, round 3/4 fixes not reopened
- [x] No secret values included (SECRET_TOKEN redacted in the saved live snapshot)

## 8. Risks

This touches the live order-creation notification path for every real order (Telegram, both CRMs) — named per standing risk rule. We've already proven today that this exact area can fail **100% silently**, with zero trace in PHP's `error_log`, for at least two consecutive real orders. Any fix here must be verified by an actual real-order smoke test (§6.8), not just "no exception thrown locally" — Codex's own dry-run fixtures cannot reproduce live DIR_STORAGE/permission/hosting conditions, which is exactly the class of bug in play here (see §9's note on `_patch_backups`/local-fixture limitations already burning us once this session, with `booster_crm_sync.php` upload confusion).

## 9. Full evidence trail (condensed — do not re-derive, extend from here)

**Round history today (2026-07-19), all in `booster-shop-ops` repo, branch `master`:**
- Round 1 (07-18, prior day): live PHP hook + Edge Function + migration 0011. `handoffs/` has no round-1 handoff file visible in this repo snapshot; report is `diagnostics/NCRM-10_order-pipeline-opencart-supabase_report_20260718.md`.
- Round 2 (07-18): migration 0012, partial-insert/missing-SKU. SQL only.
- Round 3: `handoffs/handoff_NCRM-10_round3_increase-sync-timeout_20260719.md` — timeout bump in `NcrmOrderSync::postJson()`, 1s/2s → 3s/8s. Deployed.
- Round 4: `handoffs/handoff_NCRM-10_round4_fix-order-id-type-check_20260719.md`, report `diagnostics/NCRM-10_round4_fix-order-id-type-check_report_20260719.md` — fixed `index.ts`'s `order_id` (JSON number vs `text()` string-only helper) and `unit_price` vs `price` field-name mismatch. **Deployed and confirmed working**: orders #256-#259 all synced.
- Round 5: two reports, `diagnostics/NCRM-10_round5_litespeed_checkout_wait_report_20260719.md` (diagnosis only — proved Pinta COD's `confirm()` doesn't call any Nova Poshta API, the wait is in `addHistory()`'s side effects) and `diagnostics/NCRM-10_round5_defer-order-sync-after-response_report_20260719.md` + `patches/NCRM-10_round5_defer-order-sync-after-response_20260719.php` — wraps `ncrmOrderSync()`'s actual work in `register_shutdown_function()`, registered after `boosterCrmSync()`'s. **Deployed** (confirmed via live `order.php` markers).
- Discount bug: `diagnostics/NCRM-10_discount-total-not-mapped_note_20260719.md` — `index.ts` computes `discount_total` from a `totals` array the PHP bridge never sends; PHP already computes the correct number under `payload.discount_total`, which `index.ts` ignores. Confirmed against real data — every synced `sales` row has `discount_total = "0.00"`. **Not yet fixed, separately tracked, do not fold into this task.**
- CHECKOUT-002: `patches/CHECKOUT-002_async-order-side-effects_20260719.php` + `diagnostics/CHECKOUT-002_async-order-side-effects_report_20260719.md` — converts Telegram + old CRM (`BoosterCrmSync`) + new CRM (`NcrmOrderSync`) from direct/synchronous sends to a private file queue (`system/library/booster_async_queue.php`, class `BoosterAsyncHttpQueue`) drained by a cron worker (`system/library/booster_async_queue_worker.php`, needs a manually-added cPanel cron job, once per minute — **owner confirmed CHECKOUT-002 uploaded and deployed**, cron job status not yet independently confirmed).

**Order-by-order evidence (all 2026-07-19, Kyiv time unless noted UTC):**

| Order | Time | Edge Function | Old CRM (Sheets) | New CRM (`sales`) | Notes |
|---|---|---|---|---|---|
| #251 | 07:57:40 | 404 (not deployed yet) | — | — | resolved by deployment |
| #254 | 09:01:03 | 400 in 1.27s, "invalid OpenCart order identifier" | — | — | round-4 order_id bug, pre-fix |
| #255 | 09:47:24 | no Invocations row at all; `error_log` shows failure | present | absent | pre-round-4, likely same class of bug, never retried |
| #256 | 10:49:12 UTC | 200 | present | present, `discount_total="0.00"` | first order after round 4 deployed |
| #257 | 11:04:54 UTC | 200 | present | present, `discount_total="0.00"` | |
| #258 | 11:18:08 UTC | 200 | present | present, `discount_total="0.00"` | |
| #259 | 15:17:42 UTC | 200 | present | present, `discount_total="0.00"` | **last order before CHECKOUT-002 deployed** |
| #260 | after CHECKOUT-002 deploy | **no Invocations row at all** | **absent** | **absent** | fast checkout redirect; OpenCart order exists, status "В обробці"; History tab shows exactly **one** row, no earlier entries; `error_log` clean (no `NCRM-10 order sync failed` line); name "Євгейй Леусенвіко" — deliberately test-styled by owner but does not match any test filter (Latin `leusenko`, Cyrillic `леусенко`, or `test`/`тест`) |
| #261 | after 260 | not yet checked | absent | absent | same total-silence pattern; owner additionally noticed **Telegram did not fire** (normally instant) |

**Ruled out (don't re-test these):**
- Not a timeout/cold-start issue — rounds 3+4 already fixed the real Edge Function bugs; #256-#259 prove the pipeline works end-to-end when reached.
- Not `booster_async_queue.php` missing or broken — confirmed present on the live server, syntactically clean, logic matches spec (see `live-snapshots/.../booster_async_queue.php`).
- Not the test-order filters catching #260/#261 — name checked character-by-character against both PHP (`leusenko` Latin) and Edge Function (`леусенко` Cyrillic) filters; "Леусенвіко" narrowly avoids both due to the inserted "ві". This appears to be the owner's actual deliberate test methodology (garbled-but-not-quite-test-matching names, to exercise the real pipeline) — not an accident, don't flag it as one.
- Not stale/wrong deployments of `index.ts`, `order.php`, `telegram.php`, or `booster_async_queue.php` — all confirmed current via fresh uploads from the live server this session.
- **Caveat on process**: the owner accidentally uploaded a stale/backup copy of `booster_crm_sync.php` **twice** before the correct current one came through (missing the `NcrmOrderSync` class entirely, which would have been impossible given #256-#259 succeeded). If any future file upload looks structurally impossible given other confirmed facts, say so explicitly and ask for a fresh pull rather than reasoning from it — this cost real time this session.

**Still open / unconfirmed:**
- The OpenCart-internal log check (§5) — asked three times in the prior conversation, not yet answered. This is the single blocking fact.
- Whether the cron job for `booster_async_queue_worker.php` was actually added on the server (CHECKOUT-002's report requires this as a manual owner step; not independently confirmed in this session).
- The exact line inside `telegram.php`'s unprotected block that would throw, if that hypothesis is correct — not yet narrowed further than "somewhere between line 26 and line 63."

## 10. Files referenced in this handoff

```
handoffs/handoff_NCRM-10_round3_increase-sync-timeout_20260719.md
handoffs/handoff_NCRM-10_round4_fix-order-id-type-check_20260719.md
diagnostics/NCRM-10_order-pipeline-opencart-supabase_report_20260718.md
diagnostics/NCRM-10_round3_increase-sync-timeout_report_20260719.md
diagnostics/NCRM-10_round4_fix-order-id-type-check_report_20260719.md
diagnostics/NCRM-10_round5_litespeed_checkout_wait_report_20260719.md
diagnostics/NCRM-10_round5_defer-order-sync-after-response_report_20260719.md
patches/NCRM-10_round5_defer-order-sync-after-response_20260719.php
diagnostics/NCRM-10_discount-total-not-mapped_note_20260719.md
patches/CHECKOUT-002_async-order-side-effects_20260719.php
diagnostics/CHECKOUT-002_async-order-side-effects_report_20260719.md
ncrm/supabase/functions/order-sync/index.ts   (current, round-4-fixed version)
ncrm/supabase/migrations/0011_opencart_order_sync.sql
ncrm/supabase/migrations/0012_opencart_order_sync_partial_insert.sql
live-snapshots/20260719_checkout002-silent-failure/order.php              (real live copy)
live-snapshots/20260719_checkout002-silent-failure/booster_crm_sync.php   (real live copy, secret redacted)
live-snapshots/20260719_checkout002-silent-failure/telegram.php           (real live copy)
live-snapshots/20260719_checkout002-silent-failure/booster_async_queue.php (real live copy)
```

## 11. Known friction (not a task, just so the next conversation doesn't waste time rediscovering it)

- `git commit` in this repo intermittently fails with `.git/index.lock: File exists, Operation not permitted` — a Windows-side lock issue unrelated to sandbox permissions. Retrying (sometimes after asking the owner to clear it, sometimes just retrying directly) has worked. Check `git log -1` / `git status` on resume to see what's actually committed vs. pending.
- Notion roadmap (source of truth for priorities) is **not connected/authenticated** in this environment — track status here in repo files until it is.
