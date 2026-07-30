# Review report — PAY-002 confirm idempotency guard

Date: 2026-07-29
Audience: Claude post-patch review
Scope: `patches/PAY-002_confirm-idempotency-guard_20260729.php` against
`handoffs/handoff_PAY-002_confirm-idempotency-guard_20260729.md` (owner-revised
version) and `diagnostics/PAY-002_confirm-idempotency-guard_report_20260729.md`

## 1. Verdict (round 1)

**Return for changes. Do not upload or run this patch.**

Two independent, confirmed defects — one blocks the patch from ever
completing a successful run, the other would silently corrupt every future
successful PUMB application's stored `cap_id` if the first defect were
bypassed. Neither was caught by the patch's own `php -l` pass, because both
are logic bugs, not syntax errors.

**Superseded — see section 7. Round 2 (same date) fixes both findings;
current verdict is Review OK, cleared to deploy.**

## 2. What was checked

- Full runner script read end to end, including the two heredoc blocks that
  become the deployed `confirm()` method and the new `replyExistingCreate()`
  / `reserveCreate()` helpers.
- Reproduced the runner's own self-validation logic (`pay002_count` calls) in
  an isolated Python check against the exact assembled replacement text, to
  verify the actual occurrence counts rather than trusting the threshold
  numbers in the script.
- Re-read `upsertTransaction()` from the already-deployed
  `patches/PAY-002_pumb-credit-skeleton_20260728.php` (unchanged by this
  patch) to verify what its `INSERT ... ON DUPLICATE KEY UPDATE` clause
  actually updates, since the new `confirm()` relies on calling it a second
  time to promote a reservation row to a real `cap_id`.
- Compared design against the owner-revised handoff (no automatic retry after
  a failed/terminal state — confirmed correctly implemented: `CREATE_FAILED`
  and any existing row unconditionally short-circuit `confirm()`, matching
  "manual recovery is safer" from the handoff).

## 3. Finding 1 (blocking) — self-check threshold makes the patch fail on every run

The runner validates its own output twice using
`pay002_count($source, "'PENDING-OC-' . $orderId")`:

- Line 161 (idempotent re-run detection): requires `>= 3`.
- Line 296 (pre-write validation, runs *before* backup/write): requires
  `>= 3`, i.e. fails if `< 3`.

The actual assembled replacement text contains this exact substring **twice**
— once in `confirm()`'s failure branch (`'PENDING-OC-' . $orderId,` passed to
`upsertTransaction()`), once in `reserveCreate()`
(`$pendingCapId = 'PENDING-OC-' . $orderId;`). A third, unrelated occurrence
(`strpos($capId, 'PENDING-OC-')` in `replyExistingCreate()`) does not match
the searched substring because it lacks the `. $orderId` suffix.

Verified independently (not just re-read): reconstructed both heredoc bodies
verbatim and ran Python's `str.count()` — equivalent to PHP's `substr_count`
for this non-overlapping case — against the concatenated text. Result: `2`.

Consequence: line 296's check (`< 3`) evaluates true on the very first run,
throwing `RuntimeException('Post-patch controller shape validation failed
before write.')` **before the backup or write step runs at all** (that block
starts at line 300, after this check). The patch cannot succeed on any run,
first or repeat — it is not a race condition or environment-dependent
flake, it is a fixed off-by-one in the runner's own logic.

Fix: change both thresholds from `3`/`>= 3`/`< 3` to `2`/`>= 2`/`< 2`, or add
whatever third intentional occurrence was meant to exist. Re-verify the count
against the actual final source the runner assembles, not against assumption.

## 4. Finding 2 (blocking, more serious) — real `cap_id` is never persisted after a successful reservation

`reserveCreate()` inserts a row with `cap_id = 'PENDING-OC-' . $orderId`
(placeholder) and `state = 'CREATING'`. On a successful bank response,
`confirm()` calls the *existing, unmodified* `upsertTransaction()` a second
time with the real `cap_id` to promote that row:

```php
$this->upsertTransaction($orderId, 'OC-' . $orderId, $capId, 'WAITING_CLIENT', $isTest, ['create' => $response], null);
```

`upsertTransaction()`'s SQL (unchanged, `patches/PAY-002_pumb-credit-skeleton_20260728.php`
line 258) is:

