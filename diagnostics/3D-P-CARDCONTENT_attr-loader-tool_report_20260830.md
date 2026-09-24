# Codex Report — 3D-P-CARDCONTENT: reusable attribute loader

Date: 2026-08-30

## Scope

Implemented the handoff as the persistent repository tool
`scripts/bs-attr-load.php`, not as a self-deleting patch.

- `--report` is read-only and does not perform a lossy name-to-id lookup, so
  the known duplicate `Матеріал` definitions do not block the first audit.
- `--dry-run <csv>` resolves all supplied SKUs and names before any write path.
  A duplicate definition aborts and prints every matching `attribute_id`.
- `--apply <csv> --owner-approved` performs the same validation, creates the
  C6 backup/rollback artifacts, then writes only inside one transaction to
  `DB_PREFIXproduct_attribute`.

The extra `--owner-approved` flag is the explicit runtime gate for the
owner-approval part of C6; `--apply` without it stops before opening the DB.

## Files touched

```
scripts/bs-attr-load.php                              — reusable PHP 8.0 tool
diagnostics/3D-P-CARDCONTENT_attr-loader-tool_report_20260830.md — this report
```

## Local checks

```
php -l scripts\bs-attr-load.php
No syntax errors detected in scripts\bs-attr-load.php

php scripts\bs-attr-load.php --apply example.csv
ERROR=owner_approval_required: --apply needs the explicit --owner-approved flag after the CSV filename
```

`git diff --check -- scripts/bs-attr-load.php` returned no whitespace errors.
The source uses no PHP 8.1-only declarations (`readonly`, `enum`, `never`, or
union types); the word “never” only appears in comments.

## Database safety and rollback

`--report` issues only SELECT queries. `--dry-run` only reads the CSV and runs
SELECT queries. `--apply` is the sole write path and contains only INSERT/UPDATE
statements for `DB_PREFIXproduct_attribute`.

Before its first write, `--apply` writes:

```
_patch_backups/bs-attr-load-<UTC>-<random>/before.json
_patch_backups/bs-attr-load-<UTC>-<random>/restore.sql
```

`restore.sql` deletes rows created by the run and restores modified values with
the key `(product_id, attribute_id, language_id)`, not an unstable surrogate id
or an assumed composite-unique constraint.

## Production gate

No production database, card, definition, or source file was changed locally.
The first owner-run command remains read-only:

```bash
cd ~/public_html || exit
php bs-attr-load.php --report > attr-report.txt
```

Only after reviewing that report and explicitly approving a CSV should the
owner use `--dry-run`, then `--apply ... --owner-approved`.
