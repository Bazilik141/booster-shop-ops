# Codex return brief — 2026-08-09

Executor: **Codex** · Author: Claude (chat) · Owner: Raccoon
Covers: `3D-P-025` (returned), `CRM-005` (accepted with fixes), `3D-P-019` (decision only).

Source review: `diagnostics/CRM-005_3D-P-025_3D-P-019A_claude-review_20260809.md`.
Read that for the full evidence; this brief is the executable summary.

---

## 0. Ground rules for this delivery

- **One dashboard delivery.** `dashboard/booster-dashboard.html` carries WP1, WP2 and WP3. It is
  uploaded once. Do not split it into separate files — the owner runs one upload.
- **Nothing here has been deployed yet.** That is why WP4–WP7 are included now: fixing them costs
  nothing today and would cost an extra named Apps Script version if deferred until after deploy.
- **No commit, no push, no deploy.** Prepare changes, checks and a diff summary only. Commit/push
  authority requires a direct owner request in the active task; there is none.
- **No Notion writes.** Status is already `In progress` on all three tasks, set by Claude (chat).
- **Report per work package** in `diagnostics/`, using `templates/codex-report-template.md`.

---

## WP1 — `3D-P-025` B1 · fresh reread queries the wrong sheet · BLOCKING

**File:** `dashboard/booster-dashboard.html`, `saveThreeDpStock()`.

Current:

```js
const fresh = await call3dp('3dp_get_row', { sheet: 'Номенклатура', sku: row.SKU }),
      freshRow = fresh && fresh.row,
      freshCurrent = threeDpNumber(threeDpMetrics(freshRow).availability),
```

**Why it is wrong.** `threeDpMetrics()` reads the count from a nested join
(`(row||{}).availability || {}` → `['Наявно зараз, шт']`). That join is produced only by
`skusAction3dp_`, which does `Object.assign({}, row, { availability: bySku[...] })` against
`SHEETS_3DP.availability` = `Наявність`. `getRowAction3dp_` performs **no join** — it returns the raw
row of whichever sheet was requested. A `Номенклатура` row has no `availability` key, so
`threeDpNumber(undefined)` yields `0`, the delta becomes `actual − 0`, and the POST carries
`expected_current: 0`. The API stock guard then rejects every correction with `STALE_WRITE`.

**Fix.** Read the availability sheet directly and do not route a raw row through `threeDpMetrics`,
whose contract is the `3dp_skus` join shape:

```js
const fresh = await call3dp('3dp_get_row', { sheet: 'Наявність', sku: row.SKU });
if (!fresh || !fresh.row) throw new Error('SKU не знайдено під час повторного читання.');
const freshCurrent = threeDpNumber(fresh.row['Наявно зараз, шт']);
```

`getRowAction3dp_` already whitelists `Номенклатура` and `Наявність`, so no Apps Script change is
needed. Handle `ROW_NOT_FOUND` explicitly — a missing availability row must surface as an error, never
read as `0`.

**Order matters:** the `!fresh.row` guard must run *before* the value is used, not after.

## WP2 — `3D-P-025` B2 · empty field is read as an actual count of 0 · BLOCKING

**File:** same, `previewThreeDpStockAdjustment()` and `saveThreeDpStock()`.

Both do `Number(String(input.value || '').trim())`. An empty field yields `0`, which passes
`Number.isInteger(actual) && actual >= 0`. Reachable path: type `97`, clear the field to retype —
`oninput` fires, the preview reads `Буде записано: −196 шт` and submit becomes enabled on an empty
input.

`0` is a legitimate actual count, so reject the **empty string**, not the value:

```js
const raw = String(input.value || '').trim();
if (raw === '') return { valid: false, delta: null, text: 'Вкажіть фактичну наявність.' };
```

Apply the same guard in `saveThreeDpStock()`, not only in the preview. Currently masked by WP1; it
goes live the moment WP1 lands, so both ship together.

### Verification required for WP1 + WP2 — read this before writing the test

The reason B1 escaped local checks: the test exercised the pure helper
`threeDpStockAdjustmentForActual` with hand-fed numbers (`196`, `195`). It never touched the
`3dp_get_row` response shape, which is exactly where the defect lived.

The replacement test must **mock the transport**, not the helper:

- [ ] Stub `call3dp` to return the real `3dp_get_row` shape for `Наявність`
      (`{ action, sheet, row: { SKU, 'Наявно зараз, шт': 195, … } }`) and assert the computed delta
      and the `expected_current` actually sent to `call3dpPost`.
- [ ] Stub it to return a `Номенклатура`-shaped row (no `availability` key) and assert the code
      does **not** silently compute from `0` — this is the regression guard for B1.
- [ ] Stub `ROW_NOT_FOUND` and assert an error surfaces.
- [ ] Cover the render-vs-submit divergence required by acceptance criterion §6: rendered `196`,
      fresh `195`, entered `97` → delta must be `−98` and `expected_current` must be `195`.
- [ ] Empty input, both in preview and at submit.

A green run that never sees the API response shape is not evidence.

## WP3 — `CRM-005` compact integrity tile

Full spec: `handoffs/handoff_CRM-005-UI_compact-integrity-tile_20260809.md`. Do not re-derive it here.

Headline points: the full-width `#crmIntegritySection` is removed; one card is appended to
`#summaryCards` following the `repeatRateCard` remove-then-append pattern but **outside** the
`monthly_summary` try block; click-to-run only, no `integrity_check` on page load (owner decision);
detail table moves to a `#crmIntegrityDetail` block hidden unless there are problems; last result
survives a data refresh.

