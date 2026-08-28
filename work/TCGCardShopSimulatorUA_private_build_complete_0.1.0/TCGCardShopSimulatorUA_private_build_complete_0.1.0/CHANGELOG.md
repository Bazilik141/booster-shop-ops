# Changelog

## Private payload completion — 2026-08-25

- added the exact user-supplied Doorstop 4.5.0 root files: `.doorstop_version`, `doorstop_config.ini`, `winhttp.dll`, and `version.dll`;
- verified x64 PE architecture and the configured BepInEx preloader target;
- recorded SHA-256 hashes in `ROOT_LOADER_AUDIT.md`;
- private/local installer payload is now structurally complete; Windows build/runtime tests remain pending;
- public redistribution remains blocked by `Translator.dll` permissions unless the author grants permission.
- hardened `build.ps1`: payload ZIP is created with .NET `ZipFile.CreateFromDirectory` and checked for all mandatory entries before publish, including `.doorstop_version`.

## 0.1.0 — 2026-08-25

Baseline:

- TCG Card Shop Simulator Windows x64 / Mono;
- Unity `2021.3.38.8007589`;
- BepInEx `5.4.23.5`;
- Translator `1.0.3` (`shaklin.Translator`);
- exact Steam build ID not available in the supplied BepInEx package.

Localization:

- repaired all 6 split/malformed logical records;
- converted the inspected 2196 physical lines to 2190 valid one-line mappings;
- reviewed all 525 originally `source == target` records;
- reused 240 already-translated legacy `NotUsed/` / `Roadmap/` equivalents where applicable;
- manually reviewed the remaining equal rows;
- translated 77 additional mappings whose targets still effectively contained English/corrupted text;
- repaired 8 pre-existing placeholder-parity issues;
- retained only 7 reviewed identical technical/numeric tokens: `HP`, `TCG`, `1st`, `2nd`, `3rd`, `OMW`, `OOMW`;
- final dictionary has zero malformed rows, zero duplicate source keys in this revision, zero placeholder mismatches and zero CJK rows;
- targeted legacy cleanup: `Playable TCG`, `Arcade Claw`, `Rocket Missile`.

Installer source:

- Ukrainian WinForms UI;
- Steam library auto-detection plus folder picker;
- game-root validation;
- running-game guard;
- pre-write change plan;
- coexistence logic for unrelated BepInEx files;
- timestamped backups for replaced managed files;
- ownership manifest with SHA-256 and safe uninstall behavior;
- transactional pre-mutation snapshots with rollback for failed install/update and uninstall operations;
- shared-runtime files are skipped only when their hashes match the payload; same-version-but-different runtime files are surfaced and backed up before replacement.

Not completed in this environment:

- Windows `.exe` build;
- clean-install/update/uninstall execution against a copied real game folder;
- final in-game smoke test/screenshots;
- Cyrillic font/glyph runtime check;
- public redistribution permission for `Translator.dll`.
