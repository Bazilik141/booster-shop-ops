# Codex Handoff — 3D-P-006: Owner dashboard tab («3D-друк» section in booster-dashboard.html)

Date: 2026-07-31 | Parent: 3D-P-000 (discovery & scoping) · related: 3D-P-001, 3D-P-005, 3D-P-007
Codex config: model=Sol · effort=xhigh

## Context

New business line (3D-printed TCG merch with the owner's friend Serhiy). Owner decided the architecture on
2026-07-28 (documented in 3D-P-005) and reconfirmed it 2026-07-31: **no new backend/VPS for now**. Owner's
own view = a new tab inside the *existing* `dashboard/booster-dashboard.html`. Serhiy's view (separate task,
3D-P-007) = a local server on his own machine hitting the same read API.

Data currently lives in a standalone Google Sheets workbook. **Live Sheet URL — v4, confirmed uploaded and
verified 2026-07-31 (content spot-checked via Drive `read_file_content`, formulas resolve correctly, owner is
sole editor):**
`https://docs.google.com/spreadsheets/d/13FwH5YH7ju0_854jpzzS08E6c7PGUroiE-Z6UnaAlYk/edit`
(tabs: Легенда, Номенклатура, Друк-лог, Продажі, **Виплати**, Маркетингові_плюшки, Наявність, Аналітика).
`Продажі` has a new `S` helper column (auto period key, `YYYY-MM`) that `Виплати` depends on for its
per-period payout aggregation. Build against this URL/schema.

**Note:** an older v3 Sheet (`1tu_koVE-4hOsRzq4wgdOW4W0cAaKVItd-IQRqJfIxbA`) is still live in Drive — do not
read from it, it lacks the `Виплати` tab and `Продажі!S` column. Owner may want to archive it later; not this
task's concern.

Add `action=3dp_payouts` (reads `Виплати`: period, accrued amount, review deadline, actual payout date,
status, notes) as a fifth read-only action alongside the four below. It is **not** connected to the live
CRM/OpenCart in any way — treat it as a fully separate data source.

**Owner decision, 2026-07-31: Серій does NOT get direct editor access to this Google Sheet.** His access path
is exclusively his own local server hitting the Apps Script Web API (this task's read-only actions) — matches
plan §13, already the intended architecture, now explicitly confirmed. Do not provision Sheet-level sharing
for him as part of this or any task.

Full target-state vision is `plans/3D-P_handoff-chatgpt_v1_20260728.md` (24 sections — a whole separate app
with dual UI, versioned costing, CRM sync, etc.). **This task is deliberately a small subset of that vision's
§10 (owner interface), not a full implementation.** Do not attempt to build Product/Variant/CostVersion/
Settlement entities or the full calculator (§6) — that is explicitly out of scope here.

Reference for the existing dashboard's page/nav/API pattern (mirror this, don't invent a new pattern):
- Sidebar nav items: `dashboard/booster-dashboard.html` ~line 316-348 (`<nav class="sidebar">`, `onclick="showPage('xxx')"`)
- Page section pattern: ~line 355 onward (`<div id="page-overview" class="page active">` ... `<div id="page-stock" class="page">` etc.)
- Existing CRM API client pattern to mirror (NOT reuse — see "What NOT to touch"): ~line 519-555
  (`const API = 'https://script.google.com/macros/s/.../exec'`, `TOKEN_STORAGE_KEY` in localStorage, `call(action, extra)` via GET, `callPost(payload)` via POST, both require `token`).

## Scope (what to change)

- **New Apps Script Web App**, bound to the live v4 Google Sheet (see URL above). Read-only GET actions only
  in this first version:
  - `action=3dp_overview` — aggregate counts (SKU count, наявно/продано/видано з вкладки Наявність, сума
    "Нараховано Сергію" за поточний місяць з Продажі)
  - `action=3dp_skus` — join Номенклатура + Наявність
  - `action=3dp_sales` — rows from Продажі (include the P/Q/R discount columns)
  - `action=3dp_plyushky` — rows from Маркетингові_плюшки
  - `action=3dp_payouts` — rows from Виплати (period, accrued amount, review deadline, actual payout date,
    status, notes)
  - Auth via a **new, separate token** (e.g. `BOOSTER_3DP_TOKEN`), stored as an Apps Script Script Property,
    never hardcoded in any client file. This is the same lesson already closed in CRM-003 — do not repeat that
    mistake on a new script.
