# Patch Handoff — CONTENT-QUALITY: 30 product cards, content wave 2026-08-25 v2

Date: 2026-08-27 | Parent: `CONTENT-QUALITY` (working label — no Roadmap ID assigned yet, see §10)
Executor: **Codex · model=Sol · effort=xhigh** — proposed, owner decides.
Justification: pure DB write straight to production with no staging, 28 long HTML rows plus a product creation, and the payload must be written in the exact entity encoding the existing rows use. The work is mechanical once specified but unforgiving, and DB is a listed risky zone. Claude Code is an equally valid pick if the weekly Codex quota is the constraint; do not split the wave across both.

Source of truth for all content: `BOOSTER-SHOP_CONTENT-QUALITY_RELEASE_20260825_v2.zip` in the repository root. This repo copy already has the `/product/` link prefix applied and a regenerated `SHA256SUMS.txt`; see `LINK_PREFIX_APPLIED.md` inside the archive. **Do not use the 2026-08-24 archive and do not use any copy of the v2 archive from outside the repository.**

---

## 1. Context

The content wave was audited twice. Verdict of 2026-08-27 (`diagnostics/CONTENT-QUALITY_release-package-v2-audit_20260827.md`): the FAQ accordion markup, payload internal consistency, scope discipline and skills v10 all pass, verified against `backup-8.24.2026_10-35-09_boosters`. The owner decided on 2026-08-27 to ship as is, with the link prefix fixed and the category/attribute instructions re-scoped to verification.

All 28 existing products are `status = 0`. Nothing in this wave becomes visible to buyers; visibility is gated separately by `CHECKOUT-011`, `CRM-005`, photos and prices.

Descriptions on this install are stored **HTML-entity-encoded** in `ocp5_product_description.description` (`&lt;h2&gt;`, `class=&quot;…&quot;`, `&amp;`). This is the single highest-risk mechanical detail in the wave: writing raw HTML renders visible tags on the page.

## 2. Goal

Bring 28 existing product cards to the audited v2 content, add the confirmed 3D attribute values, apply the two category renames, and create `BR-CHARM-200`. No product becomes visible, no commercial field changes.

## 3. Work packages — three patch files, never bundled

### WP1 — `patches/CONTENT-QUALITY_cards-update-28_20260827.php`

Update 28 existing products. Table `ocp5_product_description`, `language_id = 4`, and `ocp5_product_attribute`.

**Descriptions — 9 non-3D cards, from `01_FINAL_NON3D_10.md`** (body + FAQ concatenated into `description`, body first, FAQ block last):

| SKU | product_id |
|---|---:|
| `ACC-007-400` | 144 |
| `YGO-JP-QCAC-BBX` | 145 |
| `PKM-JP-STES-BBX` | 146 |
| `PKM-JP-STES-BST` | 147 |
| `PKM-JP-INFX-BBX` | 148 |
| `PKM-JP-TGTR-BBX` | 149 |
| `PKM-JP-TGTR-BST` | 150 |
| `YGO-JP-BETB-BBX` | 151 |
| `YGO-JP-BETB-BST` | 152 |

For these nine also write `meta_title`, `meta_description`, `meta_keyword` from the payload `## SEO` block.

`PKM-JP-SVEL-SET` is the tenth card in that file. **It is out of scope** — see §6.

**Descriptions — 19 existing 3D cards, from `02_FINAL_3D_20_PATCH.md`** (body + FAQ, same concatenation):

| SKU | product_id | | SKU | product_id |
|---|---:|---|---|---:|
| `BR-MEW-100` | 125 | | `ACC-3D-PKM-110` | 137 |
| `BR-CHARM-100` | 126 | | `ACC-3D-PKM-120` | 138 |
| `BR-SQUIR-100` | 127 | | `ACC-3D-PKM-130` | 139 |
| `BR-BULB-100` | 128 | | `ACC-3D-PKM-200` | 140 |
| `BR-PIKA-100` | 129 | | `ACC-3D-PKM-300` | 141 |
| `FIG-ONIX-500` | 130 | | `ACC-3D-PKM-700` | 142 |
| `FIG-GEOD-511` | 131 | | `ACC-3D-PKM-710` | 143 |
| `FIG-MEW-100` | 132 | | `FIG-LUFFY-500` | 134 |
| `FIG-PKBL-600` | 133 | | `FIG-LUFFY-400` | 135 |
| | | | `FIG-LUFFY-410` | 136 |

