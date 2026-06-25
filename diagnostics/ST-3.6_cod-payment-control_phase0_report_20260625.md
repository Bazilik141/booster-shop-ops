# Codex Report — ST-3.6: COD PaymentControl Phase 0

Date: 2026-06-25

## Scope
Handoff scope is diagnostic only. No code changes were made.

ST-3.6 is high-risk because it changes how money is collected for Nova Poshta COD orders. The current goal is to identify the exact Nova Poshta API payload for "Контроль оплати" and produce a minimal implementation plan.

## Current Module Behavior
File reviewed from `backup-6.23.2026_11-21-43_boosters.tar.gz`:

```
extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
```

The TTN payload is assembled in:

```
createInternetDocument()
prepareInternetDocumentProperties()
```

The module currently sends classic backward delivery money data:

```php
if ($backward_delivery_cargo_type !== 'Disabled') {
    $internet_document_properties['BackwardDeliveryData'] = array(
        array(
            'CargoType' => $backward_delivery_cargo_type,
            'PayerType' => $backward_delivery_payer_type,
            'RedeliveryString' => $backward_delivery_cargo_string,
        ),
    );
}
```

The form defaults are currently inverted relative to the business need:

```php
if (!in_array($paymentCode, array('pinta_nova_poshta_cod', 'pinta_nova_poshta_cod.pinta_nova_poshta_cod'))) {
    $formdata['backward_delivery_cargo_type'] = 'Money';
    $formdata['backward_delivery_payer_type'] = 'Recipient';
    $formdata['backward_delivery_cargo_string'] = $cost;
} else {
    $formdata['backward_delivery_cargo_type'] = 'Disabled';
    $formdata['backward_delivery_payer_type'] = 'Recipient';
    $formdata['backward_delivery_cargo_string'] = '';
}
```

Per handoff, this inverted COD/default behavior is documented only and not changed in this phase.

## Official API Status
I attempted to access the official Nova Poshta developer documentation at:

```
https://developers.novaposhta.ua/documentation
https://developers.novaposhta.ua/documentation?modelName=InternetDocument&calledMethod=save
```

The local fetch failed with TLS/client credential errors in this environment, so I could not safely confirm the exact official `PaymentControl` / "Контроль оплати" payload shape from the source required by the handoff.

Because this is a real-money COD flow, I do not recommend implementing from guesses or third-party snippets.

## Integration Point
Once the exact official API shape is confirmed, the smallest implementation point is:

```
extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
prepareInternetDocumentProperties()
```

That method already owns:

- `PayerType`
- `PaymentMethod`
- `Cost`
- sender/recipient refs
- `BackwardDeliveryData`
- final payload passed to `PintaInternetDocument->saveInternetDocument()`

The sender type is already available in:

```
prepareSender()
$counterparty['counterparty_type']
```

For a clean implementation, store the sender counterparty type on the controller during `prepareSender()`, for example:

```php
$this->sender_counterparty_type = $counterparty['counterparty_type'];
```

Then select the money service while assembling the InternetDocument payload.

## Proposed Rule
Do not implement until official payload is confirmed.

Expected business rule:

- Sender `PrivatePerson`: keep classic `BackwardDeliveryData` / `CargoType=Money`.
- Sender legal entity / FOP / organization: use Nova Poshta "Контроль оплати" service.
- Amount should match the declared COD/order amount already represented by `$cost`, unless official docs require a different field or amount base.
- Payer should remain recipient unless official docs specify otherwise.

## Minimal Future Patch Plan
1. Add a controller property for sender counterparty type.
2. In `prepareSender()`, after loading `$counterparty`, save its `counterparty_type`.
3. In `prepareInternetDocumentProperties()`, branch the money-service block by sender type.
4. For `PrivatePerson`, preserve existing `BackwardDeliveryData`.
5. For FOP/legal sender, add the official "Контроль оплати" payload exactly as documented.
6. Preserve ST-3.5-1/-2/-3 changes.
7. Do not touch checkout/payment/Hutko/Checkbox/totals/DB/CRM.

## Risk
High. A wrong payload can create a real TTN with wrong money collection behavior or be rejected by Nova Poshta. This must be tested with one controlled real TTN and then cancelled/deleted if it is only QA.

## Required Input Before Implementation
One of:

- official documentation excerpt/link from `developers.novaposhta.ua` showing `InternetDocument/save` payload for "Контроль оплати";
- or a successful sample request/response from Nova Poshta support/API cabinet for a FOP/legal sender with payment control.

## Rollback
No rollback needed for this phase. No files were changed.
