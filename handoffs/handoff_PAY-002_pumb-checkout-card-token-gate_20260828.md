# Handoff — PAY-002: PUMB checkout card behind a URL-token preview gate

Date: 2026-08-28
Executor: **Codex** · model=`Sol/xhigh` for WP2, `Terra/medium-high` for WP1 · effort=high
Justification: the `pumb_credit` extension was authored end-to-end by Codex across
PAY-002 and PAY-004; WP2 edits the shared live checkout controller and template
(risky zone), which rules out a small model. Owner decides; do not swap executor
mid-round.

Owner decision recorded 2026-08-28: visibility is gated by a **URL token**, not by
customer group. The earlier `customer_group_id = 3` proposal in
`handoffs/handoff_PAY-002_session-continuation_20260826.md` §4 is withdrawn.

---

## 1. Task ID

`PAY-002` (Notion: `3aa6bf20-bdb4-812a-b541-ef4d483f3657`, In progress).
Closes the outstanding dependency of `PAY-004`
(`3c66bf20-bdb4-81e3-93fd-d503961ab549`, In progress).

No new roadmap ID is created by this handoff. If the owner wants this tracked
separately, it goes through `bs-roadmap-write` first.

## 2. Context

Bank-side integration is proven end to end on the test contour (2026-08-26,
`cap_id 19040054`): create → sign → `WAITING_STORE_CONFIRM` → `goods_shipped` →
`FUNDED` → refund → `REFUND_FINISHED`. Evidence:
`diagnostics/PAY-002_bank-test-drive_result_20260825.md`.

Nothing on the bank side blocks launch. The single blocker is that **a customer
cannot choose PUMB at all** — the credit drawer renders PUMB as a static
`СКОРО БУДЕ` card.

Current live state, read from `backup-8.24.2026_10-35-09_boosters.tar.gz`
(the newest owner-provided backup; the PAY-004 and callback-basic-auth patches were
deployed **after** this backup was taken, so the executor must re-verify the two
files marked below against the live tree before writing anchors):

- `extension/pumb_credit/catalog/model/payment/pumb_credit.php` — `getMethods()`
  returns `[]` unconditionally. Same shape as the mono isolation.
- `extension/mono_chast/catalog/model/payment/mono_chast.php` — also returns `[]`.
  **The redesigned checkout does not source credit methods from the OC4 payment
  model.** It injects them in
  `catalog/controller/checkout/payment_method.php::getBoosterCheckoutPaymentMethods()`
  via `pay001MonoChastMethod()`, gated by `pay001MonoChastConfigured()`
  (`payment_mono_chast_status` + `api_base`/`store_id`/`store_secret` present) and
  `pay001MonoChastGate()` (threshold, payable total). Options are keyed
  `mono_chast_<n>` with codes `mono_chast.mono_chast_<n>`; session carries
  `pay001_mono_chast_parts` and `pay001_mono_chast_from_modal`.
- `catalog/view/template/checkout/payment_method.twig` — the drawer renders the
  monobank provider card with 3/4/5 buttons, and immediately after it a static
  block `pay001-checkout-provider--soon` containing
  `<em>СКОРО БУДЕ</em>`, `<small>До 5 платежів</small>` and
  `catalog/view/image/payment/pay001-pumb.svg`.
- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` **(re-verify:
  changed by PAY-004 after the backup)** — `index()` returns `''`; `confirm()`
  guards on `payment_pumb_credit_status` and an order id, then calls
  `requestedTerm()`, which reads `$this->request->post['term'] ?? $this->request->get['term']`,
  validates it against `payment_pumb_credit_terms` (`[3,4,5]`) and rejects with
  `error_term` when absent or invalid.
- `payment_pumb_credit_status` has **no row** in `oc_setting`. That is OpenCart's
  normal representation of an unchecked switch. Any guard must treat absent and
  `'0'` as equally disabled.

Environment: production PHP **8.0.30**, no 8.1+ binary on the host. `never`,
`enum` and `readonly` die at parse time before any guard runs. Host is LiteSpeed.
There is no staging — every patch lands directly on production.

## 3. Goal

A customer holding a secret link can select PUMB in the existing credit drawer,
pick 3/4/5 payments and complete a real application; every other visitor sees the
current `СКОРО БУДЕ` card and is unaffected. One admin switch later opens PUMB to
everyone with no further patch.

Explicit non-goal: no second checkout, no duplicated route, no copy of
`checkout/checkout`. A separate checkout URL would not scope payment-method
visibility — the gate has to be a server-side condition either way, so the copy
would add a permanent fork with no isolation benefit.

## 4. What to change

Two patch files, deployed in order. **Do not bundle them.** WP1 is invisible to
customers and independently verifiable in admin; WP2 is the risky-zone change and
must be rollback-able on its own.

### WP1 — `patches/PAY-002_pumb-preview-token-gate_20260828.php`

Touches the `pumb_credit` extension and its admin form only. No shared checkout
file is edited in this work package.

1. Two new settings on the existing PUMB admin form:
   - `payment_pumb_credit_preview_token` — free text, the secret used in the link.
     Empty means the preview entry point is closed.
   - `payment_pumb_credit_public` — checkbox, default `0`. `1` means PUMB is
     offered to every customer, ignoring the token.
   Preserve the existing admin-form save behaviour introduced by
   `patches/PAY-002_admin-status-settings-preserve_20260728.php`; do not regress it.
2. A preview entry point on the catalog controller, e.g.
   `extension/pumb_credit/payment/pumb_credit.preview`, that:
   - compares `$this->request->get['token']` against the configured token using
     `hash_equals()`, refusing when the configured token is empty;
   - on match, sets `$this->session->data['pay002_pumb_preview'] = true`;
   - accepts `off` (or an explicit clear parameter) to unset the flag, so the owner
     can leave preview mode without dropping the session;
   - always redirects to a clean URL afterwards, so the token does not stay in the
     address bar or leak through `Referer`;
   - never reveals whether the token was right — same redirect either way.
3. One shared server-side predicate in the PUMB controller, used by everything else:
   PUMB is offered when `payment_pumb_credit_status` is enabled **and** the API
   credentials are configured **and** (`payment_pumb_credit_public` is `1`
   **or** `pay002_pumb_preview` is set in session).
4. `confirm()` must call that predicate, not only `payment_pumb_credit_status`.
   This is the load-bearing check: once the method is enabled, the
   disabled-status safety net is gone and this predicate replaces it. A request
   that reaches `confirm()` without the gate must be refused server-side even if
   the UI is manipulated.

`getMethods()` in the PUMB model stays returning `[]` — legacy SimpleCheckout must
not gain PUMB. State that intent in a comment so a later reader does not "fix" it.

### WP2 — `patches/PAY-002_pumb-checkout-card_20260828.php`

Touches the shared live checkout. **Risky zone.**

1. In `catalog/controller/checkout/payment_method.php`, mirror the PAY-001 shape
   with PAY-002 names: a `configured` check, a `gate` (min/max total from
   `payment_pumb_credit_min_total` / `payment_pumb_credit_max_total`, currently
   500 / 500000), and a method builder producing options keyed `pumb_credit_<n>`
   with codes `pumb_credit.pumb_credit_<n>` for each term in
   `payment_pumb_credit_terms`. Inject it into
   `getBoosterCheckoutPaymentMethods()` alongside `mono_chast`. The injection must
   be skipped entirely when the WP1 predicate is false — verified server-side, not
   in the template.
2. In `catalog/view/template/checkout/payment_method.twig`, replace the static
   `pay001-checkout-provider--soon` PUMB block with a real provider card when the
   controller supplies a PUMB method, and keep the existing `СКОРО БУДЕ` block
   verbatim when it does not. Reuse the monobank provider markup and the existing
   term-button behaviour; do not introduce a new component or new CSS file.
   Purchase-action colour rules are unchanged — green stays reserved for purchase
   actions.
3. Term delivery to `confirm()` — this is what closes PAY-004. When a
   `pumb_credit.pumb_credit_<n>` code is selected, persist the chosen term in
   session (mirror `pay001_mono_chast_parts`; suggested key
   `pay002_pumb_credit_term`), and make `index()` in the PUMB catalog controller
   render its confirm trigger with that `term` value, since
   `checkout/confirm` loads `extension/<ext>/payment/<code>` for the selected
   method. **The executor must verify how `mono_chast::index()` renders its
   confirm trigger in the live tree and mirror that path** — do not invent a new
   AJAX contract. `confirm()` keeps its own independent validation of `term`
   against `payment_pumb_credit_terms`; the UI is not trusted.
4. Cross-method safety: the existing `pay001PreparePaymentChange()` boundary
   protects a live mono transaction when the customer switches payment method.
   Verify whether an equivalent guard is needed for a live PUMB transaction and
   state the finding in the report. If it is needed and is more than a few lines,
   stop and report rather than growing this patch.

Both patch files follow the seven runner conventions in `AGENTS.md` §Patch
conventions. WP1 adds settings rows — the owner has approved that DB change in
this handoff; rollback SQL goes in the patch header. WP2 must not touch the
database.

## 5. Do not touch

- `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess`
  (`.htaccess` especially — the PAY-002 change built for the header-stripping
  hypothesis was rolled back and the live file carries no PAY-002 marker; leave it
  that way).
- Merchant feed, Product schema, any structured data.
- Fiscalization (Checkbox), Hutko, COD, IBAN, Nova Poshta logic and totals.
- `extension/mono_chast/**` — monobank behaviour must not change in any way.
- `catalog/view/template/checkout/checkout.twig` beyond what WP2 strictly needs;
  if it needs nothing there, touch nothing there.
- Production callback credentials — they are deliberately empty so a bank test
  callback can never write into production order state. Do not fill them.
- OAuth / API base settings — they point at `*.dts.fuib.com` on purpose.
- `payment_pumb_credit_terms` — `[3,4,5]` is already correct for production.
- Order-status consolidation (§8a of the plan, 6 mono statuses → 5 shared) — a
  separate task, not this patch.
- `boostershop-ds.css` and any design-system token file.

## 6. Likely files / areas

Likely, not confirmed — the executor verifies every anchor against the live tree.

| File | WP | Note |
|---|---|---|
| `extension/pumb_credit/admin/controller/payment/pumb_credit.php` | 1 | two new settings + validation |
| `extension/pumb_credit/admin/view/template/payment/pumb_credit.twig` | 1 | two new fields |
| `extension/pumb_credit/admin/language/uk-ua/payment/pumb_credit.php` | 1 | labels, errors |
| `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` | 1, 2 | preview route, shared predicate, `confirm()` guard, `index()` term wiring — **changed by PAY-004 after the backup, re-read live** |
| `extension/pumb_credit/catalog/language/uk-ua/payment/pumb_credit.php` | 2 | customer-facing strings |
| `extension/pumb_credit/catalog/model/payment/pumb_credit.php` | 1 | comment only; keeps returning `[]` |
| `catalog/controller/checkout/payment_method.php` | 2 | method injection + gate — **shared live checkout, risky** |
| `catalog/view/template/checkout/payment_method.twig` | 2 | provider card — **shared live checkout, risky** |

## 7. Acceptance criteria

WP1:

1. Admin → PUMB settings shows the two new fields, saves without
   `Could not call registry key error!`, and the saved values survive a reload.
2. `payment_pumb_credit_status` stays disabled after the patch run; the patch
   prints its marker and `already_applied=yes` on a second run.
3. Opening the preview URL with the correct token returns a redirect (302) to a
   clean URL and produces no visible change anywhere, because WP2 is not deployed
   yet. With a wrong token, an empty configured token, or the `off` parameter, the
   response is identical from the outside.
4. `php -l` clean on every written file under PHP 8.0; no `never`, `enum` or
   `readonly` anywhere in the patch output.

WP2, with `payment_pumb_credit_status` enabled and `payment_pumb_credit_public = 0`:

5. Ordinary visitor, cart ≥ 500 UAH: the credit drawer shows the monobank card
   with 3/4/5 and the PUMB card still reading `СКОРО БУДЕ`. No PUMB radio option
   exists in the rendered payment list — check the HTML, not just the visual.
6. After opening the preview URL in the same browser: the same drawer shows PUMB
   as a selectable provider with 3/4/5 buttons; selecting a term selects payment
   code `pumb_credit.pumb_credit_<n>`.
7. Placing the order with PUMB term 4 sends `"term": 4` in the bank payload and
   returns `201` with a `cap_id`; the transaction row records `requested_term = 4`.
8. Forged request: calling PUMB `confirm()` from a session that never opened the
   preview URL is refused server-side, with no bank call made.
9. Forged term: `confirm()` with `term=6` or no term is refused with `error_term`
   and no bank call.
10. Regression on the live checkout with no preview flag: monobank 3/4/5, Hutko,
    COD and IBAN behave exactly as before; totals, coupon behaviour and the Nova
    Poshta tariff line are unchanged.
11. Setting `payment_pumb_credit_public = 1` makes PUMB visible without the token;
    setting it back to `0` hides it again. No file edit involved.

## 8. QA / smoke test

Payment and checkout are touched, so the owner runs the full 11-step
`bs-checkout-smoke` plan on production before PUMB is left enabled for any
length of time — the shortened list above is not a substitute.

The new preview route is a new URL on the live site: run it past
`bs-seo-risk-gate` before deploy. Expected classification is low — it emits a
redirect, no indexable content, no canonical or sitemap change — but that must be
confirmed, not assumed.

Test window discipline, carried over from PAY-001: do not leave
`payment_pumb_credit_status` enabled outside an active test window until the
owner decides to go live. Enabling it is what removes the old safety net.

## 9. Rollback note

WP2 first, then WP1 — reverse deploy order.

- WP2: restore `catalog/controller/checkout/payment_method.php` and
  `catalog/view/template/checkout/payment_method.twig` from
  `_patch_backups/PAY-002_pumb-checkout-card_20260828-<ts>/`. The site returns to
  today's behaviour: PUMB renders as `СКОРО БУДЕ` again. No DB change to undo.
- WP1: restore the extension and admin files from
  `_patch_backups/PAY-002_pumb-preview-token-gate_20260828-<ts>/`, then remove the
  two settings rows with the rollback SQL in that patch's header. The additive
  `requested_term` column from PAY-004 is not touched by either rollback.
- Fastest kill switch that needs no rollback at all: set
  `payment_pumb_credit_status` back to disabled in admin. That removes PUMB from
  every surface immediately.

## 10. Recommended status after execution

`PAY-002` stays **In progress** — the remaining scope after this patch is bank
approval of the branded layout (contract п. 2.2.8), order-status consolidation,
`NCRM-14` verification on a real PUMB order, `PAY-001-SMOKE`, and the production
contour cutover.

`PAY-004` moves to **Owner QA** once acceptance criteria 7 and 9 pass on
production, and to **Done** only after the owner confirms a real application with
a customer-selected term. Status changes go through `bs-roadmap-write`, and the
`ROADMAP_TASKS` dashboard mirror is updated in the same pass.

Diagnostics report required (risky zone):
`diagnostics/PAY-002_pumb-checkout-card_report_20260828.md`, per
`templates/codex-report-template.md`.

## Delivery

The executor writes both patch files into `patches/` and stops. It does not
commit, push, upload or deploy. The owner uploads each file to `~/public_html`
and runs, one at a time, WP1 first:

```
php PAY-002_pumb-preview-token-gate_20260828.php
php PAY-002_pumb-checkout-card_20260828.php
```
