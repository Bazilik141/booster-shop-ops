# Codex Report — 3D-P-015: price model rebuild around actual RRP

Date: 2026-08-08

## Scope and deployment state

The original 3D-P-015/FIX1 evidence below is historical pre-deployment evidence. Per the owner
handoff for FIX2, the 3D-P and main CRM Apps Script versions were published and
`3dp_setup_3dp015` was executed live on 2026-08-08; the replacement version numbers still need to
be recorded. **FIX2 itself is local-ready only: Codex did not paste Apps Script source, publish a
Web App version, or change a workbook cell.**

The owner-run live preflight was saved before any planned schema write in
`diagnostics/3D-P-015_live-preflight_20260808_174949.json`:

- 3D-P mirror matches the supplied live export (V7, published 2026-08-03 20:55);
- `Номенклатура!O:P` are unchanged technical columns; `Q:S` are empty;
- both real SKU `K` formulas still contain the legacy unconditional `+N`;
- `Продажі!T` is still `CRM row number`; `U:W` are empty;
- `Аналітика!A3:N17` contains the legacy three-scenario calculator and an existing `#REF!` row.

The current CRM mirror has a V92 live baseline (2026-08-08), so the CRM hook was changed only
against that recorded source, not an assumed older script.

## Endpoint correction

A prior manual preflight attempt did prompt for credentials; the main CRM `/exec` URL and CRM
token were entered instead of the 3D-P pair. The earlier Codex note incorrectly described this as
an unset `BOOSTER_3DP_URL`. Both `live-3D-P-015-preflight.ps1` and
`live-3D-P-015-migrate.ps1` now first perform a read-only `3dp_overview` identity probe and
require its 3D-P response contract. A CRM endpoint fails with a clear “use the 3D-P URL/token,
not main CRM” message; the migration sends no POST request in that case.

## Implemented changes

- 3D-P Apps Script adds guarded, idempotent `3dp_setup_3dp015` migration:
  - relabels `Номенклатура!N` as a fixture reference price;
  - adds owner-only `Q` actual RRP, `R` buyout price, and `S` model link after technical `O:P`;
  - removes `+N` from every approved existing `K` formula;
  - adds frozen `Продажі!U:W` after `T` and requires new appended sales to include numeric
    `F/U/V` plus payer `W`;
  - preserves historical `F` values/formulas, but converts the approved `I/K/L` formulas to
    payer-aware calculations;
  - synchronizes `Аналітика!A3:N17` from current SKU rows on every setup run, with actual RRP and
    explicit `pending` recommended RRP; stale rows, legacy scenarios, and current `#REF!` are removed;
  - writes a `SETUP_3DP015` entry into `_Аудит_API` and restores snapshots if the migration fails
    before completion.
- Main CRM sync fetches current `Номенклатура!K/Q/N` before creating a new 3D-P sale and appends
  frozen literals `F/U/V/W` (`W=власник` by default).
- If `K` production cost or `Q` actual RRP is blank/invalid, the CRM sale remains successful, no
  3D-P row is appended, and `_Журнал_3DP_синхронізації` records
  `skipped_missing_cost_or_rrp`. An invalid filled `N` is also explicit (`skipped_invalid_fixture_price`).
- Dashboard now reads/writes durable `Q/R/S`; old browser-local model links are surfaced for a
  deliberate one-time migration and are never silently overwritten or deleted.
- Serhiy's local server only adopts the relabelled fixture reference header; it receives no
  pricing write permission.
- 3D-P-008 Addendum #3 is marked superseded by this task, and the dashboard mirror records the
  local-ready/not-deployed state without marking the task done.

## Formula reconciliation

Per sale unit, with `E` sale price, `F` Serhiy production cost, `G` packaging, `V` frozen
fixture price, and split `H`:

| Fixture payer | Margin `I` | Serhiy accrual `K` | BoosterShop income `L` |
|---|---|---|---|
| `власник` | `E - F - G - V` | `F + H*I` | `I*(1-H)` |
| `Сергій` | `E - F - G` | `F + H*I + V` | `I*(1-H) - V` |

