# Codex Report — 3D-P-007 WP3b: package fixes and Serhiy UX

Date: 2026-08-30

## Outcome

WP3b and review findings 1-4 are implemented locally. Parts A and B were completed first, the final archive was built with official portable Node.js v24.19.0, extracted into a clean temporary directory, launched through the packaged `Запустити.bat` -> `start-hidden.vbs` chain, and opened in a real browser against the live read-only Serhiy projection.

No Apps Script, dashboard, CRM, Notion, deployment, commit, or push action was performed. No live form was submitted during browser QA.

## Scope

Implemented the handoff in:

- `3d-print/serhiy-local-server/**`
- `scripts/build-serhiy-3dp-package.ps1`
- this report and browser-proof images in `diagnostics/`

The pre-existing unrelated working-tree changes were not modified.

## Files changed

```text
.gitattributes
3d-print/serhiy-local-server/distribution/launcher.ps1
3d-print/serhiy-local-server/distribution/negative-qa.ps1
3d-print/serhiy-local-server/distribution/Запустити.bat
3d-print/serhiy-local-server/distribution/start-hidden.vbs
3d-print/serhiy-local-server/distribution/Змінити токен.bat
3d-print/serhiy-local-server/distribution/Перевірити заборони.bat
3d-print/serhiy-local-server/distribution/Прочитай мене.txt
3d-print/serhiy-local-server/distribution/Спільна перевірка.txt
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/public/settings-controls.js
3d-print/serhiy-local-server/public/styles.css
3d-print/serhiy-local-server/server.mjs
3d-print/serhiy-local-server/tests/server-local.test.mjs
3d-print/serhiy-local-server/tests/settings-controls.test.mjs
scripts/build-serhiy-3dp-package.ps1
diagnostics/3D-P-007-WP3b_browser-calculator-proof_20260829.png
diagnostics/3D-P-007-WP3b_browser-information-proof_20260829.png
```

## Part A — package blockers

- All shipped `.bat` and `.vbs` files are ASCII-only and CRLF.
- All shipped `.ps1` and `.txt` files are UTF-8 with BOM and CRLF; Windows PowerShell 5.1 parser result: 0 errors for the build, launcher, and negative-QA scripts.
- `.gitattributes` keeps VBS and the package distribution TXT files on CRLF after future Git operations.
- The build validates source distribution files before copying them and re-opens the finished zip to validate packaged `.bat`/`.vbs`/`.ps1`/`.txt` bytes again.
- Every packaged file referenced by a `.bat` or `.vbs` is checked in the source distribution, staging tree, and final ZIP. The first guard run exposed an overly broad `%~dp0` parse; the parser was corrected and the missing-reference mutation was repeated successfully.
- The packaged Node runtime now contains only the required `node.exe` plus upstream license/readme documents. This avoids shipping unused upstream `npm.ps1`, which is BOM-less and correctly failed the new package guard.
- Negative mutation proofs:
  - malformed/non-ASCII BAT: rejected with `must be ASCII-only`;
  - BOM-less PS1: rejected with `must be UTF-8 with BOM`.
  - missing referenced `start-hidden.vbs`: rejected;
  - LF-only VBS: rejected;
  - BOM-less TXT: rejected.

## Part B — blocking code defects

- Static GET handling now awaits `serveStatic()`. A missing asset produces a bounded JSON 404 instead of an unhandled rejection or server exit.
- `/favicon.ico` returns 204.
- A safe `unhandledRejection` logger reports only the error message and never credentials.
- Settings controls use names `setting_2` through `setting_5`; the shared helper maps every field to its own source row for both fill and save.
- Added a regression test that fills all four fake values and proves a row-4 edit emits row 4.

Final packaged-server probe:

```text
favicon.ico: 204
missing CSS: 404, code=NOT_FOUND
page after 404: 200
```

## Fixture investigation (read-only)

The direct live `3dp_fixtures` read under the Serhiy credential returned:

```text
action=3dp_fixtures
count=0
returnedRows=0
```

The real browser therefore correctly showed only `Без фурнітури / очистити`. The shared `Фурнітура_довідник` currently has no data rows returned by its table action; this is an owner data task, not a client or API code defect. The API was not changed.

## Part C — Serhiy UX

