# ACC-006 visual step — WP1 report: «Обране» names, heart toggle state, notices, «Обране» page

Date: 2026-10-28
Executor: Claude Code (cloud session). Handoff: `handoffs/handoff_ACC-006_favorites-visual_20261028.md`. G0: `diagnostics/ACC-006_visual-wp0_G0_report_20261028.md`.
Status: **package ready for the independent Claude chat review**. Not applied. No production access, deployment, Notion or dashboard action.

| Item | Path | SHA-256 |
|---|---|---|
| Patch | `patches/ACC-006-visual-WP1_20261028.php` | `43f9642e1e6d1f8310e83df5cd5df36cf9ff320d04c2172472237391907b1465` |
| Owner packet (bytes the owner runs: Windows checkout, CRLF per `.gitattributes` `*.ps1 eol=crlf`) | `diagnostics/ACC-006-visual-WP1_owner_20261028.ps1` | `9d34b33a0b7cfcfc697ba4a4dbe1d84715a52175b018c60eba6434060290d14d` |
| Same packet, LF repository blob (`git show`) | ditto | `7b31b16e08d640d57f20b351cebeb8cce24040409f07a872ceafc202ef5021ed` |
| Evidence | `diagnostics/ACC-006_visual-wp1_evidence_20261028/` | tests, outputs, `hash-contract.json`, per-target diffs, screenshots |

## 1. Authority and decisions applied
- **Owner decisions 2026-10-28 (G0):**
  - **D1 = A: a true toggle.** This is the narrow exception to «JS selectors and texts only» and «wishlist controller contract unchanged»:
    1. visible member IDs are emitted once on the header config element and returned as `ids` in the wishlist JSON;
    2. the JS marks the hearts and toggles add/remove;
    3. a guest's `remove` drops only the session-pending ID;
    4. `getList()` sizes the card photo with the RD-25 fit.
  - **D2:** the guest wording «Збережено в цьому браузері до входу» / «Увійдіть, і товар перейде у ваш кабінет.», with «Увійти» only.
  - **D3:** applies to WP3.
- **Package split:** all behaviour changes are in WP1. WP2 (tile) and WP3 (product) are presentation only.
- **Owner token-only exception required before Apply.** `common/header.twig` gets two new asset tokens:
  - `boostershop-ds.css?v=acc006-visual-wp1-20261028`;
  - `booster-wishlist.js?v=acc006-visual-wp1-20261028`.

  Both are located by path prefix and replaced wholesale (AGENTS.md convention 8). The runner tolerates any earlier token value. H1 covered RD-17/18 only.

## 2. What changes (16 targets, all existing files; no created files)

