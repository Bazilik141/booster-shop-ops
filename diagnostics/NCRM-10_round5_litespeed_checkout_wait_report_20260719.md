# Codex Report — NCRM-10 round 5: LiteSpeed checkout-wait follow-up

Date: 2026-07-19

## Scope

Investigate the owner-recorded wait between checkout submission and success
redirect after the round-5 NCRM deferral. This is a diagnosis only; no server
file, database, Edge Function, or checkout behavior was changed.

## Evidence and finding

The owner HAR records the COD payment-confirm request as approximately 12.9 s.
The immediately preceding `checkout/confirm.confirm` request is approximately
249 ms, and the success-page document is approximately 80 ms.

Fresh source from `booster-debug-pinta-confirm.tar.gz` establishes that
`extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php`
does not call Nova Poshta or any cURL API in `confirm()`. Its only substantive
operation is:

1. call `model_checkout_order->addHistory(order_id, status_id)`;
2. return the success redirect JSON.

The wait is therefore inside the shared OpenCart order-status side-effect path,
not Pinta's payment controller or a Nova Poshta API lookup. The inspected order
model shape performs Telegram notification before registering the CRM/NCRM
shutdown callbacks. The existing Booster CRM sender performs a synchronous
remote request before falling back to a local queue on failure. NCRM has a
separate remote sender.

Round 5 was deliberately conditional on `fastcgi_finish_request()`. Its own
report records the limitation: if that API is unavailable, shutdown callbacks
remain on the client request path. The storefront response identifies LiteSpeed;
LiteSpeed cPanel installations normally run LSPHP/LSAPI, so the PHP runtime
must be checked before relying on the PHP-FPM-specific deferral.

This proves the Pinta controller is not the slow external client. It does not
yet attribute the 12.9 s between Telegram, Apps Script CRM, and NCRM without
current instrumented source/runtime evidence.

## Files inspected

```
extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php
extension/PintaNovaPoshtaCod/catalog/model/payment/pinta_nova_poshta_cod.php
extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/pintanovaposhtaapi.php
catalog/view/template/checkout/checkout.twig
patches/NCRM-10_round5_defer-order-sync-after-response_20260719.php
diagnostics/NCRM-10_round5_defer-order-sync-after-response_report_20260719.md
```

## Dry-run result

Not applicable: no patch was created or run.

## PHP lint result

Not applicable: no PHP source was modified.

## Idempotency

Not applicable: no patch was created.

## Rollback

Not applicable: diagnosis only.

## Required fresh source before a fix

```
catalog/model/checkout/order.php
system/library/booster_crm_sync.php
extension/telegram_notify/ (exact live module directory)
```

The next implementation must decide whether customer-visible checkout should
write local queue records only and use an owner-configured cron worker for CRM
and NCRM delivery. That is a reliability/operations change and must not be
substituted with an unverified background-process trick on shared hosting.

## Post-deploy QA checklist

Not applicable until a separate scoped patch exists.

## Side effects / risks

- Moving remote senders to a queue changes delivery timing and requires a
  durable worker plus retry/duplicate handling.
- Deferring only NCRM cannot guarantee the customer redirect is fast while
  Telegram and the legacy CRM sender remain in `addHistory()`.
