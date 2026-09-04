# Handoff — UI-FIX post-deploy defect: reCAPTCHA follows column order on account/login

Date: 2026-09-03. Written by Claude (chat) after owner QA on `UI-FIX_mobile-desktop-polish_20260903.php`
(patch 2 of the UX-036 batch), already deployed to production. Root cause
confirmed live on `https://boostershop.website/?route=account/login` via
read-only DOM inspection (no form submitted, no data entered by Claude).

**Priority: high.** Owner has since confirmed by testing that the captcha is
functionally tied to login on this site — this is not cosmetic. Real customers
logging in are affected. Fix before the next DB-touching patch in this batch if
practical; does not block `home-category-tiles` (unrelated files).

Executor: Claude Code · model=Sonnet · thinking=medium — well-bounded, file
already identified below; root cause is confirmed, only the render source of
`#g-recaptcha-1` on this page needs to be traced and made column-order
independent. Same executor as the rest of this batch, for continuity.

## Owner-reported symptom

Both desktop and mobile: the reCAPTCHA widget and its "response missing"
validation message render inside the **Реєстрація** (registration) column
instead of the **Зареєстрований клієнт** (login) column. Owner confirms by
testing that the captcha is genuinely required for login on this site (not
just visually misplaced) — so this blocks or degrades real customer logins,
not only registration.

## Confirmed root cause

`patches/UI-FIX_mobile-desktop-polish_20260903.php`, section `T2`, swapped
which top-level `.col.mb-3` block renders first in `catalog/view/template/account/login.twig`:
before, Реєстрація was first / Логін second; after, Логін first / Реєстрація
second. The form itself (`#form-login`, its `action`, its tokens) is
byte-unchanged — confirmed in the round-1/round-2/round-3 review chain.

Live DOM inspection (2026-09-03, read-only, no submit) on the current page:

```
document.getElementById('g-recaptcha-1') → parented under:
  DIV#g-recaptcha-1 → DIV.col-sm-10 → DIV.col.mb-3.required
  → DIV.border.rounded.p-3.d-flex.flex-column.h-100 → DIV.col.mb-3 (2nd top-level .col.mb-3)
  → DIV.row → DIV#content.col → DIV#account-login.container

g.closest('#form-login') → null   (the widget is NOT a DOM descendant of the login <form> element,
  even though the owner confirms it functionally gates login)
```

So whatever renders `#g-recaptcha-1` targets **the second `.col.mb-3` on the
page**, not a stable id for "the login column" or "the register column". T2's
reorder made that second slot the registration column instead of login,
so the widget (and its required/validation state) moved with it. This is a
positional dependency in whatever attaches the widget — not something T2's own
diff touched directly, and not the ACC-003 login/register token-session bug
(`diagnostics/ACC-003_login-register-token-session-stable_report_20260822.md`,
a controller-level token issue in `login.php`/`register.php`). Trace where
`#g-recaptcha-1` is actually emitted (OpenCart's recaptcha extension render
call, likely `extension/ps_google_recaptcha/...`, or a controller/`{{ captcha }}`
hook — see the render path used for checkout's captcha in
`patches/RD-13.1D_checkout_visual_root_fixes_20260712.php` for a precedent of
how this extension's widget gets targeted elsewhere in this codebase) and
anchor it to a stable target tied to the login column specifically, so it
stops depending on DOM order. Given the owner confirms it functionally gates
login submission (not just visually adjacent), also verify directly how the
`g-recaptcha-response` value reaches the login POST despite not being a DOM
descendant of `#form-login` — e.g. a hidden proxy field, JS that copies the
value in before submit, or a `form=` attribute — so the fix keeps that wiring
intact and only changes placement/targeting.

## Scope guardrails

Do not touch the T2 column order itself (that reorder — Увійти first — was the
requested change and is otherwise correct) or `#form-login`'s markup/action/
tokens. Fix only how the recaptcha widget is targeted/anchored, and preserve
whatever currently wires its value into the login submission. New patch file,
following the usual C1–C7 conventions; this is a fix on top of an already-
deployed patch, not a resend of `UI-FIX_mobile-desktop-polish_20260903.php`
(which already ran and self-deleted). Verify on both breakpoints (owner
reported the same misplacement on desktop and mobile).

## Other QA from this round — no action needed

- Preorder delivery ETA line (`Component D`) shows in the product-page info
  rows, correctly gated by status — matches design intent (product page only,
  never on catalog tiles).
- Breadcrumb truncation (`…` on long titles) confirmed working, both
  breakpoints.
- Homepage tiles unchanged — expected, that is a separate patch
  (`UI-FIX_home-category-tiles_20260903.php`) not yet run.

---
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_016VGrbhuBLnM2B31XeDjxYP
