# Handoff — CRM-008: five OP-16 starter-deck SKUs + purchase lot `yskh293`

Date: 2026-08-10 | Parent: none | Notion: `3b86bf20-bdb4-8129-bddf-e002b9e8cd87`
Executor: **Codex** · model=Sol · effort=xhigh — same reasoning as `CRM-007`: bounded writes into the
live CRM workbook, which Codex already reaches, and CRM is a risky zone that does not go on a small
model.

Companion content task: `CONTENT-005` (product cards + site publication). Do not do any of it here.

> **Delivery path.** Direct bounded writes into the live CRM spreadsheet
> (`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`). No patch file, no `~/public_html`, no deploy.
> **The owner must give an explicit go-ahead in the active task before the first write.**

> **Ordering against `CRM-007`.** `CRM-007` repairs `Закупки` rows 58 and 123. This task appends new
> `Закупки` rows and new `Товари` / `РРЦ` rows. They do not overlap, but they must not run in the
> same session — two writers against `Закупки` at once is a parallel-writer violation. Finish and
> verify one, then start the other. The owner decides the order.

---

## 1. Task ID

`CRM-008` — Five OP-16 starter-deck SKUs + purchase lot `yskh293` into the main CRM.

## 2. Context

The owner bought lot `yskh293` on 2026-08-10 (ZenMarket/Mercari `m15056144167`): one sealed OP-16
`決戦の刻` booster box plus five different starter decks, ST-32 through ST-36. Total ₴4 257.00 plus
¥800 Japan fees. Status `Замовлено` — not shipped, so there is no Ukraine delivery cost yet.

`OP-JP-OP16-BBX` already exists in `Товари` (24 boosters per box, 6 cards per booster) and in `РРЦ`
at ₴5 000. The five decks do not exist as SKUs, and the CRM has **no `Starter Deck` format at all**
— `Налаштування` lists only Booster, Booster Box, Blister, Collection Set, Mystery Box, Single Card,
Promo, Accessory, Booster Bundle, Mini Tin, 3D аксесуар.

## 3. Goal

Five new SKUs exist once each in `Товари`, `РРЦ` and the automation master catalogue, and the whole
six-line lot is booked as a single logical purchase under `yskh293` with the owner's cost split — with
the integrity check no worse after than before.

## 4. What to change

### Owner decisions, 2026-08-10 — these are given, not to be re-derived

| Decision | Value |
|---|---|
| Format | new `Starter Deck`, code `STD` |
| SKUs | `OP-JP-ST32-STD`, `OP-JP-ST33-STD`, `OP-JP-ST34-STD`, `OP-JP-ST35-STD`, `OP-JP-ST36-STD` |
| Character suffix | **none** — ST-32…ST-36 are already unique, unlike the `PKM-EN-Q2-MTIN-SAL/-GAL` case |
| RRP | ₴700.00 per deck |
| Cost split of ₴4 257.00 | box **₴3 000.00**, each deck **₴251.40** (3 000 + 5 × 251.40 = 4 257.00 exactly) |

Character mapping, from the owner's photo of the physical lot — use it for names, do not invent
anything beyond it:

| SKU | Deck | Character | Colour on box |
|---|---|---|---|
| `OP-JP-ST32-STD` | ST-32 | ロロノア・ゾロ — Roronoa Zoro | green |
| `OP-JP-ST33-STD` | ST-33 | クザン — Kuzan | blue |
| `OP-JP-ST34-STD` | ST-34 | シャーロット・カタクリ — Charlotte Katakuri | purple |
| `OP-JP-ST35-STD` | ST-35 | サボ — Sabo | black |
| `OP-JP-ST36-STD` | ST-36 | ユースタス・キッド — Eustass Kid | yellow |

### WP1 — settings, catalogue and RRP

Follow `docs/CRM-new-SKU-runbook.md` step by step. It is the procedure; this section only adds what
is specific to this lot.

**Step 0 — pre-checks.**

1. Run the dashboard CRM integrity check and record the full bounded output as the baseline. It will
   not be clean. The known `formula_column_literal` entries in `Товари` and `Розхідники` are
   **pre-existing and belong to `CRM-006` pass 4** — record them, do not repair them.
2. Ask the owner for a fresh full copy of the workbook as the rollback artefact; record its name and
   timestamp. Do not start before it exists.

**Step 1 — two structural additions in `Налаштування`**, both dropdown sources. Locate them by header
text, not by remembered coordinates.

- `Формати товарів` list: add `Starter Deck` with code `STD` and a short label.
- Set-code list (the one holding `OP-10`→`OP10`, `EB-03`→`EB03`, `V7 Promo`→`V7PR`): add `ST-32`→`ST32`,
  `ST-33`→`ST33`, `ST-34`→`ST34`, `ST-35`→`ST35`, `ST-36`→`ST36`.

