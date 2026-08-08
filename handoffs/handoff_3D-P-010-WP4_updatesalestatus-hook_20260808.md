# Patch Handoff — 3D-P-010 WP4: hook the third sale-write path `updateSaleStatus()`

Date: 2026-08-08 · Parent task: `3D-P-010` · related: `3D-P-014`, `3D-P-015`, `3D-P-019`
**Executor: Codex · model=Sol · effort=high** — Codex owns `3D-P-010` and is already editing the
same file this round for `3D-P-014`; the skill rule is never to swap executor mid-round, and a
second author in `crm/apps-script/Code.gs` would be a parallel-writer violation. The change itself
is now architecturally unambiguous (exact insertion point identified below), but it sits inside the
CRM order-save path, which is a risky zone — hence `high`, not `low`. **Owner decides.**

> ⚠ **Sequencing, not optional.** `3D-P-014` is in flight against the same file and adds journal
> writes to every branch of `sync3dpSales_`. Apply this work package **strictly after `3D-P-014`
> is deployed and QA-passed.** Applied in that order, the new call site is journalled from its
> first run, which is exactly the diagnosability the owner asked for on 2026-08-03. Do not merge
> the two into one patch file — independent rollback matters more here because there is no staging
> environment.

## 1. Task ID

`3D-P-010` work package 4 (dashboard subtask id `4`, "Підчепити третій шлях запису
updateSaleStatus()"). Notion: `3af6bf20-bdb4-8110-8e88-fdee44316a0d`.

## 2. Context

The CRM → 3D-P sale sync has failed three times in a row. V87 was update-only and never fired;
V89 fixed the create path and order `OC-FOP-0300` still did not sync.

Root cause is **proven from source** as of 2026-08-08, using the repository mirror
`crm/apps-script/Code.gs` (pull 2026-08-08 11:41, see `crm/apps-script/SOURCE_STATE.md`):

- `sync3dpSales_` is reached only through the compatibility wrapper `sync3dpPackagingCost_`;
- that wrapper is called from exactly **two** places — inside `apiAddSale_` and inside
  `apiUpdateSale_`, both of which are `doPost` (Web App) entry points;
- `updateSaleStatus()` and its alias `updatePaymentStatus()` — the in-Sheet menu form
  `Оновити_продаж`, which is the owner's habitual working path — contain **no 3D-P call of any
  kind**.

So the hook is correct but unreachable from the way the owner actually updates orders.

Earlier evidence: `diagnostics/3D-P_live-schema-audit_20260803.md` Findings 9–10;
`diagnostics/3D-P-010_auto-create-upsert_report_20260803.md`;
`handoffs/handoff_3D-P-010_crm-packaging-cost-pull_20260802.md`.

## 3. Goal

A 3D-P sale row is created or updated, and its packaging cost and stock decrement applied, when the
owner updates an order through the in-Sheet `Оновити_продаж` form — with exactly the same
fail-open contract as the two existing call sites.

Non-goal: any change to the sync logic itself, to matching, to stock semantics, or to the fixture
half of `3D-P-010`.

## 4. What to change

**One call site.** Inside `updateSaleStatus()` in the main CRM Apps Script, after the
`rows.forEach(...)` write loop completes and before the closing UI alert, call the existing wrapper:

```
sync3dpPackagingCost_(sales, order, rows);
```

Everything the wrapper needs is already in scope at that point and needs no new derivation:

| Argument | Already in scope as | Meaning |
|---|---|---|
| `sales` | `const sales = ss.getSheetByName('Продажі')` | same sheet object the two existing call sites pass |
| `order` | `const order = resolveSaleUpdateOrder_(ss, selectedOrder)` | already validated non-empty and matched to rows |
| `rows` | `const rows = matches.map(m => m.row)` | every CRM row of that order, same shape `apiUpdateSale_` passes |

**Placement requirements:**

1. **After** all sheet writes and after `fixSaleCostForRow_` has run for every row — the hook
   re-reads `Продажі` columns A–P through `crm3dpOrderRows_`, so it must see the final values,
   including the packaging amount written to column 16.
2. **After** `invalidateDoGetCache_()`, so a slow or hanging 3D-P API cannot delay the cache
   invalidation that the dashboard depends on.
3. **Before** `clearSaleUpdateForm()` and the `SpreadsheetApp.getUi().alert(...)`, so the owner's
   confirmation dialog appears only once the sync attempt has returned. Do **not** add the sync
   outcome to the alert text in this work package — that surface belongs to `3D-P-014`'s journal.
4. The return value is deliberately ignored, exactly as at the two existing call sites.
   `sync3dpSales_` already wraps its whole body in `try/catch` and returns `{ ok: false, skipped }`
   rather than throwing, so the order update can never be broken by a 3D-P failure. **Do not add a
   second try/catch around the call** — that would create a redundant swallow point and hide the
   journal write that `3D-P-014` puts inside those branches.

**No other change is authorised in this work package.**

## 5. Do not touch

- `sync3dpSales_` itself, `crm3dpSaleRows_`, `crm3dpSaleMatches_`, `crm3dpSaleAppendValues_`,
  `crm3dpEnsureStock_`, `crm3dpOrderRows_`, `crm3dpConfig_`, `crm3dpFetchJson_` — all owned by
  `3D-P-014` this round.
- `apiAddSale_`, `apiUpdateSale_` — the two working call sites.
- `resolveSaleUpdateOrder_`, `parseOrder_`, `restoreSaleUpdateFormulas_`, `clearSaleUpdateForm`,
  and the `Оновити_продаж` form ranges, formulas and data validation. The `Паковання` dropdown
  contamination is **`CRM-004`**, not this task.
- `fixSaleCostForRow_`, `orderRowWeights_`, `allocateAmount_`, `getPackagingCost_`, the FIFO cost
  functions, mystery-box recalculation, `Списання`, `Розхідники`.
- The 3D-P Apps Script project (`3d-print/apps-script-3dp-api/Code.gs`) — no change needed here.
- Fixture logic of any kind (`3D-P-019`), price-model columns (`3D-P-015`).
- Standing protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`,
  checkout, payment, fiscalization, Merchant feed, schema.

## 6. Likely files / areas

**Confirmed** (read from the 2026-08-08 mirror, but the executor must re-verify against the live
script before patching, because the owner may have deployed since the pull):

- `crm/apps-script/Code.gs` — `updateSaleStatus()`, the block ending with
  `invalidateDoGetCache_(); clearSaleUpdateForm();` and the success alert.
- `patches/` — new paste block for the owner, one work package only.
- `tests/3d-p-010-crm-packaging-pull.test.mjs` — extend, do not rewrite.

**Delivery path — Apps Script, not the PHP runner.** The PHP `patches/*.php` + `php <patch>.php`
flow does **not** apply. Deliver a paste block following the existing convention of
`patches/3D-P-010_PASTE-THIS-BLOCK_20260803.js`: the owner pastes it into the live main-CRM script
editor and publishes a **new Web App version**. The executor never commits, pushes or deploys.
Per `AGENTS.md` → "Apps Script mirrors", whoever changes the live script refreshes
`crm/apps-script/Code.gs` and the pull date in `crm/apps-script/SOURCE_STATE.md` in the same
session.

## 7. Acceptance criteria

Local, before handoff back:

1. `node --check` passes on the patched source copy.
2. `node --test tests/3d-p-010-crm-packaging-pull.test.mjs` passes, including these new cases:
   - menu-path update of an order containing a trigger SKU with **no** existing 3D-P row → one
     `3dp_append_row` call, then packaging written once;
   - menu-path update of an order whose 3D-P row **already exists** → no append, packaging updated
     only when it actually differs by ≥ 0.005;
   - menu-path update of an order with **no** 3D-P SKU → hook returns `skipped: 'no_3dp_sku'` and
     performs zero HTTP calls;
   - 3D-P API unreachable → `updateSaleStatus()` still completes all sheet writes and returns
     normally; no exception escapes;
   - the same order updated through the **dashboard** and then through the **menu form** → the
     second run performs no duplicate append and no second stock decrement (`crm3dpEnsureStock_`
     is reason-keyed and must report `already_applied`).
3. The bounded diff touches exactly one function, `updateSaleStatus()`, plus the test file.

## 8. QA / smoke test — owner, on production

Risky zone: CRM order flow. No payment, checkout, fiscalization or Nova Poshta code is touched, so
`bs-checkout-smoke` is not required. Not an SEO or schema change, so `bs-seo-risk-gate` and
`bs-merchant-schema-qa` do not apply.

Before starting: create a named Google Sheets version of both the CRM and the 3D-P workbook.

1. **First run authorization.** `updateSaleStatus()` runs from the menu under the *user*
   authorization context, not the Web App context — this is the one behaviour that cannot be
   proven locally. On the first menu run after deploy, expect a possible Google authorization
   prompt for external requests. Accept it and record whether it appeared.
2. Update a real order containing a **trigger-matching 3D-P SKU that exists in both the CRM
   catalogue and the 3D-P `Номенклатура`** through `Оновити_продаж`.
   ⚠ **Corrected 2026-08-08:** an earlier draft of this step named `FIG-CHARM-001`. Verified by a
   read of the live CRM spreadsheet the same day: `FIG-CHARM-001` appears **zero** times in the CRM
   catalogue — it never was there, which is exactly why it triggered `Недійсне значення` warnings
   (Finding 10). Meanwhile `ACC-3D-DITTO-410` is in the CRM catalogue but fails the trigger regex
   (`3D-P-022`) and is not yet in `Номенклатура`. **No SKU currently satisfies all three
   conditions**, so `3D-P-022` must be fixed and one SKU registered on both sides before this step
   is runnable. Expect: a new row in
   3D-P `Продажі` with `N` = order number and `T` = the CRM row number, `G` = packaging total,
   and one matching `_Коригування_наявності` entry with reason
   `auto: CRM order <id> row <row>`.
3. Repeat the same update with no field changed → the form's own "Нічого не змінено" guard must
   still fire first, and no 3D-P call happens.
4. Change only the packaging type on the same order → 3D-P `Продажі!G` updates, and **no** second
   stock decrement appears in the ledger.
5. Update an order with **two** 3D-P lines → two separate 3D-P rows, distinguished by `T`.
6. Update an order with no 3D-P SKU → nothing appears in the 3D-P workbook.
7. **Timing.** Note the wall-clock duration of the menu action. It ran 42.1 s in the 2026-08-03
   incident before any sync was attached, and this adds up to four HTTPS round trips.
   `UrlFetchApp` has no timeout parameter, so a slow 3D-P API extends the owner's wait. If the
   dialog now takes noticeably longer, report it — the mitigation would be a follow-up task, not a
   change inside this one.
8. After `3D-P-014` is live, confirm each of the runs above produced exactly one
   `_Журнал_синхронізації` row with the expected outcome.

## 9. Rollback note

Single-line rollback: remove the `sync3dpPackagingCost_(sales, order, rows);` call from
`updateSaleStatus()` and publish a new Web App version. The menu form returns to its pre-patch
behaviour immediately; nothing else in the order-save path was modified.

Data written before rollback stays valid and needs no cleanup — the 3D-P rows and ledger entries
are the same shape the dashboard path produces. If a bad row must be undone, archive it through the
3D-P API rather than deleting it directly, and counter-adjust stock through
`3dp_adjust_stock` with an explicit reason, per the existing archive-not-delete rule.

Keep the previous CRM script version available in the Apps Script version history before
publishing.

## 10. Recommended status after execution

`In progress` until the owner completes the production QA above. Move `3D-P-010` to `Done` only
when steps 1–8 pass, since this is the work package that makes the whole task functional.
Notion status is written by Claude (chat); `ROADMAP_FLOW` subtask `4` is the executor's to update
within this authorised implementation.
