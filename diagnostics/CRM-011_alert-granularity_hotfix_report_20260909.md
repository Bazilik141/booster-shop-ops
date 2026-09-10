# CRM-011 — granular managed-alert hotfix report

Date: 2026-09-09

## Outcome

The local Alerts Apps Script and dashboard now manage concrete alert items
instead of treating `Мінусовий залишок (7)` as one dismissible group. The owner
reported the previous token-gated API live and working; this hotfix is local and
requires a new owner publication before it can be live-tested.

## Root cause

`collectDataQualityIssues_()` treated each row of `Якість_Даних` as one alert.
That sheet is a summary: its negative-stock row contains count `7`, generic
detail text, and no per-item identity. Consequently the alert ID represented the
whole summary signature, the dashboard had no SKU to show, and dismissing it
suppressed all seven items together.

## Change

- `Мінусовий залишок` is resolved against `Майстер_Товарів`. Every matching SKU
  gets its own ID, balance/reason, action, and active/dismissed state.
- Simple formula-backed quality checks using `COUNTIF` are expanded against the
  referenced source column when that source contains a discoverable SKU column.
  Unsupported aggregate formulas remain visible as honest summary checks rather
  than being discarded or fabricated.
- Action queues are included per SKU from the existing bounded
  `Черга_Складу` ranges: `Докупити`, `Пильнувати`, and `Не просувати`.
- Daily Telegram alerts, weekly summaries, API reads, and status mutations all
  use the same granular candidate set. A changed balance/queue recommendation
  changes the signature and reactivates the item.
- The dashboard table now shows `Тип`, `SKU / позиція`, `Чому це алерт`, and
  `Що зробити`, with one status button per row.

## Read limits

- `Якість_Даних`: at most 500 rows x 20 columns.
- `Майстер_Товарів`: at most 2,000 rows x 20 columns.
- `Черга_Складу`: three fixed 6-row x 7-column ranges.
- API result: at most 200 managed items.

## Verification

```text
Alerts Apps Script parse: pass
crm-011-followup-data-and-alerts.test.mjs: 4/4 pass
crm-011-r2-pass-a.test.mjs: 6/6 pass
git diff --check: pass
```

The focused fixture proves that an aggregate negative-stock count of two becomes
two independent SKU alerts and that the three action queues add three more
independent items, all with unique IDs.

## Owner publication and QA

1. Replace the separate Alerts Apps Script `Code.gs` with
   `crm/alerts-apps-script/Code.gs`. Keep the existing
   `BOOSTER_ALERTS_TOKEN`; do not send it in chat.
2. Deploy a new version of the existing Alerts Web App.
3. In the dashboard, press Ctrl+F5 and open `Увага` → `Оновити`.
4. Confirm the seven negative-stock SKUs are seven rows with individual reasons.
5. Mark one row `Неактуально`; confirm only that row moves to the dismissed
   list. Run the forced Telegram test only if desired and confirm that exact row
   is absent while the other active rows remain.

Stop and send the visible rows if the API still returns a grouped count, an
incorrect SKU, or a generic item that should have a concrete source. Do not
dismiss the whole group as a workaround.
