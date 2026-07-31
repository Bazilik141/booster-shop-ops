# Review report — ST-2c mini-cart shipping threshold alignment

Date: 2026-07-29
Reviewer: Claude
Patch reviewed: `patches/ST-2c_minicart_shipping_threshold_alignment_20260729.php`
Diagnostic input: `diagnostics/ST-2c_minicart_shipping_threshold_alignment_report_20260729.md`
Handoff: none — Codex-direct diagnosis.

## Verdict (updated after owner + Codex follow-up, 2026-07-29)

**Review OK; deploy promptly; owner QA required.**

Owner confirmed the Round 2 coupon patch is already live on the host — see
"Critical open question" below, now answered. Codex supplied empirical proof
(not another static assertion) for the `getTotals()` call: an isolated
harness reproducing the real cPanel `Loader`/`Proxy` classes shows the direct
call yields `total = 0` with no rows/taxes populated, while the
closure-invocation form yields `total = 2100` with a populated total row and
tax entry. This matches my Round 3 theoretical correction exactly and
resolves required item 2 below.

Required item 3 (confirm `grand`'s declaration/scope in the unpatched part
of `checkout-reskin.js`) is **still not addressed** in the updated report —
see "Still open" below. Given (a) production currently has a confirmed
silent wrong-total defect that this patch fixes, and (b) the unresolved item
is a fail-loud risk (a missing `grand` throws a visible `ReferenceError`,
it does not silently miscalculate), I'm not blocking deployment on it —
recommend deploying now and having the owner's QA include a browser-console
check as a safety net, per "Still open" below.

## Correction to my own earlier review

This patch reverts the `getTotals()` call in
`pinta_nova_poshta.php::getBoosterCartTotalUah()` back to the exact form it
had before I asked Codex to change it in my Round 1 review of the coupon
patch (`diagnostics/ST-2c_coupon_shipping_threshold_refresh_review_20260728.md`).
Having re-analyzed this with OpenCart's actual model-loading architecture in
mind (not just generic PHP-class assumptions), I believe **my Round 1 finding
was wrong** and this patch's reversal is very likely correct. Details below.

### Why I now believe the wrapped-parens form is required here

`$this->model_checkout_cart` is not a plain instance of the Cart model class.
OpenCart's `Loader::model()` loads models through a `Proxy` object: it
instantiates the real model once, then exposes each of its public methods as
a Closure stored as a dynamic property on the Proxy (e.g.
`$proxy->getTotals = Closure::fromCallable([$realModel, 'getTotals'])`), and
the Proxy implements `__call()` so that ordinary `->method()` syntax still
works by dispatching to that stored closure.

This matters because **PHP's `__call($name, $arguments)` cannot forward
by-reference parameters back to the caller.** `$arguments` is a plain value
array assembled by the engine at the call site; even though the underlying
closure may declare `&$total`, invoking it via `call_user_func_array()` from
inside `__call()` only binds the reference to a copy living in that local
`$arguments` array — not to the original variable in the caller's scope. This
is a long-standing, documented PHP limitation of magic `__call()`, independent
of OpenCart.

`getTotals(array &$totals, array &$taxes, float &$total)` populates its
result entirely through by-reference output parameters. So:

- `$this->model_checkout_cart->getTotals($totals, $taxes, $total);` (the form
  I asked for in Round 1) dispatches through the Proxy's `__call()` — `$total`
  in `getBoosterCartTotalUah()` is **not actually updated** and stays `0`.
  This does not throw. It is a silent wrong-value bug, not a crash.
- `($this->model_checkout_cart->getTotals)($totals, $taxes, $total);` (the
  original form, and what this patch restores) evaluates the property first
  via `__get` (retrieving the real Closure) and invokes it directly, with no
  `__call` boundary in between — by-reference binding works normally.

This is consistent with the surrounding code: `$this->cart->getTaxes()` (a
few lines above, unchanged) uses plain method syntax without issue, because
`$this->cart` is the core `Cart` *library* object, registered directly on the
registry (not loaded via `Loader::model()`), so it is a real object with real
methods — no Proxy, no `__call`, no reference-forwarding problem. The
asymmetry between the two calls in the same function is not an inconsistency;
it reflects a real difference between a library object and a Proxy-wrapped
model.

### Why I got this wrong in Round 1

I evaluated the call using generic PHP semantics (real object, real
method) without accounting for OpenCart's specific lazy-model-loading Proxy
mechanism, and without execution access to confirm either way. I flagged the
wrapped form as broken with high confidence; it should have been flagged as
"needs confirmation against OpenCart's actual model-loading behavior for this
build" instead. Codex's original Round-0 patch had this right the first
time; my review request made it wrong, and Round 2 endorsed that regression
using "the fix compiles and the crash theory would be fail-loud" reasoning —
which doesn't apply to a silent wrong-value bug.

## Critical open question — is the wrong version already live?

