# Claude audit — OP-15 box→pack split cost duplication, and PKM-JP-MZERO-BLR cost chain

Date: 2026-08-10
Author: Claude (chat surface)
Scope: read-only diagnosis. No Google Sheet cell, Apps Script source, dashboard, Notion property or
order value was changed.

**This report supersedes both `diagnostics/CRM-OP15-split-cost-audit_report_20260810.md` (already
withdrawn by its author) and the OP-15 section of
`diagnostics/CRM-MZERO-FIFO-cost-audit_report_20260810.md`.** The MZERO-specific findings of the
latter are independently re-derived below and confirmed.

## Evidence source

Direct read of the live spreadsheet `Booster Shop CRM — облік товарів`
(`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`, modified 2026-08-10 07:30 UTC) via the Drive
connector, plus the V98 Apps Script mirror `crm/apps-script/Code.gs`.

Read limitation to record honestly: the Drive text export truncates the `Продажі` sheet after
2026-05-08 (about row 79 of ~265). All `Закупки`, `Склад`, `Списання`, `Розхідники`,
`Налаштування`, `Товари` and `РРЦ` rows quoted below were read in full from the live file. The two
legacy OLX sale rows were read directly. Post-May sale rows (`MAN-FOP-0002`, `OC-FOP-0304`,
`OC-FOP-0312`, `OC-FOP-0314`) could not be re-read; where they are quoted, the source is the earlier
Codex report and the values are cross-checked against independently computed FIFO. This is stated at
each point of use.

---

## Finding 1 (primary) — the two-box lot cost was never halved; the split doubled it a second time

### The recorded state

| Field | `Закупки!58` `LOT-0063` | `Закупки!123` `LOT-0119` |
|---|---:|---:|
| ZenMarket order | `yskh243` | `yskh243 (split)` |
| Track | `LX322066112JP` | `LX322066112JP` |
| Delivery date | 2026-06-27 | 2026-06-27 |
| SKU | `OP-JP-OP15-BBX` | `OP-JP-OP15-BST` |
| Кількість одиниць | **1** | **20** |
| Вартість лоту, грн | 6 225.00 | 5 187.50 |
| Доставка / комісії по Японії, грн | 142.86 | 119.05 |
| Доставка UA, грн | 913.80 | 761.50 |
| Собівартість партії / ПРРО | 7 281.66 | 6 068.05 |
| Собівартість 1 од. / ПРРО | 7 281.66 | 303.40 |
| Кредитне обслуговування (6 %) | 436.90 | 364.08 |
| Управлінська собівартість партії | 7 718.56 | 6 432.13 |
| Управлінська собівартість 1 од. | 7 718.56 | 321.61 |
| Статус | На складі UA | На складі UA |

`LOT-0119` note: `Роздербан 1 боксу LOT-0063: 20 паків переведено у OP-JP-OP15-BST.`

### Proof that 6 225.00 UAH is the price of BOTH boxes, not one

1. **Owner statement plus the source listing.** The Rakuma listing supplied by the owner is
   `神の島の冒険 2BOX` — two boxes — at ¥21 500, displayed as ≈ ₴6 317. The recorded lot value is
   ₴6 225.00. Same order of magnitude, one lot, two boxes.
2. **The Ukraine-delivery allocation proves 6 225.00 was the whole lot's value on 2026-06-27.**
   Shipment `LX322066112JP` carried three lots: `LOT-0048` (₴553.00), `LOT-0063` (₴6 225.00),
   `LOT-0067` (₴984.00); total ₴7 762.00. `LOT-0063`'s note records ¥3 988 of Ukraine delivery
   "розподілена за вартістю лоту". At the `Курси` rate JPY = 3.5000 per ₴1, ¥3 988 = ₴1 139.43, and
   proportional allocation gives ₴81.18 / ₴913.80 / ₴144.45 — **exactly** the three recorded values.
   So ₴6 225.00 was already the full lot value at delivery, before any split.
3. **The sale price contradicts a one-box reading.** `OC-FOP-0314` sold one box for ₴3 655.00
   (corroborated independently by the `Оновити_продаж` dropdown row: `ТТН 0177 | 2026-08-10 |
   3 655,00 грн | Оплачено | Відправлено | Еквайринг`). No one sells a box for ₴3 655 that cost
   ₴7 281.66 to land; ₴3 655 sits just above half of that, ₴3 640.83.
