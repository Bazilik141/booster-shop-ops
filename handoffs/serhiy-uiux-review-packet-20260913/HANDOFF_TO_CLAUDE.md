# Serhiy local server — UI/UX redesign and priority table fixes

Date: 2026-09-13  
Executor: Claude Code · model=Opus · thinking=high  
Reason: this is a multi-file, interaction-heavy UI task with a required audit before broader redesign decisions.

## Owner intent

Improve the local Serhiy 3D-print server's design and usability. The first two fixes below are mandatory. Then conduct a complete UI/UX audit and propose a coherent, phased redesign. Do not silently turn every proposal into a code change.

## Supplied current-state material

This packet contains:

- `CURRENT_BUILD/Booster-3DP-Оновлення_20260913.exe` — the current updater provided for installation; SHA-256 is in the adjacent `.sha256.txt` file.
- `serhiy-local-server-source_20260913.zip` — a source snapshot of the current local working tree. It intentionally contains no real URL, token, `.env`, Node runtime, or `node_modules`.

The source snapshot is **not a claim of committed, deployed, or live Apps Script state**. It contains current local uncommitted work. The updater is an existing artifact; do not rebuild or distribute a replacement unless the owner explicitly approves the reviewed code and package step.

## Product boundary

The app is a local-only Windows UI at `http://127.0.0.1:3107`. The Node server uses the Serhiy-projected 3D-P Apps Script API. It must not gain a Google Sheets/Drive client, owner CRM access, CRM synchronization controls, or any credential persistence beyond the existing Windows user environment-variable flow.

Apps Script publication and all production workbook writes remain owner-gated. Local tests and the UI fixture must never call the real workbook. Do not request, expose, log, package, or screenshot credentials.

## Mandatory implementation scope

### 1. Keep the column filter open after a checkbox is changed

Current root cause: `informationTable()` renders `<details class="column-picker">` anew at `public/app.js:65-71`. The delegated `change` handler at `public/app.js:196-202` saves a changed checkbox preference and calls `renderInformation()`. That replacement recreates `<details>` closed.

Required behavior:

- When a user ticks or unticks a column under `Стовпці`, the same table's column picker remains open.
- Keep keyboard focus on the changed checkbox, or move it predictably to the equivalent replacement control if a full re-render is retained.
- Do not regress saved column visibility, product-type filtering, sorting, or the existing Ukrainian labels.
- The behavior must work independently in every information table.

### 2. Paginate table blocks at 15 displayed records per page

Apply pagination to every tabular block that can contain multiple records:

- active print log;
- settings journal;
- analytics;
- all products;
- sales;
- payouts;
- marketing gifts.

Do not paginate the attention signal list, KPI cards, forms, or a single calculation result.

Rules:

- The page size is exactly **15 records**.
- Filter and sort the complete data set first, then paginate the resulting records.
- Show a clear range/total, for example `16–30 of 47`, and accessible Previous/Next controls; boundary buttons are disabled.
- Reset to page 1 when filter or sort changes. If the active page becomes empty after data refresh, move to the nearest valid page.
- Preserve existing per-table column visibility, type filter, sort direction, table actions, long-text wrapping, and horizontal table scrolling.
- Retain pagination state while the current screen remains open where it does not conflict with the reset rules above. Persisting it in existing local preferences is optional, but do not store sensitive data.

## Required audit and proposal — no unapproved broad redesign

After implementing the two mandatory fixes, inspect the whole interface and write a concise evidence-based redesign proposal. Assess at least:

1. Information architecture: three main zones, headings, ordering, and progressive disclosure.
2. Workflows: calculation → save → manufacture; product edit/stock correction/draft creation; information review and payout acknowledgements.
3. Dense forms: grouping, labels, units, required-state clarity, help/error text, and destructive/write-action cues.
4. Data tables: filters, columns, sorting, pagination, empty/loading/error states, and action discoverability.
5. Responsive layouts at 360px, 900px, and 1440px; include long Ukrainian product names.
6. Accessibility: keyboard traversal, focus visibility, semantic controls, `aria-expanded`/live status, contrast, and touch targets.
7. Visual consistency: hierarchy, spacing, typography, state colors, button priority, and the amount of information shown before a user needs it.

For each proposed improvement, state the user problem, evidence, expected benefit, affected files, risk, and whether it belongs in a small follow-up or a larger approved redesign. Do not change non-mandatory behavior in this round without owner approval.

## Existing behavior to preserve

- SKU search augments rather than replaces SKU dropdowns.
- Product-type filters apply only to SKU-related data; payout rows do not have a product type.
- Human-readable print time is kept, including zero-padded minutes.
- Text wraps in table cells and wide data can scroll horizontally.
- `operation-state.js` protections against duplicate/uncertain writes remain intact. In particular, a network failure must not encourage a blind retry of manufacture logging.
- Existing API payloads, endpoints, permission boundaries, request IDs, and Sheet column meanings are not changed for UI work.
- The UI remains Ukrainian.

## Key source entry points

| Area | Files | Notes |
|---|---|---|
| Page structure, zones, forms, information accordions | `public/index.html` | Current Calculator / Products / Information layout. |
| Rendering and delegated interactions | `public/app.js` | `informationTable`, `objectTable`, `renderInformation`, table event handlers. |
| Table transform | `public/information-tables.js` | Current type filtering and sorting; a good place for pure pagination logic. |
| Layout and component states | `public/styles.css` | Existing responsive breakpoints at 940px and 620px. |
| Safe state transitions | `public/operation-state.js` | Preserve write protection. |
| Isolated browser fixture | `tests/ui-fixture.mjs` | Starts fake local data only; use for visual/manual QA. |

## Verification required

Run and report:

```powershell
npm test
node --check .\server.mjs
node --check .\public\app.js
node .\tests\ui-fixture.mjs
```

Use the fixture at its reported `127.0.0.1` address. Confirm manually:

1. a column checkbox changes visibility but the picker stays open and focus remains usable;
2. every listed table shows 15 rows maximum and has correct page transitions;
3. filter/sort occurs before pagination and resetting behavior matches the rules;
4. product-type filtering, payout actions, settings toggle, search plus dropdown, and existing write controls still work;
5. 360px, 900px, and 1440px layouts, including a long Ukrainian name.

No live Apps Script call, Sheet write, credential use, publication, updater rebuild, commit, or push is authorized by this handoff.

## Expected return package

1. A narrowly scoped source diff implementing only the two mandatory fixes.
2. Added/updated deterministic tests for pagination and the open column-picker behavior.
3. `diagnostics/SERHIY-UIUX_redesign-audit_20260913.md` with the audit findings and a prioritized redesign proposal; no claims of live validation.
4. A concise report separating static/local tests, fixture/browser QA, and remaining owner gates.

## Stop conditions

Stop and ask the owner if any requested visual change requires an Apps Script/API-contract change, alters a write operation, needs a real credential, or would rebuild/distribute the updater. Also stop if current source and the supplied updater disagree on a behavior that affects safety or a required workflow.
