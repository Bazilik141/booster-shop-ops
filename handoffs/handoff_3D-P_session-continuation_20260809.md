# 3D-P — session continuation brief

**Date:** 2026-08-09 · **For:** the next Claude (chat) session working the 3D-P series
**Purpose:** pick up without re-deriving state. Read this first, then `context-index.md` for the
specific task. Supersedes `handoffs/handoff_3D-P_session-continuation_20260808.md`.

---

## 1. What changed on 2026-08-08 to 2026-08-09

The price model was rebuilt, deployed and migrated on the live workbook. This is the headline: the
accounting engine is now correct, and everything that follows is operational plumbing around it.

| Task | Result |
|---|---|
| `3D-P-015` | Deployed and migrated live. `Номенклатура` `Q/R/S`, `Продажі` `U/V/W`, `Аналітика` rebuilt, three price scenarios gone |
| `3D-P-015-FIX1` | Analytics re-syncs on every run; margin shows the owner's post-split share; missing SKU journals truthfully |
| `3D-P-015-FIX2` | Planned defect rate as a global constant `Налаштування!B5` |
| `3D-P-024` | Print time can be typed as `1:39`; normalised to decimal hours. Live-verified |

**Live-verified by the owner, not inferred:** `1:39` → `1.65`, the out-of-band warning fires without
blocking, `Q/R/S` persist across a hard refresh, and the margin class label reads «до split».

## 2. Verified system state

- **Main CRM Apps Script: V95**, exported 2026-08-08 19:31 Kyiv.
- **3D-P Apps Script: V10**, exported 2026-08-08 21:53 Kyiv.
- **Both mirrors re-verified 2026-08-09 as identical to the owner's exports** apart from CRLF. See
  `crm/apps-script/SOURCE_STATE.md`. Content-level proof, not filename trust: V10 contains `onEdit`,
  `3dp_setup_3dp024` and the `$B$5` defect reference; V95 contains `skipped_sku_not_in_nomenclature`
  and `CRM_3DP_SALES_FROZEN_HEADERS_`.
- **Live constants** (`Налаштування`), owner-tuned and expected to change again after testing on
  large long-print items: power `0.11` kW, electricity `4.32`, amortization `12`, defect `0.08`.
- **Live SKUs:** `ACC-3D-DITTO-410` and `FIG-LUFFY-500`. `FIG-CHARM-001` was deleted as test data and
  will be re-entered properly.

**Not verified, do not claim otherwise:**

1. Serhiy's local server has never been installed or tested with two parties.
2. Order-save timing after the `3D-P-015` hook changes — never measured, and the hook now makes one
   extra HTTP call per distinct 3D-P SKU.
3. Bulk paste of times into the print-time columns — covered less completely than keyboard entry.

## 3. Known test data awaiting cleanup

Both are deliberate and known to the owner. Clean before real trading:

- `ACC-3D-DITTO-410` stock reads `196` — inflated by a `+99 тестові коригування` ledger row from
  2026-08-09, on top of two `−1` rows from the 2026-08-08 QA orders.
- `FIG-LUFFY-500` has `Ціна під викуп = 999 грн` — a deliberate test value.

`3D-P-025` is the task that makes correcting the stock natural; do that first, then fix the count
through the panel rather than by hand.

## 4. Traps that cost real time — do not rediscover them

**The three-catalogue trap.** A 3D SKU must exist in `Товари` (main CRM), `Майстер_Товарів` with
`Активний = так` (a *different* Google file), and `Номенклатура` (3D-P workbook). Only the SKU string
links them. Item two is the one everyone forgets: the row can be present and still invisible because
`Активний` is blank.

**Decimal hours.** Print time is stored as decimal hours everywhere. `1.65` = 1 h 39 min. Never set a
duration format on those columns — Google reads such a number as a fraction of a day. `3D-P-024` now
normalises typed input, but the stored unit is unchanged and every consumer assumes it.

**Live scripts write before they verify.** Three times on 2026-08-08 a live PowerShell script wrote to
the workbook and then failed on its own post-write check, leaving the owner unsure whether anything
had changed. Twice the workbook had in fact been migrated successfully. When a live script errors,
**establish what the workbook actually looks like before re-running anything.**

