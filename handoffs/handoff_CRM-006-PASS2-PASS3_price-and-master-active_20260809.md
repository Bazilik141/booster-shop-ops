# CRM-006 passes 2 and 3 — RRP correction, then the `Активний` formula

Date: 2026-08-09 · Task: `CRM-006` · Executor: **Codex** · Author: Claude (chat)
Basis: `diagnostics/CRM-006_pass1-result-and-master-active-chain_20260809.md`.

Pass 1 succeeded and its gate passed: `rrp_mismatch_3dp` now fires for `ACC-3D-DITTO-410`
(CRM `90` vs 3D-P `100`) at `РРЦ` row 75, proving the row is keyed to the right product.
Problem count went 150 → 78; `price_without_sku` and `active_sku_without_rrp` are both gone.

Two passes below. **They do not merge.** Each gets its own named Sheets version, its own before/after
`integrity_check`, and its own entry in the diagnostic.

---

## PASS 2 — `РРЦ!E75`: `90` → `100`

Already owner-approved (2026-08-09: the 3D-P workbook is correct, the CRM sheet is the one that was
wrong). Unblocked by the pass-1 gate.

**Owner performs the edit.** One cell.

1. Named Sheets version.
2. Set `РРЦ!E75` to `100`. Nothing else on that row — `F75` (date) and `G75` (note) stay as they are
   unless the owner decides otherwise.
3. Run `integrity_check`. `rrp_mismatch_3dp` must disappear. Record the output.

Expected total after pass 2: **77**.

If any *other* code changes count in this pass, that is a defect of the change, not noise — investigate
before continuing.

---

## PASS 3 — `Майстер_Товарів!P2`, the VLOOKUP index

### What is wrong

`P2` is an ARRAYFORMULA using `VLOOKUP(A2:A;Source_CRM_Products!A7:O;13;FALSE)`. Column **12** is
`Активний товар`; column **13** is `Посилання на товар`. Independently corroborated against the
`Товари` header order recorded in `crm/apps-script/tests/integrity-check.test.mjs`.

### Why this is bigger than one cell

`apiSkuList_` hard-filters on that column:

```js
const active = String(apiObjVal_(row, ['Активний', 'Active']) || '').trim().toLowerCase();
if (['так', 'true', 'yes', '1'].indexOf(active) === -1) return;
```

so all ~72 SKUs are currently skipped. `apiStockAlerts_` uses the identical filter, and `apiSummary_`
derives `rrcBySku` from `apiSkuList_` — which is why potential profit is exactly `₴0` while warehouse
cost displays normally. The owner's three reported dashboard symptoms all trace here.

**The earlier blast-radius question is answered:** the column is already broken, so the fix restores
function rather than altering working behaviour. But ~72 SKUs will appear in views that are currently
empty and profit will jump from `0` to a real number, so this still needs its own before/after.

### Required before the edit

1. **Read and record `P2`'s current formula verbatim.** That string is the rollback.
2. Confirm from the live sheet, not from this document, that `Source_CRM_Products!A7:O` column 12 is
   `Активний товар` — the offset starts at row 7, and the mapping must be proven against the imported
   sheet's own header row, not against `Товари`.
3. Check whether any **other** `Майстер_Товарів` column formula uses an index into the same range. An
   off-by-one in one column is a strong hint that neighbours may share it. Report what you find even
   if nothing else is wrong.
4. Named Sheets version.
5. Save the pre-write `integrity_check` output.

### The edit

Change the index from `13` to `12` in `P2` only. Do not restructure the formula, do not change the
range, do not touch any other cell. If the formula needs more than an index change to be correct,
**stop and report** rather than rewriting it.

### Verification

1. `P2:P` now returns `так` / `ні` values rather than links or blanks.
2. Run `integrity_check`: `master_row_inactive` should drop to near zero. Any SKU still reported is
   now a *real* catalogue omission and must be listed individually — that is a genuine finding, not
   residue.
3. Hard-refresh the dashboard and confirm all three symptoms clear:
   - the SKU/Товари list populates;
   - stock views populate;
   - **Потенційний прибуток складу** and **прибуток активів** stop reading `₴0`.
4. Sanity-check the new profit figure against `Собівартість складу ₴84 077`. A potential profit that
   is wildly implausible relative to that base means the price map is populating with wrong values —
   report rather than accept.

Expected total after pass 3: **~5**, i.e. only the `formula_column_literal` findings.

---

## Not authorised in either pass

- `Товари!B`/`J` and `Розхідники!F:H` formula restoration. Separate pass, separate approval.
- **Any fill-down, anywhere.** A literal can match the formula's visible result while still being
  structurally wrong, so restoring these needs per-row confirmation, not a drag.
- Any change to `apiSkuList_`, `apiStockAlerts_` or `apiSummary_`. The Apps Script is behaving
  correctly given its input; the defect is in the sheet. Do not "fix" the filter to tolerate bad data
  — that would hide the next occurrence.

## Acceptance criteria

- [ ] Pass 2 and pass 3 have separate named Sheets versions and separate before/after outputs.
- [ ] `P2`'s original formula is recorded verbatim before the edit.
- [ ] The column-12 mapping is proven against `Source_CRM_Products`' own header row.
- [ ] Neighbouring `Майстер_Товарів` column formulas are checked for the same off-by-one and the
      result is reported either way.
- [ ] `rrp_mismatch_3dp` clears after pass 2.
- [ ] `master_row_inactive` drops to near zero after pass 3; any survivor is listed individually.
- [ ] The three dashboard symptoms are confirmed cleared by the owner, hard-refresh included.
- [ ] No formula column anywhere holds a literal as a result of this work.

## Rollback

Pass 2: set `РРЦ!E75` back to `90`, or restore the named version.
Pass 3: restore the recorded `P2` formula, or restore the named version.

Both are single-cell inverses, which is why each pass gets its own version — a shared version would
make them inseparable.

## Housekeeping still outstanding from pass 1

`diagnostics/CRM-005_integrity-check-and-rule_report_20260809.md` still claims rows 71–75 block the
spill. Append a dated correction marking that sentence superseded by the CRM-006 diagnosis. Do not
rewrite history.