Note for accuracy, so you do not over-engineer: several existing SKUs already use format codes that
are **absent** from that table (`BBN` for Booster Bundle, `MTIN` for Mini Tin, `ACC`). The table is
therefore not the authority for SKU codes — it is the dropdown source. Add the rows anyway for
consistency, but if a dropdown range has to be widened, treat that as a structural change and say so
in the diagnostic.

**Step 2 — five rows in `Товари`.** Manual-input columns only.

`A` SKU · `C` Повна назва для сайту · `D` Бренд = `One Piece` · `E` Мова = `JP` · `F` Сет = `ST-32`…`ST-36` ·
`G` Формат = `Starter Deck` · `K` Мінімальний залишок = `1` · `L` Активний товар = `Так` ·
`M` Посилання на товар = leave empty, `CONTENT-005` fills it after publication · `N` Примітка =
`Лот yskh293, 2026-08-10`.

- **`B` (`Коротка назва`) and `J` (`Поточна ціна продажу`) are formula columns — do not type into
  them.** After adding the rows, check that both formulas actually reach the new rows. If they do
  not, **stop and report**: the cause is the overwritten block in rows 38-39 / 49-67 / 71-76 that
  `CRM-006` pass 4 owns. Do not fix it here and do not drag a formula over that block.
- `H` (`Карт у бустері`) and `I` (`Бустерів у боксі`) describe boosters, not decks. **Leave both
  empty** unless the owner supplies verified deck contents. Do not invent a card count.
- `O` (`Фіксована собівартість`) stays empty — cost comes from the purchase lots.

**Step 3 — `РРЦ`.** Wait for `РРЦ!A:D` to expand the five SKUs automatically, then enter ₴700.00,
the date `2026-08-10` and a note **in those SKU-keyed rows only**. Never type a price into a blank
`РРЦ` row — that is exactly the defect that produced 150 integrity problems on 2026-08-09.

**Step 4 — `Майстер_Товарів`** (automation workbook). Verify only: each SKU appears once, `Активний`
matches `Товари`, and the CRM price is populated. Do not create manual rows and do not type over
formulas. If a SKU does not appear, report it — `Майстер_Товарів!P2` was a known defect fixed under
`CRM-006`, so a recurrence is information the owner needs.

### WP2 — the purchase, after WP1 is verified

`apiAddPurchase_` slices `items` to **three** and the `Внести_закупку` form reads only `A12:E14`, so
six lines require **two entries**. Both carry the same `order_ref` `yskh293`.

| | Entry 1 | Entry 2 |
|---|---|---|
| `order_ref` | `yskh293` | `yskh293` |
| `total_cost` | **3 502.80** | **754.20** |
| `japan_fees_jpy` | **400** | **400** |
| `ukraine_delivery_uah` | empty | empty |
| `status` | `Замовлено` | `Замовлено` |
| `supplier_channel` | `zenmarket_jp` | `zenmarket_jp` |
| `order_url` | `https://zenmarket.jp/mercariproduct.aspx?itemCode=m15056144167` | same |
| Line 1 | `OP-JP-OP16-BBX` qty 1, manual_cost **3 000.00** | `OP-JP-ST35-STD` qty 1, manual_cost **251.40** |
| Line 2 | `OP-JP-ST32-STD` qty 1, manual_cost **251.40** | `OP-JP-ST36-STD` qty 1, manual_cost **251.40** |
| Line 3 | `OP-JP-ST33-STD` qty 1, manual_cost **251.40** | `OP-JP-ST34-STD` qty 1, manual_cost **251.40** |

Why these exact numbers:

- `apiAddPurchase_` throws when all lines carry a manual cost and their sum differs from `total_cost`
  by more than 0.05, so each entry's `total_cost` must equal its own three manual costs. 3 502.80 +
  754.20 = 4 257.00.
- Japan fees are allocated **by quantity**, not by value, so ¥400 across three units in each entry
  keeps every unit at the same ¥133.33 ≈ ₴38.10. Entering ¥800 twice would book ₴457.14 of fees
  instead of ₴228.57.
- Rate: `Курси` JPY = 3.5000 per ₴1. Verify it is still 3.5000 at execution time; if it changed, the
  ₴ figures below change with it and must be recomputed, not copied.

Expected after both entries: six new `Закупки` rows with auto-generated `LOT-` ids, `Кількість
одиниць` 1 each, `Вартість лоту` 3 000.00 / 251.40 × 5, `Доставка / комісії по Японії` ≈ 38.10 each
(one line absorbs the rounding remainder), `Доставка UA` empty, status `Замовлено`, and no delivery
date.

Append a note to each new row: `Лот yskh293; розподіл вартості за рішенням власника 2026-08-10: бокс
3 000 грн, колоди по 251.40 грн.`

## 5. Do not touch

- `Товари!B` and `Товари!J` on **any** row, new or old — `CRM-006` pass 4 owns them.
- `РРЦ!A3:D3` ARRAYFORMULA seeds and any blank `РРЦ` row.
- `Розхідники` derived columns and `Майстер_Товарів` formula outputs.
- `Закупки` rows 58 and 123 (`LOT-0063`, `LOT-0119`) — those belong to `CRM-007`. Any other existing
  `Закупки` row.
