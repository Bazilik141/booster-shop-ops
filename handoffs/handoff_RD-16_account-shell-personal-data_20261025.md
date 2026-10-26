# Handoff — RD-16: account shell, hub and personal data (light variant A)

Date: 2026-10-25
Executor: Codex · model=Terra · effort=high. The owner assigned Codex. Codex wrote ACC-007/008/009 and holds the context for the account controllers, the custom-field guard and the name policy that this redesign must leave unchanged. The work is mostly markup and CSS, so a mid-tier model is enough. Effort is high because the personal-data form sits on the guarded customer-data save path.
Notion: RD-16 https://app.notion.com/p/3706bf20bdb481b396b7e38300a3c8bf (the page holds the owner scope, 2026-10-13). Umbrella on the dashboard: `RD-ACCOUNT`.

## 1. Task ID
`RD-16`. It blocks RD-17, RD-18, RD-19 and RD-20, which reuse this shell, and the ACC-006 visual step.

## 2. Context
The account area still uses near-stock markup. ACC-001 left one menu surface plus logout in `account/account.twig`. The data foundation is now live:
- ACC-007: customer-facing status labels, groups and hidden statuses.
- ACC-008: a server-side `custom_field` preservation guard.
- ACC-009: optional patronymic, Telegram and Viber in the profile, plus the final Ukrainian name policy. Closed 2026-10-23.
- ACC-006: the wishlist backend, the header counter and GA4 (2026-10-15).

So RD-16 items 3 and 4 (new fields, name validation) are **already implemented**. RD-16 is now a presentation task over working functionality.

Design source: `design/account-light-A/` (README, four boards: `A-Hub`, `A-Edit`, `A-Mobile-Hub`, `A-Mobile-Edit`). Live canvas: https://claude.ai/artifact/d5fbf671-8451-42ce-b887-4e7904ad81f3.

There is no staging environment. Every deployed patch lands on production.

## 3. Goal
1. Build one account shell in the approved light variant A:
   - desktop: a left sidebar with the nav, a «Допомога» card and the theme placeholder;
   - mobile: the hub shows the nav as a list, and other account pages show a horizontal chip nav.
2. Restyle the hub (`account/account`) and the personal-data page (`account/edit`).
3. Keep every save, validation and data behaviour exactly as it is live today.

## 4. What to change

### WP0 — fresh evidence (stop at G0)
The newest backup, `backup-10.15.2026_13-09-55_boosters.tar.gz`, **predates** ACC-008 (deployed 2026-10-16) and ACC-009 (deployed 2026-10-22/23). Both changed files in this scope, so the backup is stale for them.
- List every file the patches will read or change, with its purpose. Then request one owner-run narrow pull under `CODEX_WORKFLOW.md` → «Narrow file pulls». Claude chat composes the owner command and you check it. Include at minimum:
  - `catalog/controller/account/account.php`
  - `catalog/controller/account/edit.php`
  - `catalog/view/template/account/account.twig`
  - `catalog/view/template/account/edit.twig`
  - `catalog/view/javascript/booster-account-profile.js`
  - `catalog/view/javascript/booster-account-universal.js`
  - the Ukrainian `account/account` and `account/edit` language files
  - `catalog/view/stylesheet/boostershop-ds.css`
  - every file that renders the cache-bust reference
  - whatever renders the current account menu (`column_left` or the account module); verify this, do not assume it
- Verify against the pulled files and the ACC-009 release targets (`diagnostics/ACC-009_closeout_evidence_20261023/release-artifacts.json`): the pulled after-hashes must match the recorded post-release state. Report any drift.
- Check for a theme DB override of the account templates (an `ocp5_theme` row) before planning Twig edits.
- **Decide the shell mechanism and report it before WP1:**
  - (a) a controller-rendered block passed to each account template;
  - (b) a shared Twig partial;
  - (c) duplicated markup per template.
  OpenCart 4 `ArrayLoader` can break third-party `{% include %}`, so option (b) only after proving the active loader supports it. Recommend one option with its trade-off. RD-17/18/19/20 and the wishlist page will reuse it.