Hand check at `E=99`, `F=12.50`, `G=10`, `V=4`, `H=0.5`, quantity `1`:

- owner-paid: `I=72.50`, `K=48.75`, `L=36.25`; `K+L=85.00 = E-G-V`;
- Serhiy-paid: `I=76.50`, `K=54.75`, `L=34.25`; `V` is reimbursed separately from `F`, and
  `K+L=89.00 = E-G`.

Thus owner-paid fixture cost is deducted once (not duplicated in `G`), while Serhiy-paid fixture
cost is never merged into the print-cost field `F`.

## FIX1 amendments — maintainability, margin semantics, truthful journal

- `3dp_setup_3dp015` now re-syncs `Аналітика!A4:N17` on every invocation. A matching formula row
  is untouched; a new SKU receives `F=0.5`; a changed owner `F` remains attached to its SKU; and
  stale rows are cleared without touching the market-reference block below row 17. The 14-SKU guard
  still rejects the run before any write. A no-change re-run is `already_applied: true` and adds no
  audit row.
- `Аналітика!D3` now has a durable note: its `Номенклатура!N` fixture figure is an owner-paid
  planning default, while actual payer attribution lives only in `Продажі!W`.
- `Аналітика!I` now means BoosterShop's **post-split** share, as its unchanged label requires;
  `J` is that share as a percentage of actual RRP. `K` derives separately from the base amount,
  rather than from `I`, so the Serhiy accrual remains correct at any split fraction.

  Hand check: `G=99`, `C=12.50`, `D=4`, `F=0.5` gives `I=41.25`, `J=41.67%`,
  `K=53.75`, and `I+K=95.00=G-D`.
- The dashboard pricing-class grid retains its correct pre-split arithmetic
  `(РРЦ − собівартість Сергія) ÷ РРЦ`; its displayed tag now says `Клас маржі до split` and
  exposes the same definition in a tooltip. No grid arithmetic changed.
- A `ROW_NOT_FOUND` or `ROW_FILTERED` lookup from the 3D-P `Номенклатура` now produces
  `skipped_sku_not_in_nomenclature` for that CRM row and continues with sibling 3D-P rows. Other
  API failures still re-throw and journal as `skipped_api_error`.

## FIX2 amendments — planned defect rate in current production cost

- `Налаштування!A1:C5` extends the existing constants block with `A5=Планований брак, частка`,
  default `B5=0.1`, and `C5=частка (0.1 = 10%)`. Existing `B2:B4`, including the owner's
  `B2=0.15`, and a non-blank existing `B5` are preserved. `B2:B5` are owner-editable blue cells;
  Serhiy still has no settings write permission.
- `Номенклатура!K` now applies the owner-locked simple uplift to the full production base:

  ```text
  =IF(A<r>="";"";IFERROR((H<r>/I<r>*J<r>+G<r>*'Налаштування'!$B$2*'Налаштування'!$B$3+G<r>*'Налаштування'!$B$4)*(1+'Налаштування'!$B$5);""))
  ```

  Fixture `N` remains outside `K`; the rejected `÷(1-rate)` form is not present. With
  `G=1.39`, `H=27.64`, `I=1000`, `J=900`, `B2=0.15`, `B3=4.32`, `B4=12`, and `B5=0.1`, the exact
  base is `42.45672`; the formula returns `46.702392`, displayed as **46.70 грн**. The owner
  explicitly accepted this no-`ROUND` result on 2026-08-08. `B5=0` returns the prior displayed
  `42.46 грн`.
- `3dp_setup_3dp015` snapshots the settings block, creates/repairs row 5, and accepts all three
  historical `K` shapes: legacy `+N`, 3D-P-015 without `N`, and FIX2 without `N` plus `B5`. It
  normalizes only to the newest form; a no-change rerun remains `already_applied: true` with no audit row.
