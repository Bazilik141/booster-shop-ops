# 3D-P-007 — Owner QA: local UI feedback and draft cleanup

Date: 2026-08-31
Executor: Codex
Workspace: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`

## Outcome and scope

Owner items 1–6 are implemented in the Serhiy local UI. Item 7 is partially addressed: new drafts no longer submit invented `Не вказано` values for optional C/E/F. The existing live row and its validation rules have NOT been changed. The stock discrepancy has a separate, confirmed live-sheet cause; no stock values or formulas were changed.

Only local-server source/tests and this diagnostic were authored in this round. The package builder was executed, not edited in this round. Existing unrelated working-tree changes were preserved. No commit, push, Notion action, Apps Script deployment, CRM write, or live data mutation was performed. Approval of an EXE updater is recorded as a separate next work item; an updater is not included in this package.

## Implemented changes

| Owner item | Result |
| --- | --- |
| 1 | Removed the duplicate no-saved-calculation message below the calculator. |
| 2 | Kept `Можна: 1:30, 1 год 30 хв або 1,5.` static. Parsing errors have a separate element and do not replace the hint. |
| 3 | Save/manufacture actions share one row, including narrow screens; button text can wrap inside each button. |
| 4 | Immediate pending text, busy indicators, disabled controls, retained success/error feedback, duplicate-submit lock, and authoritative read-back after manufacture. If the sheet reports unchanged stock after accepting a batch, the UI warns against repeating the batch. |
| 5 | Top loading/no-draft statuses use a high-contrast bordered notice. The top no-draft notice remains, reconciling item 5 with removal of the bottom duplicate in item 1. |
| 6 | Draft action shows `Створюю…`, disables during the operation, and uses the former owner-suggestion area for pending/success/error feedback. The form clears only after confirmed creation and remains populated after rejection. Owner SKU suggestions are no longer rendered. |
| 7 | Removed client-generated C/E/F placeholders for future drafts. Existing FIG-123-500 validation warnings remain a separate repair gate. |

Root causes: the old action handlers had no pending state; reload replaced action success with generic read status; draft creation used `event.currentTarget.reset()` after an `await`, when `currentTarget` was no longer available. The old time renderer replaced the hint. The old action group occupied one grid cell and wrapped the buttons. Draft creation explicitly invented C/E/F values.

The new operation runner distinguishes an accepted write from a failed subsequent refresh. A refresh failure says the operation succeeded, offers `Оновити дані`, and warns not to resubmit. SKU reads discard stale responses when selection changes.

## Files changed in this round

- `3d-print/serhiy-local-server/public/app.js`
- `3d-print/serhiy-local-server/public/index.html`
- `3d-print/serhiy-local-server/public/styles.css`
- `3d-print/serhiy-local-server/public/operation-state.js` — new state helper
- `3d-print/serhiy-local-server/tests/operation-state.test.mjs` — new regressions
- `3d-print/serhiy-local-server/tests/settings-controls.test.mjs` — extended HTML contract
- `3d-print/serhiy-local-server/tests/server-local.test.mjs` — updated draft contract
- `3d-print/serhiy-local-server/tests/ui-fixture.mjs` — isolated browser fixture, excluded from delivery
- This diagnostic.

No theme overrides or `!important` were introduced. Existing local `.status`, `.actions`, and responsive rules were edited at their source; the busy icon uses a reduced-motion-aware animation. No UI `setTimeout`, fixed positioning, or absolute positioning was added. The fixture intentionally delays responses with a timer.

## Automated and browser verification

`npm test`: **14 passed, 0 failed**. Includes exact 17-type parity, server routes/error boundary, settings/calculator regressions, immediate pending state, duplicate exclusion, write rejection, and post-confirmation refresh/render failures. A sandboxed attempt failed with process-spawn EPERM; the successful run used Windows PowerShell outside that restriction.

`node --check` passed for app.js, operation-state.js, and ui-fixture.mjs. Scoped `git diff --check` passed.

The actual in-app browser interacted with the UI at `http://127.0.0.1:3108/`, backed solely by the local fixture. The fixture does not load credentials or contact external APIs. Default write delay was 2.4 seconds; the draft success scenario used 10 seconds.

| Browser scenario | Observed evidence |
| --- | --- |
| Save | Immediate `Зберігаю…`, both actions disabled; final `Розрахунок збережено.` survives refresh. Exactly one save recorded. |
| Manufacture | Immediate `Записую партію…`; stock tile changed 8 → 10, SKU stock 3 → 5, retained success with row number. |
| Draft | Immediate `Створюю…` and local busy notice; success clears the captured form. Submitted values contain B/D/L and optional actual form fields, but no C/E/F placeholders. |
| Rejected draft | Visible `QA_WRITE_FAILED`; entered name retained and submit re-enabled. |
| Accepted draft, failed refresh | Warning begins `Чернетку виробу створено.` and instructs refresh without repeating the write; form cleared. |
| Accepted manufacture, frozen stock | Warning reports unchanged stock and advises not to repeat; no optimistic stock increment. |
| Time input | `1:30` and `1 год 30 хв` normalize to 1.5; static hint survives. Invalid text shows a separate error and disables calculation actions. |
| Rapid SKU changes | Final BR-TEST-100 remains selected with blank draft values after the slower FIG response completes. |
| Responsive layout | Widths 1280/820/390 tested with a long Ukrainian product name. Paired button Y coordinates: 617.25/617.25, 695.5/695.5, 1182.75/1182.75. No document-level horizontal overflow. At 390, each action is 155px wide × 62px high. |
| Keyboard | Focus outline observed: solid 3px. Hover/active rules were source-checked; a held-pointer active state was not independently captured. |
| Console | No error/warning entries returned by the browser log query. Expected fixture HTTP failures were handled in the UI. |