- Applied the exact requested header and three overview tiles; removed the page H1 and projection explanation.
- The calculator imports the same `lib/calculator.mjs` formula used by the server and recalculates on input without a Calculate button.
- Renamed the save action to `Зберегти розрахунок` and moved manufacture into the calculator as `Виготовити партію`, reusing quantity, total weight, and total time.
- Removed the defect input. Manufacture requests intentionally send `defects: 0` while retaining a stable retry `request_id` until success.
- Applied the requested product, stock, and draft labels.
- Draft fields C, E, F, and L are hidden. Exact submitted defaults are:
  - C / franchise: `Не вказано`
  - E / track: `Не вказано`
  - F / stage: `Не вказано`
  - L / date: local system date in `YYYY-MM-DD`
- The result shows only the owner suggestion; it does not present a DRAFT key as an assigned article.
- Empty tabular information sources still render a table with an explicit no-records row.

Defect-removal consequence: actual defective units are no longer entered from Serhiy's manufacture form and therefore do not write a non-zero value to `Друк-лог!E`. The defects aggregate remains zero for these submissions, and stock accuracy depends on the separate journalled stock-correction flow.

## Launcher behavior

- Normal entry point: `Запустити.bat`, which calls the Latin-only hidden launcher `start-hidden.vbs`; the browser QA process had `MainWindowHandle=0`.
- If Windows Script Host is blocked or returns an error, `Запустити.bat` falls back to a visible direct PowerShell launch. The fallback is documented in `Прочитай мене.txt`.
- If either user credential variable is missing, the hidden launcher opens a visible Windows PowerShell first-run prompt, saves the values with `.NET` user-environment APIs, validates identity, and continues through the hidden launcher.
- If port 3107 is already serving, relaunch opens the local URL and exits without starting another server.
- The page sends a heartbeat every 30 seconds. The server exits after approximately five minutes without a page heartbeat.
- Token-change and negative-QA BAT files remain visible.

Relaunch proof against the extracted final package:

```text
PID before: 50496
PID after:  50496
same server: true
HTTP after relaunch: 200
```

## Automated verification

`npm test` (Windows run outside the restricted child-process sandbox):

```text
tests 8
pass 8
fail 0
```

Additional gates:

```text
Node syntax checks: 4/4 passed
Windows PowerShell 5.1 parser: 3/3 scripts, 0 errors
distribution encoding: source and ZIP passed for BAT, VBS, PS1 and TXT
launcher references: source, staging and ZIP passed
git diff --check (authorized source scope): passed
forbidden legacy UI terms: 0
packaged credential hits: token=0, URL=0
packaged .env files: 0
browser console warnings/errors: 0
```

## Archive

```text
Path: 3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260830.zip
Size: 35,822,782 bytes
SHA-256: C45A6EE2E86FF326950785DBF826390A8DD9F88E8B15CE0A1919E0B856CEA88A
Runtime: official portable Node.js v24.19.0 win-x64
```

## Real-browser evidence

The final archive was extracted to a clean temporary directory. Its packaged `Запустити.bat` called `start-hidden.vbs` and launched that exact extracted `runtime/node.exe` on port 3107 with no visible window. A real browser loaded `http://127.0.0.1:3107/` and showed live projected data:

```text
status: Дані отримано через 3D-P API.
active SKU: 7
available stock: 212
settings rows 2..5: 0.11, 4.32, 12, 0.08
fixture rows: 0
tabular Information blocks: 5/5 rendered tables
```

The live calculator was exercised without submission using quantity 4, total weight 80 g, total time 2 h, spool weight 1000 g, and spool price 800 UAH. It recalculated immediately to 20 g and 0.5 h per unit and 24.02 UAH defect-adjusted Serhiy cost; both save and manufacture buttons became enabled.

Evidence files:

- `diagnostics/3D-P-007-WP3b_browser-calculator-proof_20260829.png`
- `diagnostics/3D-P-007-WP3b_browser-information-proof_20260829.png`
- `diagnostics/3D-P-007-WP3b_browser-launcher-review-proof_20260830.png`

The verified page was left open for owner inspection. The temporary server will stop approximately five minutes after the browser page is closed.

## Remaining owner gate

- Populate `Фурнітура_довідник` if fixture choices are required, then refresh the page and confirm the shared options appear.
- Run the write-bearing joint QA only with the designated history-free test SKU. This report contains read-only browser proof only.
- If the negative QA unexpectedly accepts payout-period creation for `2099-12`, stop immediately and correct that live data through the journalled owner flow.

No commit, push, deployment, Notion update, Apps Script edit, dashboard edit, or CRM edit was performed.