4. **The pack margin contradicts it too.** `Товари` sets `OP-JP-OP15-BBX` = 24 boosters per box and
   `Поточна ціна продажу` ₴4 800 (₴200 per pack). At the recorded ₴321.61 management cost per pack
   the RRP is below cost; `Склад` accordingly shows `Потенційний прибуток` = **−₴1 980.72** for
   `OP-JP-OP15-BST`. At the halved cost (₴160.80) the margin is normal.

### Proof that the split re-used the full lot value a second time

Every money column of `LOT-0119` is exactly **5/6 = 20/24** of the corresponding `LOT-0063` column:

```
5187.50 / 6225.00 = 0.833333
 119.05 /  142.86 = 0.833333
 761.50 /  913.80 = 0.833333
```

So the split took `LOT-0063`'s **total** (two-box) cost, treated it as the cost of one box, divided
by 24 and multiplied by 20. The whole two-box cost therefore now appears twice in the ledger:

| | Управлінська, ₴ |
|---|---:|
| Real landed cost of the purchase (two boxes) | 7 718.56 |
| Currently recorded on `LOT-0063` (1 box) | 7 718.56 |
| Currently recorded on `LOT-0119` (20 packs) | 6 432.13 |
| **Total in the ledger** | **14 150.69** |
| **Overstatement** | **+6 432.13** |

### Why no automation caught it

- There is **no box-to-pack transfer function anywhere in the CRM Apps Script** (V98 mirror). Both
  the OP-15 split and the later `ACC-003 → ACC-009` split were manual row edits.
- `apiIntegrityCheck_` only checks `Товари`, `РРЦ`, `Розхідники` and `Майстер_Товарів`
  (`report.checked`). `Закупки`, `Продажі`, `Склад` and `Списання` — the entire cost engine — are
  **out of its scope by construction**. A clean integrity check says nothing about this class of
  defect.
- `Закупки!L:P` are derived (`ПРРО партії = I+J+K`; `кредитне обслуговування = 6 %` from
  `Налаштування!B6`; `управлінська = ПРРО + кредит`). They faithfully recomputed a wrong input, which
  is why the row looks internally consistent.
- `getFifoCostBatches_` reads batches by SKU only and has no notion of a parent lot, so the doubled
  unit cost propagated straight into `fixSaleCostForRow_` for `OC-FOP-0314`.

### Second, separate defect in the same operation: 20 packs instead of 24

`Товари` states 24 boosters per box. `Списання!WRT-0160` (2026-08-03) reads
`1 × OP-JP-OP15-BBX (20 паків) → OP-JP-OP15-BST; 4 паки списано на власне відкриття.`

So the box was booked in as 20 packs **and** 4 packs were written off — the four opened packs were
deducted twice. `Склад` currently shows `OP-JP-OP15-BST`: Закуплено 44, Продано 4, Списано 16,
Залишок 24. With 24 packs booked in, the same sales and write-offs leave **28**.

This requires a physical count before repair; see the owner action list.

### Required corrected values (value-neutral repair)

Only the input columns `H`, `I`, `J`, `K` need to change; `L:P` recompute — **unless** `LOT-0119`
was created with literal values in `L:P`, which the executor must verify first, since it is a
hand-made row and `apiIntegrityCheck_` does not cover `Закупки`.

| Field | `LOT-0063` (retained box) | `LOT-0119` (converted box) |
|---|---:|---:|
| Кількість одиниць | 1 | **24** (pending physical count) |
| Вартість лоту, грн | **3 112.50** | **3 112.50** |
| Доставка / комісії по Японії, грн | **71.43** | **71.43** |
| Доставка UA, грн | **456.90** | **456.90** |
| Собівартість партії / ПРРО (derived) | 3 640.83 | 3 640.83 |
| Собівартість 1 од. / ПРРО (derived) | 3 640.83 | **151.70** |
| Кредитне обслуговування 6 % (derived) | 218.45 | 218.45 |
| Управлінська партії (derived) | 3 859.28 | 3 859.28 |
| Управлінська 1 од. (derived) | 3 859.28 | **160.80** |

