# BUG-003 — Phantom stock after order-status change — diagnostic (round 1)

Date: 2026-10-02 · Author: Claude (chat) · Notion: `3ed6bf20-bdb4-81b9-9428-c72fb23a7aba`
Status of this document: evidence log, not a fix. No production change was made.

## Symptom

`PKM-JP-OUTL-BST` (product_id 73, `subtract = 1`). Owner sets quantity 0 via admin
(Catalog → Products → Edit → Data → Quantity → Save). Stock later reappears without
a real purchase. Observed 2026-09-28, 2026-10-01, 2026-10-02. With
`config_stock_checkout = 0` the phantom units are purchasable (order #402, 28 pcs,
refunded).

## Inputs

- `boosters_ocart49.sql.gz` (repo root, dump of 2026-09-12): status lists, events,
  order history up to that date.
- Owner phpMyAdmin reads, 2026-10-02 (screenshots in chat): product 73 row, order
  history for product 73 since 2026-09-13, `ocp5_order` ids ≥ 374.
- Owner admin screenshots: history of orders #374 and #402.

## Configuration as of the 2026-09-12 dump (not yet re-read live)

- `config_processing_status` = 1, 7, 3, 13, 12
- `config_complete_status` = 5, 12, 10, 14
- Stock-holding union: 1, 3, 5, 7, 10, 12, 13, 14. Outside: 0, 15, 17–22, 23.
- Status names: 1 В обробці · 3 Доставляється · 5 Отримано · 7 Доставлено — чекає
  отримувача · 10 Помилка · 12 Повернено · 13 Повернення коштів · 14 Протерміновано ·
  15 Чернетка (системний) · 17–22 Розстрочка — … · 23 Передзамовлення.
- Events on order history: `telegram_notification` on
  `catalog/model/checkout/order/addHistory/after`. No event writes product quantity
  in the dump.

## Live evidence (product 73)

| Order | Qty | History (status, time) |
|---|---|---|
| 396 | 7 | 1 @09-24 20:40 · 1 @09-24 20:48 · 3 @09-25 18:08 · **5 @10-01 11:46** |
| 399 | 28 | 1 @09-25 17:30 · 3 @09-25 18:01 · **5 @09-27 21:29** |
| 402 | 28 | 1 @09-28 01:03 · 12 @09-28 07:52 (owner refund) |
| 408 | 7 | 1 @10-01 12:17 · **23 @10-01 13:46** |
| 374 | 7 | 1 @09-09 · 3 @09-09 · 5 @09-13 (clean; earlier hypothesis withdrawn) |

Product 73 now: quantity 0, stock_status_id 5, minimum 3, date_available
0000-00-00, date_modified 2026-10-02 14:16:33 (owner zeroing).
Order ids 374–401: no gaps except 386, 393, 397 — not yet checked whether those were
deleted orders containing product 73 (deleted orders restock in OpenCart).
Note: `ocp5_order.date_modified` is not bumped by status changes on this store
(#374 still shows 09-09), so it cannot be used as a "last touched" filter.

## Reading

1. Both phantom amounts equal the quantity of an order that had just moved
   **3 → 5 (Доставляється → Отримано)**, and the next customer order was for exactly
   that amount: #399 (28) → #402 (28) about 3.5 h later; #396 (7) → #408 (7) 31 min
   later. 2 of 2.
2. With the 09-12 lists, 3 → 5 stays inside the holding union and stock OpenCart
   would not restock. So either (a) the live lists changed after 09-12 and 5 is no
   longer in `config_complete_status`, or (b) the status-change path used for 5 voids
   the order (restock) without re-deducting.
3. #408 1 → 23 (Передзамовлення) restocks +7 by design, because 23 sits outside the
   holding lists. This explains a 0 → 7 jump on its own, but it is expected behaviour
   given the lists. Whether 23 should hold stock is an owner decision for phase 2.
4. If (1) holds, the defect is global: every product whose order reaches «Отримано»
   gets its units back. Not yet verified on another product.
5. `date_available` is unrelated: OpenCart never changes quantity based on it.

## Open checks (next round)

1. Live read: `config_processing_status`, `config_complete_status`, and
   `ocp5_event` rows whose trigger contains `order`.
2. Who/what sets status 5: owner manually in admin, or the Nova Poshta module.
3. Code from the 2026-09-24 cPanel backup: `catalog/model/checkout/order.php`
   (`addHistory`, `editOrder`), `catalog/controller/api/**`,
   `admin/controller/sale/order.php`, `extension/**/*.php` — trace every path that
   writes `product.quantity` or calls `addHistory`.
4. Gaps 386, 393, 397: deleted orders or never created.
5. Confirm globality on one other product with a recent 3 → 5 transition.

## Phase 2 (owner-approved 2026-10-02, after root cause is fixed)

- Add status «Скасовано» outside both holding lists.
- Remove 10 «Помилка» and 14 «Протерміновано» from `config_complete_status`.
- Before changing: audit every consumer of the complete/processing lists
  (First15 coupon logic, reports, downloads, NCRM order-sync, CRM). Existing orders
  are not retro-restocked; order #238 (10 × product 73 in «Помилка») needs a manual
  owner decision.
