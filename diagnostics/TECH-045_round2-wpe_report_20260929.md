# TECH-045 round 2 — WP-E purchase green, sizes, success-page label

Date: 2026-09-29 · Executor: Claude Code · Handoff: `handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md` → "Round 2"
Patch: `patches/TECH-045_wpe-purchase-green-sizes_20260929.php` (boostershop-ds.css, booster-typography.css, header.twig tokens)
Live source: `tech045-live3-20260929.tar.gz` (repo root) → `live-snapshots/20260929_tech045-round2/`.
Round-1 after-set: added to `diagnostics/TECH-045_round1-patches_report_20260928.md` §2a before this patch was written.

Root causes, override history and per-rule detail are in the patch header. This file holds what the patch header
cannot: deviations, verification and owner QA.

## 1. Deviation from the handoff

**No separate mobile size for card «Купити».** The handoff assumed a 2-column mobile grid and a 13px mobile rule at
ds.css ~500. Live 2026-09-29 has neither. Cards are one column below 768 px, and `.bs-pcard__buy-btn` takes its size
from `.bs-btn` (14.5px) at every width. Narrowest card button measured: 291 px (product-page related row at 360 px).
Category and home buttons measure 330 px at 360/390/768 and ≥ 300 px at 1440. The longest label, «Передзамовити»,
needs 159 px at 16px/700 including padding. **16px/700 is used at every breakpoint.** If the owner still wants a
smaller mobile size, it is a one-line follow-up.

## 2. Acceptance — contrast (white text)

| Control | Base → ratio | Hover/focus/open → ratio |
|---|---|---|
| `.bs-btn-primary` (card «Купити», mini-cart «Оформити замовлення», sticky «Купити», empty-state links, success «Переглянути замовлення») | #12883E → 4.55 | #15803D → 5.02 |
| header mini-cart trigger | #12883E → 4.55 | #15803D → 5.02 |
| `#cart .mini-cart-checkout-btn` | #12883E → 4.55 | #15803D → 5.02 |
| product `#button-cart` «У кошик» | #12883E → 4.55 | #15803D → 5.02 |
| checkout confirm «Підтвердити замовлення →» | #12883E → 4.55 (was #16A34A 3.29) | #15803D → 5.02 |

## 3. Verification

Test data: the round-2 live copy, run through the site's Twig 3.18. Runs: an LF and a CRLF copy, then a repeat.

- **Patch runs.** First run `done=ok`; the repeat returns `already_applied=yes`. The diff is limited to the rules
  named in the patch header, and nothing references `--bs-green-dd` afterwards. The CRLF copy produces output
  identical to the LF copy after line-ending normalisation.
- **Gates.** `scripts/check-php-host-compat.php`: clean. `php -l` (8.3): clean.
- **Live-page simulation** (the new rules injected into https://boostershop.website/):
  - axe-core 4.10 colour-contrast on home at 390 px: 0 violations.
  - Header trigger computes rgb(18,136,62).
  - Card «Купити» computes 16px/700, height 44 px, `scrollWidth = clientWidth` (one line).
  - A test `<a class="bs-btn bs-btn-primary">` inside `body.bs`, standing in for the success-page link, computes
    white text on #12883E. Without the patch it computes navy rgb(30,58,138), which reproduces the owner's report.
  - Toast buttons are unchanged: mobile shows a transparent button with a white outline and white text; desktop at
    1440 px shows white with ink text.
- **Not verifiable without an order.** The checkout confirm button and the real success page. The checks are in §4.

## 4. Owner QA after deploy

```bash
cd ~/public_html && php -l TECH-045_wpe-purchase-green-sizes_20260929.php && php TECH-045_wpe-purchase-green-sizes_20260929.php
```

Then refresh the theme cache and press Ctrl+F5. Check at 390 / 768 / 1440 px:

- **Home, category, product:** card «Купити» is on one line and bolder; the green is lighter than yesterday.
  Product «У кошик» is the same green; its hover is darker.
- **Mini-cart:** check it full and empty. The trigger and «Оформити замовлення» are the new green; «До каталогу»
  stays white.
- **Checkout:** «Підтвердити замовлення →» is 17px, on one line at 390 px, new green, darker on hover.
- **Success page** (logged-in order): «Переглянути замовлення» has white text in normal, hover and keyboard-focus
  states, with no underline.
- Then run **bs-checkout-smoke**.

Rollback: copy the three files back from `_patch_backups/TECH-045_wpe-purchase-green-sizes_20260929-<ts>/`, refresh the
theme cache, Ctrl+F5.

## 5. Recorded, not changed

- Legacy `.product-thumb .button .btn-buy-full` greens: ds.css ~663 (`--bs-green`) and stylesheet.css #28a745
  `!important`. The current `thumb.twig` does not render them.
- Out of scope per the handoff: preorder colour/label, mobile toast colour, `.bs-footer__legal`, heading order,
  `bs-menu__panel` aside role.

Status: patch ready, not deployed; Notion stays `In progress`. Review: Claude (chat).
