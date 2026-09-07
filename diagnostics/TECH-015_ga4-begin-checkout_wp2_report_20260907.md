# Codex Report — TECH-015 WP2: GA4 begin_checkout anchor restoration

Date: 2026-09-07

## Production outcome

WP2 is runtime-confirmed in production. Checkout renders the owner-approved
`Оформлення замовлення` heading and Google Analytics accepted one
`begin_checkout` event in a `204` collection request for destination
`G-283QW89TX8`. The verified payload used `currency=UAH`, `value=800`, an empty
coupon, and one item (`item_id=85`, `price=800`, `quantity=1`) with the expected
name, affiliation, brand, category and checkout-list metadata. GA4 batched
`page_view` and `begin_checkout` into the same transport request, which explains
why the earlier narrow visual inspection was misleading.

## Production incident and corrective hotfix

The initial WP2 runner was deployed by the owner at
`2026-09-07T09:36:44+00:00` and produced the expected
`fb7d6a8a...49499e` Twig hash, `database_touched=no`, `done=ok`,
`self_delete=ok`, and `cache cleared`.

Owner browser QA immediately found a visible regression: the checkout H1
rendered `Мій кошик`, and no `begin_checkout` request appeared. Root cause:
`catalog/controller/checkout/checkout.php` uses the checkout language value for
the document title and breadcrumb but does not assign
`$data['heading_title']`. By Twig render time, child-controller language loads
have replaced the global `heading_title` with the cart value. The former
hardcoded H1 had insulated the page from that collision.

Corrective runner:

```text
patches/TECH-015_ga4-begin-checkout_wp2-hotfix_20260907.php
```

It replaces the faulty dynamic H1 with the owner-approved hardcoded
`Оформлення замовлення` and places the vendor-prepared `begin_checkout` and
conditional `qualify_lead` snippets directly beside it. Payload construction
remains in the vendor event handler; the hotfix does not duplicate cart logic
or edit vendor code. Expected corrected Twig SHA-256:

```text
675e6cb14f082c53211113f307feea97e4bb4857d639c748a4b5e1294809cafb
```

The original WP2 runner is superseded and must not be run again.

Hotfix local fixture evidence:

```text
changed_file=catalog/view/template/checkout/checkout.twig
after_sha256=675e6cb14f082c53211113f307feea97e4bb4857d639c748a4b5e1294809cafb
database_touched=no
done=ok
self_delete=ok
repeat: already_applied=yes
PHP 8.0 compatibility scan: OK
```

## Scope

Implemented WP2 as a separate hosting runner after WP1 production QA. The
runner restores the exact dynamic checkout H1 anchor expected by the installed
GA4 module. It changes one Booster-owned Twig file and does not edit the vendor
extension, controller, language file, payment flow, order lifecycle, database,
Notion, or `ROADMAP_FLOW`.

Owner wording decision: the visible checkout H1 may change from the hardcoded
`Оформити замовлення` to the existing Ukrainian language value
`Оформлення замовлення`. The browser title already uses that language value, so
the visible H1 and tab title become consistent.

## Root cause and prior override history

`patches/RD-13_checkout-reskin_20260706.php` replaced the former
`<h1>{{ heading_title }}</h1>` with the hardcoded
`<h1>Оформити замовлення</h1>` as part of the checkout visual-shell rewrite.
The installed GA4 module still searches for the exact dynamic H1 anchor before
injecting its registered `begin_checkout` script. Because the anchor no longer
exists, the event handler builds data but inserts nothing.

Repository history was searched before editing. RD-13 is the patch that
introduced the current hardcoded H1. The older
`st2b5c_ga4_dedupe_stock_checkout_20260614.php` also relies on the same dynamic
H1/module injection contract. No CSS selector, shared stylesheet, `!important`,
timer, fixed positioning, or magic-pixel override is added by WP2.

## Files touched

```text
patches/TECH-015_ga4-begin-checkout_wp2_20260907.php
patches/TECH-015_ga4-begin-checkout_wp2-hotfix_20260907.php
diagnostics/TECH-015_ga4-begin-checkout_wp2_report_20260907.md
```

