# PAY-004 — patch review (pre-deploy gate)

Date: 2026-08-24
Reviewer: Claude (chat). Author: Codex. Review performed by a different surface
than the patch author, per `CLAUDE.md` → Review routing.

Patch: `patches/PAY-004_pumb-customer-selected-term_20260824.php` (362 lines,
read in full)
Report: `diagnostics/PAY-004_pumb-customer-selected-term_report_20260824.md`
Handoff: `handoffs/handoff_PAY-004_pumb-customer-selected-term_20260824.md`

## Verdict

**Return for changes.** One blocking defect: the patch aborts on its own
pre-flight guard and cannot apply on the live database as it currently stands.

The logic of the change itself is sound and matches the handoff. The blocker is a
single wrong assumption about how OpenCart stores an unchecked settings toggle.

## Evidence base

- `backup-8.24.2026_10-35-09_boosters.tar.gz` →
  `mysql/boosters_ocart49.sql` (owner-provided, taken 2026-08-24 10:35) — the
  authoritative live-state source for this review.
- `pumb-live_2026-08-14.tar.gz` — live extension tree, used to verify every
  anchor the patch expects.
- `pumb-settings.txt` (2026-08-14 masked settings dump) — corroborates the same
  finding ten days earlier.

`php -l` could not be run: no PHP binary in the review sandbox. B4 (syntax) is
therefore **unverified by execution**; the file was read in full and no syntax
problem was visible, but that is not a lint.

## Blocking

| ID | Severity | Where | What is wrong | What the canon says |
|---|---|---|---|---|
| B-1 | Blocking | `PAY-004_..._20260824.php:132` (and the same call at `:339`) | `ensure(settingValue($db, $prefix, 'payment_pumb_credit_status') === '0', ...)`. `settingValue()` (`:97-101`) itself asserts `count($rows) === 1`. **There is no `payment_pumb_credit_status` row in `oc_setting`.** Verified in today's backup: the `payment_pumb_credit_*` key set contains `..._status_failed`, `..._status_funded`, `..._status_returned`, `..._status_waiting_client`, `..._status_waiting_store` — and no bare `..._status`. The patch will exit with `Expected exactly one setting row for payment_pumb_credit_status.` before writing anything. | C1/C2 pre-checks must reflect real live state. A guard that can never pass is a guard that blocks the patch, not the risk |

**Why the row is absent.** The skeleton patch inserted `status = '0'` at install.
The admin form renders the enable switch as an unchecked HTML checkbox; an
unchecked checkbox is not submitted, so `editSetting('payment_pumb_credit', $post)`
— which deletes all rows for the code and re-inserts only what was posted —
dropped the row on the first admin save. `config->get()` then returns `null`,
which is falsy, so the method is correctly disabled. **Absent is the normal,
healthy representation of "off" here**, not a corruption.

`payment_mono_chast_status = 1` exists precisely because that switch is on.

