# PAY-002 — final test and cutover handoff

Date: 2026-08-31
Audience: **Codex (executor) and the owner.** Self-contained: everything needed
to finish PUMB without access to Claude's chat.
Author: Claude (chat), from repository evidence plus decisions taken in support
conversations. No new patch, no settings change, no status change.

Authority unchanged: bank applications, signatures, refunds, credential changes
and the public switch are **owner gates**. This document authorises none of them.

## 0. Where things stand in one paragraph

The bank side is proven on the test contour. The customer side is now built —
checkout drawer (WP2–WP4) and product page/modal (WP5) are deployed and owner-QA
passed. **The two have never met**: every application to date was created by the
diagnostic script, never by a customer choosing a term in the UI. That single
end-to-end run is the next step, and it is the last unknown before the release
gates in §5.

---

## 1. Environment and value matrix

### 1.1 Endpoints

| Item | Value | Status |
|---|---|---|
| OAuth (test) | `POST https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token`, `client_id=EXT_OIC`, `grant_type=password` | **verified** — token 200, `expires_in=300`, 2026-08-25/26 |
| API base (test) | `https://api.dts.fuib.com/ext-oic/galadriel/v1` | **verified** 2026-08-25/26 |
| OAuth / API base (production) | — | **UNKNOWN.** §7d: prod credentials were to be emailed in a separate message; no production endpoint is recorded anywhere in this repository. Required for cutover |
| Callback, production route | `https://boostershop.website/index.php?route=extension/pumb_credit/payment/pumb_credit.callback` | **verified** — bank posted to it 2026-08-25 |
| Callback, test route | `…/pumb_credit.callbackTest` | **verified** — bank posted to it, HTTP 200, from 2026-08-26 11:32:10 |
| Bank callback source IPs | `194.44.66.16/28`, same range for test and prod (§7d). Observed sender: `194.44.66.21` | **verified** |

`allowedIp()` does exact-string matching, not CIDR. If the allowlist fields are
used, all sixteen addresses must be entered individually:
`194.44.66.16,…,194.44.66.31`. An empty allowlist means the check passes — Basic
auth over TLS is then the only protection.

### 1.2 Fixed request values

| Field | Value | Status |
|---|---|---|
| `point_of_sale_code` | `1700001669IN020147` | verified |
| `partner_name` | `Boostershop Digital SF Internet` | verified |
| `channel_type` | `INTERNET` | verified |
| `flow.type` | `DIGITAL_SF` | verified |
| `store_user_login` | any id; `boostershop-oc` used | verified — bank: no validation; not the cause of the 2026-08-25 `403` |
| `promo_product_code` | omitted deliberately | verified, §7a Q7 |

### 1.3 Settings — desired state for the test window vs last observed

Setting keys are `payment_pumb_credit_*` in `oc_setting`, editable in the PUMB
admin form.

| Key | Desired for the test | Last observed | Status |
|---|---|---|---|
| `status` | `1` **only inside the test window**, `0` before and after | `0` outside windows | verified by design |
| `public` | `0` | `0` | verified — added by WP1 |
| `preview_token` | non-empty, owner-held | set by owner | last-known 2026-08-30 |
| `test_mode` | `1` (test contour) | `1` | last-known 2026-08-26 |
| `terms` | `[3,4,5]` | `[3,4,5]` | verified correct for production, §7e — **do not change** |
| `min_total` / `max_total` | `500` / `500000` | `500` / `500000` | verified, §7a Q6 |
| `test_callback_user` | `pumb_test_cb` | `pumb_test_cb` | verified |
| `test_callback_password` | rotated 2026-08-26, shared with bank | set | verified |
| `callback_user` / `callback_password` (production) | **deliberately empty** | empty | verified — this is what makes a bank test callback return 401 against production state. Do not fill before cutover |
| `oauth_url`, `api_base`, `oauth_username`, `oauth_password` | test values | set | last-known 2026-08-26 |
| `status_waiting_client` / `_waiting_store` / `_funded` / `_returned` / `_failed` | mapped onto the `ПЧ mono — …` statuses | unchanged | verified — see §5, item §8a |

