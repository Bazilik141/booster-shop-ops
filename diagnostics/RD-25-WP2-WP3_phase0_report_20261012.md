# RD-25 WP2+WP3 — joint Phase 0 report

Date: 2026-10-12
Executor: Codex, explicitly assigned by the owner. The addendum's pending executor line is superseded by this session's instruction.
Status: Phase 0 complete; await **go WP2-WP3**. No patch authored, commit, push, deployment or roadmap/status mutation in this stage.

## Scope and precedence

Owner decision 2026-10-11 combines WP2+WP3 into one stage, one Phase 0/review and one deploy session, with **two future runners and separate backups/rollback**. Read the addendum first, then base handoff §4 WP2/WP3. Reuse the accepted v4 `mediaInfo()` / fit-v2 `resizeFit()` / `cropFit()` without new classifier thresholds, metadata versions or resizer behavior. Whole product visible; empty space accepted. Diagnostics below use copied live inputs and prototype-only markup/CSS. They do not edit the extracted PHP/Twig/CSS files.

## 1. Fresh export and source gate

Archive: `RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz`, 249,334,135 bytes.
SHA256: `6c7723bb5b2ae34af2e605b3a62dee9f68e5b2e9e8beae3a5ff2a6e2af8d36f0`.
Owner export UTC: `2026-10-12T06:47:07+00:00`; collector revision 3; hosting PHP **8.0.30**; `done=ok`, no missing files, zero DB/site writes. All **399 exported file hashes and lengths match**; 400 regular archive members including the manifest, no unsafe members. Original source mtimes were retained for the real cache keys. After all diagnostics, **400/400 extracted member bytes still match** the archive.

All accepted WP1 checkpoints match exactly:

| File | Fresh SHA256 | Gate |
|---|---|---|
| `catalog/model/tool/image.php` | `85cf9489244c48fe915bc25f370cdfdb2b39c9c6d6a42219cec7f84a9e375e71` | matches |
| `system/library/image.php` | `7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9` | matches |
| `catalog/controller/product/thumb.php` | `cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1` | matches |
| `catalog/view/stylesheet/boostershop-ds.css` | `c140e25de36e40b89830972cbc270b35cdda017cac0cd64118df935312eaf0f9` | matches |
| `catalog/view/template/common/header.twig` | `bf0328cd9da4095282069584150ca7a396fc4a09426b0e90b00ab852ffaa70d2` | matches |
| `catalog/view/template/product/thumb.twig` | `d9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6` | matches |

Header token: `rd25-wp1-photo-contain-20261011`. Metadata v4, fit key v2, 1.25s cumulative request admission budget, LOCK_NB fallback remain unchanged. See `source-gate.json` and `source-integrity-after.json` in the evidence directory.

The collector initially rejected a legitimate `..png` filename and did not decode the two `&amp;` image paths. Revision 3 resolved both. Raw DB paths and explicit HTML-entity resolution evidence are retained; there is no DB filename repair.

## 2. Product gallery map and root cause

| Area | Current live source | Observed behavior |
|---|---|---|
| Payload | `catalog/controller/product/product.php:346–368` | Primary thumb `resize(...,500,500)`; additional thumbs 100×100. Primary and additional popup callers remain 700×700. `getImages()` already supplies additional image paths; no extra product lookup is needed. |
| Markup | `catalog/view/template/product/product.twig:56–76` | Main anchor uses `popup`; main img uses `thumb`. Additional anchors use `image.popup` / `image.thumb`, 72×72 intrinsic markup. No primary-image thumbnail in the current rail. |
| Base gallery CSS | `boostershop-ds.css:341–375` | Main background `var(--bs-surface,#f8fafc)`, initial square image aspect, horizontal wrapping 72×72 links. |
| Later constraints | `boostershop-ds.css:4949–4964`, `:5124–5125`, `:5312–5318` | 460px maximum height on desktop; repeated 340px constraints at mobile scopes. These create the landscape main-image area and must be edited/retired at the source in WP2, rather than overridden again. |
| Lightbox | controller `:268–269`; Twig `:970–978` | Exported jQuery 3.7.1 and Magnific Popup; `.magnific-popup` delegates **every anchor** to the image lightbox, with gallery enabled. A thumbnail click currently opens the lightbox; it does not switch the main image. No separate gallery-switch code in `booster-product-polish.js`. |
| JSON-LD dependency | Twig `:980–992` | `image` is the primary `popup` followed by every `images[].popup`. **Do not insert the new primary thumbnail into legacy `images`.** Add a separate gallery-view array instead; retain the original popup/resize callers and schema fields. |
| Order | `catalog/model/catalog/product.php:789–793` | `getImages()` orders by `sort_order ASC`. Preserve its returned order in Phase 1. The export orders tied rows by image for determinism; the contact sheet is not proof of live tie ordering. |

