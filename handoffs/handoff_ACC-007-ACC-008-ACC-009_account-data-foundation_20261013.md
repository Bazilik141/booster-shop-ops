# Handoff — ACC-007 + ACC-008 + ACC-009: account data foundation

Date: 2026-10-13
Executor: Codex · model=Sol · effort/thinking=xhigh. Codex wrote the ACC-004 audit these tasks come from, so it keeps the source context. Sol/xhigh because the work touches order status, customer/CRM data and a DB metadata step: risky, multi-file, no small model.
Basis: `diagnostics/ACC-004_customer-data-architecture_report_20261013.md` (reviewed: Review OK). Notion: ACC-007 https://app.notion.com/p/3f86bf20bdb481baaf56e4d219252e3b · ACC-008 https://app.notion.com/p/3f86bf20bdb4813aa1cec1f5c85cbfbd · ACC-009 https://app.notion.com/p/3f86bf20bdb481ae91a5e6e3326077ab. The Notion pages hold the scope; this handoff adds execution order, gates and constraints.

## 1. Work packages
One dialog, strictly sequential, **one patch file per package, never bundled**. Stop at every gate and wait for the owner.

| WP | Task | Output |
|---|---|---|
| 0 | all | Fresh-evidence request: exact file list for the owner's `tar` drop (see §3) |
| 1 | ACC-007 | `patches/ACC-007_<slug>_<YYYYMMDD>.php` + `diagnostics/ACC-007_<slug>_report_<YYYYMMDD>.md` |
| 2 | ACC-008 | `patches/ACC-008_<slug>_<YYYYMMDD>.php` + report |
| 3a | ACC-009 | read-only preflight report (incl. Nova Poshta apostrophe check) |
| 3b | ACC-009 | source patch (validation, profile/admin UI) |
| 3c | ACC-009 | separate DB-metadata patch (custom-field rows) |

Gates:
- **G0:** after WP0 → owner provides the drop.
- **G1:** after WP1 delivered → independent Claude review → owner deploy + QA. WP2 may start in parallel only after the owner says so.
- **G2:** after WP2 delivered → review → owner deploy + QA. **WP3 does not start before ACC-008 is live and QA-passed.**
- **G3:** after WP3a → owner approves; 3b and 3c each need their own review; 3c needs a fresh DB dump first and explicit owner `go`.

## 2. Owner decisions already taken (2026-10-13) — do not re-ask
- Status map from ACC-004 §4 accepted: hide draft 15; status 19 never shown as completed/delivered; historical missing status 2 shown neutrally; admin status names and IDs are never renamed.
- Customer sees only purpose-written public messages, never raw history comments.
- Date **and** time, Europe/Kyiv; verify server/DB timezone before converting.
- New fields: profile and admin only; not on registration or checkout.
- Legacy names that fail the new rule stay as they are until edited; historical orders are never rewritten.
- DB metadata for the fields is a separate package with a dump; payment/direct-writer status consistency is a separate future task (not in this handoff).
- Telegram: after trim, `@` + 5–32 of `A–Z a–z 0–9 _` (case-insensitive); input without `@` accepted and stored as `@username`; `t.me/…` not accepted; syntax only.
- Names (last/first/patronymic): trim; collapse repeated spaces; normalize `'` `ʼ` `’` to ASCII `'` for storage; apostrophe, hyphen, space only between two letters, one at a time; letters `А–Щ Ь Ю Я І Ї Є Ґ` + lowercase; reject Latin, digits, `Ы Э Ё Ъ`; first/last 1–32, patronymic optional ≤32 after normalization; case not changed. **Before implementing, verify how the Nova Poshta API and checkout/NP flows handle ASCII `'` in names (WP3a) — not verified yet.**

## 3. WP0 — fresh evidence
ACC-004 used `backup-10.9.2026_22-22-10_boosters.tar.gz` (October 9). Before WP1 list the exact paths each package needs and give the owner one `tar -czf booster-debug-files.tar.gz <paths>` command to run in `~/public_html` (or the relevant root). Diff the drop against the backup and state drift. Extract to a temp directory **outside the repository**. Bounded DB metadata only (status/custom-field/event/theme rows, aggregates) — no customer rows in any report.

