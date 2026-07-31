# Review report — ST-2c coupon shipping-threshold refresh

Date: 2026-07-28
Reviewer: Claude
Patch reviewed: `patches/ST-2c_coupon_shipping_threshold_refresh_validated_20260728.php`
Handoff: `handoffs/handoff_ST-2c_coupon-shipping-threshold-review_20260728.md`
Diagnostic input: `diagnostics/ST-2c_coupon_shipping_threshold_refresh_report_20260728.md`

## Verdict (superseded twice — see Round 3 correction)

**Round 1: Return for changes.** One blocking defect found in the patch
payload (`extension/PintaNovaPoshtaCod/.../pinta_nova_poshta.php`). Do not
deploy in current form.

**Round 2: Review OK; owner QA required.** See section 4 below.

**Round 3 correction (2026-07-29): Round 1's defect finding was very likely
wrong, and Round 2 endorsed a regression.** See
`diagnostics/ST-2c_minicart_shipping_threshold_alignment_review_20260729.md`
for full analysis. Summary: `$this->model_checkout_cart` is not a plain
object — OpenCart's `Loader::model()` wraps loaded models in a `Proxy` that
stores each real method as a Closure-valued dynamic property, dispatched via
`__call()` for normal `->method()` syntax. PHP's `__call()` cannot forward
by-reference parameters back to the caller (a documented PHP limitation), so
for a method with by-reference outputs like `getTotals(&$totals, &$taxes,
&$total)`, only the direct closure-invocation form —
`($this->model_checkout_cart->getTotals)($totals, $taxes, $total)` — actually
populates `$total`. The plain-call form Round 1 demanded
(`$this->model_checkout_cart->getTotals($totals, $taxes, $total)`) goes
through `__call()` and silently leaves `$total` at its initial value (`0`),
which is a silent wrong-total bug (not a fatal error) — worse than the
Round 1 "fatal on every quote" theory, and it would not throw, so it would
not have been caught by the fail-loud reasoning Round 2 used to justify
proceeding. If the Round 2 patch was deployed to the live host, Pinta's
free-shipping threshold check has likely been silently wrong (always
comparing against 0) since that deployment — see the Round 3 file's
"critical open question" for the exact confirmation needed from the owner.

## Evidence boundary

- No owner cPanel backup / live-source archive for the 4 target files exists
  in this repository. Review is based solely on the bounded patch diff (the
  `preg_replace_callback` anchors and replacement bodies inside the runner)
  plus the handoff's stated root cause. Pre-patch surrounding code outside the
  matched anchors was not independently inspected — it is not available
  locally.
- No PHP interpreter is available in this review environment, so the defect
  below is identified by PHP language-semantics analysis, not by executing
  the code. Recommend Codex/owner confirm with an actual run (staging PHP or
  a local quote-endpoint smoke test) before/at deploy, since `php -l` alone
  will not catch it (explained below).

## 1. Blocking defect — malformed method call in Pinta quote path

File: `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`
New code (inside `getBoosterCartTotalUah()`):

```php
$total = 0;
($this->model_checkout_cart->getTotals)($totals, $taxes, $total);
```

`getTotals` is a real declared **method** on the Cart model, not a property
holding a Closure. Wrapping `$this->model_checkout_cart->getTotals` in
parentheses before calling it tells PHP to first evaluate it as a **property
fetch** (the documented idiom for invoking a closure stored in a property),
not as a method call. Since no such property exists (and the class has no
`__get`), this resolves to `null`, and `(null)($totals, $taxes, $total)`
throws `Error: Value of type null is not callable`.

This is syntactically valid PHP — `php -l` cannot detect it, which is
consistent with the patch's own validation evidence ("PHP syntax check:
passed", "php -l passed"). It is a runtime-only failure. `getBoosterCartTotalUah()`
runs on every Pinta shipping-quote evaluation, so this would break shipping
quoting generally, not just the coupon-triggered path this task targets —
i.e. broader blast radius than ST-2c's stated scope.

**Required fix:** call the method directly, no wrapping parentheses:

```php
$this->model_checkout_cart->getTotals($totals, $taxes, $total);
```

**Required re-validation:** re-run `php -l` (will still pass, as before) plus
an actual invocation of the Pinta quote path (staging request or equivalent)
to confirm `getTotals()` executes and returns a total, since lint cannot
catch this class of error.

## 2. Non-blocking findings (fix or confirm before/at deploy)

