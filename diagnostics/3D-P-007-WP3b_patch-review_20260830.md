# Claude review — 3D-P-007 WP3b: package fixes and Serhiy UX

Date: 2026-08-30 | Executor: Codex | Reviewer: Claude (chat)
Handoff: `handoffs/handoff_3D-P-007-WP3b_fixes-and-serhiy-ux_20260828.md`
Codex report: `diagnostics/3D-P-007-WP3b_fixes-and-serhiy-ux_report_20260828.md`
Artefact: `dist/Booster-3DP-Serhiy_Node-v24.19.0_20260830.zip`

**Verdict: Deploy OK.** Round two. All four findings from
`3D-P-007-WP3b_patch-review_20260830` (first pass) are closed, verified against
the rebuilt archive rather than the source tree.

The package is ready to carry to Serhiy. What remains is the joint session, which
is the gate that closes `3D-P-015`.

## Archive verified

SHA-256 of the delivered zip is
`C45A6EE2E86FF326950785DBF826390A8DD9F88E8B15CE0A1919E0B856CEA88A`, matching the
report exactly. 35 822 782 bytes, 19 entries.

| shipped file | CRLF | bare LF | BOM | non-ASCII |
|---|---:|---:|---|---|
| `Запустити.bat` | 4 | 0 | — | no |
| `Змінити токен.bat` | 3 | 0 | — | no |
| `Перевірити заборони.bat` | 3 | 0 | — | no |
| `start-hidden.vbs` | 7 | 0 | — | no |
| `launcher.ps1` | 163 | 0 | yes | yes |
| `negative-qa.ps1` | 62 | 0 | yes | yes |
| `Прочитай мене.txt` | 19 | 0 | yes | yes |
| `Спільна перевірка.txt` | 54 | 0 | yes | yes |

Every launcher reference resolves inside the package — five references across
four files, zero missing, matching the report's count.

## Findings closed

**1 — the broken launcher.** `Запустити.bat` is now

```bat
@echo off
wscript.exe //B "%~dp0start-hidden.vbs"
if errorlevel 1 powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0launcher.ps1" -Mode Start
if errorlevel 1 pause
```

The hidden launcher carries an ASCII filename, so the ASCII-only rule no longer
mangles the reference, and the Ukrainian entry-point name is preserved. The
second line is the WSH fallback — if Windows Script Host is blocked, `wscript`
returns non-zero and the launcher runs visibly through PowerShell instead. That
closes findings 1 and 3 in one change.

**2 — the guard now covers what it ships, and it would have caught last round's
defect.** `Assert-WindowsScriptBytes` validates `.bat`/`.vbs` as ASCII + CRLF and
`.ps1`/`.txt` as UTF-8-BOM + CRLF. The zip re-check filters
`@(".bat", ".ps1", ".vbs", ".txt")`.

More importantly, `Get-DistributionReferences` strips `%~dp0`, extracts every
`.bat`/`.cmd`/`.ps1`/`.vbs` reference, **throws when a launcher references
nothing at all**, and `Assert-DistributionReferencesOnDisk` asserts each
referenced file exists — enforced in source, staging **and** inside the finished
zip.

Tested against the exact string that shipped last round: the reference pattern
requires a leading alphanumeric, so `"%~dp0?????????.vbs"` yields **zero**
references and the build throws `does not reference a packaged launcher file`.
The guard catches the regression it was written for; that was checked, not
assumed.

**`.gitattributes`** gained `*.vbs text eol=crlf` and a scoped
`3d-print/serhiy-local-server/distribution/*.txt text eol=crlf`, so the working
tree stops drifting back to LF.

**4 — Serhiy's text files** now ship CRLF with a BOM.

**3 — the instructions** name a single entry point, `Запустити.bat`, and explain
the fallback in one plain sentence: «Якщо Windows Script Host заблокований,
«Запустити.bat» автоматично спробує видимий запуск через PowerShell.» Better than
what was asked for — Serhiy never has to know which file is which.

## Re-verified, unchanged

`npm test` → **8/8** re-run here on the current tree.
`3d-print/apps-script-3dp-api/Code.gs` is byte-identical to the WP2-era mirror —
no API change, as required. No credential anywhere in the archive.

## Carried, non-blocking

| # | Issue |
|---|---|
| A | The Node archive is still validated by **executing** `node.exe --version`, with no integrity check. nodejs.org publishes `SHASUMS256.txt`. Unchanged recommendation since WP3; the binary goes onto a third party's machine, which is where it would earn its keep. |
| B | `settings-controls.test.mjs` drives a fake form. Nothing asserts that `public/index.html` still contains controls named `setting_2` … `setting_5`. A rename there throws a clear runtime error rather than mis-resolving, so the failure mode is loud — but the suite would stay green. One assertion over the HTML closes it. |

Neither blocks the joint session. Fold them into whatever touches this package
next.

## What is proven, and the gate that remains

Proven: the contract against a fake API, the assembly path, the packaged bytes,
and — since WP3b — a real browser loading the page from the extracted archive
with live projected data and a clean console.

Not proven, and it is the whole point of what follows: installation on **Serhiy's**
machine, credential persistence there, and every live write and refusal under his
credential.

`3D-P-015` closes on four things from his machine, in the rewritten 2026-08-16
wording: `Q`/`R`/`S` writes journalled **with role `serhiy` as the author**;
payout period creation and closure refused; `Налаштування` outside `B2:B5`
refused; and no order or customer identity visible in any Інформація block.
`Спільна перевірка.txt` covers all four.

Two reminders for that session, both already recorded: create the named Sheets
version first, use a history-free test SKU and not `FIG-LUFFY-410`; and if the
refusal check ever reports `НЕБЕЗПЕКА` on payout-period creation, a real
`2099-12` row now exists in `Виплати` and must be deleted.
