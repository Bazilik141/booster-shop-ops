# PAY-002 — PUMB protocol revision (2026-07-27)

Supersedes sections 6.1–6.3 of `plans/PAY_decomposition_mono-pumb-preorder_20260721.md`.
That document's PUMB technical model was written from the general PDF "v2.4" and
from a verbal summary. The owner has now supplied the bank's actual integration
package. **The real protocol is structurally different from what section 6 assumed.**
Do not build PAY-002 from section 6.1/6.2.

Roadmap: PAY-002 · Risky zone: checkout / payment / order status
Status of this document: planning revision. No code, no patch, no external write.

---

## 1. Outcome

The 2026-07-21 assumption "PUMB is ~90% the same as mono, clone the mono_chast
architecture" is **false at the transport and client-flow level**. It remains true
only at the lifecycle level (create → client acts → shipment confirm → refund).

Three assumptions that must be discarded:

1. **No HMAC signature.** Authentication is OAuth 2 / OpenID Connect (Keycloak),
   password grant, against a bank-issued username and password.
2. **No redirect flow.** There is no `redirect_url` / `return_url`. The customer
   receives a push in the PUMB Online app and signs there. Our site never hands
   the browser to the bank.
3. **No callback signature.** The bank's callback carries no signature field.
   Callback trust must come from our own endpoint authentication plus an IP
   allowlist.

Consequence: the mono transaction-table / state-machine / poll-fallback *shape*
is still reusable, but the API client, the auth layer, and the entire checkout
UX branch are new code, not a copy.

## 2. Evidence

Owner-supplied archive `Інтеграція ПУМБ Сплачуй частинами.zip` (received
2026-07-21, files dated 2026-07-21 11:04–11:47):

| File | Role |
|---|---|
| `Сommunication_protocol_Digital_Installment_active_passive_scheme.pdf` | Primary spec — auth, create, callback, status, shipment, refund |
| `Сommunication_protocol_Active_scheme_Digital_Installment_promo_10.pdf` | Active-scheme variant with `promo_product_code` |
| `Сommunication_protocol_Passive_scheme_Digital_Installment_promo.pdf` | Passive-scheme variant |
| `get partners operations API_звітність_10.07.2025.pdf` | Settlement register API (`GET /partners-operations`) |
| `Ключова інформація про банківський продукт ... Сплачуйте частинами ПУМБ.pdf` | Mandatory product disclosure and brand/placement rules |
| `Логотипи по Сплачуйте частинами та ПУМБ.zip` | Official logo assets (SVG / PNG / JPG + bank logo) |
| `Уточнення при інтеграції 1.docx` | Bank's callback questionnaire and proposed callback format |

Bank contact: Роман Назаренко. Bank-issued identifiers, provided 2026-07-21:

- `partner_name` = `Boostershop Digital SF Internet`
- `point_of_sale_code` = `1700001669IN020147`

Not yet supplied by the bank: stage/production credentials, base URLs for
production, callback source IPs, promo product codes, retry policy.

## 3. Divergence table — 2026-07-21 plan vs actual protocol

