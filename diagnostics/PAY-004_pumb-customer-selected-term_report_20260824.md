# PAY-004 — PUMB customer-selected term report

Date: 2026-08-24

## Scope

Prepared one production runner for the PUMB extension only. It leaves
`payment_pumb_credit_status` at `0`; no checkout UI, callback, order-status,
or mono files are changed.

## Fresh-backup discovery

Source: `backup-8.24.2026_10-35-09_boosters.tar.gz`.

Outcome: **(b)** — no PUMB customer selection is persisted today.

- `extension/pumb_credit/catalog/model/payment/pumb_credit.php` returns `[]`.
- Product and checkout credit UI render PUMB as `СКОРО БУДЕ` and only write
  `mono_chast` payment codes/terms.
- Therefore PAY-004 does not invent or change a checkout UI. PUMB `confirm()`
  now accepts an explicit future-flow `term` parameter and validates it before
  reservation creation or any bank call. PAY-001-UI / PAY-003 still owns a
  future PUMB selector and its persistence.

## Patch contents

`patches/PAY-004_pumb-customer-selected-term_20260824.php`

- Migrates legacy `payment_pumb_credit_term=3` to
  `payment_pumb_credit_terms=[3,4,5]`.
- Changes the PUMB admin field to `Allowed terms (JSON: [3,4,5])`; invalid
  JSON and any value outside 3/4/5 is rejected on save.
- Requires a numeric `term` in PUMB `confirm()` and validates it against the
  configured list. Invalid/missing input returns a JSON error before a
  reservation, transaction row, or `POST /sf-credits`.
- Passes the explicit term to `createPayload(array $order, int $term)`.
- Adds nullable `requested_term` to `pumb_credit_transaction`, records the
  sent term there, and retains the create request (including
  `credit_request.term`) in transaction payload through later poll/callback
  updates.
- Preserves the existing confirm reservation and idempotency implementation.

## Local validation

- The runner validates every live anchor exactly once before writing.
- It runs `php -l` on all generated and then all written PHP files.
- It preflights the PUMB transaction/settings tables, expects the legacy term
  setting exactly once, and refuses ambiguity.
- It checks `payment_pumb_credit_status=0` before and after mutation.

Local static validation still cannot prove a production bank call. The runner
must be executed by the owner in `~/public_html`.

## Rollback

The runner creates:

`_patch_backups/PAY-004_pumb-customer-selected-term_20260824-<timestamp>/`

It contains all five pre-patch source files and `settings-before.json` with the
old non-secret term-setting rows. Restore those source files and the saved
setting state if required; keep the nullable `requested_term` column in place.

## Owner QA after deployment

- [ ] First run prints `done=ok`; repeat upload/run prints `already_applied=yes`.
- [ ] Admin PUMB page displays `Allowed terms (JSON: [3,4,5])`; save/reload
  preserves it.
- [ ] PUMB enable switch is still off and Test contour still on.
- [ ] Storefront checkout has no visual change and no PHP error.
- [ ] During the first bank test order with a future PUMB UI term `4` or `5`,
  verify the transaction row's `requested_term` and preserved payload
  `create.request.credit_request.term` match it.
- [ ] Submit an out-of-list term only in the dedicated PUMB test flow: expect
  JSON error, no `/sf-credits` request, and no new transaction row.

## Out of scope / residual risk

- The PUMB selection UI and persistence do not yet exist; they remain
  PAY-001-UI / PAY-003 scope.
- Existing payload amount composition (products only) and the unverified
  hryvnia-vs-kopiyka unit issue were not changed.
- First live bank-test evidence is required before PAY-004 can be closed.
