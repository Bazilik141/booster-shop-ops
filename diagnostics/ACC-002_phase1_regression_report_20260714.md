# ACC-002 Phase 1 — regression diagnosis

Date: 2026-07-14  
Scope: authorised checkout saved Nova Poshta addresses, recipient prefill, and the Nova Poshta re-pick form.  
Risk: medium-high; checkout and customer-address surfaces. No database was changed during this investigation.

## Evidence from the fresh live archive

Archive: `booster-debug-ACC002-regression-live.tar.gz` supplied after the Phase 1 deploy.

### 1. False `stale` classification — confirmed root cause

The browser message `Збережена точка Нової пошти більше не доступна` is emitted only after `validateSavedNpMetadata()` returns `false` in `catalog/view/javascript/checkout-reskin.js`.

That function validates a saved point by calling the public autocomplete endpoint `searchWarehouse` with its display label and then looks for the stored `warehouse_ref` in the returned option list. This is not a lookup-by-ref.

Read-only reproduction against the live public endpoint on 2026-07-14:

```text
POST /index.php?route=extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta|searchWarehouse
string=Поштомат №49489
city=Дніпро
type=poshtoma

{"options":[]}
```

The message therefore proves an autocomplete/index miss, not that the saved point has disappeared. The Phase 1 account-save path had already validated the point by `warehouse->getByRef($point_ref)`, which is the appropriate identity operation.

Required fix: move saved-metadata validation to the authorised `account/address.npMetadata` endpoint and validate the exact refs with the module models. The browser must consume the endpoint result and must not revalidate a known ref through text autocomplete.

### 2. Recipient fields — confirmed root cause

`catalog/controller/checkout/checkout.php` puts only `rd13_receiver_override` session values into `receiver_override_firstname` / `receiver_override_lastname`. It does not pass the logged-in customer's profile name.

`checkout-reskin.js` then falls back to parsing the selected saved-address option text. `cacheReceiverName()` accepts the placeholder `-- Виберіть --`; consequently that placeholder becomes the first and last name shown in the receiver card. The telephone is separate and comes from the customer profile, matching the observed UI.

Required fix: supply profile first/last name as the default for an authorised customer while preserving a non-empty per-order receiver override. Never parse or cache an empty/placeholder select option as a name.

### 3. Address preselection — corrected finding after owner feedback

The earlier conclusion that the observed blank selection meant no primary address was **not proven and is withdrawn**.

The fresh live controller does attempt to hydrate `customer.address_id` for a logged-in customer when `session['shipping_address']` is empty. Therefore a real primary address should be selected in a fresh empty session.

The controller has a separate unsafe branch: it treats every non-empty `session['shipping_address']` as authoritative, even if it has `address_id = 0`, no `address_id`, or no longer belongs to the logged-in customer. Stock/register flows can construct `shipping_address` with `address_id = 0`; if such an array survives into an authorised checkout, the default-hydration branch is skipped and the rendered selector receives `address_id = 0`.

This is a verified source path, but it is **not yet proof** that it was the state of the owner's affected session. The primary-address theory is therefore not a root-cause claim. To prove this branch for a reproduction, capture the response for the `checkout/shipping_address` XHR in the affected browser session: the rendered selected option/value will distinguish `address_id=0` from a valid saved address. A defensive fix may treat a session shipping address as usable only when its non-zero id resolves for the current customer; it is session-only and does not write the database.

### 4. Duplicate NP recipient inputs — confirmed root cause

`extension/PintaNovaPoshtaCod/catalog/view/template/shipping/checkout_shipping_address_form.twig` unconditionally renders first name, last name, and middle name inputs in the manual NP form. They duplicate the redesigned receiver card after `Переобрати адресу НП`.

Required fix: for an authorised checkout, synchronize the hidden module values from the receiver card and hide the duplicate visual fields. Guest checkout retains its existing required fields and validation.

## Released-for-review no-DB patches

1. `ACC-002A_saved-np-ref-validation_20260714.php`
   - `catalog/controller/account/address.php`
   - `catalog/view/javascript/checkout-reskin.js`
   - moves the saved warehouse/poshtomat availability check to the authorised
     server endpoint and checks the immutable `warehouse_ref`; the browser no
     longer treats a text-autocomplete miss as a removed saved point.

2. `ACC-002B_authorized-np-recipient-ui_20260714.php`
   - `catalog/controller/checkout/checkout.php`
   - `catalog/view/javascript/checkout-reskin.js`
   - uses the authorised customer profile for the receiver first/last-name
     fallback, rejects placeholder text as a receiver name, and synchronizes
     the module's hidden NP fields while hiding its duplicate recipient inputs.

Neither patch changes the initial saved-address policy. The session-address
gate remains deferred until the faulty session state is captured.

## Verification performed

- Fresh-live file inspection: passed.
- Read-only public NP autocomplete request: reproduced the false-negative result.
- Both patches: exact-anchor dry-run passed against
  `booster-debug-ACC002-regression-live.tar.gz`.
- Both patches: full fixture run created backups, passed `php -l`, self-deleted
  on success, and returned `already_applied=yes` on a repeat run.
- Resulting `catalog/controller/account/address.php` and
  `catalog/controller/checkout/checkout.php`: `php -l` passed.
- Resulting `catalog/view/javascript/checkout-reskin.js`: `node --check` passed.

## Rollback

No live files or database rows were changed. On a live run each patch creates
its own `_patch_backups/<patch>-<timestamp>/` folder before writing.

## Planned post-patch QA

1. Authorised new structured warehouse and poshtomat addresses hydrate without a re-pick prompt.
2. A truly missing ref still shows the stale re-pick prompt.
3. A legacy address still shows the legacy re-pick prompt.
4. New authorised checkout shows profile first/last name even before an address is selected; placeholder text never becomes a name.
5. Clicking re-pick has no duplicate visible name inputs for an authorised customer; guest inputs remain visible and valid.
6. No register autosave fires during programmatic hydration; run the authorised and guest checkout smoke checks.
7. Verify an existing saved warehouse and poshtomat address on desktop and
   mobile: neither shows the false stale alert; a legacy address still shows
   the legacy re-pick prompt.
8. Verify the original intermittent address-preselection case separately; it
   is intentionally outside these two patches.
