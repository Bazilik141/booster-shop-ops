# ACC-006 — wishlist GA4 success-path correction

Date: 2026-10-15
Revision: 2 (post-review report/test-text correction; application patch unchanged)
Executor: Codex. Owner explicitly assigned implementation in the original dialogue; account WP2/3 and photo replacement are assigned elsewhere. No commit/push, deployment, settings change, Notion/dashboard write or production action has been performed.
Runner: `patches/ACC-006_wishlist-ga4_20261015.php`.
Runner SHA-256: `c3a5175476d8c9e7933fa84a1cedb563d44bbd2861b72a12b29569ad73ba3de4`.

## Outcome and review boundary

The four-file correction supplies native PS Enhanced Measurement with one `add_to_wishlist` event after a confirmed **new** wishlist membership. It covers the accepted custom product/card buttons independently of page-specific dataset availability. Repeated additions, removal, state refresh, invalid requests and denied cookie policy produce no event. Optional analytics failures do not undo a successful wishlist operation.

Actual independent design review round 1 returned changes; revision 2 received **Design OK** through actual Claude Opus 5.5/high, saved subscription CLI. Both complete verdicts are retained. The final CODE/composition verdict is a separate artifact and does not establish production GA4 receipt. Use its full actual response and the post-review unchanged-input disposition, not this report alone, for source acceptance. The owner retains dry-run, deploy, settings and production-QA gates.

## Root cause and exact source

The accepted diagnosis `ACC-006_wishlist-ga4-diagnosis_report_20261015.md` established two independent blockers: published `ga4_tracking.add_to_wishlist=false`, and no analytics call in the current successful custom wishlist JS flow. Native click interception applies only to `[data-ps-track-event]`; existing PS replacements search obsolete stock submit/formaction anchors. Current product/thumb custom controls do not match those anchors. Enabling the setting alone is insufficient.

Fresh owner archive: `ACC-006-ga4-current_20261015.tar.gz`, 55,101 bytes, SHA-256 `a64b222389bf1181da4bb16436ff9b46963746ac176f3344835604ea67baddc0`. All ten requested regular files were accepted, no extras/duplicates/links/traversal. All ten match their retained corresponding accepted source/candidate/public bytes. Source was extracted outside the repository; archive instructions/code were not executed. `ACC-006_wishlist-ga4_evidence_20261015/source-intake.json` records exact identities.

Six bounded core dependencies were read from the October 9 backup, explicitly **historical**, not called fresh. The runner requires their exact host hashes plus six fresh read-only dependencies before any write. A mismatch is a stop for fresh evidence, not permission to relax a hash. No config secrets, DB rows, private GA4 metrics or account data were read into this package/review.

## Four changes, applied in this order

| File | Change |
| --- | --- |
| `catalog/view/javascript/booster-wishlist.js` | Validate a successful new-add response and call native `ps_dataLayer.pushEventData` once; isolate exceptions from the completed save. |
| `extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php` | Add a marked internal product-to-ecommerce helper, reusing native identifiers/categories/tax/currency. No event registration, tag or response emission. |
| `catalog/controller/account/wishlist.php` | Determine existing customer/store or guest-pending membership before the unchanged persistence call; return `added` and optional server-built payload only after success. |
| `catalog/view/template/common/header.twig` | Add a marker and replace only the wishlist JS version token, read/validated by path prefix. Preserve existing CSS token and all accepted mobile-header/media behavior. |

No CSS, image, product template, wishlist model, payment, checkout, account-field, status or auth-token source is changed. Previously accepted runners remain byte-identical. The pending mobile hero corner request is not silently included; its actual component/source still needs separate evidence.

## Event and data contract

