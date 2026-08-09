# Handoff — 3D-P-019: fixture operational half (payer, consumption, Serhiy's purchases)

Date: 2026-08-09
Executor: **Codex** · model=Sol · effort=xhigh
Justification: touches the deployed main-CRM sale/write-off paths and a live consumables sheet that
other logic already reads. Risky zone, architecturally ambiguous, needs live discovery. Owner decides.

---

## 1. Task ID

`3D-P-019` · Notion `3b56bf20-bdb4-81f6-8f8a-e4c5842ede7e`

Design basis: `plans/3D-P-019_fixture-payer-model_20260808.md` (decisions F1–F8).
**The schema half already shipped inside `3D-P-015` (decision F8) and is live.** `Номенклатура!K` no
longer folds the fixture price into Serhiy's cost, `Номенклатура!N` is a reference price only, and
`Продажі!V`/`!W` hold the frozen fixture cost and payer. This handoff is the operational half only.

## 2. What changed since the design note — read this, two assumptions were wrong

**2.1 The category is `3D-друк`, not `Фурнітура`.** The design note assumed fixtures would carry a
`Тип = Фурнітура` value. The owner has since entered real fixture rows in the main CRM `Розхідники`
sheet under **`Категорія = 3D-друк`**. Verified from the owner's export, 2026-08-09:

| Тип розхідника | Категорія | Собівартість 1 шт | Стан |
|---|---|---|---|
| `FUR-BR-COLOR-MIX` | `3D-друк` | `2,96 грн` | 80 шт їде через витрати, залишок 0 |
| `FUR-BR-CARB` | `3D-друк` | `1,24 грн` | 50 шт їде через витрати, залишок 0 |

Both are the owner's Temu purchases. Neither has arrived — stock is `0`, quantity is in transit.

**Owner decision 2026-08-09: rename the category from `3D-друк` to `Фурнітура`** and adjust whatever
depends on it. See §4.1 — this is a live data change with unknown dependents and must be discovered
before it is executed.

**2.2 `Фурнітура_довідник` is not the source of truth.** The empty tab in the 3D-P workbook is not a
fixture catalogue. Per F3, confirmed by the owner 2026-08-09: **the main CRM `Розхідники` sheet is
the single source of truth for fixtures.** The 3D-P workbook's role is only to receive Serhiy's
*unconfirmed* purchases pending the owner's approval. Decide whether to repurpose
`Фурнітура_довідник` into that pending-purchases tab or retire it and add a new one — and say which,
with reasoning. The existing `3dp_fixtures` read action points at it and must be updated to match
whatever is decided.

## 3. Owner decisions in force

| # | Decision | Source |
|---|---|---|
| F1 | Unit cost is the **current price on the fixture row**, replaced when a newer purchase changes it. No lots, no FIFO — fixtures follow the existing `Розхідники` consumables pattern, which has no lot model. Historical accuracy is preserved because `3D-P-015` freezes the cost into the sale row at creation. | 2026-08-08 |
| F2 | Payer is recorded by **one row per (fixture, payer)**. | 2026-08-08 |
| F2a | **Format decided 2026-08-09:** the payer lives in a dedicated **`Платник` column appended at the END** of `Розхідники`, and is read from that column — **never parsed out of the name**. The row name stays a stable code (`FUR-BR-CARB`). A name suffix such as `-S` is added only when a second payer's row for the same fixture actually exists, so nothing changes in the sheet today. Appending at the end is required: the deployed consumable lookup reads `Розхідники` columns by position. | 2026-08-09 |
| F3 | Serhiy's purchases reach the CRM by **owner-confirmed import, not automatic sync**. He records quantity and total cost in his server → the row lands in the 3D-P workbook as pending → the owner confirms → only then is a `Розхідники` row created or topped up with `Платник = Сергій`. No second automatic sync direction. | 2026-08-08 |
| F4 | Track-2 giveaways consume fixtures through the **existing write-off form**. Accepted for the current Sheets model only; recorded against NCRM as a shortcut not to be made permanent. | 2026-08-08 |
| F5 | **Serhiy cannot record the owner as payer.** His role may only write rows attributed to himself. | 2026-08-08 |
| F6 | Insufficient fixture stock **warns, never blocks** an order save. | 2026-08-08 |
| F7 | Money split per §6 of the design note: an owner-paid fixture is a BoosterShop cost that reduces profit before the split; a Serhiy-paid fixture is reimbursed to him as a **separate accrual record**, never merged into the print-cost figure. | 2026-08-08 |

