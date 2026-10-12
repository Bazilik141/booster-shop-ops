# Handoff — RD-25 WP2-FIX: product gallery frame follows the main photo

Date: 2026-10-12
Executor: Codex · model=Sol · effort=high — authored WP2/WP3 this round (same-executor rule).
Owner decision on executor: pending.

## 1. Task ID
RD-25 (In progress). Patch `RD-25-WP2-FIX_gallery-frame-ratio_<date>.php`. One runner.

## 2. Context
WP2 + WP3 deployed 2026-10-12; owner QA: cart, mini-cart and checkout OK. Product page defect (desktop and mobile, owner screenshots): the main frame is always 4:5 and photos are bottom-aligned, so square photos (albums 360 white/black, Mystery Boxes, most 3D shots) sit at the bottom with an empty band above — "photos slid down". Near-4:5 photos (Pokeball box) and sealed packs look right. In the catalog this is acceptable because tiles align in a grid; on the product page there is no visual floor, so the band reads as broken.

## 3. Goal / owner decision (2026-10-12)
"Рамка під фото": the main frame takes the aspect ratio of the **primary** gallery image, clamped between 4:5 (tallest) and 1:1 (widest). Photos are centred; no empty band for square or 4:5 photos. Sealed packs/cut-outs keep today's look.

## 4. What to change
- Main frame ratio = primary item's served fit `main_width / main_height`, clamped to [0.8, 1.0]. Compute once (controller or Twig) and expose it as a CSS custom property on the gallery (e.g. `--bs-gallery-ratio`), replacing the fixed `aspect-ratio: 4 / 5` on `.bs-product__main-img`. Keep `max-width: 480px` desktop, full width mobile. The frame does **not** change when another thumbnail is selected (no layout jump); other images are contained in the same frame.
- Product page only: `photo` and `legacy` images → `object-fit: contain; object-position: center center`. `cut` keeps the WP2 inset grid and bottom alignment. Thumbnails: same rule (photo centred, cut bottom) in their existing 64×80 / 56×70 slots.
- Desktop rail stays bound to the main frame height (it now follows the clamped ratio).
- Fix review note N1 in the same runner: for **additional** images, when the 600×750 fit is refused, the main fallback must be the item's popup URL with popup dimensions (`config_image_popup_*`), not the 100×100 additional thumb.
- Catalog tiles, cart, mini-cart, checkout: no change.

## 5. Do not touch
Listing `.bs-pcard__media*` rules; WP1 model/library/thumb; WP3 files and rules; legacy `popup`/`thumb`/`images` fields and JSON-LD; lightbox list/order and its 700×700 URLs; GA4 anchors (re-prove counts); add-to-cart form, options, sticky ATC; RD-26 rules; sitemap, robots, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema.

## 6. Likely files (verify against a fresh export)
`catalog/controller/product/product.php` (after-WP2 `74b93752…`), `catalog/view/template/product/product.twig` (after-WP2 `abfa0d62…`), `catalog/view/stylesheet/boostershop-ds.css` (after-WP3 `213d4b48…`), `catalog/view/template/common/header.twig` (token read by prefix, replaced wholesale). Ask the owner for a fresh export of these four plus the GA4 catalog model and verify hashes before writing; any mismatch → stop.

## 7. Acceptance criteria
- Album 360 white/black (1:1 photos): frame is square, photo fills it, no band, at 1440 / 1024 / 390 / 360.
- Pokeball box (≈ 4:5 photo): unchanged look.
- Sealed booster (OP-15 #78), booster box (#80): frame 4:5, pack bottom-aligned as today.
- Mystery Box (#77, square): square frame, no band. Album 400 yellow (1.5:1): square frame, photo centred.
- Six-image product (#201): switching thumbs never changes frame height; rail scroll intact.
- Cold fit refusal on an additional image shows the popup-sized image, not a blurred thumb.
- JSON-LD and popup URLs byte-identical; GA4 anchor counts identical; no horizontal scroll at 360; no JS errors.
- Before/after contact sheet for the cases above in the report.

## 8. QA (owner)
Ctrl+F5 on: Album 360 white and black, Pokeball box, an OP booster, a booster box, a Mystery Box, Abyss Eye (6 photos) — desktop and phone. Click thumbs, open lightbox. Add to cart from the product page once.

## 9. Rollback
Re-upload the same runner, `--rollback=_patch_backups/<printed directory>`, refresh compiled Twig cache, Ctrl+F5. Returns to the WP2+WP3 state; WP3 rollback is not needed.

## 10. Status after execution
RD-25 stays In progress until owner QA of this fix; then closure (Notion + dashboard + DECISIONS: product page frame rule).