### WP1 — shell + hub (one patch)
- **Nav**, in order: Кабінет · Особисті дані · Адреси доставки · Мої замовлення · Улюблене · Налаштування · separator · Вийти.
  - «Налаштування» is not a link: `aria-disabled="true"`, not focusable, «Скоро» label (until ACC-005).
  - Active item uses `aria-current="page"`.
  - «Вийти» uses the existing live logout route and parameters exactly as ACC-001 left them. Verify OpenCart 4's `customer_token` requirement; do not hand-build the URL.
- **«Допомога» card** (desktop: under the nav; mobile hub: a card; mobile sub-pages: a chip in the chip nav). Link `https://t.me/BoosterShop_Support_bot`, `target="_blank" rel="noopener"`.
- **Theme placeholder «Тема сайту»** («Світла / Темна»):
  - blurred and not interactive (`disabled` buttons, `aria-hidden` on the blurred group or an equivalent);
  - «Скоро» badge;
  - no script, no storage. RD-29 enables it.
- **Hub content:**
  - welcome banner with the H1 «Особистий кабінет»;
  - four summary cards: personal data (name, email, phone), main address (default address or an empty state), latest order, favorites count;
  - «Останнє замовлення» row.
  - Requirements:
    - read-only data only;
    - the latest order and its status label come from the ACC-007 presentation map, with hidden statuses excluded the same way as the order list;
    - the favorites count comes from the live ACC-006 count source;
    - every card has a truthful empty state (no orders, no address, no favorites).
  - Boards show placeholder data. Never render placeholder text.
- **Colors:** map board hex values to existing DS tokens. Add a new token only if none exists, and say why. Green stays reserved for purchase. No green in the shell.
- **CSS:** goes into `boostershop-ds.css` under a scoped account block, following AGENTS.md «UI/CSS patch discipline». Cache-bust under patch convention 8: read the token, never hardcode it.

### WP2 — personal-data page (one patch, after the owner QA-passes WP1)
- Card form per `A-Edit` / `A-Mobile-Edit`:
  - fieldsets «Основне» (first name, last name, optional patronymic) and «Контакти» (phone, email, optional Telegram, optional Viber);
  - labels above inputs;
  - «Зберегти зміни» primary (blue, not green) and «Скасувати» secondary.
- **Keep ACC-009 behaviour byte-for-byte in logic:**
  - field names and IDs that the ACC-009 JS/controller bind to;
  - client and server validation;
  - normalization and existing error messages;
  - the dynamic-hint behaviour.
  The board's `pattern` attribute and hint text are pre-ACC-009 and **must not** be added. Restyle the existing error presentation only.
- **Keep the ACC-008 guard path untouched:** same form action, same posted keys, no new hidden echo of `custom_field`. Keep the ACC-008 profile success confirmation and only restyle it.
- **Email field — owner decision D1, open.** The board shows email read-only with «Зміна основного email — у розділі «Налаштування»». ACC-005, which provides email change, is not built, so a read-only field would remove the only way to change email.
  - Default unless the owner decides otherwise: keep email editable and validated as today, and omit the «Налаштування» hint.
  - Do not implement the read-only variant without a recorded owner decision.

