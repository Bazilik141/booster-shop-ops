# Codex Report — PAY-003: PUMB admin order panel

Date: 2026-09-01

## Scope

Implemented a PUMB lifecycle panel on the standard OpenCart admin order page. The panel is shown only for PUMB orders and provides an explicit bank-status refresh plus state-gated shipment confirmation and full-refund actions.

The OpenCart status `Доставляється` is intentionally not wired to `goods_shipped=true` in this round. Bank shipment confirmation remains a separate, confirmed admin action so an ordinary status edit cannot accidentally trigger a financial workflow.

## Root cause

PUMB callbacks updated the local transaction table, but the test callback flow intentionally did not advance OpenCart order history after `WAITING_CLIENT`. The admin order page had no direct view of the canonical PUMB transaction and no supported way to request a current bank state. This made a valid bank transition appear stuck in OpenCart.

## Files touched

```text
patches/PAY-003_pumb-admin-order-panel_20260901.php
extension/pumb_credit/admin/controller/payment/pumb_credit.php
<detected-admin-directory>/view/template/sale/order_info.twig
```

The production admin directory is discovered structurally at runtime; the runner does not hardcode `adminEvhenii`.

## Runtime behavior

- The panel reads the latest local PUMB transaction for the order without calling the bank.
- `Перевірити статус у ПУМБ` explicitly sends a bank GET, validates the returned state, and refreshes the existing local transaction.
- `Підтвердити передачу перевізнику` is available only in `WAITING_STORE_CONFIRM` and sends `goods_shipped=true` after explicit confirmation.
- `Повне повернення` is available only in `FUNDED` when the agreement and canonical stored credit amount are present.
- Bank actions are blocked if the transaction environment fingerprint no longer matches the active TEST/PROD settings.
- Access requires the admin sale/order permission; mutations also require modify permission for the PUMB extension.
- Short server-side locks and client busy guards prevent accidental duplicate actions.
- No credentials, agreement number, guarantee letter, or raw bank payload are rendered into the order page.

## Local validation

```text
candidate controller: PHP syntax OK
runner: PHP syntax OK
PHP checks: checks=23 result=ok bank_calls=0 database_writes=0
client checks: checks=13 result=ok network=0 dom=static
runner clean fixture: done=ok, self_delete=ok
runner repeat fixture: already_applied=yes
runner rollback fixture: rollback=ok, original SHA256 restored
source-drift fixture: refused before backup/write
rollback-drift fixture: refused without overwriting changed target
```

Candidate SHA256:

```text
extension/pumb_credit/admin/controller/payment/pumb_credit.php
9dec4558a7d7395ae02c7830a9aa4b7740e72161ffac777de6ab8aeb0402d82a

<detected-admin-directory>/view/template/sale/order_info.twig
e3ddfb5689f5650db7e57ec3b22a53339622a2872483700d4f8fcee5992247e2

patches/PAY-003_pumb-admin-order-panel_20260901.php
ff4e48c21c37b971d92451f126e86d80df5e4f566f7e976a4624af979bd5700f
```

## UI discipline

Prior patches touching the same order template and PUMB admin controller were inspected. The new panel uses the existing Bootstrap layout and controls; it adds no CSS file, `!important`, delayed timers, fixed/absolute positioning, or magic color overrides. Static markup/JavaScript checks passed. Real admin-theme behavior and responsive layout remain part of owner post-deploy QA.

## Idempotency

Re-running an uploaded copy after a successful deployment returns `already_applied=yes`. The successful runner self-deletes, so a second run requires re-uploading it.

## Rollback

The runner creates `_patch_backups/PAY-003_pumb-admin-order-panel_20260901-<timestamp>/` with original files, generated candidates, a manifest, and a drift-protected rollback script. Use the exact rollback command printed by the successful run.

## Run command (owner)

```bash
cd ~/public_html || exit
php PAY-003_pumb-admin-order-panel_20260901.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Runner reports `done=ok`, `self_delete=ok`, `database_touched=no`, and `bank_calls=no`.
- [ ] Open order #341 and confirm the PUMB panel shows TEST, CAP ID `19040764`, term `3`, current bank state, and last update time.
- [ ] On order #341 click only `Перевірити статус у ПУМБ`; expected final state is `REFUND_FINISHED` once the bank has completed the already submitted refund.
- [ ] Do not press shipment or refund on order #341: its full refund was already submitted before this patch.
- [ ] Verify the order page at desktop, tablet, and phone widths and confirm buttons wrap without overlap.
- [ ] Run Tier 1 storefront smoke URLs, including cart and checkout entry.

## Side effects / risks

- Patch installation makes no database writes and no bank calls.
- An explicit status refresh calls the bank and updates the existing PUMB transaction row, but does not add OpenCart order history. The panel is the current source for the synchronized bank state in this round.
- Shipment and refund buttons perform real bank mutations in the transaction's matching environment. Their state gates, confirmations, locks, and environment fingerprint reduce but do not eliminate operator risk.
- Automatic mapping from OpenCart `Доставляється` to shipment confirmation is deliberately out of scope pending a separately approved status/automation policy.
