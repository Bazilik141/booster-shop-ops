# Codex Report — ACC-002E: repair selected saved NP address in place

Date: 2026-07-15

## Scope

Fixed the remaining single-duplicate path after ACC-002D. The earlier patch stopped repeated NP autosaves, but the explicit `Переобрати адресу НП` flow still opened a create-only form without the selected address ID. A successful re-pick therefore called `addAddress()` and left the legacy row without `bs_np_v1` in the address book.

ACC-002E carries the selected legacy/stale address ID only for the explicit re-pick action and updates that customer-owned row through `editAddress()` after the existing server-side NP ref validation. Ordinary `+ Інша адреса` still creates one normal new address, and structured duplicate submissions still use ACC-002D idempotent reuse.

No schema migration, SQL cleanup, address deletion, or bulk rewrite is included. Existing historical duplicates remain until the owner/customer removes them separately.

## Confirmed root cause

- `checkout-reskin.js` displayed the legacy/stale prompt from the selected address metadata state.
- The prompt button only switched `shipping_existing` to the NP form and cleared the visible fields.
- `checkout_shipping_address_form.twig` had no hidden field carrying the address being repaired.
- `shipping_address.php` consequently had only two outcomes: reuse an already structured identity or call `addAddress()`.
- A legacy row has no `bs_np_v1`, so it cannot match structured identity reuse; the server added one valid row and left one legacy row that continued to show the re-pick warning.

This directly matches the post-ACC-002D symptom: one valid address plus one crooked legacy address, instead of the former repeated duplicates.

## Files touched

```text
patches/ACC-002E_checkout-np-repair-in-place_20260715.php

Live targets modified by the patch:
catalog/controller/checkout/shipping_address.php
catalog/view/javascript/checkout-reskin.js
```

## Dry-run result

```text
dry_run=ok patch=ACC-002E_checkout-np-repair-in-place_20260715 files=2 php_l=deferred
```

## Syntax and focused checks

```text
No syntax errors detected in ACC-002E_checkout-np-repair-in-place_20260715.php
No syntax errors detected in catalog/controller/checkout/shipping_address.php
node --check catalog/view/javascript/checkout-reskin.js: passed
self_delete=ok
```

Focused invariants checked in the applied diff:

- repair ID is posted only by the explicit re-pick action;
- ownership is checked by `getAddress(customer_id, address_id)`;
- missing/foreign repair IDs fail without a write;
- repair uses `editAddress()`, so the address count does not increase;
- the target row's `default` flag is preserved;
- successful repair clears the hidden target and forces fresh `npMetadata` hydration;
- ordinary new-address mode clears any stale repair target.

## Idempotency

Re-running on the patched fixture returns:

```text
already_applied=yes patch=ACC-002E_checkout-np-repair-in-place_20260715
```

The first successful run self-deletes the uploaded patch.

## Rollback

Backup created before writing:

```text
_patch_backups/ACC-002E_checkout-np-repair-in-place_20260715-<timestamp>/
```

Restore both target files from that directory if rollback is required, then clear the OpenCart data/template caches.

## Run command (owner)

```bash
cd ~/public_html || exit
php ACC-002E_checkout-np-repair-in-place_20260715.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Before the test, note the address count for the test customer.
- [ ] Select the crooked address and confirm the re-pick warning appears.
- [ ] Click `Переобрати адресу НП`, select a valid city/point once, and wait for autosave.
- [ ] Confirm the dropdown count did not increase and the same row no longer shows the re-pick warning.
- [ ] Confirm Network response contains the same `address_id` and `address_updated=true`.
- [ ] Click `+ Інша адреса`, choose a genuinely different NP point, and confirm exactly one new row is created with `address_updated=false`.
- [ ] Re-select the same structured point and confirm `address_reused=true` with no count increase.
- [ ] Verify the repaired address still remains default if it was default before repair.
- [ ] Complete one authorised checkout smoke test.

## Side effects / risks

- Risk: medium-high because authorised checkout and Nova Poshta address-book writes are affected.
- No database schema or bulk-data operation occurs at patch execution.
- A user explicitly clicking re-pick now edits that selected address in place; this is the intended semantic change.
- Existing historical duplicate rows are not deleted. They can be removed manually in the account after the flow is verified.
