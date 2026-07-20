# Review report — ST-2c.3a → ST-2c.4 checkout regression patchset

Date: 2026-07-20  
Audience: Claude post-patch review  
Scope: the three latest Booster Shop checkout patches only

## 1. Executive summary

This is one cumulative review chain, not three independent alternatives:

1. `ST-2c.3a` repaired payment lookup by the option's canonical code.
2. `ST-2c.3b` removed the remaining dependency on an asynchronously populated
   `session.payment_methods` snapshot and made the three allowed payment choices
   visible before address entry.
3. `ST-2c.4` addresses the separate guest-only lost-session update that can erase
   `shipping_method` between save and totals render; it also removes the duplicated
   delivery-address line from the Receiver card.

Current evidence boundary:

- ST-2c.3a is confirmed present in the later ST-2c.3b live bundle.
- ST-2c.3b is confirmed present in the later
  `booster-debug-ST2c4-guest-shipping.tar.gz` live bundle.
- ST-2c.4 is locally validated against that latest bundle but has not been claimed
  as deployed or production-QA-passed. It is the current review/deploy candidate.

No patch in this set changes DB schema/data, Nova Poshta tariffs, the 2000 UAH
free-shipping rule, gateway execution, fiscalization, order creation, CRM payloads,
or order statuses.

## 2. Artifacts and integrity

| Patch | SHA-256 | Review status |
|---|---|---|
| `patches/ST-2c.3a_payment-code-lookup-fix_20260720.php` | `59B27ECE29627B5B9D0351200A7E830DDE481238B06E2298B8435EB02ED331AF` | Later live bundle confirms installed base |
| `patches/ST-2c.3b_payment-state-write-boundary-fix_20260720.php` | `9507BE7CFF4BA6B15BEB638E4263E04FFE600729935438BEB9A1AEFECBCDD745` | Latest ST-2c.4 input bundle confirms installed output |
| `patches/ST-2c.4_guest-shipping-session-serialization_20260720.php` | `F2CC1A4AA8D8F2427E86D63BC199E6DA2FB4C8CBC55B3D27DF0A896929C2FB87` | Review-hardened local dry-run complete; needs Claude review + owner deploy/QA |

Source reports:

- `diagnostics/ST-2c.3a_payment-code-lookup-fix_report_20260720.md`
- `diagnostics/ST-2c.3b_payment-state-write-boundary-fix_report_20260720.md`
- `diagnostics/ST-2c.4_guest-shipping-session-serialization_report_20260720.md`

### ST-2c.4 source-gate verification follow-up

The reviewer did not receive the ST-2c.4 input archive, but it is present on the
owner/Codex machine and was rechecked directly. Archive SHA-256:

```text
53FA007190B5DDEF2023B241E207511C77C7723A465063C7DBF259DC2E245FF9  booster-debug-ST2c4-guest-shipping.tar.gz
```

All five preimage hashes match the gates embedded in ST-2c.4:

```text
9B52FE4A1DC3E69EF4070F944A4F0E0EA47829C62F7AAF4C123625DCA5AEE709  catalog/controller/checkout/shipping_method.php
B189A924756ACBA9E037D23514821C83E1E03213664FD637A7C353A6E4C7FD3C  catalog/view/javascript/checkout-state.js
743068A97F0A0D8DF34A6A53F29BA930391EB77E644598E960B340DC403B0CED  catalog/view/javascript/checkout-reskin.js
7360A7F5865D4A93AA8CC37D6F478DE0E64B96358C9997C3FF9A4E8091B85091  catalog/view/template/checkout/shipping_method.twig
B8CF36BB28C2EF913777ABD9188DF1048EB704EA16B19E9B6063B0F1F4580BDE  catalog/view/template/checkout/checkout.twig
```

This closes the source-gate concern for the supplied live archive. The runner
still fails before backup/write if production drifted after that capture.

## 3. Regression chain and why each patch exists

### ST-2c.3a — canonical payment-code lookup

**Observed problem**

After ST-2c.3, a payment radio could be visibly selected, but
`checkout/payment_method.save` returned `error_payment_method`, kept the sidebar in
the “payment missing” state, and left the hidden payment code empty.

**Root cause**

