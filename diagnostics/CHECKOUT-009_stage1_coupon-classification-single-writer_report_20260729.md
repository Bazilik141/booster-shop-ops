# Codex Report — CHECKOUT-009: Stage 1 coupon classification and single address writer

Date: 2026-07-29  
Codex config: model=Sol · effort=xhigh

## Outcome

Prepared one atomic hosting runner for Option C Stage 1. No production
deployment, order creation, commit or push was performed.

The runner:

- adds only the approved `mutated` boolean to `coupon.summary`;
- carries `{kind, mutated}` into the checkout state coordinator;
- keeps `mutated:false` query-only;
- queues one `mutated:true` requote behind an in-flight address/bootstrap
  shipping save;
- releases a non-current `activeAddressRevision` before any later promo
  requote;
- preserves exactly one requote/resave for successful manual apply/remove;
- replaces the Pinta 250 ms quote timer and the broad address URL matcher with
  one explicit `AddressCommitted` callback;
- publishes both checkout scripts with
  `v=checkout009-stage1-20260729`.

## Phase 0 evidence

| Evidence | Result |
|---|---|
| Live archive | `CHECKOUT-009-live-evidence-20260729-155027.tar.gz`; SHA-256 `2990f6ab406786b8eae4d104da98aa3411a39555d35cd58ed540674549fffc79` |
| Guest HAR | `boostershop.website.har`; SHA-256 `32b95d3fc7ab84c91ade1e8701bfedd09e7379f3d1836a7d6f35891ca9d6b6b1`; response bodies present; no order-write |
| Logged-in HAR | `boostershop.website1.har`; SHA-256 `1149f69c7b2d5c443060c0b3dc3974db28bc56b7daa83b105925ae09f7678079`; response bodies present; no order-write |
| Theme override export | Header only, zero checkout routes; SHA-256 `882828fa2a0824dc99d2400bcca4c89c26aa21fcb0cd3bef58d7daf64c3b76a2` |
| First15 proof | `coupon.php::summary()` calls the existing helper; `applyCouponCode()` writes `session.coupon`; repeat summary sees the same before/after coupon and returns `mutated:false` |

The logged-in HAR confirms
`shipping_method.quote → coupon.summary → shipping_method.save → recovery
quote`. The guest HAR confirms
`register.save → coupon.summary → shipping quote(s)`.

## Files touched

```text
patches/CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.php
plans/CHECKOUT-009_checkout-behaviour-register_20260729.md
diagnostics/CHECKOUT-009_stage1_coupon-classification-single-writer_report_20260729.md
```

The runner changes these live files as one unit:

```text
catalog/controller/checkout/coupon.php
catalog/view/javascript/checkout-state.js
catalog/view/javascript/checkout-reskin.js
catalog/view/template/checkout/checkout.twig
extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig
```

No model, DB, payment transport, credit gate, CRM, Telegram, analytics
registration, SimpleCheckout or Stage 2 state-authority file is changed.

## Exact source and planned hashes

| Live target | Before SHA-256 | After SHA-256 |
|---|---|---|
| `catalog/controller/checkout/coupon.php` | `114923340eb7da20ce3da2f0668b2ed1a0ba3fdc67ad7bd0b68bdfac5aa5840e` | `a5c15849bf310a47df5953f955c8466902309d1205bf4d1222242ccefa0b3cad` |
| `catalog/view/javascript/checkout-state.js` | `ef67a9cd578844f9f217d1eb1015fc67ea9d8a28acc291c55cbf7f2d292c8927` | `1bafab528a73d5f5cbb6ecaacd0e384b1a875ab80d3ed101f34e1eed32641a29` |
| `catalog/view/javascript/checkout-reskin.js` | `abe369a24671784826f8858555c53d1957363ffa3323655bac0a0ea7fe60b4e8` | `f97b889073b2bdc8efad650ee19143c235753972353c82d7f696ea683e30b62f` |
| `catalog/view/template/checkout/checkout.twig` | `563697ba196c97f14984a9fa2f82fb9cc2ab5c4847cdb4262d0432ead03709c9` | `d355ae2cfcd99bd1bba9c5d0f5825fc17de531a1b57fc8fb80343489fffb937f` |
| Pinta address JS Twig | `e45da5bb303fcde91600bcfed71fddbbca915beb3318577ebfc034fc4961960e` | `d4421dfe312771b15213b5e65306a03424a5462f03952ca014d359a816c4dc08` |

