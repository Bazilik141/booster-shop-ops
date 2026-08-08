# Handoff — 3D-P-015-FIX2: planned defect rate as a global cost constant

Date: 2026-08-08
Executor: **Codex** · model=Sol · effort=medium-high
Justification: same file family and same round as `3D-P-015` and `3D-P-015-FIX1`. Never swap
executor mid-round. Owner decides.

---

## 1. Task ID

`3D-P-015-FIX2` — a bounded delta on `3D-P-015`, raised by the owner on 2026-08-08 **after** the
live migration had already been applied to the workbook.

## 2. Context

`3D-P-015` and `3D-P-015-FIX1` are **deployed and live** as of 2026-08-08:

- 3D-P Apps Script published (new Web App version, replacing V7 — owner must supply the number);
- main CRM Apps Script published (new version replacing V92 — owner must supply the number);
- `3dp_setup_3dp015` executed live and confirmed by evidence
  `diagnostics/3D-P-015_live-preflight_20260808_194233.json`;
- `Номенклатура` now has `Q/R/S`, `Продажі` has `U/V/W`, `Аналітика` is rebuilt post-split.

This task changes the **production-cost formula itself**, which the deployed migration owns and
normalizes. It therefore cannot be done by hand in the sheet: `validate3dp015Schema3dp_()` compares
`Номенклатура!K` against the known formula shapes and throws `SETUP_ANCHOR_MISMATCH` on anything
else, and `normalizeNomenclatureFinalCostFormula3dp_()` would overwrite a hand edit on the next run.

**Already done by the owner, no work required:** `Налаштування!B2` (printer power) changed from
`0.17` to `0.15` kW. Verified against the live cost: `ACC-3D-DITTO-410` reads `K = 42.46 грн` with
`G=1.39, H=27.64, I=1000, J=900`, which reconciles only at `B2 = 0.15`
(`24.876 + 0.9007 + 16.68 = 42.457`). `B2` is an owner-editable constant, so this needed no code
change and none should be added.

## 3. Goal

Fold a planned defect rate into Serhiy's production cost, as a single global constant the owner can
change without a code change.

## 4. Owner decisions — 2026-08-08, locked

| # | Decision |
|---|---|
| X1 | **The defect rate is applied as a simple uplift: cost × (1 + rate).** At 10% this is `×1.1`. The owner was shown the alternative (`÷ (1 − rate)`, i.e. "print 10, keep 9", which yields `×1.111`) and explicitly chose the simple uplift. Do not implement the division form and do not re-argue it. |
| X2 | **One global rate for all SKUs**, stored in the existing `Налаштування` constants block alongside printer power, electricity price and amortization. No per-SKU defect column. |
| X3 | Default value on creation: **`0.1`** (10%). The owner may change it afterwards without a code change. |

Note for the record: this reverses the earlier scope decision in
`diagnostics/3D-P_gap-register-and-work-plan_20260807.md` §3.3, which listed "planned defect rate in
the cost formula" as deliberately dropped, with defect tracked only as a `Друк-лог` count. That
exclusion is now withdrawn by the owner. Update §3.3 so the register does not contradict the live
formula.

## 5. What to change

### 5.1 New constant row

Extend the `Налаштування` block from 4 rows to 5:

| Cell | Value |
|---|---|
| `A5` | `Планований брак, частка` |
| `B5` | `0.1` |
| `C5` | `частка (0.1 = 10%)` |

`setupGlobalSettings3dp_()` currently builds and validates `A1:C4` only, and paints `B2:B4` blue.
Extend both to row 5. It must:

- create row 5 when the sheet is created from scratch;
- **add row 5 without touching `B2:B4` values** when the block already exists live — the owner's
  `B2 = 0.15` must survive;
- leave an existing `B5` value alone; only fill `0.1` when `B5` is empty;
- keep the anchor validation on columns `A` and `C` and extend it to row 5;
- paint `B5` the same editable blue as `B2:B4`.

### 5.2 Cost formula

`nomenclatureFinalCostFormula3dp_()` becomes:

