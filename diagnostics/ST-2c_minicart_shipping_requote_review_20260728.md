# Review report — ST-2c mini-cart shipping re-quote

Date: 2026-07-28
Reviewer: Claude
Patch reviewed: `patches/ST-2c_minicart_shipping_requote_20260728.php`
Diagnostic input: `diagnostics/ST-2c_minicart_shipping_requote_report_20260728.md`
Handoff: none — Codex-direct diagnosis, no separate handoff file (same pattern
as `CHECKOUT-005` in `context-index.md`).

## Verdict

**Review OK; owner QA required — with one pre-deployment coordination item
that must be resolved first (see Critical finding).**

## Evidence boundary

Same as the coupon-threshold review: no owner cPanel backup/live-source
archive for `checkout-state.js` is present in this repository. Review is
based on the bounded patch diff plus the report's stated root cause. No PHP
interpreter is available in this environment to execute the runner; `node`
is also unavailable here, so the JS replacement is checked by structural
reading, not execution.

## Critical finding — SHA-256 preimage conflict with the coupon patch

Both this patch and `patches/ST-2c_coupon_shipping_threshold_refresh_validated_20260728.php`
(reviewed and passed earlier today) target the **same live file**,
`catalog/view/javascript/checkout-state.js`, each guarded by a whole-file
SHA-256 taken from a different snapshot:

| Patch | Expected pre-image SHA-256 for `checkout-state.js` |
|---|---|
| Coupon threshold refresh | `23378071030674F5CCDD34B039CDE51CD2D5C40ACC07ED2012BD2BB67A49D025` |
| Mini-cart re-quote (this patch) | `C291F81EE26E354CE51C34BA7C8694FA4F0CACCE46263B79509A989DA7EDFA6A` |

These are different hashes of the same file. Only one can match the file's
actual current state on the host. Whichever of these two patches runs
**second** will almost certainly fail with `sha256_mismatch` once the first
one has changed the file — this is a safe failure (the guard refuses to
write rather than corrupt anything), but it blocks deployment and needs a
plan, not a surprise mid-rollout.

The two patches touch different, non-overlapping functions
(`totalsChanged`/`couponChanged` vs `cartChanged`), so there is no logical
conflict in the actual code changes — this is purely a whole-file-hash
sequencing problem from generating two patches against two different
snapshots of the same file.

**Required before running either patch a second time:**

1. Confirm which of the two hashes matches the file currently live on the
   host (i.e., has the coupon patch already been executed on the host, or
   not?).
2. Run the matching patch first.
3. Ask Codex to regenerate the *other* patch's SHA-256 gate against the
   post-first-patch file content before attempting to run it — do not just
   retry it, it will fail again by design.

This is an owner/Codex coordination step, not a code defect in either patch.

## Patch-logic review

`cartChanged()` is rewritten to follow the exact pattern already reviewed
and approved in the coupon patch: increment `revision`, clear the transient
shipping-display/payment state, and call the existing
`bsCheckoutLoadShippingMethods({autoSelect: false, resaveCurrent: true,
quietAddressError: true, stateRevision: token})`. No order-write call, no
Hutko/payment/DB reference — consistent with the declared scope and with the
already-vetted `couponChanged()` implementation from the earlier patch.

Runner contract: SHA-256 guard, exact-anchor count check (`substr_count`,
not regex — fine, since the preceding whole-file SHA match already
guarantees byte-exact content, so this is a redundant-but-safe sanity
check), per-file backup before write, idempotency with self-delete on
`already_applied=yes` (correctly included from the start, unlike the
first draft of the coupon patch), self-delete after success. No `php -l`
equivalent needed since the only target is a `.js` file; the runner
explicitly logs `js_syntax=owner_run_required` rather than silently
skipping the concern — reasonable given no JS runtime is typically
available on a shared PHP host.

## Non-blocking findings

a. **No JS cache-bust bump.** An earlier patch in this same file's history
   (`ST-2c.3b`, per `diagnostics/ST-2c.3a-ST-2c.4_checkout-regression-patchset_review_20260720.md`)
   bumped the `checkout-state.js` cache-bust key in `checkout.twig` when
   changing that file. This patch does not touch any cache-bust reference.
   Recommend confirming whether this theme serves `checkout-state.js` with a
   versioned query string, and bumping it if so — otherwise browsers may
   keep serving the pre-patch JS after deployment, independent of the
   OpenCart server-side template cache.

b. **`addressSaved()`'s full behavior not independently checked.** The report
   states `cartChanged()` previously called `addressSaved()` "for its
   delivery re-quote side effect." Not verified here whether `addressSaved()`
   has other side effects (e.g., address-field validation) that are now
   dropped for the mini-cart-change path. Given a cart quantity/removal
   change has no address data to (re)validate, this is very likely a safe,
   intentional narrowing — flagging only because the underlying function
   definition isn't visible in the bounded diff.

## Answers relevant to this patch

- No DB/payment/Hutko/order-write changes — confirmed absent from the diff.
- Rollback: single-file backup under `_patch_backups/<id>-<ts>/`; restore
  that file to undo.

## Owner QA (per the report, unchanged) + one addition

Run the report's 5-item QA list. Add: before running this patch, resolve the
SHA-256 sequencing question above with Codex — do not attempt to run both
patches back-to-back without that confirmation.