| Item | Section 6 assumption | Actual (archive) |
|---|---|---|
| Auth | `Base64(HMAC-SHA256(body, secret))`, headers `X-Store-Id` / `X-Signature` | OAuth 2 / OpenID Connect. `POST https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token`, form-encoded `client_id=EXT_OIC`, `username`, `password`, `grant_type=password` → `Bearer` JWT, `expires_in` 300 s, `refresh_expires_in` 1800 s |
| API base (test) | not stated | `https://api.dts.fuib.com/ext-oic/galadriel/v1` |
| Client flow | redirect to bank `redirect_url`, return via `return_url` | No redirect. Push notification in the PUMB Online app; customer signs with OTP in the app |
| Create | `store_order_id`, products, kopiykas | `POST /sf-credits`, JSON, **amounts in hryvnia**, response `{"id": <cap_id>}` HTTP 201 |
| Idempotency | duplicate `store_order_id` → `409` | `store_order_id` documented as "unique within the application"; no `409`-on-duplicate behaviour documented. **Unverified — ask the bank** |
| Callback signature | signature in body | **None.** Body is `{"cap_id", "state", "guarantee_letter"}`; we must answer HTTP 200 with `{"success": true, "error": null}` |
| Callback SLA | 200 within 10 s, 5 retries every 15 min | **Not stated in this archive.** Unverified |
| IP allowlist | bank whitelists our IP | Reversed in practice — we need the bank's *source* IPs to allowlist their callback. **Ask the bank** |
| Min amount | 1000 UAH | **500 UAH** (API validation message and product sheet both say 500) |
| Max amount | 100 000 ПЧ / 300 000 credit | Three different figures inside the same package: active-passive protocol says **150 000**, both promo-scheme protocols say **100 000**, the product sheet says **300 000**. **Conflict — ask the bank** |
| Terms | 3/4/5 only | API accepts `[2,3,4,5,6,7,8,9,10,12,15,18,20,24]`. Our 3/4/5 restriction stays an owner business rule enforced on our side |
| Confirmed state name | `FOUNDED` | `FUNDED` |
| Rate limit | 100 req/min → `429` | Not stated in this archive. Unverified |
| Sandbox test phones | `+38000000000{1,2,3}` | Not stated in this archive. Unverified |
| Shipment confirm | mono-style confirm | `PATCH /sf-credits/{id}` with `method: "UPDATE"`, `goods_shipped: true`, `flow.type: "DIGITAL_SF"` |
| Cannot ship | not specified | `PATCH /sf-credits/{id}` with `method: "CLOSE"`, `cancel_reason: "CancelLead50"` |
| Refund | separate endpoint | Same `POST /sf-credits` with `{id, agreement_number, refund: true, amount, point_of_sale_code, partner_name, flow}` |

## 4. Actual protocol — reference summary

### 4.1 Authorization

```
POST https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token
Content-Type: application/x-www-form-urlencoded
client_id=EXT_OIC&username=<bank-issued>&password=<bank-issued>&grant_type=password
```

Access token TTL 300 s. The module must cache the token and refresh it, not
request one per API call. Credentials are stored the same way as the mono store
secret — never in code, chat, or a commit.

### 4.2 Create application

```
POST https://api.dts.fuib.com/ext-oic/galadriel/v1/sf-credits
Authorization: Bearer <token>
X-Flow-Id: <GUID per request>
```

Body fields relevant to our configuration:

- `store_order_id` — OpenCart order id (unique)
- `point_of_sale_code` — `1700001669IN020147`
- `partner_name` — `Boostershop Digital SF Internet`
- `channel_type` — `"INTERNET"` (so `store_user_login` is **not** mandatory; it is
  required only for `"POS"`)
- `flow.type` — `"DIGITAL_SF"`
- `customer` — `phone` required in `+XXXXXXXXXXX` format; name fields optional
- `invoices[]` — `date` (must equal the current date), `invoice_number`,
  `goods[]` (`name`, `count`, `amount`), `total_amount`
- `credit_request` — `term`, `amount`, optional `promo_product_code`

Hard validation rules taken from the error table:

- `sum(invoices[].total_amount)` must equal `credit_request.amount`
- `invoices[].date` must equal the current date
- amount must be between 500 and 150 000 in the active-passive protocol, but the
  two promo-scheme protocols in the same package state 500–100 000 — see section 7
- numeric bound is `<6 digits>.<2 digits>`

Response: HTTP 201, `{"id": <cap_id>}`. `cap_id` is the key we store and the key
the callback uses.

### 4.3 Status callback (bank → us)

```
POST <our callback URL>
{"cap_id": 49791, "state": "WAITING_CLIENT", "guarantee_letter": null}
```

Expected answer: HTTP 200 + `{"success": true, "error": null}`; failure form is
`{"success": false, "error": "<text>"}`.

States (non-final): `WAITING_CLIENT`, `WAITING_STORE_CONFIRM`, `FUNDED`.
States (final): `CANCELED_BY_CLIENT`, `CANCELED_BY_STORE`, `REJECTED`,
`NO_LIMIT`, `OVER_LIMIT`, `CLIENT_NOT_FOUND`, `FAIL`, `PUSH_TIMEOUT`,
`CONFIRM_TIME_EXPIRED`, `FAIL_OTP`, `REFUND_FINISHED`, `IDENTIFICATION_FAILED`.
`GET` additionally returns `IN_PROGRESS`.

`FUNDED` is documented as non-final: it can still move to `REFUND_FINISHED`.

### 4.4 Status pull (us → bank, hybrid fallback)

