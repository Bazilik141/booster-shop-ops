# Claude Code Handoff — UI-FIX: mobile/desktop polish batch (2026-09-02 owner review; executor switched Codex → Claude Code 2026-09-03)

Date: 2026-09-02 | Parent: `UX-036` (registered in the Notion roadmap 2026-09-02; design brief tracked as `UX-036-UI`)
Executor: Claude Code · model=Sonnet · thinking=high — **owner override 2026-09-03: switched from Codex.** Nine independent, well-bounded fixes across six templates/pages/assets, now including Claude Design's delivered specs for the credit-modal buttons, subcategory navigation, and homepage category tiles. Task 8 (category tiles) needs local asset-derivative generation and verification, which is exactly the signal `AGENTS.md`'s executor table calls out for Claude Code. None of the nine are individually architecturally ambiguous, but the batch is multi-file, multi-step, and now touches one new asset pipeline. If Task 5 or Task 10's DB gate resolves as DB-writing work, split it into its own round per §0 rather than raising the whole batch to Opus/high.
Diagnosis input: newest cPanel backup in repo root is `backup-8.28.2026_13-26-46_boosters.tar.gz` (2026-08-28) — **5 days old relative to this handoff.** The owner's report below (admin price, disabled special) reflects today's (2026-09-02) admin state. If the backup doesn't confirm what's described here — especially for Task 5 — request a fresh backup drop before patching; do not infer today's DB state from a 5-day-old snapshot.

## 0. DB-write gate (applies to Task 5 and Task 10)
Per `AGENTS.md` convention C6: any DB write requires explicit owner approval + rollback SQL in the patch header. If Task 5's root cause needs a DB write (see Task 5 below), **stop, present the finding and proposed SQL to the owner, and hold that one task out of this batch's patch** until approved — ship the other five tasks without waiting on it. Do not fold an unapproved DB write into the same auto-running patch as the template/CSS fixes.

Task 10 (added 2026-09-03) needs its own sign-off before any write: it is a **schema addition** (a new table), not a data correction, so present the exact DDL for owner approval — not just rollback SQL for an existing row — before running `CREATE TABLE`.

## 1. Context
Owner reviewed the live site (boostershop.website) on mobile and flagged 5 technical/copy issues (below) plus visual-design issues that went through a separate Claude Design brief (§7). Everything below is fully scoped and ready to implement **except Task 11** (Component D, preorder delivery-date label placement), which is still PENDING Claude Design output.

**Addendum 2026-09-03:** Task 10 (preorder delivery-date ETA, product + listing pages) and its companion Task 11 (Claude Design placement/typography) were added after this handoff's original six-task scope. Task 10 carries its own DB-approval gate (§0, extended above) separate from Task 5's.

**Addendum 2026-09-03 (second):** Claude Design delivered specs for three of the four pending components — Task 7 (installment/credit modal buttons), Task 8 (homepage category tiles), and Task 9 (subcategory navigation) — now fully scoped below in §7. Component D (Task 11, preorder delivery-date label placement) has not been delivered yet and remains PENDING. The owner also switched this handoff's executor from Codex to Claude Code for all tasks (see Executor line above).

## 2. Scope — ready to implement now

