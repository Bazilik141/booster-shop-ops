# Codex Report — ACC-001: one responsive account menu and mobile logout

Date: 2026-07-14

## Scope

The live `account.twig` renders both menu surfaces. The legacy `column_left` is visible from Bootstrap `md` (`>=768px`), while the custom menu was visible below `lg` (`<992px`); therefore both appear between 768 and 991px, as in the desktop-site mobile screenshot. Below 768px only the custom menu remains, but it did not include logout.

The patch aligns the custom menu with the legacy breakpoint (`d-md-none`) and passes the standard `account/logout` URL to the Twig view. No database, account-data, or checkout change is made.

## Files touched

```
patches/ACC-001_account-menu-single-surface-logout_20260714.php
diagnostics/ACC-001_account-menu-dedup-logout_report_20260714.md
```

The runner changes exactly:

```
catalog/controller/account/account.php
catalog/view/template/account/account.twig
```

## Dry-run result

Tested against an isolated copy of `booster-debug-CHECKOUT003-ACC001-ACC002-live.tar.gz`:

```
dry_run=ok patch=ACC-001_account-menu-single-surface-logout_20260714 files=2
done=ok patch=ACC-001_account-menu-single-surface-logout_20260714 files=2 php_l=ok
```

Each target and each anchor must occur exactly once before the backup/write step.

## php -l result

```
No syntax errors detected in ACC-001_account-menu-single-surface-logout_20260714.php
No syntax errors detected in catalog/controller/account/account.php
```

## Idempotency

Fixture replay result:

```
already_applied=yes patch=ACC-001_account-menu-single-surface-logout_20260714
```

## Rollback

The runner creates:

```
_patch_backups/ACC-001_account-menu-single-surface-logout_20260714-<timestamp>/
```

Restore both files from that one backup directory, then clear the template/cache entries.

## Run command (owner)

```bash
cd ~/public_html || exit
php ACC-001_account-menu-single-surface-logout_20260714.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] At 390px width, exactly one custom menu is shown and it includes `Вихід`; click it and verify the login page/session logout.
- [ ] At 768–991px, only the legacy left account menu is shown; the custom card is absent.
- [ ] At desktop width, only the legacy left menu is shown; no duplicated links remain.
- [ ] Verify the existing links: contact data, password, addresses, wishlist, order history, and logout.

## Side effects / risks

- No DB, authentication logic, account address, checkout, payment, or order change.
- The route uses the same language/customer-token URL contract as the other account links in the current controller.