`GET /sf-credits/{id}` with `X-Flow-Id`. Returns `state` and, once issued, the
full `guarantee_letter` object including a Base64 PDF.

### 4.5 Shipment and refund

- Shipped: `PATCH /sf-credits/{id}` `{"method":"UPDATE","goods_shipped":true,"flow":{"type":"DIGITAL_SF"}}`
- Cannot ship: `PATCH /sf-credits/{id}` `{"method":"CLOSE","cancel_reason":"CancelLead50","flow":{"type":"DIGITAL_SF"}}`
- Refund: `POST /sf-credits` `{"id","agreement_number","refund":true,"amount","point_of_sale_code","partner_name","flow":{"type":"DIGITAL_SF"},"external_id"}`

`agreement_number` comes from `guarantee_letter.content.customer_agreement.number`,
so the guarantee letter must be captured and stored before any refund is possible.

`PATCH` returns `409` when the application is still `WAITING_CLIENT` or is not in
`WAITING_STORE_CONFIRM` — the shipment hook must treat `409` as "not yet
shippable", not as a hard failure.

### 4.6 Settlement register (separate, later)

`GET https://api.dts.fuib.com/ext-oic/dior/partners-operations`, requires our IBAN
(`account_no`, 2600*) and the retailer agreement number. Out of scope for the
first PAY-002 round; relevant later for NCRM payout reconciliation.

## 5. Scheme decision

Owner decision, 2026-07-27: **hybrid** — the bank pushes callbacks, and we keep
`GET /sf-credits/{id}` as a fallback poll. This mirrors the mono architecture
(callback + poll) and is the only option that survives a missed callback.

Callback endpoint authentication, owner decision 2026-07-27: **HTTP Basic over
TLS plus an IP allowlist** of the bank's callback source addresses. We generate
the Basic credentials and hand them to the bank; we must request their source IPs.

### 5.1 Reserved callback URL

Owner decision, 2026-07-27: **Variant A — one production host, two routes.**
No staging site exists for this project (confirmed by repo search); a second
subdomain/OpenCart copy was considered and rejected as disproportionate for one
integration. Both the bank's test and production callback traffic land on the
same live host, on two distinct routes with two distinct Basic-auth credential
pairs:

- production: `https://boostershop.website/index.php?route=extension/pumb_credit/payment/pumb_credit.callback`
- test: `https://boostershop.website/index.php?route=extension/pumb_credit/payment/pumb_credit.callbackTest`

Requirements for the extension skeleton (binding on Codex):

- The two routes must write to clearly separated state — either a `is_test`
  column on the transaction table or a separate test table — so a bank test
  callback can never update a real order.
- Each route has its own Basic-auth credential pair; the test credentials are
  the ones handed to the bank for their test contour.
- Once the bank supplies their callback source IPs (question 2, section 7), the
  IP allowlist applies to both routes independently — test source IPs will
  differ from production source IPs.

⚠️ Still unverified: the exact route string against the live OC4 route registry.
No cPanel backup was mounted in this session, so the deployed `mono_chast` route
form could not be read as a precedent. Codex must confirm the exact route string
against the newest backup before this URL is sent to the bank as final, and must
confirm that a `POST` with a JSON body to an `index.php?route=` URL is not
altered by the site's `.htaccess` / SEO-URL rewriting.

## 6. Impact on existing artifacts

| Artifact | Impact |
|---|---|
| `plans/PAY_decomposition_mono-pumb-preorder_20260721.md` §6.1–6.3 | Superseded by this file. §5, §6.5, §7, §8 (contract facts, owner flow decisions, QA, risks) remain valid |
| NCRM-14 (`credit_pumb_3/4/5`) | Payment-type codes unaffected. Commission percentages come from contract Додаток №2, not from this API. The `payment_method_code` TODO in `paymentTypeCode()` resolves to the `pumb_credit` extension code once the skeleton exists |
| PAY-001-UI modal | The PUMB card can now use official assets — the logo archive closes the "Лого ПУМБ ❌ не знайдено" gap from 2026-07-21 |
| LEGAL-002 offer | Product-sheet limits (500–300 000, 3–24 months) differ from the API validation range; verify which figure the public offer text quotes |
| Brand and copy rules | The product sheet fixes mandatory naming: product is «Сплачуйте частинами» (never «СЧ», «Оплата частинами», «ОЧ»); the bank short name is written only as ПУМБ in capitals. Any public UI still requires the contractual pre-approval of the layout (contract п.2.2.8) |
| Checkout success page | Owner confirmed 2026-07-27 it is shared between mono and PUMB — consistent with the PAY-001 architecture already on record ("Покупка в кредит" is one purchase type, mono and PUMB are parallel cards in one shared modal, not separate checkout payment methods). New task **PAY-003** (section 9) covers the shared intermediate "waiting for client confirmation" page and success-page polish |

