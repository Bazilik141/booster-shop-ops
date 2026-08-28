# Codex Report — NCRM-TEST-UNBLOCK: remove test-order filters and replay OC-FOP-0332

Date: 2026-08-24

## Scope

Remove the live PHP test-order filters from `BoosterCrmSync` and `NcrmOrderSync`; remove the equivalent Supabase Edge Function filter; enqueue only `OC-FOP-0332` through both normal delivery paths. Historical `SKIP_ORDER_IDS` / `SKIP_ORDER_KEYS` remain unchanged because they do not match this order and are not a general test-data filter.

## Evidence before change

The owner ran the cPanel checks for `OC-FOP-0332`:

- no worker-log entry and no pending queue job existed;
- the live `system/library/booster_crm_sync.php` contains both PHP test-filter implementations and their configured phone/email/name/product markers.

That proves the NCRM path returns before it can enqueue the order. The local Edge Function also contained its own `test-filter` response, so it must be deployed before replaying `0332`.

## Files touched

```
patches/NCRM-TEST-UNBLOCK_remove-test-filters-replay-0332_20260824.php
    — cPanel runner: guarded PHP source change plus replay enqueue
ncrm/supabase/functions/order-sync/index.ts
    — removes the Edge Function test filter
```

## Local verification

```
php -l patches/NCRM-TEST-UNBLOCK_remove-test-filters-replay-0332_20260824.php
No syntax errors detected

order-sync test-filter static check OK
git diff --check -- ncrm/supabase/functions/order-sync/index.ts
```

No production deployment, replay, or dashboard verification has occurred yet.

Update at 17:25 UTC: the production runner removed the PHP filters, created its backup, and passed `php -l`, but its one-off replay stopped before enqueuing because this OpenCart DB constructor requires `DB_PORT` as a string. The runner was corrected; rerun only performs the replay because the source change is already applied.

Update at 17:26 UTC: the replay found a runner defect in the now-modified `shouldSkipOrder()` body: it no longer returned `false` after the retained historical-ID check. No jobs were enqueued. A separate guarded hotfix restores that return, runs `php -l`, then replays only `OC-FOP-0332`.

## cPanel runner behaviour

- checks every expected live anchor before a write;
- creates `_patch_backups/NCRM-TEST-UNBLOCK_remove-test-filters-replay-0332_20260824-<timestamp>/booster_crm_sync.php`;
- restores that backup if `php -l` fails;
- refuses to replay if the legacy CRM sent-marker for `OC-FOP-0332` already exists;
- enqueues exactly `booster-crm-OC-FOP-0332.json` and `ncrm-OC-FOP-0332.json`;
- does not manually run the global queue worker, so unrelated jobs are not processed;
- self-deletes only after both jobs are enqueued and `done=ok` is printed.

## Rollback

Restore the generated backup over `system/library/booster_crm_sync.php`, then run `php -l system/library/booster_crm_sync.php`.

The Supabase rollback is redeploying the prior `order-sync` source revision.

## Owner deployment order

1. Deploy the modified Edge Function from `ncrm/supabase` with `npx supabase functions deploy order-sync --use-api`.
2. Upload and run the cPanel patch from `~/public_html`.
3. Wait for the normal queue-worker cadence; do not run it manually.
4. Confirm `OC-FOP-0332` appears once in the CRM/dashboard and that both queue jobs are gone.

## Risk

Medium: this is checkout-adjacent CRM synchronization. Future owner/test orders will be recorded like normal orders. The patch does not alter checkout creation, order status, payment, stock, or database structure.
