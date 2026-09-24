# PAY-002 — addendum: pre-deploy polish (round-4 non-blocking notes)

Date: 2026-08-30
Executor: Codex (same executor as rounds 1–4; do not swap mid-task)
Parent: `handoffs/handoff_PAY-002_pumb-checkout-card-token-gate_20260828.md`
Review: `diagnostics/PAY-002_pumb-checkout-card_review-round4_20260830.md`

Round 4 verdict was `Deploy OK; є неблокуючі зауваження`. The owner elected to
clear the non-blocking notes before deploying. Nothing here is a defect in the
current behaviour — these are correctness-of-form fixes plus one real gap in the
verification itself.

## Scope

Only these two files:

- `patches/PAY-002_pumb-preview-token-gate_20260828.php`
- `patches/PAY-002_pumb-checkout-card_20260828.php`

Same filenames, same work-package split. Do not touch the Twig edits, the drawer
replacement, the five counted replacements, the term persistence, or the
generated `index()` — all reviewed and accepted. The generated Twig must come out
byte-identical to the round-4 output.

Do not deploy, upload, commit or push. Fixture verification only.

## P1 — remove the dead `$payable` threading (WP2)

`$payable` is passed by value, so `$payable ??= $this->pay002CheckoutPayable()`
inside a gate assigns only to that gate's own copy. The caller's variable stays
`null` and the second gate resolves the total itself. Correctness is carried
entirely by the memoised `$pay002_checkout_payable` property; the argument
threading reads as if a value is shared when it is not, and it costs two extra
anchors in the shared live checkout controller.

Remove the threading rather than repairing it:

- In the `PUMB gate` replacement, drop `$payable = null;`. Restore
  `$pay001_gate ??= $this->pay001MonoChastGate();` and call
  `$pay002_gate = $this->pay002PumbGate();`.
- Drop the `shared payable parameter` `rx()` entirely — `pay001MonoChastGate()`
  keeps its original signature, one less edit to a shared file.
- In the `shared payable` replacement inside the mono gate, use
  `$payable = $this->pay002CheckoutPayable();` instead of `??=`. Its position is
  already correct: after the `configured` and preorder early returns.
- In `pay002PumbGate()`, drop the `?float $payable = null` parameter and resolve
  `$payable = $this->pay002CheckoutPayable();` at the same place it is resolved
  now — after its own `configured` and preorder short-circuits.

Behaviour must not change: one coupon/totals pass per request via the memoised
property, zero passes when both providers short-circuit.

## P2 — bound the confirm-gate assertion to the `confirm()` body (WP2)

The current assertion is one `/s` regex spanning the whole file, so it would
still pass if the gate were relocated to a method below `confirm()`. This is the
check that has to catch a repeat of the round-3 deletion, so it should be exact.

Replace it with a bounded check:

- locate `    public function confirm(): void {` and take the slice up to the next
  method declaration at the same indentation (`\n    public function ` /
  `\n    private function `);
- inside that slice assert: exactly one occurrence of the language load, exactly
  one occurrence of `if (!$this->pay002Available() || !$orderId)`, and the
  language load's offset lower than the gate's;
- keep both call sites — preflight on the in-memory controller before any write,
  and post-write on the file re-read from disk;
- keep `generated_confirm_gate=ok` printed only after the post-write check
  passes, still inside the `try` so a failure restores all three targets;
- each failure message names which of the three conditions failed.

## P3 — clean failure output (WP1 and WP2)

`fail()` throws, which is correct and must stay — it is what makes the restore
block reachable. But a failure before the inner `try` has no handler, so the
runner dies with an uncaught `RuntimeException` and a stack trace instead of the
single clear line `AGENTS.md` C1 asks for.

Wrap the script body — everything after the function declarations — in a
top-level `try { … } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }`.
The existing inner `try`/`catch` must still run first and restore the files; the
outer handler only formats the exit. The `already_applied=yes` early `exit(0)`
path must keep working.

## Verification required

Fresh fixture from `backup-8.28.2026_13-26-46_boosters.tar.gz`, WP1 → WP2, plus:

1. All round-4 output lines reproduced, `generated_confirm_gate=ok` included.
2. Generated `catalog/controller/checkout/payment_method.php` contains no
   `?float $payable` parameter and no `$payable = null;` inside
   `getBoosterCheckoutPaymentMethods()`.
3. Coupon/totals call count: 0 with both providers unconfigured, 1 when PUMB is
   eligible and both gates are read.
4. Generated Twig byte-identical to the round-4 generated Twig — state the
   comparison result explicitly.
5. **P2 negative test:** take the generated PUMB controller, move the gate line
   out of `confirm()` into a later method, re-run the assertion, confirm it now
   fails. Fixture only.
6. **P3 negative test:** force a preflight failure (run from a directory without
   `config.php`) on both runners; output must be one `ERROR: …` line, exit code
   1, no stack trace.
7. Repeat runs still print `already_applied=yes`.

Report the results as an appended section in
`diagnostics/PAY-002_pumb-checkout-card_report_20260828.md`. State plainly which
assertions were executed against generated files versus asserted from the runner
source — round 3 recorded a green line for a control that did not exist, and that
is the failure mode this project cares about most.

## Out of scope — decide before doing anything

The fourth round-4 note was: PUMB has no "unavailable" row, so a customer below
the minimum sees nothing for PUMB where monobank shows a reason. Closing it means
exporting a `pay002_credit_gate` and reworking the blocked-credit row in the
Twig, which currently hardcodes `mono_chast.mono_chast_3`, `pay001Credit: true`,
`monoOptions: []` and the mono gate messaging. That is a new UI behaviour on the
live checkout, not polish, and it does not belong in a patch already through four
review rounds.

Do not include it here. If the owner wants it, it becomes WP3 with its own
handoff, patch file and review.

## Delivery

Two corrected runners into `patches/` under the same filenames, plus the report
section. Executor does not commit, push, upload or deploy; the owner runs
WP1 then WP2 on production after the next review round.
