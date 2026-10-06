# Codex Report — UX-003: restore category load more

Date: 2026-10-06

## Outcome and scope

The current runner-9 JS rejected legitimate page-2 responses without a canonical link and called `window.location.assign`, losing the first 15 displayed products. The fix permits an absent response canonical only when appending. Nonempty matching category identity, response origin, redirect, result total and product checks remain mandatory. Supplied conflicting canonical links and missing canonical on replacement requests still fail.

This corrects a runner-9 regression. The earlier local fixture included canonical on paginated responses and therefore missed the production behavior.

Deliverable: `patches/UX-003_load-more-fix_20261006.php`.

Production scope is exactly two files:
- `catalog/view/javascript/bs-category-results.js`: one response guard and explanatory content marker.
- `catalog/view/template/product/category.twig`: only script cache version, from `ux003-r9-20261006` to `ux003-lm-fix-20261006`.

SEO metadata output/policy, PHP controllers, filter module, CSS, header, badges, DB, schema and purchase logic are unchanged. No status update, commit, push or deployment was performed. This report supersedes the pending source-input gate in `diagnostics/UX-003_load-more-regression_diagnosis_20261006.md`.

## Verified sources and safety

Owner supplied `C:/Users/14bez/Downloads/category.twig`. Publicly served category JS was read again during validation and matched the earlier live read byte-for-byte.

```text
category.twig SHA256: 2ede52f642757083241e1634ed77babc3786be5ccdfce963be3651f30da1dded
JS SHA256:           3b31c563aca5f20764c6b041655480494c711cc6230cd41d79fd5e5589e9c735
```

The runner checks both source hashes and expected single anchors before backup or mutation. Verified backups precede both writes. It checks for a source change during preflight, verifies written bytes and restores both targets after a write failure. Idempotency is determined by the JS content marker and corrected guard, independently of shared cache tokens. Inconsistent marker states fail closed. CLI runner self-lint runs before changes, and self-delete occurs after success. PHP 8.0-compatible constructs only; the local interpreter is PHP 8.3.30.

## Verification

Executed:

```text
php -l patches/UX-003_load-more-fix_20261006.php: PASS
node work/verify-load-more-fix_20261006.cjs: PASS
php work/parse-load-more-twig_20261006.php: PASS (source and patched Twig)
```

Local fixture checks: exact JS guard-only diff and Twig token-only diff, original-byte backup equality, success/self-delete, idempotency with unchanged file bytes, refusal of JS drift, Twig drift and partial marker before backup/write. Patched JS passes `node --check`. Source preservation plus local Twig parse proves that the cache-token edit leaves template syntax intact. Write-failure restoration is implemented but not fault-injected in this pass.

An isolated headless Edge browser used actual public category responses and the runner-generated JS injected only in the local session:
- One Piece at 390px and 1440px: 15 -> 27, first 15 retained, no duplicate product links, address unchanged.
- Pokemon at 768px: 15 -> 30 -> 45 -> 48, first 15 retained, no duplicate product links, address unchanged.
- Sorting -> append -> reset -> one filter -> reset -> browser Back: passed; filter checkbox state restored.
- Wrong canonical, wrong/missing category identity, missing result wrapper, changed total on append, absent canonical during replacement: all retained the normal-navigation fallback.

Evidence: `work/load-more-fix-20261006/evidence.json`, `apply.log`, fixture outputs and backups. Test scripts are local companions, not production upload inputs. The original failure reproduction is retained under `work/category-load-more-20261006/`.

This proves behavior against real responses with a locally injected fix. It is not deployment or owner acceptance.

## Owner run

Upload only `UX-003_load-more-fix_20261006.php` to `~/public_html`. Run in that directory:

```bash
php UX-003_load-more-fix_20261006.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected: `twig_preservation=ok`, `php_lint=ok`, backup path, two `changed` lines, `done=ok`, `self_delete=ok`, `cache cleared`. On source mismatch stop and obtain a fresh export; do not bypass the hashes.

## Rollback and remaining QA

Risk: medium, category request lifecycle and pagination integration. No SEO policy changes.

Restore both relative paths from the logged `_patch_backups/UX-003_load-more-fix_<UTC timestamp>-<random>/` directory, clear caches and refresh. Only restore directly if there have been no subsequent edits to those targets; otherwise reconcile them first. This restores the original runner-9 behavior, including the diagnosed bug.

- [ ] Owner refreshes One Piece and clicks load more: 27 products remain together on the base address.
- [ ] Owner refreshes Pokemon and clicks repeatedly: prior products remain, last page hides the button.
- [ ] Filter, sort and Back/Forward still behave correctly on phone and desktop.
- [ ] Tier 1 smoke URLs from AGENTS.md: home, top/nested Pokemon categories, sealed and 3D products, information page, cart, checkout entry.

## Owner confirmation

On 2026-10-06 the owner reported that the deployed fix works ("пофікшено, працює") and explicitly authorized commit/push. Deployment and successful load-more behavior are owner-confirmed; the individual QA items above were not separately reported and remain unverified. This is not an independent full-store smoke-test result.
