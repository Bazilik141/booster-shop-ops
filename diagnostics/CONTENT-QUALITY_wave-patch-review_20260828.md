# CONTENT-QUALITY wave — patch review 2026-08-28

Date: 2026-08-28 | Task: `CONTENT-QUALITY` / `3D-P-CARDCONTENT` / `CRM-RRP` | Author: Claude (chat)
Reviewed: all six runners dated 2026-08-28 in `patches/`
Contract: `handoffs/handoff_CONTENT-QUALITY_wave_20260828.md`
Evidence: full read of all six files, decode and verification of all six payloads, `backup-8.24.2026_10-35-09_boosters` (schema, discount rows, **`catalog/model/catalog/product.php`**), and the owner's production read of 2026-08-28

**WP1, WP2, WP3, WP4, WP5 — `Deploy OK`.**
**WP6 — `Return for changes`.** Two defects in the runner, and one error of mine in the specification it was built from.

---

## 1. My error, and it comes first

The 2026-08-27 analysis that produced the four "disable this special" rows was wrong, and the owner approved those four on the strength of it.

I read `ocp5_product_discount` and treated every row with `special = 1` as the price a buyer pays. It is not. The live catalogue model — `catalog/model/catalog/product.php`, statements `discount` and `special` — selects a special only when **`quantity = '1'`**:

```sql
… WHERE `ps`.`product_id` = `p`.`product_id` AND `ps`.`customer_group_id` IN (…)
  AND `ps`.`quantity` = '1' AND `ps`.`special` = '1'
  AND ((`ps`.`date_start` = '0000-00-00' OR `ps`.`date_start` < NOW())
   AND (`ps`.`date_end`   = '0000-00-00' OR `ps`.`date_end`   > NOW()))
```

Rows with `quantity` 2, 3 or 5 are bulk tiers applied in the cart, not the displayed price.

Of the four rows queued for disabling:

| discount_id | product | SKU | quantity | displayed as a special? | verdict |
|---:|---:|---|---:|---|---|
| 1116 | 91 | `MTG-JP-AFRS-BST` | **5** | no — bulk tier | **do not disable** |
| 1153 | 103 | `PKM-EN-PORD-BBN` | **2** | no — bulk tier | **do not disable** |
| 1152 | 105 | `PKM-EN-CHRS-BBN` | **2** | no — bulk tier | **do not disable** |
| 1123 | 106 | `PKM-EN-CHRS-BST` | 1 | yes, 300.00 | genuine case |

So the claim "three products would sell above their listed price" is false. Those three never showed a special at all; their page price is the base price, and lowering it to the CRM value is simply correct. What would actually happen is that a 5-piece tier at 270 becomes worse than buying one at 250 — untidy, but not a price inversion, and not something the owner asked to change.

Only `PKM-EN-CHRS-BST` has a real displayed special, and there the new base (300) equals the special (300), so the page would show a 0 % discount. That one still needs a decision — disable it, or leave a discount that shows nothing.

The same error runs through §7 of the handoff: the "active special, stays" annotation is wrong for every row with quantity > 1 — products 57, 59, 67, 68, 69, 70, 71, 72, 78, 98. Those pages have no displayed special; the base price is what the buyer sees. It does not change what gets written to `price`, only the description of what the buyer will notice.

**None of this affects the 20 base-price updates.** They stand as specified.

### What the owner has to decide

1. `MTG-JP-AFRS-BST`, `PKM-EN-PORD-BBN`, `PKM-EN-CHRS-BBN` — leave the bulk tiers alone (recommended), or still switch them off knowing they are 5-piece and 2-piece tiers.
2. `PKM-EN-CHRS-BST` — disable the qty-1 special at 300, or keep it and accept a 0 % discount badge on that page.

Until that is answered, WP6 must not run: three of its four disables are changes nobody intended.

---

## 2. WP6 — defects in the runner

### 2.1 The generic inverted-price guard never fires · blocking

`CRM-RRP_site-price-reconcile_20260828.php`, line 10:

```php
} else {
    foreach ($specialRows as $special)
        if ((float)$crm <= (float)$special['price']) {
            $skips[] = $sku . ':inverted_special_guard';
            continue;               // ← continues the INNER foreach
        }
}
if ((string)$row['price'] !== $crm || $disable !== null) $updates[] = […];
```

