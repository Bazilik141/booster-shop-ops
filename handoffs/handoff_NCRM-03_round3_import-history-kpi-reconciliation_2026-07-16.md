# Codex Handoff — NCRM-03 round 3: close remaining warehouse residual

Date: 2026-07-16 | Parent: NCRM-01 (Done), NCRM-02 (Done) | Round 2 report: `diagnostics/NCRM-03_import-history-kpi-reconciliation_round2_report_20260716.md`

## 1. Task ID
NCRM-03 — round 3, after owner review of the round 2 report's +255.54 UAH / +9-unit warehouse residual. Notion: `In progress`, Current Owner `Codex (round 3)`.

## 2. Context
Round 2 closed 96% of the original warehouse-cost gap (6,807.78 → 255.54 UAH) by fixing the effective-availability boundary for four undated lots, and reconstructed Mystery COGS for 14 exact + 1 approximated legacy box group. The remaining residual was attributed to two known, honestly-reported causes: (1) six `Списання` rows excluded pending NCRM-13, of which only `WRT-0094`/`WRT-0095` (−12 units) visibly affected the quantity residual; (2) two pre-receipt sale/writeoff events where the sale date precedes the purchase lot's recorded delivery date (`OP-JP-OP12-BST`, `PKM-JP-BBLT-BST`).

Owner reviewed both causes 2026-07-16 and made two decisions:

**(a) Pre-receipt consumption is not a defect.** The owner confirmed the goods were physically on hand; the recorded `delivery_date` merely lagged the real-world event (administrative delay in the legacy sheet). This is a **general rule for this one-time historical import**: wherever an imported sale or writeoff date precedes its purchase lot's recorded `delivery_date`, treat the lot as effectively available from that earlier sale/writeoff date, not the recorded date. This is not limited to the two already-found cases — apply it to any further instance the round-3 run surfaces, and log every instance (lot code, original recorded date, effective date used) in the diagnostics report exactly as round 2 already did for the four undated-lot cases. This must remain visible and reversible, not a silent backdate: do not overwrite `delivery_date` itself — apply the adjustment the same way round 2 handled the four undated lots (a documented `legacy_effective_availability_from` audit note), so the original source fact stays intact.

**(b) Negative `Списання` rows — hybrid resolution.** Re-reading the source rows found that 4 of the 6 are SKU-reclassification corrections with an identifiable positive counterpart already in the imported 142-row positive set:
- `WRT-0094` (−11 `PKM-JP-MSYM-BST`), `WRT-0095` (−1 `PKM-JP-MDEX-BST`), `WRT-0096` (−6 `PKM-JP-OUTL-BST`) are explicitly noted as reversed into `WRT-0093` (+18 `PKM-JP-MZERO-BST`) — net effect of the group is zero units.
- `WRT-0097` (−1 `PKM-JP-MBRV-BST`) is explicitly noted as a paired transfer with `WRT-0098` (+1 `PKM-JP-OUTL-BST`) — net effect is zero units.

Since `WRT-0093` and `WRT-0098` are already imported as ordinary positive writeoffs, these four negative rows should be recognized as **already absorbed** by their counterpart — no insert needed, no schema change, and importantly no further quantity adjustment (the correction is already fully reflected). Mark them in the import report as "superseded by paired correction (`WRT-0093`/`WRT-0098`), zero net quantity impact" rather than "excluded/deferred."

Only `WRT-0051` (−3 `PKM-JP-MBRV-BST`) and `WRT-0052` (−7 `PKM-JP-MZERO-BST`) remain deferred — these are standalone physical-inventory-count corrections ("факт більше, ніж у CRM") with no counterpart row in the source, and genuinely have no home in the current schema (`writeoff_items.qty > 0`). **NCRM-13's scope is now narrowed accordingly: 2 rows, net −10 units, not 6/−29.** The Notion ticket has been updated.

