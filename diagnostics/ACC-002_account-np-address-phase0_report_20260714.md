# Codex Phase 0 Report — ACC-002: NP-structured account address form

Date: 2026-07-14

## Scope

Read-only Phase 0 against `booster-debug-CHECKOUT003-ACC001-ACC002-live.tar.gz`. No account form, checkout, database, saved address row, or Nova Poshta configuration was changed.

## Established current contract

### Account address persistence

`catalog/controller/account/address.php` accepts the stock fields and validates free text: `address_1` (3–128), `city` (2–128), country and zone. `catalog/model/account/address.php` persists only the stock `oc_address` fields:

```
firstname, lastname, company, address_1, address_2, postcode,
city, zone_id, country_id, custom_field (JSON), default
```

`catalog/view/template/account/address_form.twig` is likewise stock-shaped, although relabelled: free-text `city`, free-text `address_1` for branch/poshtomat, free-text `address_2` for courier address, and the normal zone select. It has no NP type, area/city/warehouse/street ref fields.

The current post-registration redirect is real and remains intact: `catalog/controller/account/register.php` sets `address_required_after_register` and redirects to `account/address.form`; `account/address.php::save()` later returns the customer to checkout if the cart still has products.

### Checkout NP contract

The checkout NP form uses the module directory endpoints `searchArea`, `searchCity`, `searchWarehouse` and `searchStreet`. During a selection it maintains client-only hidden refs:

```
shipping_novaposhta_area_ref
shipping_novaposhta_city_ref
shipping_novaposhta_warehouse_ref
shipping_novaposhta_street_ref
```

Checkout synchronisation submits human-readable `city` and `address_1` plus the normal zone/country fields. The supplied code contains no account-address persistence path for those refs or the NP delivery type.

### Saved-address read path

`catalog/view/javascript/checkout-reskin.js` consumes a saved address as the rendered option text and uses `parseAddressText()` heuristics (`поштомат`, `відділення`, street words) to infer its display/type. It does not read a structured NP ref/type from `oc_address` or `custom_field` and cannot reliably hydrate an exact city/warehouse selection.

## Conclusion

The Phase 1 acceptance criterion “account-saved address → a new checkout session has city and warehouse preselected” cannot be met with account-side changes alone in the supplied current code. The account model has no persisted NP refs/type, and the checkout has no reader for such metadata.

Creating a visually similar account form that only fills `city` and `address_1` would improve data entry but would not prove the required exact round trip. It would silently leave legacy and new rows dependent on display-text parsing, which is not acceptable for a delivery address.

## Existing address inventory

The archive contains source files only, not an `oc_address` export or a read-only DB count. Therefore the existing-address count and the share of legacy free-text rows are **not established**. No PII was requested or inspected.

If the count is needed before Phase 1, I will provide a separate host-safe, read-only diagnostic runner after the owner selects the storage/legacy policy. I will not guess the CLI bootstrap or run a database query from an unverified command.

## Owner decision required before Phase 1

1. **Recommended:** preserve legacy rows, use the existing `custom_field` JSON column for versioned NP metadata (no schema migration), and approve one narrowly scoped checkout reader/hydrator. New form data can then round-trip exactly; legacy rows prompt the customer to re-pick an NP point on the next checkout.
2. Keep Phase 1 strictly account-only: use the NP picker but persist only display text to stock fields. This cannot meet exact checkout preselection and is not recommended.
3. Add dedicated DB columns for NP refs/type. This requires explicit DB approval and a rollback statement; it provides a clearer schema but is more invasive than option 1.

For legacy rows, choose one policy:

- **A:** leave them intact; when used in checkout, require manual NP re-pick.
- **B:** display a clear prompt to re-pick on the next saved-address use, without bulk rewriting anything.

## Phase 1 boundary after approval

Option 1 necessarily expands ACC-002 to include the minimal checkout metadata reader/hydrator. Per the handoff, this requires explicit approval before implementation; no such patch is included now. No DB change is proposed for option 1.

## Risks

- A free-text-only replacement risks wrong warehouse/type inference and breaks the requested saved-address round trip.
- A database inventory or migration without an owner-approved command/rollback would exceed this Phase 0 boundary.
- Guest checkout, confirmation/order gates, Hutko, Checkbox, totals and CRM remain untouched.
