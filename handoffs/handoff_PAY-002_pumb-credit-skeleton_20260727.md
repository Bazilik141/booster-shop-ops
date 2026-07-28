# Codex Handoff — PAY-002: PUMB "Сплачуйте частинами" — `extension/pumb_credit` skeleton

Date: 2026-07-27 | Parent: PAY-001 (Done) — clone-adjacent, not a shared codebase
Codex config: model=Sol · effort=xhigh

**Risky zone (AGENTS.md): checkout / payment / order status / DB.** This handoff
does not require live bank credentials to execute — those are config values
entered later, the same way mono's sandbox credentials were entered after
`mono_chast` was deployed. Do not block this round on the bank's response;
the OAuth2 client, transaction table, and callback routes can be built and
`php -l`-verified without a working token exchange.

## Context

PUMB's real integration protocol (obtained 2026-07-21, analyzed 2026-07-27) is
structurally different from monobank's: OAuth2/OpenID Connect instead of
HMAC-SHA256, no redirect flow (confirmation happens in the PUMB Online app), and
an unsigned callback body. Full protocol reference, all endpoints, and every
resolved owner decision are in `plans/PAY-002_pumb-protocol-revision_20260727.md`
(read this first — it supersedes §6.1–6.3 of
`plans/PAY_decomposition_mono-pumb-preorder_20260721.md`). Contract facts (rates,
shipment-signal timing, refund window) are in that decomposition file's §6.5.

Owner-approved, ready to implement:
- Scheme: hybrid (bank callback + our `GET /sf-credits/{id}` poll fallback).
- Our callback auth: HTTP Basic over TLS + IP allowlist (bank's source IPs —
  not yet supplied; the route must still enforce Basic auth now and be ready
  to add the IP check later without a redesign).
- Callback URLs (Variant A — same production host, two routes, revision §5.1):
  - prod: `index.php?route=extension/pumb_credit/payment/pumb_credit.callback`
  - test: `index.php?route=extension/pumb_credit/payment/pumb_credit.callbackTest`
- Order-status consolidation: rename the 6 existing mono-specific OC order
  statuses to a shared, provider-agnostic set of 5, and have `pumb_credit` write
  to the same set (revision §8a — table below). **This part touches
  `mono_chast`'s status-mapping code and the live `oc_order_status` table.**
  Owner approved the exact merge (активна+завершена → оформлено) 2026-07-27.

| Shared status (new) | Replaces (mono) | pumb_credit maps from |
|---|---|---|
| Розстрочка — очікує клієнта | ПЧ mono — очікує клієнта | `WAITING_CLIENT` |
| Розстрочка — очікує видачі | ПЧ mono — очікує видачу | `WAITING_STORE_CONFIRM` |
| Розстрочка — оформлено | ПЧ mono — активна + ПЧ mono — завершена (merged) | `FUNDED` |
| Розстрочка — повернено | ПЧ mono — повернена | `REFUND_FINISHED` |
| Розстрочка — відхилено | ПЧ mono — відхилена | `CANCELED_BY_CLIENT`, `CANCELED_BY_STORE`, `REJECTED`, `NO_LIMIT`, `OVER_LIMIT`, `CLIENT_NOT_FOUND`, `FAIL`, `PUSH_TIMEOUT`, `CONFIRM_TIME_EXPIRED`, `FAIL_OTP`, `IDENTIFICATION_FAILED` |

Precedent (same shape, different protocol — do not copy-paste, the auth layer
and client flow are genuinely different):
- `extension/mono_chast/catalog/model/payment/mono_chast.php` — `getMethods()` gate
- `extension/mono_chast/catalog/controller/payment/mono_chast.php` — `confirm()`/`callback()`/`poll()`
- `extension/mono_chast/admin/controller/payment/mono_chast.php` — settings page +
  manual confirm/reject actions. **Known past bug on this exact file:** it
  crashed on save because the class never declared `protected array $error = [];`
  before using `$this->error[...]` (OC4 `Controller::__get()` registry magic).
  Declare this property on `pumb_credit`'s admin controller from the start.
- `ocp5_mono_chast_transaction` — transaction table shape (idempotency key,
  state, raw payload, trace/flow id)

