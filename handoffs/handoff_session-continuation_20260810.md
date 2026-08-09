# Session continuation brief — 2026-08-10

**For:** the next Claude (chat) session · **Written:** 2026-08-09 evening
**Supersedes:** `handoffs/handoff_3D-P_session-continuation_20260809.md`

Scope note: 2026-08-09 was mostly a **CRM** day, not a 3D-P day. Read this first, then
`context-index.md` for the specific task.

---

## 1. What changed on 2026-08-09

The headline: the main CRM's own data integrity went from unmeasured to measured, then repaired.
**150 problems → 5**, in three surgical edits.

| Task | Result |
|---|---|
| `3D-P-025` | **Done.** Stock field takes the actual count; two blocking defects found in review and fixed first |
| `CRM-005` | Deployed. Read-only `integrity_check`, KPI tile, copy button, `OPS-CRMINTEGRITY` rule, new-SKU runbook |
| `CRM-006` | Passes 1–3 done. 150 → 78 → 77 → 5 |
| `CRM-006-ORDER` | Deployed. Expandable order rows with per-line detail |
| `3D-P-013` | **Done.** Owner QA passed |
| `3D-P-024` | **Done.** Owner QA passed on both live SKUs |
| `3D-P-019` | Phase A discovery accepted; decision **F9** locked |

### The CRM-006 chain, because it is worth understanding once

Three root causes, not 150 defects:

1. **`РРЦ!A76:D76`** held four literals that blocked the `A3:D3` ARRAYFORMULA — the `#REF!` named row
   76 itself. Clearing them removed **72** problems. Rows 71–75 were a *symptom* (their `A:D` were
   blank because the spill was blocked); the original diagnosis blamed them and was wrong.
2. **`РРЦ!E75`** `90` → `100` for `ACC-3D-DITTO-410`.
3. **`Майстер_Товарів!P2`** used `VLOOKUP(...;13;...)` while `Активний товар` is column **12** —
   column 13 is `Посилання на товар`. One index change took `master_row_inactive` from **72 → 0**.

Cause 3 also explains why the dashboard showed empty stock, an empty SKU list and `₴0` potential
profit: `apiSkuList_` hard-filters on `Активний`, and `apiSummary_` builds its price map **from
`apiSkuList_`**. After the fix: warehouse potential profit `₴0` → **`₴25 889`**, asset potential
profit `₴0` → **`₴47 627`**, and «Потребують уваги» `0` → **13 SKUs needing purchase, 1 urgent**.

## 2. Verified system state

- **Main CRM Apps Script: V98**, published 2026-08-09. Adds `integrity_check` (+ its `ok`/`clean`
  fix) and `order_items`.
- **3D-P Apps Script: V10**, unchanged today.
- **Both mirrors verified content-level against the owner's exports**, not by filename — V98 contains
  `apiOrderItems_`; see `crm/apps-script/SOURCE_STATE.md`.
- **CRM integrity baseline: 5 problems**, all `formula_column_literal`, all in `Товари!B`/`J` and
  `Розхідники!F:H`. `elapsed_ms` has ranged 5750 → 11191 → 6812 across the day.
- **Notion and `ROADMAP_FLOW` are in sync.** Four rows were missing from the dashboard entirely
  (`3D-P-025`, `CRM-005`, `CRM-006`, `CRM-006-ORDER`) and were added on 2026-08-09.

**Not verified — do not claim otherwise:**

1. Serhiy's local server has still never been installed or run by anyone, and two-party concurrent
   use has never been tested.
2. `CRM-006` pass 4 (the 5 remaining formula literals) has not started.

**Settled, do not re-raise:** the mystery-box write-off for `OC-FOP-0312` was completed by Codex and
confirmed correct by the owner on 2026-08-09. The `₴1` unit cost that surfaced when the order was
first expanded is resolved.

## 3. First thing tomorrow — owner instruction, 2026-08-09

**Annotate the superseded root cause in
`diagnostics/CRM-005_integrity-check-and-rule_report_20260809.md`.** It still states that the manual
cells in `РРЦ` rows 71–75 block the ARRAYFORMULA spill. That is wrong — the blocker was
`РРЦ!A76:D76`, and the `#REF!` errors named row 76 themselves. Rows 71–75 were a symptom.

