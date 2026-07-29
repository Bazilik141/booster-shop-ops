# Review report — CHECKOUT-009 Stage 1, round 2

Date: 2026-07-29
Reviewer: Claude
Round 1 review: `diagnostics/CHECKOUT-009_stage1_review_20260729.md`
Reviewed: updated `patches/CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.php`,
updated `diagnostics/CHECKOUT-009_stage1_coupon-classification-single-writer_report_20260729.md`,
`plans/CHECKOUT-009_checkout-behaviour-register_20260729.md`

## Verdict

**Review OK; owner deploy and QA required.**

All three blocking items from round 1 are resolved at the source, not papered
over. One residual edge is recorded below as a non-blocking follow-up.

## Round 1 items — disposition

**1. Latching `activeAddressRevision` — fixed.** The self-healing guard is at
the top of `requoteDeliverySelection()` (patch lines 362–367): a non-current
active address revision, and its obsolete deferred request, are released before
the gate is evaluated. No timer and no new state concept were introduced. The
harness gained `stale_address_revision_manual_coupon_requote=pass`, which is the
case I asked for: address commit in flight → `cartChanged()` advances the
revision → stale `shippingSaved` → manual coupon apply still produces exactly
one requote. Dropping (rather than replaying) a deferred requote after a
superseding transition is correct, because the superseding transition requotes
against the current coupon/cart state itself.

**2. No other `totalsChanged('coupon')` caller — verified.** Sweep across the
complete extracted archive (286/286 files) found only the definition in
`checkout-state.js` and the single `checkout-reskin.js` caller that the runner
converts to the typed `promoResult()` path. The compatibility adapter therefore
cannot silently swallow a coupon mutation.

**3. `route=` present on every address-save request — verified.** 92 guest and
46 logged-in HAR entries inspected; the only captured address-save target is
`POST index.php?route=checkout/register.save&language=`, and no other
`checkout/shipping_address.*` sub-action appears in either capture. The live
Twig source constructs all three callbacks — `shipping_address.address`,
`shipping_address.save`, `register.save` — with a `route=` query parameter, so
the narrowed matcher cannot silently miss a writer.

Note the honest limitation Codex states: the HARs did not capture a stock
`shipping_address.*` call, so items 3's proof rests on source construction for
those two routes rather than on observed traffic. Owner QA covers this
empirically — if the narrowed matcher ever missed them, the guest flow would
fail immediately and visibly, not silently.

## Residual edge — non-blocking follow-up

The self-heal releases the flag only when the active address revision is **not
current**. If an address quote fails without any other transition advancing the
revision, `activeAddressRevision` stays equal to the current revision, so it is
not released and a later promo mutation is still deferred.

Practical impact is close to nil: in that state either no delivery is committed
(so `hasCommittedDelivery()` returns false and no requote was owed anyway), or
the customer is already looking at a visible quote error and must retry the
address, which fires `AddressCommitted` and resets the flag. It is not a silent
money-affecting path like the round 1 finding.

Recommended for the next authorized round, not this one: release the flag in the
quote-failure/no-methods path as well, so the invariant becomes "the flag is
released whenever the commit it represents can no longer complete", rather than
"whenever it is superseded".

The two round 1 minor notes remain open by explicit decision and are acceptable
in this bounded correction: no fallback payment-method load if the shipping
loader is unavailable, and a missing `mutated` field on an apply/remove response
is still read as `false` rather than raised.

## Unchanged from round 1 and still confirmed

Scope discipline (only the approved `mutated` field in the controller; model,
eligibility and grant timing untouched; no DB, payment transport, credit gate,
CRM, Telegram, analytics registration or Stage 2 authority file), guest ordering
correction, honest `mutated` derived from a real `session.coupon` before/after
comparison, rule 5, Pinta timer removed with the third-party overwrite warning
recorded in the runner header and register row 23, `alterRedirectShippingAddressSave()`
left registered, both scripts moved to `v=checkout009-stage1-20260729` in the
same unit, proven rollback via injected post-write failure.

Accounting re-checked: `34 preserved / 6 replaced / 0 removed / 0 UNKNOWN-PURPOSE
/ 40 of 40`; 27 of 27 before-image markers retained; new marker present in all
five targets. Only the `checkout-state.js` after-hash changed between rounds
(`8c3b87ca…` → `1bafab52…`), consistent with a change confined to that file; all
five before-hashes are unchanged, so the runner still guards the same live
source.

I did not re-run Codex's hashes, lint, `node --check`, marker diff, idempotency
or rollback tests.

## Not proven by this review

No server, database, live site or HAR file access. Production cache delivery,
real Nova Poshta responses, the live ₴2000 matrix, analytics delivery, order
creation, CRM and Telegram side effects remain owner post-deploy QA.