### Task 1 — Sealed/Unweighed section spacing on the guarantee page
**Page:** `https://boostershop.website/information/original-garanty`
**Symptom (owner screenshots, mobile):** vertical spacing between the intro paragraph, the "Sealed — заводське пакування без втручання" heading, and the following paragraph reads uneven/misaligned — reported as "layout got skewed" near this block and the "Unweighed — без зважування та відбору" block right below it.
**Likely files/zone (unconfirmed — verify against the backup):** this is an `information/` CMS-style content page; check whether the content is a static block/template or admin-editable rich text, and whether a shared content-page CSS rule (rather than this page specifically) is misapplied.
**Fix:** identify the actual rule producing the uneven spacing (root cause first, per `AGENTS.md` UI/CSS patch discipline #1) and correct it. Do not introduce a page-specific `!important` override without stating why fixing the source rule is unsafe (discipline #3).
**Acceptance criteria:**
- [ ] Vertical rhythm between the intro paragraph → "Sealed" heading → its paragraph → "Unweighed" heading → its paragraph is visually consistent (same pattern repeated for each of the three guarantee sub-sections on this page, not just these two).
- [ ] No regression on desktop width for the same page.

### Task 2 — account/login page: block order + registration copy
**Page:** `https://boostershop.website/?route=account/login`
**Change A (reorder):** the "New Customer / Реєстрація" block and the "Registered Customer / Увійти" block currently render as [Реєстрація block] then [Увійти block]. Swap their order so [Увійти] renders first, [Реєстрація] second. Pure DOM/template reorder — no new markup.
**Change B (copy replacement):** replace the current registration block copy (which currently reads: intro sentence about creating an account, then a paragraph about quantity-discount tiers and free shipping over 1500 грн) with exactly:
- Heading (H2): `Реєстрація`
- Paragraph: `Створіть акаунт, щоб купувати в один клік і не вводити дані щоразу.`

Confirm whether the existing heading text ("Новий клієнт" / "Реєстрація" as a two-line stack, per the owner's screenshot) should collapse to the single H2 above, or whether "Новий клієнт" is a separate eyebrow/label element that stays — if the current template has both as separate elements, keep the structure but the **H2 itself becomes "Реєстрація"** and the body copy becomes exactly the sentence above; do not silently drop an existing element without flagging it in the diagnostic.
**What NOT to touch:** the "Продовжити" button, the login form fields/captcha below, and — because `ACC-003` (`handoffs/handoff_ACC-003_login-register-token-rotation_20260822.md`) already found `login_token`/`register_token` regenerating on every render as a live P0-class defect on this exact page — **do not touch anything token/form-submission-related on this page.** If reordering the blocks in the template risks re-triggering that token regeneration bug, stop and flag it rather than guessing.
**Acceptance criteria:**
- [ ] Увійти block renders above Реєстрація block, both blocks otherwise unchanged.
- [ ] Реєстрація block shows exactly the H2 and paragraph text specified above.
- [ ] Login and registration still submit successfully after the reorder (manual owner QA — this page has a known token-regeneration defect, see above).

### Task 3 — Telegram reviews button + OLX logo fix
**Pages:** `https://boostershop.website/information/original-garanty` (add) and every product page's "Відгуки" tab (fix existing).
**Part A — add TG button to the guarantee page:** the guarantee page currently has a plain text link "Переглянути відгуки на OLX →" under "Звідки береться товар". Product pages already have the correct pattern for this: a "Відгуки" tab containing two side-by-side icon-boxes, "Відгуки в Telegram" and "Відгуки на OLX" (see product-page screenshot). Replace the plain-text OLX link on the guarantee page with the **same two-icon-box component already used on product pages**, reusing that existing markup/partial rather than building a new one.
- Telegram target: `https://t.me/boostershop_tcg/23` (owner-confirmed).
- OLX target: keep the existing link already used elsewhere (`https://www.olx.ua/uk/list/user/ubnF9/?tab=ratings`).
**Part B — fix the OLX logo asset:** the OLX icon currently shown in the product-page "Відгуки на OLX" box does not match OLX's actual brand mark. The owner has dropped the correct logo file in the repo root: **`Logo OLX.png`** (1024×605 PNG, RGBA, added 2026-09-02). Use this as the source asset — crop/export it to whatever icon size the existing box component needs; do not source a different OLX logo from elsewhere.
**What NOT to touch:** the Telegram-reviews box that already exists on product pages — only replace the OLX icon asset within it. Do not touch the modal/tab logic itself in this task (that's Task 4).
**Acceptance criteria:**
- [ ] Guarantee page shows two icon-boxes (Telegram + OLX) in place of the current plain-text OLX link, visually matching the product-page pattern.
- [ ] Telegram box links to `https://t.me/boostershop_tcg/23`.
- [ ] OLX icon (both on this page and on existing product-page boxes) uses the `Logo OLX.png` asset, correctly sized/cropped — not stretched or pixelated.

### Task 4 — "Відгуки про нас →" button behavior on product pages
**Symptom:** on a product page, the small "★ Відгуки про нас →" link (positioned above the price block) currently navigates straight to the external OLX ratings page (`https://www.olx.ua/uk/list/user/ubnF9/?tab=ratings`), bypassing the on-page "Відгуки" tab entirely.
**Fix:** change this link's behavior so it no longer navigates externally. Instead it should scroll the page down to the tab section (Опис / Характеристики / Відгуки), programmatically select the "Відгуки" tab, and stop there — letting the customer choose between the Telegram box and the OLX box that already live in that tab (per Task 3).
**What NOT to touch:** the tab component itself, its two review boxes, or the OLX/TG links inside them (Task 3 owns the OLX icon only).
**Acceptance criteria:**
- [ ] Clicking "Відгуки про нас →" no longer opens olx.ua directly.
- [ ] Click scrolls to the tab row and activates the "Відгуки" tab, revealing both the Telegram and OLX boxes.
- [ ] Works from any scroll position on the product page (top of page, mid-page).

### Task 5 — Price mismatch: Pokémon TCG Inferno X booster box
**Owner report (today, 2026-09-02):** admin base price = 6000 грн; a special/discount price of 5700 грн exists but is **disabled**. Live site currently shows: homepage recommended-products block → 5700 грн; product page → 6000 грн (per the two screenshots, home shows 5700, product page shows 6000 — **note the two screenshots show opposite values from each other**, so confirm the actual current live values per surface before fixing, do not assume the direction from this handoff); category listing (`Pokémon → Бустер бокси`) → a third value. Treat the owner's description of "which surface shows which price" as directional, not exact — re-verify live before diagnosing, since the two attached screenshots of the same product disagree with each other on which page shows 5700 vs 6000.
**Root cause: unconfirmed.** Do not guess. Investigate whether this is (a) a leftover/incorrectly-disabled `product_discount` row still being read by some but not all price-rendering paths (home/related-products block vs. category-listing block vs. product-page block may use different queries or cache layers), or (b) a caching issue (category listing cache not invalidated after the last admin price edit), or (c) something else entirely. **Gate:** if the fix requires a DB write (e.g. deleting/correcting a `product_discount` row), stop per §0 above and get owner approval + rollback SQL before writing anything — do not fold this into the same silent-apply patch as Tasks 1–4/6.
**Acceptance criteria:**
- [ ] All three surfaces (homepage, product page, category listing) show the same, correct price for this product, matching the current admin base price (confirm the actual admin value live before fixing — the 6000/5700 figures above are the owner's report as of today, re-verify rather than hardcode).
- [ ] If a stale/disabled discount row was the cause, confirm no other product shares the same defect pattern (spot-check a couple of other products with a disabled special, per the diagnostic).

### Task 6 — Breadcrumb "current" chip overflow on long product names
**Symptom (owner screenshot, dev-tools inspector open):** on a product page, when the product name is long (example in the screenshot: "Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання)"), the breadcrumb's final "current page" pill visually overflows its rounded background — text extends past the chip's boundary instead of staying inside it. Inspector confirms the element is `span.bs-crumb__current`, computed styles: `color: #6B3A00`, `font: 12px Manrope, system-ui, -apple-system,...`, `background: #FBF4DC`, `padding: 4px 12px` — no `max-width`/`text-overflow`/`white-space` shown in the inspected computed set.
**Root cause (historical evidence, not confirmed against current live state):** `handoffs/handoff_RD-10D2_breadcrumb-mockup-fix_2026-06-11.md` shows the product-page breadcrumb's current chip (`.bs-crumb__current` in `product.twig`) rendered as an inline-styled `<span>` with only `background`/`border-color`/`color` set per-instance — no width constraint. By contrast, that same handoff's *category-listing* breadcrumb CSS (`stylesheet.css`, `.breadcrumb .breadcrumb-item:last-child > a`) explicitly sets `max-width: min(54vw, 420px)` with implied ellipsis handling. If `.bs-crumb__current` in the current `boostershop-ds.css`/`product.twig` still lacks an equivalent max-width + `overflow: hidden; text-overflow: ellipsis; white-space: nowrap`, that's the likely gap — **but a later patch, `patches/cat002_5c_mobile_visual_breadcrumb_20260630.php` (2026-06-30), postdates RD-10D2 and may have already changed this — verify against the current live/backup state before patching, don't patch against the June handoff blind.**
**Note:** `.bs-crumb__current` lives in the shared `boostershop-ds.css` (soft risky zone per `AGENTS.md` UI/CSS patch discipline #6) — grep `patches/` for prior overrides on this selector before editing, per discipline #2, and state what you found.
**Acceptance criteria:**
- [ ] Long product names (test with the example above, and at least one other long product name) truncate with an ellipsis inside the pill rather than overflowing it, on mobile widths (≈360–390px).
- [ ] Short product names are unaffected (no unnecessary truncation on names that already fit).
- [ ] Category-listing breadcrumbs (which already handle this per RD-10D2) are not regressed.

### Task 10 — Preorder delivery-date ETA (product page + category/listing label) — ADDED 2026-09-03
**Owner request:** show an estimated delivery/arrival window for products in preorder status (`stock_status_id = 8`, the same "Передзамовлення" flag already used by the store's checkout/cart preorder logic — see `patches/ORDER-STATUS-001_preorder_order_status_20260721.php` and `patches/PAY-001_preorder-stock-gate_20260725.php`).

**Mechanism (owner decision 2026-09-03, chosen from three options Claude presented):** two new dedicated date fields (delivery-window start/end) — **not** a reuse of OpenCart's native `date_available`. `date_available` is intentionally left untouched: its default OpenCart behavior ties into catalog visibility and add-to-cart gating (unconfirmed against this store's exact code — verify before assuming, but the standard OpenCart product-listing query and stock/availability checks commonly gate on `date_available <= NOW()`), which would conflict with the preorder feature's whole point (sell now at qty 0, ETA is informational only). Reusing it was rejected to avoid a second bespoke override, on top of the one `Cart::hasStock()` already needed for `stock_status_id = 8` (see the `PAY-001_preorder-stock-gate` patch header). A single date padded into a fake range was also rejected — the owner wants a real, manually set range per product.

**Data model (guidance, not a mandate — Codex verifies and finalizes the exact DDL against the live schema):** a small dedicated table keyed by `product_id`, e.g. `oc_product_bs_delivery_window(product_id INT PRIMARY KEY, delivery_from DATE NULL, delivery_to DATE NULL)`, rather than `ALTER TABLE` on core `oc_product` — smaller blast radius, trivial rollback (`DROP TABLE`). Confirm this does not collide with an existing custom table before creating it (check the newest backup's schema dump).

**Admin UI:** two new date-picker fields on the product edit form (Admin → Catalog → Products → Data tab), positioned near the existing `Date Available` field, both optional (empty is a valid, expected state — see fallback rule). Ukrainian labels, e.g. "Дата надходження (з)" / "Дата надходження (по)". Standard admin `product.php` controller/model + template changes — lower risk than storefront/checkout, still needs `php -l` and a manual admin save/edit smoke test.

**Storefront display logic:**
- Only render anything when `stock_status_id = 8`. Never show this on an in-stock or any other-status product.
- Both dates set → show the real range:
  - Product page, next to the existing "Статус: Передзамовлення" pill (see owner screenshot — the empty circled space next to the pill is roughly where this goes; exact placement/typography is Claude Design's call, see Task 11): `Термін доставки — орієнтовно 20–25 вересня` when both dates fall in the same month; `Термін доставки — орієнтовно 28 вересня – 3 жовтня` when the range crosses a month boundary. Ukrainian month names in the genitive case (вересня, жовтня, листопада, …) — cover all 12 months, not just the current one.
  - Category/listing/promo pages, near the existing preorder badge (no screenshot of that badge's current layout in this handoff — inspect the live markup first): compact numeric format `20–25.09` (same month) or `28.09–03.10` (crossing months), zero-padded two-digit day/month.
- Either date missing → fallback text: `орієнтовно 3–4 тижні` on the product page, `3–4 тижні` on listing pages. Hardcoded constant, no settings UI needed for this task.
- `delivery_to` before `delivery_from` (bad admin input) → treat as unset, fall back to the generic text rather than rendering a nonsensical range.

**What NOT to touch:** `date_available` and its existing behavior; `stock_status_id` assignment logic; the `ORDER-STATUS-001` order-status flow. This task is read-only against all three.

**Acceptance criteria:**
- [ ] Preorder product with both dates set shows the real range in both formats (product-page prose, listing compact) — same-month and cross-month cases both verified.
- [ ] Preorder product with no dates set shows the fallback text in both places.
- [ ] In-stock (or any non-preorder-status) product shows neither the range nor the fallback anywhere.
- [ ] Admin can set, edit, and clear both dates via the product edit form without errors; clearing both reverts the storefront to the fallback text.
- [ ] `date_available`-driven behavior (catalog visibility, add-to-cart gating) is unchanged — spot-check at least one product to confirm this task did not touch it.

**Risk / DB gate:** creating a new table is a schema change — see the extended §0 gate above. Do not write to the database until the owner has approved the exact DDL and rollback SQL.

## 3. What NOT to touch (whole batch)
- Checkout, cart logic, or payment API integrations (Tasks above are presentation-only where they touch payment-adjacent surfaces).
- `sitemap.xml`, `robots.txt`, canonical tags, `.htaccess`, Merchant feed, schema/JSON-LD.
- Any product's price other than the one named in Task 5, unless the diagnostic finds the same defect pattern elsewhere (report it, don't silently fix it beyond the named product without flagging).
- `login_token`/`register_token` handling on the account/login page (see Task 2 note — known live P0 defect tracked separately under `ACC-003`).
- `date_available` and its existing catalog-visibility/cart-gating behavior (Task 10 is additive only — see that task's own exclusion note).
- Component A (Task 7): button text, order, click handlers, and provider logic — presentation only.
- `.bs-subtiles` ("Інші TCG", "Аксесуари") and the `--bs-pokemon`/`--bs-onepiece` accent variables as tile borders/fills (Task 8) — scope is `.bs-catcards` only.
- The desktop subcategory segmented control and the category count/business logic behind it (Task 9) — mobile-only change, presentation only.

## 4. Diagnostics report
Required (this is a handoff task, and Task 5/6 touch DB-adjacent and shared-CSS zones respectively) — use `templates/codex-report-template.md`. Naming: `diagnostics/UI-FIX_mobile-desktop-polish-batch_report_20260902.md`.

## 5. Patch output
Suggested: one self-contained runner for Tasks 1–4, 6, 7, and 9 (template/CSS/copy only, no new files), named `patches/UI-FIX_mobile-desktop-polish-batch_20260902.php`, following all 7 conventions in `AGENTS.md`. Task 8 ships as its own runner — it creates new image derivatives and possibly a new folder (`image/catalog/tiles/`), a different risk/verification profile than the template-only tasks. Task 5 ships separately (own patch + its own DB-approval gate) if it turns out to need a DB write — do not block Tasks 1–4/6/7/9 on Task 5's diagnosis. Task 10 ships separately too, gated on its own DDL approval per §0.

## 6. QA checklist (owner runs after deploy)
Per `AGENTS.md` UI/CSS patch discipline #5 — check at minimum 3 breakpoints (not mobile-only), long-content edge cases, and interactive states:
- [ ] `https://boostershop.website/information/original-garanty` — spacing (Task 1) + TG/OLX boxes (Task 3) at mobile (~390px), tablet, and desktop widths.
- [ ] `https://boostershop.website/?route=account/login` — block order + copy (Task 2), and a real login + a real registration attempt succeed (token-regeneration regression check).
- [ ] Any product page's "Відгуки" tab — OLX logo renders correctly (Task 3), "Відгуки про нас →" scrolls+switches tab instead of leaving the site (Task 4).
- [ ] Homepage, Pokémon category listing, and the Inferno X product page — same price shown on all three (Task 5).
- [ ] A product page with a long product name — breadcrumb current chip truncates cleanly (Task 6), at mobile width specifically.
- [ ] General regression pass on the standard Tier 1 smoke URLs (`AGENTS.md` § Tier 1 smoke URLs) since this batch touches shared CSS (`boostershop-ds.css`) and a shared product-page partial.
- [ ] A preorder product with a real range set, a preorder product with no range set (fallback text), and an in-stock product — delivery-date text appears correctly (or not at all) in all three cases, on both the product page and a listing/category page (Task 10).
- [ ] Credit-installment modal (any product ≥500 ₴ with an installment option) — both provider cards' buttons sit side by side, ≥48px tall, at mobile and desktop (Task 7).
- [ ] Homepage — both category tiles render as square illustrations with a working CTA, correct links, `.webp` served, legacy duplicate logo row gone, `.bs-subtiles` unchanged (Task 8).
- [ ] `/catalog/Pokemon` and `/catalog/One-Piece` — subcategory grid holds at both pages' actual counts (4 and 3), no truncated names, first product card starts near the top of the viewport (Task 9).

## 7. Claude Design output — Components A, B, C ready; Component D still pending

Claude Design delivered three of the four components from `handoffs/handoff_UI-CD_visual-design-brief_20260902.md`, provided by the owner 2026-09-03:
- `CODEX - UI-CD_final_A-credit_C-subcat_20260903.md` — Component A (credit modal buttons) + Component C (subcategory navigation, direction C3).
- `CODEX - UI-CD_Btiles_20260903.md` — Component B (homepage category tiles), with a rendered reference prototype (`B - плитки категорій ФІНАЛ.html`) built against a live saved copy of the homepage.

Component B supersedes an earlier, since-withdrawn B draft from the same design round — this handoff uses only the final spec below.

### Task 7 — Installment/credit modal action buttons (Component A)
**Scope:** presentation only — button height, layout, spacing. Do **not** change button text, order, click handlers, provider logic, or the loading-state copy. This modal is part of the active PAY-002 (monobank) / PAY-003 (ПУМБ) credit flow, patched 2026-08-31–2026-09-01 — treat as a soft risky zone even though this task is CSS-only.
**Owner decision:** buttons go side-by-side (not stacked) at both mobile and desktop widths.
**Spec:**
- `display:flex; flex-direction:row; gap:12px` at all breakpoints — delete the existing mobile-only `flex-direction:column` override.
- `min-height:48px` on both buttons (never a fixed `height`); `flex:1` each for a true 50/50 split; no `min-width` (the modal itself caps at 390px mobile / 440px desktop).
- `padding:0 12px`; `white-space:normal; text-align:center; line-height:1.25` — the secondary label ("Продовжити покупки") may wrap to two lines at 360px width and grow the button to ~56–60px tall; this is accepted, not a bug.
- `16px` gap between the term-pill row and the button row.
- Colors/radius unchanged: primary `background:#111; border:1.5px solid #111; color:#fff`; secondary `background:#fff; border:1.5px solid var(--bs-line); color:var(--bs-ink-2)`; radius `var(--bs-r-sm)`.
- Identical treatment on both provider cards (monobank, ПУМБ) — same markup.
**Acceptance criteria:**
- [ ] 360px and 390px: both buttons on one row, ≥48px tall, no clipped labels.
- [ ] Desktop dialog: same row layout, 48px, 16px gap above.
- [ ] Both providers' loading state (spinner + "Додаємо…") still renders inline at 48px.
- [ ] No change to click behavior, button order, or the modal's JS/data layer — spot-check both provider flows still submit correctly after the CSS-only change.

### Task 8 — Homepage category tiles redesign (Component B)
**Scope:** `.bs-catcards` only (Pokémon TCG / One Piece Card Game tiles). Do **not** touch `.bs-subtiles` ("Інші TCG", "Аксесуари") — owner decision, they stay exactly as on production.
**What changes:** the two tiles become full-bleed 1:1 square illustrations with a single white "Дивитись усе" CTA in the top-left corner — no category name text, no description, no product count, no photo, no colored fill. The category name lives in the artwork itself and survives for machines via `alt`/`aria-label`.

**Source assets — owner-reported, not independently verified (no server access from this chat):** the owner states two PNGs were uploaded directly to production at `image/catalog/Other/` (site root `public_html/`), with these exact filenames:
- `One Piece Card Game logo tiles catygory.png`
- `Pokemon trading Card Game logo tiles catygory 2.png`

(Filenames, including the "catygory" spelling, are as the owner typed them — verify the exact on-disk names first, do not silently "fix" the typo when referencing the file.) **First step: confirm these two files actually exist at that path before building anything against them.**

**Derivatives to generate** (per Claude Design's spec — reference prototype `B - плитки категорій ФІНАЛ.html`, `FINAL_CSS` constant is the shipping CSS):

| File | Size | Format |
|---|---|---|
| `category-tile-onepiece-1080.webp` | 1080×1080 | WebP q80 |
| `category-tile-onepiece-540.webp` | 540×540 | WebP q80 |
| `category-tile-pokemon-1080.webp` | 1080×1080 | WebP q80 |
| `category-tile-pokemon-540.webp` | 540×540 | WebP q80 |

Target ≤160 KB per 1080 file; drop to q72 rather than shrinking dimensions if a file exceeds that. No cropping/recolor/baked-in overlay — the scrim is CSS. Write derivatives to `image/catalog/tiles/` (new folder). Keep the source PNGs as masters; do not serve them directly.

**⚠ Open technical question — confirm before choosing an implementation approach:** these derivatives need to exist as real files at `image/catalog/tiles/` on production. This handoff has no confirmed answer on whether production PHP 8.0 has GD or Imagick with WebP encode support:
- If yes: the patch script itself can read the two source PNGs and generate all four WebP files server-side when the owner runs it (standard patch-runner pattern — file-exists check, backup, idempotent marker).
- If no, or unconfirmed and risky to assume: fall back to asking the owner to supply the four pre-converted WebP files directly (same naming/path), and this task's patch only wires up the DOM/CSS/template — no image processing in the patch itself.

Pick based on what you can confirm about the production PHP build; do not guess and ship a patch that silently fails on `imagewebp()`/`Imagick` not existing.

**DOM** (replace contents of the existing `.bs-catcards` container, keep the container element/position, change its class):
```html
<div class="bs-cattiles">
  <a class="bs-cattile" href="/catalog/Pokemon" aria-label="Pokémon TCG — дивитись усе">
    <picture>
      <source type="image/webp"
              srcset="/image/catalog/tiles/category-tile-pokemon-540.webp 540w,
                      /image/catalog/tiles/category-tile-pokemon-1080.webp 1080w"
              sizes="(min-width:900px) 530px, 50vw">
      <img src="/image/catalog/tiles/category-tile-pokemon-1080.webp"
           alt="Pokémon Trading Card Game" width="1080" height="1080"
           loading="lazy" decoding="async">
    </picture>
    <span class="bs-cattile__scrim" aria-hidden="true"></span>
    <span class="bs-cattile__cta">Дивитись усе</span>
  </a>
  <!-- same block for One Piece → href="/catalog/One-Piece" -->
</div>
```
`href` values are the existing ones already on the page — read them from the current `.bs-catcard` markup, do not retype them. Order stays as on production.

**CSS** (tokens only, 4px grid — full block, copy verbatim):
```css
.bs-cattiles{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:4px 0 12px}
.bs-cattile{position:relative;display:block;aspect-ratio:1/1;overflow:hidden;
  border-radius:var(--bs-r-lg);text-decoration:none;background:var(--bs-ink)}
.bs-cattile img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;
  transition:transform .5s cubic-bezier(.2,.7,.2,1)}
.bs-cattile__scrim{position:absolute;inset:0 0 auto 0;height:36%;pointer-events:none;
  background:linear-gradient(to bottom,rgba(10,12,16,.66),rgba(10,12,16,0))}
.bs-cattile__cta{position:absolute;left:11px;top:10px;font-size:11.5px;font-weight:700;
  color:#fff;letter-spacing:.01em}
.bs-cattile:hover img{transform:scale(1.04)}
.bs-cattile:focus-visible{outline:2px solid var(--bs-ink);outline-offset:3px}
@media (prefers-reduced-motion:reduce){
  .bs-cattile img{transition:none}
  .bs-cattile:hover img{transform:none}
}
@media (min-width:900px){
  .bs-cattiles{gap:24px;margin:8px 0 16px}
  .bs-cattile__scrim{height:34%}
  .bs-cattile__cta{left:20px;top:18px;font-size:13.5px}
}
```
Two columns at every breakpoint (mobile is the pair of squares, not a stack — owner's explicit pick over a full-width square). `--bs-pokemon`/`--bs-onepiece` accent variables are no longer used here — do not reintroduce as borders/dots/fills.

**Legacy block to remove:** `.category-tiles` — the duplicate logo-button row currently under the tiles, repeating the same two categories. Delete from the template (not `display:none`). If it's generated by a module/setting rather than the template, disable it there and note which.

**SEO flag — confirm before deleting:** `.bs-catcard__desc` (the current tile description text) is removed from the tile. If this text is unique indexable SEO copy (not a decorative duplicate of the category page's own text), it must be preserved elsewhere on the homepage — check this before deleting, don't assume it's decorative.

**Acceptance criteria:**
- [ ] 390px and 360px mobile: two equal square tiles side by side, gap ~14px, nothing cut off, CTA legible on one line.
- [ ] 1100px+ desktop: two columns, gap 24px, hover zooms artwork ~4% with no jitter/overflow; reduced-motion disables the zoom.
- [ ] Each tile links to its correct category (`/catalog/Pokemon`, `/catalog/One-Piece`).
- [ ] Legacy `.category-tiles` row is gone; `.bs-subtiles` ("Інші TCG", "Аксесуари") unchanged.
- [ ] View-source: homepage `<h1>` untouched; both tiles have `alt` and `aria-label`.
- [ ] Each tile serves a `.webp` (not the PNG) ≤160 KB, correct size variant per viewport (540 at mobile widths).
- [ ] Homepage mobile PageSpeed/LCP not regressed by the `loading="lazy"` tiles; if either tile sits above the fold on a tall phone and LCP regresses, switch the first tile to `loading="eager" fetchpriority="high"` and re-measure.

### Task 9 — Category subcategory navigation, mobile (Component C, direction C3)
**Scope:** mobile only (≤640px). Desktop's segmented subcategory control is out of scope — leave as-is; only harden it (`overflow-x:auto` instead of shrinking) if it's confirmed live and visibly squeezed at 5+ chips.
**Owner decision:** direction **C3 "inverted hierarchy"** — subcategories become the page's primary navigation; the category H1 becomes a small caption line above them. Horizontal scrolling and truncated subcategory names are explicitly rejected — every name must be fully readable.

**Before implementing — confirm production state:** patches `patch-r03-final-segmented-tabs-20260522.php` and `patch-r03-subcat-siblings-and-count-20260522.php` (2026-05-22) shipped a segmented desktop control + auto-grid mobile tabs for this component — **their live status is unconfirmed.** Check `view-source` on `/catalog/Pokemon` and `/catalog/One-Piece` for `.bs-segmented`, `.bs-subcat-tabs`, `.bs-subcat-tab`: if present, the mobile tab block is replaced by the structure below (desktop segmented control stays); if absent/reverted, build the structure fresh (desktop still untouched).

**Structure** (replace `.bs-cat-header` + `.bs-subcat-tabs` on mobile, placed directly under the breadcrumbs):
1. Caption line: category name + count as one line, e.g. `font-size:12px; font-weight:700; color:var(--bs-ink)`, count `font-weight:500; color:var(--bs-ink-4)` after a `·` separator. Margin `0 2px 6px`. **The element must remain a real `<h1>`** even though it renders as a 12px caption — do not swap it for a `<div>` or hide it; it also stays in the breadcrumbs, so this demotion is visual only.
2. Subcategory grid: `display:grid; grid-template-columns:1fr 1fr; gap:8px`, wrapped in `<nav aria-label="Підкатегорії">` with `<a>` cells. Each cell:
   - `background:#fff; border:1px solid var(--bs-line); border-radius:var(--bs-r); padding:9px 12px; min-height:40px; box-sizing:border-box; display:flex; align-items:center; justify-content:space-between; gap:8px`.
   - Name: `font-size:12.5px; font-weight:700; color:var(--bs-ink); line-height:1.2; overflow-wrap:anywhere` — full name, never truncated, wraps and grows the card if needed.
   - Count badge: `font-size:11px; font-weight:700; padding:2px 7px; border-radius:var(--bs-r-pill); flex:0 0 auto` — Pokémon: `color:var(--bs-pokemon); background:var(--bs-gold-soft)`; One Piece: `color:var(--bs-onepiece); background:var(--bs-blue-soft)`.
   - `.card:nth-child(odd):last-child{grid-column:1/-1}` — odd totals: last card spans the full row.
3. Old `.bs-subcat-tabs` row removed from the mobile view entirely.

**Count behavior — a rule, not a hardcode:** this must hold at 2, 3, 4, 5, 6, 7+ subcategories with no future design pass (this is what the May patches got wrong: `:has(> :nth-child(4):last-child)` matched an exact count and broke at 5+). The fixed 2-column grid + odd-last-child span rule is count-agnostic by construction.

**a11y note:** cards are 40px min-height, below the 44px touch guideline — accepted because the tap target is the full card width (~183px at 390px). If QA objects, `min-height:44px` costs ~16px of total page height.
**Not in scope:** category business logic or the count values themselves (existing wiring, presentation only).

**Acceptance criteria:**
- [ ] `/catalog/Pokemon` (4 subcategories): 2×2 grid, all names fully readable, counts 16/13/11/19 present, first product card starts ≈300px from top.
- [ ] `/catalog/One-Piece` (3 subcategories): 2 + 1 full-width row, counts 10/8/8.
- [ ] Adding/removing a subcategory in admin → layout holds with no CSS edit.
- [ ] Desktop unchanged on both pages.
- [ ] `<h1>` present in the DOM and in rendered source.


### Task 11 — PENDING — Preorder delivery-date label: placement + typography — ADDED 2026-09-03
*(Awaiting Claude Design. Two placements: (1) product page, next to the existing "Статус: Передзамовлення" pill in the specs table — see owner screenshot, the circled empty space is roughly where the text goes; must handle both a fairly long real-range string ("орієнтовно 28 вересня – 3 жовтня") and the shorter fallback ("орієнтовно 3–4 тижні") without breaking the row on mobile. (2) category/listing/promo pages, near the existing preorder badge — no current screenshot of this in the brief, inspect the live badge first. Secondary/informational text weight, not a CTA — do not style as a button or use purchase-green. Backend/data logic is already scoped in Task 10.)*

## 8. Risks
- Task 5's root cause is unconfirmed and may require a DB write — see §0 gate.
- Task 6 touches a shared DS file (`boostershop-ds.css`) — soft risky zone, override-history check required before editing.
- Task 2 sits on a page with a known live P0 token-regeneration defect (`ACC-003`) — reordering markup on this page carries a real (if small) risk of re-triggering it; flag rather than guess if anything about the reorder looks like it touches token generation.
- Newest available backup (2026-08-28) is 5 days old; Task 5 in particular depends on today's admin state — request a fresh backup if the old one doesn't match what's described here.
- Task 10 is a new-table schema change — do not let it ride along in the same auto-apply patch as Tasks 1–4/6 without its own explicit DDL approval, same spirit as Task 5's gate.
- Task 8's source PNGs were uploaded by the owner directly to production (`image/catalog/Other/`), not into this ops repo — there is no local copy to test the image pipeline against before deploy; existence and correctness of the derivatives can only be verified after the owner runs the patch on production, same as any other patch, but the extra step here is confirming GD/Imagick+WebP support first (see Task 8's open question).
- Task 8 removes `.bs-catcard__desc` text from the homepage tiles — confirm it isn't unique indexed SEO copy before deleting (see Task 8's SEO flag); if it is, this task needs a place to preserve that text before it can ship as scoped.
- Task 9 depends on the live/reverted state of the 2026-05-22 subcategory patches, which is unconfirmed — the implementation path differs depending on what's actually live; verify before writing the patch, don't build against the May handoff blind (same caution as Task 6's breadcrumb history).
