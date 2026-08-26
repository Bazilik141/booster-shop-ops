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
- `channel_type` — `"INTERNET"`
- `store_user_login` — ⚠️ **corrected 2026-08-25.** The protocol field table says
  this is required only for `"POS"`, and the first implementation omitted it for
  `INTERNET`. Oleksiy Berlizov (PUMB), in the integration chat on 2026-08-25:
  *"store_user_login передавайте сюди будь який айді юзера хто створив заявки, ну
  або айді системи хто створив заявку, там не має ввалідації"* — send it anyway;
  any user id or system id; no server-side validation. Treat the bank's chat
  statement as authoritative over the field table here. **Not** the cause of the
  `403 "The partner login does not match the chain of store"` — tested 2026-08-25
  08:47:44 UTC with `store_user_login = boostershop-oc` present in the payload
  (`X-Flow-Id f6ee1b606b59906e17f78a369963e384`); the response was byte-identical
  to the runs without the field. Keep sending it because the bank asked for it,
  but it changes nothing about the 403
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

## 7a. Bank answers received (2026-07-28)

Partial answer from the bank's integration contact; several items explicitly
deferred to named colleagues.

| # | Status | Answer / next step |
|---|---|---|
| 2 (our IPs) | Resolved implicitly | Not asked back — bank did not request our source IPs |
| 2 (their IPs, our Q2) | **Escalated, not answered** | Not documented on their side either. Contact **Oleksiy Berlizov** directly — Telegram `@berlizovoleksii` / `Oleksiy.Berlizov@fuib.com` |
| 3 (our Q3, SLA/retry) | **Open, confirmed undocumented** | No retry/timeout policy exists in the bank's own docs |
| 5 (our Q5, `amount` type) | **Open, confirmed ambiguous** | Bank confirms the contradiction we found; no authoritative answer available |
| 6 (our Q6, max amount) | **Resolved — new figure** | Bank states **500 UAH min / 500 000 UAH max**. This is a fourth, bank-stated figure, higher than all three conflicting document figures (100k/150k/300k). Treat as the current best answer; still worth getting in writing before hardcoding, but safe to use as the admin-panel default now |
| 7 (our Q7, `promo_product_code`) | **Resolved — not applicable to us** | Used only for chain-partner/vendor promo campaigns (e.g. flagship-phone deals) with their own analytics tracking — not relevant to Booster Shop. Confirms the plan's existing approach: omit the field entirely, no code change needed |
| 8 (our Q8, duplicate `store_order_id`) | **Open, confirmed undocumented** | No documented behavior; must be discovered empirically in the test contour |
| 9 (our Q9, test contour) | **Resolved, with new detail** | Test OAuth2 endpoint exists (same `auth.dts.fuib.com` host, test credentials). Additionally: `GET /galadriel/v1/sf-credits/{id}` has **static fixture IDs 1–13** that return fixed states (`WAITING_CLIENT`, `FUNDED`, `REJECTED`, etc.) without needing a full create→confirm cycle — useful for `PAY-001-SMOKE` Stage 2 polling tests. Test phone numbers are not documented; will come from colleagues `@Andrii_sitt` and Vasyl during actual test-contour work in the bank's Telegram group |
| 10 (our Q10, rate limits) | **Open, confirmed undocumented** | No rate-limit policy documented |
| 11 (our Q11, shipment timing) | **Resolved 2026-07-28** — see §7c | Owner forwarded a 2026-07-21 exchange (Роман Назаренко ↔ Євгеній Леусенко) directly on this point; our chosen flow (signal at carrier handover) is accepted |
| 11 (our Q11, TTL) | **Escalated, not answered** | "Question for colleagues" — no ETA given |
| Credentials timing (our Q10 in the original letter) | **Resolved** | Test/prod OAuth2 credentials and any available allowlist info are emailed to the address we provided, after the bank completes its own setup — **3–7 days after the bank receives our callback URL and other required integration info**. The clock starts on their receipt of that info, not on this reply |

### What this changes right now

- **Max amount**: set the admin panel's `payment_pumb_credit_max_total` to
  `500000` once the patch is deployed (config-only, no code change — the field
  was deliberately left empty/configurable for exactly this reason).
