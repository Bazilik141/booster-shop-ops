# Claude review — TECH-015 WP1: GA4 `purchase` on order success

Date: 2026-09-07
Reviewer: Claude (chat). Patch author: Codex. Separate surfaces, per CLAUDE.md.
Patch already deployed to production by the owner on 2026-09-06 — this is a
post-deploy review, not a pre-deploy gate.

## Verdict

**Review OK; owner QA required.**

WP2 may start. The two open runtime items below do not touch the checkout page
and cannot interact with WP2, but they stay open on TECH-015 and block closure.

## Independent verification performed

Not taken from the Codex report — reproduced here.

1. Pre-patch sources: `catalog/controller/checkout/success.php` and
   `catalog/view/template/checkout/success.twig` extracted from
   `backup-9.3.2026_21-30-35_boosters.tar.gz` hash to exactly the two "before"
   SHA-256 values the runner refuses to run without.
2. The runner's four nowdoc anchor/replacement pairs were extracted and applied
   to those backup originals outside the runner. Each anchor occurs exactly once.
   The rebuilt files hash to
   `90bea312550bdd42b37129273e7a5aa8a3b80fc4ccc11a70e8ed6d053077737b` and
   `4b61912437fcb79520425d8520453deb1ff1663bd86443ef045aef7971833252` — the same
   "after" hashes the production runner printed. The deployed files are therefore
   byte-for-byte what the audited source plus this patch produce, with nothing
   else changed.
3. Rebuilt template contains `pushEventData('purchase'` exactly once.

## Findings against the handoff's five review questions

**1. Runner safety.** Sound. CLI-only, refuses to run in the repo tree
(`.git` present), refuses symlinked targets, hard SHA-256 gate on both sources
plus a re-check immediately before each write, write-verify by hash, PHP lint on
the generated file *before* the live write and again after, ordered restore of
already-written files if a later write throws, timestamped+random backup
directory holding both `original/` and `generated/`, `already_applied=yes`
short-circuit that still verifies the applied hashes, self-delete. PHP 8.0-safe:
no `never`, `enum`, `readonly`, no 8.1+ syntax; array destructuring and `??` are
7.x. No database, payment, order-status or vendor-extension write.

**2. Payload source and `value` rule.** Correct. The block sits inside
`if ($order_info)`, i.e. only after `canShowSuccessOrder()` authorized and loaded
the order; it reuses the already-loaded `getProducts()` / `getTotals()` results
and never reads `$this->cart`. `value = total − tax − shipping` matches the
vendor module's own purchase convention. Tax and shipping accumulate *before*
the R-11-FIX `continue` that suppresses zero-value shipping rows from the display
array, so the display filter cannot corrupt the payload. `shipping` sums both
`shipping` and `pinta_nova_poshta` codes. Coupon discount is inside the `total`
row, so `value` is net of it. Every money field is converted with the order's
`currency_value`, so a non-UAH order is not mis-stated. `$ga4_total` falls back
to `$order_info['total']` when no `total` row exists.

**3. Twig placement deviation.** Necessary, and the report's reason is confirmed:
in the live template `{{ text_message }}` occurs once, at line 153, inside the
`{% else %}` no-order fallback branch — where a purchase payload can never
exist. The snippet at lines 160–163 sits after both branches and is guarded by
`ga4_purchase_payload`. The handoff's instruction was wrong for this file; the
deviation is right and was documented rather than silently taken.

**4. Dedup and PAY-003 guards.** Correct. The marker
`bs_ga4_purchase_emitted_order_id` holds one int and is compared with strict
`!==` against the current order id, then overwritten — so it can never suppress a
different order, and a customer placing two orders in one session gets two
events. `!$pay003_recovery` prevents emission on the historical
credit-recovery render. JSON is encoded with
`JSON_HEX_TAG|HEX_AMP|HEX_APOS|HEX_QUOT`, so `|raw` in the template cannot be
used to break out of the `<script>` block.

**5. Sufficient to proceed.** Yes for WP2. Not yet for closing WP1 — see below.

## Open items — WP1 cannot be closed on this evidence

1. **Authorized PAY-003 recovery render was never exercised.** Order #341 returned
   `Посилання недоступне.`, which proves only the access-control rejection path.
   The guard itself is a single boolean already governing other behaviour on that
   path, so code-level confidence is high; runtime proof is still missing. If the
   guard were wrong, the cost is a duplicate `purchase` for an old order —
   inflated revenue, recoverable, not a storefront break.
2. **Hutko return unverified, and correctly not forced.** Do not create a real
   payment to close this. Close it at the next genuine Hutko order.

## New finding not in the Codex report — verify at the next real Hutko order

`canShowSuccessOrder()` authorizes by ownership and by the signed return cookie.
It does **not** gate on order status. The credit flows are safe here — PAY-003
redirects away unless `$pay003_view['confirmed']` — but for Hutko, whether an
unconfirmed or failed payment can still reach `checkout/success` rather than
`checkout/failure` was not established in this review. If it can, GA4 will book
revenue for an order that never gets paid.

This is not a regression: the vendor module would have behaved the same way. It
is a data-accuracy question to answer with evidence, not to patch speculatively.
Answer it while closing open item 2 above, and only then decide whether GA4
needs a status gate or a refund signal.

## Non-blocking recommendation

The inline snippet calls `ps_dataLayer.pushEventData(...)` unguarded. If the GA4
module is ever disabled or reinstalled, `ps_dataLayer` becomes undefined and the
order-success page throws a console `ReferenceError`. The page still renders — the
script is after the content — but this is the page a customer lands on after
paying, so it should not be able to throw. Wrapping the call in
`if (window.ps_dataLayer) { … }` is a one-line hardening. Fold it into the next
patch that touches `success.twig`; do not raise a separate patch for it now.

The payload also omits `affiliation`, `item_brand` and `item_category`, which the
vendor module includes elsewhere. Revenue and conversion are unaffected. Optional.

## What the owner must do

Nothing on this review. The remaining WP1 work is the two open runtime items,
both of which wait for real traffic, plus the WP2 copy decision recorded in the
Codex report.
