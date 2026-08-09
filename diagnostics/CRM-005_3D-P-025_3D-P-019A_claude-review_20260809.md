# Claude (chat) review — CRM-005, 3D-P-025, 3D-P-019 phase A

Date: 2026-08-09 · Reviewer: Claude (chat) · Executor under review: Codex
Review method: working tree read against baselines, not the executor reports.

## Verdicts

| Task | Verdict |
|---|---|
| `CRM-005` | Deploy OK; non-blocking issues (N1–N5 below) |
| `3D-P-025` | **Return for changes** — B1 below |
| `3D-P-019` phase A | Discovery accepted; claims independently verified |

Deployment of the dashboard file is blocked by `3D-P-025` B1. See "Coupling" below.

## Baselines used

- `crm/apps-script/Code.gs` compared against the owner's V95 export
  `CodeJS - CRM (Версія 95, 8 серп. 2026 р., 19.31).txt` (CRLF-normalised).
  Result: **purely additive** — 2 hunks, 164 lines added, 0 lines removed. No existing V95 logic
  was modified.
- `dashboard/booster-dashboard.html`, `AGENTS.md`, `CODEX_WORKFLOW.md` compared against `git HEAD`
  via `git show HEAD:<path>` (object-store read only; no index lock created, owner commits unaffected).
- 3D-P API contract checked against the owner's V10 export
  `CodeJS 3D sheets (Версія 10, 8 серп. 2026 р., 21.53).txt`.

---

## B1 — BLOCKING · 3D-P-025 · the fresh reread queries the wrong sheet

**Where:** `dashboard/booster-dashboard.html`, `saveThreeDpStock()`.

```js
const fresh = await call3dp('3dp_get_row', { sheet: 'Номенклатура', sku: row.SKU }),
      freshRow = fresh && fresh.row,
      freshCurrent = threeDpNumber(threeDpMetrics(freshRow).availability),
```

**Why it fails.** `threeDpMetrics()` reads the current count from a nested join:

```js
function threeDpMetrics(row) { const availability = (row || {}).availability || {};
  return { availability: availability['Наявно зараз, шт'], ... }; }
```

That `availability` sub-object is created only by `skusAction3dp_` in the 3D-P API, which does
`Object.assign({}, row, { availability: bySku[...] })` where `bySku` is built from
`SHEETS_3DP.availability` = `Наявність`.

`getRowAction3dp_` (the `3dp_get_row` handler) performs **no join**. It returns
`rowObject3dp_(headers, values[index], ...)` — the raw row of the requested sheet. A
`Номенклатура` row therefore has no `availability` key.

**Resulting chain on every correction:**

1. `threeDpMetrics(freshRow).availability` → `undefined`
2. `threeDpNumber(undefined)` → `Number('')` → **`0`**
3. `threeDpStockAdjustmentForActual(97, 0)` → delta **`+97`** (correct value is `−99`)
4. POST `3dp_adjust_stock` with `expected_current: 0`, `delta: 97`
5. 3D-P API stock-adjustment guard: `equalCellValue3dp_(oldValue /* 196 */, 0)` → false →
   `STALE_WRITE`, "Stock changed after it was read. Refresh and retry."

**Net effect.** No inventory corruption — the server-side `expected_current` guard catches it — but
the feature never completes, and it fails with an error that points the owner at a phantom race
condition instead of at the real cause. It would only appear to work when true stock is `0`, since
`expected_current: 0` then matches by coincidence.

**Fails acceptance criterion** §6: *"The base value used for the calculation is read fresh at submit
time; prove it with a test where the stock changes between render and submit."* The executor's local
check exercised the pure helper `threeDpStockAdjustmentForActual` with hand-fed numbers (`196`,
`195`). It never exercised the `3dp_get_row` response shape, which is where the defect is.

**Required correction (executor: Codex, same executor that authored it):** read the fresh value from
the availability sheet and do not route it through `threeDpMetrics`, whose contract is the
`3dp_skus` join shape, not a raw row:

```js
const fresh = await call3dp('3dp_get_row', { sheet: 'Наявність', sku: row.SKU });
if (!fresh || !fresh.row) throw new Error('SKU не знайдено під час повторного читання.');
const freshCurrent = threeDpNumber(fresh.row['Наявно зараз, шт']);
```