**How to verify current state without changing anything:** open the PUMB settings
form in the OpenCart admin and read the fields; or read `oc_setting` rows for
`code='payment_pumb_credit'`. Absent row = disabled (OpenCart's normal
representation of an unchecked switch); the guard treats absent and `'0'` alike.

### 1.4 Secrets — never written here

OAuth username/password, callback Basic passwords, the preview token and any
production credentials live **only** in the OpenCart admin form and the owner's
own records (bank email + the integration Telegram chat). Do not paste any of
them into the repository, a report, a patch, or a chat message. When a runbook
step needs one, it names the setting field, not the value.

---

## 2. Bank-issued test data — **MISSING, and it blocks the runbook**

**PUMB has never supplied test phone numbers.** Two dated records, both in
`plans/PAY-002_pumb-protocol-revision_20260727.md`:

- §7a Q9 (2026-07-28): test phone numbers "not documented; will come from
  colleagues `@Andrii_sitt` and Vasyl during actual test-contour work";
- §7d (2026-07-30): "Test phone numbers — **Pending** — Andrii: «будемо надамо»".

Nothing later supersedes this. Any list of PUMB test phones circulating in a
chat is unverified; do not use one without the bank naming it. The
`+38000000000{1,2,3}` triple in §3 of the protocol revision is explicitly marked
there as an unverified carry-over from the monobank plan — **it is not PUMB test
data.**

What *is* known about the client-signing step:

- **There is no redirect flow.** The customer receives a push notification in the
  **PUMB Online app** and signs with an **OTP inside that app** (§3 divergence
  table, verified against the protocol).
- Phone format in the payload: `+XXXXXXXXXXX` (`customer.phone`).
- Consequence: whoever signs the test application must have the PUMB Online app
  installed and logged in for the phone number sent in the payload.
- On 2026-08-26 the client-confirmation step **did** complete for `cap_id
  19040054` (callback at 11:39:37 Kyiv, `agreement_number 5001904005401` written
  automatically). **Which phone number was used, and who signed, is not recorded
  anywhere in this repository.** The owner ran that test and can answer in one
  line — see §7.

Also available instead of a full cycle, for polling checks only:
`GET /sf-credits/{id}` with **static fixture ids 1–13** returns fixed states
(`IN_PROGRESS`, `WAITING_CLIENT`, `FUNDED`, `REJECTED`, …) without creating
anything (§7a Q9, verified in the 2026-08-25 run for ids 1 and 13).

---

## 3. Support decisions and traps

Everything below is settled unless marked open. Where a later answer overturned
an earlier one, both are shown so nobody re-derives the wrong one.

### 3.1 Amounts

- **Hryvnia, decimal — not kopiykas.** `700.0` was accepted and the guarantee
  letter returned `net_amount: 700.0`. Bank confirmed in chat 2026-08-25.
  Overturns the written §7b Q5 answer of "integer, kopiykas". The deployed module
  already sends hryvnia.
- Bounds: **500 min / 500 000 max**, bank-stated (§7a Q6), overriding the three
  conflicting document figures (100k/150k/300k). Numeric format
  `<6 digits>.<2 digits>`.
- `sum(invoices[].total_amount)` **must equal** `credit_request.amount`.
- `invoices[].date` **must equal the current date**.

### 3.2 Terms

| Contour | 3 | 4 | 5 |
|---|---|---|---|
| test | ✅ `201` | ✅ `201` | ❌ `400 "Term 5 is not supported"` |
| production | ✅ | ✅ | ✅ — Roman Nazarenko, 2026-08-26: «На проді так» |

`payment_pumb_credit_terms = [3,4,5]` is correct and must not be narrowed on the
stage evidence. **Term 5 can never be rehearsed on the test contour** — the first
real 5-payment application will be a production one. Open owner decision: ask the
bank to enable 5 on stage, or accept the gap knowingly.

