# Design Brief — UI-CD: mobile/desktop polish — four components

Date: 2026-09-02 | Parent: none (new batch, not yet registered in Notion roadmap)
For: Claude Design
Paired Codex handoff (implementation, prepared in parallel): `handoffs/handoff_UI-FIX_codex-handoff_20260902.md`
Owner evidence: 15 phone screenshots (boostershop.website, live production), one with browser dev-tools inspector open — provided 2026-09-02. Plus 1 additional screenshot (product page, preorder status area circled) — provided 2026-09-03 for Component D.

## 1. Summary
Four storefront UI elements read as visually unfinished, break under real content, or (Component D) are a brand-new element with no existing visual to build from. This brief covers only the parts that need a design decision (layout, hierarchy, visual treatment). Several other items from the same owner review are pure technical/copy fixes and go straight to Codex — they are not part of this brief.

**Addendum 2026-09-03:** Component D (preorder delivery-date label) was added after this brief's original three-component scope. Its data/backend logic is fully scoped in the paired Codex handoff's Task 10 — this brief covers only where and how the label looks.

## 2. Task type
Visual design / UX specification only. No code, no backend logic, no copy invention beyond what's already live on the site.

## 3. Workflow
Claude Design produces the visual spec below → owner reviews/approves (or asks for revisions) → owner pastes the result back to Claude (chat) → Claude merges it into `handoffs/handoff_UI-FIX_codex-handoff_20260902.md`, replacing the four "PENDING — Claude Design" placeholder sections (Tasks 7, 8, 9, 11) → Codex implements the whole batch (this brief's output + the already-scoped technical items) as one patch.

## 4. Status
Brief ready. Not blocked on anything else.

## 5. Site constraints (apply to all three components below)
- OpenCart storefront, mobile-first. Shared design-system tokens/components live in `catalog/view/stylesheet/boostershop-ds.css`. **Do not invent new colors, fonts, or spacing scales** — reuse existing DS tokens/components used elsewhere on the site. If something genuinely isn't covered by the current DS, flag it as an open question rather than improvising.
- Brand tone: Booster Shop is a curated TCG store, not a marketplace — avoid aggressive/marketplace-style promo visuals (e.g. discount-badge clutter, urgency banners) unless it's already how the rest of the site looks.
- Green is reserved for purchase/cart/checkout/success actions site-wide. Do not introduce new green elements in these three components unless the element genuinely is a purchase action (none of the three below are).
- Exact current colors/spacing for these three components are **not captured in this brief** (no dev-tools inspection was done on them, unlike the breadcrumb issue that went to Codex). Claude Design should inspect the live pages/elements directly before proposing final values — do not guess hex codes.
- Mobile is the primary target (owner's screenshots are all phone-width, ~390px viewport) but each spec must also hold up on desktop.

Live pages to inspect:
- Homepage: `https://boostershop.website/`
- Pokémon category (top level): `https://boostershop.website/catalog/Pokemon`
- One Piece category (top level): reachable from the homepage "One Piece Card Game" tile
- Any in-stock product priced ≥500 ₴ with an installment option, to see the live credit modal (click the installment/"Покупка частинами" control on the product page)
- For Component D: a product page currently showing "Статус: Передзамовлення" (the owner's 2026-09-03 screenshot shows one example — find it or a similar one live), plus a category/listing page containing at least one preorder item, to see the current badge there before proposing placement

## 6. Component A — Installment/credit modal action buttons

### Current state (owner screenshot)
Product-page modal titled "Виберіть кредитну пропозицію" lists two provider cards stacked vertically — monobank ("Покупка частинами", term pills 3/4/5 платежів) and ПУМБ ("Сплачуйте частинами", same term pills) — each card ends with two full-width buttons stacked one above the other: a filled black "Додати й оформити" and an outline "Продовжити покупки". Both buttons are comfortably wide but visually thin/short in height — a small mobile tap target.

### ⚠ Risky-zone caution — read before designing
This modal belongs to the active credit-installment feature (monobank via PAY-002, ПУМБ via PAY-003), which had multiple patches deployed 2026-08-31–2026-09-01 (`patches/PAY-002_*`, `patches/PAY-003_*`). "Додати й оформити" is the real submit action for that flow, not a decorative button.
**Scope this design to presentation only: button height/min-height, internal padding, and stacked-vs-side-by-side layout. Do not propose changes to the buttons' text, order, click behavior, or the modal's data/logic.** Codex will apply your layout/sizing spec without touching the JS event bindings; flag anything in your spec that would require a logic change instead of a CSS change.

### What to design
Owner gave two acceptable directions — pick one (or present both for the owner's own final call):
1. Keep the two buttons stacked (current order, full width each) but taller — enough for a comfortable mobile tap target.
2. Make them taller **and** place them side by side in one row (roughly 50/50 split) instead of stacked.

Do not change the buttons' fill/outline colors (black fill primary, outline secondary is the existing monobank-brand-aligned treatment — keep it).

### Deliverable
- Final button height (px or existing DS spacing token) for both buttons.
- Final layout choice (stacked vs side-by-side) with exact gap/spacing between the two buttons and between this button pair and the term-pill row above it.
- Spec for both provider cards (monobank and ПУМБ) — same treatment, since they currently share the same button markup.
- Mobile (≈360–390px) and desktop states.

## 7. Component B — Homepage main category tiles (Pokémon TCG / One Piece Card Game)

### Current state (owner screenshot)
Two horizontal rows under the hero banner, each: game logo on the left, game name + "Переглянути →" link on the right, plain background, thin top accent line (orange for Pokémon, navy for One Piece). Owner describes the result as "reads as primitive/unfinished" — most visible on mobile, but also flagged (less severe) on desktop.

### What to design
A redesign of these two entry tiles that reads as more finished/premium while staying inside the existing DS (see §5) — this is the main storefront entry point into the catalog, so it should feel intentional, not like an unstyled list. Keep the functional pattern (tile → click-through to that game's category) but improve visual hierarchy, spacing, and card treatment (elevation/border/imagery — whatever the DS already has precedent for elsewhere on the site).

**Do not:**
- add invented pricing, ratings, review counts, or "top seller"-style claims to these tiles — none of that data is wired here;
- turn this into a marketplace-style promo banner (see brand tone in §5).

### Deliverable
Mockup/spec for both tiles at mobile width (~390px) and desktop, including hover/focus state for desktop.

## 8. Component C — Category subcategory filter chip row

### Current state (owner screenshots)
Below a category's H1 (e.g. "Pokémon · 43 товарів" or "One Piece Card Game · 19 товарів"), a row of subcategory chips with item counts. Pokémon currently has 4 chips (Бустери 16 / Бустер бокси 13 / Набори 11 / Фігурки та декор 19); One Piece has 3 (Бустери 10 / Набори та бокси 8 / Фігурки та декор 8). Owner reports the row already looks cramped/misaligned at 3 chips and visibly breaks (uneven wrapping) after the 4th chip was added to Pokémon.

### What to design
A layout/scaling rule for this chip row that holds up from 2 chips up through at least 5–6, on mobile widths down to ~360px, without owner intervention every time a subcategory is added or removed. Options to consider (pick the one that fits the DS best, not obligated to use these exact patterns):
- single-line horizontal scroll (the breadcrumb component elsewhere on the site already uses a horizontal-scroll pattern for overflow — consistency with that is a plus, not a requirement);
- controlled 2-line wrap with fixed chip sizing;
- "+N more" overflow pattern.

Whatever is chosen must be a **rule**, not a one-off fix sized to exactly today's 3-or-4-chip count — a future 5th or 6th subcategory should not require a new design pass.

### Deliverable
Mockup/spec showing the 2-chip, 3-chip, and 4-chip states at mobile width, plus the explicit rule for what happens at 5+ (so Codex can implement it as CSS logic, not a hardcoded list).

## 9. Component D — Preorder delivery-date ETA label — ADDED 2026-09-03

### Current state
New feature, not a fix — no existing visual to build from. Backend/data logic is fully scoped in the paired Codex handoff's Task 10 (`handoffs/handoff_UI-FIX_codex-handoff_20260902.md`): for products with preorder status ("Статус: Передзамовлення"), the storefront will show an estimated delivery window — either a real admin-set date range or a generic fallback when no range is set. Text content and exact wording are already fixed by the owner (see Codex handoff Task 10) — this brief is about **where and how it looks**, not what it says.

### What to design
Two placements:
1. **Product page** — next to the existing "Статус: Передзамовлення" pill in the specs table (see the owner's 2026-09-03 screenshot: the empty space circled next to the pill is roughly where the owner wants this). Text will be one of:
   - `Термін доставки — орієнтовно 20–25 вересня` (real range, same month)
   - `Термін доставки — орієнтовно 28 вересня – 3 жовтня` (real range, crossing a month)
   - `Термін доставки — орієнтовно 3–4 тижні` (fallback, no range set)
   The row must hold up with the longest of these (the cross-month case) without breaking on mobile — this is the same class of bug as the guarantee-page spacing issue and the breadcrumb overflow issue already in this batch, so treat row/wrap behavior as a first-class constraint, not an afterthought.
2. **Category/listing/promo pages** — near the existing preorder badge shown on product cards/tiles. **No screenshot of this badge exists in this brief** — inspect the live markup on a category page that has a preorder item before proposing placement (do not guess a layout). Text will be the compact form: `20–25.09`, `28.09–03.10`, or the fallback `3–4 тижні`.

### Constraints
- Secondary/informational text weight — this is not a call to action. Do not style it as a button, an urgency badge, or use purchase-green (see §5 brand rule).
- Must read clearly at both the real-range length and the shorter fallback length without the layout jumping around between products.
- Reuse the existing preorder badge's visual language (color/weight) where sensible rather than inventing a new visual system just for this one label.

### Deliverable
Placement + typography spec for both locations, at mobile width (~360–390px) and desktop, covering both the real-range and fallback text-length cases.

## 10. What NOT to touch
Scope is exactly these four components. Do not propose changes to: the breadcrumb component, other buttons on the site, checkout/payment flow beyond §6's presentation-only note, product data, the category business logic that produces the chip counts, or the preorder date logic itself (that's Codex's wiring in Task 10, not a design concern).

## 11. QA checklist (design output, before handback)
- [ ] Only existing `boostershop-ds.css` tokens/components used — no new colors/fonts introduced without flagging them as an open question.
- [ ] Component A: button color/text/click-order unchanged; only height/layout touched; both layout options documented if the owner should make the final call.
- [ ] Component A: no proposed change requires touching modal JS/logic (flag separately if unavoidable).
- [ ] Component B: no invented pricing/rating/review data on the tiles.
- [ ] Component C: scaling rule explicitly stated for 5+ chips, not just today's counts.
- [ ] Component D: both placements specced (product page + listing), both text-length cases (real range and fallback) covered, no button/urgency/green styling used.
- [ ] All four specced at mobile width ≈360–390px; B, C and D also specced for desktop.

## 12. Handback
Save/paste the result as `handoffs/CODEX - UI-CD_visual-design-result_20260902.md` (or hand it to the owner to paste into the Claude chat). Claude (chat) will fold it into the four "PENDING — Claude Design" sections (Tasks 7, 8, 9, 11) of `handoffs/handoff_UI-FIX_codex-handoff_20260902.md` so the owner gets one merged Codex handoff covering everything.
