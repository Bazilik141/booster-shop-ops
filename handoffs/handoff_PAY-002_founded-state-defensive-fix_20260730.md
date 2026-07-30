# Codex Handoff — PAY-002: accept `FOUNDED` alongside `FUNDED` as the funded state

Date: 2026-07-30 | Parent: PAY-002 (In progress, disabled) — small follow-up fix,
not new scope.

**Risky zone (AGENTS.md): payment / order state.** No live traffic affected —
`payment_pumb_credit_status` stays `0`. This is a correctness fix for when
the method is later enabled, not an urgent production issue today.

## Context

The bank's state name for "financed / funds transferred" has been spelled
two different ways by two independent sources: the original 21.07 protocol
archive used `FOUNDED` (previously assumed to be a typo), and the 2026-07-28
round-2 written answer used `FUNDED` (treated as authoritative at the time).
On 2026-07-30, Roman Nazarenko stated directly and unprompted, in the same
message thread as the other round-4 answers: **"у вас фінальний статус має
бути FOUNDED. Це значить що заявка профінансована (тобто гроші відправлені
на рахунок)."** This is now two independent human sources using `FOUNDED`,
against one written answer using `FUNDED` — genuinely unresolved, not
confidently one or the other.

Full context: `plans/PAY-002_pumb-protocol-revision_20260727.md` §7d, "New
finding — likely wrong state-name spelling in deployed code."

The deployed controller checks this exact string in exactly one place:
`extension/pumb_credit/catalog/controller/payment/pumb_credit.php`,
`applyOrderStatus()` (originates from `patches/PAY-002_pumb-credit-skeleton_20260728.php`
line 261):

```php
$key = $state === 'WAITING_CLIENT' ? 'waiting_client' : ($state === 'WAITING_STORE_CONFIRM' ? 'waiting_store' : ($state === 'FUNDED' ? 'funded' : ($state === 'REFUND_FINISHED' ? 'returned' : (in_array($state, [...failed bucket...], true) ? 'failed' : ''))));
```

If the bank's real API response uses `"FOUNDED"`, this never matches,
`$key` falls through to `''`, `payment_pumb_credit_status_` (empty suffix)
resolves to nothing, and the method returns without ever updating the order
status — silently. No error, no log, just an order stuck at "очікує видачі"
forever after the bank has actually funded it. This is a live-money-adjacent
state; a silent miss here is worse than a loud failure.

## Scope (what to change)

- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` — in
  `applyOrderStatus()`, change the `FUNDED` comparison to accept **both**
  spellings:

  ```php
  in_array($state, ['FUNDED', 'FOUNDED'], true) ? 'funded' :
  ```

  in place of the current `$state === 'FUNDED' ? 'funded' :`. Every other
  branch in that ternary chain stays exactly as-is.
- Confirm this is the *only* place in the deployed controller (and admin
  controller, if it has any parallel state-check logic — verify against the
  live file, do not assume it matches the skeleton patch source verbatim
  after the two prior patches) that compares against the literal string
  `FUNDED`. If there is a second occurrence anywhere (e.g. in a manual admin
  action, a display label, or a log message), apply the same dual-acceptance
  fix there too, or explicitly note in the report why it doesn't need it.
- Patch runner: same conventions as the two prior PAY-002 patches — anchor
  the exact current live text before replacing (do not assume the line
  number above is still accurate after the two prior patches), `php -l`
  gate, backup + restore-on-failure, idempotency marker, self-delete on
  `done=ok`.

## What NOT to touch

- Do not change `WAITING_CLIENT`, `WAITING_STORE_CONFIRM`,
  `REFUND_FINISHED`, or the failed-state bucket array — only the `FUNDED`
  comparison.
- Do not flip `payment_pumb_credit_status` on, touch checkout UI, or touch
  `mono_chast`.
- Do not guess and hardcode `FOUNDED` as a *replacement* for `FUNDED` —
  accept both, since neither source is 100% authoritative yet (live
  confirmation is still pending test-contour access).

## Acceptance criteria

- [ ] `php -l` passes on the changed file.
- [ ] A simulated/fixture state value of `FUNDED` still resolves to the
      `funded` status key (no regression).
- [ ] A simulated/fixture state value of `FOUNDED` now also resolves to the
      `funded` status key (the actual fix).
- [ ] No other state string comparison in the file was altered.
- [ ] `payment_pumb_credit_status` unchanged (`0`) after the patch runs.
- [ ] Report confirms whether any second `FUNDED`-comparison site was found
      elsewhere in the extension and how it was handled.

## Owner QA after deploy

- [ ] Confirm `done=ok`, backup path present.
- [ ] Admin → Extensions → Payments → PUMB settings still opens/saves
      without error.
- [ ] `payment_pumb_credit_status` still `0`.
- [ ] No behavior change expected today — this only matters once real bank
      responses start arriving in test contour; note it as closed and move
      on unless something looks different.

## Recommended status after execution

- `PAY-002` stays **In progress**. This closes the `FOUNDED`/`FUNDED`
  ambiguity risk pre-emptively; still waiting on bank OAuth2 credentials and
  test phone numbers before any live/test-contour verification is possible.
  Once a real bank response is captured, update
  `plans/PAY-002_pumb-protocol-revision_20260727.md` §7d with the confirmed
  exact spelling for the record.