**The dashboard is PC-only.** Never ask for tablet or mobile QA on `booster-dashboard.html`. Mobile
belongs to NCRM after the migration. Stale mentions were corrected 2026-08-09 in the gap register, in
`ROADMAP_FLOW` and in the `3D-P-013` and `3D-P-015` blockers.

**Sandbox git leaves a lock.** Running `git` from the sandbox leaves `.git/index.lock` that it cannot
delete, which blocks the owner's commit. Prefer tracking edited files manually; if a lock happens, the
owner's commit block must delete it first.

## 5. Open tasks with a ready handoff

| Task | Handoff | Note |
|---|---|---|
| `3D-P-019` | `handoffs/handoff_3D-P-019_fixture-operational-half_20260809.md` | **Next up, owner's choice.** Schema half already live |
| `CRM-005` | `handoffs/handoff_CRM-005_integrity-check-and-rule_20260809.md` | New 2026-08-09 |
| `3D-P-025` | `handoffs/handoff_3D-P-025_stock-field-actual-count_20260809.md` | New 2026-08-09 |
| `3D-P-016` | none | Unblocked by `3D-P-015`. Needs the below-minimum behaviour decision |
| `3D-P-017` | none | Unblocked. Owner's period rules already decided 2026-08-07 |

`3D-P-013`, `3D-P-015` and `3D-P-024` are all `In progress` pending only owner desktop QA and, for
`3D-P-015`, Serhiy-server QA. Their substantive work is done.

## 6. Open owner decisions

1. Below-minimum sale behaviour (`3D-P-016`): flag only, require a reason, or block.
2. Recommended-РРЦ mechanism — still unapproved, still ships as an explicit `pending` placeholder.
   **Do not invent a formula.** There are 0 real Track-1 sales to compute one from.
3. Which system feeds the storefront price. The CRM `РРЦ` sheet said `90` for `ACC-3D-DITTO-410` while
   the 3D-P workbook said `100`; the owner confirmed on 2026-08-09 that **100 is correct** and the CRM
   sheet needs correcting. The broader question of a single canonical price source is still open.
4. Serhiy's data scope (`3D-P-007`) — the current server README grants more than V1 §3.9/§9.2.
5. Licence cost tracking — never decided; the line is IP-adjacent.
6. Whether `Фурнітура_довідник` is repurposed as the pending-purchases tab or retired.

## 7. Working rules established the hard way

- **Verify against the live system before asserting.** During this session a claim that there were
  zero live sale rows was wrong — the rows existed, their cost column was simply empty because
  `Номенклатура!K` was blank. The conclusion happened to hold; the reasoning did not.
- **Read the diff, not the report.** Every executor claim reviewed this session was checked against
  the actual source and the live evidence file. Two review findings the report did not mention were
  found that way.
- **Owner decisions override design notes.** The design note assumed `Тип = Фурнітура`; the owner had
  already entered `Категорія = 3D-друк`. Check live data before trusting a plan written days earlier.
- **Commit blocks must have their file count computed, not guessed.**
- **The owner is the only deploy gate**, and the only one who sets a task to `Done`.

## 8. Reference documents

- `plans/3D-P-019_fixture-payer-model_20260808.md` — fixture design, decisions F1–F8.
- `diagnostics/3D-P_gap-register-and-work-plan_20260807.md` — agreed-vs-implemented register.
- `diagnostics/3D-P-015_price-model-rebuild_report_20260808.md` — implementation report.
- `diagnostics/3D-P-015_live-migration_20260808_205617.json` — post-migration live evidence.
- `diagnostics/3D-P-024_print-time-entry-usability_report_20260808.md`.
- `crm/apps-script/SOURCE_STATE.md` — mirror and deployment state for both Apps Script projects.
- `plans/3D-P_sku-naming-convention_20260807.md` — canonical SKU grammar.
- `diagnostics/3D-P_live-schema-audit_20260803.md` — **Finding 4 is wrong**, struck in the gap
  register. Also predates the `3D-P-015` schema; treat its column lists as historical.
