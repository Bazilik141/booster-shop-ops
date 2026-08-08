# Handoff — 3D-P-024: make print-time entry safe and natural everywhere

Date: 2026-08-08
Executor: **Codex** · model=Sol · effort=medium-high
Justification: same file family and same round as `3D-P-015` and its two fixes. Never swap executor
mid-round. Owner decides.

---

## 1. Task ID

`3D-P-024` — new roadmap task, raised by the owner on 2026-08-08 during live QA of `3D-P-015`.
Next free ID per `ROADMAP_SOP.md` D7 (`3D-P-022` and `3D-P-023` are taken; `3D-P-009` stays unused).

## 2. Context — this is a live data-integrity defect, not a cosmetic complaint

Print time is stored and consumed everywhere as **decimal hours** (`1.65` = 1 h 39 min). Nothing in
the UI says so, and the natural way to write "одна година тридцять девʼять" is `1:39` or `1,39` —
both of which are wrong, silently, with no error.

Two confirmed live incidents:

1. **`FIG-CHARM-001`** had `Номенклатура!G2` entered as a clock time. Google stored it as
   `0.1032` (a fraction of a day) and the cost formula consumed it as **0.1 hours** instead of
   2 h 28 min. Its production cost was understated by roughly 24×, undetected, for weeks. Evidence:
   `diagnostics/3D-P-015_live-preflight_20260808_174949.json`, `Аналітика` row 5, `E = 0.1032`.
2. **`ACC-3D-DITTO-410`**, 2026-08-08: the owner entered `1,39` meaning 1 h 39 min. Real value is
   `1.65`. Cost read `46.70 грн` instead of `50.32 грн` — a 7% understatement that would have gone
   straight into Serhiy's reimbursement and the 50/50 split base.

Both failures are silent. There is no validation, no unit hint, and no upper/lower bound. The
number simply comes out wrong and every downstream figure inherits it.

## 3. Goal

The owner and Serhiy type print time the way a human writes it, and the stored value is always
correct decimal hours. No mental arithmetic, no `=1+39/60` workarounds.

## 4. Approach — convert at entry, do not change storage

**Storage stays decimal hours.** Do **not** switch the columns to Google duration values and do not
change any formula. Decimal hours is consumed by `Номенклатура!K`, `Аналітика!E` and `!L`
(`Прибуток Сергію/год друку` divides by it), the batch-draft calculator, and
`3d-print/serhiy-local-server/lib/calculator.mjs`. Changing the stored unit means changing all of
them at once, which is a far larger blast radius for no additional benefit.

Instead, every **entry point** accepts human input and normalises to decimal hours before it is
stored.

Accepted input forms, all three must work:

| Typed | Stored |
|---|---|
| `1:39` | `1.65` |
| `1 год 39 хв` / `1год39хв` / `1h39m` | `1.65` |
| `1,65` or `1.65` | `1.65` |

Rounding: keep at least 4 decimal places. Do not round to 2 — a 6-minute print is `0.1`, and
over-rounding short prints distorts amortization.

## 5. What to change

### 5.1 Google Sheet — `onEdit` normalisation

The 3D-P Apps Script is bound to the workbook but currently has **no `onEdit` handler** (verified:
no `onEdit` in `3d-print/apps-script-3dp-api/Code.gs`). Add one, scoped tightly.

Watched cells only:

- `Номенклатура!G2:G` — `Час друку за од., год`
- `Друк-лог!D2:D` — `Час друку факт, год`

Behaviour:

- If the user typed a clock value, Sheets stores it as a date/time serial **before** `onEdit` fires.
  Detect that case and convert to decimal hours. **Verify the conversion factor against a real cell
  rather than assuming it** — a bare `1:39` is stored as a fraction of a day, so decimal hours is
  `value × 24`, but confirm this live before shipping, including for values over 24 hours.
- If the user typed a plain number, leave it untouched.
- If the user typed a recognised text form (`1 год 39 хв`), replace it with the decimal number.
- Always reset the cell's number format to plain number after writing, so the next edit is not
  re-interpreted as a duration.
- Never recurse: writing back must not retrigger normalisation into a loop.
- Blank stays blank.

`onEdit` is a simple trigger and runs as the editing user. Only the owner edits this workbook
(Serhiy has no direct access — decision 2026-07-31), so this is acceptable. State that assumption in
the report.

### 5.2 Sanity guard on implausible values

Add a visible warning — cell note, or a status the dashboard surfaces — when a stored print time is
outside a plausible band, suggested `0.02 h` (≈1 min) to `100 h`. This is exactly the check that
would have caught `FIG-CHARM-001`'s `0.1032`.

**Warn, never block.** Consistent with the fail-open rule locked 2026-08-03.

### 5.3 Unambiguous labelling

- Header or cell note on both columns: `год, десятковим (1,5 = 1 год 30 хв; можна ввести 1:30)`.
- Wherever a print time is *displayed* read-only, show both forms: `1,65 год (1 год 39 хв)`.

### 5.4 Owner dashboard

`dashboard/booster-dashboard.html`:

- The batch calculator takes `total_print_time_h` (validated at ~line 845). Accept the human forms
  above and show a live hint under the field with the parsed result (`= 1 год 39 хв`).
- The product form shows `Час друку / од.` read-only (`threeDpMetrics().time`, ~line 849). Render it
  in both forms.
- Surface the implausible-value warning from 5.2 next to the value.

### 5.5 Serhiy's local server

`3d-print/serhiy-local-server/public/index.html` has two `type="number"` hour inputs:

