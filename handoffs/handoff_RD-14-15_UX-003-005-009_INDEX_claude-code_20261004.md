# Пакет хендофів — RD-14 / RD-15 / UX-003 / UX-005 / UX-009

## Claude review 2026-10-04 — read first, binding

Verdict: approved for implementation with the corrections below and in each package handoff. Where a correction conflicts with the Ukrainian text, the correction wins.

**Evidence.**
- Source of truth: the owner's fresh live pull `rd-ux-batch-live2-<timestamp>.tar.gz` in the repo root. Extract it to `live-snapshots/20261004_rd-ux-batch-live2/` before anything else. The `20260930_rd14-rd15-ux003-ux005-ux009` snapshot is reference only.
- Design sources: `handoffs/design_20261004_rd14-15_ux003-005-009/`. The HTML pages load `rd14-shared.css`, `rd14-final.jsx`, `ux-b.css`, `ux-b-shell.jsx`, `ux-b-search.jsx` and `ux-b-stage3.jsx`, plus the audit page. If any of these is missing, stop and ask the owner. Do not rebuild the mockups from the handoff text alone.

Verified by Claude in `backup-9.24.2026_16-35-03_boosters.tar.gz`:
- `ocp5_theme` has no rows, so there are no DB template overrides.
- `config_telephone` = `+380636743252` and `config_email` = `helpbs@boostershop.website`, matching the RD-15 text.
- `ocp5_seo_url`: `path 59_73` → `Pokemon/figurky-ta-dekor-pokemon`; `path 60_74` → `One-Piece/figurky-ta-dekor-one-piece`.
- Hutko `response()` always redirects to `checkout/success`. `checkout/failure` is reached only from `confirm()` when the session has no order. That is `CHECKOUT-012` and out of scope.

**Chained runners.** Every package changes `boostershop-ds.css`, and therefore its `?v=` in `common/header.twig`. Packages 3 and 4 also change header markup.
- Build the runners strictly in index order on a local working copy. Runner N applies on top of runner N-1's output and SHA-256-guards that output.
- Every runner's backup includes `common/header.twig`.
- Deploy in the same order, with owner QA after each runner.
- On a failure, roll back only the last applied runner and stop. Never roll back an earlier runner while later ones are applied.
- Package 7 is last and may ship later.

**Runner rules** (on top of the `AGENTS.md` patch conventions):
- PHP 8.0 compatible. Never `require` Composer's autoloader (2026-09-22 incident: production CLI is PHP 8.0, the Composer platform check wants 8.1).
- Twig compile gate on every changed `.twig` before any write. Reuse `twig_gate` from `patches/TECH-045_wpe-purchase-green-sizes_20260929.php` or `twigCompile12` from `patches/RD-12_minicart-toast_20260921.php`.
- `php -l` on every changed PHP file. If any gate fails, restore all targets. Clear the OpenCart theme/Twig cache the same way the TECH-045 runners do.
- Twig hazards (RD-11/RD-12 storefront outage, 2026-09-22):
  - No literal `{#` inside inline CSS or JS: minified `{#checkout-success` opens a Twig comment. Keep a space, or wrap inline blocks in `{% verbatim %}`.
  - Write `0.3`, never `.3`.
- CSS: edit the current source rules instead of stacking overrides, and remove what the new design replaces.
  - RD-14's current styles: `#checkout-success …` in `boostershop-ds.css` (about lines 1953–2030 in the 2026-09-30 copy) and the inline `<style>` in `success.twig`.
  - RD-15's current styles: the inline R-11b `<style>` in `failure.twig`.
  - `!important` only with a stated reason (`AGENTS.md`, UI/CSS patch discipline 1–7).
- No database writes in any package. RD-15 is the only package that edits a language file.
- Before delivery, render every listed state locally with fixture data at 390 / 768 / 1440. Put the screenshots and gate output in `diagnostics/<TASK-ID>_<slug>_report_20261004.md`.

**Owner decision after the RD-14 deploy (2026-10-04) — support links.**
- Every "questions / напишіть у Telegram" support link goes to `https://telegram.me/BoosterShop_Support_bot`. That covers the RD-14 footer link and fallback button, the RD-15 button and `text_message` link, and the package 5 empty-state button.
- Links that present the channel stay on `https://telegram.me/boostershop_tcg` (burger «Наш Telegram-канал», footer).
- RD-14 is live with the channel URL, and runner 2 is already built with it. Do not rebuild runner 2.
- The URL swap is part of runner 3b below; there is no separate micro-runner.
- Runner 5 uses the bot URL directly.

