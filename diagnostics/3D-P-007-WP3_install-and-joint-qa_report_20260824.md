# Codex Report — 3D-P-007 WP3: install package and joint QA

Date: 2026-08-24

## Outcome

WP3 local deliverables are ready. The draft-type contract test detects value or
order drift, the Windows distribution can be assembled with portable Node.js
without placing the runtime in the repository, and separate Ukrainian files now
cover Serhiy's first launch and the owner + Serhiy live QA.

No live QA was run. Installation on Serhiy's machine, Windows user-variable
persistence with the real credential, and every workbook write/refusal remain
the owner + Serhiy gate described in `Спільна перевірка.txt`.

## Scope

Implemented only under:

- `3d-print/serhiy-local-server/**`
- `scripts/`
- this report in `diagnostics/`

Not touched in this WP3 execution: Apps Script, dashboard, CRM, Notion,
deployment, Git commit, or Git push. Existing uncommitted WP2 edits in the local
server were preserved and extended rather than replaced.

The handoff requested a staging ignore while the owner's active scope excluded
the repository-root `.gitignore`. The bounded resolution is
`scripts/.gitignore`; the assembly script itself stages under the Windows temp
directory, so Node never exists under the repository even temporarily.

## Step 1 — draft-type contract proof

Added `tests/draft-type-contract.test.mjs`. It:

1. loads `NOMENCLATURE_DRAFT_SUGGESTIONS_3DP` from the existing Apps Script
   mirror in a VM context;
2. parses the `draftTypes` literal from `public/app.js`;
3. compares the complete label arrays with `deepEqual`, which checks value,
   length, and order.

### Required negative proof

A scratch copy changed only the first client label:

```text
actual:   Брелок (змінено для перевірки)
expected: Брелок
```

Result:

```text
tests 1
pass 0
fail 1
AssertionError: Serhiy draft types stay aligned with the API mapping
```

The scratch file was then deleted. The production client list and Apps Script
mapping were not changed.

### Restored/current proof

```text
tests 1
pass 1
fail 0
```

The current 17 labels match by value and order.

## Step 2 — distribution package

### Launcher files

- `distribution/Запустити.bat`
- `distribution/Змінити токен.bat`
- `distribution/launcher.ps1`
- `distribution/Прочитай мене.txt`

The launcher:

- uses fixed `127.0.0.1:3107` and stops with a Ukrainian message if the port is
  occupied;
- reads Windows **user** variables and prompts only when they are missing;
- masks token input with `Read-Host -AsSecureString`;
- persists both values through `setx` with its output suppressed, then sets the
  same values in the current process so first launch can continue;
- preflights the API and requires the Serhiy projection marker
  `settings.range === B2:B5`; an owner-scoped credential is refused before the
  server or negative QA can continue;
- shows the API's own `UNAUTHORIZED` message plus the Ukrainian instruction to
  run `Змінити токен.bat`, with no retry loop;
- starts the bundled Node process, waits for port 3107, opens the browser, and
  keeps the server attached to the launcher window;
- never reads a `.env` file. `.env.example` remains documentation only.

The local server also appends the same Ukrainian recovery hint when an
`UNAUTHORIZED` response reaches the browser. Other API codes and messages still
cross the local boundary unchanged.

### Portable Node assembly

Assembly script: `scripts/build-serhiy-3dp-package.ps1`.

Expected build: **Node.js v24.19.0 LTS, Windows x64 portable zip**.

Official source:

`https://nodejs.org/dist/v24.19.0/node-v24.19.0-win-x64.zip`

The script accepts either that zip or its extracted directory, executes
`node.exe --version`, and stops unless the result is exactly `v24.19.0`. It
copies the local server, shared print-time module, launchers, instructions, and
portable runtime into a GUID-named Windows temp directory; produces the final
zip in the owner-supplied output directory; then removes staging.

### Real assembly verification

The official zip was downloaded to the Windows temp directory and supplied to
the script. Result:

```text
Package created: ...\Booster-3DP-Serhiy_Node-v24.19.0_20260824.zip
Node runtime: v24.19.0 (portable Windows x64)
Repository runtime copies: 0
ZIP size (final launcher build): 40,345,394 bytes
```

The generated zip was expanded in Windows temp. Its bundled `node.exe` returned
`v24.19.0`, and the bundled `app/server.mjs` passed `node --check`. After the
final launcher quoting hardening, the package was assembled again; the final zip
contains 2,049 entries, three root `.bat` files, the masked prompt, `setx`, and
the quoted server path. Repository scans under both authorized roots found no
`.exe`, `.dll`, `.zip`, or `.7z`.