Value conservation check: 3 640.83 × 2 = 7 281.66 and 3 859.28 × 2 = 7 718.56 — exactly the original
landed cost, nothing created or destroyed.

### Downstream effects of the repair

| Object | Now | After repair |
|---|---:|---:|
| `OC-FOP-0314` Собівартість продажу / ПРРО | 7 281.66 | **3 640.83** |
| `OC-FOP-0314` Управлінська собівартість продажу | 7 719.73 (incl. ₴1.17 sticker) | **3 860.45** |
| `Склад` `OP-JP-OP15-BST` Залишок | 24 | **28** |
| `Склад` `OP-JP-OP15-BST` сер. ПРРО / управлінська | 266.54 / 282.53 | ≈ **141.77 / 150.28** |
| `Склад` `OP-JP-OP15-BST` Потенційний прибуток | −1 980.72 | positive |

The `Склад` averages above are the expected result of the existing `updateSkuCurrentCost_` FIFO
logic (4 packs left from `LOT-0036` at 82.21/87.14 plus 24 packs from the corrected `LOT-0119`); they
must be produced by re-running that routine, not typed in.

Note for the owner's expectations: `OC-FOP-0314` remains a near-break-even order after the repair
(₴3 655.00 revenue against ₴3 640.83 PRRO cost, before acquiring and packaging). The repair changes
a fictitious ≈ ₴4 000 loss into a real result of roughly break-even; it does not turn the order
profitable. `WRT-0160` needs no lot reassignment — the four opened packs stay in ordinary oldest-lot
FIFO.

---

## Finding 2 — `PKM-JP-MZERO-BLR`: stock and the two newest orders are correct; two legacy OLX rows are not

### Lot chain and current stock — correct

| Lot | Delivery | Qty | ПРРО 1 од. | Управлінська 1 од. | Status |
|---|---|---:|---:|---:|---|
| `LOT-0001` | 2026-04-01 | 1 | 1 255.39 | 1 330.71 | Продано |
| `LOT-0002` | 2026-04-01 | 1 | 1 115.39 | 1 182.31 | Продано |
| `LOT-0047` | 2026-06-15 | 1 | 769.60 | 815.78 | Продано |
| `LOT-0072` | 2026-07-05 | 2 | 1 210.63 | 1 283.27 | Продано |
| `LOT-0080` | 2026-07-19 | 2 | 927.41 | 983.05 | На складі UA |
| `LOT-0097` | — | 2 | 747.43 | 792.28 | Замовлено |

`Склад` shows Закуплено 7, Продано 5, Списано 0, Залишок 2, average 927.41 / 983.05, stock value
1 854.82 / 1 966.10. Seven delivered minus five sold leaves exactly `LOT-0080`, whose unit cost is
what `Склад` shows. **The warehouse figures and the "last batch" cost are correct.**

The owner's "ще 2 закуплені чи в дорозі" are `LOT-0097`. They appear in `Склад!Закуплено всього` = 9
but in `Очікується / Японія` = 0, because that column counts only `В дорозі`, `На складі в Японії`
and `Виграно`, and this lot is still `Замовлено`. This is a status-labelling artefact, not a cost
error. `LOT-0097` also has no Ukraine-delivery cost yet, so its 747.43 / 792.28 is provisional and
will rise on delivery — expected behaviour, not a defect.

### The real defect: legacy sale rows carry a live lookup instead of a frozen historical cost

Read directly from the live sheet:

```
Продажі  OLX-FOP-0017  2026-04-05  PKM-JP-MZERO-BLR  1  ...  927,41  983,05  927,41  983,05
Продажі  OLX-FOP-0021  2026-04-19  PKM-JP-MZERO-BLR  1  ...  927,41  983,05  927,41  983,05
```

927.41 / 983.05 is the **current** `Склад` value, i.e. `LOT-0080`, delivered 2026-07-19 — three
months after these sales. The correct FIFO costs are `LOT-0001` (1 255.39 / 1 330.71) for
`OLX-FOP-0017` and `LOT-0002` (1 115.39 / 1 182.31) for `OLX-FOP-0021`. Combined management cost is
understated by **₴546.92** and historic profit overstated by the same amount.