**Deploy log and runner 3b (owner QA, 2026-10-04).**

Runners 1–3 are deployed on production. Owner QA passed except for the items below. Runner 1 backup: `_patch_backups/RD-14_success-steps_20261004-20261004-111827/`.

Build one follow-up runner, `RD-UX-qa-followups_20261004.php` (chain position **3b**), on top of the deployed post-runner-3 state. Runners 4–7 are then built on 3b's output. Its changes:
1. **Support links → bot.** Swap `https://telegram.me/boostershop_tcg` for `https://telegram.me/BoosterShop_Support_bot` in the RD-14 footer link and fallback button (`checkout/success.twig`), and in the RD-15 button (`checkout/failure.twig`) and `text_message` link (`extension/ukrainian/catalog/language/uk-ua/checkout/failure.php`; `php -l` and the sprintf check as in runner 2).
2. **Burger link.** Under One Piece Card Game in `common/header.twig`, add «Набори та бокси One Piece» → `/catalog/One-Piece/one-piece-nabory-ta-boksy`, between «Бустери One Piece» and «Фігурки та декор». The SEO path `60_68` was verified in the 2026-09-24 backup. Root-relative, class `bs-menu__sub`.
3. **Mini-cart «До каталогу».** In `common/cart.twig`, the empty-state button is `<button … data-bs-mini-cart-close>До каталогу</button>` and only closes the drawer. Owner decision: it closes the mini-cart and then opens the burger catalogue menu (the same action as `#bs-menu-open`).
   - Keep the close behaviour.
   - Open the menu only after the mini-cart's scroll lock is released.
   - QA on phone and desktop that the page scroll and the burger's own lock end in a clean state.
4. **Review N2** (`diagnostics/RD-UX-batch_runners-1-3_review_20261004.md`): set `#checkout-success .bs-success-f15-k` to `var(--bs-buy-hover)`. This is the only ds.css change in 3b, so 3b bumps the ds.css token in `header.twig`. Runner 4 no longer carries N2.

Runner 3b rules:
- SHA-guard every target on the deployed state.
- Run the Twig gate on every changed template (`success.twig`, `failure.twig`, `header.twig`, `cart.twig`).
- Note that `cart.twig` is not touched by runners 4–7; BUG-004 builds on 3b's output.

**Live search (runner 4).** The owner confirmed the mobile suggestions are too tall. Hiding the description, as runner 4 already specifies, is the owner's requested fix: name up to 2 lines, small thumbnail, price.

**Status.** Notion stays `In progress`. Claude (chat) sets `Done` after owner QA. The executor writes neither Notion nor `ROADMAP_TASKS`.

**Out of this batch:** `BUG-004` (mobile mini-cart: missing quantity badge; swipe-down on the open drawer reloads the page), `CHECKOUT-012`, remaining `TECH-045` work.

**Deploy log runners 3b–7 and runner 8 (owner QA, 2026-10-04).** Runners 3b, 4, 5, 6 and 7 are deployed on production and passed owner QA except the items below. Claude's review: `diagnostics/RD-UX-batch_runners-3b-7_review_20261004.md` (R1–R10).

Build one follow-up runner, `UX-003-005-009_polish_20261004.php` (chain position **8**), on the deployed post-runner-7 state. Reconstruct it outside the repo: live2 pull + runners 1, 2, 3, 3b, 4, 5, 6, 7 from `patches/`; derive every `EXPECTED_SHA` from that state. One work package: layout polish, CSS/Twig only.