## 7. Questions for the bank (Роман Назаренко)

1. Scheme confirmation: hybrid (callback + our `GET /sf-credits/{id}` fallback).
2. Callback source IP addresses, for our allowlist.
3. Callback SLA: response timeout and retry policy — not stated in the supplied
   protocol.
4. Is the proposed callback body format accepted as-is (the `Уточнення` document
   asks us to confirm)?
5. `credit_request.amount` is typed `Integer` in the field table, but the error
   table implies `<6 digits>.<2 digits>`. Which is authoritative for an order
   total with kopiykas?
6. Maximum amount: the active-passive protocol says 150 000, the two promo-scheme
   protocols say 100 000, and the product sheet says 300 000. Which applies to our
   `INTERNET` channel and our point of sale?
7. `promo_product_code` — the contract's Додаток №2 lists `NEW_3` / `NEW_4` /
   `NEW_5` with 3.00 / 4.50 / 5.80 %. Are those the values to send as
   `promo_product_code`, and what happens to the commission if the field is omitted?
8. Duplicate `store_order_id` behaviour — error, or return the existing `cap_id`?
9. Test-contour behaviour: are there test phone numbers or a test client, given
   that signing happens inside the real PUMB Online app?
10. Rate limits.
11. Confirmation that the shipment signal at the moment of handover to the postal
    operator (owner's chosen flow) is acceptable, and the actual TTL for
    `WAITING_STORE_CONFIRM`.

## 8. Open owner decisions

1. ~~Test callback URL host~~ ✅ Resolved 2026-07-27 — Variant A, section 5.1.
2. **PAY-001 closure.** The owner reported on 2026-07-27 that a real production
   order (placed by a third party, not sandbox) completed the full mono flow:
   it appeared correctly in both the monobank partner cabinet and OpenCart, and
   the owner cancelled it afterward by choice, not because of a failure. This is
   materially stronger evidence than the sandbox-only smoke recorded in the last
   committed dashboard state, and it implies monobank has already issued a
   working `point_id` (the previously stated production blocker). Whether this
   is sufficient to mark PAY-001 `Done`, or whether the full 15-step smoke should
   still be re-run first, is the owner's call — see chat.

## 8a. Order-status governance (owner decision 2026-07-27)

The live OC4 admin order-status list already carries the 6 mono-specific
statuses from the 2026-07-19 handoff (`ПЧ mono — очікує клієнта / очікує видачу
/ активна / завершена / повернена / відхилена`) plus the generic `Чернетка
(системний)`. The owner's first real production mono order landed in `Чернетка
(системний)`, not one of the six named states, and the owner confirmed that is
enough for now — but flagged that the admin dropdown is already too cluttered
with technical statuses and does not want it to grow further.

**Owner decision, 2026-07-27 (round 2):** option (b) — rename the existing mono
statuses to provider-agnostic labels and share them across both providers,
**and reduce the count where possible.** Distinguish mono vs PUMB internally via
`payment_type_code` in NCRM, not via the OC status label.

### Proposed status set — 6 → 5, grounded in the actual state tables

Source: mono's real API status table (`handoffs/handoff_PAY-001_monobank-chastyny-integration_20260718.md`
§7.D — `SUCCESS/ACTIVE`, `SUCCESS/DONE`, `SUCCESS/RETURNED`,
`IN_PROCESS/WAITING_FOR_CLIENT`, `IN_PROCESS/WAITING_FOR_STORE_CONFIRM`, and 10
distinct `FAIL/*` sub-reasons already collapsed into mono's single "відхилена"
today) and PUMB's protocol states (section 4.3 above).

| Proposed shared OC status | mono states it covers | PUMB states it covers |
|---|---|---|
| Розстрочка — очікує клієнта | `IN_PROCESS/WAITING_FOR_CLIENT` | `WAITING_CLIENT` |
| Розстрочка — очікує видачі | `IN_PROCESS/WAITING_FOR_STORE_CONFIRM` | `WAITING_STORE_CONFIRM` |
| Розстрочка — оформлено | `SUCCESS/ACTIVE`, `SUCCESS/DONE` | `FUNDED` |
| Розстрочка — повернено | `SUCCESS/RETURNED` | `REFUND_FINISHED` |
| Розстрочка — відхилено | all `FAIL/*` sub-reasons (already collapsed today) | `CANCELED_BY_CLIENT`, `CANCELED_BY_STORE`, `REJECTED`, `NO_LIMIT`, `OVER_LIMIT`, `CLIENT_NOT_FOUND`, `FAIL`, `PUSH_TIMEOUT`, `CONFIRM_TIME_EXPIRED`, `FAIL_OTP`, `IDENTIFICATION_FAILED` |

This drops the admin dropdown from 7 relevant entries (6 mono + `Чернетка`) to 6
(5 shared + `Чернетка`), instead of growing to 13 if PUMB got its own six.

**Owner sign-off received 2026-07-27:** the merge below is approved as stated.

**One merge needed explicit owner sign-off, not just a rename:** collapsing
mono's `SUCCESS/ACTIVE` ("товар видано, ПЧ активна") and `SUCCESS/DONE` ("ПЧ
повністю сплачена") into a single "Оформлено" status loses the distinction
between "installment still being paid off" and "installment fully repaid" at
the OC order-status level. This is a real trade-off, not just a label change:
PUMB's documented protocol never tells us when a customer finishes repaying
anyway (no equivalent to `SUCCESS/DONE` exists in the PUMB callback states), so
for PUMB there was never a "завершена" to preserve. For mono, if this
distinction matters operationally, it can still be recovered from the
transaction table's raw state column or from monobank's `store/report`
reconciliation endpoint — it just would not show in the OC admin dropdown.
Proceeding with the merge as approved.

**Sequencing:** this rename touches a live, actively-used order-status table and
the mono extension's status-mapping code, so it ships together with the PAY-002
Codex handoff (same round, one coordinated patch with rollback), not as a
separate immediate change. It is verified as part of `PAY-001-SMOKE` (Stage 3.1)
before either provider goes live to real customers.

## 9. Related — PAY-003 (new task)

Owner decision 2026-07-27: a shared intermediate page is needed between order
submission and checkout-success, shown while the customer has not yet confirmed
the credit application in their banking app (mono push confirmation, or PUMB
Online app confirmation). Full scope, sequencing, and rationale are recorded in
`plans/PAY_decomposition_mono-pumb-preorder_20260721.md` §10 (PAY-003) — kept
there because it spans both providers, not just PUMB. Notion: `PAY-003`.

PAY-001 closed 2026-07-27 on real production evidence (owner decision). The
deferred full regression, plus the order-status consolidation from §8a, is now
covered once for both providers by `plans/PAY-001-SMOKE_unified-credit-qa_20260727.md`
(Notion: `PAY-001-SMOKE`) — the gate before either provider goes live to real
customers unattended.

## 10. Risks

- **Callback has no cryptographic authenticity.** Anyone who learns the URL and
  Basic credentials can move an order's payment state. Mitigations: IP allowlist,
  never log the credentials, and treat the callback as a trigger to re-read state
  via `GET /sf-credits/{id}` rather than as a trusted state source.
- **PII in the guarantee letter.** `guarantee_letter.content.customer` carries tax
  ID and identity-document data, and `file` is a Base64 PDF. Store the minimum
  needed (`agreement_number`), never write the payload to a log, and decide
  retention before implementation.
- **Token TTL 300 s.** A naive implementation that fetches a token per request
  will hit the auth service hard and fail unpredictably under load.
- **`invoices[].date` must equal the current date.** Any retry that crosses
  midnight will fail validation. The retry path must rebuild the payload, not
  replay it.
- **`sum(total_amount) == credit_request.amount`.** Coupons, discounts, and
  shipping must be reconciled into the goods array. This is the same class of bug
  as the mono `discount_total` defect in NCRM-14.
- **Checkout is a risky zone.** Every PAY-002 patch requires a rollback path, a
  status flag defaulting to off, and `bs-checkout-smoke` before any enablement.
- **Public UI carrying the PUMB brand requires written bank approval of the
  layout before publication** (contract п.2.2.8).
