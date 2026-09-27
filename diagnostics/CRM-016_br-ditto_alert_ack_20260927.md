# CRM-016: one-incident BR-DITTO-400 alert dismissal

Date: 2026-09-27

## Outcome and scope

The live Alerts control sheet now marks the current `3dp_sale_sync_missing` alert for `BR-DITTO-400` as dismissed. The known incident is the fulfilled CRM sale `OC-FOP-0382`, row 387, accounted in 3D-P by the marketing gift allocation documented in `CRM-016_br-ditto-ledger-audit_20260927.md`. This is an alert acknowledgement, not a stock or ledger repair. No 3D-P or CRM transaction cell was changed.

The current deployed Alerts V8 uses `3dp_sale_sync_missing|BR-DITTO-400` as the signature. A new local CRM + Alerts API candidate identifies the exact unmatched sale rows in that signature. Once both candidates are published, a later missing sale on a different CRM row receives a different alert ID and is active.

## Live control write

Spreadsheet: `Booster Shop — Майстер-дашборд автоматизацій`; sheet `_Керування_Алертами`.

Before: bounded read `A1:D500` showed 52 populated rows and no `BR-DITTO-400` status row. Rows 53–54 were empty. After: bounded read `A53:D54` confirmed both records:

| Row | Alert signature | Status | Purpose |
| --- | --- | --- | --- |
| 53 | `3dp_sale_sync_missing|BR-DITTO-400` | `dismissed` | Current deployed V8 alert |
| 54 | `3dp_sale_sync_missing|BR-DITTO-400|387:OC-FOP-0382:1` | `dismissed` | Exact incident signature after local source publication |

The IDs in column A are SHA-256 of the signatures, matching `hashText_`. The live UI/API response was not read because its token is owner-held. The deployed V8 code reads column A/B status rows on every `alerts` request, so the current item should show as dismissed on the next refresh.

## Source changes and checks

- `crm/apps-script/Code.gs` adds `missing_fulfilled_sources` to 3D sale-sync exceptions. The source list consists only of unmatched fulfilled sale row, order ID and quantity; stock arithmetic is unchanged.
- `crm/alerts-apps-script/Code.gs` uses that source list in sale-sync alert identity. All other alert kinds and the earlier incoming-quantity copy change are retained.
- Complete paste files: `work/CRM-016_CRM_API_from_V192_alert_incident_identity.gs` and `work/CRM-016_Alerts_API_from_V8_incoming_incident_ids.gs`.
- Both complete `.gs` files passed Node syntax checks via stdin. Focused inventory and alert contract tests passed, including a second missing sale at CRM row 400 receiving a different incident ID.

## Remaining gate and rollback

The owner reported publishing the two candidates as CRM V193 (09:57) and Alerts V9 (09:58) on 2026-09-27. Their local mirrors match the paste files byte for byte; live Web App output was not independently checked after publication. The distinct-sale re-alerting behavior is covered by local contract tests, not a real second-sale QA.

To undo the live acknowledgement, set status `active` for rows 53–54 through the Alerts UI/API or restore those two previously empty cells. To roll back the code, restore the previous V192 CRM and V8 Alerts sources. After publication, verify that `OC-FOP-0382` is dismissed, a simulated/new distinct missing sale gets an active alert, and the stock row remains explicitly unverified until ledger classification is corrected.