a. **No cache-clear step.** The runner and the handoff's owner-QA list do not
   include a Twig compiled-template cache clear, even though
   `shipping_method.twig` changes. Per `AGENTS.md` UI/CSS patch discipline and
   `CLAUDE.md` review-routing item 5 ("cache-clear instruction"), this should
   be added explicitly (compiled-template/cache refresh + owner hard
   refresh) before owner QA, or the QA steps may test stale markup.

b. **Renamed private method — other call sites unverified.** `coupon.php`'s
   `cartSubtotalUah()` is replaced wholesale by `totalToUah(float $total)`
   (different signature). The patch only rewrites the one call site it
   touches. If `cartSubtotalUah()` is called anywhere else in `coupon.php`
   outside the patched anchors, that call breaks (`Error: Call to undefined
   method`). Recommend Codex confirm via `grep -n "cartSubtotalUah("` on the
   live file before deploy.

c. **`stateRevision` staleness guard — consumption unverified.** `checkout-state.js`
   threads a new `stateRevision: token` value through to
   `saveShipping(currentQuote.code, currentQuote.label, options.stateRevision)`
   in `shipping_method.twig`, presumably to guard against a stale async
   response race. Whether `saveShipping()`'s existing implementation actually
   reads and checks this third argument is not visible in the bounded diff
   (it's an existing, unpatched function). If it doesn't consume the
   argument, the value is inert and the race protection is only cosmetic.
   Recommend Codex confirm `saveShipping()`'s existing signature/behavior.

d. **No live syntax gate for JS/Twig.** The runner's rollback-on-lint gate
   (`st2cv_lint`) only covers the two PHP targets. `checkout-state.js` and
   `shipping_method.twig` rely entirely on local pre-validation (`node
   --check`) plus the exact SHA-256 match, with no host-side syntax check.
   Acceptable given the exact-hash design (host content is guaranteed
   byte-identical to what was locally tested), but noting it as a residual
   gap: a subtle Twig templating error would not be caught until render time.

## 3. Answers to the handoff's 5 review questions

1. **Post-discount total basis** — Yes, matches the owner's rule in
   principle: `free_shipping_subtotal` now derives from `$summary['total_value']`,
   which is set from `$total` computed via the standard
   `cart->getTotals()` totals pipeline (post-coupon-discount, by OpenCart's
   normal total-extension order). Not independently verifiable against the
   full live file (not available locally); treat as inherited trust in the
   handoff's stated root cause plus standard OpenCart behavior.
2. **`getTotals()` call inside Pinta, given display-only `cost = 0.0`
   architecture** — Not safe as written; see blocking defect above. Setting
   that aside, calling `cart->getTotals()` from within the shipping model is
   architecturally sound and should not recurse into Pinta's own quote,
   because OpenCart's `total/shipping` extension reads the already-selected
   `session.shipping_method` rather than invoking a shipping model's
   `getQuote()`. Since Pinta's real cost is always `0.0`, there's no
   circular total-depends-on-shipping-depends-on-total loop either.
3. **`resaveCurrent` preserves all 3 NP modes** — Plausible by design: the
   patch reuses the pre-existing generic `current`/`currentQuote` matching
   already in `shipping_method.twig` (matched by code, not hardcoded per
   mode), so it should generalize across warehouse/poshtomat/courier without
   special-casing. Not fully verifiable from the bounded diff (the
   `currentQuote` lookup itself is pre-existing, unpatched code, not shown).
   This is exactly why the handoff's owner-QA item 3 already requires testing
   all three modes explicitly — that step remains necessary regardless.
4. **No hidden order-write / Hutko change / payment regression / stale-race** —
   No order-write call, Hutko/Mono/fiscalization reference, or payment
   calculation change appears in the diff; consistent with the stated
   exclusions. See 2c above regarding the unverified `stateRevision`
   consumption for the race-guard claim specifically.
5. **Runner contract** — SHA guards, anchor pre-check, per-file backups,
   rollback-on-lint-fail (PHP only), idempotency (`already_applied` +
   partial-marker-state guard), and self-delete are all present and correctly
   implemented. Cache-clear instruction is **absent** — see 2a above.

## Required next action (Round 1 — resolved by Round 2)

Send back to Codex: fix the `getTotals()` call in
`pinta_nova_poshta.php` (remove the wrapping parentheses), confirm no other
`cartSubtotalUah(` call sites in `coupon.php`, confirm `saveShipping()`
consumes `stateRevision`, and add an explicit cache-clear step. Re-validate
(including an actual quote-path invocation, not just `php -l`) and resubmit
for review. No owner deployment or QA until this is resolved — do not mark
ST-2c or any related roadmap task as done from this patch.

---

## 4. Round 2 — re-review of the resubmitted patch (2026-07-28, same day)

Codex resubmitted the same file
(`patches/ST-2c_coupon_shipping_threshold_refresh_validated_20260728.php`)
claiming: (a) the `getTotals()` call fixed to a direct method call, (b) no
`cartSubtotalUah(` left in the patch script, (c) `saveShipping()` in the
"actual supplied Twig snapshot" confirmed to accept `stateRevision`, check
`isCurrent()`, and pass the revision into `shippingSaved()`, and (d) self-delete
restored on the `already_applied=yes` branch.

### Independent verification performed

Ran a line-level diff between the previously reviewed patch text and the
resubmitted file (not just trusting the changelog). Result: **exactly two
hunks changed, nothing else drifted**:

1. Line ~35 (idempotent branch): added `@unlink(__FILE__);` before `exit(0);`
   when `already_applied=yes`. Correct and low-risk — this also closes a
   minor hygiene/security gap: previously, a re-run of an already-applied
   patch left the script sitting in the web root indefinitely instead of
   self-deleting, extending the window during which the runner file is
   web-reachable at `~/public_html`.
2. Line ~68 (`getBoosterCartTotalUah()`): changed
   `($this->model_checkout_cart->getTotals)($totals, $taxes, $total);` to
   `$this->model_checkout_cart->getTotals($totals, $taxes, $total);` — this
   is now a standard, unambiguous method call. **Confirms the Round 1
   blocking defect is fixed.**

No PHP interpreter is available in this environment (same limitation as
Round 1), so this is confirmed by structural diff plus PHP grammar, not by
executing the file. The change is a minimal, well-formed method-call
correction with no brace/paren imbalance introduced.

### Round 1 non-blocking items — status

a. **Cache-clear step** — resolved. Codex's owner-run command now chains the
   patch execution with a `DIR_CACHE`-based cleanup (`cache.*` files plus
   `template/*`) after `config.php` is loaded. This matches OpenCart's
   standard compiled-Twig-cache location convention for this constant. Not
   independently verified against this store's actual `config.php` (no live
   source available locally) — low risk either way, since if the path
   assumption were wrong, `glob()` simply returns no matches and the owner's
   QA (page reload / stale-markup check) would surface it, consistent with
   the project's existing fallback rule (inspect theme DB override if a Twig
   change isn't visible after cache refresh).

