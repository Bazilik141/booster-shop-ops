# PAY-002 — patch review round 5 (WP1 + WP2), pre-deploy polish

Date: 2026-08-30
Reviewer: Claude (chat), read-only. Nothing run, uploaded, committed or deployed.
Inputs: both runners rev 2026-08-30 09:35,
`diagnostics/PAY-002_pumb-checkout-card_report_20260828.md` rev 09:39,
addendum `handoffs/addendum_PAY-002_pumb-checkout-card-polish_20260830.md`,
rounds 1–4.
Live evidence: `backup-8.28.2026_13-26-46_boosters.tar.gz`, read-only extraction
of `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`.

## Verdict

**Deploy OK.** All three polish items are implemented as specified and verified
against the live file, not accepted from the report. No new findings.

## P1 — dead `$payable` threading removed

- Caller is back to `$pay001_gate ??= $this->pay001MonoChastGate();` with
  `$pay002_gate = $this->pay002PumbGate();` — no `$payable = null;`, no argument.
- The `shared payable parameter` `rx()` is gone; `pay001MonoChastGate()` keeps its
  original signature, one less edit to the shared checkout controller.
- Both gates resolve `$payable = $this->pay002CheckoutPayable();` after their own
  `configured` and preorder short-circuits. Verified on the live file that the
  replaced region inside the mono gate sits after both of its `return $gate;`
  early exits, so the zero-pass case is preserved.
- `pay002PumbGate()` has no parameter and initialises `'payable' => 0.0` before
  its short-circuits.

## P2 — confirm-gate assertion bounded to the method body

`assertPay002ConfirmGate()` locates `    public function confirm(): void {`,
finds the next same-indentation method declaration, slices the body, and asserts
three things separately: exactly one language load, exactly one
`if (!$this->pay002Available() || !$orderId)`, and the language load's offset
lower than the gate's. Each failure names which condition failed. It runs on the
in-memory controller before any write, and again on the file re-read from disk
after the writes; `generated_confirm_gate=ok` is printed only after the second
one, and both sit inside the `try`, so a failure restores all three targets.

Reproduced independently against the live 2026-08-28 controller:

- WP1 output → language count 1, gate count 1, ordering correct → assertion passes.
- Same file with the gate relocated out of `confirm()` into `callback()` → gate
  count 0 inside the body → assertion fails.

That is exactly the round-3 failure mode, now caught. The executor's own negative
test recorded the same message (`gate count=0`, exit 1).

Residual, low: the boundary search requires a following method declaration, so if
`confirm()` ever became the last method in the class the assertion would fail as
"boundary missing" — a false negative rather than a false pass, and not the case
today.

## P3 — clean failure output

Both runners wrap their executable body in an outer
`try { … } catch (Throwable $e) { fwrite(STDERR, …); exit(1); }`. `fail()` still
throws, so the inner restore handlers run first and the outer one only formats
the exit. The `already_applied=yes` `exit(0)` path is unaffected. The executor's
negative test shows one `ERROR: …` line, exit 1, no stack trace, on both runners.

## Report accuracy

Checked and accurate this round. The Twig byte comparison is explicitly recorded
as executed on generated files with a SHA-256, not inferred from runner source —
which is the distinction round 3 got wrong. The negative-test output matches what
was reproduced here.

## Conventions

C1–C7 satisfied on both runners. C6 applies to WP1 only; header rollback SQL
matches the keys written. WP2 reports `database_touched=no`.
Risky zones: checkout · payment · order flow · DB (WP1).

## Deferred, tracked

- PUMB "unavailable" row → **PAY-005**, created 2026-08-30 on owner instruction
  (Notion `3cc6bf20-bdb4-8135-a249-c949fff3e7bb`, `ROADMAP_TASKS` mirror added).
  Hard gate before `payment_pumb_credit_public = 1` and before `PAY-001-SMOKE`.
- `pay001PreparePaymentChange()` remains monobank-only, so switching payment
  method away from a live PUMB transaction is unguarded. Observe during smoke.

## Перед запуском

**WP1 first, then WP2.** WP2 refuses to run without WP1's gate.

WP1 expected: `php_l=ok` ×3, `backup=…`, `changed=` four files,
`payment_pumb_credit_status_preserved=yes`, `done=ok`, `self_delete=ok`, plus the
two `oc_setting` rows if absent.

WP2 expected: `php_l=ok` ×2, **`generated_confirm_gate=ok`**, `backup=…`,
`changed=` three files, `database_touched=no`, `done=ok`, `self_delete=ok`.
Missing `generated_confirm_gate=ok` means the run failed and restored — stop.

C7: both self-delete on success; a repeat run needs a fresh upload.

After deploy: set the preview token in admin, enable
`payment_pumb_credit_status` for the test window only, confirm
`payment_pumb_credit_public` is off.

## Rollback

Reverse order — WP2, then WP1, from `_patch_backups/<PATCH_ID>-<ts>/`; WP1 also
needs its two settings rows deleted. Kill switch with no rollback: set
`payment_pumb_credit_status` back to disabled in admin.

## Смоук після

`bs-checkout-smoke`, full 11 steps, then `bs-deploy-verify`.
`bs-seo-risk-gate` on the WP1 preview route. Two extra cases no fixture can
prove: the forged confirm call from a session that never opened the preview URL,
and switching payment method away from a live PUMB transaction.
