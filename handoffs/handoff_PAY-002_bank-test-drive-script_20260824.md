# PAY-002 — one-off CLI test-drive script for the PUMB test contour

Date: 2026-08-24
Executor: **Codex** · model=`Sol/xhigh` · effort/thinking=high
Justification: Codex already worked `PAY-004` this round and must not be swapped
mid-task. High effort because the script creates **real credit applications in the
bank's test contour** from a live production host, and its guards are the only
thing standing between a test run and the production contour.

Roadmap: `PAY-002` · Notion `3aa6bf20-bdb4-812a-b541-ef4d483f3657`
Risky zone: **payment** (outbound bank calls) · **DB** (one bounded INSERT)
One work package. One file. No commit, no push, no deploy.

---

## 1. Task ID

`PAY-002` — first bank test-contour drive. This is the QA instrument, not a
feature. It unblocks nothing in `PAY-004`; the two are independent.

## 2. Context

The PUMB extension is deployed with `payment_pumb_credit_status` effectively off
(the setting row is absent, which is OpenCart's representation of an unchecked
switch — see `diagnostics/PAY-004_pumb-customer-selected-term_review_20260824.md`
§Blocking). There is no PUMB card in the checkout UI (`PAY-001-UI` is
`Not started`; the credit modal renders PUMB as `СКОРО БУДЕ`), and the admin panel
offers only shipment-confirm, cancel and refund — all of which need a `cap_id`
that does not exist yet.

Consequence: **no PUMB application can currently be created by any means.**

Everything the shop still does not know is bank-side, and none of it needs a
checkout button to answer:

| Open question | Source |
|---|---|
| Do the test credentials from the bank's 2026-08-12 letter actually authenticate? | never tried |
| Is the create payload accepted as built, or rejected on validation? | never tried |
| **Amount units — hryvnia decimals or Integer kopiykas?** The live code sends hryvnia; the bank's round-2 answer says Integer kopiykas | `plans/PAY-002_pumb-protocol-revision_20260727.md` §7b Q5 — unresolved |
| The exact `state` string the API returns — `FUNDED` or `FOUNDED` | §7b / §7d; bank said `Funded` in chat 2026-08-24, casing unverified |
| Does the bank's callback reach our route, pass Basic auth, and pass the IP allowlist? | the allowlist has never been populated during a real bank call |
| Does `PATCH goods_shipped` behave as documented, including `409` when not yet shippable | never tried |

Owner decision, 2026-08-24: answer these with a **one-off CLI script**, run by the
owner in the site terminal, rather than by building a temporary UI. The method
stays off, nothing customer-reachable changes, and the script is deleted
afterwards.

The bank's test phone number, received 2026-08-24: `+380695060051`.

## 3. Goal

One command the owner can run in `~/public_html` that proves — or disproves — the
whole bank round trip, prints verbatim evidence for each open question above, and
leaves the site exactly as it found it apart from one clearly-marked test
transaction row.

## 4. What to change

Create **one** new file:
`patches/PAY-002_bank-test-drive_diagnostic_20260824.php`

Classified as a diagnostic, following the existing house style in
`patches/*_diagnostic_*.php` (`out('key', 'value')` lines, `declare(strict_types=1)`,
run from `~/public_html`). It is **not** a patch: the seven patch conventions do
not apply, and in particular it must **not** self-delete, because several runs are
expected.

### 4.1 Guards — refuse to run unless all hold

Every one of these is a hard stop with a plain message, checked **before** any
network call:

1. `PHP_SAPI === 'cli'`. Refuse to execute over HTTP under any circumstance.
2. `config.php` present in the working directory; DB constants defined.
3. `payment_pumb_credit_test_mode` is `1`.
4. `payment_pumb_credit_oauth_url` and `payment_pumb_credit_api_base` both point
   at the **test** hosts (`auth.dts.fuib.com`, `api.dts.fuib.com`). A literal
   host check, not a substring guess. If either resolves to `pumb.ua`, abort with
   `production_contour_refused`.
5. `payment_pumb_credit_oauth_username` and `..._oauth_password` are both
   non-empty. If either is empty, say exactly which one and stop — that is the
   likely state today (both were empty in the 2026-08-24 10:35 backup).