DB theme override: the owner collector queried the actual `DB_PREFIX . 'theme'`, using owner-held config rather than a guessed prefix. Required `route`/`code` columns exist, status **checked**, **zero matching rows** for product/product, product/thumb, common/cart and checkout routes. Active configured theme: `basic`. No DB theme patch is indicated. Evidence: `theme-and-image-settings.json`.

Prior implementation history: the source retains the RD-10 gallery height layers, RD-11 cart inline styling, and RD-12 mini-cart/checkout compatibility. Matching patch history includes `RD-11_cart-page_20260921.php`, `RD-12_minicart-toast_20260921.php`, `RD-12-UI-FIX_toast-canonical-rebuild_20260922.php`, and `RD-12_checkout-thumbnail-compat_20260922.php`. The prototype has temporary drawing overrides; Phase 1 must edit the identified source rules, without stacking overrides or adding `!important`.

## 3. Mini-cart, cart and checkout map

| Caller / renderer | Image source and size | Scope decision |
|---|---|---|
| `catalog/controller/common/cart.php:76` → `common/cart.twig:41` | Existing cart service supplies primary image. Current 120×120 resize is displayed at 56×56; DS row/image rule `:7236` uses cover. Quantity/remove reload uses the same `common/cart` fragment. | WP3 adds fit 104×130 and class/dimensions to this existing payload; edits the row/image source rule and existing Twig only. Preserve direct `.bs-mini-cart__row > a > img`. |
| `catalog/controller/checkout/cart.php:163` → `checkout/cart_list.twig:28` | `getList()` supplies 120×120 legacy thumb and renders cart_list. `checkout/cart.twig:20–22` owns row sizing: 96×96 desktop, 72×72 mobile, cover. AJAX `checkout/cart.list` reloads this fragment. | WP3 adds fit 104×130; 52×65 slot on both widths. Update cart_list markup **and the inline source CSS in cart.twig**. Preserve quantity, minimum, preorder, stock, remove and totals code. |
| Checkout summary | `checkout-reskin.js:1152–1179` finds matching alt/name in `#cart .bs-mini-cart__row > a > img`; `:1259–1262` copies **img.src only** into its own `.bs-co-item__img`. DS `:6708–6716` sets 48×48, contain. | This is DOM URL reuse, **not a shared Twig partial**. Keep checkout template/JS/48×48 rule outside WP3's conditional summary scope. It will inherit the mini-cart fit URL and keep whole-image contain; source class/inset is not propagated. Changing the summary to 52×65 would require explicit scope expansion. |
| Related products | Existing `product/thumb` listing path, already covered by WP1 | No second renderer/resizer rewrite; keep the protected Thumb files and `.bs-pcard__media*` rules unchanged. |

There are no new `getProduct()` calls needed for WP2/WP3: product primary/additional paths and cart primary paths already exist in the caller payload. No business/cart/order data was exported or used; fixture prices and basket quantities are synthetic.

**Checkout QA gate:** WP3 necessarily touches `checkout/cart.twig` and `checkout/cart_list.twig`. The handoff says any checkout template change requires owner `bs-checkout-smoke` steps 1–4, so that gate applies even though the checkout summary and order/payment logic remain outside the patch.

