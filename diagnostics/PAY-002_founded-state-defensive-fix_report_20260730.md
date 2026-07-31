# PAY-002 — FUNDED / FOUNDED defensive state fix

Date: 2026-07-30  
Codex config: model=Sol · effort=xhigh

## Outcome

Prepared a narrow host-run patch that maps both `FUNDED` and `FOUNDED` to the existing PUMB `funded` order-status key. PUMB remains disabled; the patch does not call the bank, change settings, or alter checkout.

## Live evidence

Owner ran, from `~/public_html`:

```text
grep -RIn --include='*.php' -E 'FUNDED|FOUNDED' extension/pumb_credit
```

The only result was `catalog/controller/payment/pumb_credit.php:199`, in `applyOrderStatus()`, with the exact old anchor:

```php
$state === 'FUNDED' ? 'funded' :
```

No second PHP comparison was found in the PUMB extension, including admin code.

## Implementation and rollback

The runner replaces only that one expression with:

```php
in_array($state, ['FUNDED', 'FOUNDED'], true) ? 'funded' :
```

It requires exactly one old anchor and zero pre-existing dual mappings, backs up the controller under `_patch_backups/`, runs `php -l`, restores the controller if a post-write step fails, writes an idempotency marker, then self-deletes after `done=ok`. It makes no database change, so no SQL rollback is needed.

## Local validation

- `php -l patches/PAY-002_founded-state-defensive-fix_20260730.php`
- Fixture: both `FUNDED` and `FOUNDED` resolve to `funded`; `WAITING_CLIENT` and an unknown state do not resolve to that key.
- Static patch check: one old anchor becomes one dual-state expression, with no other comparison changed.

## Remaining gates

Bank test-contour responses are still needed to establish the bank's canonical spelling. Keep `payment_pumb_credit_status=0` until the wider PUMB QA gate is complete.
