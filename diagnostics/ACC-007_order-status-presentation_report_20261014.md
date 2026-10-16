# Codex Report — ACC-007: customer order status presentation

Date: 2026-10-14
Revision: 1
Gate: **G1 — WAIT_INDEPENDENT_REVIEW_AND_OWNER_DEPLOY_QA**

## Outcome and scope

WP1 is prepared for independent review. The source-only runner replaces administrative status output with the accepted customer presentation in account order list/detail/history, removes raw history comments from the query and view data, hides draft status 15, restores neutral historical statuses, and implements real ten-row history pagination. It displays recorded date and time with an explicit unknown-date fallback. There is no database migration or business-data write.

Authority: owner `go` after the G0 source intake; `handoffs/handoff_ACC-007-ACC-008-ACC-009_account-data-foundation_20261013.md` §§2,4,6. Scope matches ACC-007; ACC-008 and ACC-009 have not started. No Git command, commit/push, deploy, Notion or dashboard write was performed. Independent review, owner deployment and production QA remain pending.

Patch: `patches/ACC-007_order-status-presentation_20261014.php`.

SHA-256: `73d292554bf3f49cca5c13c10551a6b1124390ad527ce71f41012d8ef1fd2221`.

## Verified source and metadata

- Owner fresh drop: `booster-debug-files.tar.gz`, 74/74 regular members, SHA-256 `582dcba5debd9989212b1315ea89c112d2ec13fbaf532696df9f5bd9d449e682`. WP0 intake validated paths, hashes and drift against the October 9 backup: 73 files identical, only shared DS CSS changed; that current CSS is preserved. See the separate WP0 source-intake report/evidence.
- Owner read-only PHP preflight returned `done=ok`, PHP **8.0.30**, prefix `ocp5_`, active Ukrainian language **4 / uk-ua**, `adminEvhenii` present, all 16 status definitions unchanged from the audit, no active overrides for the three views. Its privacy-safe result is `ACC-007_order-status-presentation_evidence_20261014/owner_preflight.json`; no customer rows, comments, payment identifiers or credentials are included.
- Aggregate evidence: 564 history rows; 33 with missing definition 2, four with status 0, one hidden draft 15, no missing timestamp/reference in the preflight aggregate. The missing-date UI is still required for future/legacy inconsistent records and synthetic edge cases.
- One relevant active hook: `analytics_ps_enhanced_measurement`, trigger `catalog/view/account/order_info/before`. Owner cPanel hashes matched retained source bytes: controller `7273ac3733c3233cc52c86f07aa2a4714adb575d570e901808c61d3498eff00e`; model `eea58d1e247a16c2cc4bc1e61b8a5028e41dc7ed557e9c652d74382f7984b4b4`. These are verified dependencies, not files to change.
- Bootstrap/startup hashes from the owner also matched the narrowly extracted source. The runner verifies ten read-only dependency hashes, plus current status/language/timezone/theme/event metadata again at application time. Unknown events, drifted dependencies, active view overrides or registry/timezone changes fail before any target write.

### Time basis and its limits

The framework initially selects UTC and synchronizes its first DB connection to `+00:00`. The preflight's `emulated_openCart_connection` reports **that early phase only**. It is not the final storefront timezone. The verified `catalog/controller/startup/setting.php:56–60` later applies `config_timezone=Europe/Kyiv` to PHP and issues the matching DB `SET time_zone` using `date('P')`. The owner's initial DB connection was SYSTEM/EEST, offset 10800 seconds.

Normal shop writes therefore record **Kyiv local wall time** in DATETIME. The helper strictly validates and formats that stored wall time as `d.m.Y H:i:s`; it does not add three hours as if the value were UTC. Null/zero/invalid dates, invalid spring DST gaps and an unexpected configured timezone get `Дату зміни не зафіксовано`.

DATETIME has no per-row UTC offset or writer identity. This package cannot prove the absolute instant of an ambiguous autumn DST hour or every historical external writer's timezone. It preserves the recorded wall time, does not invent UTC conversion, and does not rewrite history.

## Root causes and implementation

Verified source root causes: `catalog/controller/account/order.php:66–81` outputs the administrative name and creation date; `:449–458` retrieves unbounded history and forwards `nl2br(raw comment)` plus the raw result while discarding time. `catalog/model/account/order.php:274–302` joins status definitions with a language filter that drops missing IDs, has no history LIMIT, and counts a different row set. `account/order_list.twig:30–32` prints the raw name and creation date. These are fixed at their source.

