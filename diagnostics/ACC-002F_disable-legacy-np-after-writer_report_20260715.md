# Codex Report — ACC-002F: disable legacy NP address after-writer

Date: 2026-07-15

## Scope

Removed the confirmed second address writer from the authorised Nova Poshta checkout save path. The Pinta event remains registered, but its `alterRedirectShippingAddressSave()` handler is now a no-op because the patched stock checkout controller already validates NP refs, persists `bs_np_v1`, and owns the canonical JSON response.

No schema change, SQL update, event-table change, existing-address cleanup, or module-model change is included.

## Confirmed root cause

The owner supplied a fresh live response and files that prove the complete sequence:

1. The structured checkout controller created address `88` with valid `custom_field.bs_np_v1`, postcode `49000`, and the validated warehouse ref.
2. Active OpenCart event `pinta_nova_poshta_controller_checkout_shipping_address_after` runs after `catalog/controller/checkout/shipping_address.save`.
3. Its live `alterRedirectShippingAddressSave()` called `createNovaPoshtaAddress()`.
4. The module model built another address with no `custom_field`, an empty postcode, and `firstname + " " + middlename`; it called `addAddress()` and created legacy address `89`.
5. The after-hook replaced the valid response, so the browser received `address_id=89` without the ACC-002D/E `address_updated` and `address_reused` fields.

The row signatures match the live response exactly. This is why earlier controller/JS patches reduced the symptom but could not eliminate the final duplicate.

## Why the earlier fixes were insufficient

- ACC-002D corrected the structured checkout writer and made its save path idempotent, but the Pinta `after` event still ran afterward and appended a second legacy row.
- ACC-002E corrected explicit re-selection/repair semantics for an existing address, but it did not disable that independent server-side after-writer.

## Post-deploy result

**PASS — owner-confirmed on 2026-07-15.**

The owner deployed ACC-002F and repeated the previously failing authorised-checkout scenario: start with a valid saved NP address, switch delivery type, select a new NP point, and return to the saved-address flow. The extra legacy duplicate and its “re-select Nova Poshta point” warning no longer appeared; the owner reported the issue fixed.

This confirms that the second Pinta `after` writer was the operative root cause of the observed duplicate-address regression. Verification in this conversation covers that regression scenario; a full authorised-order and guest-order smoke test was not reported.

## Files touched

```text
patches/ACC-002F_disable-legacy-np-after-writer_20260715.php

Live target modified by the patch:
extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php
```

The following confirmed source was inspected but is not modified:

```text
extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
```

## Dry-run result

```text
dry_run=ok patch=ACC-002F_disable-legacy-np-after-writer_20260715 files=1 php_l=deferred
```

## Syntax and focused checks

```text
No syntax errors detected in ACC-002F_disable-legacy-np-after-writer_20260715.php
No syntax errors detected in extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php
model_unchanged=ok
self_delete=ok
```

Focused post-apply checks confirmed:

- `alterRedirectShippingAddressSave()` no longer loads the module model;
- it no longer calls `createNovaPoshtaAddress()`;
- it no longer overwrites the structured controller response;
- `alterShippingAddressRegister()` and `createNovaPoshtaAddressRegister()` remain unchanged;
- the module model hash is unchanged.

## Idempotency

Re-running on the patched fresh-live fixture returns:

```text
already_applied=yes patch=ACC-002F_disable-legacy-np-after-writer_20260715
```

The first successful run self-deletes the uploaded patch.

## Rollback

Backup created before writing:

```text
_patch_backups/ACC-002F_disable-legacy-np-after-writer_20260715-<timestamp>/extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php
```

Restore that file if rollback is required, then clear OpenCart data/template caches.

## Run command (owner)

```bash
cd ~/public_html || exit
php ACC-002F_disable-legacy-np-after-writer_20260715.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [x] Repeat the original authorised-checkout delivery-type switch and new-point selection scenario.
- [x] Confirm no second legacy duplicate is created and no legacy re-selection warning appears for the newly saved address.
- [ ] Confirm the POST response returns the structured row and includes `address_updated=false` and `address_reused=false` for a genuinely new point.
- [ ] Confirm the new row's `npMetadata` returns `legacy=false`, `stale=false`, and valid `bs_np_v1` metadata.
- [ ] Confirm no new row has `custom_field=[]`, empty postcode, or a trailing blank in firstname.
- [ ] Re-select the same structured point and confirm `address_reused=true` with no count increase.
- [ ] Complete one authorised checkout smoke test, then one guest checkout smoke test.

## Side effects / risks

- Risk: medium-high because the OpenCart checkout/Nova Poshta event chain is affected.
- Only the obsolete authorised shipping-address after-writer is disabled.
- The module's form injection, rates, payment integration, register hook, NP directories, and model code remain unchanged.
- Existing legacy/duplicate rows are not deleted automatically; remove them manually only after successful QA.
