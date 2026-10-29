# ACC-006 visual step — WP2 report: catalog tile heart

Date: 2026-10-29
Executor: Claude Code (cloud session). Handoff: `handoffs/handoff_ACC-006_favorites-visual_20261028.md` WP2; G0 §5; owner instruction 2026-10-29 after WP1 apply and QA.
Status: **package ready for the independent Claude chat review**. Not applied. No production access, deployment, Notion or dashboard action.

| Item | Path | SHA-256 |
|---|---|---|
| Patch | `patches/ACC-006-visual-WP2_20261029.php` | `32d4ed48ea68b70af2357b0004b6880e89718fa34da46985926934a03d0cc95c` |
| Owner packet: bytes the owner runs (Windows checkout, CRLF per `*.ps1 eol=crlf`) | `diagnostics/ACC-006-visual-WP2_owner_20261029.ps1` | `c65b7cb2c81ff7e8db9bb6b1d85e35a90ff181c9d3a1a4f7c9c7773fd78fda29` |
| Same packet, LF repository blob | ditto | `c204b5f241e20f845a86050e6109a4ce59d222f7876bb9522d4332e027da6539` |
| Evidence | `diagnostics/ACC-006_visual-wp2_evidence_20261029/` | tests, outputs, `hash-contract.json`, per-target diffs, packet diff, screenshots |

## 1. Scope and authority
- **Presentation only (handoff WP2, G0 §5):**
  - `thumb.twig` wraps the existing media link in `.bs-pcard__media-wrap`;
  - the heart is the link's sibling, bottom-right of the RD-25 slot with the 8px inset;
  - the full-width text button is removed;
  - `data-bs-wishlist-add`, `data-bs-wishlist-name`, `aria-pressed` and `aria-label` keep the WP1 toggle/sync contract.

  No controller, model, language, request or GA4 change.
- **`booster-wishlist.js`, presentation logic only:**
  - **Review note 3:** an error notice stays until closed. The `setTimeout` now skips `options.error === true`.
  - **Correction to WP1 report §8:** that section said no live listing loads tiles by script. That was wrong. `catalog/view/javascript/bs-category-results.js` (UX-003-R9) appends tiles («Показати ще») and replaces the results region (filters) with `importNode`.
    - Live WP1 effect: an inserted tile of a saved product shows the unpressed state. A tap sends `add`, the server answers `added=false` with `ids`, the state corrects itself, and no GA event fires.
    - WP2 adds a `MutationObserver` (child list, subtree) that calls the unchanged `sync()` when an inserted element carries `[data-bs-wishlist-add]`. `sync()` changes attributes and text only, so it cannot re-trigger the observer.
- **Header:** owner token-only exception for WP2/WP3 (2026-10-29). Only the `boostershop-ds.css?v=` and `booster-wishlist.js?v=` values change, to `acc006-visual-wp2-20261029`. They are located by path prefix (convention 8). No other header byte changes; the runner asserts the normalized header is identical before and after.

## 2. Targets: before = accepted WP1 after-states
Live public `ds.css` and `booster-wishlist.js` (fetched 2026-10-29) hash to the WP1 after-states, and the live header carries the WP1 tokens.

| # | Target | sha256_before | sha256_after |
|---|---|---|---|
| 0 | `catalog/view/stylesheet/boostershop-ds.css` (append only) | `6e985f45c03b9b232a9b6ab8f75bc0283ff012b632b87ee5ff07c62d0daa15c0` | `8f6272a091edc61408a659f289c7119406250e29ac4e93fdd01f50b56ce976ed` |
| 1 | `catalog/view/javascript/booster-wishlist.js` | `762e00e4a221653804198e9ab16ea50096180c1122bb30759080afa965ddd5e4` | `723b4899f7bbacd2a44d8506b808128d1734dae3d769262973b770c7b21cd7f2` |
| 2 | `catalog/view/template/product/thumb.twig` | `5cbccd9205da21f23b74aa745aca79593c932a77df02d826ecdb9e0520da9c4c` | `2a414ce2e18410b19b3c3bcdcb8fe33c805db9d8ef5714a1075eb632b8d1068d` |
| 3 | `catalog/view/template/common/header.twig` | `51b9327ed96f0c5b5f42ed87fffd0015b8795399e4f7884231ff6669ab934399` (non-token content; both tokens normalized) | `178b19ea9c641d4800e2283be27b0c765d713fc1384b806a2368716dcb36448d` |

**Listings using `thumb.twig`** (live HTML, 2026-10-29): every tile on each of these is a `bs-pcard`. No listing without `thumb.twig` was found.

| Listing | Tiles |
|---|---|
| category | 13 |
| accessories | 15 |
| search | 15 |
| special | 7 |
| manufacturer | 15 |
| home modules | 4 |
| product-page related rail | 5 |

## 3. Runner, admission, stdout, rollback
Same reviewed structure as WP1:
- the template is identical apart from ID, CSS marker and comment lines (build asserts each substitution once);
- exact before-hashes, with the header compared token-normalized;
- `ds.css` must be byte-exact, and its after-state is checked by its marker block;
- 37 pinned dependencies:
  - the RD-18-WP5 pins for theme/event startup, loader, Twig, image and language;
  - PS, reCAPTCHA and live-search;
  - fresh `thumb.php` / `product.php`;
  - the WP1 after-states of `common/header.php`, `account/wishlist.php`, `product.twig`, `wishlist_list.twig` and uk-ua `account/wishlist.php`, which provide the IDs, toggle endpoints, interim product button and «Обране» list.