The server rendered a canonical payment option using `option.code`, but the save
validator split that code on `.` and assumed those pieces were identical to the
internal payment-group and option array keys. The extension array keys are not
contractually required to match `option.code`.

**Change**

`catalog/controller/checkout/payment_method.php` gained one shared recursive
lookup that finds an allowed option by its exact own `option.code`. The returned
canonical option becomes `session.payment_method`.

**Why this was not sufficient alone**

The corrected lookup still searched `session.payment_methods`. That map was
written by a different asynchronous request, so a valid rendered choice could
still fail if the transient session snapshot was absent or stale.

### ST-2c.3b — payment validation at the real write boundary

**Observed problems after 3a**

1. The initial Payment card could be empty until the address/shipping bootstrap
   completed.
2. A selected allowed method could still immediately show
   `Потрібен спосіб оплати!` because save depended on the transient
   `session.payment_methods` map.

**Root cause**

- `checkout-state.js::bootstrap()` returned through the shipping bootstrap before
  rendering the payment preview.
- `payment_method.save` treated an inter-request cache as an authority instead of
  rebuilding currently allowed methods at the write boundary.

**Change**

- `payment_method.php` now has one server helper shared by `getMethods()` and
  `save()`. Save recomputes the currently available methods from the current
  checkout context, filters them to the canonical Hutko / preferred COD / IBAN
  choices, and validates the posted exact code through the 3a lookup helper.
- `checkout-state.js` renders the payment preview immediately.
- `payment_method.twig` and the checkout fallback remove address-dependent helper
  copy; the three choices are visible before recipient/address completion.
- `checkout.twig` bumps the state-script cache key.

**Fresh-live confirmation**

The ST-2c.4 input archive contains all 3b markers and these hashes:

```text
47D0EBE28C2A0C66688B7FD9CAA0ED3112A36817AA117BB623AAF7C9A93019A4  catalog/controller/checkout/payment_method.php
B189A924756ACBA9E037D23514821C83E1E03213664FD637A7C353A6E4C7FD3C  catalog/view/javascript/checkout-state.js
28DF66CE280893355A8BAF38E12BB04AC020F86658F539AA3A38D10E14FF6A24  catalog/view/template/checkout/payment_method.twig
B8CF36BB28C2EF913777ABD9188DF1048EB704EA16B19E9B6063B0F1F4580BDE  catalog/view/template/checkout/checkout.twig
```

The owner reported the payment-selection problem resolved after this stage.

### ST-2c.4 — guest shipping session serialization and atomic summary

**Observed problems after 3b**

- Logged-in checkout showed the paid shipping amount correctly.
- Guest checkout could keep a delivery radio selected but show a dash and exclude
  shipping from the checkout total.
- The Receiver card displayed a delivery-address line already present in the
  Delivery card.

**Root cause evidence**

- Guest `register.save` and shipping quote/save used the global checkout `chain`,
  but `checkout/coupon.*` requests in `checkout-reskin.js` did not.
- `catalog/view/javascript/common.js` from the newest full cPanel backup available
  locally (file SHA-256
  `4B1F23FE428F7CFBF907A8B80E62A8EC896F84C830DFD0D96A23DF828CB545A5`)
  confirms the queue implementation: `attach()` appends to `data`, invokes
  `execute()` only when `start` is false, and `execute()` advances via
  `jqxhr.done()`. An attach during active work therefore appends without recursive
  execution. No repo patch references `catalog/view/javascript/common.js` after
  that backup, but the targeted ST-2c.4 archive did not contain the file, so exact
  current-live identity still requires either a one-file capture or browser QA.
- The done-only executor revealed a failure-path risk: a raw rejected coupon jqXHR
  would stall later queued work. The review-hardened ST-2c.4 returns an
  always-resolved Deferred wrapper after coupon `complete`, so both success and
  HTTP failure release the queue. The global `common.js` is unchanged.
- The latest full cPanel backup locally available confirms OpenCart reads the full
  session at request start, writes it in a shutdown handler, and the DB adaptor
  performs `REPLACE INTO` for the whole JSON session blob without locking or
  field-level merge. The last finishing overlapping request can therefore erase
  another request's newer `shipping_method`.
