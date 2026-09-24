# Claude review — 3D-P-007 WP3: install package and joint-QA kit

Date: 2026-08-28 | Executor: Codex (delivered 2026-08-24) | Reviewer: Claude (chat)
Handoff: `handoffs/handoff_3D-P-007-WP3_install-and-joint-qa_20260824.md`
Codex report: `diagnostics/3D-P-007-WP3_install-and-joint-qa_report_20260824.md`

**Verdict: Deploy OK; є неблокуючі зауваження.**

Nothing in this package deploys. It produces a zip the owner carries to Serhiy's
machine, plus the checklists for the joint session. The real gate — the live QA
that closes `3D-P-015` — has not been run.

## Scope

Only `3d-print/serhiy-local-server/**`, `scripts/build-serhiy-3dp-package.ps1`,
`scripts/.gitignore` and the report. `3d-print/apps-script-3dp-api/Code.gs` still
carries its 2026-08-24 04:18 mtime, untouched.

The dashboard and `crm/apps-script/Code.gs` have both moved since, on 2026-08-28
— that is the CONTENT-QUALITY wave, a separate thread, not this package.

The handoff asked for a staging entry in the root `.gitignore`; Codex used
`scripts/.gitignore` instead because the root file was outside its scope. The
right call, and moot in practice: staging happens in a GUID-named Windows temp
directory and the `finally` block removes it under a `StartsWith($tempRoot)`
guard, so nothing is ever written inside the repository.

## Step 1 — the draft-type contract test

`tests/draft-type-contract.test.mjs` loads `NOMENCLATURE_DRAFT_SUGGESTIONS_3DP`
from the Apps Script mirror in a VM context, parses the `draftTypes` literal out
of `public/app.js`, and `deepEqual`s the label arrays — value, length and order.

Verified rather than taken on trust: `npm test` gives **7/7**, and altering one
label in a scratch copy here (`"Брелок"` → `"Брелок ЗМІНЕНО"`) makes the test
fail; restoring it makes it pass. The drift this closes is real and the guard
bites.

## Step 2 — launcher and package

`server.mjs` changed by 239 bytes against the WP2 version. Diffed in full: three
error strings translated to Ukrainian, plus an `UNAUTHORIZED` branch that appends
«Запусти "Змінити токен.bat"…» to the API's own message. **No route, calculator,
projection or UI behaviour changed.** Other API codes still cross the boundary
untouched.

### The launcher does something better than asked

`Invoke-IdentityCheck` probes `3dp_bootstrap` before starting anything and
**refuses to continue unless `settings.range` is exactly `B2:B5`**.

Checked against `Code.gs`: `bootstrapAction3dp_` takes its Serhiy branch only for
a Serhiy-scoped credential and returns `getRangeAction3dp_(…, 'B2:B5', …)`, whose
response carries `range: parsed.a1`, and `parseBoundedRange3dp_` returns the raw
uppercased input — so the string is literally `"B2:B5"`. The owner branch returns
`"A1:C5"`. The check therefore genuinely discriminates, and an owner-scoped
credential pasted onto Serhiy's machine is refused before the server starts. This
was not in the handoff.

### Credential handling

Masked `Read-Host -AsSecureString`; BSTR marshalled and zeroed in a `finally`;
`setx` output suppressed and the stored value read back and compared; the token
nulled after use and handed to Node through the inherited process environment,
never on a command line. `Invoke-IdentityCheck` has its own `catch` that prints a
fixed Ukrainian sentence rather than the exception, so the probe URL — which
carries the token as a query parameter — never reaches the console.

### Assembly

Staging in temp, guarded cleanup, Node pinned to `v24.19.0` and verified by
running `node.exe --version`, nothing copied into the repository. The Ukrainian
instruction file is plain, covers the failure paths, and tells Serhiy not to
photograph the token.

## Step 3 — joint-QA kit

`Спільна перевірка.txt` plus `Перевірити заборони.bat` / `negative-qa.ps1`. The
negative helper re-runs the same `B2:B5` identity check before any probe, then
submits five refusals and stops with `НЕБЕЗПЕКА` on any acceptance.