Runtime target written by the patch:

```text
catalog/view/template/checkout/checkout.twig
```

Read-only deployment preflight also verifies:

```text
extension/ukrainian/catalog/language/uk-ua/checkout/checkout.php
extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php
```

## Source validation

Newest available repository backup:
`backup-9.3.2026_21-30-35_boosters.tar.gz`.

```text
before SHA-256 = d355ae2cfcd99bd1bba9c5d0f5825fc17de531a1b57fc8fb80343489fffb937f
after SHA-256  = fb7d6a8a44d9b4d8a7898bb3c98a8c2df686a000916b5fb2366640e02449499e
```

The runner additionally fails closed unless:

- the hardcoded H1 exists exactly once;
- the approved Ukrainian language value `Оформлення замовлення` exists exactly
  once;
- the vendor model still searches for `<h1>{{ heading_title }}</h1>` exactly
  once;
- the vendor model still contains exactly one `begin_checkout` emitter.

## Local fixture result

```text
cwd=.../work/tech015-wp2/fixture
backup=.../_patch_backups/TECH-015_ga4-begin-checkout_wp2_20260907-...
backup_file=.../original/catalog/view/template/checkout/checkout.twig
php_lint=not_applicable target=catalog/view/template/checkout/checkout.twig
changed_file=catalog/view/template/checkout/checkout.twig
after_sha256=fb7d6a8a44d9b4d8a7898bb3c98a8c2df686a000916b5fb2366640e02449499e
database_touched=no
done=ok
self_delete=ok
```

The patched template exposes one exact module anchor. A bounded simulation of
the verified vendor replacement produced:

```text
source_anchor_count=1
rendered_begin_checkout_count=1
approved_heading_count=1
```

## PHP checks

```text
No syntax errors detected in patches/TECH-015_ga4-begin-checkout_wp2_20260907.php
```

The only runtime target is Twig, so target PHP lint is not applicable. The
runner itself is PHP 8.0 compatible.

## Idempotency and drift refusal

Re-running an uploaded copy against the patched fixture returns:

```text
already_applied=yes
self_delete=ok
```

Changing one byte in the source Twig produced the following before backup or
write:

```text
ERROR: Source SHA-256 mismatch: catalog/view/template/checkout/checkout.twig
backup_created=no
```

## Rollback

The runner backs up the original under:

```text
_patch_backups/TECH-015_ga4-begin-checkout_wp2_20260907-<timestamp>-<random>/original/catalog/view/template/checkout/checkout.twig
```

Restore with the actual backup directory printed by the runner:

```bash
cp _patch_backups/TECH-015_ga4-begin-checkout_wp2_20260907-<timestamp>-<random>/original/catalog/view/template/checkout/checkout.twig catalog/view/template/checkout/checkout.twig
```

Clear OpenCart cache after rollback. Roll back if checkout fails to render, the
approved heading is wrong, or the module inserts more than one
`begin_checkout` snippet in one page response.

## Original WP2 command — executed, now superseded

```bash
cd ~/public_html || exit
php TECH-015_ga4-begin-checkout_wp2_20260907.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Do not run the original WP2 runner again.

## Corrective hotfix command — owner

```bash
cd ~/public_html || exit
php TECH-015_ga4-begin-checkout_wp2-hotfix_20260907.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Production execution evidence — owner, 2026-09-07

The owner ran the patch from `~/public_html` at
`2026-09-07T09:36:44+00:00`. The guarded mutation completed with:

```text
backup=/home2/boosters/public_html/_patch_backups/TECH-015_ga4-begin-checkout_wp2_20260907-20260907-093644-037bab
backup_file=.../original/catalog/view/template/checkout/checkout.twig
php_lint=not_applicable target=catalog/view/template/checkout/checkout.twig
changed_file=catalog/view/template/checkout/checkout.twig
after_sha256=fb7d6a8a44d9b4d8a7898bb3c98a8c2df686a000916b5fb2366640e02449499e
database_touched=no
done=ok
self_delete=ok
cache cleared
```

