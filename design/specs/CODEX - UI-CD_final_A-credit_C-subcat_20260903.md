# Visual Design Result — UI-CD (final) — Components A + C

Date: 2026-09-03 | From: Claude Design | For: Codex (paired with `handoffs/handoff_UI-FIX_codex-handoff_20260902.md`)
Source brief: `uploads/handoff_UI-CD_visual-design-brief_20260902.md`
Supersedes: `handoffs/CODEX - UI-CD_visual-design-result_20260902.md` (that draft's Component B section is withdrawn — B is being redesigned separately, see `handoffs/CLAUDE - B tiles continuation_20260903.md`).

**Scope of this handoff: Component A and Component C only. Component B (homepage category tiles) is NOT in scope — do not touch `.bs-catcards`, `.bs-subtiles`, or `.category-tiles`.**

Owner decisions (forms 2026-09-02 / 2026-09-03):
- A → buttons side-by-side on mobile AND desktop.
- C → direction **C3 «перевернута ієрархія»**: subcategories become the page's primary navigation, the category H1 card is demoted to a small caption line.

All values reuse existing `boostershop-ds.css` / `tokens.css` tokens: 4px grid, `--bs-r-sm:6px`, `--bs-r:10px`, `--bs-r-lg:14px`, `--bs-r-pill:999px`, `--bs-line`, `--bs-line-2`, `--bs-ink`/`-2`/`-3`/`-4`, `--bs-gold-soft`, `--bs-pokemon`, `--bs-onepiece`, `--bs-blue`. No new colors, fonts, or off-scale spacing.

Reference prototypes (rendered against real saved production pages at 390px):
- A: `PAY-001 Купити в кредит.html` / `pay001-credit.jsx`
- C: `C - підкатегорії mobile у живому контексті.html` (variant C3, third frame; the 3/4/5 switch at the top shows subcategory-count behaviour)

---

## Component A — Installment/credit modal action buttons

Presentation-only. No change to button text, order, click handlers, provider logic, or the loading state's copy.

### Spec
- Layout: `display:flex; flex-direction:row; gap:12px` at **both** breakpoints. The existing mobile-only `flex-direction:column` override is deleted — that is the core of the change.
- Height: `min-height:48px` on both buttons (up from the ~46px the current `padding:13px 16px` produces). 48px is on the 4px grid, one step above the DS default `.bs-btn` 44px — justified because this is a modal's primary/secondary pair, not an inline UI button. Use `min-height`, never a fixed `height`.
- Width: `flex:1` on each — true 50/50. No `min-width`; the modal itself caps at 390px (mobile sheet) / 440px (desktop dialog).
- Internal padding: `padding:0 12px` (height now governs vertical size).
- Text wrap: `white-space:normal; text-align:center; line-height:1.25`. At 360px the secondary label «Продовжити покупки» has ~145px of width and may wrap to two lines; `min-height:48px` lets the button grow to ~56–60px instead of clipping. This is accepted, not a defect.
- Gap above the button row: `16px` between the term-pill row and the button row.
- Colors unchanged: primary `background:#111; border:1.5px solid #111; color:#fff`; secondary `background:#fff; border:1.5px solid var(--bs-line); color:var(--bs-ink-2)`. Radius `var(--bs-r-sm)`. Loading spinners unchanged — a spinner + «Додаємо…» still fits inline at 48px.
- Identical for both provider cards (monobank, ПУМБ) — same markup, same fix.

### Verify after deploy
- 360px and 390px: both buttons on one row, ≥48px tall, neither label clipped.
- Desktop dialog: same row, 48px, 16px above.
- Loading state on each provider still renders spinner + text inline.

---

## Component C — Category subcategory navigation (mobile), direction C3

### Intent
On a parent category page the client should see, immediately and without scrolling, (1) which subcategories exist, with counts, and (2) the start of the real product grid. Today the H1 card plus the tab row eat the top of the screen and the first product card starts at **335px** from the top. C3 brings that to **302px** while making the subcategory list *more* prominent, not less. Horizontal scrolling and truncated subcategory names are explicitly rejected by the owner — every name must be fully readable.

### Structure (mobile, ≤640px)
Replace the current `.bs-cat-header` + `.bs-subcat-tabs` presentation with a single block placed where the header card is now, directly under the breadcrumbs:

1. **Caption line** — category name + product count as one small line: `font-size:12px; font-weight:700; color:var(--bs-ink)`, the count as `font-weight:500; color:var(--bs-ink-4)` after a `·` separator. Margin `0 2px 6px`.
2. **Subcategory grid** — `display:grid; grid-template-columns:1fr 1fr; gap:8px`. Each cell is a link card:
   - `background:#fff; border:1px solid var(--bs-line); border-radius:var(--bs-r); padding:9px 12px; min-height:40px; box-sizing:border-box`
   - `display:flex; align-items:center; justify-content:space-between; gap:8px`
   - Name: `font-size:12.5px; font-weight:700; color:var(--bs-ink); line-height:1.2; overflow-wrap:anywhere` — full name, never truncated, wraps to a second line if needed (the card grows).
   - Count badge: `font-size:11px; font-weight:700; padding:2px 7px; border-radius:var(--bs-r-pill); flex:0 0 auto`, colored with the page's category accent — Pokémon: `color:var(--bs-pokemon); background:var(--bs-gold-soft)`; One Piece: `color:var(--bs-onepiece); background:var(--bs-blue-soft)`.
   - Odd total: `.card:nth-child(odd):last-child{grid-column:1/-1}` — the last card spans the full row instead of leaving a hole.
3. The old `.bs-subcat-tabs` row is removed from the mobile view entirely.

### Count behaviour — the actual rule, not a per-count hardcode
This is what the May 2026 patches got wrong (`:has(> :nth-child(4):last-child)` matched an *exact* count, so 5 or 7 subcategories fell through and broke). The C3 grid is count-agnostic by construction: a fixed 2-column grid plus the odd-last-child span rule holds at 2, 3, 4, 5, 6, 7+ with no future design pass. Verified in the prototype at 3, 4 and 5 subcategories.

### Before you implement — confirm production state
The patches `patches/patch-r03-final-segmented-tabs-20260522.php` and `patches/patch-r03-subcat-siblings-and-count-20260522.php` shipped a segmented desktop control + auto-grid mobile tabs for this component on 2026-05-22; **their live status is unconfirmed** (owner does not know). Check `view-source` on `/catalog/Pokemon` and `/catalog/One-Piece` for `.bs-segmented`, `.bs-subcat-tabs`, `.bs-subcat-tab`:
- present/live → the mobile tab block is replaced by the C3 block; the desktop segmented control stays untouched;
- absent/reverted → build the C3 block fresh; still leave desktop as-is.

### Desktop — no change
The desktop segmented control is explicitly out of scope. Only harden it if it is already live and visibly squeezing at 5+ chips, in which case allow overflow instead of shrinking (`overflow-x:auto; flex-wrap:nowrap; scrollbar-width:none`). Nothing else on desktop changes.

### SEO / a11y flags (need Codex judgment, please confirm before deploy)
- **The H1 must remain a real `<h1>`** with the category name, even though it renders as a 12px caption. Do not replace it with a `<div>` and do not `display:none` it. The category name also stays in the breadcrumbs, so the demotion is visual only.
- The subcategory grid should be a `<nav aria-label="Підкатегорії">` containing `<a>` elements.
- Cards are 40px min-height — below the 44px touch guideline. Accepted here because the tap target is the full card width (~183px at 390px) and the vertical rhythm matters more than the extra 4px; if QA objects, `min-height:44px` costs ~16px of page height total.
- No change to category business logic or the count values (Codex's existing wiring per §9 of the brief) — this is presentation only.

### Verify after deploy (390px, real device)
- `/catalog/Pokemon` (4 subcategories): 2×2 grid, all four names fully readable, counts 16/13/11/19 present, first product card starts ≈300px from top.
- `/catalog/One-Piece` (3 subcategories): 2 + 1 full-width, counts 10/8/8.
- Add or remove a subcategory in the admin → layout still holds with no CSS edit.
- Desktop unchanged on both pages.
- `<h1>` still present in the DOM and in the rendered source.
