# PAY-002 — first successful end-to-end test on the PUMB test contour

Date: 2026-08-25
Owner-run, on production (`uashared43`), against the bank's **test** contour
(`auth.dts.fuib.com` / `api.dts.fuib.com`).
Instrument: `patches/PAY-002_bank-test-drive_diagnostic_20260824.php` (rounds 1–6).
OpenCart order used: **#332**, created by the owner as a disposable test order.
`cap_id`: **19039867**. Term requested: **4**. Amount: **700.00 UAH**.

Server clock is UTC; Kyiv time is UTC+3. Times below are as printed by the tool.

## Outcome

**The full lifecycle completed.** `create` → client confirmation → shipment
confirmation → `FUNDED`.

| Step | Time (UTC) | Result |
|---|---|---|
| OAuth token | 09:23:05 | `200`, `expires_in=300` |
| `GET /sf-credits/1` (fixture) | 09:23:05 | `200`, `{"id":1,"state":"IN_PROGRESS"}` |
| `GET /sf-credits/13` (fixture) | 09:23:05 | `200`, `{"id":13,"state":"FUNDED"}` |
| `POST /sf-credits` | 09:23:05 | **`201`**, `{"id":19039867}`, `X-Flow-Id b3baeffe02e57ef056e158fc1e2467e3` |
| `GET` status | 09:28:25 | `WAITING_CLIENT` |
| Bank pushed client confirmation | ~09:29 | — |
| `GET` status | 09:33:53 | `WAITING_STORE_CONFIRM`, guarantee letter present |
| Admin «Підтвердити видачу» (`PATCH goods_shipped`) | 09:37:11 | `{"http":200,"response":[]}` |
| `GET` status | 09:40:37 | **`FUNDED`** |

## What this settles — primary evidence, supersedes prior written answers

1. **Amount unit is hryvnia, not kopiykas.** We sent `"amount": 700.0` /
   `"total_amount": 700.0` and the bank accepted it. Guarantee letter shows
   `"net_amount": 700.0` and goods `"amount": 700.0`. Serhiy Kondratenko (PUMB)
   confirmed in chat 12:31 Kyiv: *"підтвердив, 700 грн"*. Had `700.0` been read
   as kopiykas it would be 7 UAH, below the 500 UAH minimum, and the create would
   have failed. **The deployed module already sends hryvnia — no change needed.**
   Overturns the 2026-07-28 written answer (§7b Q5).
2. **State string is `FUNDED`, uppercase.** Returned verbatim by the API in both
   the fixture read and the live application. The deployed `$state === 'FUNDED'`
   comparison is correct. The `FOUNDED` spelling used by two human sources was
   wrong; the never-deployed defensive patch stays unapplied.
3. **The customer-selected term reaches the bank correctly when supplied.**
   Guarantee letter: `"product": {"name":"Сплачуйте частинами NEW_4","term":4}`.
   We requested 4 and got a 4-payment product. Confirms the payload shape
   `PAY-004` builds on.
4. **`GET /sf-credits/{id}` requires `X-Flow-Id`.** Without it the bank answers
   `400`. Discovered when the round-2 script omitted the header; adding it
   produced `200` immediately. The live module's `api()` already sends it.
5. **The bank does retry callbacks** — see below. Overturns §7b Q3.
6. **`PATCH` shipment confirmation works from the existing admin panel** and
   moves the application to `FUNDED` within minutes.

## The one thing that does not work: callbacks

**The bank delivers test-contour callbacks to the production route.** Production
access log, 2026-08-25, source `194.44.66.21` (inside the allowlisted
`194.44.66.16/28`), agent `Java/17.0.20`:

```
12:23:22 POST .../pumb_credit.callback  401
12:23:32 POST .../pumb_credit.callback  401
12:23:42 POST .../pumb_credit.callback  401
12:29:58 POST .../pumb_credit.callback  401
12:30:08 POST .../pumb_credit.callback  401
12:30:18 POST .../pumb_credit.callback  401
12:37:24 POST .../pumb_credit.callback  401
12:37:35 POST .../pumb_credit.callback  401
```

Never once to `...pumb_credit.callbackTest`.

`401` is **correct behaviour on our side**: the production callback Basic user
and password are deliberately empty, and `validBasicAuth()` returns false when
either is empty (live controller `:138`). The two-route design exists precisely
so bank test traffic cannot write into production order state.

**Retry policy, observed:** 3 attempts at ~10-second intervals per state change
(the last group shows 2, likely truncated by the log tail). Not "no retries".