- Guest first pending add counts. Repeated pending or stored membership does not. A pending item surviving into login is also treated as existing to prevent a second guest-to-login event. Remove then re-add counts again. The native model remains unchanged; normal customer/store scope is retained. Login merge itself is not instrumented or executed by this package.
- One item, quantity `1`, decoded public product name. Identifier matches the **actual native transmitted cart convention**: configured field plus native fallback/formatter and numeric conversion only on ID. No blanket JSON_NUMERIC_CHECK on names or other values. The actual unchanged native cart method is compared in fixtures for SKU/model, numeric leading zeroes, ASCII separators, Cyrillic, exponent, large number and zero cases. A native view-item runtime comparison is not claimed; its implementation is available in the complete controller for source review.
- Effective quantity-one catalogue price is `(float)special ?: price` from validated `getProduct`, then native configured tax and currency formatting. It is not cart options/subscriptions or stock quantity. The actual product constructor and getter are supplied for review. Native cart discount calculation has separate inherited semantics; full cart-price parity is not promised.
- When guest prices are hidden, or configured currency conversion is invalid/unavailable, the entire price/value/currency group is omitted. Valid price/value are the same finite nonnegative number. Optional categories use native product-derived modes 0/1; page-context modes 2/3 are omitted because this POST carries no verified category. No coupon, customer identity, email, telephone, token or account URL enters the event payload.
- Caller and helper both honor the module/event setting and the exact header cookie-policy predicate. The pre-bootstrap gtag queue shim is not treated as consent. Consent defaults/tag IDs are untouched. No direct collect endpoint, fallback tag, forced debug/consent, stock click attributes or telemetry retry timer is added.
- An Exception object returned by OpenCart's loader is rejected with `is_array`; thrown Error/TypeError/Exception during optional payload generation is caught as Throwable. Storage/auth/count failures are not swallowed. A thrown browser sink likewise leaves success UI/count intact.

## Local verification

All evidence uses fictional products/accounts/configuration. No production wishlist click or Google collection request occurred.

- **50 backend assertions:** actual changed controllers, actual unchanged wishlist model and retained actual OpenCart loader; fictional DB/product/category/tax/currency services. Covers guest/customer/duplicate/pending transitions, store/customer scope, errors, cookie values, privacy, currency/tax, zero-price/sold-out and native cart-ID parity.
- **12 native template compositions:** actual complete product/thumb Twig passed through native PS view replacements, event flag true/false and options/no-options; no wishlist native tracking attributes appear. Actual changed full header/native header replacement renders correctly for both modes/flags and keeps injection anchors/version reference. These are source/fixture results, not live theme/cache proof.
- **254 real Chrome assertions:** actual native PS header API, unchanged external delegated listener and real jQuery, actual Twig-produced custom buttons and actual PHP-produced response fixtures. gtag/GTM each receive one new-add event across declared product/home/module/category/search/related button fixtures. No duplicate/failed/remove/state event, no Ads conversion or stock interception, no mutation of the native dataset, no UI failure when the sink throws. Focus/hover/rapid click behavior exercised at 390/768/1440. These are button-level integrations, not complete live page-controller runs or a full visual re-QA.
- **Mutation control:** removing the actual new bridge call causes the positive exactly-one-event assertion to fail. Old/new client/server mixes remain functional and safely omit tracking.
- **24 runner assertions:** exact four candidates and originals; read-only dry-run, self-delete, idempotence, arbitrary valid asset tokens, CRLF, missing/drifted sources/dependencies, theme/event/schema gates, forced post-write lint/write failure with full restore, rollback, tampering/later-change/traversal refusal. Fixture-only mysqli double under `php -n`; no real DB writes/connection.
- Syntax: local PHP **8.3.30**, not hosting 8.0.30. Generated PHP is written using PHP 8.0 constructs and no mysqlnd-only APIs. Host execution remains required. JS checked in real Chrome. Changed additions introduce no `!important`, `setTimeout`, `position:absolute/fixed`, magic layout pixels, `innerHTML` or `eval`; existing untouched header/module signatures are not new code.

