# Design Brief — RD-11 / RD-12: Cart page, mini-cart, add-to-cart toast

> Owner decision 2026-09-21: the add-to-cart toast is folded into RD-12 (no separate Roadmap ID). Scope confirmed as success + error states, one pass.

Date: 2026-09-21 | Parent: RD-redesign series (`plans/RD-redesign-roadmap-plan_2026-05-30.md`)
For: Claude Design
Owner evidence: 1 screenshot (production, 2026-09-21) — add-to-cart success toast overlapping the "Аксесуари" category row.
Notion: RD-11 `3706bf20-bdb4-81a4-b3fa-f35e7610defa` (Status: Not started, Priority: High) · RD-12 `3706bf20-bdb4-81f1-94ed-cf08fd3b7e11` (Status: Not started, Priority: High, scope now includes the add-to-cart toast per owner decision 2026-09-21).

## 1. Summary

Three related cart-flow surfaces, briefed together because RD-12 explicitly shares patterns with RD-11 and the toast fires from the same interaction (add to cart) that both cart views display:

- **RD-11** — cart page (`checkout/cart.twig`) — barely touched by the redesign (8 `bs-` classes only).
- **RD-12** — mini-cart (`common/cart.twig`, currently a Bootstrap dropdown, not a drawer) **plus the add-to-cart toast** (`#alert`), folded into RD-12's scope on 2026-09-21 rather than tracked as a separate ID. The toast is not in the original RD-01…RD-21 mapping — it is 100% stock OpenCart/Bootstrap, zero `bs-` classes, zero rules in `boostershop-ds.css`. This is a real gap, not a missed redesign pass.

RD-11's Notion blocker text ("wait for R-13.5 before redesigning cart") is stale — `R-13.5` closed Done 2026-09-08 and its sole remaining gate, `ST-2c`, closed 2026-07-29. RD-11 is unblocked.

## 2. Task type

Visual design / UX specification only. No code, no backend logic, no copy invention beyond what the evidence files already contain.

## 3. Workflow

Claude Design produces the visual spec below → owner reviews/approves (or asks for revisions) → owner pastes the result back to Claude (chat) → Claude folds it into an implementation handoff addressed to the assigned executor (Codex or Claude Code, owner assigns) → executor patches `checkout/cart.twig`, `common/cart.twig` (+JS), and whatever the toast component needs.

## 4. Evidence files provided

Extracted 2026-09-21 from `backup-9.7.2026_20-35-02_boosters.tar.gz` (newest cPanel backup available in the repo; 2 weeks old — flag to the owner if a fresher backup exists before implementation, not before this design pass). Folder: `live-snapshots/20260921_cart-minicart-toast-redesign/`. Give Claude Design this whole folder rather than screenshots.

| File | What it is |
|---|---|
| `catalog/view/template/checkout/cart.twig` | Cart page markup (RD-11 target) |
| `catalog/view/template/checkout/cart_list.twig` | Cart page line-items partial |
| `catalog/controller/checkout/cart.php` | Cart page controller — confirms what data is available (no shipping/threshold logic lives here; see §7) |
| `catalog/view/template/common/cart.twig` | Mini-cart dropdown markup (RD-12 target) — includes an inline `<style>` block with a hardcoded trigger-button color, see §8 |
| `catalog/view/template/common/cart_list.twig` | Mini-cart line-items partial |
| `catalog/controller/common/cart.php` | Mini-cart controller |
| `catalog/view/template/common/header.twig` | Shows where `#alert` sits in the DOM and how the mini-cart is injected into the header (string `replace()` on the rendered `cart` variable — fragile, informational only, not a design concern) |
| `catalog/view/javascript/common.js` | `add to cart` AJAX handler; builds and inserts the `.alert.alert-success.alert-dismissible` toast markup (~line 146) |
| `catalog/view/stylesheet/boostershop-ds.css` | Full design-system tokens/components. Confirmed current: `--bs-green: #16A34A`, `--bs-green-d: #15803D` (purchase actions only), radius tokens `--bs-r-sm` / `--bs-r` / `--bs-r-lg` |
| `catalog/view/stylesheet/stylesheet.css` | Base OpenCart stylesheet — lines 37–90 are the **only** styling the toast currently has (see §9) |

