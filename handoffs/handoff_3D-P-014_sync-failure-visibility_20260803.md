# Codex Handoff — 3D-P-014: make CRM→3D-P sync failures visible

Date: 2026-08-03 · **Revised 2026-08-08 (rev 2)** | Parent: 3D-P-000 · related: 3D-P-010, 3D-P-013, 3D-P-008
Codex config: model=Sol · effort=high
Priority: do this **before** any further 3D-P architecture work (owner decision
2026-08-03).

---

## ⚠ REVISION 2026-08-08 — the journal moves to the CRM. Read this before anything below.

**Codex found a real design flaw in rev 1 and it is credited: a journal that lives in the 3D-P
workbook cannot record the failure "the 3D-P API is unreachable", because writing that row needs
the very API that is down.** The QA case "broken URL → `skipped_api_error`" was therefore
impossible as specified. Rev 1 was wrong on this point.

**Owner decision 2026-08-08: the journal lives in the MAIN CRM spreadsheet, not in the 3D-P
workbook.** No queue, no retry, no backfill.

Reasoning, so the choice is not re-litigated later:

- The hook is CRM code making CRM-side decisions, and it is **already writing to the CRM
  spreadsheet** in the same call — appending one more local row costs no network call and cannot
  fail for network reasons. The flaw disappears structurally rather than being handled.
- It removes the whole dependency: `3D-P-014` no longer needs the 3D-P API to be up in order to be
  QA'd or to be useful.
- Division of responsibility becomes clean: **`_Аудит_API` in the 3D-P workbook records what
  actually happened in the workbook; the CRM journal records what the hook decided.**
- Both alternatives were rejected: best-effort leaves the outage case — the exact case that caused
  the 2026-08-03 `OC-FOP-0300` incident — invisible; a durable queue with backfill adds storage,
  retries, dedupe and ordering, i.e. a new failure surface that would itself need diagnosing.

Rejected on purpose: dual-write (local **and** mirrored into 3D-P). Two records of one event
reintroduces "which one is right".

### What changes versus rev 1

| Rev 1 (superseded) | Rev 2 (build this) |
|---|---|
| Tab `_Журнал_синхронізації` in the 3D-P workbook | Hidden tab **`_Журнал_3DP_синхронізації` in the main CRM spreadsheet** |
| Written through the 3D-P API | Written locally with `SpreadsheetApp`, **no network call** |
| New read action `3dp_sync_journal` on the 3D-P API | New read action **`sync_journal` in the main CRM `handleApiAction_` registry** (line ~690), same shape as `recent_sales` |
| Dashboard panel reads the 3D-P API | Dashboard panel reads the **main CRM API** — the dashboard already uses both clients |
| `3dp_sync_journal` must reject the Serhiy token | n/a — the CRM API has no Serhiy token. The action is owner-only by virtue of the CRM token, and **must never be exposed to Serhiy's server** |

Everything else in rev 1 — fail-open contract, no secrets or personal data in the journal, one row
per outcome, empty journal is itself diagnostic — stands unchanged.

### Build notes — specific to this codebase

**Write target.** Get the spreadsheet from the sheet already in hand: `sales.getParent()`.
Do **not** use `SpreadsheetApp.getActive()` — the hook also runs in the `doPost` Web App context,
where the active spreadsheet is not reliable. This is the kind of context assumption that has
already cost this task three failed attempts.

**Smallest sufficient change.** `crm3dpLogSkip_` (line ~811) and `crm3dpLogWarning_` (line ~815)
are already called at most failure branches and today only write `Logger.log`. Extend those two to
append a journal row as well, then add explicit journal calls at the branches that currently log
nothing. Line numbers are from `crm/apps-script/Code.gs`, mirror pulled 2026-08-08 — **re-verify
against the live script before patching.**

| Line | Branch | `outcome` |
|---|---|---|
| 902–903 | invalid whole stock quantity | `skipped_invalid_qty` |
| 908 | stock adjustment already applied | `noop` |
| 925 | insufficient stock warning | `warning_negative_stock` |
| 932 | `missing_order_id` — **no log today** | `error` |
| 936 | `no_3dp_sku` — **no log today** | `skipped_no_3dp_sku` |
| 942–943 | properties not configured | `skipped_not_configured` |
| 951 | duplicate 3D-P key for a CRM row | `warning_duplicate_key` |
| ~975 / ~985 | successful append / successful `G` write — **no log today** | `created` / `updated` |
| 1002 | catch-all | `skipped_schema` if the error message is the `Продажі!T schema is not ready` throw from `crm3dpSaleRows_`, otherwise `skipped_api_error` |

One row per outcome event: an order with two trigger lines produces two `created`/`updated` rows;
an order-level skip produces one row.

**`source` column.** `sync3dpSales_` cannot see its caller. Add an **optional 4th parameter**
`source`, defaulting to `'unknown'`, and pass it from the two existing call sites (`apiAddSale_`,
`apiUpdateSale_`). The wrapper `sync3dpPackagingCost_` keeps its three-argument form for the
existing tests, so this stays backward-compatible. An unlabelled call then shows up as `unknown`
in the journal, which is itself useful. **Do not add the third call site here** —
`updateSaleStatus()` is `3D-P-010` WP4, a separate patch file, applied after this one.

**Performance.** The menu path already ran 42.1 s in the 2026-08-03 incident. The journal append
must be a single bounded write per event — no read-modify-write of the whole sheet. Cap the tab
and trim oldest rows past the cap (propose ~1000, confirm with the owner if you disagree).

**Fail-open, unchanged.** If the journal append itself throws, swallow it after attempting.
Nothing here may block an order save.

---

<details>
<summary>Rev 1 text (2026-08-03) — superseded scope, kept for history. Do not build against §Scope items 1–3 below.</summary>

