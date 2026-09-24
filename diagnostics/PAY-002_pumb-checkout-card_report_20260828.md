# PAY-002 — PUMB checkout card fixture report

Date: 2026-08-29

## Scope

Processed `handoffs/handoff_PAY-002_pumb-checkout-card-token-gate_20260828.md`.
The newest backup was `backup-8.28.2026_13-26-46_boosters.tar.gz`. Only the four
relevant OpenCart source files were extracted into an isolated local fixture:

- `catalog/controller/checkout/payment_method.php`
- `catalog/view/template/checkout/payment_method.twig`
- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`
- `extension/pumb_credit/catalog/model/payment/pumb_credit.php`

The WP2 runner was corrected for the deployed Twig shape. Its `PUMB drawer match`
anchor now accepts the actual `match(/mono_chast_(\d)/)` expression, and the
inserted JavaScript fragments use valid whitespace rather than literal `\\n`
text. No production files were run or changed.

## WP2 fixture result

The corrected runner passed against the extracted backup fixture:

```text
php_l=ok file=.../catalog/controller/checkout/payment_method.php
php_l=ok file=.../catalog/view/template/checkout/payment_method.twig
php_l=ok file=.../extension/pumb_credit/catalog/controller/payment/pumb_credit.php
database_touched=no
done=ok
self_delete=ok
```

The generated files contain the expected server/UI wiring:

- PUMB is added only through `pay002PumbGate()` and `pay002PumbMethod()`.
- PUMB option codes are `pumb_credit.pumb_credit_3`, `_4`, and `_5`.
- Selected terms persist as `pay002_pumb_credit_term`.
- `index()` passes `term` to the existing PUMB confirm route.
- `confirm()` has the fail-closed `pay002Available()` gate and retains independent
  `requestedTerm()` validation.
- The static `СКОРО БУДЕ` PUMB card remains in the drawer fallback markup.
- PUMB credentials use API base, OAuth URL/login/password,
  `point_of_sale_code`, and `partner_name`; no PUMB `store_id` or `store_secret`
  fields were introduced.

Embedded JavaScript validation also passed:

```text
twig_embedded_js=ok blocks=1
```

## WP2 idempotency

Re-running the runner against the patched isolated fixture returned:

```text
already_applied=yes
```

## WP1 static-only result

WP1 was not executed because it requires a live DB write and the handoff
explicitly prohibits live DB execution at this stage. The runner passed:

```text
No syntax errors detected in patches/PAY-002_pumb-preview-token-gate_20260828.php
wp1_static=ok no_pumb_store_fields=yes
```

Static checks confirmed the preview/public settings, `hash_equals()`, session
preview flag, clean redirect path, and the actual PUMB credential field names.

## Files changed locally

```text
patches/PAY-002_pumb-checkout-card_20260828.php
diagnostics/PAY-002_pumb-checkout-card_report_20260828.md
```

The existing unrelated dirty files were preserved. No OpenCart generated source
was written into the repository.

## Rollback / deployment status

No production deployment, upload, DB operation, commit, or push was performed.
The isolated fixture backup was created by the runner at:

```text
_pay002_fixture_source/_patch_backups/PAY-002_pumb-checkout-card_20260828-20260829-050242/
```

The owner must still review WP1 and run WP1 → WP2 on production only after the
local fixture pass and then perform the handoff's full checkout smoke plan.

## Risks / remaining gates

- This is local fixture evidence, not production proof.
- WP1 live DB/settings and preview redirect QA remain owner-gated.
- Bank application, transaction persistence, callback behavior, and full checkout
  regression remain unverified in production.
- The existing `pay001PreparePaymentChange()` boundary was preserved; no separate
  PUMB transaction-switch guard was added in WP2 because this patch only creates
  the PUMB selection/term path and does not alter the existing payment lifecycle
  boundary.

## Revision after 2026-08-29 review

The review blockers were returned to the original executor and corrected:

- both runners now throw on failed writes/lint so the catch rollback executes;
- WP1 uses full OpenCart setting keys and matching rollback cleanup;
- WP1 and WP2 compose in the mandated order, with one shared
  `pay002Available()` method;
- WP2 validates exact anchor counts and lints only generated PHP files;
- the PUMB drawer is conditional and branded dynamically, while `СКОРО БУДЕ`
  remains only for the unavailable branch;
- PUMB selection matching, totals transport, unique confirm ID, redirect and
  visible error handling were corrected;
- WP1's public checkbox now posts explicit `0` when unchecked;
- PUMB gate reuse takes the already computed checkout payable total rather than
  running a second coupon/totals pass.

Fresh isolated sequence result:

```text
WP1: done=ok, self_delete=ok
WP2: done=ok, database_touched=no, self_delete=ok
WP1 repeat: already_applied=yes
WP2 repeat: already_applied=yes
generated PHP lint: ok
embedded Twig JavaScript: ok
generated assertions: ok
```

This remains local fixture evidence only. No production upload, deployment,
commit, or push was performed.

## Revision after 2026-08-29 round-2 review

Round-2 findings were addressed by the original executor:

- WP2 now emits the generated confirm JavaScript URL values using PHP `.` concatenation; a bounded runtime expression check passed.
- Checkout payable is computed once through `pay002CheckoutPayable()` before the paired gate calls and passed into both gates; the cached helper prevents a second coupon/totals pass.
- The PUMB confirm gate is placed after its language file is loaded, and the duplicate WP1 gate in the confirm path is removed during WP2 composition.
- The five final Twig replacements now use explicit counted anchors; the actual fixture count for `selected.pay001Credit` is two and is asserted as two.

Round-2 verification on a fresh fixture extracted from
`backup-8.28.2026_13-26-46_boosters.tar.gz`:

```text
WP1 -> WP2: done=ok, database_touched=no
generated catalog/controller/checkout/payment_method.php: php -l ok
generated catalog/view/template/checkout/payment_method.twig: embedded JS ok
generated extension/pumb_credit/catalog/controller/payment/pumb_credit.php: php -l ok
generated admin/controller/payment/pumb_credit.php: php -l ok
confirm language-load precedes the single server gate: ok
shared payable helper count=1; coupon preparation count=1; shared gate args=2
WP1 repeat: already_applied=yes
WP2 repeat: already_applied=yes
```

The temporary `_pay002_fixture_source` fixture was used only for local
verification and is not an OpenCart deployment. No production upload,
deployment, database operation, commit, or push was performed.

## Revision after 2026-08-30 round-3 review

Round-3 findings were addressed:

- WP2 no longer transforms `confirm()` at all. It asserts that WP1 already supplied the single gate in `confirm()`, and then preserves that generated method unchanged.
- The fixture assertion now checks the actual generated PUMB controller after WP2 writes it. It requires the language load and `pay002Available()` gate in `confirm()` and emits `generated_confirm_gate=ok` only after reading the written file.
- Shared payable calculation is lazy again. The checkout initializes the shared argument as `null`; each configured gate requests the memoized total only after its own configuration/preorder short-circuit. With both providers disabled, no coupon/totals pass is introduced.

Fresh local fixture evidence from
`backup-8.28.2026_13-26-46_boosters.tar.gz`:

```text
WP1 -> WP2: done=ok, database_touched=no
WP2: generated_confirm_gate=ok
confirm block: gate count=1, language load precedes gate=yes
WP2 source: no confirm mutator present=yes
shared payable helper count=1; prepareCouponTotal count=1; lazy guard=yes
generated PHP lint: payment_method.php ok; PUMB controller ok; admin controller ok
embedded Twig JavaScript: ok (1 script block)
WP1 repeat: already_applied=yes
WP2 repeat: already_applied=yes
```

This is local fixture evidence only. No production upload, deployment, live DB
operation, commit, or push was performed.

## Independent self-QA — 2026-08-30

An additional isolated runtime harness was run against a fresh WP1 -> WP2
fixture generated from `backup-8.28.2026_13-26-46_boosters.tar.gz`.

```text
PUMB status disabled: unavailable
PUMB credentials + public=0 + no preview: unavailable
PUMB credentials + session preview flag: available
PUMB credentials + public=1: available
PUMB index: selected session term 4 present in confirm URL
forged confirm without preview: error_unavailable before any DB/bank work
both providers disabled: coupon/totals calls = 0
eligible PUMB: gate available; coupon/totals calls memoized to 1 across two gate reads
generated PHP lint: all generated PHP targets ok
embedded Twig JavaScript: ok
```

Rollback smoke used a deliberately modified **fixture-only copy** of WP2 that
throws after writing its three targets. The runner exited non-zero as expected;
SHA-256 checks confirmed restoration of all three targets and no WP2 marker was
created. The shipped runner was not modified for this test.

No additional defect was found in this self-QA. This remains local evidence;
the owner-controlled production smoke, admin persistence, preview redirect,
real bank application, and forged-route production proof remain required.

## Round-4 pre-deploy polish — 2026-08-30

The owner elected to clear the non-blocking round-4 notes before deployment.
Only the two PAY-002 runners were changed; the Twig transformation block, drawer
replacement, term persistence, counted replacements, and generated `index()`
path were left unchanged.

- P1: removed pass-by-value `$payable` threading. Both gates now use the same
  request-local memoized helper lazily, after their own configuration/preorder
  short-circuits. The generated checkout controller has no `?float $payable`
  parameter and no `$payable = null;` in
  `getBoosterCheckoutPaymentMethods()`.
- P2: replaced the file-spanning confirmation regex with
  `assertPay002ConfirmGate()`. It isolates `confirm()` up to the next
  same-indentation method declaration and separately asserts one language load,
  one server gate, and the required order. It runs once on the in-memory source
  before any write and once after re-reading the generated controller from disk;
  only the latter emits `generated_confirm_gate=ok`.
- P3: both runners now wrap their executable body in an outer formatter that
  prints one `ERROR: ...` line with exit code 1. The inner rollback handlers
  still execute before the outer formatter.

Fresh fixture results from `backup-8.28.2026_13-26-46_boosters.tar.gz`:

```text
WP1 -> WP2: done=ok; WP2 generated_confirm_gate=ok
generated PHP lint: checkout controller, PUMB catalog controller, PUMB admin controller = ok
embedded Twig JavaScript: ok
generated checkout: ?float payable parameter = absent; payable = null = absent
both providers unconfigured, both gates read: coupon/totals calls = 0
eligible Mono + PUMB, both gates read: coupon/totals calls = 1 / 1
WP1 repeat: already_applied=yes
WP2 repeat: already_applied=yes
```

Twig byte comparison was executed on generated files, not inferred from runner
source. A reconstructed round-4-equivalent baseline (only P1 controller-output
changes reversed; Twig transform block unchanged) and the polished fixture both
produced SHA-256
`0D18197B0D58780725CD979ED3D465C198F054CDDCB6CC1EE629DFFDE214E9B2` for
`catalog/view/template/checkout/payment_method.twig`.

Negative tests:

```text
gate moved from confirm() to callback() fixture mutation ->
ERROR: confirm gate assertion failed: gate count=0; exit=1

WP1 without config.php ->
ERROR: Run from OpenCart public_html (config.php missing).; exit=1; no stack trace

WP2 without config.php ->
ERROR: Run from OpenCart public_html (config.php missing).; exit=1; no stack trace
```

No production upload, deployment, live DB operation, commit, or push was
performed.
