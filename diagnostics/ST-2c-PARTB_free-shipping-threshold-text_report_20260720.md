# Codex Report — ST-2c Part B: поріг безкоштовної доставки 1500 → 2000

Date: 2026-07-20

## Scope

Prepared one owner-run PHP database patch for the approved content-only scope:

- `DB_PREFIX product_description` — only rows containing the exact substring
  `1500 грн` in `name`, `description`, `meta_title`, `meta_description`, or
  `meta_keyword`;
- `DB_PREFIX information_description` — the same exact-substring replacement,
  but only for `information_id=4` ("Оплата і доставка"). This table correctly
  uses `title` (not `name`) alongside its other text fields.

No checkout, payment, Hutko, Checkbox, URL routing, JSON-LD/schema, sitemap,
or database schema change is included. `DB_PREFIX order_history` is read only
to prove its count of comments containing `1500` is unchanged.

## Evidence and live guards

The handoff was based on the full cPanel snapshot
`backup-7.19.2026_09-58-50_boosters.tar.gz`, where `DB_PREFIX=ocp5_` and the
expected matching text was `1500 грн`. Because live data can change after that
snapshot, the runner reads the actual prefix, validates the two table schemas,
counts every live match before writing, and aborts if the required anchor is
absent. It never relies on the snapshot's 49/2 counts as a brittle assertion.

## Patch behaviour

- `--dry-run` prints the live pre-counts without writing.
- Normal mode creates
  `_patch_backups/ST-2c-PARTB_free-shipping-threshold-text_20260720-<timestamp>/`
  with `db-before.json` and exact-row `rollback.sql` before a transaction.
- The transaction replaces only `1500 грн` with `2000 грн`, verifies zero old
  phrases remain in the two target scopes, verifies both target table row
  counts and the `order_history` count are unchanged, then commits.
- A re-run returns `already_applied=yes` when both target scopes have no old
  phrase. Successful runs self-delete.

## Dry-run result

Local static validation only: the patch uses the live `config.php` and live DB
at owner execution time; Codex has no server/DB access. Local `php -l` passed.
Owner preflight exposed two server-specific compatibility points before any
write: `information_description.title` is the correct field name, and this
server's mysqli build has no `mysqli_result::fetch_all()`. The runner now uses
the compatible `fetch_assoc()` loop and must be re-uploaded over the failed
copy before retrying.

## PHP syntax

`php -l patches/ST-2c-PARTB_free-shipping-threshold-text_20260720.php` — pass.

## Rollback

Use the generated `rollback.sql` in the patch's backup directory, then clear
the OpenCart cache. The SQL restores only the five text fields of rows captured
before this patch; it does not touch order history or other tables.

## Run command

```bash
cd ~/public_html || exit
php ST-2c-PARTB_free-shipping-threshold-text_20260720.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- Confirm terminal output has `post_hits_product_description=0`,
  `post_hits_information_description_id_4=0`, `post_row_counts=unchanged`,
  and `done=ok`.
- Open three random product pages and the "Оплата і доставка" page; each
  changed sentence must show `2000 грн` and retain its HTML formatting.
- If the output is anything other than `done=ok`, send the complete terminal
  output before re-running.