6. `payment_pumb_credit_status` is absent or `0`. Never proceed if the method is
   live.

### 4.2 Arguments

```
--order=<opencart_order_id>     required for the create step
--term=<3|4|5>                  required for the create step
--units=<hryvnia|kopiyka>       required for the create step
--phone=<+380XXXXXXXXX>         optional, default +380695060051
--live                          without it, the script is dry-run
```

**Dry-run is the default.** Without `--live` the script performs the read-only
steps and prints the exact JSON it *would* POST, and stops. Creating a real
application must require the owner to type `--live` deliberately.

### 4.3 Steps

1. **Token.** `POST` to the configured OAuth URL, form-encoded, `client_id=EXT_OIC`,
   `grant_type=password`. Print `oauth_http=<code>`, `oauth_token=ok|fail`, and
   `oauth_expires_in=<n>`. **Never print the token, the password, or any part of
   either.**
2. **Read fixtures.** `GET /sf-credits/1` and `GET /sf-credits/13`. Print the HTTP
   code and the `state` value **verbatim, byte for byte** — this is what settles
   the `FUNDED` / `FOUNDED` / `Funded` casing question. Do not normalise case.
3. **Build the create payload** for the given order, mirroring the field shape the
   live module builds in `createPayload()`:
   `store_order_id`, `point_of_sale_code`, `partner_name`,
   `channel_type = INTERNET`, `flow.type = DIGITAL_SF`, `customer.phone`,
   `invoices[0]` with today's date, `invoice_number`, `goods[]`, `total_amount`,
   and `credit_request` with `term` and `amount`.
   - `goods[]` comes from `oc_order_product` for the given order.
   - `--units=kopiyka` multiplies every amount by 100 and casts to `int`;
     `--units=hryvnia` sends the rounded decimal. One flag, both hypotheses
     testable in two runs.
   - Before sending, assert locally that `sum(goods.amount × goods.count)` equals
     `invoices[0].total_amount` equals `credit_request.amount`, and print all
     three numbers. A local mismatch aborts before the bank sees it.
   - Print the full payload JSON.
4. **Create**, only with `--live`. Print `create_http=<code>` and the **entire
   response body verbatim**, success or failure. A `400` validation message is a
   successful outcome for this exercise — it is how the units question gets
   answered.
5. **Persist**, only on `HTTP 201`. Insert exactly one row into
   `{DB_PREFIX}pumb_credit_transaction`: the returned `cap_id`, `is_test = 1`,
   `state` exactly as returned, `order_id`, `store_order_id`, and the request and
   response in `payload`. This exists so the owner can then drive shipment-confirm
   and refund from the **existing** admin buttons, which look the row up by
   `cap_id`.
   - Print the exact `DELETE FROM ... WHERE cap_id='...' AND is_test=1` statement
     that would undo this insert. One row, named explicitly.
6. **Closing reminder.** Print, unconditionally, that the file must be deleted
   from `~/public_html` when testing is finished.

### 4.4 Output discipline

Machine-readable `key=value` lines, one per fact, like the existing diagnostics.
The owner will paste the output back for review, so it must be complete enough to
review from and must contain no secret. Explicitly forbidden in output: the OAuth
password, the bearer token, the callback Basic credentials, customer personal
data beyond the phone number being tested.

## 5. Do not touch

- Any file under `extension/pumb_credit` or `extension/mono_chast`. The script
  reads settings from the database; it does not load, include, or edit extension
  code.
- Any row in `oc_setting`. The script is read-only against settings.
- `payment_pumb_credit_status` — never write it, never turn the method on.
- The callback routes, Basic-auth check, IP allowlist, `is_test` separation.
- Any existing `pumb_credit_transaction` row. The only write is the single INSERT
  in step 5. No `UPDATE`, no `DELETE`, no `ALTER`.
- The production contour — see guard 4.
- Any OpenCart order record. `oc_order` and `oc_order_product` are read-only here;
  the script must not create an order, change an order status, or touch order
  history.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed,
  schema, fiscalization, Nova Poshta, Hutko, Checkbox, CRM.
- `patches/PAY-004_*` — a separate, still-open round with a returned blocker.