## 4. Scope per package
- **WP1 ACC-007:** Notion page scope. Customer presentation map keyed by `(language_id, order_status_id)`; account order list/detail/history with recorded change time, deterministic order (timestamp + history ID), one visibility policy for rows **and** counts, real pagination (SQL `LIMIT`), neutral fallback for missing IDs, explicit unknown-date message; never present `order.date_modified` as a transition date. Read-only presentation; no DB write.
- **WP2 ACC-008:** Notion page scope. Server-side reload + allowlisted merge of `custom_field` JSON in storefront profile/address saves and admin customer/address writers; preserve `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename` and unknown keys; safe-fail on undecodable non-empty JSON; no bulk rewrite, no DDL. Synthetic round-trip fixture evidence is mandatory.
- **WP3 ACC-009:** Notion page scope plus §2 rules. Include the admin list/autocomplete JSON check (`adminEvhenii/model/customer/customer.php:317`).

## 5. Do not touch
- `catalog/controller/mail/order.php`, payment modules (Hutko, monobank ПЧ, `pumb_credit`), Checkbox/fiscalization, NP logic, order status values/names and processing/complete settings, direct-SQL status writers.
- Checkout and registration forms (fields must not appear there), ACC-003 login/register token logic, ACC-002 NP address form and hidden postcode stub, ACC-006 wishlist files.
- Sitemap, robots, redirects, canonical, `.htaccess`, Merchant feed, schema/JSON-LD.
- `boostershop-ds.css` and DS token files unless a rule is genuinely needed — then AGENTS.md UI/CSS discipline applies in full, cache-bust token read, never hardcoded.
- Any DB write outside WP3c. Notion and `ROADMAP_TASKS`: you do not write them; state needed status changes and stop.
- Secrets: `.env.review`, `scripts/.env`, `client_secret.json`.
- Visual redesign (RD-16/17/18): functional markup with existing `bs-*` classes only.

## 6. Acceptance
- **ACC-007:** all 16 status IDs map to approved label/group/visibility; 15 hidden; 19 not green/completed; no raw comment reaches the customer; history shows date+time in deterministic order; counts equal visible rows; pagination correct; missing ID / missing timestamp give truthful fallback; admin statuses, mail, stock/payment logic unchanged.
- **ACC-008:** storefront and admin edits preserve `bs_first15_pending`, NP metadata and unknown keys; clearing affects only the chosen field; corrupt non-empty JSON safe-fails; covered by synthetic round-trip evidence.
- **ACC-009:** fields round-trip in profile and admin customer card; absence valid; client and server rules identical to §2; legacy names usable until edited; future order snapshots show stored values, old orders unchanged; recipient patronymic separate; fields absent from registration and checkout.
- All: PHP 8.0 compatible, no mysqlnd-only APIs, zero personal data and secrets in reports, owner production QA incl. Tier 1 URLs, account/auth/address flows, checkout start, no new errors in `/home2/boosters/ocartdata/storage/logs/`. UI changes: 3 breakpoints, long content, hover/focus/active.

## 7. Rollback
Every patch backs up touched files to `_patch_backups/<patch>-<ts>/`; rollback = restore + template cache refresh. WP2: source rollback does not recover data overwritten after deploy — the report must say so and require a fresh DB dump before the owner runs it. WP3c: DB dump first; rollback SQL in the patch header removes exactly the inserted metadata rows by recorded ID; saved contact JSON is preserved.

## 8. Recommended status after execution (Claude writes Notion)
ACC-007 / ACC-008 / ACC-009: In progress while their package is open; Done only after owner production QA.

## Technical constraints
- Production PHP 8.0.30; mysqli **without mysqlnd** (`get_result()`/`fetch_all()` fatal; use `result_metadata()` + `bind_result()`, reference `bs_stmt_rows()` in `patches/LEGAL-002_offer_mono_pumb_archive_v4_20260724.php`).
- DB prefix `ocp5_`, Ukrainian language ID 4, admin directory `adminEvhenii` — re-verify in the fresh drop.
- Patch conventions C1–C7 from AGENTS.md; idempotent marker in the touched file; naming `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php`.
- Per-render tokens are fragile (ACC-003); OpenCart 4 `ArrayLoader`: no new `{% include %}` partial without proof.
- If something cannot be confirmed in actual files: write "the executor must verify against actual project files" and stop.
- No commit, push or deploy. End each package reply with: what it does (1–2 sentences), local path, run command, one terminal block.
