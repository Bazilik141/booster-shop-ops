# Handoff — ACC-003: Login and registration silently bounce back to the form

Date: 2026-08-22 | Parent: none (P0 conversion defect)
Executor: **Claude Code · model=Opus · thinking=high** — owner assignment
2026-08-22. This **overrides** the original recommendation in this file
(`Codex · Terra · high`); recorded, not re-argued. Opus/high because this is
authentication code whose failure mode is silent, and the executor must verify
the live files against this handoff before editing.

Notion: `ACC-003` · page `3c46bf20-bdb4-8147-8107-e7a80c93baa3`
Dashboard mirror: `ROADMAP_TASKS` row `ACC-003`, status `todo`

> Read §11 before writing anything: line numbers here come from
> `backup-8.21.2026_22-06-47_boosters.tar.gz`, not from the live server.

---

## 1. Task ID

`ACC-003` — login and registration return a bare redirect to their own form,
with no message, whenever the per-render form token was regenerated after the
form was displayed.

## 2. Context

OpenCart 4.1.0.3. Both controllers mint a fresh single-value token on **every**
render and store it in the session:

| File | Mints | Compares | On mismatch |
|---|---|---|---|
| `catalog/controller/account/login.php` | `:96` `$this->session->data['login_token'] = oc_token(26);` | `:120` | `:121` `$json['redirect'] = account/login` |
| `catalog/controller/account/register.php` | `:50` `$this->session->data['register_token'] = oc_token(26);` | `:145` | `:146` `$json['redirect'] = account/register` |

`catalog/view/javascript/common.js:127-129` then runs
`location = json['redirect']`. The customer sees the page reload, the form
empty, no error, and is still logged out. A correct wrong-password attempt
behaves differently (it returns `json.error.warning` and shows a red alert), so
the customer cannot tell the two apart — they conclude their password is wrong.

Any second render of the page while the form is open overwrites the token in the
session and kills the form the customer is looking at. Triggers seen or possible:
a third-party tag that re-requests the page, browser speculative prefetch, a
second tab, back/forward navigation, any future module that renders these pages.

### What made it constant

The Plerdy tag (installed 2026-08-05) re-requested the current URL ~4–5 s after
every page load — same origin, with cookies, route re-serialised as `%2F`.
Access-log signature:

```
12/Aug/2026:22:24:05 GET /?route=account/login    200 11607  ref=…/account/wishlist…
12/Aug/2026:22:24:10 GET /?route=account%2Flogin  200 11594  ref=https://boostershop.website/?route=account/login
12/Aug/2026:22:24:17 POST /?route=account/login.login&login_token=97bb…  200 85
12/Aug/2026:22:24:17 GET /?route=account/login    200 11589   ← silent bounce
```

`%2F` proves the caller was client-side JS, not a link and not a server
redirect: `catalog/controller/startup/seo_url.php:168` converts `%2F` back to
`/` in every URL the server builds. Microsoft Clarity is exonerated — it was
already present in `backup-8.5.2026_10-49-27` and produced no self-requests.

**The owner removed the Plerdy block from `footer.twig` on production 2026-08-22**
(`handoffs/handoff_ANALYTICS-001_plerdy-tracking-install_20260805.md`). That
removed the trigger only. The defect is live: the same 21/Aug log already shows
Chrome issuing a duplicate speculative request to `/?route=account/login`
(`200 0`) with no tag involved.

### Impact, production access log 31/Jul – 21/Aug

Each login POST matched to the most recent render of `account/login` by the same IP:

| Last render before POST | Silent bounce | Success | Real error message |
|---|---|---|---|
| the `%2F` self-request | **43** | 1 | 4 |
| the normal page load | 2 | 19 | 7 |

Login 44 silent failures / 66 attempts from 07/Aug (0 / 6 before). Registration
18 / 22 from 06/Aug (0 / 2 before). Of 20 visitors who submitted either form, 17
hit it; 8 never reached an account, 8 never registered.

## 3. Goal

A form token that stays valid for as long as the customer has the form open,
whatever else renders the page — and, when a token genuinely is invalid, a
visible Ukrainian message instead of a blank reload.

## 4. What to change

**One work package, one patch file**, covering both controllers. They are two
files but one defect with one acceptance criterion; shipping half of it leaves
registration broken and makes the QA result unreadable.

1. `catalog/controller/account/login.php`, `index()` — mint `login_token` **only
   when the session does not already hold one**. Keep the existing value on
   subsequent renders. `login()` already unsets it on success, so the next
   render issues a fresh one; do not change that.
2. `catalog/controller/account/register.php`, `index()` — same treatment for
   `register_token`. Verify against the live file whether `register()` unsets it
   on success the way `login()` does, and preserve whatever it does.
3. `login.php`, `login()` — on token mismatch return
   `$json['error']['warning'] = $this->language->get('error_token');`
   **instead of** `$json['redirect']`. `common.js:133-141` renders
   `json.error.warning` into `<div id="alert">` (`common/header.twig:246`), so
   the customer keeps their input and sees why.
4. `register.php`, `register()` — same substitution.

Language keys already exist, both verified in the backup — do **not** write new
Ukrainian copy:

- `extension/ukrainian/catalog/language/uk-ua/account/login.php:22` `error_token`
- `extension/ukrainian/catalog/language/uk-ua/account/register.php:25` `error_token`

