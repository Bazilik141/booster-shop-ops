# 3D-P state audit — CRM automation and the 3D workbook

**Date:** 2026-08-07
**Author:** Claude (chat) — read-only audit. No write to Notion, Sheets, CRM, dashboard or live site.
**Scope requested by owner:** current real state of the 3D-print tasks, limited to
(a) CRM setup and (b) the 3D workbook. Stale information separated from current.

**Evidence used**

- Notion roadmap DB `5aef22c3-048d-4dde-a5b1-ad409de9301c` (canonical status), queried 2026-08-07.
- `ROADMAP_SOP.md` §3D-P series, `context-index.md` §3D-P, `dashboard/booster-dashboard.html` ROADMAP_FLOW.
- `diagnostics/3D-P_live-schema-audit_20260803.md` (live workbook read, extended 2026-08-04).
- `3d-print/apps-script-3dp-api/Code.gs` + `README.md`, `patches/3D-P-010_*`, handoffs 006/007/008/010/013/014/015.
- Google Drive metadata for the live workbook `1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo`, read 2026-08-07.
- `git ls-tree -r HEAD` vs working tree (read-only; no index write).

**Not verifiable from here:** the deployed state of the main CRM Apps Script project
(V86/V87/V89) and of the 3D-P web app deployment. Every deployment claim below is
owner-reported and labelled as such.

---

## 1. Executive state

| Area | Real state 2026-08-07 |
|---|---|
| 3D-P API foundation (3D-P-008) | **Working.** Addendum #1 + #2 deployed, owner QA passed 2026-08-02. |
| `Продажі!T` match-key gate (3D-P-010 precondition) | **Satisfied.** `T1 = CRM row number` confirmed by live read 2026-08-03. |
| CRM → 3D-P sale sync (3D-P-010) | **Not working.** Blocked on an unhooked third write path (Finding 9, 2026-08-04). No fix designed yet. |
| Sync failure visibility (3D-P-014) | **Not implemented.** Handoff ready 2026-08-03; no `3dp_sync_journal` action exists in `Code.gs`. |
| Price model rebuild (3D-P-015) | **Not implemented.** Blocked by 3D-P-014 per owner sequencing. |
| Dashboard 3D tab (3D-P-006 → 013) | **Built, partial owner QA.** Two UI bugs found 2026-08-02 and fixed in the repo file; re-QA + tablet/mobile pending. |
| Serhiy local server (3D-P-007) | **Package complete, never installed.** Live QA with Serhiy has never happened. |
| The 3D workbook (3D-P-001) | **Live, frozen since 2026-08-03T17:55Z.** Structurally incomplete: no RRP column anywhere. |

**Last real movement on the CRM/workbook track: 2026-08-04.** All 3D-P work committed
after that date (2026-08-06/07) is catalog, legal and content work
(`3D-P-011` variant feasibility, `LEGAL-002b-3DP`, SKU naming convention), not CRM.

---

## 2. CRM track — what is actually deployed

### 2.1 Deployed and confirmed

1. **3D-P Apps Script API** (`3D-P-008`, Notion **Done**). Base deployment 2026-08-01;
   Addendum #1 (final spool-based cost schema) and Addendum #2 (archive/history,
   batch drafts, stock ledger) both deployed and QA-confirmed live 2026-08-02.
2. **`setup3dp010()` schema gate.** The live workbook shows `Продажі!T1 = CRM row number`
   (live read 2026-08-03), so the composite match key `Продажі!N + T` is available.
3. **Reconciliation diff formally waived** by the owner 2026-08-02 — kept for history only.

### 2.2 Not working — the actual blocker

`3D-P-010` (auto-pull packaging/fixture cost from the main CRM into the 3D-P sales log)
has failed three consecutive times, each for a different reason:

| Attempt | Owner-reported deploy | Result |
|---|---|---|
| V87 | 2026-08-02 | Update-only hook; the 3D-P sales row it tried to update was never created by anything. Never fired. |
| V89 | 2026-08-04 | Corrected create/upsert block pasted after the owner confirmed `sync3dpSales_` was absent from the live CRM. Order `OC-FOP-0300` still did not sync. |
| — | — | **Finding 9 (2026-08-04):** the owner's habitual path is the in-Sheet menu function `updateSaleStatus()` (alias `updatePaymentStatus()`), which writes `Продажі` directly and never calls `apiUpdateSale_`. It is not hooked at all. |

There are three sale-write paths in the main CRM; the 3D-P-010 design covered two:

| Path | Entry point | Hooked |
|---|---|---|
| Web App create | `doPost` → `apiAddSale_` | yes |
| Web App update | `doPost` → `apiUpdateSale_` | yes |
| In-Sheet menu update | `updateSaleStatus()` / `updatePaymentStatus()` | **no** |

The menu path runs in a *user* authorization context, not the Web App context, so
`UrlFetchApp` quota/permission behaviour must be re-verified there rather than assumed.
**No handoff has been written for this fix.**