The customer map is keyed by `(language_id, order_status_id)`, independently of DB names, notify flags and processing/complete settings. Shared helpers apply the same visibility and owner/store restrictions to list, detail, history and corresponding counts. Current status 0/nonpositive orders keep their existing exclusion; historical 0, NULL, missing 2 and unknown positive statuses remain neutral. Draft 15 is excluded from orders and history before pagination/counting.

History selects only `order_history_id`, `order_status_id`, `date_added`, sorts `date_added ASC, order_history_id ASC`, and uses SQL LIMIT. The controller supplies offset/limit 10 and constructs a fresh allowlisted view array; raw history comment/notify/admin name is never selected or forwarded. Negative pages and extreme integer pages are bounded without arithmetic becoming a float.

For list/detail current-status time, a correlated query checks the **latest appended history ID**. Its timestamp is used only when that row's status matches the current order status. An older occurrence of a recurring status is insufficient if the latest record disagrees; no history, latest disagreement or an undated latest entry means unknown. `order.date_modified` and creation time are never substituted. History entries are recorded observations; the table has no separate flag proving that each entry changed a previous state.

The existing buyer-supplied **order-level** comment remains unchanged. The approved privacy policy concerns technical **history** comments; no new reader, editor or display of a raw history comment is added. Other product/subscription model methods, the PAY-003 credit link/context and existing auth/token logic are preserved.

### Accepted map

| ID | Customer label | Group / colour | Visible |
|---|---|---|---|
| 1 | В обробці | in progress / blue | yes |
| 3 | Передано в доставку | in transit / blue | yes |
| 5 | Отримано | completed / green | yes |
| 7 | Очікує отримання | in transit / blue | yes |
| 10 | Потрібна увага | cancelled/problem / amber | yes |
| 12 | Повернено | cancelled/problem / amber | yes |
| 13 | Повернення коштів | cancelled/problem / amber | yes |
| 14 | Термін оплати минув | cancelled/problem / amber | yes |
| 15 | No badge or message | hidden | no |
| 17 | Підтвердьте оплату частинами | in progress / amber | yes |
| 18 | Оплата частинами — перевірка магазину | in progress / blue | yes |
| 19 | Оплату частинами підтверджено | in progress / blue | yes |
| 21 | Оплату частинами повернено | cancelled/problem / amber | yes |
| 22 | Оплату частинами не підтверджено | cancelled/problem / amber | yes |
| 23 | Передзамовлення | in progress / blue | yes |
| 24 | Скасовано | cancelled/problem / red | yes |
| 0 / NULL / missing 2 / unknown | Статус уточнюється | neutral | history remains visible |

Purpose-written public messages are in the decoded payload/helper. Status 19 explicitly says finance confirmation does not mean the order was received; 13 describes the recorded refund status without promising settlement; 22 does not imply irreversible cancellation of the entire order.

### Functional markup and CSS discipline

The existing `.bs-badge` (`boostershop-ds.css:134–144`) supplies type, padding, radius and uppercase. The finite helper colour enum uses existing semantic DS tokens in a local `style` value: blue, green, amber, red, neutral. No arbitrary DB/request value enters that attribute. There is no generic blue/red badge modifier in the current stylesheet; adding one would edit protected shared CSS. This package uses the existing base class and token values without changing any shared selector, token file or cache-bust URL.

Read-only override-history search found `CAT-004-SD-7_rare-pack-listing-badge_20260918.php` and `RD-26_preorder-badge-rules_20261009.php` using badge variants. Their product-specific variants are preserved. No `!important`, new selector override, layout pixels, timers or positioning rule is added. Visual redesign remains deferred to RD account work; stock responsive tables/controls stay in use.

## Hosting targets — exactly seven

| Path | Change |
|---|---|
| `catalog/model/account/order.php` | Shared visibility/ownership/store scope, truthful recorded current time, safe history query/count and real pagination |
| `catalog/controller/account/order.php` | Customer map, time formatting, safe public messages, real history offset and bounded pages |
| `catalog/view/template/account/order_list.twig` | Status badge and recorded status-date column |
| `catalog/view/template/account/order_info.twig` | Current status/time/public message; preserve credit control and scripts |
| `catalog/view/template/account/order_history.twig` | Public-message/status-time headers and status badges |
| `extension/ukrainian/catalog/language/uk-ua/account/order.php` | Four functional Ukrainian labels |
| New `system/library/acc007_order_status.php` | Shared map, visibility predicate and strict Kyiv date formatter |

The six existing target files are hash- and anchor-checked, with original line endings preserved. The new helper is explicitly required through `DIR_SYSTEM`; no assumption about camel-case autoload conversion or new Twig partial is needed. It is not a maintenance script or a database writer.