## 5. Do not touch
- **Protected zones:** `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment (Hutko, monobank ПЧ, `pumb_credit`), Checkbox/fiscalization, Nova Poshta logic, Merchant feed, schema/JSON-LD.
- **Customer data logic:**
  - `system/library/booster_account_profile.php`;
  - the ACC-008 guard and writer bodies in `catalog/controller/account/edit.php` and `address.php`;
  - `catalog/model/account/*`;
  - admin customer files;
  - custom-field metadata rows. No DB writes of any kind.
- **Validation sources:** the ACC-009 name/Telegram/Viber rules in their JS and PHP sources. Restyle only.
- **Login/register tokens:** the session-stable `login_token` / `register_token` logic (ACC-003).
- **Other account pages (separate tasks):** `account/address*`, `account/order*`, `account/login`, `account/forgotten`, `account/register` (RD-17/18/19/20), and the wishlist templates/backend (ACC-006). They adopt the shell in their own tasks. If the chosen mechanism (a) or (b) makes them render the new nav automatically, report that in WP0 and get an owner decision before shipping.
- **Shared header and product templates:** `common/header.twig`, product card, product page.
- **No RD-29 work:** no dark theme or switcher logic.

## 6. Likely files / areas
Likely, not confirmed. **The executor must verify against the actual pulled files.**
- `catalog/view/template/account/account.twig`
- `catalog/view/template/account/edit.twig`
- `catalog/controller/account/account.php` (read-only hub data)
- the account menu source (`column_left` / the account module)
- the Ukrainian account language files (new labels only)
- `catalog/view/stylesheet/boostershop-ds.css` and its cache-bust reference

## 7. Acceptance criteria
- `index.php?route=account/account` (logged in) at 390/768/1280:
  - desktop sidebar and hub layout match `A-Hub`;
  - mobile list nav matches `A-Mobile-Hub`;
  - one nav surface only: no duplicate nav between 768 and 991 (the ACC-001 regression class);
  - «Вийти» visible at all three widths;
  - «Налаштування» not focusable by Tab and announces as disabled;
  - the Telegram link opens in a new tab with `rel="noopener"`;
  - the theme placeholder is not operable by mouse or keyboard.
- **Hub cards with three synthetic accounts:**
  - no orders / no address / no favorites → truthful empty states;
  - a hidden-status latest order → behaves exactly like the ACC-007 order list;
  - a visible-status order → its ACC-007 label shows;
  - the favorites count equals the header counter.
- **`account/edit` at 390/768/1280:**
  - matches the boards, with the D1 email behaviour;
  - all ACC-009 positive and negative name/Telegram/Viber cases give the same results and messages as before the patch;
  - empty and filled save round-trips persist;
  - service keys (`bs_first15_pending`, `bs_np_v1`) are preserved per the ACC-008 fixture;
  - the success confirmation renders.
- **AGENTS.md UI checks:**
  - long-content edge cases: a 32-character name, a long email;
  - hover, focus and active states;
  - no unexplained `!important`, `setTimeout`, `position:absolute/fixed` or magic pixel values.
- **Platform and hygiene:**
  - PHP 8.0.30-compatible;
  - no mysqlnd-only APIs (`get_result`, `fetch_all`);
  - no new console errors on account pages;
  - Tier 1 smoke URLs return 200.
- **Report contents:** the executor's report includes:
  - the exact patch SHA-256;
  - per-target before/after hashes or `absent` states;
  - the backup path;
  - the stdout contract;
  - rollback facts;
  - the exact locally checked owner-run PowerShell packet and its SHA-256, reviewed together with the patch in one integrated independent Claude review (subscription CLI, per `docs/BOOSTER_AUTOMATIC_PEER_REVIEW_20261014.md`).

## 8. QA / smoke test (owner, after each deploy)
1. Hard-refresh. Log in, open «Особистий кабінет» on desktop and phone. Check that the nav has one copy, that «Вийти» works, and that «Допомога» opens the bot.
2. Check that the hub cards show your real data or honest empty states, never sample names or numbers.
3. *(WP2)* Open «Особисті дані», change and save, reopen. Clear the optional fields, save, reopen.
4. *(WP2)* Enter a Latin name. You should see the same error as today and the form should not save.
5. *(WP2)* This also covers the responsive check deferred from ACC-008: phone, tablet and desktop widths on the profile save flow.
6. Check the Tier 1 smoke URLs.

## 9. Rollback note
- One patch per WP, each with its own rollback.
- Every patch:
  - verifies all targets and its backup before changing anything;
  - backs up to `_patch_backups/<patch>-<ts>/`;
  - records the per-target pre/post SHA-256, or `absent` for created files;
  - on rollback, restores existing files and removes only files it created, then clears the template cache.
- The CSS cache-bust token is restored from the backup copy of the referencing file.
- No DB change, so no SQL rollback.
- Rollback order: WP2, then WP1.

## 10. Recommended status after execution (Claude writes Notion)
- `RD-16` is In progress from handoff until the owner QA-passes WP2.
- Done after WP2 owner QA. That also closes the ACC-008 deferred responsive item.
- `RD-ACCOUNT` subtask 1 is mirrored on the dashboard.