Traced what each probe would actually do if its guard failed:

| Probe | Guards | Worst case if unrefused |
|---|---|---|
| read `Налаштування!B1:B6` | read projection | none — a read |
| write `Налаштування` row 6 | role whitelist **+** `assertWriteTargetAllowed3dp_` bounding the sheet to `B2:B5` for everyone **+** non-numeric value rejected by `normalizedSettingsValue3dp_` | none reachable |
| `3dp_payout_create` `2099-12` | `assertOwner3dp_` only | **a real `2099-12` row appended to `Виплати`** |
| `3dp_payout_mark_paid` | role **+** `expected_period` mismatch | none reachable |
| `3dp_nomenclature_assign_sku` | role **+** `DRAFT-WP3-QA` does not exist → `ROW_NOT_FOUND` | none reachable |

Four of the five are double- or triple-guarded and cannot write even if the role
check were broken. Only the payout-period probe is single-guarded — inherent to
testing that guard — and its worst case is one obviously bogus far-future row
that the owner deletes. See finding 4.

## Findings

| # | Severity | Where | Issue |
|---|---|---|---|
| 1 | non-blocking | `distribution/launcher.ps1`, `Save-UserVariable` | `setx` receives the credential as a **command-line argument**, and on Windows another process running as the same user can read process command lines. `[Environment]::SetEnvironmentVariable($Name, $Value, "User")` writes the same user-scoped variable straight to the registry with no argument exposure — and the script already uses that API to read it back and to set the process copy. Same effect, strictly less exposure, one line. |
| 2 | non-blocking | `scripts/build-serhiy-3dp-package.ps1` | The Node archive is validated by **executing** `node.exe --version` — the binary is run to decide whether to trust it, with no integrity check. nodejs.org publishes `SHASUMS256.txt`; verifying the zip's SHA-256 before extracting is the correct gate. The owner downloads over HTTPS from the official URL, so exposure is small — but this binary is then carried onto a third party's machine, which is where hardening earns its keep. |
| 3 | non-blocking | build script, `foreach ($fileName in @("server.mjs", "package.json", ".env.example"))` | `.env.example` is copied into the shipped package. The server reads only `process.env` and has no `.env` loader, so a `.env` Serhiy might create from it would **silently do nothing while looking like it should work** — and it would be a second copy of the credential, which the handoff explicitly set out to avoid. Drop it from the package. |
| 4 | worth knowing, not a defect | `distribution/negative-qa.ps1` line 52 | If «створення періоду виплати» ever reports `НЕБЕЗПЕКА`, a real `2099-12` row now exists in `Виплати` and must be deleted. Say so in the checklist so the owner knows the message implies cleanup, not only a stop. |
| 5 | cosmetic | build script, `$packageName` | The package name hardcodes `20260824`. A rebuild after a code change produces a zip with the same name, so two different builds are indistinguishable by filename. Derive the date at build time. |

Nothing blocking. No secret in any tracked file, all three PowerShell files and
both JavaScript entry points parse, and the repository holds no runtime binary.

## What is proven and what is not

Proven: the client contract against a fake API, the assembly path, and the
packaged syntax.

Not proven, and this is the whole remaining point of WP3: installation on
Serhiy's machine, Windows user-variable persistence with the real credential,
SmartScreen behaviour on a downloaded zip, and every live workbook write and
refusal. Codex ran none of the distribution scripts against the live endpoint,
and says so.

## The gate this exists to satisfy

`3D-P-015` closes on evidence from Serhiy's own machine, in the rewritten
2026-08-16 wording:

- `Q`/`R`/`S` writes appear in the live change journal **with role `serhiy` as the author**;
- payout period creation and closure are refused;
- `Налаштування` outside `B2:B5` is refused;
- no order or customer identity is visible in any Інформація block.

`Спільна перевірка.txt` covers all four and requires the named Sheets version
`Перед 3D-P-007 WP3 QA — 2026-08-24`, a history-free test SKU, and explicitly
forbids `FIG-LUFFY-410`. Keep the journal rows and refusal messages — that
evidence is what closes the task.

---

# Blocking defect found in owner testing, 2026-08-28

