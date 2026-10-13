# Handoff — ACC-004 + ACC-006: customer data audit, then wishlist restore

Date: 2026-10-13
Executor: Codex · model=Sol · effort/thinking=xhigh — owner chose Codex for this dialog (the discovery-heavy signal would otherwise point to Claude Code; override recorded, not re-argued). Sol/xhigh because the work touches order status, customer/auth data and DB by subject and is architecturally ambiguous; no small model on this.
Parent / related: RD-16, RD-17, RD-18 (scope notes 2026-10-13), ACC-002, ACC-003, RD-29. Notion: ACC-004 https://app.notion.com/p/3f86bf20bdb4812cade0f152df4d56cc · ACC-006 https://app.notion.com/p/3f86bf20bdb481cc9e95fb7751143503

## 1. Task IDs and work packages
One dialog, three work packages, strictly sequential, **one patch file per package, never bundled**. Packages 1 and 2 are read-only. Stop at each gate and wait for the owner.

| WP | Task | Type | Output |
|---|---|---|---|
| 1 | ACC-004 | read-only analysis | `diagnostics/ACC-004_customer-data-architecture_report_20261013.md` |
| 2 | ACC-006 | read-only diagnosis | `diagnostics/ACC-006_wishlist-diagnosis_report_20261013.md` |
| 3 | ACC-006 | patch (only after owner "go") | `patches/ACC-006_wishlist-restore_<YYYYMMDD>.php` + report |

Gate A: after WP1 and WP2 reports → stop, summarise in 5 lines, wait.
Gate B: WP3 starts only after the owner approves the WP2 diagnosis and any DB step in it.

## 2. Context
The buyer account is being redesigned (light variant A approved). Three needs came out of it: (a) a «Улюблене» (wishlist) section — stock OpenCart wishlist is believed to be hidden or removed, state unknown; (b) new optional profile fields (patronymic, Telegram, Viber) and Ukrainian-only name validation, which raise the question where customer data lives and how the admin shows it; (c) order statuses must show with the date of each change, including credit orders whose technical statuses must not leak to the customer. ACC-004 audits the data architecture; ACC-006 restores the wishlist. Visual work is a **later** phase and is not part of this handoff.

## 3. Goal
- WP1: a factual, evidence-backed picture of customer / address / order / status data and every workaround around it, plus a proposed target model and a ready-to-register implementation task.
- WP2: establish whether the wishlist was hidden or removed, and the smallest safe way to restore it.
- WP3: working wishlist backend (add / remove / list / counter / guest→login merge) with minimal functional controls. Final look is applied later in the RD visual phase.

## 4. What to do

### WP1 — ACC-004 (read-only)
Evidence: newest `backup-*.tar.gz` in the repo root by filename timestamp (currently `backup-9.24.2026_16-35-03_boosters.tar.gz`). Extract only the files / SQL needed, to a temporary directory **outside the repository**. If the order data or a file you need is missing or older than the question, say so and ask the owner for a fresh drop (`tar -czf booster-debug-files.tar.gz <paths>`). Verify DB prefix (believed `ocp5_`), language IDs and storage format against the backup before any claim.

Cover:
1. Customer entity: tables, custom fields, address storage (incl. the NP address form from ACC-002 and the hidden postcode stub), duplicates between customer, address and order records.
2. Account flow: registration, login, profile edit, address book, password / email change; which stock controllers are overridden, events registered (`ocp5_event`), OCMOD / VQMod, theme overrides in DB.
3. Orders: how customer data is copied into the order (snapshot vs reference); `order_history` (status + `date_added` + `notify` + comment); what `account/order.info` and the order list actually show the customer today.
4. **Customer-visible status register** (core deliverable): every row of `order_status` (id, name), for each: who writes it (checkout core, `cheque`, `bank_transfer`, Hutko, monobank ПЧ, `pumb_credit`, Nova Poshta flows, manual admin), with what `notify` value, whether the customer can see it (account, emails), and a proposed customer-facing label + colour group (in progress / in transit / completed / cancelled-problem / hidden). Credit statuses ("Розстрочка — …", five rows previously seen) need an explicit mapping or a hide decision.
   Previously observed facts to re-verify, not to trust: `catalog/controller/mail/order.php` `history()` switches on the status **name string**, so renaming a status in admin silently breaks its email copy → the proposal must map labels in a separate layer and must NOT rename admin statuses; payment modules call `addHistory(..., false)` for automatic transitions; the monobank cabinet confirm did not write a status back into OpenCart history; for ПЧ the OpenCart status is only a local label.
