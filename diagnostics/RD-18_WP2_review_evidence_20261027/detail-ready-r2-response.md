# Review OK: RD-18 WP2 detail visual/photo repair and owner packet

This is an integrated code and composition ready review. It is not deployment approval, visual acceptance or owner QA. Review OK lets the owner start the new route (Upload, then Verify, return the receipt, then a separate Apply). The mandatory closures below still apply.

## Bound candidate

| Item | Path | SHA-256 | Bytes |
|---|---|---|---|
| Runner | `patches\RD-18-WP2_detail-visual_20261027.php` | `7848eea3a118ecfaf89f948bebffbee83a9d06dbd8b22682216067322853df7a` | 614028 |
| Owner packet | `diagnostics\RD-18-WP2_owner_20261027.ps1` | `b212cb6591594071429fc6d1ab499acf20882f751d6720b06074c8ba91b9d9b1` | 11844 |

I did not compute, decode or run anything. The binding rests on these hashes appearing identically across the evidence:

- The packet's `$Sha` equals the runner hash.
- `release-facts.json` lists both hashes.
- All 10 `pair_before`/`pair_after` brackets in `bound-check-results.json` match.
- The five after-hashes in `targets.json` match `render-projection-summary` and `native-photo-results.controller_sha256`.
- The three photo source pins in `dependencies.json` match the intake.
- The five routing-core hashes match the dependency pins.
- Header bytes grow by +6, which matches the token length change.

Any byte change to either file voids this review.

## Findings: no blocking code defect

**Photo helper (`rd18Thumb`):**
- It runs only inside `if ($order_info)`, so after native ownership validation.
- Each distinct product ID is looked up once per request. The GD check comes before any model call, so the native constructor's `exit` cannot be reached.
- It uses a scalar, length- and NUL-limited filename and a single entity decode, matching the native resize.
- The canonical root prefix check and `getimagesize` type allow-list run before the unchanged native `resize(…,64,64)`.
- It catches `Throwable` and falls back to the placeholder.
- Only the image string is used, so stored name, price and quantity are untouched. The render traces confirm the extra calls are limited to `getProduct`/`resize` and the `rd18_thumb` field.
- Disabled, unavailable or other-store products also show the placeholder, because `getProduct` filters on status, date and store. This is consistent with the owner's "placeholder if removed" choice.

**Twig:**
- Hidden reorder forms and the native inline handlers are unchanged in the diff.
- `#history` AJAX pagination still targets the same container. The current-state disclosure sits outside it and does not claim to be a history row.
- New output is escaped (`e`/`e('html_attr')`); unescaped `quantity`, `reward` and `option.value` are pre-existing native fields.
- Completed badges are dark with a check mark. The purchase button keeps `--bs-buy`.

**CSS:**
- The owned block is replaced in place. The new `.bs-order-product__action` rule comes after the base `.bs-order-buy`/`.bs-order-return` rule at equal specificity, so 44px and `padding:0` win.
- The 96px column is 44 + 8 + 44.
- There is no `!important`, absolute/fixed positioning or timer.
- The two-column flex rule (999/400 primary, 1/300 aside) and the mobile three-column grid address the rejected r3 composition defects. These are: bottom info grid, duplicate current card, hollow 12px markers, missing thumbnails, 44px title anchor, and price below the title on mobile.

**Runner control:**
- The `block` kind only affects no-op detection. Apply still requires the exact full before-hash, full replacement and the after-hash.
- Apply order 3,0,2,1,4 and rollback order 1,2,0,3,4 are coherent. Header-last means the restored r3 CSS returns under its original cached token.
- `changed_files` is now dynamic.
- The `DIR_IMAGE` mapping is checked only on Check/Apply.
- Eight photo trigger edges are appended. They are relevant-and-refused unless the handler is `event/theme` on a view `before` trigger. No new allowed hook is added.
- Lock, backup, double preflight, automatic restore and self-delete logic is unchanged from r3.

**Packet:**
- Steps are one-use and replay is refused.
- `&&` gates cache cleanup. A failed Apply falls through to `die runner_did_not_self_delete` with a nonzero exit, and receipts are preserved.
- The backup regex matches the runner's format.
- Rollback requires a recorded backup and is a separate owner choice.

## Mandatory closures (no runner or packet byte change)

1. **Packet self-hash:** before the first step, the owner confirms with `Get-FileHash` that the packet equals `b212cb65…d9b1`. The packet checks the runner hash but cannot check its own.
2. **Verify gate:** the complete Verify receipt must be returned and assessed before Apply. It must show `php_version=8.0.x`, `admission_sha256=<64hex>`, `syntax_gate=ok`, `production_writes=0`, `backup=none`, `done=ok` and `RD18_WP2_VERIFY_OK`. Any STOP or `already_applied` is not retried, reset or re-pinned.
3. **Correct the focus-evidence claim:** the report says hover/focus/active were checked on the 44px controls. `test-browser.cjs` asserts focus only on `.bs-order-buy`. The icon-only return link (`.bs-order-return`), the thumbnail link and the title link have no authored focus style. An earlier comment says `stylesheet.css` clears outlines with `!important`. The record must narrow this claim to the buy button only.
4. **Owner QA items required before acceptance:**
   - Visible keyboard focus on the return icon, thumbnail and title links. If focus is invisible, that needs a new corrective package with new hashes and a new review.
   - Real catalogue photos on the host, including GD/WebP and a cold first load.
   - Real totals, payment and billing data.
   - Desktop, mobile and tablet layouts.
5. **Rollback scope awareness:** rollback STOPs if any target changed after Apply, including a later DS append or token change, even though the no-op check tolerates such changes. That case needs a targeted assessment, not a forced rollback. Derived `image/cache` files are not removed by rollback.

## Optional suggestions (none are blocking)

- **Tablet layout:** at 768px, the main area (about 736px) can fit 400 + 16 + 300, so it likely shows two columns. Geometry was asserted only at 1280 and 390, so owner QA should judge 768.
- **Comment panel:** when present, it renders full-width below both columns. Check it against the boards on a commented order, or move it into the primary column.
- **Desktop heading:** the creation date can sit on the same line as the h1 and badge. This was not asserted.
- **Duplicate links:** the thumbnail link duplicates the title link, giving two tab stops per product. Consider `tabindex="-1"` and `aria-hidden` on the thumbnail.
- **Sharpness:** 64px rasters will look soft on 2× screens. A 128px source is a possible later improvement.
- **Cancelled events:** recorded cancelled events also get a blue check marker. Consider whether that reads correctly.
- **Stale comment:** "twenty-six targets" in the `rd16_event_check` docblock is out of date.
- **Packet tests:** they exercise only the happy path and replay refusal, not the STOP branches.

## Evidence limits

- **Not seen:** screenshots, `comparison.html`, PNG geometry files, and the two compiled contract blobs (571052 and 11244 encoded bytes). Their equality to the five payloads is the executor's claim, which I have not verified.
- **Hash-only:** the three newly acquired native source bodies (`product.php`, `tool/image.php`, `library/image.php`) and all other dependencies. My native-API reasoning relies on the executor's local inspection and synthetic runs, plus my general knowledge of OpenCart 4.
- **Not certified:** host, SSH, MySQL, permissions, GD/WebP on the server, real images, the production Twig wrapper and cache, GA4 and other hooks, whole-store event coverage, performance, deployment and owner visual acceptance.
- **Not attested:** whether this transfer had applicable external-transfer authority. I reviewed only the material supplied.
