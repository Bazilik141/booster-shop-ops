# Codex Report — ORDER-280: CRM discount correction

Date: 2026-07-25

## Scope

Correct the live CRM sale rows for `OC-FOP-0280` to match the owner-confirmed
OpenCart order: OP-14 × 2 at 200.00 UAH, OP-15 × 2 at 210.00 UAH, and OP-08 × 1
at 170.00 UAH; gross 990.00 UAH, discount 148.50 UAH, payable 841.50 UAH.

## Live evidence and cause

Before correction, the three live `Продажі` rows already contained the intended
SKUs, quantities, and unit prices, but their discounts totalled only 87.00 UAH
(30.00 + 31.50 + 25.50). Therefore the sheet formula in `K` correctly showed
903.00 UAH; the dashboard was displaying that stored source value, not making
an arithmetic error.

The local Apps Script source-copy shows that the OpenCart importer allocates
discount only from the incoming `payload.totals`, then ignores later payloads
for the same `order_key` (`ignored_existing_order`). This is consistent with a
post-order product change leaving the CRM discount stale. It is source-copy
evidence, not proof of the deployed Apps Script version.

## Data touched

Live spreadsheet `Booster Shop CRM — облік товарів`, sheet `Продажі`:

| Range | Before | After |
|---|---:|---:|
| `J227` | 30.00 | 60.00 |
| `J228` | 31.50 | 63.00 |
| `J229` | 25.50 | 25.50 |

No SKU, quantity, unit price, formula, formatting, validation, status, or
inventory-cost cell was changed.

## Verification

The existing formulas in `K227:K229` recalculated to 340.00, 357.00, and
144.50 UAH. Their sum is 841.50 UAH; the discount sum is 148.50 UAH.

## Rollback

Restore the former discount values in the same cells: `J227=30`, `J228=31.5`,
`J229=25.5`. This would restore the incorrect total and is not recommended.

## Remaining preventive work

Manual substitutions made after OpenCart creates an order need a supported
CRM-order refresh path. Until one exists, re-check the three CRM inputs
(`SKU`, quantity, unit price, discount) after any post-order substitution.
