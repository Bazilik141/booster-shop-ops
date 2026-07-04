# Codex Report — CHECKOUT-001 Phase 0: guest account auto-creation audit

Date: 2026-07-03

## Executive verdict

OpenCart 4 already contains reusable checkout account-registration logic: the stock
checkout can switch between `account=1` and guest mode, validate a password and
duplicate email, create the customer and addresses, and log the new customer in.
The currently uploaded stock template still contains this UI.

However, normal generated checkout links are currently rewritten globally to
SimpleCheckout. The desired passwordless UX — an optional checkbox that creates an
account without asking for a password — is **not** implemented by either checkout.
It needs custom Phase 1 glue around the existing customer model plus a one-time
set-password/reset-link flow.

Phase 1 must remain blocked until ST-2c is `Done` and a fresh post-ST-2b6d live bundle
is collected. The latest uploaded live bundle predates the ST-2b6d patch and also
does not confirm the ST-2b.5B oferta gate described in the handoff.

## Scope

Read-only audit against:

- full cPanel backup `backup-7.1.2026_10-11-00_boosters.tar.gz` (2026-07-01);
- targeted live bundles `booster-debug-ST-2b6-live-20260703.tar.gz` and
  `booster-debug-ST-2b6b-live-20260703.tar.gz` (2026-07-03);
- repository patch/report for ST-2b6d, used only to identify the intended
  post-snapshot trusted-click logic.

No production file, database row, schema, dashboard, or roadmap status was changed.

### Evidence freshness limitation

The 2026-07-03 targeted bundles include the current stock checkout Twig files and
the SimpleCheckout controller/template, but do not include `system/library/url.php`,
the stock checkout/customer controllers and models, `confirm.twig`, or a
post-ST-2b6d `checkout.twig`. Those missing files are cited from the newest full
backup (2026-07-01). The four overlapping stock Twig files are byte-for-byte
identical between the 2026-07-01 full backup and the 2026-07-03 targeted bundle.

The ST-2b6d patch was created after the latest targeted bundle. Its deployed state
cannot be confirmed from the supplied backups.

## 1. Current checkout routing

### Finding

Generated `checkout/checkout` links are routed to SimpleCheckout for every segment.
There is no guest-vs-logged-in condition in the route rewrite:

- `system/library/url.php:61-65` rewrites `checkout/checkout` to
  `extension/SimpleCheckout/module/pinta_simple_checkout`.
- The database snapshot has `module_pinta_simple_checkout_status=1`
  (`boosters_ocart49.sql:235012`) and `config_checkout_guest=1`
  (`boosters_ocart49.sql:235223`).

Therefore, a guest who follows a normal generated checkout link reaches
`PintaSimpleCheckout::index()`, not the stock `Checkout` controller. The same rewrite
also applies to a logged-in customer; segmentation happens inside SimpleCheckout,
not in the router:

- SimpleCheckout checks the logged-in state at
  `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php:23-27`.
- Its guest session starts at the default customer group at
  `.../pinta_simple_checkout.php:49-50`.
- Its order builder uses the real customer at `:3011-3024`, or `customer_id=0`
  for a guest at `:3026-3034`, then creates the order at `:3563`.

The stock route remains directly callable through a literal
`index.php?route=checkout/checkout` request because the rewrite is in URL generation,
not request dispatch. That is the audit/test path before ST-2c; it is not the normal
generated-link path.

### Answer

There is **no current customer-segment split at routing level**. Normal links for
both guests and logged-in customers go to SimpleCheckout. Stock checkout is reached
only through a direct/hard-coded route until ST-2c removes the rewrite.

## 2. Native OC4 “create account” capability

### Stock checkout

The capability exists and is not removed from the uploaded stock code:

- `catalog/controller/checkout/checkout.php:58-62` loads
  `checkout/register` for visitors who are not logged in.
- `catalog/view/template/checkout/register.twig:6-14` renders native
  “Реєстрація” (`account=1`) and guest (`account=0`) choices.
- `catalog/controller/checkout/register.php:25-27` exposes the guest-checkout
  configuration.
- `catalog/controller/checkout/register.php:52-56` selects account registration
  on the initial empty session; after guest data is saved with `customer_id=0`,
  guest mode is selected.
- `catalog/view/template/checkout/register.twig:452-475` contains the password,
  newsletter, and account-agreement controls.
- `catalog/view/template/checkout/register.twig:483-499` shows password/account
  agreement only when `account=1`.
- `catalog/controller/checkout/register.php:418-456` validates the password and
  account agreement for `account=1`.
- `catalog/controller/checkout/register.php:491-494` calls `addCustomer()`;
  `:573-576` and `:664-670` attach payment/shipping addresses;
  `:690-698` logs in the created account when approval is not required.

### SimpleCheckout

SimpleCheckout independently exposes the same kind of explicit registration:

- `extension/SimpleCheckout/catalog/view/template/module/checkout.twig:581-604`
  contains a `register` checkbox plus password/confirmation fields.
- `.../pinta_simple_checkout.php:1058-1063` selects registration vs guest
  validation.