No checkout/register/auth/address/NP/payment/mail/direct-status-writer, administrative status, stock/reward, processing/complete setting, GA4 source, DS CSS, SEO/feed/schema or wishlist file is changed. Nine unrelated account-order model methods and the public history auth wrapper are byte-identical. The locked ACC-006 patch remains SHA-256 `01363fafc078fe7d3c247cf3e3ed1410c5733d54fdc6a55b40fd1c849b40a995`.

## Runner safety and dry run

The runner checks CLI/PHP 8, target paths/files, exact source hashes/anchor counts, the absent new-helper path, candidate hashes, ten dependencies and bounded DB metadata before writing. Plain mysqli `query()`/`fetch_assoc()` with integer DB_PORT avoids mysqlnd-only APIs. Only SHOW/SELECT metadata queries are issued; no SET, INSERT, UPDATE, DELETE or DDL is performed.

It backs up six originals/seven candidates plus a hash receipt to `_patch_backups/ACC-007_order-status-presentation_20261014-<UTC>-<nonce>/`, lints PHP candidates, installs with atomic writes, lints installed PHP and self-deletes on success. Injected write/post-lint failures restored original bytes and removed the new helper. A complete repeat reports `already_applied=yes` without another backup; partial markers or changed installed content fail closed. Rollback rejects tampered backups and later target edits before restoring any file.

Synthetic dry-run key output:

```text
source_gate=7_targets_ok
db_preflight=read_only_ok
time_basis=verified_shop_Kyiv_wall_time
db_mutations=none
dry_run=ok
done=ok
```

This is fixture execution, not a claim that the production runner has been executed.

## Local verification and limits

| Evidence | Result |
|---|---|
| Backend | **51 checks passed**: native PHP helper/model/controller; actual emitted SQL executed against independent typed SQLite fixtures; ownership/store/guest/draft filters, neutral missing IDs, count/row agreement, deterministic ties, ten-row pages, current-time uncertainty, private comment suppression and date/DST edge cases |
| Runner | **40 checks passed**: dry-run/apply/self-delete/repeat/rollback, exact target hashes and untouched dependencies, missing/drifted target/dependency, new-helper collision, partial markers, anchor gate, schema/index/language/registry/timezone/theme/event gates, redacted connection/query failures, injected write/lint restore, tampering/traversal/later edits, exact CRLF preservation/rollback |
| Twig | Three views compile/render in native Twig **3.28.0 ArrayLoader**; real controller safe history data also renders without raw comments; no new include |
| GA4 | Verified actual adapter's anchor counts preserved: product link 0→0, reorder link 0→0, footer 1→1. The first two anchors were already absent in the current modified view; this package does not repair them. Injected footer view compiles/renders, and both retained GA4 script calls execute locally |
| Embedded JS | Order detail's one existing inline script remains byte-identical, including cart/reorder and history pagination handlers |
| UI | **69 checks passed**, headless Chrome at **390/1024/1440**; long status/product/comment fixtures, colours and credit-19 semantics, no document overflow, stock horizontal table scrolling, link hover/focus/active, actual retained history JS page2/back with synthetic token, unknown-state/date display, zero JS runtime errors |
| Syntax | Runner plus four target PHP files pass `php -l` on local **8.3.30**; source uses PHP 8.0-compatible constructs. Host **8.0.30** apply/runtime remains owner evidence |
| Added diff signatures | Zero `!important`, `setTimeout`, absolute/fixed positioning, magic pixel values, new Twig includes, `innerHTML` or `eval` |

Visual inspection covered mobile detail and desktop list screenshots. Browser fixtures use current owner-drop CSS and a minimal page shell; jQuery is retained October 12 tooling. Synthetic pages do not prove a production account, complete site shell, bank integration, MySQL performance or GA4 DebugView delivery. Historical external time attribution and status/direct-writer consistency remain outside this source-only package. No production browser/DB test was run by Codex.

Evidence folder: `diagnostics/ACC-007_order-status-presentation_evidence_20261014/` contains `owner_preflight.json`, `ga4_owner_hashes.json`, `payload.json`, `source.diff`, `manifest.json`, `backend-tests.json`, `runner-tests.json`, `render.json`, `ui-tests.json`. The payload is decoded for independent review, so reading the embedded base64 is optional. Raw source, hosting copies, rendered HTML and screenshots remain outside the repo. Focused tooling is in `work/acc007-wp1-20261014/`; fixture doubles/fault injection are never embedded in the production runner.

