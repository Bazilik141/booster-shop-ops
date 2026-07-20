# Codex Handoff — NCRM-09c: Write-форми списання + РРЦ

Date: 2026-07-17 | Parent: NCRM-09. Split now stands as 09a (Done) / 09b (Done) / **09c (this task — списання+РРЦ)** / 09d (повернення, not scoped yet) / 09e (mystery box, not scoped yet). See §Scope note below for why the original "09c = списання/РРЦ/повернення/mystery" grouping was split further.

## 1. Task ID
NCRM-09c — Write-форми: списання (writeoff) і РРЦ (RRC). Blocker NCRM-09b is Done, owner-confirmed.

## Scope note (why this is narrower than the original NCRM-09 plan)
`plans/NCRM-09_write-forms-auth-split_20260717.md` originally grouped списання/РРЦ/повернення/mystery box into one "09c". While scoping the actual work, повернення (returns) turned out to need a **new** repository (no `refunds.repo.ts` exists) and has two materially different flows (a normal resellable/damaged/money-only return vs. an unopened Mystery return, which is guarded by `fn_prepare_refund_item`/`fn_reverse_mystery_fulfillment` to force the Mystery-specific path). Mystery box fulfillment is a full reservation→commit state machine (`needs_assembly → reserved → committed/released/reversed`) with component-eligibility filtering, already backed by DB RPCs (`fn_reserve_mystery_fulfillment`, `fn_commit_mystery_fulfillment`, `fn_release_mystery_fulfillment`) but no UI or repo wrapper at all. Bundling both of those with списання/РРЦ (which are both already repo-ready, single-flow, low-risk) into one Codex handoff would mix a quick low-risk change with two open-ended ones. This handoff covers only списання+РРЦ; returns and Mystery box get their own scoped handoffs later (09d/09e), each written after reading the relevant migrations in the same depth this one required.

## 2. Context
- Repo-layer convention (`ncrm/README.md`): UI (`ncrm/app/*`) never queries Supabase directly — only `ncrm/lib/repositories/*.repo.ts`.
- **Both mutation functions already exist and are ready to call:** `lib/repositories/writeoffs.repo.ts` → `addWriteoff(payload)` (already takes `createdBy`, wired in NCRM-09a), `lib/repositories/products.repo.ts` → `updateRrc(payload)` (no `created_by` — `product_prices` has no such column, confirmed in `0010`, do not add one). Get the acting user via `getCurrentStaff()` (`lib/auth/session.ts`).
- `writeoffs.type` is a hard DB check constraint (`0003`): `'Власне відкриття' | 'Маркетинг' | 'MBOX' | 'Інше'`. **`MBOX` must not be a selectable option in this form.** A trigger (`fn_guard_mbox_writeoff`, `0007`) hard-blocks any `MBOX` writeoff insert that wasn't created by `fn_commit_mystery_fulfillment` (checked via a session-local `app.mystery_commit` flag this form will never set) — so a user picking `MBOX` here would hit a raw DB exception, not a clean UI error. Exclude it from the dropdown entirely rather than relying on the DB to reject it gracefully.
- This form is explicitly **not** the same thing as `inventory_adjustments`/`inventory_adjustment_items` (the newer signed-correction model from the financial contract §3.4, tracked separately as NCRM-13, not started). `списання` here means the existing `writeoffs` table only — do not touch `inventory_adjustments` in this task.
- RRC: `updateRrc` upserts into `product_prices` keyed on `(product_id, effective_from)` with `source` defaulting to `'manual'`. The read side (`v_current_rrc`, used across NCRM-08's screens) already filters to `price_kind = 'manual'` — verify this still lines up with what `updateRrc` writes before assuming no further change is needed.
- Product/SKU selection: reuse `listSku()` (`products.repo.ts`, from NCRM-08) for both forms' product pickers — do not build a new product list query.
- Access: both forms inherit the NCRM-09a route gate (`owner`/`admin` only) automatically — no new access logic needed.

## 3. Goal
Two small write forms — a writeoff form (type/reason/date/items) and an RRC-update form (product + new RRC + effective date + optional note) — that call the existing `addWriteoff`/`updateRrc` functions with a real `createdBy` where applicable. No new business logic; FIFO/stock effects of a writeoff are already handled by existing triggers from `0006` — verify this against the actual trigger definitions before assuming, don't just trust this summary.

## 4. What to change (scope)
- New `ncrm/app/writeoffs/` route (list if none exists — NCRM-08 did not cover writeoffs as a read screen — + `ncrm/app/writeoffs/new/page.tsx` form). Type dropdown limited to `'Власне відкриття' | 'Маркетинг' | 'Інше'` (no `MBOX`). Repeatable item block (product + qty + note) same pattern as the sale/purchase forms from 09b.
- New `ncrm/app/sku/[id]/rrc/page.tsx` or an inline RRC-update control on the existing `ncrm/app/sku/page.tsx`/`[id]` screen (NCRM-08) — Codex's call on placement, but it must be reachable from the SKU screen, not a hidden/unlinked route. Fields: new RRC value, effective date (default today, matching `updateRrc`'s existing default), optional note.
- Reuse `ncrm/lib/repositories/reference.repo.ts` (from 09b) if a writeoff-reason or type lookup is needed — verify first whether `type`/`reason` are free text or need a lookup table; per `0003`'s check constraint above, `type` is a fixed literal set, not a reference table, so a hardcoded dropdown (matching the exact DB-constraint strings) is correct, not a new lookup query.
- Links from `ncrm/app/layout.tsx` or the relevant list pages to the new writeoff form, same pattern as 09b's "Новий продаж"/"Нова закупка" links.

