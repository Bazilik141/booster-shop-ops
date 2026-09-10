# Alerts Apps Script — repository mirror state

## CRM-011 managed-alert API — owner-reported live; granular hotfix NOT PUBLISHED (2026-09-09)

`Code.gs` is based on the owner-pasted Telegram and weekly-summary source. The
supplied Web App URL returned `Функцію сценарію doGet не знайдено` on
2026-09-08. On 2026-09-09 the owner reported that the token-gated Alerts API is
now working; the exact deployed version and live source bytes were not supplied.

The deployed candidate adds token-gated `doGet`/`doPost`, stable alert IDs, the private
`_Керування_Алертами` status sheet, active/dismissed management, and filters
dismissed alerts from both daily Telegram alerts and the weekly owner summary.
The owner then reported that `Мінусовий залишок (7)` rendered as one dismissible
group with no SKU-level explanation. The new local hotfix expands each negative
stock SKU into its own alert, adds per-SKU `Докупити`, `Пильнувати`, and
`Не просувати` queue items, and expands other simple formula-backed `COUNTIF`
quality checks per SKU when their source is discoverable. Status lookup and
Telegram/weekly filtering use the same granular candidate set. Reads remain
bounded: quality 500x20, product master 2000x20, and three fixed six-row queue
ranges. This granular hotfix is not yet published.

No token is stored in the mirror. `BOOSTER_ALERTS_TOKEN` remains owner-held in
Script Properties. No live source export or source-byte identity proof exists.
