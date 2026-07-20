# RD-13 follow-up — stock coupon and free-shipping dependencies

Date: 2026-07-06

## Context

The owner approved splitting RD-13 into:

1. visual-only stock checkout reskin; and
2. a separate HIGH-RISK dependency task for coupon/First15 and free shipping.

## Verified problem

From `backup-7.6.2026_12-14-21_boosters.tar.gz`:

- SimpleCheckout has coupon/First15 runtime logic and UI.
- Stock checkout does not have:
  - `catalog/controller/checkout/coupon.php`;
  - `catalog/model/checkout/booster_coupon.php`;
  - `catalog/view/template/checkout/coupon.twig`.
- `total_coupon_status` is disabled in persistent settings; SimpleCheckout
  enables it only at runtime.
- No durable free-shipping threshold setting exists.
- No stock or SimpleCheckout implementation currently provides the approved
  threshold/progress contract.

## Separate implementation scopes

### A. Stock coupon / First15 parity

- Start from the already validated ST-2b.5A design.
- Add a stock-only coupon endpoint/model/view.
- Refresh stock totals without calling `checkout/confirm.confirm`.
- Preserve guest account opt-in and `welcome_coupon_pending`.
- Do not touch SimpleCheckout coupon behavior.
- Do not change Hutko/payment/fiscalization logic.

### B. Free-shipping business rule

Before implementation, owner must define:

- exact threshold and persistent config key/admin source;
- which NP delivery modes become free;
- whether the carrier API quote is replaced with zero or subsidized separately;
- whether promo-discounted payable total or raw subtotal drives eligibility;
- how order totals, CRM, fiscal receipt, and payment payload represent shipping.

The UI progress bar must not ship before the real order-total rule exists.

## Risks

- Coupon totals can alter payment amounts and fiscal payloads.
- Free shipping affects shipping quotes, order totals, Hutko amount, Checkbox
  receipt lines, CRM accounting, and customer-visible totals.
- These dependencies require separate patches and separate checkout smoke
  evidence; they must not be folded into the visual RD-13 patch.

## Acceptance criteria

- Coupon apply/remove updates stock checkout totals without creating an order.
- First15 cannot be reused by an ineligible customer.
- Free-shipping eligibility uses one durable source of truth.
- Displayed shipping, stored order totals, payment amount, fiscal receipt, and
  CRM readback agree exactly.
- Payment selection alone creates no order.
- One trusted final click creates exactly one correct order.
