# ACC-006 visual WP3 — independent Claude chat review

Date: 2026-10-29. Reviewer: Claude chat (Booster project). Executor: Claude Code (cloud), branch `claude/acc-006-visual-3hr0np`, commit `a72e2e445488a60b9f66de76a2a540441778009a`.
Scope: integrated CODE/composition review of the exact pair below. Review only.

| Item | SHA-256 | Checked |
|---|---|---|
| `patches/ACC-006-visual-WP3_20261029.php` | `5d4b7e3ea91e6b47ae33438ceb1820ade4e1b5bfdaa5754de2459df7d1adbae5` | recomputed; `php -l` OK |
| `diagnostics/ACC-006-visual-WP3_owner_20261029.ps1`, CRLF bytes the owner runs | `762cb5367a15ec42b2e49cc0b421b2923b1785eb4827f030cbd7e56fdb0d7a91` | LF blob `2cf9e5fc…` recomputed; CRLF conversion reproduces `762cb536…` |

**Verdict: Review OK**, with one owner decision: the desktop square is 44×44 instead of the board's 48×48.

## Basis
- **Runner.** The non-payload body differs from the applied WP2 runner only in the ID, the marker and the comments, plus the trigger/view lists retargeted from `product/thumb` to `product/product`. The gates, backup, rollback and self-delete logic are unchanged.
- **Packet.** My own diff against the applied WP2 packet equals the executor's `packet-diff.txt`: name/hash, receipts folder, markers, the backup regex and the count only.
- **Target diffs (4):**
  - **`product.twig`:**
    - a mobile heart as a sibling of the Magnific `<a>` in `.bs-product__main-img`, so a tap does not open the popup;
    - a desktop square heart after the CTA inside `.bs-buy-row`, `type="button"`;
    - a `--solo` fallback when there is no gallery;
    - the WP1 text button removed.

    The purchase form, credit/PUMB blocks, sticky bar and variant selector are untouched.
  - **`boostershop-ds.css`:** an append-only scoped block. `display:none` switches the variants (one visible, one focusable heart); the D3 breakpoint is 767.98px. No `!important`, no absolute positioning. The RD-10D3 row rules are not edited.
  - **`booster-wishlist.js`:** confirmation auto-close changed from 5000 to 4000 ms (owner request). Login and error notices still persist.
  - **`header.twig`:** the DS and JS version tokens only, under the owner's WP2/WP3 token-only exception.
- **Screenshots** (390 mobile photo heart, 768/1280 buy row): placement as on the boards; the row height stays at 44px.
- **Risky zones:** none; the product page purchase form is untouched by design (`type="button"`, outside the submit path).

## Owner decision
**Square 44 instead of 48.** Measured live, the buy row is 44px high. A 48px square would stretch the label and quantity (RD-10D3 `align-items:stretch !important`) while the CTA stays at 44px. The reviewer recommends accepting 44 (`--bs-control-h`).

## Out of scope, reported by the executor
On preorder product pages the CTA text reads «У кошик», not «Передзамовити». The cause is `booster-product-polish.js` (TECH-045), which predates ACC-006. It is a candidate for a separate task.

## Rollback
Run `-Step RollbackUpload` → `RollbackVerify` → `Rollback` with the saved backup `/home2/boosters/public_html/_patch_backups/ACC-006-visual-WP3_20261029-<UTC>-<nonce>/`. Across packages: WP3 → WP2 → WP1.