Fixture (розхідники) half of `3D-P-010` is still Phase 0 and has no source data:
`Фурнітура_довідник` is live but empty (headers only).

### 2.3 Ready but not picked up

- **`3D-P-014` — sync journal.** Handoff `handoffs/handoff_3D-P-014_sync-failure-visibility_20260803.md`,
  Notion blocker "None — ready for Codex pickup". Confirmed not implemented: `Code.gs`
  exposes no `3dp_sync_journal` action and no `_Журнал_синхронізації` tab logic.
  Owner sequenced this **before** any further 3D-P architecture work (2026-08-03).
- **`3D-P-015` — price model rebuild.** Handoff written 2026-08-03, blocked by 014.
  Carries one unresolved structural decision (new business columns before or after the
  technical `O`/`P` block; a shift touches deployed write paths and needs its own migration).

### 2.4 Dashboard tab

`dashboard/booster-dashboard.html` contains the three-zone 3D tab
(Калькулятор / Вироби / Інформація) and 19 wired `3dp_*` actions, all of which exist in
`Code.gs`. The 2026-08-02 owner-QA bug `renderThreeDpAttention is not defined` is fixed
in the current repo file (`threeDpAttention()` defined and called at lines 874/882), as is
the blank-Delta key. Outstanding: short owner re-QA, tablet/mobile widths, and a
dashboard smoke of the Addendum #2 write actions.

### 2.5 Main-CRM data-validation defects (Finding 10, 2026-08-04)

Not 3D-P script issues; they need their own CRM task and must not be folded into 014/015:

- the `Оновити_продаж` `Паковання` dropdown's data-validation source range overlaps
  product data (a product name appears in the packaging list);
- 3D-P and mystery-box SKUs trigger `Недійсне значення` warnings because they are absent
  from the SKU validation list (warning-only; values still land).

---

## 3. Workbook track — the 3D file

**Live file:** `3D-P_nomenclature-tracker_v6_20260731`
(`1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo`), owner `14bezlikiy14@gmail.com`,
**modifiedTime `2026-08-03T17:55:10Z`**, last viewed 2026-08-07. A Drive title search for
`3D-P` spreadsheets returns this file only.

**Live schema ≠ repository copies.** The six `3d-print/3D-P_nomenclature-tracker_v*.xlsx`
snapshots stop at 2026-07-31 and predate Addendum #1/#2. The live workbook additionally has
`Номенклатура!O/P`, `Продажі!T`, and the hidden tabs `_Чернетки_партій`,
`_Коригування_наявності`, `_Аудит_API`. **Canonical schema reference is
`diagnostics/3D-P_live-schema-audit_20260803.md`, not the xlsx files.**

Structural gaps confirmed against the live sheet:

1. **No canonical price exists.** `Номенклатура` has no RRP column, no buyout-price column,
   no model-link column. `Продажі!E` is a post-discount transaction price. The only price
   concept in the workbook is the three speculative `Аналітика` scenarios
   (FIG-CHARM-001: 50 / 62 / 75 ₴), and every margin and Serhiy accrual descends from them.
2. **Cost is not versioned.** `Номенклатура!K` is one live formula cell; `Продажі!F` is
   derived, not frozen. A filament-price change silently rewrites past sales and amounts
   already accrued to Serhiy.
3. **Spool data duplicated** between `Номенклатура!I/J` and `_Чернетки_партій`, with no
   defined precedence.
4. **`Продажі` write-whitelist role split looks inverted.** Per `Code.gs`, Serhiy holds
   `C, F, I, J, K, L, S` (product name + computed financials) while the owner holds
   `A, B, D, E, G, H, M, N, O, P, Q, R` and cannot write `Назва`. Needs an owner decision,
   then correction.
5. **`ПРИКЛАД-001` demo rows are still live** in Номенклатура, Продажі, Виплати,
   Маркетингові_плюшки, Наявність and Аналітика and still feed totals (Наявність 2 units,
   Виплати 165 ₴ accrual). The API filters them from reads; the sheet formulas do not.
6. **`Фурнітура_довідник` empty** while `Номенклатура!N` expects a per-unit fixture price.
7. `FIG-CHARM-001` reads `Наявно зараз = 3` (Addendum #2 smoke artifact plus a later
   `тест` adjustment) with `Продано = 0` — not real inventory.

Items 1–2 are exactly what `3D-P-015` was written to fix; items 5–7 have no task.

---

## 4. Stale information — do not act on