| # | Target | sha256_before | sha256_after |
|---|---|---|---|
| 0 | `extension/ukrainian/catalog/language/uk-ua/account/wishlist.php` | `e14da75efcea3f2ff84e2dcae2445d95c9d8f185361306f241a5c2e170f48383` | `2ef8f622c5dd089e56bca561fc19c3c9d2a25636f0196a9e194ea5483de6b9a1` |
| 1 | `extension/ukrainian/catalog/language/uk-ua/account/account.php` | `121f9e65a90820469c0d013d923e00b1a94fadc22ecf2c36c64e84f46a7a5040` | `11230460858614c62bfa8edbb02597738c2125658134503b18e87c1201b0fae7` |
| 2 | `extension/ukrainian/catalog/language/uk-ua/account/address.php` | `ca6c35e0707650c5ace0333ba2bcd6004027aa3866fa5134144d364963aab592` | `5eb48dcabdabc840a1df3c3baf2fd6d6a63d144753fbd87c6d69315d2dbb9bcf` |
| 3 | `extension/ukrainian/catalog/language/uk-ua/account/edit.php` | `8e37b5575aae9bc8f0e5a160cb6cd1c42ddc4b8d6228084978351bedd0314622` | `00179ceb8fb17148aa57c5bb3479189478be671798d7acafec4ccceaee486a60` |
| 4 | `extension/ukrainian/catalog/language/uk-ua/account/order.php` | `917f6cc7e0af8a5813d6c1a3c3b702f4c870c30015782a52cf2a2cbe65fe50c5` | `4bf69457086e5700a553202fa1ddc71b898252bb67abf6ace521698bd4cf3d10` |
| 5 | `catalog/controller/account/wishlist.php` | `8f5eea4d22f271ee2cd502cbcdadd650f1abb657d3ec1a8fa10214595c3e24c1` | `b5aa26bd04f90787b9b83d2a9729d6d5652357d7428231bd0bcf306ebfe8704a` |
| 6 | `catalog/controller/account/shell.php` | `b39077e4b62f540a4ec47fc02021447ea961fbb8b2423d4027688f45c14aa3e1` | `deded989a849af17d74f3de5a068ca2b7616e5c4f465402dc54ea1fa66c59f07` |
| 7 | `catalog/controller/common/header.php` | `64a96e2fe537887a2de906075bad8dd9862f04c9b965eeb79745cfacde01a6dd` | `1fb9427799b443eb536a213c41fb3c13912b76f789c24faa06284d9ce6497d32` |
| 8 | `catalog/view/stylesheet/boostershop-ds.css` (append only) | `ff548b2b7bf52eeae34e5e5d18f6313b913451e0b113997a108faf254c283293` | `6e985f45c03b9b232a9b6ab8f75bc0283ff012b632b87ee5ff07c62d0daa15c0` |
| 9 | `catalog/view/javascript/booster-wishlist.js` | `f8ef6c244be886fb29f0414d57ce474e5331b513fe1ebb6ef7c3b630bc80340c` | `762e00e4a221653804198e9ab16ea50096180c1122bb30759080afa965ddd5e4` |
| 10 | `catalog/view/template/account/wishlist_list.twig` | `00aea6f066e244d65468710c35fb53d62afe26c79affa1b6d6528614f17df563` | `7c73293b24d440d53e6bd5367b3ac89c0eda2ccdb044c1bb44995e7b42ba6b33` |
| 11 | `catalog/view/template/account/wishlist.twig` | `99df9afb6810ef36a41f099d143e7cc4a45e55bb2f50cee32e08ae9478a22879` | `c475e368500f5547bf0b1c405edc7fa8f4e88ccd5ff7383689e7b2287771b8e6` |
| 12 | `catalog/view/template/account/shell.twig` | `f61496ef34e56f813a17ace4795962ff450ed71ec88f1413a62a0e486f85ca16` | `27ec44bdb5cd3e26214e555a281a8adcd26fe53644d2b39e27a4cba141b1ca82` |
| 13 | `catalog/view/template/product/thumb.twig` | `72dec6ce3b0bbd20b65e6bc194dff88c3493f2b9b6dcbf58bf6d69051dd8fced` | `5cbccd9205da21f23b74aa745aca79593c932a77df02d826ecdb9e0520da9c4c` |
| 14 | `catalog/view/template/product/product.twig` | `882eeb6de97308267802c8833a385f6ef1fa05b2c082e362f7f158eb4046f536` | `3f957f3d07bb7ee68e40fc0cc5d5c84fe657ef05458299487d3f264af0a1a32b` |
| 15 | `catalog/view/template/common/header.twig` | `96abebbd32f15a5f0bf574f6bc273e43a8ca422a382f0f7fb9ae7987df986ffc` (non-token content; both tokens normalized) | `51b9327ed96f0c5b5f42ed87fffd0015b8795399e4f7884231ff6669ab934399` |

Before states: owner pulls r1 (manifest-bound) and r2. r2 arrived without its manifest, receipt or inventories: its 5 files are bound only by this runner's before-hash gate on the server. `product.twig` is based on the live `882eeb6d…`, including the unrecorded JSON-LD `-BULK-` line, which stays byte-identical (G0 §2).

Per-target diffs: `evidence/diffs/`. In summary:
- **Names:** every inventoried «Улюблене» becomes «Обране», in the correct case forms:
  - page title, H1 and breadcrumb;
  - shell nav on all account pages;
  - hub card, welcome text and header `aria-label`;
  - tile and product buttons («Додати в обране» / «В обраному»);
  - notices («Додано в обране», «Прибрано з обраного»);
  - unused stock «Закладки» strings in `account/wishlist`.
- **Toggle state:**
  - `wishlist.ids()` is read-only and uses the `total()` SQL with `SELECT DISTINCT … ORDER BY`. The test asserts the same filter. Guests get their sanitized pending IDs.
  - `header.php` emits `data-ids`; `stateData()` adds `ids`.
  - JS `sync()` sets `aria-pressed`, `aria-label` («Додати в обране: …» / «Прибрати з обраного: …») and the text span on every `[data-bs-wishlist-add]` with that ID.
  - A tap toggles add/remove.
  - The add request, `pending[id]` guard, queue, busy state, counter sync, `refreshList()` and `trackAddition()` are unchanged. The `add_to_wishlist` call stays on new adds only.
