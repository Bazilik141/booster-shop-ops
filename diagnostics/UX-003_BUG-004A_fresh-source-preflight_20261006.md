# UX-003 runner 9 / BUG-004A — fresh-source preflight

Date: 2026-10-06
Workspace: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`

## Scope and evidence

Read-only review of the owner-supplied handoff ZIP and fresh source archive
`ux003-r9-bug004a-live-20261006-223210.tar.gz`. All 13 requested files are present.
Sources were extracted to `work/ux003-r9-bug004a-20261006/source/` for review.
No production source, patch runner, task status, Git history or external state was changed.

The archive's `catalog/view/template/product/category.twig` SHA-256 is
`c7c658d677a120ab3895e37547de7f782b96b8ff42986db2309b177f1f50d54f`.
It equals the recorded runner 8b output in
`diagnostics/UX-003_category-heading-fouc_report_20261005.md` (line 21).
The existing batch index records owner deployment and QA for runner 8b.
This verifies source correspondence; it is not independent live browser QA.

## Confirmed implementation inputs

- The filter template exists at `extension/opencart/catalog/view/template/module/filter.twig`.
  Its checkbox handler builds the URL from the server-provided `action`, removes `page`,
  and navigates after a 250 ms debounce. Preserve that URL construction.
- `catalog/controller/product/category.php:433-446` calculates the filtered total and
  exports `product_total`. `category.twig:484` renders it in the category heading.
  Counts must come from the response, not from the number of cards on the current page.
- `common/cart.twig:1-4` already sums `product.quantity` into `bs_cart_qty`.
  The trigger markup (line 16) contains no badge. Fragment replacement via
  `common/cart.info` and `bs:cart-updated` is present. Reuse the existing quantity source
  and refresh mechanism without changing purchase logic or drawer behavior.
- Category pagination is enhanced to a load-more button, not a visible stock pager.
  The current load-more closure retains references to the original grid and reads the
  next URL from `head link[rel=next]`. Runner 9 must explicitly rebind or coordinate it
  after grid replacement and cancel stale load-more work during a filter/sort request.
- The product grid is conditional on `products`; a zero-result response has no
  `#product-list`. Runner 9 needs a stable response wrapper for both populated and
  empty states. Otherwise the mandatory missing-grid fallback would prevent the
  requested AJAX empty state.
- Build order from the existing owner decision: post-8b source -> BUG-004A -> runner 9.
  Deliver two separate runners. Coordinate shared CSS/cache tokens and independent
  rollback; preserve the already applied sibling patch during a later rollback.

## SEO risk gate and required owner decision

Risk: **High**, because the task affects filtered category rendering/history and
pagination metadata. The source review uses `.claude/skills/bs-seo-risk-gate/SKILL.md`.

Evidence:

- `catalog/controller/product/category.php:527-543`: canonical always points to the
  clean base category. Presence of `filter`, `sort`, `order` or `limit` changes robots
  from `index,follow` to `noindex,follow`. Pagination prev/next also varies by response.
- `catalog/view/template/common/header.twig:18-20`: the server emits the robots tag.
- The new handoff permits replacing the grid/pagination/count and pushing history,
  but explicitly prohibits touching meta robots. A body-only update would leave
  stale metadata when moving between clean and filtered states.

Concrete proposed clarification: allow runner 9 to synchronize the **exact server
response values** of `meta[name=robots]` and `link[rel=prev/next]` after successful
response validation. Preserve the server policy and canonical. No controller,
robots.txt, sitemap, redirects, schema or Merchant feed edits are proposed.

Alternative if permission is declined: use normal navigation whenever response SEO
metadata differs. This preserves metadata through server rendering, but the first
filter from a clean category (and reset back to clean) will reload the page.

Owner decision: after this preflight, the owner directed the executor to choose the
proper solution for the site. The executor selected exact server-response metadata
synchronization, preserving the existing server policy and canonical. This resolves
the earlier pending clarification. See the implementation reports for final evidence.

The skill's mandatory staging requirement applies to canonical/redirect/sitemap
changes. None is proposed. Local fixtures can establish DOM/history behavior;
live before/after and owner QA remain separate deployment gates. The owner is the
only production deployment authority.

## Validation and remaining gates

Completed: archive inventory, source inspection, category SHA correspondence,
quantity/count source identification, source-level SEO gate.

At the preflight stage: implementation, lint, executable fixtures, idempotency/rollback,
browser QA and production deployment had not been performed. The subsequent
implementation reports supersede this stage's validation status. No production pass
is claimed by this preflight.

Required after implementation:

- Exact stock filter/sort URLs, populated/empty/no-module cases and load more.
- Back/Forward checkbox/sort/count restoration, request cancellation, offline fallback.
- Metadata equality with direct server navigation, including reset to a clean URL.
- Responsive and keyboard/focus checks at the handoff widths and 575/576, 991/992 edges.
- Badge quantities 0/1/3/12/21, cart fragment refresh, preserved drawer/toast, desktop.
- Focused syntax, idempotency and rollback checks before delivery.
- Owner cart/add/edit/remove and checkout-entry smoke, without payment or order creation.
  `.claude/skills/bs-checkout-smoke/SKILL.md` informs that checklist; unrelated coupon,
  payment-return, fiscalization and email cases are not implementation evidence for
  this display-only badge and must be marked not applicable with reasons.

No commit/push, status update, deployment or live business-data mutation is authorized.
