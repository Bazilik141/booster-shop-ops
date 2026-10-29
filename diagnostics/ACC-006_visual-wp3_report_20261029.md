# ACC-006 visual step — WP3 report: product page heart

Date: 2026-10-29
Executor: Claude Code (cloud session). Handoff: `handoffs/handoff_ACC-006_favorites-visual_20261028.md` WP3; G0 §6; owner D3 and the instruction of 2026-10-29 after WP2 apply and QA.
Status: **package ready for the independent Claude chat review**. Not applied. No production access, deployment, Notion or dashboard action.

| Item | Path | SHA-256 |
|---|---|---|
| Patch | `patches/ACC-006-visual-WP3_20261029.php` | `5d4b7e3ea91e6b47ae33438ceb1820ade4e1b5bfdaa5754de2459df7d1adbae5` |
| Owner packet: bytes the owner runs (Windows checkout, CRLF per `*.ps1 eol=crlf`) | `diagnostics/ACC-006-visual-WP3_owner_20261029.ps1` | `762cb5367a15ec42b2e49cc0b421b2923b1785eb4827f030cbd7e56fdb0d7a91` |
| Same packet, LF repository blob | ditto | `2cf9e5fc5a8235817d1903959acbd09e546a5ee02bb6db19b648038eb4e36f3e` |
| Evidence | `diagnostics/ACC-006_visual-wp3_evidence_20261029/` | tests, outputs, `hash-contract.json`, per-target diffs, packet diff, buy-row geometry, screenshots |

## 1. Scope
- **`product.twig`** (presentation only):
  - **Desktop:** a square heart after the `#button-cart` if/else block inside `.bs-buy-row`, `type="button"`.
  - **Mobile:** a round heart as the sibling after the main `<a data-bs-gallery-main>` inside `.bs-product__main-img`.
  - Both carry `data-bs-wishlist-add="{{ product_id }}"`, `data-bs-wishlist-name`, `aria-pressed` and `aria-label` (WP1 toggle/sync contract). The WP1 interim text button under the form is removed.
  - **Without a gallery** (no `bs_gallery`), the row heart gets `--solo` and stays visible on mobile, so such a page still has one heart.
- **CSS** (appended block):
  - The variants switch with `display:none` at the product layout's own breakpoint, ≤767.98px (D3). Exactly one heart is visible and in the tab order.
  - States: outline `--bs-ink-2`; when added, `--bs-danger` border and fill.
  - **Desktop:** 8px radius.
  - **Mobile:** 44px round, top-right of the photo with the board's 12px inset, `--bs-sh-sm`, placed by grid overlap on `.bs-product__main-img` at ≤767.98px only. No absolute positioning.
- **`booster-wishlist.js`:** owner addition. A confirmation now closes after 4000 ms (was 5000). Login and error notices still stay until closed. The cart toast is not touched.
- **Header:** owner token-only exception for WP3. Only the `boostershop-ds.css?v=` and `booster-wishlist.js?v=` values change, to `acc006-visual-wp3-20261029`. They are located by path prefix; the normalized header is identical before and after.

### Owner decision needed: desktop heart is 44×44, not 48×48
The instruction says 48×48. The board's square is 48 because the board's own row is 48px; the live row is 44px (label, quantity and CTA). The RD-10D3 rule `.bs-product__info .bs-buy-row { align-items: stretch !important }` combines with the CTA's fixed 44px height.

- **With 48×48** (measured): the row grows to 48, the label and quantity stretch to 48, and the 44px CTA stays top-aligned. That is a visible change to the purchase row the handoff says must not change.
- **With 44×44** (packaged): the label, quantity and CTA stay exactly at their live top, height and alignment. Only the CTA narrows by the heart and the gap (345 → 291px at 1280). Evidence: `buy-row-geometry-1280.txt`.

If you want 48×48 anyway, the CTA and quantity heights in RD-10D3 must change too. That is a separate change to the purchase row; this package does not make it.

## 2. Targets: before = accepted WP2/WP1 after-states
The live product pages (2026-10-29) carry the WP1 product button and the WP2 tile hearts.

