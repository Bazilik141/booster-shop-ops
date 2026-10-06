# UX-003 runner 9 — server-backed category updates

Date: 2026-10-06
Executor: Codex; owner instructed the executor to choose the proper site solution.
Status of evidence: local fixture validation complete. On 2026-10-06 the owner
confirmed the patches work on the site and authorized Git publication and closure.
Production acceptance is owner-reported, not independent remote verification.

## Scope and resulting behavior

Checkboxes, chips, reset and sorting fetch the existing server category URLs and
replace the result region. Browser history restores controls, results and metadata.
The existing load-more button shares this request lifecycle and always uses the
current grid. Superseded requests cannot apply stale data; failures navigate normally.
The panel and group nodes remain in place, preserving expansion and checkbox focus.

The server remains authoritative for results, counts, filter selection, sort links,
robots and pagination links. No filtering algorithm, PHP controller, canonical rule,
indexing policy, product-card template, schema, feed or purchase logic was changed.

Input: owner's `ux003-r9-bug004a-live-20261006-223210.tar.gz`. Category source SHA
matches runner 8b's recorded output. Apply BUG-004A first, then this runner.

## Files touched by the server runner

- `catalog/view/template/product/category.twig`: stable result wrapper including
  the empty state; sticky CTA, chips/status containers; replace the old independent
  filter-panel and load-more controllers with the category script. The existing
  breadcrumb JSON-LD remains byte-identical.
- `extension/opencart/catalog/view/template/module/filter.twig`: expose the real
  server `action`; retain the existing URL builder and 250 ms debounce with a normal
  navigation fallback; support cancellation when sorting/reset/history interrupts it.
- `catalog/view/javascript/bs-category-results.js`: new category-only controller.
- `catalog/view/stylesheet/boostershop-ds.css`: source fixes for responsive groups,
  checkbox targets, long labels and sticky containment; new approved state styles.
- `catalog/view/template/common/header.twig`: CSS version token only.

Repository deliverable: `patches/UX-003_runner9_filters-no-reload_20261006.php`.
Evidence: `diagnostics/UX-003_BUG-004A_20261006_evidence/`.

## Root causes and override history

- The live filter script calls `window.location.href` after every checkbox change.
- The old load-more closure captures the original `#product-list` and counters.
  Replacing the grid without replacing that lifecycle would append into a detached node.
- Empty responses omit `#product-list`; the stable result wrapper avoids treating a
  valid zero-result response as a missing-grid failure.
- DS source `.bs-cat-header` (line 227) uses `overflow:hidden`, and the mobile
  `main/#content` rule (lines 1242–1246) uses `overflow-x:hidden`. These create scroll
  containers that trap `position:sticky`. The header now uses `clip`. The grouped
  mobile rule was split into mutually exclusive category/other-page selectors:
  category ancestors use `clip`, other page types retain their existing behavior.
- Runner 7 introduced `.bs-ff-*`; runner 8 changed the narrow toolbar into two rows;
  runner 8b moved the first inline style block. These behaviors are preserved.
- Existing checkbox/group/footer source rules are edited directly. No appended
  competing rule is used to fix their breakpoints, sizes or overflow.

## Owner-directed clarification and handoff reconciliation

The handoff prohibited touching robots while requiring history changes and body-only
updates. The source switches robots between `index,follow` and `noindex,follow` based
on the request. Owner delegated the decision to the executor. The chosen solution
copies exact response robots and prev/next values on result replacement, leaving
canonical and the server policy unchanged. A different/missing canonical, wrong
category identity, missing wrapper, redirect, invalid count or non-200 status triggers
normal navigation before applying the response.

Load-more keeps its existing address behavior: append cards, advance the exact
server-provided next-page cursor, retain robots/prev for the current address. It
does not introduce a self-referential prev link from the appended page.

The actual sort select is `.bs-ff-sort select`; the archive has no `#input-sort`.
Its element, options, labels and existing inline navigation fallback are preserved.
Only response-backed option hrefs/selection are synchronized with the active filter.
The current visible pagination is load-more; no new pager UI was invented.

## Validation

Local environment: PHP 8.3.30, Twig 3.28.0 and isolated headless Chrome on Windows.
PHP 8.0 compatibility was maintained in runner syntax; no PHP 8.0 runtime pass is
claimed. At execution, the runner parses the unmodified and candidate templates
using the hosting installation's own Twig source before writing.

Passed:

- `php -l` for both runners, `node --check` for the category script, Twig control
  and candidate parsing, CSS balance/hazard checks.
- Dry-run, apply, byte equality with generated candidates, self-delete, repeat-run
  `already_applied=yes` with byte-identical targets (including cache token).
