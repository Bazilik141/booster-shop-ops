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