- **Read-only admission:**
  - zero `oc_theme` overrides for `product/thumb` and `common/header`;
  - no alternate theme template;
  - the enabled events relevant to those views and controllers equal exactly 4 reviewed pairs: PS on thumb; PS, reCAPTCHA and live-search on header.
- **Apply order:** CSS → JS → `thumb.twig` → header tokens last. Backup and automatic restore as in WP1; self-delete.

**Stdout (apply):**
```
admission_sha256=<64 hex>
syntax_gate=ok
backup=/home2/boosters/public_html/_patch_backups/ACC-006-visual-WP2_20261029-<YYYYMMDDTHHMMSSZ>-<12 hex>
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

**Rollback:** `-Step RollbackUpload` → `-Step RollbackVerify` → `-Step Rollback`, with the exact receipt backup. It restores the 4 files, including the header's WP1 tokens, and clears the template cache. Across packages: WP3 → WP2 → WP1.

## 4. Owner run (Windows PowerShell packet, separate steps)
Fetch branch `claude/acc-006-visual-3hr0np`, then:

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP2_owner_20261029.ps1" -Step Upload
```
```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP2_owner_20261029.ps1" -Step Verify
```
```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP2_owner_20261029.ps1" -Step Apply
```

- Receipts go to `work\acc006-visual-wp2-20261029\owner-receipts\`.
- The packet is the applied WP1 packet with mechanical substitutions only: name, SHA, receipts folder, remote file name, markers and heredoc delimiter, backup regex, `changed_files=4`. See `evidence/packet-diff.txt`.
- ASCII, no BOM; `bash -n` passes on the remote bodies and `php -l` on the cache block.
- **Not run under Windows PowerShell 5.1:** no PowerShell in this environment. The WP1 packet with the same structure ran on the owner's PC.

## 5. Local verification
| Check | Result | Fidelity / limit |
|---|---|---|
| Twig and anchors (`twig.txt`) | PS anchors unchanged (thumb `[1,0,0]`, header `[0,1,1]`). For in-stock, sale, preorder+Rare, out and no-image tiles: exactly one heart, outside `<a>` and `<form>`; no text button; the media link (RD-25 slot, badges, CAT-004 column-0 whitespace) is byte-identical; the rest of the tile (cart form, TECH-015 script) is identical apart from inter-tag whitespace. The header render differs only in the two token values | real PS model replacements; Twig 3.30 |
| Browser (`browser.txt`) | 127/127 at 390/768/1280 on category, accessories, home and the product-page related rail: one heart per tile, 44×44 at 8/8px bottom-right; clear of top badges; outside link and form; out-of-stock image .45 with the heart not dimmed; saved product red-filled on load; unsaved outline in ink-2; tap = one `add` (double click), red fill, exactly one `add_to_wishlist`, notice; second tap removes with no GA; keyboard focus. UX-003 load-more append and filter replace (the same `importNode` calls) sync the inserted saved tile. An error notice is still visible after 5.6 s; a confirmation closes after 5 s. No page errors | 150 live WP1-state tiles transformed to the WP2 markup, with candidate JS/CSS; in-test wishlist server; non-boostershop hosts aborted; nothing reached production endpoints; assets replayed from a local cache |
| Runner (`runner.txt`) | 27/27, the same cases as WP1 on 4 targets: check/apply/repeat/rollback; wrong admission; token drift; non-token header drift; target/dependency drift; partial state; theme override; extra, wildcard and missing hooks; alternate template; runner in web root; injected failure with automatic restore; rollback refused after a later CSS append; no DB write statements | production runner bytes, with only the cache path and dependency pins substituted (asserted); `php -n` with a mysqli double |

Screenshots in `evidence/screenshots/`:
- category at 390/768/1280;
- home at 390;
- an out-of-stock related tile;
- a related tile at 768.

## 6. UI/CSS discipline
- **Root cause:** `thumb.twig`'s full-width wishlist text button under «Купити», which the WP1 rename left in place.
- **Override history:**
  - `.bs-pcard__media` rules (`ds.css:265-286`, RD-25-WP1 and the ACC-006-RD-25 centring) and the badge rules (`ds.css:287-289`, UI-PCARD-BADGE) are not edited.
  - No stylesheet or script in the live page set selects `.bs-pcard > …` or depends on the media link being a direct card child. Checked: ds, stylesheet, typography, live-search CSS, common, product-polish and category-results JS.
- **Signatures:**
  - no `!important`, no `position:absolute`/`fixed` (grid overlap in the new wrapper);
  - the one `setTimeout` is WP1's, now skipped for errors;
  - pixels: 44px touch target and a 22px icon (board values, commented).
  - The heart SVG path carries `fill="none"`, so it stays an outline if the stylesheet fails to load.
- **States:** outline `--bs-ink-2`; pressed fill `--bs-danger`, never green; hover border/colour danger; active `--bs-line-2`; focus-visible 2px `--bs-blue`; busy .6.

## 7. Owner QA after Apply
1. Hard-refresh a category on phone and desktop. There is one round heart per tile at the bottom-right of the photo, no button under «Купити», and the badges are unchanged.
2. Tap a heart: it turns red and filled, the notice reads «Додано в обране», the header counter goes up by 1. Tap again: «Прибрано з обраного».
3. On an out-of-stock tile, the photo is pale and the heart is not.
4. Category «Показати ще» or a filter change: a saved product among the new tiles shows a red heart.
5. Logged out: the guest heart flow and «Увійти» notice, as in WP1.
6. GA4 Realtime: one `add_to_wishlist` per new add, none on remove.
7. The product page still has WP1's text button (WP3 replaces it) and the sticky bar is unchanged.
8. Tier 1 smoke URLs; no new PHP errors in `/home2/boosters/ocartdata/storage/logs/`.
