# RD-25 WP2+WP3 — implementation report

Date: 2026-10-12
Executor: Codex. Authority: owner `go WP2-WP3`; Phase 0 review C1–C3 accepted with mandatory conditions.

## 1. Delivery and scope

Ready for independent Claude review. Production apply, host PHP 8.0.30 runtime and owner QA are pending. No commit, push, deploy, Notion or roadmap status update was performed.

| Runner | SHA256 | Targets |
|---|---|---|
| `patches/RD-25-WP2_product-gallery_20261012.php` | `2d0dbf976f6f54d854e6e6b3f327b54ee68c417b0dce3b37e71f20aa9b5cf607` | 4 |
| `patches/RD-25-WP3_small-thumbs_20261012.php` | `b5ad8231828d1442614aa900c81be97815d19c9d01576d8551774418866af776` | 7 |

Implementation follows accepted Phase 0 §9. WP2 adds a separate `bs_gallery` view array, including the primary photo, while retaining legacy `popup`, `thumb`, additional-only `images`, existing resize expressions and API order. Main is 4:5 (600×750 fit, maximum 480px); rail is left at ≥992px, 64×80 with 10px thumb gap, and a horizontal 56×70 row below on smaller screens. No rail for one valid image. Anchors switch the main src/dimensions/kind and literal popup attribute; only the main opens the original lightbox list at the selected index. Whole product remains visible; cut inset is 6%/4%/88%/96%, photo is full-slot contain/bottom. A CSS grid expresses the inset and bounds rail scrolling without new absolute positioning.

WP3 adds separate fit fields to the existing row payload, preserving legacy `thumb` and resize calls. Mini/cart slots are 52×65, generated fit 104×130, cut inset 8%/6%/84%/94%, photo full-slot contain/bottom. The mini-cart keeps direct `row > a > img`; its kind is on the article so the GA4 literal image-link anchor remains intact. Cart AJAX and controls are unchanged. Checkout summary remains its existing 48×48 contain DOM-copy consumer of mini-cart src; `checkout-reskin.js` is untouched. Live-search product admin dimensions remain 64×64; vendor files and settings are untouched. No extra getProduct/getImages calls or DB writes.

Root causes and history: fresh CSS :341–375 square/grey gallery, RD-10 max-height layers :4949–4964, :5124–5125, :5312–5318, RD-12 canonical mini-cart cover rule :7236, and RD-11 inline cart cover rules :20–22. Those original rules were edited/retired at source. Prior mini-cart writers found in `RD-12_minicart-toast_20260921.php` and `RD-12-UI-FIX_toast-canonical-rebuild_20260922.php`; repeated gallery source layers retain retirement comments. No override stack, new !important, absolute/fixed positioning or timer is added. Pixel geometry is documented beside the edited rules.

## 2. Source gates and composition

Input archive SHA256 `6c7723bb5b2ae34af2e605b3a62dee9f68e5b2e9e8beae3a5ff2a6e2af8d36f0`. All 400 extracted regular-member bytes remain identical, including all source images. Original 399 export hashes and six-file WP1 gate are retained from Phase 0. Fresh GA4 archive `0b79154a6f65ba7101f8f5d7881c4e98066376bbfc03982bdf4c8195c603dc70` contains two model files; only catalog model `extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php` is relevant/read/hash-pinned. Its SHA256 is `eea58d1e247a16c2cc4bc1e61b8a5028e41dc7ed557e9c652d74382f7984b4b4`. The admin model is not a target.

WP1 model/library/Thumb behavior, metadata v4, fit-v2, source trim/classification, 1.25s admission budget, LOCK_NB, listing media and RD-26 rules are unchanged. Read-only context gates include the catalog GA4 model. WP3 additionally requires the WP2 product controller/Twig hashes and the after-WP2 CSS hash. Header hashes below normalize only the validated asset token; it is read by path prefix and replaced wholesale. No fixed prior token anchor or suffix append.

