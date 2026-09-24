# PAY-002 — bank-source reconciliation and completion gates

Date: 2026-08-31
Scope: read-only review requested by the owner. No implementation, bank request,
settings change, deployment, status transition, commit, or push performed.

## Outcome

The supplied bank chat and email resolve the alleged missing test phone and
production endpoints. The final handoff is NOT safe to execute verbatim.
There is enough context to prepare the final work, but not enough current
deployment/settings evidence to certify public-launch readiness.

## Evidence and precedence

- Owner-provided `ChatExport_2026-08-31/messages.html`: message IDs below are
  stable anchors. Dates refer to dates displayed in the export; avoid inferring
  Kyiv times from its inconsistent timezone presentation.
- Owner-provided Gmail MHTML, subject "Доступи до API «Сплачуйте частинами» +
  інструкція з тестування", sender bank team, displayed date August 12.
  Only relevant bank content was assessed; credentials were withheld from output.
- `handoffs/handoff_PAY-002_final-test-and-cutover_20260831.md`.
- `plans/PAY-001-SMOKE_unified-credit-qa_20260727.md`, especially prerequisites
  and the explicit owner-waiver requirement.
- Archived controller in `work/pay002-cart-perf/source/extension/pumb_credit/
  catalog/controller/payment/pumb_credit.php`. This is source evidence, NOT
  fresh live verification after all owner deployments.

## Corrections to the handoff

1. **Test phone exists.** Bank message `message500528`, August 25, supplies
   `+380739991740`. The owner reconfirmed this number on August 31. Do not use
   the earlier number or invented Mono-derived fixtures.
2. **Bank staff advance stage scenarios.** `message500522` says applications
   are advanced manually for the requested test case; `message500525` requests
   coordination when ready. Messages `message500542`/`message500543`,
   `message500547`/`message500548`, and `message500664` show that workflow.
   Replace the handoff's demand that the owner log into PUMB Online under the
   test phone. Coordinate bank availability, create through the shop, then send
   the application reference privately to the existing bank support group.
   This does not change how a real production customer signs.
3. **Production endpoints are supplied in the email.**
   - OAuth: `https://authsrv.pumb.ua/auth/realms/pumb_ext/protocol/openid-connect/token`
   - API base: `https://apiext.pumb.ua/ext-oic/galadriel/v1`
   - Test OAuth: `https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token`
   - Test API base: `https://api.dts.fuib.com/ext-oic/galadriel/v1`
   The email contains separate credential fields for both environments. Their
   presence is verified, not their current validity. Do not guess production
   hosts by removing `.dts`, or copy credential values into this report.
4. **Seven days on production is explicitly confirmed, not just promised.**
   `message500674`, August 26: bank reports the shop's production lifetime set
   to seven days. This is written configuration confirmation, not a completed
   seven-day runtime test; it does not prove the stage setting or explain the
   earlier FAIL conclusively. Do not impose a new seven-day delay automatically;
   reconcile any delayed-test requirement with the owner before final sign-off.
5. **Production terms 3/4/5 are confirmed in writing.** The question in
   `message500670` is answered by `message500672`. Stage term 5 failed in the
   supplied evidence (`message500547`); "can never work on stage" overstates
   that evidence. Use term 4 for the next test. Ask whether stage 5 is available
   now, or record an explicit owner-approved production test plan for it.
6. **The bank does not deduplicate by store_order_id.** `message494078`, July
   28, explicitly says repeat POST creates another application and a new ID.
   The same reply requests fallback polling no more frequently than once per
   30 seconds per application, with no hard API rate limit then specified.
   The handoff's "unknown / duplicate returns 409" is incorrect. Retest shop-side
   duplicate protection, including concurrent/retry behavior. Archived source
   has a create reservation and existing-transaction guard, but its DB uniqueness
   constraints and deployed behavior still need verification. Never blindly
   retry an ambiguous create response.
7. **Amounts are hryvnia.** `message500543` confirms the submitted 700.0 is
   700 UAH, superseding the earlier kopiyka answer. Preserve this correction.
8. **Release scope was understated.** The unified smoke plan requires the
   PAY-003 waiting/recovery page, shared statuses and NCRM mapping verification.
   The handoff omits PAY-003 and labels some dependencies nonblocking. They may
   be unnecessary for the isolated next bank test, but that does not waive them
   for public launch. Either provide implementation/runtime evidence or obtain
   an explicit owner-approved scope amendment. Do not silently mark them done.
