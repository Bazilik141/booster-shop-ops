# PAY-003 — shared waiting/recovery intake

Date: 2026-08-31
Scope: owner selected item 2, PAY-003, for implementation. No PAY-005 work.

## Result and source gate

Implementation intake completed; deployable patch is blocked on current Mono
and account-order sources. No production files, bank state, database, roadmap,
Notion, or Git state were modified. Only this local report was added.

The current owner archive `booster-debug-pay002-final-20260831-164048.tar.gz`
contains checkout and PUMB but no Mono extension or account routes/templates.
The Mono controller under `work/pay002-cart-perf/source/` is older historical
evidence, not a verified current patch target. Current PUMB amount-patch source
must be preserved: owner deployed controller SHA256
`422d126854263405112bfe2a7267b178cdaf12aaf381dbcb8f97a1840e01ac45`.

## Confirmed integration issue

- Archived PUMB `catalog/controller/payment/pumb_credit.php` index (lines 5–8)
  redirects non-error creation to checkout success, before client confirmation.
- The deployed amount patch changes amount construction, not this redirect.
- Current archived `catalog/controller/checkout/success.php` clears the cart
  and unsets order/payment session context (lines 50–64). A waiting page cannot
  rely on this context surviving a success-page visit.
- Existing PUMB poll reads only the active session order and current test mode;
  it is not an authenticated historical-order recovery contract.
- Older Mono source also redirects create responses to success. Its exact live
  bytes and current auth/state/poll behavior need verification before edits.

## Scope to preserve

`plans/PAY_decomposition_mono-pumb-preorder_20260721.md` section 10 specifies one
shared waiting/recovery surface for both providers, no new administrative order
statuses, and recovery after closing a tab. The unified smoke plan additionally
requires entry from order history and a shared success destination.

Missing inputs: current `extension/mono_chast`; current account order controller,
model and templates; fresh checkout/PUMB source for composition; current shared
header/styles for visual integration. Request a source-only archive, excluding
config, logs, dumps and backups. Never request credentials or customer rows.

## Next implementation checks

1. Establish provider and environment from the authorized original order and
   transaction; reject cross-customer/cross-store access. Guest recovery must
   not be authorized by a guessable order ID alone.
2. Read existing transactions when resuming. Never POST a new bank application
   on page reload, polling, or recovery. Ambiguous creation is not retry-safe.
3. Separate client confirmation from funding. The section-10 client-confirmed
   transition and the smoke plan's terminal-success wording need reconciliation
   in the implementation contract: PUMB WAITING_STORE_CONFIRM precedes shipment,
   so waiting for FUNDED may keep a customer waiting until physical dispatch.
4. Bound bank fallback polling; honor the bank's 30-second minimum per PUMB
   application across tabs/sessions, stop terminal polling and back off errors.
   Do not expose bank payloads, identifiers, credentials or personal details.
5. Preserve test isolation, amount correction, Mono create behavior, cart/NP
   fixes, non-credit success, and unrelated dirty workspace files.
6. Test state mappings, auth failures, expired guest access, tab-close recovery,
   delayed callbacks, network errors, duplicate prevention, environment changes,
   and 3 responsive widths with long text and keyboard/focus states.

No lint/runner/idempotence/rollback or live QA results exist for PAY-003 yet.
The eventual runner requires exact source guards, backups, syntax gates,
restore-on-failure and an isolated composition test before owner upload.