| Stage | Target | Before SHA256 | After SHA256 |
|---|---|---|---|
| WP2 | `catalog/controller/product/product.php` | `4d9ee4e7fcb9042a46817801a6dfb973c471823751c87010cf70e0b07d7e345d` | `74b937525c579a56f67fb62d23cf75f0456e0bc5f034479e600de38e843bb84a` |
| WP2 | `catalog/view/template/product/product.twig` | `0f4f38030e4bf1e9d42260f2b26c6793907277f3fae5a70a18003a25ce63069e` | `abfa0d62cc7542a34301f9ed8936203ea497a92a6e4deba52e785c1775ee3aec` |
| WP2 | `catalog/view/stylesheet/boostershop-ds.css` | `c140e25de36e40b89830972cbc270b35cdda017cac0cd64118df935312eaf0f9` | `265f69431271fb921d6529c4909d5eee40b8566c4e70374f4132e90bde212a44` |
| WP2 | `catalog/view/template/common/header.twig` | `eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee` | `eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee` |
| WP3 | `catalog/controller/common/cart.php` | `0f42ca6f73bb776c1fd1ea6f904d06e875a613ccc29c58a09bae2f9f048f78c8` | `91f8be5d269bc86adcc76a3a58d76b93a9c74698429ad9cbdb2bc24e7000ab8e` |
| WP3 | `catalog/controller/checkout/cart.php` | `ec527cb02545378297df1b637a0c5647fef6e4e5b8f1255ae1aa75a793b06f10` | `d4b240b0987fac525a2e51bb59b0500a5779594afe3bcfde95ae634aeb515753` |
| WP3 | `catalog/view/template/common/cart.twig` | `511214cad1d69a218ae47b4d1c0226620f97336ff0d7a6af4e258b1019dcf0dc` | `0f2cd5891b519b9f516335cb957713cd0295ecf06fe17ff2eccbe8be2794a2fc` |
| WP3 | `catalog/view/template/checkout/cart_list.twig` | `cfe6d57209ca0938e1044679b8e66379bd1e46a47528d2a120618a49e4d950b7` | `a99e4108d7819640410ddce3522c874da73907d270a72e3bf29c210e49b09eb2` |
| WP3 | `catalog/view/stylesheet/boostershop-ds.css` | `265f69431271fb921d6529c4909d5eee40b8566c4e70374f4132e90bde212a44` | `213d4b48394644d04a464f497d68a0478af7d220142aa68fd3ce52eeafb9caef` |
| WP3 | `catalog/view/template/checkout/cart.twig` | `847d9356bb49c3c2b471bd1119f7621d5c9c2a641264570f2b5890945886eb48` | `633bb4f7e0a0a62e82bd6561343d2b819bde41171868284ad3f4bfa0dc4d9252` |
| WP3 | `catalog/view/template/common/header.twig` | `eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee` | `eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee` |

Independent backup directories `_patch_backups/RD-25-WP2_product-gallery_20261012-<UTC>-<nonce>/` and `_patch_backups/RD-25-WP3_small-thumbs_20261012-<UTC>-<nonce>/` contain exact originals, candidate files and SHA256 receipts. All file/hash/anchor/context checks precede target writes. PHP candidate and written-file lint, atomic write/readback and restoration protect failures. Mixed markers, missing/drifted targets, malformed tokens and modified GA4 model fail closed. WP2 repeat after WP3 verifies the complete later state and preserves the WP3 token. Each runner self-deletes after successful apply/repeat/rollback; dry-run and failures retain it.

## 3. C1 — GA4 anchors and commerce preservation

The catalog model returns ordered search/replace arrays for the literal injector. Native PHP invoked its relevant methods for both supported VERSION branches; each of the 16 replacement entries per branch has an identical occurrence count at all three states (18 distinct template/search pairs across branches; `</head>` deliberately occurs twice as separate injector entries). The common/cart view is checked against the model's dropdown-cart `replaceCatalogViewCheckoutCartInfoBefore` replacement list. Cart list anchors are also checked on the AJAX-returned template.

