# Codex Handoff — NCRM-09e: Mystery Box fulfillment UI (reserve → commit → release)

Date: 2026-07-17 | Parent: NCRM-09. Split status: 09a (Done) / 09b (Done) / 09c (Done) / 09d (paused, merged into NCRM-13 — physical restock on returns is unimplemented, see `context-index.md` NCRM-13 row) / **09e (this task)**.

## 1. Task ID
NCRM-09e — Mystery Box fulfillment: reserve components, commit shipment, release before shipment. Blocker NCRM-09b is Done (Mystery sales already create fulfillment rows automatically, confirmed by owner QA screenshots showing `provisional` cost state on Mystery Box line items).

## 2. Context
- Repo-layer convention: UI (`ncrm/app/*`) never queries Supabase directly — only `ncrm/lib/repositories/*.repo.ts`.
- **A Mystery fulfillment is already created automatically** — `fn_create_mystery_fulfillment` (trigger, `0007`) inserts a `mystery_fulfillments` row (`state='needs_assembly'`) whenever a `sale_items` row is inserted for a product listed in `mystery_box_types`. This already happens today through the 09b sale form; this task builds the UI for what happens *after* that row exists. No new sale-side wiring needed.
- **Component eligibility is a ready-made view** — `public.v_mystery_eligible_components` (`0007`) already joins game/JP-language/sealed-pack/not-outlet/no-override/availability exactly per the financial contract §4.2. Query it filtered by the fulfillment's Mystery product (`mystery_product_id`) — do not reimplement eligibility filtering in TypeScript.
- **Pack-count and holo cost are fully data-driven** — `mystery_box_types` (`0003`) has `expected_pack_count`, `has_holo`, `holo_cost`, `provisional_unit_cost` per Mystery SKU. Do not hardcode "5 packs for ST / 7 for XL" anywhere in the UI; read it from this table (already exposed via `mystery_box_types` — verify if a repo read for it already exists via `products.repo.ts`/`v_current_rrc`-adjacent queries, or add a small typed read if not).
- **Three DB RPCs do the actual state transitions — call them, do not reimplement their logic:**
  - `fn_reserve_mystery_fulfillment(p_sale_item_id uuid, p_components jsonb) returns mystery_fulfillments` — `p_components` is a JSON **array** of `{"product_id": "<uuid>", "qty": <int>}` objects (verify this shape against the actual function body in `0007` before coding, don't trust this summary blindly). The RPC itself validates: total selected qty equals `expected_pack_count × sale_item.qty`, each component is available, the sale isn't cancelled/refunded, and the fulfillment is currently `needs_assembly`. It raises a clear Postgres exception on any violation — surface that exception message to the user, don't pre-validate the sum in JS as the source of truth (client-side running-total display for UX is fine, just don't treat it as authoritative).
  - `fn_commit_mystery_fulfillment(p_sale_item_id uuid) returns mystery_fulfillments` — no components needed, operates on the already-reserved items. Creates the final MBOX writeoff, `mystery_contents`, actual COGS, and consumable consumption atomically (per the financial contract §4.3). This is the point where the sale item's cost state moves from `provisional` to `actual` — verify this against the trigger chain before stating it confidently in the report.
  - `fn_release_mystery_fulfillment(p_sale_item_id uuid) returns mystery_fulfillments` — cancels an unshipped reservation, returns reserved components to available stock.