No cPanel backup was mounted in this Claude session, so none of the above paths,
table names, or the current `oc_order_status` IDs are independently verified
against live code in this handoff. **Codex must confirm every file path, the
OC4 extension-registry requirements (`oc_extension_install`/`oc_extension_path`
— this exact gap caused a separate bug round on `mono_chast`), and the real
`oc_order_status` rows against the newest cPanel backup before writing anything.**

## Scope (what to change)

- `extension/pumb_credit/catalog/model/payment/pumb_credit.php` — new. `getMethods()`
  gated by its own `payment_pumb_credit_status` flag (default `0`/off), min
  amount 500 UAH (confirm actual bank-side max before hardcoding — revision §7
  question 6 is still open with three conflicting figures: 100k/150k/300k).
- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` — new.
  OAuth2 token fetch (`POST https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token`,
  `client_id=EXT_OIC`, password grant) with caching respecting the 300s
  `expires_in` (do not fetch a token per request); `POST /sf-credits` create;
  `callback()` action answering `{"success":true,"error":null}` HTTP 200 on a
  well-formed body and rejecting anything failing Basic auth; `poll()` action
  calling `GET /sf-credits/{id}` as the hybrid fallback.
- `extension/pumb_credit/admin/controller/payment/pumb_credit.php` — new.
  Settings page (OAuth2 username/password fields, both callback route's Basic
  credentials, test vs prod toggle); manual shipment-confirm action calling
  `PATCH /sf-credits/{id}` `method=UPDATE`; cancel action calling `method=CLOSE`,
  `cancel_reason=CancelLead50`; refund action calling `POST /sf-credits` with
  `refund:true` (needs `agreement_number` from the stored `guarantee_letter`).
  Declare `protected array $error = [];` in the class body — see the mono
  precedent bug above.
- New table (name to confirm against DB prefix in the newest backup, e.g.
  `ocp5_pumb_credit_transaction`) — `store_order_id`, `cap_id`, `state`,
  `is_test` (boolean — required, see "two callback routes" below), raw payload,
  `agreement_number` (nullable until guarantee letter arrives), timestamps.
- Both callback routes (`...callback` and `...callbackTest`) must write to
  clearly separated state (the `is_test` column, or two tables) so a bank test
  callback can never touch a real order — this is a hard requirement, not a
  nice-to-have (revision §5.1).
- Order-status rename/consolidation (table above): rename the 6 existing
  `mono_chast`-specific `oc_order_status` rows to the 5 shared labels, remove
  the now-redundant 6th (the активна/завершена merge), and update
  `mono_chast`'s own status-mapping code to write the new shared labels.
  **This is a live-data rename — follow the DB-change rule below exactly.**

## What NOT to touch

- `checkout/checkout.twig` and the credit-provider selection modal — that is
  PAY-001-UI/PAY-003 scope, not this handoff. Do not add a PUMB card to the
  live modal in this round; `payment_pumb_credit_status` stays `0`.
- `mono_chast`'s create/callback/poll logic itself — only its status-label
  writes change (per the table above), nothing else in its request/response
  handling.
- `ncrm/supabase/functions/order-sync/index.ts` and any NCRM migration — that
  is NCRM-14, a separate authorized task. Coordinate payment-type codes
  (`credit_pumb_3/4/5`) with it but do not duplicate or edit its scope here.
- Any real customer order or transaction row — this round ships disabled
  (`payment_pumb_credit_status=0`) and touches no live order.
- Protected zones, unrelated to this task: `sitemap.xml`, `robots.txt`,
  redirects, canonical tags, `.htaccess`, fiscalization (Checkbox), Merchant
  feed, schema/JSON-LD.
- Do not hardcode a maximum order amount yet — three conflicting figures exist
  in the bank's own documentation (revision §3, §7 question 6); leave it as a
  configurable admin field, not a constant, until the bank answers.

## Likely files / areas (verify, not confirmed)

- `extension/mono_chast/` tree (see Context) — read for the real current
  structure before mirroring it; do not assume the paths above are exact.
- `oc_extension_install` / `oc_extension_path` registry — `mono_chast` needed a
  follow-up patch here (`PAY-001` round 6-7) because the first patch only
  populated the classic `{$prefix}extension` table. Populate both from the
  start for `pumb_credit`.
- `oc_order_status` — confirm the current 6 mono-specific rows' exact IDs and
  language rows (`uk`) before renaming.
- `system/library/url.php` isolation pattern from `PAY-001_simple_checkout_isolation_20260721.php`
  is mono-specific; `pumb_credit` should not need its own isolation patch if it
  never registers through the live `SimpleCheckout` `getMethods()` path — verify
  this assumption against the current checkout architecture before writing
  `getMethods()`.

## Acceptance criteria

- [ ] `php -l` passes on every new/changed file.
- [ ] OAuth2 token fetch works against a stub/mocked response in local
      validation (no bank credentials required for this check); token is
      cached and not re-fetched before `expires_in` elapses.
- [ ] New transaction table exists with the columns above; `is_test` correctly
      separates prod vs test callback writes — confirmed by two manual writes
      that never cross.
- [ ] `pumb_credit.callback` and `pumb_credit.callbackTest` both: return
      `{"success":true,"error":null}` HTTP 200 on a well-formed test body;
      return 401/403 on missing or wrong Basic credentials, and do not write
      any transaction row on that rejected request.
- [ ] Admin settings page for `pumb_credit` saves without error (no repeat of
      the `mono_chast` `$error`-property crash).
- [ ] `payment_pumb_credit_status=0` by default — method does not appear in any
      checkout, live or direct-URL.
- [ ] Admin → Orders → status dropdown shows exactly the 5 shared statuses (no
      `ПЧ mono —` labels remain, no PUMB-specific duplicates added).
- [ ] Existing mono checkout flow (Hutko/COD/IBAN/mono ПЧ regression) is
      unaffected by the status rename — a real or sandbox mono order still
      writes to and displays the correct (renamed) status.

## QA checklist (owner runs after deploy)

This round is a disabled-by-default skeleton, not a go-live — full
bank-integrated QA is `PAY-001-SMOKE` (`plans/PAY-001-SMOKE_unified-credit-qa_20260727.md`),
run once both `pumb_credit` and PAY-003 exist. For this specific round:

- [ ] Admin settings page opens and saves (OAuth2 fields, both callback
      credential pairs) without the known `$error` crash.
- [ ] `oc_order_status` dropdown shows 5 shared statuses; a fresh mono sandbox
      order still lands in and can move through the renamed statuses correctly.
- [ ] `pumb_credit.callbackTest` responds correctly to a manually-sent test
      request (curl with correct/incorrect Basic auth) — bank credentials not
      required for this, only the route's own auth.
- [ ] Method stays invisible on the live checkout (`payment_pumb_credit_status=0`
      confirmed after deploy, not just in code).

## Risks

- **Order-status rename is a live-data change.** Follow the DB-change rule in
  `AGENTS.md` exactly: explicit owner approval (already given 2026-07-27,
  reference this handoff), rollback SQL in the patch header, backup to
  `_patch_backups/` before write. If any order is currently sitting in one of
  the 6 old mono statuses at deploy time, the rename must carry it forward
  correctly, not orphan it.
- **Checkout/payment — HIGH-RISK zone** per AGENTS.md: ship disabled, keep the
  status flag off, run `bs-checkout-smoke` (full run is `PAY-001-SMOKE`) before
  any enablement.
- **Two callback routes writing to unseparated state would let a bank test
  callback corrupt a real order** — the `is_test` separation in Scope is not
  optional.
- **Do not guess the bank's real amount ceiling** (100k/150k/300k conflict,
  unresolved) — leave it configurable, not hardcoded.
- If anything above cannot be verified against the newest backup or current
  code, state that explicitly in the diagnostic rather than proceeding on
  assumption (per `AGENTS.md` evidence rules).

## Recommended status after execution

- `PAY-002` stays **In progress** — skeleton built and disabled, still waiting
  on the bank for OAuth2 credentials, source IPs, and the amount-ceiling
  answer before any live test is possible.
- `PAY-001-SMOKE` stays **Not started** — still blocked on PAY-003 and NCRM-14
  in addition to this round.
- Do not set anything to `Done` from this round; report back to Claude for
  Notion/dashboard update per the usual review flow.