For these nineteen, **Meta changes only on two cards**, from `02` §"Meta / attributes — локальні правки":

- `FIG-PKBL-600` (133) — `meta_description` → `Фігурка-клікер Покебол (Pokémon) 2,5 см із натискною механікою на чорній основі. 3D-друк PLA — Booster Shop, Україна.` (removes the internal word `fidget` from the live value)
- `ACC-3D-PKM-710` (143) — `meta_description` → `Обертова підставка для слабів: 6 карток на дисплеї та до 6 усередині. Сумісна з PSA, BGS, SGC і слабами на магніті.`

Every other `meta_title` / `meta_description` / `meta_keyword` on products 125–143 stays as it is in the database.

**Attributes** (`ocp5_product_attribute`, `language_id = 4`), values confirmed by the owner:

- attribute 43 `Типовий строк виготовлення при відсутності на складі` = `1–2 робочих дні` on all 19 products above and on `BR-CHARM-200` in WP3. The attribute row already exists in `ocp5_attribute` — **verify, do not create or rename**.
- attribute 44 `Може трапитись у Mystery Box`: `Ні` on 125, 126, 127, 128, 129. The row already exists under the current name — **verify, do not rename**. `BR-CHARM-200` gets `Так` in WP3.
- `ACC-3D-PKM-700` (142): `Місткість дисплея` = `6 топлоадерів`, `Внутрішнє зберігання` = `до 38 топлоадерів`.
- `ACC-3D-PKM-710` (143): `Сумісність` = `PSA, BGS, SGC, слаби на магніті`, `Місткість дисплея` = `6 слабів`, `Внутрішнє зберігання` = `до 6 слабів`.

Resolve those four attribute names to their `attribute_id` against the database. If any of them does not exist, **stop and report** — do not create an attribute.

### WP2 — `patches/CONTENT-QUALITY_categories-73-74_20260827.php`

Table `ocp5_category_description`, `language_id = 4`. From `05_CATEGORY_AND_GLOBAL_PATCH.md` §4–§5:

- category 73: `name` → `Фігурки та декор Pokémon`; `meta_keyword` → `брелоки Pokémon, фігурки Pokémon, 3D-друк Pokémon, декор Pokémon, Pokémon 3D-друк Україна`. Description, meta_title, meta_description, FAQ unchanged.
- category 74: `name` → `Фігурки та декор One Piece`. Nothing else.

§1 and §2 of that file are **verification only**: attribute 44 already reads `Може трапитись у Mystery Box` and attribute 43 already exists in the 2026-08-24 backup. Confirm both and report; issue no rename.

Category 74 stays `status = 0`. Its text still promises four One Piece keychains that do not exist.

### WP3 — `patches/CONTENT-QUALITY_create-br-charm-200_20260827.php`

Create one product from `03_NEW_SKU_BR-CHARM-200_FULL.md`. Run **after** WP1, so the anchor in `BR-CHARM-100` resolves the moment the row exists.

- `ocp5_product`: `model = BR-CHARM-200`, `status = 0`, `quantity = 0`, `stock_status_id = 8` (the value the other 19 3D products carry — verify against product 126 rather than trusting this line), `date_available` = deploy date, weight/dimensions from the payload `Характеристики` block.
- `ocp5_product_description` (`language_id = 4`): name, description (body + FAQ), meta_title, meta_description, meta_keyword from the payload.
- `ocp5_seo_url`: `key = product_id`, `value = <new id>`, `keyword = brelok-kliker-charmander-pokemon-3d-druk`, `store_id = 0`, `language_id = 4`, matching the shape of the existing product rows. This keyword does not exist in the 2026-08-24 backup — confirm it is still free before insert and abort on collision.
- `ocp5_product_to_category`: 59 and 73.
- attributes per the payload, including attribute 43 = `1–2 робочих дні` and attribute 44 = `Так`.
- **Price, quantity beyond the placeholder, and status come from the owner or CRM. Do not invent them.** If the owner has not supplied a price, insert with the same 1 UAH placeholder the other 3D products carry and say so in the report.

`02_FINAL_3D_20_PATCH.md` also contains a `BR-CHARM-200` body/FAQ section; it is byte-identical to `03`. Use `03` — it is the only file with the full create payload.

