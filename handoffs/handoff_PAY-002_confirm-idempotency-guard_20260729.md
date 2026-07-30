# Codex Handoff — PAY-002: PUMB confirm idempotency guard

Date: 2026-07-29 | Parent: PAY-002 PUMB skeleton deployed, disabled
Codex config: model=Sol · effort=xhigh

**Risky zone:** payment / checkout / order state / DB.

## Context

The PAY-002 PUMB skeleton was deployed on the owner host on 2026-07-28 and
remains disabled (`payment_pumb_credit_status=0`). Its status consolidation,
PUMB admin settings page, and safe max amount of 500000 UAH were owner-checked.
This is deployment evidence only; no PUMB OAuth or bank transaction has run.

The bank subsequently confirmed a critical behavior: `POST /sf-credits` has
**no uniqueness constraint on `store_order_id`**. A repeated create request
generates a separate application with a new `cap_id`. The deployed
`extension/pumb_credit/catalog/controller/payment/pumb_credit.php` currently
calls create without an app-side idempotency guard. A double-click, refresh, or
resubmission could therefore create two credit applications for one OpenCart
order once PUMB is enabled.

Read first:

- `plans/PAY-002_pumb-protocol-revision_20260727.md` sections 7b, 7c, and the
  "New finding — no app-side guard against double POST /sf-credits" section.
- `diagnostics/PAY-002_pumb-credit-skeleton_report_20260728.md` for what was
  actually deployed and the owner QA evidence.
- `AGENTS.md`, `CODEX_WORKFLOW.md`, and the current dirty-tree status.

## Preconditions / evidence gate

Codex has no server access. Before generating a mutation patch, use the newest
owner cPanel backup or request a narrow fresh archive containing:

```bash
tar -czf booster-debug-pay002b.tar.gz \
  extension/pumb_credit/catalog/controller/payment/pumb_credit.php \
  extension/pumb_credit/admin/controller/payment/pumb_credit.php \
  extension/pumb_credit/admin/view/template/payment/pumb_credit.twig \
  config.php
```

Do not infer that the deployed controller still equals the previous runner's
embedded source. The final host runner must preflight the real
`{DB_PREFIX}pumb_credit_transaction` schema and prove the columns/indexes it
uses before any mutation.

## Scope (what to change)

- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` — make
  `confirm()` safe against duplicate bank creates for the same OpenCart order
  and contour (`order_id` + `is_test`).
  - Before `POST /sf-credits`, serialize competing create attempts for that
    exact order/contour using a production-compatible DB mechanism verified
    against the fresh source/schema.
  - If any transaction already exists for the order/contour, return its known
    `cap_id` and state without another bank `POST`. Do not automatically create
    a second PUMB application, including after terminal/failed states; manual
    recovery is safer until a separate owner-approved policy exists.
  - Create a durable local reservation before the outbound request, so a second
    browser request cannot pass the same empty-table check concurrently.
  - If the bank call fails or returns no `cap_id`, preserve auditable local
    failure state/payload without exposing credentials. A retry must not happen
    implicitly from another browser submission.
  - Release any request-level lock in every success/failure path. Do not hold a
    DB lock while performing broad unrelated work.
  - Keep PUMB disabled; this round must not add checkout UI, enable a method,
    or create a real customer order.

- `patches/PAY-002_confirm-idempotency-guard_20260729.php` — one uploadable
  self-contained PHP runner. It must backup the controller and DB evidence,
  verify anchors exactly, preflight the exact DB connection/schema, run
  `php -l`, restore source on failure, include rollback SQL for any approved DB
  schema/data mutation, be idempotent, log changed files, and self-delete only
  after `done=ok`.

- `diagnostics/PAY-002_confirm-idempotency-guard_report_20260729.md` — record
  source evidence, exact idempotency design, local validation, rollback,
  deployment command, and remaining bank-dependent QA.

## Callback credential preparation (owner action, no code change)

After the guard patch is reviewed/deployed, the owner generates two distinct
Basic-auth user/password pairs (test and production) in a password manager and
saves them in the existing PUMB admin settings. Do not put values in source,
patches, diagnostics, Git, chat, or a handoff. Provide the bank's test pair
only through a bank-approved secure channel after they identify the recipient.

This handoff does not authorize entering OAuth credentials, enabling the
payment method, or calling PUMB APIs. Those actions wait for bank test access
and the owner QA gate.

## What NOT to touch

- `checkout/checkout.twig`, credit-provider modal, and PAY-003 shared waiting
  screen — separate UI scope.
- `extension/mono_chast/` — no mono change in this round.
- PUMB admin settings/template except an evidence-proven minimal change needed
  for the guard; do not redesign the form.
- `ncrm/`, Supabase migrations, order-sync, `payment_method_code`, and
  `credit_pumb_3/4/5` — NCRM-14 remains separate and waits for a real order.
- Existing status labels, `oc_order_status`, fiscalization, merchant feeds,
  SEO files, `.htaccess`, and production credentials.
- PUMB polling cadence/UI. Bank permits one fallback GET per pending
  application per 30 seconds; PAY-003/PAY-001-SMOKE owns any browser/UI poll
  scheduling.

## Acceptance criteria

- [ ] Fresh source/schema evidence is recorded before patch generation.
- [ ] First create attempt may issue one bank `POST`; a concurrent or later
      repeat for the same order/contour issues zero additional create requests
      and returns the stored application identity/state.
- [ ] A failed/no-`cap_id` create is auditable and does not trigger implicit
      browser retries or a second bank application.
- [ ] Test and production transactions remain separated by `is_test`.
- [ ] `php -l` passes on every changed PHP file; focused fixture/mock validation
      proves reservation, repeat-submit, failure, and lock-release paths.
- [ ] `payment_pumb_credit_status` remains `0` after deployment.
- [ ] No OAuth/Basic credentials, customer data, or payment identifiers appear
      in repository files or diagnostics.

## Owner QA after deploy

- [ ] Confirm PUMB remains absent from checkout while disabled.
- [ ] With a non-bank fixture/mocked transport only, repeat the same confirm
      request and verify one local transaction/reservation and no second create
      call.
- [ ] Generate and store separate test/production callback Basic credentials in
      the PUMB settings, then save without enabling PUMB. Keep values private.
- [ ] Send the bank the prepared integration request; wait for test OAuth
      credentials, test instructions/phones, and any callback IP information.
- [ ] Do not enable PUMB until bank test-contour QA, PAY-003, and
      PAY-001-SMOKE are complete.

## Bank facts and remaining dependencies

- Resolved: 500–500000 UAH, no bank callback retries, integer amounts in
  kopiykas, no hard rate limit (poll no more than once per 30 seconds), and
  duplicate `store_order_id` creates a separate PUMB application.
- Still external: test/prod OAuth credentials; test phones/instructions;
  callback source IPs or confirmation they are not static; written resolution
  of the 24-hour vs 7-day `WAITING_STORE_CONFIRM` TTL conflict; exact live
  `FUNDED` spelling to validate against test responses.

## Recommended status after execution

- PAY-002 remains **In progress**: code may be safe for a future test contour,
  but the payment method stays disabled until bank access and owner QA.
- PAY-001-SMOKE remains **Not started**. Do not change Notion or
  `ROADMAP_FLOW` in this implementation round without separate owner authority.
