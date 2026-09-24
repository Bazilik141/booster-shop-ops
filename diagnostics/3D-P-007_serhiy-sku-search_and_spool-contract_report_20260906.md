# Codex Report — 3D-P-007: Serhiy updater, SKU search, stock refresh, and spool contract

Date: 2026-09-06

## Scope

Added SKU search above each of the four active-SKU dropdowns while preserving the dropdowns. Added a self-contained Windows EXE updater that keeps the existing URL/token environment settings, replaces only dashboard application files, creates a rollback backup, and remembers the selected install folder. Added bounded post-manufacture refreshes so the formula-backed stock tile has time to recalculate before the UI declares the value stale.

## Files touched

```
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/public/styles.css
3d-print/serhiy-local-server/public/operation-state.js
3d-print/serhiy-local-server/tests/operation-state.test.mjs
3d-print/serhiy-local-server/distribution/Прочитай мене.txt
scripts/build-serhiy-3dp-updater.ps1
scripts/serhiy-updater/Program.cs
3d-print/serhiy-local-server/dist/Booster-3DP-Оновлення_202609062.exe
3d-print/serhiy-local-server/dist/Booster-3DP-Оновлення_202609062.exe.sha256.txt
3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260906.zip
3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260906.zip.sha256.txt
3d-print/serhiy-local-server/dist/ПЕРЕДАТИ СЕРГІЮ 2026-09-06/
3d-print/serhiy-local-server/dist/ПЕРЕДАТИ-СЕРГІЮ_Оновлення_2026-09-06.zip
```

## Root causes and boundaries

- The local client refreshed `/api/bootstrap` immediately after a confirmed `3dp_manufacture_batch` write. The stock card is sourced from the formula-backed `Наявність!Наявно зараз, шт`, so the first read can still return the pre-write calculation. The client now performs at most four reads with bounded waits (immediate, then 400 ms, 900 ms, and 1600 ms) and stops as soon as the selected SKU stock changes. It never resends the manufacture write.
- `SPOOL_WEIGHT_REQUIRED` is emitted by the deployed FIFO action when the request body does not contain a positive `spool_weight_g`. The current local server validates and forwards `spool_weight_g`, `spool_price_uah`, and `serhiy_consumables_uah`; the contract test passes. The supplied screenshot cannot prove which package was running on Serhiy's PC.
- The launcher already persists `BOOSTER_3DP_URL` and `BOOSTER_3DP_SERHIY_TOKEN` in Windows User environment variables. The updater does not read, print, replace, or request them.
- No Apps Script, spreadsheet cell, token, live API, or deployed Web App was changed in this work.

## Updater safety

- Validates the target contains `Запустити.bat`, `app/server.mjs`, and `runtime/node.exe`.
- Refuses to update while local port 3107 is still occupied.
- Verifies embedded payload paths and SHA-256 hashes before installation.
- Backs up every replaced file under `_update_backups/update-<timestamp>`.
- Restores the prior files and removes newly created payload files if installation or validation fails.
- Leaves the bundled Node runtime and saved Windows URL/token untouched.
- Remembers the approved install path under `%LocalAppData%\BoosterShop3DP\install-path.txt` after a normal successful run.

## Verification evidence

- `npm test`: 18/18 passed, including the spool forward contract and two bounded-refresh tests.
- `node --check public/app.js` and `node --check public/operation-state.js`: passed.
- PowerShell parser check for `scripts/build-serhiy-3dp-updater.ps1`: passed.
- End-to-end updater QA against a clean extraction of the prior package: exit code 0; previous `app.js` backup hash matched; `runtime/node.exe` hash stayed unchanged; installed payload hashes matched; updated server launched on an isolated localhost port.
- The rebuilt full archive passed Node v24.19.0, encoding, launcher-reference, and archive validations.
- Updater SHA-256: `2e8ec240f049a19f3b89b702b78edaf94480aac3adc3f971d01813ed1dc67503`.
- Full ZIP SHA-256: `f14f37400af365fae5580ff95babaf74923b9fddc187f11dce6012ad45088f54`.
- Serhiy handoff ZIP SHA-256: `a1c5169a9e66bfa3e33f02a16fb02aeffc0e3f0d48481cb448c8f1ea5a475778`.

## Owner QA gate

- [ ] Send Serhiy only the contents of `dist/ПЕРЕДАТИ СЕРГІЮ 2026-09-06/`.
- [ ] Serhiy closes the dashboard/server, waits up to five minutes, runs the EXE, and selects the folder containing `Запустити.bat` on the first run.
- [ ] Confirm all four SKU fields have search plus the retained dropdown.
- [ ] Create exactly one designated test batch with positive spool weight and price.
- [ ] Confirm the API reports one successful write and the stock tile changes by the accepted quantity (`printed_quantity - defects`).
- [ ] If a warning remains after the bounded refresh, do not repeat the batch; capture the full message and verify the availability formula/live deployed contract separately.

## Rollback

The updater automatically creates `_update_backups/update-<timestamp>` inside Serhiy's current installation. A successfully created real FIFO batch is not rolled back by local-file rollback and must not be repeated to test the screen refresh.
