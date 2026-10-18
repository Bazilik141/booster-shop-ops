# Handoff — CAT-005: Bulk / Bulk Mix cards (3 SKU) + public offer 18.10.2026

Date: 2026-10-18
Executor: Claude Code · model=Opus · effort/thinking=high — proposal, owner decides. Why: an audit-first task with two DB-write patches (a legal page and new catalog rows plus two new attributes), where the risk is in what the audit finds, not in typing the patch.

Delivery: patch files into `patches/`, an audit report into `diagnostics/`. The owner uploads to `~/public_html` and runs `php <patch>.php`. The executor never deploys. No staging — patches run on production.

## 0. Cloud execution (owner-approved override for this task)

- This task runs in a cloud session with the Git repo only. The full backup is not in Git. Audit inputs are in **`live-snapshots/20261018_cat005-audit-inputs/`** (read its README): a catalog-only SQL extract of backup 10.15.2026 and the live template/controller/model files. Everything you cannot see there (theme settings, `ocp5_setting`, Merchant feed module files, customer/order data) is **unverified** — say so in the audit instead of guessing.
- Base your work on the branch that contains this handoff (`origin/codex/3dp-payout-approval-status` unless it was merged into `master` — check which one has `handoffs/handoff_CAT-005_bulk-cards-and-offer_claude-code_20261018.md`). Work on your own new branch.
- Owner override for this task only: you may commit the deliverables (audit report, patch files, test reports) and **push your own branch** to origin so the owner and Claude (chat) can review them. Never push to `master` or to the branch you started from, never force-push, never merge. The owner merges after review.
- You may load `catalog-extract_backup-10.15.2026.sql.txt` into a scratch MariaDB/MySQL in your sandbox for the patch tests; never touch any real database.

## 1. Task ID

CAT-005 — Notion roadmap task, In progress. It closes only on the owner's feedback that the cards are on the site, the offer is published and all is OK; Claude (chat) then updates Notion and the dashboard mirror together.

## 2. Context

- New product line **Bulk / Bulk Mix**: original cards the shop assembles itself from cards taken out of boosters it opened. Not a sealed manufacturer product. Terminology everywhere: "Bulk / Bulk Mix" (never "Bulk Box", "Mix Bulk").
- 5 SKUs exist in the CRM; **3 launch on the site**: `PKM-JP-BULK-155`, `OP-JP-BULK-155`, `PKM-MIX-BULK-100`. `PKM-JP-BULK-100` and `OP-JP-BULK-100` are deferred — create nothing for them (no products, no SEO URLs).
- Final card copy (QA-reviewed, polished; use verbatim): **`handoffs/CAT-005_bulk-card-copy_FINAL_20261018.md`** — per SKU: h1, seo_keyword, meta_title, meta_description, tags, category, manufacturer, description HTML (body + FAQ), attributes. Do not rewrite copy; if something looks wrong, report it.
- Final offer body: **`handoffs/offer_html_20261018.html`** (SHA-256 `6ec16036b7bfeedc57f20882975ae5928ac8fa9d32c1a7e6393c82c252146dea`, 100 972 bytes). Built from `handoffs/offer_html_20261006.html` (SHA-256 `9e22347c80bd773292832614818ecba68d66b053af37a0e369f54d43e07efb71`) by adding 2.20, 5.6, a subheading `<h3>Особливості товарів Bulk / Bulk Mix</h3>` + 6.19–6.25, 13.13–13.14; §20 date `18.10.2026`; §21 gets the first link `…/publichna-oferta-arhiv-2026-10-06` («Редакція від 6 жовтня 2026 року (архів)»). Everything else is byte-identical to the 06.10 body. Use it verbatim.
- Owner decisions already taken:
  - cards go to the subcategory «Набори» (Pokémon id 64; One Piece id 68 «Набори та бокси One Piece») — verify both ids against the backup;
  - stock shows «В наявності / Немає в наявності» **with the quantity**;
  - the owner enters weight and dimensions himself;
  - two new attributes in group 7: `Кількість карток у наборі`, `Склад за рідкістю`;
  - no damage-policy sentence; condition = «Оригінальні картки з розкритих бустерів; ступінь стану не заявляється».
- Prices (owner RRP): `PKM-JP-BULK-155` 250 грн, `OP-JP-BULK-155` 250 грн, `PKM-MIX-BULK-100` 150 грн.
- Known live facts (verify, do not trust): table prefix `ocp5_`, Ukrainian `language_id` 4; attribute group 7; attributes 12 Мова, 13 Назва сету, 17 Стан, 18 Походження товару, 20 Виробник, 21 Тип пакування, 24 Додатковий вміст (never use 15, 16, 19, 49); manufacturers Pokémon 11, Bandai 12; technical fields mirrored from the Mystery Mix analogue (products 77/85): `shipping=1`, `subtract=0`, `minimum=1`, `stock_status_id=5`, `tax_class_id=0`, `weight_class_id=2`, `length_class_id=1`. Live offer: `ocp5_information_description` id=3, language 4. Archive pattern: see `handoffs/handoff_LEGAL-003_offer-rare-pack-and-archive_20261006.md` and `patches/LEGAL-002b-3DP_offer-revision-and-archive_20260806.php`.
- SEO keywords (all free in the 2026-09-24 backup; re-check on the newest backup): `Pokemon-Bulk-155-JP`, `One-Piece-Bulk-155-JP`, `Pokemon-Bulk-Mix-100`. The SKU never goes into a URL.

