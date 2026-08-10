# Handoff — CRM-006 pass 4: restore the overwritten formula columns

Date: 2026-08-10 · Task: `CRM-006` (pass 4) · Author: Claude (chat)
Executor: **Codex** · model=Sol · effort=xhigh — owner decision pending confirmation in the active
task. Justification: CRM is a risky zone, Codex already holds live workbook access and executed both
`CRM-007` and the `CRM-008` preflight this round, and the never-swap-mid-round rule applies. This work
is structurally ambiguous — the correct formula for each column is not recorded anywhere and must be
derived from the live sheet — so it does not go on a small model.

Basis:

- `diagnostics/CRM-005_first-live-baseline_20260809.md` — the original per-column row lists;
- `diagnostics/CRM-006_pass1-result-and-master-active-chain_20260809.md` — pass 1 result and the
  explicit "no fill-down is authorised" rule;
- `handoffs/handoff_CRM-006-PASS2-PASS3_price-and-master-active_20260809.md` §"Not authorised" — this
  pass is the one that document deferred;
- `diagnostics/CRM-008_starter-decks-sku-and-purchase_report_20260810.md` — the live 2026-08-10
  preflight that found `Товари!B77:B81` empty and blocked `CRM-008`;
- `diagnostics/CRM-COST-SPLIT_OP15-and-MZERO_claude-audit_20260810.md` §consequence table.

> **Delivery path is not the usual one.** There is no PHP patch and no `patches/` file. This is a set
> of bounded formula writes into the live CRM spreadsheet
> (`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`), the same mechanism used for `CRM-007` on
> 2026-08-10. Nothing is uploaded to `~/public_html` and nothing is deployed. The executor never
> commits, pushes or deploys. **The owner must give an explicit go-ahead in the active task before the
> first write of each sub-pass.**

---

## 1. Task ID

`CRM-006` pass 4 — restore the formula columns that were overwritten with literals in `Товари` and
`Розхідники`. Owner authorised the **full** pass 4 scope on 2026-08-10, after the `CRM-008` preflight
stopped on it.

## 2. Context

Passes 1–3 of `CRM-006` took the integrity check from 150 problems to 5. The five survivors are all
`formula_column_literal` and all belong to this pass. They have been deliberately carried as a known
baseline through `CRM-007` and `CRM-008`, which is why both of those tasks could prove "no new problem
code" against them.

Recorded scope, from the `CRM-005` baseline and re-confirmed unchanged in the `CRM-007`
before/after runs on 2026-08-10:

| Sheet | Column | Rows reported as literal | Consequence of leaving it |
|---|---|---|---|
| `Товари` | `Коротка назва` | 38-39, 49-67, 71-76 | display/name derivation frozen |
| `Товари` | `Поточна ціна продажу` | 38-39 | price no longer follows `РРЦ` |
| `Розхідники` | `Надійшло через витрати` | 7-15, 17 | new purchases stop increasing available qty |
| `Розхідники` | `Їде через витрати` | 6, 8, 10-15, 17 | in-transit qty frozen |
| `Розхідники` | `Використано в продажах` | 10-11, 13-15, 17-23 | consumption no longer follows sales |

Pass 1 recorded `Розхідники!F:H` as the range holding those three columns. **The letter-to-column-name
mapping is not proven** — resolve it by header text on the live sheet, do not assume `F` = `Надійшло`.

Two facts the 2026-08-10 `CRM-008` preflight added, which the older baseline does not contain:

- `Товари!B77:B81` have **no formula and no value** — `userEnteredValue` is empty on every one. `B70`
  is empty too. So the damage is not only "literals where formulas belong"; the column also simply
  stops. This is what blocks `CRM-008`, which needs rows 77-81.
- `Товари!J77:J81` **do** retain the normal price formula. Column `J` is damaged only at rows 38-39.

Why the two sheets differ in urgency: `Товари!B` blocks another task today; `Розхідники` silently
corrupts consumable quantities and has already touched money once — row 8 is `Стікер лого+QR`
(₴1.17, auto-applied per order) whose `Надійшло через витрати` = `300` is now a literal, so
`getAutoConsumableInfo_().totalQty` will not grow when more stickers are bought.

## 3. Goal

Every affected cell holds the column's own formula again, each row confirmed individually, with the
column's coverage reaching the current last data row and beyond it far enough that `CRM-008` can add
rows 77-81 without re-breaking it. No value is invented and no visible figure changes silently without
being reported.

## 4. What to change

Three sub-passes. **They do not merge.** Each gets its own named Sheets version, its own before/after
`integrity_check`, and its own section in the diagnostic — exactly as passes 2 and 3 were run. A shared
version makes them inseparable for rollback.

Run them in this order. 4a first because it unblocks `CRM-008`.

### Step 0 — mandatory for every sub-pass, before any write

1. Run the read-only dashboard `integrity_check` and record the full bounded output verbatim. Expect
   the five known findings before 4a. `OPS-CRMINTEGRITY` step 4 applies: **any new problem code after
   a sub-pass is a defect of that sub-pass.**