## Owner run command — after independent review and deploy approval

Upload **only** `ACC-007_order-status-presentation_20261014.php` to `~/public_html`. No DB dump is required for this source-only package. The following single block runs the patch, then clears ordinary and nested compiled Twig cache using `DIR_CACHE`:

```bash
cd ~/public_html || exit
php ACC-007_order-status-presentation_20261014.php && php -r '
require "config.php";
$base = realpath(DIR_CACHE);
if ($base === false) { fwrite(STDERR, "cache_path_missing\n"); exit(1); }
foreach (glob($base . "/cache.*") ?: [] as $f) {
    if (is_file($f) && !is_link($f) && !unlink($f)) { fwrite(STDERR, "cache_clear_failed\n"); exit(1); }
}
$tpl = realpath($base . "/template");
if ($tpl !== false) {
    if (strpos($tpl . "/", $base . "/") !== 0 || is_link($base . "/template")) { fwrite(STDERR, "unsafe_template_cache\n"); exit(1); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tpl, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && !$f->isLink() && substr($f->getFilename(), -4) === ".php" && !unlink($f->getPathname())) { fwrite(STDERR, "template_cache_clear_failed\n"); exit(1); }
    }
}
echo "cache_cleared=ok\n";
'
```

Expected: `source_gate=7_targets_ok`, `db_preflight=read_only_ok`, printed backup path, **seven changed files (six existing, one new helper)**, four target PHP lint successes, `done=ok`, then `cache_cleared=ok`. Keep the exact backup path, hard-refresh the account pages. Any `done=error` or cache error: stop and provide the bounded terminal output; do not bypass a gate or edit a table to make it pass. An optional owner dry run is `php ACC-007_order-status-presentation_20261014.php --dry-run`; it makes no target/backup write and retains the runner.

## Rollback

Re-upload this exact runner because success self-deletes it. In `~/public_html`, run:

```bash
php ACC-007_order-status-presentation_20261014.php --rollback=_patch_backups/<exact printed ACC-007 backup directory>
```

Then perform the same cache-refresh command above. Rollback restores the six exact originals and removes only the unchanged new helper. It does not write/delete order/history data. No rollback SQL is needed. If a target has changed since application, rollback refuses to overwrite that newer work and requires a bounded review.

## Required owner production QA

1. Log in normally; open order list and one detail. Approved labels agree between list, current status and history. Status 19 is blue, says finance confirmation, and is not labelled completed/received. Status 5 is green. Hidden 15 has no order/history row or empty badge; counts/page totals exclude it.
2. Check an existing history containing missing ID 2: show a neutral record rather than silently dropping it. Technical/provider/free-form history comments never appear, for notify 0 or 1. Buyer order-level comment remains the existing field.
3. Verify a recorded row's date **and time** against the admin history, without an added three-hour shift. List/detail status date agrees with its matching latest recorded state; it is not order creation/modification time. Where no latest record attests the current status, show the explicit unknown-date message. Do not change real statuses solely to create QA fixtures.
4. On an order with more than ten visible histories, page1/page2/back show ten rows per full page, correct final remainder and visible total; rows are timestamp-ordered with stable history-ID ties. List pagination also excludes drafts. Empty histories are valid.
5. Log out: protected detail/history remains unavailable; log in again normally. A customer cannot read another customer's order/history by changing order_id, and current-store ownership is retained. Use owner-controlled test accounts, without sending account data/tokens to an agent.
6. At 390/1024/1440 (or matching phone/tablet/desktop widths), check the longest credit label and a real long product/order comment; tables scroll locally, controls work with hover/focus/pressed state. The existing credit-status link, reorder/cart controls and GA4 account-detail integration behave as before.
7. Account navigation, login/logout, profile and saved-address forms work; checkout starts. Verify **all eight Tier 1 URLs**: `https://boostershop.website/`, `/catalog/Pokemon`, `/catalog/Pokemon/Pokemon-booster-box`, `/product/One-Piece-Boosters-OP-11`, `/product/FIG-CHARM-001`, `/information/oplata-i-dostavka`, `index.php?route=checkout/cart`, `index.php?route=checkout/checkout`.
8. During the bounded QA window, check for new errors in `/home2/boosters/ocartdata/storage/logs/`. Source/local checks do not replace production runtime, cache, MySQL and owner account QA.

## Next gate

Stop at **G1**. Independent Claude review is next, followed by owner deploy and QA; Codex waits for the owner's next `go`. Do not start ACC-008 in parallel under the existing authorization. Task status remains subject to Claude/owner closure; no status was changed.
