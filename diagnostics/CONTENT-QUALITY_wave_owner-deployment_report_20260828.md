# CONTENT-QUALITY wave — owner-run production deployment report

Date: 2026-08-28  
Author: Codex  
For: Claude review / task-status decision

## Status

All six work packages were executed by the owner in production from
`~/public_html`. Every final run printed `done=ok`. This report records
owner-supplied terminal output; Codex did not access the host or independently
inspect the live storefront.

The owner subsequently reported that the result appeared OK. Itemised visual
QA is still owner-reported, not independently evidenced here.

No commit, push, Notion change, or cache clear was performed by Codex.

## Final production evidence

| WP | Runner | Final result | Production backup |
|---|---|---|---|
| WP1 | `CONTENT-QUALITY_cards-update-28_20260828.php` | `updated_descriptions=28`, `faq_items=52`, `attribute_43_44=verified_preexisting`, `done=ok` | `/home2/boosters/public_html/_patch_backups/CONTENT-QUALITY_cards-update-28_20260828-20260828-120823` |
| WP2 | `CONTENT-QUALITY_category-73-keywords_20260828.php` | `updated_category=73`, `attribute_43_44=verified_preexisting`, `done=ok` | `/home2/boosters/public_html/_patch_backups/CONTENT-QUALITY_category-73-keywords_20260828-20260828-120928` |
| WP3 | `CONTENT-QUALITY_create-br-charm-200_20260828.php` | Owner reported product creation successful earlier in the session. | Exact final output / backup path was not retained in this chat. |
| WP4 | `CONTENT-QUALITY_create-svel-set_20260828.php` | `created_sku=PKM-JP-SVEL-SET`, `created_product_id=155`, `configured_price=650.0000`, `done=ok` | `/home2/boosters/public_html/_patch_backups/CONTENT-QUALITY_create-svel-set_20260828-20260828-121203` |
| WP5 | `3D-P-CARDCONTENT_create-kit-cards-7_20260828.php` | `created_skus=7`, `faq_items=23`, `attributes_per_product=13`, `done=ok` | `/home2/boosters/public_html/_patch_backups/3D-P-CARDCONTENT_create-kit-cards-7_20260828-20260828-121423` |
| WP6 | `CRM-RRP_site-price-reconcile_20260828.php` | `price_updates=24`, `specials_disabled=4`, `skip=PKM-JP-OUTL-BST:permanent_promotion`, `done=ok` | `/home2/boosters/public_html/_patch_backups/CRM-RRP_site-price-reconcile_20260828-20260828-121627` |

All final runners printed the host warning `MYSQL_OPT_RECONNECT is deprecated`.
It did not stop any dry-run or production run and is unrelated to this wave's
payloads.

## Preflight and deployment sequence

The owner ran each runner from `~/public_html`; successful runners self-deleted
after `done=ok` by design.

1. WP3 was created first, satisfying the only ordering dependency before WP1.
2. WP1 dry-run passed: 28 descriptions, 52 FAQ items, and all seven targeted
   per-SKU FAQ counts.
3. WP2 dry-run passed: category 73.
4. WP4 dry-run passed: `create_sku=PKM-JP-SVEL-SET`.
5. WP5 dry-run passed: 7 SKUs, 23 FAQ items, 13 attributes per product.
6. WP6 dry-run passed: 66 visible products, 24 planned price updates, 4
   planned special disables, and the permanent Outlet exception.

WP6's final live plan matched the approved list: the four disabled
`product_discount_id` values were 1116, 1153, 1152, and 1123. The previous
round-2 review's text expecting 20 `planned_price_updates` is superseded by
the deployed runner's final count of 24: it counts the 20 ordinary updates plus
the 4 named-special products whose base price also changes.

## Two stopped attempts and their resolution

### 1. Attribute-name preflight failures

- WP3 first dry-run stopped because `Матеріал` matched two live definitions.
  A read-only preflight identified IDs 29 and 51; WP3 and WP5 were regenerated
  to resolve canonical 3D `Матеріал` exclusively as ID 51. WP3 then completed.
- WP1 first dry-run stopped on nonexistent `Місткість дисплея`. A read-only
  preflight proved that both capacity names and `Внутрішнє зберігання` were
  absent, while `Сумісність` existed as ID 55. Round 3 removed those four
  writes, retained only product 143 compatibility, and kept the facts in the
  description text.
- WP4 was regenerated in round 3 to use eight confirmed existing sealed-card
  attributes (IDs 12, 13, 14, 17, 20, 21, 24, 49), not thirteen invented names.
  Its successful run created product 155 and verified exactly eight rows.

No attribute definition was created, renamed, merged, or deleted.

### 2. WP1 post-write assertion failure

The first non-dry WP1 attempt stopped at
`storage_encoding_invalid:ACC-007-400`. The runner had already begun a MySQL
transaction and its catch path rolled it back before `COMMIT`, so it did not
leave the 28 writes applied.

Cause: the assertion incorrectly required encoded `<h2>` in every description;
`ACC-007-400` intentionally begins with `<h3>`. The regenerated runner instead
requires every stored description to equal the exact entity-encoded payload.
The corrected run is the WP1 final evidence above.

## Scope actually written

- WP1: 28 `language_id = 4` product descriptions; permitted attributes 43 on
  products 125–143, 44 on products 125–129, and attribute 55 on product 143
  (`PSA, BGS, SGC, слаби на магніті`). Product 142 compatibility was untouched.
- WP2: only category 73 `meta_keyword`.
- WP3: one disabled `BR-CHARM-200` product.
- WP4: one disabled `PKM-JP-SVEL-SET` product, price 650, product ID 155.
- WP5: seven disabled kit-card products.
- WP6: 24 buyer-visible base-price updates, four named discount end dates, and
  no change to the permanent Outlet promotion.

## Remaining QA / closure gate

For a fully itemised final QA record, the owner should confirm:

- one live Yu-Gi-Oh content page renders no raw HTML and its FAQ opens/closes;
- `ACC-007-400` has four FAQ items; product 143 compatibility is the value
  above; the removed capacity fields are not expected attributes;
- `BR-CHARM-200`, `PKM-JP-SVEL-SET`, and all seven kit cards are disabled with
  quantity 0 and correct categories; SVEL has 400 g, 220 x 160 x 60 mm, and
  eight attributes;
- the four WP6 promotion pages show a single current price; `OP-JP-OP15-BBX`
  is 5400; `PKM-JP-ABYE-BBX` is 4900; `PKM-JP-OUTL-BST` remains 90 crossed out
  / 80 current.

Until those individual observations are recorded, treat production execution
as complete and storefront/admin QA as owner-reported only. Preserve all
`_patch_backups` paths above; WP1 and WP6 rollback material is time-sensitive.