### 3.3 Application lifetime

- §7b (2026-07-28): 24 hours — **superseded**.
- §7c (2026-07-21 exchange): 7 days — supported.
- §7d (2026-07-30): 7 days wins, bank-side setting.
- §7e (2026-08-26): «налаштуємо 7 днів» — **future tense**, the bank was applying
  it for our POS as a result of that exchange.
- Owner (2026-08-28): reported closed by the bank.
- **Not yet re-tested with a deliberately delayed shipment confirmation.** §7e
  asked for exactly that before go-live. Not blocking the run in §4; worth one
  deliberate slow run before public launch.
- This explains the `FAIL` on `19039895`: ~22 h between creation and shipment
  confirmation under the pre-widening window. `19040054` completed in seven
  minutes and was unaffected. The `FAIL` cause is **open** but no longer
  suspicious.
- Owner's own operating habit — ship and signal within 24 h — remains a good
  habit, no longer a hard deadline.

### 3.4 Callbacks

- Body: `{"cap_id":…, "state":"…", "guarantee_letter": …}`. Expected reply
  `HTTP 200` + `{"success": true, "error": null}`.
- **`guarantee_letter` is absent on the first callback** and appears only after
  the client signs (§7d Q4). Deployed code handles this.
- **Retries: 3 attempts at ~10-second intervals** per state change — observed in
  the production access log 2026-08-25. Overturns §7b Q3 ("no retries").
- Basic auth per route, separate credential pairs. The 2026-08-25 `401` storm was
  **not** header stripping — a probe proved the `Authorization` header reaches
  PHP intact, and the `.htaccess` change built for that hypothesis was rolled
  back; the live file carries no PAY-002 marker. The cause was a stale password
  on the bank's side, rotated 2026-08-26.
- Test traffic hitting the production route returns `401` **by design** —
  production callback credentials are empty on purpose.
- Hardening idea, not implemented: re-verify state with `GET /sf-credits/{id}`
  before trusting a callback body, since IP allowlisting may be the only other
  layer.

### 3.5 States

Non-final: `WAITING_CLIENT`, `WAITING_STORE_CONFIRM`, `FUNDED`.
Final: `CANCELED_BY_CLIENT`, `CANCELED_BY_STORE`, `REJECTED`, `NO_LIMIT`,
`OVER_LIMIT`, `CLIENT_NOT_FOUND`, `FAIL`, `PUSH_TIMEOUT`,
`CONFIRM_TIME_EXPIRED`, `FAIL_OTP`, `REFUND_FINISHED`, `IDENTIFICATION_FAILED`.
`GET` may also return `IN_PROGRESS`.

`FUNDED` is **not terminal** — it can still move to `REFUND_FINISHED`.
The spelling is **`FUNDED`**, uppercase, verbatim from the API. Two humans wrote
`FOUNDED`; the defensive patch built for that stays unapplied.

### 3.6 Shipment, cancel, refund

- Shipped: `PATCH /sf-credits/{id}` `{"method":"UPDATE","goods_shipped":true,"flow":{"type":"DIGITAL_SF"}}`.
- Cannot ship: `PATCH … {"method":"CLOSE","cancel_reason":"CancelLead50",…}` — **never exercised**.
- Refund: `POST /sf-credits` with `id`, `agreement_number`, `refund:true`,
  `amount`, `point_of_sale_code`, `partner_name`, `flow`, `external_id`.
- **`agreement_number` comes only from the guarantee letter**
  (`guarantee_letter.content.customer_agreement.number`), which arrives with the
  post-signing callback. No letter stored ⇒ no refund possible. This is what
  blocked the refund on 2026-08-25.
- `PATCH` returns **`409` when the application is not in `WAITING_STORE_CONFIRM`**
  — treat as "not yet shippable", not a hard failure.
- Timing: after the shipment signal, funds reach the merchant account in
  **5–10 minutes** (Roman, §7c).