- line ~50 `total_print_time_h` — `Сумарний час партії, год`
- line ~76 `actual_time_hours` — `Час друку факт, год`

Serhiy hits the same trap and his numbers feed his own reimbursement. Change both to accept the
human forms with the same parser and hint. `lib/calculator.mjs` keeps receiving decimal hours and
needs no maths change — only the input boundary moves.

The parser must be one shared implementation, not three divergent copies. If the server cannot
import from the dashboard, keep a single source file and document the duplication explicitly.

### 5.6 Existing wrong data

Only `ACC-3D-DITTO-410` exists live and the owner is correcting it by hand to `1.65`. No migration
of historical values is needed. `FIG-CHARM-001` was deleted and will be re-entered.

## 6. Also fix in this pass — leftovers from the 2026-08-08 live run

1. **The migration rejects a blank cell inside a validated column.**
   `validate3dp015Schema3dp_()` walks `Номенклатура!K` from row 2 to the last row holding a formula
   and requires every cell to match one of three approved shapes. A **blank** `K3` — created simply
   by the owner deleting rows — matched none, so the migration refused to run with
   `Номенклатура!K3 differs from the approved cost formula`. The owner had to paste the formula down
   by hand to get past it. Blank must be treated as acceptable and filled by
   `normalizeNomenclatureFinalCostFormula3dp_()`.
   **The same hole exists in the `Продажі` `I`/`K`/`L` loop in the same function** — fix both.
2. **Misleading audit text.** The live FIX2 run recorded
   `Номенклатура!K2:K7 no longer includes fixture reference N` as the change description, when the
   actual change was adding the defect-rate uplift. `_Аудит_API` and the migration evidence now
   describe the change incorrectly. Make the message state what actually changed.
3. **Stale `Аналітика` title.** Cell `A1` still reads
   `Маржа-калькулятор по SKU (цінові сценарії, формула 50/50 після повернення собівартості)`. The
   price scenarios were removed by `3D-P-015`. It sits outside the managed `A3:N17` block, so update
   it deliberately — and keep the update outside the destructive rebuild range.

Row 18 currently starts the `Ринкові орієнтири` block (confirmed by the owner 2026-08-08). Anything
this task writes must stay above it.

## 7. Do not touch

- The stored unit. Decimal hours stays.
- `Номенклатура!K`, `Аналітика!E`/`!L`, batch-draft maths, `calculator.mjs` arithmetic.
- The defect-rate constant `Налаштування!B5` and the FIX2 formula shape.
- `Продажі` frozen columns and the frozen-literal contract.
- The 50/50 split, the two-track model, the recommended-РРЦ `pending` placeholder.
- Serhiy's permissions — he gains no pricing access here.

## 8. Acceptance criteria

- [ ] Typing `1:39` into `Номенклатура!G` for a SKU results in a stored value of `1.65` and a plain
      number format, with the conversion factor verified against the live sheet, not assumed.
- [ ] Typing `1,65` leaves `1.65` untouched. Typing `1 год 39 хв` also yields `1.65`.
- [ ] Values above 24 hours convert correctly.
- [ ] The same holds for `Друк-лог!D`.
- [ ] `onEdit` does not loop, does not fire on unrelated sheets or columns, and leaves blanks blank.
- [ ] A stored print time outside `0.02`–`100` h raises a visible warning and **blocks nothing**.
- [ ] Dashboard batch calculator and Serhiy's two inputs accept all three forms and show the parsed
      hint; one shared parser.
- [ ] Read-only print-time displays show both `1,65 год` and `1 год 39 хв`.
- [ ] `ACC-3D-DITTO-410` at `1.65` computes cost `50.32 грн`, margin `24.84`, Serhiy accrual
      `75.16`, hourly `45.55` — hand-checked and stated in the report.
- [ ] A blank cell inside the validated `Номенклатура!K` range no longer aborts the migration; same
      for `Продажі!I/K/L`. Regression test for both.
- [ ] Audit/change text names the real change.
- [ ] `Аналітика!A1` no longer mentions price scenarios; the `Ринкові орієнтири` block stays at or
      below row 18.

## 9. QA / smoke test

Local, by the executor: parser unit tests for every accepted and rejected form; `onEdit` simulation
including the recursion and blank cases; migration regression with a deliberate blank in `K` and in
`Продажі!I`; dashboard static tests; Serhiy server tests.

Owner, after deployment:

1. Type `1:39` into the print-time cell for Дітто — it must become `1,65` by itself.
2. Confirm cost `50,32`, margin `24,84`, Serhiy `75,16`.
3. Enter something absurd (e.g. `0,001`) and confirm a warning appears **and the save still works**.
4. In the dashboard batch calculator, type `2:30` and confirm the hint reads 2 год 30 хв.
5. On Serhiy's server, do the same in both time fields.

## 10. Rollback note

`onEdit` is additive and can be removed without touching data. The parser changes are UI-only. The
migration blank-tolerance fix is a loosened comparison, not a schema change.

**A fresh named Google Sheets version is required before deployment**, because `onEdit` writes to
cells the owner types into. The version created before `3D-P-015` is already two migrations stale.

## 11. Recommended status after execution

`3D-P-024`: `In progress` while the handoff is open; `Done` only after owner QA above. `3D-P-015`
is unaffected and can close on its own QA. Claude (chat) is the Notion writer.

## Risk classification

Not an SEO risk zone. It **is** a financial-model risk zone: print time feeds Serhiy's production
cost, his reimbursement, and the 50/50 split base. It is also the first `onEdit` trigger in this
workbook, so the recursion and scope guards are not optional. Owner is the only deployment gate.
