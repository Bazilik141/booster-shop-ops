# Codex Report — 3D-P-007: Serhiy UI/UX round 1

Date: 2026-09-13

## Scope

Implemented the attached redesign handoff's round-1 data-table work and the
separately scoped local attention signals:

- preserve the column picker DOM while changing a visible column;
- paginate projected tables locally at 15 rows, after type filtering and sort;
- add table controls to the active print log and settings journal;
- add local, individually hideable attention signals with stable IDs.

No Apps Script source, API request/payload, spreadsheet cell, credentials,
release, commit, or push was changed.

The relevant local-server files already had owner changes before this task. The
implementation preserves those changes; this report does not claim that every
existing working-tree change belongs to this round.

## Files touched

```
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/public/information-tables.js
3d-print/serhiy-local-server/public/attention-signals.js
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/public/styles.css
3d-print/serhiy-local-server/tests/information-tables.test.mjs
3d-print/serhiy-local-server/tests/ui-fixture.mjs
```

## Behaviour delivered

- The table toolbar remains in place while only the table and pager are
  replaced. An open column picker and the checkbox focus therefore survive a
  column visibility change.
- Sort and type-filter changes reset to page 1. A reload clamps an unavailable
  saved page to the final available page and persists the corrected value.
- Each table page reports its visible range, for example `16–30 із 47`, and
  disables previous/next controls at its bounds.
- Attention signals are generated only from already-projected local bootstrap
  data. Hidden IDs are kept in browser local storage, are removed when their
  underlying signal disappears, and reappear as active if the condition later
  returns.

## Local validation

```
node --check public\app.js
node --check public\information-tables.js
node --check public\attention-signals.js
npm test
```

Result: all syntax checks passed. `npm test` passed 25/25 tests, including six
table/attention tests. Windows sandboxing initially denied Node's child-process
spawn; the same local-only test command then passed in the permitted local
environment. No live endpoint or workbook was used.

## Browser fixture QA

The isolated `tests/ui-fixture.mjs` fixture was run on localhost with fake data.

- In `Всі вироби`, unchecking `Назва виробу` left `Стовпці` open and the same
  checkbox focused while only the table body and pager changed.
- In `Продажі`, 17 fake rows rendered as `1–15 із 17`; `Далі` rendered
  `16–17 із 17` and became disabled.
- Two fake attention signals rendered. Hiding one changed the active KPI and
  heading count from 2 to 1; its `Приховані` entry exposed `Повернути`.

## Owner QA remaining

- [ ] Check the shipped local build at 360, 900, and 1440 px.
- [ ] Confirm a real projected print-log and settings-journal table retain the
  expected Ukrainian labels, type filter, sorting, and column preferences.
- [ ] Confirm locally hidden attention signals remain hidden after a browser
  reload, then return after the underlying condition first clears and later
  reappears.

## Local updater package

Prepared for the owner's local test only; it has not been handed to Serhiy:

```
3d-print/serhiy-local-server/dist/Booster-3DP-Оновлення_20260913-uiux.exe
SHA-256: bafe05e20a68c63ee626915e8009a31a57ab204eb226a1511ab8d73711bc829f
```

The updater was exercised with `--target`, `--quiet`, and `--no-remember` on a
fresh extraction of the 2026-09-06 portable build. It exited `0`, installed the
new attention module and paginator, and created one `_update_backups` snapshot.
This is an isolated local smoke test, not owner QA of the installed dashboard.

## Risks and rollback

Risk is local UI state only. The new browser storage key contains stable signal
IDs, not workbook contents. Clearing that key only restores all signals to the
active list.

Rollback is a source revert of the files listed above. No data migration or
external rollback is required.
