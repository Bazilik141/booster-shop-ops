# Handoff — RD-17: account addresses (list + form) in light variant A

Date: 2026-10-26
Executor: Codex · model=Terra · effort=high. The owner assigned Codex. Codex built ACC-002 (Nova Poshta address form), ACC-008 (address-save guard) and the RD-16 shell, so the protected logic this restyle must preserve is already in its context. Effort is high because the address form sits on the guarded customer-data and NP save path.
Notion: RD-17 https://app.notion.com/p/3706bf20bdb481ad9674c74099d07f77 (owner scope 2026-10-13, plus the ACC-008 carry-over note of 2026-10-25). Dashboard umbrella: `RD-ACCOUNT`, subtask 2.
Sequence: run RD-17 to completion **before** RD-18 (`handoffs/handoff_RD-18_account-orders_20261026.md`). The two tasks share `boostershop-ds.css`, its cache-bust token in the shared header, and the shell. One writer at a time.

## 1. Task ID
`RD-17`. RD-20 (registration) later reuses this address form.

## 2. Context
RD-16 is Done (2026-10-26). It delivered:
- the account shell: `catalog/controller/account/shell.php` + `catalog/view/template/account/shell.twig`, mechanism (a), explicit per-page opt-in;
- the scoped CSS prefix `.bs-account-page`;
- restyled hub and personal-data pages.

Address pages do **not** use the shell yet; they still render the legacy column-left menu.

Live address logic that this task must preserve:
- ACC-002: Nova Poshta form with lookups, courier mode and `bs_np_v1` metadata;
- ACC-008: server-side `custom_field` preservation guard and the address success confirmation (r16);
- ACC-009 N2: unified name policy on `address_form.twig` and the `account/address` controller.

Design source:
- `design/account-light-A/`: `A-Addresses`, `A-Addresses-Open`, `A-Mobile-Addresses`, `A-Mobile-Addresses-Open`;
- the README there for how to read the boards. The boards show a simplified form; the live NP form is the functional source.

Full RD-16 history and mechanism: `diagnostics/RD-16_wp0_source-request_report_20261025.md`.

There is no staging environment. Every deployed patch lands on production.

## 3. Goal
The address book uses the RD-16 shell and the approved light-A look:
- address cards with clear actions;
- a compact add/edit form that is collapsed until needed.

Every save, validation, NP and data behaviour stays exactly as it is live.

## 4. What to change

### WP0 — fresh evidence and form-flow decision (stop at G0)
- Request one owner-run narrow pull (`CODEX_WORKFLOW.md` → «Narrow file pulls»). Claude chat composes the owner command and you check it. Cover:
  - `catalog/controller/account/address.php`;
  - `catalog/view/template/account/address_list.twig`, `address_form.twig` and any other `account/address*` view (verify the names);
  - the NP form assets and JS bound to the address form (ACC-002, ACC-009 `booster-account-universal.js`);
  - `shell.php` / `shell.twig`;
  - the Ukrainian `account/address` language file;
  - `boostershop-ds.css` and `common/header.twig` (cache-bust reference).

  Diff the pull against the RD-16 post-release hashes and the ACC-008/ACC-009 release records. Report any drift.
- Check for a theme DB override of these templates (`ocp5_theme`).
- **Report these facts and decide at G0:**
  1. **Form flow.** The boards show the form inline on the list page, collapsed by default:
     - «Додати адресу» or «Редагувати» opens it;
     - «Скасувати» collapses it.

     Verify whether the live add/edit form is a separate route or page. Recommend one option with its trade-off:
     - (a) keep the separate route and make the list page match the board, with «Додати адресу» linking to the form page;
     - (b) render the form inline on the list page, collapsed by default.

     Option (b) must not create a second writer, a new save endpoint, or a duplicate NP widget initialization.
  2. **«Зробити основною».** Verify whether a live action already sets the default address outside the form.
     - If one exists, wire the card button to it unchanged.
     - If not, **do not add a new writer**: show the default choice only inside the form, as today, and report the gap as a follow-up.
  3. **Patronymic on the address.** The board shows an optional patronymic field and a «Підставлено з особистих даних» prefill hint. Verify whether the live address form already has a recipient patronymic field and a profile prefill.
     - Restyle only what exists.
     - Adding a stored field, or a new prefill writer, is out of scope here; report it.
  4. **Field order.** Target order: Імʼя, Прізвище, По батькові (if present), Місто, Відділення/поштомат, Адресна доставка, Країна, Регіон, Основна адреса.
     - If the NP form requires a different order (for example region before city lookup), keep the order that works and report why.
     - Never reorder in a way that breaks NP lookups.

