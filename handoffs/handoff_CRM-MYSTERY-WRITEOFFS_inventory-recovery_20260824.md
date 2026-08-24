# CRM Mystery Box write-off inventory recovery

Date: 2026-08-24  
Executor: Codex · model=Terra · effort=high — bounded CRM data/formula recovery with rollback guards and full local regression coverage.  
Owner action required: create the workbook copy, paste/publish, run the public menu actions, and perform the live read-back.

Status: recovery completed successfully at 12:59 Kyiv on 2026-08-24. This handoff is retained as historical execution evidence; the temporary recovery code has been removed locally for the next source paste.

## Scope

Correct the CSV-audited inventory discrepancy without creating new write-offs:

- retain one valid `WRT-0206` record;
- clear exactly 16 identical surplus copies;
- expand existing `Склад!G` write-off formula references through the current `Списання` capacity;
- enable the existing daily row-capacity maintenance trigger;
- recalculate SKU current costs only after the successful recovery.

Out of scope: physical count, new manual write-offs, FIFO rewrites, historical sale-cost edits, and any customer data.

## Local candidate

`crm/apps-script/Code.gs` adds:

- temporary menu action `Відновити списання Mystery Box (24.08)` → `repairMysteryBoxWriteOffInventory20260824`;
- permanent public menu action `Налаштувати автооновлення формул CRM` → `setupCrmRowCapacityMaintenanceMenu`.

The temporary action takes the document lock and refuses to write unless the live ledger contains exactly 17 semantically identical `WRT-0206` rows with the audited date, type, SKU, and quantity. It keeps the first row, clears the other 16 full rows, updates only matching `Склад!G` formulas, flushes, checks for formula errors, runs bounded CRM integrity comparison, and restores the cleared rows/formulas on any failure before returning an error.

It does not refresh FIFO/current-cost cells. That is deliberately left to the existing public `Оновити собівартість складу` menu action after recovery success.

## Owner-run sequence

1. Make a fresh copy of the CRM workbook and retain its URL/version history as rollback evidence.
2. Paste the local `crm/apps-script/Code.gs` candidate into the bound Apps Script project and publish a new Web App version. Refresh the CRM spreadsheet so its menu reloads.
3. In `Booster CRM`, select `Відновити списання Mystery Box (24.08)` once. Expected success result: `duplicate_rows_cleared: 16`; a mismatch/error means **stop** and do not retry or edit rows manually.
4. Select `Налаштувати автооновлення формул CRM`. A newly created or already-existing daily maintenance trigger is both acceptable.
5. Select `Оновити собівартість складу` once.
6. Read back the affected `Списання` records and all affected `Склад!G:H:I:J` rows. Confirm one `WRT-0206` remains, its 16 surplus copies are blank, no `#` formula errors are present, and the stock quantity decline is 47 valid units in total (44 Mystery Box component units plus 3 other write-off units).
7. Run the CRM integrity check. It must not show newly introduced problem codes.

## Rollback

Before the recovery returns success, any failure automatically restores only the cleared duplicate rows and changed `Склад!G` formulas. Once it returns success, use the workbook copy/version history from step 1 for rollback; do not reconstruct records by hand.

## Post-success cleanup

After the owner confirms the live read-back, remove the temporary recovery menu item, helper functions, and `mystery-writeoff-inventory-recovery.test.mjs`. Keep the permanent capacity-maintenance menu wrapper and the diagnostic report.

## Local verification

- `Code.gs` parsed successfully with Node.
- All 21 `crm/apps-script/tests/*.test.mjs` tests passed.
- `git diff --check` passed for the scoped files.
- No live CRM data, Apps Script source, trigger, Web App publication, or cache state was changed locally.