- Shipment signal at **carrier handover** is the agreed flow and explicitly
  accepted by the bank. The alternative (signal immediately, accept return risk)
  was considered and **not adopted** — do not reopen without a reason.
- `X-Flow-Id` is **required on `GET`** (400 without it) and sent per request by
  the deployed `api()`.
- The bank can restore a "fallen" application on request (§7d) — useful for a
  stuck order, not a code path.

### 3.7 Still open with the bank

1. Written per-POS term list for `1700001669IN020147` on **both** contours —
   requested 2026-08-25, production answered verbally 2026-08-26, stage list
   still not in writing.
2. Root cause of `FAIL` on `19039895` — asked, no answer.
3. Whether term 5 can be enabled on stage — not yet asked as a decision.
4. Production contour switch: what the bank requires from us, and who approves
   the brand layout (contract п. 2.2.8) — **never asked; no contact named.**
5. Duplicate `store_order_id` behaviour, rate limits — undocumented on the bank's
   side; to be discovered empirically. PUMB explicitly returns `409` on a
   duplicate per the plan's earlier note, but this was never exercised.

---

## 4. End-to-end test runbook — one application, test contour, term 4

**Why term 4.** The drawer's default preferred term is 3, and 5 cannot be
exercised on stage. Only a non-default, stage-supported term distinguishes "the
customer's choice propagated" from "the code fell back to the default". A test at
term 3 proves nothing.

**Blocked until §7 item 1 is answered** (which phone signs). Everything else is
ready.

### Preconditions — owner

| # | Action | Expected | Stop rule |
|---|---|---|---|
| P1 | Confirm `test_mode=1`, `api_base`/`oauth_url` point at `*.dts.fuib.com` | test contour active | any production endpoint ⇒ stop |
| P2 | Confirm `public = 0`, production callback user/password **empty** | as above | non-empty ⇒ stop, clear them first |
| P3 | Confirm `terms = [3,4,5]`, `min_total = 500` | as above | — |
| P4 | Delete `patches/PAY-002_bank-test-drive_diagnostic_20260824.php` from `~/public_html` if still present — it does not self-delete and can create **real** applications with `--live` | file absent | — |
| P5 | Use a **new** disposable order, not #332. Order 332 still carries three `is_test=1` rows (`19039867 FUNDED`, `19039895 FAIL`, `19040054 REFUND_FINISHED`) and `transactionByOrder()` matches on `order_id` | fresh order id | reusing 332 ⇒ stop |
| P6 | Enable `payment_pumb_credit_status = 1` **for the window only** | PUMB live behind the token | — |

### Negative case first — no order created

| # | Executor | Action | Expected | Evidence |
|---|---|---|---|---|
| N1 | owner | In a browser session that has **never** opened the preview-token URL (private window), open a product page | PUMB shows `СКОРО БУДЕ`; no PUMB in checkout | screenshot |
| N2 | owner | From that same session, call the PUMB confirm route directly | refused server-side, **no bank call** | response body + a check that no new `pumb_credit_transaction` row and no new `cap_id` appeared |

N2 exercises the gate that replaced the disabled-status safety net. It creates no
order because `confirm()` refuses before any bank work.

### Main run