- `Продажі`, `Склад`, `Списання` — nothing in this task writes there. `Склад` picks the new SKUs up
  by itself.
- The OpenCart site, product pages, categories, the Merchant feed and schema — that is `CONTENT-005`.
- `crm/apps-script/Code.gs` and the live Apps Script project. No code change, no new Web App version.
- Standing protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout,
  payment, fiscalization, Merchant feed, schema.
- Notion properties and status — Claude (chat) is the writer.
- `dashboard/booster-dashboard.html` — the `CRM-008` and `CONTENT-005` rows were created by Claude on
  2026-08-10. Do not add duplicates.

## 6. Likely files / areas

Live spreadsheet `Booster Shop CRM — облік товарів`
(`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`): `Налаштування`, `Товари`, `РРЦ`, `Закупки` (write);
`Курси`, `Склад` (read / derived). Automation workbook
(`1YUGdtxHQJee6vY8MdwRsrUxudJCMtnghOGPVJXwO5ik`): `Майстер_Товарів` (verify only).

Reference only: `docs/CRM-new-SKU-runbook.md`, `AGENTS.md` §`OPS-CRMINTEGRITY`,
`crm/apps-script/Code.gs` V98 (`apiAddPurchase_`, `getCurrencyRate_`, `apiIntegrityCheck_`).
All column letters and coordinates in this handoff are **likely, not confirmed** — resolve them by
header text against the live sheet.

## 7. Acceptance criteria

- [ ] `Налаштування` contains format `Starter Deck` / `STD` and set codes `ST-32`…`ST-36` → `ST32`…`ST36`
- [ ] `Товари` holds exactly five new rows, one per SKU, `Формат` = `Starter Deck`, `Активний товар` = `Так`
- [ ] `Товари!B` and `Товари!J` on the five new rows are **formula results, not typed values** — verified by reading formulas, not values
- [ ] `РРЦ` shows all five SKUs keyed automatically in `A:D`, each with ₴700.00 and date `2026-08-10`
- [ ] `Майстер_Товарів` shows each SKU once, `Активний` matching `Товари`, price populated
- [ ] Six new `Закупки` rows exist under `yskh293`: costs 3 000.00 / 251.40 × 5, summing to 4 257.00
- [ ] Japan fees across the six rows sum to ≈ ₴228.57, **not** ₴457.14
- [ ] `Склад` lists all five deck SKUs with Закуплено 0 and Очікується reflecting `Замовлено` per the existing formula — do not adjust `Склад` by hand
- [ ] Integrity check after the change shows **no new problem code and no new row range** versus baseline; both outputs pasted into the diagnostic
- [ ] `diagnostics/CRM-008_starter-decks-sku-and-purchase_report_20260810.md` written, with the rollback copy name, both integrity outputs, and the generated `LOT-` ids

## 8. QA / smoke test (owner runs)

Not checkout/payment/fiscalization, so `bs-checkout-smoke` does not apply. Not SEO/schema/feed, so
`bs-seo-risk-gate` and `bs-merchant-schema-qa` do not apply — they become relevant in `CONTENT-005`.
CRM risky zone, so:

- [ ] Fresh workbook copy **before** any write; note its name.
- [ ] Integrity check before and after; copy both results.
- [ ] Open the dashboard `Товари` tab and confirm the five decks appear with RRP ₴700 and no error.
- [ ] Open the dashboard `Склад` tab and confirm the five decks appear without breaking existing rows.
- [ ] Check the `Закупки` sheet: six rows under `yskh293`, total ₴4 257.00, Japan fees ≈ ₴228.57.
- [ ] Spot-check three unrelated SKUs (`OP-JP-OP16-BBX`, `PKM-JP-MZERO-BLR`, `ACC-009`) — unchanged.
- [ ] When the parcel arrives, enter the Ukraine delivery cost and change status through the normal
      purchase-update form. That is routine operation, not part of this task.

## 9. Rollback note

Rollback is the fresh workbook copy taken in Step 0. Because this task **adds** rather than edits,
the manual reverse is also bounded: delete the six new `Закупки` rows, the five `Товари` rows, the
five `РРЦ` prices, and the added `Налаштування` entries — in that order, so no formula reads a
half-deleted SKU. Record the exact row numbers of everything added, in the diagnostic, as they are
created.

If the `Товари` formulas in `B`/`J` had to be extended to reach the new rows, that is **not** a
rollback-safe action and must not be done here at all — report and stop instead.

## 10. Recommended status after execution

`In progress` until the owner has run the QA list. Then the owner authorizes closure and **Claude
(chat) writes `Done`** in Notion and mirrors `done` in `ROADMAP_FLOW`. The CRM Definition-of-Done
gate is a live read-back: quote the five `Товари` rows, the five `РРЦ` prices and the six `Закупки`
rows as read from the live sheet after the change.

`CONTENT-005` starts only after this task closes — its first step needs the SKUs to exist.
