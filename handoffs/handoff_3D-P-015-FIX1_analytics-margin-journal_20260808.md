# Handoff — 3D-P-015-FIX1: analytics maintainability, margin semantics, truthful skip outcome

Date: 2026-08-08
Executor: **Codex** · model=Sol · effort=high
Justification: Codex authored the 3D-P-015 implementation in this same round and owns
`3d-print/apps-script-3dp-api/Code.gs` and `crm/apps-script/Code.gs` for it. Never swap executor
mid-round. Owner decides.

---

## 1. Task ID

`3D-P-015-FIX1` — a bounded delta on `3D-P-015`, raised by independent review on 2026-08-08.
It is **not** a new roadmap task. It lands in the same undeployed change set as `3D-P-015`,
before the owner publishes anything.

## 2. Context

`3D-P-015` is implemented locally and **not deployed**. 3D-P Apps Script is still V7
(2026-08-03 20:55); no workbook cell has been changed. Independent review of the local diff and
of `diagnostics/3D-P-015_live-preflight_20260808_174949.json` found three issues. The owner
reviewed all three on 2026-08-08 and asked for all three to be fixed before deployment.

**Owner decision, 2026-08-08:** `FIG-CHARM-001` and the six placeholder rows
(`(призначити SKU)` — Генгар, Mew case, Ditto pencil holder, Pokeball deck box, Onix, Pikachu)
are **test data**. Losing them from `Аналітика` is authorised and requires no preservation step.
Do not add work to save them. This closes review finding 1's *data* half; finding 1's
*maintainability* half below is still in scope.

## 3. Goal

1. `Аналітика` must stay correct as SKUs are added and removed, without a re-migration.
2. The margin columns must not mislabel a pre-split number as BoosterShop's income.
3. A SKU missing from `Номенклатура` must produce a truthful journal outcome and must not
   abort sync for the rest of the order.

## 4. What to change

### FIX-1 — `Аналітика` must be re-runnable, not one-shot

**Problem.** `rebuild3dp015Analytics3dp_()` returns immediately when `A3:N3` already matches the
new header set. Each data row is bound to a fixed source row (`='Номенклатура'!A9`). Consequence:
after the migration runs once, a SKU added to `Номенклатура` never appears in `Аналітика`, and
re-running `3dp_setup_3dp015` cannot fix it because the header check short-circuits first. A SKU
row that is deleted or reordered leaves a `#REF!` — the exact artefact this migration is cleaning
up (`Аналітика!A4` in the preflight evidence).

**Required behaviour.** Make the `Аналітика` block **idempotent by content, not by header
presence**: on every run, recompute the set of real SKU rows from `Номенклатура` and re-sync
`A4:N17` to it — add rows for new SKUs, clear rows whose source SKU is gone, leave a matching row
untouched.

Constraints:

- Keep the existing 14-row guard (`SETUP_ANCHOR_MISMATCH` when more than 14 real SKUs exist).
- Never write below row 17. The market-reference research block underneath is out of scope and
  must not move or be cleared.
- `F` (`% прибутку Сергію`) is the only owner-editable cell in the block. **Preserve a per-row `F`
  the owner has changed**; write the `0.5` default only into a newly created row.
- Re-running the action with no SKU change must still report `already_applied: true` and append no
  `_Аудит_API` record.
- The row-to-source binding stays direct-reference, but the re-sync makes a stale `#REF!`
  self-healing on the next run.

If a full re-sync is judged too large for this delta, the fallback is acceptable only if it is
explicit: keep the one-shot rebuild **and** add a separate owner-runnable maintenance action that
performs the re-sync, documented in the report and in the owner QA list. Do not ship the one-shot
rebuild with no documented way to add a SKU.

### FIX-2 — the margin columns must compute BoosterShop's **post-split** share

**Problem.** The new `Аналітика!I` is `РРЦ − собівартість − фурнітура`, i.e. the **total margin
before** the 50/50 split, but it is labelled `Маржа BoosterShop, грн`. The legacy column with that
name was `(ціна − собівартість − витрати) × (1 − F)`, i.e. BoosterShop's share **after** the split.
Same label, roughly double the number. `Продажі!L` (`Дохід Booster Shop, грн`) remains a post-split
figure, so the workbook now contradicts itself.

**Owner decision, 2026-08-08 (explicit): do not rename the column and do not add a second one.
`Маржа BoosterShop` must show the owner's own margin after the 50/50 split.** This restores the
legacy semantics exactly, with `РРЦ фактична` (`G`) replacing the removed `Ціна Середня`.

**Required change** — formulas only. Headers `I`, `J` keep their current names; `M` and `N` stay
empty; the 14-column block does not change shape.