## 5. Site constraints (all three components)

- OpenCart 4 storefront, mobile-first. Reuse `boostershop-ds.css` tokens/components only — **do not invent new colors, fonts, radii, or spacing scales**. If something genuinely isn't covered by the current DS, flag it as an open question.
- Brand tone: Booster Shop is a curated TCG store, not a marketplace — no urgency banners, no discount-badge clutter unless it's already how the rest of the site looks.
- Green (`--bs-green` / `--bs-green-d`) is reserved for purchase/cart/checkout/success actions site-wide.
- Mobile is the primary target; every spec must also hold up on desktop.
- Inspect the live pages directly before proposing final pixel values — do not guess from the evidence files alone, they show structure, not the rendered result.

Live pages to inspect:
- Any category or product page — add an item to cart to see the live toast and mini-cart update.
- Cart page — reachable via the mini-cart's "Кошик"/checkout link after adding an item.
- Mobile width (~360–390px) and desktop for all three.

## 6. Component A — RD-11: Cart page

### Current state (from evidence, not the 2026-05-30 plan)
`checkout/cart.twig` carries only 8 `bs-` classes — effectively unstyled by the redesign, still close to stock OpenCart markup. `checkout/cart_list.twig` (the line-items partial) carries 23. **No shipping/threshold row exists in the current markup or controller** — this is a planned addition, not something to visually match against an existing element.

### What to design
- Line-item rows: thumbnail, title, qty control sized **44×44** (tap target), price, remove.
- Summary block with subtotal/total.
- A shipping-info row stating the delivery cost rule. The free-shipping threshold is an **admin-editable setting** (`shipping_pinta_nova_poshta_free_from`, no fixed number to design against) — design the row so the amount is a variable/token position, not hardcoded copy like "₴1500".
- Empty-cart state, consistent with the DS empty-state pattern used elsewhere on the site (RD-06).
- Primary CTA "Оформити" in DS purchase-green, full-width on mobile.

### Constraints
- Markup/CSS only — **do not change price or checkout logic**.

### Deliverable
Mockup/spec for populated cart (1 item, multiple items) and empty cart, mobile (~360–390px) and desktop.

## 7. Component B — RD-12: Mini-cart

### Current state (from evidence)
`common/cart.twig` is a Bootstrap `.dropdown` (`data-bs-toggle="dropdown"`), not a drawer — confirmed live, not from the plan doc. 11 `bs-` classes on the wrapper, 15 more in `common/cart_list.twig`. The trigger button's color is **hardcoded inline** in a `<style>` block inside `common/cart.twig` (`#1fa247` / hover `#18853a`) instead of using the DS tokens `--bs-green` (`#16A34A`) / `--bs-green-d` (`#15803D`) — a real color-drift bug, worth fixing in the same pass even though it's implementation, not design.

### What to design
- Convert dropdown → right-side drawer: 380px desktop, 100% width mobile.
- Header "Кошик · N товарів" + close control.
- Line items: thumbnail 56×56, title up to 2 lines, qty −/+ control, price.
- Subtotal, sticky to the bottom of the drawer.
- Two CTAs: "Продовжити покупки" (text/secondary) + "Оформити замовлення" (primary, full-width, DS green).
- Empty-cart state.
- Must not visually collide with the cookie banner or a back-to-top control if either is on-screen at the same time.

### Deliverable
Mockup/spec for the drawer open state (1 item, multiple items, empty), the header trigger (badge count), mobile and desktop.

## 8. Component C — Add-to-cart toast (part of RD-12)

### Current state (from evidence, confirmed in code — this is the whole story, no guessing needed)
`#alert` sits as the first child of `#container` in `header.twig`, before the header itself. Its only styling is in `stylesheet.css` (the **base** OpenCart stylesheet, not the DS file):
- `position: fixed; top: 30%; left: 50%; z-index: 9999`
- width 400px (mobile) / 600px, `margin-left` negative half-width to center
- Colors and shape come from raw Bootstrap `.alert-success` / `.alert-danger` / etc. — **not** DS tokens
`common.js` inserts the markup on add-to-cart success: `<div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> {message} <button class="btn-close" ...></button></div>`, auto-dismissed after 3s. The same `#alert` container also carries error/danger alerts (out-of-stock, validation) using the identical stock styling.

