# Codex Report — ST-2b.6e: server-render order-write gate

Date: 2026-07-12

## Scope

Evidence-first diagnosis and one-file fix for the redesigned stock checkout creating or editing an OpenCart order when the checkout page is opened, restored, or refreshed. The investigation used the newest cPanel archive `backup-7.12.2026_20-01-49_boosters.tar.gz`, its OpenCart SQL dump, and its Apache access log. No server access, deployment, database write, schema change, payment-math change, or fiscalization change was performed.

The patch changes only the confirmed server-side write boundary. It does not alter Hutko, COD, Checkbox, Nova Poshta, totals, order statuses, the trusted-click handler, or the legacy SimpleCheckout module.

## Root cause — confirmed

The browser click path was not the missing trigger. The page render itself calls the order-writing controller:

1. `catalog/controller/checkout/checkout.php:93` unconditionally runs `$this->load->controller('checkout/confirm')` for every stock-checkout GET.
2. That invokes `catalog/controller/checkout/confirm.php::index()` directly, not the guarded public `confirm()` endpoint.
3. When cart, shipping, payment, and agreement remain complete in the session, `confirm.php:88-89` enters the order-generation block.
4. `confirm.php:312-316` calls `addOrder()` or `editOrder()`.
5. Only after that server-side work does `checkout.twig:87` receive and print `{{ confirm }}`. Client-side `isTrusted` checks run too late to prevent it.

This directly matches the reported trigger: reload/browser restore/tab reopen repeats `GET checkout/checkout` while the checkout session still contains shipping and payment data.

### Database + access-log correlation

The current backup contains a concrete request-to-row correlation (not proof of PHP session identity):

- order `224` has `date_added=2026-07-12 19:56:48`, `date_modified=19:56:50`, and was stamped with Windows Chrome; its final dump state is COD/status 1, so it is evidence of the early row creation path, not a retained status-0 draft;
- the matching pseudonymous client appears in access logs at `19:56:47` and `19:56:49` with only `GET /index.php?route=checkout/checkout` plus ordinary shipping/payment-method reads;
- there is no `checkout/confirm.confirm` request at the order-creation timestamp;
- the first explicit `checkout/confirm.confirm` request visible in that time window appears only at `19:57:15`, after the row already existed, and carries an Android Pixel user agent;
- the same SQL dump contains one recent status-0 draft, order `216` (2026-07-08 21:47:40, Hutko), consistent with this server-render path.

No IP address, email, phone, name, address, or token was copied into this report.

### Why earlier ST-2b.6 work did not close this

ST-2b6c fixed a separate real defect: address refresh could silently reselect/save Hutko. ST-2b6d added a trusted-event gate to the deferred browser button. Those changes explain why a draft could contain Hutko and block synthetic browser clicks, but neither can intercept the internal PHP call from `checkout.php` to `confirm::index()`.

The original ST-2b.1 patch moved the visible AJAX `confirm.confirm` call behind the button but changed only Twig/payment-method JavaScript. ST-2b.3 then cached the initial server-rendered `{{ confirm }}` table under the incorrect assumption that it was read-only. That retained the hidden write path.

## Fix

`confirm::index()` now accepts an explicit `$allow_order_write=false` flag:

- internal page rendering keeps the default `false`, so it may render the cart/totals preview but cannot call `addOrder()`, `editOrder()`, or render a live payment-extension button;
- public `confirm()` keeps its existing validation and calls `index(true)`, preserving the intended single order creation after the place-order action;
- existing CAPTCHA validation in `confirm()` is preserved.

## Files touched

```text
patches/ST-2b6e_server-render-order-write-gate_20260712.php
catalog/controller/checkout/confirm.php  (server target at patch runtime)
diagnostics/ST-2b6e_server-render-order-write-gate_report_20260712.md
```

Source hashes from the fresh backup:

```text
checkout.php  3F6110E7C986CB5D3B70EE3E1339231BF71D6312F0EF7F674FAAC92BD0500093
confirm.php   8CEB7AEA1E76AFC8B494C62AF42DE168DD0C27E6EC0C0CCAAC8A7D6030E7393D
checkout.twig DFBF99D5045A729B50DE7F709DE623A28B0A294BCD6ACD2084326CAF7F10F9EB
```

The patch is SHA-256 gated to the listed `confirm.php` and stops before writing if live source has drifted.

## Dry-run result

Applied to an isolated copy of the exact fresh-backup target:

```text
scope=gate addOrder/editOrder and payment HTML behind explicit confirm() call
db_schema_changes=none
db_data_changes=none
php_l_patch=ok
php_l_candidate=ok
php_l_target=ok
changed=catalog/controller/checkout/confirm.php
server_render_order_write=blocked
explicit_confirm_order_write=preserved
done=ok
```

Post-write structural checks found exactly one each of the read-only default, order-write gate, payment-HTML gate, `index(true)` call, `addOrder()`, and `editOrder()`.

## php -l result

```text
No syntax errors detected in ST-2b6e_server-render-order-write-gate_20260712.php
No syntax errors detected in catalog/controller/checkout/confirm.php
```

## Idempotency

The isolated second run returned:

```text
already_applied=yes
changed_files=none
done=ok
```

Both the first and repeat-run patch copies self-deleted.

## Rollback

Backup is created before any target write at:

```text
_patch_backups/ST-2b6e_server-render-order-write-gate_20260712-<timestamp>/catalog/controller/checkout/confirm.php
```

The patch restores automatically on candidate lint, target lint, or post-write invariant failure. For manual rollback, restore the printed backup to `catalog/controller/checkout/confirm.php`. OpenCart template/cache cleanup is not required because this patch changes only a PHP controller.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2b6e_server-render-order-write-gate_20260712.php
```

## Post-deploy QA checklist

- [ ] Record the highest current order ID / status-0 draft count before testing.
- [ ] Prepare a complete checkout session with shipping and a payment method selected, but do not press the place-order button.
- [ ] Refresh the redesigned checkout three times; confirm the order count does not change.
- [ ] Close and reopen the tab/browser with that checkout session; confirm the order count does not change.
- [ ] In Network, confirm those page loads contain no order-producing request; ordinary shipping/payment-method reads are acceptable.
- [ ] Place one COD test order by the real button; confirm exactly one order is created and reaches success.
- [ ] Place one Hutko sandbox test order by the real button; confirm exactly one order, correct amount, redirect, and fiscal behavior.
- [ ] Repeat one order as guest if CAPTCHA is enabled; confirm the existing CAPTCHA gate still works.
- [ ] Run the full `bs-checkout-smoke` once before marking the task Done.

## Side effects / risks

- High-risk checkout/order path, but the runtime diff is four narrow lines in one controller.
- Initial `{{ confirm }}` remains available as a read-only table for the existing ST-2b.3 summary cache; live payment-extension HTML is withheld until explicit confirm.
- No existing draft rows are deleted or modified by the patch.
- A separate source mismatch was found: current `confirm.php` contains RD-13.1C CAPTCHA validation, while current `checkout.twig` uses the older GET `.load()` and does not contain the corresponding RD-13.1C POST payload. The backup proves the mismatch, not which later action caused it. This is not the auto-draft root and is not silently bundled into this fix; guest CAPTCHA checkout needs explicit QA/follow-up.
- Legacy SimpleCheckout has its own internal confirm/order path. The reported redesigned stock-checkout bug is proven independently; SimpleCheckout hardening/cutover remains a separate scope.