Append a dated correction pointing at
`diagnostics/CRM-006_bounded-live-diagnosis_report_20260809.md`. **Do not rewrite the original
sentence** — mark it superseded. The equivalent annotations in
`diagnostics/CRM-005_first-live-baseline_20260809.md` and on the `CRM-006` Notion page were already
done on 2026-08-09; this report is the last copy still carrying the wrong mechanism, and it will
mislead the next session that reads it cold.

## 4. Then — Serhiy's server

The owner's own next step. `3D-P-007`.

**One real gap, already diagnosed.** `server.mjs` reads settings via `3dp_get_range` on
`Налаштування!A1:C4` — three constants — and `lib/calculator.mjs` computes
`base_uah = material + electricity + amortization` with **no planned-defect multiplier**.
`3D-P-015-FIX2` added `Налаштування!B5` on 2026-08-08 and the workbook now multiplies by `(1 + B5)`.
B5 sits outside `A1:C4`. Result: Serhiy's server reports a cost ~8 % below the workbook for the same
batch. Fix before any test, or the owner validates the wrong formula.

**Already current, verified from source:** all twelve `3dp_*` actions it calls exist in V10; it
imports the shared parser `3d-print/shared/print-time.js` so `3D-P-024` is reflected; it writes only
`Номенклатура!G:J` and never touches `Q/R/S`, so "no pricing controls" is provable by inspection.

**Two things to verify** — the README predates decision F8: that fixture price stays a reference value
and does not enter the base formula, and that the overview reads nothing from the reshaped
`Аналітика`.

**Owner decision, locked 2026-08-09:** ship as a **folder with a `.bat` launcher** containing a
portable Node runtime — not an installer, not a compiled `.exe`. Self-built Windows binaries get
flagged by antivirus, which is a worse conversation than "install Node".

**Why the owner can test it himself:** the token-rejection check is enforced server-side in Apps
Script, so it does not care whose machine sends the request. A Serhiy-scoped token already exists.
That means `3D-P-015` can close without waiting for Serhiy.

## 5. Traps that cost real time on 2026-08-09

**The dashboard has no deploy step.** `dashboard/booster-dashboard.html` in the repo *is* the
dashboard; the owner opens it at its `file://` path and `Ctrl+F5` is the entire release. Both Claude
and Codex gave him upload instructions during the day by carrying over the OpenCart patch model, and
Codex asked him to "create a named version" for a local HTML file. Release separation for the
dashboard is a **git commit**, not a deploy.

**Commit-block file counts must come from `git diff --cached --name-only`, not
`git status --porcelain`.** Rename detection collapses a delete+add pair into one entry; a block that
expected 36 threw at 34 because two rotated export files were renames.

**Git from the sandbox:** `git --no-optional-locks show HEAD:<path>` is safe and leaves no
`index.lock`. Verified repeatedly on 2026-08-09. Plain `git status` without the flag is the thing to
avoid.

**Reviewing a producer and a consumer separately does not review the contract between them.** The
`ok:false` collision shipped with both test layers green: the unit test called `apiIntegrityCheck_`
directly, the tile test stubbed `call`, and nothing exercised the seam where `if (!d.ok) throw` lives.
Same class of gap as the `3dp_get_row` sheet bug. When a defect is at an interface, demand a test that
crosses it.

**`apiDoGetCacheKey_` ignores parameters for every action except `sku_list`.** Adding a
parameterised action to `CACHEABLE_ACTIONS` without extending that function would serve one order's
data for every order. `order_items` is deliberately uncached for this reason.

**The three-catalogue trap** (still valid): a SKU must exist in `Товари`, in `Майстер_Товарів` with
`Активний = так`, and in `Номенклатура`. Item two is the one everyone forgets — and on 2026-08-09 it
turned out to have been silently broken for 72 SKUs by a formula, not by human omission.

**Decimal hours** (still valid): print time is stored as decimal hours everywhere. `1.65` = 1 h 39 min.
Never set a duration format on those columns.

## 6. Open tasks with a ready handoff

