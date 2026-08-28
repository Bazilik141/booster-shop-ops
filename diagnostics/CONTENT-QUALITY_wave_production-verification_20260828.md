# CONTENT-QUALITY wave — production verification and closure

Date: 2026-08-28 | Author: Claude (chat)
Basis: owner-run read-only verification from `~/public_html`, output pasted in the session
Deployment record: `diagnostics/CONTENT-QUALITY_wave_owner-deployment_report_20260828.md`

**Verdict: the wave is verified in production. All six work packages did what they were meant to do.**

The two `FAIL` lines in the first verification run were defects in my verification script, not in the deployment. Both are explained and resolved below.

---

## 1. Verified by query, not by report

| Check | Result |
|---|---|
| FAQ items across the 28 updated cards | 52 |
| Per-card FAQ counts, all 28 | match the payload exactly |
| Raw (unencoded) HTML stored in any description | 0 rows |
| `Сумісність` (attr 55) on product 143 | `PSA, BGS, SGC, слаби на магніті` |
| Attribute 43 = `1–2 робочих дні` | 19 products |
| Attribute 44 = `Ні` | products 125–129 |
| Capacity attributes on 142/143 | none created |
| Category 73 keywords / name / status | correct, name untouched, disabled |
| Category 74 | disabled |
| New products | `BR-CHARM-200` 154, `PKM-JP-SVEL-SET` 155, kit cards 156–162 |
| Each new product | `status = 0`, `quantity = 0`, correct price, categories, slug, attribute count |
| `PKM-JP-SVEL-SET` physical data | 400 g, 220 × 160 × 60 mm, 8 attributes |
| Attribute definitions in the database | still 40 — none created |
| Prices against the plan | 25 / 25 |

## 2. The two failures were mine

`WP6 four promotions ended` and `WP6 Outlet promotion untouched` both returned 0 because my script pinned `product_discount_id` values — 1116, 1152, 1153, 1123, 1140 — that no longer exist.

The live rows for those five products are now:

| discount_id | product | quantity | price | special | date_start | date_end |
|---:|---:|---:|---:|---:|---|---|
| 1182 | 73 `PKM-JP-OUTL-BST` | 1 | 80.00 | **1** | 0000-00-00 | 0000-00-00 |
| 1177 | 91 `MTG-JP-AFRS-BST` | 5 | 270.00 | 0 | 0000-00-00 | 2026-08-27 |
| 1178 | 103 `PKM-EN-PORD-BBN` | 2 | 1700.00 | 0 | 0000-00-00 | 2026-08-27 |
| 1179 | 105 `PKM-EN-CHRS-BBN` | 2 | 1700.00 | 0 | 0000-00-00 | 2026-08-27 |
| 1180 | 106 `PKM-EN-CHRS-BST` | 1 | 300.00 | 0 | 2026-08-23 | 2026-08-27 |

Read against the intent, every one of them is correct:

- the four promotions carry `date_end = 2026-08-27`, in the past, and none is an active special any more;
- base prices are 250, 1600, 1600 and 300 — so each of those four pages now shows a single current price;
- `PKM-JP-OUTL-BST` keeps an active `special = 1`, `quantity = 1` row at 80.00 with no end date, against a base of 90.00 — 90 crossed out, 80 charged, exactly the standing exception.

`product_discount` still holds 45 rows, the same total as the 2026-08-24 backup. Nothing was added or lost.

## 3. What actually changed the ids, and why it matters

`product_discount_id` is not stable. Saving a product in OpenCart's admin deletes that product's discount rows and re-inserts them, so the ids move. All five ids shifted by roughly +60 and two of them swapped order — the signature of a re-save, not of anything the patches did.

WP6 reported `specials_disabled=4`, so at its run time the ids were still the originals. The renumbering happened afterwards — most likely the product pages being opened and saved in admin during QA.

Two consequences worth carrying forward:

**WP6's rollback for the promotion half is now stale.** `_patch_backups/CRM-RRP_site-price-reconcile_20260828-20260828-121627/restore.sql` restores `date_end` by `product_discount_id`, and those ids are gone; those statements would now match zero rows. The price half of the same file is unaffected — it targets `product_id`. If the promotions ever need restoring, do it by `product_id` + `price`, not by id. The four values are in the table above.

**Never address a discount row by id in a patch that might run later.** WP6 got away with it because it ran within the hour. Had a product been saved in admin between payload generation and the run, the named disable would have logged `special_already_disabled` and moved on, quietly leaving the promotion live while still lowering the base price — which on three of those four products would have produced exactly the inverted price the whole guard exists to prevent. Match on `product_id` + `price` + `quantity` + `special` instead.

## 4. Still open, outside the patches

- `CONTENT-QUALITY` has no Roadmap ID. The Notion row and its `ROADMAP_TASKS` dashboard mirror are Claude's action on owner instruction.
- Storefront rendering on the three visible cards (145, 151, 152) — the accordion opening and closing — remains the one owner-eye check the database cannot answer.
- Photos, `config_stock_checkout = 0` and the CRM rows still gate making any of the nine new products visible.
- `scripts/bs-cards-export.php` still needs its one-line decode fix before the next export.