**Consequence for this test:** the transaction row
(`pumb_credit_transaction_id=2`) still has `state = ''` and
`agreement_number = NULL`, and `date_modified` never moved off creation time.
Everything we know about the application came from polling, not from callbacks.

Asked of the bank 2026-08-25: route test callbacks to `...callbackTest` with the
credentials already supplied, or tell us if only one callback URL per partner is
possible. **Awaiting reply.**

## Not yet tested

- **Refund.** Blocked by the callback gap: the admin refund action requires
  `agreement_number` on the transaction row, which arrives only with the
  guarantee letter via callback. The number **does** exist on the bank side —
  `customer_agreement.number = 5001903986701`, visible in the `GET` response —
  but our row is empty. Either fix the callback first, or populate the row from a
  poll before attempting a refund.
- **Cancel** (`PATCH method=CLOSE`).
- The whole customer-facing path: there is still no PUMB card in checkout, so
  `confirm()` has never run in this test. Everything above went through the
  diagnostic script and the admin panel.

## Second run, same day — term support and the callback root cause

### Term 5 is rejected; 3 and 4 are accepted

`POST /sf-credits` with `"term": 5`, 09:54:56 UTC
(`X-Flow-Id da2860e3a832f0cce85a5fbc485d04fc`):

```
HTTP 400 {"type":"https://zalando.github.io/problem/constraint-violation",
"title":"Constraint Violation","status":400,
"violations":[{"field":"term","message":"Term 5 is not supported"}]}
```

Same order, same payload, `"term": 3`, 09:56:09 UTC → **`201`**, `cap_id 19039895`.
Guarantee letter confirms `"product": {"name":"Сплачуйте частинами NEW_3","term":3}`,
`customer_agreement.number = 5001903989501`.

So on the **test** contour: **3 ✅ · 4 ✅ · 5 ❌**.

⚠ **Do not shorten the allowed-terms list on this evidence alone.** Bank, in the
integration chat 2026-08-25: *"перелік доступних термінів на проді та стейджі
часто відрізняється"* and *"там різні схеми можуть, на проді зазвичай одна
класична, на стейдж деякі можуть бути не включені"*. Term 5 may well work in
production. A written per-POS list for `1700001669IN020147`, for both contours,
has been requested and is **open**.

Impact on `PAY-004`: the deployed `payment_pumb_credit_terms = [3,4,5]` is not
yet proven correct for PUMB. The list must be reconciled with the bank's answer
before the method is exposed to customers, or a customer choosing 5 will get a
`400` after their order is placed. This does not change the PAY-004 code — only
the configured value.

### Callback: root cause is a credential mismatch, not header stripping

Sequence, all on 2026-08-25:

1. 12:23–12:37 — bank posts to the **production** route `...pumb_credit.callback`,
   `401` (production Basic credentials deliberately empty). Reported to the bank.
2. 12:51 — bank updates the URL. 12:56 — bank posts to `...pumb_credit.callbackTest`.
   **Still `401`**, although the test Basic credentials are populated.
3. Working hypothesis at that point: Apache/LiteSpeed strips the `Authorization`
   header before PHP. `.htaccess` contains no `CGIPassAuth` and no
   `HTTP_AUTHORIZATION` rewrite, which supported it.
4. `patches/PAY-002_pumb-callback-basic-auth-passthrough_20260825.php` was built
   to prove it before changing anything. Owner ran it 12:14:20 UTC. **Phase 1
   result: `phase1_authorization_header_reached_php=yes`.**

**The hypothesis was wrong.** The header reaches PHP unmodified. The only
remaining explanation for the `401` is that the Basic credentials held by the
bank differ from those configured in the admin panel — consistent with the
password having been rotated on 2026-07-29 after it was exposed in a terminal.

Resolution taken: rather than determine whose value is stale, a fresh password
was generated, set in **Test callback Basic password** (user unchanged,
`pumb_test_cb`), and sent to the bank with the test callback URL. Production
callback credentials remain deliberately empty. Awaiting the bank to update and
re-push `19039895`.

### Runner defect — recorded because the review missed it

The passthrough runner's `finishSuccess()` has no `exit`, and the Phase 1
early-return branch (`:117-122`) does not return either. On the "change not
needed" path it therefore printed `htaccess_change=not_needed`, `done=ok`,
self-deleted — **and then fell through and applied the Phase 2 `.htaccess`
change anyway**, ending with `error=self_delete_failed` (the second unlink of an
already-deleted file).