**Verdict revised: Return for changes.** The launcher cannot run. Found when the
owner reached step 5 of the dry run and double-clicked `Запустити.bat`:

```
'Policy' is not recognized as an internal or external command,
operable program or batch file.
'?тисни' is not recognized as an internal or external command,
operable program or batch file.
```

## Finding 6 — blocking

**All three `.bat` files are written with bare LF line endings.** Verified on the
owner's uploaded copy: 278 bytes, **0 CRLF, 9 bare LF**, no BOM. `cmd.exe`
requires CRLF in batch files; with LF only it loses line boundaries, which is
exactly why `-ExecutionPolicy` split into a phantom `Policy` command and why
`echo Натисни…` lost its `echo` and its first character.

The repository already states the correct rule. `.gitattributes` carries:

```
*.ps1 text eol=crlf
*.bat text eol=crlf
*.cmd text eol=crlf
```

Those attributes apply on checkout and commit. **The WP3 files are uncommitted**,
so git never normalised them, and `build-serhiy-3dp-package.ps1` copies the
working-tree files verbatim into the zip. The package therefore ships broken
launchers to a non-technical user on a machine where nobody can debug them.

`launcher.ps1`, `negative-qa.ps1` and `build-serhiy-3dp-package.ps1` are LF too.
PowerShell tolerates that, so it is not blocking — but it violates the same
declared convention and should be fixed in the same pass.

### Second defect in the same files

Even with CRLF, `chcp 65001` on line 2 is followed by non-ASCII text on lines 3
and 7 (`title Booster Shop — 3D-друк`, `echo Натисни…`). `cmd.exe` tracks its
position in the batch file by byte offset and re-decodes after a codepage change,
which desynchronises it on multi-byte characters — a long-standing cmd behaviour
and a second, independent cause of the same class of error.

**The `.bat` files must be ASCII-only.** All Ukrainian text belongs in the
PowerShell scripts, which already set `[Console]::OutputEncoding` themselves and
handle UTF-8 correctly. A bare `pause` prints the system-localised prompt and
needs no literal text at all.

### Why neither the executor nor this review caught it

The report's verification block lists `PowerShell parser: 0 errors` for all three
`.ps1` files and `node --check` for the JavaScript — nothing parses a `.bat`,
because nothing can: cmd has no syntax checker. The report is explicit that
"Windows SmartScreen behaviour, browser opening, user-variable persistence" were
unproven, and double-clicking the launcher falls in that same unproven band.

This review inspected the `.bat` contents and found them correct **as text**, and
did not check their line endings. For a Windows launcher that is the one property
that decides whether the file runs at all. Recorded so the next packaging review
starts with `file`/byte-level checks on every `.cmd`/`.bat`, not with reading them.

### Required fix

- rewrite all three `.bat` files with **CRLF** and **ASCII-only** content;
- move every Ukrainian string out of them and into the `.ps1` files;
- normalise the three `.ps1` files to CRLF for consistency with `.gitattributes`;
- add a guard to `build-serhiy-3dp-package.ps1` that **refuses to build** if any
  file it is about to copy into the package has bare-LF endings or non-ASCII
  bytes in a `.bat` — the packaging step is the last place this can be caught
  before the zip reaches someone who cannot diagnose it;
- re-run the assembly and confirm from the produced zip, not from the source
  tree.

Everything else in this review stands. The defect is in packaging only; no route,
guard, projection or test behaviour is affected.

## Finding 7 — blocking, same family

After the `.bat` files were repaired locally and the launcher actually started,
`launcher.ps1` failed to parse:

```
$Host.UI.RawUI.WindowTitle = "Booster Shop вЂ” 3D-РґСЂСѓРє"
Unexpected token '3D-РґСЂСѓРє"…' in expression or statement.
```

`РґСЂСѓРє` is UTF-8 «друк» decoded as CP1251. **The `.ps1` files are UTF-8
without a BOM.** `Запустити.bat` invokes `powershell.exe` — Windows PowerShell
**5.1** — and 5.1 reads a BOM-less script using the system ANSI codepage, not
UTF-8. Every Ukrainian string literal turns into mojibake, which breaks the
quoting and cascades into the parse errors above.

