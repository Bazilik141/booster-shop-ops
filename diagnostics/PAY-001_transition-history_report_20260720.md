# PAY-001 — transition-history follow-up report

## Scope

Sandbox-only follow-up to the deployed PAY-001 extension. No storefront UI, checkout selector, Hutko, COD, Checkbox, NCRM source or credentials are changed.

## Changes

- Creates ocp5_mono_chast_event as an append-only audit table.
- Keeps ocp5_mono_chast_transaction as the current-state row.
- Records create, callback, poll, admin confirm and admin reject events, with raw response/callback payload, HTTP status and Trace-Id when supplied.
- Records incoming callback Trace-Id and captures response Trace-Id for the protected admin confirm/reject action.
- On admin confirm/reject, updates the intended existing OC order status (active / failed) and adds matching order history.

## Safety

- Exact anchors were verified against pay001-postdeploy-files.tar.gz.
- Patch backs up both controller files and stores SHOW CREATE TABLE for ocp5_mono_chast_transaction before writes.
- It fails on any unexpected target/anchor, verifies both modified PHP files with php -l, restores files on syntax or DB connection failure, and is idempotent with .pay001-history-marker.
- Rollback instructions are in the generated _patch_backups/.../rollback.sql. Do not drop the new table after meaningful sandbox history exists.

## Local verification

- Runner php -l: passed.
- Test against an extracted post-deploy extension: all anchors passed and generated controller syntax passed.
- Deliberate unavailable-DB test: failed loudly, restored controllers, left no marker.

## Owner QA

1. Run the patch while the payment method remains disabled for public users.
2. Exercise sandbox create, callback, poll, admin confirm and admin reject.
3. For each action, confirm a new row appears in ocp5_mono_chast_event; confirm ocp5_mono_chast_transaction still contains only the newest state.
4. Confirm invalid callback signature remains rejected and the existing Hutko/COD checkout stays unchanged.
