# Handoff — RD-19 + RD-20: sign-in, password recovery and two-step registration (light variant A)

Date: 2026-10-29
Executor: Claude Code · model=Opus · thinking=high. The owner named Claude Code as implementer in the design brief (2026-10-29). Opus/high because registration and login sit on account creation, the ACC-003 form-token fix, the guarded customer `custom_field` JSON (ACC-008), First15 eligibility and the Nova Poshta address writer, and the executor does not share the earlier Codex context.
Notion: RD-19 https://app.notion.com/p/3706bf20bdb481768f6ef9cd80cbbb9c · RD-20 https://app.notion.com/p/3706bf20bdb481e197cce7b6b2f71fb0
Design brief: `handoffs/handoff_RD-19-RD-20_auth-registration_design-brief_20261029.md` (background; this handoff and the approved spec win where they differ).
Sequence: RD-19 first, then RD-20, one writer at a time. Both share `boostershop-ds.css`, its header cache-bust token and the same templates' language files.

## 0. Execution contract (read first)

- **Owner decision needed before start: where the executor runs.**
  - Local Claude Code in the repository: the standard rules apply. Patch files go to `patches/`, reports to `diagnostics/`. No commit or push.
  - Cloud session: the ACC-006 pattern applies only if the owner grants it for this task in writing. That pattern is: commit and push only to `claude/rd-19-20`, never to `master`, no merge, no force-push. Without that grant, stop after WP0 and ask.
- **Live-source input.** You list the exact files (WP0). Claude chat composes the owner-run narrow pull (`CODEX_WORKFLOW.md` → «Narrow file pulls»). The owner runs it and hands you the archive. Extract it outside the repository. Never commit raw live source. Never reconstruct live files from older reports.
- **Never:** SSH, SCP, server or DB access, the owner key, Notion writes, deployment.
- **Delivery.**
  - Patch files go to `patches/`, one work package per patch file.
  - You provide the patch SHA-256, per-target before/after hashes (`absent` for created files), the backup path, the stdout contract and rollback facts.
  - The owner-run PowerShell packet follows `CODEX_WORKFLOW.md` → «Owner deployment» and must work in Windows PowerShell 5.1.
  - Independent review (Claude chat, `bs-patch-review`) covers the exact patch hash and packet hash together. A changed hash needs a fresh review. Claude Code never approves its own patch.
- **There is no staging environment.** Every deployed patch lands on production.

## 1. Task IDs

- `RD-19`: sign-in, forgotten password, password reset.
- `RD-20`: two-step registration — contacts, then an optional address — and the one-time hub toast.

## 2. Context

The signed-in account area is live in light variant A:
- RD-16: shell, hub, personal data;
- RD-17: addresses;
- RD-18: orders;
- ACC-006: «Обране».

The signed-out pages still look like stock OpenCart: left «Особистий кабінет» menu, small Bootstrap fields, cyan buttons, English reCAPTCHA label, and false copy («купувати в один клік», «отримати пароль електронною поштою»).

**Approved design (owner, 2026-10-29). Read in this order:**
1. `design/account-light-A/rd19-rd20/SPEC.md` — pages, field order, verbatim copy, states, owner decisions.
2. `design/account-light-A/rd19-rd20/screens/*.png` — full-page renders, desktop 1280 and mobile 390.
3. `design/account-light-A/rd19-rd20/preview/*.html` — the same boards as static HTML. Open them in a browser to measure spacing.
4. `design/account-light-A/A-Login.dc.html` … `A-Mobile-Register-Done.dc.html` — the canvas sources. `design/account-light-A/README.md` explains how to read them.

The boards are inline-styled mock code. Take layout, hierarchy, spacing, copy and states from them. Do not copy the markup. Map every hex value to an existing `boostershop-ds.css` token (`design/TOKENS.md`).

**Live facts already on record — verify them, do not assume:**
- **Customer `custom_field` JSON** carries service keys that every save must preserve: `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename`, and unknown keys. Sources: ACC-008, and `diagnostics/ACC-004_customer-data-architecture_report_20261013.md`.
- **First15** eligibility lives in `bs_first15_pending` and `catalog/model/checkout/booster_coupon.php`, per the ACC-004 report.
- **ACC-003** made the login/register form tokens session-stable: `diagnostics/ACC-003_login-register-token-session-stable_report_20260822.md`.
- **ACC-009:**
  - name policy: 3–32 characters, Ukrainian letters, apostrophe or hyphen only between letters;
  - optional patronymic, Telegram and Viber exist in the profile.
- **ACC-002** built the NP-structured account address form. Its handoff notes that the post-registration redirect lands on that form. The phase-0 report records the stored address shape: `diagnostics/ACC-002_account-np-address-phase0_report_20260714.md`.

