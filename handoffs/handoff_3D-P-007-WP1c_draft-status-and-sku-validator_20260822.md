# Codex Handoff — 3D-P-007 WP1c: draft status + SKU validator

Date: 2026-08-22 | Parent: 3D-P-007 (Serhiy local server)
Codex config: model=Sol · effort=xhigh

Justification: this is the highest-risk package of the whole task. A draft row
that leaks past a status filter becomes a sellable product with no canonical
article, or enters Serhiy's accrual. It also spans **two deployed scripts**,
including the main CRM, which WP1 and WP1b were explicitly forbidden to touch.

Owner decisions implemented here: `plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md`,
revision section of 2026-08-16, headings "Заведення нових виробів" and
"Присвоєння артикула".

## Context

Serhiy will create new products himself, one at a time, instead of receiving a
bulk script load. He must not invent article numbers: the mnemonic in
`<ПРЕФІКС>-<МНЕМОНІКА>-<XYZ>` is a readability judgement (`JIGGLYPUFF → JIGGL`,
`POKEBALL → PKBL`), and the convention in
`plans/3D-P_sku-naming-convention_20260807.md` explicitly requires stopping and
asking the owner when an item's mechanic matches no existing category.

So Serhiy creates a **draft**; the owner assigns the article; only then may the
product be sold.

Live baseline: 3D-P Web App **V25** (2026-08-22 17:35), CRM **V122**
(2026-08-15). No source export exists for V25 — see
`3d-print/apps-script-3dp-api/SOURCE_STATE.md`.

## Scope (what to change)

Two scripts. **One patch file per script**, not one combined patch.

### 1. `Чернетка` as a third nomenclature status

`API_3DP` already carries `activeStatus: 'Активний'` and
`archivedStatus: 'Архів'` on the nomenclature status column `O`. Add a third
value. Do not invent a parallel mechanism.

**The danger is not adding the value — it is every place that currently asks
"is this archived?" instead of "is this active?".** A draft is not archived, so
those sites will treat it as a normal sellable product.

Verified anchors in `3d-print/apps-script-3dp-api/Code.gs` at the V25 baseline —
treat this as a starting list, not a complete one:

| Kind | Approx. line | Current shape | Required |
|---|---|---|---|
| Guard | 1405, 1473, 1664, 1893 | `nomenclatureStatusAtRow3dp_(...) === archivedStatus` | must become "not active" — a draft must be refused here too |
| Whitelist | 2350, 2797 | `[activeStatus, archivedStatus].indexOf(status) === -1` → reject | must accept `Чернетка` |
| Row read | 741 | `row['API_статус_запису'] === archivedStatus` | decide and state whether drafts are hidden from this read |
| Formulas | `setupAvailabilityArchiveAwareFormulas3dp_` | `Наявність` excludes archived rows | must exclude drafts as well |

**Prove the list is complete.** Enumerate every site that branches on the
nomenclature status, state what each one does with a draft, and cover each with a
test. A site you did not list is a site you did not fix.

### 2. CRM-side sync filter — risky zone

`crm/apps-script/Code.gs` line ~7508 filters the 3D sync with
`String(row.API_статус_запису || '').trim() === 'Архів'`. A draft passes this
check and would sync into `Продажі`.

Change it to exclude anything that is not the active status. Keep the literal
comparison style already used there; do not import 3D-P constants into the CRM.

This is the only CRM change in scope. Lines ~1099–1120 and ~4544/4663 use
`Активний` for CRM product tables — a different concept. Do not touch them.

### 3. Draft creation by Serhiy

- Serhiy may create a nomenclature row with **no article assigned**, carrying
  production data: name as free text, type/category selection, dimensions, mass,
  print time, material, his proposed prices.
- The row is created with status `Чернетка`.
- Serhiy may edit his own drafts through the columns he already owns.
- Serhiy may **not** set the status. Only the owner moves a row out of
  `Чернетка`.