---

## WP4 — `CRM-005` N3 · widen the `OPS-CRMINTEGRITY` trigger list

**File:** `AGENTS.md`.

The rule currently triggers on `Товари` / `РРЦ` / `Розхідники`. The handoff's proposed wording also
included **`Майстер_Товарів`**, described there as the single most-forgotten step, and
`apiIntegrityCheck_` already checks that tab. Add it, and mirror the change in the
`CODEX_WORKFLOW.md` cross-reference if the tab list is repeated there.

## WP5 — `CRM-005` N4 · test the five untested problem codes

**File:** `crm/apps-script/tests/integrity-check.test.mjs`.

Handoff §8 required each defect injected one at a time. Current coverage: `price_without_sku`,
`active_sku_without_rrp`, and the per-code cap. **Untested:** `missing_master_row`,
`master_row_inactive`, `formula_column_literal`, `duplicate_sku`, `rrp_mismatch_3dp`.

Handoff §9 warns that a check with false positives is worse than no check, because it trains everyone
to ignore the output. Those five are where a false positive would come from. For each: one injected
defect, assert exactly one problem of that code, and assert a clean fixture produces none.

`rrp_mismatch_3dp` needs `crm3dpGet_` stubbed — cover the matched-mismatch case, the
`skipped_missing_crm_rrp` case, and the `deferred` case when the remote call throws.

## WP6 — `CRM-005` N5 · correct the owner-QA expectation

**File:** the CRM-005 report in `diagnostics/`.

Acceptance criterion "reports the `ACC-3D-DITTO-410` `100` vs `90` disagreement" is not met
behaviourally: the implementation deliberately refuses to match an unkeyed manual `РРЦ` row to a 3D
SKU, so that SKU lands in `coverage.skipped_missing_crm_rrp` and no `rrp_mismatch_3dp` is emitted.
**The design choice is correct and stands** — do not change it to guess a match.

What must change is the written expectation. The handoff's owner-QA step 3 ("correct 90→100, confirm
the mismatch clears") cannot behave as written, and the current report drops that step silently. State
plainly in the report that the price comparison begins working only once `РРЦ` row 75 carries a keyed
SKU, and give the owner the corrected QA step.

## WP7 — `CRM-005` N7 · make the runtime measurable

**File:** `crm/apps-script/Code.gs`.

The handoff's risk note requires that this must not slow down `doGet`, and the runtime has never been
measured — 20 individual `getRange().getFormula()` round trips against `Майстер_Товарів` plus four
full-sheet `getValues()`/`getFormulas()` pairs.

Add an elapsed-milliseconds field to the `integrity_check` response and surface it in the tile's
`card-sub`. Read-only, trivial, and it turns an unknown into a number the owner sees on the first
live run. If it comes back slow, batching the `Майстер_Товарів` seed reads into one range read is the
obvious follow-up — do not do that now, measure first.

## WP8 — `CRM-005` N8 · state the menu decision

Handoff §5.2 asked for an explicit decide-and-justify on a `Booster CRM` spreadsheet menu entry. A
`createMenu('Booster CRM')` already exists in the script. No menu item was added and no decision was
recorded either way. Record the decision in the report. No code required if the answer is "no".

---

## Not in scope

- `3D-P-019` **phase A** — not authorised yet. It is gated behind CRM-005 being deployed and its
  integrity check run as a baseline. Phase A discovery is accepted and closed;
  `diagnostics/3D-P-019_fixture-phase-a-discovery_report_20260809.md` stands as delivered.
- `3D-P-019` **phase B** — decision F9 is locked
  (`handoffs/handoff_3D-P-019B_single-payer-per-sale_20260809.md`), implementation is not specced and
  not authorised.
- Repairing `РРЦ` rows 71–75. CRM-005 §6 forbids it; the check must report them so they serve as the
  first real test.
- `apiIntegrityCheck_` behaviour and the `integrity_check` response contract, apart from the WP7
  timing field.
- `N6` (the hardcoded `CRM_INTEGRITY_3DP_SKU_RE_` duplicating the SKU grammar) — accepted as-is. Add
  a one-line comment pointing at `plans/3D-P_sku-naming-convention_20260807.md` so the drift is at
  least visible. No refactor.

## Deliverables

1. `dashboard/booster-dashboard.html` — WP1, WP2, WP3.
2. `crm/apps-script/Code.gs` — WP7.
3. `crm/apps-script/tests/integrity-check.test.mjs` — WP5.
4. `AGENTS.md` (+ `CODEX_WORKFLOW.md` if the tab list repeats) — WP4.
5. Updated/new reports in `diagnostics/` — WP6, WP8, and one per fixed work package.
6. A diff summary. No commit, no push.

## Deployment sequence the owner will follow

1. Named Apps Script version created **before** anything is pasted.
2. `Code.gs` pasted into the bound main-CRM project; new Web App version published; repository mirror
   and `crm/apps-script/SOURCE_STATE.md` refreshed in the same session.
3. Dashboard uploaded; hard refresh.
4. Integrity tile clicked; the bounded output kept as the pre-change baseline.
5. `3D-P-025` owner QA on `ACC-3D-DITTO-410`: enter `97` → preview and ledger `−99`, stock reads `97`;
   enter `97` again → `Змін немає`, submit blocked, no ledger row; enter `100` → `+3`, stock `100`.

**Rollback coupling to state in the report:** the dashboard file carries WP1, WP2 and WP3 together.
Reverting it reverts all three. Do not describe any of them as independently reversible.