`continue` belongs to the inner `foreach` over `$specialRows`, not to the product loop. After logging the skip the code falls straight through and queues the update anyway. The guard reports and then does exactly what it was meant to prevent.

The handoff asked for this guard as the safety net for anything the frozen snapshot got wrong — which, per §1, is the failure mode that actually occurred. Fix: `continue 2`, or set a flag and test it before the `$updates[]` line.

### 2.2 The special query matches the wrong rows · blocking

Same line:

```sql
SELECT product_discount_id, price, date_start, date_end
FROM ocp5_product_discount
WHERE product_id = ? AND date_start <= CURDATE()
  AND (date_end = '0000-00-00' OR date_end >= CURDATE())
```

No `special = '1'`, no `quantity = '1'`. It therefore picks up quantity discounts and bulk tiers alike. The 2026-08-24 backup has one active `special = 0` row — product 115 `PKM-JP-MDEX-BBX`, quantity 1, 4500.00 — which this query treats as a special, and 17 of the 28 active rows are quantity > 1.

The query must mirror the catalogue model exactly: `special = '1' AND quantity = '1'`, and the date test should use the model's own form (`date_start = '0000-00-00' OR date_start < NOW()`) rather than an asymmetric `<= CURDATE()`.

The named-special disable is unaffected — it matches by `product_discount_id` and re-checks the price — but it is disabling the wrong rows for the reason in §1, not because of this query.

### 2.3 Non-blocking

| ID | Where | What | Fix |
|---|---|---|---|
| N1 | WP6 line 10–11 | prices compared as strings (`(string)$row['price'] !== $crm`). Works only while both sides carry exactly four decimals. | compare numerically with a 0.005 epsilon |
| N2 | WP6 | no expected-count gate. With §2.1 broken there is no second line of defence against a bad map rewriting far more rows than intended. | assert the update count against a bound and abort above it |
| N3 | WP6 line 10 | `$beforeDiscounts` is collected and never used | remove |

---

## 3. WP1 — `Deploy OK`

Payload decoded and verified independently:

- integrity hash matches; 28 cards; ids exactly 125–152; `PKM-JP-SVEL-SET` correctly absent
- **52 FAQ items**, distribution **11 × 1, 11 × 2, 5 × 3, 1 × 4** — matches the addendum exactly
- all **9** addendum blocks embedded verbatim, including the two answers corrected on 2026-08-28 (`ACC-3D-PKM-120` keeps the 35PT conditional, `ACC-3D-PKM-130` keeps One Touch and no PT figure)
- `PKM-JP-INFX-BBX` contains `не гарантуються виробником` exactly once
- per-SKU expected counts baked in and asserted; total asserted at 52

Runner: entity encoding via `htmlspecialchars(ENT_COMPAT)` matching the live storage form; `check_html` enforces one accordion, no legacy markup, unique ids, two-way ARIA and `/product/` on every href; `name` re-read and asserted unchanged; `status`, `price`, `quantity`, `stock_status_id`, `image` re-read and asserted unchanged; attributes 43 and 44 verified pre-existing and never created; `already_applied` implemented as a real content comparison rather than a marker file; `--dry-run` exits before the backup directory is created; `php -l` self-gate, transaction, `restore.sql`, self-delete.

One cosmetic defect worth fixing before the run, because it misleads exactly when it matters most: the file header still says the rollback *"deletes the exact generated PKM-JP-SVEL-SET dependent rows and product ID"*, and `insert_product()` remains in the file, unused. SVEL-SET moved to WP4; the header describes a rollback this patch cannot perform. Convention C6 requires the header rollback to be accurate.

## 4. WP2 — `Deploy OK`

Correctly reduced to the single real change. Asserts, before writing, that category 73 is still named `Фігурки та декор Pokémon`, that 74 is still `Фігурки та декор One Piece`, and that both are `status = 0` — so the already-done renames are verified, not re-applied, and the `affected_rows = 0` trap from the 2026-08-27 version is gone. Attributes 43 and 44 verified and reported. `already_applied` triggers when the keyword already matches.

## 5. WP3 — `Deploy OK`