This proves assembly and packaged syntax locally. It does not prove Windows
SmartScreen behaviour, browser opening, user-variable persistence, or network
access on Serhiy's PC.

## Step 3 — joint live QA package

Added:

- `distribution/Спільна перевірка.txt` — full Ukrainian positive and negative
  checklist;
- `distribution/Перевірити заборони.bat` and
  `distribution/negative-qa.ps1` — five refusal checks with no secret output.

The checklist requires the exact named Sheets version
`Перед 3D-P-007 WP3 QA — 2026-08-24`, a designated active test SKU with no
history, and explicitly forbids `FIG-LUFFY-410`.

The positive half covers draft save/reload and K-cost comparison, Q/R/S,
actual-count stock correction, idempotent manufacture repeat, DRAFT creation,
and both append-once payout acknowledgements.

The negative helper first verifies the `B2:B5` Serhiy projection. Only then it
submits the bounded refusal probes for:

- settings read outside B2:B5;
- settings write outside B2:B5;
- payout-period creation;
- payout-period closing;
- canonical article assignment.

Any unexpectedly accepted response stops with `НЕБЕЗПЕКА`. The checklist also
requires manual live-journal evidence that Q/R/S were authored by role
`serhiy`, plus a walk through every Information block to prove that
`Продажі!N`, `Продажі!T`, and `Маркетингові_плюшки!G/H` identity data are absent.

Codex did not execute these scripts against the live endpoint.

## Files touched for WP3

```text
3d-print/serhiy-local-server/README.md
3d-print/serhiy-local-server/server.mjs
3d-print/serhiy-local-server/tests/server-local.test.mjs
3d-print/serhiy-local-server/tests/draft-type-contract.test.mjs
3d-print/serhiy-local-server/distribution/launcher.ps1
3d-print/serhiy-local-server/distribution/negative-qa.ps1
3d-print/serhiy-local-server/distribution/Запустити.bat
3d-print/serhiy-local-server/distribution/Змінити токен.bat
3d-print/serhiy-local-server/distribution/Перевірити заборони.bat
3d-print/serhiy-local-server/distribution/Прочитай мене.txt
3d-print/serhiy-local-server/distribution/Спільна перевірка.txt
scripts/.gitignore
scripts/build-serhiy-3dp-package.ps1
diagnostics/3D-P-007-WP3_install-and-joint-qa_report_20260824.md
```

## Verification

### Full local suite

```text
> npm test
tests 7
pass 7
fail 0
```

The suite uses fake localhost data and does not call the live workbook.

### Syntax and packaging gates

```text
node --check server.mjs                         PASS
node --check public/app.js                     PASS
PowerShell parser: launcher.ps1                0 errors
PowerShell parser: negative-qa.ps1             0 errors
PowerShell parser: build-serhiy-3dp-package.ps1 0 errors
Packaged Node version                          v24.19.0
Packaged server syntax                         PASS
Runtime/archive binaries in repository         0
```

## Owner run command

From the repository root, after downloading the official portable Node zip:

```powershell
& '.\scripts\build-serhiy-3dp-package.ps1' `
  -NodePath 'C:\Users\14bez\Downloads\node-v24.19.0-win-x64.zip' `
  -OutputDirectory 'C:\Users\14bez\Downloads'
```

Expected result:

```text
C:\Users\14bez\Downloads\Booster-3DP-Serhiy_Node-v24.19.0_20260824.zip
```

## Remaining owner + Serhiy gate

1. Build the zip with the command above and copy the extracted folder to
   Serhiy's PC.
2. Create the named Sheets version and select a history-free test SKU; do not
   use `FIG-LUFFY-410`.
3. Follow `Спільна перевірка.txt` in order and retain the journal/refusal
   screenshots.
4. Treat any `НЕБЕЗПЕКА`, missing `serhiy` author role, visible customer
   identity, or duplicate manufacture row as a stop condition.

Success is owner evidence from Serhiy's own machine, not the local green suite.

## Side effects and rollback

No external side effects were performed. The downloaded Node archive, generated
test zip, and expanded package existed only in Windows temp for verification.

Local rollback is limited to the WP3 files/hunks listed above. Do not revert the
pre-existing WP2 local-server edits when reviewing or rolling back this work.