- **Guest remove:** after the unchanged token/method gate and the positive-ID gate, a guest's `remove` calls `clearPending()` only: no DB, no model. The logged-in path is unchanged; its auth check now follows the ID check, and the error text is the same either way.
- **Notices:** the existing `role=status aria-live=polite` region becomes a DS popover (`--bs-sh-pop`):
  - title;
  - optional detail;
  - «Переглянути» (logged-in add) or «Увійти» (guest add);
  - ×.

  Confirmations close after 5 s. A notice with «Увійти» stays until closed.
- **«Обране» page:**
  - It opts into the RD-16 shell in page mode, like RD-18 orders: no `column_left` call. The nav shows «Обране» with a count pill; `shell.php` reads `account/wishlist.total`.
  - Heading: «Обране» + «N товар/товари/товарів».
  - Desktop shows a grid of white cards: the RD-25 fit photo with cut/photo framing, badges top-left, and the heart bottom-right at 8px.
  - Mobile (≤575.98) shows rows: 80px photo, name/price, heart on the right, full-width action.
  - Buttons: «Купити» green, «Передзамовити» blue-light, disabled «Немає в наявності»; the out-of-stock photo is dimmed .45, the heart is not.
  - Empty state per board, with blue «До каталогу» → home.
  - The stock table is removed.
- **Interim:** the tile and product buttons stay full-width text buttons until WP2/WP3. They already toggle; pressed shows a red outline.

## 3. GA4 anchors (unchanged; see G0 §3)
All PS replacement anchors keep identical counts before and after, using the actual PS model and str_replace semantics (`evidence/anchors.txt`):

| Template | Anchor counts |
|---|---|
| thumb | `[1,0,0]` |
| product | `[2,0,1,1]` |
| wishlist | `[1]` |
| wishlist_list | `[2,1]` |
| header | `[0,1,1]` |

On «Обране»:
- the two `<a href="{{ product.href }}">` links and the cart button `<button type="submit" form="form-product-{{ product_row }}" class="btn btn-primary mb-1">{{ button_cart }}</button>` are byte-exact;
- «Передзамовити» reuses that same button: `button_cart` gets a per-card value inside the loop;
- rendered with the PS replacements, every buyable card keeps `data-ps-track-event="add_to_cart"` and every link `select_item`/`select_promotion`.

## 4. Runner, admission and stdout contract
**Modes:** `--check`, `--apply --admission=<sha256>`, `--rollback-check=<backup>`, `--rollback=<backup>`. Owner CLI only. cwd must be `public_html`; the runner must be outside it on the same filesystem. PHP 8.0.

**Gates before any write:**
- **Complete no-op:** `already_applied=yes`, with no token retouch.
- **Targets:** every exact before-hash. The header is compared with both tokens normalized. `ds.css` must be byte-exact, and the after-state is checked by its marker block.
- **44 pinned dependencies:** the accepted RD-18-WP5 pins (current 2026-10-28) plus fresh r1/r2 hashes. They cover:
  - account controllers and templates;
  - the wishlist and product models, the image model and library;
  - theme and event startup, the loader, Twig and language;
  - the PS, reCAPTCHA and live-search implementations.
- **Read-only DB admission:**
  - column checks;
  - zero `oc_theme` overrides for the 6 changed views;
  - no alternate theme template shadowing them (store settings mirrored);
  - the enabled events relevant to the changed views and controllers must equal exactly the 7 reviewed pairs. These are PS on thumb, product, wishlist, wishlist_list and header, plus reCAPTCHA and live-search on header, per the event table in ACC-008 evidence 2026-10-16 and the RD-18-WP5 pins.
  - A mismatch prints the `relevant_hook=` pairs (non-secret) and stops.
- **Admission hash and lint:** an admission hash over runtime facts, targets and dependencies; every PHP candidate linted.

**Apply:**
- a backup with 16 originals and `manifest.json`;
- recheck;
- atomic renames in order: languages → controllers → CSS → JS → views → header (tokens last), with a post-hash and lint per file;
- on any failure, an automatic restore of the written targets;
- self-delete.