- **`promo_product_code`**: confirmed out of scope — no action needed, the
  skeleton already omits it.
- **IP allowlist**: genuinely uncertain whether the bank can supply static
  callback source IPs at all — their own integration contact didn't have this
  and redirected to a named colleague. The skeleton already degrades
  gracefully if no IPs are ever configured (`allowedIp()` returns true when
  the allowlist setting is empty), so this does not block anything — but it
  does mean Basic auth over TLS may end up being the *only* protection on the
  callback route, not defense-in-depth with an IP check. Worth remembering
  when deciding whether to harden the callback handler later (e.g. always
  re-verify state via `GET /sf-credits/{id}` before trusting a callback body,
  rather than applying its stated state directly).
- **Still open, no code impact yet**: SLA/retry, `amount` type
  (Integer vs Decimal(2)), duplicate `store_order_id` behavior, rate limits,
  TTL for `WAITING_STORE_CONFIRM`. None of these block building or deploying
  the disabled skeleton; all of them matter before enabling live traffic and
  should be answered (or empirically observed in the test contour) before
  `PAY-001-SMOKE`.

## 7b. Bank answers, round 2 (2026-07-28)

Second reply from the same bank contact, resolving five of the remaining
open items. Numbering below maps by content to the original §7 list, not to
the bank's own reply numbering (the bank did not reuse our numbering).

| Original Q | Status | Answer |
|---|---|---|
| Q3 (SLA/retry) | ~~Resolved~~ **PARTLY OVERTURNED 2026-08-25 — the bank does retry** | Live production access log, 2026-08-25, source `194.44.66.21`, agent `Java/17.0.20`: **3 attempts at ~10-second intervals** per state change (12:23:22 / :32 / :42 for `WAITING_CLIENT`; 12:29:58 / 12:30:08 / :18 for `WAITING_STORE_CONFIRM`; 12:37:24 / :35 after `goods_shipped`). All returned `401` from our side. So a retry policy does exist — roughly 3× / 10s — even though the bank's own written answer denied it. The poll fallback remains necessary (three attempts over 20 seconds will not survive an outage), but "no retries at all" is factually wrong. **Also discovered in the same log: the bank delivers *test-contour* callbacks to the *production* route** `...pumb_credit.callback`, never to `...pumb_credit.callbackTest`. See the callback finding below. Original answer follows. — The bank's system performs **no automatic retries** on a failed/unreachable callback delivery. The bank explicitly says the hybrid scheme (callback + our `GET /sf-credits/{id}` poll fallback) is the right fit for exactly this gap — a missed callback is recovered by our own polling, not by a bank-side retry. Validates the architecture decision made 2026-07-27 |
| Q5 (`amount` type) | ~~Resolved~~ **OVERTURNED 2026-08-25 — hryvnia, not kopiykas** | The 2026-07-28 written answer said Integer kopiykas (1000.00 UAH → `100000`). **Live evidence contradicts it.** First successful create on the test contour, 2026-08-25 09:23:05 UTC (`X-Flow-Id b3baeffe02e57ef056e158fc1e2467e3`), sent `"amount":700.0` / `"total_amount":700.0` as hryvnia decimals and was accepted: `HTTP 201 {"id":19039867}`. Had the bank parsed `700.0` as kopiykas it would be 7 UAH, below the documented 500 UAH minimum, and the create would have been rejected. The deployed module already sends hryvnia — **no code change is needed**. ⚠️ Still worth one visual confirmation that the client-facing application shows 700 ₴ and not another figure, during the first pushed test scenario |
| Q7 (`promo_product_code`) | **Corrected — see note below** | Confirmed: `NEW_3` / `NEW_4` / `NEW_5` (contract Додаток №2) are valid `promo_product_code` values. The field is **optional** — if omitted, the bank auto-selects the standard product by `term` and charges the **base contract commission**, which is the same 3.00/4.50/5.80% schedule. Net effect: omitting the field is safe and gives the same commission outcome as sending it explicitly, so the skeleton's current omission is fine for MVP; no code change required |
| Q8 (duplicate `store_order_id`) | **Resolved — new risk identified** | The bank has **no unique constraint** on `store_order_id`. A repeated `POST /sf-credits` with the same `store_order_id` creates a **new, separate application** with a new `cap_id` — it is not treated as a duplicate or an error. See finding below: our own `confirm()` does not currently guard against this |
| Q10 (rate limits) | **Resolved** | No hard rate limit. Bank asks partners to keep fallback `GET` polling to **no more than once per 30 seconds per pending application** |
| Q11 (TTL) | **Conflicting — see §7c** | Round-2 answer states `WAITING_STORE_CONFIRM` times out at **24 hours** → `CONFIRM_TIME_EXPIRED`. A separate, online-store-specific 2026-07-21 exchange (§7c) states the bank sets a **7-day** application lifetime for online stores specifically to allow for postal transit. Same underlying window, two different numbers — not resolved, needs one direct written clarification. State-mapping code is unaffected either way: `CONFIRM_TIME_EXPIRED` is already handled (see below) |

