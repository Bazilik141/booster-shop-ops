# CAT-005 — Stage 0 read-only audit (Bulk / Bulk Mix cards + offer 18.10.2026)

Date: 2026-10-18
Executor: Claude Code · model=Opus 5.5 · effort/thinking=not exposed to the session
Base: `origin/codex/3dp-payout-approval-status` @ `a269f17` (the only branch carrying the handoff; not `master`).
Handoff: `handoffs/handoff_CAT-005_bulk-cards-and-offer_claude-code_20261018.md`

## Inputs and method

- DB: `live-snapshots/20261018_cat005-audit-inputs/catalog-extract_backup-10.15.2026.sql.txt` (backup `10.15.2026_13-09-55`, dump finished 2026-10-15 13:09:59 UTC), loaded into a scratch MariaDB 10.11.14 in the sandbox. No real database was touched.
- Files: `live-snapshots/20261018_cat005-audit-inputs/live-files/` (product/category/information controllers, models, twig).
- Repo: `crm/apps-script/Code.gs` (mirror state per `SOURCE_STATE.md`: 2026-10-04), `sitemap-regen.sh`, prior patches, `live-snapshots/20261009_design-canon/`.
- Input hashes verified: `offer_html_20261018.html` SHA-256 `6ec16036…dea`, 100 972 bytes; `offer_html_20261006.html` SHA-256 `9e22347c…fb71`. The 06.10→18.10 diff contains exactly the additions the handoff lists (2.20, 5.6, `<h3>` + 6.19–6.25, 13.13–13.14, §20 date, first §21 link) and nothing else.
- The public site was not reachable from the sandbox (proxy 403), so nothing was read live.
- **Unverified** = not determinable from these inputs. Live may also have changed after 2026-10-15; the patches must gate at run time.

## Blockers

None. The decisions D1–D4 below shape WP2's field values. None of them blocks WP1.

## Answers

### 1. Catalog structure

| Item | Finding |
|---|---|
| Language | only `language_id` 4 (`uk-ua`). No other language rows are needed anywhere. |
| Category 64 | «Набори», parent 59 «Pokémon», status 1, store 0, path 59→64, SEO `path=59_64` → `Pokemon/pokemon-tcg-nabory` |
| Category 68 | «Набори та бокси One Piece», parent 60 «One Piece Card Game», status 1, store 0, path 60→68, SEO `path=60_68` → `One-Piece/one-piece-nabory-ta-boksy` |
| Analogue 77 `PKM-JP-MBX-ST` | `product_to_category` = {59, 64} |
| Analogue 85 `OP-JP-MBX-ST` | `product_to_category` = {60, 68} |

New cards therefore get: `PKM-JP-BULK-155` and `PKM-MIX-BULK-100` → {59, 64}; `OP-JP-BULK-155` → {60, 68}.

### 2. Product storage (as found on 77/85 and across all 151 products)

| Field | Live format | WP2 rule |
|---|---|---|
| `description` | **entity-escaped** in all 151 rows (`&lt;p&gt;`, `&quot;`); zero rows with raw `<`; zero double-escaped. ASCII `'` stays raw (35 rows contain it; no `&#039;`). This is `htmlspecialchars(…, ENT_COMPAT, 'UTF-8')`, the OC4 request cleaner, and matches the earlier creation patches (`CONTENT-QUALITY_create-svel-set_20260828.php`: `ENT_COMPAT \| ENT_SUBSTITUTE`). Storefront decodes it with `html_entity_decode(…, ENT_QUOTES)` (`product.php:312`). | store `htmlspecialchars(FINAL, ENT_COMPAT\|ENT_SUBSTITUTE, 'UTF-8')`. Escaped size ≈ 5.7–5.8 KB per card (column is `TEXT`). |
| `name`, `meta_title`, `meta_description`, `tag` | same escaping (`S&amp;V`, raw `'`) | same function; for the FINAL strings it is an identity (no `& < > "` in them) |
| attribute `text` | same escaping (7 of 1 405 rows carry entities) | same function; identity for the FINAL values |
| `model` / `sku` | 109 rows `sku` NULL, 42 rows `sku = model`, 0 differing | `model = sku = <SKU>` |
| `meta_keyword` | filled on 77/85 | empty `''` per handoff |
| `master_id` 0, `variant` `''`, `override` `''`, `location` `''`, `upc/ean/jan/isbn/mpn` NULL, `points` 0, `rating` 0 | as on 77/85 | mirror |
| `date_available` | 77/85: creation date. Storefront `getProduct` requires `status='1' AND date_available <= NOW()`; `bs_is_preorder` = `date_available > today` | set to the run date; never a future date |
| `product_to_store` | store 0 only | store 0 |
| `product_to_layout` | **0 rows in the whole table** | create none |
| `ocp5_seo_url` | `store_id 0, language_id 4, key 'product_id', value '<id>', sort_order 0` | same |
| discounts / images / options | 85 has one expired 2026-08 discount; 10 image rows on 77/85; no options | create none (photos are owner-supplied) |
| FAQ markup | 150 of 151 live descriptions carry the same `bs-faq-accordion` markup, editor-normalised (`data-bs-faq-accordion=""`, `hidden=""`, attributes re-ordered). FINAL uses bare `data-bs-faq-accordion` / `hidden`, which has identical DOM semantics. | store FINAL verbatim (escaped); do not normalise. If the owner later saves the card in admin, the editor will normalise it — harmless. |

