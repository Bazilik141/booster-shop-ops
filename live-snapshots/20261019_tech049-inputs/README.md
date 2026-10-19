# TECH-049 audit inputs (2026-10-19)

Purpose: give the cloud executor the live Merchant feed generator, which is not in Git.

| File | Source | SHA-256 |
|---|---|---|
| `merchant-feed-build.php` | `homedir/merchant-feed-build.php` in `backup-10.15.2026_13-09-55_boosters.tar.gz` (mtime 2026-09-22 05:43, written by `GMC-OPS_merchant-feed-backup-retention_20260921`) | `ef75327cba3c906d0d0779841bdd9620c4f1649894ee9c2fc4de6b1801ee177d` |

- Unmodified copy; `php -l` clean. It holds no credentials: DB constants come from `public_html/config.php` at run time.
- The generator runs from the hosting home (`__DIR__ . '/public_html'` is its web root) and needs `public_html/config.php` and an existing writable `public_html/merchant-feed.tsv`.
- Not in the inputs (unverified): the live `merchant-feed.tsv`, how or when the generator is scheduled, and the theme DB override for `product.twig`. The live `product.twig` is in `../20261018_cat005-audit-inputs/live-files/catalog/view/template/product/product.twig`.
