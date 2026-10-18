# CAT-005 audit inputs (read-only reference)

Source: owner cPanel backup `backup-10.15.2026_13-09-55_boosters` (DB dump completed 2026-10-15 13:09:59 UTC; files as of the same backup). The full backup is not in Git by design.

- `catalog-extract_backup-10.15.2026.sql.txt` — a mysqldump filtered to catalog and content tables only (information*, seo_url, product*, attribute*, category*, manufacturer*, stock_status, language, layout*, tax_class, weight/length class). It is plain SQL despite the `.txt` suffix (`*.sql` is git-ignored). It can be loaded into a scratch MariaDB for patch testing. It deliberately contains NO customer, order, user, setting, API-key or payment tables.
- `live-files/` — live copies of the product/category/information templates, controllers and models from `public_html/` in the same backup (paths mirror `public_html/`).

Not included, so unverifiable from here: `ocp5_setting` (theme and module settings), customer/order data, Merchant feed module files, anything else on the server. Mark such findings "unverified".
Do not edit anything in this folder.
