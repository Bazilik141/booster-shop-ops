# Handoff — PAY-006: add PUMB's new source IP to the callback allowlist (TEST + PROD)

Date: 2026-10-16
Executor: Claude Code · model=Opus · effort/thinking=high — owner assignment 2026-10-16; the task is small but sits in two risky zones (payment, DB), so no small model.
Risk zones: payment · order status (callback moves order state) · DB. Rollback and a focused post-deploy check are mandatory. No staging: the patch lands on production.

## 1. Task ID

`PAY-006`. Related: `PAY-002` (PUMB module), `PAY-003` (callback CIDR patch, prod callback registration review).

## 2. Context

PUMB support notice received 2026-10-16 (owner relayed the text):

- PUMB adds a **new** integration IP: `3.123.129.140`.
- The existing range `194.44.66.16/28` **stays unchanged**.
- The list is identical for STAGE (test) and PROD.
- The new IP must be **added, not substituted**.
- Partners that restrict access by IP must update their lists **by 2026-10-25**.

Our side: the `pumb_credit` module restricts the **inbound bank callback** by source IP. Two independent settings in `ocp5_setting`, comma-separated, trimmed per entry:

- `payment_pumb_credit_callback_ips` — PROD route `pumb_credit.callback`
- `payment_pumb_credit_test_callback_ips` — TEST route `pumb_credit.callbackTest`

Matcher (`allowedIp()` + `ipMatches()` in `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`): exact IPv4/IPv6 and CIDR, `REMOTE_ADDR` only, forwarded headers ignored. Behaviour that matters here:

- **Empty list = allow all (fail-open).**
- A malformed entry never matches but does not disable valid ones; the list fails closed only when no entry is valid (corrected after `diagnostics/PAY-006_pumb-callback-ip-state_20261016.md` §1).

Last known values (stale, from `diagnostics/PAY-003_prod-callback-registration_review_20260909.md`, 2026-09-09): PROD list = the 16 addresses `194.44.66.16` … `194.44.66.31` enumerated; TEST list **not recorded**; `test_mode=1`, `public=0`, `status=1`. The module has since gone through further work — treat every value above as unverified until read from the newest backup.

Our outbound calls to PUMB (`api_base`, OAuth) are not part of this notice; nothing in the notice asks us to change them.

## 3. Goal

Both callback allowlists accept `3.123.129.140` in addition to everything they accept today, deployed well before 2026-10-25 (the notice does not say from which date PUMB starts using the new IP, so do not plan around the deadline).

## 4. What to change

Two deliverables, in this order. Different files, different rollback.

**A. Read-only diagnostic** → `diagnostics/PAY-006_pumb-callback-ip-state_20261016.md`. From the newest owner-provided cPanel backup (`backup-10.15.2026_13-09-55_boosters.tar.gz` or newer — extract only the needed files/rows to a temp location, never into the repo; do not unpack the whole archive):

1. Exact stored values of both `*_callback_ips` settings (row key, `store_id`, `serialized` flag, raw value).
2. `test_mode`, `public`, `status`, `api_base` — to state whether PROD cutover has happened since 2026-09-09. Do not print `callback_user`/`callback_password`/OAuth values; report only empty/non-empty.
3. SHA-256 of the live `pumb_credit.php` controller versus `951dcf43…41dd` (2026-09-09), and confirmation that `ipMatches()` is present.
4. Any other IP gate on the callback path: `.htaccess` rules, other code referencing `194.44.66`, anything that would reject `3.123.129.140` before the controller runs. Hosting-level firewall/WAF is not visible from a backup — say so explicitly instead of guessing.
5. Whether OpenCart caches settings (so the patch knows if a cache clear is needed), and how the admin PUMB settings form reads/saves these two fields — a later admin save must not revert the new value.

**B. Patch** `patches/PAY-006_pumb-callback-ip-add_20261016.php`, one patch file. It appends `3.123.129.140` to each of the two settings:

- Preserve the existing value byte-for-byte and append `,3.123.129.140` (separator style as already stored).
- Validate the IP with `filter_var(..., FILTER_VALIDATE_IP)`.
- Idempotent: if the IP is already an exact entry in a list, leave that list untouched (`already_applied=yes` when both are).
- **Refuse to write a list that is empty** and report it instead: appending to an empty list would turn allow-all into "only the new IP" and lock the bank's `194.44.66.x` callbacks out. Handle each list independently; the other list may still be written.
- Do not collapse the 16 enumerated addresses into a CIDR, do not reorder, do not normalize. The only change is the appended entry.
- Print before/after of both values (IP lists are not secrets), plus `settings_changed=yes|no`.
- Refuse with a clear error if a setting row is missing, duplicated, or `serialized` unexpectedly.
- DB credentials: read from the server's own `config.php` at run time; the executor does not open credential files locally.
- Rollback SQL in the patch header (convention 6) restoring the captured previous values; the backup directory also stores them.

DB write requires the owner's approval at deployment (convention 6): state in the patch report that approval is pending and that the owner's run command is the approval.

## 5. Do not touch

- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`
- checkout, payment methods list, Hutko, Checkbox / fiscalization, Nova Poshta
- Merchant feed, schema / JSON-LD
- Any PUMB setting other than the two IP lists: `api_base`, `oauth_*`, `test_mode`, `public`, `status`, `terms`, `callback_user`, `callback_password`, `test_callback_user`, `test_callback_password`
- The controller code and the Twig/JS of the module — the deployed matcher already supports an exact IP; no code change is needed or authorized
- Basic Auth logic, order-status mapping, the monobank module, CRM / order-sync
- Existing `194.44.66.*` entries (no removal, no rewrite)
- No new patch for anything discovered along the way — report it in the diagnostic and stop

## 6. Likely files / areas

Likely, not confirmed — the executor must verify against actual project files and the newest backup:

- `ocp5_setting` rows with `code='payment_pumb_credit'`, keys above (table prefix `ocp5_` to be confirmed from the backup)
- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` (read-only)
- `extension/pumb_credit/admin/**` settings form (read-only)
- Precedent for a settings-only patch: `patches/PAY-002_admin-status-settings-preserve_20260728.php`
- Reference: `diagnostics/PAY-003_pumb-callback-cidr_report_20260901.md`, `diagnostics/PAY-003_prod-callback-registration_review_20260909.md` (§C1 the PROD list; §C2 the one-slot endpoint settings)

## 7. Acceptance criteria

Patch (local, fixture-based — no production access):

1. Fixture with two non-empty lists → both end as `<old>,3.123.129.140`, old part identical byte-for-byte.
2. Second run → `already_applied=yes`, no write.
3. Fixture with one empty list → that list untouched and reported, the other written.
4. Missing/duplicate row, invalid IP constant → refuses before any write.
5. Rollback restores both original values exactly.
6. `php -l` clean; self-delete after success; backup in `_patch_backups/PAY-006_pumb-callback-ip-add_<ts>/`.

Production, after the owner runs it (owner or executor reading back the output):

7. Output shows `done=ok`, `settings_changed=yes`, before/after for both keys, `self_delete=ok`.
8. Admin → PUMB settings shows `3.123.129.140` in both the TEST and the PROD IP fields, with the previous entries still present.

Not provable by us: an actual callback from `3.123.129.140`. State that gap in the report; do not claim it verified.

## 8. QA / smoke test

Focused, not the full `bs-checkout-smoke`: nothing in checkout, cart or payment selection changes.

1. Run output matches criterion 7.
2. Admin read-back matches criterion 8.
3. Site and checkout page still load (no regression from the cache clear, if one was needed).
4. Watch-only after deploy: once PUMB starts using the new IP, the access log should show `3.123.129.140` on the `pumb_credit.callback` / `callbackTest` route with a 2xx answer. A 403/401 from that IP means the list was not applied or an upstream gate blocks it (see diagnostic item 4).

## 9. Rollback note

Run the `rollback.php` printed by the patch (or the rollback SQL in its header): restores both settings to the captured values. Clear the OpenCart cache if the diagnostic found settings are cached. Rolling back removes the new IP — callbacks from `3.123.129.140` would then be rejected, so roll back only if the patch broke callbacks from the existing range.

## 10. Recommended status after execution

- Handoff delivered → `In progress` (executor assigned).
- Patch reviewed by Claude and cleared → still `In progress` until the owner has run it.
- `Done` only after: production output per criterion 7, admin read-back per criterion 8, owner confirmation. If PUMB has not yet used the new IP, close as `Done` with a watch-only note for criterion 8's log check (SOP gate for payment tasks: owner manual QA).
- Executor does not write Notion. `ROADMAP_FLOW` row for PAY-006 already exists (created with the task); a status change on it is handed back to Claude.

## Delivery path

Patch file → `patches/`; the owner uploads it to `~/public_html` and runs `php PAY-006_pumb-callback-ip-add_20261016.php`. The executor does not commit, push, deploy or run anything on production. One patch author for this task: if the owner reassigns, the current executor stops first.

## Evidence added 2026-10-16 (Claude chat, read-only extraction from `backup-10.15.2026_13-09-55_boosters.tar.gz`)

Covers deliverable A items 1–3; items 4–5 remain for the executor. Rows from `ocp5_setting`, `code='payment_pumb_credit'`, `store_id=0`, `serialized=0`:

| setting_id | key | value |
|---|---|---|
| 11146 | `payment_pumb_credit_callback_ips` | `194.44.66.16,…,194.44.66.31` — 16 enumerated addresses, comma without spaces, non-empty |
| 11149 | `payment_pumb_credit_test_callback_ips` | identical 16 addresses, non-empty |
| 11135 | `payment_pumb_credit_status` | `1` |
| 11137 | `payment_pumb_credit_public` | `1` |
| 11139 | `payment_pumb_credit_api_base` | `https://api.dts.fuib.com/ext-oic/galadriel/v1` (the TEST endpoint) |
| 11152 | `payment_pumb_credit_terms` | `[3,4,5]` |

- No `payment_pumb_credit_test_mode` row exists in this backup → `isTestMode()` is falsy.
- Live controller SHA-256 = `951dcf43a4f4c2712b1f1a124fbac08ed80b57879a5f9bc91ab5cd9bf80a41dd` (unchanged since 2026-09-09); `ipMatches()` present.
- Both lists non-empty, so the patch precondition holds; the real append target is `…,194.44.66.31` → `…,194.44.66.31,3.123.129.140`.
- Since 2026-09-09 the module went from `public=0` to `public=1`: callbacks now move real orders. This raises the cost of a bad write, not the scope.
- Out of scope, report only: `public=1` together with TEST endpoints and no `test_mode` row. Do not change.