The fix is a **UTF-8 BOM** on `launcher.ps1` and `negative-qa.ps1`. Switching the
`.bat` to `pwsh` is not an option: PowerShell 7 is not present on a stock Windows
install and Serhiy will not have it.

### Why the executor's check passed

The report lists `PowerShell parser: launcher.ps1 — 0 errors`. That check almost
certainly ran under PowerShell 7, which defaults to UTF-8 and parses the file
fine. The script is then launched by 5.1, which does not. The artefact was
validated in a different runtime from the one that runs it — the same shape of
error as the line endings in finding 6, and the same lesson.

### Required fix, consolidated with finding 6

`build-serhiy-3dp-package.ps1` must refuse to build unless, for every file it is
about to copy:

- `.bat` — CRLF line endings **and** ASCII-only bytes;
- `.ps1` — a UTF-8 BOM (`EF BB BF`), and CRLF for consistency with `.gitattributes`;
- the built zip is then re-opened and the same checks re-run against its contents,
  because the source tree passing is not evidence that the package does.

Both defects are packaging-only. Nothing in the client, the API contract, the
guards or the tests is affected — every one of those still stands as reviewed.

## Finding 8 — blocking, and it is not packaging

Running the server directly, so its output stayed visible, produced the real
fault:

```
Сторінка Сергія працює: http://127.0.0.1:3107
Error: Not found.
    at fail (…/app/server.mjs:53:17)
    at serveStatic (…/app/server.mjs:407:31)
    at Server.<anonymous> (…/app/server.mjs:442:42)
  status: 404, code: 'NOT_FOUND'
Node.js v24.19.0
```

**The server process dies on any 404.** `server.mjs:442` is

```js
if (request.method === "GET") return serveStatic(response, url.pathname);
```

inside the request handler's `try`. In an `async` function, `return promise`
leaves the `try` block before the promise settles, so the `catch` below **never
sees the rejection** — it becomes an unhandled rejection and Node ≥15 terminates
the process. Every other route in that handler is written `return json(…, await …)`
and is correctly covered; this is the single line that is not.

`serveStatic` throws `NOT_FOUND` for any path whose extension is outside `MIME`.
`.ico` is outside `MIME`. **Every browser requests `/favicon.ico` immediately
after loading a page**, so the sequence on every single launch is:

1. `GET /` → `index.html` served;
2. `GET /favicon.ico` → throw → unhandled rejection → **server exits**;
3. the page's own `GET /api/bootstrap` finds nothing listening → «Failed to fetch»;
4. any later request → `ERR_CONNECTION_REFUSED`.

That is the complete explanation for both symptoms the owner reported, and it
retracts the launcher hypothesis raised before this evidence arrived — the
launcher was doing its job.

**Fix:** `return await serveStatic(response, url.pathname);`. One word. Consider
also serving a 204 for `/favicon.ico` so a normal browser request is not an error
path at all, and adding `process.on('unhandledRejection')` so a future escape
logs instead of killing the process.

### Provenance and why every gate missed it

The line is **not new**. It is identical in the WP2 file and in the original
2026-08-01 package — `grep` confirms the same statement at line 440 in the WP2
copy and 442 here. The package has carried this since the day it was written and
nobody had ever opened it in a browser: the 2026-08-02 «4/4 tests» run, WP2's
6/6, WP3's 7/7 and my own re-runs of all of them drive the API routes against a
fake API and never request an unknown static path. A green suite proved the
contract and said nothing about whether the thing runs.

This review approved WP2 having read `serveStatic` and the handler, and did not
notice that one `return` lacked its `await` while its fourteen neighbours had it.
Recorded plainly: three rounds of review and three green suites did not find a
defect that the first real browser load surfaced in seconds.

### Consequence for the work package

Findings 6 and 7 are packaging. **This one is the client**, so the WP3 fix scope
now includes `server.mjs`, and the acceptance criterion for WP3 has to change:
a green `npm test` is not sufficient evidence. The package must be started and a
real browser pointed at it, with the page loading its data, before WP3 is
delivered again.