1. **Header fits at every width (owner decision: fix).** `.bs-header__actions` overflows the viewport at roughly 769–905 px (measured in the runner-6 report; the upper bound depends on the real fonts — the owner's 900 px screenshot shows the cart button cut off). Fix at the source rules: in the affected range show «Акаунт/Увійти» and «Telegram» as icons only, keeping accessible names (`aria-label` or visually-hidden text), and shorten the cart label (for example icon + total). Determine the range by measurement. Widths where everything already fits, and 390 px, stay unchanged.
2. **Category header card below 992 px: two rows, no swipe (owner decision).** The owner rejects the single swipe row. Row 1: subcategory pills wrap onto as many lines as needed, counts kept, no horizontal scroll. Row 2: the «Фільтр» button and the sort control side by side, equal width, filling the row, with text labels (no icon-only squares). The filter panel opens below row 2 as now. ≥992 px unchanged.
3. **Extra gap on mobile.** At 400 px the owner sees about 35 px of empty space between the category header card and the first product row; at ≥992 the gap is about 15 px. Find the cause (empty `.bs-ff-chips`, the panel container, the 14 px margin added by runner 7, or the dead UX-004 chips block), make the gap equal to the ≥992 gap, and name the cause in the report.
4. **Review R2.** The empty `.bs-ff-chips` placeholder renders an 8×8 grey dot at ≥992 on categories without sub-/sibling categories. Fix at the source rule (`:empty`) or stop rendering the empty placeholder.
5. **Review R6.** The live-search error button `.bs-ls-state__btn` overflows at 320 px. Let it wrap (`white-space: normal; height: auto; max-width: 100%`) at the source rule.

**Runner 8 rules.** Same runner library and gates as runners 1–7: SHA guard on every target, Twig parse gate on every changed template, hazard scan on added text, CSS balance gate, ds.css `?v=` token bumped once, backup + restore-all-on-fail, idempotent marker, self-delete. No PHP logic, no DB, no `!important` unless the report justifies it. Measure before/after at 320, 360, 390, 576, 768, 769, 800, 900, 991, 992, 1024, 1280 and 1440 px on home, category (with subcategories, without subcategories, with a filter applied), product, search and checkout (no submit); horizontal scroll must be zero at every listed width. Write `diagnostics/UX-003-005-009_polish_report_20261004.md` with files, before/after SHA-256, gates, the rollback command (prefixed with `cd ~/public_html &&`) and an owner QA list. Stop and report if any gate fails or the work crosses this scope.

**Not in runner 8.**
- Filter chips and filtering without a page reload: runner 9, owner-confirmed (fetch the same filtered category URL, swap the product list, chips from the checked state, `history.pushState`, fallback to a normal reload on error). It waits for the Claude Design states brief `handoffs/handoff_UX-003_filters-no-reload_visual-design-brief_20261004.md`.
- List/grid toggle: hidden by an earlier owner decision. Do not restore it; drop it from QA lists.
- `BUG-004`.
- Console issue: Chrome reports "Content Security Policy of your site blocks the use of `eval`" (`script-src`). None of runners 1–7 adds `eval`, `new Function` or string timers (grep of every changed file). The only `new Function` in the pulled files is in `catalog/view/javascript/nunjucks-slim.js`, unchanged since the pull and not referenced by any pulled template. Source unattributed; separate diagnostic task if the owner wants one.

---


Дата: 2026-10-04 · Executor: Claude Code (призначено власником)
Правило: один пакет = один патч-файл у `patches/`, окремий відкат. Порядок нижче — рекомендований.

| № | Пакет | Хендоф | Модель | Ризик | Обов’язкова перевірка |
|---|---|---|---|---|---|
| 1 | RD-14 «Замовлення прийнято» | `handoffs/handoff_RD-14_success_claude-code_20261004.md` | Opus/high | checkout | `bs-checkout-smoke` |
| 2 | RD-15 «Оплата не пройшла» | `handoffs/handoff_RD-15_failure_claude-code_20261004.md` | Sonnet/medium-high | суміжно з checkout | кроки failure з `bs-checkout-smoke` |
| 3 | Шапка + бургер | `handoffs/handoff_UX-003-005_header-burger_claude-code_20261004.md` | Sonnet/medium-high | усі сторінки | кроки 1–4 `bs-checkout-smoke` |
| 4 | Живий пошук | `handoffs/handoff_UX-009_live-search_claude-code_20261004.md` | Sonnet/medium-high | низький | — (після №3) |
| 5 | Сторінка пошуку | `handoffs/handoff_UX-009_search-page_claude-code_20261004.md` | Sonnet/medium-high | низький | meta robots / canonical до-після |
| 6 | Сітка fluid до 991 | `handoffs/handoff_UX-003_grid-fluid-991_claude-code_20261004.md` | Sonnet/medium-high | глобальний CSS | скріни 600–990 |
| 7 | Фільтр категорії C | `handoffs/handoff_UX-003_category-filter-C_claude-code_20261004.md` | Opus/high | SEO фільтр-URL | `bs-seo-risk-gate`; блокер — filter.twig |

Поза пакетом: бейдж кількості кошика на мобільному не рендериться (аудит, пункт 10) — окрема баг-задача.

Дизайн-джерела: `RD-14 RD-15 - фінал.html`, `UX-003 UX-005 UX-009 - макети.html`, `UX-003 UX-005 UX-009 - етап 3.html`, `UX-003 UX-005 UX-009 - аудит.html`.