- The Addendum-1 prerequisite intentionally remains `Налаштування!A2:A4`: it verifies the
  historical base block and must not reject a workbook that predates `B5`.
- Dashboard now loads `Налаштування!A1:C5`, exposes B5 in the existing owner settings panel, and
  applies the same `×(1+B5)` uplift in its batch-cost preview. This prevents the preview from
  disagreeing with `Номенклатура!K`; no new settings panel or CSS override was added.
- CRM needs no source change: it reads the calculated `Номенклатура!K` value, then freezes that
  literal into a new sale's `F`. A later B5 edit cannot move existing `Продажі!F/U/V/W`.
- Live scripts now capture `Налаштування!A1:C5`. The `K:N` evidence range uses explicit
  `${rowNumber}` interpolation, so `fixture_price_n` records N rather than silently requesting
  only K. Migration POST timeout is 300 seconds and explicitly warns that client timeout is not
  proof of failure; its header assertion accepts empty `Аналітика!M:N` cells.

## Files touched

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
3d-print/apps-script-3dp-api/tests/live-3D-P-015-preflight.ps1
3d-print/apps-script-3dp-api/tests/live-3D-P-015-migrate.ps1
3d-print/apps-script-3dp-api/tests/live-positive-audit-smoke.ps1
crm/apps-script/Code.gs
crm/apps-script/tests/3dp-sync-journal.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/3dp-sync-journal-static.test.mjs
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/server.mjs
3d-print/serhiy-local-server/tests/server-local.test.mjs
handoffs/handoff_3D-P-008_apps-script-api-foundation_20260731.md
diagnostics/3D-P_gap-register-and-work-plan_20260807.md
```

## Local verification

```text
node crm/apps-script/tests/3dp-sync-journal.test.mjs
  PASS — includes fail-open missing K/Q plus missing `Номенклатура` SKU: sibling sale
  continues, truthful `skipped_sku_not_in_nomenclature`, and non-OK API errors still fail open

node 3d-print/apps-script-3dp-api/tests/api.test.mjs
  PASS — setup idempotency, frozen append contract, Q/R/S owner-only, Serhiy denial;
  includes analytics re-sync/F preservation, stale-row removal, 14-SKU guard, post-split hand check,
  B5 owner/Serhiy write matrix, all three K formula generations, frozen-sale immutability, and 46.70 check

node dashboard/tests/3dp-sync-journal-static.test.mjs
  PASS — pre-split margin label and formula tooltip

npm --prefix 3d-print/serhiy-local-server test
  PASS — 4/4

