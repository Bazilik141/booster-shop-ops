# Handoff — TECH-049: item condition for non-sealed cards (Bulk / Bulk Mix) in product JSON-LD and the Merchant feed

Date: 2026-10-19
Executor: Claude Code · model=Opus · effort/thinking=high — owner decision 2026-10-19. Why: risky zone (schema + Merchant feed, production-direct, no staging); the same executor already holds the CAT-005 context.

Delivery: two patch files into `patches/`, one report into `diagnostics/`. The owner uploads to `~/public_html` and runs `php <patch>.php`. The executor never deploys. No staging — patches run on production.

Risk classification (`bs-seo-risk-gate`): **High** — schema/JSON-LD and Merchant feed. Rules from `bs-merchant-schema-qa` apply: no invented GTIN, rating or review; `price`/`availability`/`id` never change; `shippingDetails` and the return policy must stay equal to the legal policy.

## 0. Cloud execution (same override as CAT-005)

- Cloud session with the Git repo only. Inputs: `live-snapshots/20261019_tech049-inputs/` (the live Merchant feed generator, read its README) and `live-snapshots/20261018_cat005-audit-inputs/` (live `product.twig`, catalog SQL extract of backup 10.15.2026).
- Base your branch on the branch that contains this handoff. Work on a new branch. You may commit and push **your own branch only**; never push to `master` or the base branch, never force-push, never merge. The owner merges after review.
- You may load the SQL extract into a scratch MariaDB/MySQL for tests; never touch a real database.
- Anything outside those inputs (theme DB overrides, `ocp5_setting`, the live `merchant-feed.tsv`, the cron that runs the generator) is **unverified** — say so, do not guess.

## 1. Task ID

TECH-049 — Notion `3fe6bf20-bdb4-81ce-bf76-d969f014ee43`, In progress. Blocks enabling the CAT-005 cards. Closure only on the owner's confirmation (see section 10).

## 2. Context

- The CAT-005 cards `PKM-JP-BULK-155` (product 225), `OP-JP-BULK-155` (226), `PKM-MIX-BULK-100` (227) are live in the DB with `status=0`. They are original cards taken from opened boosters; the shop does not state a grade (attribute «Стан» = «Оригінальні картки з розкритих бустерів; ступінь стану не заявляється»). The offer (2.20, 6.19, 6.23) says they are not a factory-sealed product.
- Two places assert "new" for **every** product:
  1. `catalog/view/template/product/product.twig` product JSON-LD (live copy in `live-snapshots/20261018_cat005-audit-inputs/live-files/catalog/view/template/product/product.twig`): offer-level `"itemCondition": "https://schema.org/NewCondition"` at line 1040, and a second `itemCondition` inside `hasMerchantReturnPolicy` at line 1047.
  2. `~/merchant-feed-build.php` (live generator, backup 10.15.2026, SHA-256 `ef75327cba3c906d0d0779841bdd9620c4f1649894ee9c2fc4de6b1801ee177d`): the CSV row writes the literal `'new'` for the `condition` column. The feed selects `p.status = 1 AND p.price > 0 AND p.image <> '' AND (quantity > 0 OR preorder status)`, so status-0 cards are **not** in the feed today.
- The feed `id` is the SKU (`model`), the link is `index.php?route=product/product&product_id=…`. `product_type_for()` has no Bulk branch; a Bulk card would fall through to `<family> > Booster Pack`, which is wrong for a non-sealed set.
- **Owner decisions (2026-10-19):** non-sealed Bulk / Bulk Mix cards carry condition **used** (schema `UsedCondition`, feed `used`); sealed products stay `new`/`NewCondition` and must not change. Executor: Claude Code Opus/high.
- Rule that identifies a non-sealed card: the SKU (`model`) contains the token `-BULK-` (case-insensitive). It covers the three launch SKUs and the two deferred ones (`PKM-JP-BULK-100`, `OP-JP-BULK-100`). It must be the **same rule** in the twig and in the feed.
- Related: `TECH-048` (Merchant feed for CONTENT-010 products) will edit the same generator file later; keep this change minimal and isolated so that task can rebase on it.

## 3. Goal

A product whose `model` contains `-BULK-` is described as used in the product JSON-LD and in the Merchant feed (and gets a correct feed `product_type`); every other product's JSON-LD and feed row stays byte-for-byte as today.

## 4. What to change

Two work packages, two patch files, independent rollback. Deploy order: WP1 and WP2 in any order; both must be live before the cards are enabled.

**WP1 — `patches/TECH-049_product-schema-condition_20261019.php`** (target `public_html/catalog/view/template/product/product.twig`)
- Replace only the **offer-level** `itemCondition` value with a Twig conditional: `UsedCondition` when the `model` contains `-BULK-` (case-insensitive), otherwise `NewCondition`. Suggested shape: `"itemCondition": "https://schema.org/{% if '-BULK-' in (model|default('')|upper) %}UsedCondition{% else %}NewCondition{% endif %}",` — verify that `model` is the variable the controller passes (`{% if model %}` at line 1029 suggests it is) and that the expression renders correctly in the project's Twig version.
- Anchor uniqueness: the two `itemCondition` lines differ only in indentation and neighbours; anchor on the offer-level line **plus** the following `"hasMerchantReturnPolicy"` line so the match count is exactly 1.
- Leave the `hasMerchantReturnPolicy.itemCondition` line unchanged (policy-level, not product-level; out of scope).
- Follow the existing Twig-patch precedent in `patches/` for the compiled-template cache refresh and for the syntax gate that replaces `php -l` for a `.twig` file; state which precedent you used.

