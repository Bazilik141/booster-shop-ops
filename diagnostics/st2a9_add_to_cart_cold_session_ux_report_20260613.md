# ST-2a.9 Cold-Session Add-to-Cart UX - Codex Report

Date: 2026-06-13

## Baseline

- Handoff: `handoffs/handoff_ST-2a8_guest-autosave-ref-gate_2026-06-13.md`
- Backup inspected: `backup-6.13.2026_16-15-57_boosters.tar.gz`
- Backup SHA256: `55EBF4AD5C9A8F53192BB0340F2F39B371AD302F6DF4BB5274BF965E842ED6F3`
- Target: `catalog/view/template/product/product.twig`

## Patch

- File: `patches/st2a9_add_to_cart_cold_session_ux_20260613.php`
- Live target: `catalog/view/template/product/product.twig`
- Scope: client JS only, B1 UX mitigation.
- DB changes: none.
- Server/cart/session changes: none.
- Not touched: cookie banner, GA4/GTM/Clarity, cart totals, `checkout/cart.add` business logic.

## Behavior

- Add-to-cart submit now has an in-flight guard, so repeated clicks cannot send parallel `cart.add` requests.
- The button shows `Додаємо у кошик...` while the request is pending.
- AJAX timeout is set to 12000 ms.
- On complete, timeout, or error, the button is always re-enabled.
- On timeout/error, a visible retry warning is shown instead of leaving a permanent spinner.
- On success, the existing success alert and mini-cart refresh remain unchanged.

## Validation

- Patch file syntax: `php -l` ok.
- Dry-run against extracted fresh backup: `done=ok`.
- Repeat run on patched copy: `already_applied=yes`.
- Post-patch grep: ST-2a.9 marker, `bsCartAddPending`, `timeout: 12000`, and retry warning present.

## B2 Status

B2 server profiling is intentionally not implemented as a blind fix. The measured 7-8 s cold `cart.add` request still needs server-side timing evidence before any session/render/server patch:

- measure cold `checkout/cart.add` wall time;
- measure product page render time while cold session is being created;
- identify whether the delay is session lock wait, module work, DB, or analytics/event work;
- only then propose a server-side fix with rollback.

## Run Command

```bash
cd ~/public_html || exit
php st2a9_add_to_cart_cold_session_ux_20260613.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## QA

- Cold session: clear `OCSESSID` + `policy`, reload product page, click add-to-cart once.
- Button shows progress and never remains permanently loading.
- If request succeeds, item is added and mini-cart updates.
- Warm session add-to-cart remains fast and unchanged.
- Double-click during pending request does not double-send.