## 4. Live-search admin setting

Module code `module_ps_live_search`; product keys `module_ps_live_search_product_image_width` and `module_ps_live_search_product_image_height` are both **64**. The catalog module directly calls legacy resize with those values (`ps_live_search.php:147`) and returns thumb dimensions (`:166–167`). Admin fields exist; validation permits 16–128 independently (`admin/controller/module/ps_live_search.php:110–120`). Thus 52×65 can be set by the owner in the module's admin product-image size fields, if requested later. This stage changes neither setting nor vendor code, and a size setting alone does not adopt the new fit/classification API. Category/manufacturer image sizes also remain 64×64. Module enabled/disabled values were intentionally not exported, so this report makes no new status claim.

## 5. Complete product image inventory

Scope: **134 enabled store-0 products**, with primary plus additional refs. All dates are available by the export date; no blank primary image and no missing nonempty source. Export includes 169 additional rows, of which **22 are empty strings** and are skipped by the real product controller. Effective refs: **281**; **245 unique source files**, measured with the exact unchanged v4 helper: **149 cut / 96 photo**. All 245 cached metadata arrays then match `mediaInfo()` exactly; zero warnings. No classification/trim thresholds changed. The helper was called directly for the inventory so the request admission budget did not truncate a catalogue-wide diagnostic; measured request-cost scenarios use the actual public methods and budget.

| Effective gallery images | Products |
|---|---|
| 1 | 37 |
| 2 | 62 |
| 3 | 24 |
| 4 | 8 |
| 5 | 2 |
| 6 | 1 |

Full per-product source/class/reason/dimensions inventory: `measured-inventory.json`; compact product table: `product-image-inventory.csv`. Blank extra rows explain why SQL row counts misleadingly suggested 11 images for #85 and 6 for #73; both actually have one image. The existing skip behavior is preserved, with no DB cleanup in scope.

### Single-image products — no empty rail

| Product ID | Name |
|---|---|
| 66 | Promotion Pack Vol.7 One Piece Card Game (Японське видання) |
| 77 | Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання) |
| 79 | Бустер Pokémon TCG: Mega Dream EX (Японське видання) |
| 81 | Бустер Pokémon TCG: Black Bolt (Японське видання) |
| 82 | Бустер Pokémon TCG: White Flare (Японське видання) |
| 83 | Бустер Yu-Gi-Oh! OCG: Quarter Century Art Collection (Японське видання) |
| 84 | Бустер One Piece Card Game OP-08 Two Legends (Японське видання) |
| 85 | Містері бокс One Piece Card Game: Mystery Mix Standard (Японське видання) |
| 87 | Бустер Pokémon TCG: Inferno X (Японське видання) |
| 89 | Набір Pokémon S&amp;V ex Special Set — Snow Hazard &amp; Clay Burst (Японське видання) |
| 90 | Бустер Yu-Gi-Oh! OCG: World Premiere Pack 2024 (Японське видання) |
| 91 | Бустер MTG: Adventures in the Forgotten Realms (Японське видання) |
| 95 | Протектори для карток 63×89 мм, 100 шт Sleeves |
| 96 | Протектори для карт Games 7 Days 63.5×88 мм Premium+, 50 шт Sleeves |
| 104 | Бустер Pokémon TCG: Mega Evolution — Perfect Order (Англійське видання) |
| 106 | Бустер Pokémon TCG: Mega Evolution — Chaos Rising (Англійське видання) |
| 108 | Бустер One Piece Card Game OP-16: The Time of Battle (Японське видання) |
| 110 | Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання) |
| 111 | Містері бокс One Piece Card Game: Mystery Mix XL (Японське видання) |
| 125 | Брелок Mew (Pokémon) — 3D-друк |
| 127 | Брелок Squirtle (Pokémon) — 3D-друк |
| 128 | Брелок Bulbasaur (Pokémon) — 3D-друк |
| 129 | Брелок Pikachu (Pokémon) — 3D-друк |
| 133 | Фігурка-клікер Покебол (Pokémon) — 3D-друк |
| 134 | Фігурка Luffy (One Piece) — 3D-друк |
| 135 | Настільна картина Luffy (One Piece) — 3D-друк |
| 136 | Картина Luffy (One Piece) — 3D-друк |
| 140 | Підставка для слаба — 3D-друк |
| 147 | Бустер Pokémon TCG: Storm Emeralda (Японське видання) |
| 152 | Бустер Yu-Gi-Oh! OCG: BEYOND THE BRAVE (Японське видання) |
| 155 | Стартовий набір Pokémon TCG: Terastal Loudbone ex (Японське видання) |
| 166 | Закладка для книг One Piece — 3D-друк |
| 171 | Брелок Going Merry (One Piece) — 3D-друк |
| 174 | Бустер One Piece OP-17: The World's Strongest Warriors (Японське видання) |
| 178 | Бустер One Piece Card Game OP-13 (Японське видання) |
| 196 | Rare Pack: One Piece EB-02 Anime 25th collection Booster (Японське видання) |
| 204 | Стартова колода Pokemon ex Greninja ex, water (Японське видання) |