| Task | Handoff | Note |
|---|---|---|
| `3D-P-007` | none yet | **Next up.** Calculator defect-rate fix + F8/Аналітика verification + folder-and-`.bat` packaging |
| `3D-P-015` | — | Closes after the owner runs Serhiy's server locally. Everything else is verified |
| `CRM-006` | `handoff_CRM-006-PASS1_...`, `handoff_CRM-006-PASS2-PASS3_...` | Pass 4 not written yet: 5 formula literals, per-row confirmation, **no fill-down** |
| `3D-P-019` | `handoff_3D-P-019B_single-payer-per-sale_20260809.md` | Phase A executable now — its CRM-005 gate is cleared. Phase B decision locked, implementation not specced |
| `3D-P-016` | none | Needs one owner decision: below-minimum sale behaviour — flag only, require a reason, or block |
| `3D-P-017` | none | Unblocked by `3D-P-015`/`3D-P-014`. Returns hit the same multi-write-path problem as `3D-P-010` |

## 7. Open owner decisions

1. Below-minimum sale behaviour (`3D-P-016`).
2. `CRM-006` pass 4 timing — the 5 formula literals.
3. Whether margin deserves its own toggleable column in the Вироби picker. It currently appears only
   as a badge under `РРЦ фактична`; the owner noticed and thought it was missing.
4. Recommended-РРЦ mechanism — still unapproved, still an explicit `pending` placeholder.
   **Do not invent a formula.** There are 0 real Track-1 sales to derive one from.
5. Serhiy's data scope (`3D-P-007`) — the server README still grants more than V1 §3.9/§9.2.
6. Licence cost tracking — never decided, IP-adjacent.
7. Whether `Фурнітура_довідник` becomes the pending-purchases tab or is retired.

## 8. Known test data awaiting cleanup

- `ACC-3D-DITTO-410` stock reads **99** — a deliberate test quantity. The owner states the true
  physical count is **1**. Correct through the stock panel before real trading.
- `FIG-LUFFY-500` has `Ціна під викуп = 999 грн`, a deliberate test value.
- `РРЦ` rows 71–75 hold manual prices that are now correctly SKU-keyed after the spill repair.

## 9. Project progress — assessed 2026-08-09

Excluding the NCRM migration (`3D-P-005`): **10 of 25 tasks Done, ~40 % by count.** By layer:
accounting engine **~95 %**, operational plumbing **~25 %**, going-to-market **~20 %**.

**The bottleneck is no longer software.** There are 0 real Track-1 sales, no 3D product live on the
site, no confirmed plastic type or material cost from Serhiy, and the «Фігурки» category does not
exist. Further engine polish will raise the task count without moving the business. The two real
unblocks are a conversation with Serhiy about actual numbers (which frees `3D-P-000`, `002` and `003`
at once) and getting one product card live end to end.

## 10. Reference documents

- `diagnostics/CRM-006_pass1-result-and-master-active-chain_20260809.md` — the full CRM-006 chain,
  all three passes, and the `Майстер_Товарів` → dashboard evidence.
- `diagnostics/CRM-005_3D-P-025_3D-P-019A_claude-review_20260809.md` — both review rounds.
- `diagnostics/CRM-006_bounded-live-diagnosis_report_20260809.md` — root causes, live-verified.
- `diagnostics/CRM-005_first-live-baseline_20260809.md` — **disposable**, delete once CRM-006 finishes.
- `plans/3D-P-019_fixture-payer-model_20260808.md` — decisions F1–F9.
- `crm/apps-script/SOURCE_STATE.md` — V98 / V10, both content-verified.
- `docs/CRM-new-SKU-runbook.md` — the procedure that should prevent the CRM-006 class of breakage.
- `diagnostics/3D-P_live-schema-audit_20260803.md` — **Finding 4 is wrong**, struck. Predates the
  `3D-P-015` schema; treat its column lists as historical.
- Note: `diagnostics/CRM-005_integrity-check-and-rule_report_20260809.md` still claims rows 71–75
  blocked the spill. That is superseded by the CRM-006 diagnosis and was never annotated by its author.

## 11. Working rules confirmed again on 2026-08-09

- **Read the diff, not the report.** Every executor claim reviewed this day was checked against source
  and live evidence. Three defects were found that way, two of them blocking.
- **Verify the executor's "unrelated failure" claims.** Codex correctly reported a stale failing test;
  running it against the `HEAD` baseline in an isolated tree confirmed it. A second failing test turned
  out to need PowerShell, which the sandbox lacks — an environment limit, not a defect.
- **Own your own misses.** The `ok`/`clean` collision passed two Claude reviews before the owner hit it
  live. The wrong `РРЦ` root cause was repeated from an executor report into two Claude artifacts.
  Both are annotated rather than quietly corrected.
- **The owner is the only deploy gate, and the only one who authorises `Done`.**