Preparation failures are retained as local development limitations: incomplete test doubles/fixture rounding/version constants were repaired; a Windows default-text-encoding mistake in runner assembly caused a dry-run anchor refusal **before writes** and was fixed by explicit UTF-8 throughout. The final runner's decoded payload is mechanically compared with the authored JSON and exact candidate hashes. None was a production failure.

Inherited PS identifier formatting can yield an empty ID for Cyrillic (its transliteration result is discarded); the nonempty item name remains available. Synthetic Cyrillic cases also exposed four inherited native iconv notices on local Windows, captured explicitly by the test handler, not presented as new warnings eliminated by this patch. No global vendor identifier rewrite is included. Concurrent independent sessions/tabs and blocked/lost browser transport remain best-effort analytics, not transaction-level exactly-once delivery.

## Owner dry-run gate

Upload the runner to `~/public_html`, then:

```bash
cd ~/public_html && php ACC-006_wishlist-ga4_20261015.php --dry-run
```

Expected: `source_gate=4_targets_ok`, `db_preflight=read_only_ok`, `db_mutations=none`, `dry_run=ok`, `done=ok`. No source/DB write or backup on dry-run. If any hash/override/event/schema/error gate fails, stop and supply the bounded output; do not alter the runner or disable a guard. Wait for the owner's next go before apply.

## Owner apply/settings and QA, after the gate

Apply and cache refresh in one owner-run block:

```bash
cd ~/public_html && php ACC-006_wishlist-ga4_20261015.php && php -r '
require "config.php";
$cacheRoot = realpath(DIR_CACHE);
if ($cacheRoot === false) throw new RuntimeException("cache_root_missing");
$erase = static function ($path) { if (!unlink($path)) throw new RuntimeException("cache_clear_failed"); };
foreach (glob(DIR_CACHE . "cache.*") ?: [] as $file) if (is_file($file) && !is_link($file)) $erase($file);
$templatePath = DIR_CACHE . "template";
if (is_dir($templatePath)) {
    $templateRoot = realpath($templatePath);
    $prefix = $cacheRoot . DIRECTORY_SEPARATOR;
    if ($templateRoot === false || is_link($templatePath) || strpos($templateRoot . DIRECTORY_SEPARATOR, $prefix) !== 0) throw new RuntimeException("unsafe_template_cache");
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($entries as $file) if ($file->isFile() && !$file->isLink() && $file->getExtension() === "php") $erase($file->getPathname());
}
echo "cache_cleared=ok\n";
'
```

Record the printed `_patch_backups/ACC-006_wishlist-ga4_20261015-<ts>-<suffix>/` path. The runner self-deletes after success. No DB dump is required for this source-only runner/read-only metadata check; it neither writes business rows nor changes settings.

Then open the installed PS Enhanced Measurement settings and enable only its wishlist tracking switch. Retained English UI source calls it **Track Add to Wishlist Event**, key `analytics_ps_enhanced_measurement_track_add_to_wishlist`, element `input-add-to-wishlist`; exact current translated UI labels are not claimed live-verified. Preserve other flags, tag/measurement IDs and consent. Save and reload the storefront page. This manual owner setting is required: the patch deliberately honors the currently false flag.

QA:

1. In DebugView/Realtime with analytics permitted, add a genuinely absent item: one `add_to_wishlist`, correct native item ID/name, quantity=1, equal price/value and currency when prices are permitted. No duplicate Ads conversion. A standard historical report alone is not a collection test.
2. Repeat add: no new event. Remove then add: one event. Back/forward/state/removal: no event.
3. Guest pending add and later login: wishlist persists, merge does not create an event. Hidden-price guest receives no price group. Do not manufacture customer/order data for this test.
4. Product, category, search, home/module and related card; desktop/mobile header count, notice/login, keyboard, remove and Back continue working. Test a sold-out item and a long name. Cookie-policy denial means no tracking payload while wishlist works. No new OpenCart errors.
5. Tier 1: home; `/catalog/Pokemon`; `/catalog/Pokemon/Pokemon-booster-box`; `/product/One-Piece-Boosters-OP-11`; a currently valid 3D product from the catalogue (**FIG-CHARM-001 is retired**); `/information/oplata-i-dostavka`; `index.php?route=checkout/cart`; `index.php?route=checkout/checkout`. Open checkout entry only; do not submit/pay.