### More than five valid images — rail overflow case

| Product ID | Images | Name |
|---|---|---|
| 201 | 6 | Бустер бокс Pokémon TCG: Abyss Eye (Японське видання) - уцінка через пошкоджену плівку бокса. |

## 6. Proposed geometry and interactive contract

| Surface | Slot | Fit request | Framing |
|---|---|---|---|
| Product main | 4:5; desktop main max-width 480; full gallery column on mobile | 600×750 | contain + center bottom; cut in 88%×96% box (left 6%, top 4%) per base WP2; photo full slot with existing radius token; no grey backing |
| Gallery thumbnails | ≥992: 64×80 left rail, 10px gap, constrained to main height with vertical overflow; <992: 56×70 horizontal scroll row | 128×160 | same class framing, active 2px blue border, other 1px line border, visible keyboard focus |
| Mini-cart / cart rows | 52×65 | 104×130 | contain + center bottom; cut in 84%×94% box (8% / 6% inset); photo full slot; existing small radius |

Include the primary image in the **separate gallery-view array**, show no rail for a single image, use native focusable anchors with explicit photo labels, and update the main fit src, intrinsic dimensions, class and literal popup attribute on selection. Prevent thumbnail lightbox delegation; the main-image lightbox opens the existing primary + additional popup list at the selected index, with previous/next and Escape preserved. Keep the legacy `popup`, `thumb`, `images` fields and the original resize calls available; fit refusal falls back to their existing URLs.

Use `getAttribute`/`setAttribute` for literal popup URL transfer. Assigning normalized DOM `.href` text can change non-ASCII/escaped URL bytes even when navigation is equivalent. The Phase 0 prototype exercises the attribute-preserving approach. Low-resolution originals cannot gain detail from a larger slot; no source-image replacement or upscaling policy change is included.

## 7. Before/after contact sheet and local proof

Open [contact sheet](RD-25-WP2-WP3_phase0_evidence_20261012/contact-sheet.html). It filters by viewport/surface and links full-resolution screenshots. Static product overviews: [1440](RD-25-WP2-WP3_phase0_evidence_20261012/product-contact-1440.jpg), [390](RD-25-WP2-WP3_phase0_evidence_20261012/product-contact-390.jpg).

Cases: #78 sealed OP-15 booster; #80 booster box; #77 Mystery Box; #131 dark Geodude 3D source (classified cut by existing rules); #144 Album 400 yellow; #96 Games 7 Days 63.5×88; #201 six-image damaged-film booster box. These seven also form the illustrated mini-cart/cart basket. Product name/image bytes and relevant Twig are real; the right information column, prices, basket/controls data are illustrative. The mini-cart is isolated as an open panel, not a full screenshot of the live header/checkout.

