# Codex Handoff — 3D-P-007 WP1b: Serhiy write rights + payout acknowledgement

Date: 2026-08-16 | Parent: 3D-P-007 (Serhiy local server)
Codex config: model=Sol · effort=xhigh

Justification: financial zone — these columns set the shop's retail price and the
price the shop pays Serhiy, and the payout acknowledgement becomes the record of
mutual agreement. Codex owns this file through WP1 rev 2; a second author would
be a parallel-writer violation.

Owner decisions implemented here are recorded in
`plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md`, revision section
of 2026-08-16.

## Context

WP1 rev 2 is deployed and owner-QA'd. Serhiy can now read the whole 3D line and
edit the four cost parameters in `Налаштування!B2:B5` with an append-only
journal. He still cannot set prices, correct stock, or acknowledge a payout.

Live baseline after WP1 rev 2 deployment. Confirmed live settings values:
printer power `0.11` kW, electricity `4.32` UAH/kWh, amortisation `12` UAH/h,
planned defect `0.08`.

## Scope (what to change)

All changes in `3d-print/apps-script-3dp-api/Code.gs` plus its tests. One patch
file, WP1b only.

### 1. Serhiy writes `Номенклатура` `Q`, `R`, `S`

- Add `Q`, `R`, `S` to `SERHIY_MANUAL_COLUMNS_3DP['Номенклатура']`.
  `Q` = `РРЦ фактична, грн`, `R` = `Ціна під викуп, грн`,
  `S` = `Посилання на модель`.
- Rationale, so it is not later mistaken for a leak: `R` is the price at which
  Serhiy sells to the shop, so it is his decision by definition; `Q` is set by
  him under verbal agreement with the owner.
- Every accepted write to `Q`, `R` or `S` by **either** role appends a journal
  row: Kyiv timestamp, actor role, SKU, column header, old value, new value.
  Rejected writes append nothing.
- Reuse the `_Журнал_налаштувань_3DP` mechanism from WP1 rev 2 rather than
  inventing a third journal shape. Either extend that sheet with a scope/SKU
  column or create a sibling sheet built the same way — state which and why.
  Whatever you choose must be hidden like the existing one.
- `Q` and `R` are numeric and must be validated and bounded, as
  `Налаштування` values are. Reject non-numeric and negative input. `S` is a
  URL-shaped string; reject anything that is not `http(s)://`.

⚠ **Read/write consistency rule.** Today `Q` sits in the `Номенклатура`
projection `baseline` while `R` and `S` sit in `fullEconomics`. If
`SERHIY_FULL_ECONOMICS_VISIBLE_3DP` is ever flipped to `false`, Serhiy would be
able to write `R` and `S` but not read them back. Move every column Serhiy can
write into `baseline`, so writability never outruns visibility. Add a test that
fails if a Serhiy-writable column is absent from that sheet's `baseline`.

### 2. Serhiy corrects stock

- `adjustStockAction3dp_` currently calls `assertOwner3dp_`. Replace that with a
  check admitting `owner` and `serhiy`, in the manner
  `assertPrintLogRole3dp_` already uses.
- `stockAdjustmentsAction3dp_` (the read) is already role-open and projected;
  confirm it stays consistent.
- Semantics do not change: the field takes the **actual count on hand**, not a
  delta, and the difference is computed from the current value at submit time —
  this is the 3D-P-025 behaviour and it must survive untouched.
- The `_Коригування_наявності` ledger stays append-only and must record the
  actor for every row.

### 3. Payout two-way acknowledgement

- `Виплати` gains two owner-visible, Serhiy-writable acknowledgements:
  1. Serhiy agrees with the calculated amount;
  2. Serhiy confirms the money arrived.
- Each stores a Kyiv timestamp and the acting role. A blank value means "not
  yet"; never store a bare boolean with no time.
- Both are **append-once**: a set acknowledgement is not silently overwritten. A
  correction is a new explicit action that preserves the previous value.
