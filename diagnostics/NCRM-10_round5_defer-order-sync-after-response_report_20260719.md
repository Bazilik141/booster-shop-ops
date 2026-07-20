# Codex Report — NCRM-10 round 5: defer order-sync after checkout response

Date: 2026-07-19

## Scope

Owner evidence showed a successful OpenCart to Supabase invocation with
execution_time_ms=2032 and a created sales row, while the customer still waited
about five seconds before the checkout success redirect. The remote call was
therefore on the customer request path, not a failed background operation.

Fresh source tracing confirms the cause:

1. order.php calls boosterCrmSync first. It registers a shutdown callback that
   calls fastcgi_finish_request when PHP-FPM provides it.
2. The round-1 ncrmOrderSync method then directly calls NcrmOrderSync.syncOrder
   before addHistory returns.
3. That direct POST waits for the Edge Function response, adding its observed
   roughly two seconds to the checkout redirect path.

The patch changes only ncrmOrderSync in catalog/model/checkout/order.php. It
registers its existing best-effort sender as a second shutdown callback. The
already-registered Booster CRM callback therefore flushes the response first
under PHP-FPM; NCRM delivery occurs after the browser receives the redirect.

## Files touched

    patches/NCRM-10_round5_defer-order-sync-after-response_20260719.php
        — uploadable one-file OpenCart runner
    diagnostics/NCRM-10_round5_defer-order-sync-after-response_report_20260719.md
        — this report

## Dry-run result

Applied to an isolated fixture containing the fresh order.php plus the
round-1 and round-3 NCRM additions:

    backup_dir=.../_patch_backups/NCRM-10_round5_defer-order-sync-after-response_20260719-...
    changed_file=catalog/model/checkout/order.php
    done=ok

The fixture verifies all required anchors:

- the round-1 NCRM method marker appears once;
- boosterCrmSync registration precedes ncrmOrderSync registration once;
- the original immediate NCRM method appears once before replacement;
- the final source has two shutdown callbacks in the expected order.

No cloud deployment, OpenCart server, Edge Function, migration, secret,
Apps Script, cache, or database row was changed.

## PHP lint result

    No syntax errors detected in NCRM-10_round5_defer-order-sync-after-response_20260719.php
    No syntax errors detected in catalog/model/checkout/order.php

## Idempotency

The target receives one round-5 marker. Re-running returns:

    already_applied=yes

Successful and idempotent runner invocations self-delete.

## Rollback

The runner creates:

    _patch_backups/NCRM-10_round5_defer-order-sync-after-response_20260719-<timestamp>-<id>/

Restore its order.php:

    cp _patch_backups/NCRM-10_round5_defer-order-sync-after-response_20260719-<timestamp>-<id>/order.php catalog/model/checkout/order.php

No cache clear is required. No schema or data rollback exists because this
round changes one PHP method only.

## Runtime assumption and limitation

The fresh source already conditionally uses fastcgi_finish_request in the
first shutdown callback. PHP invokes shutdown callbacks in registration order,
so on PHP-FPM this runner moves the Edge POST beyond the client response.

If fastcgi_finish_request is unavailable on the hosting SAPI, PHP may wait for
shutdown callbacks before the client sees the response. This was already a
fallback limitation in the existing Apps Script bridge; the owner checkout
smoke below is the required confirmation. Do not infer client-side latency
improvement from syntax checks alone.

## Run command (owner)

Upload the patch to public_html, then run:

    cd ~/public_html || exit
    php NCRM-10_round5_defer-order-sync-after-response_20260719.php

Require done=ok. If an anchor error appears, send the complete output; the
file remains unchanged.

## Post-deploy QA checklist

- [ ] Place one ordinary non-test storefront order and measure time from
      submitting checkout to the success redirect. The redirect should no
      longer wait for the roughly two-second Edge response.
- [ ] Confirm the matching sales and sale_items arrive in Supabase shortly
      afterward.
- [ ] Confirm error_log has no new NCRM-10 order sync failed entry.
- [ ] Confirm the existing Apps Script order sync still completes.
- [ ] If redirect latency remains high, collect PHP SAPI plus the next
      error_log tail before changing further code.

## Side effects / risks

- NCRM delivery remains bounded by the round-3 eight-second timeout, but runs
  after response under PHP-FPM rather than blocking the customer.
- A post-response worker failure still logs the existing best-effort error and
  cannot undo the already-created OpenCart order.
- No retry, queue, duplicate policy, payload, timeout, Edge Function, or
  checkout business rule changed.