- `.../pinta_simple_checkout.php:1682-1688` requires password/confirmation.
- `.../pinta_simple_checkout.php:1707-1741` creates and logs in the customer.

### Answer

OC4 has reusable account creation. It was **bypassed by routing**, not deleted from
the stock checkout template/controller. Re-enabling the stock account option with a
user-entered password is mostly UI/restyling after ST-2c. The requested
passwordless “Зберегти дані для наступного разу” flow is a custom build because the
native path requires a password.

## 3. Customer creation requirements and password strategy

### Current core behavior

`catalog/model/account/customer.php:40-63` shows the exact insert behavior:

- `customer_group_id` is accepted only from configured display groups; otherwise
  the store default is used (`:41-45`);
- `firstname`, `lastname`, `email`, `telephone`, and `password` are used directly;
- password is hashed with `password_hash()` (`:52`);
- newsletter defaults to `0` when absent (`:52`);
- status is `1` only when the customer group does not require approval (`:52`);
- when approval is required, an approval record is created (`:56-61`).

Current settings in the full backup:

- default/display customer group: `1` (`boosters_ocart49.sql:235211-235212`);
- group `1` has `approval=0` (`boosters_ocart49.sql:2371`);
- password minimum length: `6`, with a number required
  (`boosters_ocart49.sql:235219-235220`);
- `config_account_id=0`, so no separate account-terms information page is currently
  configured (`boosters_ocart49.sql:235216`);
- newsletter remains opt-in and defaults to off.

The core model will technically hash an empty string, but the controllers prevent
that in supported registration flows. Calling `addCustomer()` with an empty password
would therefore bypass controller policy and create an unusable/weak account.

### Existing reset-link capability

No existing checkout code was found that generates a random password and emails it.
Both stock checkout and SimpleCheckout require a customer-entered password.

There is a reusable core password-reset mechanism:

- `catalog/controller/account/forgotten.php:80-85` creates a 40-character
  password token;
- `catalog/controller/mail/forgotten.php:46-55` builds the one-time reset URL;
- `catalog/controller/account/forgotten.php:119-150` verifies the token and builds
  the password form;
- `catalog/controller/account/forgotten.php:199-205,253-261` verifies the token
  again, stores the new password, and deletes the token.

### Answer / Phase 1 direction

Use the existing customer/address models and reset-token machinery. Do not email a
plaintext random password. A safe passwordless account flow should create a strong
unusable random secret server-side, issue a one-time “set password” token, and send
an explicit account-created/set-password email. That orchestration and email copy
do not exist today and must be built.

## 4. Duplicate-email handling

### Stock checkout

- Guest mode does not reject an email that already belongs to a customer.
- Registration mode does reject it:
  `catalog/controller/checkout/register.php:286-290`.
- The standalone account registration also rejects it:
  `catalog/controller/account/register.php:190-195`.
- `catalog/model/account/customer.php:40-63` itself does **not** check duplicates.
- `ocp5_customer.email` is a normal index, not a unique index
  (`boosters_ocart49.sql:1878-1900`).
- A guest order remains unlinked with `customer_id=0`; stock confirm copies the
  session customer values into the order at
  `catalog/controller/checkout/confirm.php:100-107`.

### SimpleCheckout

- Guest validation has no email-existence check
  (`.../pinta_simple_checkout.php:1296-1438`).
- Registration validation rejects an existing email at
  `.../pinta_simple_checkout.php:1595-1601`.

### Answer / required policy

OC4 does not merge a guest order into an existing account. Existing-email guest
orders remain guests. Account-registration controllers block duplicates, but the
model/database do not guarantee uniqueness.

For Phase 1, when opt-in is checked and the email already exists:

1. do not create a second customer;
2. do not silently log in or attach the order by email;
3. continue the order as guest;
4. show/send a neutral “account already exists — sign in or reset password” path.

An atomic database uniqueness guarantee would require a schema change and explicit
owner approval. Without it, a check-before-insert race remains possible.

## 5. Safe UI placement vs ST-2b.6 / ST-2b6d

### Correction to the handoff premise

The oferta checkbox is not in `confirm.twig`. In the uploaded code:

- `catalog/view/template/checkout/confirm.twig:1-58` contains only the order table
  and payment block.
- `catalog/view/template/checkout/payment_method.twig:14-18` contains
  `#input-checkout-agree`.
- `catalog/view/template/checkout/payment_method.twig:272-289` persists its state
  through the comment endpoint.

Also, the full-backup settings have `config_checkout_id=0`
(`boosters_ocart49.sql:235226`), so `payment_method.php:40-45` produces no
`text_agree`; the conditional markup is not rendered in that snapshot.

The 2026-07-03 live `checkout.twig` also lacks the ST-2b.5B
`bsCheckoutHasAgreeReady()` gate: `checkout.twig:720-726` requires only shipping
and payment. Thus the supplied live evidence does not confirm the handoff statement
that the oferta gate is active.

### Recommended placement