```sql
INSERT INTO ... SET `order_id`=..., `store_order_id`=..., `cap_id`=..., `state`=..., ...
ON DUPLICATE KEY UPDATE
  `state`=VALUES(`state`),
  `guarantee_letter`=COALESCE(VALUES(`guarantee_letter`),`guarantee_letter`),
  `agreement_number`=NULLIF(VALUES(`agreement_number`),''),
  `payload`=VALUES(`payload`),
  `date_modified`=NOW()
```

**`cap_id` is not in the `UPDATE` clause.** Since a row for this
`(store_order_id, is_test)` already exists (the `CREATING` reservation),
this call hits the `ON DUPLICATE KEY UPDATE` branch, not a fresh `INSERT` —
so `state` correctly becomes `WAITING_CLIENT`, but the `cap_id` **column
stays `PENDING-OC-{orderId}` forever**. The real `cap_id` only survives
inside the JSON `payload` blob (`['create' => $response]`), not in the
indexed column anything else reads.

This is not a pre-existing bug — in the original (already-deployed, disabled)
code, `upsertTransaction()` was only ever called once per fresh application
with the real `cap_id` already known at `INSERT` time (a plain insert, never
hitting the `UPDATE` branch for a value change), or called again later with
the *same* `cap_id` it already had (so the missing column in the `UPDATE`
clause was harmless). This patch is the first caller that needs `cap_id` to
actually *change* on an existing row, and the reused helper doesn't support
that.

**Downstream breakage if this shipped as-is:**
- `poll()` reads `$tx['cap_id']` and calls
  `GET /sf-credits/{cap_id}` — would call `GET /sf-credits/PENDING-OC-{orderId}`,
  not the real application, on every poll for every successful order.
- `handleCallback()` looks up the transaction by the bank's real `cap_id`
  (`transactionByCap($capId, ...)`) — would find **no row**, since the stored
  `cap_id` never matches what the bank sends back. `$orderId` resolves to 0,
  and the real order's status is never updated by a bank callback.
- Admin shipment-confirm / cancel / refund actions, which `PATCH`/`POST`
  against the stored `cap_id`, would target a non-existent application.

In effect, every dimension of the payment flow this guard doesn't directly
touch (poll, callback, admin actions) would silently stop working for every
order that goes through a successful `confirm()`, the moment the method is
enabled — a strictly worse outcome than the duplicate-create bug this patch
sets out to fix, even though it carries no customer-facing risk yet (method
stays disabled).

Fix: add `cap_id=VALUES(cap_id)` to `upsertTransaction()`'s
`ON DUPLICATE KEY UPDATE` clause (minimal, safe for all existing callers —
`poll()`/`handleCallback()` already pass back the same `cap_id` they read,
so this is a no-op for them), or have `confirm()`'s promotion step use a
dedicated `UPDATE ... SET cap_id=..., state=... WHERE store_order_id=... AND
is_test=...` instead of reusing `upsertTransaction()`. The former is smaller
and touches one already-reviewed line.

## 5. What was not re-verified this session

- No live server or database access; both findings are derived from static
  analysis of the runner's generated source and the existing, unchanged
  `upsertTransaction()` SQL, not from an actual execution against a live
  schema. Recommend Codex reproduce Finding 1 by actually invoking the
  runner against a disposable fixture before resubmitting, not just
  re-reading the count logic.
- Preflight checks (table/column/unique-index existence) were reviewed for
  soundness but not exercised against the live schema — they were not the
  cause of either finding and appear correctly written.
- Did not re-verify `transactionByOrder()`'s exact `WHERE` clause (unchanged,
  assumed consistent with its use in `poll()` from the original patch).

## 6. Next step (round 1)

Send both findings back to Codex against the same handoff
(`handoffs/handoff_PAY-002_confirm-idempotency-guard_20260729.md`) rather than
opening a new task — this is a correction round on the same fix, not new
scope. Do not deploy `patches/PAY-002_confirm-idempotency-guard_20260729.php`
in its current form.

## 7. Round 2 — both findings verified fixed (2026-07-29)

### Verdict

**Review OK. Cleared to deploy** (still a disabled-method change — no live
customer traffic reaches this code either way).

### What changed

- Finding 1 fix: the two `pay002_count($source, "'PENDING-OC-' . $orderId")`
  thresholds (marker-exists check, line 161; pre-write validation, line 303)
  now require `>= 2` / fail on `< 2`, matching the actual occurrence count.
