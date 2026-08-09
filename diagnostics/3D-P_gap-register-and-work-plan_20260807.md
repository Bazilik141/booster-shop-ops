# 3D-P gap register — agreed logic vs live implementation, and the remaining work

**Date:** 2026-08-07
**Author:** Claude (chat) — read-only. No write to Notion, Sheets, CRM, dashboard or live site.
**Trigger:** owner states that the workbook and backend never had the agreed logic rewritten into
them, that only cosmetic changes landed on his side, and that both are roughly still at the state of
the original ChatGPT V1 handoff. This file tests that claim against code and live schema, then sizes
the remaining work in the owner's required sequence.

**Evidence:** `plans/3D-P_handoff-chatgpt_v1_20260728.md` (V1 spec), `plans/3D-P-000_scoping-and-architecture_20260728.md`,
handoffs 006 / 010 / 013 / 014 / 015, `diagnostics/3D-P_live-schema-audit_20260803.md` (live workbook read),
`3d-print/apps-script-3dp-api/Code.gs`, `dashboard/booster-dashboard.html`,
`3d-print/serhiy-local-server/lib/calculator.mjs`, Notion roadmap DB, Drive metadata for the live workbook.

---

## 1. Verdict on the owner's claim

**Substantially correct, with two corrections.**

Correct:

- **The business/financial layer was never rewritten.** There is still no approved RRP, no cost
  versioning, no break-even price, no returns operation, and no compensation rule anywhere in the
  workbook or the API. Every one of these exists as a written agreement (V1 §5.4–5.7, §7.4, or a
  2026-08-03 owner decision) and none of them exists in code.
- **The dashboard displays surrogates, not business data** (§3.2 below). This is the mechanism by
  which the tab can look finished while the model underneath is not.
- **Nothing has moved on this track since 2026-08-04.** Live workbook `modifiedTime` is
  `2026-08-03T17:55Z`.

Corrections:

- **The technical layer is ahead of V1, not at V1.** The cost formula *was* rewritten to Serhiy's
  final spool-based model and deployed (Addendum #1, 2026-08-02). Archive-not-delete, the `_Аудит_API`
  trail, batch drafts and the stock ledger (Addendum #2) do not exist in V1 at all — they are later
  additions and they are live. The split is: **technical scaffolding ahead of V1, business logic behind it.**
- **Serhiy's local server is not built on the first draft.** `lib/calculator.mjs` implements exactly
  the 2026-08-02 locked formula (`(вага_виробу ÷ вага_котушки) × ціна_котушки` + `0.17 × год × 4.32` +
  `12 × год`, batch totals divided by quantity). What is true is that it was specced *before* the
  013/014/015 decisions, so it has no concept of фактична РРЦ, of the sync journal, or of the
  rebuilt Аналітика. It needs a re-spec, not a rewrite from zero.

One further gap the owner has not raised and no task covers: **the owner dashboard has no
Виробництво / Друк-лог zone at all.** `Code.gs` exposes `3dp_print_log`, `3dp_print_log_update`,
`3dp_print_log_archive`, `3dp_print_log_restore`; `dashboard/booster-dashboard.html` calls none of
them. Production data can only be entered through Serhiy's server, which has never been installed.

---

## 2. Layer map — what actually exists live

| Layer | State | Evidence |
|---|---|---|
| Cost formula (spool-based, 4 inputs, 3 global constants) | **Live** | `Налаштування!B2:B4` = 0.17 / 4.32 / 12; `Номенклатура!K` formula |
| Archive-not-delete + edit history | **Live** | `Номенклатура!O/P`, `Друк-лог!J/K` |
| `_Аудит_API`, `_Чернетки_партій`, `_Коригування_наявності` | **Live, hidden** | live schema audit 2026-08-03 |
| Role split + formula-cell write protection | **Live** | `Code.gs` `OWNER_MANUAL_COLUMNS_3DP` / `SERHIY_MANUAL_COLUMNS_3DP` / `FORMULA_COLUMNS_3DP` |
| `Продажі!T` CRM match key | **Live** | `T1 = CRM row number` |
| Виплати tab (simplified Settlement) | **Live** | `Виплати!B/C` are formulas |
| Approved RRP / buyout price / model link | **Absent** | no such column in `Номенклатура` (A–P) |
| Cost versioning / frozen sale economics | **Absent** | `Номенклатура!K` is one live formula; `Продажі!F` derived |
| Break-even price, max discount, below-minimum flag | **Absent** | 0 matches for `мінімальн` / `беззбитк` in `Code.gs` |
| Returns as a financial operation | **Absent** | 0 matches for `Поверненн` in `Code.gs` |
| Licence cost, stock locations | **Absent** (locations deliberately dropped; licence never decided) | `Code.gs`, V1 §6.8/§6.9/§7.7 |
| CRM → 3D-P sale sync | **Deployed but non-functional** | Finding 9, 2026-08-04 |
| Sync failure visibility | **Absent** | no `3dp_sync_journal` in `Code.gs` |
| Owner-side production log UI | **Absent** | dashboard calls no `print_log` action |

---

## 3. Gap register

### 3.1 Agreed and never built

| # | Agreement | Source | Live state | Task |
|---|---|---|---|---|
| G1 | One **фактична РРЦ** per SKU + **ціна під викуп** (Track 2) + **посилання на модель**, as durable columns | owner decision 2026-08-03; 3D-P-008 Addendum #3 → superseded by 015 | no such columns | `3D-P-015` handoff ready |
| G2 | **Freeze cost and RRP as numeric literals into the sale row** at creation, so a filament-price change cannot rewrite past sales or Serhiy's accruals | owner 2026-08-03; V1 §7.4 (`CostVersion`) | `Продажі!F` derived, `Номенклатура!K` a single live formula | inside `3D-P-015` |
| G3 | **Rebuild Аналітика** — remove the three speculative price scenarios and everything derived from them, keep market research | owner 2026-08-03 | three scenarios still live and still feed «Нараховано Сергію (Середня)» | inside `3D-P-015` |
| G4 | **Sync failures must be visible** — durable journal tab + `3dp_sync_journal` read action + dashboard panel | owner 2026-08-03, sequenced first | absent | `3D-P-014` handoff ready |
| G5 | **Мінімальна беззбиткова ціна** (Model B: `C_s + C_b`), max discount without sign-off, red flag + mandatory reason for a below-minimum sale | V1 §5.4–5.5; partially decided 2026-07-31 (no numeric discount cap) | absent everywhere | **no task exists** |
| G6 | **Повернення as a separate financial operation** — reduces the open period or creates a next-period correction, returns stock via a separate movement, never deletes the sale | V1 §5.6 | absent | **no task exists** |
| G7 | **Compensation rule for Track 2 / плюшки** — which ledger line a Serhiy payout + hardware posts to | 3D-P-004, open since 2026-07-31 | `Маркетингові_плюшки` records the purchase; no rule | open owner decision |
| G8 | **Recommended / dynamic РРЦ** — 75% margin-class position + 25% sales interest | proposed 2026-08-02, never confirmed | placeholder only | blocked on owner confirmation |
| G9 | **Owner-side production log** (Друк-лог view/edit from the dashboard) | implied by 3D-P-013 Zone C; API built for it | API actions exist, dashboard uses none | **no task exists** |

### 3.2 Built, but wired to surrogate data

`dashboard/booster-dashboard.html` cannot read fields that do not exist, so it substitutes:

| Field shown | What it actually reads | Consequence |
|---|---|---|
| РРЦ | `threeDpLatest(sales, sku, 'Фактична ціна за од., грн (після знижки)')` — the **last post-discount transaction price** | the margin grid classifies against a discounted price; a SKU with no sales (e.g. `FIG-CHARM-001`, Продано = 0) shows «немає РРЦ» permanently |
| Ціна під викуп | last `Ціна закупівлі` row in `Маркетингові_плюшки` | same class of error on the Track-2 side |
| Посилання на модель | `localStorage` in the owner's browser (`threeDpModelLinks`) | not in the workbook, invisible to Serhiy, lost on browser reset |
| Рекомендована РРЦ | literal placeholder | — |

The margin grid itself (5 cost brackets × 5 tiers, `(РРЦ − собівартість) ÷ РРЦ`) **is** implemented
correctly in `threeDpMargin()`. It is correct arithmetic over a wrong input.

### 3.3 Deliberately out of scope — do not treat as a gap

The 3D-P-006 handoff states the tab is a subset of V1 §10: no `Product`/`Variant`/`CostVersion`/
`Settlement` entities, no production kanban, no market-comparables browser. Also deliberately dropped:
plastic type (not tracked), labour as a separate cost line (absorbed by the 50/50 split), Model A. These
are decisions, not misses — except `CostVersion`, whose *function* returned as G2 and must be built.

The planned defect rate is **not** deliberately excluded anymore. The owner reversed that decision on
2026-08-08: `3D-P-015-FIX2` adds one owner-only global `Налаштування!B5` rate to the production-cost
formula. It is a planned simple uplift, distinct from the actual `Друк-лог` defect count.

### 3.4 Correction to an earlier diagnostic

`diagnostics/3D-P_live-schema-audit_20260803.md` Finding 4 states that Serhiy holds
`Продажі` columns `C, F, I, J, K, L, S`. **That is wrong.** `SERHIY_MANUAL_COLUMNS_3DP` has no
`Продажі` entry at all — Serhiy cannot write that tab. `C, F, I, J, K, L, S` are `FORMULA_COLUMNS_3DP`
for `Продажі`, i.e. formula-protected against everyone. The real (and much smaller) issue is that
`Назва` (`C`) is a formula column by design, so no role writes it manually. Finding 4 should be
struck and no "role split correction" work should be scheduled off it.

---

## 4. Remaining work, in the owner's required sequence

Sequence fixed by the owner 2026-08-07: **workbook + CRM backend → owner dashboard QA → Serhiy server rework.**

### WP-A — workbook and API business layer

| ID | Work | Handoff | Blocked by |
|---|---|---|---|
| A1 | Sync journal (`3D-P-014`) | ready | nothing |
| A2 | Price model: РРЦ фактична / ціна під викуп / посилання на модель, Аналітика rebuild, freeze cost+RRP into sale rows (`3D-P-015`, covers G1–G3) | ready | A1 (owner sequencing) + column-placement decision |
| A3 | Break-even price, max discount, below-minimum flag (G5) | **none** | owner decision on the rule |
| A4 | Returns as a financial operation (G6) | **none** | owner decision on period handling |
| A5 | Track-2 compensation ledger rule (G7) | **none** | owner decision |
| A6 | Data cleanup: delete `ПРИКЛАД-001` rows across 6 tabs, populate `Фурнітура_довідник`, reset `FIG-CHARM-001` test stock (3 units, 0 sold) | **none** | owner decision on demo-row deletion |

A2 carries a migration risk that must not be improvised: the new business columns go either before or
after the technical `O`/`P` block; a shift touches deployed write paths, `nomenclatureStatusColumn` /
`nomenclatureHistoryColumn` and the whitelists, and needs its own migration step verified against the
live sheet with the audit log preserved.

### WP-B — CRM side

| ID | Work | Handoff | Blocked by |
|---|---|---|---|
| B1 | Hook the third sale-write path `updateSaleStatus()` / `updatePaymentStatus()` with the same fail-open contract; re-verify `UrlFetchApp` behaviour in the menu (user) authorization context | **none** | A1 recommended first, so the next attempt is diagnosable |
| B2 | Fixture / розхідники half of `3D-P-010` (multi-line entry, write-off semantics) | Phase 0 only | `Фурнітура_довідник` data (A6) + owner decision on consumption semantics |
| B3 | Main-CRM data-validation defects: `Паковання` dropdown source range overlaps product data; new SKUs trip `Недійсне значення` | **none** | — (separate CRM task, must not be folded into 3D-P) |

### WP-C — owner dashboard, ending in the QA gate

| ID | Work | Handoff | Blocked by |
|---|---|---|---|
| C1 | Re-QA of the two 2026-08-02 fixes, **desktop only** | `3D-P-013` | nothing |
| C2 | Replace the three surrogates (§3.2) with the real columns | `3D-P-013` partial | A2 |
| C3 | Виробництво zone over the existing `print_log` API actions (G9) | **none** | nothing technical; needs scope |
| C4 | Recommended-РРЦ generator (G8) | spec pending | owner confirmation of the mechanism |
| C5 | Full owner QA gate — the point after which Serhiy's server may be reworked | — | C1–C4 |

### WP-D — Serhiy's server (only after C5)

| ID | Work | Blocked by |
|---|---|---|
| D1 | Re-spec against the post-A2 schema (RRP, frozen economics, journal) | C5 |
| D2 | Decide what Serhiy may see — V1 §3.9/§9.2 lists a narrower data set than the current README grants | owner decision |
| D3 | Install on Serhiy's machine + live two-party QA (never done) | D1–D2 |

### Volume

17 work items. **3** have a ready handoff (A1, A2, C1). **8** have no handoff and no task at all
(A3–A6, B1, B3, C3, D2 scope). **9** are gated on an owner decision listed in §5. Two touch risky
zones under `AGENTS.md` (CRM write paths, workbook migration) and need rollback plans and focused
smoke tests. This is not a finishing pass — the business layer is a build, not a patch.

---

## 5. Owner decisions

### 5.1 Locked 2026-08-07

| # | Decision | Recorded in |
|---|---|---|
| D1 | **`3D-P-015` column placement: append AFTER the technical `O`/`P` block** — new columns become `Q`, `R`, `S`. No shift of `O`/`P`, no migration step, no change to `nomenclatureStatusColumn` / `nomenclatureHistoryColumn` or the write whitelists. Owner accepted the cosmetic cost of business columns sitting after technical ones in exchange for not touching deployed archive/history write paths. | Notion `3D-P-015` Owner Decision; `ROADMAP_SOP.md` §3D-P |
| D2 | **Returns are a separate financial operation and never delete the sale row.** Payout period still open → reduces the current accrual. Period already paid out → negative correction in the next period. Serviceable stock returns via a separate stock movement. | Notion `3D-P-017` |
| D3 | **Track-2 cost posts to the general Marketing expense line of the MAIN CRM**, not only to the 3D-P plyushky tab. Serhiy's payout flow is unchanged. | Notion `3D-P-020`, `3D-P-004` |
| D4 | **Fixture payer must be recorded per fixture** (owner or Serhiy), for Track 1 and Track 2, because the 50/50 split and the Track-2 buyout price are currently computed on an unattributed cost. *New requirement raised 2026-08-07.* | Notion `3D-P-019` |
| D5 | **Full cleanup of demo and test data**: named Google Sheets version as the rollback point first, then delete `ПРИКЛАД-001` across all six tabs and zero the `FIG-CHARM-001` test stock. Stock corrections go through the Addendum #2 ledger, never a direct cell edit. | Notion `3D-P-021` |
| D6 | **Build order:** workbook + CRM backend → owner dashboard QA → only then rework Serhiy's server. Serhiy's server is not installed before that gate. | Notion `3D-P-007` |
| D7 | **`3D-P-009` stays permanently unused.** Numbering continued at `3D-P-016`; next free ID is `3D-P-022`. | `ROADMAP_SOP.md` §3D-P |

Defaults adopted where the rule already existed and was never contradicted, owner may
override at any time:

- Break-even (`3D-P-016`) uses Model B: `мінімальна ціна = C_s + C_b`. Owner confirmed
  2026-07-31 there is no numeric discount cap without Serhiy's sign-off, so the control is
  **informational, not blocking**.
- Recommended-РРЦ (`C4`) ships as an explicit placeholder. Not built, not invented.

### 5.2 Still open

1. Below-minimum sale behaviour (`3D-P-016`): flag only, require a written reason, or block.
2. Where the fixture payer is recorded (`3D-P-019`): per fixture reference row, per print
   batch, or per SKU.
3. `Фурнітура_довідник` source data — the tab is empty; no fixture work can start without it.
4. Recommended-РРЦ mechanism (`C4`) — confirm or replace the 2026-08-02 proposal. Note there
   are currently 0 real Track-1 sales, so the sales-interest half has no data to run on.
5. Owner-dashboard production zone (`3D-P-018`): view print-log rows only, or also enter them.
6. Serhiy's data scope (`D2` in WP-D) — the current server README grants more than V1
   §3.9/§9.2 specified.
7. Licence cost tracking — never decided; relevant because the line is IP-adjacent (Pokémon).
8. `3D-P-006` closure — its scope shipped and is superseded by `3D-P-013`. Claude does not set
   `Done` without owner authorization (`ROADMAP_SOP.md` §3 stage 6).

## 6. Housekeeping

- Seven files, including the `3D-P-014`/`015` handoffs and the canonical live-schema audit, exist in
  the working tree but not in `HEAD` (see `diagnostics/3D-P_state-audit_20260807.md` §5).
- Notion `3D-P-006` and `3D-P-013` show `Not started` although both shipped; `3D-P-010`'s Notion
  record predates Findings 9 and 10.
- `ROADMAP_FLOW` in `dashboard/booster-dashboard.html` is missing `3D-P-007`, `014`, `015`,
  `3D-P-CARDCONTENT`.
- G5, G6, G9 and B3 have no roadmap entry anywhere and need IDs before they can be handed off
  (next free number is `3D-P-016`; `3D-P-009` was never issued).
