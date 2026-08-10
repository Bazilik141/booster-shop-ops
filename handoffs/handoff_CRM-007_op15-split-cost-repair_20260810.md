# Handoff — CRM-007: repair the duplicated cost from the OP-15 box→pack split

Date: 2026-08-10 | Parent: none | Notion: `3b86bf20-bdb4-814d-a838-fcd3e218601a`
Executor: **Codex** · model=Sol · effort=xhigh — owner decision 2026-08-10. Justification: this is
a bounded write against live Google Sheets rows that are already identified cell by cell below, and
Codex already holds live workbook access and performed the comparable `CRM-STOCK-ADJUSTMENT` write
on 2026-08-08. CRM is a risky zone, so it does not go on a small model.

Diagnostic (read it before starting):
`diagnostics/CRM-COST-SPLIT_OP15-and-MZERO_claude-audit_20260810.md`.
It supersedes the withdrawn `diagnostics/CRM-OP15-split-cost-audit_report_20260810.md` — **do not
reuse any number from that file, in particular ₴14 150.69.**

> **Delivery path is not the usual one.** There is no PHP patch and no `patches/` file here. The
> change is a direct bounded write into the live CRM spreadsheet
> (`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`), the same mechanism used on 2026-08-08. Nothing is
> uploaded to `~/public_html` and nothing is deployed. **The owner must give an explicit go-ahead in
> the active task before the first write** — as of this handoff he has approved the analysis and the
> task, not the write.

---

## 1. Task ID

`CRM-007` — Repair duplicated cost from the OP-15 box→pack split + FIFO rule for internal SKU
transfers.

## 2. Context

`Закупки!58` (`LOT-0063`, `OP-JP-OP15-BBX`, order `yskh243`, track `LX322066112JP`, delivered
2026-06-27) carries ₴6 225.00 as `Вартість лоту` against `Кількість одиниць` = 1. The owner confirmed
on 2026-08-10 that ₴6 225.00 was paid for **two** boxes. The 2026-08-03 manual box→pack split then
created `Закупки!123` (`LOT-0119`) as exactly 20/24 of every money column of `LOT-0063`
(5187.50/6225 = 119.05/142.86 = 761.50/913.80 = 0.833333), i.e. it treated the two-box total as the
cost of one box. The purchase's landed cost is therefore recorded twice — ₴7 718.56 + ₴6 432.13 =
₴14 150.69 management against a true ₴7 718.56.

The first visible symptom was `OC-FOP-0314` (2026-08-10, one box sold for ₴3 655.00) showing a
management cost of ₴7 719.73.

Independent corroboration that ₴6 225.00 was the whole lot: ¥3 988 of Ukraine delivery on shipment
`LX322066112JP` was allocated across `LOT-0048` / `LOT-0063` / `LOT-0067` by lot value
(₴553.00 / ₴6 225.00 / ₴984.00) at the `Курси` rate JPY 3.5000, producing exactly the recorded
₴81.18 / ₴913.80 / ₴144.45.

Second defect in the same operation: `Товари` sets 24 boosters per box, but `LOT-0119` was booked as
20 while `Списання!WRT-0160` separately wrote off the 4 packs the owner opened — the same four packs
deducted twice. **Owner physically counted on 2026-08-10 and confirmed 28 packs on the shelf.**

Owner rule set 2026-08-10, standing: an internal SKU transfer always takes the cost of the **oldest
remaining lot (FIFO)**.

## 3. Goal

Make the ledger hold the purchase's real landed cost exactly once, split evenly between the retained
box and the converted box, and let the existing FIFO engine recompute everything downstream. No new
value may be created or destroyed.

## 4. What to change

### WP1 — OP-15 lot re-split (this round)

**Step 0 — mandatory pre-checks, before any write.**

1. Run the read-only `integrity_check` action and record its full bounded output verbatim in the
   diagnostic. It will not be clean; the known pre-existing `formula_column_literal` list in
   `Товари` and `Розхідники` is not yours. Rule `OPS-CRMINTEGRITY` step 4 applies: **any new problem
   code after the change is a defect of this change.**
