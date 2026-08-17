# CRM row-capacity auto-growth report

Date: 2026-08-16

## Outcome

Prepared a local Apps Script change that keeps every append-only CRM writer
ahead of the sheet edge. `setupCrmRowCapacityTrigger()` only installs one daily
trigger near 04:00 in the script time zone; it does not scan or rewrite sheet
formulas interactively. A writer has a synchronous fallback only when that
trigger has not refilled the target sheet yet, so normal order-save latency
does not wait for row insertion.

## Capacity policy

| Target | Refill | Refill threshold |
| --- | ---: | ---: |
| `Продажі` | 100 rows | fewer than 20 free rows |
| `Закупки` | 50 rows | fewer than 20 free rows |
| `Списання`, `Витрати`, `Розхідники` | 10 rows each | fewer than 10 free rows |
| `Використання_компонентів`, `Використання_фурнітури`, `3D_облік_замовлень`, `Новини_кандидати` | 10 rows each | fewer than 10 free rows |
| `Товари` + `РРЦ` + `Склад` | 10 shared catalog rows | fewer than 10 free catalog rows |

`Товари`, `РРЦ`, and `Склад` are handled together so the catalog row numbers
remain aligned. The retained `_Журнал_3DP_синхронізації` rotation is deliberately
excluded: it has its own bounded retention policy rather than append-only data.

## Correctness controls

- New rows copy formatting, data validation, row height, and formulas from the
  final populated data row; user-entered values are never copied.
- Capacity expansion refreshes known fixed formula limits in the CRM tables,
  including `Продажі`, `Закупки`, `Списання`, `Витрати`, `Розхідники`,
  `Товари`, `РРЦ`, and `Склад`.
- A real expansion runs the bounded CRM integrity check before and after. A
  newly introduced integrity problem stops the caller and is surfaced as an
  error; pre-existing findings are not silently relabelled as new.
- All sale, purchase, writeoff, expense, consumable, component-ledger,
  fixture-ledger, 3D-accounting, news-candidate, OpenCart-import, and catalog
  append paths use the shared capacity allocator.

## Verification

- `new Function(Code.gs)` syntax parse: passed.
- New focused `row-capacity.test.mjs`: passed; proves the 100-row sales refill,
  10-row aligned catalog refill, formula copy, and formula-range expansion.
- Full local CRM Apps Script suite: 14 of 14 test files passed.
- `git diff --check` for the scoped tracked source/tests: passed.

## Boundaries and live gate

- This is a local source-mirror change only. No Google Sheet rows, trigger, or
  published Web App were changed in this session.
- Setup replaces an earlier unlabelled five-minute capacity trigger with the
  daily 04:00 trigger once, then leaves later repeat runs idempotent.
- The source mirror has unrelated working-tree changes. Merge the bounded CRM
  capacity helper and its call sites into the owner-verified live source; do
  not replace live `Code.gs` wholesale.
- Before paste/publication, run the dashboard integrity check and retain its
  bounded result. After publication, run `setupCrmRowCapacityTrigger()` once
  from the Apps Script editor, authorize it, then run the integrity check again
  and perform a normal `OC-FOP-0321` retry without changing the form.