Counts of zero are existing missing stock anchors (body, wishlist, old table/dropdown/buttons). They remain zero; this task does not repair older GA4 integration gaps. This is source/injector preservation, not proof of current GA4 event delivery or registered event settings. The exported model is not changed. Both runners also validate the union of anchor counts against current templates and their candidates before writing.

### Model VERSION branch 4.0.2.3

| Template | Replacement index | Literal anchor | Before / WP2 / WP3 |
|---|---|---|---|
| `common/header.twig` | 0 | `<body>` | 0 / 0 / 0 |
| `common/header.twig` | 1 | `</head>` | 1 / 1 / 1 |
| `common/header.twig` | 2 | `</head>` | 1 / 1 / 1 |
| `product/product.twig` | 0 | `<button type="submit" id="button-cart"` | 2 / 2 / 2 |
| `product/product.twig` | 1 | `<button type="submit" formaction="{{ add_to_wishlist }}"` | 0 / 0 / 0 |
| `product/product.twig` | 2 | `if (json['success']) {` | 1 / 1 / 1 |
| `product/product.twig` | 3 | `{{ footer }}` | 1 / 1 / 1 |
| `common/cart.twig` | 0 | `<a href="{{ product.href }}">` | 2 / 2 / 2 |
| `common/cart.twig` | 1 | `<button type="submit" data-bs-toggle="tooltip" title="{{ button_remove }}"` | 0 / 0 / 0 |
| `common/cart.twig` | 2 | `<button type="button" data-bs-toggle="dropdown"` | 0 / 0 / 0 |
| `checkout/cart.twig` | 0 | `<div id="shopping-cart">{{ list }}</div>` | 1 / 1 / 1 |
| `checkout/cart_list.twig` | 0 | `<a href="{{ product.href }}">` | 0 / 0 / 0 |
| `checkout/cart_list.twig` | 1 | `<input type="text" name="quantity"` | 1 / 1 / 1 |
| `checkout/cart_list.twig` | 2 | `<button type="submit" formaction="{{ product_edit }}"` | 0 / 0 / 0 |
| `checkout/cart_list.twig` | 3 | `<button type="submit" formaction="{{ product_remove }}"` | 0 / 0 / 0 |
| `checkout/cart_list.twig` | 4 | `<table class="table table-bordered">` | 0 / 0 / 0 |
### Model VERSION branch 4.1.0.3

| Template | Replacement index | Literal anchor | Before / WP2 / WP3 |
|---|---|---|---|
| `common/header.twig` | 0 | `<body>` | 0 / 0 / 0 |
| `common/header.twig` | 1 | `</head>` | 1 / 1 / 1 |
| `common/header.twig` | 2 | `</head>` | 1 / 1 / 1 |
| `product/product.twig` | 0 | `<button type="submit" id="button-cart"` | 2 / 2 / 2 |
| `product/product.twig` | 1 | `<button type="submit" formaction="{{ wishlist_add }}"` | 0 / 0 / 0 |
| `product/product.twig` | 2 | `if (json['success']) {` | 1 / 1 / 1 |
| `product/product.twig` | 3 | `{{ footer }}` | 1 / 1 / 1 |
| `common/cart.twig` | 0 | `<a href="{{ product.href }}">` | 2 / 2 / 2 |
| `common/cart.twig` | 1 | `<button type="submit" data-bs-toggle="tooltip" title="{{ button_remove }}"` | 0 / 0 / 0 |
| `common/cart.twig` | 2 | `<button type="button" data-bs-toggle="dropdown"` | 0 / 0 / 0 |
| `checkout/cart.twig` | 0 | `<div id="shopping-cart">{{ list }}</div>` | 1 / 1 / 1 |
| `checkout/cart_list.twig` | 0 | `<a href="{{ product.href }}">` | 0 / 0 / 0 |
| `checkout/cart_list.twig` | 1 | `<input type="text" name="quantity"` | 1 / 1 / 1 |
| `checkout/cart_list.twig` | 2 | `<button type="submit" formaction="{{ edit }}"` | 1 / 1 / 1 |
| `checkout/cart_list.twig` | 3 | `<a href="{{ product.remove }}"` | 1 / 1 / 1 |
| `checkout/cart_list.twig` | 4 | `<table class="table table-bordered">` | 0 / 0 / 0 |