| # | Executor | Action | Expected | Evidence to retain | Stop rule |
|---|---|---|---|---|---|
| 1 | owner | Open the preview-token URL, then a product page ≥ 500 UAH | PUMB is a real provider row, not `СКОРО БУДЕ` | screenshot | placeholder still shown ⇒ stop, WP5 gate wrong |
| 2 | owner | Open the credit modal, pick **PUMB**, **4 платежі**, «Додати й оформити» | lands on checkout, item in cart | screenshot | — |
| 3 | owner | In the checkout drawer, confirm the PUMB card shows **4** preselected | 4 active, not 3 | screenshot | 3 preselected ⇒ stop; the modal→checkout term hand-off is broken |
| 4 | owner | Place the order | `POST /sf-credits` → `201` with a `cap_id` | `cap_id`, `X-Flow-Id`, HTTP status | non-201 ⇒ stop, capture the body |
| 5 | Codex | Read the payload actually sent | `credit_request.term = 4`; `credit_request.amount` == `sum(invoices[].total_amount)`; hryvnia decimal; `invoices[].date` = today; `store_order_id = OC-<order_id>` | the payload | any mismatch ⇒ stop |
| 6 | Codex | Read the transaction row (read-only): `SELECT pumb_credit_transaction_id, order_id, cap_id, state, requested_term, is_test, agreement_number FROM ocp5_pumb_credit_transaction WHERE order_id=<id> ORDER BY 1 DESC LIMIT 5;` | `requested_term = 4`, `is_test = 1`, `cap_id` stored | query output | `requested_term` ≠ 4 ⇒ **PAY-004 defect**, stop |
| 7 | owner | Sign in the PUMB Online app (push + OTP) | callback within ~1 min; state → `WAITING_STORE_CONFIRM`; `agreement_number` written automatically | access-log line (`194.44.66.*`, `pumb_test_cb`, `200`), row state | no callback ⇒ check the callbackTest route and Basic password before anything else |
| 8 | Codex | Read the guarantee letter via `GET /sf-credits/<cap_id>` (X-Flow-Id required) | `product.name = "Сплачуйте частинами NEW_4"`, `term: 4` | the non-personal fields only | `NEW_3` ⇒ **the customer's term did not reach the bank** — the whole point of the test; stop |
| 9 | owner | Admin «Підтвердити видачу» (`PATCH goods_shipped`) | `{"http":200}`, then `FUNDED` within minutes | response + state | `409` ⇒ not in `WAITING_STORE_CONFIRM` yet, wait, do not retry blindly |
| 10 | Codex | Record the OpenCart order status after each transition and what NCRM received | evidence for §8a and NCRM-14 | status names, sync payload | not a pass/fail gate here |
| 11 | owner | Admin «Повернення» for the full amount | `201`, then callback → `REFUND_FINISHED` | response + row state | leaves no open test deal |
| 12 | owner | Set `payment_pumb_credit_status = 0` | PUMB gone from both surfaces | — | — |
| 13 | owner | Run the non-credit half of `bs-checkout-smoke` — Hutko, COD, IBAN | unchanged behaviour | checklist | WP3 replaced `flattenPaymentMethods()`, which builds **every** payment row |

**Pass criteria: step 6 shows `requested_term = 4` AND step 8 shows `NEW_4`.**
Anything else returns to Codex as a defect in the customer path.

**Rollback at any point:** disable `payment_pumb_credit_status`. That removes PUMB
from both surfaces immediately and needs no file restore. Per-patch file rollback
paths are in each patch's own report under `_patch_backups/`.

**Data handling.** The guarantee letter contains a test persona's tax ID,
identity-document data and a Base64 PDF. It is synthetic, but it is **not** to be
committed to this repository — record only the non-personal fields, as the
2026-08-25 result document did. Retention rules: protocol revision §10.

### Housekeeping after the run

```sql
-- read-only check first
SELECT cap_id, state, requested_term, is_test FROM ocp5_pumb_credit_transaction WHERE order_id=<new test order>;
-- cleanup of the older fixture rows, owner-approved, when order 332 is to be reused
DELETE FROM ocp5_pumb_credit_transaction WHERE order_id=332 AND is_test=1;
```

---

## 5. Remaining release gates