This proves the expected live source was changed, backed up, and cache-cleared.
Browser QA then exposed the H1 regression described at the top of this report;
the original WP2 runtime result is not accepted.

## Post-deploy QA checklist

- [x] Runner reports `done=ok`, expected after hash, `database_touched=no`,
  `self_delete=ok`, then `cache cleared`.
- [x] Initial browser QA failed: the H1 rendered `Мій кошик` and the filtered
  Network list contained no `begin_checkout`. Corrective hotfix prepared.
- [ ] Hotfix runner reports corrected hash, `done=ok`, `database_touched=no`,
  `self_delete=ok`, then `cache cleared`.
- [x] After the hotfix, checkout with a non-empty cart rendered the visible H1
  exactly as `Оформлення замовлення` (owner screenshot, 2026-09-07).
- [x] The first post-hotfix screenshot showed an empty Network result for
  `en=begin_checkout`; later direct inspection proved this was a diagnostic
  false negative, not a missing event.
- [x] The GA4 request reports page title `dt=Оформлення замовлення`.
- [x] `view-source:` contains exactly one `pushEventData('begin_checkout'`
  snippet with the current cart payload: UAH, value 800, product id 85, price
  800, quantity 1 (owner evidence, 2026-09-07).
- [x] Read-only Console inspection after reload returned `ps: "object"`,
  `gtag: "function"`, `begin_enabled: true`, and `begin_queued: 1`. This proves
  the rendered snippet called the enabled vendor transport exactly once and
  queued `gtag('event', 'begin_checkout', ...)`; the remaining open question is
  why the Google tag did not turn that queued command into a visible collection
  request (owner evidence, 2026-09-07).
- [x] A second read-only Console inspection found one
  `googletagmanager.com/gtag/js?id=G-283QW89TX8` script and resource entry, and
  `window.google_tag_manager` contains the `G-283QW89TX8` destination. The
  Google tag loader is therefore present and initialized for the expected GA4
  property (owner evidence, 2026-09-07).
- [x] A third read-only Console inspection returned
  `processor_attached: true` and the exact processed-command order
  `js -> config G-283QW89TX8 -> event begin_checkout`. The event parameters
  contain `currency`, `value`, `coupon`, and `items`. This confirms the WP2
  emitter, vendor enablement gate, destination configuration, command order,
  and Google dataLayer processor; any remaining failure is after the browser
  tag has accepted the event command (owner evidence, 2026-09-07).
- [x] The Resource Timing API returned no retained Google Analytics `collect`
  entries. This is not conclusive transport evidence because the checkout page
  loaded more than 500 resources and early timing entries may have been evicted;
  the later direct Network evidence supersedes this negative result.
- [x] DevTools Network shows one Google Analytics fetch initiated by
  `js?id=G-283QW89TX8`, status `204`. Its batched payload contains
  `en=begin_checkout`, `cu=UAH`, `epn.value=800`, empty `ep.coupon`, and one
  item: id 85, price 800, quantity 1, with the expected name, Booster Shop
  affiliation, Bandai brand, categories and checkout-list metadata (owner
  evidence, 2026-09-07).
- [x] After clearing Console and reloading checkout, no browser-console errors
  appeared and the checkout sections still rendered (owner screenshot,
  2026-09-07).
- [ ] Run the required Tier 1 storefront URLs from `AGENTS.md`; no real payment
  or order is required for WP2 QA.

## Side effects / risks

- This reactivates the vendor module's existing checkout event and its existing
  `qualify_lead` injection at the same anchor if that separate setting is
  enabled. The backup evidence says the lead status associations were empty,
  but runtime source inspection should note any additional injected snippet.
- `begin_checkout` is page-entry based. Reloading/re-entering checkout may
  legitimately emit another event; WP2 does not add session deduplication.
- The owner deploys and performs production QA. Codex did not access hosting.
- Hutko remains production-only and is not exercised for this patch. PAY-003
  authorized recovery remains consciously open for verification when a valid
  session/account is available.
