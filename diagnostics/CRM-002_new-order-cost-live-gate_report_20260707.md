# Codex Report — CRM-002: new-order cost gate still deferred live orders

Date: 2026-07-07

## Scope

Діагностика та точкове виправлення живого CRM-рядка, де нове замовлення вже резервувало склад, але не отримувало FIFO-собівартість.

## Root cause

Попередній фікс був внесений у `Apps_Script_код` source-copy і в формули `Склад`, але bound Apps Script/Web App не був live-deployed. Тому живий код при створенні нового рядка досі відкладав собівартість для `Не оплачено / В обробці`.

Додатково знайдено ще одне старе місце у source-copy:

```js
if (String(row[9] || '').trim() !== 'На складі') return;
```

Воно стосувалося статусу розхідників у `Витрати!J:J` і тепер приймає `На складі UA` та `На складі`.

## Files / ranges touched

- Google Sheet `Продажі!L194:M194`
- Google Sheet `Продажі!AD194:AF194`
- Google Sheet `Apps_Script_код!A1417`
- Patch file: `patches/CRM-002_order-cost-runtime-fix_20260707.js`

## Live row fixed

`Продажі!194`:

- order: `MAN-PHYS-0001`
- SKU: `PKM-JP-OUTL-BST`
- qty: `10`
- status: `Не оплачено / В обробці`
- source lot: `LOT-0058`, status `На складі UA`

Applied values:

- PRRO unit cost: `48.62`
- management unit cost: `51.66`
- method: `FIFO + авторозхідники`
- audit: `before=208; LOT-0058: 10 x 48.62/51.54; auto consumables: Стікер лого+QR=1.17`

## Verification

- `Продажі!L194:M194` now displays `48,62 грн / 51,66 грн`.
- `Продажі!N194:O194` recalculated to `486,20 грн / 516,60 грн`.
- `Продажі!U194:V194` recalculated to `263,80 грн / 194,80 грн`.
- `Склад!F23` already includes `В обробці` and shows `212` sold/reserved.
- `Склад!H23` shows `45` remaining.
- `Apps_Script_код!A1468` already includes `Нове` and `В обробці`.
- `Apps_Script_код!A1510` already includes `На складі UA`.
- `Apps_Script_код!A1417` now accepts `На складі UA` and `На складі`.

## Deployment boundary

The live sheet row is corrected now. Future new orders will still repeat the deferred-cost behavior until the bound Apps Script is replaced/deployed with `patches/CRM-002_order-cost-runtime-fix_20260707.js`.

## Rollback

- Restore `Продажі!L194:M194` to blank and `Продажі!AD194:AF194` to the prior deferred values if the order must not reserve cost.
- Restore `Apps_Script_код!A1417` to the old strict `На складі` check if needed.

## Post-deploy QA checklist

- Deploy `patches/CRM-002_order-cost-runtime-fix_20260707.js` to the bound Apps Script/Web App.
- Create one controlled `Не оплачено / В обробці` order.
- Confirm the new row immediately gets `FIFO` or `FIFO + авторозхідники`.
- Confirm the audit references the active `На складі UA` lot instead of `Відкладено`.
