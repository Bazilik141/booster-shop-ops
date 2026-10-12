# RD-25 WP2-FIX — gallery frame ratio and N1 report

Date: 2026-10-12
Executor: Codex. Authority: owner gallery-frame decision and `handoff_RD-25-WP2-FIX_gallery-frame-ratio_20261012.md`.

## 1. Delivery / gate

Ready for Claude review; no commit, push, deploy or task status update. Runner `patches/RD-25-WP2-FIX_gallery-frame-ratio_20261012.php`, SHA256 `5f29571f6d096e4c30ce6ae815fcf78c24659ae57d00043ef2017e6710195129`. Only four targets; rollback of this fix alone returns the accepted WP2+WP3 state. Host PHP 8.0.30 execution and owner QA are pending.

Fresh owner archive `RD-25-WP2-FIX-check-20261012-182229-3901452.tar.gz`, SHA256 `091b6d2359593f88acfe95fabcca38862d3265f7fc080e3452027944cfdf8bb6`: **5/5 expected hashes matched** before implementation. Its four target files and GA4 catalog model remain byte-identical in the intake copy. Header expected hash normalizes only a validated token; all other target hashes are exact. References/photos/model/library come from the accepted Oct 12 Phase 0 archive, not the Sep 24 backup. 21 distinct reused photo originals for the eight cases match the original measurement SHA256s; this is local source validation, not a new live image export. No photo files were supplied/replaced in this five-file export.

| Target | Before SHA256 | After SHA256 |
|---|---|---|
| `catalog/controller/product/product.php` | `74b937525c579a56f67fb62d23cf75f0456e0bc5f034479e600de38e843bb84a` | `7d24f5b7ec992cae6d54ff59a976d71048d8bead3799382bcdebbb5079dba268` |
| `catalog/view/template/product/product.twig` | `abfa0d62cc7542a34301f9ed8936203ea497a92a6e4deba52e785c1775ee3aec` | `1c499f79bd3849ef26e60f0d598f512bc74dcae7f48bb935745dff797febe007` |
| `catalog/view/stylesheet/boostershop-ds.css` | `213d4b48394644d04a464f497d68a0478af7d220142aa68fd3ce52eeafb9caef` | `e396793de22fdfa588267755a22c13a5c9b260ad086b97af942b3efe6af6b357` |
| `catalog/view/template/common/header.twig` | `eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee` | `eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee` |

Header values are normalized SHA256s. Actual token changes `rd25-wp3-20261012` → `rd25-wp2-fix-20261012` by reading/validating its path prefix and replacing the token wholesale. Repeat preserves a later valid token.

## 2. Root cause and implementation

Fresh `boostershop-ds.css:344` fixes the main frame at 4:5; `:353` gives both main and rail images bottom alignment. On a square primary photo this leaves its spare vertical space above it. Those WP2 source rules are edited directly. Prior writers: WP2 source gallery rebuild and removal of RD-10 height layers; WP3 changed only mini-cart source rules. No override layer, !important, new positioning or timer is added.

The controller computes `max(0.8, min(1.0, primary.main_width / max(1, primary.main_height)))` after building the view-only gallery. These are the primary **served fit** dimensions. Twig exposes the numeric result as `--bs-gallery-ratio` on the gallery; main aspect-ratio consumes it once. The existing JS is byte-identical and never rewrites this property, so selecting additional images does not change the frame. Desktop max-width 480px, mobile full-width, rail sizes/scroll, lightbox list/order and cut inset grid are retained. Main and rail `photo`/`legacy` images are contain/centre; the more specific cut rule remains contain/bottom with 6%/4%/88%/96% inset.

N1: the gallery helper takes an additional-image flag. When main fit is refused, an additional item's main uses its existing popup plus `config_image_popup_width/height` (700×700 in the export), while its small fallback remains the existing 100×100 additional thumb. Successful fit still overrides URL/dimensions as before. Primary legacy fallback stays unchanged. Legacy `popup`, `thumb`, additional-only `images`, original resize/resizeFit expressions, product API order, JSON-LD and original 700×700 lightbox URLs remain intact. No new image size/cache version or classification rule is introduced.