| Col | Header (unchanged) | Required formula, per row |
|---|---|---|
| `I` | `Маржа BoosterShop, грн` | `=IF(OR(A="";NOT(ISNUMBER(C));NOT(ISNUMBER(G)));"";IF(G-C-N(D)<0;"збиток";(G-C-N(D))*(1-F)))` |
| `J` | `Маржа BoosterShop, %` | `=IF(OR(NOT(ISNUMBER(I));NOT(ISNUMBER(G));G=0);"";I/G)` |
| `K` | `Нараховано Сергію, грн` | `=IF(OR(A="";NOT(ISNUMBER(C));NOT(ISNUMBER(G)));"";IF(G-C-N(D)<0;"збиток";C+F*(G-C-N(D))))` |
| `L` | `Прибуток Сергію/год друку, грн` | unchanged (`K/E`, with the existing guards) |

**`K` must change too, and this is the part that is easy to get wrong.** It is currently
`C + F*I`. Once `I` becomes the post-split share, `C + F*I` is no longer Serhiy's accrual. Derive
both `I` and `K` from the base inputs (`G − C − N(D)`) as shown, not from each other. Do not
introduce a helper column and do not write `C + F*I/(1-F)` — it breaks at `F = 1`.

The shape above is deliberately the legacy `Аналітика` shape captured in
`diagnostics/3D-P-015_live-preflight_20260808_174949.json` (rows 4–17, columns `K`/`M`), with the
speculative price replaced by фактична РРЦ. Reuse it rather than re-deriving it.

**Hand check to include in the report.** With `G=99`, `C=12.50`, `D=4`, `F=0.5`:
`I = 41.25`, `J = 41.67%`, `K = 12.50 + 41.25 = 53.75`, and `I + K = 95.00 = G − D`. State the
computed values in the report so the owner can verify them against the live sheet.

**Do not "fix" the dashboard margin grid to match.** `threeDpMargin()` classifies by
`(РРЦ − собівартість) ÷ РРЦ`, which is a **pre-split** pricing-class metric and is correct for
that purpose. But the two percentages will now differ, so **label the dashboard grid's metric
explicitly** (header, caption or tooltip — smallest change that makes it unambiguous) so the
difference is never reported later as a defect. No change to its arithmetic.

**Also document, in `Легенда` or the block's own note:** `Аналітика!D`
(`Витрати BoosterShop (фурнітура), грн`) reads `Номенклатура!N` unconditionally and therefore
assumes fixture payer = `власник`. `Аналітика` has no payer dimension; the payer lives on the sale
row (`Продажі!W`). This is a planning-view default, not a bug, but it must be written down so it is
not later read as a defect.

### FIX-3 — a SKU missing from `Номенклатура` must journal the truth

**Problem.** `crm3dpFrozenSaleInputs_()` calls `crm3dpGet_(config, { action: '3dp_get_row', ... })`.
When the SKU is absent, the 3D-P API returns `ROW_NOT_FOUND`; `crm3dpFetchJson_()` converts any
non-`ok` payload into a **thrown** `Error`. That throw escapes `triggerRows.forEach`, is caught by
the outer handler in `sync3dpSales_()`, and is journalled as **`skipped_api_error`**.

Two consequences:

1. The journal reports an outage for what is actually "this SKU is not in `Номенклатура`" — the
   documented three-catalogue trap, and the exact failure `3D-P-014` exists to make legible.
2. The throw aborts the loop, so **every other 3D-P line in the same order is silently skipped**
   and the packaging-cost write never runs.

The `if (!row)` guard already present in `crm3dpFrozenSaleInputs_()` is unreachable dead code.

**Required change.** Catch the failure inside `crm3dpFrozenSaleInputs_()`, inspect the sanitised
remote code carried in the message by `crm3dpFetchJson_()`, and:

- `ROW_NOT_FOUND` / `ROW_FILTERED` → return `{ ok: false, skipped: 'sku_not_in_nomenclature', reason: ... }`,
  mapped to a new journal outcome **`skipped_sku_not_in_nomenclature`**, so the `forEach` continues
  for sibling rows;
- any other failure (HTTP error, invalid JSON, timeout, `UNAUTHORIZED`, `SHEET_NOT_ALLOWED`) →
  **re-throw unchanged**, so a genuine outage still surfaces as `skipped_api_error`. Do not swallow
  real failures to make the loop nicer.

The reason string must name the SKU and say plainly that the SKU is absent from the 3D-P
`Номенклатура`. Remove or make reachable the dead `if (!row)` branch.

Journal outcomes are free-form strings with no validation vocabulary, so a new value needs no
schema change. Precedent: `skipped_sku_shape` was added in `3D-P-022` for this same class of
misleading-label problem, on the owner's decision.

## 5. Do not touch

- The 50/50 split rule itself, the two-track model, the «ЗБИТКОВИЙ — рішення власника» rule.
- `Номенклатура` `O`/`P`, `API_3DP.nomenclatureStatusColumn`, `nomenclatureHistoryColumn`.
- `Продажі!T` position or header; the `U`/`V`/`W` placement and the frozen-literal contract.
- The interim fixture rule (`W = власник` by default, `V` from `Номенклатура!N`).
- The market-reference research block below `Аналітика!17`.
- `SERHIY_MANUAL_COLUMNS_3DP` — Serhiy gains nothing here either.
- The recommended-РРЦ `pending` placeholder. Still unapproved. Do not invent a formula.
- `_Аудит_API`, `_Коригування_наявності`, `_Чернетки_партій` semantics.
- Main CRM pricing, storefront prices, Merchant feed, Product schema.