## 6. Likely files / areas

- **New:** `patches/PAY-002_bank-test-drive_diagnostic_20260824.php` — the only
  file created.
- **Read-only reference**, to mirror the payload shape exactly:
  `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`
  → `createPayload()`, `api()`, `oauthToken()`, `upsertTransaction()`.
  Live copy available at `pumb-live_2026-08-14.tar.gz`; re-verify against
  `backup-8.24.2026_10-35-09_boosters.tar.gz` before relying on it.
- **Read-only reference** for the transaction table shape:
  `patches/PAY-002_pumb-credit-skeleton_20260728.php` (the `CREATE TABLE`).
  Note the `requested_term` column does **not** exist yet — `PAY-004` adds it and
  was returned for changes, so this script must not assume it.

## 7. Acceptance criteria

1. Run over HTTP (not CLI) → refuses, no network call made.
2. Run with `payment_pumb_credit_test_mode = 0`, or with a `pumb.ua` host
   configured → refuses with `production_contour_refused`, no network call made.
3. Run with empty OAuth credentials → names the empty field and stops.
4. Dry-run without `--live` → prints the full payload JSON and makes **no**
   `POST /sf-credits`; no transaction row is created.
5. `oauth_http=200` and `oauth_token=ok` on a correctly configured test contour.
6. `GET /sf-credits/1` and `/sf-credits/13` each print an HTTP code and a `state`
   string reproduced exactly as received.
7. `--live` create prints `create_http` and the complete response body, whatever
   it is.
8. On `201`: exactly one new row in `pumb_credit_transaction` with `is_test=1`,
   and the printed `DELETE` statement matches that row.
9. No secret appears anywhere in the output. Verified by reading the output, not
   by assertion.
10. The script is safe to run twice: a second dry-run changes nothing; a second
    `--live` run on the same order is expected to create a second application
    (the bank has no dedup — `plans/PAY-002_pumb-protocol-revision_20260727.md`
    §7b Q8), and the script must **warn** about this before creating when a row
    for that `order_id` already exists.

## 8. QA / smoke test

Payment zone, so `bs-checkout-smoke` is named — but it does **not** apply this
round: nothing customer-reachable changes and the method stays off. The real gate
remains `PAY-001-SMOKE` before go-live.

Owner sequence, after upload to `~/public_html`:

1. Dry-run on a real recent order, term 4, hryvnia. Read the payload.
2. Dry-run again with `--units=kopiyka`. Compare.
3. One `--live` run with whichever unit form the owner chooses first.
4. If `400`: read the bank's validation text, re-run `--live` with the other unit
   form. Two runs settle the question.
5. On `201`: the bank pushes a notification to the phone; confirm in the PUMB
   Online app; watch whether the callback reaches the site and whether the order
   status moves.
6. Then drive shipment-confirm from the existing admin panel using the printed
   `cap_id`.
7. Delete the script from `~/public_html`.

## 9. Rollback note

The script changes no file and no setting, so there is nothing to restore.

The only persistent effect is the single `pumb_credit_transaction` row from step
5, and the script prints the exact `DELETE` statement for it. Removing that row
does **not** cancel the application on the bank's side — that is done through the
admin cancel action (`PATCH method=CLOSE`) or by letting it expire.

If the script itself misbehaves, deleting the file is the entire rollback.

Blast radius: outbound calls to the bank's **test** contour, plus one clearly
marked test row. No customer-facing surface, no order flow, no settings.

## 10. Recommended status after execution

`PAY-002` stays `In progress`. This script produces evidence, not a closure. What
it should produce is a diagnostic report recording, at minimum: the verbatim
`state` strings, which amount unit the bank accepted, the create response, whether
the callback arrived, and whether the IP allowlist let it through. Those answers
then feed `PAY-004`, `PAY-001-UI` and `PAY-001-SMOKE`.

---

## Delivery

One file into `patches/`. The owner uploads it to `~/public_html` and runs it with
the arguments above. The executor does not commit, push, deploy, or run it. The
diagnostic report goes to
`diagnostics/PAY-002_bank-test-drive_report_20260824.md` and must state which
guards were exercised locally and which could only be exercised by the owner.