Note `getRowAction3dp_` allows exactly `Номенклатура` and `Наявність`, so no API change is needed.
`ROW_NOT_FOUND` is thrown by the API for an absent availability row — handle it explicitly rather
than letting it read as `0`.

## B2 — BLOCKING (same return) · 3D-P-025 · an empty field is read as an actual count of 0

**Where:** `previewThreeDpStockAdjustment()` and `saveThreeDpStock()`.

Both do `Number(String(input.value || '').trim())`. For an empty field this yields `0`, which passes
`Number.isInteger(actual) && actual >= 0`.

Reachable path: the owner types `97`, then clears the field to retype. `oninput` fires on the clear,
so the preview reads **"Буде записано: −196 шт"** and the submit button is **enabled** on an empty
input. With a reason already typed, submission proceeds to the confirm dialog and, once B1 is fixed,
would write `−196` and zero the stock.

`0` is a legitimate actual count, so the fix is to reject the empty string specifically, not the
value `0`:

```js
const raw = String(input.value || '').trim();
if (raw === '') return { valid: false, delta: null, text: 'Вкажіть фактичну наявність.' };
```

This is currently masked by B1 (both sides evaluate to `0`, producing "Змін немає"). It becomes live
the moment B1 is corrected. Both must ship in the same patch.

## N-class · non-blocking

| ID | Task | Where | Issue |
|---|---|---|---|
| N1 | 3D-P-025 | `saveThreeDpStock()` | Submit enablement ignores the reason field. The button goes live before a reason exists; the `reason.length < 3` error only surfaces after the click. |
| N2 | 3D-P-025 | `saveThreeDpStock()` | `threeDpMetrics(freshRow)` is evaluated before the `if (!freshRow) throw` guard. Harmless today (optional chaining inside `threeDpMetrics` prevents a TypeError) but the guard is dead code as ordered. |
| N3 | CRM-005 | `AGENTS.md`, `OPS-CRMINTEGRITY` | The rule's trigger list is `Товари` / `РРЦ` / `Розхідники`. The handoff's proposed wording included **`Майстер_Товарів`**, described there as the single most-forgotten step. The check itself covers that tab; only the governing rule text is narrower than intended. Widen the rule. |
| N4 | CRM-005 | `crm/apps-script/tests/integrity-check.test.mjs` | §8 required each defect injected one at a time. Tests cover `price_without_sku`, `active_sku_without_rrp` and the per-code cap. **Five of seven codes have no test at all**: `missing_master_row`, `master_row_inactive`, `formula_column_literal`, `duplicate_sku`, `rrp_mismatch_3dp`. §9 warns that a check with false positives is worse than no check; these five are exactly where a false positive would come from. |
| N5 | CRM-005 | `crmIntegrityCheck3dpRrp_` | Acceptance criterion "reports the `ACC-3D-DITTO-410` `100` vs `90` disagreement" is **not met behaviourally**. The implementation deliberately refuses to match an unkeyed manual `РРЦ` row to a 3D SKU, so `ACC-3D-DITTO-410` falls into `coverage.skipped_missing_crm_rrp` and no `rrp_mismatch_3dp` is emitted. The criterion permits this if explained, and the report does explain it — but the handoff's owner-QA step 3 ("correct 90→100, confirm the mismatch clears") cannot behave as written, and the executor report drops that step without saying so. The design choice is sound; the QA expectation must be restated. |
| N6 | CRM-005 | `CRM_INTEGRITY_3DP_SKU_RE_` | The 3D SKU grammar `/^(?:BR\|FIG\|ACC-3D)-[A-Z0-9][A-Z0-9-]*$/` is hardcoded a second time, independent of `plans/3D-P_sku-naming-convention_20260807.md`. Drift risk when the convention changes. |
| N7 | CRM-005 | `crmIntegrityCheckMasterFormulaSeeds_` | 20 individual `getRange().getFormula()` round trips, plus 4 full-sheet `getValues()` + `getFormulas()` pairs. The handoff's risk note requires that this "must not slow down or break `doGet`". Runtime has never been measured. Time the first live run. |
| N8 | CRM-005 | handoff §5.2 | The handoff asked for an explicit decide-and-justify on a `Booster CRM` spreadsheet menu entry. A `createMenu('Booster CRM')` already exists in the script. No menu item was added and the report records no decision either way. |

