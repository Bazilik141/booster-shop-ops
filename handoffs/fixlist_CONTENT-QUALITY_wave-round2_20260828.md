# Fix list — CONTENT-QUALITY wave runners, round 2

Date: 2026-08-28 | Parent: `handoffs/handoff_CONTENT-QUALITY_wave_20260828.md`
Basis: `diagnostics/CONTENT-QUALITY_wave-patch-review_20260828.md`
Executor: same as round 1. Do not split authorship.

Two files change. **WP2, WP3, WP4, WP5 are accepted as they stand — do not touch them.**

---

## 1. `CONTENT-QUALITY_cards-update-28_20260828.php` — cosmetic, but do it

The patch itself is accepted. Two leftovers from the round-1 version must go, because the first is read exactly when something has gone wrong:

1. **File header rollback text is false.** It still says the rollback *"deletes the exact generated PKM-JP-SVEL-SET dependent rows and product ID. The runner writes that ID into restore.sql before COMMIT."* This patch creates nothing. Replace that paragraph with what the rollback actually does: restore the 28 original `language_id = 4` description rows and the targeted `product_attribute` rows from the generated `restore.sql`.
2. **Remove the unused `insert_product()` function.** SVEL-SET moved to WP4; the function is dead code in this file.

Nothing else in WP1 changes. The payload, its hash, and every assertion stay exactly as they are.

---

## 2. `CRM-RRP_site-price-reconcile_20260828.php` — two blocking fixes plus three cleanups

### 2.1 Owner decision, unchanged

All four named rows are still disabled, now knowingly:

| `product_discount_id` | product_id | SKU | quantity | price |
|---:|---:|---|---:|---:|
| 1116 | 91 | `MTG-JP-AFRS-BST` | 5 | 270.0000 |
| 1153 | 103 | `PKM-EN-PORD-BBN` | 2 | 1700.0000 |
| 1152 | 105 | `PKM-EN-CHRS-BBN` | 2 | 1700.0000 |
| 1123 | 106 | `PKM-EN-CHRS-BST` | 1 | 300.0000 |

Three of these are bulk tiers (quantity 2 and 5), not displayed specials. The owner has confirmed on 2026-08-28 that they are switched off anyway. The payload's `specials` map does not change.

### 2.2 Blocking — the inverted-price guard never fires

Current code, line 10:

```php
} else {
    foreach ($specialRows as $special)
        if ((float)$crm <= (float)$special['price']) {
            $skips[] = $sku . ':inverted_special_guard';
            continue;            // continues the INNER foreach
        }
}
if ((string)$row['price'] !== $crm || $disable !== null) $updates[] = […];
```

`continue` belongs to the inner loop, so after logging the skip the product is queued for update anyway. Fix so that a triggered guard actually skips the product — `continue 2`, or a boolean set in the loop and tested before `$updates[]`. The skip must be logged exactly once per product, not once per matching row.

### 2.3 Blocking — the special query matches the wrong rows

Current query has no `special` and no `quantity` filter, so it returns quantity discounts and bulk tiers as if they were specials.

The two uses need **different** filters. Do not apply one filter to both — that is the trap here.

**Fetch once, per product, all currently active discount rows**, using the catalogue model's own window:

```sql
SELECT product_discount_id, quantity, special, price, date_start, date_end
FROM ocp5_product_discount
WHERE product_id = ?
  AND (date_start = '0000-00-00' OR date_start < NOW())
  AND (date_end   = '0000-00-00' OR date_end   > NOW())
```

Then:

- **Named disable** — match by `product_discount_id` across *all* those rows, regardless of `quantity` or `special`. Three of the four targets are quantity 2 or 5 and would vanish under a `quantity = '1'` filter. Keep the existing price re-check before disabling.
- **Generic inverted-price guard** — consider **only** rows with `special = '1' AND quantity = '1'`. That is what `catalog/model/catalog/product.php` requires for a row to be the price shown on the page; a bulk tier is not a price the buyer sees, and must not block a base-price update.

Reference, from the live model, statement `special`:

```sql
… AND `ps`.`quantity` = '1' AND `ps`.`special` = '1'
  AND ((`ps`.`date_start` = '0000-00-00' OR `ps`.`date_start` < NOW())
   AND (`ps`.`date_end`   = '0000-00-00' OR `ps`.`date_end`   > NOW()))
```

### 2.4 Blocking — a named row that is already ended must not fail the run

`date_end = DATE_SUB(CURDATE(), INTERVAL 1 DAY)` on a row whose window has already closed writes zero rows, and the current `need(… === 1)` turns that into `special_disable_failed`. With the corrected window in §2.3 an already-ended row will not be returned at all, so handle the case explicitly: if a named `product_discount_id` is not among the active rows, treat that one as already disabled, log `special_already_disabled:<SKU>`, and continue with the product's price update instead of aborting.

### 2.5 Non-blocking

| ID | What | Fix |
|---|---|---|
| N1 | prices compared as strings — `(string)$row['price'] !== $crm` works only while both sides carry exactly four decimals | compare numerically with a 0.005 epsilon, and keep the string form only for output |
| N2 | no ceiling on how many rows may be rewritten; with §2.2 broken there was no second line of defence | abort when the number of queued price updates exceeds **24**, printing the full list first. The 2026-08-28 snapshot expects 20 |
| N3 | `$beforeDiscounts` collected and never used | remove |

### 2.6 Unchanged in WP6

Scope stays `status = 1` only. The alias map (`PKM-KR-HWA-BST`, `YGO-JP-BODE-BST`, `PKM-MEGA-BOX`) stays as a fallback applied only when the direct model match misses. `PKM-JP-OUTL-BST` stays a hard skip with its reason printed. The RRP map, its hash, backup, `restore.sql`, transaction, `--dry-run`, `already_applied` and self-delete all stay as they are.

---

## 3. Acceptance for round 2

- [ ] `php -l` passes on both files; **no PHP 8.1+ syntax** — verify against an 8.0 parser
- [ ] WP1 header describes only the rollback this patch can perform; `insert_product()` gone; payload hash unchanged; every existing assertion still present
- [ ] WP6 dry-run prints `visible_products=66`, a `plan` line per product, and `skip` lines for `PKM-JP-OUTL-BST` and for anything the guard catches
- [ ] WP6 dry-run plan contains **20 price updates and 4 disables** against the 2026-08-28 state; a different count is reported, not silently accepted
- [ ] a product whose new base price is ≤ an active **quantity-1** special is skipped and appears exactly once in `skip`, and its price is **not** written — prove it with a unit-style check or a stated manual trace
- [ ] a bulk tier (quantity > 1) does **not** trigger the guard
- [ ] a named `product_discount_id` that is already outside its window logs `special_already_disabled` and does not abort the run
- [ ] price update count above 24 aborts before any write
- [ ] `restore.sql` still restores the original `price` per product and the original `date_end` per disabled row

Deliver both files into `patches/`, a short diagnostic per file into `diagnostics/`. Do not commit, push or deploy. Claude reviews again before the owner runs anything.