## Dry-run result

Final read-only dry-run was executed directly against the unchanged extracted
live source:

```text
dry_run=yes
db_changes=none
third_party_pinta_warning=update_can_overwrite_single-writer_fix
changed_files=5
write_performed=no
done=ok
```

All five exact source hashes and every replacement anchor matched once.

## Fixture apply and syntax results

The runner was copied into an isolated `C:\tmp` fixture made from the five live
targets. It created five backups, wrote all five targets, linted the changed PHP
controller, verified every post-write hash and self-deleted:

```text
changed_files=5
php_lint=No syntax errors detected in ...\catalog\controller\checkout\coupon.php
done=ok
self_delete=ok
```

Additional checks:

```text
php -l runner                                              PASS
runner SHA-256                                             8cc63adf0056415b6cbdfae160b9131de8f17ce73e71950c302d01ecf0d9ab5c
node --check patched checkout-state.js                     PASS
node --check patched checkout-reskin.js                    PASS
sanitized checkout.twig inline JavaScript parse            PASS
sanitized Pinta Twig JavaScript parse                       PASS
fixture backup count                                       5
fixture backup hashes equal all five live before hashes     PASS
```

## Deterministic contract checks

The harness executed the actual patched `checkout-state.js` with controlled
quote/save completion:

```text
summary_false_query=pass
apply_remove_one_requote=pass
no_committed_delivery_no_requote=pass
summary_before_address_save=pass
summary_after_address_save=pass
first15_repeat_no_loop=pass
bootstrap_first15_causality=pass
stale_address_revision_manual_coupon_requote=pass
twig_script_syntax=pass
single_writer_static_checks=pass
```

When `mutated:true` arrives before shipping save, the address revision remains
current; one requote starts only after `shippingSaved()` commits. When it
arrives after shipping save, one immediate requote starts. A repeated
`mutated:false` summary starts no requote. If another transition has already
advanced the revision, the stale `activeAddressRevision` is released before a
later promo requote. The invariant is: a non-current active address revision
never gates a later requote.

## Review disposition

All three blocking items in
`diagnostics/CHECKOUT-009_stage1_review_20260729.md` are resolved:

1. `requoteDeliverySelection()` now self-heals a non-current
   `activeAddressRevision` and clears its obsolete deferred request before
   evaluating the active-address gate. No timer or new state concept was added.
2. The complete extracted live archive contains `286/286` files from the tar
   source. Its only `totalsChanged(` occurrences are the function definition in
   `checkout-state.js` and one caller in `checkout-reskin.js`, passing
   `'coupon', json.summary_html || ''`. The runner replaces that caller with
   the typed `promoResult({kind, mutated}, ...)` path. No other live caller can
   lose a coupon-mutation requote through the compatibility adapter.
3. HAR URL inspection found `92` guest and `46` logged-in entries. The only
   captured address-save target is guest `POST checkout/register.save`; its URL
   is `index.php?route=checkout/register.save&language=`. Neither HAR contains
   another `checkout/shipping_address.*` sub-action. The live Twig source also
   constructs all three exact callbacks with a `route=` query parameter:
   `checkout/shipping_address.address`, `checkout/shipping_address.save`, and
   `checkout/register.save`. Therefore every captured relevant request and
   every live source constructor used by the narrowed matcher carries `route=`.

The review's two minor notes remain non-blocking follow-ups: no extra fallback
was added for a missing shipping-loader function, and malformed apply/remove
responses are not promoted to a new error contract in this bounded correction.

