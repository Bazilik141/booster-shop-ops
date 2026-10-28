# Codex Report — UI-PCARD-BADGE: raise listing badges

Date: 2026-10-06

## Scope and root cause

Owner requested moving all listing badges upward, approximately to the marked image edge. Both canonical rules in the owner-supplied `boostershop-ds.css:270-271` use `top: 18px`. The runner changes these to `4px` directly, matching the design system spacing grid. Horizontal offsets remain 18px. No overriding rule or `!important` is added.

History checked: CAT-004-SD-7 adds the left flex column with 6px gap, without changing top/left. UI-PCARD-D removes the card decoration and preserves the badge offsets. No recent downward-offset change was found in those patches. This is not proof of the complete production deployment history.

The header CSS token is read by its path prefix, validated, and replaced with `ui-pcard-badge-20261006`. Its previous value is not an anchor. The CSS content marker controls idempotency, independently of later header-token changes.

## Files and source evidence

- Deliverable: `patches/UI-PCARD-BADGE_raise-badges_20261006.php`.
- Production targets: `catalog/view/stylesheet/boostershop-ds.css`, `catalog/view/template/common/header.twig`.
- Owner-supplied `thumb.twig` inspected; no markup change required.
- The three `header.twig.before-*` files are historical backup templates and are not runner inputs.
- No task properties/status, commits, pushes or production writes performed.

Owner-supplied source SHA256:

```text
boostershop-ds.css 2523731CF09D1030535ACD66F01765173F10D65EA810FD49BB5749F9F9AE778A
header.twig       BCE83880A8C26A0B60908E95FB171BFE4486D9304B97B6B8DAFBA9FC32DFCA44
thumb.twig        1E7658CDDB25720A06E44F9F4C8EA989D3A410D61A22E3425498611A7A9C3A5C
```

## Local verification

```text
php -l patches/UI-PCARD-BADGE_raise-badges_20261006.php
No syntax errors detected
node work/verify-pcard-badges_20261006.cjs
PASS: backups, exact diff, self-delete, idempotency, fail-closed guards;
390/768/1440px, 4 interaction states, six product cases.
```

Fixture checks prove that the CSS changes consist only of the two offsets plus explanatory marker, and the header changes only its CSS token. Both backup files match originals. A repeat run returns `already_applied=yes`, preserves file bytes and self-deletes. Anchor drift and inconsistent marker state fail before backup/write. The runner restores both targets on a write/readback failure; failure restoration was not injected in this pass.

The local browser fixture uses the complete supplied CSS, representative listing markup and the long OP-05 title from the owner screenshot, with synthetic pack artwork. It covers Rare Pack, preorder, out-of-stock, preorder + Rare Pack, out-of-stock + Rare Pack, ordinary products, and discounts on selected cases. All three screenshots were visually inspected. Measured top inset is 4px, horizontal inset 18px and stacked gap 6px across normal, hover, focus and active states. This is local component QA, not full-store/runtime proof.

Evidence: `work/pcard-badges-20261006/evidence.json`, `preview.html`, `preview-390.png`, `preview-768.png`, `preview-1440.png`. Reproducible fixture runner: `work/verify-pcard-badges_20261006.cjs` (requires supplied Downloads sources and configured local runtimes). Node child-process spawning returned sandbox EPERM; the identical fixture command passed in the approved execution context. Local PHP is 8.3.30; runner syntax is compatible with PHP 8.0, but PHP 8.0 was not executed locally.

Review signatures: no new `!important`, `setTimeout`, or fixed position; existing absolute position retained. The new 4px value has an inline rationale.

## Owner run and rollback

Upload only the PHP runner to `~/public_html`. In that directory run:

```bash
php UI-PCARD-BADGE_raise-badges_20261006.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected: `php_lint=ok`, logged backup path, two changed files, `done=ok`, `self_delete=ok`, then `cache cleared`. PHP lint checks the runner before writes because no production PHP file changes. If CLI lint is unavailable the runner stops without writes.

Backups are verified before either write under `_patch_backups/UI-PCARD-BADGE_raise-badges_<UTC timestamp>-<random>/`. To roll back, restore both relative paths from the logged directory, provided no subsequent patch has changed either target; otherwise reconcile the later changes first. Clear caches with the same PHP cache-clear command and refresh the browser.

## Remaining owner QA and risk

Risk: low, shared listing CSS affects all product-card instances.

- [ ] Refresh catalog at mobile, tablet and desktop widths; badges start near the image-slot top, without clipping.
- [ ] Check Rare Pack, preorder, unavailable and discount badges; two left badges retain their order and gap.
- [ ] Check long titles and hover/keyboard focus, including a home-page product module.
- [ ] Run the project's Tier 1 smoke URLs recorded in AGENTS.md: home, both Pokemon categories, sealed TCG and 3D products, information, cart, checkout entry.

Deployment and owner QA remain pending.