| Artifact | Why it is stale | Current source |
|---|---|---|
| `3d-print/apps-script-3dp-api/README.md` §"Current gate" — "the 2026-08-02 schema-correction source is prepared locally but is **not deployed** yet" | Contradicted by Notion 3D-P-008 (Done) and `diagnostics/3D-P-008_schema-correction_report_20260802.md`. Addendum #1 and #2 are live. | Notion 3D-P-008 + the 2026-08-02 diagnostics |
| `diagnostics/3D-P-008_reconciliation_diff_20260801.md` | Formally waived by owner 2026-08-02 | history only |
| `3D-P-008` Addendum #3 | Superseded by `3D-P-015` | `handoffs/handoff_3D-P-015_price-model-rebuild_20260803.md` |
| `handoffs/handoff_3D-P-006_owner-dashboard-tab_20260731.md` flat layout | Superseded by the three-zone restructure | `handoffs/handoff_3D-P-013_dashboard-tab-restructure_20260802.md` |
| `handoffs/handoff_3D-P-011_dashboard-tab-restructure_20260802.md` (587 B stub) | Left over from the 011→013 renumbering | the 013 handoff |
| Notion `3D-P-013` Stage field, which points at `handoffs/handoff_3D-P-011_dashboard-tab-restructure_20260802.md` | Pre-renumbering path | the 013 handoff |
| `3d-print/3D-P_nomenclature-tracker_v1..v5.xlsx` | Superseded by v6 and then by live Addendum #1/#2 changes | the live sheet + `3D-P_live-schema-audit_20260803.md` |
| `plans/3D-P_handoff-chatgpt_v1_20260728.md` | Archived original external draft | `plans/3D-P-000_scoping-and-architecture_20260728.md` |
| Notion 3D-P-003 record of the 160/200/230 ₴ RRP band | Rejected by owner the same day (2026-07-31) | 50–75 ₴, v6 scenario |
| `3D-P-009` | Referenced in the 3D-P-010 handoff; never existed | `ROADMAP_SOP.md` note 2026-08-03 |

---

## 5. Cross-system drift (as of 2026-08-07)

**Notion (canonical status) is 3–4 days behind the repository on this track.**

| Task | Notion Status / Last Updated | Repository evidence | Verdict |
|---|---|---|---|
| 3D-P-006 | Not started · 2026-08-02 | first tab version shipped 2026-08-02 (`ROADMAP_SOP.md`, ROADMAP_FLOW step 1 done) | Status wrong |
| 3D-P-013 | Not started · 2026-08-02 | built; owner desktop QA partially passed; two bugs fixed locally | Status wrong |
| 3D-P-010 | In progress · 2026-08-03 | Findings 9 and 10 dated 2026-08-04 are absent from the Notion Stage/Blocker | Status right, content stale |
| 3D-P-014 / 015 | Not started · 2026-08-03 | matches repo | OK |

**ROADMAP_FLOW (`dashboard/booster-dashboard.html`) is missing four 3D-P entries**
that exist in Notion and `ROADMAP_SOP.md`: `3D-P-007`, `3D-P-014`, `3D-P-015`,
`3D-P-CARDCONTENT`. Its `3D-P-010` and `3D-P-013` notes are dated 2026-08-02 and also
predate Findings 9/10.

**`context-index.md`** still carries unreconciled duplicate rows for `3D-P-011` and
`3D-P-012` (flagged 2026-08-06, owner reconciliation pending). A handoff must not be
written against an unreconciled ID.

**Seven files exist in the working tree but not in `HEAD`** — including the two newest
CRM handoffs and the canonical live-schema audit:

```
diagnostics/3D-P-010_auto-create-upsert_report_20260803.md
diagnostics/3D-P-011_catalog-state-addendum_report_20260806.md
diagnostics/3D-P-011_native-variant-feasibility_report_20260806.md
diagnostics/3D-P_live-schema-audit_20260803.md
handoffs/handoff_3D-P-014_sync-failure-visibility_20260803.md
handoffs/handoff_3D-P-015_price-model-rebuild_20260803.md
patches/3D-P-010_PASTE-THIS-BLOCK_20260803.js
```

(Determined by comparing `git ls-tree -r HEAD` with the working tree; no index was written.
The working tree may contain further uncommitted owner changes outside this pattern.)

---

## 6. Owner decisions still open on this track

1. **3D-P-010 third write path.** Hook `updateSaleStatus()` with the same fail-open
   contract, or accept that sales entered through the in-Sheet menu never reach 3D-P.
   No handoff exists either way.
2. **3D-P-015 column placement** — new business columns before or after the technical
   `O`/`P` block (a shift needs its own migration step).
3. **`Продажі` role whitelist** — confirm the intended owner/Serhiy split before correction.
4. **`ПРИКЛАД-001` demo rows** — delete, or keep and accept contaminated totals.
5. **`Фурнітура_довідник` source data** — the fixture half of 3D-P-010 cannot proceed without it.
6. **3D-P-011 / 3D-P-012 duplicate rows** in `context-index.md` — reconcile.
7. **Recommended-RRP formula** — still unapproved; must ship as a placeholder, never invented.

## 7. Suggested next bounded action

Per the owner's own 2026-08-03 sequencing, `3D-P-014` (sync journal) is the next
executable item: its handoff is complete, its blocker is "None", and without it every
further 3D-P-010 attempt has to be diagnosed by inference again. Everything else on the
CRM track either waits on 014 or waits on an owner decision listed in §6.