⚠ **Design decision you must make and state.** `Номенклатура` is keyed by SKU and
many lookups resolve by it. A blank SKU risks collisions and broken lookups.
Recommended: a reserved temporary identifier in the SKU column, shaped so it can
never match the CRM sync trigger regex `^(?:BR|FIG|ACC-3D)-…` — a `DRAFT-` prefix
satisfies this, which means a draft is inert on the CRM side by construction as
well as by status. Whichever you choose, state why, and prove the identifier
cannot match the trigger.

### 4. Article assignment — owner only, hybrid, never automatic

- A new owner-only action assigns the canonical article to a draft and moves it
  to `Активний`.
- The action **validates strictly** against the canon:
  `^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$`. This is the strict grammar agreed in
  3D-P-022 for the creation path.
- It rejects an article already present in `Номенклатура`.
- It may **suggest** prefix and category digits derived from the type selection —
  the deterministic part. It must never derive or suggest the mnemonic, and must
  never assign without an explicit owner-supplied article.
- Assignment while the draft has no sales and no print-log history is a plain
  field write. If any history exists, refuse and say so — renaming a key with
  history is a migration, not an edit, and is out of scope here.

⚠ The 3D-P API has **no SKU shape validation at all** today (verified). The canon
is enforced in exactly two places: the CRM sync trigger and the owner dashboard
create form (3D-P-022 finding). This package adds the third, and it must be the
strict one.

## What NOT to touch

- `Продажі` column set — `CRM_3DP_SALES_FROZEN_HEADERS_` is enforced by strict
  equality in the CRM.
- The WP1 rev 2 projection boundary and `SERHIY_FULL_ECONOMICS_VISIBLE_3DP`.
- The WP1b write rights, journals and payout acknowledgements.
- `Виплати` acknowledgement columns `G1:H1` — already live, never remove.
- Existing article numbers. Nothing is renamed by this package.
- `dashboard/booster-dashboard.html` and the Serhiy local server — WP2 and WP2b.
- Any token.

## Acceptance criteria

- [ ] Every status branch site is enumerated in the report with its draft
      behaviour stated, and each is covered by a test.
- [ ] A draft row is excluded from `Наявність` totals.
- [ ] A draft row cannot be sold, cannot enter an accrual, and does not sync from
      the CRM — proven by a test on the CRM side too, not only in 3D-P.
- [ ] A draft row is refused by every guard that currently refuses an archived
      row.
- [ ] Serhiy can create and edit a draft; Serhiy cannot change its status.
- [ ] Article assignment is owner-only, rejects a non-canonical shape, rejects a
      duplicate, and refuses a draft that already has history.
- [ ] Suggestions never include a mnemonic and never auto-assign.
- [ ] The draft identifier cannot match the CRM sync trigger regex, proven by a
      test.
- [ ] Owner read responses stay byte-identical to the WP1b baseline via the
      existing comparison harness, with no silent-skip path.
- [ ] The baseline-invariant test from WP1b still passes.
- [ ] All existing 3D-P and CRM suites pass.

## Deployment order

State it explicitly in the report. The safe order is **CRM first, 3D-P second**:
the CRM filter change is inert until drafts can exist, whereas publishing the
3D-P side first opens a window in which a draft could be created while the CRM
still syncs it.

If a schema migration is required, ship it as a `preview…` / `setup…` pair in the
same style as WP1b, and say plainly what a rollback does and does not undo.

## Risks

Risky zone: CRM, financial, deployed Apps Script, production-direct, two scripts.

- **Blast radius is the whole 3D line plus the CRM sync path.** The failure mode
  is silent: a draft that looks like a product, sells, and creates an accrual
  against an article that does not exist.
- **The guard-polarity inversion is the core of this package.** Every
  `=== archivedStatus` that means "only active rows may proceed" is a latent bug
  the moment a third status exists.
- **Rollback.** Republish CRM V122 and 3D-P V25, then owner hard-refresh. Any
  draft rows created in between remain in the sheet and become invisible to the
  new status logic — say in the report how the owner identifies and clears them.
- **Two publications means a window.** Name it and order it.

## Delivery

Two patch files into `patches/` — one per script — and one report into
`diagnostics/`. No commit, no push, no Apps Script publication, no live Sheet
write. The owner deploys.