b. **Other `cartSubtotalUah(` call sites in the live `coupon.php`** — **not
   actually closed.** Codex's confirmation ("cartSubtotalUah( not left in the
   patch file") verifies the *patch script's own text*, which was never in
   question — the patch script doesn't execute application logic, it only
   injects text. It does not confirm whether the *live* `coupon.php` (the
   file the patch modifies) has a second call site to the old method name
   outside the one anchor the patch rewrites. If such a call site exists, it
   breaks with `Error: Call to undefined method` the same way the Round 1
   defect did, and `php -l` will not catch it either. Treating this as a
   residual, low-probability, fail-loud risk rather than blocking (see
   verdict rationale below) — but the specific confirmation asked for in
   Round 1 is still outstanding.

c. **`stateRevision` consumption by `saveShipping()`** — Codex now reports
   checking this against an actual supplied Twig snapshot (not just asserting
   it). Accepted as Codex-reported evidence; not independently verifiable
   here since that live source isn't in this repo. Consistent with, and
   reinforced by, the owner-QA steps already required (rapid coupon
   apply/remove, all 3 NP modes).

d. **No live syntax gate for JS/Twig** — unchanged from Round 1, still an
   accepted residual gap given the exact-SHA design.

### Verdict rationale

The confirmed, certain defect (blocking in Round 1) is fixed and verified by
diff. The one item not fully closed (b) shares the same "lint can't catch
it" defect class, but differs in two ways that make it acceptable to proceed
under owner QA rather than blocking again:

- It is speculative (no evidence a second call site exists or doesn't;
  Round 1's defect was certain and present in the code shown).
- It is a *fail-loud* risk: if it exists, applying a coupon would visibly
  break checkout (fatal error) on the very first manual QA pass, before the
  owner ever gets to assess shipping-threshold correctness — unlike a
  fail-silent risk (e.g., wrong totals with no error), which would warrant
  blocking.

**Verdict: Review OK; owner QA required.**

### Owner QA (unchanged from the handoff, plus one addition)

Run the handoff's original 4-item QA list. Add: on the very first coupon
apply during QA, confirm the page does not throw a fatal error / 500 — this
single check closes out residual item (b) in practice.