## 6. Likely files / areas

```text
3d-print/apps-script-3dp-api/Code.gs      # FIX-1, FIX-2
3d-print/apps-script-3dp-api/tests/api.test.mjs
crm/apps-script/Code.gs                   # FIX-3
crm/apps-script/tests/3dp-sync-journal.test.mjs
diagnostics/3D-P-015_price-model-rebuild_report_20260808.md   # extend, do not replace
```

The dashboard is expected to need no change; confirm rather than assume, since the Інформація
zone reads analytics tiles.

## 7. Acceptance criteria

- [ ] Running `3dp_setup_3dp015` twice in a row is a no-op the second time
      (`already_applied: true`, no new `_Аудит_API` record).
- [ ] Adding a SKU row to `Номенклатура` and re-running the action adds exactly one
      `Аналітика` row for it, with correct source-row references.
- [ ] Removing a SKU row and re-running clears its `Аналітика` row and leaves no `#REF!`.
- [ ] An owner-edited `F` on an existing `Аналітика` row survives a re-run; a newly created row
      gets `0.5`.
- [ ] Nothing is ever written below `Аналітика!17`.
- [ ] More than 14 real SKUs still raises `SETUP_ANCHOR_MISMATCH` and changes nothing.
- [ ] `Аналітика` column headers are **unchanged**; `M` and `N` stay empty; no column was added.
- [ ] `Аналітика!I` returns BoosterShop's share **after** the 50/50 split, and `J` is that share
      as a percentage of фактична РРЦ.
- [ ] `Аналітика!K` is derived from `G − C − N(D)`, not from `I`, and still equals Serhiy's cost
      reimbursement plus his half of the profit.
- [ ] For `G=99`, `C=12.50`, `D=4`, `F=0.5`: `I=41.25`, `K=53.75`, `I+K=95.00=G−D`, hand-checked
      and stated in the report.
- [ ] `I` and `K` both still render `збиток` when `G − C − N(D) < 0`.
- [ ] The dashboard margin grid's arithmetic is unchanged, and its metric is now explicitly
      labelled as pre-split.
- [ ] The fixture-payer assumption of `Аналітика!D` is written down somewhere durable.
- [ ] A sale whose SKU is absent from `Номенклатура` journals
      `skipped_sku_not_in_nomenclature`, the CRM sale still saves, and **other 3D-P lines in the
      same order still sync** — proven by a test with two 3D-P SKUs, one present and one absent.
- [ ] A simulated network/HTTP failure still journals `skipped_api_error`, not the new outcome.
- [ ] All existing local tests still pass; new tests cover both branches of FIX-3.

## 8. QA / smoke test

Local, by the executor:

1. Re-run idempotency, add-SKU, remove-SKU, and owner-edited-`F` cases against the test harness.
2. Two-SKU order with one SKU absent from `Номенклатура` — assert one `created`, one
   `skipped_sku_not_in_nomenclature`, `ok: true`, and that the packaging write still happened.
3. Forced non-`ok` remote failure — assert `skipped_api_error` is preserved.

Owner, after deployment (added to the existing `3D-P-015` QA list, not replacing it):

1. Add a new SKU to `Номенклатура`, re-run the setup action, confirm it appears in `Аналітика`.
2. Read `Аналітика` for one SKU with a filled РРЦ and confirm «Маржа BoosterShop» is your own
   half after the split — it must be roughly half of «РРЦ мінус собівартість мінус фурнітура»,
   and «Маржа BoosterShop» plus «Нараховано Сергію» must equal РРЦ minus фурнітура.
3. Create an order with a 3D-P SKU that is deliberately not in `Номенклатура` and confirm the
   journal names the real reason.

## 9. Rollback note

Nothing is deployed, so the rollback for FIX-1/2/3 as source is `git checkout` of the two
`Code.gs` files. The live rollback story is unchanged from `3D-P-015`: a **named Google Sheets
version created before the migration** is the only complete recovery path, because the in-script
snapshot restore only fires on an exception during execution and will not help if Apps Script
times out mid-run.

FIX-1 makes the setup action repeatable, which slightly raises the cost of a bad run — it can now
be invoked more than once. The 14-row guard, the "never below row 17" rule and the `F`-preservation
rule are what bound that. Verify all three before the owner runs it live.

## 10. Recommended status after execution

`3D-P-015` stays **In progress**. This delta does not close it. `3D-P-015` reaches `Done` only
after the owner deploys both scripts, runs the live migration, and completes the combined owner QA
list. Claude (chat) is the Notion writer; the executor writes no Notion property.

## Risk classification

Not an SEO risk zone. It **is** a CRM / financial-model risk zone under `AGENTS.md`: it touches the
deployed CRM sale-write path and the workbook's money formulas. Rollback plan and focused smoke
test are mandatory, and the owner remains the only deployment gate.
