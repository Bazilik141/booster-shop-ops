# CONTENT-QUALITY wave — patch review, round 2

Date: 2026-08-28 | Author: Claude (chat)
Reviewed: `CONTENT-QUALITY_cards-update-28_20260828.php` and `CRM-RRP_site-price-reconcile_20260828.php` after the round-2 fixes
Basis: `handoffs/fixlist_CONTENT-QUALITY_wave-round2_20260828.md`
Round-1 review, which still stands for WP2–WP5: `diagnostics/CONTENT-QUALITY_wave-patch-review_20260828.md`

**Deploy OK — all six work packages.**

---

## 1. WP1

Both items done. The header now describes the rollback this patch can actually perform — restore of the 28 `language_id = 4` description rows and the captured `product_attribute` rows — with no mention of a product creation. `insert_product()` is gone; `SVEL` appears nowhere in the file, payload included.

Payload hash is byte-identical to round 1 (`33f729c7…`), and re-decoding confirms nothing else moved: 28 cards, **52 FAQ items**, distribution **11 × 1, 11 × 2, 5 × 3, 1 × 4**. Every assertion is still in place — `faq_total_invalid`, `inferno_sentence_invalid`, `name_changed`, `storage_encoding_invalid`, `commercial_field_changed` — along with `lint_self`, the transaction, `restore.sql`, `already_applied` and the self-delete.

## 2. WP6

Payload unchanged (`02c534e7…`): 95 RRP entries, the three aliases, the `PKM-JP-OUTL-BST` exception and the four named specials with their ids and prices.

All four fixes verified in the code:

**Guard now skips the product.** Line 29–31 sets a `$guard` flag inside the inner loop and `continue`s the product loop after it, so a triggered guard no longer falls through to `$updates[]`. The skip is logged once per product, not once per matching row.

**Two filters, correctly separated.** Line 20 fetches every currently active discount row using the storefront's own window — `(date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())`. The named disable then matches by `product_discount_id` across all of them regardless of `quantity` or `special` (line 24), so the three bulk-tier targets at quantity 5 and 2 are still found. The generic guard considers only `special === 1 && quantity === 1` (line 30), which is exactly what `catalog/model/catalog/product.php` requires for a row to be the price shown on the page. This was the trap in the fix list and it was avoided.

**Already-ended named row no longer aborts.** Line 25 logs `special_already_disabled` and falls through, so the product still gets its price update.

**Cleanups.** `same_price()` compares numerically with a 0.005 epsilon and is used at every comparison point including the post-write verification; the 24-update ceiling fails before any write (line 36) and applies in dry-run too; `$beforeDiscounts` is gone.

Untouched and still correct: scope limited to `status = 1`, the alias fallback applied only after a direct miss, `PKM-JP-OUTL-BST` hard-skipped, backup and `restore.sql` written before the transaction, `restore.sql` restoring both the original `price` and the original `date_end`, and the post-write assertion that `status`, `quantity`, `stock_status_id`, `image` and `date_modified` are unchanged.

`php -l` passes on both files. No PHP 8.1+ syntax in either.

### 2.1 Non-blocking

| ID | What | Note |
|---|---|---|
| N1 | skip label format is inconsistent: `special_already_disabled:<SKU>` versus `<SKU>:<reason>` everywhere else | cosmetic; matters only if the output is ever parsed |
| N2 | for a named SKU the generic guard is skipped entirely (`if`/`else`). If such a SKU ever also carried a separate active quantity-1 special, the base price could be written below it | latent only: on 2026-08-28 products 91, 103, 105 and 106 each have exactly one active discount row. Worth closing if this patch is ever generalised into a recurring sync |
| N3 | the ceiling of 24 equals the expected count exactly — 20 price updates plus 4 disables | this is my specification, not a defect. If the run aborts with `price_update_limit_exceeded:25`, reality has drifted from the 2026-08-28 snapshot: regenerate the payload, do not raise the limit |

---

## 3. Owner decision recorded

All four named rows are disabled knowingly. Three of them — `MTG-JP-AFRS-BST` (quantity 5), `PKM-EN-PORD-BBN` and `PKM-EN-CHRS-BBN` (quantity 2) — are bulk tiers that never appeared as a page price; the owner confirmed on 2026-08-28 that they are switched off anyway. `PKM-EN-CHRS-BST` (quantity 1) is the one displayed special in the set.

---

## 4. Run order and expected output

`--dry-run` first on every patch, then the real run. `WP3 → WP1 → WP2 → WP4 → WP5 → WP6`; only the WP3-before-WP1 pair is a real dependency.

| Patch | dry-run should print | real run should print |
|---|---|---|
| WP3 | `create_sku=BR-CHARM-200` | `created_sku`, `created_product_id` |
| WP1 | `existing_descriptions=28`, `faq_items=52`, seven `faq_<SKU>` lines: 110 = 3, 120 = 2, 130 = 2, 200 = 2, 700 = 2, `FIG-LUFFY-500` = 2, `ACC-007-400` = 4 | `updated_descriptions=28`, `faq_items=52`, `attribute_43_44=verified_preexisting` |
| WP2 | `dry_run=ok`, `category=73` | `updated_category=73`, `attribute_43_44=verified_preexisting` |
| WP4 | `create_sku=PKM-JP-SVEL-SET` | `created_product_id`, `configured_price=650.0000` |
| WP5 | `create_skus=7`, `faq_items=23`, `attributes_per_product=13` | same three, plus `created_skus=7` |
| WP6 | `visible_products=66`, `planned_price_updates=20`, `planned_special_disables=4`, a `plan` line per product, `skip=PKM-JP-OUTL-BST:permanent_promotion` | `price_updates=20`, `specials_disabled=4` |

If WP6's dry-run shows anything other than 20 and 4, stop and send me the output — the CRM snapshot or a live price has moved since this morning.

**C7 self-delete:** each patch removes itself after a successful run. A repeat needs the file re-uploaded, and on that second run each reports `already_applied=yes` instead of writing twice.

## 5. Rollback

`_patch_backups/<patch>-<timestamp>/restore.sql` inside `~/public_html`, written before the first write of each patch. Time-sensitive only for WP1 (three visible cards: 145, 151, 152) and WP6 (24 visible prices). Rollback material from the 2026-08-22 patches stays on the server.

## 6. Smoke after

`bs-deploy-verify`. No checkout, payment, fiscalization, schema or feed code is touched. The storefront checks that matter the same day: the FAQ accordion opening on `YGO-JP-QCAC-BBX`, `YGO-JP-BETB-BBX`, `YGO-JP-BETB-BST`, and the prices on the four SKUs whose promotions come off.
