# Handoff — ACC-003: Login and registration silently bounce back to the form

Date: 2026-08-22 | Parent: none (new defect, P0 conversion loss)
Executor: **Codex · model=Terra · effort=high** — the two target files are already
identified and the edit is small, but it is authentication code with a
silent-failure mode, so it needs a careful multi-step executor rather than a
mechanical one. Owner decides; weekly quota is a legitimate override.

⚠ Roadmap ID `ACC-003` is **provisional**. It is free in `context-index.md`
(ACC-001, ACC-002 only) but has NOT been verified against the Notion registry.
Verify before the Notion row is created.

---

## 1. Root cause (proven)

OpenCart 4.1.0.3 regenerates a **single-value, per-render** form token on every
render of the login and register pages:

- `catalog/controller/account/login.php:96` — `$this->session->data['login_token'] = oc_token(26);`
- `catalog/controller/account/register.php:50` — `$this->session->data['register_token'] = oc_token(26);`

The POST handler compares the submitted token with the session value. On
mismatch it returns a bare redirect **with no error message**:

- `login.php:120-122` → `$json['redirect'] = account/login`
- `register.php:145-146` → `$json['redirect'] = account/register`

`catalog/view/javascript/common.js:127-129` then executes `location = json['redirect']`.
Result for the customer: the page reloads, they stay on the same form, no
message, not logged in.

Any **second render** of the page after the form was displayed overwrites the
session token and kills the form the customer is looking at. Since 2026-08-06
something re-requests the current page from the browser about 4–5 seconds after
load, which makes that second render happen on nearly every visit.

### The re-request

Access-log signature (`logs/boostershop.website-ssl_log-Aug-2026.gz`):

```
12/Aug/2026:22:24:05 GET /?route=account/login    200 11607  ref=…/account/wishlist…
12/Aug/2026:22:24:10 GET /?route=account%2Flogin  200 11594  ref=https://boostershop.website/?route=account/login
12/Aug/2026:22:24:17 POST /?route=account/login.login&login_token=97bb…  200 85
12/Aug/2026:22:24:17 GET /?route=account/login    200 11589   ← silent bounce
```

Properties: same IP and User-Agent, `Referer` = the page itself, full HTML
response, delay 4–5 s, route re-encoded as `%2F`.

`%2F` proves the request is **client-side JavaScript**, not a link and not a
server redirect: `catalog/controller/startup/seo_url.php:168` explicitly
converts `%2F` back to `/` in every URL the server generates
(`str_replace(['%2F'], ['/'], http_build_query($query))`). A re-serialised query
string (`URLSearchParams.toString()` or equivalent) is what produces `%2F`.

### Attribution

| Date | Event | Evidence |
|---|---|---|
| ≤ 2026-08-05 10:49 | Microsoft Clarity already installed in `header.twig:86-92` | `backup-8.5.2026_10-49-27_boosters.tar.gz` |
| 31/Jul – 04/Aug | 6 login POSTs, 2 register POSTs — **zero** silent failures, **zero** `%2F` self-requests from real browsers | month access log |
| 2026-08-05 | Owner installs the Plerdy snippet in `footer.twig` (now lines 148-162) | `handoffs/handoff_ANALYTICS-001_plerdy-tracking-install_20260805.md` |
| 2026-08-06 21:17 | First `%2F` self-request from a real browser | month access log |
| from 2026-08-07 | Silent failures begin and continue daily | month access log |

Clarity is exonerated by the 2026-08-05 backup: it predates the onset and
produced no self-requests. Plerdy is the only change in the window. Not yet
confirmed by direct network capture — see §5.

---

## 2. Production impact (month access log, 31/Jul – 21/Aug)

Correlation over all 76 login POSTs, matching each POST to the most recent
render of `account/login` by the same IP:

| Last render before POST | Silent bounce | Success | Real error message |
|---|---|---|---|
| the `%2F` self-request | **43** | 1 | 4 |
| the normal page load | 2 | 19 | 7 |

- **Login:** 44 silent failures / 70 attempts from 07/Aug. Zero in the 6 attempts before 05/Aug.
- **Registration:** 19 silent failures / 22 attempts from 06/Aug. Zero in the 2 attempts before.