## Feature-preservation accounting

Rows replaced: `18`, `19`, `23`, `24`, `25`, `40`.

- `18`: typed promo result plus truthful server flag.
- `19`: unconditional coupon mutation removed.
- `23`: Pinta timer replaced by the shared callback.
- `24`: exact normalized stock address routes replace substring matching.
- `25`: `AddressCommitted` starts before the guest First15 summary.
- `40`: both scripts move to one immutable key.

The other 34 rows remain preserved. Accounting is
`34 preserved / 6 replaced / 0 removed / 0 UNKNOWN-PURPOSE / 40 of 40`.

Marker comparison across the five changed live targets found 27 relevant
before-image markers, 27 retained, zero missing, and the new
`CHECKOUT-009-STAGE1-20260729` marker in every target.

Diff signature review:

- no CSS, `!important`, `position:absolute/fixed` or unexplained pixel value;
- the Pinta `setTimeout(..., 250)` writer is removed;
- no replacement timer is added;
- `alterRedirectShippingAddressSave()` remains registered and untouched.

## Idempotency

Re-uploading and re-running the runner on the patched fixture returned:

```text
already_applied=yes
done=ok
self_delete=ok
```

Partial marker state fails before writing.

## Rollback

The runner creates:

```text
_patch_backups/CHECKOUT-009_stage1_coupon-classification-single-writer_20260729-<UTC timestamp>/
```

It restores all five targets automatically if a post-backup write, PHP lint or
post-write hash check fails. Manual rollback must restore all five files from
the same backup directory. Do not restore only the cache key or only the Pinta
file.

An injected post-write failure was run on a separate fixture after the first
two target writes. The runner exited `2` with `rollback=restored`; all five
target hashes then matched the original live hashes.

## Third-party risk

The changed Pinta Twig belongs to `PintaNovaPoshtaCod`. A Pinta extension update
can overwrite the explicit callback and silently restore the duplicate-writer
defect. The warning is present in the runner header, the deployed marker and
behaviour register row 23.

## Stage 2 observation only

`coupon.summary` remains a public POST route and can be reached directly by a
client that knows the route; the normal storefront caller is checkout-reskin.
Stage 1 does not change eligibility or grant timing. Stage 2 should move the
First15 grant to an explicit transition and make summary a true query.

## Run command (owner)

Upload the PHP runner to `~/public_html`, then run:

```bash
cd ~/public_html || exit 1
php CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

The captured host reported `DIR_MODIFICATION` unavailable/missing, so the cache
command uses only paths defined by `config.php`.

## Post-deploy owner QA

- [ ] Confirm both script URLs use `v=checkout009-stage1-20260729`, once in a
  warm browser and once incognito.
- [ ] Guest warehouse, parcel locker and courier each reach an enabled confirm
  state without address reselection.
- [ ] Logged-in default and alternate saved addresses reach payment on first
  selection.
- [ ] `coupon.summary` without mutation causes no delivery/payment reset.
- [ ] Manual apply/remove crossing ₴2,000 each cause one requote/resave.
- [ ] Guest checkout registration with automatic First15 near ₴2,000 works in
  both threshold directions; reload causes no second mutation or flicker.
- [ ] Mini-cart quantity/removal crossing ₴2,000 remains aligned.
- [ ] Card, COD and IBAN work; Mono minimum/preorder gates remain; PUMB remains
  disabled.
- [ ] One real address save produces one analytics observation, not zero or
  two.
- [ ] Comment, offer, newsletter, account opt-in and receiver override remain.
- [ ] Run `bs-checkout-smoke`.
- [ ] Only after these gates, place one owner-approved low-value COD order and
  confirm admin, CRM and Telegram outcomes.

## Not locally proven

No local fixture can prove production cache delivery, real Nova Poshta
responses, payment availability, the live ₴2,000 matrix, analytics delivery,
order creation, CRM or Telegram side effects. These remain explicit owner
post-deploy QA gates.
