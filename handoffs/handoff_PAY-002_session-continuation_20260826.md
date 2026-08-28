# PAY-002 — session continuation handoff

Date: 2026-08-26
Audience: **Claude (chat), next session.** Not a patch handoff — no executor is
assigned and nothing here is ready to hand to Codex without further work.

Purpose: let a fresh session resume PUMB «Сплачуйте частинами» without re-reading
the whole task history. Read this first, then only the files it names.

---

## 1. One-line state

The bank-side integration is **finished and proven**. Everything that remains is
on our side, and the single thing blocking a launch is that **there is no PUMB
card in checkout — a customer physically cannot choose the instalment.**

`PAY-002` and `PAY-004` are both `In progress` in Notion.

## 2. What is proven, and by what

Full lifecycle on the bank's test contour, 2026-08-26, application
`cap_id 19040054` (order #332, term 4, 700.00 UAH):

```
POST /sf-credits 201 → callback → client signs → WAITING_STORE_CONFIRM
(agreement_number written automatically) → PATCH goods_shipped 200 → FUNDED
→ refund 201 → REFUND_FINISHED
```

Inbound callbacks land at every step: `194.44.66.21`, user `pumb_test_cb`,
HTTP 200, delivered to
`...index.php?route=extension/pumb_credit/payment/pumb_credit.callbackTest`.

Evidence: `diagnostics/PAY-002_bank-test-drive_result_20260825.md` — the single
most useful file in this task. It records the runs verbatim, with timings and
`X-Flow-Id` values.

## 3. Five facts that overturn older documents

Older sections of `plans/PAY-002_pumb-protocol-revision_20260727.md` are wrong
where they conflict with these. The plan carries dated corrections inline; trust
the correction, not the original paragraph.

| Fact | Was previously documented as | Proven by |
|---|---|---|
| Amounts are sent in **hryvnia**, decimal | "Integer, in kopiykas" (§7b Q5, written bank answer) | `700.0` accepted, bank confirmed «700 грн» in chat |
| Final state string is **`FUNDED`**, uppercase | `FOUNDED` claimed by two humans | verbatim API response |
| The bank **does retry** callbacks, ~3× / 10 s | "no automatic retries" (§7b Q3) | production access log |
| `GET /sf-credits/{id}` **requires `X-Flow-Id`** | not stated anywhere | 400 without it, 200 with it |
| Production terms are **3/4/5**; term 5 is stage-disabled | open question | Roman Nazarenko, 2026-08-26 (§7e) |

Also: the callback `401` was **never** a header-stripping problem. A probe proved
`Authorization` reaches PHP untouched; the `.htaccess` change built for that
hypothesis was rolled back and the live file carries no PAY-002 marker. The
cause was a stale Basic password on the bank's side, since rotated.

## 4. What is left, in rough dependency order

1. **PUMB card in checkout.** Does not exist; the credit modal renders PUMB as
   `СКОРО БУДЕ`. Owner's stated preference (2026-08-24) is to build it properly
   but gate visibility to the customer group **`Тестувальники`**
   (`customer_group_id = 3`, confirmed present in the live DB), with a
   single admin field to open it to everyone later. **Enforce the group check
   server-side in `confirm()`, not only in the template** — once the method is
   enabled, the disabled-status safety net is gone and the group check replaces
   it. Scope belongs to `PAY-001-UI` / `PAY-003`; no handoff written yet.
2. **`PAY-004`** — the customer-selected term still has no path into `confirm()`.
   The server half is deployed and correct; it now *rejects* any call without a
   valid `term`, so the card in item 1 must supply one.
3. **Bank approval of the layout** carrying the PUMB brand — contract п. 2.2.8.
   Not started, no contact named, and plausibly the longest lead time of
   anything here. Worth starting in parallel rather than last.
4. **Order-status consolidation** — §8a of the plan, owner-approved 2026-07-27,
   6 mono statuses → 5 shared. Not done. PUMB currently maps onto the
   `ПЧ mono — …` statuses.
5. **`NCRM-14`** verification on a real PUMB order.
6. **`PAY-001-SMOKE`** — the shared final gate for mono + PUMB.

Two pre-go-live checks that are easy to forget:

- **Verify the 7-day application window actually applies.** The bank said
  «налаштуємо» (future tense) on 2026-08-26 — it was being configured, not
  reported as already live. Test with a deliberately delayed shipment
  confirmation. Do not take it on trust; the 2026-08-25 `FAIL` on `19039895`
  happened for exactly this reason.
- **Term 5 cannot be exercised on the test contour at all.** The first real
  5-payment application will be a production one unless the bank enables it on
  stage. Owner decision needed.

## 5. Environment facts worth not rediscovering

- Production PHP is **8.0.30**; there is no 8.1+ binary on the host
  (`ea-php72` is the only other one). `never`, `enum`, `readonly` in a patch die
  at parse time, before any guard runs.
- The host runs **LiteSpeed** (`.htaccess` carries LSCACHE blocks).
  `CGIPassAuth` would be silently ignored; mod_rewrite forms work.
- OpenCart 4 `config.php` defines **`HTTP_SERVER`** only — there is no
  `HTTPS_SERVER`. Its value is already `https://boostershop.website/`.
- SEO URLs are keyword-only: the Pokémon category is `/Pokemon`, **not**
  `/catalog/Pokemon`.
- `payment_pumb_credit_status` **has no row** in `oc_setting`. That is OpenCart's
  normal representation of an unchecked switch, not corruption. Any guard must
  treat "absent" and `'0'` as equally meaning disabled.
- Order **#332** was the disposable test order. Its three test transaction rows
  were deleted on 2026-08-26; the order itself still exists.

## 6. Live configuration, as of now

Method disabled for customers. Test contour on. OAuth and API base point at
`*.dts.fuib.com`. `payment_pumb_credit_terms = [3,4,5]` — correct for
production, no change needed. `min_total = 500`, `max_total = 500000`.
Test callback Basic user `pumb_test_cb`, password rotated 2026-08-26 and shared
with the bank. **Production callback credentials are deliberately empty** so a
bank test callback can never write into production order state — do not fill
them until the production cutover.

## 7. Open with the bank

Nothing blocking. All previously open questions are closed. Not yet asked:
what exactly is required from us to switch to the production contour, and who
approves the brand layout.

## 8. Immediate next action

Ask the owner whether to start the checkout card (item 1). If yes, that begins
with a design/scope decision, not a patch: the card lives in the shared
«Купити в кредит» modal alongside monobank, and the group gate plus the term
selector have to be specified together, because `PAY-004` depends on the term
reaching `confirm()`.

## 9. Files to read, in order

1. `diagnostics/PAY-002_bank-test-drive_result_20260825.md` — everything proven, verbatim
2. `plans/PAY-002_pumb-protocol-revision_20260727.md` — protocol, with dated corrections in §7a–§7e
3. `handoffs/handoff_PAY-004_pumb-customer-selected-term_20260824.md` — term scope
4. `diagnostics/PAY-002-PAY-004_round2_review_20260824.md` — what is deployed and why

Do not read the whole task history. Do not re-read the 2026-07 rounds unless a
specific question needs them.
