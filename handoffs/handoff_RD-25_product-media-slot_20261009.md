# Handoff — RD-25: product media slot (proportion, framing, mobile grid)

Date: 2026-10-09 | Notion: RD-25 (`3f46bf20-bdb4-814d-91a7-c9cc06088eea`), status In progress
Executor: Claude Code · model=Sonnet · thinking=medium-high (owner decides)
Justification: needs live-file discovery across thumb/product/cart templates plus a resizer change and visual checks at four widths; no risky zone is touched if the "Do not touch" list holds.

Three work packages, **one patch file each**, in this order: WP1 → WP2 → WP3. WP2 and WP3 depend on WP1's resizer method.

## 1. Task ID
`RD-25` — patches `RD-25-WP1_media-fit-catalog_<date>.php`, `RD-25-WP2_product-gallery_<date>.php`, `RD-25-WP3_small-thumbs_<date>.php`.

## 2. Context
Measured on the live site 2026-10-09 (32 listing images, four categories):
- Every listing image is a 500×500 file. The OpenCart resizer (`system/library/image.php::resize`) always draws a canvas of exactly W×H and centres the scaled image on it, padding with transparent pixels (PNG/WebP sources) or white (JPEG). The padding is baked into the cached file.
- The slots are landscape: catalog tile `aspect-ratio: 331/240`, product page main image box 632×460 with background `#F8FAFC`.
- The products are portrait: sealed boosters ≈ 1:2 (content 0.49–0.51 of height), starter decks ≈ 0.56, tins ≈ 0.65, 3D photos 3:4. A booster therefore fills about a third of the tile width.
- Sealed photos are cut-outs (transparent background). 3D-print photos are opaque, shot on a dark background, padded to a square with white by the resizer — the photo rectangle "floats" on the white page. Mystery-box photos (Pokémon category) are opaque full-bleed squares.
- Mobile (<576 px) catalog is one column (`row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4` on `#product-list`); each card is 400 px tall at 375 px width, two products per screen.
- Product page: main image uses the 500×500 cache file, the lightbox link and the JSON-LD `image` use the 700×700 cache file, gallery thumbs use 100×100 (rendered 72×72).

Owner decisions 2026-10-09 (approved mockups: Claude canvas «Місце під фото товару — варіанти», `https://claude.ai/artifact/EC4WtKHbj4Sjwaq6p9GQB8`, private to the owner):
- Desktop catalog tile 1:1. Mobile catalog 2 columns, tile 4:5.
- Product page: main image 4:5 with a vertical thumbnail rail on the left (desktop), 4:5 full width (mobile); no grey backing.
- Small thumbnails (mini-cart, cart, search, related) 4:5.
- Framing rule everywhere: **cut-out** images are scaled to the slot height and sit on the bottom edge ("shelf"); **opaque photos** fill the whole slot (`object-fit: cover`) with rounded corners.
- Rejected by the owner: any solid fill behind the product, any blurred copy of the photo as a backdrop. Shadows, passe-partout and boxed tiles were already rejected in RD-24 round 1.

## 3. Goal
Products read at a useful size in every product image slot, with no visible empty bands, on desktop and mobile, without changing the shop's light, boxless look.

## 4. What to change