## Why

`3D-P-010`'s CRM hook is deliberately fail-open: a 3D-P outage, schema problem,
or rejected write must never block a real customer order. That part is correct
and stays.

What is wrong is that it fails **silently**. Every failure path calls
`crm3dpLogSkip_()` / `crm3dpLogWarning_()`, which write to `Logger.log` — a
surface the owner never sees and which is not retained usefully. On 2026-08-03
order `OC-FOP-0300` (SKU `FIG-CHARM-001`) did not appear in the 3D-P sheet and
the cause had to be inferred from Apps Script execution durations, because there
was no record anywhere of what the hook actually decided to do.

This is a design defect in the original `3D-P-010` handoff, not a Codex
implementation error.

## Scope

**1. Durable sync journal in the 3D-P workbook.**

New hidden/system tab `_Журнал_синхронізації` (same treatment as
`_Аудит_API`/`_Коригування_наявності`: append-only, never returned wholesale by
a generic read action). Suggested columns — confirm against
`diagnostics/3D-P_live-schema-audit_20260803.md` and existing system-tab
conventions before creating:

`timestamp_kyiv | source | order_id | crm_row | sku | outcome | detail`

- `source`: `apiAddSale_` or `apiUpdateSale_`.
- `outcome`: one of a small closed set — `created`, `updated`, `noop`,
  `skipped_no_3dp_sku`, `skipped_not_configured`, `skipped_schema`,
  `skipped_api_error`, `warning_negative_stock`, `error`.
- `detail`: short human-readable reason. **Never a token, URL with token, or
  customer personal data.**

Every branch of `sync3dpSales_` that currently returns a skip/warning, and every
successful create/update, must write exactly one journal row. A row is written
even when the hook succeeds, so an empty journal is itself diagnostic (it means
the hook never ran).

**2. New bounded read action** `3dp_sync_journal` (owner role only), returning
the most recent N rows (cap consistent with existing read caps). Read-only.

**3. Dashboard surface** in `dashboard/booster-dashboard.html`, 3D-друк tab,
Інформація zone: a compact "Синхронізація з CRM" panel showing the latest
journal entries, with non-`created`/`updated`/`noop` outcomes visually flagged.
Reuse the existing "Потребує уваги" pattern rather than inventing a new one.

**4. Fail-open is preserved exactly.** If the journal write itself fails, that
failure must also not block the CRM order — swallow it after attempting, same as
today. The journal must never become a new way to break order saving.

</details>

## What NOT to touch

- The fail-open contract itself — do not convert any skip into a thrown error
  that could reach `apiAddSale_`/`apiUpdateSale_`.
- Main CRM sheet data, packaging calculation, `getPackagingCost_()`, `doPost`.
- The price model — that is `3D-P-015`, deliberately separate.
- Any storefront, checkout, payment, SEO or Merchant surface.
- **`updateSaleStatus()` / `updatePaymentStatus()`** — adding the third call site is
  `3D-P-010` WP4 (`handoffs/handoff_3D-P-010-WP4_updatesalestatus-hook_20260808.md`),
  a separate patch file applied after this one.
- The 3D-P Apps Script project and the 3D-P workbook — rev 2 makes this task
  CRM-only. `_Аудит_API` stays exactly as it is.
- The existing `handleApiAction_` actions; add `sync_journal` alongside them
  without altering the others.

## Acceptance criteria

- [ ] Every outcome branch of `sync3dpSales_` produces exactly one journal row,
      including successes — verified by test, not by inspection.
- [ ] No token, tokenised URL, or customer personal data can reach the journal
      or the dashboard panel — assert this in a test.
- [ ] A deliberate 3D-P outage produces a `skipped_api_error` row **and** the
      CRM order still saves normally.
- [ ] Journal write failure does not propagate to the CRM order flow.
- [ ] `sync_journal` returns newest-first, respects the row cap, and is reachable
      only with the CRM owner token. (Rev 1's "rejects the Serhiy token" no longer
      applies — the CRM API has no Serhiy token; instead assert the action is never
      wired into Serhiy's server.)
- [ ] Journal writes use `sales.getParent()` and never `SpreadsheetApp.getActive()` —
      assert the hook journals correctly when invoked through `doPost`.
- [ ] The `source` column records `apiAddSale_` / `apiUpdateSale_`, and an
      unlabelled call records `unknown`.
- [ ] Dashboard panel renders the latest entries and visibly flags non-success
      outcomes.
- [ ] `ROADMAP_FLOW` entry for `3D-P-014` added.

## Owner QA

1. Save one order containing a 3D-P SKU → journal shows `created`.
2. Update that same order → journal shows `updated` or `noop`, no duplicate row.
3. Save an order with no 3D-P SKU → journal shows `skipped_no_3dp_sku`.
4. Temporarily break `BOOSTER_3DP_URL` → order still saves, journal shows
   `skipped_api_error`; restore the property afterwards. **This case is the whole
   point of rev 2 and is now actually testable** — it was impossible under rev 1.
5. Clear `BOOSTER_3DP_URL` entirely → journal shows `skipped_not_configured`.
6. Confirm the dashboard panel shows all of the above without a page reload
   workaround.
7. Time one order save before and after the patch; the added journal write must not
   be noticeable. Report the numbers.

## Rollback

Journal tab and read action are additive; disabling the panel and the journal
writes returns behaviour to today's. No CRM business data is touched — the journal
tab is new and hidden, and removing it removes nothing that existed before.
Keep the previous CRM script version in Apps Script version history before publishing.

## Risks

CRM risky zone — the hook sits inside live order creation/update. The single
most important invariant is unchanged: **nothing added here may block an order
save.** Any doubt on that point stops the task.

## Recommended status

`Not started` → `In progress` on pickup → `Done` once owner QA passes.
