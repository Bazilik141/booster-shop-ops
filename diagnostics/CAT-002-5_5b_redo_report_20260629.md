# CAT-002-5 + CAT-002-5b REDO — diagnostics

## Scope

- Source of truth: `backup-6.29.2026_18-11-10_boosters.tar.gz` (2026-06-29 18:11 cPanel backup).
- Handoff: `handoffs/handoff_CAT-002-5_5b_redo_20260629.md`.
- Owner overrides applied: Generic is already assigned; no manufacturer writes; product 101 receives description only; child category SEO URLs are in scope.

## Files touched

- `catalog/view/template/common/home.twig`
- `catalog/view/template/common/header.twig`
- `catalog/view/stylesheet/boostershop-ds.css`

The patch adds two secondary tiles, six verified category SEO URLs, the two new burger entries, four corrected color tokens, explicit 20×20 glyph SVG dimensions, and a new CSS cache version.

## DB scope

- Creates `Характеристики аксесуарів` and nine attributes only if missing.
- Upserts accessory attributes for products 95–100.
- Updates only `description` for products 95–101 and category 70.
- Does not create or update categories, products, manufacturers, manufacturer assignments, SEO rows, or product 101 attributes.

## Evidence and checks

- Fresh backup confirms category 70, product IDs/models 95–101, Generic manufacturer ID 16, all required SEO keywords, and zero `ocp5_theme` overrides for `common/home` / `common/header`.
- `php -l`: OK.
- File dry-run against the three fresh-backup files: `done=ok`; all transformed-state assertions passed.
- Embedded description payload: 7 products + category, SHA-256 integrity gate.
- Production DB dry-run is not possible locally because Codex has no server/DB access.

## Idempotency

Markers and exact DB state are checked before changes. A repeat run returns `already_applied=yes` and self-deletes after `done=ok`.

## Rollback

Before the first mutation the patch writes:

- exact copies of all three files;
- `db-prechange.json`;
- executable `rollback.sql`.

They are stored under `_patch_backups/cat002_5_5b_redo_20260629-<timestamp>/`. Any failure before commit rolls back the DB transaction and restores file backups.

## Run command

```bash
cd ~/public_html || exit
php cat002_5_5b_redo_20260629.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- Home desktop/mobile: exactly two new compact tiles; glyphs remain 20×20.
- Burger: Pokémon, One Piece, four child links, Other TCG accordion, Accessories, and Sales all open correctly.
- `/catalog/acsesuary` and `/catalog/more-tcg` return 200 and show the expected category.
- Products 95–100 show accessory attributes and descriptions; product 101 shows its description without new accessory attributes.
- Repeat run returns `already_applied=yes`.
