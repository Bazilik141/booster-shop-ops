# 3D-P `Номенклатура` — live snapshot

Date: 2026-08-22 22:55 (Europe/Kyiv) · Read-only, owner-run in the bound Apps
Script editor · Purpose: settle the WP1c blank-status question and give the
executor a verified card baseline so nothing is overwritten blind.

## Verdict on the WP1c blank-status risk — CLEARED

`porozhniy_status_kilkist: 0`. Every row carries `API_статус_запису = Активний`.

The WP1c CRM change at line 7508 (`=== 'Архів'` → `!== 'Активний'`) therefore
excludes nothing that is currently syncing. No backfill is required and the
patch may deploy as delivered.

⚠ This clearance is a point-in-time read of **three** rows. It is not a
structural guarantee. Any row created outside the dashboard append path could
still land with a blank status, and on the 3D-P side blank is treated as active
(`nomenclatureStatusForRow3dp_` defaults to `activeStatus`) while the CRM side
now requires the literal. If nomenclature ever grows through a non-API path,
re-run this check before trusting the sync.

## Live column order (headers, A→S)

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
| K | Собівартість Сергія (виробнича), грн |
| L | Дата оновлення |
| M | Примітки |
| N | Фурнітура (ціна-довідка), грн/шт |
| O | API_статус_запису |
| P | API_історія_змін |
| Q | РРЦ фактична, грн |
| R | Ціна під викуп, грн |
| S | Посилання на модель |

This confirms the `Q`/`R`/`S` placement assumed by 3D-P-015 and used by WP1b.

## Current rows — do not overwrite without owner instruction

| Row | SKU | Q РРЦ фактична | R Ціна під викуп | K Собівартість | G Час друку, год |
|---|---|---|---|---|---|
| 2 | `ACC-3D-DITTO-410` | 100 | 70 | 46,63 | 1,35 |
| 3 | `FIG-LUFFY-500` | 75 | **999** | 29,00 | 0,8861111111 |
| 4 | `BR-CHARM-100` | 25 | 20 | 5,00 | 0,1319444444 |

All three carry a model URL and were created through the dashboard append path,
each with an `API_історія_змін` entry.

## Data findings worth the owner's attention

1. **`FIG-LUFFY-500` still holds `Ціна під викуп = 999`.** This is the deliberate
   test value recorded in `diagnostics/3D-P_owner-qa-checklist_013-015-024_20260809.md`,
   which warned against using that SKU for QA because of it. It is still live.
   After WP1b, Serhiy can see it, and it is the price the shop pays him for a
   Track-2 item — a real figure, not a placeholder, from the system's point of
   view. Correct it or confirm it is intentional.
2. **`Франшиза` is blank** for `FIG-LUFFY-500` and `BR-CHARM-100`, though both
   names carry the franchise in brackets. Not a defect for the sync, but it is an
   input for card content under `3D-P-CARDCONTENT`.
3. **`Трек` and `Статус` (columns E and F) are blank on every row.** Column F is
   the human status described in the Легенда; the API uses `O` instead. Nothing
   reads `F` today — flagged so it is not mistaken for the status column that
   WP1c changes.
4. Only three products exist. Any statement about nomenclature scale in older
   documents should be read against that.

## Method

Owner ran a read-only function reading `Номенклатура!A1:S<last>` by display
values and reporting blank-status rows plus a full card dump. No writes, no token
handled, no customer data touched.
