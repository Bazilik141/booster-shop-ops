# Codex Report — 3D-P-007: owner-machine QA, session 1

Date: 2026-08-31

## Outcome and scope

Started the reviewed Serhiy package on the owner's Windows machine and opened its page for joint testing. This is an initial read-only smoke check, not completion of the write-bearing joint QA or installation at Serhiy's.

No application source edits, live workbook writes, deployment, commit, push or Notion action. The only repository additions are this report and `diagnostics/3D-P-007_owner-local-qa_20260831.png`.

## Package and launch evidence

- Archive: `3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260830.zip`.
- SHA-256 verified before extraction: `C45A6EE2E86FF326950785DBF826390A8DD9F88E8B15CE0A1919E0B856CEA88A`.
- Clean extraction: `C:\Users\14bez\AppData\Local\Temp\Booster3DP-OwnerQA-20260831-8a8be00e01134708af1cce54c9ca48b7\Booster-3DP-Serhiy_Node-v24.19.0_20260830`.
- First sandbox launch did not produce a listener. The same packaged BAT was launched outside the restricted environment; the resulting server was verified by its exact extracted runtime path.
- Server PID: `64760`; executable: the extracted `runtime\node.exe`; `MainWindowHandle=0`.
- HTTP: `200` at `http://127.0.0.1:3107/`.
- Existing Serhiy-scoped credential and API URL were present in Windows user environment variables. Values were not displayed or copied into files.

## Browser smoke evidence

- Status: `Дані отримано через 3D-P API.`
- Active SKU: 7; available units: 211; current-month accrued amount: 0.00 UAH.
- Calculator, Products, Information and settings panel open.
- Information: five visible tables.
- Settings: four controls populated.
- Fixture dropdown currently offers only the empty/clear option; no new fixture diagnosis performed.
- Browser warnings/errors: 0.
- Returned to the unfilled calculator and left the page open for the owner.

No save, manufacture, stock correction, draft creation or payout action was submitted. No test suite was rerun because application code was not changed.

## Observed polish candidate — not implemented

The Products print-log table exposes technical API status/history columns, request markers and currency values with long fractional tails. Discuss a presentation-only cleanup with the owner; do not change source values or accounting calculations to alter their display.

## Updating the package — explanation only

Current delivery is a replaceable folder, not an installer/updater. Credentials are stored outside that folder in Windows user environment variables, so folder replacement does not require re-entering them under the same Windows account.

A future one-file updater is feasible but not implemented or authorized by this session. Suggested design: record the installation path once, verify update contents, stop only the identified Serhiy server, keep a recoverable previous version, replace application files, preserve credentials, restart and health-check; roll back on failure. Do not search and overwrite arbitrary Node applications or silently alter the Apps Script API/CRM.

The existing handoff chose ZIP/BAT distribution. Moving to EXE is a separate owner decision. Windows reputation/signing behavior is a distribution consideration, not a reason to promise warning-free execution.

## Next bounded QA

Start with owner-driven calculator input and immediate recalculation without saving. Collect concrete UX findings. Live-write tests require a designated history-free test SKU and the backup/approval steps from the joint-QA checklist before submission.