## 4. What to change

### 4.1 Category rename — discovery before execution

Rename `Категорія = 3D-друк` to `Фурнітура` on the fixture rows.

**Do not rename anything until every consumer of the category value has been found in the deployed
source.** Known so far, from the 2026-08-08 mirror read: `getAutoConsumableInfo_` reads `Розхідники`
columns A (name) / B (category) / C (cost), and auto-write-off behaviour for marketing consumables is
condition-driven. Whether anything matches the literal string `3D-друк` is **not verified** — find
out, list every hit, and only then propose the rename as a bounded change.

Note for the record: `Фурнітура` is a narrower label than `3D-друк`. If a non-fixture 3D consumable
is ever added, it will need its own category. Not a problem today — both live rows are fixtures.

### 4.2 `Платник` column

Append `Платник` at the end of `Розхідники`. Values: `власник` / `Сергій`. Backfill both existing
rows as `власник` — both are the owner's Temu purchases, evidenced by the purchase notes in the sheet.

The composite identity of a fixture becomes **(name, payer)**. Any lookup that resolves a fixture must
respect that. The safest path is to keep names unique so the deployed name-keyed lookup keeps working,
with the column as the authoritative machine-readable field — but verify how `getAutoConsumableInfo_`
resolves a consumable before committing to either approach.

### 4.3 Multi-line fixture entry in the order form

From the 2026-08-02 Addendum, still binding: the order-edit form gets a **repeatable** fixture entry —
add/remove rows, each a fixture + quantity. Explicitly **not** a single dropdown: one product can
carry a chain *and* a carabiner, or 2× of the same part. This was caught once already and must not be
lost again.

Each consumed line decrements that `Розхідники` row's stock. The dropdown label must make the payer
visible.

### 4.4 Write-off path for Track-2 giveaways

Add the same multi-line fixture entry to the existing write-off form. Without it, a fixture given away
as a bonus never decrements and its cost lands nowhere. `WRITEOFF_TYPES` already includes `Промо` and
`Подарунок`, so this is an addition to an existing form, not new machinery.

### 4.5 Serhiy's purchases — pending import

Serhiy records quantity and total cost on his server; unit cost is derived (`total ÷ qty`). The row
lands in the 3D-P workbook as pending. The owner sees it in the 3D-друк tab under a
"Закупівлі Сергія — потребує підтвердження" section and confirms. Only on confirmation is a
`Розхідники` row created or topped up with `Платник = Сергій`.

Serhiy must not be able to set the payer to `власник` (F5). Enforce server-side, not in the UI.

### 4.6 Sale-row wiring

The frozen fields `Продажі!V` (fixture cost) and `!W` (payer) already exist and are populated by the
`3D-P-010` hook with the interim rule `W = власник`, `V` from `Номенклатура!N`. Replace that interim
source with the real consumed-fixture data once fixture lines exist on the order.

Where an order carries multiple fixture lines, decide and document how they collapse into the single
per-unit `V` — sum per unit is the obvious answer, but state it explicitly and prove it with a worked
example. `W` cannot express two payers on one sale row; if both parties' fixtures are on one order,
say what happens. **This is the sharpest unresolved edge in this task — do not paper over it.**

## 5. Do not touch