5. Workarounds register: table of every hack / override touching customer, account or order data — path, reason, risk, who depends on it.
6. Admin usability: what is hard or impossible to see in the admin customer card and order card today, and what the new fields (patronymic, Telegram, Viber) would need to appear there.
7. Inputs from RD-16: three optional fields (patronymic, Telegram `@username`, Viber `+380XXXXXXXXX`), Ukrainian-only name validation rule (`А–Щ Ь Ю Я І Ї Є Ґ`, lowercase equivalents, apostrophe `' ʼ ’`, hyphen, space). Recommend storage (custom fields vs columns) with trade-offs.

Report must contain: current state; problems with evidence (file / table / query); risks; target data model; change list ordered by risk with DB impact and rollback per item; **draft text of the NEW implementation task** (title, scope, risk class, rollback, acceptance) — you do not create it; Claude registers it after the owner reads the report; open owner questions.
Privacy: counts and structure only. No customer names, phones, emails or addresses in the report or in chat. No secrets.

### WP2 — ACC-006 diagnosis (read-only)
Establish from the backup (likely, not confirmed — verify every path):
- stock wishlist code: `catalog/controller/account/wishlist.php`, `catalog/model/account/wishlist.php`, table `<prefix>customer_wishlist`, language files, `account/wishlist.twig` in the active theme;
- how it was disabled or removed: template markup, header link / counter, product card and product page buttons (`product/thumb.twig`, `product/product.twig`), events, theme DB override, deleted files, dropped table, routes;
- guest behaviour (session wishlist and merge on login) as implemented in this OpenCart version;
- what the account nav and account hub render today for it (ACC-001 menu work, RD-16 shell);
- smallest restore path, with exact files, DB impact, rollback, blast radius on product card and product page templates (shared, high traffic).
Also state whether the wishlist data model needs anything from ACC-004 findings.

### WP3 — ACC-006 patch (after Gate B)
Restore working add / remove / list / counter / guest→login merge using the stock mechanism. Controls may be plain functional markup using existing `bs-*` classes; **do not design the final look** — the owner will choose 3 button variants for card and page and a block name later. Account «Улюблене» page must render inside the existing account shell and list products with name, price, availability and a remove control; out-of-stock products stay listed with an inactive buy control.

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed, schema / JSON-LD.
- Checkout, payment (Hutko, monobank ПЧ, `pumb_credit`), Checkbox / fiscalization, Nova Poshta logic, order status values and names in admin, `catalog/controller/mail/order.php`.
- Login / registration token logic fixed in ACC-003; the ACC-002 NP address form and the hidden postcode stub.
- `boostershop-ds.css` and any shared DS token file, unless WP3 genuinely needs a rule: then root cause, override history grep and `!important` justification are mandatory (AGENTS.md → UI/CSS patch discipline), and the cache-bust token is read, never hardcoded.
- Any DB change in WP1/WP2 (none allowed) and in WP3 without explicit owner approval + rollback SQL in the patch header.
- Notion and `ROADMAP_FLOW` / `ROADMAP_TASKS`: you do not write them. State needed status changes at the end and stop.
- Secrets: `.env.review`, `scripts/.env`, `client_secret.json`.
- The Claude Design boards and the account redesign (RD-16/17/18) implementation — visual phase is later.

## 6. Likely files / areas (likely, not confirmed — verify against the backup)
Customer / account: `catalog/controller/account/*`, `catalog/model/account/*`, `catalog/view/template/account/*`, `admin/controller/customer/*`, `admin/model/customer/*`; orders: `catalog/controller/account/order.php`, `catalog/model/account/order.php`, `catalog/view/template/account/order_*.twig`, `admin/controller/sale/order.php`, `catalog/controller/mail/order.php`; wishlist: see WP2; tables `customer`, `customer_group`, `address`, `custom_field*`, `order`, `order_history`, `order_status`, `customer_wishlist`, `event`, `theme`.

