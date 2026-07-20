# Codex Report — CHECKOUT-002: async order side effects

Date: 2026-07-19

## Scope

Fix the measured customer-visible wait after COD checkout confirmation without
changing OpenCart order creation, payment status, database schema, checkout
routes, Nova Poshta data, fiscalization, or the Supabase Edge Function.

Fresh evidence showed that Pinta COD `confirm()` only calls OpenCart
`addHistory()` and then returns the success redirect. It does not call a Nova
Poshta API. The 12.9-second request is therefore in order-status side effects.

The source contains two Telegram paths for the same `addHistory()` call:

1. a direct `sendOrderNotification()` controller call in `order.php`;
2. extension code that configures an event for `addHistory/after` that calls
   `sendNotification()` and then `sendOrderNotification()`.

The archive does not include the current `oc_event` row, so the second path's
live enabled state is not assumed. The patch preserves the known direct path
and disables the event callback if it is enabled, guaranteeing one sender.

Telegram used a synchronous cURL call with a ten-second timeout. The CRM and
NCRM senders also performed remote requests during the request/shutdown path.
The prior NCRM round-5 deferral only flushes early when PHP-FPM exposes
`fastcgi_finish_request()`, which does not provide a reliable LiteSpeed/LSAPI
delivery boundary.

This patch keeps the known direct Telegram call as the sole notification path
and makes its event callback inert. Telegram, Booster CRM, and NCRM instead
write a small, atomic job into private `DIR_STORAGE`; an owner-configured cron
worker sends the jobs outside the customer request and retries failures on the
next minute.

## Files touched

```
patches/CHECKOUT-002_async-order-side-effects_20260719.php
    — one uploadable runner
catalog/model/checkout/order.php
    — retains the direct Telegram path and marks it as the single authority
system/library/booster_crm_sync.php
    — queues Booster CRM and NCRM outbound HTTP jobs instead of posting inline
extension/telegram_notify/catalog/controller/event/telegram.php
    — suppresses duplicate after-event dispatch and queues Telegram HTTP jobs
system/library/booster_async_queue.php
    — new private-storage queue and locked HTTP worker implementation
system/library/booster_async_queue_worker.php
    — new CLI cron entry point
```

## Dry-run result

Applied to an isolated fixture extracted from the owner-provided current source
archive:

```
changed_file=catalog/model/checkout/order.php
changed_file=system/library/booster_crm_sync.php
changed_file=extension/telegram_notify/catalog/controller/event/telegram.php
created_file=system/library/booster_async_queue.php
created_file=system/library/booster_async_queue_worker.php
done=ok
```

No production server, database, cron configuration, remote CRM, Telegram, or
Supabase state was changed during validation.

## PHP lint result

```
No syntax errors detected in CHECKOUT-002_async-order-side-effects_20260719.php
No syntax errors detected in catalog/model/checkout/order.php
No syntax errors detected in system/library/booster_crm_sync.php
No syntax errors detected in extension/telegram_notify/catalog/controller/event/telegram.php
No syntax errors detected in system/library/booster_async_queue.php
No syntax errors detected in system/library/booster_async_queue_worker.php
```

## Idempotency

The isolated second run returned:

```
already_applied=yes
```

The runner uses exact source anchors, writes backup copies before changing
anything, self-deletes after success, and refuses partial state.

## Rollback

The runner creates:

```
_patch_backups/CHECKOUT-002_async-order-side-effects_20260719-<timestamp>-<id>/
```

To roll back, restore the three files from that directory and remove:

```
system/library/booster_async_queue.php
system/library/booster_async_queue_worker.php
```

No database rollback is needed.

## Run command (owner)

Upload the patch to `~/public_html`, then run:

```bash
cd ~/public_html || exit
php CHECKOUT-002_async-order-side-effects_20260719.php
```

No OpenCart cache clear is required for these PHP source changes.

## Required cron job (owner)

After `done=ok`, add a separate cPanel Cron Job with every schedule field set
to `*` (once per minute). Keep the existing sitemap cron unchanged.

```bash
/bin/bash -lc 'php /home2/boosters/public_html/system/library/booster_async_queue_worker.php' >> /home2/boosters/logs/booster-async-order-sync.log 2>&1
```

The worker processes at most 25 jobs per run, uses a non-blocking file lock,
and retains failed jobs for retry. It accepts only the required Apps Script,
Supabase, and Telegram hosts.

## Post-deploy QA checklist

- [ ] Runner reports `done=ok`; rerunning a newly uploaded copy reports
      `already_applied=yes`.
- [ ] Run the worker manually once from `public_html`; it returns
      `status=ok` and does not expose a secret.
- [ ] Add the every-minute cron job above; leave the sitemap cron intact.
- [ ] Place one ordinary non-test COD order and capture a fresh HAR. The
      Pinta `|confirm` request should no longer wait for Telegram/CRM/NCRM.
- [ ] Within one minute, confirm exactly one Telegram notification and matching
      Booster CRM and Supabase order records.
- [ ] Check the worker log after the test: `delivered` should increment and
      `retry` should be zero. If not, retain the queue files and send only the
      worker output plus redacted error details.

## Side effects / risks

- Notifications and CRM/NCRM delivery become asynchronous, normally within one
  minute rather than during the customer redirect.
- Pending jobs contain order data and request credentials, so the queue is kept
  inside private `DIR_STORAGE` with restrictive directory/file permissions.
- If cron is disabled or broken, checkout stays fast but jobs remain queued
  until the worker is repaired; they are not silently discarded.
- The direct Telegram path remains the sole sender, so the patch does not rely
  on the unknown live database status of the extension event.