Technical fields from the handoff confirmed on 77/85: `shipping=1`, `subtract=0`, `minimum=1`, `stock_status_id=5`, `tax_class_id=0`, `weight_class_id=2` (g), `length_class_id=1` (cm). See D1 on `subtract`.

Free identifiers (case-insensitive, `utf8mb4_unicode_ci`): `Pokemon-Bulk-155-JP`, `One-Piece-Bulk-155-JP`, `Pokemon-Bulk-Mix-100`, `publichna-oferta-arhiv-2026-10-06` — no rows. No product has `BULK` in `model`/`sku`/name. Deferred `PKM-JP-BULK-100` / `OP-JP-BULK-100`: absent, as required.

Next AUTO_INCREMENT in the backup (informational only; the patches must use `insert_id`): product 214, attribute 57, seo_url 2371, information 9.

### 3. Attribute dictionary (group 7 «Характеристики», language 4)

| id | name (exact) | sort_order |
|---|---|---|
| 13 | Назва сету | 1 |
| 27 | Тип товару | 1 |
| 12 | Мова | 2 |
| 21 | Тип пакування | 3 |
| 15 / 16 / 24 | Кількість карток у бустері / Кількість бустерів у боксі / Додатковий вміст | 4 |
| 19 / 49 | Зважування / Кількість карток у колоді | 5 |
| 17 | Стан | 6 |
| 18 | Походження товару | 7 |
| 20 | Виробник | 8 |
| 14 | Рік випуску | 9 |

Ids 12/13/17/18/20/21/24 and their names match the handoff and FINAL exactly. `Кількість карток у наборі` and `Склад за рідкістю` do not exist (0 rows). Manufacturers 11 «The Pokémon Company» and 12 «Bandai» exist, store 0.

Storefront order is `a.sort_order, ad.name` (`model/catalog/product.php:627`). Proposal (D2): `Кількість карток у наборі` sort 4, `Склад за рідкістю` sort 5. Resulting card table: Назва сету · Мова · Тип пакування · Додатковий вміст · **Кількість карток у наборі** · **Склад за рідкістю** · Стан · Походження товару · Виробник — the count and composition sit with the other content/count rows. Ids are taken from `insert_id` at run time (57/58 in this backup).

Attribute 27 «Тип товару» is in group 7 but not in the handoff's list. FINAL does not set it; it drives the «Rare Pack» listing badge (`CAT-004-SD-7`). Leaving it unset is correct for Bulk; no change proposed.

### 4. Stock display

- Product page (`product.twig:85-155`, `product.php:315-333`):
  - `quantity <= 0` → stock text = the product's stock status. With `stock_status_id=5` («Закінчився») the page shows title **«Закінчився»** with the sub-line «Товару немає в наявності».
  - `quantity > 0` → title **«В наявності»**. The sub-line «**N шт** на складі» appears only when the stock text is numeric, i.e. only when the store setting `config_stock_display` («Display Stock») is on; otherwise it shows the store's default in-stock status name.
- Listing card (`thumb.twig:42`): out-of-stock badge «Немає в наявності».
- `config_stock_display` lives in `ocp5_setting` → **unverified**. It is store-wide, not per product. Smallest change if the quantity is not shown today: Admin → System → Settings → store → Option → Display Stock = Yes. No code change, but it affects every product. Report only; not changed.
- The literal title «Немає в наявності» on the product page would need a twig change or a store-wide stock-status rename → out of scope; report only.

### 5. CRM / sync

