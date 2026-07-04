# Codex Report — CAT-002-5 + CAT-002-5b rollback

Date: 2026-06-29

## Scope

Full rollback of `cat002_5_5b_tiles_burger_20260628.php`.

## Files touched

```text
patches/cat002_5_5b_rollback_20260629.php
diagnostics/CAT-002-5_5b_rollback_report_20260629.md
```

Runtime restore targets:

```text
catalog/view/template/common/home.twig
catalog/view/template/common/header.twig
catalog/view/stylesheet/boostershop-ds.css
```

Conditional DB cleanup:

```text
DB_PREFIX.category
DB_PREFIX.category_description
DB_PREFIX.category_to_store
DB_PREFIX.seo_url
```

## Preflight

The rollback discovers the newest complete original backup under:

```text
_patch_backups/cat002_5_5b_tiles_burger_20260628-*/
```

It requires the exact three original files plus `db-prechange.json`, verifies
that source files contain no CAT-002 markers, and uses the snapshot to decide
whether the Accessories category was created by the original patch.

## php -l result

```text
No syntax errors detected in cat002_5_5b_rollback_20260629.php
```

## Idempotency

If files already match the original backup and the patch-created Accessories
category is absent, the runner returns `already_rolled_back=yes` and `done=ok`.
Mixed or unknown file/DB states fail before writes.

## Rollback safety

Before restoring, the runner backs up the current patched files and the current
Accessories DB rows to:

```text
_patch_backups/cat002_5_5b_rollback_20260629-<timestamp>/
```

File restores and DB deletes are coordinated with restore-on-fail behavior.

## Run command

```bash
cd ~/public_html || exit
php cat002_5_5b_rollback_20260629.php
```

## Post-run QA

- [ ] Terminal ends with `assert=rollback_final_state:ok` and `done=ok`.
- [ ] Home contains only the original Pokémon and One Piece category cards.
- [ ] Burger no longer contains Other TCG or Accessories additions.
- [ ] Original Pokémon and One Piece burger URLs are restored.
- [ ] CAT-002 secondary-tile CSS and tokens are absent/restored to pre-patch state.
- [ ] `/catalog/accessories` no longer resolves if the original patch created it.

## Side effects / risks

The rollback refuses to delete Accessories if the original snapshot says it
pre-existed, or if dependent category rows appeared after deployment.

## Server preflight correction — 2026-06-29

The first rollback run stopped before writes because `seo_url.value` and
`CAST(category_id AS CHAR)` used incompatible implicit collations. The join now
uses numeric comparison:

```sql
CAST(seo_url.value AS UNSIGNED) = category.category_id
```