No damage: both smokes returned `200`, the Phase 2 probe passed, and the inserted
block is a no-op given the header already arrives. The owner restored `.htaccess`
from
`_patch_backups/PAY-002_pumb-callback-basic-auth-passthrough_20260825-20260825-121420-1fc2eb/.htaccess.before`,
so the live file is back to its pre-run state and carries no PAY-002 marker.

The reviewer's claim that "phase gating is strict"
(`diagnostics/PAY-002_pumb-callback-basic-auth-passthrough_review_20260825.md`)
was wrong — the gate is correct, the *return* after it is missing. Any future
re-use of that runner needs the `exit` added first.

## 2026-08-26 — full lifecycle completed, callbacks included

New test application `cap_id 19040054`, order #332, term 4, 700.00 UAH.
Every state transition below was delivered to us **by callback**, not by polling.
All callback deliveries: `194.44.66.21`, user `pumb_test_cb`, HTTP **200**.

| Time (Kyiv) | Event | Evidence |
|---|---|---|
| 11:35:50 | `POST /sf-credits` → `201 {"id":19040054}` | `X-Flow-Id 4bb80575b7c6d8e1f9c8ba49f37e019c` |
| 11:36:10 | Callback: application created | access log `200`; row state populated |
| 11:39:37 | Callback: client signed → `WAITING_STORE_CONFIRM` | **`agreement_number = 5001904005401` written automatically** from the guarantee letter |
| ~11:41 | Admin «Підтвердити видачу» → `PATCH goods_shipped` | `{"http":200,"response":[]}` |
| — | `GET /sf-credits/19040054` | `FUNDED` |
| ~11:42 | Admin «Повернення», 700 | `{"http":201,"response":{"id":19040054}}` |
| 11:42:10 | Callback: refund complete | row `state = REFUND_FINISHED`, `GET` agrees |

**This closes the bank-side integration.** Create, client confirmation, shipment
confirmation, funding, refund, and inbound callbacks at every step are all
proven on the test contour, through the deployed extension and the existing
admin panel.

### What the callback fix actually was

The `401` was never a header problem — Phase 1 of the passthrough runner proved
the `Authorization` header reaches PHP untouched, and the `.htaccess` change was
rolled back. The cause was a stale Basic password on the bank's side. A fresh
password was generated, set in **Test callback Basic password** (user unchanged,
`pumb_test_cb`), and given to the bank, who updated their stage config on
2026-08-26. First successful callback: 11:32:10.

### `FAIL` on 19039895 — open question, not a blocker

The previous application went to `FAIL` after its shipment confirmation, ~22
hours after creation, even though the `PATCH` returned `200` and no guarantee
letter was ever issued for it. `19040054` completed the identical sequence
without incident inside seven minutes. Working theory: a stage-side timeout or
instability on an application left overnight. Asked of the bank; **open**.

Practical consequence for go-live: nothing yet, but if the real
`WAITING_STORE_CONFIRM` window turns out to be short, the owner's own
"ship within 24 hours" habit (owner decision 2026-07-29) matters more than the
§7d "7 days" answer suggests. Do not build a "ship within X" warning until the
bank confirms the number in writing.

### Remaining test-contour housekeeping

Three diagnostic rows exist on `order_id = 332`, all `is_test = 1`:
`19039867` (`FUNDED`), `19039895` (`FAIL`), `19040054` (`REFUND_FINISHED`).
They are harmless, but `transactionByOrder()` matches on `order_id`, so order
#332 must not be reused for a genuine PUMB purchase until they are removed:

```sql
DELETE FROM `ocp5_pumb_credit_transaction` WHERE `order_id`=332 AND `is_test`=1;
```

`patches/PAY-002_bank-test-drive_diagnostic_20260824.php` is still on the server
and can create real applications with `--live`. Delete it from `~/public_html`.

## Data handling note

The guarantee letter returned by `GET` contains a test persona's tax ID and
identity-document data plus a Base64 PDF. It is synthetic bank test data, not a
real customer, but it is **not** reproduced in this repository — only the
non-personal fields are recorded above. `plans/PAY-002_pumb-protocol-revision_20260727.md`
§10 governs retention when this goes live.

## Immediate housekeeping

`patches/PAY-002_bank-test-drive_diagnostic_20260824.php` does **not** self-delete
and can create real applications with `--live`. **Delete it from `~/public_html`.**
It remains in the repository for the next round.

The test transaction row can be removed with the statement the script printed:

```sql
DELETE FROM `ocp5_pumb_credit_transaction` WHERE `cap_id`='19039867' AND `is_test`=1;
```

Keep it for now if the callback fix is coming — it is the row a redelivered
callback would update.
