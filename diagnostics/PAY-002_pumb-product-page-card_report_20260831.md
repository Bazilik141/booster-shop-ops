# PAY-002 WP5 — PUMB product-page card and modal

Date: 2026-08-31

## Scope

Prepared one file-only production runner from the fresh owner archive
`C:\Users\14bez\Downloads\booster-debug-files.tar.gz` (archive SHA-256
`0243a6ce09742897f4829cbdb53eda213cd89c3ada075002d8d50f22ea23c0db`).
It contained exactly the three handoff targets.

The PUMB product card is visible only under the existing status + UAH + six
credentials + (public or preview-session) predicate. Its 3/4/5 terms use
`payment_pumb_credit_terms`; PUMB makes no commission claim. The PUMB modal
uses `pumb_credit_term`, and the checkout controller clears a stale preference
from the other credit provider before it stores the requested provider term.

Closed-gate legacy teaser, modal, and JavaScript are retained from the supplied
source in a separate Twig branch. Local structural verification reported
`legacy_closed_gate_branches=byte_identical_source=ok`.

## Files touched by the runner

```text
catalog/controller/product/product.php
catalog/view/template/product/product.twig
catalog/controller/checkout/checkout.php
```

Runner: `patches/PAY-002_pumb-product-page-card_20260831.php`

No database, setting, credential, PUMB extension, checkout-drawer, CSS, SEO,
CRM, deployment, commit, push, or roadmap-status change occurred.

## Local validation

Fresh-file SHA-256 gates:

```text
product.php    4c33564176c8363dc1156861a1bfd9eb3647ffd62103c20c7314b420fda232c0
product.twig   4b2744fc1117db1bd97cb922eaf19fe2c036396b5a871b73e1fc6b493913f3d6
checkout.php   fd5943d20bbe437e66af7a3b9893c2134ede8d212564eb318024c947c29e5b60
```

Final isolated fixture run:

```text
php_l=ok product.php
php_l=ok checkout.php
after_sha256 product.php   cd9624e3c7d1d0698dff0723935599e1cc7342d701610f324a7319fce0b33d82
after_sha256 product.twig  1f705580684ba8ffcb70fb279d0ac6a288dfeae14d051ac4cf2263764c5b6ec4
after_sha256 checkout.php  39bb445651806fde1451bd8d47531c9040fc7d4f926fda123e24e55ee5027c10
assertions=ok
database_touched=no
done=ok
self_delete=ok
```

Also passed: runner `php -l`; generated PUMB-enabled modal `node --check`; and
repeat execution (`already_applied=yes`). These are local/static checks only,
not bank, callback, production-theme, or production-checkout proof.

## Rollback

The runner creates `_patch_backups/PAY-002_pumb-product-page-card_20260831-<ts>/`
before writes. On a write, hash, lint, or assertion failure it restores all three
files and verifies their original SHA-256. Manual rollback: restore those files,
then clear cache. Immediate no-file kill switch: disable
`payment_pumb_credit_status` in admin.

## Owner run command

```bash
cd ~/public_html || exit
php PAY-002_pumb-product-page-card_20260831.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy owner QA

- [ ] PUMB status on, public off, ordinary visitor: product teaser and modal
  still render `СКОРО БУДЕ`.
- [ ] Preview URL: PUMB row and neutral PUMB card render with allowed terms.
- [ ] PUMB term 4 → `Додати й оформити`: item is added and checkout preselects
  PUMB term 4.
- [ ] Mono 3/4/5 modal regression still arrives with `mono_chast_parts`.
- [ ] Mono disabled and PUMB preview-visible: PUMB-only block has no console JS
  error.
- [ ] Run complete `bs-checkout-smoke`, explicitly including Mono regression.

`bs-seo-risk-gate` is not required: no new URL or indexable content is added;
the product markup changes only for gated sessions. `PAY-002` remains In progress;
bank-side term proof remains owner-gated.