```
=IF(A<r>="";"";IFERROR((H<r>/I<r>*J<r> + G<r>*Налаштування!$B$2*Налаштування!$B$3 + G<r>*Налаштування!$B$4) * (1+Налаштування!$B$5);""))
```

The uplift multiplies the **whole** production cost — material, electricity and amortization — not
only material. A failed print consumes all three. Fixture stays out of `K`, unchanged from
`3D-P-015`.

Expected result for `ACC-3D-DITTO-410` at `B5 = 0.1`: `42.46 → 46.71 грн`. State the computed value
in the report.

### 5.3 Migration must accept three formula generations

`validate3dp015Schema3dp_()` currently accepts exactly two shapes for `Номенклатура!K` — legacy
(with `+N`) and the 3D-P-015 shape (no `N`, no defect). It must now also accept the new shape (no
`N`, with defect), or the very next migration run throws `SETUP_ANCHOR_MISMATCH` on a live workbook
that this task itself created.

`normalizeNomenclatureFinalCostFormula3dp_()` rewrites to the newest shape, as today.

The migration stays idempotent: a second run against an already-updated workbook must report
`already_applied: true` and append no `_Аудит_API` record.

### 5.4 Write guard and API surface

- The `3dp_write` guard at `Code.gs:1392` rejects anything outside `Налаштування!B2:B4`. Widen it to
  `B2:B5`.
- The capability description at `Code.gs:1762` still says `Налаштування!B2:B4` — update the string.
- The Addendum-1 precondition check at `Code.gs:1897` also references `B2:B4`. Decide deliberately
  whether it should require `B5`; the safe answer is **no** — it guards a historical prerequisite
  and widening it could block a rerun on a workbook that predates `B5`. Whatever is chosen, say so
  in the report.
- `SERHIY_MANUAL_COLUMNS_3DP` has no `Налаштування` entry. Leave it that way — Serhiy must not set
  the defect rate, because it changes his own reimbursement.

### 5.5 Dashboard

`dashboard/booster-dashboard.html` reads and writes the constants block through
`3dp_get_range` with `range:'A1:C4'` at two call sites (~line 820 and ~line 923). Both must become
`A1:C5` so the new constant is visible and editable in the existing settings panel. No new panel.

## 6. Do not touch

- The `÷ (1 − rate)` alternative. Rejected by the owner — see X1.
- Fixture handling: `Номенклатура!N` stays a reference price and stays out of `K`.
- `Продажі` frozen columns `F/U/V/W` and the frozen-literal contract. Past sales must not move —
  `F` is frozen at creation, so a later defect-rate change cannot rewrite them. Verify this rather
  than assume it.
- `Аналітика` column set and the post-split margin formulas from `3D-P-015-FIX1`.
- The 50/50 split, the two-track model, the «ЗБИТКОВИЙ — рішення власника» rule.
- The recommended-РРЦ `pending` placeholder.
- `Друк-лог` defect counting. It stays an actual-count field; this task adds a *planned* rate and
  the two must not be conflated in any label.

