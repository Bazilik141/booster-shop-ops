# CODEX HANDOFF — R-13.1: Stock-aware sorting on category & search pages

**Roadmap ID:** R-13.1  
**Priority:** High  
**Task type:** Backend  
**Scope:** category pages, subcategory pages, search results  
**Files to modify:** 2 (model files only)

---

## Context

Category and search pages currently sort products purely by user-selected criteria (name, price, sort_order, etc.). Products that are out of stock appear mixed with in-stock products. The goal is to prepend a stock-availability priority layer to every sort so that:

1. **В наявності** (in stock) — always first
2. **Передзамовлення** (pre-order) — second
3. **Закінчились** (out of stock, no pre-order) — last

Within each group the user's selected sort order is preserved.

**Stock status IDs in DB (ocp5_stock_status):**
- `5` = Закінчився
- `7` = В наявності
- `8` = Передзамовлення

**Availability logic:**
- In stock: `p.quantity > 0` OR `p.subtract = 0` (stock not tracked → always orderable)
- Pre-order: `p.subtract = 1` AND `p.quantity <= 0` AND `p.stock_status_id = 8`
- Out of stock: everything else

---

## Files to modify

### FILE 1: `catalog/model/catalog/product.php`

**Function:** `getProducts()` — the ORDER BY block near end of function.

**Find this exact block** (appears once in the function, after `GROUP BY p.product_id`):

```php
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
			} elseif ($data['sort'] == 'p.price') {
				$sql .= " ORDER BY (CASE WHEN `special` IS NOT NULL THEN `special` WHEN `discount` IS NOT NULL THEN `discount` ELSE `p`.`price` END)";
			} else {
				$sql .= " ORDER BY " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY `p`.`sort_order`";
		}
```

**Replace with:**

```php
		$stock_priority = "(CASE WHEN (`p`.`quantity` > 0 OR `p`.`subtract` = 0) THEN 1 WHEN (`p`.`subtract` = 1 AND `p`.`quantity` <= 0 AND `p`.`stock_status_id` = 8) THEN 2 ELSE 3 END)";

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY " . $stock_priority . " ASC, LCASE(" . $data['sort'] . ")";
			} elseif ($data['sort'] == 'p.price') {
				$sql .= " ORDER BY " . $stock_priority . " ASC, (CASE WHEN `special` IS NOT NULL THEN `special` WHEN `discount` IS NOT NULL THEN `discount` ELSE `p`.`price` END)";
			} else {
				$sql .= " ORDER BY " . $stock_priority . " ASC, " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY " . $stock_priority . " ASC, `p`.`sort_order`";
		}
```

The direction suffix block that follows (`DESC, LCASE(pd.name) DESC` / `ASC, LCASE(pd.name) ASC`) — **do not touch**, it applies correctly to the secondary sort field.

---

### FILE 2: `catalog/model/smart_filter/smart_filter.php`

**Function:** `getProducts()` — same ORDER BY block (SmartFilter has its own model invoked when filters are active on category pages).

**Find this exact block:**

```php
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
			} elseif ($data['sort'] == 'p.price') {
				$sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
			} else {
				$sql .= " ORDER BY " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY p.sort_order";
		}
```

**Replace with:**

```php
		$stock_priority = "(CASE WHEN (p.quantity > 0 OR p.subtract = 0) THEN 1 WHEN (p.subtract = 1 AND p.quantity <= 0 AND p.stock_status_id = 8) THEN 2 ELSE 3 END)";

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY " . $stock_priority . " ASC, LCASE(" . $data['sort'] . ")";
			} elseif ($data['sort'] == 'p.price') {
				$sql .= " ORDER BY " . $stock_priority . " ASC, (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
			} else {
				$sql .= " ORDER BY " . $stock_priority . " ASC, " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY " . $stock_priority . " ASC, p.sort_order";
		}
```

Note: smart_filter.php uses unquoted identifiers (no backticks) — keep that style.

---

## Acceptance criteria

1. On any category/subcategory page: all in-stock products appear before pre-order products, which appear before out-of-stock products.
2. Within each availability group the user's selected sort (name, price, sort_order, date_added) is respected with correct ASC/DESC direction.
3. Search results page shows the same availability-first ordering.
4. SmartFilter-filtered category pages (when filter chips are active) also respect the ordering.
5. No PHP syntax errors in either file.
6. Product model cache auto-invalidates (hash changes with new SQL — no manual action needed).

---

## Risks

- **Cache:** OpenCart caches `getProducts()` results by `md5($sql)`. The new SQL string produces a new hash, so existing cached results become unreachable and new ones are built on first load. No manual cache flush needed, but first load after deploy may be slightly slower.
- **`subtract = 0` edge case:** Products with stock tracking disabled always land in group 1 (in stock). This is correct behaviour — they're always orderable.
- **smart_filter scope:** smart_filter.php `getProducts()` is used only on category pages with active filters. Search page does NOT use smart_filter model.

---

## Backup before patching

Create a timestamped backup of both files before applying:

```bash
cp catalog/model/catalog/product.php catalog/model/catalog/product.php.before-r13.1-$(date +%Y%m%d-%H%M%S)
cp catalog/model/smart_filter/smart_filter.php catalog/model/smart_filter/smart_filter.php.before-r13.1-$(date +%Y%m%d-%H%M%S)
```

---

## QA checklist (owner verifies manually)

- [ ] Category page (e.g. /pokemon/) — in-stock products come first with default sort
- [ ] Category page — switch sort to "Ціна: від дешевих" — in-stock still first, cheapest-in-stock before cheapest-preorder
- [ ] Category page — switch sort to "Назва: А-Я" — in-stock still first
- [ ] Category page with SmartFilter chips active — ordering correct
- [ ] Search results page — in-stock first
- [ ] Product with `subtract=0` appears in group 1 (in stock)
- [ ] Pre-order product (stock_status_id=8, quantity≤0) appears in group 2
- [ ] Out-of-stock product (stock_status_id=5, quantity≤0) appears in group 3
- [ ] No 500 errors, no blank pages
- [ ] Admin: save any product → no errors