| Product | Case | Served primary dimensions | Frame ratio | Kind |
|---|---|---|---|---|
| 190 | Альбом для колекційних карток на 360 карток, чорний | 600×600 | 1 | photo |
| 193 | Альбом для колекційних карток на 360 карток, білий | 600×600 | 1 | photo |
| 163 | Коробка для карток Покебол (Pokémon), квадратна — 3D-друк | 591×750 | 0.8 | photo |
| 78 | Бустер One Piece Card Game OP-15 (Японське видання) | 209×418 | 0.8 | cut |
| 80 | Бустер бокс One Piece TCG: Пригоди на острові богів OP-15 (Японське видання) | 453×750 | 0.8 | cut |
| 77 | Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання) | 600×600 | 1 | photo |
| 144 | Альбом для колекційних карток на 400 карток, жовтий | 600×400 | 1 | photo |
| 201 | Бустер бокс Pokémon TCG: Abyss Eye (Японське видання) - уцінка через пошкоджену плівку бокса. | 600×737 | 0.814111 | photo |

If primary fit is refused, its served legacy dimensions determine the fixed frame for that request; a later warmed request may use the fit ratio. Thumbnail selection still cannot change it. Larger/wider additional photos are contained in the fixed primary frame; intentional empty space is permitted. No source is upscaled by a new policy.

## 3. Protected scope / GA4

WP1 model/library/Thumb are read-only hash gates. Listing/RD-26 bytes before the gallery and the WP3/later CSS region are byte-identical. Cart, mini-cart, checkout templates/controllers/JS, product add-to-cart/options/sticky ATC, commerce/schema/SEO/vendor files are not targets. All existing inline product scripts are byte-identical; legacy and fit calls/product lookup counts are checked in `preservation.json`.

Native fresh GA4 catalog model methods were invoked for both VERSION branches. All 16 replacement entries per branch have equal before/after counts; zero-count stock anchors stay zero. Exact replacement output contains identical ordered `data-ps-track-*` attributes and complete form/control markup in all five relevant templates, including product_id/quantity and cart keys/edit/remove controls. Untouched cart checker references use accepted WP3 bytes. These are injector/source proofs, not production GA4 event-delivery claims. The runner pins the fresh GA4 model SHA256 and checks every distinct relevant anchor in both current templates and candidates before writing.