**Stdout (apply):**
```
admission_sha256=<64 hex>
syntax_gate=ok
backup=/home2/boosters/public_html/_patch_backups/ACC-006-visual-WP1_20261028-<YYYYMMDDTHHMMSSZ>-<12 hex>
target=<path> sha256_before=<hex> sha256_after=<hex> backup=<backup>/original-<i>   (×16, table order)
changed_files=16
self_deleted=yes
done=ok
```
The packet then prints `template_cache_removed=<n>`, `template_cache_cleanup=ok` and `staged_runner_absent=yes`.

Other outcomes:
- no-op: `already_applied=yes`;
- failure: `error=<code>` with exit 1, plus `automatic_restore=ok` if a write had started.

No DB write; no SQL rollback.

## 5. Owner run (Windows PowerShell 5.1 packet, separate steps)
Run them in this order:
1. Fetch branch `claude/acc-006-visual-3hr0np` into the local repository.
2. Run `-Step Upload`, then `-Step Verify`. Verify runs `--check`, which makes no production writes.
3. Grant the token exception (§1) before `-Step Apply`.

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP1_owner_20261028.ps1" -Step Upload
```
```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP1_owner_20261028.ps1" -Step Verify
```
```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\ACC-006-visual-WP1_owner_20261028.ps1" -Step Apply
```

**Receipts** go to `work\acc006-visual-wp1-20261028\owner-receipts\`. Send the Verify receipt if it is not `ACC006_WP1_VERIFY_OK`.

**Rollback** is a separate owner choice: `-Step RollbackUpload` → `-Step RollbackVerify` → `-Step Rollback`.
- It uses the exact backup saved from the Apply receipt.
- It preflights every target and backup.
- It restores in reverse order and clears the template cache.
- A target changed after apply (e.g. a later CSS append) stops the rollback before any write.
- Rollback order across packages: WP3 → WP2 → WP1.

**Packet composition:** mechanical substitutions only on the accepted `diagnostics/RD-18-WP4_owner_20261028.ps1`:
- name, SHA and receipts folder;
- remote file name;
- markers and heredoc delimiter;
- the backup-name regex;
- `changed_files=16`.

The full line diff is in `evidence/packet-diff.txt`. ASCII-only, no BOM.

**Line endings:** the repository stores the packet as LF and checks it out as CRLF (`*.ps1 text eol=crlf`). The RD-16/17/18 packets were pinned with `-text` rules in `.gitattributes`; that file is outside this task's push scope.
- The reviewed hash is the executed CRLF checkout, `9d34b33a…`.
- The LF blob is `7b31b16e…`.
- Every remote body is normalized CRLF→LF by `Invoke-LfBody`, which also rejects any stray CR, so both byte forms send identical commands.
- Optional owner action when publishing to `master`: add `diagnostics/ACC-006-visual-WP1_owner_20261028.ps1 -text` to `.gitattributes`.

## 6. Local verification
| Check | Result | Fidelity / limit |
|---|---|---|
| Anchors and Twig (`anchors.txt`) | 6 templates compile and render before and after; anchor counts equal | real PS model `replace*Before()`, real Twig 3.30 (host Twig version not pinned), `autoescape=false` as OpenCart |
| List render (`render-list.txt`) | 11/11: per-card button text, including a buyable card after a preorder; GA4 attributes; disabled out; encoded names kept; nav count | real templates and PS replacements; fixture data |
| Backend (`backend.txt`) | 21/21: guest add/repeat/remove without DB; invalid, array and 0 IDs; bad token, GET, bad customer token; logged add/remove; ids SQL = total SQL filter; index shell call and links; fit photo, states, discount; Ukrainian plurals (0–111); shell count | actual candidate controllers; fake OpenCart services and DB double; local PHP 8.3 (host 8.0.30: no 8.1+ constructs used) |
| Browser (`browser.txt`) | 99/99 at 390/768/1280, guest and logged: server IDs mark tiles; double click = 1 request; pressed + counter; exactly one `add_to_wishlist` per new add, none on remove; notice text, links and viewport; one-line button with unchanged tile height; product button and sticky bar; «Обране» layout, heart position, dim .45, button colours, columns, label/nav, remove updates label/nav/header; empty state | live public category/product HTML with candidate markup transforms, candidate JS/CSS and an in-test wishlist server. Non-boostershop hosts aborted; no wishlist/cart/GA request reached production. Assets were fetched once and replayed. «Обране» is composed into the live page frame: the real logged-in page is owner QA |
| Runner (`runner.txt`) | 27/27: check/apply/repeat/rollback; wrong admission; token drift; non-token header drift; target/dependency drift; partial state; theme override; extra, wildcard and missing hooks; alternate theme template; runner in web root; injected write failure with automatic restore; rollback refused after a later CSS append; no DB write statements | production runner bytes, with only the cache path and dependency pins substituted (asserted) and one injected-failure line; `php -n` with a fixture-only mysqli double |
| Packet | `bash -n` of the remote bodies; `php -l` of the cache block; ASCII/LF/no BOM | **Not run under Windows PowerShell 5.1**: no PowerShell in this environment, and downloading one was refused by the session policy. 5.1 compatibility rests on the accepted RD-18-WP4 packet with mechanical substitutions |

## 7. UI/CSS discipline
- **Root cause:**
  - the stock `account/wishlist` table and `column_left` (`wishlist.twig:7`, `wishlist_list.twig:4-53`);
  - the plain notice container (`header.twig:652`);
  - no member state anywhere (G0 §7).
- **Override history:** the new rules are scoped to `.bs-account-favorites`, `.bs-fav-*`, `.bs-wishlist-notice*` and `.bs-account-nav__count`. They are appended as one marked block; no existing rule is edited.
  - The mobile sub-page block copies the accepted RD-18 orders pattern (`ds.css:7596-7602`), so the hub's `display:contents` is untouched.
  - The interim pressed rule is `.bs-btn[data-bs-wishlist-add][aria-pressed="true"]`.
- **Review signatures:**
  - `!important`: none.
  - `position:absolute`: none; grid overlap places the card heart and badges.
  - **One `position:fixed`:** the notice popover. It sits at the top because the cookie bar (z 9999) and the product sticky bar own the bottom. Its z is `--bs-z-toast` (500) over the sticky header (300).
  - **One `setTimeout`:** auto-close of a confirmation after 5 s. It is cleared on each new notice and not used for a login notice.
  - **Pixel values** are board values, commented in the block: 44 touch, 80 row photo, 210 card min, 22 pill. The 8px radius and `#2563EB` match `.bs-btn` / `.bs-btn-preorder:hover`.
  - `.mb-1` on the GA4-anchored button keeps Bootstrap's 4px margin; it is not overridden, because that would need `!important`.
