# Codex Report — OC-FOP-0414: 3D-P FIFO idempotency conflict

Date: 2026-10-04

## Scope

Diagnosed the failed CRM order update and prepared a guarded recovery path for
the existing FIFO sale allocation. Only the CRM script was edited in the local
repository. No Apps Script source was copied or published, and no live workbook
cell was changed.

## Files touched

```
crm/apps-script/Code.gs                                      — CRM retry and accounting recovery
crm/apps-script/SOURCE_STATE.md                              — V195 export and local candidate state
diagnostics/OC-FOP-0414_3dp-idempotency-conflict_report_20261004.md — diagnosis and recovery handoff
```

## Findings

- In the live CRM workbook, `Продажі` row 439 is `ACC-3D-PKM-110`, quantity 2,
  sale total UAH 80, under `OC-FOP-0414`.
- The live `_Журнал_3DP_синхронізації` records the FIFO sale as created at
  12:47:33 Kyiv time with allocation
  `3DP-A-crm_sale-OC-FOP-0414-439`. The order update failed at 12:55:01; the
  retry failed again at 12:57:36 with `IDEMPOTENCY_CONFLICT`.
- The existing `3D_облік_замовлень` snapshot for this CRM row has quantity 2
  and UAH 0.71 total packaging. The latest CRM order update allocated entered
  UAH 200 packaging as UAH 141.59, 44.25, and 14.16 across its three order
  lines. Row 439 currently carries UAH 14.16 total packaging, or UAH 7.08 per
  unit. Delivery UAH 95 is allocated separately and is not part of the 3D-P
  FIFO request.
- `packaging_unit` is in the original FIFO operation fingerprint. A changed
  payload therefore correctly blocks a normal replay. The old recovery path
  had no way to reconcile new packaging while preserving the existing stock
  allocation.
- The CRM component ledger contains six rows for this order once, with the
  same dashboard request marker. Do not submit the full order form again.

## Local fix prepared

- The CRM now catches only the exact `IDEMPOTENCY_CONFLICT` response. It reads
  the unique 3D-P sale row by order and CRM row and compares its SKU, quantity,
  date, price, channel, mode, RRP, profit share, fixtures, buyout, and frozen
  FIFO unit cost against the retry payload. It proceeds only when packaging is
  the sole stored sale field that differs.
- When packaging differs, CRM uses the existing owner-only `3dp_write` action
  with `expected_current` to update only `Продажі!G`; the existing API write
  route appends the audit entry. The retry then appends the corrected CRM
  accounting snapshot, using the frozen FIFO cost already stored on the sale.
- No reversal or new sale operation is created, so this path does not touch
  batch stock. Repeating the retry leaves G unchanged and does not consume FIFO
  stock again.
- If another sale field changed, the key is duplicated, the API returns a
  different error, or the source data no longer matches, the operation fails
  closed.
- A task-specific HTML repair file is not needed: after the CRM candidate is
  deployed, the existing dashboard **Retry only 3D-P after error** button uses
  the guarded recovery path.

## Source evidence

- The supplied CRM V195 CSV matches the repository `Code.gs` that existed
  before this task.
- The supplied 3D-P V41 TXT matches the repository API `Code.gs` that existed
  before this task. It did not include separate `CatalogFifo.gs`, so the
  comparison does not independently verify that bound-project file. This fix
  uses the V41 generic `3dp_write` action and makes no edits to 3D-P source.
- The local CRM candidate is unpublished. Live recovery and post-retry QA
  remain with the owner.

## Verification

- Source/diff review only; automated tests and production writes were not run.
- No PHP files changed.

## Rollback

Before deployment, discard the local CRM changes listed above to restore the
prior source. If deployed, restore the previous owner-managed CRM Apps Script
version. Deployment rollback does not undo an already applied
packaging correction or accounting snapshot; those require a separately
reviewed accounting correction.

## Owner run and QA

After publishing the updated CRM Apps Script project, use the existing
**Retry only 3D-P after error** action for `OC-FOP-0414`. Do not clear delivery
cost or resubmit the full order form.

- [ ] Confirm CRM `_Журнал_3DP_синхронізації` records a packaging-only correction.
- [ ] Confirm 3D-P `Продажі!G` reflects the current CRM packaging per unit and
  `_Аудит_API` records the G-cell change.
- [ ] Confirm `3D_облік_замовлень` and its CRM projection reflect the updated
  packaging economics.
- [ ] Confirm the original FIFO allocation and batch stock counters did not
  change or get consumed twice.
- [ ] Confirm order component and marketing gift rows remain single.

## Risks / limits

This is CRM/3D-P accounting and requires owner-controlled CRM publication and
live QA. The CRM candidate is not deployed yet, so the current retry button
will continue to report the conflict until the new CRM version is published.
