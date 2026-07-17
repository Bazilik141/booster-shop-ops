# Codex Report — NCRM-08: read screens

Date: 2026-07-16

## Scope

Implemented the handoff's local, read-only NCRM screens: real summary, orders and order detail, full stock list, full active-SKU catalogue, customers, and shared navigation. The NCRM-02 demo copy is removed from `/`.

No migration, import, legacy CRM, OpenCart, checkout, SEO, payment, or public-deployment change was made.

### Query-design decision

- **Customers:** a repository-layer aggregation over `sales` plus `sale_items`/`v_sale_item_financials`; phone normalisation matches `v_repeat_customers` (`[^0-9+]` removed). It keeps one-order customers and marks `isRepeat` from the aggregate rather than using `v_repeat_customers` as the base list. The aggregation and pagination happen after the read in `customers.repo.ts`, deliberately avoiding a new SQL view/migration in NCRM-08.
- **SKU catalogue:** repository-layer composition rooted in all active `products`, then nullable reads of `v_current_rrc`, `v_inventory_fifo_valuation`, `v_stock_alerts`, and `v_top_skus`. A SKU without trailing-30-day sales stays in the result with `0` performance metrics; `v_top_skus` is never the catalogue base.

## Files touched

```
ncrm/app/page.tsx                         — real summary/dashboard
ncrm/app/layout.tsx, ncrm/app/globals.css — shared navigation and table layout
ncrm/app/orders/page.tsx                  — orders list
ncrm/app/orders/[id]/page.tsx             — order lines and refunds read view
ncrm/app/stock/page.tsx                   — full v_stock_alerts list
ncrm/app/sku/page.tsx                     — full active catalogue
ncrm/app/customers/page.tsx               — all normalized phone customers
ncrm/lib/domain/analytics.ts              — P&L v2, guardrail, quality, forecast, SKU types
ncrm/lib/domain/orders.ts                 — order read types
ncrm/lib/domain/customers.ts              — customer read types
ncrm/lib/domain/index.ts                  — domain exports
ncrm/lib/repositories/analytics.repo.ts   — extended analytics and SKU catalogue reads
ncrm/lib/repositories/orders.repo.ts      — sales/reference/financial/refund reads
ncrm/lib/repositories/customers.repo.ts   — customer read aggregation
ncrm/lib/repositories/index.ts            — repository exports
```

## Dry-run result

```text
cd ncrm && npm run build
✓ Compiled successfully
✓ Running TypeScript
Route (app)
ƒ /
ƒ /customers
ƒ /orders
ƒ /orders/[id]
ƒ /sku
ƒ /stock
```

Additional static boundary checks:

- `git diff --name-only -- ncrm/supabase/migrations` → empty.
- No file under `ncrm/app/` imports `@/lib/supabase/client`.
- No `insert`, `update`, `delete`, or `upsert` call in the NCRM-08 UI/repositories.

## php -l result

Not applicable: this is a TypeScript/Next.js app, not a PHP server patch. `npm run build` completed with TypeScript validation.

## Idempotency

Not applicable: no runner, schema change, or mutation exists. Rendering these routes issues read queries only.

## Rollback

App/repository layer only. Revert the NCRM-08 files above; no database state, migration, cloud Supabase, legacy CRM, Apps Script, OpenCart, or public deployment needs rollback.

## Run command (owner)

```bash
cd ncrm || exit
npx supabase start
npx supabase db reset
npm run dev
```

## Post-deploy QA checklist

- [ ] Keep the app localhost-only; do not deploy it to Vercel, a public URL, or a shared machine before the owner assigns the NCRM-08/09 auth split.
- [ ] Open `/`, `/orders`, `/stock`, `/sku`, and `/customers`; confirm there is no runtime Supabase error.
- [ ] Spot-check 2–3 orders against known source data, including labels for channel/order/payment status.
- [ ] Confirm `/sku` contains a known active SKU without sales in the last 30 days.
- [ ] Confirm `/customers` contains a known customer with exactly one order.
- [ ] Before any commit, check `git status`; do not stage `.env.local`, credentials, Docker temp files, imports, or unrelated dirty-tree files.

## Side effects / risks

- The screens expose financial data (revenue, margin, COGS, RRC) end-to-end. The current app uses `service_role`, which bypasses RLS, and has no login/session/per-role check. This is intentionally out of scope and remains an owner decision for the NCRM-08/09 split.
- The customer aggregation reads all local `sales` rows before aggregation so paging is correct after phone normalisation. This is appropriate for the current local dataset; move it into a dedicated SQL view only under a later, explicitly approved migration/performance task.
- The repository already had unrelated dirty and untracked files, including NCRM-03 import artifacts and prior untracked migrations. They were not edited by NCRM-08.
