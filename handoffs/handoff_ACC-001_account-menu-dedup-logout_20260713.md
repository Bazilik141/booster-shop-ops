# Codex Handoff — ACC-001: account menu duplicated on desktop, incomplete on mobile (no logout)

Date: 2026-07-13. Risk: LOW (account templates, no checkout/payment/SEO surface). `bs-seo-risk-gate` n/a. Related to RD-ACCOUNT (full account redesign, still todo) — this is a standalone quick fix that must not wait for it.

> Live-file task: account templates are owner-server-only; anchor against LIVE files.

## 1. Task ID

ACC-001 — authorized customer, account pages (`route=account/account`):
- **Desktop view (Chrome «версія для ПК» on mobile):** two menus render simultaneously — the sidebar «Особистий кабінет» (Мої замовлення / Змінити персональні дані / Мої адреси / Змінити пароль / **Вихід**) AND the card «Меню» (Змінити контактну інформацію / Змінити свій пароль / Змінити мої адреси / Переглянути закладки / Історія замовлень).
- **Mobile view:** only the «Меню» card renders — it has no «Вихід» and no direct «Мої замовлення» equivalent naming; owner reports it as the incomplete menu.
Observed by owner 2026-07-13 on Android Chrome (screenshots exist).

## 2. Context

- Two menu surfaces coexist in the account area: the OpenCart column/sidebar and a card-style list. Which template renders which, and what hides the sidebar on mobile (CSS breakpoint vs template condition), is not established — Codex must verify on live (`account/account` template, column_right/column_left partials, theme CSS).
- RD-ACCOUNT (full redesign) is not started; this fix should be minimal and not pre-implement the redesign.

## 3. Goal

Exactly one account menu per viewport, with a complete item set. «Вихід» (logout) must be reachable on mobile. Item set consistent between desktop and mobile.

## 4. What to change

- Diagnose on live which templates/blocks produce each menu and why mobile hides one.
- Pick the minimal fix (owner-visible proposal in the report before patching if the choice is ambiguous): either keep one menu and remove/hide the duplicate on desktop, or merge the missing items («Вихід», «Мої замовлення», якщо відсутній аналог) into the menu that stays visible on mobile.
- Ensure all links kept are valid current routes (verify each `href` on live; do not invent routes).

## 5. Do not touch

- Checkout (all of it), payment, fiscalization, Hutko, NP module logic.
- Login/logout controller logic — link/markup only.
- Registration flow and the post-registration address redirect (owner keeps it; it is ACC-002 territory).
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD, DB.

## 6. Likely files / areas (verify against LIVE)

- `catalog/view/template/account/account.twig` and account partials.
- Theme column templates (`common/column_right.twig` / `column_left.twig`) and the CSS that collapses them on mobile.
- Codex should verify against actual project files before assuming which menu is «old» vs «custom».

## 7. Acceptance criteria (measurable)

1. Desktop `account/account`: exactly one menu block visible.
2. Mobile `account/account`: exactly one menu block visible, containing working «Вихід» (logs out, redirects to the stock logout confirmation/home) and access to orders history.
3. Every remaining menu link returns HTTP 200 as an authorized user.
4. No visual regression on neighboring account pages that share the same partials (адреси, пароль, історія замовлень — spot-check each).
5. Diff limited to account templates/CSS listed in the report.

## 8. QA / smoke test

No checkout smoke required (no checkout files touched — if the diff ends up touching any checkout file, stop and report instead). QA = §7 on one desktop + one real mobile device, authorized user.

## 9. Rollback note

Back up every changed template/CSS to `_patch_backups/ACC-001_<...>-<timestamp>/...`; restore and clear `cache.*` + `template/*` via `DIR_CACHE` to roll back.

## 10. Recommended status after execution

`На перевірці` → owner mobile+desktop QA → `Готово`. Note in report anything worth carrying into RD-ACCOUNT later.