**Suggested fix direction** (executor's call, not prescribed): treat "no row" and
`'0'` as equally valid proof of disabled, and fail only on a present non-zero
value. The same applies to the post-mutation verification at `:339`. Do not
"repair" the missing row by inserting `status = 0` — that would be a scope
change, and the next admin save would delete it again.

## Non-blocking — report as fixes

| ID | Severity | Where | What is wrong | What the canon says |
|---|---|---|---|---|
| N-1 | Medium | `:245-270` + live `admin/view/template/payment/pumb_credit.twig` | `validateTerms()` sets `$this->error['warning']`, but the PUMB admin template renders **no** error output anywhere (`grep error` on the live twig returns nothing). Invalid JSON in the terms field therefore produces a silent non-save: no message, no redirect, the form simply re-renders with the submitted value. | Same silent-form-bounce class as `ACC-003`. Acceptance criterion 6 still passes, so this is not blocking — but the owner will hit it during QA and read it as "the field does not save" |
| N-2 | Medium | `:319-348` | `ALTER TABLE` runs before `begin_transaction()` and is not covered by the rollback. If the settings migration then fails, source files are restored and the marker is not written — but a re-run aborts at `:295` (`requested_term already exists without a PAY-004 marker`). Recovery then needs a manual `ALTER TABLE ... DROP COLUMN`, which the patch never states. | Rollback must be executable by the owner without improvisation. The catch message hints at the situation but gives no command |
| N-3 | Low | `:259` | `$shopTerms = [3, 4, 5]` is a literal in the admin validator. The handoff §4 step 2 asked for the allowed set to come from configuration. The catalog side does read config (`allowedTerms()`, `:196-205`) — only the admin gate is hardcoded, so offering a different term later needs a code patch, not a settings change | Handoff §4 step 2. Defensible as a deliberate business-rule guard; worth an explicit line in the report either way |
| N-4 | Low | header `:2-21` | C6 asks for rollback **SQL** in the patch header. The header documents the rollback path in prose and the runner does save `settings-before.json`, which is materially better than nothing, but no SQL statement is present | `AGENTS.md` C7 conventions, C6 |
| N-5 | Info | `:191` | `requestedTerm()` accepts `term` from `GET` as well as `POST`. Harmless — the value is validated against the allowed list before use — but `POST`-only would be tighter for a payment-initiating call | — |

## Conventions C1–C7

| # | Status | Note |
|---|---|---|
| C1 file-exists | ✅ | `:122`, all five targets |
| C2 anchor pre-check | ✅ | `replaceOnce` / `replaceCount` assert exact counts. Independently verified against the live tree: `['create' => $response]` occurs exactly 2×, the `is_test`/`guarantee_letter` INSERT fragment exactly 1×, `transactionByCap()` exists at live `:191` and is safe to call from `upsertTransaction()` |
| C3 backup | ✅ | `:297-300`, before any write; includes `settings-before.json` |
| C4 `php -l` gate | ✅ | Lints generated content before writing (`:285-288`) **and** written files (`:311-317`), restores from backup on failure |
| C5 idempotent marker | ✅ | `:134-141`, and it re-verifies both the column and the setting value rather than trusting the marker alone |
| C6 DB changes | ⚠️ | Owner-approved via handoff §4 steps 4–5. Rollback documented in prose, not SQL — see N-4 |
| C7 self-delete | ✅ | `:360-361`, with an explicit failure message |

## Scope

- **S1 Do not touch** — respected. Only the five files named in handoff §6 are
  written. `payment_pumb_credit_status`, the callback routes, the Basic-auth and
  IP checks, the `is_test` separation, `applyOrderStatus()` and `mono_chast` are
  untouched. The two explicitly out-of-scope defects (amount composition,
  hryvnia-vs-kopiyka units) were correctly left alone and are restated in the
  report.
- **S2 One work package** — respected. One patch file, one concern.
- **S3 Risky zones** — payment · checkout · order status · DB. Correctly
  identified. Method stays disabled throughout, which keeps blast radius at the
  admin settings page plus a code path no customer can currently reach.
- **S4 / S5** — n/a (no visual CSS, no SEO URLs).

## Discovery outcome — and what it means for the plan

Codex reports step-0 outcome **(b)**: no PUMB customer selection is persisted
today. Independently consistent with the live tree —
`catalog/model/payment/pumb_credit.php` returns `[]`, and the credit UI renders
PUMB as `СКОРО БУДЕ`.

Consequence the owner must decide on, which is **not** a patch defect:

After this patch, `confirm()` rejects any call that does not carry a valid `term`.
Nothing on the site sends one. There is also no "create application" action in the
admin panel — the manual lifecycle actions cover only shipment-confirm, cancel and
refund, all of which require an existing `cap_id`.

Therefore **there is currently no way to create a PUMB application at all**, and
the owner's stated sequencing goal — first bank test order on a 4- or 5-payment
term, in one pass (owner decision 2026-08-24) — is not reachable with this patch
alone. Options, in the owner's hands:

1. Build the PUMB selector properly (`PAY-001-UI`, then `PAY-003`) and test
   through the real flow. Slowest, but it is the work that has to happen anyway
   before go-live.
2. A temporary, admin-only "create test application" trigger behind the existing
   permission check, removed in a later round. Fastest path to bank evidence;
   adds a temporary write path into a payment zone, so it needs its own review.
3. Defer the bank test until (1) lands, and deploy PAY-004 now purely as the
   server-side half.

## Also found while reviewing — outside PAY-004

Two items from today's backup, reported because they affect the next step, not
because the patch causes them:

1. **`payment_pumb_credit_oauth_username` and `payment_pumb_credit_oauth_password`
   are both empty**, as are `payment_pumb_credit_callback_ips` and
   `payment_pumb_credit_test_callback_ips`. The backup was taken 2026-08-24 10:35;
   if the owner saved the admin form after that time, this is stale. Must be
   re-checked before any bank test — the module cannot authenticate without them.
2. **The test callback Basic password is stored in plain text in the settings
   table** and is therefore present in every cPanel backup. It was already
   rotated once after being exposed in a terminal during 2026-07-29 QA. It was
   exposed again during this review, in the reviewer's own tool output, by an
   insufficiently narrow query. Treat it as compromised: rotate it and hand the
   new value to the bank. This is a reviewer error, recorded here so the rotation
   is not skipped.

## Before running

- Expect five `changed=` lines, `db_column=requested_term`,
  `setting=payment_pumb_credit_terms:[3,4,5]`, `payment_pumb_credit_status=0`,
  `self_delete=ok`, `done=ok`.
- Exactly one legacy `payment_pumb_credit_term` row is expected and asserted; one
  row is deleted and one inserted.
- **C7 self-delete**: the file removes itself on success. A repeat run needs a
  fresh upload — the repeat run should print `already_applied=yes`.

## Rollback

Handoff §9 plus the directory this patch creates:
`~/public_html/_patch_backups/PAY-004_pumb-customer-selected-term_20260824-<timestamp>/`

It holds all five pre-patch source files and `settings-before.json`. Restore the
five files, restore the term setting from that JSON, delete
`extension/pumb_credit/.pay004-marker`, and refresh the OpenCart compiled-template
cache. Leave the additive `requested_term` column in place; do not drop it as part
of an emergency restore.

## Smoke after deploy

`bs-deploy-verify` for the deployment itself. `bs-checkout-smoke` is **not**
required this round — the method stays disabled and no customer-reachable path
changes — but it becomes mandatory before `payment_pumb_credit_status` is ever
set to `1`, as part of `PAY-001-SMOKE`.

## Recommended status

`In progress` — unchanged. PAY-004 does not close on the patch; it closes on live
bank evidence of a 4- or 5-payment term, which is blocked on the decision above.
