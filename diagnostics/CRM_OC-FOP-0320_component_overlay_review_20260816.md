# Claude Review — CRM: OC-FOP-0320 prepared cost repair + reported gift double-count

Date: 2026-08-16
Reviewer: Claude (chat). Read-only. No file in `crm/apps-script/`, no spreadsheet cell,
no Notion property and no dashboard file was modified by this review.

## Verdict

**Review OK. The reported defect is fixed and proven live. Findings below are latent
risks for a separate task, not blockers.**

The repair removes the Mystery Box composition double count (targeted component ledger
counted again through its linked writeoff). Verified against the owner's live run, not
only against source.

### Owner run verification — 2026-08-16 13:44, order `OC-FOP-0320`

`repairOCFOP0320MysteryBoxCost()` returned `ok: true`, `already_applied: false`,
`writeoff_formula_rows_repaired: 4`.

| Value | Predicted in the cost diagnostic | Live result |
| --- | ---: | ---: |
| Mystery Box management cost per unit (row 281) | 764.41 | 764.41 |
| Mystery Box PRRO cost per unit (row 281) | — | 707.96 |
| Mystery Box management cost, 2 units | 1,528.82 | 1,529 (dashboard) |
| Order-level component share allocated to the Mystery Box, management | 53.96 | 53.96 (26.98 × 2) |
| Order-level component share allocated to the Mystery Box, PRRO | 27.58 | 27.58 (13.79 × 2) |
| Order profit | about +812.48 / 30.1% | 812 / 30.1% (dashboard) |
| Non-Mystery row 280 | unchanged | unchanged (43.00 / 47.54) |

Every predicted figure reproduced exactly. `mystery_cost.mgmt_unit` is 737.43 and the
final stored value is 764.41; the 26.98 difference is the order-level component overlay
applied once on top, which is the intended behaviour. The read-only integrity check ran
clean afterwards (`problems: []`).

### What the earlier "Return for changes" draft got wrong

The first draft of this review read the owner's report of doubled *"подарунки"* as
order-level marketing gifts and concluded the fix was incomplete. The doubled items were
the Mystery Box composition components entered through the same
"Компоненти / маркетингові подарунки" form, which is precisely what this repair fixes.
The findings below remain valid as unexercised code paths, but no evidence shows any of
them has been triggered in production.

## Scope of the review

- `crm/apps-script/Code.gs` — `fixSaleCostForRow_`, `recalculateMysteryBoxOrderCost_`,
  `mysteryBoxComponentLedgerScope_`, `orderComponentTotals_`, `applyOrderComponentCost_`,
  `replaceOrderComponentAudit_`, `trimCostAudit_`, `appendOrderComponents_`,
  `apiUpdateSaleWithComponents_`, `project3dpAccountingToCrm_`,
  `repairMysteryBoxOrderComponentCost_`.
- `dashboard/booster-dashboard.html` — order editor component collection and request-ID reuse.
- `crm/apps-script/tests/` — existing coverage.
- `diagnostics/CRM_OC-FOP-0320_cost_diagnostic_report_20260816.md`.

The reviewer performed no live CRM read: the read-only Apps Script GET API exposes
`summary`, `orders`, `stock_alerts`, `sku_list` only, none of which return the component
ledger or the sale cost-audit column. Runtime evidence in the verdict above comes from
the owner's Apps Script execution log and dashboard, captured 2026-08-16. Everything in
the "Latent risk" sections is source evidence only.

## What the prepared repair does correctly

- `mysteryBoxComponentLedgerScope_()` builds `linked_writeoffs` for *all* ledger rows of
  the order, and `recalculateMysteryBoxOrderCost_()` skips any writeoff present in that
  map. This removes the ledger-plus-writeoff double count and also stops order-level
  gifts leaking into Mystery Box composition. Correct.
- `applyOrderComponentCost_()` zeroes `targeted` for Mystery Box rows, so the frozen
  composition is not added a second time on top of the recomputation. Correct.
- Legacy unlinked writeoffs keep the pre-ledger fallback. Correct.
- `ensureComponentWriteoffFormulaRows_()` writes the canonical formulas only when all
  five target cells are blank and throws on any unexpected formula shape. This is the
  right conservative behaviour for derived-data cells.
- `project3dpAccountingToCrm_()` rewrites cost columns L/M **and** the audit column AE
  together, and runs before the component overlay. That ordering is safe.

## Latent risk 1 — cost-audit marker is a fragile idempotency key

Not observed in production. Recorded because the repair depends on this mechanism.

`applyOrderComponentCost_()` is a re-appliable overlay. Its only protection against
re-adding an amount already present in `Продажі!L:M` is the marker
`order_components_prro=<x>,mgmt=<y>` stored in the **cost-audit cell, column AE (31)**:

```
const audit    = String(sales.getRange(row, 31).getValue() || '');
const prior    = CRM_ORDER_COMPONENT_AUDIT_RE_.exec(audit);
const basePrro = round2_(num_(current[0]) * count - priorPrro);
```

If AE is overwritten by another writer **without** L/M being reset in the same pass, the
prior amount is no longer subtracted and the full ledger total is added again. Two
reachable paths do exactly that.

