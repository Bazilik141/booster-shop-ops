# Codex Handoff — 3D-P-007 WP3: install at Serhiy's + joint live QA

Date: 2026-08-24 | Parent: `3D-P-007` (Serhiy local server)
Executor: Codex · model=Terra · effort=high — bounded and multi-step, with no
architectural ambiguity left: the one open decision (where Serhiy's credential
lives) was settled by the owner on 2026-08-24 and is stated below. Owner assigned
Codex on 2026-08-24.

## Verified state — 2026-08-24, ~18:00 Kyiv

| Surface | State |
|---|---|
| 3D-P Apps Script | **V31** live. `3d-print/apps-script-3dp-api/Code.gs` is the published source. |
| CRM Apps Script | **V149** live, mirror byte-verified against the owner's export. |
| `3d-print/serhiy-local-server/` | WP2 delivered and reviewed — `Deploy OK`, `diagnostics/3D-P-007-WP2_patch-review_20260824.md`. `npm test` 6/6. Speaks the V31 projected contract. |
| Owner dashboard | WP2b live, closed in the roadmap. |
| Installation at Serhiy's | **Never performed.** He has never run this package. |

WP3 is the last work package in `3D-P-007` **and the outstanding closure gate for
`3D-P-015`.** Everything below exists to produce that gate's evidence.

## Step 1 — draft-type contract test (do this first)

Owner instruction, 2026-08-24: this is the first thing in WP3.

`public/app.js` carries the 17 draft type labels as a client-side literal
(`const draftTypes = [...]`). Nothing checks them against the API. They match
today — verified by hand in the WP2 review, 17 on each side in identical order —
but a change to `NOMENCLATURE_DRAFT_SUGGESTIONS_3DP` would silently leave Serhiy
with a type the API no longer recognises, and a null `sku_suggestion`.

- Add a test under `3d-print/serhiy-local-server/tests/` that reads
  `NOMENCLATURE_DRAFT_SUGGESTIONS_3DP` out of
  `../apps-script-3dp-api/Code.gs` in a VM context, parses `draftTypes` out of
  `public/app.js`, and asserts the label lists match **by value and by order**.
- Model it on `dashboard/tests/dashboard-contract.test.mjs`, which does exactly
  this for the owner dashboard.
- Prove it works rather than asserting it does: change one label in a scratch
  copy, confirm the test fails, restore, confirm it passes. Put both results in
  the report.
- `npm test` must stay green: 6 existing tests plus this one.

## Step 2 — distribution package for Serhiy

Owner decision, 2026-08-09, unchanged: **ship a folder with a `.bat` launcher.**
Not an installer, not a compiled `.exe` — a self-built Windows binary of this
kind gets flagged by antivirus, which replaces a small "install Node"
conversation with a much worse one. Updates are a folder replacement.

### Credential storage — owner decision, 2026-08-24

**The credential and the Web App URL are stored as Windows *user* environment
variables**, not in a file inside the folder.

The reason is the update model: updates replace the folder, so anything stored
inside it is destroyed on every update and Serhiy would have to re-enter the
credential each time. A user environment variable survives.

- First run: if `BOOSTER_3DP_SERHIY_TOKEN` or `BOOSTER_3DP_URL` is absent, prompt
  for it and persist with `setx`, then continue into the same session.
- Subsequent runs: no prompt.
- Provide a separate `Змінити токен.bat` (or an explicit flag on the launcher)
  so a rotated credential can be re-entered without editing anything by hand.
- **The credential must never** be written into a file inside the package, echoed
  to the console, logged, included in an error message, or committed. Mask the
  prompt input.
- `.env.example` stays as documentation. Do not add a real `.env` to the package
  and do not read one at runtime as a fallback — one storage path only, so there
  is never a stale second copy.

### Launcher behaviour

- `Запустити.bat` starts the server and opens `http://127.0.0.1:3107` in the
  default browser.
- If the port is already in use, say so plainly in Ukrainian and stop; do not
  silently pick another port, because the opened URL would then be wrong.
- If the credential is rejected by the API, show the API's own message plus one
  Ukrainian line telling Serhiy to run `Змінити токен.bat`. Do not retry in a
  loop.
- Closing the window stops the server. State that in the instructions.
- Everything Serhiy sees is Ukrainian. He is not a developer and has no terminal
  habits.

### The Node runtime — do not commit it

The 2026-08-09 decision calls for a portable Node runtime in the zip so Serhiy
never installs anything. A Node runtime must **not** enter the repository.

Deliver instead a **PowerShell assembly script** in `scripts/` that the owner
runs once: it takes a path to an already-downloaded portable Node, copies the
server package plus the launchers into a staging folder, and produces the zip.
Add the staging folder to `.gitignore`. State in the report exactly which Node
build the script expects and where the owner gets it.