### New finding — no app-side guard against double `POST /sf-credits`

`confirm()` (patch lines 149–163) calls `POST /sf-credits` unconditionally
whenever it runs — there is no check for an existing, still-open transaction
for the same `order_id` before creating a new application. Combined with the
bank's Q8 answer (no dedup on their side, a repeat POST just creates a second
`cap_id`), a double-click on the confirm button, a page refresh after
confirm, or a browser back-button resubmit will now provably create two
separate live credit applications for one order — not just a theoretical
risk, a confirmed bank behavior.

This does not block running the disabled skeleton (`payment_pumb_credit_status`
stays `0`, no customer traffic reaches `confirm()` yet), but it must be fixed
before enabling the method or running `PAY-001-SMOKE`. Recommended fix for a
Codex follow-up: in `confirm()`, before calling the API, look up
`transactionByOrder($orderId, $isTest)`; if a row already exists and its
last known `state` is not a failed/expired/cancelled terminal state, return
the existing `cap_id`/state instead of creating a new application.

## 7c. Q11 shipment-signal timing — resolved, with a new TTL conflict

Owner forwarded a 2026-07-21 chat exchange between Роман Назаренко (PUMB)
and Євгеній Леусенко (Booster Shop side) that predates the numbered §7
question list and answers the shipment-timing half of Q11 directly, in the
context of an online store shipping via postal operators (matches our setup:
FOP agreements with Нова Пошта / Укрпошта, no physical point of sale).

**Resolved:** our chosen flow — sending the "відвантаження товару" signal
(`PATCH .../method=UPDATE, goods_shipped:true`) at the moment goods are
handed to the postal carrier, not at the moment the client completes the
installment application in the PUMB app — is explicitly acceptable. Роман
confirms this is how many sellers operate. Sequence confirmed: the shipment
signal moves the application to a funded state, and **funds land in the
merchant account 5–10 minutes later**. (Роман's own chat spells the funded
state "FOUNDED" — same spelling as the original 21.07 plan draft that this
document's §3 divergence table treats as a typo for "FUNDED". A human
casually typing "FOUNDED" in Telegram is weak evidence either way — not
enough to overturn the existing FUNDED conclusion, which came from the
actual protocol docs, but worth a live-response check before hardcoding
either spelling anywhere.)

