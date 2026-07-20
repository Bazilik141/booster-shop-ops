# Codex Report — NCRM-10 round 2: partial insert + missing-SKU flag

Date: 2026-07-18

## Scope

Implemented only the additive 0012 migration requested by the round-2
handoff. It replaces the body of public.fn_ingest_opencart_order(jsonb)
without changing already-applied migration 0011.

Owner clarification takes precedence over the handoff deployment sentence:
the round-1 OpenCart PHP patch was not uploaded or run, so the storefront hook
is not live. This round does not change that patch, Edge Function, secrets,
Apps Script, or checkout.

## Files touched

    ncrm/supabase/migrations/0012_opencart_order_sync_partial_insert.sql
        — replace RPC body with partial-insert behaviour
    diagnostics/NCRM-10_round2_partial-insert-missing-sku_report_20260718.md
        — this report

## Behaviour

- One or more unknown SKU lines no longer reject the whole payload.
- The sale header is inserted and its note receives one explicit
  SKU не знайдено: <sku> (qty <n>) entry per skipped payload item.
- Only matched products create sale_items.
- items_skipped returns an array of sku, qty, unit_price objects and
  header_only is true where no SKU matched.
- A fully unmatched order receives a header-only sales row so it remains
  visible for manual catalogue mapping. Its source discount total remains on
  the header; it has no allocatable sale items until the catalogue issue is
  resolved.
- For any order with matched items, the whole source discount_total is
  distributed only over matched gross value. Rounding is resolved on the final
  matched row, so inserted discount_alloc sums exactly to discount_total.
- Duplicate opencart_order_id remains the round-1 response: ok=true and
  duplicate=true with no extra rows.

## Dry-run result

Applied only to local Docker Supabase:

    Applying migration 0012_opencart_order_sync_partial_insert.sql...
    Finished supabase db push.
    local_migration_0012=applied

Three transaction-isolated local smoke tests passed:

    smoke_pass=partial insert: one matched + one unknown SKU
    smoke_pass=all matched + duplicate replay
    smoke_pass=header-only: all SKUs unknown
    residual_test_sales=0

The partial scenario verified one inserted item, the missing-SKU note, and
sum(discount_alloc) = 30.00 for the order discount. No cloud database,
OpenCart server, checkout, Apps Script, or Edge deployment was touched.

## PHP lint result

Not applicable: round 2 contains SQL only and deliberately does not modify the
already-deployed-by-design PHP hook.

## Idempotency

Migration 0012 is additive and uses create or replace function; Supabase
records it once in migration history. Re-posting an existing OpenCart order
still returns duplicate=true without inserting another header or items.

## Rollback

No destructive rollback was performed. If a rollback is required after cloud
deployment, add a new forward migration that restores the known round-1
function body from 0011_opencart_order_sync.sql. Do not edit 0011 or delete
0012 from migration history.

## Run command (owner)

After Claude review, deploy the new migration to the linked cloud project from
the NCRM directory:

    npx supabase migration list
    npx supabase db push

Do not run or upload the old PHP patch merely for this round. It remains a
separate owner deployment decision.

## Post-deploy QA checklist

- [ ] Cloud migration list shows 0012 applied after 0011.
- [ ] Local Studio/RPC test with one matched and one fake SKU produces one
      sale_items row, a visible missing-SKU note, and an items_skipped response
      array.
- [ ] The inserted matched-item discounts sum to sales.discount_total.
- [ ] All-matched payload has an empty items_skipped array and unchanged normal
      behaviour.
- [ ] Reposting the same opencart_order_id returns duplicate=true.
- [ ] A matched Mystery Box line in a partial order still creates its existing
      needs_assembly fulfilment.
- [ ] Before any storefront smoke, separately decide whether to upload the
      round-1 OpenCart patch; it is not applied according to owner evidence.

## Side effects / risks

- A header-only sale intentionally needs manual product mapping before it has
  any sale lines or item-level financial effect.
- The change is service-side only. It does not turn on the OpenCart pipeline;
  without the earlier PHP hook deployment, no live storefront request reaches
  this RPC.
- No secrets or customer data were added to source or this report.
