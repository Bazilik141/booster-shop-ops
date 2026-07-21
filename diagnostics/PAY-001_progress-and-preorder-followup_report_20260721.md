# Codex → Claude report — PAY-001 progress and preorder follow-up

Date: 2026-07-21

## Context

PAY-001 remains `In progress`; its authoritative current handoff is `handoffs/handoff_PAY-001_RESET_checkout-architecture-correction_20260721.md`.

The owner confirmed the two deployed protections: the Mono admin `$error` fix and the SimpleCheckout isolation patch. ST-2c cutover remains blocked by PAY-001. The redesigned `checkout/checkout` is the intended Phase 2 target; SimpleCheckout remains the live customer path until cutover.

## Completed work and evidence

### PAY-001 architecture

- Phase 2 must add a dedicated credit entry only to `checkout/checkout`; it must not restore or use `extension/mono_chast/.../getMethods()`.
- The current SimpleCheckout isolation remains: `getMethods()` returns `[]`.
- The stock checkout will validate the selected 3/4/5 plan on the server, then persist the existing Mono payment code so the existing trusted order-write boundary and Mono `confirm()` route remain in use.
- Owner supplied official PUMB material: `Логотипи по Сплачуйте частинами та ПУМБ.zip` and its product-placement PDF. The package includes usable official SVG assets.
- Owner business rule: both Mono and PUMB are limited to 3, 4, or 5 payments.
- PUMB UI copy must use the approved product name `Сплачуйте частинами` and bank name `ПУМБ`. PUMB remains an inactive `Скоро буде` option until a separate PUMB merchant/API integration is supplied and approved.

### Payment-order status model

- Do not keep six bank-specific Mono states as the daily operational order-status workflow.
- Preserve delivery/fulfilment in the existing OpenCart statuses; show bank application state in a dedicated payment/credit panel or transaction history.
- Use a provider-neutral internal state vocabulary later (`awaiting_customer`, `awaiting_store_confirmation`, `active`, `completed`, `returned`, `rejected`). Map Mono and future PUMB raw API states into it only after PUMB API documentation is available.
- The existing Mono status rows are currently referenced by deployed sandbox configuration. Do not delete or merge them until a follow-up patch stops that reference and sandbox QA passes.

### ORDER-STATUS-001 deployed

- Patch: `patches/ORDER-STATUS-001_preorder_order_status_20260721.php`.
- Owner ran it and reported `done=ok`.
- It inserted exactly one new Ukrainian OpenCart admin status: `Передзамовлення`.
- It did not modify any order, checkout logic, stock rule, payment flow, fiscalization, CRM payload, or dashboard state.
- Owner still needs a short UI check: choose `Передзамовлення` on a disposable order, save, reload, and confirm its history row.

## What was tried / what was intentionally not done

- The preorder status is deliberately manual for now.
- No automatic status assignment was added at order creation, because that changes a checkout/order-write rule and needs a separate scoped patch.
- No PUMB checkout integration was implemented: supplied PUMB files are branding/product-placement material, not merchant API/callback documentation.
- No Notion or dashboard status was changed by Codex.

## Required Claude action — create a separate low-priority task

Create a new Notion task (choose the next valid task ID; suggested title below) with **Priority: Low** and status `Not started`.

> Автоматично ставити «Передзамовлення» для замовлень з preorder-товарами

### Proposed scope

- At the confirmed server-side order write only, detect products that are preorder at that time: product stock is tracked, quantity is not positive, and OpenCart product `stock_status_id = 8` (`Передзамовлення`).
- Assign the existing OC order status `Передзамовлення` only when the approved rule matches.
- Do not change price, totals, payment selection, payment confirmation, stock deduction, Hutko, Mono, PUMB, Checkbox, Nova Poshta, CRM, or email flows.
- Preserve the current read-only checkout render and trusted-click order-write gate.

### Open business decision for the task

For a mixed cart containing both in-stock and preorder products, confirm one policy before implementation:

1. Mark the entire order `Передзамовлення`; or
2. Keep the standard operational status and show preorder at item level.

Do not silently choose either policy.

### Acceptance criteria

- A preorder-only test order receives `Передзамовлення` exactly once after explicit confirmation.
- A normal in-stock order keeps its existing initial status.
- Mixed-cart behavior follows the owner-approved policy.
- Refreshing or reopening checkout creates no order and changes no status.
- Existing COD, Hutko, and later credit flows retain their current status behavior.

## What to verify next

1. Owner completes the small admin QA for the new `Передзамовлення` status.
2. Owner completes Mono sandbox setup and the documented sandbox scenarios while isolation is temporarily rolled back only for the test window, then restores `payment_mono_chast_status=0` and isolation.
3. Claude reviews the future PAY-001 Phase 2 patch against this architecture and the current ST-2c/CHECKOUT sources.

## Risks

- `checkout/confirm.php` is a high-risk order-write boundary; the requested preorder automation must be its own patch and QA pass.
- The PUMB material changes allowed branding/copy, but does not demonstrate a PUMB payment API or bank-state lifecycle.
- The repository contains unrelated untracked MKT-TG-008 files; they were not touched by this PAY-001/status work.