- **Explicitly not in scope: `fn_reverse_mystery_fulfillment`.** That function handles a post-commit reversal (an unopened Mystery return) and is entangled with the refund/`refund_items` flow that NCRM-09d just got paused and folded into NCRM-13 (physical restock on resellable/mystery returns isn't implemented yet — see `context-index.md` NCRM-13 row, decided 2026-07-17). Do not build a "reverse"/"undo shipped Mystery box" action in this task.
- **`created_by` gap, worth a light fix, not a blocker:** `mystery_fulfillments.created_by` exists (added in `0010`, same as `sales`/`purchases`/`writeoffs`) but none of the three RPCs above accept or set it — they're `SECURITY INVOKER` functions with no such parameter. Recommend the repo wrapper does a small follow-up `UPDATE mystery_fulfillments SET created_by = $staffId WHERE id = $fulfillmentId` after a successful `reserve` call (first state transition away from the auto-created NULL row) rather than modifying the RPC itself — state so explicitly in the report if implemented, or flag it as deferred if not.
- Access: inherits the NCRM-09a route gate (`owner`/`admin` only) — no new access logic needed.
- No repo/domain module for Mystery exists yet (`lib/repositories/`, confirmed empty of any `mystery`-related file) — this is fully new, unlike 09b/09c which extended existing functions.

## 3. Goal
A queue screen listing sale items awaiting assembly (`needs_assembly`) and in-progress reservations (`reserved`), a reservation screen to pick components against `v_mystery_eligible_components` and call `fn_reserve_mystery_fulfillment`, and commit/release actions calling their respective RPCs. All three RPCs already contain the actual business rules (eligibility, pack count, availability, atomicity) — this task is UI plus thin RPC-calling repo functions, not new domain logic.

## 4. What to change (scope)
- New `ncrm/lib/domain/mystery.ts` + `ncrm/lib/repositories/mystery.repo.ts`:
  - `listMysteryQueue({ state? })` — list `mystery_fulfillments` joined to `sale_items`/`sales`/`products` for context (order no, customer, Mystery SKU, qty, state), default to `needs_assembly` + `reserved`.
  - `getEligibleComponents(mysterySkuOrProductId)` — reads `v_mystery_eligible_components` filtered to the fulfillment's Mystery product.
  - `getMysteryBoxType(productId)` — reads `expected_pack_count`/`has_holo`/`holo_cost` for the pack-count/holo display (verify exact source — direct table read is fine, it's a small reference row).
  - `reserveMysteryFulfillment(saleItemId, components, staffId)` — calls the `fn_reserve_mystery_fulfillment` RPC, then the `created_by` follow-up update described above.
  - `commitMysteryFulfillment(saleItemId)` — calls `fn_commit_mystery_fulfillment`.
  - `releaseMysteryFulfillment(saleItemId)` — calls `fn_release_mystery_fulfillment`.
- New `ncrm/app/mystery/page.tsx` — queue list (needs_assembly / reserved), linking into a per-fulfillment detail/reserve view.
- New `ncrm/app/mystery/[saleItemId]/page.tsx` (+ form/action) — shows the Mystery SKU, expected pack count/holo, the eligible-component list with quantity inputs, a running total vs. expected count (client-side convenience only, not authoritative), and a submit that calls `reserveMysteryFulfillment`. Once `reserved`, the same page (or a state-conditional view) shows commit/release buttons instead of the picker.
- `ncrm/app/layout.tsx` or `/orders` — a discoverable link to `/mystery` (e.g. "Mystery box" nav entry, matching the pattern from 09b/09c).

## 5. What NOT to touch
- No `fn_reverse_mystery_fulfillment` call or "reverse/undo" UI — out of scope, entangled with NCRM-13 (see Context).
- No changes to `fn_reserve_mystery_fulfillment`, `fn_commit_mystery_fulfillment`, `fn_release_mystery_fulfillment`, `v_mystery_eligible_components`, or any trigger/function in `0001`-`0010` — call them, don't modify them.
- No refund/return form or repository — that work is now NCRM-13, not this task.
- No changes to `addSale`/the sale form from 09b — Mystery fulfillment auto-creation on sale is already working, don't touch that trigger path.
- No new migration expected. If something genuinely requires one (e.g. a missing index for a slow queue query), stop and flag it rather than adding one silently.
- `ncrm/middleware.ts`, `ncrm/lib/auth/*`, `ncrm/app/login/*` — auth is done (09a), out of scope.
- `ncrm/supabase/migrations/*`, `ncrm/scripts/import-history/*`, `ncrm/import/*` — untouched.
- Live Apps Script CRM, Google Sheet, OpenCart — separate codebase.
- Standard protected zones (not present in `ncrm/`, confirm no accidental cross-touch): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.

## 6. Likely files/areas
- New `ncrm/lib/domain/mystery.ts`, `ncrm/lib/repositories/mystery.repo.ts`
- New `ncrm/app/mystery/page.tsx`, `ncrm/app/mystery/[saleItemId]/page.tsx` (+ form/action)
- `ncrm/app/layout.tsx` — nav link
- `diagnostics/NCRM-09e_mystery-box-fulfillment_report_<date>.md` (new)
- No changes expected in migrations, auth files, sale form — verify before assuming otherwise.

## 7. Acceptance criteria
- [ ] `cd ncrm && npm run build` succeeds, 0 errors
- [ ] `git diff` on `ncrm/supabase/migrations/*` is empty (or flagged, not silently added)
- [ ] `/mystery` lists a real `needs_assembly` fulfillment created by a Mystery sale from 09b (create one during QA if none exists)
- [ ] Reserving with a correct total (matching `expected_pack_count × qty`) succeeds and moves the fulfillment to `reserved`; an incorrect total is rejected with the RPC's own error message shown to the user, not a generic failure
- [ ] Committing a `reserved` fulfillment succeeds, creates an MBOX writeoff (verify in `writeoffs` with `type='MBOX'` and a non-null `mystery_fulfillment_id`), and the originating sale item's cost state moves off `provisional`
- [ ] Releasing a `reserved` (not yet committed) fulfillment returns it to `needs_assembly` and frees the reserved components' available quantity
- [ ] No `fn_reverse_mystery_fulfillment` call anywhere in the diff
- [ ] Reachable only as `owner`/`admin` (inherits NCRM-09a middleware — spot-check)
- [ ] No file under `ncrm/app/` imports `@/lib/supabase/client` directly (grep-verifiable, same rule as prior NCRM-09 tasks)
- [ ] Report states whether the `created_by` follow-up update was implemented, and confirms `v_mystery_eligible_components` was used as-is (no reimplemented eligibility logic)

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema — none of `bs-checkout-smoke`/`bs-merchant-schema-qa`/`bs-seo-risk-gate` apply. This is the most state-heavy piece of NCRM-09 (five fulfillment states, atomic commit touching writeoffs/contents/COGS/consumables in one RPC) — the RPC itself is already proven logic from NCRM-05/06 migrations, but this is the first time anything calls it. Still local-only, no deployment.

- [ ] `npm run dev`, log in as owner
- [ ] Create a test Mystery sale (from the 09b form, e.g. `PKM-JP-MBX-ST`), confirm it shows on `/mystery` as `needs_assembly`
- [ ] Reserve it with exactly the expected pack count (5 for ST, 7 for XL — but read the real number off the screen, don't hardcode it while testing either), confirm it moves to `reserved` and the picked components' available stock drops
- [ ] Try reserving a different fulfillment with a wrong total (too few/too many packs), confirm a clear rejection, not a silent partial reservation
- [ ] Commit the correctly reserved one, confirm an MBOX writeoff appears and the sale item's cost state is no longer `provisional`
- [ ] Create a second test Mystery sale, reserve it, then release it before committing — confirm it returns to `needs_assembly` and components are available again
- [ ] Log out / blocked-role spot-check
- [ ] `git status` before commit — no `.env`/keys staged

## 9. Rollback note
App/repo-layer only — no schema change, no modification to the underlying RPCs. Rollback = revert `ncrm/app/mystery/*`, the new `mystery.ts`/`mystery.repo.ts`, and the nav link, via `git`. Any test fulfillments/reservations/MBOX writeoffs created during QA are real rows in the local DB (the commit RPC is not a dry run) — identify and clean up deliberately; do not `db reset` if imported history must be preserved.

## 10. Recommended status after execution
Stays `In progress` (parent NCRM-09) until Claude reviews the diff and owner runs the §8 smoke test — reserve, wrong-total rejection, commit, release. Once owner-confirmed, this closes out the NCRM-09 write-forms scope as originally split (09a/09b/09c/09e — 09d's remaining piece lives under NCRM-13 going forward, not as a separate NCRM-09 sub-task).
