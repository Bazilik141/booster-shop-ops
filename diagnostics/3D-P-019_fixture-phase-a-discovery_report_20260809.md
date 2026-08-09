# Codex Report — 3D-P-019: phase A fixture discovery

Date: 2026-08-09

## Scope

Read-only discovery for the category rename and payer-column migration. No CRM, 3D-P workbook,
Apps Script, dashboard, or live data was changed.

## Fresh-source status

Both Apps Script mirrors were re-verified from owner exports on 2026-08-09, so the discovery used
current source rather than an inferred deployed version.

## Live fixture state

Narrow read of `Розхідники!A4:N80` found exactly two current fixture rows. Both currently have:

- category `3D-друк`;
- zero on-hand stock with quantity in transit;
- no payer field, because column N is the existing dropdown formula and is currently the last column.

The migration must append `Платник` as new column O, never insert it before the existing columns.
Both existing rows are owner-paid per the approved handoff and require the backfill `власник`.

## Category-consumer inventory

| Consumer | Actual dependency | Effect of `3D-друк` to `Фурнітура` rename |
|---|---|---|
| `getAutoConsumableInfo_` | Resolves a consumable by name and reads cost/quantities; it does not branch on category B. | No code change. |
| `addExpense()` | Reads the expense-form category but only has a `Пакування` condition and generic consumable validation. | No `3D-друк` branch to update. |
| Current CRM dashboard | Displays 3D areas but has no fixture-category filter. | No code change. |
| 3D-P API | Uses `Фурнітура_довідник` as a legacy 3D-P tab reference, not the main CRM category string. | Separate pending-purchase design work only. |

Exact-source search found no main-CRM or 3D-P code comparison to the literal category `3D-друк`.
The automatic-consumable reader is therefore unaffected by the approved category rename.

## Phase-A implementation recommendation

Prepare one owner-run, idempotent Apps Script setup action that:

1. validates the existing `Розхідники` header sequence through N;
2. appends column O with header `Платник` only when absent;
3. safe-fails unless the two known fixture rows are the only targets and their category is either
   pre-migration `3D-друк` or already-applied `Фурнітура`;
4. renames only those two categories and backfills only blank payer cells as `власник`;
5. reports `already_applied` on a repeat run without moving columns or modifying other consumables.

Before owner execution, deploy CRM-005 and run its integrity check. Record the bounded result both
before and after the setup action. A fresh named Google Sheets version is still required.

## Unresolved financial rule — blocks phase B only

`Продажі!W` holds one payer, while a single order can contain fixture lines paid by both parties.
The current handoff correctly forbids guessing this accounting. Phase B (multi-line order/write-off
forms, pending purchases, and frozen V/W wiring) needs an owner choice before implementation:

- restrict a sale to fixtures from one payer; or
- support a mixed payer with a new per-line fixture ledger and explicit `W`/accrual semantics.

Phase A itself can proceed independently after the CRM-005 deployment gate.

## Not yet verified

No migration has run and no owner deployment or live UI QA occurred. Real fixture consumption cannot
be QA'd until the in-transit stock arrives.