| # | Target | sha256_before | sha256_after |
|---|---|---|---|
| 0 | `catalog/view/stylesheet/boostershop-ds.css` (append only) | `8f6272a091edc61408a659f289c7119406250e29ac4e93fdd01f50b56ce976ed` | `a954513b3070cf1f2629f664660aba39bbe11a90675b11afcaaf9b97ee32539a` |
| 1 | `catalog/view/javascript/booster-wishlist.js` | `723b4899f7bbacd2a44d8506b808128d1734dae3d769262973b770c7b21cd7f2` | `1923cb2ee620e29b0455982aaedbbfa221d36ecb66ac51a038b33d250acad718` |
| 2 | `catalog/view/template/product/product.twig` | `3f957f3d07bb7ee68e40fc0cc5d5c84fe657ef05458299487d3f264af0a1a32b` | `2e1b6e6331a78263cb1f3928e0d7d2c408cd6b84dc6a3d9eed0622a9bb17b2e6` |
| 3 | `catalog/view/template/common/header.twig` | `178b19ea9c641d4800e2283be27b0c765d713fc1384b806a2368716dcb36448d` (non-token content; both tokens normalized) | `b8eddde695f237e0d083f94b3708201348f60b486d8e2975242f78c82221950e` |

## 3. Runner, admission, stdout, rollback
Same reviewed structure as WP1 and WP2. The build asserts each template substitution once: ID, CSS marker and comment lines.

- **Exact before-hashes:** the header is compared token-normalized; `ds.css` must be byte-exact and its after-state is checked by its marker block.
- **37 pinned dependencies:**
  - RD-18-WP5 pins: theme/event startup, loader, Twig, image, language, PS, reCAPTCHA and live-search;
  - fresh `product.php` / `thumb.php`;
  - accepted after-states: WP2 `thumb.twig`; WP1 `common/header.php`, `account/wishlist.php`, `wishlist_list.twig` and uk-ua `account/wishlist.php`.
- **Read-only admission:**
  - zero `oc_theme` overrides for `product/product` and `common/header`;
  - no alternate theme template;
  - relevant enabled events equal exactly 4 reviewed pairs: PS on product; PS, reCAPTCHA and live-search on header.
- **Apply order:** CSS → JS → `product.twig` → header tokens last. Backup and automatic restore; self-delete.

**Stdout (apply):**
```
admission_sha256=<64 hex>
syntax_gate=ok
backup=/home2/boosters/public_html/_patch_backups/ACC-006-visual-WP3_20261029-<YYYYMMDDTHHMMSSZ>-<12 hex>
target=<path> sha256_before=<hex> sha256_after=<hex> backup=<backup>/original-<i>   (×4, table order)
changed_files=4
self_deleted=yes
done=ok
```
The packet then prints `template_cache_cleanup=ok` and `staged_runner_absent=yes`.

Other outcomes:
- no-op: `already_applied=yes`;
- failure: `error=<code>` with exit 1, plus `automatic_restore=ok` if a write had started.

No DB write; no SQL rollback.

**Rollback:** `-Step RollbackUpload` → `-Step RollbackVerify` → `-Step Rollback`, with the exact receipt backup. It restores the 4 files: the product page gets back the WP1 text button and the header the WP2 tokens. Across packages: WP3 → WP2 → WP1.