- Finding 2 fix: a new anchor, `` `state`=VALUES(`state`),`guarantee_letter`=COALESCE(...),`agreement_number`=NULLIF(...),`payload`=VALUES(`payload`),`date_modified`=NOW() ``,
  is matched verbatim against `upsertTransaction()`'s real, unmodified SQL
  (verified byte-for-byte against `patches/PAY-002_pumb-credit-skeleton_20260728.php`
  line 258 before this patch touches anything — line 184), then
  `` `cap_id`=VALUES(`cap_id`), `` is prepended to it via `str_replace` (line 299).
  A matching `=== 1` / `!== 1` count guard covers both the marker-exists and
  pre-write paths.

### Independent verification performed this round

Re-reading the diff was not enough on its own, given round 1's finding was
exactly this kind of "looks right, counts wrong" bug. Rebuilt the runner's
full transform pipeline independently in Python (not PHP — none available in
this session's sandbox) using the verbatim heredoc bodies from the actual
patch file and the verbatim `upsertTransaction()` SQL from the real,
already-deployed first patch:

1. Confirmed the pristine anchor text appears exactly once in a synthetic
   source built from the two real, previously-verified fragments (matches
   the runner's own `!== 1` precondition, line 184).
2. Applied the same three transforms the runner applies, in the same order
   (`confirm()` body replace → helper insertion before the callback anchor →
   `str_replace` on the upsert anchor).
3. Counted the final assembled text exactly as the runner's own guard does.

Result: `PENDING-OC- . $orderId` = 2, `` cap_id`=VALUES(`cap_id`) `` = 1,
`reserveCreate` signature = 1, `CREATE_FAILED` = 3 — every one of the
runner's own pre-write conditions (`=== 1`, `>= 2`, `=== 1`, `>= 2`
respectively) is satisfied. The patch will reach the backup/write step and
complete, not abort as in round 1.

Also re-confirmed the semantic fix for the other callers of
`upsertTransaction()` (`poll()`, `handleCallback()`): both already pass back
the *same* `cap_id` they read from the existing row, so adding
`cap_id=VALUES(cap_id)` to the `UPDATE` clause is a no-op for them and only
changes behavior for the new reservation-promotion call in `confirm()` —
no regression for the already-reviewed parts of the original skeleton patch.

### Not independently verified

- No live PHP interpreter was available in this session to run `php -l` or
  execute the runner directly; verification is by faithful reproduction of
  its string-transform logic, not by executing the actual file. Codex's own
  report states `php -l` was run against the runner and against a
  fixture-assembled controller — this is corroborating, not independently
  reproduced here.
- No live database — the reservation/promotion behavior itself (the actual
  `INSERT ... ON DUPLICATE KEY UPDATE` race-safety and the `cap_id` promotion)
  is unverified against a real MySQL instance. The runner's own preflight
  (table/column/unique-index checks) is the safety net for schema drift at
  deploy time; this review did not re-derive the live schema independently.

### Owner QA (deploy, still disabled — no bank traffic involved)

1. ✅ done 2026-07-29 — patch uploaded to `~/public_html`, ran successfully.
2. Not separately re-run; superseded by item 4 below (the deployed controller
   was exercised directly via a real callback request, which only succeeds
   against the post-patch shape).
3. ✅ done 2026-07-29 — `payment_pumb_credit_status` confirmed `0`
   (screenshot, toggle off), unaffected by this patch.
4. ✅ done 2026-07-29 — callback-route test against the live deployed
   controller (`diagnostics/PAY-002_pumb-credit-skeleton_review_20260728.md`
   §5 item 8):
   - Missing/wrong Basic auth → `HTTP 401 {"success":false,"error":"unauthorized"}`,
     confirmed twice (browser GET with no header, curl with placeholder
     credentials).
   - Correct test Basic credentials → `HTTP 200 {"success":true,"error":null}`.
   - DB row confirmed: `TEST-CAP-001 | FUNDED | 1 | PUMB-TEST-TEST-CAP-001 | 2026-07-29 16:21:43`
     — `cap_id` persisted correctly (not stuck on a placeholder), `is_test=1`,
     matches Finding 2's fix working end to end on the live controller.
   - Test Basic password was visible in the terminal transcript during this
     round; owner rotated it immediately after ("Пароль поміняв").
5. The reservation/promotion path specifically (`confirm()` → `reserveCreate()`
   → real bank `cap_id`) still needs test OAuth2 credentials and a real
   `confirm()` call to verify end to end — that remains test-contour QA
   (`PAY-001-SMOKE`), not blocked by anything found this round.

**All items in this section that don't require bank credentials are
complete. Guard patch QA is closed.**
