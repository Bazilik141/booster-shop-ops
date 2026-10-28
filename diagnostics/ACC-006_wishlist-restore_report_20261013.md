# ACC-006 — wishlist restore, WP3

Date: 2026-10-13 (Europe/Kyiv)
Executor: Codex. Authority: owner `го` after WP1/WP2 and the October 13 source intake.
Revision: 3. Revision 2 was independently accepted: Deploy OK with non-blocking notes. Next gate: owner authorization/deployment and production QA. Revision 1 received **Return for changes**. No commit/push, deployment, Notion or dashboard change is claimed. Revision 3 changes only the optional Git block commit message; the patch is byte-identical to revision 2.

## Outcome and scope

Prepared one self-contained runner: `patches/ACC-006_wishlist-restore_20261013.php`.
SHA-256: `01363fafc078fe7d3c247cf3e3ed1410c5733d54fdc6a55b40fd1c849b40a995`.

It restores separate functional wishlist buttons on cards/product pages, a visible header counter/link, and the existing account wishlist page. Add/remove use the stock customer/store/product model; guest choices remain in session and merge through the unchanged ordinary login. Sold-out products remain listed with disabled buy controls; pre-order signals follow the existing card rule. No final visual design is implemented.

Root causes verified before writing: absent card/page/header controls; logged-in add also creating pending guest entries; zero/missing remove ID calling delete-all; tokenless list reload; always-enabled wishlist buy control. Existing cart-specific toast behavior must not handle wishlist actions. Evidence and line references: `diagnostics/ACC-006_wishlist-diagnosis_report_20261013.md`.

The renderer/count now hide unavailable catalogue records without deleting their stored wishlist choices. This avoids GET/render-time cleanup and preserves preferences if a disabled product returns. Ordinary zero-stock records remain visible. This is a bounded safety refinement of the approved diagnosis; there is no data repair/backfill.

## Review corrections — revision 2

The reviewed revision 1 had SHA-256 `6f20278a5710e40e1a4f544d8ba38862dc5220c4781ad6ec2e11bdc0a69ca63e`. The review accepted WP2 diagnosis, did not review WP1, and returned WP3 for the name-encoding defect. This revision addresses all three findings:

| Finding | Correction | Local evidence |
|---|---|---|
| F1, blocking | Remove the additional escape filters on `product.name`, `name` and `heading_title`. Wishlist text/image attributes and the two new aria labels retain stock printing of already entity-encoded catalogue names. URL/token escaping remains. No name/DB rewrite | Native Twig and browser assertions cover `&amp;`, `&quot;`, `&#039;` in text, alt/title and both aria labels at three widths |
| F2 | Tag deliberately public application errors. Other failures show the Ukrainian fallback: `Не вдалося виконати дію. Перевірте з’єднання та повторіть спробу.` Browser exception messages are not displayed | Mocked failed network, rejected redirect and malformed JSON each show the fallback and release busy state; own server errors remain plain text |
| F3 | Change `state` to POST; read the feature token exclusively from the POST body. The existing native `customer_token` URL mechanism is preserved | GET state and body-less POST with query-only feature token are rejected; BFCache restore sends POST with no feature token in the query and updates counters |

The runner still modifies 8 existing files and adds 1 JS asset. No analytics configuration or new event binding is added by these corrections. Preserving the existing GA4/cart adapter anchors does not prove `add_to_wishlist` emission from the restored controls; the review's DebugView check remains unverified and requires separate integration/production evidence.

## Source and target boundary

Source: `ACC-006-current-files-20261013.tar.gz`, 20/20 regular members, SHA-256 `41e702bc7d601574d83b23ebfff4d802f229a645f41ae67a83779e92761c078c`.
Raw source and synthetic hosting fixtures were kept outside the repository. The runner contains exact source hashes, edit anchors and candidate hashes; it does not restore whole shared templates from the October 9 backup.