## 7. Likely files / areas

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
3d-print/apps-script-3dp-api/tests/live-3D-P-015-preflight.ps1     # capture B5 in evidence
3d-print/apps-script-3dp-api/tests/live-3D-P-015-migrate.ps1       # assert the new K shape
dashboard/booster-dashboard.html
diagnostics/3D-P_gap-register-and-work-plan_20260807.md            # §3.3 exclusion withdrawn
```

Main CRM `Code.gs` is expected to need **no** change: the hook reads `Номенклатура!K` as a value,
not as a formula. Confirm rather than assume.

## 8. Also fix in this pass — three live-script defects found during the 2026-08-08 owner run

These blocked or misled the owner during the real migration and must not survive into the next one.

1. **`$requestTimeoutSeconds = 30` is too short for the migration POST.** The real
   `3dp_setup_3dp015` run exceeded it; PowerShell reported a client timeout while Apps Script had in
   fact completed the migration successfully. The owner was left unable to tell whether the
   workbook had been changed. Raise the POST timeout to at least 300 s, and on timeout print an
   explicit message that a client timeout does **not** mean the migration failed, naming the checks
   that distinguish the cases.
2. **`Assert-HeaderRow -Expected` cannot bind an array containing empty strings.** The `Аналітика`
   expected-header array ends with two `''` entries (columns `M`, `N`), so
   `live-3D-P-015-migrate.ps1` dies with *"Cannot bind argument to parameter 'Expected' because it
   is an empty string"* — reproduced twice on the owner's machine. It fails **after** every
   substantive check has passed, so it also prevents the evidence file from being written. Add
   `[AllowEmptyString()]` to the parameter, or compare `A3:L3` and assert `M3:N3` emptiness
   separately.
3. **`live-3D-P-015-preflight.ps1` reports `fixture_price_n` as `null` when the cell holds a
   value.** In `diagnostics/3D-P-015_live-preflight_20260808_194233.json`, `FIG-CHARM-001` shows
   `fixture_price_n: null`, while `Аналітика!D4` in the same file reads `3` — and `D4` is computed
   live from `Номенклатура!N2`. The two contradict each other, so the evidence file is wrong. The
   likely cause is PowerShell unrolling the single-row `values` array returned by `3dp_get_range`,
   making `$Range.values[0][3]` index into a string instead of a row. Force array semantics
   (`@($response.values)`) and add a regression assertion.

## 9. Acceptance criteria

- [ ] `Налаштування` has the 5-row block; `A5`/`C5` labels exact; `B5` blue and owner-editable.
- [ ] Running setup against the live-shaped workbook **preserves `B2 = 0.15`** and any existing
      `B5`.
- [ ] `Номенклатура!K` multiplies the full production cost by `(1 + Налаштування!$B$5)`.
- [ ] `ACC-3D-DITTO-410` computes `46.71 грн` at `B5 = 0.1`, hand-checked and stated in the report.
- [ ] Setting `B5 = 0` reproduces the pre-FIX2 cost exactly (`42.46`).
- [ ] The migration accepts all three `K` generations and is still idempotent on a second run.
- [ ] `3dp_write` accepts `Налаштування!B5` for the owner and rejects `B6`; Serhiy is rejected for
      `B5`.
- [ ] Dashboard settings panel shows and saves the defect rate; both `A1:C4` call sites updated.
- [ ] A pre-existing frozen sale row's `F/U/V/W` do not change when `B5` changes.
- [ ] Migrate script survives a slow POST and no longer dies on the empty-string header binding.
- [ ] Preflight evidence reports the real `Номенклатура!N` value, with a regression test.
- [ ] Gap register §3.3 no longer lists the defect rate as deliberately excluded.

## 10. QA / smoke test

Local, by the executor: setup-from-scratch and setup-against-existing paths, `B5 = 0` and
`B5 = 0.1` cost cases, owner/Serhiy write matrix for `B5`, migration idempotency, and both
PowerShell fixes exercised against the existing mock.

Owner, after deployment:

1. Open the settings panel in the dashboard, confirm the defect rate is visible and editable.
2. Confirm `ACC-3D-DITTO-410` cost moved from `42,46` to `46,71 грн`.
3. Set the rate to `0`, confirm the cost returns to `42,46`, then set it back to `0.1`.
4. Confirm the `Аналітика` margin for that SKU dropped by the same amount the cost rose.

## 11. Rollback note

The named Google Sheets version created before the `3D-P-015` migration is **older than the current
live state** and is no longer a clean rollback for this task. **The owner must create a fresh named
version before deploying FIX2**, e.g. `Before 3D-P-015-FIX2 2026-08-08`.

Beyond that, the change is a single formula and one constant row: setting `B5 = 0` neutralises the
uplift without any code rollback, which is the fastest safe undo if the number looks wrong in
production.

## 12. Recommended status after execution

`3D-P-015` stays **In progress** until the owner completes the combined QA for `3D-P-015`, FIX1 and
FIX2. Claude (chat) is the Notion writer.

## Risk classification

Not an SEO risk zone. It **is** a financial-model risk zone: it changes the number that is
reimbursed to Serhiy and that the 50/50 split is computed on. Rollback plan and focused smoke test
mandatory; owner is the only deployment gate.
