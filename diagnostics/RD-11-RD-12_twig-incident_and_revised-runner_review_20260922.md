# RD-11 / RD-12 — Twig incident and revised-runner review

Date: 2026-09-22  
Reviewer requested: Claude  
Status: local revision ready for review; **do not re-upload or run either runner until review is accepted.**

## Context and authorized scope

The owner authorized two separate PHP runners only:

- `patches/RD-11_cart-page_20260921.php`
- `patches/RD-12_minicart-toast_20260921.php`

They must remain separate. No DB, checkout flow, payment, price calculation, deployment, commit, or push changes are authorized. The only controller exception remains exactly two display-only assignments in each named controller: the Nova Poshta free-shipping threshold (fallback `2000.0`) and raw pre-coupon `sub_total`.

Fresh target evidence: `rd11-rd12-live-files_20260921-182837.tar.gz` (2026-09-21).

## Production incident and recovery

The owner ran the original RD-11 and RD-12 runners. Both completed their PHP/JS checks but introduced Twig syntax errors.

1. After RD-12, every storefront page failed because `common/cart.twig` is rendered from the header. The visible wrapper error was `Could not load template common/cart`.
2. The owner restored all five RD-12 targets from the runner-created `_patch_backups/RD-12_minicart-toast_20260921-<timestamp>/`; the storefront recovered.
3. The cart page still failed with `Could not load template checkout/cart` after RD-11.
4. The owner restored all four RD-11 targets from `_patch_backups/RD-11_cart-page_20260921-<timestamp>/`; the cart recovered.

No production files are currently intended to retain RD-11/RD-12 changes. No commit, push, or deployment was made by Codex.

## Second preflight incident (2026-09-22) and recovery status

The first revised RD-12 runner was uploaded and started by the owner. It printed `cwd` and `time`, then stopped with Composer’s platform check:

```text
Your Composer dependencies require a PHP version ">= 8.1.0". You are running 8.0.30.
```

This happened before `twig_compile=ok`, `backup=`, any target write, or cache clearing. It therefore introduced no new file change. RD-11 was not run.

Cause: the first revised preflight directly required `DIR_STORAGE/vendor/autoload.php`. The production CLI is PHP 8.0, while Composer’s generated platform check requires PHP 8.1. This was incompatible with the project’s PHP-8.0 requirement and must not be used.

## Why the storefront error had no useful line number

The owner supplied the deployed `system/library/template/twig.php`. It calls `Twig\Environment::render()` at line 119 and catches `Twig\Error\SyntaxError` at lines 120-121, then throws only:

```text
Error: Could not load template <filename>!
```

The inner parser error is deliberately discarded by the OpenCart wrapper. The page stack traces therefore correctly named the affected template but could not identify its source line.

## Reproduced root causes

The original runners were applied to a clean fixture made from the fresh archive. Candidate Twig was parsed with the local Twig parser.

| Runner | Candidate file | Reproduced parser error | Root cause |
|---|---|---|---|
| RD-11 | `catalog/view/template/checkout/cart.twig` | `Unclosed comment` at line 21 | The inline CSS contained `@media(max-width:767.98px){#checkout-cart...`. Twig treats literal `{#` as the start of a Twig comment. |
| RD-12 | `catalog/view/template/common/cart.twig` | `Unexpected token "operator" of value "."` at line 9 | The Twig expression used `shipping_pinta_nova_poshta_free_from * .3`. Twig requires `0.3`, not a leading-decimal literal. |

`checkout/cart_list.twig` parsed successfully; the earlier top-level `{% else %}` correction was retained and is not the cause of this incident.

## Revised runners

### RD-11

- Changes `@media(...){#checkout-cart...` to `@media(...){ #checkout-cart...`, preserving CSS behavior while preventing the `{#` Twig comment token.
- Adds a pre-write `twigCompile11()` gate for the candidate `checkout/cart.twig` and `checkout/cart_list.twig`.
- The gate creates an in-memory `Twig\Environment` with `ArrayLoader`, tokenizes and parses each candidate, and reports the real template path, line, and parser message before backup or any write.

### RD-12

- Changes `* .3` to `* 0.3`.
- Adds the equivalent pre-write `twigCompile12()` gate for the candidate `common/cart.twig`.

### Shared pre-write behavior (second revision; PHP 8.0 compatible)

The owner supplied `public_html/system/vendor.php`. It reveals the actual OpenCart PHP-8.0-compatible Twig loading path:

```php
$autoloader->register('Twig', DIR_STORAGE . 'vendor/twig/twig/src/', true);
```

Both runners now mirror only that Twig registration themselves:

1. Read the `DIR_STORAGE` string from root `config.php` with a regex. `config.php` is not required or executed.
2. Require that `<DIR_STORAGE>/vendor/twig/twig/src/Environment.php` exists.
3. Register a narrow `Twig\` PSR-4 loader rooted at that directory.
4. Conditionally load the same optional `Resources/*.php` files named by `system/vendor.php`.
5. Tokenize and parse each candidate template in an in-memory `Twig\Environment`.

No Composer autoload file is required. If the storage path or Twig source is absent, the runner emits `dir_storage_missing_in_config` or `twig_source_missing` and exits **before backup and before writes**.

## Files touched locally

```text
patches/RD-11_cart-page_20260921.php
patches/RD-12_minicart-toast_20260921.php
diagnostics/RD-11-RD-12_twig-incident_and_revised-runner_review_20260922.md
```

The revised runners retain their original target lists:

```text
RD-11
catalog/controller/checkout/cart.php
catalog/view/template/checkout/cart.twig
catalog/view/template/checkout/cart_list.twig
catalog/view/stylesheet/boostershop-ds.css

RD-12
catalog/controller/common/cart.php
catalog/view/template/common/cart.twig
catalog/view/javascript/common.js
catalog/view/stylesheet/stylesheet.css
catalog/view/stylesheet/boostershop-ds.css
```

## Local verification evidence

Both revised runner files:

```text
No syntax errors detected in patches\RD-11_cart-page_20260921.php
No syntax errors detected in patches\RD-12_minicart-toast_20260921.php
```

Fresh fixture, sequential RD-11 then RD-12, using a Twig autoload path:

```text
twig_compile=ok files=2
... RD-11 backup, writes, target PHP lint, done=ok
twig_compile=ok files=1
... RD-12 backup, writes, target PHP lint, done=ok
```

The exact `DIR_STORAGE` regex was separately exercised against the production-shaped value and resolved the Twig source root below it:

```text
twig_source_root=/home2/boosters/ocartdata/storage/vendor/twig/twig/src/
```

Independent post-transformation parsing:

```text
twig_parse=ok file=cart.twig
twig_parse=ok file=cart_list.twig
twig_parse=ok file=cart.twig  # common/cart.twig
```

The fixture also confirmed runner self-deletion after successful completion. This is local/static evidence only; it is not production UI or AJAX proof.

## Rollback

Each runner backs up every target before writing to:

```text
_patch_backups/<runner-filename-without-.php>-<timestamp>/
```

The incident recovery used those backups successfully. If a future runner reaches the write phase and fails the target PHP lint, its catch block restores only the files it has written.

## Required Claude review

Please return **approve / return for changes** separately for RD-11 and RD-12, while keeping them paired for any later production retry.

1. Verify the root-cause claims above against the runner payloads and supplied `twig.php`.
2. Verify that Twig preflight is reached after all Twig transformations but before backup creation and any target write.
3. Verify the regex extraction of `DIR_STORAGE` and the narrow Twig PSR-4 loader against `system/vendor.php`; confirm no Composer autoload is invoked.
4. Parse-review all generated Twig, especially literal `{#` sequences in inline CSS/JS and numeric literals in Twig expressions.
5. Re-check the permitted controller assignments: exactly the two read-only display fields, raw pre-coupon `sub_total`, fallback `2000.0`, and no prohibited Nova Poshta helper calls.
6. Re-check RD-11’s top-level empty-cart anchor and RD-12’s two unique structural anchors.
7. Confirm no scope expansion into checkout, payment, price calculation, database, or unrelated files.
8. Do not treat fixture success as live acceptance. If approved, the owner should upload the revised runners and execute one at a time, confirming `twig_compile=ok` appears before any `backup=` line.

## Remaining risks and post-deploy acceptance

- The OpenCart wrapper’s `render()` hides Twig parser details; the runner-level preflight is now the diagnostic safety net.
- `DIR_STORAGE` extraction is intentionally read-only. It does not execute `config.php`, and it must remain that way; `config.php` can contain environment-specific definitions.
- The previous Composer preflight is rejected: it is incompatible with CLI PHP 8.0 and has been removed from both runners.
- No browser/UI/AJAX regression test has been run against production after recovery.

If Claude approves a re-run, acceptance requires:

- [ ] `twig_compile=ok` before `backup=` for the runner being executed.
- [ ] `done=ok` and target controller `php_l=ok`.
- [ ] Home page renders after RD-12.
- [ ] Cart page renders after RD-11.
- [ ] Cart quantity update/remove works; mini-cart add/remove/quantity works.
- [ ] Desktop and mobile visual checks match the supplied prototype.
- [ ] Theme cache is cleared only after a successful runner.
