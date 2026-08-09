# Handoff — CRM-005: main-CRM integrity check + rule for structural changes

Date: 2026-08-09
Executor: **Codex** · model=Sol · effort=high
Justification: touches the deployed main-CRM Apps Script and needs careful discovery of what each
sheet's formula columns actually are. Same executor family as the recent CRM work. Owner decides.

---

## 1. Task ID

`CRM-005` · Notion `3b76bf20-bdb4-8140-8397-f14d1cc785dd`

## 2. Context — why a rule alone will not fix this

Adding a SKU to the main CRM is a multi-sheet operation. At minimum it touches `Товари`,
`Майстер_Товарів` (with `Активний = так`, or the SKU never appears in the Облік dropdown), the
`РРЦ / актуальні ціни продажу` sheet, and — for 3D SKUs — `Номенклатура` in the separate 3D-P
workbook. Nothing links these to each other except the SKU string, and nothing verifies the result.

The owner, 2026-08-09: *"Кодекс знову зламав нахуй СРМ, коли додавав нове СКЮ. Через це не
підтягуються всі інші СКЮ і не рахує потенційні прибутки. Це вже дуже часто стало повторюватись.
Треба вирішити не фактичним фіксом одного запису, а системно."*

Live symptom the same day, visible in the `РРЦ` sheet: **rows 71–75 carry a price and a date but no
SKU and no product name.** Row 76 is intact. Whatever populates those two columns stopped producing
values for that block, and it went unnoticed until the owner happened to look.

Instructions telling the executor to be careful have already been tried. The load-bearing part of
this task is the automated check; the rule exists only to make running it mandatory.

## 3. Owner constraint — read this before designing anything

The owner's explicit objection, 2026-08-09: *"кодекс не почне читати всю таблицю тепер кожен раз при
роботі з нею? Бо токени нахуй улетять тоді. Треба не зробити одну траблу замість іншої."*

**The check must never stream sheet contents to an agent.** It runs *inside* Apps Script, walks the
sheets server-side, and returns a short problem list. Same shape as the existing summary actions
(`3dp_overview` returns aggregates, not rows; `sync_journal` returns a bounded list). A clean run is
a handful of bytes.

Target response:

```json
{ "ok": false,
  "checked": ["Товари", "Майстер_Товарів", "РРЦ", "Розхідники"],
  "problems": [
    { "sheet": "РРЦ", "rows": "71-75", "code": "price_without_sku",
      "detail": "заповнена ціна без SKU і без назви" },
    { "sheet": "Майстер_Товарів", "rows": "—", "code": "missing_master_row",
      "detail": "PKM-EN-XYZ є в Товари, немає в Майстер_Товарів" }
  ] }
```

Cap the output: at most N problems per code with a `truncated` count, so a badly broken sheet cannot
produce a huge response.

## 4. Goal

1. One read-only action that answers "is the CRM internally consistent right now" cheaply.
2. The owner can run it himself from the dashboard, with no agent involved.
3. A rule that makes any structural change run it before and after.

## 5. What to change

### 5.1 The check — new read-only action in the main CRM Apps Script

Register it in the existing `handleApiAction_` registry in `crm/apps-script/Code.gs` alongside
`summary`, `sku_list`, `sync_journal`. Read-only: it must never write, never repair, never delete.

**Discovery first, and this is the real work.** Do not implement the checks from this list verbatim —
this list is the owner's intent, not verified schema. Read the live sheets and the deployed code, and
confirm each column and each formula column before asserting a rule about it. Record what you found
in `diagnostics/`.

Checks to implement, subject to that discovery:

| Code | Meaning |
|---|---|
| `price_without_sku` | a `РРЦ` row has a price and/or date but no SKU or no name — the exact live symptom |
| `missing_master_row` | a SKU in `Товари` has no row in `Майстер_Товарів` |
| `master_row_inactive` | a row exists in `Майстер_Товарів` but `Активний` is blank — the SKU silently disappears from the Облік dropdown, the single most-forgotten step |
| `active_sku_without_rrp` | an active SKU has no price row |
| `formula_column_literal` | a cell in a known formula column holds a literal instead of a formula — the classic "agent pasted a value over a formula" failure |
| `duplicate_sku` | the same SKU appears twice where it must be unique |
| `rrp_mismatch_3dp` | a 3D SKU's price in the CRM `РРЦ` sheet differs from `Номенклатура!Q` in the 3D-P workbook |