A session-stable token is the standard CSRF pattern and is strictly stronger
here than a per-render token any background request can silently invalidate.

## 5. Do not touch

- `catalog/view/javascript/common.js` — its `location = json['redirect']` and
  `json.error.warning` handling are both correct; the bug is that the server
  sends a redirect for a form that is still alive.
- `catalog/view/template/account/login.twig`, `register.twig` — no markup change
  is needed. `register.twig`'s inline script only drives the customer-group
  field.
- `catalog/controller/startup/seo_url.php` — the `%2F` handling is correct.
- `catalog/controller/startup/session.php` — cookie, SameSite and lifetime are
  not implicated. The session survives; only the token value is overwritten.
- `catalog/controller/account/forgotten.php` — its `reset_token` is a mailed
  one-shot credential with different semantics. Out of scope; if you think it
  has the same flaw, say so and stop.
- Anything under `checkout/` — guest registration runs `checkout/register.save`,
  a different route, unaffected in the same log window.
- `footer.twig` / `header.twig` — analytics tags are the owner's decision.
- Protected zones, untouched by this task and not to be edited for any reason:
  `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout,
  payment, fiscalization, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas

Paths are from the newest backup and are **likely, not confirmed** against the
live server:

- `~/public_html/catalog/controller/account/login.php`
- `~/public_html/catalog/controller/account/register.php`

Both were stock OpenCart 4.1.0.3 in the backup — no prior Booster patch touches
them (`patches/` grep: `ACC-001`, `ACC-002*`, `CHECKOUT-003` touch account
address and menu code, not these two files). If the live files differ from the
line references above, stop and report rather than patching by resemblance.

## 7. Acceptance criteria

- [ ] Two consecutive renders of `account/login` in one session return the
      **same** `login_token` in the form action URL.
- [ ] Same for `register_token` on `account/register`.
- [ ] Open `account/login`, load the same URL in a second tab, then submit valid
      credentials in the first tab → `POST account/login.login` returns
      `{"redirect": "…route=account/account&customer_token=…"}` and the customer
      lands logged in.
- [ ] Same sequence on `account/register` → reaches `account/address.form` or
      `account/success`.
- [ ] Wrong password still returns `json.error.warning` and renders a red alert.
- [ ] A genuinely invalid token (session cleared server-side) returns
      `json.error.warning` with the existing `error_token` string — never a bare
      `json.redirect`, never a blank reload.
- [ ] `php -l` clean on both files.
- [ ] Patch satisfies conventions C1–C7 in `AGENTS.md` (file-exists check, anchor
      pre-check with expected counts, backup to `_patch_backups/<patch>-<ts>/`,
      `php -l` gate with restore-on-fail, `already_applied=yes` marker,
      no DB changes, self-delete).

## 8. QA / smoke test

Owner runs on production after deploy — there is no staging.

- [ ] Mobile, real device: log in with a real account, 5 attempts in a row, all succeed.
- [ ] Mobile: register a throwaway account → reaches the address form.
- [ ] Desktop: both flows.
- [ ] Wrong password → Ukrainian error message, form keeps the entered e-mail.
- [ ] Log out and back in — confirms the token still rotates per session.
- [ ] Tier 1 smoke URLs from `AGENTS.md` all return 200.
- [ ] After 24 h, in the access log:
      `grep 'account/login.login' <log> | grep '" 200 85 '` and
      `grep 'account/register.register' <log> | grep '" 200 86 '`
      return **no new lines**. Those two byte sizes are the silent-bounce
      signature — that grep is the durable regression check for this task.

`bs-checkout-smoke` not required — checkout, payment and fiscalization are not
touched. `bs-merchant-schema-qa` not required — no schema or feed change.
`bs-seo-risk-gate` not required — no sitemap, robots, canonical, redirect or
`.htaccess` change.

## 9. Rollback note

Patch convention C3 writes both original files to
`_patch_backups/ACC-003_login-register-token-session-stable-<ts>/` before any
write; C4 restores on a failed `php -l`.

Manual rollback: copy
`_patch_backups/ACC-003_login-register-token-session-stable-<ts>/login.php` and
`register.php` back over
`~/public_html/catalog/controller/account/`. No DB change, so nothing else to
undo. Twig runs with `auto_reload => true`
(`system/library/template/twig.php:109`), and these are PHP controllers anyway —
no cache clear needed either way.

Rollback trigger: any login or registration path that worked before the patch
stops working, or `error_token` appears on a first, clean attempt.

## 10. Recommended status after execution

`In progress` while the patch is being written; the owner deploys and runs §8;
`Done` only after the owner confirms the mobile login and registration checks
and authorizes closure. Claude (chat) performs the Notion write and the
`ROADMAP_TASKS` mirror. The executor writes neither.

## 11. Delivery

- One patch file: `patches/ACC-003_login-register-token-session-stable_20260822.php`
- Drop to `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\patches\`
- Owner uploads it to `~/public_html` and runs `php ACC-003_login-register-token-session-stable_20260822.php`
- Report: `diagnostics/ACC-003_login-register-token-session-stable_report_20260822.md`
- The executor never commits, pushes, deploys, or touches Notion.
- Only one patch author per task per round. Codex must not start this task.

Every line reference in this handoff comes from
`backup-8.21.2026_22-06-47_boosters.tar.gz`. Verify each anchor against the live
file before editing; if an anchor count differs from expected, stop and report
rather than guessing the equivalent line.