2. Ask the owner for a named Sheets version and record its exact name and timestamp. Do not start
   before it exists.
3. **Establish the reference formula from the live sheet.** For the column being repaired, read the
   formula — not the value — from rows the integrity check does *not* flag, i.e. rows that still hold
   the intact formula. Read at least three such rows and confirm they agree once relative references
   are normalised for the row offset.
4. **Stop conditions, all hard:**
   - if no unflagged row in that column holds a formula at all, there is no reference to restore
     from — **stop and report**, do not reconstruct a formula from what the values look like;
   - if the intact rows disagree with each other, **stop and report** — the column has more than one
     convention and the owner must choose;
   - if the column turns out to be driven by an `ARRAYFORMULA` in a header cell rather than per-row
     formulas, **stop and report** before writing anything: the repair is then one cell, not many, and
     the blast radius is different from what this handoff assumes.
5. Record every target cell's current literal value verbatim before overwriting it. **That record is
   the only rollback for the literals** — a formula written over a literal cannot be reversed from the
   sheet alone.

### Step 1 — the write, per sub-pass

Write the column's own formula into each flagged cell, adjusted only for the row offset. Nothing else.

**No fill-down, no drag, no copy-paste across a block, anywhere in this pass.** This is a standing
`CRM-006` rule from pass 1 and it is the whole reason this work was deferred: a literal can match the
formula's visible result while still being structurally wrong, so each row is confirmed on its own.

### Step 2 — compare before and after, per row

For every repaired cell, record the literal that was there and the value the restored formula now
produces.

- **Where they match**, the repair is silent and safe — say so.
- **Where they differ, do not treat the new value as automatically correct.** List every difference in
  the diagnostic with the old value, the new value, and the delta. A difference means the frozen
  literal had drifted from reality, which is information the owner needs, and in some cases it means
  the formula is not the right one for that row. Do not resolve a disagreement yourself.

### 4a — `Товари!B` (`Коротка назва`)

Flagged rows 38-39, 49-67, 71-76. Additionally extend coverage to `B70` and `B77:B81`, which are empty
rather than literal — that extension is the point of this sub-pass, because it is what `CRM-008` needs.

`Коротка назва` is a display/name derivation. Changing it changes how products are labelled in CRM
views and anything downstream that reads the short name. Report any row whose short name changes.

### 4b — `Товари!J` (`Поточна ціна продажу`), rows 38-39 only

**Highest-risk sub-pass despite being two cells.** Restoring the formula makes the price follow `РРЦ`
again. If the frozen literal differs from what `РРЦ` currently holds for those two SKUs, the effective
selling price in CRM changes the moment the formula lands.

Therefore: read `РРЦ` for both SKUs first, compute what the restored formula would produce, and
**present both numbers to the owner and stop.** He confirms per SKU before the write. Do not write 4b
on a general "pass 4 is authorised" — this specific comparison needs its own go-ahead.

Rows 77-81 are not in this sub-pass; `J` already reaches them.

### 4c — `Розхідники` (`Надійшло через витрати`, `Їде через витрати`, `Використано в продажах`)

Recorded as the `F:H` range; resolve the actual letters by header.

Blast radius to state explicitly in the diagnostic before writing: `Розхідники` row 8 is
`Стікер лого+QR`, the auto-applied consumable currently costed at ₴1.17 per order via
`getAutoConsumableInfo_()`. Restoring the quantity formulas changes `totalQty`, which changes the
per-unit consumable cost, which changes the consumable component of **future** FIFO sale
recalculations. It does not retroactively rewrite stored sale rows, but any order recalculated after
this pass will pick up the new figure.

So: after 4c, compute and report the new per-unit `Стікер лого+QR` cost against the current ₴1.17, and
name any other row in these three columns whose restored quantity differs from its literal. Do not
recalculate any sale to "check" — that is a `Продажі` write and is out of scope here.

## 5. Do not touch

- Any cell in `Товари` or `Розхідники` outside the flagged rows plus the `B70`/`B77:B81` extension.
- Any other column of `Товари` — in particular `H`/`I` (`Карт у бустері`, `Бустерів у боксі`) and the
  `Активний товар` column repaired in pass 3.
- `Майстер_Товарів!P2` and the pass-3 repair generally. If a pass-4 change makes a
  `Майстер_Товарів` output move, report it; do not adjust the master workbook to compensate.
- `Закупки`, `Продажі`, `Склад`, `Списання` — every row, including the `CRM-007` rows closed on
  2026-08-10. This pass does not touch cost or stock.
- `РРЦ` — read-only here, including `РРЦ!A3:D3` ARRAYFORMULA seeds and any blank `РРЦ` row.
- `Налаштування` — the `Starter Deck` / `ST-32`…`ST-36` additions and the `J4:J14` / `AD4:AD39`
  validation ranges belong to `CRM-008`, not here.
