# Codex Report — NCRM-10 round 3: increase order-sync timeout

Date: 2026-07-19

## Scope

Implemented one isolated OpenCart runner for the existing NcrmOrderSync sender.
It changes no order logic, payload, test filter, secret, Edge Function,
migration, Apps Script, or checkout controller.

The patch changes exactly these values in
system/library/booster_crm_sync.php:

    CURLOPT_CONNECTTIMEOUT: 1 to 3 seconds
    CURLOPT_TIMEOUT: 2 to 8 seconds
    stream fallback timeout: 2 to 8 seconds

## Files touched

    patches/NCRM-10_round3_increase-sync-timeout_20260719.php
        — uploadable runner for the live PHP bridge
    diagnostics/NCRM-10_round3_increase-sync-timeout_report_20260719.md
        — this report

## Dry-run result

The runner was applied to an isolated fixture containing the fresh order model,
the supplied bridge source, and the round-1 NcrmOrderSync addition:

    max_execution_time=0
    backup_dir=.../_patch_backups/NCRM-10_round3_increase-sync-timeout_20260719-...
    changed_file=system/library/booster_crm_sync.php
    done=ok

The fixture is PHP CLI, where max_execution_time=0 means unlimited. The live
hosting value cannot be read locally because Codex has no server access.
Before any write, the uploaded runner prints the actual live value and refuses
to modify the file when it is a finite value below 15 seconds. Thus the chosen
8-second total timeout retains at least 7 seconds of PHP execution headroom;
the owner receives a safe failure rather than a checkout-path change on a
tighter host limit.

## PHP lint result

    No syntax errors detected in NCRM-10_round3_increase-sync-timeout_20260719.php
    No syntax errors detected in system/library/booster_crm_sync.php

## Idempotency

The target receives one NCRM-10 round-3 marker. Re-running returns:

    already_applied=yes

Both successful and idempotent runs self-delete the uploaded runner.

## Rollback

The runner creates:

    _patch_backups/NCRM-10_round3_increase-sync-timeout_20260719-<timestamp>-<id>/

Restore its saved booster_crm_sync.php:

    cp _patch_backups/NCRM-10_round3_increase-sync-timeout_20260719-<timestamp>-<id>/booster_crm_sync.php system/library/booster_crm_sync.php

No cache clear is required because this is direct PHP code. No database row,
schema, or Edge Function setting changes in this round.

## Run command (owner)

Upload the patch to public_html, then run:

    cd ~/public_html || exit
    php NCRM-10_round3_increase-sync-timeout_20260719.php

Require output containing done=ok and max_execution_time of 0 or at least 15.
If it prints max_execution_time_too_low, stop and send that output; the target
file remains unchanged.

## Post-deploy QA checklist

- [ ] Confirm patch output has done=ok and the reported runtime limit passed.
- [ ] After the Edge Function has been idle for several minutes, place one
      ordinary non-test order through the storefront.
- [ ] Confirm a matching sales row and sale_items arrive in Supabase.
- [ ] Confirm error_log has no new NCRM-10 order sync failed entry.
- [ ] Confirm checkout still reaches success promptly; its only added worst-case
      wait remains bounded at eight seconds.
- [ ] Repeat one TEST or test-filter order and confirm it remains excluded.

## Side effects / risks

- A cold Edge Function may now hold this best-effort shutdown callback open for
  up to eight seconds instead of two. The bound remains finite and is guarded
  against an insufficient PHP execution limit.
- This is not retry logic. A function outage or error still leaves the OpenCart
  order and Apps Script bridge independent, while NcrmOrderSync logs its
  best-effort failure.
