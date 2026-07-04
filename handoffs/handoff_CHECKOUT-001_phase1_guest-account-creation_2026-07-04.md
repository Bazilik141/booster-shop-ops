# Codex Handoff — CHECKOUT-001 (Phase 1): Guest account auto-creation — stock checkout implementation

Date: 2026-07-04 | Parent: `diagnostics/CHECKOUT-001_phase0_audit_20260703.md` (Phase 0 audit)

## 1. Task ID
CHECKOUT-001 — «Автоматична реєстрація при замовленні» (guest checkout opt-in → auto-create account).

## 2. Context
Phase 0 audit (`diagnostics/CHECKOUT-001_phase0_audit_20260703.md`) is closed. Key findings this handoff builds on:
- OC4 stock checkout still contains native account-registration logic (`checkout/register.php`, `register.twig`) — bypassed by routing, not deleted.
- Passwordless "save my data" opt-in does not exist anywhere in the code today. It is a custom build on top of the existing customer model + the existing password-reset token mechanism (`account/forgotten.php`).
- Oferta/consent checkbox lives in `payment_method.twig` (not `confirm.twig` as originally assumed) — new checkbox goes next to it.
- Email uniqueness is not enforced at DB/model level; guest mode does not check for an existing account by email.

**Owner decision (2026-07-04): ST-2c is explicitly NOT a blocker for this task.** Implement now, targeting the **stock checkout only** (not SimpleCheckout). Rationale: SimpleCheckout is retired by ST-6 after ST-2c — building the feature there would be throwaway work. Consequence the owner has accepted: `system/library/url.php` still routes 100% of generated checkout links to SimpleCheckout until ST-2c ships, so **this feature will have zero effect on live guest traffic until ST-2c cuts over.** It is reachable only via the direct route (`index.php?route=checkout/checkout`) in the meantime — same path used for ST-2c testing today.

**Evidence-freshness prerequisite (from Phase 0, not waived by the owner):** the live bundles used for the audit predate the ST-2b6d patch. Before writing a single line, Codex must collect a fresh live bundle and re-confirm every line anchor cited below — do not blind-edit against Phase 0 line numbers.

```bash
cd ~/public_html || exit
tar -czf booster-debug-CHECKOUT-001-phase1.tar.gz \
  catalog/controller/checkout/checkout.php \
  catalog/controller/checkout/register.php \
  catalog/controller/checkout/payment_method.php \
  catalog/controller/checkout/confirm.php \
  catalog/controller/account/forgotten.php \
  catalog/controller/mail/forgotten.php \
  catalog/model/account/customer.php \
  catalog/view/template/checkout/checkout.twig \
  catalog/view/template/checkout/register.twig \
  catalog/view/template/checkout/payment_method.twig \
  catalog/view/template/checkout/confirm.twig
```

## 3. Goal
1. Guest reaching **stock checkout** sees an unchecked, optional checkbox "Зберегти дані для наступного разу" in `payment_method.twig`, next to the existing oferta block.
2. If checked and the order's email has no existing customer account: on the trusted place-order click, before `confirm.confirm` fires, create a customer account from the order's guest data with a strong random password (never emailed in plaintext), attach the address(es), then send a one-time "account created — set your password" email using the existing reset-token mechanism.
3. If checked and the email already belongs to an existing customer: do nothing (no duplicate, no auto-login, no silent attach) — order continues as guest.
4. Unchecked → current guest behavior, fully unchanged (`customer_id=0`).
5. Newsletter opt-in (if present) stays fully independent — never inferred from this checkbox.
6. `confirm.confirm` must still fire exactly once per order (ST-2b.1–2b.5 guarantee) — the account-creation call is a separate pre-step, not folded into `confirm.php`'s order-creation logic (Phase 0 §5: adding it inside `confirm.twig`/`confirm.confirm` is "too late" and risks touching `confirm.php:278-282`, avoid).

## 4. What to change
- `catalog/view/template/checkout/payment_method.twig` — add checkbox markup immediately after the existing oferta block (Phase 0 cited current line 18, **re-verify against fresh bundle**), before the script block. Field name: `create_account_opt_in`. Unchecked by default. Placed outside `#form-register` and outside `[data-bs-deferred-confirm]` so it does not match the autosave handler (`checkout.twig` ~1109-1113) or the deferred trusted-click handler (`checkout.twig` ~1098-1105) — Phase 0's explicit reasoning for this placement.
- `catalog/controller/checkout/payment_method.php` — persist the opt-in flag into session/checkout data, following the same persistence pattern the oferta checkbox already uses (Phase 0 cited `payment_method.twig:272-289` comment-endpoint pattern). Codex to identify and confirm the exact controller-side handler against the fresh bundle.
- A dedicated pre-confirm step, invoked by the existing trusted "Оформити" click handler **before** `confirm.confirm`: reads the opt-in flag + guest session data; if checked and no existing customer for that email, calls the existing customer-creation path (the model used by `register.php:491-494` `addCustomer()`-equivalent) with a server-generated random password, attaches address(es) the same way `register.php:573-576` / `:664-670` do.
- Password-reset email: reuse `account/forgotten.php:80-85` token generation + `mail/forgotten.php:46-55` email-building pattern to send a new "account created — set your password" email (new template, adapted copy — not the existing forgotten-password wording, must read as account-created, not password-reset).
- Consent microcopy next to the checkbox: what happens, link to privacy policy, mention the password-setup step. **Draft copy only — final wording needs explicit owner sign-off before deploy, do not invent legal language.**

