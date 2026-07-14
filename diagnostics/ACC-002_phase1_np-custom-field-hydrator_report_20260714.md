# Codex Report — ACC-002 Phase 1: NP account picker + `bs_np_v1` hydrator

Date: 2026-07-14

## Scope

Implemented the owner-approved Phase 1 contract from `handoff_ACC-002_phase1_np-custom-field-hydrator_20260714.md` against `booster-debug-ACC002-phase1-live.tar.gz`, collected after live deployment of CHECKOUT-003 and ACC-001.

No DB schema change, bulk address rewrite, guest checkout change, order gate, payment, Hutko, Checkbox, totals, or CRM change is included. The runner requires the live CHECKOUT-003 autosave gate in `checkout.twig` before it will write anything.

## Metadata contract

The extra data is merged into the existing `oc_address.custom_field` JSON under the mandatory versioned key:

```json
{
  "bs_np_v1": {
    "version": 1,
    "type": "warehouse | poshtomat | courier",
    "area_ref": "NP area ref",
    "city_ref": "NP city ref",
    "warehouse_ref": "NP warehouse/poshtomat ref or empty",
    "street_ref": "NP street ref or empty",
    "labels": {
      "area": "human-readable area",
      "city": "human-readable city",
      "point": "warehouse/poshtomat/street label"
    },
    "house": "courier only",
    "flat": "courier only"
  }
}
```

The controller starts an edit from the stored JSON, merges all submitted real account custom fields with `array_replace()`, and then replaces only `bs_np_v1`. A constructed merge fixture confirmed that existing custom-field key `12` and a newly submitted key `15` both survive alongside the renewed metadata key.

If a pre-existing `custom_field` is invalid JSON, OpenCart exposes it as non-array. The patch treats that row as legacy and blocks the edit instead of writing an empty JSON value over the raw column; the customer is told to create a new NP address.

## Endpoints and hydrator

The account picker reuses the current module endpoints without server/module modification:

```
extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|searchArea
extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|searchCity
extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|searchWarehouse
extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|searchStreet
```

The supplied live module controller exposes these as ordinary POST directory lookups; it has no checkout-only/session guard. Account save independently verifies the selected area, city, warehouse/poshtomat or street through the same live module models before persisting labels and refs.

For checkout, the new session-scoped `account/address.npMetadata` endpoint returns metadata only for the logged-in customer’s own address. `checkout-reskin.js` fetches it for the selected saved address, re-validates each ref through the same directory endpoints, then writes the NP fields and hidden refs. Every programmatic type change is enclosed by `window.bsCheckoutNpInitialising = true`, which is the CHECKOUT-003 gate; hydration therefore cannot schedule `checkout/register.save` by itself.

## Legacy and stale fallback

- Missing, malformed, or incomplete `bs_np_v1`: checkout shows one explicit `Переобрати адресу НП` prompt. It does not use text parsing to hydrate a point.
- A metadata ref not returned by the current directory endpoint (including a closed warehouse): the same explicit prompt appears.
- Pressing the prompt switches to the manual NP form and clears all hydrated refs under the CHECKOUT-003 programmatic-event guard. The old `oc_address` row is not changed.
- `parseAddressText()` remains only for presentation of legacy address text; structured-address hydration is metadata-only.

## Files touched

```
patches/ACC-002_phase1_np-custom-field-hydrator_20260714.php
diagnostics/ACC-002_phase1_np-custom-field-hydrator_report_20260714.md
```

The runner changes exactly:

```
catalog/controller/account/address.php
catalog/view/template/account/address_form.twig
catalog/view/javascript/checkout-reskin.js
```

## Dry-run result

Tested on an isolated fixture made from the supplied post-deploy live archive:

```text
dry_run=ok patch=ACC-002_phase1_np-custom-field-hydrator_20260714 files=3 php_l=ok
done=ok patch=ACC-002_phase1_np-custom-field-hydrator_20260714 files=3 php_l=ok
```

The runner safe-fails before backup/write if any target or anchor has drifted, if a prior marker is partial, or if the CHECKOUT-003 marker/gate is absent from the live `checkout.twig`.

## Syntax checks

```text
No syntax errors detected in ACC-002_phase1_np-custom-field-hydrator_20260714.php
No syntax errors detected in catalog/controller/account/address.php
account_picker_js_parse=ok
checkout_reskin_js_parse=ok
```

The first JS check parses the generated account-picker script block. The second uses `node --check` on the patched reskin source. All three target files preserved LF line endings from the fresh live archive.

## Idempotency

Fixture replay:

```text
already_applied=yes patch=ACC-002_phase1_np-custom-field-hydrator_20260714
```

## Rollback

The runner creates:

```text
_patch_backups/ACC-002_phase1_np-custom-field-hydrator_20260714-<timestamp>/
```

Restore all three target files from the same backup directory, then clear `DIR_CACHE` entries. Rows that already carry `bs_np_v1` remain harmless after rollback: stock OpenCart ignores the extra JSON key.

## Run command (owner)

```bash
cd ~/public_html || exit
php ACC-002_phase1_np-custom-field-hydrator_20260714.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Before tests, record max order ID and the count of status-0 drafts.
- [ ] Logged-in account → add address: type/select area, city, branch; free typing without selecting a dropdown option must not save.
- [ ] Repeat for a poshtomat and courier address; courier requires a selected street and house number.
- [ ] Edit an address that already has a normal account custom field. After save, verify that field still has its old value and `bs_np_v1` is present (owner may inspect the address through the form/checkout; do not expose raw JSON publicly).
- [ ] Edit a legacy free-text address: the visible re-pick notice appears; the old string remains reference-only; save requires a fresh NP selection.
- [ ] New registration → redirect to address form → NP selection → save. Confirm the existing redirect back to checkout still occurs when cart has products.
- [ ] New authorised checkout session with a structured saved address: DevTools shows metadata fetch plus directory validation; city and point populate from refs; no `checkout/register.save` request is generated solely by hydration.
- [ ] Select a legacy address and then simulate a stale saved point (or use a known unavailable point): exactly one re-pick prompt appears, no JavaScript error, and the old row is not changed.
- [ ] Press the re-pick prompt, choose a new valid NP point and complete a COD order. Confirm exactly one order and no new draft on page refresh/open.
- [ ] Run full `bs-checkout-smoke`: authorised structured-address COD, authorised Hutko sandbox, and one guest order; verify payment/fiscal/CRM readback and draft count.

## Side effects / risks

- Medium-high saved-address change, constrained to the three listed files. It intentionally removes free-text NP selection from account address add/edit.
- The new metadata endpoint is read-only and verifies address ownership through the logged-in customer ID; it exposes neither other customers’ metadata nor raw corrupted JSON.
- Directory validation has been proven from source and fixture parsing, not from a live authenticated browser session. Owner QA must confirm the four lookup requests on the deployed account form before using it for real orders.
- No DB migration or automatic rewrite exists. A failed server-side point validation returns a normal form error rather than silently storing a mismatch.