2. Ask the owner to take a fresh full copy of the workbook (`Файл → Створити копію`) as the rollback
   artefact, and record its name and timestamp. Do not start before that copy exists.
3. Verify the row numbers by content, not by trusting this document: locate `LOT-0063` and `LOT-0119`
   by `Закупки!A`, and confirm `E58` = `OP-JP-OP15-BBX`, `E123` = `OP-JP-OP15-BST`.
4. **Read `Закупки!L58:P58` and `Закупки!L123:P123` as formulas, not values.** Row 58 is expected to
   be formula-driven (`L = I+J+K`, `N` = 6 % from `Налаштування!B6`, `O = L+N`, `M = L/H`, `P = O/H`).
   Row 123 was created by hand and may contain literals. **If `L123:P123` are literals, stop and tell
   the owner before writing anything** — writing a literal over a formula column is forbidden by
   `OPS-CRMINTEGRITY` step 2, and restoring the formulas is then part of this task rather than a
   silent side effect.

**Step 1 — write exactly these eight input cells. Nothing else in `Закупки`.**

| Cell | Column | Current | New |
|---|---|---:|---:|
| `Закупки!H58` | Кількість одиниць | 1 | 1 *(verify only, do not write if already 1)* |
| `Закупки!I58` | Вартість лоту, грн | 6 225.00 | **3 112.50** |
| `Закупки!J58` | Доставка / комісії по Японії, грн | 142.86 | **71.43** |
| `Закупки!K58` | Доставка UA, грн | 913.80 | **456.90** |
| `Закупки!H123` | Кількість одиниць | 20 | **24** |
| `Закупки!I123` | Вартість лоту, грн | 5 187.50 | **3 112.50** |
| `Закупки!J123` | Доставка / комісії по Японії, грн | 119.05 | **71.43** |
| `Закупки!K123` | Доставка UA, грн | 761.50 | **456.90** |

**Step 2 — verify the derived columns recomputed to these exact values.** Do not type them.

| | `LOT-0063` | `LOT-0119` |
|---|---:|---:|
| `L` Собівартість партії / ПРРО | 3 640.83 | 3 640.83 |
| `M` Собівартість 1 од. / ПРРО | 3 640.83 | 151.70 |
| `N` Кредитне обслуговування (6 %) | 218.45 | 218.45 |
| `O` Управлінська собівартість партії | 3 859.28 | 3 859.28 |
| `P` Управлінська собівартість 1 од. | 3 859.28 | 160.80 |

Conservation check that must hold: `L58 + L123` = 7 281.66 and `O58 + O123` = 7 718.56 — exactly the
original landed cost.

**Step 3 — append a note to `Закупки!R58` and `Закупки!R123`.** Append, never overwrite; use
`appendCellText_`-style `; ` separation. Suggested text (Ukrainian, owner-facing):
`2026-08-10 CRM-007: вартість лоту стосувалась 2 боксів; розділено порівну між LOT-0063 і LOT-0119,
LOT-0119 виправлено з 20 на 24 паки.`

**Step 4 — force `OC-FOP-0314` to recompute. Never type its cost by hand.**

Locate the row by `Продажі!A` = `OC-FOP-0314` (the earlier report said row 265 — verify, do not
trust it). Then:

1. Clear `Продажі!AD<row>` (`Метод собівартості`, column 30). This is required: `fixSaleCostForRow_`
   returns early when `L:M` hold no formulas **and** the method cell starts with `FIFO`, which is
   exactly this row's state, so without clearing it the recalculation is silently skipped.
2. Trigger the existing recalculation path — the dashboard order-update form (`apiUpdateSale_`),
   which calls `fixSaleCostForRow_` for every row of the order. A note-only update is enough.
   `sync3dpPackagingCost_` will log `skipped_no_3dp_sku`; that is expected and harmless.
3. Expected result on that row: `L` = **3 640.83**, `M` = **3 860.45** (3 859.28 plus the ₴1.17
   `Стікер лого+QR`), and `AD` back to a `FIFO`-prefixed method with an audit string naming
   `LOT-0063`.