## 3. Goal

The six pages look like the approved boards on desktop and mobile, with the approved copy. Behaviour changes are limited to these:
- recovery answers the same for every e-mail;
- the registration flow continues to an optional address step and ends on the hub with a one-time toast;
- step 1 takes optional Telegram and Viber;
- step 2 takes the address in separate fields with an optional patronymic, if G0 proves storage supports it.

Everything else stays exactly as it works live. That includes login, the lock counter, reset tokens, customer creation, First15, the NP save path and the form tokens.

## 4. What to change

### WP0 — fresh evidence and decisions (stop at G0)

**Narrow pull.** You list the paths; Claude chat composes the command; the owner runs it. Verify each name — these are expected, not confirmed:
- `catalog/controller/account/login.php`, `forgotten.php`, `register.php`, `success.php`, `account.php`, `address.php`;
- the matching `catalog/view/template/account/*.twig`, including `login.twig`, `forgotten.twig`, the reset view, `register.twig`, `account.twig`, `address_form.twig`;
- the Ukrainian language files for these routes;
- the reCAPTCHA extension template and its language file;
- `catalog/model/account/customer.php`, the login-attempt functions and the `config_login_attempts` setting;
- `catalog/model/checkout/booster_coupon.php` and every place that sets or reads `bs_first15_pending`;
- the NP address form JS and assets from ACC-002 and ACC-009;
- the RD-16 `shell.php` / `shell.twig`;
- the RD-12 toast JS/CSS;
- `common/header.twig` — the account link label and the cache-bust reference only;
- `boostershop-ds.css`;
- any theme DB override of these templates (`ocp5_theme`).

**Diff** the pull against the RD-16/17/18 and ACC-003/008/009 post-release hashes. Report any drift.

**Report at G0, with file/line evidence, and stop for owner decisions where marked:**
1. **Lock.** The live lock rule: number of attempts, lock period, and the exact message it shows. The lock copy uses «через годину» only if the period is one hour.
2. **Forgotten response.** What the live code does for an unknown e-mail. Propose the smallest change that returns the neutral «Перевірте пошту» state for every e-mail (owner decision Q4). The e-mail is still sent only when the account exists.
3. **Reset outcome messages.** Where the «password changed» and «link invalid/used» outcomes render today: the reset page or a redirect to login with a message. Recommend:
   - (a) reuse the live redirect and show the board messages on the login page;
   - (b) show them on the reset page.

   Owner decides.
4. **First15 and `account/register`.** Does a customer created through `account/register` get `bs_first15_pending`, and does the 15% then apply to the first order? Show the code path.
   - Yes → the discount block ships with the owner text.
   - No → **stop and ask the owner**. Do not show the block and do not invent a mechanism.
5. **Register → step 2 flow.** Where the live register success redirects: `account/success`, the address form, or elsewhere. Propose how step 2 is reached and how «Пропустити» lands on the hub, with no new storage. The customer is already created and logged in after step 1.
6. **Address storage (owner instruction).** How the NP module and the account address store courier ("адресна") delivery: `address_1` / `address_2`, `bs_np_v1` fields, delivery type, and where the patronymic goes (`rd13_receiver_middlename` or other).
   - Can Вулиця, Будинок and Квартира be separate inputs that save and round-trip into checkout correctly?
   - Show one saved example per delivery type: відділення, поштомат, кур'єр.
   - If separate inputs need new storage, DDL, or a change to how checkout reads the address, **stop and ask the owner**.
7. **Recipient name.** How step 2 fills the address first and last name from the customer without showing name fields.
8. **Telegram/Viber on register.** How ACC-009 stores profile Telegram and Viber, and whether `account/register` can save them through the same validators and allowlisted merge. No new DB columns.
9. **Hub toast.** A one-shot session flag/message read by the hub, rendered with the RD-12 toast component. State the mechanism.
10. **Logged-in header label.** The live account link label when signed in. Keep the live one; the board's «Кабінет» is a placeholder.

### WP1 — RD-19: login, forgotten, reset (one patch)

- Restyle `account/login`, `account/forgotten` and the reset page per the boards. Opt into the RD-16 scoped prefix only (`.bs-account-page`). Do not use the signed-in shell nav.
- **Login:**
  - card + side card on desktop; stacked on mobile;
  - fields, eye toggle, «Забули пароль?», reCAPTCHA label, help line, error and lock states;
  - copy per SPEC §3.
- **Forgotten:**
  - board layout and copy;
  - the neutral result for every e-mail (G0-2).
- **Reset:**
  - board layout, rule hint and field errors;
  - outcome messages per the owner's G0-3 choice.