`ga4-4.0.2.3.json` and `ga4-4.1.0.3.json` retain every exact replacement and preservation result. Real model replacement output at before/WP2/WP3 has identical ordered `data-ps-track-*` attributes and complete control/form markup for all five templates. This includes product add-to-cart `product_id`/`quantity`, category injection, hidden cart key, quantity steppers/submission/edit URL, remove URL/data-key and mini-cart quantity form. Existing mini/cart inline JS is byte-identical; no AJAX handler or payload field was rewritten. Original product/product content outside the gallery markup/initialization is unchanged. Legacy resize expressions and original product lookup counts are checked in `source-preservation.json`.

## 4. C3 — local checks

| Evidence | Result |
|---|---|
| Runner composition/safety (`runner-checks.json`) | 73/73 passed |
| Native real-controller gallery image block + Twig | 8 cases × before/WP2/WP3; 0 PHP warnings |
| Full product Twig JSON-LD | 8/8 exact bytes at all three states |
| Original 700×700 popup URLs | legacy controller fields retained; selected literal href/lightbox src exact |
| UI (`ui-checks.json`) | 40 screenshots at 1440/1024/390/360; 8 interaction groups; 0 JS errors |
| Gallery | 4:5, no grey background/page overflow; left bounded scrolling rail / mobile row; single-image no rail; six-item selection, native keyboard focus, hover/active borders, previous/next/Escape |
| Cart fragments | real unchanged mini/cart JS + exported common.js/Bootstrap; qty buttons trigger one edit each; both fragment reload paths retain fit URLs/direct mini image shape; mini/cart remove work |
| Fit refusal | exact new gallery closure with simulated API refusal retains the precise legacy URL; legacy fallback intrinsic sizes come from config |

Runner checks include WP1→WP2→WP3; own repeats and WP2 repeat after WP3; dry-runs with zero target writes; missing/drifted targets/context/GA4/header and mixed-marker refusal; forced write and target-lint failure restoration; refusal of WP2 rollback while WP3 is active; exact WP3 rollback→after-WP2 and WP2 rollback→WP1; modified-backup refusal; preserving a later valid header token. PHP 8.3.30 CLI/GD and local Chromium were used. Runners use PHP 8.0-compatible syntax; host execution is still required. Fault injection modifies fixture copies only. Source images/model/library were not edited.

Visual cases: #78 sealed OP-15, #80 OP-15 box, #77 Mystery Box, #131 dark Geodude, #144 Album 400 yellow, #96 Games 7 Days, #201 six images, #73 Outlet Mix with blank extra rows. Interactive scripts are actual patched gallery and unchanged cart JS. Cart HTTP responses are mocked locally; prices/context/right product panel are synthetic, not a complete live controller or checkout session. Before contact images are the accepted Phase 0 fixtures at 1440/390; after images come from actual patched templates, not the Phase 0 drawing. Contact sheet: `RD-25-WP2-WP3_implementation_evidence_20261012/contact-sheet.html`.

Phase 0 prose correction: §4 says #73 has one valid image; its own inventory JSON actually contains two (#85 still has one). This implementation uses the real controller's existing skip rule and renders two images for #73; single-image checks use #77/#96. No inventory data or class thresholds changed.

During fixture checking, Enter on the existing cart quantity inputs submitted twice (keydown handler plus normal form submit). Those handlers are byte-identical to input source; this behavior was not changed within the image-only scope. Successful reload checks use the existing + buttons. Separately addressing Enter behavior requires its own scoped cart change.

Cold cost/limits remain the accepted Phase 0 model: worst measured product ≈2.8s and 20-largest cart ≈3.7s on first fully cold local request, then ≈0.05s. These are local image-payload/Twig measurements excluding live DB/network/header/related modules. Existing admission budget and legacy fallback are reused, not a total HTTP time cap. New sizes warm on first use. Production concurrency/latency and live GA4 events are owner QA, not claimed here.

