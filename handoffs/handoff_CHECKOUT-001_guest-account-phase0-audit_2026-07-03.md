# Codex Handoff — CHECKOUT-001 (Phase 0): Guest account auto-creation — technical audit

Date: 2026-07-03 | Parent: none (independent feature, sequenced after ST-2c/ST-6)

## 1. Task ID
CHECKOUT-001 — «Автоматична реєстрація при замовленні» (guest checkout opt-in → auto-create account).

## 2. Context
Owner scoped this out of RD-13/RD-20 on 2026-06-06 with an explicit note: **"Needs technical audit"** — changes touch checkout UI and customer DB at the same time, no detailed plan exists yet.

Dashboard/roadmap: `status: todo`, `blockedBy: ['ST-2c']`. ST-2c (cutover to stock OC4 checkout + real shipping cost) is **not Done** — both its subtasks are still `todo`. Owner has explicitly accepted the sequencing risk on 2026-07-03 and approved starting this **read-only audit phase** in parallel with ST-2c, on the condition that no code changes ship until ST-2c is confirmed Done in Notion + dashboard.

Same checkout step (`confirm.twig` / confirm controller) currently carries several fragile, recently-fixed behaviors that any new UI element must not disturb:
- ST-2b.1–2b.5: single `confirm.confirm` caller, zero duplicate draft orders, coupon/First15/agree-oferta/GA4 parity.
- ST-2b.6 (closed 2026-07-03): silent Hutko payment reset triggered by address-field autosave; fixed by removing autosave's Hutko re-fetch, not the field-change trigger itself.
- ST-2b6d: trusted-click gate on the «Оформити» button (blocks synthetic/cross-origin clicks, keyboard nav still works).

`system/library/url.php` still routes some checkout traffic to SimpleCheckout until ST-2c Phase 2 lands — so today not all guest orders even reach the stock OC4 confirm flow this feature targets.

## 3. Goal
Produce a read-only diagnostic report answering the open technical questions below, **with file:line references from the actual live code** (server / cPanel backup), so Claude can scope a Phase 1 implementation handoff afterward. No implementation in this phase.

## 4. What to change
Nothing. This is an investigation-only phase — no file edits, no patch, no DB changes.

Codex should answer, with evidence:
1. Which checkout controller guest orders are currently routed through (stock OC4 confirm vs SimpleCheckout), and whether that differs by customer segment right now (pre-ST-2c cutover).
2. Does stock OpenCart 4's native checkout already ship a "create account" step/checkbox that was suppressed or bypassed during the ST-2 migration to the patched stock checkout? If yes — where, and what would it take to re-enable vs building custom.
3. What does OC4's core customer-creation path require (password, customer_group_id, newsletter default, approval status) when there's no user-entered password at guest checkout — is there an existing pattern (e.g. random password + reset-link email) anywhere in this codebase already, or does one need to be built.
4. Duplicate handling: what happens today if a guest checks out with an email that already belongs to an existing customer account — does OC4 core already block/merge this, or would auto-creation silently create a conflicting/duplicate record.
5. Where exactly in `confirm.twig` (relative to the existing "agree to oferta" checkbox from ST-2b5b) a new "Зберегти дані для наступного разу" checkbox could sit without re-triggering the ST-2b.6 autosave chain or interfering with the ST-2b6d trusted-click gate on «Оформити».
6. Any GDPR/consent implication of storing/creating an account from checkout data without a separate explicit registration screen.

## 5. What NOT to touch
- `confirm.twig`, confirm controller, `checkout/*` — read-only inspection only, no edits.
- `system/library/url.php` — do not touch (owned by ST-2c cutover).
- `payment/hutko*` — do not touch (fiscalization/payment logic).
- Customer DB tables — no writes, no schema changes.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed — out of scope, not touched by this task.

## 6. Likely files / areas (unconfirmed — verify against live code)
- `catalog/controller/checkout/confirm.php` (or OC4 equivalent) — likely home of guest order finalization.
- `catalog/model/account/customer.php` — likely core customer-creation model.
- `catalog/view/theme/*/template/checkout/confirm.twig` — likely template for the new checkbox.
- Codex should verify against actual project files before naming exact paths in the report.

## 7. Acceptance criteria
- [ ] Diagnostics report delivered to `diagnostics/CHECKOUT-001_phase0_audit_<date>.md`.
- [ ] All 6 questions in §4 answered with file:line evidence, or explicitly marked "not found in code — needs owner/server confirmation".
- [ ] Report states plainly whether OC4 has reusable native account-creation capability or this needs a custom build.
- [ ] Report flags any direct conflict risk with ST-2b.6 / ST-2b6d fragile logic.
- [ ] Zero files modified, zero commits containing code changes for this task.

## 8. QA / smoke test
Not applicable to Phase 0 — no live changes, no deploy. Once Phase 1 (implementation) is scoped from this audit, `bs-checkout-smoke` (full 11-step) is mandatory before any production deploy, with extra manual checks specific to this feature: new account actually created and usable for login, no duplicate customer record on repeat guest orders with the same email, no regression in the ST-2b.6/ST-2b6d fixed behaviors.

## 9. Rollback note
N/A — no code changes in this phase. If Phase 1 later ships, rollback note must be written then (likely: feature-flag the checkbox / new account-creation call, single toggle to disable).

## 10. Recommended status after execution
- Notion + dashboard: CHECKOUT-001 stays `In progress` (audit phase), not `Done`.
- Phase 1 (actual implementation) must **not** start until ST-2c is confirmed `Done` in Notion + dashboard (both subtasks: real shipping cost, full checkout cutover) — owner accepted running the audit in parallel, not the implementation.

## Risks
**High-risk task** (checkout + customer DB simultaneously, per owner's own flag) — see `bs-checkout-smoke` for the mandatory smoke test once Phase 1 is implemented. Sequencing risk: ST-2c not yet Done; audit-only phase approved by owner 2026-07-03 specifically to avoid touching live checkout code while ST-2c is still in flight.