## 7. Acceptance criteria
WP1 report is accepted when:
- [ ] DB prefix, language IDs and backup filename are stated with evidence.
- [ ] Status register covers 100% of `order_status` rows with writer, notify, visibility, proposed label and colour group.
- [ ] Workarounds register lists path + reason + risk for each item found; "none found" is stated explicitly where true.
- [ ] Storage recommendation for the three new fields is given with admin-visibility consequences.
- [ ] The draft implementation task is present and has scope, risk, rollback, acceptance.
- [ ] Zero personal data and zero secrets in the file.

WP2 report is accepted when it states hidden vs removed with file / table evidence, the restore path with exact files, DB impact (or "none"), rollback, and a smoke list.

WP3 is accepted when (owner QA, production, no staging):
- [ ] Logged-in customer adds a product → it appears in the account «Улюблене» list; remove works; counter equals the list length.
- [ ] Guest adds a product → login prompt or session item; after login the item is in the account list.
- [ ] Out-of-stock product is listed with an inactive buy control.
- [ ] Tier 1 smoke URLs return 200 (Home, `/catalog/Pokemon`, nested category, `/product/One-Piece-Boosters-OP-11`, `/product/FIG-CHARM-001`, `/information/oplata-i-dostavka`, cart, checkout entry) and checkout starts normally.
- [ ] No new PHP error in the OpenCart log (`/home2/boosters/ocartdata/storage/logs/`) during the QA run.

## 8. QA / smoke test
Owner runs WP3 on production: the acceptance list above, plus a second browser for the guest case. If the patch changes any Twig, include the compiled-template / cache refresh step and an owner hard refresh; if a Twig change is not visible, inspect the theme DB override before patching again and preserve rollback evidence. Checkout is not modified; `bs-checkout-smoke` is required only if WP3 ends up touching a checkout template (it should not).

## 9. Rollback
WP1/WP2: nothing to roll back (read-only). WP3: runner backs up every touched file to `_patch_backups/<patch>-<ts>/` before write; rollback = restore from that folder + refresh caches. If a table is created or altered: separate DB dump first (cPanel → Backup → MySQL database, OpenCart DB) and rollback SQL in the patch header; no DB step without owner approval.

## 10. Recommended status after execution
Claude writes Notion; you only report.
- ACC-004: In progress → stays until the owner has read the report and the follow-up task is registered; Done on owner closure.
- ACC-006: In progress after WP2; Done only after WP3 owner QA **and** the later visual step (button variants + block name) is closed or split off by the owner.

## Technical constraints (all packages)
- Production PHP 8.0.30: no 8.1+ syntax (no `never`, enums, readonly, first-class callable syntax).
- Production mysqli has **no mysqlnd**: `get_result()` and `fetch_all()` are fatal. Prepared-statement rows must be read via `result_metadata()` + `bind_result()` (reference pattern: `bs_stmt_rows()` in `patches/LEGAL-002_offer_mono_pumb_archive_v4_20260724.php`). Plain `$db->query()` is fine.
- Patch conventions from AGENTS.md apply to WP3: file-exists check, anchor count pre-check, backup, `php -l` with restore-on-fail, idempotent marker in the touched file, self-delete, cache-bust token read not hardcoded. Naming `patches/ACC-006_<slug>_<YYYYMMDD>.php`.
- Per-render form tokens are fragile (ACC-003): any new form state must assume a second render can happen at any time.
- OpenCart 4 `ArrayLoader`: do not use `{% include %}` for a new external Twig partial without proving the active loader supports it.
- If anything cannot be confirmed in the actual project files, write "the executor must verify against actual project files" and stop, do not guess.
- Executor never commits, pushes or deploys. Delivery of WP3: patch into `patches/`, owner uploads to `~/public_html` and runs `php <patch>.php`; end your reply with: what it does (1–2 sentences), local path, run command, one terminal block.
