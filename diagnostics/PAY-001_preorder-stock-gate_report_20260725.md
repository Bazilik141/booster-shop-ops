# Codex Report — PAY-001: preorder stock gate

Date: 2026-07-25

## Scope

Implemented Defect A from `handoff_PAY-001_preorder-gate-breaks-checkout_20260725.md`.

Root cause was established before the fix:

1. The verified preorder products use `quantity = 0` and `stock_status_id = 8` (`Передзамовлення`).
2. `Cart::hasStock()` returns `false` for every insufficient-stock row without distinguishing preorder from the blocking `Закінчився` state (`stock_status_id = 5`).
3. The stock-checkout shipping endpoint therefore returns `redirect=checkout/cart`; `shipping_method.twig` follows it, which explains the apparent direct-checkout redirect.
4. The payment endpoint has the same guard and exits before Hutko/COD/IBAN are resolved; `payment_method.twig` then renders the redirect-only response as an empty payment list.
5. Register/address/shipping/payment/confirm boundaries reuse `Cart::hasStock()`, so an endpoint-only UI change would not make a non-credit preorder order complete.

The patch changes only the shared stock predicate: insufficient stock is allowed when the explicit store stock status is `8`. Status `5` and any other insufficient-stock state remain blocked. PAY-001's separate credit gate still marks every zero-stock cart as `preorder`, so credit remains unavailable while non-credit methods can proceed.

SimpleCheckout provider isolation is unchanged. Its controller already contains the same preorder-aware business rule; the shared predicate only makes its remaining generic stock checks consistent.

## Files touched

```text
patches/PAY-001_preorder-stock-gate_20260725.php — uploadable patch
system/library/cart/cart.php                    — live target
```

No DB, settings, order-write, Mono API, payment-provider, URL, R-13.5, or QA2 changes.

## Dry-run result

```text
changed_file=system/library/cart/cart.php
php_l=ok
done=ok
```

Focused stock-gate cases:

```text
preorder_only=true
ended_only=false
in_stock=true
mixed_allowed=true
mixed_blocked=false
stock_gate_cases=ok
```

## php -l result

```text
No syntax errors detected in PAY-001_preorder-stock-gate_20260725.php
No syntax errors detected in system/library/cart/cart.php
```

## Idempotency

Re-running against the patched fixture returns:

```text
already_applied=yes
```

The patch validates the exact source SHA256 and one exact anchor before writing.

## Rollback

Backup is created at:

```text
_patch_backups/PAY-001_preorder-stock-gate_20260725-<timestamp>/system/library/cart/cart.php
```

Restore that file to `system/library/cart/cart.php`.

## Run command (owner)

Run together with the separate product-threshold patch after review.

## Post-deploy QA checklist

- [ ] Cart contains one preorder product (`quantity=0`, stock status `Передзамовлення`).
- [ ] Direct stock-checkout URL remains on the stock checkout after shipping-method load.
- [ ] Hutko, COD, and IBAN methods are listed.
- [ ] Credit row is visible but muted/disabled with the preorder explanation.
- [ ] A non-credit preorder order completes once and retains the chosen non-credit payment method.
- [ ] A product with stock status `Закінчився` remains blocked.
- [ ] A normal in-stock order still completes.
- [ ] Do not mark the full smoke complete and do not tell Monobank “ready” until all handoff QA passes.

## Side effects / risks

Risk: medium-high because `Cart::hasStock()` is shared. The change is deliberately restricted to explicit `stock_status_id = 8`; focused tests prove that status `5` and mixed carts containing a status-5 shortage remain blocked.