Screenshot capture is a limitation of this run: repeated `Page.captureScreenshot` calls timed out, including on the extracted package page. No new PNG was created and no older screenshot is presented as new proof. Real-browser navigation, DOM, computed layout, interactions, and console checks above succeeded. Visual screenshot review remains pending; this is not a claim of completed owner visual acceptance.

## Rebuilt and extracted package

- Archive: `3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260831.zip`
- Size: **35,825,112 bytes**
- SHA-256: `B3EB5E9C33B15866A05A53564FA06A352397729DE454C51017E9912102F3BB76`
- Build guards passed: source/archive script encodings, BAT/VBS reference existence in source/staging/archive, portable Node v24.19.0.
- No loose Node runtime was placed in the repository; no executable/archive is Git-tracked.
- Extracted to `C:\Users\14bez\AppData\Local\Temp\Booster3DP-UXQA-20260831-12b439c809294ce6a4afcb97dd8100ec\Booster-3DP-Serhiy_Node-v24.19.0_20260831`.
- Extracted app.js, index.html, styles.css, operation-state.js, settings-controls.js, and server.mjs match source hashes.
- Started **the extracted runtime and server**, hidden, PID **51436**, on **127.0.0.1:3109**. This run used node.exe directly with the existing user-scoped configuration; the default BAT launcher was not rerun because port 3107 was intentionally retained.
- Opened the extracted page in the real browser: live read succeeded, 8 active SKU, stock tile 211; console query returned no errors/warnings.
- Selected BR-BULB-100 read-only: visible loading notice, then saved calculation loaded; quantity 1, weight 15g, time 2h, spool 1000g/1000 UAH. Hint unchanged; buttons on the same Y=593.25 row, enabled. No save/manufacture/draft button was pressed against live data.

The existing Aug30 package process, PID **64760**, remains on **3107**, preserving the owner's open page. The new preview is **3109**, not 3107. Switching the default launcher to the new package should wait until the owner confirms there are no unsaved fields or in-flight actions. Runtime process IDs are a point-in-time observation, not permanent identifiers.

## Live read-only findings and remaining gates

### Stock tile — confirmed upstream formula issue

Dedicated 3D-P API reads returned:

- `Наявність!C2:G2`, ACC-3D-DITTO-410: `[99, 0, 0, 0, 101]`.
- C2 formula string is empty: **99 is a literal**, not a print-log SUMIFS formula.
- G2 uses C2 − D2 − E2 − F2 plus `_Коригування_наявності`.
- A comparison row, BR-BULB-100 at C5, has the expected print-log SUMIFS formula; C5:G5 returned `[1, 0, 0, 0, 1]`.

Therefore new print-log entries cannot update the printed count for Ditto while C2 remains literal. The local page already reads the authoritative overview after manufacture; fabricating a local increment would conceal this sheet defect.

**Gate:** reconcile the actual print-log total and existing correction before restoring C2. Replacing 99 blindly could materially change stock. No formula repair is included or authorized by this report.

### FIG-123-500 validation warnings

Dedicated API `3dp_get_row` confirmed Nomenclature row 9, active status, C/E/F=`Не вказано`, D=`Функціональний аксесуар`. The API projection does not expose dropdown validation rules. Owner screenshots show warning triangles; their exact validation message/allowed values were not provided.

**Gate:** owner to provide the tooltip from C9/E9/F9 (or the exact validation list), then agree the correct classification/blank-value repair. Existing row 9 is unchanged. Stopping new placeholder submission does not retroactively repair it and is not proof of live draft validation acceptance.

The 3D-P SOURCE_STATE mirror is stale (V29 export metadata predates later handoffs); it was inspected before source reference. The owner's V157 execution screenshot is CRM execution evidence, not proof of a 3D-P API deployment version. No API patch was inferred or made.

## Forbidden-surface verification

Start/end SHA-256 hashes match:

- `3d-print/apps-script-3dp-api/Code.gs`: `FDF22BFB2B6C659F6E727BE85447049826CB5355278C4ADC61CB417C633BEDF9`
- `crm/apps-script/Code.gs`: `37B0F62A07A0EE8214DE278D324A39E10814F8C120FC9F8CE21C74B9AD46C08F`
- `dashboard/booster-dashboard.html`: `6C28F3C6848EC0B5004A4F715D5F92EB01E5596551ECF147F1B60347BACB90A1`

## Rollback and next bounded action

The Aug30 ZIP/extracted directory remains intact. No live data rollback is needed because this run performed no live writes. For application rollback, stop only the verified new package process and keep using the existing Aug30 package; do not reset the dirty repository.

Next: owner checks the new UI on port 3109, supplies the validation tooltip, and confirms readiness to replace the old 3107 process. Formula reconciliation and the approved EXE updater are separate follow-up scopes. Before installation for Serhiy, complete owner visual acceptance and verify a real, explicitly authorized draft/save/manufacture cycle.