## 3. Goal

1. Publish the 18.10.2026 offer and keep the 06.10.2026 edition reachable as an archive.
2. Create the 3 Bulk / Bulk Mix product cards (disabled until the owner finishes photos, weights and stock) with correct attributes, SEO, categories and manufacturer.

## 4. What to change

### Stage 0 — read-only audit (report first; no write before the owner reads it)

Newest owner-provided cPanel backup + repo. Report in `diagnostics/CAT-005_bulk-audit_20261018.md`. Answer, with evidence:

1. Catalog structure: confirm category ids 64 / 68 and the parent chain of the Mystery Mix analogue (products 77/85): which categories they are assigned to, so the new cards mirror it.
2. Product storage: how the sibling products store `model`, `sku` (older rows leave `sku` NULL — new rows must fill both with the SKU), `description` (**raw HTML or entity-escaped — match live exactly**), `meta_title`, `tag`, attributes, SEO URL (`ocp5_seo_url`, key `product_id`), `product_to_store`, `product_to_category`, `product_to_layout`.
3. Attribute dictionary: confirm ids 12/13/17/18/20/21/24 and their exact names; confirm the two new names do not exist; propose `attribute_id`s and `sort_order` inside group 7 so the two new rows sit sensibly after the existing ones. Language rows for every language the store has, not only 4, if the dictionary has others.
4. Stock display: how the theme shows «В наявності / Немає в наявності» today and whether the quantity is displayed. If the quantity is not displayed, say what controls it (theme setting, twig, admin option) and the smallest change — **report only, do not change the theme or twig without the owner's approval.**
5. CRM sync: how SKUs reach the site (order sync, stock sync, `sku_list`, any SKU-prefix or product-type pattern, any mapping that depends on the product-type column). List everything that could break or mis-handle a `-BULK-` SKU or a new type value `Bulk`. The owner adds `Bulk` to the CRM `Товари` dropdown himself.
6. Side effects of enabling the cards: Merchant feed (what pulls products in, required fields, `identifier_exists`/GTIN handling), sitemap, category and product twig blocks keyed to category or manufacturer (e.g. sealed-product disclosure blocks or schema that would assert something false for a non-sealed set), related-products or filters keyed to «Набори».
7. Offer page: confirm live id=3 body hash against the newest backup; confirm that an `<h3>` renders acceptably inside the offer page (styling of `information.twig` for legal pages). If `<h3>` is not styled, say so — the fallback is `<p><strong>Особливості товарів Bulk / Bulk Mix</strong></p>` and the owner decides.

If the audit finds a blocker, stop and report; do not build patches around it.

### WP1 — `patches/CAT-005_offer-bulk-and-archive_20261018.php` (offer; deploy first)

Clone the LEGAL-003 / LEGAL-002b-3DP pattern exactly.

1. Precondition: live id=3/lang 4 `description` hashes to `9e22347c…fb71` (the 06.10 body). Otherwise refuse, write nothing, exit 1, keep the file.
2. Create the 06.10 archive: `ocp5_information` + `_description` + `_to_store` + `ocp5_seo_url` (store 0, language 4, key `information_id`, keyword `publichna-oferta-arhiv-2026-10-06`; refuse if the keyword exists).
   - title and meta_title `Публічна оферта — архів 06.10.2026`; meta_description='' and meta_keyword='';
   - description = banner + the live body read from the DB at run time after the hash gate, unchanged;
   - banner, same markup as the existing archives:
     `<blockquote><p><strong>Це архівна редакція Публічної оферти від 6 жовтня 2026 року.</strong> Вона зберігається для довідки й не застосовується до нових замовлень. Актуальна редакція: <a href="https://boostershop.website/information/publichna-oferta">boostershop.website/information/publichna-oferta</a></p></blockquote>`
3. Replace id=3/lang 4 `description` with the embedded `offer_html_20261018.html` (gzip+base64 + SHA guard). Do not touch title, meta_title, meta_description, meta_keyword.
4. Gate the new archive link by the exact `href="…arhiv-2026-10-06"`.
5. One transaction; JSON backups to `_patch_backups/<patch>-<ts>/db/`; in-transaction verification; idempotent re-run → `already_applied`; self-delete only on full success.

### WP2 — `patches/CAT-005_bulk-cards_20261018.php` (cards)