### WP1 — address list + form (one patch, unless G0 shows two independently deployable parts)
- **Shell:** opt both address views into the RD-16 shell (explicit child call, as RD-16 did for edit). Use the active item «Адреси доставки» and the mobile chip nav. Stop rendering the legacy column-left menu on these pages only, as RD-16 did at its callers. Do not edit shared `column_left`, the account module or their CSS.
- **List:** one card per address.
  - Card content: full name (patronymic if stored), city + NP branch/postomat or courier address, country and region.
  - «Основна» badge on the default address.
  - Actions: Редагувати · Зробити основною (per G0 item 2) · Видалити.
  - Empty state when there are no addresses.
  - Keep the existing delete flow and its confirmation; restyle only.
- **Form:** card form per the boards (labels above inputs, 48px controls).
  - «Зберегти адресу» primary, blue, not green.
  - «Скасувати» secondary.
  - Collapse behaviour per G0 item 1.
- **Name fields:**
  - ACC-009 validation, normalization and messages stay unchanged; restyle only;
  - do not add the board's `pattern` attribute or hint text (pre-ACC-009).
- **NP widget:**
  - lookups, courier toggle, hidden postcode stub and stored `bs_np_v1` stay unchanged;
  - restyle only through scoped classes;
  - no change to NP JS logic or request payloads.
- **ACC-008 carry-over** (owner decision 2026-10-25, presentation only):
  - replace the cart-styled delete/warning toasts on the address pages with the account notice style;
  - make the one-use success message unable to show stale after an interrupted redirect, if achievable in presentation; otherwise report it;
  - give the native address alert an accessible role and live region.

  The ACC-008 guard, writers, commit order and NP validation are untouched.
- **CSS:** under `.bs-account-page` with component classes, existing DS tokens only. Follow AGENTS.md «UI/CSS patch discipline».
- **Cache-bust:** patch convention 8 (read the token, never hardcode it). The DS cache token lives in the protected shared header; see owner decision H1 below.