## 3. Goal
Re-run reconciliation with (a) and (b) applied and report the resulting residual precisely. Full closure to `Done` is plausible if the residual reaches kopiyka tolerance; if not, the remaining gap must be exactly attributable to `WRT-0051`/`WRT-0052` (now NCRM-13's sole scope) and nothing else.

## 4. What to change (scope)
- Implement rule (a): when building the FIFO/availability layer, if a sale or writeoff date < lot `delivery_date`, use the earlier date as effective availability, and add an audit note per affected lot (same pattern as the four already-handled undated lots). Verify whether any other rows in the 2026-07-16 snapshot trigger this beyond the two already found — report the full list, don't assume it's still exactly two.
- Implement rule (b): change `WRT-0094`, `WRT-0095`, `WRT-0096`, `WRT-0097` from "excluded/deferred to NCRM-13" to "superseded by paired correction" in the import logic and in the report — confirm first, against the full row set (not just the notes I quoted here), that no other row references these four before treating them as fully resolved. Keep `WRT-0051`/`WRT-0052` excluded and reported as deferred to NCRM-13 (updated scope: 2 rows / −10 units).
- Re-run the batch (new tag, e.g. `ncrm03_20260716_r3`, or update `ncrm03_20260716` in place if that's cleaner — Codex's call, document which).
- Update the diagnostics report (new file or append a "round 3" section) with the recomputed KPI table and an explicit statement of what closed and what — if anything — remains, tied only to `WRT-0051`/`WRT-0052`.

## 5. What NOT to touch
Same protected zones as round 2 (§5 of the round-2 handoff): no new migrations, `writeoff_items.qty > 0` stays enforced, live production CRM/Apps Script untouched, no remote/cloud writes, `refunds`/`expenses` out of scope, sitemap/robots/canonical/.htaccess/checkout/payment/fiscalization/Merchant feed/schema untouched.

## 6. Likely files/areas
`ncrm/scripts/import-history/import-history.mjs`, `reconcile.mjs`, new/updated rollback SQL, `diagnostics/NCRM-03_*round3*`. Verify current file state first — round 2's scripts were still uncommitted at handoff time.

## 7. Acceptance criteria
- [ ] Rule (a) applied generally (not hardcoded to just the 2 known SKUs); every triggered case logged with lot code + original vs effective date
- [ ] Rule (b): `WRT-0094`/`0095`/`0096`/`0097` marked superseded, verified against full row references (not assumed from the notes alone); `WRT-0051`/`0052` remain the only NCRM-13-deferred rows
- [ ] Recomputed KPI table (same 9 metrics as round 2) with the new diff, and explicit attribution of any remaining non-zero diff to `WRT-0051`/`WRT-0052` only
- [ ] If residual reaches ≤0.01 UAH / 0 units except for the NCRM-13-tracked SKUs, report should say so plainly
- [ ] Rollback script provided for the new/updated batch

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema. Financial/CRM data — verify manually:
- [ ] Confirm no other pre-receipt cases were missed or, if found, that they're genuine (spot-check 1-2 newly flagged ones against the live sheet)
- [ ] Confirm `WRT-0093`/`WRT-0098` really do fully cover `WRT-0094`-`0097` with no other row touching the same SKUs in between
- [ ] Confirm final residual (if any) is only `WRT-0051`/`WRT-0052`-sized
- [ ] `git status` before commit — no `.env.local`/keys staged

## 9. Rollback note
Same pattern as round 2 — batch-tagged, reversible via SQL script, doesn't touch NCRM-01 seeds or prior round artifacts.

## 10. Recommended status after execution
If the residual closes to the NCRM-13-only gap (or fully, if owner later decides to fold `WRT-0051`/`0052` in too) — NCRM-03 can move to `Done`, with a note that the remaining −10 units / associated UAH value is intentionally tracked in NCRM-13. Otherwise stays `In progress` with the new, smaller, precisely attributed residual.