For Phase 1, place the visible optional account checkbox in
`catalog/view/template/checkout/payment_method.twig` immediately after the oferta
block (after current line 18) and before the script (current line 19), with a unique
name such as `create_account_opt_in`.

Reasons:

- it is adjacent to the legal/consent area;
- it is outside `#form-register`, so it does not match the broad autosave handler
  at `catalog/view/template/checkout/checkout.twig:1109-1113`;
- it is outside `#checkout-confirm [data-bs-deferred-confirm]`, so it does not match
  the deferred/trusted place-order click handler at `checkout.twig:1098-1105`;
- putting it in `confirm.twig` would be too late: loading `confirm.confirm` can
  create/edit the order at `catalog/controller/checkout/confirm.php:278-282`.

Phase 1 must persist opt-in separately without triggering `confirm.confirm`. The
final trusted user click should run a dedicated pre-confirm account/guest save and
only then execute the existing single `confirm.confirm` path.

### ST-2b6d evidence gap

The latest uploaded `checkout.twig` predates the ST-2b6d patch. Repository patch
`patches/ST-2b6d_deferred-confirm-trusted-click-gate_20260703.php:80-109` shows the
intended `isTrusted`, target, and currentTarget checks, but this is implementation
history, not proof of current live deployment.

Before Phase 1, collect the post-ST-2b6d live files and re-anchor against them.

## 6. Privacy / consent implications

This section is implementation guidance, not legal advice.

Order fulfilment requires storing order/customer contact data, but an optional
persistent account is a separate purpose. The lowest-risk design is:

- unchecked, explicit opt-in; no preselection and no account creation from silence;
- concise text explaining account creation, saved data, one-time password setup,
  retention, deletion/access rights, and a privacy-policy link at collection time;
- newsletter consent separate and unchecked — never infer it from account opt-in;
- store only data required for the account/order; do not copy unrelated checkout
  fields;
- log consent timestamp, policy/copy version, and source;
- provide account deletion/access handling while retaining only order records that
  must remain under another legal obligation;
- never email a plaintext password; use a short-lived single-use set-password link.

Basis:

- GDPR Article 5 requires purpose limitation and data minimisation; Article 6
  requires a lawful basis; Article 13 requires point-of-collection information;
  Article 25 requires privacy by design/default:
  <https://eur-lex.europa.eu/eli/reg/2016/679/oj/eng>.
- The Ukrainian Law “On Personal Data Protection” requires a defined, transparent
  purpose, adequate/non-excessive data, notice at collection, and supports access,
  correction/deletion, and withdrawal rights:
  <https://zakon.rada.gov.ua/laws/show/2297-17>.

Account opt-in and newsletter consent must remain separate. Final legal wording and
retention rules should be approved before Phase 1 deployment.

## Files touched

```text
diagnostics/CHECKOUT-001_phase0_audit_20260703.md — this report only
```

Zero checkout/customer code files and zero database records were changed.

## Dry-run result

Not applicable: Phase 0 is read-only. Evidence reads completed successfully.

## php -l result

Not applicable: no PHP file was created or changed.

## Idempotency

Not applicable: no runner/patch exists for Phase 0.

## Rollback

Not applicable: no production or database change.

## Run command

None. There is nothing to upload or run for Phase 0.

## Phase 1 preflight collection

Before implementation, collect the exact post-ST-2c/post-ST-2b6d live state:

```bash
cd ~/public_html || exit
tar -czf booster-debug-CHECKOUT-001-phase1.tar.gz \
  system/library/url.php \
  catalog/controller/checkout/checkout.php \
  catalog/controller/checkout/register.php \
  catalog/controller/checkout/payment_method.php \
  catalog/controller/checkout/confirm.php \
  catalog/model/account/customer.php \
  catalog/view/template/checkout/checkout.twig \
  catalog/view/template/checkout/register.twig \
  catalog/view/template/checkout/payment_method.twig \
  catalog/view/template/checkout/confirm.twig \
  extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php \
  extension/SimpleCheckout/catalog/view/template/module/checkout.twig
```

## Phase 1 acceptance / QA requirements

- [ ] ST-2c is `Done` in both Notion and dashboard before implementation starts.
- [ ] Fresh live bundle confirms the final ST-2b.5B/ST-2b6d anchors.
- [ ] Opt-in defaults off and newsletter remains independent/off.
- [ ] New email + opt-in creates exactly one usable account and one address set.
- [ ] Existing email + opt-in creates no duplicate and does not expose account state.
- [ ] Guest without opt-in remains `customer_id=0`.
- [ ] One-time set-password link works once and expires.
- [ ] `confirm.confirm` fires exactly once on a trusted place-order activation.
- [ ] Address autosave does not create an account or reload Hutko/payment selection.
- [ ] Full 11-step `bs-checkout-smoke` plus owner manual payment/fiscal/CRM checks pass.

## Status recommendation

Keep CHECKOUT-001 `In progress` after this audit. Do not start Phase 1 until ST-2c is
confirmed `Done` in Notion and dashboard and the fresh live bundle is reviewed.