- `dashboard/booster-dashboard.html`:
  - New sidebar nav item: `<div class="nav-item" onclick="showPage('3dprint')">🖨️ 3D-друк</div>` (icon
    negotiable), inserted after the existing `Роадмап`/`Облік` items.
  - New `<div id="page-3dprint" class="page">` section, following the exact structural pattern of
    `page-overview`/`page-stock` (header, cards, section blocks with loading spinners).
  - New JS: a **separate** API client (`API_3DP` constant, **separate** `TOKEN_3DP_STORAGE_KEY` localStorage
    key, **separate** token prompt) — do not extend or reuse the existing `API`/`TOKEN`/`call()` used for the
    main CRM. These must stay two independent credential paths.
  - Render: overview cards (SKU count, наявно, нараховано Сергію поточний місяць), SKU table (Номенклатура +
    Наявність), sales log table (Продажі, including discount type/param columns), plyushky log table, payouts
    table (Виплати — period, accrued, review deadline, actual payout date, status). This is the realistic
    subset of §10 the current flat-Sheet data model supports — not the full §10 feature list (no калькулятор
    UI, no виробництво kanban, no ринкові аналоги browser in this pass).
  - `ROADMAP_FLOW` mirror: add/update the `3D-P-006` entry status per `ROADMAP_SOP.md` once this lands.

## What NOT to touch

- Main CRM Apps Script project/webapp and its `BOOSTER_CRM_TOKEN` / Script Properties — completely separate
  credential and completely separate script. Do not add 3D-P actions to the existing CRM `doPost`/`doGet`.
- Existing dashboard pages (`page-overview`, `page-stock`, `page-orders`, `page-skus`, `page-clients`,
  `page-roadmap`, `page-accounting`) and their JS state/functions — additive only, no refactor of shared code
  unless a genuinely shared helper is obviously reusable (ask first if unsure).
- The `3D-P_nomenclature-tracker` workbook itself — this task **reads** it via Apps Script, it does not modify
  its structure, formulas, or the Легенда documentation. Any write-back is out of scope (per `bs-crm-plan`
  hard rule: no write actions without a separate, explicit owner approval).
- `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess`, checkout, payment, fiscalization,
  Merchant feed, Product schema — none of this task touches those, and none of it should.
- 3D-P-007 (Serhiy's local server) — separate task, out of scope here beyond making sure the new Apps Script
  API is reusable by it (same actions, same token scheme, no dashboard-specific coupling).

## Acceptance criteria

- [ ] New Apps Script webapp deployed, responds to
      `action=3dp_overview`/`3dp_skus`/`3dp_sales`/`3dp_plyushky`/`3dp_payouts` with real data from the v4
      Sheet, rejects requests with a missing/wrong token.
- [ ] `BOOSTER_3DP_TOKEN` exists only as an Apps Script Script Property — `grep` the diff confirms it is not a
      literal string in any `.html`/`.js` file.
- [ ] New "3D-друк" nav item visible in the sidebar, clicking it shows `page-3dprint` without touching other
      pages' state.
- [ ] Overview cards, SKU table, sales table, plyushky table render real data — Чармандер SKU (`FIG-CHARM-001`)
      visible in the SKU table as the concrete proof point.
- [ ] Existing main-CRM pages (Огляд, Склад, Замовлення, Товари, Клієнти, Роадмап, Облік) still work exactly as
      before — no regression in shared JS/CSS.
- [ ] `ROADMAP_FLOW` entry for `3D-P-006` added/updated in `dashboard/booster-dashboard.html`.

## QA checklist (owner runs after deploy)

- [ ] Open dashboard, hard-refresh (per existing `hardRefresh()` convention), confirm the two token prompts
      (main CRM token, new 3D-P token) are independent — entering only one does not unlock the other page.
- [ ] Confirm the "3D-друк" tab shows the Чармандер SKU with the same numbers as the xlsx workbook.
- [ ] Spot-check one existing page (e.g. Огляд) still loads correctly — no shared-code regression.
- [ ] Confirm no new token string appears in page source (View Source / dev tools) for either token.

## Rollback note

- `git revert` on the `dashboard/booster-dashboard.html` diff restores the file to its pre-3D-P-006 state
  (additive change, low blast radius on existing pages if the nav-item/page-block insertion is clean).
- The new Apps Script webapp is a separate deployment — roll back by redeploying the previous Apps Script
  version (Apps Script keeps versioned deployments) or disabling the new webapp URL; this has zero effect on
  the main CRM Apps Script since it is a fully separate script project.
- No database or OpenCart changes in this task, so no server-side rollback is needed beyond the two items above.

## Risks

- **CRM risky zone** per `AGENTS.md` — extra care, rollback plan above, and the QA checklist above must run
  before this is marked Done in Notion.
- Scope-creep risk: it is tempting to build more of `plans/3D-P_handoff-chatgpt_v1_20260728.md` §10 while in
  this file (калькулятор, виробництво, ринкові аналоги) — do not. Open a new bounded task for each extra slice.
- If the live Google Sheet ID/URL for the workbook is not yet known or the workbook is still only local
  (`3d-print/3D-P_nomenclature-tracker_v3_20260731.xlsx`, not yet uploaded to Sheets), **stop and ask the owner**
  — Codex should verify against the actual live Sheet, not assume the local xlsx is already the synced copy.

## Recommended status after execution

`In progress` in Notion until owner QA passes → then `Done`, and only then update `3D-P-007`'s blocker note to
reflect that the shared API contract exists.