## 5. Owner apply — only after independent review

Upload the two runners into `~/public_html`. Run WP2 first, then WP3; `&&` stops on any failed gate. Each successful runner prints `done=ok`, `self_delete=ok` and its backup. Clear only compiled Twig PHP files via config-derived cache; retain metadata/fit/image caches.

```bash
cd ~/public_html &&
php RD-25-WP2_product-gallery_20261012.php &&
php RD-25-WP3_small-thumbs_20261012.php &&
php -r 'require "config.php"; $rd25_t = realpath(DIR_CACHE . "template"); $rd25_c = realpath(DIR_CACHE); if ($rd25_t !== false) { if ($rd25_c === false || $rd25_t !== $rd25_c . "/template") { fwrite(STDERR, "Unsafe template cache path
"); exit(1); } foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rd25_t, FilesystemIterator::SKIP_DOTS)) as $rd25_f) { if ($rd25_f->isFile() && !$rd25_f->isLink() && $rd25_f->getExtension() === "php" && !unlink($rd25_f->getPathname())) { fwrite(STDERR, "Twig cache cleanup failed
"); exit(1); } } } echo "Twig cache refreshed
";'
```

Ctrl+F5 after successful cache refresh. Do not delete rd25-media-v4 JSON or image/cache/rd25-fit as part of this deploy.

## 6. C2 — mandatory owner QA

**bs-checkout-smoke steps 1–4; no order submission:**

1. Add an available product from its tile/product page. Confirm the success toast and basket quantity/total.
2. Open mini-cart: whole photo, 52×65 slot; change quantity and remove an item; thumbnails/total update and drawer controls still work.
3. Open cart page: change quantity with +/− and remove an item; both cart and mini-cart reload correctly; total/stock/minimum/preorder behavior is unchanged.
4. Proceed to checkout: entry page opens, existing summary photos remain complete in their 48×48 contain boxes, delivery/payment controls load. Stop before submitting an order.

Also inspect at 1440/1024/390/360: booster + dark 3D, single-image #96, six-image #201; rail orientation/scroll, all images complete, primary included, click/Enter switch and lightbox previous/next/Escape, visible focus, long names, no horizontal scroll. Check one category/home to confirm WP1/RD-26 framing and buttons unchanged. Revisit pages to assess warm speed; check PHP/browser logs for new errors and owner GA4 debug view if available.

## 7. Rollback

Re-upload the same corresponding runner, then supply its own backup directory name from the apply log as a relative path beginning `_patch_backups/`. Roll back **WP3 first**, clear compiled Twig cache, then WP2 and refresh cache again. Command forms:

```bash
cd ~/public_html && php RD-25-WP3_small-thumbs_20261012.php --rollback=_patch_backups/<printed-WP3-directory-name>
cd ~/public_html && php RD-25-WP2_product-gallery_20261012.php --rollback=_patch_backups/<printed-WP2-directory-name>
```

Replace the angle-bracket parts with the exact printed directory names; use the cache-refresh command from §5 after each successful rollback. These are command forms, not literal paste-ready placeholders. Receipt hash gates refuse restoring over changed/newer targets or tampered backups; stop and send the output if refused. Metadata/fit outputs can remain unused. Rollback triggers: broken/missing product/cart images, changed JSON-LD/popup URLs, gallery overflow, or a new cart/checkout failure.

## 8. Evidence and remaining gate

Evidence directory `diagnostics/RD-25-WP2-WP3_implementation_evidence_20261012/`: source preservation, every GA4 replacement/count, native payload/schema output, 73 runner checks, 40 screenshots, UI metrics/interactions, actual target diffs and candidate bytes, self-contained contact sheet, reproduction instruments, SHA256 seal. Reproduction uses retained Phase 0 work inputs and fixture-only caches; no credentials, orders or production access.

Next gate: Claude review of both runners/report, then owner deploy and QA. Production success and RD-25 closure are not claimed.
