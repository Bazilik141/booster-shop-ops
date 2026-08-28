# Build instructions

## Prerequisites

Windows x64 build machine with:

- .NET 8 SDK;
- Python 3;
- PowerShell 5.1+ or PowerShell 7+.

The installer is a `.NET 8` WinForms self-contained single-file executable for `win-x64`.

## Required payload tree

`build.ps1` refuses to build until all of these exist:

```text
payload/
├── .doorstop_version
├── doorstop_config.ini
├── winhttp.dll
├── version.dll
└── BepInEx/
    ├── core/
    │   ├── BepInEx.dll
    │   └── BepInEx.Preloader.dll
    ├── config/
    │   └── shaklin.Translator.cfg
    └── plugins/
        └── Translator/
            ├── Translator.dll
            └── localization_data.txt
```

The private build workspace now contains the four exact game-root loader files supplied from the inspected working setup:

```text
.doorstop_version
doorstop_config.ini
winhttp.dll
version.dll
```

Static audit confirms Doorstop `4.5.0`, an enabled configuration targeting `BepInEx\core\BepInEx.Preloader.dll`, and x86-64 PE proxy DLLs. See `ROOT_LOADER_AUDIT.md` for hashes. Do not substitute unrelated proxy DLLs.

For a **private/local build** on the same PC that already has the verified working setup, the helper can copy the runtime payload without replacing this project's Ukrainian dictionary:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\prepare_payload_from_game.ps1 `
  -GameRoot "E:\SteamLibrary\steamapps\common\TCG Card Shop Simulator"
```

This convenience does not grant redistribution rights for the copied third-party files.

## Translator redistribution blocker

Do not make a public build containing `Translator.dll` until Shaklin grants permission. The Nexus permissions for Translator 1.0.3 state that asset use in another mod/file requires permission, and upload to other sites is prohibited. The author separately documents that translators may edit and share `localization_data.txt` on Nexus.

## Validate dictionary only

```powershell
python .\tools\validate_dictionary.py `
  .\payload\BepInEx\plugins\Translator\localization_data.txt `
  --expect-min-records 2190 `
  --allowed-equal-file .\localization\reviewed_unchanged.txt `
  --forbid-cjk
```

Expected result for 0.1.0:

```text
physical_lines=2190
valid_records=2190
malformed_nonempty=0
empty_sources=0
duplicate_source_keys=0
source_equals_target=7
unexpected_source_equals_target=0
placeholder_mismatches=0
cjk_rows=0
```

## Rebuild the dictionary from the inspected baseline

The repository does not need to redistribute the original broken baseline. Given that exact baseline:

```powershell
python .\tools\rebuild_dictionary.py `
  C:\path\to\original\localization_data.txt `
  .\payload\BepInEx\plugins\Translator\localization_data.txt `
  --manual .\localization\manual_translations.tsv `
  --residue .\localization\english_residue_translations.tsv `
  --repairs .\localization\repair_records.txt `
  --report .\localization\rebuild_report.json
```

For the inspected baseline, the expected dictionary SHA-256 is:

```text
ad980b76dca03c38d8578312a8a9aea329fe3b742aff8c3318427a2aa9c99c3e
```

## Build the installer

For a **private/local build**, the payload is now complete. To make a **public** build, resolve the Translator redistribution permission first:

```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1
```

or:

```powershell
pwsh -File .\build.ps1
```

Output:

```text
release\TCGCardShopSimulatorUA-Installer.exe
release\SHA256SUMS.txt
```

The current source does **not** configure code signing. Therefore a locally produced file is unsigned unless you explicitly add a real signing step and certificate. Never label an unsigned build as signed.

## GitHub Actions build (optional)

A manual Windows workflow is included at `.github/workflows/build-windows.yml`. With the private payload now complete, place the project in a **private repository**, open **Actions → Build Windows installer → Run workflow**, and download the generated artifact.

Do not use a public repository to smuggle third-party binaries around the redistribution restrictions documented in `LICENSES/`.
