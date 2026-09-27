# CRM-016: BR-DITTO-400 ledger audit

Date: 2026-09-27

## Scope and result

Read-only, bounded audit of the live 3D-P spreadsheet and current local CRM / 3D-P source mirrors. No Sheet, API, deployment, or stock value was changed.

The unit is accounted for exactly once in 3D-P, but as a marketing gift instead of the CRM sale `OC-FOP-0382`. CRM therefore sees its fulfilled sale without a corresponding 3D sale row and withholds the physical-stock figure. The reported dash / sale-sync alert is an accounting classification mismatch, not evidence that another physical unit is missing.

## Live evidence

Spreadsheet: `3D-P_nomenclature-tracker_v6_20260731` (bounded Google Sheets reads, 2026-09-27).

| Location | Observed value |
| --- | --- |
| `Наявність!A17:G17` | `BR-DITTO-400`; printed 1; defect 0; sold on site 0; bonus 1; available 0 |
| `Друк-лог!A11:E11` | One print on 2026-09-15; no defect |
| `Продажі!B1:B996` | Five populated SKU cells including header; no `BR-DITTO-400` sale |
| `_Партії_FIFO_3DP!F11:I11,S11:T11` | One good unit; one allocated; zero available |
| `_Розподіл_FIFO_3DP!A12:P12` | Committed consume of one unit, FIFO cost UAH 16.6056; `source_type=marketing_gift`; `source_ref=CRM-015/marketing:CRM015-MUIC25U2-59B6QQWB`; projection row 8; no CRM row link |
| `Маркетингові_плюшки!A8:I8` | Same SKU and operation marker; one unit recorded as purchased from Serhiy for UAH 30 and one issued as bonus; no order number |
| `_Аудит_API!A317:H317` | `FIFO_MARKETING_WRITEOFF_COMMITTED` at 2026-09-26 14:56:24; target allocation row 12; same operation ID |

Prior bounded CRM evidence in `diagnostics/CRM-016_global_inventory_fix_candidate_report_20260925.md`: `Продажі!387` has the completed customer sale `OC-FOP-0382`, quantity 1; `Витрати!74` records the separate UAH 30 marketing expense; CRM `Склад!H113=-1` derives from its ordinary sales/purchases formula and does not include 3D prints. The owner-supplied V192 snapshot reported `3dp_sale_sync_missing` and `3dp_physical_unverified` for this SKU.

## Source contract mismatch

`crm/apps-script/Code.gs` sends `3dp_marketing_writeoff` with the operation ID above. The local `3d-print/apps-script-3dp-api/CatalogFifo.gs` implementation writes a `Продажі` row and a FIFO allocation with `source_type=marketing_writeoff`; its reversal routine supports that source type. The live transaction instead wrote `Маркетингові_плюшки` and `source_type=marketing_gift`, even though its API audit label is `FIFO_MARKETING_WRITEOFF_COMMITTED`.

The 3D-P `SOURCE_STATE.md` records owner-reported V36 but explicitly says the bound source was not independently exported or byte-verified. The current bound `CatalogFifo.gs` behavior must be treated as different from the local mirror for this action. A replay or reversal based on the mirror is unsafe.

## Correct repair boundary

Do not retry the customer sale now: the single costed unit is already allocated and 3D-P availability is zero. Do not write a literal into CRM `Склад!H113` or suppress the alert solely by changing its display rule.

A controlled reclassification should preserve one physical consumption and one Serhiy accrual: link this FIFO allocation to CRM sale row 387 / `OC-FOP-0382`, remove the erroneous marketing classification and its separate UAH 30 CRM expense only after the finance effect is reconciled, and leave 3D-P availability at zero. It requires an exact live 3D-P action contract or a fresh bound-project export, plus a preview of affected ledger rows, rollback, and before/after CRM integrity checks. One-time repair code belongs in separate `CRM-016.html` and must be removed after verification.

Acceptance: 3D-P printed 1, defect 0, sold 1, bonus 0, available 0; FIFO allocated 1 and not 2; CRM customer sale linked once; no duplicate Serhiy accrual or CRM marketing expense; dashboard physical/available 0; sale-sync and physical-unverified alerts absent; CRM integrity clean. These are target conditions, not verified results.
