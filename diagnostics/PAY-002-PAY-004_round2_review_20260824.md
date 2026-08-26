# PAY-002 / PAY-004 — round 2 review (three files)

Date: 2026-08-24
Reviewer: Claude (chat). Author: Codex.
Supersedes the open findings in
`diagnostics/PAY-004_pumb-customer-selected-term_review_20260824.md` (B-1, N-1)
and `diagnostics/PAY-002_bank-test-drive_review_20260824.md` (N-1).

## Verdict

**Deploy OK; owner QA required** — all three.

## What was checked in this round

| File | Change | Status |
|---|---|---|
| `patches/PAY-002_bank-test-drive_diagnostic_20260824.php` | `fail(): never` → `: void` (`:23`) | ✅ Only change. All guards re-verified intact: CLI-only, `test_mode=1`, exact test hosts, method-not-live, non-empty credentials, `--live` gate, single bounded INSERT, no secret in output |
| `patches/PAY-004_pumb-customer-selected-term_20260824.php` | `fail(): never` → `: void` (`:27`); new `pumbStatusIsDisabled()` (`:102-106`) | ✅ **B-1 closed.** `return !$rows \|\| $rows[0]['value'] === '0'` — absent row now correctly reads as disabled, and `ensure(count($rows) <= 1, ...)` still rejects a corrupt multi-row state. Applied at both the pre-check (`:137`) and the post-mutation check (`:344`). No `status` row is inserted |
| `patches/PAY-002_pumb-agreement-number-preserve_20260824.php` | New, 76 lines | ✅ See below |

PHP 8.0 compatibility re-verified across all three: no `never`, `enum`,
`readonly`, first-class callable syntax, or `array_is_list()` remains.

## Agreement-number patch — findings

Conventions C1–C7 all present: file-exists (`:32`), exact anchor count
(`:46-47`), backup before write (`:50-55`), `php -l` with restore on failure
(`:61-67`), idempotent marker that re-verifies both the old and new expressions
(`:39-44`), no DB change at all, self-delete (`:74-75`).

Semantics correct. `COALESCE(NULLIF(VALUES(agreement_number),''), agreement_number)`
— an empty incoming value falls back to the stored one; a non-empty incoming
value overwrites. In MySQL's `ON DUPLICATE KEY UPDATE`, the bare column name on
the right refers to the current row value, which is the intended behaviour. It
mirrors the protection already applied to the adjacent `guarantee_letter` column.

**Order-independence verified by the reviewer**, since no report accompanied this
patch. Against the live controller:

- the patch's anchor occurs exactly once, the replacement zero times;
- PAY-004's two overlapping-region anchors (the `state`/`guarantee_letter`
  `ON DUPLICATE` fragment, and the `is_test`/`guarantee_letter` INSERT fragment)
  each occur exactly once and **do not** contain the agreement-number anchor.

The two patches can therefore be applied in either order. Neither invalidates the
other's anchors.

## Non-blocking, carried forward

- `PAY-002_bank-test-drive` N-2 (fixture step prints `state` but not the raw
  body) and N-3 (the diagnostic row's real `order_id` will short-circuit a later
  genuine `confirm()` for the same order) are unchanged and still apply. N-3 is
  handled operationally: the owner created disposable order **#332** for this.
- `PAY-004` N-1…N-5 unchanged.
- `PAY-004:364` prints `payment_pumb_credit_status=0` even when the row is
  absent. Cosmetic; "0" here means "disabled", not "a row exists with value 0".
- **OPcache is enabled on the host** (`Zend OPcache v8.0.30`, seen in `php -v`).
  A patched controller may take up to the configured revalidation interval to
  take effect for web requests. Not a defect; relevant only if a change appears
  not to have landed immediately.
- No diagnostic report was delivered for the agreement-number patch. The
  order-independence check it was asked to record is covered above instead.

## Owner QA

Per patch: first run prints its `changed=` / `done=ok` lines; a re-uploaded second
run prints `already_applied=yes`. After PAY-004: the admin PUMB page loads, shows
`Allowed terms (JSON: [3,4,5])`, saves and reloads; the enable switch is still off
and `Test contour` still on; the storefront checkout is visually unchanged with no
PHP error.

`bs-checkout-smoke` is **not** required this round — the method stays disabled and
no customer-reachable path changes. It becomes mandatory before
`payment_pumb_credit_status` is ever set to `1`, as part of `PAY-001-SMOKE`.

## Rollback

Each patch writes its own `_patch_backups/<PATCH_ID>-<timestamp>/` directory
before touching anything; restore from there and delete the corresponding marker
under `extension/pumb_credit/`. The `requested_term` column added by PAY-004 is
additive and stays. The diagnostic script writes no file and no setting.

All three self-delete on success except the diagnostic, which is intentionally
kept for repeat runs and must be removed manually when testing ends.

## Recommended status

`PAY-002` and `PAY-004` both stay `In progress`. Neither closes on a patch; both
close on live bank evidence.