9. **Archived test callbacks intentionally do not change order status.**
   `handleCallback(true)` and test polling update the test transaction but skip
   `applyOrderStatus`; initial `confirm()` still applies WAITING_CLIENT. Thus a
   full bank test alone cannot prove production status progression/NCRM sync.
   Do not bypass this isolation by switching a stage test to production mode.
10. **Other unsafe shortcuts are not authorized.** Do not execute the handoff's
    fixture-row DELETE, clear production callback credentials, or change public
    visibility merely because the document says so. Inspect current state and
    use a new test order. Verify exact targets before any cleanup.

## Remaining inputs

- A fresh, bounded source archive after today's final fixes (command below).
- Current PUMB configuration, reported safely: mode, visibility, enabled flag,
  endpoint hosts, terms/limits, status mappings, IP rules, and secret presence
  only. Source archive does not include live DB settings or indexes. After
  reviewing it, prepare a bounded read-only preflight rather than requesting a
  full database dump or credential screenshots.
- The email references `Інструкція_тестування_API_СЧ.pdf` and
  `Статуси заявок, та що вони означають.pdf`, but the MHTML has **zero PDF MIME
  parts**. Download the actual two attachments from Gmail; screenshots of their
  names are not the documents. These improve complete status/failure QA and do
  not invalidate the already confirmed phone or endpoints.
- Coordinate the bank-assisted test window. Obtain any outstanding layout
  approval and production activation requirements through the existing group;
  the email asks the partner to report production readiness. Do not claim bank
  approval already exists or advise immediate public activation.

## Owner source-collection command

Run in cPanel Terminal. This creates an archive only; no source, settings, bank
state, or database changes. It excludes configuration dumps/logs/backups. Review
the archive as sensitive before sharing. If tar fails, stop and send the error.

```bash
cd ~/public_html || exit
archive="booster-debug-pay002-final-$(date +%Y%m%d-%H%M%S).tar.gz"
tar --exclude='*.log' --exclude='*.bak' --exclude='*.sql' \
  --exclude='*.zip' --exclude='*.tar.gz' \
  -czf "$archive" \
  extension/pumb_credit \
  catalog/controller/checkout \
  catalog/model/checkout \
  catalog/view/template/checkout \
  catalog/controller/product/product.php \
  catalog/view/template/product/product.twig \
  catalog/view/javascript/common.js \
  catalog/view/javascript/checkout-state.js \
  system/library/cart/cart.php \
  && printf 'archive=%s\n' "$archive"
```

## Completion sequence

1. Codex verifies fresh code and prepares safe settings/schema checks; owner
   runs the bounded read-only check. Preserve the working Mono/cart/NP fixes.
2. Confirm test environment and private preview gate; coordinate bank support.
   Verify no-preview rejection without treating absence of an order as proof
   that the preview gate itself was exercised.
3. Owner creates a new shop order with PUMB, test phone, and four payments.
   Codex checks safely supplied term/amount evidence before callbacks overwrite
   the stored create payload. Bank staff advance client confirmation. Owner
   performs the agreed test shipment/refund actions. Require requested_term=4,
   bank term/product NEW_4, and final REFUND_FINISHED; no real shipment implied.
4. Close the actual remaining client-flow gaps: PAY-005 unavailable explanation,
   PAY-003 waiting/recovery, safe failure/cancel handling, duplicate protection,
   status mapping and NCRM behavior. Implementation requires bounded approval;
   this report does not authorize all of these changes.
5. Run unified Mono/PUMB and existing-payment regression, including failures,
   callback authentication/fallback, stock correction and delivery recalculation.
   Classify local fixtures, stage proof and production proof separately.
6. After owner/bank readiness decisions and layout approval, conduct a controlled
   production cutover with verified production endpoints/credentials/callbacks,
   mode separation and an agreed first real test. Public visibility is the last
   gate, not a way to test an unfinished integration.

## Validation / side effects

Document review and bounded source inspection only. PHP lint, runner
idempotence, deployment, bank calls and runtime QA: not performed / not
applicable to this turn. Original handoff and uploaded evidence were not edited.
Only this diagnostic report was added.

The raw bank chat and Gmail export are currently untracked inside the repo and
may contain credentials/private information. Do not stage, commit, publish or
bulk-copy them. No claim is made that untracked files are protected from an
unscoped `git add`; credential safety must be preserved during later Git work.
