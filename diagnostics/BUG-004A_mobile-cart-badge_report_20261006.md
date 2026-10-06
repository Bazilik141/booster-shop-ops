# BUG-004A — mobile cart badge, variant A

Date: 2026-10-06
Executor: Codex. Scope: A only, delivered before UX-003 runner 9 as a separate runner.

## Outcome and source

The trigger shows a white quantity badge below 992 px: 1–9 or `9+`; zero hides it.
Quantity is the existing Twig sum of `product.quantity`, not product-row count or a
parsed currency label. Responsive accessible labels use the full quantity and the
existing total format; from 576 through 991 they include the amount. Desktop keeps
its original accessible label and appearance.

Source: owner's `ux003-r9-bug004a-live-20261006-223210.tar.gz` (post-runner-8b).
Root cause: `common/cart.twig:1-4` already computes the quantity, but line 16's trigger
renders only icon/text. The existing fragment reload path already updates this Twig
template. Its regenerated data and markup are reused after add/edit/remove.

## Files changed

- `catalog/view/template/common/cart.twig`: trigger badge/wrapper/data attributes and
  one responsive-label updater. Drawer content, swipe/lock logic, AJAX endpoints and
  existing quantity/remove handlers remain unchanged.
- `catalog/view/stylesheet/boostershop-ds.css`: consolidate historical cart-trigger
  selectors into one source block; variant A styles, 575/576 and 991/992 behavior.
- `catalog/view/template/common/header.twig`: CSS token only.

Deliverable: `patches/BUG-004A_mobile-cart-badge_20261006.php`.
Evidence: `diagnostics/UX-003_BUG-004A_20261006_evidence/`.

## Source-rule and signature review

Prior rules at DS lines 914, 1056, 1075, 1158, 2700, 2861, 2921 and 3021–3075
repeatedly define the same trigger and old quantity styles. The 2026-10-04 polish
runner adds the split label and 769–959 lead hiding. Relevant owner batch rules and
source declarations were inspected before edits.

Owned cart selectors were removed from those rules, preserving shared ghost/header
selectors, and consolidated at their source with reversible markers. Existing
important priorities remain necessary against shared Bootstrap/header rules; this
does not stack a new competing override over the old cart rules. Desktop computed
width/height/colors/border/radius/padding/gap/label font/weight/icon/text match the
unmodified source exactly in the browser fixture.

Absolute positioning is the approved variant A badge: 16 px, inside the 44 px trigger
at 3/3 below 576; -7/-9 relative to the icon at 576–991. The desktop badge is hidden.
No timer was added to production badge logic; it updates on initial DOM readiness,
fragment evaluation, existing cart event and responsive breakpoint changes.

## Validation

Shared local matrix: **25/25 browser cases**, plus runner apply/idempotency/rollback/
safe-failure checks. See the UX-003 report and JSON evidence for exact coverage.

Badge checks: 390/575/576/768/991/992/1000/1440; quantities 0/1/3/12/21; full
plural/amount accessible labels; 44 px trigger; long amounts; unchanged desktop;
quantity edit/remove with drawer remaining open; category-card add and toast after
grid replacement. No new JS exceptions were observed.

`php -l`: both runners pass on PHP 8.3.30. Local Twig: 3.28.0. Runner syntax targets
PHP 8.0; hosting runtime and actual Twig are checked by the runner before writes.
No production PHP 8.0 runtime/browser pass is claimed.

Dry-run passes; apply reports `done=ok`; successful runner self-deletes. Repeat
reports `already_applied=yes` and changes no target bytes/cache token. Independent
rollback orders preserve the sibling patch. Partial markers and source drift abort
before writes. A fixture-injected write failure after the cart write restores all
targets and reports `restore=ok`.

## Risk and owner QA

Risk: Medium — shared header CSS and cart display; business logic untouched.
Owner still needs real category/product add, edit/remove, refresh, drawer/toast and
checkout-entry QA without payment/order creation, plus Tier 1 page checks.

Scoped `bs-checkout-smoke` checklist:

| # | Test | Expected / applicability | Actual |
|---|---|---|---|
| 1 | Register/cart/checkout | Add from category/product, correct badge, checkout entry. Registration flow is unchanged and n/a to this patch. | Local category add passed; real product/add/entry pending owner |
| 2 | Auto First15 | n/a: no new-user/coupon changes | Not run |
| 3 | First15 reuse | n/a: no coupon changes | Not run |
| 4 | Invalid coupon | n/a: no coupon changes | Not run |
| 5 | Nova Poshta | n/a: no shipping changes | Not run |
| 6 | Order button | n/a: no checkout-form changes | Not run |
| 7 | Hutko return/session | n/a: no payment/session changes | Not run |
| 8 | Success redirect | n/a: no order/redirect changes | Not run |
| 9 | Clean JSON | Existing local add/edit/remove responses remain usable by unchanged handlers; no PHP/endpoint changes. Verify real cart requests. | Synthetic local responses passed; production pending owner |
| 10 | Email | n/a: no order/email changes | Not run |
| 11 | Checkbox/fiscalization | n/a: no payment/order-status changes | Not run |

This is a local synthetic fixture, not production/staging purchase proof. No real
payment, order, email or fiscalization action was performed or suggested.

## Run / rollback

Upload after review and apply this runner before runner 9. The single combined run
and cache-clear command is in the UX-003 report. Standalone command:
`php BUG-004A_mobile-cart-badge_20261006.php` from `~/public_html`.

Backup: `_patch_backups/BUG-004A_mobile-cart-badge_20261006-<timestamp>-<nonce>/`.
On broken add/header/drawer behavior, re-upload this exact PHP file and run
`php BUG-004A_mobile-cart-badge_20261006.php --rollback`, followed by cache cleanup.
The surgical rollback preserves runner 9 and compatible unrelated changes; it aborts
if an owned segment was modified. Avoid whole-file shared CSS/header restoration
after runner 9 has been applied. Rollback is also self-deleting on success.

No commit/push, roadmap/Notion status write, deployment or live cart mutation occurred.

## Owner acceptance and closure — 2026-10-06

The owner confirmed the delivered patches work on the site and authorized commit,
push and roadmap updates. BUG-004 (A and B) is Done in canonical Notion and the
dashboard mirror. A was delivered by this separate runner; B had already passed
the owner's phone check without a new swipe patch. Production acceptance is
owner-reported; no individual checklist result or remote deployment hash is inferred.
The no-write statement above describes the earlier implementation phase.
