# 3D-P-011 — Native OpenCart "Variant" feasibility, bounded diagnostic

**Date:** 2026-08-06
**Author:** Claude (chat) — read-only diagnostic, no patch, no write
**Scope:** Answer one question with live evidence — does the storefront already know how
to present two linked "variant" products (e.g. Онікс 21 см / 15 см) as one page with a
selector, using OpenCart 4.1's native master/variant feature visible in
`admin → Catalog → Products → (row) → Додати варіант`?

**Source:** `backup-8.5.2026_10-49-27_boosters.tar.gz` (owner-provided, newest cPanel
backup at the time of this diagnostic), extracted to a temporary local path only —
not unpacked into the repository, per `AGENTS.md`. Files pulled:

- `homedir/public_html/catalog/controller/product/product.php`
- `homedir/public_html/catalog/model/catalog/product.php`
- `homedir/public_html/catalog/view/template/product/product.twig`
- `homedir/public_html/catalog/view/template/product/thumb.twig`

## Finding 1 — backend/data-model support is live and native (confirmed)

`catalog/controller/product/product.php:416-452` contains OpenCart core's stock
master/variant logic, unmodified:

```
// Check if product is variant
if ($product_info['master_id']) {
    $master_id = (int)$product_info['master_id'];
} else {
    $master_id = (int)$this->request->get['product_id'];
}
$product_options = $this->model_catalog_product->getOptions($master_id);
foreach ($product_options as $option) {
    if ((int)$this->request->get['product_id'] && !isset($product_info['override']['variant'][$option['product_option_id']])) {
        ...
    }
}
```

`catalog/model/catalog/product.php:122` also decodes a `variant` JSON column on the
product row (`$product_data['variant'] = ... json_decode($query->row['variant'], true)`).

This confirms: the "Додати варіант" button in the admin screenshot is not cosmetic —
OpenCart 4.1 core already tracks `master_id` per product and already excludes
variant-defining options from the generic options list when rendering a variant's own
page. No core patch is needed to create the admin-side master/variant relationship.

## Finding 2 — the live storefront theme has no variant-selector UI (confirmed absence)

`grep -i variant` returns **zero matches** in both
`catalog/view/template/product/product.twig` (58,619 bytes) and
`catalog/view/template/product/thumb.twig`. No `master_id`, `swatch`, `combination`,
`switcher`, or `data-variant` string exists anywhere in either template.

The only selector UI the template renders is the generic OpenCart `options` block
(`product.twig:183-260`) — `select` / `radio` / `checkbox` / `text` / `textarea` —
which is standard price-modifier option rendering, not a variant switch. The
Add to Cart form posts a **fixed** hidden `product_id` (`product.twig:349`,
`<input type="hidden" name="product_id" value="{{ product_id }}">`) to
`checkout/cart.add`; nothing in the template changes that value, and nothing re-fetches
price/stock/images when an option value changes.

The image gallery block (`product.twig:56-67`, `{% if thumb or images %}` /
`{% for image in images %}`) renders only the current product_id's own image set — no
cross-variant image swap exists.

## Conclusion

Creating Онікс 21 см as a "variant" of Онікс 15 см (or vice versa) through the admin
button **today** would produce two separate, fully independent live product
pages/URLs with no visible link between them on the storefront — i.e. exactly the
"two separate product pages" outcome the task is trying to avoid. The admin/data-model
half of "one page, pick a characteristic" already exists natively; the storefront half
does not, in this specific customized theme.

This narrows 3D-P-011's real scope to one bounded frontend addition, not a full
custom build:

1. Controller: pass a small `product_variants` list to the template (sibling
   `master_id`/variant product_ids + href/price/stock/image), sourced from data
   already available via the existing `master_id` field.
2. Template: render a selector using that list (can reuse the existing
   `options`-style markup for consistency) that either navigates to the variant's own
   URL or updates the page via `history.pushState` + a small fetch, so canonical/URL
   stays correct per variant.
3. SEO/schema follow-up (still open, still risky-zone per `AGENTS.md`): confirm how
   Product `JSON-LD` and canonical should represent two linked variant URLs — not
   answered by this diagnostic, needs `bs-seo-risk-gate` / `bs-merchant-schema-qa`
   before any real implementation.

## Not answered by this diagnostic

- Whether any product on the live store already has a `master_id` relationship
  configured (this diagnostic did not query the live database).
- Exact JSON-LD/canonical behavior OpenCart 4.1 core produces for a variant page
  today (would require reading `catalog/controller/product/product.php`'s
  structured-data block specifically, not yet done here).
- Effort estimate in hours — still pending owner scope decision on which UX pattern
  (navigate vs. in-place swap) to build.
