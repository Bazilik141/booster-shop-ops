# SimpleCheckout retirement: bounded proposal

Date: 2026-10-23
Executor: Codex, diagnostic proposal only.
Status: NOT_IMPLEMENTED. No uninstall, deletion, status change or host action.

The owner proposed disabling/removing the old SimpleCheckout to simplify future
maintenance. That direction is reasonable, but direct folder deletion is unsafe
with the currently retained dependencies. It is separate from the three held
ACC-009 WP3b/N2/WP3c packages and must not change their frozen review inputs.

## Confirmed retained-source facts

- The admitted account/address controller sends its successful welcome-address
  flow to `extension/SimpleCheckout/module/pinta_simple_checkout` (before-image
  line700), immediately after a First15 message. This proves a retained route
  dependency; it does not prove which route is currently the production default.
- Registration and native checkout also maintain First15 state. The native
  create-account flow sets the server-owned `bs_first15_pending` service flag.
  Removing the legacy module does not justify discarding that flag or its native
  coupon/success consumers.
- The legacy checkout delegates custom-field metadata to the shared catalog
  model and customer creation to the shared customer model. The current account
  repair explicitly protects those reachable calls while the old module exists.
- Current independent review deliberately excludes module retirement. Source
  acceptance of the account patches is not approval to uninstall the module,
  delete settings, edit extension metadata or change payment integration.

Evidence: the exact before/after source indexes, current-tree identities and
reports under `work/acc009-remaining-repair-r3-20261023/` and
`diagnostics/ACC-009_remaining_r3_repairs_report_20261023.md`.
These are retained source facts, not a fresh hosting activation inventory.

## Suggested implementation boundary, after ACC-009 source acceptance

1. Establish the newly admitted production after-state and enumerate remaining
   legacy route/config/payment/event references. Reuse accepted unchanged source;
   obtain only missing current owner-host facts through an owner-run packet.
2. Replace surviving account/address/navigation transitions with the current
   native checkout route. Preserve welcome-coupon eligibility and its timing.
   Assess old direct links: a small GET compatibility redirect can avoid broken
   bookmarks. Do not replay legacy POSTs, create orders or copy partial payloads
   into native checkout automatically.
3. Prepare a reversible disable stage and its exact backup/rollback before
   removing code. Inspect what the module's own uninstall actually deletes.
   Never delete settings/tables shared with active payment or shipping providers
   merely because their names mention the legacy module.
4. Locally verify guest/authenticated entry, registration -> address -> checkout,
   First15, nonshipping checkout and NP warehouse/locker/courier composition.
   Provider HTTP stays fictional in local tests; owner host admission and normal
   real-workflow QA remain separate. Do not create a shipment just for a test.
5. Only after accepted disable/route QA, remove the owned unused module files and
   any proven orphan assets with an exact manifest, private backup and independent
   integrated review of the owner packet. Any DB/settings removal needs its own
   explicit scope and backup gate. No broad directory/config cleanup.

Expected benefit: one maintained checkout implementation, fewer legacy readers,
less duplicated validation/AJAX and a smaller review surface. That benefit is an
architectural inference, not a measured performance improvement or a guarantee
that direct deletion cannot break checkout. Final target/rollback facts require
the actual retirement package; no deletion or owner execution command is issued
by this proposal.
