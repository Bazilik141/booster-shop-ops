# Claude Code Report — UI-FIX post-deploy: reCAPTCHA anchored to the login column

Date: 2026-09-03
Handoff: `handoffs/handoff_UI-FIX_postdeploy-captcha-regression_20260903.md`
Patch: `patches/UI-FIX_login-captcha-anchor_20260903.php`
Regression source: `UI-FIX_mobile-desktop-polish_20260903.php` task T2 (mine),
already deployed and self-deleted — this is a fix on top, not a resend.

## Scope

Only the widget's targeting/anchor, per the handoff's guardrails. Two files:

```
catalog/view/template/account/login.twig                                   +1 attribute (+6-line comment)
extension/ps_google_recaptcha/catalog/model/captcha/ps_google_recaptcha.php  1 rule re-anchored
```

Untouched, and asserted by the patch: the T2 column order (Увійти still first),
`<form id="form-login">` and its action/tokens, the Увійти button markup, the
registration column, every other route's injection rule, captcha behaviour and
configuration. No DB writes.

Root cause and the wiring analysis are in the patch header — not repeated here.
The short version: the extension injects `{{ captcha }}` before the **2nd**
`<div class="text-end">` in the template source (`'positions' => [2]`), and T2
made that the registration column. The fix replaces the positional anchor with
a named slot, `data-captcha-slot="login"`, that exists only in the login column.

## Why this was login-breaking and not cosmetic

Confirmed against the extension template and the live page: the site runs
`key_type = v2_checkbox`, whose only wiring is `grecaptcha.render(...)`. Google
injects `<textarea name="g-recaptcha-response">` inside the widget container,
and that value reaches the POST for exactly one reason — the container is a DOM
descendant of `#form-login`. There is no hidden proxy field, no `form=`
attribute, no JS that copies the token. Once the widget moved into the
registration column, which has no `<form>` at all, the response stopped being
posted. `recaptcha_form1 = document.currentScript.closest('form')` also became
`null`, so the post-submit reset stopped binding. Restoring containment restores
both; nothing else carried the value, so nothing else needed preserving.

## Verification

The injection cannot be exercised by running the template, so I reimplemented
the extension's own mechanism: parse the `$views` rules out of the model source
and apply them exactly as `replaceViews()` does (`str_replace`, or `replaceNth`
when `positions` is set). Fixtures are the real files — `login.twig` in its
currently-deployed post-T2 state, reconstructed by replaying the shipped T2
replacements onto the 2026-08-28 backup copy, and the extension model from the
same backup.

**The simulation reproduces the live defect before it confirms the fix** — a
check that passed in both states would prove nothing:

| # | State | Captcha lands in | Inside `#form-login` |
|---|---|---|---|
| 1 | pre-T2 (the state that worked) | ЛОГІН | YES |
| 2 | **currently deployed — the defect** | **РЕЄСТРАЦІЯ** | **NO** |
| 3 | after this patch | ЛОГІН | YES |
| 4 | after this patch, columns swapped again | ЛОГІН | YES |

Row 2 matches the owner's report and the handoff's DOM evidence exactly. Row 4
is the point of the fix: the anchor no longer depends on column order, so a
future reorder cannot move the widget again.

Other checks:

- `--dry-run` reports both files and writes nothing.
- Patch-time assertions: slot unique in the twig; slot present on both sides of
  the model rule; `'positions' => [2]` gone; the button rule still matches the
  untouched button; two columns still present; form line byte-identical; and two
  structural gates that print on every run —
  `verified=slot_is_inside_form-login` and `verified=t2_column_order_unchanged`.
- `php -l` on the written extension model after the write, restore-on-fail (C4);
  patch self-lints before touching anything.
- Line endings preserved per file: `login.twig` stays CRLF (66/0),
  the model stays LF (0/220). They differ, so this was worth checking.
- Repeat run → `already_applied=yes`, self-deletes (C5, C7).
- Rollback tested: copying both files back from `_patch_backups/` returns the
  simulation to row 2, i.e. the backup is a true rollback.
- `php -l` clean; `scripts/check-php-host-compat.php` clean (no PHP 8.1+
  construct, no mysqlnd-only call).

## Run command (owner, from `~/public_html`)

Syntax gate first, on production's own PHP 8.0.30:

```bash
php -l UI-FIX_login-captcha-anchor_20260903.php
```

```bash
php UI-FIX_login-captcha-anchor_20260903.php --dry-run
```

```bash
php UI-FIX_login-captcha-anchor_20260903.php
```

## Post-deploy QA

- [ ] `?route=account/login` — the reCAPTCHA checkbox sits in the **Увійти**
      column, under the password field and above the Увійти button. Desktop and
      ~390px both (owner reported the misplacement on both).
- [ ] **Complete a real login.** This is the check that matters: the captcha is
      functionally required, so a successful login is the only proof the
      response is being posted again.
- [ ] Registration column shows no captcha and the Продовжити button still
      works.
- [ ] Solve the captcha, submit with a deliberately wrong password, then retry —
      the widget should reset rather than stay consumed (that is the
      `closest('form')` binding coming back).
- [ ] Column order unchanged: Увійти first, Реєстрація second.
- [ ] Checkout captcha unaffected — a different injection rule, untouched here,
      but it shares the extension file this patch edits.

## Risks

- **Marketplace extension.** A future update of `ps_google_recaptcha` would
  overwrite the model and reinstate the positional rule, sending the widget back
  to the registration column and breaking login again. Editing this extension in
  place is established practice here (`RD-13.1D/E/H`, 2026-07-12), and the
  `data-captcha-slot="login"` attribute is deliberately self-documenting so the
  link is greppable from either side — but it is worth a line in the extension
  upgrade checklist.
- The fixture for `login.twig` is a reconstruction of the deployed file, since
  the deployed patch self-deleted and the newest backup predates it. The
  reconstruction replays the shipped T2 replacements onto the backup copy, and
  the patch's own anchor pre-check will refuse to apply if production differs
  from it in any byte of the anchored lines.
- The stray `"` in the rendered Увійти button
  (`data-size="normal" ">`) comes from the extension's `$button_tpl` when
  `key_type` is `v2_checkbox`, is pre-existing, and is out of scope here.
  Harmless in practice — browsers drop the malformed attribute — but worth its
  own ticket.