| VERSION branch | Template | Replacement index | Literal anchor | Before / fix |
|---|---|---|---|---|
| 4.0.2.3 | `common/header.twig` | 0 | `<body>` | 0 / 0 |
| 4.0.2.3 | `common/header.twig` | 1 | `</head>` | 1 / 1 |
| 4.0.2.3 | `common/header.twig` | 2 | `</head>` | 1 / 1 |
| 4.0.2.3 | `product/product.twig` | 0 | `<button type="submit" id="button-cart"` | 2 / 2 |
| 4.0.2.3 | `product/product.twig` | 1 | `<button type="submit" formaction="{{ add_to_wishlist }}"` | 0 / 0 |
| 4.0.2.3 | `product/product.twig` | 2 | `if (json['success']) {` | 1 / 1 |
| 4.0.2.3 | `product/product.twig` | 3 | `{{ footer }}` | 1 / 1 |
| 4.0.2.3 | `common/cart.twig` | 0 | `<a href="{{ product.href }}">` | 2 / 2 |
| 4.0.2.3 | `common/cart.twig` | 1 | `<button type="submit" data-bs-toggle="tooltip" title="{{ button_remove }}"` | 0 / 0 |
| 4.0.2.3 | `common/cart.twig` | 2 | `<button type="button" data-bs-toggle="dropdown"` | 0 / 0 |
| 4.0.2.3 | `checkout/cart.twig` | 0 | `<div id="shopping-cart">{{ list }}</div>` | 1 / 1 |
| 4.0.2.3 | `checkout/cart_list.twig` | 0 | `<a href="{{ product.href }}">` | 0 / 0 |
| 4.0.2.3 | `checkout/cart_list.twig` | 1 | `<input type="text" name="quantity"` | 1 / 1 |
| 4.0.2.3 | `checkout/cart_list.twig` | 2 | `<button type="submit" formaction="{{ product_edit }}"` | 0 / 0 |
| 4.0.2.3 | `checkout/cart_list.twig` | 3 | `<button type="submit" formaction="{{ product_remove }}"` | 0 / 0 |
| 4.0.2.3 | `checkout/cart_list.twig` | 4 | `<table class="table table-bordered">` | 0 / 0 |
| 4.1.0.3 | `common/header.twig` | 0 | `<body>` | 0 / 0 |
| 4.1.0.3 | `common/header.twig` | 1 | `</head>` | 1 / 1 |
| 4.1.0.3 | `common/header.twig` | 2 | `</head>` | 1 / 1 |
| 4.1.0.3 | `product/product.twig` | 0 | `<button type="submit" id="button-cart"` | 2 / 2 |
| 4.1.0.3 | `product/product.twig` | 1 | `<button type="submit" formaction="{{ wishlist_add }}"` | 0 / 0 |
| 4.1.0.3 | `product/product.twig` | 2 | `if (json['success']) {` | 1 / 1 |
| 4.1.0.3 | `product/product.twig` | 3 | `{{ footer }}` | 1 / 1 |
| 4.1.0.3 | `common/cart.twig` | 0 | `<a href="{{ product.href }}">` | 2 / 2 |
| 4.1.0.3 | `common/cart.twig` | 1 | `<button type="submit" data-bs-toggle="tooltip" title="{{ button_remove }}"` | 0 / 0 |
| 4.1.0.3 | `common/cart.twig` | 2 | `<button type="button" data-bs-toggle="dropdown"` | 0 / 0 |
| 4.1.0.3 | `checkout/cart.twig` | 0 | `<div id="shopping-cart">{{ list }}</div>` | 1 / 1 |
| 4.1.0.3 | `checkout/cart_list.twig` | 0 | `<a href="{{ product.href }}">` | 0 / 0 |
| 4.1.0.3 | `checkout/cart_list.twig` | 1 | `<input type="text" name="quantity"` | 1 / 1 |
| 4.1.0.3 | `checkout/cart_list.twig` | 2 | `<button type="submit" formaction="{{ edit }}"` | 1 / 1 |
| 4.1.0.3 | `checkout/cart_list.twig` | 3 | `<a href="{{ product.remove }}"` | 1 / 1 |
| 4.1.0.3 | `checkout/cart_list.twig` | 4 | `<table class="table table-bordered">` | 0 / 0 |

## 4. Local checks and visual evidence

- **32/32 runner checks**: apply on WP2+WP3, candidate byte parity, zero-write dry-run, self-delete, repeat, later valid token preservation, mixed/missing/drifted source/context/GA4/header refusal, forced write/target-lint restoration, changed-header rollback refusal, exact rollback to WP2+WP3.
- **8/8 full Twig JSON-LD blocks** are byte-identical before/after, with native original image controller blocks. Zero PHP warnings. N1 tested with all fit/metadata refused and with main refused while small fit is available; each additional main is the exact popup and 700×700, small stays correct.
- **64 screenshots / 32 before-after pairs**: eight required cases at 1440/1024/390/360, native photo bytes and actual before/patched gallery Twig. **28 interaction groups** select every thumbnail of multi-image cases by keyboard, check focus/selected src/literal popup/kind alignment, invariant frame style/height, bounded rail and lightbox/Escape; #201 also verifies its six-item list and previous/next. No horizontal page scroll or JS errors. All images load with contain; centre/bottom matches the kind. Square primary photos fill square frames without the previous band.

