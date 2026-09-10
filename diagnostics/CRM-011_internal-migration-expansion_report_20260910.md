# Codex Report — CRM-011: internal migration expansion

Date: 2026-09-10

## Outcome

Expanded the existing FIFO-safe internal inventory migration so one package
can be split into individual sellable units when the source is a booster box,
display, bundle, set, blister, or the canonical 25-piece toploader SKU.

The dashboard now provides text search by SKU or product name above both the
source and target dropdowns.

## Root cause

The accounting engine was already generic: it removed one source unit, created
the requested target quantity, transferred the same total PRRO/management cost,
and recorded the source FIFO lot. The restriction was in
`inventoryMigrationNormalizeRequest_()`, which accepted only a source whose
format matched `box / бокс / display`. The dashboard mirrored that artificial
restriction through `context.boxes` and box-specific labels.

No formula, FIFO allocation, ledger schema, or historical purchase rewrite was
required.

## Implementation

### Backend

- Added the guarded `container_to_units` request type.
- Recognizes sources through canonical catalogue data:
  - box/display format;
  - bundle/set/blister name, format, or established SKU suffix;
  - exact `ACC-003` toploader package.
- Restricts targets to individual booster-pack SKU or exact `ACC-009`.
- Enforces the toploader contract server-side:
  `1 × ACC-003 → 25 × ACC-009`.
- Keeps old `box_to_packs` requests valid for backward compatibility.
- Returns `split_sources` and `split_targets` without removing the old context
  fields.

Each operation still:

1. verifies the expected live source balance;
2. consumes the oldest remaining source lot through FIFO;
3. transfers the same total cost into the target units;
4. appends an idempotent `Міграції_Складу` record;
5. refreshes deferred preorder cost where applicable;
6. verifies source/target balances and rolls back on failure.

### Dashboard

- Renamed the first form to `Упаковка → поштучний SKU`.
- Added source and target text-search fields. Each search filters the existing
  dropdown by both SKU and visible product name.
- Selecting `ACC-003` clears stale values and auto-fills `ACC-009` and `25`.
- The owner confirmation still shows the exact source SKU, target SKU, and
  quantities before the POST request.

## UI/CSS review

No CSS was changed. Existing form, input, select, focus, and responsive grid
rules are reused, so this patch adds no `!important`, fixed/absolute position,
or magic-pixel override. Long product names remain inside the existing select
control, and the form continues to stack through the established responsive
`grid2` rule.

## Validation

```text
Inventory migration backend fixture: PASS
Focused dashboard tests:           3/3 PASS
Existing CRM-011 dashboard tests:  6/6 PASS
All Apps Script test files:        31 PASS
Dashboard inline JS syntax:        PASS
git diff --check:                  PASS
```

Backend coverage includes:

- legacy box-to-pack compatibility;
- bundle-to-pack and set-to-pack operations;
- exact `ACC-003 → 25 × ACC-009` enforcement;
- rejection of the wrong toploader target or quantity;
- FIFO cost conservation;
- preorder and write-off reservation preservation;
- duplicate request-ID protection.

The broad `dashboard-contract.test.mjs` currently detects an unrelated dirty
3D-P source/dashboard category mismatch (`Кейс / контейнер для зберігання`).
The 3D-P source file is modified outside this task; this CRM change does not
touch or conceal that separate worktree condition.

## Risk and boundary

- One migration still has exactly one source SKU and one target SKU. A mixed
  set containing several different pack SKU must not be represented as one
  migration; it needs a separately designed atomic multi-target operation.
- Source recognition depends on the catalogue name/format/SKU suffix. The
  server rejects an unrecognized source instead of allowing an arbitrary stock
  conversion.
- The dashboard filter is a view only; it does not change balances or API data.
- No live spreadsheet write, Apps Script publication, commit, or push was
  performed in this follow-up.

## Owner publication and QA gate

1. Paste the latest repository `crm/apps-script/Code.gs` into the bound main
   CRM Apps Script project and publish a new Web App version.
2. Open the local dashboard and press `Ctrl+F5`.
3. Open `Облік → Оновлення та міграція → Внутрішня міграція товару`.
4. Search by part of a SKU and by part of a product name in both new search
   fields. Confirm the matching dropdown choices remain visible.
5. Select `ACC-003`. Confirm the form auto-fills `ACC-009` and quantity `25`.
6. Do not save a live migration until the shown source balance and target
   conversion are correct. A live save changes inventory and requires the
   owner's normal spreadsheet backup/QA gate.

## Rollback

Before publication, discard only this scoped local diff. After publication,
publish the previous Apps Script version and restore the previous dashboard
file. Existing migration ledger rows require no schema rollback. Any live
migration created during owner QA is a real inventory movement and must be
reversed through an explicitly reviewed compensating operation, not by deleting
the audit row.
