# Review report — CHECKOUT-009 Stage 1 implementation

Date: 2026-07-29
Reviewer: Claude
Handoff: `handoffs/handoff_CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.md` (incl. Amendment 1 and the narrow controller exemption)
Reviewed: `patches/CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.php` (972 lines),
`diagnostics/CHECKOUT-009_stage1_coupon-classification-single-writer_report_20260729.md`,
`plans/CHECKOUT-009_checkout-behaviour-register_20260729.md`

## Verdict

**Return for changes — one bounded code change plus two live-source
verifications. Do not deploy this build.**

The design is right and the execution is close. One state variable can latch
permanently, and when it does, a later coupon apply/remove silently skips its
shipping requote — a stale free-shipping result at the ₴2000 threshold, i.e.
the same money-affecting class we have spent this task removing. The fix is
small and needs no new evidence round.

Phase 0 is satisfied: guest and logged-in HARs present with response bodies and
no order write; theme-override export returned zero checkout routes (so the
file-level mapping is authoritative); the First15 flag is derived from a real
before/after comparison of `session.coupon`, which is exactly what Amendment 1
required. Scope discipline holds: only the approved `mutated` field was added to
the controller, the model and grant timing are untouched, and no DB, payment
transport, credit gate, CRM, Telegram or Stage 2 authority file is changed. I did
not repeat Codex's hashes, lint, `node --check`, marker diff or idempotency
runs.

## Must fix before deploy

### 1. `activeAddressRevision` has no release path when the address commit never
completes under its own token (blocking)

`activeAddressRevision` is set in `AddressCommitted()` (patch line 234) and in
the bootstrap branch (line 439). It is cleared in only three places: a newer
`AddressCommitted` (232), the bootstrap branch (440), and `finishAddressCommit()`
(288) — and the last one runs **only** from `shippingSaved()` **after** its
`isCurrent(token)` guard has already passed.

So any address commit whose shipping save never arrives as the current revision
leaves the flag latched. Realistic triggers:

- the customer changes a mini-cart quantity while the address quote is in
  flight — `cartChanged()` advances `revision`, the address's `shippingSaved`
  arrives stale, returns at the `isCurrent` guard (patch line 302) and never
  reaches `finishAddressCommit`;
- the quote fails or returns no methods (Nova Poshta error, invalid address).

Consequence: `requoteDeliverySelection()` sees `activeAddressRevision !== null`
(line 361) and from then on **defers every promo mutation forever** — including a
manual `apply`/`remove`, since `couponChanged()` now routes through the same
function. The coupon applies server-side, the shipping quote is never refreshed,
and the UI shows no error because the deferred branch returns before touching
display text or payment state. It self-heals only if the customer edits the
address again.

Recommended minimal correction — a self-healing guard, no new timer, no new
concept, at the top of `requoteDeliverySelection()`:

```js
if (activeAddressRevision !== null && !isCurrent(activeAddressRevision)) {
  activeAddressRevision = null;
  deferredDeliveryRequote = null;
}
```

Apply the same release in `shippingSaved()`'s stale branch (before its early
`return false`) if Codex prefers releasing at the point of detection. Either
placement is acceptable; the invariant to establish and state in the report is:
**a non-current `activeAddressRevision` is never allowed to gate a later
requote.**

Add one deterministic harness case to the existing contract set: address commit
in flight → `cartChanged()` advances the revision → stale `shippingSaved` →
manual coupon `apply` must still produce exactly one requote.

### 2. Verify no live caller still passes `'coupon'` to `totalsChanged()` (blocking, verification only)

The rewritten `totalsChanged()` (patch line 415) ignores its `source` argument
entirely and never requotes. That is correct for the compatibility adapter, but
it means any *other* live caller still invoking `totalsChanged('coupon', …)`
after a real mutation now silently loses its requote. The patch only proves the
one caller it replaced in `checkout-reskin.js`.

Grep the whole live archive — not just the five changed targets — for remaining
`totalsChanged(` callers, list them with the argument each passes, and confirm
none of them means "a coupon mutation happened". If one does, it must route
through `promoResult()`.

### 3. Confirm every address-save request actually carries `route=` (blocking, verification only)

The broad substring matcher was replaced with `bsCheckoutRequestRoute()` (patch
line 714), which extracts the route **only** from a `route=` query parameter.
The narrowing is right, but if any address-save request reaches the server
through a rewritten/SEO URL without `route=` in the query string, the route
resolves to `''`, the callback never fires, and the address is never committed —
CHECKOUT-009 again, on a different path.

Both HARs are already in hand. Confirm from them that every
`checkout/shipping_address.save`, `checkout/shipping_address.address` and
`checkout/register.save` request carries `route=` (in either `.` or `|` form),
and state it in the report. Also confirm the HARs contain no other
`checkout/shipping_address.*` sub-action that previously matched the substring
and would now be dropped.

## Minor, non-blocking

- In `shippingSaved()`, when `requoteScheduled` is true the payment-method load
  is intentionally deferred to the requote (patch line 319). If
  `window.bsCheckoutLoadShippingMethods` were unavailable at that moment,
  `requoteDeliverySelection()` returns `null` after having cleared payment state
  and display text, and nothing reloads payment methods. Practically
  unreachable, but a one-line fallback (load payment methods when the requote
  returns `null`) removes the last silent dead end in this file.
- `promoResult()` defaults its argument to `{kind:'summary', mutated:false}`.
  Safe, but it means a malformed/absent server field is interpreted as "no
  mutation". Given the `mutated` flag now carries money-affecting meaning,
  prefer treating a *missing* field on an `apply`/`remove` response as an
  error/log case rather than silently as `false`.

## Confirmed good

- The ordering correction is real: `AddressCommitted` now starts **before**
  `bsCheckoutRefreshPromoCouponSummary()` in the guest `register.save` success
  handler (patch lines 756–778). This is the exact sequence that produced the
  incident.
- `mutated` is computed from an actual `session.coupon` before/after comparison
  (patch lines 159, 177), so a repeat summary is honestly `false` — the
  idempotency requirement is met at the source, not by client-side guessing.
- Rule 5 is implemented: no committed delivery → no requote
  (`hasCommittedDelivery()`, line 369).
- The Pinta 250 ms timer is deleted and replaced by the shared callback with a
  documented third-party-overwrite warning in the runner header and register
  row 23; no replacement timer anywhere in the diff.
- `alterRedirectShippingAddressSave()` left registered and untouched.
- Both scripts move to `v=checkout009-stage1-20260729` in the same unit as the
  source change, satisfying the cache-key sequencing rule.
- Rollback proven by an injected post-write failure returning
  `rollback=restored` with all five hashes back to the originals.

## Not proven by this review

I did not access the server, the database, the live site or the HAR files
themselves; items 2 and 3 above are exactly the checks I cannot perform from the
repository. Production cache delivery, real Nova Poshta responses, the live
₴2000 matrix, analytics delivery, order creation, CRM and Telegram side effects
remain owner post-deploy QA, per the report's own "Not locally proven" section.