| Target | Change |
|---|---|
| `catalog/controller/account/wishlist.php` | Positive-ID POST removal, stable feature-token/account validation, guest-only pending adds, pending-item clearing, safe response/count/list URLs, visible-product list/count, sold-out/pre-order availability |
| `catalog/controller/common/header.php` | Session-stable wishlist token, authenticated action URLs, one filtered count query reused by the existing label |
| `catalog/view/template/common/header.twig` | Small functional link/counter, dedicated asset and plain live notice; preserve current CSS token |
| `catalog/view/template/product/thumb.twig` | Separate `type=button` wishlist action for all states, outside cart submission |
| `catalog/view/template/product/product.twig` | Separate action after the main form; gallery, options, sticky cart and payment scripts untouched |
| `catalog/view/template/account/wishlist.twig` | Existing account shell, count in heading; remove the broken legacy inline remove/reload handler |
| `catalog/view/template/account/wishlist_list.twig` | Read-only visible-count wrapper; stock printing of entity-encoded names; positive-product remove button; disabled unavailable buy; original enabled cart button kept exactly for GA4 injection |
| `extension/ukrainian/catalog/language/uk-ua/account/wishlist.php` | Functional page title, empty-state and plain success/error messages |
| New `catalog/view/javascript/booster-wishlist.js` | Scoped queued POST requests, busy/duplicate guards, numeric counter synchronization, authenticated list reload, safe guest-login notice and Ukrainian fallback for browser failures |

The other 12 supplied source dependencies are checked and remain byte-identical: stock wishlist model, login/register/logout, product controllers/model, common JS, account navigation/module and analytics controller. No DS CSS, checkout/payment/NP/mail/status/SEO/feed/schema files are edited. Adding the header counter does not change the header's existing SEO logic.

## Behavior and security

- Stock model enforces customer/store ownership and duplicate protection. New controls submit only the product ID; options/quantity remain the cart's concern.
- Missing/zero/negative/array/malformed/oversized IDs cannot reach delete-all. Removal requires POST, a stable feature token and the existing customer-token validation. Logged-in add requires the same validation; guests use the stable feature token. Login/register token code is unchanged.
- Token is minted only when absent/invalid, not on each render. Two tabs keep the same feature token; another logged-in account's stale customer token is rejected.
- Logged-in add never appends a new guest pending entry, and add/remove clear the same pending ID. Normal logout/login no longer resurrects removed items or transfers logged-in additions to another account.
- Ordinary guest → login merge is exercised through the **actual unchanged login method**. Immediate automatic login after a new registration remains a distinct existing path; no new registration merge is promised or introduced.
- Counts apply the verified catalogue visibility conditions: customer/store, active product, availability date, current-language description and distinct product. Header count uses one SELECT without per-item pricing queries. List uses the existing product model for prices/visibility; changing catalogue visibility in a future task must keep this count aligned.
- Requests are serialized; double clicks are guarded. Wishlist actions never submit/reload the cart. Server messages are inserted as text; login/list URLs must remain on the same origin. Existing view-event scripts retain the stock jQuery HTML reload path.

## Runner safety and DB preflight

Runner-time DB operations are **read-only**; no migration, registry/settings change, status change or business-row write. Future normal user add/remove operations write their own wishlist through the stock model, as intended by the task.

Before target writes the runner:

1. verifies files, source/dependency hashes and exact anchor counts; rejects partial application and a foreign asset;
2. reads the owner's config without printing credentials; connects via mysqli with `(int) DB_PORT`, `query()`/`fetch_assoc()`, without mysqlnd-only APIs;
3. verifies wishlist columns/composite primary key and the single active Ukrainian language;
4. refuses active DB template overrides for the five affected view routes and unknown active view/controller/wildcard events;
5. creates `_patch_backups/ACC-006_wishlist-restore_20261013-<UTC>-<nonce>/` containing original/candidate bytes and a hash receipt; lints candidate PHP before installation;
6. performs atomic writes and post-write PHP lint. Errors restore already-written files when their bytes still belong to this apply; unrelated later changes require owner review;
7. reports `done=ok` and self-deletes after success. Full, verified repeat state reports `already_applied=yes` without changing tokens/files or making a new backup.

Header source hashing normalizes only line endings and the validated existing DS asset token; actual untouched bytes/EOLs and that token are preserved. No asset-token append/hardcoded old-token anchor. New JS has its own fresh versioned reference.

Production DB preflight is implemented but **has not been run by the agent**. A schema/override/event/dependency mismatch fails before source changes and requires a new bounded diagnosis; it does not authorize a DB fix.

## Local verification

Inspect `diagnostics/ACC-006_wishlist-restore_evidence_20261013/manifest.json` and its companions/diffs/screenshots.

