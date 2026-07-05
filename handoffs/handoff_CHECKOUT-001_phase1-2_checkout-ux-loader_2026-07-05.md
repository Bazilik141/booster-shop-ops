# Codex Handoff — CHECKOUT-001 (Phase 1.2): checkout UX — skip unused pre-step + continuous loader

Date: 2026-07-05 | Parent: deployed CHECKOUT-001 Phase 1 + Phase 1.1 patches

## 1. Task ID
CHECKOUT-001 — Phase 1.2 (owner-reported UX follow-up, not a new roadmap item).

## 2. Context
Owner tested the deployed opt-in flow: order and account were created correctly, but noticed a ~1-2s gap after clicking "Оформити" where the button is disabled and nothing visibly explains why, before the redirect to checkout success. Codex's own diagnosis (accepted as correct): this is the intended sequential flow — account pre-step → `confirm.confirm` → payment confirm → redirect — needed to keep the single-order guarantee. Two concrete, low-risk improvements were agreed with the owner (a third option — creating the account earlier, on checkbox-check instead of at place-order click — was explicitly declined: it would create accounts for shoppers who abandon checkout, and reopens the fragile autosave/trusted-click area on purpose this task avoided).

This is a client-side-only follow-up. It does not change server logic, does not change when or whether an account gets created, and does not touch the sequential ordering guarantee for the opted-in case.

## 3. Goal
1. When the account opt-in checkbox is **unchecked** at the moment "Оформити" is clicked, skip the `checkout/payment_method.createAccount` pre-step entirely and go straight to `confirm.confirm`. Today this AJAX call always fires (server correctly no-ops for the unchecked case, but the customer still pays the network round-trip for nothing).
2. Across the whole click-to-redirect sequence, show continuously updated, non-stale progress feedback — no silent gap where the button just looks frozen with no explanation, whether the opt-in was checked or not.

## 4. What to change
- `catalog/view/template/checkout/checkout.twig`, inside `bsCheckoutLoadConfirmAndSubmit` (already patched twice by CHECKOUT-001 Phase 1 and touched again, elsewhere in the same file, by Phase 1.1 — re-verify the exact current anchors against the live file before editing, do not assume the snippet below is still byte-identical):
  - Before firing the `$.ajax` call to `checkout/payment_method.createAccount`, check `$('#input-create-account-opt-in').is(':checked')`. If the checkbox is unchecked, or does not exist in the DOM (e.g. logged-in customer, no opt-in shown), call `loadConfirmAndSubmit()` directly and skip the AJAX call — no request to `createAccount` should be sent in this case.
  - When checked, behavior is unchanged: `createAccount` still runs before `loadConfirmAndSubmit()`, same fail-open handling as already deployed (`checkout001FailOpen` on server error or network error) — do not touch that logic.
  - Add a status update immediately before `realButton.trigger('click')` (e.g. "Підтверджуємо оплату...") so the visible button/message reflects the final leg of the sequence, not just "Створюємо замовлення..." all the way to redirect. Verify against the live `#button-confirm` markup whether OpenCart's own handler already shows its own loading/disabled state once triggered — if so, make sure our message doesn't look stale or contradict it during that leg, adjust wording accordingly rather than stacking two conflicting loaders.
  - Keep the existing "Перевіряємо дані..." message only for the case where the pre-step actually runs (checked case) — it should not appear when the pre-step is skipped.

## 5. What NOT to touch
- `catalog/controller/checkout/payment_method.php`'s `createAccount()` action — no server-side change needed, this is purely a client-side decision about whether to call it.
- `confirm.php`, `register.php`, `register.twig` — unrelated to this follow-up.
- SimpleCheckout, `system/library/url.php` — unrelated.
- The sequential ordering for the checked case (account pre-step must still complete, or fail-open, before `confirm.confirm` runs) — only the "skip entirely when unchecked" shortcut is new behavior.
- Do not implement the earlier-account-creation option (create on checkbox-check) — explicitly declined by the owner for this follow-up.

## 6. Likely files / areas
- `catalog/view/template/checkout/checkout.twig` — confirmed location (same file both prior CHECKOUT-001 patches touched). Re-verify current anchors/hashes before editing since this file has been patched twice already.

## 7. Acceptance criteria
- [ ] Opt-in unchecked at click time → no network request to `checkout/payment_method.createAccount`; flow goes straight to `confirm.confirm`. One fewer request in the network tab than the currently deployed behavior.
- [ ] Opt-in checked at click time → behavior identical to what's currently deployed (pre-step still runs, same fail-open guarantees, same account/address/login result).
- [ ] From click to redirect, visible status text changes at each real stage and never sits unexplained/stale for the full 1-2s+ span, in both the checked and unchecked cases.
- [ ] Single `confirm.confirm` call per trusted place-order click — unchanged (same regression guard as previous CHECKOUT-001 patches).
- [ ] Double-click still produces exactly one order.
- [ ] Trusted-click gate (ST-2b6d) and autosave selector counts unchanged (same anchor-count assertions Codex already uses in prior CHECKOUT-001 patches).

## 8. QA / smoke test
Client-side only, no server logic touched — a full `bs-checkout-smoke` re-run is not required, but owner should manually verify:
- unchecked flow: network tab shows no `createAccount` call, order completes as guest, redirect happens with correct status messaging throughout;
- checked flow: still creates account + order + redirect correctly, with the new "Підтверджуємо оплату..." stage visible before the final redirect;
- double-click still produces one order in both cases.

## 9. Rollback note
Single-file change (`checkout.twig`), gate behind the same patch-runner conventions as prior CHECKOUT-001 patches (backup before write, marker-based idempotency, restore-on-fail). Rollback = restore `checkout.twig` from the patch's own backup.

## 10. Recommended status after execution
- Not a roadmap status change — CHECKOUT-001 stays whatever it currently is in Notion/dashboard after Phase 1.1 QA. This is a UX polish patch on top of already-deployed functionality, log it as a sub-note if useful, no roadmap entry needed on its own.

## Risks
Low risk — client-side only, no new server endpoints, no session/DB logic changes. Main risk is Codex editing against a stale assumption of `checkout.twig`'s current content, since this file has already been patched twice (Phase 1 + Phase 1.1) — re-verify exact current anchors/hashes against the live file before writing, same fresh-source discipline as previous CHECKOUT-001 patches.