Failures appear on both mobile and desktop User-Agents. The reported
mobile/desktop asymmetry is a timing effect, not a platform difference: the
re-request lands ~4–5 s after page load, so a fill-and-submit that takes longer
than that loses the race. Desktop password-manager autofill usually beats it.

**Not affected:** guest checkout registration (`checkout/register.save`) — a
different route with no per-render token; it succeeds in the same log window.
`account/forgotten.confirm` returning 85 bytes is its **success** redirect, not
this bug.

---

## 3. Immediate mitigation (owner, no code, do first)

Remove or comment out the Plerdy block in
`~/public_html/catalog/view/template/common/footer.twig`, lines 148-162
(`<!-- BEGIN PLERDY CODE -->` … `<!-- END PLERDY CODE -->`), via cPanel File
Manager. Keep a copy of the block.

This removes the trigger, not the defect. The defect below must still be fixed —
otherwise the same silent bounce returns with any future tag, prefetch, second
tab, or bfcache back-navigation.

---

## 4. Scope (what the executor changes)

- `catalog/controller/account/login.php` — in `index()`, generate
  `$this->session->data['login_token']` **only when it is not already set**, so
  the value is stable for the whole session instead of per render. `login()`
  already unsets it on success, so the next render issues a fresh one.
- `catalog/controller/account/login.php` — in `login()`, on token mismatch set
  `$this->session->data['error']` to `error_token` (already rendered by
  `index()` at line 55-58) before returning the redirect, so the customer sees
  a reason instead of a blank reload.
- `catalog/controller/account/register.php` — same two changes for
  `register_token`; `index()` must expose the message the same way login does.
  If `register.php` has no `session.data['error']` render path, add the minimal
  one rather than inventing a new message channel.
- Language strings: reuse existing `error_token` from
  `extension/ukrainian/catalog/language/uk-ua/account/*.php`. If a key is
  missing, state it and stop — do not invent Ukrainian copy.

A per-session token is the standard CSRF pattern and is strictly stronger here
than a per-render token that any background request can silently invalidate.

## What NOT to touch

- `catalog/view/javascript/common.js` — the `location = json['redirect']`
  behaviour is correct once the server stops sending a redirect for a live form.
- `catalog/controller/startup/seo_url.php` — the `%2F` handling is correct.
- `catalog/controller/startup/session.php` — SameSite/cookie handling is not
  implicated; the session survives, only the token value is overwritten.
- Anything under `checkout/` — different route, not affected.
- `footer.twig` / `header.twig` — the tag decision is the owner's (§3).

## Acceptance criteria

- [ ] Two consecutive renders of `account/login` produce the **same**
      `login_token` in the form action while the session lasts.
- [ ] Loading `account/login`, then re-requesting the same URL in a second tab,
      then submitting valid credentials in the first tab → logs in.
- [ ] Same for `account/register`.
- [ ] A genuinely stale token (session cleared server-side) → the customer sees
      a visible message on the reloaded page, never a blank reload.
- [ ] Patch follows conventions C1–C7 in `AGENTS.md`.

## QA checklist (owner runs on production after deploy)

- [ ] Mobile, real device: log in with a real account — succeeds first attempt, 5 attempts in a row.
- [ ] Mobile: register a throwaway account — reaches the address form.
- [ ] Desktop: same two flows.
- [ ] Wrong password still shows the Ukrainian error message (not a blank reload).
- [ ] Tier 1 smoke URLs from `AGENTS.md` all return 200.
- [ ] After 24 h: `grep 'login.login' access log | grep '" 200 85 '` returns nothing new.

## Risks

- Authentication path, production-direct, no staging. Rollback = restore the two
  files from `_patch_backups/`.
- If the owner restores Plerdy later, re-run the login QA before considering it
  safe — the fix removes the failure mode, but a tag that re-requests every page
  also doubles server load on every view.

---

## 5. Open item for the owner (2 minutes, optional)

Direct confirmation that Plerdy issues the re-request, if wanted before removing it:

1. Open `https://boostershop.website/?route=account/login` on desktop, F12 → Network, filter `Doc`.
2. Wait 10 s. A second document request to `?route=account%2Flogin` appears.
3. Right-click the `a.plerdy.com/public/js/click/main.js` request → Block request URL. Reload, wait 10 s.
4. If the second document request is gone, attribution is confirmed.
