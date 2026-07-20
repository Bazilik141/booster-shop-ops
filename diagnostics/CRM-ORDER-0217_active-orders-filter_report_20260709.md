# Codex Report — CRM-ORDER-0217: active orders filter

Date: 2026-07-09

## Scope
Diagnosed why OpenCart order `OC-FOP-0217` looked missing from CRM active orders and applied a narrow Apps Script source-copy fix.

## Finding
`OC-FOP-0217` is already present in live CRM `Продажі!200`: OpenCart, 2026-07-08, `OP-JP-OP14-BST`, qty 6, 190 грн, `Оплачено / В обробці`, cost fixed as `FIFO + авторозхідники`.

The issue was the `crmOrderMatchesStatus_()` active filter: it returned active only for `Відправлено` or `Не оплачено`, so paid card orders still in `В обробці` were hidden from the active orders API/dashboard.

## Files / ranges touched
- Google Sheet `Apps_Script_код!A2068`
- `patches/CRM-ORDER-0217_active-orders-filter_20260709.js`
- `diagnostics/CRM-ORDER-0217_active-orders-filter_report_20260709.md`

## Change
Active orders now include orders with status `Нове`, `В обробці`, or `Відправлено`, plus unpaid non-terminal orders. Terminal statuses remain excluded: `Скасовано`, `Отримано`, `Повернення`.

## Verification
- Live readback found exactly one `OC-FOP-0217` row in `Продажі!200`.
- `Apps_Script_код!A2068` readback shows the updated active filter.
- Local static check: `node --check patches/CRM-ORDER-0217_active-orders-filter_20260709.js`.

## Deployment boundary
`Apps_Script_код` is a source-copy mirror. To make the dashboard/API use this behavior, copy/deploy `patches/CRM-ORDER-0217_active-orders-filter_20260709.js` into the bound Apps Script Web App.

## Rollback
Restore the previous active filter line in `Apps_Script_код!A2068` and redeploy the prior Apps Script version:

```js
return (orderStatus === 'Відправлено' || paymentStatus === 'Не оплачено') && !terminal;
```

## Post-deploy QA checklist
- [ ] Open active orders in the dashboard/API and confirm `OC-FOP-0217` appears.
- [ ] Confirm completed `Отримано` orders still do not appear in active orders.
- [ ] Confirm `orders&status=unpaid` still returns only unpaid non-terminal orders.