### 1a. Pending-row branch of `fixSaleCostForRow_` — confirmed, deterministic

```
if (!isActualSaleForCost_(values)) {
  if (options.clearPending) sales.getRange(row, 12, 1, 2).clearContent();   // NOT taken
  sales.getRange(row, 30, 1, 3).setValues([['Відкладено', buildPendingCostAudit_(values), new Date()]]);
  return null;
}
```

`apiUpdateSaleWithComponents_()` always calls `fixSaleCostForRow_` with
`{ clearPending: false }`. For a non-actual row the audit cell — and with it the marker —
is replaced by `Не зафіксовано: оплата=…, статус=…`, while L/M keep the previous overlay
amount. `applyOrderComponentCost_()` then runs unconditionally later in the same request
and adds the whole `unassigned` total on top. Every further update repeats it.

`isActualSaleForCost_()` returns false when the order status is `Передзамовлення`,
`Скасовано` or `Повернення`, or when payment is not `Оплачено` and the status is outside
`{Нове, В обробці, Відправлено, Отримано}` (including a blank status). Editing an
unpaid or preorder Mystery Box order is a normal owner workflow, so this is a live path,
not a corner case.

### 1b. 450-character audit truncation — high probability on Mystery Box rows

`replaceOrderComponentAudit_()` appends the marker at the **end** of the string and then
calls `trimCostAudit_()`, which cuts at 447 characters and appends `...`. On a Mystery Box
row the base audit produced by `recalculateMysteryBoxOrderCost_()` is
`components: <every component name>=<cost>, …; consumables=…; direct expenses=…` and is
*already* trimmed to the same 450-character cap. Appending the marker to a string at or
near the cap destroys the marker, and `CRM_ORDER_COMPONENT_AUDIT_RE_` requires a `;` or
end-of-string terminator, so a partially truncated marker does not match either. The next
update then re-adds the gift allocation for that row.

### 1c. Blast radius is wider than component edits

`applyOrderComponentCost_()` runs on **every** `update_sale` call once the ledger sheet
exists, including a plain status, TTN, packaging or note change with no components in the
payload. Any order that has ever had a component or gift is therefore exposed on every
later edit, not only on component edits.

### 1d. What is *not* the cause

The dashboard is clean here: `renderEditRowForm()` renders an empty `#editComponents`
container, `collectEditComponents()` reads only the rows currently in the form, and
previously saved components are never pre-filled. A repeat save with a new `request_id`
therefore does not re-append the earlier ledger rows. The duplication is in the cost
overlay, not in the ledger. Falsifiable by counting the order's rows in
`Використання_компонентів`: one row per entered component means no duplicate append.

## Latent risk 2 — the recovery function inherits the same weakness

`repairMysteryBoxOrderComponentCost_()` calls `applyOrderComponentCost_()`, so its
advertised "run twice, second run reports `already_applied=true`" property holds only
while the marker survives. On a row hit by 1a or 1b the recovery adds the order-component
overlay again and reports `already_applied: false` with a higher cost — drifting further
on each run rather than converging. The publication gate in the diagnostic should say
this explicitly.

## Latent risk 3 (minor) — allocation lost on zero-quantity rows

`applyOrderComponentCost_()` computes `allocateAmount_()` across all order rows but skips
any row with `count <= 0`. That row's share is silently dropped, so the total absorbed by
the order is lower than `totals.unassigned`. There is no post-condition check that the sum
of applied allocations equals the ledger total.

## Latent risk 4 (minor) — dead writer

`applyMysteryConsumableComponentCost_()` (line 6565) mutates cost column M and audit
column AE using its own separate marker and is not called anywhere in the file. It should
be removed or wired in deliberately; leaving a second, differently-keyed writer against
the same audit cell in the source is a trap for the next change.

## Test gap

`tests/order-components.test.mjs` asserts only that `replaceOrderComponentAudit_()`
replaces an existing marker and that `applyOrderComponentCost_()` is invoked after the
base-cost refresh. Nothing covers:

- second update of an order whose rows are `Відкладено` / `Передзамовлення`;
- an audit string long enough to truncate the marker;
- a second update carrying no components at all;
- a post-condition that the applied overlay equals the ledger total for the order.

## Recommended follow-up (separate task, not a blocker for this commit)

1. Stop keying idempotency on free-text inside a length-capped audit cell. Either
   (a) make the overlay derive the base cost from a value that never contains it — for
   example store the applied component amount in its own dedicated column — or
   (b) recompute the row cost from scratch (base + ledger) instead of adjusting the
   current value.
2. Until (1) lands, `fixSaleCostForRow_` must not rewrite AE for a pending row without
   also resetting L/M, and `replaceOrderComponentAudit_` must place the marker where
   truncation cannot reach it and must never emit a truncated marker.
3. Re-state the recovery function's idempotency claim in terms of the fixed mechanism,
   and keep the "run twice" verification.
4. Add the four regression cases listed above.
5. Owner-run repair for already-affected orders must be reconciled against the component
   ledger total, not against the current cell value.

## Risk

CRM cost mutation, risky zone. No mutation performed. Any repair needs an order-scoped,
reversible plan and must not touch purchase cost, stock, or unrelated sale rows.