## 5. Do not touch
- **Protected zones:** `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment (Hutko, monobank ПЧ, `pumb_credit`), Checkbox/fiscalization, Merchant feed, schema/JSON-LD.
- **Nova Poshta logic:** NP API calls, NP lookups, counterparty or shipment creation, and the NP JS logic and payloads.
- **Customer data logic:**
  - ACC-008 guard and writer bodies in `catalog/controller/account/address.php` and `edit.php`;
  - `catalog/model/account/*`;
  - `system/library/booster_account_profile.php`;
  - admin customer and address files;
  - custom-field metadata. No DB writes of any kind.
- **ACC-009 rules:** name rules in their JS and PHP sources.
- **Checkout:** checkout address forms and `checkout/*` controllers. They share NP and name logic but are out of scope.
- **Shared header:** `common/header.twig`, except the DS cache-token value if H1 is granted.
- **Shell:** `shell.php` / `shell.twig`, except adding the addresses-active state if the existing contract cannot express it. Report any change; do not alter hub or edit output.
- **Other pages:** orders (RD-18), wishlist (ACC-006), login/register (RD-19/20), and no RD-29 work.

## 6. Likely files / areas
Likely, not confirmed. **The executor must verify against the actual pulled files.**
- `catalog/controller/account/address.php` (shell opt-in and presentation data only)
- `catalog/view/template/account/address_list.twig`, `address_form.twig`
- `extension/ukrainian/catalog/language/uk-ua/account/address.php` (new labels only)
- `catalog/view/stylesheet/boostershop-ds.css` and the header cache token

## 7. Acceptance criteria
- **`index.php?route=account/address` (logged in) at 390/768/1280:**
  - layout matches `A-Addresses` / `A-Mobile-Addresses`;
  - exactly one nav surface: shell sidebar on desktop, chip nav on mobile, no legacy column-left menu;
  - «Адреси доставки» marked `aria-current="page"`.
- **Cards with synthetic addresses:**
  - 0 addresses → empty state and a visible add action;
  - 1 default address;
  - 3 addresses, one of them courier-mode and one with a 32-character name;
  - all card fields come from stored data, with no placeholder text;
  - «Основна» only on the real default.
- **Form:**
  - add, edit and set-default (per G0) save and reopen with identical stored values;
  - NP branch, postomat and courier modes still validate and save as before the patch;
  - `bs_np_v1` and unknown keys are preserved per the ACC-008 fixture;
  - collapse/open behaviour per G0 item 1 works by keyboard;
  - focus moves into the form when it opens.
- **Names:** all ACC-009 positive and negative cases give the same results and messages as before the patch.
- **Notices:**
  - delete, warning and success notices use the account style, not the cart toast;
  - a notice announces through a live region;
  - an interrupted redirect does not replay a stale success notice (or the report explains why this cannot be done in presentation).
- **AGENTS.md UI checks:**
  - three breakpoints;
  - long content;
  - hover, focus and active states;
  - no unexplained `!important`, `setTimeout`, `position:absolute/fixed` or magic pixel values.
- **Platform and hygiene:**
  - PHP 8.0.30-compatible;
  - no mysqlnd-only APIs;
  - no new console errors;
  - hub and personal-data pages byte-identical in output except the shared CSS token;
  - Tier 1 smoke URLs return 200.
- **Report contents:**
  - exact patch SHA-256;
  - per-target before/after hashes or `absent`;
  - backup path;
  - stdout contract;
  - rollback facts;
  - the exact locally checked owner-run PowerShell packet and its SHA-256, reviewed together with the patch in one integrated independent Claude review (subscription CLI, `docs/BOOSTER_AUTOMATIC_PEER_REVIEW_20261014.md`).

## 8. QA / smoke test (owner, after deploy)
1. Hard-refresh. Open «Адреси доставки» on desktop and phone. Check that the menu appears once and that every card shows your real addresses.
2. Add a new branch address. Save, reopen it, then delete it.
3. Edit an existing address: change the branch, save, reopen.
4. Make a different address the main one (per G0), then switch it back.
5. Enter a Latin name. You should see the same error as today and the address should not save.
6. Start a checkout up to the delivery step. The saved address appears as before. Do not place an order.
7. Check the Tier 1 smoke URLs.

## 9. Rollback note
- Every patch:
  - verifies all targets and its backup before changing anything;
  - backs up to `_patch_backups/<patch>-<ts>/`;
  - records the per-target pre/post SHA-256, or `absent` for created files;
  - on rollback, restores existing files and removes only files it created, then clears the template cache.
- The DS cache token is restored from the backup copy of the header.
- No DB change, so no SQL rollback.
- Source rollback does not undo address data saved after deploy. That data is written by the unchanged ACC-008 path, so no data rollback is needed.

## 10. Recommended status after execution (Claude writes Notion)
- RD-17 is In progress from handoff until owner QA of the last WP.
- Done after owner QA. That also closes the ACC-008 carry-over items, or records any that were reported as not achievable.
- Mirror `RD-ACCOUNT` subtask 2 on the dashboard.

## Owner decisions
- **H1 — header DS cache-token exception.** Requested 2026-10-26 for RD-17 and RD-18: allow each WP to change only the existing `boostershop-ds.css?v=` token value in `common/header.twig` (convention 8).
  - If the owner granted it, record the grant in the report and do not ask again per WP.
  - If not recorded, ask at the ready package, as RD-16 did.
- **G0 items 1–4** above.
