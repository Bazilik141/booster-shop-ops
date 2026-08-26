# PAY-002 — PUMB bank test-drive diagnostic report

Date: 2026-08-24

## Scope

Prepared one CLI-only diagnostic script. It does not modify extension files,
PUMB settings, OpenCart orders, order history, or customer-facing checkout.
Without `--live` it performs OAuth and read-only fixture/API checks, builds the
payload, and exits before a create call or transaction write.

## Fresh-backup evidence used

`backup-8.24.2026_10-35-09_boosters.tar.gz` confirms the PUMB controller's
payload shape (`/sf-credits`, `DIGITAL_SF`, invoice goods and credit request)
and the existing PUMB transaction table. The source still uses its legacy fixed
term; this diagnostic receives an explicit CLI term and does not load or change
the extension.

## Delivery

`patches/PAY-002_bank-test-drive_diagnostic_20260824.php`

## Guard coverage in code

- Refuses HTTP execution: `PHP_SAPI === cli` is required.
- Requires `config.php`, database constants, cURL, PUMB test mode `1`, exact
  HTTPS hosts `auth.dts.fuib.com` and `api.dts.fuib.com`, disabled/absent PUMB
  storefront status, and non-empty OAuth credentials.
- Rejects invalid/missing order, term, units, and phone arguments before any
  network call.
- Requires `--live` for `POST /sf-credits` and its single bounded INSERT.
- Never prints OAuth credentials or bearer tokens. It does print the requested
  test phone in the full payload and the bank's create response verbatim.

## Data-write boundary

On `HTTP 201` only, the script inserts one row into
`{DB_PREFIX}pumb_credit_transaction` with `is_test=1`, returned `cap_id`, exact
returned `state`, original `order_id`, and request/response payload.

The table has a unique `(store_order_id,is_test)` index while the bank permits
duplicate `OC-<order>` create requests. Therefore diagnostic rows use a clearly
marked, cap-derived `PUMB-TEST-...` store-order value; the actual bank
`store_order_id` remains in the stored request payload. This allows a warned
repeat test without an update, deletion, or schema change.

## Local validation

- PHP syntax check is required before delivery.
- Live guards, OAuth, fixture states, create response, callback arrival, and
  the amount-unit answer can only be verified by the owner on the production
  host test contour. No local network call was made.

## Owner run sequence

1. Dry-run an existing recent order with `--term=4 --units=hryvnia`.
2. Repeat dry-run with `--units=kopiyka`; compare `payload_json` and amount
   lines.
3. Add `--live` only for the chosen first hypothesis.
4. If the create returns `400`, read its verbatim body and run the other unit
   form deliberately.
5. On `201`, retain the printed `cap_id` and `rollback_delete_sql`, then test
   callback and lifecycle operations through the existing admin controls.
6. Delete the uploaded diagnostic after the test window.

## Residual risks

- The test contour creates a real bank-side application when `--live` is used;
  deleting the local row does not cancel it.
- The script cannot prove callback Basic auth or IP filtering itself; those
  require the bank callback after a successful create.
- This diagnostic does not close PAY-002 or PAY-004. It creates evidence for
  the subsequent owner-led bank test and follow-up review.