- `crm/apps-script/Code.gs` and the live Apps Script project. The script is behaving correctly given
  its input; the defect is in the sheet. Do not "fix" `apiSkuList_`, `apiStockAlerts_`, `apiSummary_`
  or `getAutoConsumableInfo_()` to tolerate bad data — that hides the next occurrence. Do not extend
  `apiIntegrityCheck_` scope and do not publish a Web App version.
- Notion properties and status — Claude (chat) is the writer. If a status change is needed, say so and
  stop.
- `dashboard/booster-dashboard.html` — the `CRM-006` `ROADMAP_FLOW` row already exists. Do not create
  a second one.
- Standing protected zones, none of which are in scope and none of which may be opened: `sitemap.xml`,
  `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed,
  schema/JSON-LD.

## 6. Likely files / areas

Live spreadsheet only — `Booster Shop CRM — облік товарів`
(`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`): `Товари` and `Розхідники` (write), `РРЦ` and
`Налаштування` (read-only reference).

Reference only, not to be edited: `crm/apps-script/Code.gs` — `apiIntegrityCheck_`,
`getAutoConsumableInfo_`, `apiSkuList_`. Header order corroboration:
`crm/apps-script/tests/integrity-check.test.mjs`.

Column letters and row numbers in this handoff are **recorded, not confirmed** — the executor verifies
every one against the live sheet by header text and by content before writing.

## 7. Acceptance criteria

- [ ] Three separate named Sheets versions, one per sub-pass, each name and timestamp recorded
- [ ] Three separate before/after `integrity_check` outputs, pasted verbatim
- [ ] The reference formula for each repaired column is recorded verbatim, with the row numbers it was
      read from
- [ ] Every overwritten literal is recorded verbatim before the write
- [ ] `formula_column_literal` for `Товари` — `Коротка назва` is gone after 4a
- [ ] `formula_column_literal` for `Товари` — `Поточна ціна продажу` is gone after 4b
- [ ] All three `Розхідники` `formula_column_literal` findings are gone after 4c
- [ ] Final `integrity_check` reports **no `formula_column_literal` at all** and **no new problem
      code** of any kind compared with the 2026-08-10 baseline
- [ ] `Товари!B70` and `Товари!B77:B81` hold formulas, verified by reading formulas and not values —
      this is the `CRM-008` unblock criterion
- [ ] Every literal-vs-restored-value difference is listed individually with old, new and delta
- [ ] The new per-unit `Стікер лого+QR` cost is reported against the current ₴1.17
- [ ] `diagnostics/CRM-006_pass4-formula-restore_report_20260810.md` written, with a section per
      sub-pass

## 8. QA / smoke test (owner runs)

Not a checkout/payment/fiscalization change, so `bs-checkout-smoke` does not apply. Not an
SEO/schema/feed change, so `bs-seo-risk-gate` and `bs-merchant-schema-qa` do not apply. CRM risky zone,
so the following is mandatory:

- [ ] Take the named version **before** each sub-pass; note all three names.
- [ ] Run the CRM integrity check from the dashboard before and after each sub-pass; copy all six
      results.
- [ ] Hard-refresh the dashboard after 4a and confirm the SKU/product lists still populate and no
      product name has become blank or obviously wrong.
- [ ] After 4b, confirm the two affected SKUs show the price you intend to sell at — this is the one
      change that can move a live selling price.
- [ ] After 4c, open the consumables view and confirm the `Стікер лого+QR` available quantity is the
      real one, then physically sanity-check one consumable against the shelf.
- [ ] Spot-check three unrelated SKUs — balances and costs must be unchanged, since this pass touches
      neither cost nor stock.
- [ ] Confirm `Закупки!58` / `Закупки!123` and `OC-FOP-0314` still read the `CRM-007` values.

## 9. Rollback note

Per sub-pass, in order of preference: restore that sub-pass's named Sheets version; or write the
recorded literals back into the repaired cells.

The literals record is not optional and not reconstructable afterwards — a formula written over a
literal destroys the literal, and the value the formula now shows is not evidence of what was there.
If Step 0.5 has not been completed for a cell, that cell must not be written.

`Товари!B70` and `B77:B81` need no rollback of their own: they were empty, so the inverse is clearing
them.

## 10. Recommended status after execution

`CRM-006` stays `In progress` until the owner has run the QA list above, including the 4b price
confirmation. Then the owner authorises closure and **Claude (chat) writes `Done`** in Notion and
mirrors `done` in `ROADMAP_FLOW`. The Definition of Done gate for a CRM task is a live read-back —
quote the restored formulas and the final `integrity_check` read from the live sheet after the change,
not from this handoff.

On completion, `CRM-008` is unblocked but **does not resume automatically**: it needs its own fresh
preflight, its own integrity baseline, and a separate owner go-ahead. Its handoff also needs amending
first to cover the `Налаштування!J4:J14` / `AD4:AD39` validation-range extension found on 2026-08-10.