- `Номенклатура!K` and the defect-rate uplift from `3D-P-015-FIX2`.
- `Номенклатура!N` — stays a reference price, never a cost input.
- The 50/50 split, the two-track model, the «ЗБИТКОВИЙ — рішення власника» rule.
- The frozen-literal contract on `Продажі!F/U/V/W`. Past sales must not move.
- The append-only stock adjustment ledger.
- `CRM-004` and `CRM-005` scope. Separate tasks.
- The recommended-РРЦ `pending` placeholder.

## 6. Prerequisites and sequencing

- `3D-P-010` (all three CRM sale-write paths) — **done**, so fixture lines entered through the owner's
  in-Sheet menu form will actually reach the workbook. This was a hard blocker and is cleared.
- `3D-P-014` (sync journal) — **done**, so a failed fixture write is visible.
- `3D-P-015` schema half — **done and live**.
- Fixture stock data — **partially available**: two rows exist, both with `0` on hand and quantity in
  transit. The mechanism can be built and tested; real consumption cannot be QA'd until stock arrives.
  Do not stall on that.

**Sequencing recommendation:** land the category rename and the `Платник` column as one small, verified
change first, with `CRM-005`'s integrity check run before and after if that task has shipped by then.
Then the forms. Two patches, not one.

## 7. Acceptance criteria

- [ ] Every consumer of the `Розхідники` category value is listed in `diagnostics/` before the rename;
      the rename touches only what that list says it touches.
- [ ] Both fixture rows read `Категорія = Фурнітура` and existing consumable behaviour for `Упаковка`
      and `Маркетинг` is provably unchanged.
- [ ] `Платник` exists as the last column, both rows backfilled `власник`, and no existing column
      shifted position.
- [ ] The payer is read from the column; nothing parses it out of a name.
- [ ] Serhiy cannot write a row with `Платник = власник`, proven by test.
- [ ] The order form accepts multiple fixture lines, including 2× of the same fixture.
- [ ] The write-off form accepts the same lines; a Track-2 giveaway decrements fixture stock.
- [ ] Insufficient stock warns and the order still saves.
- [ ] Serhiy's pending purchase appears for confirmation and creates nothing until confirmed.
- [ ] A sale with a fixture produces the correct `V`/`W`, and an owner-paid fixture is counted exactly
      once — not in `Продажі!G` and again in `V`.
- [ ] Multi-fixture and mixed-payer orders have a documented, worked-through answer.
- [ ] A hand-computed example reconciles Serhiy's accrual and BoosterShop income for both payers.

## 8. QA / smoke test

Owner, after deployment:

1. Add a fixture line to a test order, save, confirm the 3D-P sale row carries the right cost and payer.
2. Add two different fixtures and 2× of one — confirm all lines are recorded.
3. Save an order needing more fixtures than are in stock — confirm a warning and a successful save.
4. Create a Track-2 write-off with a fixture — confirm stock decrements and cost is attributed.
5. Have Serhiy record a purchase on his server — confirm it appears as pending and creates nothing
   until confirmed, then confirm it and check the resulting `Розхідники` row.
6. Confirm nothing in the sync journal reports an error for any of the above.

## 9. Rollback note

**A fresh named Google Sheets version is required before deployment** — the last one predates the
`3D-P-015` migrations and is not a clean rollback point.

The `Платник` column is additive and reversible. The category rename is a data change across rows and
must be recorded in `diagnostics/` before execution so it can be reverted precisely. The form changes
touch the deployed CRM write path, so they need their own rollback and a focused smoke test.

## 10. Recommended status after execution

`In progress` on start. `Done` only on the owner's authorization after live QA — and note that item 5
of the QA cannot run until Serhiy's server is reworked (`3D-P-007`), so partial closure may be the
honest outcome. Say so rather than claiming full QA.

## Risk classification

**High.** This is a CRM / financial risk zone: it changes a live consumables sheet other logic reads,
it modifies the deployed order-save and write-off paths, and it feeds the number reimbursed to Serhiy
and the 50/50 split base. Rollback plan, focused smoke test and owner QA are mandatory. The owner is
the only deployment gate.
