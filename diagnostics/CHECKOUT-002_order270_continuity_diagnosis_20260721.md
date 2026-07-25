# CHECKOUT-002 — order #270 CRM delivery continuity diagnosis

Date: 2026-07-21  
Scope: read-only diagnosis of the missing OpenCart order #270 delivery to CRM/NCRM. No production code, database, Apps Script deployment, queue job or cron was changed.

## Initial conclusion

The MKT-TG-008 Telegram-news source-copy does not run in the OpenCart checkout path and cannot account for the missing order #270 delivery.

Order #270 was initially consistent with the already-open CHECKOUT-002/NCRM-10 incident: immediately after CHECKOUT-002 was deployed, orders #260 and #261 reached none of Telegram, the legacy CRM Sheet or Supabase CRM. Orders #256–#259 had worked before that deployment.

## New live evidence — 2026-07-21

The owner ran the requested read-only check. The private queue exists and contains all three jobs for the same order:

```text
booster-crm-OC-FOP-0270.json
ncrm-OC-FOP-0270.json
telegram-1784636877-08ab0cc02f.json
```

This rules out the leading "Telegram aborted `addHistory()` before dispatch" hypothesis for #270. Checkout successfully reached all three queue enqueue calls. The immediate delivery failure is downstream: the worker cron has not drained these jobs, either because the cron is absent/not running or the worker is failing/retrying.

### Confirmed cause — 2026-07-21

The cron exists and the worker itself is healthy (`processed=6, delivered=5, retry=1`, then `processed=1, delivered=1`). However, its schedule is wrong:

```cron
*/2 */6 * * *
```

This runs every two minutes only during hours `00`, `06`, `12`, and `18`, not every two minutes continuously. Both CRM jobs for `OC-FOP-0270` have `attempts=0`, proving no worker cycle has claimed them since their `2026-07-21T12:27:57+00:00` enqueue time.

Replace the schedule with either `*/2 * * * *` (every two minutes) or `* * * * *` (every minute). Keep the command/path and output redirection unchanged. No PHP source patch is required.

## Evidence

- Owner-provided PHP log search shows historical `Booster CRM sync queued` timeouts/302/bad-token events through order #256, but no `order_id=270`, `order 270` or NCRM-10 entry.
- Canonical continuity handoff `handoffs/handoff_CHECKOUT002-NCRM10_silent-sync-failure-continuity_20260719.md` records #260/#261 total silence across Telegram, old CRM and NCRM after CHECKOUT-002.
- That handoff identifies the one missing proof: OpenCart internal `DIR_STORAGE/logs/*.log`, not the server PHP `error_log`.
- The relevant current evidence is the three undelivered queue files. Do not inspect job `body`, `headers` or `url`: they can contain order data and credentials.

## Do not change yet

- Do not alter the NCRM Edge Function, migrations, timeout constants or test-order filters.
- Do not run the async worker manually before capturing the cron/log state; it would mutate the evidence and may deliver pending jobs.
- Do not retry/recreate order #270 from the storefront.

## Required owner evidence

Run this read-only command from `~/public_html` and send its output. It prints the cron entry, worker-log tail and safe queue metadata only; it does not print job bodies, headers, URLs, keys, execute jobs or modify files.

```bash
cd ~/public_html || exit
echo '--- cron ---'
crontab -l 2>&1 | grep -F 'booster_async_queue_worker.php' || true
echo '--- worker log ---'
tail -n 120 /home2/boosters/logs/booster-async-order-sync.log 2>&1 || true
echo '--- order 270 queue metadata ---'
php -r 'require "config.php"; $q=rtrim(DIR_STORAGE,"/\\")."/booster_async_http_queue"; foreach(glob($q."/*0270*.json") ?: [] as $f){ $j=json_decode(file_get_contents($f),true) ?: []; echo basename($f)." attempts=".(int)($j["attempts"]??0)." created_at=".($j["created_at"]??"")." last_attempt_at=".($j["last_attempt_at"]??"")." last_error=".substr((string)($j["last_error"]??""),0,180).PHP_EOL; }'
```

After that evidence, choose the narrow branch:

1. Correct the cron schedule, then wait for one cadence interval and check that all three order #270 jobs disappear.
2. If a job remains with `attempts>0`, inspect its redacted `last_error` and fix only that HTTP/permission/allow-list cause.

## Rollback / risk

Any later fix touches checkout-adjacent notifications for every order. The live patch already created server backups under `_patch_backups/`; no rollback or write is part of this diagnosis.