| Gate | Proven | Missing | Files / settings | Blocks public launch |
|---|---|---|---|---|
| **PAY-004** — customer-selected term | server half deployed 2026-08-25, review OK; term 4 reached the bank **from a script** (`NEW_4`, 2026-08-26) | the same, **from the UI** — steps 3/5/6/8 above | `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` | **yes** |
| **PAY-005** — «недоступно» row for PUMB | scoped, Notion `3cc6bf20-bdb4-8135-a249-c949fff3e7bb`, dashboard mirrored | implementation: export `pay002_credit_gate`, rework the blocked-credit row | `catalog/controller/checkout/payment_method.php`, `…/checkout/payment_method.twig` | **yes — hard gate before `public = 1`** |
| **§8a order statuses** | decision taken 2026-07-27 (6 mono → 5 shared) | not implemented; PUMB still maps onto `ПЧ mono — …` | `payment_pumb_credit_status_*` settings + status set | no, but ugly in production |
| **NCRM-14** | migration + order-sync committed | never run against a real PUMB order | `ncrm/…/order-sync` | no |
| **Bank brand/layout approval** (contract п. 2.2.8) | — | **not started, no contact named** | — | **yes** — plausibly the longest lead item; start now, it depends on nothing |
| **Production cutover** | — | production OAuth/API endpoints unknown; production callback credentials to be filled; `test_mode → 0`; what the bank requires never asked | `payment_pumb_credit_*` | **yes** |
| **PAY-001-SMOKE** | plan exists | not run; can only ever cover terms 3/4 on stage | `plans/PAY-001-SMOKE_unified-credit-qa_20260727.md` | **yes** |

Do not count any script-created application as UI proof. `19039867`, `19039895`
and `19040054` were all created by
`patches/PAY-002_bank-test-drive_diagnostic_20260824.php`.

---

## 6. Ready-to-use references

- `diagnostics/PAY-002_bank-test-drive_result_20260825.md` — the single most
  useful file: verbatim runs, timings, `X-Flow-Id` values, the callback
  investigation, the term matrix, housekeeping SQL.
- `plans/PAY-002_pumb-protocol-revision_20260727.md` — protocol reference §4,
  callback URL decision §5.1, bank answers §7a–§7e, order statuses §8a,
  retention §10. **Read the amendment blocks, not just the early sections.**
- `diagnostics/PAY-002_verification-ledger-and-next-bank-test_20260831.md` — what
  is proven vs not, and why the product page was gated rather than opened.
- `handoffs/handoff_PAY-002_session-continuation_20260826.md` — five facts that
  overturn older documents; §10 carries the 2026-08-28 owner updates.
- `handoffs/handoff_PAY-002_pumb-product-page-card_20260831.md` — WP5 scope,
  including the "no commission claim for PUMB" rule.
- Patch reviews, rounds 1–5 of WP1/WP2 and 1–3 of WP3 — in `diagnostics/`,
  named `…_review*_2026083*.md`.
- **Diagnostic runner**: `patches/PAY-002_bank-test-drive_diagnostic_20260824.php`
  — kept in the repository, must not stay in `~/public_html`. It does not
  self-delete and `--live` creates real applications. Prefer the UI for this
  round; use the runner only to read a state.
- **Known-good evidence to compare against**: `cap_id 19040054` — full lifecycle
  with callbacks, term 4, `NEW_4`, `agreement_number 5001904005401`.
- **Logs**: production access log for callback deliveries — filter on
  `194.44.66.` and `pumb_credit.callback`. OpenCart error log for PHP/Twig
  errors after a deploy.
- Safe read-only DB checks are in §4; nothing else in this handoff needs a write.

---

## 7. First next action, and what is genuinely missing

**First action — owner, one line, before anything else:**
answer which phone number signed `cap_id 19040054` on 2026-08-26, and whether
that phone can sign again (PUMB Online app installed and logged in). Everything
in §4 is ready except this; without it the test cannot reach step 7.

Then, in order: run §4 as written, starting with the negative case.

**Missing inputs, all owner-held or bank-held:**

1. The signing phone for the test contour — see above. Bank-issued test phones
   were promised on 2026-07-30 and never delivered; if the owner used his own
   number, say so and the runbook uses it again.
2. Production OAuth and API endpoints — never recorded.
3. Written stage/production term list per POS — requested, still open.
4. A named contact for brand/layout approval — never asked.
5. Whether to ask the bank to enable term 5 on stage, or to accept that the
   first 5-payment application will be a production one — **owner decision, still
   open.**

Nothing in this list blocks §4 except item 1.