- **reCAPTCHA:** label «Підтвердіть, що ви не робот» wherever these forms render it. The widget itself is unchanged.
- **Eye toggle:** a real `<button type="button">` with `aria-label` «Показати пароль» / «Сховати пароль» and `aria-pressed`. Small scoped JS; no change to the form submit flow.
- **Copy:** remove «Зареєстрований клієнт», «Please complete the captcha validation below», «отримати пароль електронною поштою» and «купувати в один клік».
- **Breadcrumb current item:** «Вхід», «Забули пароль?», «Новий пароль». Language-key change only; breadcrumb markup unchanged.

### WP2 — RD-20 step 1: registration (one patch)

- **Layout:** restyle `account/register` per `A-Register` / `A-Mobile-Register`.
- **Field order:** Імʼя, Прізвище, E-mail, Телефон, Telegram (optional), Viber (optional), Пароль.
- **Checkboxes:** newsletter «Підписатися на новини» (optional); consent with a link to the live «Публічна оферта» information page (required). Then reCAPTCHA, «Створити акаунт» and «Вже є акаунт? Увійти».
- **No patronymic** on this step.
- **Validation:** reuse the ACC-009 name, phone, Telegram and Viber validators and messages on the server side. The name hint stays hidden until a forbidden character is typed, as on the A boards.
- **Telegram/Viber save:** through the G0-8 path and the ACC-008 allowlisted merge. Preserve every service key.
- **Discount block:** only after the G0-4 «yes». Text exactly as in SPEC §3. Desktop: in the side card. Mobile: under the H1.
- **States:** all field errors, plus «e-mail already registered» with links to login and forgotten.
- **Keep untouched:** the ACC-003 form-token markup and the customer-creation call.

### WP3 — RD-20 step 2 and hub toast (one patch)

- **Flow:** after a successful step 1, route to the address step per G0-5.
- **Address step layout:** per `A-Register-Address` / `A-Mobile-Register-Address`:
  - progress (step 1 ticked) and the notice «Акаунт створено. Залишилося додати адресу доставки.»;
  - recipient line from the customer name;
  - Область | Місто, then Відділення / поштомат;
  - the «Адресна доставка» block (Вулиця; Будинок | Квартира; По батькові, optional);
  - «Зробити основною адресою», checked;
  - «Зберегти адресу» and «Пропустити, додам пізніше».
- **No «Країна» field:** Україна is fixed by the live form, verify how.
- **Reuse the live RD-17 / ACC-002 NP form logic.** Lookups, delivery-type behaviour and the save path stay as they are. The board sets only the look and order. If the separate street/house/flat inputs failed G0-6, stop; the owner decides the fallback.
- **Patronymic:** save to the store G0-6 identified. Validate with the ACC-009 name policy.
- **After save or skip:** go to the hub (`account/account`) and show the one-time toast:
  - desktop: the RD-12 corner card;
  - mobile: the RD-12 strip;
  - text per SPEC §3, auto-hide after 4 s;
  - never shown again after that page view.
- **No backend changes beyond** the redirect and the one-shot flag agreed at G0.

### All WPs

- **CSS:**
  - scoped classes under the RD-16 prefix; existing tokens only; follow AGENTS.md «UI/CSS patch discipline»;
  - no green on these pages except the unchanged header cart;
  - primary blue, secondary outline/neutral;
  - 48px fields, 44px minimum tap targets, visible focus ring;
  - errors with icon and text, wired by `aria-describedby`.
- **Cache-bust:** convention 8. The header token edit needs the owner's token-only exception at the ready package, as for RD-17/18.
- **Code constraints:** PHP 8.0.30-compatible, no `get_result()` / `fetch_all()`. OpenCart 4 ArrayLoader: no new external `{% include %}` without proof.

## 5. Do not touch