**36 native Chromium screenshots** at 1440 and 390: seven product pages plus mini-cart and cart, each before/after. Proposed 4:5 main/thumb slots, whole-image contain + bottom, loaded generated files, no document horizontal overflow. Enter on the sixth thumbnail switches the main image; actual exported Magnific Popup opens the exact selected 700×700 URL, previous/next and Escape work at both widths. Zero JS errors. The native popup URL lists match before/after, and full product Twig JSON-LD is byte-identical in **7/7 fixtures**. See `visual-metrics.json`, `visual-manifest.json` and `schema-preservation.json`. This is a proposed-payload/layout proof; no deployed patch or full live-page QA is claimed.

## 8. Measured cold cost — full local page Twig

Native local PHP **8.3.30/GD**; host export reports **8.0.30**. Unmodified live model/library, source bytes and mtimes; new fit dimensions above. Both warm-metadata/empty-new-fit and empty-metadata/empty-fit were measured in isolated copies; legacy popup/thumb files were warmed first, representing the deployed site. Every row is a fresh PHP request, resetting the static admission accumulator. Full exported product/cart page Twig renders with synthetic non-image context; product gallery is substituted as a diagnostic payload only; persistent compiled Twig cache is off.

The timing excludes live DB queries, application/controller execution, header mini-cart, related modules, HTTP/network and browser paint. It is a local page-render component measurement, **not a full production HTTP response time or an enforced page deadline**. First restricted-sandbox runs were discarded because PHP reported the local cache unreadable/unwritable and refused all fit output; accepted runs explicitly gate cache writability and use the permitted local runtime. All accepted native runs have zero warnings.

Worst-case source selection: #201 has the most valid photos (6); #99 has the highest summed source measurement cost (3 images, 17,265,600 source pixels); #132 has the largest primary (12,102,048 pixels, two images). Cart: synthetic 20-item basket containing the largest primary images among the exported products, in descending area order; not an observed customer basket. Plan, traces, memory and output lengths are in `cold-cost.json`.

| Case | Initial cache | Request | Fit images / images | Wall seconds |
|---|---|---|---|---|
| product-201 | fit-cold | 1 | 6/6 | 1.105133 |
| product-201 | fit-cold | 2 | 6/6 | 0.059407 |
| product-201 | fit-cold | 3 | 6/6 | 0.053495 |
| product-201 | fit-cold | 4 | 6/6 | 0.054011 |
| product-201 | metadata-and-fit-cold | 1 | 6/6 | 2.332940 |
| product-201 | metadata-and-fit-cold | 2 | 6/6 | 0.054287 |
| product-201 | metadata-and-fit-cold | 3 | 6/6 | 0.051878 |
| product-201 | metadata-and-fit-cold | 4 | 6/6 | 0.051165 |
| product-99 | fit-cold | 1 | 3/3 | 1.576342 |
| product-99 | fit-cold | 2 | 3/3 | 0.044522 |
| product-99 | fit-cold | 3 | 3/3 | 0.046647 |
| product-99 | fit-cold | 4 | 3/3 | 0.045008 |
| product-99 | metadata-and-fit-cold | 1 | 2/3 | 2.803746 |
| product-99 | metadata-and-fit-cold | 2 | 3/3 | 1.243730 |
| product-99 | metadata-and-fit-cold | 3 | 3/3 | 0.044973 |
| product-99 | metadata-and-fit-cold | 4 | 3/3 | 0.044547 |
| product-132 | fit-cold | 1 | 2/2 | 1.192221 |
| product-132 | fit-cold | 2 | 2/2 | 0.044358 |
| product-132 | fit-cold | 3 | 2/2 | 0.042600 |
| product-132 | fit-cold | 4 | 2/2 | 0.046514 |
| product-132 | metadata-and-fit-cold | 1 | 2/2 | 1.287657 |
| product-132 | metadata-and-fit-cold | 2 | 2/2 | 0.045398 |
| product-132 | metadata-and-fit-cold | 3 | 2/2 | 0.043067 |
| product-132 | metadata-and-fit-cold | 4 | 2/2 | 0.045892 |
| cart-20-largest | fit-cold | 1 | 20/20 | 3.200607 |
| cart-20-largest | fit-cold | 2 | 20/20 | 0.041984 |
| cart-20-largest | fit-cold | 3 | 20/20 | 0.043695 |
| cart-20-largest | fit-cold | 4 | 20/20 | 0.039840 |
| cart-20-largest | metadata-and-fit-cold | 1 | 8/20 | 3.737627 |
| cart-20-largest | metadata-and-fit-cold | 2 | 16/20 | 2.919386 |
| cart-20-largest | metadata-and-fit-cold | 3 | 20/20 | 2.387475 |
| cart-20-largest | metadata-and-fit-cold | 4 | 20/20 | 0.040758 |

