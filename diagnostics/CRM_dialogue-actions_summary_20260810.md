# Booster Shop CRM — dialogue actions summary

Date: 2026-08-10

## Final outcome

The main CRM is live on Web App **V101** (owner-reported, 22:06 Kyiv). Its
final live dashboard integrity run is clean:

```json
{
  "ok": true,
  "action": "integrity_check",
  "problems": [],
  "coverage": {
    "rrp_mismatch_3dp": {
      "compared": 1,
      "skipped_missing_crm_rrp": 0,
      "deferred": null
    }
  },
  "clean": true,
  "elapsed_ms": 5617
}
```

No Notion property/status, `ROADMAP_FLOW`, commit, push, or production server
file was changed in this dialogue.

## 1. CRM-006 — product short names and integrity policy

Recovery point: Google Sheets version `10 серпня, 17:56
CRM-006-4a-before-20260810`.

- Restored the ordinary short-name formula in 18 verified `Товари!B` cells:
  `B38:B39`, `B49:B51`, `B61:B67`, `B70`, and `B77:B81`.
- Read-back confirmed all 18 formulas; the 12 pre-existing visible names were
  unchanged and the six intended-empty results stayed empty.
- A temporary, read-only 2026-08-08 history copy proved that the remaining 15
  short names were intentional manual history, not lost formulas.
- The V99 source policy therefore exempted only the 15 verified SKU names from
  the `Товари → Коротка назва` formula check. It did not exempt prices or any
  other column.
- Local integrity tests passed; the owner published V99 at 19:59 Kyiv. Its
  live integrity result removed the short-name finding without masking the
  independent price/consumables backlog.

Detailed records:

- `diagnostics/CRM-006_pass4-formula-restore_report_20260810.md`
- `diagnostics/CRM-006_integrity-manual-short-names_report_20260810.md`

## 2. CRM-008 — One Piece starter decks and purchase `yskh293`

Recovery point: Google Sheets version `10 серпня, 15:01 До 008`.

- Added the `Starter Deck` / `STD` setting, set aliases `ST-32` through
  `ST-36`, and extended only the existing product dropdown validations.
- Added five active catalogue/RRP/master rows for `OP-JP-ST32-STD` through
  `OP-JP-ST36-STD`, all with CRM RRP 700.00 UAH. Existing formula columns were
  preserved.
- Created the six `Закупки!126:131` records for `yskh293`:
  `LOT-0131` through `LOT-0136`, with goods total 4,257.00 UAH and Japan-fee
  total 228.58 UAH after row-level rounding.
- Each purchase row is `Замовлено`; no delivery status, tracking number, stock
  count, or FIFO-cost recalculation was changed in this dialogue.
- Read-back proved all six purchase rows, lookup formulas, cost formulas, and
  product links. The three integrity runs around CRM-008 showed no new problem
  code or range; the old CRM-006 backlog was unchanged at that time.

One known scope caveat was identified but not altered: the current stock
formula does not count status `Замовлено` as expected stock. It starts counting
only at its configured in-transit/warehouse statuses.

Detailed record:

- `diagnostics/CRM-008_starter-decks-sku-and-purchase_report_20260810.md`

## 3. OLX-FOP-0050 — Mystery Mix XL cost correction

- Found that seven already-existing component write-offs were split across
  nonexistent order references `OLX-FOP-0051` to `OLX-FOP-0055` instead of
  `OLX-FOP-0050`.
- Relinked only `Списання!K178:L183` to `OLX-FOP-0050` and refreshed the
  derived cost/audit fields on `Продажі!L267:M267` and `AD267:AF267`.
- No stock quantity, SKU, purchase lot, sales quantity, date, or formula was
  changed. Those seven boosters had already been written off from stock.
- Read-back verified PRRO cost 734.48 UAH, management cost 780.63 UAH, gross
  profit 265.52 UAH, and net profit 212.62 UAH for the Mystery Mix XL sale.

Detailed record:

- `diagnostics/OLX-FOP-0050_mystery-xl-cost-relink_report_20260810.md`

## 4. CRM-006-5 — remaining formula-literal repair

Recovery point: Google Sheets version `Booster Shop CRM — облік товарів –
10 серпня, 21:41 (копія)`.

- Restored `Товари!J38:J39` as RRP lookup formulas. The current values became
  90.00 UAH and 2,700.00 UAH, matching `РРЦ` rather than the prior literals
  150.00 and 3,000.00 UAH.
- Restored `Розхідники` receipt/in-transit formulas in `F7:F15,F17` and
  `G6,G8,G10:G15,G17`.
- Restored the real sales-audit formula in `H15` for `Наліпка Mystery Box`; it
  now calculates 8 uses instead of the stale literal 0.
- The remaining 11 literals in `Розхідники → Використано в продажах` were
  verified manual historical marketing/3D use without matching source rows.
  Replacing them with sales-derived formulas would have changed history.
- Added a narrow V101 source exception for only those 11 exact names. A literal
  for any other consumable still fails the integrity check. Local automated
  tests covered all allowed entries and a non-exempt negative case and passed.
- The owner published V101 and produced the final clean integrity result above.

Detailed record:

- `diagnostics/CRM-006_pass5_formula-preflight_20260810.md`

## Current local artefacts

The deployed V101 mirror and its integrity tests are locally modified and
uncommitted, together with these diagnostics. No Git commit or push was made
because no such authorization was given.

```text
crm/apps-script/Code.gs
crm/apps-script/tests/integrity-check.test.mjs
crm/apps-script/SOURCE_STATE.md
diagnostics/CRM-006_pass5_formula-preflight_20260810.md
diagnostics/CRM_dialogue-actions_summary_20260810.md
```