- After `shipping_method.save`, the coordinator used a separate
  `checkout/confirm` request for totals. That second request could see the
  overwritten guest session and render no shipping row, while the client-side
  radio stayed selected.
- The logged-in flow does not execute the guest register/coupon sequence, matching
  the guest-only symptom.
- `data-co-receiver-address` directly emitted and populated the duplicated visual
  address line.

**Change**

- Coupon summary/apply/remove requests now attach to the same checkout `chain` as
  guest register and shipping requests. Their queue callback resolves after both
  AJAX success and failure, matching the chain's done-only progression contract.
- `shipping_method.save` returns the existing read-only `checkout/confirm` HTML in
  `summary_html` after writing `session.shipping_method`.
- `shipping_method.twig` passes that response to the state coordinator.
- `checkout-state.js::shippingSaved()` consumes the same-request summary and skips
  the normal second totals request. The old read-only refresh remains only as a
  compatibility fallback when `summary_html` is absent.
- Receiver recap markup and its address writer are removed from
  `checkout-reskin.js`.
- Both changed JS assets receive new cache keys in `checkout.twig`.

## 4. Cumulative final file surface

Seven unique live files are affected across the three-patch chain:

| File | 3a | 3b | 4 | Final responsibility |
|---|:---:|:---:|:---:|---|
| `catalog/controller/checkout/payment_method.php` | ✓ | ✓ |  | Canonical payment list and write-boundary validation |
| `catalog/controller/checkout/shipping_method.php` |  |  | ✓ | Shipping write plus atomic read-only summary |
| `catalog/view/javascript/checkout-state.js` |  | ✓ | ✓ | Revision-gated checkout state and summary consumption |
| `catalog/view/javascript/checkout-reskin.js` |  |  | ✓ | Serialized coupon requests and Receiver visual cleanup |
| `catalog/view/template/checkout/payment_method.twig` |  | ✓ |  | Immediate canonical payment preview |
| `catalog/view/template/checkout/shipping_method.twig` |  |  | ✓ | Shipping summary response handoff |
| `catalog/view/template/checkout/checkout.twig` |  | ✓ | ✓ | Fallback copy cleanup and JS cache keys |

## 5. Validation already completed

### ST-2c.3a

```text
php_l=ok
done=ok
payment_code_lookup=ok
unavailable code rejected
already_applied=yes
```

### ST-2c.3b

```text
php_l=ok
done=ok
self_deleted=True
checkout-state.js syntax=ok
payment_method.twig embedded JavaScript=ok
preview_methods=hutko,cod,bank
legacy_session_gate=absent
address_dependent_hint=absent
already_applied=yes
```

### ST-2c.4

```text
php_l=ok
done=ok
checkout-state.js syntax=ok
checkout-reskin.js syntax=ok
controller -> Twig -> coordinator summary handoff=ok
summary rendered after session.shipping_method write=ok
data-co-receiver-address removed=ok
chain contract: FIFO append, active attach is non-recursive, done-only advance=confirmed
coupon queue failure settlement=ok
already_applied=yes
```

The ST-2c.4 hash-mismatch fixture failed before backup/write as intended:

```text
error=sha256_mismatch:catalog/view/javascript/checkout-state.js
exit_code=1
safe_fail_no_writes=ok
```

All runners check target existence, exact anchors and SHA-256, create backups
before writes, restore changed PHP targets on lint failure, and self-delete only
after `done=ok`.

## 6. What Claude should review closely

### Server-side contract

- Confirm `payment_method.save` rebuilds and filters the allowed list from current
  context and never accepts a code absent from that server-generated list.
- Confirm the 3a exact-code helper remains the single canonical lookup used after
  3b, with no fallback to legacy array-key assumptions.
- Confirm `shipping_method.save` invokes `checkout/confirm` only after
  `session.shipping_method` is assigned.
- Confirm `checkout/confirm::index()` remains read-only by default in the current
  live code, so returning its HTML from shipping save cannot create/edit an order.
- Confirm the larger JSON response is safe for current product text encoding and
  does not expose payment/gateway secrets.

### Client sequencing

- Confirm the documented `common.js` behavior is sufficient evidence for
  `chain.attach(send)`: active attach appends without recursive execution. If exact
  current-live proof is required, request only
  `catalog/view/javascript/common.js`; ST-2c.4 does not modify it.