The existing 1.25s limit is a decode/measurement **admission budget**. One admitted source can overrun; fit decode/resample/encode is outside it. Thus first-time new-fit creation still takes up to about 3.74s in the measured cart case; after warming the same page Twig is about 0.04s. #99 temporarily falls back for one image; a later request fills it. Keep the exact existing budget and LOCK_NB fallback. Main + small gallery fits create two outputs per source. Source fields and metadata version do not change, so a later size warm-up targets only missing fit keys. Production latency and concurrent hosting load remain owner QA items; a CLI pre-warm is optional future tooling, not implemented here.

## 9. Two-runner implementation plan after approval

**WP2** `patches/RD-25-WP2_product-gallery_<date>.php`: product controller, product Twig, gallery source rules in `boostershop-ds.css`, and dynamic header stylesheet token. Add separate gallery data; preserve original popup/thumb/images and JSON-LD block; reuse getImages order; replace the single existing lightbox initialization rather than attaching competing handlers. Remove/update the identified old main/rail/height rules at source. Do not change product info, options, cart submission, related listings, schema, og:image, commerce or vendor code.

**WP3** `patches/RD-25-WP3_small-thumbs_<date>.php`: common/cart controller + Twig, checkout/cart controller, checkout/cart_list Twig, checkout/cart inline source CSS, scoped mini-cart DS rules and dynamic header token. Retain direct mini-cart img shape; no checkout-reskin.js/summary rewrite or vendor live-search mutation. No extra getProduct calls or DB writes.

Fresh source hashes in the gate bind the first runner. WP3 must bind the reviewed **after-WP2** CSS/header state and unchanged original hashes of its independent files; those candidate after-hashes will be calculated only during Phase 1. Read-only gates protect the accepted model/library/Thumb files and WP1/RD-26 rules. Each runner preflights all hashes/anchor counts before writing, rejects partial/mixed markers, owns its content marker, backup and rollback receipt, syntax restore gate and self-delete. Header cache tokens are read/validated and replaced wholesale, never anchored to a particular token or appended.

Required future composition checks: WP1→WP2→WP3, repeat independently without changing later tokens, failure before target writes, forced write/lint restoration, WP3 rollback returning to after-WP2, then WP2 rollback returning to WP1. Separate backup directories mean rollback order **WP3 first, WP2 second**. Review/new owner approval precedes any deployment. Phase 1 UI checks must include 1440/1024/390/360, real long names, single/six-image cases, keyboard/hover/focus/active, gallery/lightbox, fragment reloads and owner checkout smoke steps 1–4.

## 10. Intake cleanup and remaining gate

Owner explicitly requested cleanup of failed export attempts. Command saved as `diagnostics/RD-25-WP2-WP3_export-cleanup_20261012.sh`; it removes only this chain's collector names (home and optional public_html copies), temporary export directories, and older WP2-WP3 check archives. It preserves the accepted `RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz`, older WP1 archives, site images/caches and patch backups. The command is supplied for the owner, not executed against hosting by Codex. Local evidence/reproduction fixtures are retained.

All requested Phase 0 inputs are present. **Stop here and await the owner's exact `go WP2-WP3`.** No Phase 1 patch, commit, push or deploy is authorized by this report.