**WP2 — `patches/TECH-049_merchant-feed-condition_20261019.php`** (target `~/merchant-feed-build.php`, outside `public_html`; use `GMC-OPS_merchant-feed-backup-retention_20260921.php` as the structural precedent for a home-root target)
- Add one helper (e.g. `feed_condition_for(array $row): string`) returning `used` when `model` contains `-BULK-` (case-insensitive), else `new`; replace the literal `'new',` in the row array with a call to it.
- In `product_type_for()` add a first branch for the same token returning `<family> > Bulk Set` (so a Bulk set is never typed as a Booster Pack). If you judge this out of scope, say so in the report instead of silently dropping it.
- Do not change the `SELECT`, the headers, `id`, price, availability, link, image or any other column.
- The patch must not run the generator and must not touch `merchant-feed.tsv`; the owner re-runs the generator afterwards (verify from the repo how it is run — `GMC-OPS` calls it a manual tool — and write the exact command into the report).

Both patches follow the seven conventions in `AGENTS.md`: file-exists check, anchor count check, backup to `_patch_backups/<patch>-<ts>/`, syntax gate with restore-on-fail, `already_applied=yes`, no DB changes, self-delete. PHP 8.0 compatible. CLI only.

## 5. Do not touch

`sitemap*.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, Hutko, Checkbox/fiscalization, Nova Poshta, order status; the `hasMerchantReturnPolicy` block (incl. its `itemCondition`), `shippingDetails`, `offers.price`, `offers.availability`, `aggregateRating` and the Organization JSON-LD; every other twig file; the feed `SELECT`, headers, `id`, price, availability, link, image, brand, `identifier_exists`; `merchant-feed.tsv` itself; any database row or table (this task writes no DB); the CAT-005 patches, cards and offer. Never add GTIN, rating, review or any new structured-data field.

## 6. Likely files / areas (verify against the actual files)

- `public_html/catalog/view/template/product/product.twig` (JSON-LD block ≈ lines 1007–1085).
- `~/merchant-feed-build.php` (row array `fputcsv` at line 389, the literal `'new',` at line 398; `product_type_for()` at line 139).
- Theme DB override: the extract has no `ocp5_theme` table → **unverified**. If the twig change is not visible after cache refresh, the owner checks the theme override before any second patch (preserve rollback evidence before deleting anything).

## 7. Acceptance criteria

1. Twig: rendering the JSON-LD with `model` = `PKM-JP-BULK-155`, `OP-JP-BULK-155`, `PKM-MIX-BULK-100`, `pkm-jp-bulk-100` → offer `itemCondition` = `https://schema.org/UsedCondition`; with `model` = `OP-JP-OP01-RPK`, `PKM-JP-EXSD-STD-GRS`, `ACC-3D-ONIX-110-21-BLK`, empty string → `https://schema.org/NewCondition`. Show the render evidence (Twig library in the sandbox, or state exactly what you could not run).
2. Twig: the rendered JSON-LD for a sealed `model` is **byte-identical** to the rendering of the original file (diff = empty); output is valid JSON.
3. Feed: against a scratch DB loaded from the SQL extract (stub `config.php` in a scratch tree) the new generator's output is **identical** to the old generator's output for the current data (no Bulk row eligible: all are `status=0`), including row count, `id`s and every non-`condition` column.
4. Feed: after inserting the three Bulk cards as eligible test rows (`status=1`, `quantity>0`, image set) in the scratch DB, those rows have `condition=used` and `product_type=<family> > Bulk Set`; every other row is unchanged; row count +3; no duplicate `id`.
5. Both patches: first run → expected result and backup; second run → `already_applied=yes`; anchor-count failure test (mutate the target) → exits non-zero, writes nothing; `php -l` clean; PHP 8.0 compat check; rollback restores the original bytes.
6. No file outside the two targets is written. Report lists SHA-256 of each target before/after.

## 8. QA / smoke test (owner)

Stage A — after deploying both patches (cards still disabled):
1. Tier 1 smoke URLs from `AGENTS.md` load (home, category, nested category, sealed product, info page, cart, checkout entry).
2. Sealed product page (`/product/One-Piece-Boosters-OP-11`): view-source → product JSON-LD still has `"itemCondition": "https://schema.org/NewCondition"`; Google Rich Results Test on that URL: Product valid, no new warnings.
3. Run the feed generator (command from the report), then open `merchant-feed.tsv`: same row count as before, `condition` is `new` on every row.
Stage B — after the CAT-005 cards are enabled (this is the TECH-049 closing check):
4. Each live Bulk card: view-source JSON-LD → `UsedCondition`; Rich Results Test valid.
5. Re-run the generator: the three cards are in the feed with `condition=used`; other rows unchanged.
6. Merchant Center → Diagnostics after the next fetch (24–48 h): no new errors or warnings on these items.

## 9. Rollback note

WP1: restore `product.twig` from `_patch_backups/TECH-049_product-schema-condition_20261019-<ts>/` and refresh the compiled-template cache. WP2: restore `~/merchant-feed-build.php` from `_patch_backups/TECH-049_merchant-feed-condition_20261019-<ts>/`, re-run the generator (or restore the latest `merchant-feed.tsv.bak-<stamp>` the generator saved). No DB rollback exists because no DB is written.

## 10. Recommended status after execution

Patches written and tested → report to the owner → Claude (chat) review. TECH-049 stays In progress until deploy and Stage A pass; closes (`Done`) only after Stage B on live cards and the owner's confirmation. The executor does not write Notion or the dashboard; a needed status change is stated in the report.