- Site → CRM order sync (`upsertOpenCartOrder_`, `Code.gs:4993`) takes `product.sku || product.model`, upper-cases it and resolves aliases (`normalizeOpenCartSku_`). With `sku = model = <SKU>` the Bulk SKUs pass through unchanged. They must exist in `Товари` (owner has entered the 5 rows — unverified).
- `isMysteryBoxSale_` keys on `-MBX$` / «mystery box» / «містері» → Bulk SKUs and names do not trigger Mystery Box consumables.
- Stock formula special-cases only type `Mystery Box` (`Code.gs:4511`); a new type `Bulk` takes the standard formula.
- `crm011GameCode_` uses the first SKU segment (`PKM`/`OP`) → fine. No code reads the second segment as a language, so `MIX` breaks nothing found.
- `inventoryMigrationIsBundleSku_` matches «набір» in the name → Bulk rows will be offered as split *sources* in the inventory-split tool. Cosmetic risk only (the owner would have to choose a split); noted, no action.
- CRM integrity check: a literal in `Товари` «Коротка назва» is reported as `formula_column_literal` unless the SKU is in the manual-short-name list. OPS-CRMINTEGRITY applies to the owner's row entry: run the check before and after.
- **Unverified:** the live CRM version after V195 (mirror 2026-10-04), the live `Товари` rows, and the site-side async order-sync script (not in inputs). No SKU-pattern logic was found in repository patches.

### 6. Side effects of enabling the cards

- While `status=0`: storefront product/listing queries require `status='1'` (`model/catalog/product.php:117,164,206-211`) → the cards are not listed and not served.
- JSON-LD (`product.twig:1007+`): `brand` from the manufacturer, `sku` from `model`, availability from the stock text. **`itemCondition` is hard-coded `NewCondition`** (offer and return policy) for every product → see D3. Schema is on the do-not-touch list; not changed.
- No twig/controller block in the inputs is keyed to category 64/68 or manufacturer 11/12. `category.php:508-514` keys only on categories 65/66/67/70. The Rare Pack badge keys on attribute 27. No sealed-product disclosure block exists in the product twig.
- Sitemap: `sitemap-regen.sh` mirrors the dynamic OpenCart sitemap; the generator extension is not in the inputs → **unverified**, but enabled cards are expected to appear.
- Merchant feed (`merchant-feed.tsv`): generator and its `condition`/GTIN/`identifier_exists` rules are not in the inputs or the repo → **unverified**. If it emits `condition=new` for all products, the same question as D3 applies.
- Filters, related products: no product-to-filter or related tables in the extract → **unverified**; WP2 writes none.

### 7. Offer page

- Live `id=3`/lang 4 `description` SHA-256 in the 15.10 backup = `9e22347c…fb71` (= the 06.10 body), 89 596 bytes. WP1's precondition holds as of 2026-10-15.
- Information descriptions are stored as **raw HTML** (unlike products). Archives 6/7/8: `sort_order 0`, `status 1`, store 0, no layout row, `meta_description=''`, `meta_keyword=''`, description = banner immediately followed by the body (no separator). WP1 will mirror that.
- `<h3>`: `information.twig` renders the body inside `.bs-cp-main`; its TOC script reads only `h2`. `.bs-cp-main h3` is styled (16–17 px, 700) in `boostershop-ds.css` / `content-pages.css` of the 2026-10-09 design-canon snapshot. The 15.10 CSS is not in the inputs → **unverified**, but likely styled. Recommendation: keep `<h3>` and check visually in owner QA; the `<p><strong>` fallback is not needed unless QA shows otherwise.
- The legal-page twig replacements (`information.twig:48-55`) look for anchors absent from both the 06.10 and 18.10 bodies → no behavioural change from the new body.

## Owner decisions needed before WP2

| # | Question | Options | Recommendation |
|---|---|---|---|
| D1 | `subtract` | (a) `0` as the handoff says — mirrors Mystery Mix. The site quantity never decreases on orders, so with quantity display on, the shown «N шт» goes stale and overselling is possible (depends on `config_stock_checkout`, unverified). (b) `1` — like 144 of 151 products, incl. all recent ACC/EXSD cards; the site quantity decreases per sale. | (b) `1`: Bulk is counted stock, not a Mystery Box. |
| D2 | sort_order of the two new attributes | (a) 4 / 5 — next to the other count rows; (b) 10 / 11 — after «Рік випуску», i.e. at the end | (a) |
| D3 | Schema `itemCondition: NewCondition` (and possibly the feed) asserts "new" for cards taken out of opened boosters | (a) launch as is and open a separate schema/feed task before enabling; (b) accept "new" | (a). Not changed here: schema and feed are do-not-touch. |
| D4 | Quantity display | owner checks one live in-stock product for «N шт на складі». If absent: turn on Display Stock (store-wide) or accept no count | owner check |

WP1 does not depend on D1–D4 and can be built on «go».
