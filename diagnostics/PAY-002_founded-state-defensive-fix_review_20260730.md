# Review report — PAY-002 FUNDED/FOUNDED defensive fix

Date: 2026-07-30
Scope: `patches/PAY-002_founded-state-defensive-fix_20260730.php` against
`handoffs/handoff_PAY-002_founded-state-defensive-fix_20260730.md` and
`diagnostics/PAY-002_founded-state-defensive-fix_report_20260730.md`

## Verdict

**Review OK. Cleared to deploy.**

## What was checked

- Live evidence: owner ran `grep -RIn --include='*.php' -E 'FUNDED|FOUNDED' extension/pumb_credit`
  against the deployed extension and got exactly one hit
  (`catalog/controller/payment/pumb_credit.php:199`) — confirms the handoff's
  assumption that this is the only comparison site, from the live file, not
  from the old skeleton source.
- Patch anchor (`$state === 'FUNDED' ? 'funded' :`) matches that live line
  exactly; replacement (`in_array($state, ['FUNDED', 'FOUNDED'], true) ? 'funded' :`)
  preserves the surrounding ternary shape — only the condition expression
  changes, nothing else in the chain (`WAITING_CLIENT`, `WAITING_STORE_CONFIRM`,
  `REFUND_FINISHED`, failed-bucket array) is touched.
- Pre-write guards: requires exactly one `$old` occurrence and zero `$new`
  occurrences before writing; post-write guard requires exactly one `$new`
  and zero `$old` after the replacement. No threshold-counting bug this
  round (unlike the prior guard patch's round-1 miscount) — this is a
  single fixed-string match, not a multi-occurrence count.
- Idempotency marker check is consistent with the actual post-write shape
  (checks for exactly 1/0, matching what gets written).
- No DB, setting, checkout, or credential change anywhere in the runner —
  confirmed by reading the full file, not just the report's claim.
- Backup + sha256 evidence, `php -l` gate, restore-on-failure, self-delete on
  `done=ok` — all present, matches established PAY-002 patch conventions.

## Not independently verified

- No live server access this session; the `grep` evidence and `php -l`
  results are trusted from the owner/Codex report, not re-run here.
- The actual bank spelling (`FUNDED` vs `FOUNDED`) is still unconfirmed —
  this patch deliberately doesn't need that answer, it accepts either.

## Owner QA

1. Upload and run `patches/PAY-002_founded-state-defensive-fix_20260730.php`
   from `~/public_html`; confirm `done=ok` and a `backup=` path.
2. Confirm `payment_pumb_credit_status` is still `0`.
3. No behavior change expected until a real bank response arrives in test
   contour — nothing else to check today.

Once test OAuth2 access exists and a real `state` value is observed, update
`plans/PAY-002_pumb-protocol-revision_20260727.md` §7d with the confirmed
spelling for the record (informational only — the code already handles
either case).
