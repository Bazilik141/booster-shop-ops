# PAY-002 — PUMB credit skeleton report

Date: 2026-07-28

## Scope

Prepared one owner-uploadable OpenCart PHP runner for the PAY-002 handoff. It creates a disabled-by-default `extension/pumb_credit` skeleton, PUMB transaction storage with `is_test`, OC4 extension registry entries, callback Basic-auth/IP-allowlist settings, and the owner-approved 6-to-5 shared installment-status consolidation.

The runner also changes the mono controller only at the `SUCCESS/DONE` status key so it shares the `SUCCESS/ACTIVE` status ID after the approved merge. It does not alter mono request, callback, or poll transport logic; it does not expose PUMB in Simple Checkout; it does not touch NCRM, checkout Twig/UI, fiscalization, or real orders.

## Fresh source evidence

Newest available cPanel backup: `backup-7.24.2026_17-02-32_boosters.tar.gz`.

- `config.php`: DB prefix is `ocp5_`; cache resolves from `DIR_CACHE`.
- Live OC4 DB backup contains the required `ocp5_extension`, `ocp5_extension_install`, and `ocp5_extension_path` tables.
- Current six mono status rows are IDs 17–22 in Ukrainian: waiting client, waiting store, active, done, returned, failed.
- The current `mono_chast` catalog model already returns an empty method list to isolate it from legacy Simple Checkout. PUMB mirrors that isolation intentionally.
- Live terminal evidence from 2026-07-28 confirms the mono `applyOrderStatus()` mapping still has the expected `SUCCESS/DONE → done` branch. The first runner stopped before mutations because its own PHP string failed to escape `$state`; this has been corrected in the upload file.

## Patch behavior and rollback

- Requires `config.php`, the live mono controller, all OC4 registry/order/settings tables, and exactly one of each six current mono labels before any write.
- Creates `_patch_backups/PAY-002_pumb-credit-skeleton_20260728-<timestamp>/` before writes. It contains the pre-change mono controller, DB evidence, and generated `rollback.sql`.
- Moves any existing status-20 order and order-history references to status 19 before removing status 20; the generated rollback restores the exact captured references and labels.
- Runs `php -l` on every new/changed PHP source file, restores source files on lint/error, is idempotent through `.pay002-marker`, logs its work, and self-deletes after `done=ok`.

## Local validation

- `php -l patches/PAY-002_pumb-credit-skeleton_20260728.php` passed locally.
- The PUMB code caches OAuth tokens in `DIR_CACHE` per test/prod contour, using the returned `expires_in` minus 15 seconds. The response-to-cache logic is exposed as a side-effect-free helper and passed a credential-free mocked OAuth response locally (`expires_in=300` → `expires_at=now+285`, including fresh/stale cache checks). Callback HTTP tests still require the owner-host OpenCart runtime; no bank credentials are included in this repository.
- All five embedded PHP source blocks parsed locally; `install.json` parsed as JSON.

## Owner deploy and QA

Upload the patch to `~/public_html`, then run:

```bash
cd ~/public_html || exit
php PAY-002_pumb-credit-skeleton_20260728.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

### Deployment evidence

Owner executed the corrected runner on the production host at `2026-07-28T08:28:50+00:00` from `/home2/boosters/public_html`.

- Result: `php_l=ok`, `done=ok`, then `cache cleared`.
- Server backup: `/home2/boosters/public_html/_patch_backups/PAY-002_pumb-credit-skeleton_20260728-20260728-082850`.
- The runner created the seven PUMB extension files and marker, then changed only the approved mono status-mapping source file.
- The host emitted `MYSQL_OPT_RECONNECT is deprecated`; this is a PHP/MySQL environment warning, not a PAY-002 failure.
- This proves runner execution only. It does not prove the Admin UI, callback authentication, bank OAuth, payment flow, or production enablement.

### Required pre-QA hotfix

Before saving the PUMB admin form, run `patches/PAY-002_admin-status-settings-preserve_20260728.php`. It adds five hidden form fields so OC4 `editSetting()` preserves the shared status IDs after an admin save. It has no DB changes and backs up only the PUMB Twig file.

Owner applied the hotfix on the production host at `2026-07-28T08:36:20+00:00`; output reported `done=ok`, cache cleared, and backup `/home2/boosters/public_html/_patch_backups/PAY-002_admin-status-settings-preserve_20260728-20260728-083620`.

### Admin UI evidence

The PUMB settings page rendered successfully after cache clear. Observed safe defaults: payment method toggle off, test contour on, OAuth/callback credentials empty, minimum amount 500, maximum amount empty, term 3. This is visual/UI proof only; the form must still be saved once and the status list/callback routes checked before the admin-save and callback acceptance criteria are complete.

Owner then set `payment_pumb_credit_max_total` to `500000` and saved the form successfully, while leaving the payment method disabled. This completes the safe config-only action from the bank feedback; no credentials, callback IPs, or enablement were entered.

After `done=ok`:

1. Confirm Admin → Extensions → Payments opens PUMB settings and saves without an `$error` crash.
2. Keep `payment_pumb_credit_status=0`; confirm PUMB does not appear in checkout.
3. Confirm Admin → Orders lists only the five shared `Розстрочка — ...` statuses and no `ПЧ mono — ...` labels.
4. Test both callback routes with their separate Basic credentials. Wrong/missing credentials must return 401 and create no row; a valid test callback must create/update only `is_test=1` rows.
5. Run the unified `PAY-001-SMOKE` mono regression before any enablement.

## Open risks / not proven locally

- No deployment, OpenCart runtime request, database mutation, OAuth request, callback HTTP request, or bank integration was run locally.
- Do not enter credentials, enable the method, or send callback URLs to the bank until the owner has completed the deploy QA and the bank has provided source IPs and the confirmed maximum amount.
- PAY-002 remains In progress; PAY-001-SMOKE remains blocked by PAY-003 and NCRM-14 as specified by the handoff.