### WP1 — fit images, classification, catalog tile
- **New resize variant, existing one untouched.** Add a non-padding variant (working name `fit`) that scales the source to fit inside W×H and saves the scaled image at its own size (no canvas padding, alpha preserved), cached under a distinct file name (e.g. `-{w}x{h}-fit.<ext>`) so it never collides with existing cache files. Keep `resize()` and every existing caller byte-identical: the 700×700 popup, the JSON-LD image, og:image, emails and the Merchant feed must keep their current URLs. The live resizer differs from the 2026-09-24 backup (TECH-044 added WebP output): build on the live files, not the backup.
- **Classification `cut` / `photo`.** Proposed rule, the executor must validate it on the live catalogue: source has an alpha channel **and** its four corner pixels are transparent → `cut`; otherwise `photo`. Compute once per source file and cache the result (no image decode per page view). Report the count per class and list every product whose class looks wrong.
- **Listing tile (`product/thumb` controller + template)**: use the fit image (box 500×625 is a starting point; the executor picks the box so both slots stay sharp at 2× density), add a class on the media element per class (`bs-pcard__media--cut` / `--photo`).
- **CSS, in `boostershop-ds.css`, edited at the source rules from UI-PCARD-D** (no new override layer, no `!important`):
  - `.bs-pcard__media`: `aspect-ratio: 1 / 1` at ≥768 px; `4 / 5` below 768 px. Transparent, no background.
  - cut image: `position:absolute; left:8%; top:6%; width:84%; height:94%; object-fit:contain; object-position:center bottom`.
  - photo image: `position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:var(--bs-r)` (10 px).
  - Badges stay where UI-PCARD-BADGE put them (4 px from the top, 18 px from the side); check they still read on top of a `photo` tile.
- **Mobile grid** (<576 px): 2 columns everywhere `product/thumb` renders in a grid (category, search results, specials, manufacturer, home modules — the executor lists every template with `row-cols-1`). Gap `24px 14px`. Caption below 576 px: title 13 px / 600 / line-height 1.35, two lines, min-height 35 px; price 15 px / 800; «Купити» 44 px tall (tap target), 15 px / 700. The «Показати ще» / runner-9 fetch flow must keep appending into the same grid.

### WP2 — product page gallery
- Main image: fit image (box about 600×750), slot `aspect-ratio: 4 / 5`, `max-width: 480px` on desktop, full width on mobile; same cut/photo framing as WP1 (cut: `left:6%; top:4%; width:88%; height:96%`, bottom-aligned). Remove the `#F8FAFC` backing of `.bs-product__main-img`.
- Thumbnails: fit images (box 128×160), 64×80 slots. ≥992 px: vertical rail left of the main image, gap 10 px; active thumb `2px solid var(--bs-blue)`, others `1px solid var(--bs-line)`, radius `var(--bs-r-sm)`. Below 992 px: one horizontal row under the main image, 56×70 slots. Thumbnails are real `<a>`/`<button>` with an accessible name.
- Keep the lightbox: every link still points at the existing 700×700 popup file and magnific-popup still opens.
- The mockup's info column is illustrative only; do not change the right column.

### WP3 — small thumbnails
- Mini-cart drawer rows, cart page rows, related/"Схожі товари" (if it renders through `product/thumb`, WP1 already covers it — verify), checkout order summary only if it uses the same partial: 52×65 slot (4:5), fit image (box 104×130), cut = contain + bottom, photo = cover + radius `var(--bs-r-sm)`.
- Live search: the module (`extension/ps_live_search/**`) is a vendor file and stays untouched. Check whether its thumbnail size is an admin setting; if so, report the setting for the owner to change; if not, leave live search as is and say so.

## 5. Do not touch
- `system/library/image.php::resize()` behaviour and every existing caller of `model/tool/image::resize()`; the 700×700 popup files; JSON-LD / Product schema; og:image; Merchant feed; transactional emails.
- Checkout logic, payment (Hutko), fiscalization (Checkbox), order submit, totals, Nova Poshta; cart quantity/remove logic and the add-to-cart forms (`product_id`, `quantity`, `data-ps-track-*` GA4 attributes).
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, filter URLs and runner-9 history/fetch behaviour.
- Vendor module files (`extension/ps_live_search/**`, `extension/ps_enhanced_measurement/**`).
- Source images in `image/catalog/**` (no batch edits, no deletion of existing cache).
- Database: no writes. If classification needs persistence, use a file cache, not the DB.