**This is wider than MZERO.** Every one of the 77 sale rows visible in the export — all rows up to
2026-05-08 — has an empty `Метод собівартості` (column 30), meaning none of them carries a frozen
FIFO value; they are all pre-cutover formula rows of the same kind. The two MZERO rows are the part
of that population the owner happened to notice. The full count and the total distortion across all
SKUs have **not** been measured and should be a separate task.

`fixSaleCostForRow_` would rewrite these rows correctly if they were touched: `Налаштування!B8`
(`Дата початку складського обліку`) is 2026-04-01, which is earlier than every sale, so
`getConsumedQtyBeforeSale_` counts the full history and FIFO ordering would come out right. Nothing
blocks the repair mechanically.

### The three newer orders

`MAN-FOP-0002` (2026-06-16), `OC-FOP-0304` (2026-08-06) and `OC-FOP-0312` (2026-08-09) fall outside
the truncated export and were not re-read here. The values reported by Codex — 816.95, 1 284.44 and
1 284.44 management — match independently computed chronological FIFO over the lot table above
(`LOT-0047` then `LOT-0072` twice) plus the ₴1.17 automatic sticker. No contradiction found, but
this is a consistency check, not a fresh read.

---

## Finding 3 (flag, not a proven error) — the `ACC-003 → ACC-009` split is not value-neutral either

The later split, done 2026-08-08 with the better paired-transaction convention, still does not
balance:

- `Списання!WRT-0167` removes 1 × `ACC-003` valued at 50.40 ПРРО / 53.42 управлінська;
- `Закупки!LOT-0129` adds 25 × `ACC-009` valued at 85.00 ПРРО / 90.10 управлінська in total.

An internal transfer must conserve value; this one creates ₴34.60 ПРРО / ₴36.68 управлінська. The
₴85.00 matches no `ACC-003` lot unit cost (118.80, 178.65, 131.85, 23.85, 178.65, 66.83, 40.50,
38.93) and no `Склад` average (50.40). The likely cause is that write-off rows are valued at the
`Склад` average while the incoming lot was priced by hand, but the intended basis is not recorded
anywhere, so this needs an owner decision rather than a unilateral correction.

It also shows that the two splits used **two different conventions** — OP-15 decremented the source
lot quantity, ACC-003 used a paired write-off. Until the CRM has a real transfer operation, each
manual split will keep inventing its own accounting.

---

## Finding 4 — the integrity-check output is unrelated to all of the above

The supplied `integrity_check` result is genuine but orthogonal. It reports `formula_column_literal`
only in sheets that are in its scope:

| Sheet | Column | Rows | Consequence |
|---|---|---|---|
| `Товари` | `Коротка назва` | 38-39, 49-67, 71-76 | display/name derivation frozen |
| `Товари` | `Поточна ціна продажу` | 38-39 | price no longer follows `РРЦ` |
| `Розхідники` | `Надійшло через витрати` | 7-15, 17 | new purchases stop increasing available qty |
| `Розхідники` | `Їде через витрати` | 6, 8, 10-15, 17 | in-transit qty frozen |
| `Розхідники` | `Використано в продажах` | 10-11, 13-15, 17-23 | consumption no longer follows sales |

The one that touches money in the orders examined here is `Розхідники` row 8, `Стікер лого+QR`
(₴1.17, auto-applied per order): its `Надійшло через витрати` = 300 is now a literal, so
`getAutoConsumableInfo_().totalQty` will not grow when more stickers are bought and the automatic
line can silently stop being applied. Small per order, but it drifts.

Neither `OP-JP-OP15-BBX` nor `OP-JP-OP15-BST` nor `PKM-JP-MZERO-BLR` appears in the reported
`Товари` ranges. **No listed integrity problem explains Finding 1 or Finding 2.**

---

## Owner actions required before any repair

1. **Physically count `OP-JP-OP15-BST` packs on the shelf.** The ledger says 24. If the box really
   contained 24 packs and 4 were opened, the true figure is 28 and `LOT-0119` must become 24 units.
   If the count is 24, then `WRT-0160`'s wording is wrong instead and the quantity part of the repair
   is dropped. The money part of Finding 1 holds either way.