- Both independent rollback orders preserve the sibling patch, then recover the
  original source bytes; header cache token is deliberately refreshed.
- Partial-marker and unexpected-source failures leave targets unchanged.
- A fixture-only injected failure after the cart write restores every target
  (`restore=ok`) and retains the failed runner.
- 24 integrated browser cases plus one focused interaction/loading case: **25/25**.
  Widths: 390, 575, 576, 768, 991, 992, 1000, 1440. Includes keyboard checkbox,
  chip focus/reset announcements, Back/Forward, exact response robots, long unbroken
  labels, tall-panel sticky CTA, preserved panel position during loading, cancellation
  of an uncancellable delayed response, empty/reset, load-more after filter, no-module
  category, normal fallback for request failure/HTTP 500/missing wrapper/wrong canonical,
  and card add/cart refresh after swapping the grid.
- Screens at 390/768/1000/1440 were rendered and visually inspected. Product data,
  logo/images and stock counts are synthetic fixtures, not production proof.

The integrated 24-case run preceded the final literal-white -> `--bs-paper`
substitution; the source token is `#FFFFFF`. Runner checks and the focused loading/
interaction case were rerun on the final artifact. No other functional change followed.

## Signature review

The original 250 ms debounce is retained; there are no new arbitrary delays in the
category controller. New absolute positioning is confined to the progress bar and
chip tap extension; sticky is the approved CTA behavior. Pixel sizes are documented
by the design note (44 px target, 48 px CTA, 3 px progress, 20/18 px boxes, 36/32 px
chips). Existing important overflow declarations are split at their source into
mutually exclusive selectors, preserving their priority. No new fixed overlay or
competing important override was added for category behavior.

## Risk and remaining QA

SEO gate: High — category history/rendering and pagination metadata. Safest action:
review this bounded runner, then owner deploy and compare direct server navigation
with enhanced navigation. Source-level checks and local fixtures do not prove
production route/redirect behavior, theme overrides, mobile Safari or screen-reader
speech. Canonical/robots server policy, redirects, sitemap, feed and schema are out
of the write scope. No canonical/redirect/sitemap change is proposed.

Owner checks after deployment:

- Same real stock filter/sort URL and total as opening it directly; one category
  fetch per checkbox, no normal navigation on a valid response.
- One/multiple groups, chips/reset, sorting, load more, Back/Forward, zero results.
- 390/768/1000/1440 and 575/576, 991/992 edges; real long labels, keyboard/focus.
- Slow network rapid clicks and browser Offline fallback; console clean.
- Source canonical/robots before/after identical for the same URL; enhanced DOM
  robots and pagination agree with the server response for result replacement.
- Tier 1 page smoke and category without filter/subcategories; real-phone sticky CTA.

## Owner run and rollback

After review, upload both PHP files to `~/public_html`, then use this single block:

```bash
cd ~/public_html &&
php BUG-004A_mobile-cart-badge_20261006.php &&
php UX-003_runner9_filters-no-reload_20261006.php &&
php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected: each runner reports `done=ok`; then `cache cleared`. Backup:
`_patch_backups/UX-003_runner9_filters-no-reload_20261006-<timestamp>-<nonce>/`.
`--dry-run` does not write or self-delete. Success self-deletes; failure keeps the file.
The dry-run still requires hosting `config.php` and Twig source for parser checks.

If category/history/metadata behavior breaks, re-upload this exact runner and run
`php UX-003_runner9_filters-no-reload_20261006.php --rollback`, then the same cache
cleanup. It reverses owned edits, verifies the new JS bytes before removing that
file, and preserves BUG-004A and unrelated compatible content. It safe-fails if an
owned segment was modified. Do not restore whole shared CSS/header backups over a
later sibling patch. Backups remain the emergency recovery record.

No Git commit/push, status write, production deployment or live business-data mutation
was performed.

## Owner acceptance and closure — 2026-10-06

The owner confirmed that both delivered patches work and explicitly requested commit,
push and roadmap updates. UX-003 and BUG-004 are now Done in canonical Notion and
the dashboard mirror. The direct request assigns Codex these two closure updates
for this session only; the general writer rules remain unchanged.

The earlier no-write statement above describes the implementation phase. No detailed
production smoke log or deployment hashes were supplied; do not infer individual
checklist results from the owner's general acceptance. The previously documented
GA4 view_item_list re-send gap remains a follow-up. TECH-045 stays paused until
a fresh live export and a separate owner decision. Git publication targets the
existing tracked branch codex/3dp-payout-approval-status, with only this round's
files staged. Other local changes and archives are excluded.