### What to design
- A DS-styled toast replacing the raw Bootstrap alert: success state (green, checkmark) matching the owner's screenshot intent, **and** the error/danger state that shares the same container — both states are in scope (owner decision 2026-09-21), not just success.
- Position/behavior: keep it non-blocking and dismissible; the current fixed-centered-overlay placement is a candidate to reconsider (owner screenshot shows it visually colliding with page content) — propose a placement (e.g. top-of-viewport banner, corner toast) but flag it as a UX call, not just a re-skin, since it changes behavior slightly.
- Must not use `!important` overrides on Bootstrap internals if avoidable — if unavoidable, flag it for the executor.

### Constraints
- Success state uses DS green; error/danger state must NOT use purchase-green (site rule: green = purchase actions only).
- No urgency styling (no shake, no aggressive animation) — matches brand tone.

### Deliverable
Mockup/spec for success and error states, mobile and desktop, plus the chosen placement/positioning rule.

## 9. Owner decisions (2026-09-21)

- **Roadmap ID:** the toast is folded into RD-12 — no new ID. `ROADMAP_FLOW` / Notion note updated accordingly.
- **Scope:** both success and error/danger states are in scope, one design pass.

## 10. What NOT to touch

Checkout page (`checkout/checkout.twig`, RD-13) is a separate, HIGH-RISK task — out of scope here. Payment/Hutko/fiscalization logic, cart price calculation, and the Nova Poshta quote logic are out of scope regardless of which surface they appear on.

## 11. QA checklist (design output, before handback)

- [ ] Only existing `boostershop-ds.css` tokens/components used — new colors/fonts/radii flagged as open questions, not invented.
- [ ] RD-11: no price/checkout logic implied by the spec, only markup/CSS.
- [ ] RD-11: shipping-row copy treats the threshold as a variable, not a hardcoded ₴ amount.
- [ ] RD-12: drawer does not collide with cookie banner / back-to-top.
- [ ] RD-12: trigger button color spec uses `--bs-green` / `--bs-green-d`, not a new hardcoded hex.
- [ ] Toast: both success and error states specced.
- [ ] Toast: error/danger state does not use purchase-green.
- [ ] All three specced at mobile width ≈360–390px and desktop.

## 12. Handback

Save/paste the result as `handoffs/CLAUDE-DESIGN_RD-11-RD-12-toast_visual-design-result_<date>.md`, or hand it to the owner to paste into Claude (chat). Claude (chat) will fold it into an implementation handoff for the assigned executor.

## 13. Claude Design output — decisions (owner, 2026-09-21)

- **DS token for "free shipping" tint — CONFIRMED.** Add `--bs-green-soft: #F3FBF6` to `boostershop-ds.css`, next to the existing `-soft` tokens (`--bs-blue-soft: #E8EEFB`, `--bs-gold-soft: #FBF4DC`). Use it for the free-shipping banner background. Executor adds it; Claude (chat) does not edit site CSS directly.
- **Progress-to-free-shipping threshold — CONFIRMED: 30%.** Derive the "show progress" trigger as 30% of `shipping_pinta_nova_poshta_free_from`, not a hardcoded ₴ amount. **No new admin setting** — read the existing setting and compute the 30% cutoff at render time.
- **Cart recommendations widget — CONFIRMED for now: bestseller module.** Wire in the existing stock OpenCart bestseller module (`extension/opencart/catalog/{model,controller,view/template}/module/bestseller.php|twig`) as-is; confirm whether it's currently enabled anywhere live before reusing it. **Owner note: keep this integration minimal.** A separate, later task will rework the whole recommended-products concept away from stock OpenCart's manual per-product assignment — do not over-build or invest in polish here that that rework would throw away.