Contact sheet: `RD-25-WP2-FIX_gallery-frame-ratio_evidence_20261012/contact-sheet.html` (self-contained, filters by width/product). Screenshots were visually inspected for Album 360 black at 390, Album 400 yellow at 1440 and six-image Abyss Eye at 1024. Product information/price panel is synthetic; native gallery and lightbox are real. HTTP is mocked locally; no production request or full checkout proof is claimed. PHP 8.3.30 CLI/GD and local Chromium were used; runner syntax is PHP 8.0-compatible.

No new fit sizes/version means the existing WP2 cache is reused. There is no extra source scan, image API call or DB lookup introduced for the ratio. No media pre-warm/cache clear is required by this geometry fix; only compiled Twig refresh and browser stylesheet reload are needed.

## 5. Owner run — after independent review

Upload this one runner to `~/public_html`. It creates `_patch_backups/RD-25-WP2-FIX_gallery-frame-ratio_20261012-<UTC>-<nonce>/` with verified originals, candidate files and receipts before target writes. PHP self/candidate/target lint, atomic readback and failure restoration are included. Gates reject unexpected source or partial markers before writes. Successful apply/repeat/rollback self-delete; dry-run/failures retain the runner. Syntax check result: `No syntax errors detected in RD-25-WP2-FIX_gallery-frame-ratio_20261012.php`.

Run command and config-derived compiled-Twig cleanup together; keep image and rd25 metadata caches:

```bash
cd ~/public_html &&
php RD-25-WP2-FIX_gallery-frame-ratio_20261012.php &&
php -r 'require "config.php"; $rd25_t = realpath(DIR_CACHE . "template"); $rd25_c = realpath(DIR_CACHE); if ($rd25_t !== false) { if ($rd25_c === false || $rd25_t !== $rd25_c . "/template") { fwrite(STDERR, "Unsafe template cache path\n"); exit(1); } foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rd25_t, FilesystemIterator::SKIP_DOTS)) as $rd25_f) { if ($rd25_f->isFile() && !$rd25_f->isLink() && $rd25_f->getExtension() === "php" && !unlink($rd25_f->getPathname())) { fwrite(STDERR, "Twig cache cleanup failed\n"); exit(1); } } } echo "Twig cache refreshed\n";'
```

Expected: `done=ok`, `self_delete=ok`, printed backup and `Twig cache refreshed`. Stop and send the output on a failed source gate; do not bypass hashes. Ctrl+F5 after success.

## 6. Owner QA and rollback

Desktop and phone: Album 360 white/black, Pokeball box, OP-15 booster/box, Mystery Box and six-image Abyss Eye. Square photos should no longer have the empty band above; packs keep bottom alignment. Switch thumbs (frame height fixed), open lightbox and test previous/next/Escape. Add to cart from the product page once; confirm existing toast/mini-cart. Cart/checkout are unchanged by this runner. Also inspect Album 400 yellow centred in its square frame.

Rollback: re-upload the **same** runner and run `php RD-25-WP2-FIX_gallery-frame-ratio_20261012.php --rollback=_patch_backups/<exact printed directory name>` in `~/public_html`, then use the compiled-Twig cleanup command above and Ctrl+F5. Replace the bracketed directory with the actual name from the apply log. It restores only this fix's four original files to WP2+WP3; no WP3 rollback is required. Receipt hashes refuse changed targets/tampered backups. Rollback triggers: broken images, unexpected frame jump/overflow, changed popup/JSON-LD, or a new product add-to-cart failure.

## 7. Remaining gate / artifacts

Claude review, then owner deployment/QA. RD-25 remains active; no closure or status change is authorized here.

Evidence folder holds source gate, fresh GA4 model/method replacement arrays, complete native payload/schema/fallback proof, runner logs/results, all UI metrics and screenshots, exact target diffs/candidate bytes, reproduction instruments and SHA256 seal. Reproduction uses retained Phase 0 images/model and a fixture cache; scripts should be restored under `work/rd25-wp2-fix-20261012/` with those inputs. Raw hosting archives/credentials are not part of the evidence deliverable.
