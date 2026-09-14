# CRM-013 WP-A — BR/FIG catalogue-gap diagnostic

Date: 2026-09-12
Scope: read-only catalogue coverage report; no rename, RRP, stock, or master-row write.

## Established source cause

`crm0123dpNameDivergenceReportForOwner()` creates its SKU set from `Товари` and
the 3D-P `3dp_skus` response. It reads `Майстер_Товарів` only after that set is
formed. In contrast, `apiSkuList_()` builds the dashboard catalogue from active
`Майстер_Товарів` rows. A `BR-*` or `FIG-*` SKU present only in the master can
therefore be visible in the dashboard but absent from the CRM-012 divergence
report.

The dashboard's `20%*` is also explained by code, not a stored margin: with no
30/60-day sales, `stockMarginInfo()` estimates a margin as
`max_buy_price × 1.25`, which is 20%, and appends `*` to mark the estimate.

## Temporary report runner

The temporary CRM-013 execution artifact was separate from `Code.gs`, had no
write calls, and read only headers plus needed catalogue and SKU columns before
calling `3dp_skus` with `include_archived=true`.

2026-09-13 correction: the first live run stopped before reading any data
because `Товари` uses `Сет` rather than `Сет / група`. The runner now accepts
`Сет / група`, `Сет`, `Набір`, or `Set` while preserving `Сет / група` as the
JSON field name. No data was written or exported by the failed run.

The result covers every `^(?:BR|FIG)-` SKU found in any of seven sources:

| Source | Evidence returned |
|---|---|
| `Товари` | row, short name, set, format, active |
| `Майстер_Товарів` | row, name, active |
| `РРЦ` | row, name, manual RRP |
| `Склад` | row, stock value |
| `Закупки` | presence and row |
| `Продажі` | presence and row |
| 3D-P `3dp_skus` | name and API status |

Each SKU receives exactly one bucket, in this order: `has movement without
registration`, `orphan-in-master`, `fully registered`, or `partially
registered`. The JSON has `bucket_total` so the owner can verify that the four
bucket counts equal `total`; it includes the full seven-source trace for
`BR-CHARM-100`.

## Owner-run result — 2026-09-13 11:14 Kyiv

The read-only run completed successfully. Its bounded summary is conclusive:

| Metric | Result |
|---|---:|
| BR/FIG SKU found in any source | 45 |
| Fully registered | 45 |
| Orphan in master | 0 |
| Partially registered | 0 |
| Movement without registration | 0 |

`BR-CHARM-100` is fully registered: `Товари!70`,
`Майстер_Товарів!69`, `РРЦ!70` at 30 UAH, and `Склад!70` at 0. It has no
purchase or sale movement, and its active 3D-P name matches the CRM/master
name. The `20%*` dashboard value is therefore an explicitly marked estimate
from the no-sales UI path, not a catalogue or accounting mismatch.

The Apps Script log truncated the per-SKU tail only after the complete summary
and the requested `BR-CHARM-100` trace. There is no evidence for the proposed
CRM-012 source-set change, so it is not implemented.

The local temporary runner was removed after this verified run. The owner must
remove the corresponding temporary `CRM-013` file from the live Apps Script
project.

## Verification to date

- Local temporary-runner syntax: passed before owner execution.
- Static test confirmed no `setValue`, `setValues`, `appendRow`, `deleteRow`,
  `clearContent`, or cache invalidation in the runner.
- Live result: completed read-only; no Sheet structure or data change.
