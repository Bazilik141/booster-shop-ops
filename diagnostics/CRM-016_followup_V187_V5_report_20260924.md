# CRM-016 follow-up: V187/V5 baseline and owner QA findings

Date: 2026-09-24. The owner reports that deployed CRM V187 and Alerts V5 are
exactly the two previously supplied local API files, with no subsequent manual
edits. The new files in `work/` are local release candidates. This report does
not claim a new Web App deployment or live write test.

## Local changes

- Purchases now display one top-level row per supplier + order reference. The
  expandable row shows each technical LOT-ID/SKU, quantity, management cost per
  unit and per position, purchase/shipping dates, tracking, and status. The
  top-level cost sums the formula-derived management cost of all member rows;
  any missing member cost leaves the total unavailable. A mixed-status order
  stays active while any member remains outside a terminal status. Column
  sorting, visibility, and order remain supported with new v2 preferences.
- Purchase creation accepts at most 10 SKU positions, rejects 11 rather than
  silently truncating, and writes 10 positions in four range calls instead of
  40 cell/range calls. Accounting status updates accept up to 25 SKU positions;
  26 are rejected before writes.
- For preorder order-line removal, later sales are allowed only when the target
  and every later sale audit use the same sole arrived LOT-ID. All other later
  sale cases remain blocked. The failed `OC-FOP-0335` / `PKM-EN-CHRS-BST`
  matches the one-lot pattern in the read-only live inspection. No order was
  edited in this run.
- The ZenMarket statement groups consecutive purchase-sync entries with the
  same order reference within five minutes into one visible debit and retains
  the final balance. For `yskh374`, the three ledger shares add to ¥7,360.02:
  ¥2,453.32 + ¥2,453.32 + ¥2,453.38. The source purchase is ₴2,050 goods
  plus ¥800 commission, allocated across three SKUs. The ledger is not triple
  charging this order; no financial entries were changed.
- A dated expense row with only the default `Так` flag is treated as an empty
  draft, so it does not suppress profit/margin. An incomplete expense with an
  actual category, description, order, note, or consumable still suppresses
  them. Live `Витрати!70` has the empty-draft shape that caused the reported
  unavailable values.
- Settings now has one compact integrity action and an adjacent copy button;
  the duplicate result panel is removed. The CRM token block is last. The test
  order report remains copyable without displaying raw JSON. The overview
  preorder cell no longer prints escaped NP/TTN markup. Finance card order now
  puts the ZenMarket account above data quality.
- Alerts expose `first_seen` from the first date this API version observes a
  given alert type/SKU. The date persists while details such as balance change
  and resets after the issue resolves. Past onset dates cannot be recovered.
- The overview secondary response now returns only the five SKU cards it uses,
  avoiding a ~178 kB response that was too large for the Apps Script cache.
  Opening the full product/stock views still requests the full catalogue.

## 3D stock audit (read-only)

The bounded live comparison covered 72 3D SKUs; 26 differed between the 3D
tracker `Наявність` and CRM `Склад`. The SKU-level snapshot is
`diagnostics/CRM-016_3dp_stock_audit_20260924.csv`.

The CRM stock formula counts received purchases minus CRM sales and write-offs;
it does not count `Друк-лог`. This is the common cause of false negative CRM
stock and automation alerts for manufactured goods. Examples: `BR-CHARM-100`
is 35 in 3D and -1 in CRM; `ACC-3D-PKM-110` is 2 and -1. `FIG-ONIX-500`
is currently 0 in the 3D tracker and -1 in CRM: one print is recorded on
2026-09-15 and one sale on 2026-09-22. The owner's stated second print is not
present under that SKU in the inspected print log. `FIG-ONIX-200` is 1 in 3D
and 0 in CRM. `BR-DITTO-400` also has a separate sales-sync discrepancy:
3D sale count 0 vs CRM sale count 1.

Correction after the owner's 2026-09-24 dashboard screenshots: the raw
`OP-JP-EB03-BST` CRM/automation balance is -5, but that figure includes 16
units reserved for open orders. The dashboard correctly shows **11 physically
on hand**, **16 reserved**, **5 short against those reservations**, and **8
incoming**. The earlier wording incorrectly called -5 a physical stock count
and linked the deficit to a missing warehouse status. `LOT-0219` is still
`Замовлено`; its status does not negate the 11 physically present units. The
alert reflects a negative available balance, not negative physical stock.
The owner also confirmed the second Onix print was never entered, so the 3D
tracker's physical count of `FIG-ONIX-500` is currently 0. A permanent 3D stock
repair needs a canonical
production-to-CRM stock projection and reconciliation of 3D vs CRM sales;
blindly changing stock balances would conceal missing print/sale records.

## Performance evidence and remaining work

Owner telemetry before this candidate: `sku_list` 21.6 s network / 18.1 s
server and 178 kB; `overview_secondary` ~10 s and 178 kB, cache state
`value_too_large`; `finance_report` 17.9–20.4 s network / 14.2–17.0 s
server; `overview_assets` 17.4 s network / 14.3 s server; `orders` ~10 s;
`integrity_check` 26.1 s. Browser render times were small where measured.
The slow path is Apps Script data reads/calculation and oversized responses.
The current overview payload fix removes its cache barrier. Further work should
profile per-sheet reads in `apiSkuList_`, `apiFinanceReport_`, and assets,
then use one shared projection for stock/cost data or bounded payloads. Runtime
improvement from this candidate is not yet measured.

## Verification and open gates

- Main CRM and Alerts scripts plus dashboard inline JavaScript parsed locally.
- Focused tests passed: purchase grouping and 10/25 boundaries; ZenMarket
  three-share statement and balance; preorder FIFO guard matrix; first-seen
  lifecycle; bulk alert mutation; finance draft and P&L contract.
- Owner-reported CRM integrity check was clean before this candidate. No
  post-candidate live integrity result or dashboard browser QA exists.
- The 3D stock source discrepancy remains unresolved. The owner confirmed
  the second Onix print was not saved; its current 3D physical stock is 0.