- **Breakpoints and states:** 390/768/1280 checked, with long names (2–4 lines) and focus. Hover/active rules exist for the heart, notice links and close. Screenshots: `evidence/screenshots/`.

## 8. Known limits
- Tiles inserted later by script would get their state only after the next toggle. No live listing loads tiles by AJAX: category, search and home are server-rendered.
- Server-side rendering marks hearts after `booster-wishlist.js` runs (defer). Until then the button shows «Додати в обране».
- These G0 r2 inventories were not received; neither affects WP1's changed files:
  - «Закладк» across all templates;
  - module controllers that load `product/thumb`.

  The stock «Закладки» strings in `account/wishlist` are renamed.
- The guest choice stays session-bound, as live. The wording says «в цьому браузері до входу».

## 9. Owner QA after Apply
1. Hard-refresh a category on phone and desktop. Buttons read «Додати в обране». Tap one: it turns to the red «В обраному», the notice at the top reads «Додано в обране» · «Переглянути», and the header counter goes up by 1. Tap again: «Прибрано з обраного».
2. Logged out: add one, and the notice says «Збережено в цьому браузері до входу» / «Увійдіть…» with «Увійти». Tap again to remove it. Add one, log in, and it is in «Обране».
3. Open «Обране» in the account: the new page and nav item «Обране N», the cards with their states, the heart bottom-right. Remove one: the heading count, nav count and header update. Remove all: the empty state with «До каталогу».
4. The hub card says «збережено в обраному». The nav on «Особисті дані», «Адреси», «Мої замовлення» reads «Обране» with its count.
5. GA4 Realtime: one `add_to_wishlist` per new add, none on remove.
6. Tier 1 smoke URLs; no new PHP errors in `/home2/boosters/ocartdata/storage/logs/`.