| Evidence | Result and practical limit |
|---|---|
| Native controller/model/login checks | **30 passed** with synthetic data and a DB double; includes guest merge, duplicate add, scoped remove, invalid IDs, stale tokens, non-resurrection, inactive buy, pre-order, matching count/list, stable repeated-header token, read-only POST state, GET rejection and query-only feature-token rejection |
| Independent count SQL execution | Actual emitted SELECT executed against synthetic SQLite tables with a fixed NOW function; excludes wrong customer/store/language, disabled/future records and duplicate joins; expected/actual count 4/4. Not a MySQL hosting execution claim |
| Runner checks | **30 passed**: apply/self-delete, target hashes, untouched dependencies, dry-run, repeat, token-preserving rollback, missing/drifted sources, schema/theme/event rejection, foreign asset, partial marker, tampered backup, unsafe rollback path, later owner changes; injected write/post-lint failures restore all touched targets |
| UI/transport checks | **79 passed**, headless Chrome, 390/1024/1440; entity-encoded long names in text/attributes, in-stock/sold-out/pre-order, hover/focus/active/loading, one POST on rapid repeat, correct counters/authenticated reload, guest login link, safe server text, Ukrainian network/redirect/JSON fallback, BFCache state POST without query feature token, no cart submission and zero JS runtime errors |
| Twig | All **5** changed views compile/render with native Twig `ArrayLoader`; no new external include |
| GA4 compatibility | Actual retained adapter's wishlist-list anchor counts preserved (link 2/2, cart button 1/1); injected view renders. Adapter source is retained October 12 evidence, not a newly fetched production integration |
| Protected embedded scripts | Card's 1 and product page's 11 inline scripts remain byte-identical |
| Syntax | Runner and 3 changed PHP files pass local `php -l`; new JS passes `node --check`. Local PHP is **8.3.30**; code avoids PHP 8.1+ constructs/mysqlnd-only APIs. Host PHP **8.0.30** execution remains owner QA |
| New diff signatures | No `!important`, `setTimeout`, absolute/fixed positioning or external Twig includes. Header SVG's 16px dimensions match the adjacent existing icon convention; no new layout pixel rules |

Visual inspection covered the three screenshot widths. Wishlist retains the stock horizontally scrolling table on narrow screens; final account/card/button styling is explicitly deferred. The UI fixture uses synthetic long names/images and retained RD-25 styles/assets. This is local composition evidence, not a live page or final design acceptance.

Focused verification tooling remains in `work/acc006-wp3-20261013/`: `build.py`, `runner-template.php`, `backend-tests.php`, `mock-preflight.php`, `verify-runner.py`, `render.php`, `ui.mjs`. The mysqli double/fault injection is **fixture-only**, never included in the production runner.

## Owner run command — only after independent review and deploy authorization

Upload **only** `ACC-006_wishlist-restore_20261013.php` into `~/public_html`. The one block applies it and clears ordinary/compiled Twig cache files using `DIR_CACHE`; it does not delete source or customer data.

