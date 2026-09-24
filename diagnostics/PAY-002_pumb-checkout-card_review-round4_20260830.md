# PAY-002 — patch review round 4 (WP1 + WP2)

Date: 2026-08-30
Reviewer: Claude (chat), read-only. Nothing run, uploaded, committed or deployed.
Inputs: `patches/PAY-002_pumb-preview-token-gate_20260828.php` (unchanged since
2026-08-29 06:35), `patches/PAY-002_pumb-checkout-card_20260828.php`
(rev 2026-08-30 09:13), `diagnostics/PAY-002_pumb-checkout-card_report_20260828.md`
(rev 2026-08-30 09:20), rounds 1–3.
Live evidence: `backup-8.28.2026_13-26-46_boosters.tar.gz`, read-only extraction of
`extension/pumb_credit/catalog/controller/payment/pumb_credit.php` and
`catalog/controller/checkout/payment_method.php`.

## Verdict

**Deploy OK; є неблокуючі зауваження.** Both round-3 findings are fixed, and the
fix for the gate is verified against the live file rather than taken from the
report. Four low-severity notes remain; none blocks deployment.

## R3-1 — closed, and verified independently

WP2 no longer transforms `confirm()`. There is no mutator left in the runner: the
only references to the gate are two assertions built on

```php
$confirmGatePattern = '/public function confirm\(\): void \{.*?\$this->load->language\(\'extension\/pumb_credit\/payment\/pumb_credit\'\);.*?if \(!\$this->pay002Available\(\) \|\| !\$orderId\)/s';
```

- a preflight `need()` before any write, so WP2 refuses to run when WP1's gate is
  absent;
- a post-write check that re-reads the file it just wrote from disk and asserts
  the same pattern, printing `generated_confirm_gate=ok` only after it passes.
  The check sits inside the `try`, so a failure restores all three targets.

Verified by replaying WP1's three `confirm()` transformations against the live
2026-08-28 controller and testing the pattern against the result: the assertion
matches, the gate occurs exactly once, and the language load precedes it. This
is the check whose absence let round 3's deletion through — it now reads the
generated artefact, which is the right place for it.

## R3-2 — closed

The payable total is lazy again. The caller initialises `$payable = null` and
passes it to both gates; each gate resolves it with
`$payable ??= $this->pay002CheckoutPayable()` **after** its own configuration and
preorder short-circuits. Confirmed on the live file that the replaced region in
`pay001MonoChastGate()` begins after both of its `return $gate;` early exits, so
a store with monobank disabled performs no coupon/totals pass — the pre-patch
behaviour. `pay002PumbGate()` mirrors that shape and now initialises
`'payable' => 0.0` before its short-circuits.

## Non-blocking notes

1. **Dead plumbing.** `$payable = null;` is passed by value, so the `??=` inside
   each gate assigns only to that gate's own copy — the caller's variable stays
   `null` and the second gate resolves the total itself. Correctness is preserved
   solely by the memoised `$pay002_checkout_payable` property, which does keep it
   to one pass per request. The argument threading reads as if the value is
   shared and is not; drop it or pass by reference in a later pass.
2. **The gate assertion is not bounded to the `confirm()` body.** `.*?` under `/s`
   spans the file, so the pattern would still match if the gate were ever
   relocated to a method below `confirm()`. Adequate today (one `confirm()`, one
   gate); tighten if the file grows.
3. **Preflight failures still surface as an uncaught `RuntimeException`** with a
   stack trace instead of the clean `ERROR: …` line C1 asks for. Both runners.
   Cosmetic.
4. **PUMB has no "unavailable" row.** No `pay002_credit_gate` is exported, so a
   customer below the minimum sees nothing for PUMB, where monobank shows a
   reason. Not required by the handoff — follow-up.

Carried forward from the report and still true: the cross-method boundary
`pay001PreparePaymentChange()` remains monobank-only, so switching payment method
away from a live PUMB transaction is unguarded. The handoff permitted reporting
rather than widening scope; it belongs in the production smoke.

## Report accuracy

Round 3's report carried a green line for a control that did not exist. This
revision's claims were re-checked against the runner and the live file: no
confirm mutator present, the assertion reads the written file, the payable is
lazy. All three hold.

The self-QA matrix (status disabled → unavailable, public/preview combinations,
forged confirm refused before any DB or bank work, coupon/totals calls 0 and 1)
is the executor's own runtime-harness output on a local fixture. It was not
reproduced here; what was verified is that the code paths behind each line exist
and are consistent with the claim. It remains fixture evidence, not production
proof.

## Conventions

C1–C7 satisfied on both runners. C6 applies to WP1 only; the header rollback SQL
matches the keys the patch writes. WP2 reports `database_touched=no`.
Risky zones: checkout · payment · order flow · DB (WP1).

## Перед запуском

Order is fixed: **WP1 first, then WP2.** WP2 refuses to run without WP1's gate.

Expected WP1 output — `php_l=ok` ×3, `backup=…`, `changed=` four files,
`payment_pumb_credit_status_preserved=yes`, `done=ok`, `self_delete=ok`, plus two
new `oc_setting` rows (`payment_pumb_credit_preview_token`,
`payment_pumb_credit_public`) only if they are absent.

Expected WP2 output — `php_l=ok` ×2, **`generated_confirm_gate=ok`**,
`backup=…`, `changed=` three files, `database_touched=no`, `done=ok`,
`self_delete=ok`. If `generated_confirm_gate=ok` is missing, the run failed and
the files were restored — do not proceed.

C7: both runners self-delete on success. Re-running either one needs a fresh
upload; a second run against an already-patched tree prints `already_applied=yes`.

After deploy, before any customer traffic matters: set the preview token in
admin, enable `payment_pumb_credit_status` for the test window only, and confirm
`payment_pumb_credit_public` is off.

## Rollback

Reverse order — WP2, then WP1, from `_patch_backups/<PATCH_ID>-<ts>/`. WP1
additionally needs its two settings rows deleted. Kill switch with no rollback:
set `payment_pumb_credit_status` back to disabled in admin — that removes PUMB
from every surface immediately.

## Смоук після

`bs-checkout-smoke`, full 11 steps, then `bs-deploy-verify`.
`bs-seo-risk-gate` on the WP1 preview route.

Two cases to add to the run, because no fixture can prove them:

- call the PUMB confirm route from a session that never opened the preview URL —
  it must be refused before any bank call;
- select PUMB, create an application, then switch payment method — observe what
  happens to the live PUMB transaction (unguarded boundary, note above).
