# PAY-002: preserve the requested bank in checkout auto-selection

Date: 2026-08-31
Risk: checkout/payment JavaScript; owner deployment and live QA pending.

## Scope and evidence

The owner reported that after the successful autoselect deployment at
`2026-08-31T07:53:04+00:00`, PUMB still did not become selected. The screenshot
shows Mono 3 active, while the PUMB summary uses 4 payments.

Source was reconstructed from the owner's
`booster-debug-pay002-autoselect-20260831.tar.gz` (SHA-256
`a585d4670c8f34e468d6e30425348b41bf553cddd3c1dc9de2dde5e72530e2ce`)
by applying the corrected `PAY-002_pumb-product-autoselect_20260831.php` in an
isolated local fixture. All three resulting file hashes exactly matched the
owner's successful deployment log. This provides a verified deployment image,
not an assumption that the original archive was already patched.

The owner-requested continuation authorizes this narrow checkout fix despite
the original product-page handoff excluding the checkout payment template.
No dashboard/Notion status changes, commits, pushes, production writes or bank
calls were performed by the agent.

## Root cause and implementation

In `catalog/view/template/checkout/payment_method.twig`,
`flattenPaymentMethods()` merged both providers into one installment option.
PUMB set `fromModal` and its preferred term but did not assign the option's
`code`. The Mono branch assigned its own code unconditionally (pre-fix line 170).
Consequently `renderPaymentMethods()` passed `mono_chast.mono_chast_3` to the
actual `savePayment()` call for a PUMB-4 request. The issue was reproduced in
the executable local behavior test before writing the fix.

Only two JavaScript statements change:

1. An available PUMB group with `pay002_from_modal` assigns its preferred option
   code to the unified credit option.
2. The Mono fallback assignment does not overwrite an already-established
   modal choice. This works in either provider iteration order.

Markup structure, styling, payment arithmetic, configuration gates, controller
metadata, order creation, API contracts, and callback processing are unchanged.
There are no new timers, requests, CSS overrides, `!important`, positioning
rules or pixel values. The reported live loading delay was not measured; the
local test verifies one automatic save and no additional save on refresh.

## Artifacts and guarded production files

- `patches/PAY-002_pumb-provider-selection_20260831.php` — new standalone runner.
- `scripts/tests/pay002-provider-selection.test.mjs` — executable regression test.
- This report and a correction to the previous autoselect report.

Only `catalog/view/template/checkout/payment_method.twig` is written:

| Image | SHA-256 |
|---|---|
| Before | `fb57eef71fe3141d4b7d2713ecc2c465ab371476786f491399a4d195764d0f3b` |
| After | `efee1a60c2cecc7547787646690cc00fc01a428f9a2e9a64d9f08d464db6d396` |

Read-only prerequisite guards also verify `checkout.php` =
`96ef34ad3c0c2b3c980d6ffe54953da3f66e2cd1320f9f549305f5c3f98289c9`
and `payment_method.php` =
`0497216e9267f92c385ea43b43e09fc0a9531ff5df71a6e7d954d6f816c9b79c`.
The source's own line endings are preserved; replacement bytes do not depend
on Windows/Linux `PHP_EOL`.

## Local verification

The harness executes the actual template JavaScript with stubbed DOM, AJAX,
and checkout services. It checks the request passed to `$.ajax`, generated
radio value, active bank/term button, saved hidden code, and subsequent refresh.
It does not send a request to the store or create an order.

```text
baseline_bug_reproduced=PUMB_4_saves_and_highlights_MONO_3
behavior_cases_passed=46
actual_Twig_JS_parsed=yes
mocked_network_only=yes
```

Coverage: Mono and PUMB terms 3/4/5, either provider order, one-provider mode,
immediate or deferred delivery readiness, direct entry with no automatic
selection, unavailable requested bank, saved explicit selection, and manual
provider switching. Refresh after a successful auto-save adds no save request.

`php -l` passed for the runner. The generated Twig script passed `node --check`
and VM compilation. The clean fixture run returned `assertions=ok`, `done=ok`,
`database_touched=no`, and `self_delete=ok`. Re-uploading and running the same
runner returned `already_applied=yes` and self-deleted. A source-drift fixture
failed before any write/backup, preserved the target hash, and retained its
runner. The final generated diff contains only the two statements and one
explanatory comment above.

The fixture test can be rerun against an extracted/reconstructed template:

```text
node scripts/tests/pay002-provider-selection.test.mjs <path-to-payment_method.twig>
```

This is local logic/HTML evidence, not live browser, layout, bank, or timing
proof. No CSS/structure changes require new breakpoint-specific rendering, but
normal owner desktop/mobile checkout smoke remains part of deployment QA.

## Rollback and owner execution

Backup: `_patch_backups/PAY-002_pumb-provider-selection_20260831-<timestamp>-<suffix>/`.
Restore its `catalog/view/template/checkout/payment_method.twig` to the same
relative production path, then clear the OpenCart template cache. No database
rollback is needed; the prior autoselect controller metadata remains intact.

Upload the NEW runner filename to `~/public_html`, then:

```bash
cd ~/public_html || exit
php PAY-002_pumb-provider-selection_20260831.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Owner QA remains pending:

- Start again from the product modal: PUMB 4 or 5, then Add and checkout.
  Confirm PUMB has the only active term button; Mono has none.
- Repeat Mono once. Change bank/term manually and confirm a totals refresh
  does not revert the selection.
- Check direct checkout entry and the regular checkout smoke. Do not create a
  real bank order unless separately authorized.