## Checks that passed

- **Read-only proven, not asserted.** No `setValue`, `setValues`, `setFormula`, `appendRow`,
  `insertRow`, `insertColumn`, `deleteRow`, `deleteColumn`, `clear(`, `setNote` or `UrlFetchApp.fetch`
  appears anywhere in the 164 added `Code.gs` lines. The single outbound call is the existing
  authenticated `crm3dpGet_` helper, wrapped in try/catch with a `deferred` coverage field.
- **Not cached.** `CACHEABLE_ACTIONS` is unmodified; `integrity_check` is absent from it, as the
  handoff's risk note requires.
- **Additive registration.** `integrity_check` is registered in `handleApiAction_` immediately before
  the unknown-action fallback. No existing route altered.
- **No secret exposure.** `crmIntegritySafeRemoteCode_` reduces a remote error to a single
  `[A-Z][A-Z0-9_]{1,80}` token rather than echoing the message or URL.
- **Governance edits are in scope.** `AGENTS.md` and `CODEX_WORKFLOW.md` were explicitly requested by
  CRM-005 handoff §5.3. Both diffs are additive, one section each. Not a scope violation.
- **Every problem object names sheet + row range + code**, per §7.
- **3D-P-025 preserved constraints.** `Наявність!G` is never written by the dashboard; the ledger stays
  append-only; the zero-delta path returns before POSTing; `expected_current` is still sent, so the
  server `STALE_WRITE` guard remains the final gate.

## 3D-P-019 phase A — independent verification of the discovery claims

The report's load-bearing claim was re-derived rather than accepted:

> "Exact-source search found no main-CRM or 3D-P code comparison to the literal category `3D-друк`."

**Confirmed.** Searching the V95 export, the V10 export and the dashboard for `3D-друк` returns only
UI label strings and roadmap prose (`'Глобальні константи 3D-друку'`, nav labels, `ROADMAP_FLOW`
entries). No conditional, no equality test, no filter on the `Розхідники` category value. The rename
is therefore code-inert, as claimed.

Also confirmed: `Розхідники` column N is the dropdown formula seed and is currently the last column,
so appending `Платник` as column O does not displace it. `CRM_3DP_FIXTURE_REFERENCE_HEADER_` is the
only `Фурнітура` literal in V95 and is a *header* string on a different sheet — it does not collide
with the category rename.

Phase A is accepted as a discovery product. It authorises no writes; the setup action it recommends
is a separate, still-unwritten work package.

## Coupling — deployment consequence the reports do not state

`dashboard/booster-dashboard.html` now carries **both** CRM-005 (the integrity panel) and 3D-P-025
(the stock field) changes in one file. Both reports independently claim rollback is "revert the
dashboard to the preceding version". That is true for neither task individually — reverting either
one reverts both.

Practical consequence: **the CRM-005 dashboard button cannot ship today without also shipping the
broken 3D-P-025 stock panel.** The Apps Script half of CRM-005 is unaffected and can be deployed on
its own.

Recommended sequence:

1. Codex returns the 3D-P-025 fix (B1 + B2) against the same dashboard file.
2. Owner deploys `Code.gs` (named Apps Script version first), then the dashboard, once.
3. Owner runs the integrity check and keeps its bounded output as the pre-change baseline.
4. Only then does 3D-P-019 phase A become executable — its own gate already says so.

## Status

No Notion property was changed by this review. `bs-roadmap-write` acts only on an explicit owner
instruction. Recommended: all three remain `In progress`.

---

# Round 2 — 2026-08-09, after the Codex return

Verdict: **Deploy OK.** Both blocking defects are fixed, and the verification gap that let B1 through
is closed. Same method: working tree read against the V95 export and `git HEAD`, tests executed.

## B1 — fixed and regression-guarded

`saveThreeDpStock()` now calls `3dp_get_row` with `sheet: 'Наявність'`, checks `!freshRow` **before**
using it, and reads `freshRow['Наявно зараз, шт']` directly rather than through `threeDpMetrics`.
Codex added a guard I did not ask for and which is the right instinct: an empty returned value throws
`Поточна наявність не повернулась під час повторного читання` instead of degrading to `0`. That
closes the failure mode, not just the symptom.

