# Patch Handoff — CONTENT-QUALITY + 3D-P-CARDCONTENT + CRM RRP, wave of 2026-08-28

Date: 2026-08-28 | Parent: `CONTENT-QUALITY` (working label, no Roadmap ID yet — see §10)
Executor: **Codex · model=Sol · effort=xhigh** — proposed, owner decides.
Justification: six DB work packages written straight to production with no staging, long entity-encoded HTML payloads, and one package that changes prices on pages buyers can see right now. Mechanical once specified, unforgiving if wrong. Claude Code is an equally valid pick on quota; do not split the wave between executors.

This file replaces `handoff_CONTENT-QUALITY_patch-30-cards_20260827.md` in full. That version and the three runners built from it are superseded — see §11.

## Sources of truth

| What | Where |
|---|---|
| 28 existing cards, body + FAQ + meta | `BOOSTER-SHOP_CONTENT-QUALITY_RELEASE_20260825_v2.zip` (repo root; `/product/` prefix already applied) |
| FAQ recovery overlay + Inferno X paragraph | `handoffs/addendum_CONTENT-QUALITY_faq-recovery-readycopy_20260828.md` |
| 7 kit-card products | `handoffs/handoff_3D-P-CARDCONTENT_kit-cards-7sku_20260828.md` |
| CRM recommended prices | `Booster Shop CRM — облік товарів - РРЦ.csv` (repo root, column `РРЦ, грн`, data from row 3) |
| Live catalogue snapshot | `live-snapshots/20260828_cards-export/` — text and metadata only, see §9 |
| Live prices and specials | this file, §7 — read from production 2026-08-28 |
| SKU and product-name canon | `plans/3D-P_sku-naming-convention_20260807.md` (ред. 5) |

Do not mix in the 2026-08-24 archive or any copy of the v2 archive from outside the repository.

---

## 1. Context

The content wave was audited three times and is accepted. Two things changed since the 2026-08-27 handoff:

- The owner added an RRP reconciliation against CRM, and reversed the exclusion of `PKM-JP-SVEL-SET`, which now ships with a decided slug, dimensions and status.
- A fresh production read (2026-08-28) showed that **three of the 28 existing cards are now visible** — 145, 151, 152. The previous handoff's assumption that the whole content wave is invisible no longer holds.

Descriptions on this install are stored **HTML-entity-encoded** in `ocp5_product_description.description` (`&lt;h2&gt;`, `class=&quot;…&quot;`, `&amp;`). Writing raw HTML renders visible tags on the page. This is the single most dangerous mechanical detail in the wave.

## 2. Goal

Six independent work packages:

| WP | File | What |
|---|---|---|
| WP1 | `CONTENT-QUALITY_cards-update-28_20260828.php` | content of 28 existing cards, v2 + addendum overlay |
| WP2 | `CONTENT-QUALITY_category-73-keywords_20260828.php` | one `meta_keyword` on category 73 |
| WP3 | `CONTENT-QUALITY_create-br-charm-200_20260828.php` | create `BR-CHARM-200` |
| WP4 | `CONTENT-QUALITY_create-svel-set_20260828.php` | create `PKM-JP-SVEL-SET` |
| WP5 | `3D-P-CARDCONTENT_create-kit-cards-7_20260828.php` | create the 7 `FIG-*-300` kit cards |
| WP6 | `CRM-RRP_site-price-reconcile_20260828.php` | reconcile 24 visible prices with CRM |