### Instructions for Serhiy

A short Ukrainian instruction file inside the folder — `Прочитай мене.txt` or
equivalent. Plain language, no technical vocabulary, covering only: what to
double-click, what to do on first launch, what the browser page is, how to stop
it, and what to do when something does not open. **This file is for Serhiy, so
it is Ukrainian** — the usual English-for-agent-artifacts rule does not apply to
it.

## Step 3 — joint live QA (owner + Serhiy, not Codex)

Codex prepares the checklist as a separate Ukrainian file the owner can follow on
the call with Serhiy. Codex does not run it.

Every accepted write in this QA lands on the **live** workbook — there is no
staging. Create a named Google Sheets version before starting and use a
designated test SKU with no history.

**Positive half — Serhiy's own machine, his own credential:**

- the package launches from the shortcut and the browser page loads;
- batch draft saves and reloads; the displayed cost matches `Номенклатура!K`;
- `Q`, `R`, `S` save;
- a stock correction submitted as the **actual counted quantity** applies;
- one manufactured batch is logged, and a deliberate second submit of the same
  batch reports `already_applied` instead of creating a duplicate row;
- one product draft is created and stays `Чернетка` with a `DRAFT-` key and no
  article;
- both payout acknowledgements record, and neither append action is offered
  twice.

**Negative half — this is the `3D-P-015` gate, rewritten 2026-08-16:**

The old wording required proving Serhiy *cannot* write `Q`/`R`/`S`. That is now
by-design behaviour and the wording is void. The current gate is:

- every `Q`/`R`/`S` write from Serhiy's session appears in the change journal
  **with his role recorded as the author** — check the live journal, not the UI;
- creating or closing a payout period is refused under his credential;
- a `Налаштування` read or write outside `B2:B5` is refused;
- **no order or customer identity is visible anywhere** in his interface —
  `Продажі!N`, `Продажі!T`, `Маркетингові_плюшки!G`/`!H`. Walk every Інформація
  block, not only the sales table;
- article assignment is refused under his credential.

Capture the evidence — journal rows with the author, and the refusal messages —
because that evidence is what closes `3D-P-015`.

## What NOT to touch

- `3d-print/apps-script-3dp-api/**` — V31 is live and QA'd. If WP3 appears to
  need an API change, stop and report.
- `dashboard/booster-dashboard.html` and `crm/apps-script/**`. The CRM project
  moved to V149 in a parallel round; it is neither a target nor an authority here.
- `ROADMAP_TASKS` rows in the dashboard. Claude writes Notion status and the
  mirror in the same pass after owner QA.
- The WP2 client behaviour. Step 1 adds a test; it does not change `draftTypes`,
  the routes, the calculator or the UI. If the new test fails on the current
  code, that is a finding to report, not a licence to edit either side.
- `.env`, `.env.review`, `scripts/.env`, `client_secret.json`.

## Acceptance criteria

- [ ] `npm test` passes in `3d-print/serhiy-local-server/` with the new test included.
- [ ] The new test fails when a label is altered and passes when restored, both shown in the report.
- [ ] The launcher starts the server and opens the browser on a machine with no pre-set environment variables, prompting once.
- [ ] A second launch does not prompt.
- [ ] The credential appears in no file, no log line, no console echo and no commit; the prompt is masked.
- [ ] `Змінити токен.bat` (or the equivalent flag) replaces a stored credential.
- [ ] The assembly script produces a working folder from a supplied portable Node, and no Node binary is added to the repository.
- [ ] Serhiy's instruction file is Ukrainian and contains no technical vocabulary.
- [ ] The joint QA checklist exists as its own Ukrainian file and covers both halves above.

## Risks

- **A credential is being placed on a third party's machine.** It is Serhiy-scoped and revocable — the owner can rotate `BOOSTER_3DP_SERHIY_TOKEN` in Script Properties, which is why `Змінити токен.bat` matters. It never enters the repository, a report, a screenshot or this handoff.
- **Live workbook, no staging.** Named Sheets version before QA; designated test SKU.
- **First contact with a non-technical user.** If the launcher fails on his machine the recovery has to be something he can do alone. Prefer a clear Ukrainian message and a stop over any clever automatic retry.
- `FIG-LUFFY-410` carries a deliberate test buyout price. Serhiy can see it and it is the price the shop pays him. Settle it before any real Track-2 trade on that SKU — do not use it as the QA test SKU.
- Deliver the report as `diagnostics/3D-P-007-WP3_install-and-joint-qa_report_20260824.md`. Do not commit, push, deploy, or write Notion.
