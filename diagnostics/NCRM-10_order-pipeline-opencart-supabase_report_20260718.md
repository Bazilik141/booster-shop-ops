# Codex Report — NCRM-10: OpenCart order pipeline to Supabase

Date: 2026-07-18

## Scope

The handoff requested new-order delivery from OpenCart to Supabase, direct
inserts into sales and sale_items, test-order exclusion, and preservation of
the existing Apps Script synchronization.

Fresh source inspection changed the hookup point: the live order model already
emits an order_add event from its existing addHistory path and then schedules
BoosterCrmSync. The implementation therefore adds a separate NcrmOrderSync
sender beside that bridge, immediately after its existing call. It only accepts
order_add and does not modify, replace, retry, or gate the Apps Script path.
No change was made to confirm.php.

Confirmed order key contract: order 249 corresponds to OC-FOP-0249, so the
sender generates OC-FOP plus the zero-padded OpenCart order id.

## Files touched

    patches/NCRM-10_opencart-order-sync-hook_20260718.php
        — uploadable OpenCart patch; adds separate sender and creates blank
          storage-side config template
    ncrm/supabase/migrations/0011_opencart_order_sync.sql
        — atomic service-role RPC: sale header plus sale_items, duplicate-safe
    ncrm/supabase/functions/order-sync/index.ts
        — authenticated, test-filtered Edge Function calling the RPC
    ncrm/supabase/functions/order-sync/deno.json
        — strict TypeScript config
    ncrm/supabase/config.toml
        — disables JWT verification for this shared-secret endpoint

## Data and security contract

- The Edge Function accepts only POST with X-Order-Sync-Secret.
- The shared secret is not present in source, patch output, or logs.
- The OpenCart patch writes an empty template at
  DIR_STORAGE/config/ncrm_order_sync.php. The owner fills the Edge Function URL
  and the same secret after the function is deployed.
- Only event=order_add is accepted. Existing orders and later status events are
  ignored.
- Test orders are excluded for Leusenko, phone ending 0991119279, and test or
  тест in the stored customer/comment/product name/model/SKU fields.
- The SQL RPC validates SKUs and references, inserts sales and sale_items in
  one transaction, and returns a duplicate result when opencart_order_id
  already exists.
- New imported orders start as payment_status_code=unpaid and
  order_status_code=new. Payment confirmation is intentionally out of scope.

## Dry-run result

Patch run against the fresh uploaded order.php plus the supplied
booster_crm_sync.php in an isolated public_html fixture:

    backup_dir=.../_patch_backups/NCRM-10_opencart-order-sync-hook_20260718-...
    storage_config=.../storage/config/ncrm_order_sync.php
    done=ok

Local Supabase-only validation:

    migration_0011=applied
    edge_compile=ok
    edge_auth_401=ok
    edge_test_filter=ok
    edge_insert=ok
    edge_duplicate=ok
    local_smoke_cleanup=ok

No production, cloud Supabase project, OpenCart server, database, or Apps
Script deployment was touched.

## PHP lint result

    No syntax errors detected in NCRM-10_opencart-order-sync-hook_20260718.php
    No syntax errors detected in catalog/model/checkout/order.php
    No syntax errors detected in system/library/booster_crm_sync.php
    No syntax errors detected in storage/config/ncrm_order_sync.php

## Idempotency

The patch checks markers in both target files before writing. A second run
returns:

    already_applied=yes

Both successful and idempotent runs self-delete the uploaded patch file.

## Rollback

The runner creates:

    _patch_backups/NCRM-10_opencart-order-sync-hook_20260718-<timestamp>-<id>/

Restore the two backed-up files from that directory:

    cp _patch_backups/NCRM-10_opencart-order-sync-hook_20260718-<timestamp>-<id>/order.php catalog/model/checkout/order.php
    cp _patch_backups/NCRM-10_opencart-order-sync-hook_20260718-<timestamp>-<id>/booster_crm_sync.php system/library/booster_crm_sync.php
    rm -f "$(php -r "require 'config.php'; echo rtrim(DIR_STORAGE, '/');")/config/ncrm_order_sync.php"

Remove the last line only if the config file was created by this patch and was
not used for another configuration.

## Required cloud gate before OpenCart enablement

The owner reported migrations 0005 through 0010 ready. This was not verified
against the cloud project here. Before deploying the Edge Function, an
authorized owner must verify that cloud migration list contains 0001 through
0011, then deploy migration 0011 and the order-sync function, set
ORDER_SYNC_SHARED_SECRET, and retain proof of the migration list.

## Run command (owner)

After the Edge Function URL and secret are ready, upload the patch to
public_html and run:

    cd ~/public_html || exit
    php NCRM-10_opencart-order-sync-hook_20260718.php

Then fill the two blank values in
DIR_STORAGE/config/ncrm_order_sync.php. No cache clear is required.

## Post-deploy QA checklist

- [ ] Cloud migration list confirms 0001 through 0011 before function release.
- [ ] Edge Function has ORDER_SYNC_SHARED_SECRET and the OpenCart storage config
      has the same value; neither value is pasted into chat or source control.
- [ ] Create one real non-test order after OpenCart order 249 and confirm one
      sales row plus matching sale_items with the expected OC-FOP order number.
- [ ] Replay the same request or use the same order id and confirm no duplicate
      sales row is created.
- [ ] Place a test order matching each exclusion rule and confirm neither sales
      nor sale_items change.
- [ ] Confirm the existing Apps Script pipeline still receives its normal
      order_add event independently.
- [ ] For a Mystery Box SKU, confirm whether the established NCRM fulfillment
      automation creates the expected needs_assembly record.

## Side effects and risks

- The new sender is best-effort and runs after the existing bridge; a temporary
  remote failure does not block checkout. Deferred retry/queue for this sender
  is intentionally outside NCRM-10 scope.
- The isolated insert with a real Mystery Box SKU caused the pre-existing
  fulfillment automation to create one needs_assembly record. It was fully
  removed during local cleanup. This is an integration behavior to validate,
  not an extra NCRM-10 write path.
- The order_product source has stored product name/model/SKU but no separate
  product-description snapshot. The test filter therefore uses the actual
  stored fields available at the order write boundary.
