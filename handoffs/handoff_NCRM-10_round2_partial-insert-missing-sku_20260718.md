# Codex Handoff — NCRM-10 round 2: partial insert + missing-SKU flag

Date: 2026-07-18 | Parent: NCRM-10 (round 1 delivered, now live) | Round 1 report: `diagnostics/NCRM-10_order-pipeline-opencart-supabase_report_20260718.md`

## 1. Task ID
NCRM-10 round 2 — owner review of round 1: one behavior change requested in `fn_ingest_opencart_order` (`ncrm/supabase/migrations/0011_opencart_order_sync.sql`). Everything else in round 1 (hook location, test-filter, idempotency, secrets) is accepted as-is, do not touch.

## 2. Context
Round 1 implemented fail-closed on unmatched SKU: if any item's SKU isn't found in `products`, the whole order is rejected (exception raised, nothing inserted — not even the sale header). The original NCRM-10 handoff asked for partial insert instead: insert the order anyway, skip only the unmatched item, flag it visibly. Owner reviewed round 1 and confirmed: go with the original ask (partial insert + flag), not fail-closed.

**Production context:** owner has already deployed round 1 — migration `0011` pushed to cloud, Edge Function live, OpenCart hook patch applied and self-deleted on the live server. The pipeline is receiving real order traffic right now. Apps Script remains untouched and fully parallel, so an unmatched-SKU order today isn't lost (it still lands in Sheets via Apps Script) — it's just invisible in the new Supabase-backed CRM until this fix ships. Not an emergency, but should ship before the pipeline is trusted as primary.

**Not in scope for this round (owner decision, do not touch):** the PHP-side test-filter's lastname check only matches Latin `leusenko`, not Cyrillic `Леусенко`. The Edge Function's own independent test-filter check already matches Cyrillic correctly, so no order can leak into `sales` because of this — it only means a Cyrillic-named test order still gets POSTed (then skipped). Owner will use a Latin `TEST` word in the SKU/product name when placing test orders going forward (already reliably caught by both layers, confirmed in round 1 review). Do not modify `isTestOrder()` in either the PHP patch or `index.ts` this round.

## 3. Goal
An order with a mix of matched and unmatched SKUs creates a `sales` row and `sale_items` rows for the matched items only; the unmatched item(s) are visible directly on the sale record (not just a server log), and the response makes the skip explicit and machine-readable.

## 4. What to change (scope)
- New additive migration `ncrm/supabase/migrations/0012_opencart_order_sync_partial_insert.sql` — do **not** edit `0011` in place (it's already applied to the live cloud database; editing an already-applied migration file risks a hash/history mismatch with `supabase migration list`). Use `create or replace function public.fn_ingest_opencart_order(...)` inside the new migration to redefine the function body — Postgres allows this without dropping/recreating the function or touching existing grants.
- Revised logic inside the function:
  - Keep the existing bulk SKU pre-check (`payload_skus` CTE), but instead of `raise exception` on any missing SKU, collect the missing ones and continue.
  - Insert the `sales` header regardless of missing SKUs (unless zero items match at all — decide and state explicitly in the report whether a sale with *zero* matched items still gets a header-only row or is rejected; either is acceptable, just don't leave it ambiguous).
  - Append a clear flag to the sale's `note` — e.g. `SKU не знайдено: <sku> (qty <n>)` for each missing item, appended to whatever note text already exists — so it's visible directly on the order in the CRM UI, not just in logs.
  - Insert `sale_items` only for matched items.
  - **Recompute discount allocation** using only the matched items' gross value as the denominator, not the original full-order gross — otherwise the sum of inserted `discount_alloc` will fall short of `discount_total` by the skipped item's share. State in the report which approach was taken.
  - Return value gains a field for the skipped items, e.g. `items_skipped: [{sku, qty, unit_price}]`, alongside the existing `ok`/`duplicate`/`sale_id`/`items_inserted`. Exact key names are Codex's call — just make sure the Edge Function response (which already passes `data` straight through) surfaces it without needing a separate change.
- No change expected in `ncrm/supabase/functions/order-sync/index.ts` — it already returns the RPC's JSON body as-is. Touch it only if the new return shape genuinely requires it, and say so explicitly in the report if you do.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0011_opencart_order_sync.sql` — leave as the historical round-1 record. Fix goes in a new `0012` migration only.
- `isTestOrder()` in both the PHP patch and `index.ts` — owner explicitly declined the Cyrillic-lastname fix this round (see Context). Do not touch it as unrequested scope.
- The OpenCart PHP patch/hook itself — already deployed and self-deleted on the live server. Nothing to change there this round.
- `ncrm/supabase/config.toml`, secrets, Apps Script, RLS policies, migrations `0001`–`0010` — untouched.

## 6. Likely files/areas
- `ncrm/supabase/migrations/0012_opencart_order_sync_partial_insert.sql` (new)
- `diagnostics/NCRM-10_round2_partial-insert-missing-sku_report_<date>.md` (new)
- No changes expected elsewhere — flag explicitly in the report if something else genuinely needed touching.

## 7. Acceptance criteria
- [ ] Order with one matched + one unmatched-SKU item → `sales` row created, `sale_items` has exactly one row (the matched item), `sales.note` clearly names the missing SKU and quantity, RPC response includes the skipped item(s)
- [ ] Sum of inserted `sale_items.discount_alloc` for that order matches the order's `discount_total` (recomputed against matched items only, not silently short)
- [ ] Order with all SKUs matched → identical behavior to round 1 (regression check, nothing broken)
- [ ] Re-posting the same `opencart_order_id` → still returns `duplicate: true`, unchanged from round 1
- [ ] `git diff` shows only the new `0012` migration (plus the new diagnostics report) — no changes to `0001`-`0011`, `index.ts`, `deno.json`, `config.toml`, or the PHP patch unless explicitly justified in the report
- [ ] No secret values anywhere in diff/report

## 8. QA / smoke test (owner)
Not a checkout-UI change — this is a live production database function serving real webhook traffic already. Local-only testing is fine for the fix itself (insert a payload with a deliberately fake SKU via SQL/RPC call in Studio, not through the real storefront). Once Claude review is OK on the round-2 diff:
- [ ] Re-run the original NCRM-10 handoff's smoke steps 1, 8, 12–15 (real checkout still fine, success redirect, test-filter, real sync, idempotency, fail-safe) — these were deliberately deferred pending this fix
- [ ] Additionally: confirm the round-1 "Mystery Box SKU creates needs_assembly" behavior still fires correctly when the Mystery SKU is among the *matched* items in a partial-insert scenario

## 9. Rollback note
Additive only — a further migration can `create or replace function` back to the round-1 body if needed. No backfill of already-processed orders implied; if any order was silently dropped by the round-1 fail-closed behavior between deploy and this fix, it still exists in Apps Script/Sheets and can be manually re-entered if the owner wants it in the new CRM too — not automatic.

## 10. Recommended status after execution
NCRM-10 stays `In progress`. Full smoke test (§8) happens only after this round lands and Claude reviews the diff — owner's explicit sequencing decision, not run twice.