**Step 5 — owner runs the warehouse cost refresh.** `updateSkuCurrentCost_` has no public API action;
the reachable entry point is the spreadsheet menu item backed by `updateSkuCurrentCostMenu()`. The
executor cannot run Apps Script functions — write this as an owner step, do not fake it.

Expected `Склад` afterwards for `OP-JP-OP15-BST`: Закуплено **48**, Продано 4, Списано 16, Залишок
**28**, average ≈ **141.77** ПРРО / **150.28** управлінська, `Потенційний прибуток` positive.
For `OP-JP-OP15-BBX`: Закуплено 1, Продано 1, Залишок 0, unit cost 3 640.83 / 3 859.28.

### WP2 — `ACC-003 → ACC-009` transfer value (separate round, do not combine)

Run this only after WP1 is verified and the owner gives a second go-ahead. Separate execution,
separate verification, separate diagnostic section — a shared session removes independent rollback.

`Списання!WRT-0167` (2026-08-08) removed 1 × `ACC-003` valued at 50.40 ПРРО / 53.42 управлінська,
while `Закупки!LOT-0129` added 25 × `ACC-009` valued at 85.00 ПРРО / 90.10 управлінська. ₴85.00
matches no `ACC-003` lot unit cost (118.80 / 178.65 / 131.85 / 23.85 / 178.65 / 66.83 / 40.50 / 38.93)
and no `Склад` average (50.40), so ₴34.60 ПРРО was created out of nothing.

Apply the owner rule: **oldest remaining lot at the moment of the transfer**.

1. Read live and record: every `ACC-003` lot with delivery date, quantity and unit costs; every
   `ACC-003` sale row with its date; `Списання!WRT-0166` and `WRT-0167`. The `Продажі` date of the one
   `ACC-003` sale is the deciding input and was **not** available to the audit — it determines whether
   the oldest remaining lot on 2026-08-08 was `LOT-0092` (23.85 / 25.28) or `LOT-0107`
   (178.65 / 189.37). **Do not guess it.**
2. Set `LOT-0129`'s `I` (and `J`/`K` if the source lot carried fees) so its ПРРО total equals the unit
   cost of that lot, and let `L:P` recompute. Append a dated note recording which lot was used and why.
3. Report the resulting per-unit `ACC-009` cost and the new `Склад` value; do not adjust `WRT-0167`'s
   own cost columns, which are part of the general write-off valuation model and out of scope here.

## 5. Do not touch

- `Продажі` rows other than `OC-FOP-0314`, and **all pre-2026-05-08 sale rows** — owner decision
  2026-08-10: the legacy rows that recalculate from current `Склад` instead of frozen FIFO
  (`OLX-FOP-0017`, `OLX-FOP-0021` and the rest of that population) stay untouched, accounting
  restarts on NCRM.
- `Списання!WRT-0160` — the four opened packs stay in ordinary oldest-lot FIFO. No lot reassignment.
- `PKM-JP-MZERO-BLR` — audited 2026-08-10, stock and the newest orders are correct. `LOT-0097` stays
  `Замовлено` with no Ukraine delivery cost.
- Every other row of `Закупки`, `Склад`, `Товари`, `РРЦ`, `Розхідники`, `Майстер_Товарів`, `Курси`,
  `Налаштування`.
- Any formula column, anywhere — `OPS-CRMINTEGRITY` step 2.
- `crm/apps-script/Code.gs` and the live Apps Script project. This task changes data, not code. Do
  not add a transfer function, do not extend `apiIntegrityCheck_` scope, do not publish a Web App
  version.
