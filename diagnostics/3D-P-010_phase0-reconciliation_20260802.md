# Codex Report — 3D-P-010 CRM packaging pull: V86 local package

Date: 2026-08-02

## Outcome

The packaging half of 3D-P-010 is ready as a source-anchored local patch for
**CRM Auto V86** (owner-confirmed deployed version: 2026-07-29 13:07). It is
not deployed and has made no CRM, Sheet, API, or token change.

Package:

- `patches/3D-P-010_crm-packaging-pull_20260802.js`
- `tests/3d-p-010-crm-packaging-pull.test.mjs`

## V86 evidence

The local V86 export contains complete current blocks for `apiAddSale_`,
`apiUpdateSale_`, `getPackagingCost_`, and `apiConsumables_`.

- `apiAddSale_` allocates resolved packaging to main CRM `Продажі!P` (column
  16) for each order line and writes the type to `AC` (column 29).
- `apiUpdateSale_` re-allocates the same order-level packaging cost across all
  contiguous rows with the same order number.
- `getPackagingCost_` resolves a named consumable from `Розхідники!A:C`.
- The 3D-P API returns sales rows with `row_number`, matches order number via
  `№ замовлення` (`N`), and permits only guarded `3dp_write` into `Продажі!G`.

## Patch behavior

The patch adds a fail-open post-save helper and three V86 anchor edits:

1. `apiAddSale_` records the newly created CRM row numbers and calls the helper
   after its normal cost writes.
2. `apiUpdateSale_` calls the same helper after its normal update writes.
3. The helper detects `BR-`, `FIG-`, and `ACC-3D-xxx` SKUs, sums the order's
   complete CRM `P` amount, reads bounded `3dp_sales`, selects the lowest
   matching 3D-P `row_number` by `N`, and uses `3dp_write` with
   `expected_current` for exactly one `G` cell.

It skips, logs without a token, and returns control to normal CRM flow when the
order has no 3D SKU, the 3D-P row is not yet present, properties are missing,
or the 3D-P API is unavailable. An equal current `G` is a no-op, avoiding a
second audit record.

## Local verification

```text
node --test tests/3d-p-010-crm-packaging-pull.test.mjs
5 passed, 0 failed

node --check patches/3D-P-010_crm-packaging-pull_20260802.js
PASS

git diff --check
PASS
```

The tests cover exact V86 anchors, multi-line order sum with a single first-row
write, no-3D-SKU no-fetch, API-outage fail-open behavior, and equal-value
no-op. They do not call a live endpoint.

## Owner deploy / smoke gate

1. Review the patch against CRM Auto V86; copy the helper and make its three
   marked anchor edits in the main CRM `Code.gs`.
2. In the **main CRM** Script Properties set `BOOSTER_3DP_URL` and
   `BOOSTER_3DP_SYNC_TOKEN`. The latter is the existing owner-role 3D-P API
   token; never put either value in source, Git, screenshots, or chat.
3. Deploy a new CRM Web App version (Version 87) through the normal owner flow.
4. Run a deliberate test order for: standard packaging, `Інше` custom cost,
   multiline order, later status update, no matching 3D-P row, and temporary
   3D-P API outage. Confirm normal CRM save still succeeds in both negative
   cases and `_Аудит_API` has at most one expected `Продажі!G` write per change.

## Fixture remains Phase 0

V86 proves only that `Розхідники` is read as name/category/cost (`A:C`). It
does not prove that the current admin UI accepts a new `Фурнітура` category or
what consumes/restores it, nor does it identify a separate 3D-P destination
for fixture cost. No fixture UI, inventory decrement, or fixture write has
been added.

## Recovery

Do not deploy the patch if review finds an anchor mismatch. After deployment,
revert the three hook edits and helper block as one unit; the 3D-P base API is
unchanged. A mistaken successful `G` write remains recoverable through
`_Аудит_API`.
## Addendum — safe post-deploy verifier, 2026-08-02

`tests/live-3dp010-packaging-verify.ps1` is a **read-only** verifier. After
an owner saves a deliberately created normal CRM test order, it reads V86
`recent_sales` and 3D-P `3dp_sales`, prints the minimal matching values, and
asserts that the aggregated CRM packaging total equals only the lowest matching
3D-P row's `Продажі!G`. It sends no CRM or 3D-P POST request.

Run it once per standard, `Інше`, or multi-line test case, with the deliberately
chosen test `OrderId` and expected positive packaging amount. `-ExpectNo3dpRow`
checks the missing-target fail-open case after the owner observes that normal
CRM save succeeded.

A fully automated end-to-end writer is intentionally not provided: V86 has no
test-only create/delete contract, so calling `apiAddSale_` would leave CRM sale
rows and automated config removal to simulate an outage would mutate live
Script Properties. The remaining API-outage check is therefore a short manual
owner gate; local package tests already cover fail-open behavior.