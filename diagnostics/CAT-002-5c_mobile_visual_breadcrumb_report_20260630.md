# CAT-002-5c — mobile visual and breadcrumb fix

## Scope

Source: newest cPanel backup `backup-6.29.2026_18-11-10_boosters.tar.gz`, plus the deployed CAT-002-5/5b file state reconstructed from its patch.

Fixes:

- removes the extra solid-gold mobile line below the home trust strip;
- applies category-specific header accents to Pokémon, One Piece, Other TCG, Accessories, Yu-Gi-Oh!, and MTG;
- rebuilds a product breadcrumb category path through its parent categories (`65` → `66_65`);
- makes the mobile Contacts footer column full-width so Telegram/email remain aligned with their icons;
- bumps the design-system CSS cache version.

## Files touched

- `catalog/controller/product/category.php`
- `catalog/controller/product/product.php`
- `catalog/view/template/product/category.twig`
- `catalog/view/template/common/footer.twig`
- `catalog/view/template/common/header.twig`
- `catalog/view/stylesheet/boostershop-ds.css`

No DB changes.

## Verification

- Patch `php -l`: OK.
- Prospective and post-write `php -l` for both controller files: OK.
- Dry-run against reconstructed post-redo files: `done=ok`.
- Isolated real-write smoke test: backups created, all six files written, final assertions passed, `done=ok`.
- Repeat run: `already_applied=yes`.

## Rollback

All six files are copied before writing to:

`_patch_backups/cat002_5c_mobile_visual_breadcrumb_20260630-<timestamp>/`

Any write/lint/assert failure restores these backups.

## Run command

```bash
cd ~/public_html || exit
php cat002_5c_mobile_visual_breadcrumb_20260630.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- Mobile home: one gold-to-blue gradient line only.
- Category headers: Accessories teal, Other TCG green, Yu-Gi-Oh! violet, MTG amber/brown.
- Product 101: Yu-Gi-Oh! breadcrumb opens `/catalog/more-tcg/Yu-Gi-Oh`.
- Mobile footer: Telegram bot and email stay on the same line as their icons.
- Desktop Pokémon/One Piece category accents remain unchanged.
