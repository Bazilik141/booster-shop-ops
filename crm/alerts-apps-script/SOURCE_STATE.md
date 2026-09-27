# Alerts Apps Script — repository mirror state

## Owner-reported Alerts Web App V9 (2026-09-27 09:58)

The owner reports publishing the latest Alerts source as V9 and placing that
source in the repository. The current `Code.gs` is byte-identical to
`work/CRM-016_Alerts_API_from_V8_incoming_incident_ids.gs` (local SHA-256
comparison, 2026-09-27). This verifies the local candidate identity only;
the bound Apps Script bytes and live alert response were not independently
read.

## CRM-016 incident-specific dismissal from owner-reported V8 (2026-09-27)

The mirror and complete paste source
`work/CRM-016_Alerts_API_from_V8_incoming_incident_ids.gs` retain the pending
incoming-quantity copy fix and add exact missing CRM sale rows to the
`3dp_sale_sync_missing` alert signature when the CRM snapshot supplies them.
The ordinary SKU-only signature remains a compatibility fallback. Dismissing
`BR-DITTO-400` for sale `OC-FOP-0382` then leaves a later missing sale with a
different alert ID. Local syntax and focused alert tests passed. The owner
later reported publishing this source as V9.

## CRM-016 local incoming-quantity fix from owner-reported V8 (2026-09-27)

`Code.gs` and `work/CRM-016_Alerts_API_from_V8_incoming_fix.gs` contain the
same complete local candidate. Stock-queue alerts label column F as the
quantity purchased but not yet in the UA warehouse and omit column G's
maximum purchase amount. The Telegram stock summary uses the same labels.
This candidate is locally checked and included in the owner-reported V9 source
above. No bound-project export was pulled.

## Owner-reported deployed V8 (2026-09-27 07:55)

The owner reports publishing V8 after receiving the complete
`work/CRM-016_Alerts_API_from_V7_unverified_stock_fix.gs` file. No bound-project
export was pulled, so byte-level identity and live alert behaviour remain
unverified. The dashboard archive response issue reported after this release
comes from the separate 3D-P call, not the Alerts API.

## CRM-016 local follow-up from owner-reported V7 (2026-09-26)

The current `Code.gs` and complete paste file
`work/CRM-016_Alerts_API_from_V7_unverified_stock_fix.gs` keep the specific
`3dp_sale_sync_missing` alert when the CRM snapshot correctly withholds an
unverified physical stock figure. They do not create a false print-deficit
alert for that case. Local contract tests passed. This file was later
owner-reported published as V8.

## Owner-reported deployed V7 (2026-09-25 22:16)

The owner reports deploying the complete inventory candidate
`work/CRM-016_Alerts_API_from_V6_inventory_fix.gs` as Web App V7. The local
`Code.gs` mirror was byte-identical to that file before the local follow-up
above. No fresh bound-script export,
`inventory_snapshot` result, or live alert-list result has been supplied.
`BOOSTER_CRM_URL` and `BOOSTER_CRM_TOKEN` still need confirmation in the
Alerts project's Script Properties; never record their values here.

The owner's bounded live `alerts` response first showed one active
`3dp_source` alert with `details: CRM inventory connection is not configured`.
After the owner configured the connection, the 2026-09-26 dashboard screenshot
showed four specific active alerts and no `3dp_source` item. This supports a
working Alerts-to-CRM read through the current Web App; no Alerts code change
or newer Alerts deployment was reported. The main CRM `inventory_snapshot`
separately returned `source_status: ready`.

## CRM-016 inventory candidate from owner-reported V6 (2026-09-25)

The mirror matched `work/CRM-016_Alerts_API_from_V5_followup.gs` before this
round. The owner reports that file was published as V6. The new local candidate
`work/CRM-016_Alerts_API_from_V6_inventory_fix.gs` suppresses CRM-formula 3D
stock/queue alerts and reads the main CRM `inventory_snapshot` action for 3D
print, sync and catalogue exceptions. It has not been deployed or live-tested.
The Alerts project must have `BOOSTER_CRM_URL` and `BOOSTER_CRM_TOKEN` in Script
Properties for this read; token values must never enter this mirror.

## Owner-reported deployed V6 (2026-09-24 19:31)

The owner reports Alerts Web App V6 was deployed at 19:31 from
`work/CRM-016_Alerts_API_from_V5_followup.gs`. No fresh V6 export was supplied.
The mirror is byte-identical to that previous candidate; this 3D dashboard
follow-up makes no Alerts API code changes. Owner-reported CRM integrity check
was clean, but it is not a direct verification of the Alerts API.

## CRM-016 follow-up candidate from owner-reported deployed V5 (2026-09-24)

The owner reports V5 was deployed from `work/CRM-016_Alerts_API_from_V4.gs`
and confirms there were no subsequent manual code edits. This is owner-reported
provenance, not a fresh V5 export. The local follow-up adds a stable first-seen
date for each current alert type/SKU in Script Properties; the date begins when
this version first observes the alert and is cleared after it resolves. Existing
incident start dates cannot be reconstructed. The candidate is
`work/CRM-016_Alerts_API_from_V5_followup.gs`; it has not been deployed.

## CRM-016 owner export V4 preserved (2026-09-24)

The owner confirmed that the automation spreadsheet's deployed Alerts API is
version 4 and supplied `Версія 4, 9 вер. 2026 р., 1736.txt`. This 477-line
raw source has normalized SHA-256
`9b927009c9dea63af93ca4cb09824355373125fa1dd15bb9b007d34c52725a1d`.
The V4 export already contains the earlier granular-alert hotfix, superseding
the publication uncertainty in the historical note below. The current local
`Code.gs` preserves every V4 line; its only differences are
the 28-line `setManagedAlertStatusBatch_` helper and the one-line doPost route
for `set_alert_status_batch`. The exact ready-to-paste copy is
`work/CRM-016_Alerts_API_from_V4.gs`, byte-identical to this mirror. No new
Alerts Web App deployment or live batch QA is verified.

## Historical CRM-011 state as of 2026-09-09 (superseded by V4 export)

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
ranges. At this 2026-09-09 snapshot its publication was unverified; the V4
export supplied on 2026-09-24 now proves it is in the deployed baseline.

No token is stored in the mirror. `BOOSTER_ALERTS_TOKEN` remains owner-held in
Script Properties. The lack of a live source export applied only to this older
snapshot; the V4 export is now available and compared above.

On 2026-09-23, the local unpublished candidate added `set_alert_status_batch`
for up to 100 current alert IDs. It validates the whole selection before one
control-sheet write under a script lock. Dashboard checkboxes call this action
to dismiss several alerts at once. No publication or live QA of this batch
action is verified.