## 4. Owner run (Windows PowerShell packet, separate steps)
Fetch branch `claude/acc-006-visual-3hr0np`, then:

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP3_owner_20261029.ps1" -Step Upload
```
```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP3_owner_20261029.ps1" -Step Verify
```
```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP3_owner_20261029.ps1" -Step Apply
```

- Receipts go to `work\acc006-visual-wp3-20261029\owner-receipts\`.
- The packet is the applied WP2 packet with mechanical substitutions only: name, SHA, receipts folder, remote file name, markers and heredoc delimiter, backup regex. `changed_files=4` is unchanged. See `evidence/packet-diff.txt`.
- ASCII, no BOM; `bash -n` passes on the remote bodies and `php -l` on the cache block.
- **Not run under Windows PowerShell 5.1:** no PowerShell in this environment. The WP1 and WP2 packets with the same structure ran on the owner's PC.

## 5. Local verification
| Check | Result | Fidelity / limit |
|---|---|---|
| Twig and anchors (`twig.txt`) | PS product anchors `[2,0,1,1]` unchanged; header `[0,1,1]`. In-stock, preorder and no-gallery renders: row heart in `.bs-buy-row` after `#button-cart` (`--solo` without a gallery); gallery heart once as the sibling after the Magnific `<a>` (absent without a gallery); the WP1 button gone. **Byte-identical:** sticky ATC bar, credit/installment block, variant selector, gallery main link, JSON-LD (including the `-BULK-` line), and the GA4 hidden input + `#button-cart`. The rest of the page is identical apart from inter-tag whitespace. The header render differs only in the two token values | real PS model replacements; Twig 3.30 |
| Browser (`browser.txt`) | 78/78 on OP-11 (in stock, 3 photos) and Black Bolt (preorder, rail) at 390/767/768/1024/1280. Exactly one heart visible and focusable: gallery at ≤767, row at ≥768. Gallery heart 44×44 at 12/12px; row heart 44×44 after the CTA, vertically centred, one line, CTA text fits, no row overflow. A tap (double click) = one `add`; both hearts pressed; visible heart red fill and border; exactly one `add_to_wishlist`; no Magnific popup; no form submit or cart POST. The confirmation is visible at 3.5 s and closed by 4.4 s. A second tap removes with no GA. On mobile, a tap on the photo still opens Magnific. The sticky ATC bar behaves as on the untouched live page after scrolling. The no-gallery `--solo` fallback is the single visible heart at 390 and 1280. No page errors | live public HTML (current WP2 state) transformed to the WP3 markup, with candidate JS/CSS; in-test wishlist server; non-boostershop hosts and other POSTs aborted and counted; assets replayed from a local cache |
| Runner (`runner.txt`) | 27/27, the same cases as WP1/WP2 on 4 targets. Drift, partial state, theme, hook and alternate-template cases aim at `product/product` (`thumb.twig` is now a dependency) | production runner bytes, with only the cache path and dependency pins substituted (asserted); `php -n` with a mysqli double |

Screenshots in `evidence/screenshots/`:
- OP-11 and Black Bolt at 390 (heart on the photo);
- the buy row at 768 and 1280 for both. In the buy-row shots the heart shows its hover/focus state from the test.

## 6. UI/CSS discipline
- **Root cause:** `product.twig`'s full-width wishlist text button under the purchase form (ACC-006 restore, renamed in WP1).
- **Override history:** none of these is edited:
  - the RD-10D3 buy-row rules (`ds.css:5693-5760`, `!important` on label, quantity and CTA);
  - the RD-25-WP2 gallery rules (`ds.css:344-372`);
  - the TECH-045 `booster-product-polish.js`.

  The new block adds only `.bs-product-heart*` rules and, at ≤767.98px, `display:grid` with a one-cell template on `.bs-product__gallery .bs-product__main-img`. That keeps the existing `> a` 100%/100% grid link.
- **Signatures:**
  - no `!important`, no `position:absolute`/`fixed` (grid overlap);
  - the one `setTimeout` is WP1's, now 4000 ms;
  - pixels: 44px touch target, 22/20px board icons, 8px radius matching `.bs-btn`, commented. The SVG path carries `fill="none"`.
- **States:** hover danger, active `--bs-line-2`, focus-visible 2px `--bs-blue`, busy .6.
- **Observation outside scope:** on preorder pages, `booster-product-polish.js` (TECH-045-WPC0) rewrites `#button-cart`'s `innerHTML` to «У кошик» for every product, so the live preorder CTA reads «У кошик», not «Передзамовити». This predates ACC-006 and is unchanged here. It may deserve its own task.

## 7. Owner QA after Apply
1. On a product page on desktop, the square heart sits right of «У кошик». The row height and alignment are as before; only the button is narrower. Check its size against your 48×48 expectation (§1).
2. On the phone, the round heart is on the photo, top-right, and there is no square in the buy row. Tap the heart: it turns red and no photo viewer opens. Tap the photo: the viewer opens.
3. Add, then remove: «Додано в обране» closes by itself after about 4 s; «Прибрано з обраного». The header counter changes accordingly.
4. Sticky buy bar on the phone, credit blocks, variant chips and the gallery thumbnails behave as before.
5. GA4 Realtime: one `add_to_wishlist` per new add, none on remove.
6. Tier 1 smoke URLs; no new PHP errors in `/home2/boosters/ocartdata/storage/logs/`.