```bash
cd ~/public_html || exit
php ACC-006_wishlist-restore_20261013.php && php -r '
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

Expected output: source gate, `db_preflight=read_only_ok`, a backup path, 9 changed files, PHP lint OK, `done=ok`, then `cache_cleared=ok`. Record the exact backup path. On `done=error`, stop and send the bounded output; do not rerun blindly or change a DB table. Hard-refresh the browser after successful installation.

## Rollback

Re-upload the **same** runner and run `php ACC-006_wishlist-restore_20261013.php --rollback=_patch_backups/<exact printed ACC-006 directory>`, then the same cache-refresh step above. The rollback validates original hashes/allowed paths and refuses unrelated later target changes before restoring anything. It restores the 8 existing files, removes only the unchanged new JS asset, preserves a later valid DS token, and retains saved wishlist choices. No rollback SQL is needed.

## Required owner production QA

- Logged-in add from card and page; repeat gives one item; remove only the selected item; list and header/page counters agree; logout/login does not restore it.
- Second browser: guest add then ordinary login merges once with existing choices; a subsequent account does not inherit logged-in pending additions.
- Sold-out item stays with inactive buy; normal/pre-order/optioned products preserve the existing cart flow; long names and focus/hover/pressed/busy behavior at 3 widths.
- Product 89 and another name containing encoded quotes/apostrophes display correctly in wishlist text and image/button attributes, without literal `&amp;`/`&quot;`. Browser Back restores the header/page counters.
- GA4 DebugView `add_to_wishlist`: unverified; the local adapter-anchor checks cover existing cart/list compatibility only. These corrections add no binding for the restored controls and do not establish this event's emission.
- Existing product gallery/sticky cart and credit controls behave normally; no payment/order-status change is required by this QA.
- Tier 1: Home, `/catalog/Pokemon`, `/catalog/Pokemon/Pokemon-booster-box`, `/product/One-Piece-Boosters-OP-11`, `/product/FIG-CHARM-001`, `/information/oplata-i-dostavka`, cart and checkout entry return normally; checkout starts.
- No new PHP error in `/home2/boosters/ocartdata/storage/logs/` during the bounded QA window. Source/local proof does not replace this check.

## Closure and owner control

Ready for repeat review at the gate. ACC-006 remains incomplete until owner functional QA and the later visual step is completed or explicitly split. Claude owns the Notion status; this executor changed no roadmap/status data. ACC-004 remains an audit/future task proposal, not bundled implementation; the forwarded review did not accept WP1.

There is no active-task Git grant. If the owner later chooses to commit/push, use the normal repository sentinel/staged-set procedure and only this patch, this report, its evidence folder and the seven named verification tools; exclude archives, raw hosting fixtures, `.bak`, `.log`, dumps and unrelated work. No Git command has been executed beyond read-only status.


## Prepared owner Git block (optional; not executed)

Use only after review and a separate owner decision to commit/push. It refuses a pre-existing staged set or sentinel and stages exactly the scoped artifacts/tools below; master is checked before any Git write.

```powershell
Set-Location -LiteralPath 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
if ((git branch --show-current).Trim() -ne 'master') { throw 'Expected master; inspect branch before continuing.' }
if (@(git diff --cached --name-only).Count -ne 0) { throw 'Staged changes already exist; inspect them first.' }
if (Test-Path -LiteralPath '.autosync-pause') { throw 'Another operation owns the autosync sentinel.' }
$approvedFiles = @(
    'patches/ACC-006_wishlist-restore_20261013.php',
    'diagnostics/ACC-006_wishlist-restore_report_20261013.md',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/backend-tests.json',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/catalog__controller__account__wishlist.php.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/catalog__controller__common__header.php.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/catalog__view__template__account__wishlist.twig.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/catalog__view__template__account__wishlist_list.twig.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/catalog__view__template__common__header.twig.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/catalog__view__template__product__product.twig.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/catalog__view__template__product__thumb.twig.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/diffs/extension__ukrainian__catalog__language__uk-ua__account__wishlist.php.diff',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/manifest.json',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/render.json',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/runner-tests.json',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/signature-scan.json',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/syntax.json',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/ui-1024.png',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/ui-1440.png',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/ui-390.png',
    'diagnostics/ACC-006_wishlist-restore_evidence_20261013/ui-tests.json',
    'work/acc006-wp3-20261013/backend-tests.php',
    'work/acc006-wp3-20261013/build.py',
    'work/acc006-wp3-20261013/mock-preflight.php',
    'work/acc006-wp3-20261013/render.php',
    'work/acc006-wp3-20261013/runner-template.php',
    'work/acc006-wp3-20261013/ui.mjs',
    'work/acc006-wp3-20261013/verify-runner.py'
)
Set-Content -LiteralPath '.autosync-pause' -Value 'ACC-006 owner Git operation'
try {
    git add -- $approvedFiles
    if ($LASTEXITCODE -ne 0) { throw 'git add failed' }
    $actualFiles = @(git diff --cached --name-only | Sort-Object)
    $expectedFiles = @($approvedFiles | Sort-Object)
    if (Compare-Object $expectedFiles $actualFiles) { throw 'Staged set differs from approved files' }
    git diff --cached --check
    if ($LASTEXITCODE -ne 0) { throw 'Staged whitespace check failed' }
    git commit -m 'Codex: ACC-006 restore wishlist'
    if ($LASTEXITCODE -ne 0) { throw 'Commit failed' }
    git push origin master
    if ($LASTEXITCODE -ne 0) { throw 'Push failed' }
} finally {
    Remove-Item -LiteralPath '.autosync-pause' -Force
}
```