- **Protected zones:** `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment (Hutko, monobank ПЧ, `pumb_credit`), Checkbox/fiscalization, the Merchant feed, schema/JSON-LD.
- **Header and footer markup**, including the logo. The boards show the new canonical logo, but the site logo migration is a separate task. Only the language string of the breadcrumb's current item changes.
- **Auth core:**
  - password hashing;
  - the login-attempt counter and lock logic (copy only);
  - reset-token generation and expiry;
  - the customer-creation model;
  - the ACC-003 form-token mechanism.
- **Customer data:**
  - the ACC-008 allowlisted `custom_field` merge;
  - the service keys `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename`;
  - no DDL, no bulk writes, no direct SQL rollouts.
- **First15:** the coupon/eligibility logic in `booster_coupon.php` and checkout totals.
- **Nova Poshta:** lookups, the delivery-type logic, the ACC-002 writer and how checkout reads a saved address.
- **The RD-16/17/18 pages** and the shell nav. ACC-006 «Обране».
- **The reCAPTCHA widget and its keys:** only its visible label changes.
- **No RD-29 dark-theme work.**

## 6. Likely files / areas

Likely, not confirmed. **The executor must verify against the actual pulled files.**
- **Controllers:** `catalog/controller/account/login.php`, `forgotten.php`, `register.php`, `success.php` / `account.php` (redirect and toast flag only).
- **Templates:** `catalog/view/template/account/login.twig`, `forgotten.twig`, the reset twig, `register.twig`, `account.twig` (toast slot), `address_form.twig` (or a step-2 wrapper).
- **Language files:** Ukrainian files for those routes, plus the reCAPTCHA extension.
- **CSS/JS:** `catalog/view/stylesheet/boostershop-ds.css`, a scoped JS for the eye toggle, the RD-12 toast JS (reuse, do not fork).

## 7. Acceptance criteria

**Visual — at 390, 768 and 1280, compared side by side with `screens/*.png`:**
- the same blocks, order, copy and states;
- no Bootstrap cyan;
- no horizontal scroll at 390.

**Login:**
- correct credentials sign in;
- wrong credentials show «Неправильний e-mail або пароль.» with the e-mail kept;
- after the live attempt limit, the lock message shows and login stays blocked for the live period;
- «Забули пароль?» opens forgotten.

**Forgotten:**
- an existing e-mail and an unknown e-mail both show the identical «Перевірте пошту» state;
- only the existing one receives the e-mail, and it contains a working reset link.

**Reset:**
- the new password works for login, and the old one fails;
- mismatch and rule errors show under the fields;
- a used or expired link shows «Посилання недійсне» with «Надіслати нове посилання».

**Register:**
- a valid submission creates the customer and logs them in;
- Telegram and Viber (when filled) show in the profile and admin exactly as ACC-009 profile values;
- the newsletter flag is saved as live;
- invalid name, phone, password, missing consent and a taken e-mail each show their field message;
- `bs_first15_pending` is set exactly as before this change, verified on a test customer;
- the discount block is shown only if G0-4 confirmed.

**Step 2:**
- «Зберегти адресу» creates one address with the step-1 name as recipient, the chosen Область / Місто / Відділення and — for courier — street, house and flat (patronymic saved if entered);
- that address preselects correctly in a new checkout session and a shipping quote works;
- «Пропустити» creates no address;
- both paths land on the hub with the toast exactly once.

**Hygiene:**
- AGENTS.md UI checks: three breakpoints, long names, hover/focus/active;
- no unexplained `!important`, `setTimeout` (the toast auto-hide is the one justified timer) or magic pixel values;
- no new console errors;
- Tier 1 smoke URLs return 200.

**Report:** the items listed in section 0 «Delivery».

## 8. QA / smoke test (owner, after each deploy)

1. **Login page.** Hard-refresh it on phone and desktop and compare with the screens. Sign in, sign out. Enter a wrong password once and check the message.
2. **Forgotten.** Request a reset for your e-mail and for a made-up one: the page looks the same. Open the link from the e-mail, set a new password, sign in with it.
3. **Register with an address.** Use a test e-mail with Telegram filled in. On step 2 pick an Нова пошта branch, save. You land on the hub with «Акаунт створено». Open «Адреси»: the address is there with your name.
4. **Register without an address (owner requirement).** Use a second test e-mail and press «Пропустити, додам пізніше». You land on the hub with the toast, and «Адреси» is empty.
5. **Checkout with a test customer.** Run `bs-checkout-smoke`. Check that the saved address preselects and that First15 applies to the first order exactly as before.
6. **Check the Tier 1 smoke URLs.**
7. **Clean up.** Delete the test customers in admin afterwards, or keep them as you prefer.

## 9. Rollback note

- **Every patch, before changing anything:**
  - verifies all targets and its backup;
  - backs up to `_patch_backups/<patch>-<ts>/`;
  - records per-target pre/post SHA-256, or `absent` for created files.
- **On rollback:** restore existing files, remove only files the patch created, then clear the template cache.
- **Header cache-bust token:** restored from the backup.
- **No DB schema change.** Customers and addresses created during QA are ordinary data; the owner deletes test accounts in admin.
- **Rollback order:** WP3 → WP2 → WP1.

## 10. Recommended status after execution (Claude chat writes Notion)

- **RD-19:** In progress from WP0; Done after the owner's WP1 production QA.
- **RD-20:** In progress from WP2; Done after the owner's WP3 production QA, including the no-address registration.
- Claude chat updates Notion and the dashboard `ROADMAP_FLOW` mirror in the same session. The executor does not edit the dashboard.