**New conflict — TTL / application lifetime:** this same exchange states the
bank sets application lifetime for online stores at **7 days**, specifically
to give postal delivery time to complete. This describes the same window as
`WAITING_STORE_CONFIRM`, but does not match the round-2 answer (§7b, received
the same day this document was last touched) stating **24 hours** for that
same state before `CONFIRM_TIME_EXPIRED`. Two different numbers for what
looks like the same window, from the same bank contact, days apart. Do not
rely on either number for time-sensitive logic (e.g., a "ship within X or
risk expiry" warning) until the bank confirms in writing which applies to
our contract, and whether postal transit time counts toward it or only the
handover-to-carrier step does.

**Owner operating decision (2026-07-29):** regardless of which bank-side
figure is eventually confirmed (24 hours vs 7 days), the owner will target
handing goods to the carrier and sending the `goods_shipped` signal **within
24 hours** of application creation. This is an internal operating SLA, not a
resolution of the bank-side conflict — it satisfies either scenario, so it is
not schedule-blocking, but the written clarification from the bank is still
open and should be folded into the next consolidated follow-up (see owner
status below).

**Not adopted, recorded for awareness only:** Роман also described an
alternative some sellers use — sending the shipment signal immediately at
application completion (before physically shipping) to get funded same-day,
accepting the risk of a return/refund flow if the client never collects the
parcel. Booster Shop's already-decided flow (signal only at actual carrier
handover, per the owner's 2026-07-27 decision and the "preserve trust in
original sealed products" / accuracy principle) is unchanged by this —
recorded here only so the alternative isn't reconsidered without a reason.

## 7d. Bank answers, round 4 (2026-07-30)

Reply from Roman Nazarenko and colleague Andrii Sarnavskyi to the
2026-07-29 consolidated follow-up.

| Item | Status | Answer |
|---|---|---|
| §7c TTL conflict | **Resolved — 7 days wins** | Roman: "по статусу відвантаження... ставимо 7 днів строк життя заявки." Overturns the 2026-07-28 round-2 answer of 24 hours. This is a bank-side setting ("ці налаштування на нашій стороні") — no config on our end. Our own 24h shipping *target* (owner decision, 2026-07-29) remains a fine operating habit but is no longer a hard deadline |
| Q4 (callback format) | **Resolved** | Andrii: format confirmed correct. `guarantee_letter` is absent on the *first* callback and only appears after the client signs — already handled correctly by the deployed code (`$letter = $data['guarantee_letter'] ?? null;`), no change needed |
| Callback source IPs | **Resolved** | Andrii: `194.44.66.16/28` — same proxy range for both test and prod. This is a CIDR block (16 addresses, `.16`–`.31`), but the deployed `allowedIp()` does exact-string matching against a CSV list, not CIDR matching. Practical fix needs no code change: enter all 16 addresses individually into both "Test callback IP allowlist" and "Production callback IP allowlist" fields: `194.44.66.16,194.44.66.17,194.44.66.18,194.44.66.19,194.44.66.20,194.44.66.21,194.44.66.22,194.44.66.23,194.44.66.24,194.44.66.25,194.44.66.26,194.44.66.27,194.44.66.28,194.44.66.29,194.44.66.30,194.44.66.31`. CIDR-range support in `allowedIp()` is a nice-to-have for later (bank could expand the range), not urgent |
| Test phone numbers | **Pending** | Andrii: "будемо надамо" — promised, not yet received |
| OAuth2 credentials | **Pending** | Andrii: sent by email, two separate messages for test and prod. Not yet received |
| Recovery of a "fallen" application | **New info, operational note** | Roman: the bank can restore ("відновлюємо") a failed application on their side on request — relevant to our `CREATE_FAILED` / manual-review design; not a code change, just useful to know when handling a stuck order later |

### New finding — likely wrong state-name spelling in deployed code (`FUNDED` vs `FOUNDED`)

Roman, unprompted: **"у вас фінальний статус має бути FOUNDED. Це значить що заявка профінансована."** This is the second independent human source using this spelling — the original 21.07 archive document also used "FOUNDED" (previously treated as a typo, see §3 divergence table, on the assumption that "FUNDED" from other protocol docs was authoritative). Roman stating it directly and explicitly, unprompted, raises real doubt about that earlier call.

The deployed controller checks the state string in exactly one place
(`patches/PAY-002_pumb-credit-skeleton_20260728.php` line 261,
`applyOrderStatus()`): `$state === 'FUNDED' ? 'funded' : ...`. If the bank's
actual API response uses `"FOUNDED"`, this comparison **never matches**, the
order status silently never updates to "Розстрочка — оформлено" for a
genuinely funded order, and `$key` falls through to `''` — no crash, no log,
just a silently stuck order. This is a live-money-adjacent state (funds
already transferred) that would be easy to miss until a real order got stuck.

Not urgent today (method still disabled, no live traffic), but must be
resolved before enabling. Two ways to close it, not mutually exclusive:

1. ✅ **Done 2026-07-30** — defensive fix deployed:
   `patches/PAY-002_founded-state-defensive-fix_20260730.php`, reviewed OK
   (`diagnostics/PAY-002_founded-state-defensive-fix_review_20260730.md`).
   Live `grep` confirmed this was the only `FUNDED`/`FOUNDED` comparison in
   the extension; now accepts both spellings, mapped to the same `funded`
   status key. No downside either way this resolves.
2. ✅ **Resolved 2026-08-25 — `FUNDED`, primary evidence.** First successful
   authenticated read against the bank's test contour, via
   `patches/PAY-002_bank-test-drive_diagnostic_20260824.php` run by the owner
   on production. Verbatim API responses:

   ```
   fixture_1_response_body={"id":1,"state":"IN_PROGRESS"}
   fixture_13_response_body={"id":13,"state":"FUNDED"}
   ```

   The API returns **`FUNDED`**, uppercase. The two human sources that spelled
   it `FOUNDED` (the 21.07 archive document and Roman Nazarenko in chat,
   §7d) were wrong. The deployed controller's `$state === 'FUNDED'` comparison
   is correct as written, and the defensive dual-spelling patch
   (`patches/PAY-002_founded-state-defensive-fix_20260730.php`) — which §7d
   item 1 recorded as deployed on 2026-07-30 but which was **never actually
   applied to production** (verified 2026-08-19 against both
   `pumb-live_2026-08-14.tar.gz` and the 2026-08-16 backup: no
   `.pay002-founded-state-marker`, no `FOUNDED` in the controller) — is no
   longer needed. Leave it unapplied.

   Also confirmed in the same run: OAuth 2 password grant against
   `auth.dts.fuib.com` returns `200` with `expires_in=300`, and
   `GET /sf-credits/{id}` requires the `X-Flow-Id` header — without it the
   bank answers `400`.

### Owner status (2026-07-29)

- The formal callback-URL letter to Roman Nazarenko has been sent (owner
  confirmed 2026-07-28; exact send date not recorded) — the bank's stated
  3–7 day credential-delivery window is running against that send date, not
  this document's date.
- Owner decision (2026-07-28, reaffirmed 2026-07-29): **defer** the Oleksiy
  Berlizov IP follow-up and bundle it with the still-open items (Q4 explicit
  callback-format confirmation, the §7c TTL conflict, written confirmation of
  the ~500 000 UAH max amount) into **one** consolidated follow-up rather than
  several partial messages.
- **Do not send that follow-up yet.** Sequence before contacting the bank
  again:
  1. ~~Codex applies the idempotency-guard handoff~~ — first attempt returned
     for changes 2026-07-29; **round 2 fixed both findings, independently
     re-verified, Review OK** (`diagnostics/PAY-002_confirm-idempotency-guard_review_20260729.md`
     §7). **Cleared to deploy**, still not yet uploaded.
  2. ✅ done 2026-07-29 — both patches deployed; all owner QA not gated on
     bank credentials is complete (callback route tested end to end, correct
     `cap_id` persisted, `payment_pumb_credit_status` confirmed `0`). See
     `diagnostics/PAY-002_confirm-idempotency-guard_review_20260729.md` §7 and
     `diagnostics/PAY-002_pumb-credit-skeleton_review_20260728.md` §5.
  3. ✅ done 2026-07-29 — consolidated follow-up sent to Roman Nazarenko.
     Covered: IP addresses for the callback allowlist, Q4 (callback body
     format `{cap_id, state, guarantee_letter}` — final as-is?), §7c TTL
     clarification (24h vs 7-day conflict, explicitly noting our shipment
     signal fires at Nova Poshta handover). Max amount (500 000 UAH) was
     **not** sent as a question — owner recorded it as our own fixed
     internal value (already set in the admin panel's max-amount field);
     still worth a written bank confirmation eventually, but not blocking.
  4. Credentials arriving from the bank is not itself a trigger to write
     back — it's a trigger to start test-contour QA (`PAY-001-SMOKE` Stage 2
     prerequisites).

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