- Confirm the Deferred wrapper resolves from coupon `complete` on both success and
  error so the global done-only queue always advances.
- Confirm coupon `busy` behavior remains acceptable while a request waits in the
  chain; repeated clicks are intentionally ignored as before, but the busy window
  can now include queue wait time.
- Confirm a valid `summary_html` suppresses only the redundant initial totals GET;
  coupon changes and compatibility fallback can still request read-only totals.
- Confirm revision guards still reject stale address/shipping responses and that
  payment reload happens after the final shipping selection.

Optional exact-live evidence command if Claude wants the current queue source
instead of accepting the full-backup copy plus owner QA:

```bash
cd ~/public_html || exit
tar -czf booster-debug-ST2c4-chain.tar.gz catalog/view/javascript/common.js
sha256sum catalog/view/javascript/common.js booster-debug-ST2c4-chain.tar.gz
```

### UI and regression boundaries

- Confirm the three visible payment categories remain exactly Hutko, preferred
  COD, and IBAN; the fourth stock COD option must not return after any refresh.
- Confirm removing `data-co-receiver-address` does not remove the Receiver status
  node or any delivery-address data used for the order.
- Confirm the Delivery card remains the sole visible address source.
- Scan the cumulative diff for unexplained `setTimeout`, `!important`, fixed
  positioning, magic pixels, order-write calls, or new DB operations. ST-2c.4 adds
  none of these patterns.

## 7. Risk assessment

Overall risk: **medium-high**, because checkout payment/session state and Nova
Poshta totals are affected.

Risk reducers:

- exact fresh-file SHA-256 gates;
- server validation of payment codes at the write boundary;
- one shared queue for session-mutating guest checkout traffic;
- a failure-settled coupon wrapper so the newly queued request cannot freeze the
  existing done-only global queue;
- read-only summary render, no new order-write route;
- compatibility fallback if `summary_html` is absent;
- no DB, gateway, fiscal, CRM, or order-status changes;
- per-run backups and repeat-run/partial-apply guards.

Remaining uncertainty:

- Local fixtures cannot reproduce real browser timing, the production session DB,
  Pinta API latency, or the complete checkout-to-success/CRM chain. Owner smoke QA
  is mandatory before ST-2c can be considered Done.

## 8. Acceptance criteria / owner QA after approval

1. Fresh guest checkout with empty recipient/address: all three payment choices
   are visible immediately; there is no payment error before a save attempt.
2. Below 2000 UAH, complete each NP mode (warehouse, parcel locker, courier): paid
   shipping appears in the sidebar and grand total without Ctrl+R.
3. Change email, receiver name/phone, region, city and NP destination after the
   first valid selection: the newest delivery/payment state wins and shipping
   never turns into a dash.
4. Apply/remove a coupon after shipping selection: totals recalculate once and
   retain the shipping row.
5. Select Hutko, preferred COD and IBAN in separate passes: selection persists,
   `Потрібен спосіб оплати!` does not appear, and the fourth stock COD stays hidden.
6. Repeat key checks while logged in; paid/free shipping behavior remains correct.
7. Receiver card contains receiver fields/status only; delivery address appears
   only in the Delivery card.
8. Complete one owner-approved non-charge test order and verify checkout success,
   admin order payment/shipping totals, fiscal behavior if applicable, and CRM
   readback agree.

## 9. Rollback and deploy boundary

- Claude reviews only; Claude does not deploy.
- Owner uploads and runs ST-2c.4 only after approval.
- Every runner has its own `_patch_backups/...` directory.
- To unwind the entire cumulative chain, restore in reverse deployment order:
  ST-2c.4 → ST-2c.3b → ST-2c.3a, then clear the OpenCart data/template cache.
- If only ST-2c.4 needs rollback, restore its five files; that returns checkout to
  the confirmed post-3b live state.

## 10. Requested Claude verdict

Please return:

1. **Approve / approve with changes / reject** for ST-2c.4.
2. Any unsafe interaction across 3a → 3b → 4, especially session queue ordering,
   read-only confirm rendering, or payment invalidation.
3. Whether the cumulative patchset solves the reported regressions without
   reintroducing refresh-based state races.
4. Exact owner QA additions required before deploy/Done.