1. Preconditions: the 3 SKUs / `model`s and the 3 SEO keywords do not exist; manufacturer 11/12 and categories 64/68 exist; the two new attribute names do not exist. Otherwise refuse, exit 1.
2. Create the two attributes in group 7 (`Кількість карток у наборі`, `Склад за рідкістю`) per the audit proposal.
3. Create 3 products from `CAT-005_bulk-card-copy_FINAL_20261018.md`:
   - `name` = h1, `meta_title`, `meta_description`, `tag`, `description` (HTML exactly as in the file, storage format as found in the audit), `meta_keyword` empty;
   - `model` = `sku` = the SKU; `manufacturer_id` per file; price per §2;
   - technical fields as in §2; `quantity` 0; **`status` = 0 (disabled)**; no image rows (the owner supplies photos); weight and dimensions left to the owner;
   - category assignment per the audit (the subcategory from the file + parent as the sibling does);
   - the 9 attributes per card from the file;
   - SEO URL per file, `product_to_store` 0.
4. Same safety scaffolding as WP1 (one transaction, JSON backups incl. `created_ids.json`, in-transaction verification, idempotent re-run, self-delete on success, `php -l` on PHP 8.0 — no `get_result()` / `fetch_all()`).
5. Include the applicable OpenCart compiled-template/cache refresh step in the owner instructions; no `.css` change is expected.

## 5. Do not touch

- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`
- checkout, payment, Hutko, Checkbox/fiscalization, Nova Poshta, order status, Merchant feed, schema/JSON-LD, CRM scripts and the sheet
- `ocp5_information` ids 1, 2, 4, 5, 6, 7 and the 07.08 archive; id=3 title and meta fields
- `information.twig`, product/category twig and theme settings (report findings; changes need owner approval)
- any existing product or attribute (ids 12–24, 49 included); the deferred SKUs `PKM-JP-BULK-100`, `OP-JP-BULK-100`
- card copy and offer text — use them verbatim
- weight, dimensions, images, stock quantity (owner-entered)

## 6. Likely files / areas

`ocp5_information*`, `ocp5_seo_url`, `ocp5_product`, `ocp5_product_description`, `ocp5_product_attribute`, `ocp5_attribute`, `ocp5_attribute_description`, `ocp5_product_to_category`, `ocp5_product_to_store`, `ocp5_product_to_layout`. Templates to clone: `patches/LEGAL-002b-3DP_offer-revision-and-archive_20260806.php` and the most recent product-creation patch in `patches/` (find it in the audit). Live may have changed after the backup — rely on run-time gates.

## 7. Acceptance criteria

- Audit report delivered before any patch; every audit question answered or marked unverified.
- `php -l` passes on PHP 8.0.
- Scratch-DB test seeded from the newest dump, per patch: fresh apply, idempotent re-run, precondition failure (refuses, exit 1, file kept).
- WP1: id=3 description SHA-256 = `6ec16036…dea`; the 06.10 archive exists with the banner and a body byte-identical to the pre-patch id=3; `/information/publichna-oferta-arhiv-2026-10-06` → 200.
- WP2: exactly 3 products, each with the 9 attributes, 1 SEO URL, correct categories/manufacturer, `status=0`; the description stored byte-equal to the FINAL file (in the live storage format); meta lengths within 63 / 155.
- No row outside the created ids and id=3 changed (diff of before/after dumps in the report).
- Report: `diagnostics/CAT-005_bulk-patches_report_20261018.md`.

## 8. QA / smoke test (owner, after deploy)

Offer (WP1):
- [ ] `/information/publichna-oferta` — «Редакція від: 18.10.2026»; 2.20, 5.6, 6.19–6.25 (with the subheading), 13.13–13.14 visible; the subheading looks like a heading.
- [ ] §21 — four archive links (06.10, 07.08, 24.07, 26.05), each opens 200; the 06.10 archive starts with the banner and its §20 says 06.10.2026.
- [ ] Checkout consent link still opens the offer.

Cards (WP2), after the owner uploads photos, enters weight/dimensions/stock and enables each card:
- [ ] The card opens at `/product/<keyword>`; H1, price, breadcrumb through «Набори».
- [ ] Description, FAQ accordion opens/closes; characteristics show all 9 rows incl. the two new ones.
- [ ] Stock shows «В наявності» with the quantity (or «Немає в наявності»).
- [ ] Mobile view; add to cart works (no order needed).
- [ ] Hard refresh after cache refresh.

CRM (owner): add the `Bulk` value to the `Товари` type dropdown before entering the 5 rows.

## 9. Rollback note

- WP1: restore id=3 `description` from `live_offer_before.json`; delete exactly the ids in `created_ids.json` (seo_url → to_store → description → information). No hardcoded ids.
- WP2: delete exactly the created ids from `created_ids.json` (product-dependent tables first, then `ocp5_product`, then the two attributes). Rollback SQL must be in the patch report.
- No hardcoded ids in either rollback.

## 10. Recommended status after execution

CAT-005 stays In progress. Done only after the owner confirms the offer is published, the cards are on the site and all is OK; Claude (chat) then writes Notion and the dashboard together. Claude reviews the patches first (patch review) before the owner deploys. Deploy order: WP1, then WP2.