2. **Confirm the two-box reading of `LOT-0063`** — that ₴6 225.00 was paid for two boxes together.
   Every derived figure in Finding 1 depends on it.
3. **Decide the intended basis for the `ACC-003 → ACC-009` transfer value** (Finding 3).
4. **Approve scope** for a repair task. This is a CRM risky-zone write with no staging: it touches
   `Закупки`, a historical order's frozen cost, and `Склад`. It needs a fresh spreadsheet copy as
   rollback, `integrity_check` run immediately before and after, and the repair confined to the rows
   named above.

## Owner responses, 2026-08-10

1. **Two-box reading CONFIRMED by the owner**: ₴6 225.00 was paid for two boxes. Finding 1's money
   correction is therefore approved as factually grounded; it still awaits scope approval to execute.
2. **Pack quantity — NOT yet resolved.** The owner answered "мало б бути 28, але 4 ми списали бо я
   відкрив для себе, тому 24 коректна кількість фізична". That reasoning subtracts the four opened
   packs a second time. The 28 figure already nets them out:

   | Step | Packs |
   |---|---:|
   | Left from `LOT-0036` before 2026-08-03 (24 delivered − 4 sold − 12 written off) | 8 |
   | Box opened, 24 packs in | +24 |
   | `WRT-0160`, 4 packs opened by the owner | −4 |
   | **Expected physical** | **28** |
   | Ledger (`LOT-0119` booked as 20 instead of 24) | 24 |

   Write-off evidence, read in full: `WRT-0056` 1, `WRT-0077` 3, `WRT-0088` 1, `WRT-0090` 1,
   `WRT-0105` 1, `WRT-0116` 1, `WRT-0146` 1, `WRT-0150` 2, `WRT-0155` 1 = 12, plus `WRT-0160` 4 = 16,
   matching `Склад!Списано` = 16.

   **A physical count is still required.** If the shelf really holds 24, four packs left stock
   without a record and that is a separate discrepancy; the ledger cannot both book 20 in and write 4
   off for the same event.
3. **`ACC-003 → ACC-009` (Finding 3) — owner did not follow the question.** Restated for the next
   session: which physical `ACC-003` pack was broken open, and what did that pack cost? The lots
   differ widely (118.80 / 178.65 / 131.85 / 23.85 / 178.65 / 66.83 / 40.50 / 38.93). Until that is
   answered, or a standing rule (e.g. always take the oldest remaining lot, FIFO) is agreed, the
   ₴34.60 gap cannot be closed correctly.
4. **Repair scope — owner will approve later.** No handoff written.
5. **Legacy pre-cutover sale rows — owner decision: do not fix.** Rationale: accounting restarts on
   NCRM. Recorded consequence, not a challenge to the decision: `OLX-FOP-0017` and `OLX-FOP-0021`
   (and the rest of the pre-2026-05-08 population) keep recalculating from current `Склад`, so
   all-time profit totals stay wrong and keep moving. Current-period figures are unaffected —
   post-cutover rows carry frozen FIFO values in `Метод собівартості`.

## Follow-up created, 2026-08-10

On owner instruction: Notion `CRM-007` (`3b86bf20-bdb4-814d-a838-fcd3e218601a`, `In progress`,
High, executor **Codex** by owner choice), its `ROADMAP_FLOW` mirror row in
`dashboard/booster-dashboard.html`, the `ROADMAP_SOP.md` §5 registry entry, the `context-index.md`
row, and `handoffs/handoff_CRM-007_op15-split-cost-repair_20260810.md`.

ID assignment evidence: `ROADMAP_SOP.md` §5 said the next free `CRM-` ID was `CRM-006`; a live Notion
query of the whole series returned `CRM-001`…`CRM-006` plus `CRM-006-ORDER`; max + 1 = `CRM-007`. The
stale registry line was corrected in the same edit.

## Not done here, deliberately

Nothing was written to the live spreadsheet — the owner has approved the analysis and the task, not
the write. The measurement of the full legacy-formula sale population (Finding 2, wider scope) stays
open and unscoped by owner decision.