## B2 — fixed in both places

`raw === ''` is rejected in `previewThreeDpStockAdjustment()` and, separately, in the
`saveThreeDpStock()` guard. The value `0` remains a legitimate count.

## The verification gap is closed

`tests/3d-p-025-stock-actual-count.test.mjs` extracts the three functions from the live dashboard HTML
by brace matching — it tests the shipped source, not a reimplementation — and stubs `call3dp` /
`call3dpPost` to assert what actually crosses the wire. Four cases:

| Case | Asserts |
|---|---|
| Empty input | zero GETs, zero POSTs |
| Rendered `196`, fresh `195`, entered `97` | GET targets `Наявність`; POST carries `expected_current: 195`, `delta: −98` |
| `Номенклатура`-shaped row returned | no POST, error surfaced — this is the B1 regression guard |
| `ROW_NOT_FOUND` thrown | no POST |

Case 2 is exactly the render-vs-submit divergence that acceptance criterion §6 demanded and that the
first round never exercised.

## WP3–WP8 verified

- **Tile.** Full-width `#crmIntegritySection` removed (0 occurrences). Card appended to
  `#summaryCards` outside the `monthly_summary` path, after the summary `try/catch` — so it still
  renders when the summary request fails. `crmIntegrityState` at module scope survives a data
  refresh; the refresh path re-renders both tile and detail. `role="button"`, `tabindex="0"`,
  Enter/Space handled. `card.onclick = runCrmIntegrityCheck` is a reference, not a call.
  `tests/crm-005-integrity-tile.test.mjs` asserts zero `integrity_check` requests on load, exactly one
  call site in the whole file, the clean/problems/error states, detail hidden unless problems, and
  that a refresh neither duplicates the tile nor discards the result.
- **WP4.** `OPS-CRMINTEGRITY` now lists `Майстер_Товарів`.
- **WP5.** All seven problem codes are asserted, plus `rrp_mismatch_3dp` in its matched,
  `skipped_missing_crm_rrp` and `deferred` variants.
- **WP6.** The report now states that the `100` vs `90` comparison begins working only once `РРЦ`
  row 75 carries a keyed SKU, and gives the corrected owner-QA expectation.
- **WP7.** `elapsed_ms` is set in `crmIntegrityFinalize_` and passed from both call sites — including
  the early return when headers are missing — and surfaced in the tile sub-line.
- **WP8.** The menu decision is recorded and justified: the existing `Booster CRM` menu runs
  operational actions, and a duplicate control would not make the output clearer.
- **N6.** Comment added above `CRM_INTEGRITY_3DP_SKU_RE_` pointing at the SKU convention plan.

`crm/apps-script/Code.gs` remains **purely additive** against V95: 168 lines added, 0 removed, no
write API anywhere in the added code. The dashboard diff is 5 hunks, 47 added, 6 removed — every
removal is the superseded delta-based stock code.

## Executor claim about the failing legacy test — independently confirmed

Codex reported that `tests/3d-p-013-dashboard-ui-regression.test.mjs` fails on a stale expectation
and that this delivery did not cause it. Verified by running the same test against the `git HEAD`
dashboard in an isolated tree: **it fails identically there**, and `renderThreeDpInformation()` is
byte-identical between HEAD and the current file. The test's regex expects the function body to begin
with `const records=threeDpState.skus.map(...)`, but a `renderThreeDpSyncJournal();` call was inserted
ahead of it in earlier work. Pre-existing, unrelated, claim accurate.

**Worth its own task.** A regression guard that is permanently red gives no signal and trains everyone
to skim past a failing suite — the same argument CRM-005 §9 makes about false positives. Fixing the
expectation is a few minutes of work and should not ride along inside an unrelated delivery.

## Residual, carried forward

- `rrp_mismatch_3dp` stays dormant for `ACC-3D-DITTO-410` until `РРЦ` row 75 gains a keyed SKU. This
  is the accepted design, now documented. The owner-QA step from the original handoff no longer
  applies as written.
- Integrity-check runtime is still unmeasured; `elapsed_ms` exists so the first live click measures
  it. If it is slow, batching the `Майстер_Товарів` seed reads into one range read is the follow-up.
- Dashboard rollback remains shared between `3D-P-025` and `CRM-005`. Not independently reversible.
