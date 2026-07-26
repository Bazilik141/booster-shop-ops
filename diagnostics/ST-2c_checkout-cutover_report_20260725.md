# Codex Report — ST-2c: checkout cutover

Date: 2026-07-25

## Scope
Implements the handoff 1:1: removes the `system/library/url.php` redirect from the stock `checkout/checkout` route to SimpleCheckout. No DB/settings changes and no Mono-credit change; `payment_mono_chast_status` remains outside this patch.

## Files touched
```
patches/ST-2c_checkout-cutover_20260725.php             — uploadable self-contained patch
diagnostics/ST-2c_checkout-cutover_report_20260725.md   — this report
```

## Live-source basis
Newest full cPanel backup checked: `backup-7.24.2026_17-02-32_boosters.tar.gz` (2026-07-24). It contains one exact redirect in `homedir/public_html/system/library/url.php`, lines 63–64. Source SHA-256: `941B44ECA33AA207B874972941EC29B188DD066E309ED689ED7D875F6F374969`.

## Dry-run result
Local fixture from that exact backup: `done=ok`; one automatic backup created; marker present; obsolete SimpleCheckout redirect absent. The runner refuses to write unless the live file has that exact SHA-256 and exactly one redirect anchor.

## php -l result
```
No syntax errors detected in ST-2c_checkout-cutover_20260725.php
php_l=ok (patched system/library/url.php in fixture)
```

## Idempotency
After a successful run, the retained ST-2c marker causes a repeat upload/run to return `already_applied=yes` without writing.

## Rollback
Backup at: `_patch_backups/ST-2c_checkout-cutover_20260725-<timestamp>/system/library/url.php`

To restore:
```bash
cp _patch_backups/ST-2c_checkout-cutover_20260725-<timestamp>/system/library/url.php system/library/url.php
```

## Run command (owner)
```bash
cd ~/public_html || exit
php ST-2c_checkout-cutover_20260725.php
```

## Post-deploy QA checklist
- [ ] Open a fresh incognito cart and every normal "Оформити"/checkout entry point; all must land on stock `checkout/checkout`, not SimpleCheckout.
- [ ] Run the full `bs-checkout-smoke` on the now-default route: guest and authorized, First15/coupon, Hutko/COD/IBAN, and Nova Poshta office/address/courier including the tariff fallback.
- [ ] Complete one preorder cart through a non-credit method.
- [ ] Confirm Mono credit stays hidden/disabled (`payment_mono_chast_status=0`) and inspect the first production orders closely.

## Side effects / risks
High rollout risk: this changes the default checkout for all traffic. The changed source is one routing override only; SimpleCheckout files, payment/order-write logic, DB and settings are untouched. Rollback is a single-file restore from the automatic backup.
