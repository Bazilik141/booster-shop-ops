# Codex Report — CRM-TG-ORDERS: Telegram shipment queue and stock-alert trace

Date: 2026-09-08

## Scope

- Change only the Telegram `Активні замовлення` command.
- Trace the `Мінусовий залишок (7)` value in the attached Telegram screenshot from the verified CRM V166 source.
- No Google Sheet, Apps Script deployment, Telegram message, or status record was changed.

## Files touched

```text
crm/apps-script/Code.gs
crm/apps-script/tests/telegram-news-input-state.test.mjs
```

## Change

`tgCommandOrders_()` now reads a bounded active-order set, then keeps only an order that:

1. has a non-empty TTN; and
2. does not have status `Передзамовлення`.

The limit of 20 buttons is retained after filtering. The public CRM `orders` API and dashboard active-order logic are unchanged.

## Source evidence for the alert

The attached `Версія 166, 6 вер. 2026 р., 2047.csv` matches the pre-change CRM mirror after BOM and line-ending normalization.

- The owner-provided Automation Apps Script contains the exact alert template and sender: `runTroubleAlerts_()` calls `collectDataQualityIssues_()`, then `sendTelegramMessage_()`.
- The scheduled handler is `dailyTroubleAlerts`; when `installDailyTroubleAlertTrigger()` has been run, it executes daily near 09:00 Kyiv.
- `collectDataQualityIssues_()` reads the active Automation spreadsheet's entire `Якість_Даних` sheet, locates the `Статус`, `К-сть`, `Деталі`, and `Рекомендована дія` columns by header, and sends each row whose status is a problem/warning.
- Therefore the alert's `Мінусовий залишок (7)` is the visible `К-сть` value of that Automation-sheet row. It is not calculated by `/orders` or by Telegram.
- The current script sends one alert per changed issue set and records delivery in `Лог_Алертів`; an unchanged repeat is intentionally skipped.

Conclusion from the script alone: the alert comes from the Automation table's Apps Script, but the script is only a messenger; the current rows are needed to identify affected SKU and the responsible stock movements.

## Live Automation-sheet finding (read-only, 2026-09-08)

The current `Якість_Даних` row is exact and formula-backed:

- `C8 = COUNTIF('Майстер_Товарів'!Q2:Q; "*мінусовий_залишок*")` → **7**;
- `B8 = IF(C8=0; "ОК"; "Перевірити")` → **Перевірити**;
- `Майстер_Товарів!Q` assigns `мінусовий_залишок` whenever its `Залишок` value (`L`) is below zero;
- `L` is the CRM stock view's `Залишок` column, read from `Source_CRM_Stock`.

The seven matched items are below. `Очікується після резерву` is the live incoming quantity less preorder reservations.

| SKU | CRM balance | Inbound | Reserve | After reserve | Evidence-based interpretation |
|---|---:|---:|---:|---:|---|
| `OP-JP-OP07-BST` | -1 | 0 | 1 | -1 | 24 purchased, 18 sold, 6 written off; one reserve has no inbound cover. |
| `OP-JP-EB03-BST` | -11 | 12 | 14 | -2 | The alert's current-free-stock metric is -11, but 3 physical packs plus 12 inbound minus 14 reserved gives 1 planned available pack. |
| `YGO-JP-BETB-BST` | -1 | 60 | 1 | 59 | Reservation before arrival; its inbound stock covers it. |
| `YGO-JP-QCAC-BBX` | -1 | 1 | 1 | 0 | Reservation before arrival; inbound exactly covers it. |
| `OP-JP-OP17-BST` | -1 | 6 | 1 | 5 | Reservation before arrival; its inbound stock covers it. |
| `OP-JP-OP13-BST` | -1 | 27 | 1 | 26 | Reservation before arrival; its inbound stock covers it. |
| `ACC-3D-PKM-110` | -1 | 0 | 0 | 0 | One recorded sale with no purchase/production stock in this CRM stock view. |

So the alert is **seven flagged SKU, not minus seven total units**; their raw negative balances sum to -17. Four rows are covered by expected inbound stock. `OP-JP-EB03-BST` is also covered when current physical stock is included: `3 + 12 - 14 = 1`. `ACC-3D-PKM-110` is the only row where the current view shows a sale without recorded incoming stock. This identifies the affected records; it does not yet identify the exact individual sale, write-off, or reservation row to correct.

### EB-03 calculation check

The two preorder rows are present and exact: `OC-FOP-0323` for 4 packs and `OC-FOP-0335` for 10 packs. The completed historical sales total 9 packs; write-offs total 15; purchases total 27. Hence physical stock is `27 - 9 - 15 = 3`.

The current source surfaces two separate values:

- `Залишок = -11`: current physical 3 minus 14 preorder reserves;
- `Очікується після резерву = -2`: incoming 12 minus the same 14 reserves.

Neither value is a total fulfilment forecast. The correct forecast availability is `physical 3 + incoming 12 - reserved 14 = 1`. Therefore EB-03 should not be treated as a stock-shortage alert if the alert's business meaning is “will every confirmed preorder be covered after known incoming stock arrives.”

## Local validation

```text
node crm/apps-script/tests/telegram-news-input-state.test.mjs
Telegram news input state tests passed

git diff --check
(no output)
```

The test proves that a preorder with a TTN and a non-preorder without a TTN are hidden, while non-preorder orders with TTNs remain visible.

## Publication and live QA gate

The owner reported deployment as **CRM Auto V167**. This is owner-reported deployment evidence; it was not independently queried from the live Apps Script project.

After deployment:

1. Send `/orders` to the bot.
2. Confirm every shown order has a TTN.
3. Confirm no shown row has status `Передзамовлення`.
4. To identify the `-7`, inspect the `Мінусовий залишок` row in the Automation sheet's `Якість_Даних` tab, including its formula and linked SKU/warehouse source rows.

## Risk

Low. The change is Telegram-command-specific, read-only, and leaves CRM formulas, stock accounting, dashboard filtering, and API response contracts unchanged.