## 4. What NOT to touch

- `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess` — untouched by this wave.
- checkout, payment, Hutko, Checkbox, fiscalization, Nova Poshta, order status — untouched.
- Merchant feed, schema / JSON-LD — deliberately out of scope for every wave of this task.
- `ocp5_product.status`, `price`, `quantity`, `stock_status_id`, `image` on products 125–152. The wave changes content only.
- `ocp5_product_description.name` on all 28 existing products. **The payload names differ from the live names on four cards and the live names are correct** — they follow the catalog convention set by the 2026-08-22 patch:

  | SKU | live name (keep) | payload name (ignore) |
  |---|---|---|
  | `ACC-007-400` | `…на 400 карток, жовтий` | `…на 400 місць, жовтий` |
  | `YGO-JP-QCAC-BBX` | `Бустер бокс Yu-Gi-Oh! OCG: Quarter Century Art Collection (Японське видання)` | `Yu-Gi-Oh! Quarter Century Art Collection — Booster Box (Японське видання)` |
  | `YGO-JP-BETB-BBX` | `Бустер бокс Yu-Gi-Oh! OCG: BEYOND THE BRAVE (Японське видання)` | `Yu-Gi-Oh! BEYOND THE BRAVE — Booster Box (Японське видання)` |
  | `YGO-JP-BETB-BST` | `Бустер Yu-Gi-Oh! OCG: BEYOND THE BRAVE (Японське видання)` | `Бустер Yu-Gi-Oh! BEYOND THE BRAVE (Японське видання)` |

  Product names also carry deliberate variants (`plans/3D-P_sku-naming-convention_20260807.md`, ред. 4). Report the deltas, change nothing.
- existing `ocp5_seo_url` rows for products 125–152.
- categories 59, 60, 64, 70, 71, 72 and the `product_to_category` mapping of products 125–152 (`05` §7).
- attribute rows in `ocp5_attribute` / `ocp5_attribute_description` — read only.
- `PKM-JP-SVEL-SET` — not in the database, blocked, see §6.
- any product outside the 28 listed plus the one created.

## 5. Acceptance criteria

Measured by the patch itself and printed in the run output.

- [ ] 28 rows updated in `ocp5_product_description` (`language_id = 4`), product_ids exactly 125–152, no other row written.
- [ ] Every updated `description`, after `html_entity_decode`, contains exactly one `<section class="bs-faq-accordion"` and zero occurrences of `class="bs-faq"` or `<div class="bs-faq-accordion"`.
- [ ] Decoded FAQ item counts per card match the payload: 17 cards with 1 item, 8 with 2, 5 with 3 across the full 30-card set; 43 items across the 28 updated cards.
- [ ] Every `id`/`aria-controls`/`aria-labelledby` value written is unique within its own row.
- [ ] Stored form is entity-encoded: a raw `<h2>` appears in **zero** written rows; `&lt;h2&gt;` appears in every one.
- [ ] All 5 internal anchors in the written content start with `/product/`; zero bare-slug `href="/…"` remains.
- [ ] `meta_description` changed on exactly two of products 125–143 (133, 143) and on all nine of 144–152; `meta_title` and `meta_keyword` changed on 144–152 only.
- [ ] `name` unchanged on all 28 rows — assert before/after equality and fail the patch if it differs.
- [ ] `status`, `price`, `quantity`, `stock_status_id`, `image` unchanged on all 28 products — assert before/after equality.
- [ ] Attribute 43 present with value `1–2 робочих дні` on 20 products (19 existing + the created one).
- [ ] Attribute 44 = `Ні` on 125–129 and `Так` on the created `BR-CHARM-200`; no other product's attribute 44 value touched.
- [ ] Category 73 `name` = `Фігурки та декор Pokémon`, category 74 `name` = `Фігурки та декор One Piece`, both `status = 0` after the run.
- [ ] Attribute 43 and 44 confirmed to pre-exist; the run output states this and issues no rename.
- [ ] `BR-CHARM-200` exists with `status = 0`, categories 59 + 73, and a `seo_url` row with keyword `brelok-kliker-charmander-pokemon-3d-druk` that is unique across the table.
- [ ] Each patch: file-exists / row-exists pre-check, anchor count check, backup to `_patch_backups/<patch>-<ts>/` including a **restore `.sql` with the pre-change rows**, `php -l` gate, `already_applied=yes` on repeat run, self-delete on success.