## 6. Likely files / areas (the executor must verify against live files)
- `system/library/image.php`, `catalog/model/tool/image.php` (live, post TECH-044).
- `catalog/controller/product/thumb.php`, `catalog/view/template/product/thumb.twig`.
- `catalog/view/template/product/category.twig`, `search.twig`, `special.twig`, `manufacturer_info.twig`, home/module templates rendering product grids.
- `catalog/controller/product/product.php`, `catalog/view/template/product/product.twig`.
- `catalog/controller/common/cart.php`, `catalog/view/template/common/cart.twig` (mini-cart), `catalog/view/template/checkout/cart.twig` (+ list partial).
- `catalog/view/stylesheet/boostershop-ds.css` and the cache token in `catalog/view/template/common/header.twig` (patch convention 8).

Fresh live files: the newest cPanel backup (2026-09-24) predates TECH-044 and nine CSS patches. Owner runs in `~/public_html`, then drops the archive into the repo:
```
tar -czf RD-25-live-20261009.tar.gz system/library/image.php catalog/model/tool/image.php catalog/controller/product catalog/controller/common/cart.php catalog/view/template/product catalog/view/template/common/cart.twig catalog/view/template/common/header.twig catalog/view/template/checkout catalog/view/stylesheet/boostershop-ds.css
```

## 7. Acceptance criteria
- WP1: on a category page at 1440 and 1024 the media box is square; at 390 and 360 the grid has 2 columns and the media box is 4:5. A sealed booster image is at least 90 % of the slot height and touches the bottom edge. A 3D photo fills its slot edge to edge with 10 px corners. No grey or tinted backing anywhere.
- WP1: the listing `<img>` URL ends in the new fit name; the product page JSON-LD `image` and the lightbox `href` are byte-identical to before the patch (diff saved in the report).
- WP1: classification report: count of `cut` / `photo`, list of suspected misclassifications (the mystery boxes included).
- WP2: product page at 1440: thumbnail rail left of a 4:5 main image, no `#F8FAFC`; at 390: main image full width 4:5, thumbs in one row; clicking a thumb changes the main image; the lightbox still opens the 700×700 file.
- WP3: mini-cart and cart rows show 52×65 thumbs with the same framing rule; live search either changed via its admin setting (owner) or reported unchanged.
- All: tap targets ≥44×44 on mobile, no horizontal page scroll at 360, no new console errors, focus visible on thumbs and buttons. Repeat run → `already_applied=yes`; `php -l` passes; backups before writes; self-delete on success.

## 8. QA / smoke test (owner, after each WP)
1. Ctrl+F5 on a Pokémon category, a One Piece category and a 3D-print category on the phone and on the PC: tiles as in the mockup; «Купити» and «Показати ще» work; filters still apply without reload.
2. First visit after WP1 may be slower: the new image files are generated on first view. A second visit must be normal speed.
3. Product page of a booster and of a 3D figure: rail/row of thumbs, clicking switches the photo, the photo opens full size.
4. Add to cart from a tile: toast appears; open the mini-cart and the cart page: thumbnails 4:5.
No checkout smoke test needed unless WP3 touches a checkout template; if it does, run `bs-checkout-smoke` steps 1–4.

## 9. Rollback note
Each WP restores its own backed-up files from `_patch_backups/RD-25-WP<n>_…-<timestamp>/` and clears the OpenCart theme cache; the new `-fit` cache files may stay (unused after rollback) or be removed by hand. Rollback trigger: images missing or broken on any listing/product/cart surface, layout overflow at 360, or any change in JSON-LD/lightbox image URLs. Rolling back WP1 requires rolling back WP2 and WP3 first.

## 10. Recommended status after execution
Patches ready → owner deploys WP by WP → owner QA OK per WP → after WP3 QA: RD-25 Done (Claude writes Notion on the owner's word) and `design/DECISIONS.md` moves RD-25 from "approved, not live" to live.
Delivery: patch files into `patches/`; the owner uploads each to `~/public_html` and runs `php <patch>.php`. The executor never commits, pushes or deploys.
