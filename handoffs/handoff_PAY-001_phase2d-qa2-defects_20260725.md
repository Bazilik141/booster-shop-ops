# Codex Handoff — PAY-001 Phase 2d QA2: 2 UI defects (phone display + term flattening)

Date: 2026-07-25 | Parent: `diagnostics/PAY-001_phase2d_qa2_report_20260725.md` (read-only diagnosis, no patch yet)

## 1. Task ID

PAY-001 Phase 2d QA2 — defects A and B only (defect C, Nova Poshta tariff, is explicitly out of scope — see section 5).

## 2. Context

Owner ran a second live QA round after the Phase 2d lifecycle fail-safe deploy. Codex's read-only diagnosis found 3 defects; 2 are narrow, same-file, low-risk UI bugs confined to `payment_method.twig`. This handoff covers only those two.

## 3. Goal

Fix both without touching the order-write boundary, `payment_method.php` server-side gate, or the session logic already fixed by the earlier D3 patch.

## 4. What to change

**Defect A — Mono drawer shows "вказаний у формі" instead of the real receiver phone.**
`catalog/view/template/checkout/payment_method.twig:226` reads `$('input[name="telephone"]').first().val()`, which does not match the actual receiver phone field rendered by `checkout-reskin.js:875-876` (`#bs-co-recv-telephone`, no `name="telephone"`). Read `#bs-co-recv-telephone` first; keep `#input-telephone` and the checkout root's `data-bs-receiver-telephone` attribute as fallbacks; update `[data-pay001-phone]` live when the receiver phone field changes, not only on first drawer render. Display-only — do not normalize/mutate the value; order creation already uses the correct receiver phone via `confirm.php`.

**Defect B — selected credit term (4 or 5) shows as 3 in checkout.**
`flattenPaymentMethods()` in the same twig file always builds the credit option with `code: monoOptions[0].code` (always the 3-payment option), ignoring `group.pay001_preferred` even though the server already returns 4/5 correctly. Resolve the initial credit code from `group.pay001_preferred` instead of the first sorted option; keep numeric 3/4/5 sort order and manual term-switching intact. Note: this is a second, independent root cause from the D3 fix already shipped (`checkout.php` session-clearing) — D3 was correct but insufficient, because this front-end flattening bug existed separately.

## 5. Do not touch

- Order-write boundary / `confirm.php` create-on-click gate.
- `payment_method.php` server-side gate/validation and `pay001_preferred` computation.
- `checkout.php` session logic (D3's fix stays as-is).
- Mono API client, signature, payload, callback, poll.
- SimpleCheckout isolation marker, Hutko/COD/IBAN, NCRM, DB/settings.
- **Defect C (Nova Poshta tariff shows "—")** — confirmed unrelated to payment method (pre-existing Pinta dimension×quantity bug). Do not fold into this patch. Tracked separately under R-13.5, blocked on an owner decision (see chat).

## 6. Likely files / areas

`catalog/view/template/checkout/payment_method.twig` only, per Codex's own read-only diagnosis (no other file implicated).

## 7. Acceptance criteria

- Mono drawer phone always matches the current value of the visible receiver phone field, including live updates after edit, without F5.
- Product page 3 payments → checkout shows and saves 3; 4 → 4; 5 → 5 (each tested independently).
- Manual term switching in checkout still works and saves correctly.
- Direct checkout without a prior modal choice keeps existing default behavior.
- No regression: Hutko/COD/IBAN, credit gates, coupon flow, order-write boundary, SimpleCheckout isolation.

## 8. QA / smoke test

HIGH-RISK zone: checkout/payment. `bs-checkout-smoke` protocol applies. Re-run the full 15-step plan (`diagnostics/PAY-001_phase2c_checkout_smoke_plan_20260725.md`), with explicit attention to step 1/6 (term persistence across product swaps) and the Mono drawer phone display. Keep `payment_mono_chast_status=0` outside the test window.

## 9. Rollback note

Standard project pattern: source-anchor/SHA check before write, backup to `_patch_backups/`, `php -l` / `node --check`, restore-on-failure, idempotent no-op on re-run.

## 10. Recommended status after execution

PAY-001 stays In progress. Once this patch plus the already-shipped D1-D4/Phase 2d fixes all pass one full 15-step smoke run together, PAY-001 is ready for the next real gate: Monobank testing the integration themselves before issuing `point_id`. The Nova Poshta tariff defect (C) stays tracked separately under R-13.5, blocked on the owner's fallback-display decision.