- The standing protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`,
  checkout, payment, fiscalization, Merchant feed, schema. None of them are in scope and none may be
  opened.
- Notion properties and status — Claude (chat) is the writer. If a status change is needed, say so
  and stop.
- `dashboard/booster-dashboard.html` — the `CRM-007` `ROADMAP_FLOW` row was already created by Claude
  on 2026-08-10. Do not create a second one.

## 6. Likely files / areas

Live spreadsheet only — `Booster Shop CRM — облік товарів`
(`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`), sheets `Закупки` (write), `Продажі` (one row),
`Склад` (recomputed, never typed), `Списання` (read-only), `Товари` / `Курси` / `Налаштування`
(read-only reference).

Reference only, not to be edited: `crm/apps-script/Code.gs` V98 mirror — `getFifoCostBatches_`,
`calculateFifoSaleCost_`, `fixSaleCostForRow_`, `updateSkuCurrentCost_`, `apiIntegrityCheck_`.
Row and column positions in this handoff are **likely, not confirmed** — the executor must verify
them against the live sheet by content.

## 7. Acceptance criteria

- [ ] `Закупки!L58` = 3 640.83, `M58` = 3 640.83, `N58` = 218.45, `O58` = 3 859.28, `P58` = 3 859.28
- [ ] `Закупки!H123` = 24, `L123` = 3 640.83, `M123` = 151.70, `N123` = 218.45, `O123` = 3 859.28,
      `P123` = 160.80
- [ ] `L58 + L123` = 7 281.66 and `O58 + O123` = 7 718.56 exactly
- [ ] `Закупки!L:P` on both rows are still formulas after the change, verified by reading formulas
- [ ] `OC-FOP-0314`: `L` = 3 640.83, `M` = 3 860.45, `AD` starts with `FIFO`, audit string names
      `LOT-0063`
- [ ] `Склад` `OP-JP-OP15-BST`: Закуплено 48, Залишок 28, average ≈ 141.77 / 150.28
- [ ] `Склад` `OP-JP-OP15-BBX`: Закуплено 1, Продано 1, Залишок 0
- [ ] `integrity_check` after the change reports **no new problem code** compared with the recorded
      before-run; both outputs pasted into the diagnostic
- [ ] `diagnostics/CRM-007_op15-split-cost-repair_report_20260810.md` written, including the rollback
      copy name and both integrity outputs

## 8. QA / smoke test (owner runs)

Not a checkout/payment/fiscalization change, so `bs-checkout-smoke` does not apply. Not an
SEO/schema/feed change, so `bs-seo-risk-gate` and `bs-merchant-schema-qa` do not apply. CRM risky
zone, so the following is mandatory:

- [ ] Take the fresh workbook copy **before** anything is written; note its name.
- [ ] Run the CRM integrity check from the dashboard before and after; copy both results.
- [ ] Run the warehouse cost refresh menu action after the `Закупки` change.
- [ ] Open `OC-FOP-0314` on the dashboard, expand the order lines, and confirm the cost is ≈ ₴3 860
      and no longer ≈ ₴7 720.
- [ ] Physically re-confirm 28 `OP-JP-OP15-BST` packs against the corrected `Склад` balance.
- [ ] Check `Склад` for `OP-JP-OP15-BST`: `Потенційний прибуток` must no longer be negative.
- [ ] Spot-check three unrelated SKUs (`PKM-JP-MZERO-BLR`, `PKM-JP-MDEX-BST`, `ACC-009`) — balances
      and costs must be unchanged from before the write.

## 9. Rollback note

Rollback is the fresh full copy of the workbook taken in Step 0 — restore it, or copy the recorded
before-values back into the eight cells and re-run the two recalculations. Record before-values for
every cell touched, in the diagnostic, **before** writing:
`Закупки!H58,I58,J58,K58,L58:P58,R58` and `Закупки!H123,I123,J123,K123,L123:P123,R123`, plus
`Продажі!L,M,AD,AE,AF` of the `OC-FOP-0314` row.

`Склад` needs no rollback of its own — it is fully derived and regenerates from `Закупки`,
`Продажі` and `Списання`.

If `L123:P123` turn out to be literals and are converted to formulas, record the literal values first;
that conversion is not reversible from the sheet alone.

## 10. Recommended status after execution

`In progress` until the owner has run the QA list above, including the physical pack recount. Then
the owner authorizes closure and **Claude (chat) writes `Done`** in Notion and mirrors `done` in
`ROADMAP_FLOW`. The Definition of Done gate for a CRM task is a live read-back — quote the corrected
`Закупки` and `Склад` values read from the live sheet after the change, not from this handoff.

WP2 does not block closure of WP1 if the owner chooses to split them; say so explicitly in the report
rather than leaving it implied.