PowerShell parser: PASS
Local 3D-P API mock: PASS — preflight reports `fixture_price_n=3`; migration accepts empty
`Аналітика!M:N` headers, reads B5, and issues exactly one expected `3dp_setup_3dp015` POST
Migration timeout guard: PASS — POST limit is 300 seconds and timeout guidance is present
Local CRM-endpoint mock: PASS — both 3D-P-015 scripts reject a non-3D-P `3dp_overview`
response with the corrective message and make only a GET identity probe; migration POST count is zero
Dashboard inline script: PASS
git diff --check: PASS for task files
```

The UI diff review found no newly introduced `!important`, `setTimeout`, fixed/absolute
positioning, or magic-pixel override. The product form's pre-existing `margin-top:14px` remains
on a minified changed line; it was confirmed present in `HEAD` and was not introduced here.

## Historical 3D-P-015 owner deployment sequence

1. Create a named Google Sheets version first, for example `Before 3D-P-015 migration 2026-08-08`.
2. Paste `3d-print/apps-script-3dp-api/Code.gs` into the bound 3D-P project and publish a new Web
   App version. Do not change Script Properties.
3. From this repository root, run the explicit migration and save safe evidence:

   ```powershell
   .\3d-print\apps-script-3dp-api\tests\live-3D-P-015-migrate.ps1 -ConfirmLiveWrite -SaveEvidence
   ```

4. Immediately paste `crm/apps-script/Code.gs` into the bound CRM project and publish a new Web
   App version. Avoid creating 3D-P sales in the short interval between steps 3 and 4: the old CRM
   hook will be safely journalled as skipped after the new frozen-value schema, but requires
   reconciliation rather than automatic backfill.
5. Update the local dashboard and Serhiy server from these files, restart the local server if it is
   running, then hard-refresh the dashboard (`Ctrl+F5`).
6. Export both live `Code.gs` files again and record their new Web App versions before treating the
   deployment as mirror-verified.

## FIX2 owner deployment sequence

1. Create a fresh named Google Sheets version, for example `Before 3D-P-015-FIX2 2026-08-08`.
2. Paste the updated 3D-P `Code.gs`, publish a new Web App version, and record that version number.
   Do not change Script Properties. CRM source does not change for FIX2.
3. Run the existing migration once and save evidence:

   ```powershell
   .\3d-print\apps-script-3dp-api\tests\live-3D-P-015-migrate.ps1 -ConfirmLiveWrite -SaveEvidence
   ```

4. Update the local dashboard from this worktree and hard-refresh it. Do not rerun the migration
   immediately if its client waits longer than 300 seconds; first follow the script's read-only
   preflight and `_Аудит_API` checks.

## Post-deploy owner QA

- [ ] `Номенклатура!Q:S`, `Продажі!U:W`, and `_Аудит_API` `SETUP_3DP015` exist exactly as expected.
- [ ] Enter non-empty valid `K` inputs and `Q` for a test SKU; create a test sale and confirm frozen
      numeric `F/U/V/W`.
- [ ] Change that SKU's `N` and `Q` afterwards; confirm the sale's `F/U/V/W` do not move.
- [ ] Set `W=Сергій` on the test row; confirm `K` rises by `quantity*V` and `L` falls by the same
      amount, while `F` does not change.
- [ ] Add and then remove a test SKU in `Номенклатура`; re-run setup each time and confirm the
      matching `Аналітика` row appears/clears while an owner-edited `F` on another SKU remains.
- [ ] With `G=99`, `C=12.50`, `D=4`, `F=0.5`, confirm `Аналітика` shows `I=41.25`, `J=41.67%`,
      `K=53.75`; read the note on `Аналітика!D3`.
- [ ] Create a controlled sale with missing `K` or `Q`; confirm CRM save succeeds, 3D-P append does
      not occur, and CRM journal shows `skipped_missing_cost_or_rrp`.
- [ ] Create a controlled order with one 3D-P SKU absent from `Номенклатура` and one present SKU;
      confirm the former journals `skipped_sku_not_in_nomenclature` and the latter still syncs.
- [ ] Save a `Q`, `R`, and long `S` link from dashboard; hard-refresh and verify persistence. Check
      desktop, tablet, mobile, hover/focus, and long-content presentation.
- [ ] Confirm Serhiy's server has no pricing controls and a Serhiy-token write to `Q`, `R`, or `S`
      is rejected.
- [ ] Confirm the dashboard settings panel shows `Планований брак, частка`; set B5 to `0`, verify
      `ACC-3D-DITTO-410` returns to `42.46`, then restore `0.1` and verify `46.70`.
- [ ] Verify its `Аналітика` cost/margin reflects the new K value, while a pre-existing sale's
      frozen `F/U/V/W` values remain unchanged.

## Rollback and remaining risks

- The FIX2 migration rolls back its captured settings, formula, sales, and analytics ranges if it
  fails during execution. After a successful run, setting B5 to `0` is the fastest financial-model
  neutralization; use the fresh named spreadsheet version for a full rollback.
- Source rollback is owner-controlled: use the 3D-P version recorded immediately before FIX2 if
  live QA finds a defect. CRM is not redeployed for FIX2; reconcile any sale made during the brief
  3D-P deployment window if necessary.
- The blank `K`/`Q` values above are historical `174949` pre-deployment evidence only. Run the
  current read-only preflight before relying on any live formula or sync state.
- No FIX2 live migration, Apps Script deployment, dashboard visual session, or external QA has
  been performed from this worktree.