`BR-CHARM-200`, slug `brelok-kliker-charmander-pokemon-3d-druk` (confirmed free), categories 59 + 73, price 1.0000, `status = 0`, `quantity = 0`, `stock_status_id = 8`, template from product 126 `BR-CHARM-100`. Thirteen attributes including 43 = `1–2 робочих дні` and 44 = `Так`, with the Mystery Box value re-read and asserted after insert. Keyword collision checked before insert; rollback DELETE set appended with the real id before COMMIT.

## 6. WP4 — `Deploy OK`

`PKM-JP-SVEL-SET` with everything the owner decided: slug `Pokemon-Starter-Set-Terastal-Loudbone-ex`, price `650.0000`, `status = 0`, categories 59 + 64, and — the point that mattered — **weight and dimensions come from the payload** (400 g, 220 × 160 × 60), with only `weight_class_id` and `length_class_id` taken from sibling product 146. `physical_payload_missing` aborts if those fields are absent. The booster-box copy defect from the 2026-08-27 version is gone.

`already_applied` is unusually thorough here: it re-checks model, sku, status, quantity, price, image, all five description columns and the seo keyword before declaring the product already created.

## 7. WP5 — `Deploy OK`

Seven kit cards, 13 attributes each, 23 FAQ items, all seven slugs in the live `<тип>-<персонаж>-<франшиза>-3d-druk` form and all free. `Складання` and `Формат` are absent, so no attribute definition is created — `resolve_attribute_ids` aborts on any name it cannot find, which is the right failure. `partial_kit_batch_exists` refuses to run if some but not all seven already exist. Post-insert it re-reads every product and asserts `status = 0`, `quantity = 0`, price `1.0000`, empty image and exactly 13 attributes.

---

## 8. Conventions, all six

| | C1 anchor | C2 pre-check | C3 backup | C4 `php -l` | C5 idempotent | C6 rollback | C7 self-delete |
|---|---|---|---|---|---|---|---|
| WP1 | ✓ | ✓ | ✓ | ✓ | ✓ | header inaccurate | ✓ |
| WP2 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| WP3 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| WP4 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| WP5 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| WP6 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

`php -l` passes on all six. **No PHP 8.1+ syntax anywhere** — `never` is gone, `fail()` returns `void`. That was the blocker that killed the 2026-08-27 round.

No `DROP`, `TRUNCATE`, unbounded `DELETE`/`UPDATE`, secret, hardcoded path or leftover debug statement in any file. Every write is bounded by an explicit id list or an explicit `status = 1` filter.

---

## 9. Before running

`--dry-run` first, on every patch. Expected dry-run output:

| Patch | Expect |
|---|---|
| WP1 | `existing_descriptions=28`, `faq_items=52`, seven `faq_<SKU>` lines matching 3/2/2/2/2/2/4 |
| WP2 | `dry_run=ok`, `category=73` |
| WP3 | `create_sku=BR-CHARM-200` |
| WP4 | `create_sku=PKM-JP-SVEL-SET` |
| WP5 | `create_skus=7`, `faq_items=23`, `attributes_per_product=13` |
| WP6 | **do not run until §1 is decided** |

Real-run counts to compare against: WP1 28 descriptions, WP2 1 category row, WP3 1 product, WP4 1 product, WP5 7 products. WP6, once fixed, should report 20 price updates and whatever number of disables the owner settles on.

**C7 self-delete:** every patch removes itself after a successful run. A repeat run needs the file re-uploaded — and on a second run each will report `already_applied=yes` rather than writing twice.

Run order: **WP3 before WP1**. Everything else is order-independent.

## 10. Rollback

Each patch writes `_patch_backups/<patch>-<ts>/before.json` and `restore.sql` before its first write, inside `~/public_html`. Restoring means running that `restore.sql`. For the three creation patches the restore is the DELETE set with the generated ids, appended before COMMIT. For WP6 it is the original `price` per product plus the original `date_end` per disabled discount row.

Rollback material from the 2026-08-22 patches stays on the server and must not be deleted.

Time-sensitive: WP1 touches three visible cards (145, 151, 152), WP6 touches 24 visible prices. The rest is invisible to buyers.

## 11. Smoke after

`bs-deploy-verify` for the wave. No checkout, payment, fiscalization, schema or feed code is touched — `bs-checkout-smoke` and `bs-merchant-schema-qa` do not apply. WP6 changes what buyers pay, so run the storefront price checks the same day it lands.