- Serhiy may set only his own acknowledgements. `3dp_payout_create` and
  `3dp_payout_mark_paid` keep their `assertOwner3dp_` guard — creating and
  closing a period stays with the owner.
- An acknowledgement on a period that does not exist, or that the owner has not
  yet published, is rejected.

⚠ **Do not forget the projection.** `SERHIY_READ_PROJECTION_3DP['Виплати']`
matches by header name and fails closed on an unknown header. New columns that
are not added to its `baseline` will be invisible to Serhiy — he would be unable
to see the state of his own confirmations. Add them to `baseline`, not to
`fullEconomics`.

⚠ `Виплати` already carries a `Термін перевірки Сергієм` column. Read it live
before designing, and either use it or state plainly why a new column is needed.
Do not create a second concept with the same meaning.

## What NOT to touch

- `crm/apps-script/Code.gs` — main CRM, live at V122. Nothing here needs it.
- `Продажі` column set — protected by `CRM_3DP_SALES_FROZEN_HEADERS_` under
  strict equality in the CRM. Changing it breaks sync in two deployed scripts.
- The WP1 rev 2 projection boundary — order and customer identity stay hidden.
- `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` — leave at `true`, do not repurpose it.
- Owner read-response shape — the dashboard depends on it.
- `dashboard/booster-dashboard.html` and the Serhiy local server — WP2 and WP2b.
- Any token.

## Acceptance criteria

- [ ] Serhiy can write `Q`, `R`, `S`; each accepted write appends exactly one
      journal row with old and new value; each rejected write appends none.
- [ ] A non-numeric `Q`/`R`, a negative value, and a non-URL `S` are all
      rejected with a clear message.
- [ ] Every Serhiy-writable column appears in that sheet's projection
      `baseline`, enforced by a test that fails otherwise.
- [ ] Serhiy can submit a stock correction; the actual-count semantics of
      3D-P-025 are unchanged and covered by a regression test.
- [ ] The stock ledger records the actor on every row.
- [ ] Serhiy can set both payout acknowledgements; each stores a Kyiv timestamp
      and role; a second attempt does not silently overwrite.
- [ ] Serhiy cannot create or close a payout period.
- [ ] The new `Виплати` columns are visible to Serhiy through the projection.
- [ ] Owner read responses stay byte-identical to the WP1 rev 2 baseline, proven
      by the existing comparison harness, which must not regain a silent-skip
      path.
- [ ] All existing 3D-P suites still pass.

## QA checklist (owner runs after deploy)

No staging. Publish a new 3D-P Web App version and run these on production.

- [ ] Dashboard, `Ctrl+F5`, walk all three 3D zones — any change in your own view
      is a regression, stop.
- [ ] Change an RRP from the dashboard, confirm one journal row with old and new
      value.
- [ ] Enter a stock correction, confirm the resulting on-hand figure equals the
      number you typed, not the sum.
- [ ] Create a test payout period, confirm you can create and close it.
- [ ] `integrity_check` returns `clean=true`, `problems=[]`.

Serhiy-token checks belong to WP3 joint QA, not here.

## Risks

Risky zone: financial, CRM-adjacent, deployed Apps Script, production-direct.

- **Blast radius.** `Q` and `R` feed frozen sale economics and Serhiy's accrual.
  A bad write is not cosmetic — it changes money owed. The journal is the
  recovery path, so it is a hard requirement, not a nice-to-have.
- **Rollback.** Republish the WP1 rev 2 version, then owner hard-refresh. If new
  `Виплати` columns were added, rollback leaves them in place and harmless —
  never delete them, and never delete a journal sheet.
- **Silent overwrite** of an acknowledgement is the failure mode that destroys
  the record's value. Append-once is the point of the feature.
- **Projection omission** is the second failure mode: new columns invisible to
  Serhiy because nobody added them to `baseline`.

## Delivery

Patch file into `patches/`, report into `diagnostics/`. No commit, no push, no
Apps Script publication, no live Sheet write. The owner deploys.
