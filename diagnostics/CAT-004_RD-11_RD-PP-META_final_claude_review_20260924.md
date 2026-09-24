# Final Claude review packet — CAT-004 / RD-11 / RD-PP-META

Date: 2026-09-24  
Executor: Codex  
Review requested by owner. No commit, push, deployment, or roadmap/status write was performed by Codex.

## Outcome

Seven independently runnable PHP patch files implement the product-page and cart UI rounds. The latest three correct the owner's screenshot findings and implement the owner-supplied RD-PP-META variant B handoff. All changes are local and await Claude's review and the owner's deployment/QA gate.

The owner's latest “Все чудово” is feedback on the prepared work. It is not an execution log or a post-deployment source export. Screenshots show the prior UI round, but the exact server patch state is not independently verified.

## Sources and authority

- `C:\Users\14bez\Downloads\CAT-004_ui_sources_20260923.tar.gz`: current-at-export product Twig/CSS/header and trust-use manifest from the owner.
- `C:\Users\14bez\Downloads\BS_cart_product_ui_20260924.tar.gz`: owner export of cart controller/model/library, cart Twig, product controller/model/Twig, CSS, and header. SHA-256: `6D867B3EF98E41FF65C0EFBE727BFB1F0046FF3223E16064936992D2D9077968`.
- `C:\Users\14bez\Downloads\CODEX - RD-PP-META_status-card_20260924.html` and `RD-PP-meta - статус, виробник, відгуки.html`: owner-supplied design/handoff references, not permission to deploy or change roadmap status.
- Three owner screenshots in the latest task show: a positive-stock Black Bolt incorrectly labeled preorder in cart, the wrapped preorder trust item, and the old minimum-total hint.
- Project `AGENTS.md` governs patch runners, backup, PHP 8.0 compatibility, review, and owner deployment. The handoff's “In review” line was not executed as a status transition.

## Patch chain and exact order

| Step | Patch | Purpose | Production files changed |
|---|---|---|---|
| 1 | `patches/CAT-004_variant-cosmetics_20260923.php` | Remove selector top gap and duplicate current value by “Колір” | product Twig, DS CSS, header Twig |
| 2 | `patches/CAT-004_info-column-reflow_20260923.php` | Product trust icon/text layout and prior manufacturer/stock/ETA reflow | product Twig, DS CSS, header Twig |
| 3 | `patches/CAT-004_preorder-price-credit-layout_20260924.php` | Show quantity tier for preorder; preserve existing unit special rendering; change unavailable-credit hint; make Mono/PUMB half-width peers | product Twig, DS CSS, header Twig |
| 4 | `patches/RD-11_cart-preorder-label_20260924.php` | Initial cart preorder label for status ID 8 | cart-list Twig |
| 5 | `patches/RD-11_cart-preorder-quantity_20260924.php` | Correct step 4: label requires raw inventory < 1 **and** status ID 8 | cart controller, cart-list Twig |
| 6 | `patches/CAT-004_trust-credit-copy_20260924.php` | Center only product preorder truck item; correct both minimum-total hint branches to “Сплата частинами” | product Twig, DS CSS, header Twig |
| 7 | `patches/RD-PP-META_status-card_20260924.php` | Replace the old product meta block with variant B review/manufacturer row and three-state card | product Twig, DS CSS, header Twig |

Steps 1–4 were tested in multiple orders against the September 24 export. Steps 5–7 were tested after those four in both `6 → 7` and `7 → 6` order; the table gives one safe canonical order. If steps 1–4 are already deployed, the owner should run only steps 5–7. If none are deployed, run all seven. The exact live state should be confirmed by the owner before selecting a run set.

**Composition note:** step 7 supersedes the manufacturer/stock/ETA *markup* added by step 2. Step 2 remains necessary in this chain for the product trust CSS and as the guarded anchor used by step 6. If the whole series is still unshipped, Claude may recommend consolidating that chain, but no parallel rewrite should begin without the owner's executor decision.

## Implementation details requiring review