`rrp_mismatch_3dp` is **already failing today**: `ACC-3D-DITTO-410` reads `100 грн` in the 3D-P
workbook and `90 грн` in the CRM `РРЦ` sheet (row 75, dated 2026-08-08). The owner confirmed on
2026-08-09 that **100 is correct**. That single check would have caught a live price disagreement
that otherwise sat unnoticed. Note it needs a cross-workbook read, so it may have to be optional or
run through the existing 3D-P API rather than a direct read — decide deliberately and say which.

Every problem must name the sheet and the row range. "Something is wrong in РРЦ" is not actionable.

### 5.2 Owner-facing surface

Add a panel or a button to `dashboard/booster-dashboard.html` that calls the action and renders the
problem list, with an obvious green state when `problems` is empty. This is what makes it an
operational safety net instead of an agent-discipline measure.

Consider a `Booster CRM` menu item in the spreadsheet itself as well, so the owner can run it while
he is already in the sheet — decide and justify.

### 5.3 The rule

Add to `AGENTS.md` (and reference from `CODEX_WORKFLOW.md`) a short, testable rule. Proposed wording,
tighten as needed:

> **OPS-CRMINTEGRITY.** Any change that touches main-CRM sheet structure, adds or removes rows in
> `Товари` / `Майстер_Товарів` / `РРЦ` / `Розхідники`, or edits a formula column, must:
> 1. run the integrity check **before** the change and record the result;
> 2. never write a literal value into a formula column;
> 3. run the check **again after** the change and include the output in the report;
> 4. treat any new problem code as a defect in that change, not as pre-existing noise.
>
> The check is one API call returning a bounded problem list. It does not require reading sheet
> contents into context.

Point 4 matters: without it, the natural move is to say "that was already broken" and move on.

### 5.4 New-SKU runbook

Adding a SKU is the operation that breaks most often. Write it down once, in `docs/`, as an explicit
ordered procedure covering every place a SKU must exist — including `Активний = так` and, for 3D
SKUs, `Номенклатура`. Reference the three-catalogue trap. This is documentation, not code, and it is
cheap insurance.

## 6. Do not touch

- Do not repair the currently broken `РРЦ` rows 71–75 as part of this task. The check must **report**
  them. Repair is a separate, owner-approved action, and using them as the first real test of the
  check is more valuable than fixing them silently.
- No writes of any kind from the check.
- `CRM-004` (`Паковання` dropdown / SKU validation defects) stays separate. Related, different fix.
- Do not fold anything from this into a 3D-P task — different system, different blast radius.
- Never expose `BOOSTER_CRM_TOKEN` or any customer data in the problem output.

## 7. Acceptance criteria

- [ ] The action is registered, read-only, and provably performs no writes.
- [ ] A clean run returns a small response; a broken run returns a bounded, capped list.
- [ ] Run against the current live CRM it reports the `РРЦ` 71–75 block, naming those rows.
- [ ] It reports the `ACC-3D-DITTO-410` price disagreement (`100` vs `90`), or the report explains
      why cross-workbook checking was deliberately deferred.
- [ ] Every problem names sheet + row range + code.
- [ ] The owner can run it from the dashboard and read the result without an agent.
- [ ] `OPS-CRMINTEGRITY` is in `AGENTS.md` and referenced from `CODEX_WORKFLOW.md`.
- [ ] The new-SKU runbook exists and covers all catalogues including `Активний = так`.
- [ ] Discovery evidence for every column and formula column asserted is in `diagnostics/`.

## 8. QA / smoke test

Executor: run against a copy or a mock with each defect injected one at a time; confirm one problem
per defect and no false positives on a clean sheet.

Owner:

1. Run the check from the dashboard — it must list the `РРЦ` 71–75 block.
2. Fix those rows, run again, confirm they disappear from the list.
3. Correct the CRM `РРЦ` for `ACC-3D-DITTO-410` from `90` to `100`, run again, confirm the price
   mismatch clears.

## 9. Rollback note

The action is additive and read-only; removing it changes nothing operationally. The documentation
and rule changes are text. No named Sheets version required — nothing here writes to a sheet.

The one risk worth naming: a check with false positives is worse than no check, because it trains
everyone to ignore the output. Prefer fewer, certain checks over a long list of guesses.

## 10. Recommended status after execution

`In progress` until the owner has run it live and seen it catch the known-broken rows.

## Risk classification

Read-only, so blast radius is small. But it touches the deployed main-CRM script, which is a risky
zone under `AGENTS.md`: it must not slow down or break `doGet`, and it must not be added to the
cached-action list without deliberate thought — a cached integrity check would report stale state,
which is precisely the failure mode this task exists to eliminate.
