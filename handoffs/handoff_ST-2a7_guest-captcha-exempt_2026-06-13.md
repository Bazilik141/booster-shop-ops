# Codex Handoff — ST-2a.7: guest checkout blocker = reCAPTCHA on checkout/register (exempt, Path B)

Date: 2026-06-13. Parent: ST-2 Path B (stock OC4 checkout, pre-cutover — clients still on SimpleCheckout).
Base: `(10PM)backup-6.12.2026_21-54-05` + applied deltas st2a.4 + st2a.5. Diagnosis by Claude on backup + live payload capture (2026-06-13).

## 1. Task ID
ST-2a.7 — guest checkout shipping methods never load. Root cause = reCAPTCHA v2-checkbox validated server-side on guest `checkout/register.save` with an empty token. Fix = exempt captcha validation in the **checkout** register controller only.

## 2. Context — ROOT CAUSE (confirmed: code + DB config + live payload)
- **DB config** (`ocp5_setting`, verified in 10PM dump): `config_captcha = ps_google_recaptcha`; `captcha_ps_google_recaptcha_status = 1`; `config_captcha_page = ["catalog_login","forgotten_password","register"]` (includes `register`); `captcha_ps_google_recaptcha_key_type = v2_checkbox`.
- **Code** (`catalog/controller/checkout/register.php::save()`): the captcha block is gated only by `if (!$this->customer->isLogged())` — i.e. it runs for **guests** (and skips for logged-in, which is why the logged-in flow works e2e). All three inner conditions are true for a guest, so `.validate` runs.
- **Live payload** (anon checkout, guest): `account=0`, `shipping_address_1` set (Відділення №11…), `shipping_zone_id=3484` (real zone present), and crucially `g-recaptcha-response=` is **empty** while `grecaptcha` is loaded (`object`). The ps_google_recaptcha extension injects the widget into the guest register form via its `checkout/register/before` event, but a v2-checkbox token is never produced by the autosave-on-blur flow → empty token.
- **Effect chain:** empty token → `.validate` returns error → `$json['error']['captcha']` set → `$json` non-empty → the `if (!$json) { ... }` success branch (which writes `session['shipping_address']` and returns success) is skipped → guest never gets a saved shipping address → `shipping_method.quote` has nothing → «Спосіб доставки» stuck on placeholder forever. st2a.5 (`address_id=0`) could not help because save dies on captcha before reaching that branch.
- v2-checkbox **cannot** be executed programmatically (unlike v2-invisible / v3), so it is fundamentally incompatible with the autosave-on-blur UX. Owner decision (2026-06-13): **Path B — exempt captcha on the checkout register route**, keep captcha on standalone `/account/register`, login, and forgotten_password.

## 3. Goal
Guest can complete an order on the stock checkout route. `checkout/register.save` for a guest must NOT fail on captcha. Captcha protection on `/account/register`, login, and forgotten_password must remain unchanged.

## 4. What to change
Single file: `catalog/controller/checkout/register.php`, method `save()`.

Neutralize the captcha validation block so it never sets `$json['error']['captcha']` in THIS controller. This controller serves ONLY the checkout register endpoint; the standalone account-registration captcha lives in a different controller (`catalog/controller/account/register.php`) and must stay intact.

Anchor block to modify (verify against actual file — line numbers shifted ~+1 by st2a.5; match by content, not line):
```php
			// Captcha
			$this->load->model('setting/extension');

			if (!$this->customer->isLogged()) {
				$extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $this->config->get('config_captcha'));

				if ($extension_info && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
					$captcha = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code'] . '.validate');

					if ($captcha) {
						$json['error']['captcha'] = $captcha;
					}
				}
			}
```

Required change — comment the block out (keep verbatim for rollback) with an explicit marker, e.g.:
```php
			// ST-2a.7: captcha intentionally NOT validated on checkout/register.
			// Reason: reCAPTCHA v2-checkbox cannot be solved in the autosave-on-blur
			// guest flow (empty g-recaptcha-response -> guaranteed fail -> silent block).
			// Standalone /account/register (catalog/controller/account/register.php),
			// login and forgotten_password keep their captcha. Rollback: restore backup.
			// --- original block disabled below ---
			// $this->load->model('setting/extension');
			// if (!$this->customer->isLogged()) { ... $json['error']['captcha'] = $captcha; ... }
```
Scope = exactly this block. Do not change validation order, the `account` branch, password/agree checks, the `address_id` init (st2a.5), or the success branch.

Note (do NOT implement now, ST-6): the ps_google_recaptcha widget is still injected into the checkout register form by its event — now cosmetic/unused. Removing that injection is ST-6 cleanup, out of scope here.

## 5. Do not touch
- SimpleCheckout (`extension/SimpleCheckout/`), `system/library/url.php` (cutover is separate, 2c).
- `catalog/controller/account/register.php` and its captcha — must keep working.
- Captcha extension config in admin (`config_captcha`, `config_captcha_page`, `captcha_*`) — no DB/setting change; this is a code-side exemption only.
- Hutko / Checkbox / fiscalization, getQuote cost (`cost => 0`), NP events, DB schema/indexes (st2a.3 index stays), CRM payload format.
- Login (`catalog_login`) and forgotten_password captcha.
- sitemap.xml, robots.txt, redirects, canonical, .htaccess.

## 6. Likely files / areas
- `catalog/controller/checkout/register.php` (the ONLY change). Confirm the anchor block exists once; Codex should verify against the actual project file before patching.

## 7. Acceptance criteria
1. Guest (anon tab) on `index.php?route=checkout/checkout`: fill email + NP area/city/warehouse via dropdowns + phone → `checkout/register.save` returns **`{"success":...}`** (no `error.captcha`) — verify in DevTools Network response.
2. After that success: «Спосіб доставки» loads NP method(s), payment list loads, order can be placed to admin (any method, no live charge).
3. Logged-in flow unchanged (saved address → auto shipping → payment → confirm).
4. `/account/register` (standalone) still renders the reCAPTCHA widget AND still rejects a submit with empty/invalid token (captcha error present) — regression check that the exemption did not leak.
5. `php -l catalog/controller/checkout/register.php` → "No syntax errors detected".

## 8. QA / smoke test
**HIGH-RISK: checkout flow.** Run `bs-checkout-smoke` (11-step manual plan) after the patch. Minimum matrix for this change:
- Guest × {відділення, поштомат} → order reaches admin; check order history has no fake «Відмінений» (st2a.4 regression).
- Logged-in × saved address → unchanged.
- `/account/register` → captcha visible + empty-token submit blocked.
- Network check on guest `register.save`: response is `success`, request still contains the (now-ignored) empty `g-recaptcha-response` — harmless.

## 9. Rollback note
Patch-runner style: pre-check single anchor, backup to `_patch_backups/st2a7-<ts>/catalog/controller/checkout/register.php`, `php -l` gate, self-delete. **Rollback = restore that backup file** (one file, no DB changes). Clients unaffected (pre-cutover, SimpleCheckout still live).

## 10. Recommended status after execution
Owner QA (guest order e2e + AC4 account/register regression) → guest blocker CLOSED → proceed to **2b** (coupon/First15, agree consolidation, phone-required, GA4 dedupe, confirm-on-click) → **2c** (real shipping cost in totals + Hutko amount QA + url.php cutover). Update R-13.5 in Notion.

---
Open side-issue (separate, not this patch): first AJAX click in a fresh anonymous session hangs (infinite spinner on add-to-cart/confirm), F5 recovers, subsequent clicks fine. Log under 2a.4 tail; investigate after guest blocker.