One work package per file. Never bundle. Run order: **WP3 before WP1** (WP1's `BR-CHARM-100` description links to the slug WP3 creates). Everything else is order-independent.

---

## 3. WP1 — 28 existing cards

Table `ocp5_product_description`, `language_id = 4`, plus `ocp5_product_attribute`.

### 3.1 Targets

Nine non-3D cards from `01_FINAL_NON3D_10.md` — body + FAQ concatenated into `description` (body first, FAQ block last), plus `meta_title`, `meta_description`, `meta_keyword` from the payload `## SEO` block:

| SKU | id | | SKU | id |
|---|---:|---|---|---:|
| `ACC-007-400` | 144 | | `PKM-JP-TGTR-BBX` | 149 |
| `YGO-JP-QCAC-BBX` | 145 | | `PKM-JP-TGTR-BST` | 150 |
| `PKM-JP-STES-BBX` | 146 | | `YGO-JP-BETB-BBX` | 151 |
| `PKM-JP-STES-BST` | 147 | | `YGO-JP-BETB-BST` | 152 |
| `PKM-JP-INFX-BBX` | 148 | | | |

`PKM-JP-SVEL-SET` is the tenth card in that file — it is created in **WP4**, not here.

Nineteen 3D cards from `02_FINAL_3D_20_PATCH.md` — body + FAQ, same concatenation:

| SKU | id | | SKU | id |
|---|---:|---|---|---:|
| `BR-MEW-100` | 125 | | `FIG-LUFFY-400` | 135 |
| `BR-CHARM-100` | 126 | | `FIG-LUFFY-410` | 136 |
| `BR-SQUIR-100` | 127 | | `ACC-3D-PKM-110` | 137 |
| `BR-BULB-100` | 128 | | `ACC-3D-PKM-120` | 138 |
| `BR-PIKA-100` | 129 | | `ACC-3D-PKM-130` | 139 |
| `FIG-ONIX-500` | 130 | | `ACC-3D-PKM-200` | 140 |
| `FIG-GEOD-511` | 131 | | `ACC-3D-PKM-300` | 141 |
| `FIG-MEW-100` | 132 | | `ACC-3D-PKM-700` | 142 |
| `FIG-PKBL-600` | 133 | | `ACC-3D-PKM-710` | 143 |
| `FIG-LUFFY-500` | 134 | | | |

`BR-CHARM-200` is created in WP3 and is not part of these 28.

Meta on 125–143 changes on **two cards only**:

- `FIG-PKBL-600` (133) → `meta_description` = `Фігурка-клікер Покебол (Pokémon) 2,5 см із натискною механікою на чорній основі. 3D-друк PLA — Booster Shop, Україна.`
- `ACC-3D-PKM-710` (143) → `meta_description` = `Обертова підставка для слабів: 6 карток на дисплеї та до 6 усередині. Сумісна з PSA, BGS, SGC і слабами на магніті.`

Everything else in `meta_title` / `meta_description` / `meta_keyword` on 125–143 stays as it is in the database.

### 3.2 Addendum overlay — apply before encoding

From `addendum_CONTENT-QUALITY_faq-recovery-readycopy_20260828.md`, insert the ready `<div class="bs-faq-item">` blocks verbatim, immediately before the existing `</section>` of that card's accordion. Do not renumber existing items, do not create a second section, do not rewrite a single word of the supplied copy.

| SKU | id | v2 items | append | final |
|---|---:|---:|---:|---:|
| `ACC-3D-PKM-110` | 137 | 1 | 2 | 3 |
| `ACC-3D-PKM-120` | 138 | 1 | 1 | 2 |
| `ACC-3D-PKM-130` | 139 | 1 | 1 | 2 |
| `ACC-3D-PKM-200` | 140 | 1 | 1 | 2 |
| `ACC-3D-PKM-700` | 142 | 1 | 1 | 2 |
| `FIG-LUFFY-500` | 134 | 1 | 1 | 2 |
| `ACC-007-400` | 144 | 2 | 2 | 4 |

`PKM-JP-INFX-BBX` (148) takes no new FAQ item. Replace exactly one body paragraph — the one beginning `У заводськи запечатаній коробці <strong>30 бустерів по 5 карт</strong>.` — with the ready paragraph in addendum §4. The owner-approved sentence inside it is byte-frozen.

### 3.3 Attributes

`ocp5_product_attribute`, `language_id = 4`, owner-confirmed values:

- attribute 43 `Типовий строк виготовлення при відсутності на складі` = `1–2 робочих дні` on all 19 products 125–143. The attribute row exists — **verify, never create or rename**.
- attribute 44 `Може трапитись у Mystery Box` = `Ні` on 125, 126, 127, 128, 129. Exists under the current name — verify only.
- `ACC-3D-PKM-700` (142): `Місткість дисплея` = `6 топлоадерів`, `Внутрішнє зберігання` = `до 38 топлоадерів`.
- `ACC-3D-PKM-710` (143): `Сумісність` = `PSA, BGS, SGC, слаби на магніті`, `Місткість дисплея` = `6 слабів`, `Внутрішнє зберігання` = `до 6 слабів`.

Resolve those four names to `attribute_id` against the database. If any is missing — stop and report; do not create an attribute.

### 3.4 Three of these products are visible

`YGO-JP-QCAC-BBX` (145), `YGO-JP-BETB-BBX` (151) and `YGO-JP-BETB-BST` (152) are `status = 1` as of 2026-08-28. Their descriptions change on pages buyers can open. This raises WP1's risk from "hidden content" to "live content on three pages" and it also makes the FAQ accordion rendering verifiable for the first time — see §8.

The remaining 25 are `status = 0`.

---

## 4. WP2 — category 73 keywords only

From `05_CATEGORY_AND_GLOBAL_PATCH.md` §4, table `ocp5_category_description`, `language_id = 4`:

- category 73 `meta_keyword` → `брелоки Pokémon, фігурки Pokémon, 3D-друк Pokémon, декор Pokémon, Pokémon 3D-друк Україна`

**Everything else in that file is already done on production and must not be re-applied.** Verified against the 2026-08-24 backup and the 2026-08-28 export:

| `05` says | Live reality | Action |
|---|---|---|
| §1 rename attribute 44 to `Може трапитись у Mystery Box` | already named that | verify, report, no write |
| §2 create attribute `Типовий строк виготовлення…` | attribute 43 exists | verify, report, no write |
| §4 rename category 73 to `Фігурки та декор Pokémon` | already named that | verify, report, no write |
| §5 rename category 74 to `Фігурки та декор One Piece` | already named that | verify, report, no write |

A patch that issues the category-74 rename will get `affected_rows = 0` and, if it asserts `=== 1`, will fail on a change that was never needed. Assert on the **final state**, not on the row count, for anything in this table.

Category 74 stays `status = 0` — its text still promises four One Piece keychains that do not exist. Category 73 also stays `status = 0` in this wave.

---

## 5. WP3 / WP4 / WP5 — three creations

Common rules for all three: `status = 0`, `quantity = 0`, `stock_status_id = 8`, `image = ''`, `tax_class_id = 0`, `manufacturer_id = 17` (`Booster Shop`) for 3D products. Verify `stock_status_id` against product 126 rather than trusting this line. Before inserting a `seo_url` row, confirm the keyword is free across the whole table and abort on collision.

### 5.1 WP3 — `BR-CHARM-200`

Payload: `03_NEW_SKU_BR-CHARM-200_FULL.md` (its body and FAQ are byte-identical to the `BR-CHARM-200` section of `02`; use file `03`, it is the only complete create payload).

- slug `brelok-kliker-charmander-pokemon-3d-druk` — confirmed absent from `ocp5_seo_url`
- categories 59 + 73
- price `1.0000` placeholder
- attributes per the payload, including attribute 43 = `1–2 робочих дні` and attribute 44 = `Так`
- template row for `manufacturer_id`, `shipping`, `tax_class_id`, weight, dimensions, `sort_order`: product 126 `BR-CHARM-100`

### 5.2 WP4 — `PKM-JP-SVEL-SET`

Payload: the `PKM-JP-SVEL-SET` section of `01_FINAL_NON3D_10.md` — name, body, FAQ, attributes, Meta.

Owner-decided 2026-08-28, none of it inferable from the payload:

- **SEO URL:** `Pokemon-Starter-Set-Terastal-Loudbone-ex`
- **Dimensions:** 220 × 160 × 60 mm — **Weight:** 400 g
- **Price:** `650.0000` (CRM RRP, not a placeholder)
- **Status:** `0`
- categories 59 + 64 (`05` §6)
- `manufacturer_id`: take from a sibling sealed Pokémon product, e.g. 146 `PKM-JP-STES-BBX`

Do **not** copy weight or dimensions from a sibling product. The values above are the owner's measurement of this box. Match `weight_class_id` and `length_class_id` to whatever the siblings use (grams / millimetres) and convert if those classes differ.

### 5.3 WP5 — seven kit cards

Payload: `handoffs/handoff_3D-P-CARDCONTENT_kit-cards-7sku_20260828.md`, sections 4.1–4.7. Names, bodies, FAQ, attributes, Meta and SEO URLs are final; §0 of that file lists what was corrected and why.

| SKU | slug |
|---|---|
| `FIG-JIGGL-300` | `figurka-konstruktor-jigglypuff-pokemon-3d-druk` |
| `FIG-MEW-300` | `figurka-konstruktor-mew-pokemon-3d-druk` |
| `FIG-UMBRE-300` | `figurka-konstruktor-umbreon-pokemon-3d-druk` |
| `FIG-GENG-300` | `figurka-konstruktor-gengar-pokemon-3d-druk` |
| `FIG-MAGIK-300` | `figurka-konstruktor-magikarp-pokemon-3d-druk` |
| `FIG-PIKA-300` | `figurka-konstruktor-pikachu-pokemon-3d-druk` |
| `FIG-SQUIR-300` | `figurka-konstruktor-squirtle-pokemon-3d-druk` |

- categories 59 + 73, price `1.0000` placeholder
- 13 attributes each, all names already exist in `ocp5_attribute_description`. **Do not create `Складання` or `Формат`** — neither exists and neither is wanted.
- weight and dimensions per card from the payload; these are kit-card (flat) figures, not the assembled model
- template row for `shipping`, `tax_class_id`, `weight_class_id`, `length_class_id`, `sort_order`: product 126

---

## 6. WP6 — CRM RRP reconciliation

The first package in this wave that writes a commercial field on pages buyers can see. Treat it accordingly.

### 6.1 What the patch is given and what it computes

Baked into the payload: the SKU → RRP map from `Booster Shop CRM — облік товарів - РРЦ.csv` (95 rows, column `РРЦ, грн`, values are whole hryvnia, written as `NNNN.0000`), plus the alias map and the exception list below.

Computed at run time, never taken from this file: current `price`, current `status`, and current active specials. §7 is the expected result from a production read on 2026-08-28 — it exists so the owner can compare, not for the patch to trust.

### 6.2 Scope

Only products with `status = 1`. Hidden products are out of scope by owner decision, including the whole content wave and its placeholder prices.

Match on `ocp5_product.model`. When there is no direct match, fall back to this alias map:

| Site model | CRM SKU |
|---|---|
| `PKM-KR-HWA-BST` | `PKM-KR-HWAK-BST` |
| `YGO-JP-BODE-BST` | `YGO-JP-BDOM-BST` |
| `PKM-MEGA-BOX` | `PKM-JP-MSYM-BBX` |

The fallback order means the patch works whether or not the owner renames those three in admin first.

Never create a product, never touch a hidden one, never write a CRM SKU that has no card.

### 6.3 Exception — do not touch

`PKM-JP-OUTL-BST` (73). Site base 90, CRM 80, active special 80. This is a permanent promotion whose CRM RRP already reflects the discounted price, so the two numbers are not meant to match. Skip it and print the reason. Do not "fix" this in a future wave either.

### 6.4 Specials

Specials are not touched, with four named exceptions where the new base price would land at or below an active special — which would make the page sell above its own listed price.

Disable exactly these four before updating the base, by setting `date_end` on the named `product_discount_id` to the day before the run. Do not delete the row; the original `date_end` goes into `restore.sql`.

| id | SKU | `product_discount_id` | special | base after |
|---|---|---:|---:|---:|
| 91 | `MTG-JP-AFRS-BST` | 1116 | 270.00 | 250.00 |
| 103 | `PKM-EN-PORD-BBN` | 1153 | 1700.00 | 1600.00 |
| 105 | `PKM-EN-CHRS-BBN` | 1152 | 1700.00 | 1600.00 |
| 106 | `PKM-EN-CHRS-BST` | 1123 | 300.00 | 300.00 |

Assert the current special price matches the table before disabling; abort that SKU and report if it does not.

**Generic guard.** For any other product, if the new base price ≤ an active special price at run time, **skip that product and report it**. Do not disable a special that is not in the table above. Prices move; this guard is what stops a stale snapshot from creating an inverted price.

### 6.5 What not to write

`ocp5_product.status`, `quantity`, `stock_status_id`, `image`, `date_modified`, `ocp5_product_description` in any column, any `product_discount` row not in §6.4, any product outside the reconciliation set.

---

## 7. WP6 expected result — production read 2026-08-28

66 visible products: 41 already match CRM, 20 base-price updates, 4 special-disable + update, 1 exception, 0 unmatched.

### A. Base price update (20)

| id | SKU | from | to | Δ | active special |
|---|---|---:|---:|---:|---|
| 80 | `OP-JP-OP15-BBX` | 4800 | 5400 | +600 | — |
| 56 | `PKM-MEGA-BOX` | 4400 | 4700 | +300 | — |
| 94 | `PKM-JP-ABYE-BBX` | 5200 | 4900 | −300 | — |
| 107 | `OP-JP-OP16-BBX` | 5000 | 4700 | −300 | — |
| 63 | `PKM-JP-SPIN-BBX` | 4600 | 4700 | +100 | — |
| 102 | `YGO-JP-WPP5-BBX` | 1200 | 1100 | −100 | — |
| 109 | `PKM-JP-MBRV-BBX` | 4800 | 4900 | +100 | 4500, stays |
| 115 | `PKM-JP-MDEX-BBX` | 4800 | 4900 | +100 | — |
| 104 | `PKM-EN-PORD-BST` | 330 | 300 | −30 | — |
| 108 | `OP-JP-OP16-BST` | 200 | 220 | +20 | — |
| 50 | `PKM-JP-MSYM-BST` | 160 | 170 | +10 | 150, stays |
| 57 | `PKM-JP-MBRV-BST` | 170 | 180 | +10 | 160, stays |
| 59 | `PKM-JP-MZERO-BST` | 150 | 160 | +10 | 140, stays |
| 68 | `OP-JP-OP10-BST` | 180 | 190 | +10 | 170, stays |
| 72 | `OP-JP-OP14-BST` | 170 | 180 | +10 | 160, stays |
| 74 | `PKM-JP-SPIN-BST` | 180 | 190 | +10 | 165, stays |
| 79 | `PKM-JP-MDEX-BST` | 470 | 460 | −10 | 450, stays |
| 82 | `PKM-JP-WFLR-BST` | 330 | 320 | −10 | — |
| 87 | `PKM-JP-INFX-BST` | 210 | 220 | +10 | 180, stays |
| 93 | `PKM-JP-ABYE-BST` | 200 | 210 | +10 | 190, stays |

### B. Disable special, then update base (4)

Per §6.4.

### C. Exception (1)

`PKM-JP-OUTL-BST` (73).

### D. CRM SKUs with no visible card — do not create, do not update

`ACC-3D-DITTO-410`, `OP-JP-EB03-BBX`, `OP-JP-MBX-XL`, `OP-JP-MIX-MBX`, `OP-JP-OP07-BBX`, `OP-JP-OP08-BBX`, `OP-JP-OP10-BBX`, `OP-JP-OP11-BBX`, `OP-JP-OP12-BBX`, `OP-JP-OP14-BBX`, `OP-JP-PRB01-BBX`, `OP-JP-PRB01-BST`, `PKM-JP-MIX-MBX`, `PKM-JP-SCEX-BST`, `PKM-JP-SHTR-BST`, `YGO-JP-BDOM-BBX`.

The rest of the CRM sheet that has no visible card belongs to products this wave creates or that are deliberately hidden: `ACC-007-400`, `BR-BULB-100`, `BR-CHARM-100`, `BR-MEW-100`, `BR-PIKA-100`, `FIG-LUFFY-410`, `FIG-LUFFY-500`, `PKM-JP-INFX-BBX`, `PKM-JP-STES-BBX`, `PKM-JP-STES-BST`, `PKM-JP-SVEL-SET`, `PKM-JP-TGTR-BBX`, `PKM-JP-TGTR-BST`. WP6 ignores all of them.

---

## 8. Acceptance criteria

Printed by each patch in its own run output.

### WP1
- [ ] exactly 28 rows written in `ocp5_product_description` (`language_id = 4`), ids 125–152, nothing else
- [ ] every written `description`, after `html_entity_decode`, contains exactly one `<section class="bs-faq-accordion"` and zero `class="bs-faq"` / `<div class="bs-faq-accordion"`
- [ ] **52 FAQ items** across the 28 cards, and per card: 110 = 3, 120 = 2, 130 = 2, 200 = 2, 700 = 2, `FIG-LUFFY-500` = 2, `ACC-007-400` = 4
- [ ] 104 button/panel ids across those 52 items, every id unique within its own row, every `aria-controls` / `aria-labelledby` pair resolving both ways
- [ ] `PKM-JP-INFX-BBX` decoded body contains `не гарантуються виробником` exactly once
- [ ] stored form is entity-encoded: raw `<h2>` in zero rows, `&lt;h2&gt;` in all 28
- [ ] every `href` in written content starts `/product/`; zero bare-slug links
- [ ] `meta_description` changed on exactly 11 rows (133, 143 and all of 144–152); `meta_title` and `meta_keyword` on 144–152 only
- [ ] `name` unchanged on all 28 — assert before/after equality and fail if it differs
- [ ] `status`, `price`, `quantity`, `stock_status_id`, `image` unchanged on all 28 — assert before/after
- [ ] attribute 43 present with `1–2 робочих дні` on 19 products; attribute 44 = `Ні` on 125–129 and untouched elsewhere
- [ ] run output states that attributes 43 and 44 were found pre-existing and neither was created or renamed

### WP2
- [ ] category 73 `meta_keyword` equals the target string after the run
- [ ] category 73 and 74 `name` unchanged; both `status = 0` after the run
- [ ] output reports attribute 44, attribute 43 and both category names as already-correct, with no write issued

### WP3 / WP4 / WP5
- [ ] each product exists with the specified model, `status = 0`, `quantity = 0`, correct categories, correct price
- [ ] one `seo_url` row per product with the specified keyword, unique across the table
- [ ] descriptions stored entity-encoded, FAQ contract satisfied, ids unique within the row
- [ ] WP5: 7 products, 23 FAQ items, 13 attributes each; zero new attribute definitions created
- [ ] WP4: weight 400 g and dimensions 220 × 160 × 60 mm as given, not copied from any sibling

### WP6
- [ ] set computed at run time from live data, not from §7
- [ ] 4 specials disabled by `date_end`, matching the ids and prices in §6.4; no other `product_discount` row written
- [ ] base price written only where live price ≠ CRM RRP; every write logged as `SKU old → new`
- [ ] `PKM-JP-OUTL-BST` skipped with its reason printed
- [ ] zero writes to hidden products; zero product creations
- [ ] any product where new base ≤ active special and which is not in §6.4 is skipped and listed
- [ ] `status`, `quantity`, `stock_status_id`, `image`, `date_modified` and all description columns unchanged — assert before/after

### All six
- [ ] row-exists pre-check, backup to `_patch_backups/<patch>-<ts>/` **before** any write, including a `restore.sql` with the pre-change rows
- [ ] `php -l` gate, `already_applied=yes` on a repeat run, self-delete after success
- [ ] **no PHP 8.1+ syntax.** Production is PHP 8.0. `never`, `readonly`, `enum`, first-class callables and pure intersection types all fail to parse before a single guard runs. Verify against an 8.0 parser, not a local 8.4.
- [ ] `--dry-run` supported and side-effect free

---

## 9. Known trap — the live snapshot's structural columns are wrong

`live-snapshots/20260828_cards-export/` is a read-only snapshot of production text taken 2026-08-28 08:47. Names, slugs, statuses, categories, Meta and attributes are correct and can be trusted.

Its structural fields cannot: `scripts/bs-cards-export.php` parses the description without decoding it first, so `h2`, `h3`, `strong`, `ul`, `a` are 0 on all 94 products, `faq_html` is empty, `faq_items` is 0, and the `NO_HEADING` / `NO_EMPHASIS` / `NO_FAQ` flags fire on every card. Those are artefacts of the parser, not the state of the site. `body_text` also still contains literal tags and includes the FAQ.

Do not use that snapshot to judge whether a card has a heading, emphasis or a FAQ. The script needs one fix — decode before parsing — before the next export.

---

## 10. QA — owner runs after each patch

Twenty-five of the 28 WP1 products and every newly created product are `status = 0`, so most of this is admin-level. Three products are live and get a real storefront check.

1. Storefront, **`YGO-JP-QCAC-BBX`, `YGO-JP-BETB-BBX`, `YGO-JP-BETB-BST`** — the page renders formatted text, not visible `<h2>` tags; the FAQ block appears; **click one question — the accordion opens and closes.** This is the first opportunity in the whole wave to verify the accordion actually works.
2. Admin → Catalog → Products → `BR-MEW-100`, `ACC-3D-PKM-710`, `ACC-007-400` → Description tab shows formatted text and a FAQ block; `ACC-007-400` has four FAQ items.
3. `ACC-3D-PKM-710` → Attribute tab shows `Сумісність`, `Місткість дисплея`, `Внутрішнє зберігання`, `Типовий строк виготовлення при відсутності на складі`.
4. `BR-CHARM-100` → Description → the clicker link reads `/product/brelok-kliker-charmander-pokemon-3d-druk`.
5. Admin → Categories → 73 shows the new keywords; 73 and 74 both still disabled.
6. `BR-CHARM-200`, `PKM-JP-SVEL-SET` and the seven `FIG-*-300` exist, are disabled, sit in the right categories, and each has its SEO keyword.
7. `PKM-JP-SVEL-SET` → Data tab shows 400 g and 220 × 160 × 60.
8. After WP6: open `MTG-JP-AFRS-BST`, `PKM-EN-PORD-BBN`, `PKM-EN-CHRS-BBN`, `PKM-EN-CHRS-BST` on the storefront — each shows a single price, no crossed-out value, and nothing sells above its listed price. Then spot-check `OP-JP-OP15-BBX` at 5400 and `PKM-JP-ABYE-BBX` at 4900.
9. After WP6: `PKM-JP-OUTL-BST` still shows 90 crossed out and 80 as the price.

`bs-deploy-verify` covers the general post-deploy sweep. No checkout, payment, fiscalization, schema or feed code is touched, so `bs-checkout-smoke` and `bs-merchant-schema-qa` are not in play — but WP6 changes what buyers pay on 24 pages, so run the storefront checks the same day.

---

## 11. Risks

- **DB is a risky zone and every write lands on production.** The owner has approved these DB changes; each patch carries rollback SQL in its header and writes pre-change rows to `_patch_backups/`.
- **Entity encoding.** Raw HTML in `description` breaks 28 cards at once, three of them publicly visible.
- **PHP 8.0.** The three superseded runners from 2026-08-27 all used `never` and would have died before their first guard. This is the second time this host has caught that; treat the 8.0 check as mandatory, not advisory.
- **WP6 touches live prices.** A wrong map sells goods below cost or above the listed price. The generic special guard and the run-time recomputation exist for that reason.
- **Stale-instruction risk.** `05_CATEGORY_AND_GLOBAL_PATCH.md` describes four changes that are already done. Anything in this wave that asserts on row counts rather than final state can fail on a no-op.
- **Parallel writers.** One executor authors all six files. Claude (chat) reviews with `bs-patch-review` and does not author.

## 12. Rollback

Per patch: `_patch_backups/<patch>-<ts>/restore.sql` restores the affected rows verbatim. For the creation packages, rollback is the DELETE set for the generated `product_id` across `product`, `product_description`, `product_to_category`, `product_to_store`, `product_attribute` and `seo_url`, with the id filled in by the run. For WP6, restore is the original `price` per product plus the original `date_end` on the four `product_discount` rows.

WP1 and WP6 are the only packages whose rollback is time-sensitive: 3 visible cards and 24 visible prices. The rest is invisible to buyers, so restore at leisure.

Rollback material from the 2026-08-22 patches stays in `_patch_backups/` on the server and must not be deleted.

## 13. Delivery and status

Executor: patch files into `patches/`, one diagnostic per package into `diagnostics/`. **Never commit, push or deploy.** Delivery is the file plus the run command; the owner uploads to `~/public_html` and runs `php <patch>.php`, starting with `--dry-run`.

Claude (chat) reviews every file with `bs-patch-review` before the owner runs anything.

Notion: `CONTENT-QUALITY` is still a working label with no Roadmap ID. The row and its `ROADMAP_TASKS` mirror must be created before any status write — Claude's action, not the executor's. Proposed status after a clean run and owner QA: `In progress`. The wave does not close until the six remaining `BR-` cards, the photos, and the enable gates are resolved.

### Superseded by this file

- `handoffs/handoff_CONTENT-QUALITY_patch-30-cards_20260827.md`
- `patches/CONTENT-QUALITY_cards-update-28_20260827.php`
- `patches/CONTENT-QUALITY_categories-73-74_20260827.php`
- `patches/CONTENT-QUALITY_create-br-charm-200_20260827.php`
- `handoffs/addendum_CONTENT-QUALITY_faq-recovery_20260827.md` (replaced by the ready-copy v2)

Review of the three superseded runners, and what was wrong with them, is in `diagnostics/CONTENT-QUALITY_patch-review_20260827.md`. Reuse their engineering — payload hashing, `--dry-run`, transaction, before/after assertions — and fix what that review names.
