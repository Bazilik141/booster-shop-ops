# ST-2a.7 Guest Captcha Exemption - Codex Report

Date: 2026-06-13

## Baseline

- Handoff: `handoffs/handoff_ST-2a7_guest-captcha-exempt_2026-06-13.md`
- Backup inspected: `(10PM)backup-6.12.2026_21-54-05_boosters.tar.gz`
- Target file verified in backup: `catalog/controller/checkout/register.php`
- Anchor: checkout `save()` captcha block exists once.

## Patch

- File: `patches/st2a7_guest_captcha_exempt_20260613.php`
- Live target: `catalog/controller/checkout/register.php`
- Scope: comment out only the captcha validation block inside checkout `register.save`.
- DB changes: none.
- Not touched: SimpleCheckout, `system/library/url.php`, `catalog/controller/account/register.php`, captcha settings, Hutko, Checkbox, NP quote cost, CRM payload.

## Behavior

- Guest stock checkout `checkout/register.save` no longer sets `error.captcha` for empty `g-recaptcha-response`.
- Standalone `/account/register` keeps its captcha because it is handled by `catalog/controller/account/register.php`, not this controller.
- Original checkout captcha block is preserved as comments in the patched file; rollback is restoring the patch backup.

## Validation

- Patch file syntax: `php -l` ok.
- Dry-run against extracted 10PM backup file: `done=ok`.
- Patched `catalog/controller/checkout/register.php`: `php -l` ok.
- Post-patch grep: ST-2a.7 marker present; original `$json['error']['captcha']` remains only in comments for checkout controller rollback context.

## Run Command

```bash
cd ~/public_html || exit
php st2a7_guest_captcha_exempt_20260613.php
```

No cache clear required for this controller-only PHP change.

## QA After Deploy

- Guest anon stock checkout: email + phone + NP city/warehouse save returns `{"success":...}` on `checkout/register.save`; no `error.captcha`.
- Guest checkout then loads shipping/payment and can create an admin order.
- Logged-in saved-address flow unchanged.
- `/account/register` still shows captcha and rejects empty/invalid token.
