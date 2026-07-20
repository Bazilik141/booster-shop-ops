# Codex Report — ST-2c.3: checkout state coordinator refactor

Date: 2026-07-19

## Scope

Fresh source: `booster-debug-checkout-state-refactor.tar.gz` (SHA256 `FCDF499ED19C958E286E5A3B5DE11B29A58F3158E664C29B5D5FB8DB62019A22`).

This supersedes the layered ST-2c.1/ST-2c.2 sidebar refresh hooks with one checkout state flow. No DB, order schema, Nova Poshta tariff calculation, payment extension, or `checkout/confirm.confirm` write-path changes.

Root causes confirmed in the supplied live files:

1. `checkout/register.save` cleared shipping and payment session state after every successful customer save, including email-only changes.
2. `checkout.twig`, `shipping_method.twig`, `payment_method.twig`, and `checkout-reskin.js` independently initiated overlapping refresh chains.
3. Coupon summary JSON was reused as a totals transport, so a later customer/address save could overwrite the sidebar with a snapshot that lacked the live shipping total.
4. The payment UI preview contained three business methods, but `payment_method.getMethods` later returned every OpenCart method, exposing the additional stock COD option.

Implemented contract:

- one revision-gated `address -> shipping -> payment -> totals` coordinator;
- customer-only saves preserve shipping/payment state; address changes invalidate only their real dependants;
- totals are fetched from the existing read-only `checkout/confirm` index route;
- coupon logic signals a totals change but no longer transports sidebar HTML;
- the server returns one canonical method per category (`hutko`, `cod`, `bank`) and passes that category to the UI; generic `cod.cod` is fallback-only;
- stale asynchronous callbacks cannot render or advance the current checkout revision.

## Files touched

The self-contained patch changes/creates these live files:

```text
catalog/controller/checkout/register.php
catalog/controller/checkout/payment_method.php
catalog/view/template/checkout/checkout.twig
catalog/view/template/checkout/shipping_method.twig
catalog/view/template/checkout/payment_method.twig
catalog/view/javascript/checkout-reskin.js
catalog/view/javascript/checkout-state.js                    (new)
```

Deliverables:

```text
patches/ST-2c.3_checkout-state-coordinator_refactor_20260719.php
diagnostics/ST-2c.3_checkout-state-coordinator-refactor_report_20260719.md
```

## Dry-run result

Applied to a fresh isolated copy of the supplied archive:

```text
changed=catalog/controller/checkout/register.php
changed=catalog/controller/checkout/payment_method.php
changed=catalog/view/template/checkout/checkout.twig
changed=catalog/view/template/checkout/shipping_method.twig
changed=catalog/view/template/checkout/payment_method.twig
changed=catalog/view/javascript/checkout-reskin.js
created=catalog/view/javascript/checkout-state.js
php_l=ok
done=ok
```

The seven generated files matched the reviewed work fixture byte-for-byte after normalizing only pre-existing EOF-newline differences.

## Validation

```text
PHP syntax: register.php = ok
PHP syntax: payment_method.php = ok
PHP syntax: patch = ok
JavaScript syntax: checkout-state.js = ok
JavaScript syntax: checkout-reskin.js = ok
Embedded Twig JavaScript: checkout.twig = ok
Embedded Twig JavaScript: shipping_method.twig = ok
Embedded Twig JavaScript: payment_method.twig = ok
Payment allow-list fixture: payment_filter=ok
```

The payment fixture proved that Hutko + preferred custom post-payment + bank are retained, while generic stock `cod.cod` and an unknown method are excluded. Canonical category transport to the UI was also asserted.

## Idempotency

Re-running the final patch returned:

```text
already_applied=yes
```

The repeat gate verifies markers in all six edited files plus the new coordinator. A partial application fails explicitly instead of reporting a false success.

## Rollback

Before writing, the patch copies all six existing files to:

```text
_patch_backups/ST-2c.3_checkout-state-coordinator_refactor_20260719_<UTC timestamp>/
```

Rollback: copy those six files back to their original paths, remove `catalog/view/javascript/checkout-state.js`, then clear OpenCart cache. The patch restores automatically and removes the new file if a PHP lint fails.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2c.3_checkout-state-coordinator_refactor_20260719.php
```

Expected terminal tail: `php_l=ok` and `done=ok`.

## Post-deploy QA checklist

- [ ] Hard reload checkout; confirm only the three intended payment choices appear before and after address completion.
- [ ] With a cart below the free-shipping threshold, complete each of the three Nova Poshta modes; delivery cost and total must appear without Ctrl+R.
- [ ] Change region/city/warehouse/postomat/courier address; the newest selection must win and the real delivery total must return.
- [ ] Delete/re-enter or correct email/name/phone; delivery cost, selected shipping, and payment must remain intact.
- [ ] Make several rapid address/method changes; no stale response may restore an older selection or dash.
- [ ] Select post-payment; confirm the stock fourth COD method never appears after address save.
- [ ] Apply/remove a coupon; totals must update while shipping remains present.
- [ ] Confirm no order is created before the explicit trusted confirmation click.
- [ ] Complete one test order; checkout success total must equal the final checkout total.

## Side effects / risks

Risk: high because checkout, payment selection, and session invalidation are affected. Mitigations: exact SHA256 gates for every edited live file, exact anchor counts, full backups, PHP lint with restore-on-fail, revision gates, canonical server payment filter, and no DB/write-boundary changes.

Browser QA on production remains required because the local archive does not provide a runnable OpenCart session or live Nova Poshta/payment extensions.
