# Codex Report — ACC-002D: checkout NP duplicate-address autosave

Date: 2026-07-14

## Scope

Diagnosed and fixed the authorised-checkout path that created repeated address-book rows while switching Nova Poshta delivery types. The patch prevents a stale point/ref from being autosaved under a different type, validates the posted NP identity server-side, persists `bs_np_v1`, and reuses an existing structured address with the same NP identity.

No patch-time SQL, schema migration, duplicate deletion, or legacy-row rewrite is included. Existing duplicate rows remain for separate owner-approved cleanup.

## Confirmed evidence

- Network Initiator: `checkout-reskin.js:468` programmatically triggers the module type `change`; the stack then reaches `window.bsCheckoutNpFieldChanged()` and the NP form submit handler.
- Payload reproduced an invalid mixed state: `shipping_novaposhta_type=warehouse` together with the ref/label of poshtomat `№49489`.
- `checkout/shipping_address|save` returned a newly created `address_id=81`.
- Response rows `80` and `81` have `custom_field=[]`; the original row `68` has valid `custom_field.bs_np_v1` metadata.
- Fresh live `shipping_address.php` unconditionally called `addAddress()` for every successful save.
- The module form posted NP refs as top-level fields, but the controller did not validate them or map them to `custom_field.bs_np_v1`.

This proves both observed symptoms: repeated DB rows and the subsequent legacy/re-pick warning on the automatically created rows.

## Files touched

```text
patches/ACC-002D_checkout-np-address-idempotency_20260714.php

Live targets modified by the patch:
catalog/controller/checkout/shipping_address.php
catalog/view/javascript/checkout-reskin.js
```

## Dry-run result

```text
dry_run=ok patch=ACC-002D_checkout-np-address-idempotency_20260714 files=2 php_l=deferred
```

## Syntax and focused checks

```text
No syntax errors detected in ACC-002D_checkout-np-address-idempotency_20260714.php
No syntax errors detected in catalog/controller/checkout/shipping_address.php
node --check catalog/view/javascript/checkout-reskin.js: passed
dedupe_test=ok reused_address_id=68 mismatch=0
```

The focused dedupe test used the response shape supplied by the owner: matching `bs_np_v1` metadata reused address `68`; a different delivery type did not match it.

## Idempotency

Re-running on the patched fixture returns:

```text
already_applied=yes patch=ACC-002D_checkout-np-address-idempotency_20260714
```

The first successful run self-deletes the uploaded patch.

## Rollback

Backup created before writing:

```text
_patch_backups/ACC-002D_checkout-np-address-idempotency_20260714-<timestamp>/
```

Restore both target files from that directory if rollback is required, then clear the OpenCart file/template caches.

## Run command (owner)

```bash
cd ~/public_html || exit
php ACC-002D_checkout-np-address-idempotency_20260714.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Record the address count for the test account before starting.
- [ ] With a saved warehouse/poshtomat selected, click another delivery type; before choosing a new point there must be no `shipping_address|save` POST and no new address row.
- [ ] Choose one genuinely new point; exactly one save may occur and the selected address must not show the legacy/re-pick warning.
- [ ] Switch back to the original saved address; the address count must remain unchanged.
- [ ] Re-select the same structured point; response should contain `address_reused=true`, reuse the existing `address_id`, and keep the address count unchanged.
- [ ] Verify warehouse, poshtomat, and courier flows once each.
- [ ] Complete one authorised order and one guest checkout smoke test.

## Side effects / risks

- Risk: medium-high because checkout and Nova Poshta address selection are affected.
- Legitimately selecting a new NP identity can still create one normal address-book row, preserving the existing OpenCart/module behaviour.
- Repeated saves for the same structured NP identity reuse the existing row.
- Existing legacy/duplicate rows are not deleted or silently rewritten.
- Standard non-NP `checkout/shipping_address.save` behaviour is unchanged.
