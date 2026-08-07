# 3D-P live workbook schema audit — 2026-08-03

Author: Claude (owner-authorized architecture audit)
Source: direct read of the live Google Sheets workbook
`1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo`
(`3D-P_nomenclature-tracker_v6_20260731`, modified 2026-08-03T17:55Z) via the
owner's connected Google Drive integration. Read-only; nothing was written.

**Why this file exists.** Until now every 3D-P task — the API (`3D-P-008`), the
dashboard (`3D-P-006`/`3D-P-013`), Serhiy's server (`3D-P-007`), and the CRM
sync (`3D-P-010`) — was built against an *assumed* column layout. Nobody had
confirmed the live headers. That produced at least three separate incidents:
the false claim that model-link/РРЦ/buyout-price were writable (corrected by
`3D-P-008` Addendum #3), the update-only sync design that could never fire
(corrected by `3D-P-010` Addendum #2), and the `Продажі!T` match-key question.

**This file is now the canonical reference for the live 3D-P schema.** Any task
touching these tabs must check here first, and re-verify against the live sheet
if this file is older than the change being made.

## Confirmed live tab list

`Легенда`, `Номенклатура`, `Друк-лог`, `Продажі`, `Виплати`,
`Маркетингові_плюшки`, `Наявність`, `Аналітика`, `Налаштування`,
`Фурнітура_довідник`, `_Чернетки_партій`, `_Коригування_наявності`,
`_Аудит_API`.

## Номенклатура (A–P)

| Col | Header |
|---|---|
| A | SKU |
| B | Назва виробу |
| C | Франшиза |
| D | Тип |
| E | Трек |
| F | Статус |
| G | Час друку за од., год |
| H | Вага виробу за од., г |
| I | Вага котушки, г |
| J | Ціна котушки, грн |
| K | Собівартість Сергія (виробнича), грн *(formula)* |
| L | Дата оновлення |
| M | Примітки |
| N | Фурнітура (ланцюжок/карабін), грн/шт |
| O | API_статус_запису *(technical, Addendum #2)* |
| P | API_історія_змін *(technical, Addendum #2)* |

**There is no РРЦ column, no model-link column, and no buyout-price column.**
This confirms `3D-P-008` Addendum #3's finding from live evidence rather than
inference.

## Продажі (A–T)

| Col | Header |
|---|---|
| A | Дата |
| B | SKU |
| C | Назва |
| D | Кількість |
| E | Фактична ціна за од., грн (після знижки) |
| F | Собівартість Сергія за од., грн |
| G | Витрати BoosterShop за од., грн |
| H | % прибутку Сергію |
| I | Маржинальний прибуток за од., грн |
| J | Статус |
| K | Нараховано Сергію, грн |
| L | Дохід Booster Shop, грн |
| M | Канал |
| N | № замовлення |
| O | Примітки |
| P | Тип знижки |
| Q | Параметр знижки |
| R | Погоджено з Сергієм (Так/Ні) |
| S | Період (авто, РРРР-ММ) |
| T | CRM row number *(technical, 3D-P-010)* |

`T1` already contains the exact header `CRM row number`, so `setup3dp010()` ran
successfully against the live sheet. The 3D-P-010 schema gate is satisfied.

Note `E` is the *transaction* price after discount, not a product RRP. There is
no RRP anywhere in this tab either.

## Наявність (A–G)

`SKU | Назва | Надруковано всього, шт | Брак всього, шт | Продано на сайті, шт | Видано як плюшка, шт | Наявно зараз, шт`

`FIG-CHARM-001` currently reads `Наявно зараз = 3` (the known Addendum #2 smoke
artifact plus a later `тест` adjustment), with `Продано = 0`.

## Друк-лог (A–K)

`Дата | SKU | Надруковано, шт | Час друку факт, год | Брак, шт | Витрачено матеріалу, г (факт) | Собівартість партії, грн | Хто друкував | Примітки | API_статус_запису | API_історія_змін`

## Виплати (A–F)

`Період (РРРР-ММ) | Нараховано Сергію за період, грн | Термін перевірки Сергієм | Дата фактичної виплати | Статус | Примітки`

## Маркетингові_плюшки (A–H)

`Дата | SKU | Закуплено в Друга, шт | Ціна закупівлі за од., грн | Сума закупівлі, грн | Видано як бонус, шт | До замовлення № | Примітки`

## Налаштування

Three global constants, confirmed live: printer power `0,17` кВт, electricity
price `4,32` грн/кВт·год, amortisation `12` грн/год. Matches the locked cost
formula.

## Фурнітура_довідник

`Назва фурнітури | Ціна, грн/шт` — **currently empty (headers only).**

## Аналітика — the stale price-scenario block

Columns of the "Маржа-калькулятор по SKU (цінові сценарії…)" block:

`SKU | Назва | Собівартість Сергія, грн | Витрати BoosterShop, грн | Час друку, год | % прибутку Сергію | Ціна Консервативна | Ціна Середня | Ціна Оптимістична | Маржа BoosterShop Консерв, грн | Маржа BoosterShop Середня, грн | Маржа BoosterShop Оптим, грн | Нараховано Сергію (Середня), грн | Прибуток Сергію/год друку (Середня), грн`

Live values for `FIG-CHARM-001`: 50/62/75 грн scenarios → 25,00/31,00/37,50 грн
margins, «Нараховано Сергію (Середня)» 31,00 грн, «Прибуток Сергію/год друку
(Середня)» 300,39 грн.

Below it sits the market-reference research block (32 verified comparables,
Prom.ua + Etsy, updated 2026-07-29).

## Audit findings

1. **No canonical product price exists.** The only price concept in the whole
   workbook is the three speculative Аналітика scenarios. Every derived
   financial figure (three margin columns, «Нараховано Сергію (Середня)»,
   «Прибуток/год») descends from them. Owner decision 2026-08-03: replace with
   one **фактична РРЦ** per SKU plus a separate **ціна під викуп** (Track 2),
   and derive all finance from фактична РРЦ. This is `3D-P-015`.

2. **Cost is not versioned.** `Номенклатура!K` is a single live formula cell.
   `Продажі!F` exists per sale row but is currently derived, not frozen. Any
   future filament-price change silently rewrites the economics of past sales
   and of amounts already accrued to Serhiy. Owner decision 2026-08-03: freeze
   a numeric copy of cost (and RRP) into the sale row at creation time.

3. **Spool data is duplicated.** `Номенклатура!I`/`J` (spool weight/price) and
   `_Чернетки_партій` both store spool weight and price. Two sources of truth
   for one fact, with no defined precedence.

4. **`Продажі` write-whitelist role split looks wrong.** Per
   `3d-print/apps-script-3dp-api/Code.gs`, Serhiy holds `C, F, I, J, K, L, S` —
   i.e. product name and the computed financial columns — while the owner holds
   `A, B, D, E, G, H, M, N, O, P, Q, R`. Serhiy should not own the finance
   columns; the owner cannot currently write `Назва`. Needs an explicit owner
   decision, then correction.

5. **Sync failures are invisible.** `3D-P-010`'s hook is deliberately fail-open
   but records failures only through `Logger.log`, which surfaces nowhere the
   owner can see. This is why the 2026-08-03 `OC-FOP-0300` failure had to be
   diagnosed by inference from execution durations. Owner decision 2026-08-03:
   fix visibility **first**, before further architecture work. This is
   `3D-P-014`.

6. **Illustrative `ПРИКЛАД-001` rows are still live** in Номенклатура, Продажі,
   Виплати, Маркетингові_плюшки, Наявність and Аналітика, and they still feed
   totals (Наявність shows 2 units for the example SKU; Виплати shows a 165 грн
   accrual). The API filters them out of read actions, but the sheet's own
   formulas do not.

7. **`Фурнітура_довідник` is empty**, while `Номенклатура!N` expects a
   per-unit fixture price. The fixture half of `3D-P-010` has no source data
   yet.

8. **Roadmap ID gap:** `3D-P-009` is referenced in
   `handoffs/handoff_3D-P-010_crm-packaging-cost-pull_20260802.md` but has no
   entry in `ROADMAP_SOP.md`'s 3D-P ID table and no Notion page id recorded.
   Needs resolution before the next ID is issued.

## Open diagnosis — `OC-FOP-0300` did not sync (2026-08-03)

Observed: main CRM `doPost` ran 14.5s at 21:02:02 (V88 deployed); the 3D-P API
execution log for the same window shows `doGet` entries only, **no `doPost`**.
The corrected hook must POST `3dp_append_row`; the superseded update-only hook
performs GETs then exits silently when no row matches — which is exactly the
observed pattern.

Leading hypothesis: the live main-CRM source still contains the **superseded**
helper block, i.e. the corrected patch was never re-pasted after Codex rewrote
`patches/3D-P-010_crm-packaging-pull_20260802.js`.

Owner check: search the live main-CRM script for `sync3dpSales_`. Absent ⇒ old
block confirmed, replace the whole helper block from the patch file.

**RESOLVED 2026-08-04:** owner confirmed `sync3dpSales_` was absent — the live
CRM still held the superseded update-only block. Replaced, deployed as V89.

## Finding 9 — THIRD sale-write path exists and is unhooked (2026-08-04)

After V89, order `OC-FOP-0300` was updated again and still did not sync. The CRM
execution log for that window shows **no `doPost` at all**; instead
`updateSaleStatus` ran as a `Меню` (menu) execution for 42.1s.

Confirmed by reading the V86 source: `updateSaleStatus()` (CSV line 548) is a
**separate, menu-driven function** bound to the in-Sheet `Оновити_продаж` form.
It resolves the order, reads `Продажі!A:AC`, and writes payment status (col 23),
order status (24), TTN (26), packaging cost + type (16, 29), shop delivery (20)
and note (27) **directly**, then calls `invalidateDoGetCache_()` and
`clearSaleUpdateForm()`. It never calls `apiUpdateSale_`. `updatePaymentStatus()`
is a thin alias for it.

So there are three ways a sale row gets written in the main CRM:

| Path | Entry point | Used by | Hooked by 3D-P-010? |
|---|---|---|---|
| Web App create | `doPost` → `apiAddSale_` | OpenCart order sync, dashboard | yes |
| Web App update | `doPost` → `apiUpdateSale_` | dashboard "Оновити продаж" | yes |
| **In-Sheet menu update** | `updateSaleStatus()` / `updatePaymentStatus()` | **owner working directly in the Sheet** | **no** |

This is the owner's actual habitual path, and it was never considered when the
3D-P-010 hook was designed. The hook must cover `updateSaleStatus()` too, with
the same fail-open contract. Note it runs under a *user* authorisation context
(menu/UI), not the Web App context, so `UrlFetchApp` quota/permission behaviour
must be re-verified there rather than assumed identical.

Root cause class: same as findings 1–5 — the design was anchored on an assumed
entry point without confirming how the owner actually works.

## Finding 10 — main-CRM packaging dropdown is contaminated (2026-08-04)

The `Оновити_продаж` form's `Паковання` dropdown lists the four packaging types,
`Інше`, **and** at least one product name
(`One Piece Magazine Vol. 20 без промо-картки`). `restoreSaleUpdateFormulas_`
sets `B12` to `INDEX('Продажі'!$AC$3:$AC$511; MATCH(...))`, which is the
value-restore formula, not the list; the contamination is therefore in the
cell's **data-validation source range**, which is pointing at a range that
overlaps product data rather than a clean packaging-type list.

Separately, the owner reports the 3D-P SKU (`FIG-CHARM-001`) and a new
mystery-box SKU trip `Недійсне значення` validation warnings in the main CRM
`Продажі` sheet, because those SKUs are not in the SKU validation list. Values
still land (warning-only validation), so this does not block the sync, but it
will recur on every new SKU.

Both are main-CRM data-validation configuration issues, not 3D-P script issues.
They need their own CRM task — do not fold them into 3D-P-014/015.
