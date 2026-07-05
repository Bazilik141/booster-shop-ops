# Codex Report — CHECKOUT-001 Phase 1.1: guest-only checkout and mandatory oferta

Date: 2026-07-05

## Scope

Follow-up patch for the already deployed CHECKOUT-001 Phase 1 implementation.

The patch:

- removes the native OpenCart `Реєстрація / Без реєстрації` selector from stock
  checkout;
- keeps the contact/address form and its autosave behavior;
- hides the native checkout password and account-agreement controls;
- forces `checkout/register.save` to `account=0` after all native account-mode
  conditions, so a crafted POST cannot trigger the native `addCustomer()` path;
- leaves the CHECKOUT-001 trusted-click pre-confirm flow as the only checkout
  account-creation path;
- always renders the owner-approved agreement text and URL:
  `https://boostershop.website/information/publichna-oferta`;
- blocks the client confirm button until the agreement is checked;
- blocks `confirm.confirm` server-side when session agreement is absent.

The server gate applies to every stock-checkout order. This deliberately preserves
agreement after CHECKOUT-001 logs an opted-in guest into the newly created account
before `confirm.confirm`.

Not touched: SimpleCheckout, standalone `/account/register`, customer model,
Hutko/payment implementation, fiscalization, CRM, order status, database schema,
SEO routing, dashboard, or roadmap status.

## Files touched

```text
patches/CHECKOUT-001_phase1-1_guest-only-oferta_20260705.php
```

Runtime targets:

```text
catalog/view/template/checkout/checkout.twig
catalog/controller/checkout/payment_method.php
catalog/view/template/checkout/register.twig
catalog/controller/checkout/register.php
catalog/controller/checkout/confirm.php
```

## Fresh-source preflight

The current deployed CHECKOUT-001 state was reproduced from the owner-supplied
2026-07-04 bundles plus the deployed Phase 1 runner. Exact SHA256 gates:

```text
checkout.twig       33040ad395787496ebc0f5975498f800434280a952a13c48a2e07b1fc0511023
payment_method.php  2be773b8a787a819a794954abc1fc9cd8085675ca0218d08fdcf504b99d26bf5
register.twig       8eb532f28241a4fb990249a42614a79fceed0056360fca0f5916767306bd7f67
register.php        4af74e10cc688aad28037f94d3051b40a2c649d7156a2c0dfe2fc1c6d84b4f16
confirm.php         a6ea2f9e33fea9117aff2845229431f078527d1325ea29507221c00c668e7c47
```

Any missing target, hash mismatch, anchor mismatch, or partial marker state fails
before writing.

## Dry-run result

```text
already_applied=no
changed_files=5
native_checkout_account_mode=disabled_and_server_forced_guest
checkout001_account_creation=unchanged_pre_confirm_only
oferta_client_gate=required
oferta_server_gate=required
done=ok
```

Focused checks:

```text
native_account_radio_removed=ok
hidden_guest_field_once=ok
password_hidden=ok
native_account_agree_hidden=ok
account_toggle_removed=ok
server_account_one_assignments=ok
server_guest_force_once=ok
add_customer_path_preserved=ok
oferta_url_once=ok
oferta_copy_once=ok
client_agree_gate=ok
server_agree_gate_unconditional=ok
phase1_account_step_preserved=ok
confirm_route_count=ok
checkout.twig JS syntax=ok
register.twig JS syntax=ok
client_oferta_gate_smoke=ok
```

The client smoke proved:

- unchecked agreement → confirm remains blocked;
- checked agreement with shipping/payment ready → confirm unlocks;
- missing agreement control → fail-closed.

## php -l result

```text
No syntax errors detected in CHECKOUT-001_phase1-1_guest-only-oferta_20260705.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
No syntax errors detected in catalog/controller/checkout/register.php
No syntax errors detected in catalog/controller/checkout/confirm.php
```

Local PHP: 8.3.30.

## Idempotency

Second run:

```text
already_applied=yes
changed_files=0
done=ok
```

## Database behavior

Patch execution makes no schema or row changes. Runtime customer creation remains
owned exclusively by the already deployed CHECKOUT-001 opt-in pre-confirm flow.

## Rollback

Before writing, the patch backs up all five targets to:

```text
_patch_backups/CHECKOUT-001_phase1-1_guest-only-oferta_20260705-<timestamp>-<suffix>/
```

Restore those five files and clear OpenCart cache/template files.

## Run command

```bash
cd ~/public_html || exit
php CHECKOUT-001_phase1-1_guest-only-oferta_20260705.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected terminal end:

```text
done=ok
```

## Post-deploy QA checklist

- [ ] Direct stock checkout no longer shows the native account/guest radios.
- [ ] Native password and native account-agreement controls are not visible.
- [ ] Contact, Nova Poshta address, shipping, and payment autosave still work.
- [ ] Agreement text exactly matches the owner-approved wording.
- [ ] `Публічної оферти` opens
  `https://boostershop.website/information/publichna-oferta`.
- [ ] With shipping/payment selected but agreement unchecked, place-order remains
  disabled and no `confirm.confirm` request is sent.
- [ ] Checking agreement unlocks place-order and persists through the existing
  payment comment endpoint.
- [ ] Direct/crafted `checkout/register.save` with `account=1` creates no customer.
- [ ] CHECKOUT-001 opt-in unchecked completes as guest.
- [ ] CHECKOUT-001 opt-in checked still creates one account and one set-password
  email without the standard registration email.
- [ ] Exactly one `confirm.confirm` request occurs per trusted place-order click.
- [ ] Complete the full 11-step `bs-checkout-smoke`.
- [ ] Owner manually verifies Hutko amount, Checkbox fiscalization, and CRM
  readback.

## Side effects / risks

High risk: checkout UI, server registration mode, and confirm gating change
together.

For downloadable/subscription carts, or if guest checkout is disabled in store
settings, this patch returns the existing guest-not-allowed error instead of
falling back to native checkout account creation. This is intentional: native
checkout registration is no longer an allowed account-creation path.

Status remains `In progress` until owner QA passes.