## 6. Out of scope — `PKM-JP-SVEL-SET`

The card has finished content in `01_FINAL_NON3D_10.md` but the product does not exist in the database and cannot be created yet:

- the payload states `SEO URL: resolve against existing site convention during release; do not invent` — no slug is decided;
- no price, no CRM row (`CRM-005`);
- category mapping is proposed (59 + 64) but not owner-confirmed in the same way `BR-CHARM-200` is.

Do not create it and do not invent a slug. It needs its own owner decision and its own patch file.

## 7. QA — owner runs after each patch

All 29 products are `status = 0`, so the storefront cannot render them. QA at this stage is admin and DB level; accordion behaviour is verified later, when the first card is enabled.

1. Admin → Catalog → Products → `BR-MEW-100`, `ACC-3D-PKM-710`, `YGO-JP-QCAC-BBX` → Description tab shows formatted text, **not** visible `<h2>` tags.
2. Same three cards → the FAQ block is present at the end of the description.
3. `ACC-3D-PKM-710` → Attribute tab shows `Сумісність`, `Місткість дисплея`, `Внутрішнє зберігання`, and `Типовий строк виготовлення при відсутності на складі`.
4. `BR-CHARM-100` → Description → the clicker link reads `/product/brelok-kliker-charmander-pokemon-3d-druk`.
5. Admin → Catalog → Categories → 73 and 74 show the new names and both remain disabled.
6. `BR-CHARM-200` exists, is disabled, sits in Pokémon + Фігурки та декор Pokémon, and its SEO keyword is the clicker slug.
7. Spot-check that no product suddenly shows a price other than the one it had.

**Deferred to first activation:** open one enabled 3D card on the storefront and confirm the FAQ accordion expands and collapses and that the theme renders the visible heading. Until then the markup is verified structurally only.

## 8. Risks

- **Risky zone: DB.** Every write lands on production; there is no staging. Convention 6 applies — the owner has approved these DB changes, and each patch must carry rollback SQL in its header and write the pre-change rows to `_patch_backups/`.
- **Entity encoding.** Writing raw HTML into `description` renders visible tags on 28 cards at once. This is the failure mode to guard hardest.
- **Parallel writer.** Only one executor authors these three files. Claude (chat) reviews them and does not author.
- Not touched, therefore no smoke test required: checkout, payment, fiscalization, schema, Merchant feed. `bs-checkout-smoke` and `bs-merchant-schema-qa` are not in play for this wave.
- SEO risk is low: no URL, canonical, redirect or sitemap change; the only new URL is one disabled product. `bs-seo-risk-gate` does not need a separate pass for this wave.

## 9. Rollback

Per patch, in this order:

1. `_patch_backups/<patch>-<ts>/restore.sql` restores the affected rows verbatim — 28 `ocp5_product_description` rows plus touched `ocp5_product_attribute` rows for WP1, two `ocp5_category_description` rows for WP2.
2. WP3 rolls back by deleting the created `product_id` from `ocp5_product`, `ocp5_product_description`, `ocp5_product_to_category`, `ocp5_product_attribute` and `ocp5_seo_url`. The patch header must carry that DELETE set with the actual id filled in by the run.
3. Rollback of the 2026-08-22 patches stays in `_patch_backups/` on the server and must not be deleted.

Because all products are disabled, a bad run is not customer-visible — restore at the owner's convenience rather than under pressure.

## 10. Status after execution

Executor: patch files into `patches/`, diagnostic into `diagnostics/CONTENT-QUALITY_<wp>_report_20260827.md`. **Never commit, push or deploy.** Delivery is the file plus the run command; the owner uploads to `~/public_html` and runs `php <patch>.php`.

Claude (chat) reviews with `bs-patch-review` before the owner runs anything.

Notion: `CONTENT-QUALITY` is still a working label with no Roadmap ID. The row and its `ROADMAP_TASKS` mirror have to be created before a status write is possible — that is Claude's action, not the executor's. Proposed status after a clean run and owner QA: `In progress`, not `Done`; the wave does not close until the 6 remaining `BR-` cards and the enable-gates are resolved.
