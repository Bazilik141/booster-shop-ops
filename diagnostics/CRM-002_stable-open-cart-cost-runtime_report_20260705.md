# Codex Report — CRM-002: stable OpenCart FIFO runtime

Date: 2026-07-05

## Scope

Diagnosed recurring zero-cost imports using `OC-FOP-0206`, corrected its two
live sale rows, and prepared a full current `Code.gs` with an observable
runtime version plus a bounded pending-order backfill function.

## Root cause

The Google Sheet source mirror already contained the correct rules:

- `Нове` / `В обробці` count as committed inventory;
- `На складі UA` is accepted everywhere `На складі` is accepted;
- `upsertOpenCartOrder_()` calls `fixSaleCostForRow_()` for every imported row.

Despite that, `OC-FOP-0206` arrived with:

`Метод собівартості = Відкладено`

and audit:

`Не зафіксовано: оплата=Не оплачено, статус=Нове`

This proves the active bound Web App deployment still executes an older script
version. Updating the `Apps_Script_код` sheet or saving `Code.gs` alone does not
replace the active deployment version.

Live authenticated runtime check before deploy:

`{"ok":false,"error":"unknown action: runtime_version"}`

This independently confirms that the active Web App does not contain the
current source-mirror code.

## Files touched

```text
patches/CRM-002_stable-open-cart-cost-runtime_20260705.js
diagnostics/CRM-002_stable-open-cart-cost-runtime_report_20260705.md
Google Sheet: Продажі!L192:M193, AD192:AF193
Google Sheet: Apps_Script_код!A720, A3448:A3465
```

## Implemented

1. Full current script is based on
   `MKT-TG-006_openai-url-draft_20260704.js`, so the latest Telegram/news work
   is preserved.
2. Merged all CRM status fixes:
   - `На складі UA` in FIFO, lot auto-status, and current-cost maps;
   - `Нове` / `В обробці` reserve stock and receive cost immediately.
3. Added authenticated GET action:

   `action=runtime_version`

   Expected response:

   `{"ok":true,"version":"CRM-002-20260705-r1","new_orders_reserve_stock":true}`

4. Added manual function `backfillPendingActiveOrderCosts()` to repair active
   rows still marked `Відкладено`, then refresh lot statuses and API caches.
5. Corrected `OC-FOP-0206`:
   - Mystery Box: 450.00 грн PRRO / 453.26 грн management cost;
   - 5× Hot Wind Arena KR: 71.52 грн / 75.81 грн per booster;
   - total net profit: 224.87 грн.

## Verification

```text
OC-FOP-0206 row 192 method: Fallback + авторозхідники
OC-FOP-0206 row 193 method: FIFO
Row 192 net profit: 181.05 грн
Row 193 net profit: 43.82 грн
node --check: exit_code=0
```

## Deployment gate

The fix is not considered stable until the active Web App returns runtime
version `CRM-002-20260705-r1`.

Owner steps:

1. Replace bound Apps Script `Code.gs` with the patch file.
2. Save.
3. Deploy → Manage deployments → Edit → Version → New version → Deploy.
4. Run `backfillPendingActiveOrderCosts()` once.
5. Call authenticated GET `action=runtime_version`.

Merely saving the editor or updating `Apps_Script_код` is insufficient.

## Rollback

- Redeploy the previous Apps Script version.
- Restore `OC-FOP-0206` rows 192–193 cost cells to blank and audit method to
  `Відкладено` if a rollback is required.
- No order status, payment status, customer data, or purchase rows changed.

## Post-deploy QA checklist

- [ ] `action=runtime_version` returns `CRM-002-20260705-r1`.
- [ ] `backfillPendingActiveOrderCosts()` completes without error.
- [ ] One controlled `Нове / Не оплачено` order receives FIFO/Fallback cost
  immediately.
- [ ] Cancelling that test order restores formula stock.
- [ ] `summary`, `orders`, `stock_alerts`, and `sku_list` still respond.
