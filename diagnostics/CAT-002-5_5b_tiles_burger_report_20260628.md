# Codex Report — CAT-002-5 + CAT-002-5b: secondary tiles and burger categories

Date: 2026-06-28

## Scope

Implemented the two CAT-002-5 home tiles using the approved Claude Design slim
secondary-tile treatment, added the four final category tokens, fixed only the
Pokémon/One Piece parent burger URLs, preserved existing `path=` subcategory
links, added the Other TCG accordion plus Accessories direct link, and prepared
the owner-approved Accessories category DB insert.

## Files touched

```text
patches/cat002_5_5b_tiles_burger_20260628.php
diagnostics/CAT-002-5_5b_tiles_burger_report_20260628.md
```

Runtime targets:

```text
catalog/view/template/common/home.twig
catalog/view/template/common/header.twig
catalog/view/stylesheet/boostershop-ds.css
DB_PREFIX.category
DB_PREFIX.category_description
DB_PREFIX.category_to_store
DB_PREFIX.seo_url
```

## Dry-run result

Validated against the newest local cPanel backup
`backup-6.23.2026_11-21-43_boosters.tar.gz`:

```text
file_preflight=ok (3/3)
assert=files_transformed:ok
would_change_home=yes
would_change_css=yes
would_change_header=yes
assert=dry_run:ok
done=ok
```

The backup DB dump confirmed `DB_PREFIX=ocp5_`, active language ID 4,
Other TCG ID 66, Yu-Gi-Oh ID 65, MTG ID 67, and the required existing SEO rows.
The runner discovers these three IDs by unique category name and verifies both
parent relationships and SEO rows at runtime instead of depending on hardcoded
IDs.
It also confirmed that `ocp5_theme` has no `common/home` or `common/header`
override row. The live runner repeats all these checks before any write.

## php -l result

```text
No syntax errors detected in cat002_5_5b_tiles_burger_20260628.php
```

## Idempotency

Re-running validates all three file markers and the complete Accessories DB row
set, then returns `already_applied=yes`, the saved category ID, and `done=ok`.
Partial or duplicate DB/file states fail loudly.

## Rollback

Files are backed up under:

```text
_patch_backups/cat002_5_5b_tiles_burger_20260628-<timestamp>/
```

The same directory contains `db-prechange.json` and executable `rollback.sql`
with the resulting Accessories category ID.

## Run command (owner)

```bash
cd ~/public_html || exit
php cat002_5_5b_tiles_burger_20260628.php
```

## Post-deploy QA checklist

- [ ] Output contains every `assert=...:ok`, `run_url=...`, and `done=ok`.
- [ ] Home shows two slim secondary tiles below Pokémon and One Piece.
- [ ] Desktop secondary tiles are two columns; mobile tiles stack.
- [ ] Pokémon and One Piece parent burger links use SEO URLs.
- [ ] Existing Pokémon/One Piece subcategory links remain unchanged `path=` URLs.
- [ ] Other TCG accordion shows YGO and MTG with category accent dots.
- [ ] Accessories is a direct link; Promotions remains last.
- [ ] `/catalog/more-tcg`, YGO, MTG, and `/catalog/accessories` do not return 404.
- [ ] Burger open/close, accordion, Esc, and scrim behavior still works.

## Side effects / risks

The patch inserts one category and its active-language/store/SEO rows. It does
not alter products, checkout, payment, sitemap, JavaScript, or existing category
records. DB writes are transactional; file writes restore from backup on failure.

## Post-deploy correction — 2026-06-29

The first server run stopped during read-only DB preflight:

```text
error=mysqli::real_escape_string(): Argument #1 ($string) must be of type string, int given
done=failed
```

No backup, file write, transaction, or DB insert had started. PHP converted
numeric SEO array keys to integers. The corrected patch casts both SEO key and
keyword to string before escaping.