No real GA4 receipt, host PHP/cache behavior or owner QA is asserted by local checks or review.

## Rollback and future maintenance

Re-upload the same runner (it self-deletes), then use the exact recorded backup path:

```bash
cd ~/public_html && php ACC-006_wishlist-ga4_20261015.php --rollback=_patch_backups/ACC-006_wishlist-ga4_20261015-<ts>-<suffix> && php -r '
require "config.php";
$cacheRoot = realpath(DIR_CACHE);
if ($cacheRoot === false) throw new RuntimeException("cache_root_missing");
$erase = static function ($path) { if (!unlink($path)) throw new RuntimeException("cache_clear_failed"); };
foreach (glob(DIR_CACHE . "cache.*") ?: [] as $file) if (is_file($file) && !is_link($file)) $erase($file);
$templatePath = DIR_CACHE . "template";
if (is_dir($templatePath)) {
    $templateRoot = realpath($templatePath);
    $prefix = $cacheRoot . DIRECTORY_SEPARATOR;
    if ($templateRoot === false || is_link($templatePath) || strpos($templateRoot . DIRECTORY_SEPARATOR, $prefix) !== 0) throw new RuntimeException("unsafe_template_cache");
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($entries as $file) if ($file->isFile() && !$file->isLink() && $file->getExtension() === "php") $erase($file->getPathname());
}
echo "cache_cleared=ok\n";
'
```

Restores exact old JS/PHP/header content, retaining the current unrelated DS cache token. No wishlist rows are removed. Refuses tampered backups or later source edits before restoration. Restore the owner-changed wishlist tracking switch to its prior state if reverting this feature; the runner does not change settings in either direction. Revalidate the marked vendor PS controller helper and native conventions after any module upgrade.

Evidence: `ACC-006_wishlist-ga4_evidence_20261015/`; task work: `work/acc006-ga4-fix-20261015/`. Existing GA4 diagnosis and accepted old runners are not rewritten. WP2/3, photo upload, mobile hero corners, statuses, Git and production gates remain with their assigned owners.

## Post-review disposition

Actual CODE verdict: **Deploy OK; no blockers**, `ACC-006_wishlist-ga4_claude-cli-review-code-01_20261015.md`, actual Opus 5.5/high subscription CLI. Reviewed report/code/test snapshots remain in frozen `inputs-code-01`. This revision changes only report wording/cache guidance and repairs Ukrainian/Cyrillic text in the local test fixture; the runner and all four application candidates are unchanged. The 50 backend and 254 browser assertions pass again with clean Cyrillic/Ukrainian test text. A separate provider call for optional report/test nits is not required by the automatic-review policy.

The cache command now removes compiled PHP recursively under verified `DIR_CACHE/template`, without following links or deleting unrelated guard files. It no longer depends on a top-level-only glob. Retained current Twig source also has `auto_reload=true`; hosting behavior remains owner QA. The reviewer did not execute this report-only command; its bounded synthetic cleanup check is separate local evidence.

Accepted/deferred nonblocking notes: malformed UTF-8 outside the normal utf8mb4 source contract could make the existing JSON reply fail; future hardening is not silently added to the reviewed patch. Brand entity decoding and omission of page-dependent category modes are intentional bounded differences. Enabling the native flag also creates unused native wishlist page datasets; check the existing guest-price policy before enabling, since native PS can put prices in HTML independently of this response. No native PS privacy rewrite, backup-directory web ACL change, factory/login rewrite or module upgrade is included. Revalidate after core/vendor upgrades.