## 5. What NOT to touch
- `system/library/url.php` — owned by ST-2c, not this task.
- SimpleCheckout (`extension/SimpleCheckout/**`) — explicitly out of scope by owner decision (2026-07-04); do not add the feature there.
- `confirm.twig` / `confirm.php`'s order-creation logic (`confirm.php:278-282` per Phase 0) — no account-creation call inside `confirm.confirm` itself.
- `catalog/model/account/customer.php` — reuse the existing insert path as-is; do not change its signature or validation behavior.
- Database schema — no unique index on `ocp5_customer.email` (Phase 0 flagged this as a possible future hardening needing separate owner approval; not in scope here).
- `checkout.twig` autosave handler (~1109-1113) and deferred-confirm click handler (~1098-1105) — do not attach the new checkbox or its persistence call to either selector.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed — unrelated, not touched.

## 6. Likely files / areas (confirm against fresh bundle before editing)
- `catalog/view/template/checkout/payment_method.twig`
- `catalog/controller/checkout/payment_method.php`
- `catalog/controller/checkout/confirm.php` (read-only reference for the trusted-click hook point; no edits to its order-creation logic)
- `catalog/model/account/customer.php` (call only, no edits)
- `catalog/controller/account/forgotten.php`, `catalog/controller/mail/forgotten.php` (pattern reference for the reset-token email)
- Codex should verify all line numbers against the fresh live bundle, not the Phase 0 audit's citations.

## 7. Acceptance criteria
- [ ] Checkbox visible, unchecked by default, only on stock checkout (`payment_method.twig`).
- [ ] Newsletter opt-in (if present) stays independent — never auto-checked or inferred.
- [ ] New email + opt-in checked → exactly one usable customer account created + one address set attached.
- [ ] Existing email + opt-in checked → no duplicate account, no auto-login, no account-state leak; order completes as guest.
- [ ] Opt-in unchecked → behavior identical to current guest checkout (`customer_id=0`), zero regression.
- [ ] One-time set-password link works exactly once and expires afterward.
- [ ] `confirm.confirm` fires exactly once per trusted place-order click (no dedupe regression from ST-2b.1–2b.5).
- [ ] Address-field autosave does not create an account and does not reload/reset Hutko or payment selection (no ST-2b.6 regression).
- [ ] Feature reachable only via stock checkout / direct route; SimpleCheckout traffic and `url.php` behavior fully unaffected.

## 8. QA / smoke test
High-risk (checkout + customer DB simultaneously) → full 11-step `bs-checkout-smoke` is mandatory before production deploy, run via the direct stock-checkout route since normal links still resolve to SimpleCheckout. In addition to the standard 11 steps, owner must manually verify:
- new-email opt-in creates one working account (owner can log in with the set-password link);
- existing-email opt-in does not create a duplicate and does not expose whether the email has an account;
- unchecked opt-in leaves guest checkout behavior untouched;
- no Hutko/payment-selection reset on address autosave (ST-2b.6 regression check);
- single `confirm.confirm` call per order (ST-2b.1–2b.5 regression check).

## 9. Rollback note
Gate the new checkbox + pre-confirm account-creation call behind a single, clearly-commented block in `payment_method.twig` / `payment_method.php` (e.g. `<!-- CHECKOUT-001 START/END -->`) so it can be stripped in one edit without touching SimpleCheckout, `url.php`, or `confirm.php`'s core logic. No DB rollback needed (no schema change in this phase).

## 10. Recommended status after execution
- Notion + dashboard: CHECKOUT-001 → stays `In progress` until owner QA (11-step smoke + feature-specific checks) passes; only then `Done`.
- Because of the routing situation, `Done` here means "ready and correct in stock checkout" — it does **not** mean guests currently see it. Re-verify this feature still behaves correctly once ST-2c cuts over traffic to stock checkout (add a one-line regression check to the ST-2c cutover QA).

## Risks
**High-risk task** — checkout UI + customer DB write in the same step, in a controller area with recent fragile-fix history (ST-2b.1–2b.6, ST-2b6d). See `bs-checkout-smoke` for the mandatory test. Two risks specific to this task: (1) evidence used to plan this was collected before ST-2b6d — Codex must re-anchor against a fresh bundle first, not proceed on Phase 0's line numbers blindly; (2) consent/privacy copy must get explicit owner sign-off before deploy — do not ship placeholder or invented legal wording to production.
