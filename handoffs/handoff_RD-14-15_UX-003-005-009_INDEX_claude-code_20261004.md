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

**Status.** Notion stays `In progress`. Claude (chat) sets `Done` after owner QA. The executor writes neither Notion nor `ROADMAP_TASKS`.

**Out of this batch:** `BUG-004` (mobile mini-cart: missing quantity badge; swipe-down on the open drawer reloads the page), `CHECKOUT-012`, remaining `TECH-045` work.

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