This patch's Pinta anchor searches for the literal text
`        $this->model_checkout_cart->getTotals($totals, $taxes, $total);`
— i.e. the **direct-call form that only exists in the file after the Round 2
coupon patch was applied.** The original (pre-any-ST-2c-coupon-patch) file
would have had neither form of this line in this shape (the whole
`getBoosterCartTotalUah()` function was introduced by the coupon patch). This
strongly implies the Round 2 coupon patch has already been run on the live
host, and that this alignment patch is a direct follow-up correction to a
live regression, not a pre-deployment catch.

**Please confirm directly: has
`patches/ST-2c_coupon_shipping_threshold_refresh_validated_20260728.php`
already been executed on the live host?** If yes, Pinta's free-shipping
threshold check has likely been silently comparing against `0` (not the
real cart total) since that deployment — meaning the threshold behavior in
production right now may be wrong in one consistent direction (either always
showing paid shipping or always showing free shipping, depending on the
comparison operator), independent of the coupon scenario this whole chain
was originally trying to fix. This raises the priority of deploying this
alignment patch from "next in queue" to "corrects a live defect."

**Answered (2026-07-29): yes**, the owner confirmed the coupon patch is
already on the host. Deploying this alignment patch is now a live-defect
correction, not a pre-emptive fix. The owner has not yet run this new
alignment patch.

## Resolved — `grand` scope in checkout-reskin.js (2026-07-29)

Closed empirically. Report addendum ("JavaScript scope proof" section):
`buildSummaryView()` spans lines 1169–1323 in the patched fixture; `var
grand = null;` is declared at line 1218; the new `renderedPayableTotal =
grand ? ...` use is at line 1267 — both inside the same function, confirmed
by a Node scope check, plus `node --check` passing. No `ReferenceError` risk.
All three required pre-deployment items are now closed.

## Deployment status (2026-07-29)

Owner confirmed: patch deployed, QA per the report's 5-item list passed. No
outstanding items on this specific patch.

## Second change — checkout-reskin.js summary widget

```js
var renderedPayableTotal = grand ? parseMoney(grand.value) : NaN;
var subtotalAmount = isFinite(renderedPayableTotal) && renderedPayableTotal >= 0
  ? renderedPayableTotal
  : (isFinite(serverSubtotal) && serverSubtotal >= 0
    ? serverSubtotal
    : (subtotal ? parseMoney(subtotal.value) : 0));
```

Root cause as stated (progress widget kept showing a coupon-cached subtotal
instead of the fresh post-mini-cart-change total) is plausible and the fix
is a reasonable, backward-compatible priority order: prefer a fresh rendered
grand total, fall back to the previous `serverSubtotal` source, fall back
further to `subtotal.value`. Low risk by construction (degrades to the old
behavior if the new source is unavailable, rather than replacing it).

Not verifiable from the bounded diff: whether `grand` is actually in scope
at this point in the function (it's referenced but not declared/assigned in
the shown anchor, so it must come from surrounding code not visible here). If
`grand` is not in scope, this throws a `ReferenceError` in the browser at
runtime — a fail-loud, console-visible break of the progress widget, not a
silent miscalculation. `node --check` (mentioned in the report) only
validates syntax, not variable scope/existence, so it would not catch this
either way. Recommend Codex confirm `grand`'s declaration is present in the
unpatched surrounding code of this function, and the owner's QA include a
browser-console check during the mini-cart-quantity-change steps.

## Runner contract

SHA-256 guards for both files, exact single-occurrence anchor check via
`substr_count` for each, per-file backups before write, `php -l` on the
Pinta file only (JS again logged as `js_syntax=owner_run_required`, no host
check — consistent, accepted limitation as in prior patches), rollback of
both written files if the PHP lint fails, idempotency with self-delete
included from the start. Contract is sound and consistent with the prior two
patches in this chain.

## Required before deployment — status

1. **Owner: confirm whether the Round 2 coupon patch has already been run on
   the live host.** ✅ Answered — yes, already deployed. Live-defect priority.
2. **Codex: provide one empirical confirmation, not another static
   assertion, on this specific line.** ✅ Done — isolated `Loader`/`Proxy`
   harness against the real cPanel snapshot: direct call → `total=0`,
   closure-invocation → `total=2100` with populated rows/taxes. Matches the
   Round 3 theoretical correction.
3. **Codex: confirm `grand`'s declaration/scope** in the unpatched part of
   `checkout-reskin.js`. ❌ Still not provided — see "Still open" above.
   Non-blocking (fail-loud risk) but not resolved either.
4. Run the report's 5-item owner QA, adding a browser-console check during
   mini-cart quantity changes (covers item 3 in practice).

## Evidence boundary

No PHP interpreter available in this review environment (attempted to
install via apt — no root; attempted to fetch a static PHP binary — network
proxy allowlist blocked it). No owner cPanel backup for these files is
present in this repository. This review is based on structural reading of
the bounded diff plus reasoning about OpenCart's documented model-loading
architecture, not on execution.