1. **Cart predicate.** `system/library/cart/cart.php` already treats stock status ID 8 as preorder for `hasStock()`. Its product `stock` field is raw inventory; `catalog/controller/checkout/cart.php` previously overwrote the display row's `stock` key with a boolean. Step 5 adds only `bs_preorder_label = (raw stock < 1 && stock_status_id === 8)` before that overwrite. The cart Twig shows preorder for that flag, otherwise the existing unavailable label when its display stock flag is false. No checkout eligibility rule changes.
2. **Prices.** The product controller already formats active unit `special` and `getDiscounts()` quantity tiers. The product Twig already renders base plus active special. Step 3 only removes `{% if discounts and not _is_preorder %}` in favor of `{% if discounts %}`. The owner-supplied EB-03 example has ₴230 unit price and ₴200 from 3 units, with no active unit special; the active-special preorder case remains a manual data-backed QA check.
3. **Credit copy and layout.** Two JS render branches now say `Сплата частинами доступна лише для товарів у наявності.` for unavailable products and `Сплата частинами доступна від …` for the minimum-total hint. Only wording changed; thresholds, calculations, and payment eligibility are unchanged. Mono/PUMB card CSS edits its source rules into a two-column grid, with a one-provider full-width state and narrow-screen wrapping.
4. **Trust strip scope.** The component also exists in `catalog/view/template/common/home.twig`; old `cat002_5c_mobile_visual_breadcrumb_20260630.php` touches `#content > .bs-trust-strip`. Steps 2 and 6 use `.bs-product__info` scope. Step 6 marks only the truck item used for preorder and centers it at the mobile breakpoint. Homepage and other trust items are untouched.
5. **Status card.** Step 7 reuses `_is_preorder`, `_is_out`, the current `stock` text, review flags, manufacturer, and the existing hardcoded `3–4 тижні` ETA. It preserves current preorder-before-out branch precedence. Any numeric in-stock quantity, including 1–5, shows a green card with the exact count; nonnumeric/missing quantity omits the subtitle. No low-stock state was added. The review link retains its `href`, full `onclick`, and `bs-pp-reviews__olx` class; its icon/text children follow the handoff. The existing ratings branch remains.
6. **CSS integration.** Handoff color and size values were used. The existing `.bs-pp-reviews` class also names the ratings row, so pill rules were scoped to `a.bs-pp-reviews` within `.bs-pp-head`; this intentional deviation prevents styling reviewed-product ratings as a pill. The product column already has a 13px flex gap; three scoped spacing rules make visible title/head, head/card, and card/price gaps approximately 13/14/18px. Old `.bs-pp-meta` rules were left in the shared stylesheet because the supplied export cannot prove no other theme use. No new `!important`, `setTimeout`, or absolute/fixed positioning was introduced.

## Validation performed

- All seven runner files passed local `php -l`. Step 5 also ran `php -l` on the modified cart controller after writing its fixture copy. Local PHP was 8.3.30; runner syntax stays within production PHP 8.0 features.
- Every runner produced `done=ok` with guarded anchors, backups, and self-delete on copied source. Re-running steps 3–7 in their respective final fixtures returned `already_applied=yes` where tested.
- Combined fixtures: `work/cart-product-ui-20260924/fixture-old-first/`, `fixture-followup-final/`, and `fixture-followup-reverse/` show the patch chain. These are local test copies, not production files or commit candidates.
- Product Twig parsed with local Twig 3.28.0. Focused rendering checks in `work/cart-product-ui-20260924/check-followup-render.php` passed for in-stock count, preorder ETA, out state, absent manufacturer/reviews, absent numeric quantity, existing ratings, preserved review `onclick`, and three cart label outcomes.
- Final DS CSS had balanced brace counts in the local fixture. CSS token replacement reads whatever valid current token exists; runners do not depend on a hardcoded predecessor token.
- Visual browser QA of this final round was not completed: headless Chrome/Edge exited 13, and the browser tool explicitly blocked a local `file://` fixture. No alternative browser route was attempted after that block. No current production screenshot, console check, Rich Results Test, or Tier 1 smoke was performed by Codex for the final round.

## Claude review checklist

- Inspect all **untracked** patch/report files directly; `git diff` alone does not include them. Verify no overlap with unrelated dirty worktree files.
- Confirm step 5 checks the product's raw inventory rather than cart line quantity or the controller's rewritten display-stock boolean. In particular, positive-stock Black Bolt with status ID 8 must have no preorder label.
- Verify step 7's preserved review `onclick`, the reviewed-product ratings branch, manufacturer URL, three status branches, and no new controller status logic.
- Review CSS cascade and source-rule edits at desktop 1280/1024, tablet 768, and mobile 390/320px; check long product/manufacturer names, wrapped trust text, one/two bank card states, hover/focus/disabled states, and horizontal overflow.
- Explicitly scan new CSS/JS for `!important`, `setTimeout`, `position:absolute/fixed`, and unexplained pixel constants. The additions intentionally use handoff dimensions and the scoped 13px-column-gap integration rule.
- Check home-page trust strip, a product with no manufacturer/reviews, an in-stock product with quantity 1–5, and an active-unit-special preorder if one is configured.
- Check template/theme DB overrides if deployed Twig does not reflect changed source after cache clear. No DB patch is included.

## Deployment boundary and rollback

Claude reviews and prepares a recommendation; only the owner uploads/runs scripts and performs final manual QA. Each runner checks target existence and exact anchors before writing, backs up changed files under `_patch_backups/<patch>-<timestamp>/`, and self-deletes on success. Restore the logged files in reverse run order and clear the OpenCart template cache to roll back. If any runner reports `done=failed`, stop the chain and inspect its logged error rather than retrying against unknown live state.

Owner-run command after review, **only if steps 1–4 have already succeeded on the server**:

```bash
cd ~/public_html || exit
php RD-11_cart-preorder-quantity_20260924.php &&
php CAT-004_trust-credit-copy_20260924.php &&
php RD-PP-META_status-card_20260924.php &&
php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

No commit/push command is included because the review may change the exact approved file set. The owner retains that decision and the production gate.