## 5. What NOT to touch
- No refund/return form or repository — NCRM-09d, separate handoff, not yet written.
- No Mystery box reservation/assembly UI or repository — NCRM-09e, separate handoff, not yet written.
- No `inventory_adjustments`/`inventory_adjustment_items` — that's NCRM-13, unrelated to this task despite the name similarity to "списання".
- No changes to `addWriteoff`, `updateRrc`, or any trigger/function in `0001`-`0010` — call them, don't modify them.
- Do not add `MBOX` as a selectable writeoff type under any circumstance in this task.
- No new migration expected. If something genuinely requires one, stop and flag it rather than adding one silently.
- `ncrm/middleware.ts`, `ncrm/lib/auth/*`, `ncrm/app/login/*` — auth is done (09a), out of scope.
- `ncrm/supabase/migrations/*`, `ncrm/scripts/import-history/*`, `ncrm/import/*` — untouched.
- Live Apps Script CRM, Google Sheet, OpenCart — separate codebase.
- Standard protected zones (not present in `ncrm/`, confirm no accidental cross-touch): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.

## 6. Likely files/areas
- New `ncrm/app/writeoffs/page.tsx` (list, if none exists), `ncrm/app/writeoffs/new/page.tsx` (+ form/action)
- RRC control added to `ncrm/app/sku/page.tsx` or `ncrm/app/sku/[id]/page.tsx` (+ form/action) — verify which SKU route currently exists before assuming a detail page is there
- `ncrm/app/layout.tsx` or list pages — new writeoff-form link
- `diagnostics/NCRM-09c_writeoff-rrc-forms_report_<date>.md` (new)
- No changes expected in migrations, auth files — verify before assuming otherwise.

## 7. Acceptance criteria
- [ ] `cd ncrm && npm run build` succeeds, 0 errors
- [ ] `git diff` on `ncrm/supabase/migrations/*` is empty (or flagged, not silently added)
- [ ] A writeoff can be created through the UI with type `Інше`/`Власне відкриття`/`Маркетинг` and 1+ items; `created_by` is set correctly
- [ ] The writeoff type dropdown does not offer `MBOX` as an option
- [ ] An RRC update through the UI is reflected in `product_prices` and visible on the SKU screen's current-RRC value afterward
- [ ] Both forms are reachable only as `owner`/`admin` (inherits NCRM-09a middleware — spot-check)
- [ ] No file under `ncrm/app/` imports `@/lib/supabase/client` directly (grep-verifiable, same rule as prior NCRM-09 tasks)
- [ ] Report confirms `inventory_adjustments`/`inventory_adjustment_items` were not touched

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema — none of `bs-checkout-smoke`/`bs-merchant-schema-qa`/`bs-seo-risk-gate` apply. Lower risk than 09b (no multi-table allocation, no rollback logic) — both underlying functions are simple single/two-table inserts already proven by 09a/09b. Still local-only, no deployment.

- [ ] `npm run dev`, log in as owner
- [ ] Create a test writeoff (`Інше`, 1 item), confirm it appears in the writeoff list with correct `created_by`
- [ ] Update RRC for a test SKU, confirm the new value shows on `/sku`
- [ ] Confirm `MBOX` is absent from the writeoff type dropdown
- [ ] Log out / blocked-role check — same as prior QA, spot-check rather than full repeat
- [ ] `git status` before commit — no `.env`/keys staged

## 9. Rollback note
App/repo-layer only, no schema change, no live-system write. Rollback = revert the new writeoff routes and the RRC control, via `git`. Test writeoff/RRC rows created during QA can be identified and cleaned up manually; do not `db reset` if imported history must be preserved (standing NCRM-03 warning).

## 10. Recommended status after execution
Stays `In progress` (parent NCRM-09) until Claude reviews the diff and owner runs the §8 smoke test. NCRM-09d (повернення) gets scoped and written next — it needs its own read-through of `refund_items`/`fn_prepare_refund_item`/the Mystery-return guard before a handoff can be written responsibly, not a quick follow-on to this one.
