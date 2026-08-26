# PAY-004 — PUMB: pass the customer-selected instalment term to the bank

Date: 2026-08-24
Executor: **Codex** · model=`Sol/xhigh` · effort/thinking=high
Executor assignment: **owner decision, 2026-08-24** (recorded, not proposed). High
reasoning effort is required because this is a risky-zone change (checkout /
payment) whose correct shape depends on how the existing credit modal persists the
customer's choice — that must be discovered in live code, not assumed.

Roadmap: `PAY-004` · Notion `3c66bf20-bdb4-81e3-93fd-d503961ab549`
Risky zone: **checkout · payment · order status**
One work package. One patch file. No commit, no push, no deploy.

---

## 1. Task ID

`PAY-004` — PUMB sends a fixed instalment term instead of the customer's choice.

## 2. Context

`extension/pumb_credit` is deployed on production with
`payment_pumb_credit_status = 0` (disabled for customers). Test-contour
credentials, a bank test phone number, the confirmed final state string `FUNDED`
and the confirmed 500 000 UAH ceiling are all now in place, so the first PUMB test
order is imminent.

**Defect.** `createPayload()` builds `credit_request.term` from a single admin
setting and `confirm()` never reads any customer-supplied term:

```php
'credit_request' => ['term' => (int)$this->config->get('payment_pumb_credit_term'), 'amount' => $total]
```

Live setting on 2026-08-14: `payment_pumb_credit_term = 3`. Every PUMB application
would therefore be a 3-payment agreement regardless of what the customer selected.

**The monobank precedent — this is the pattern to mirror.** `mono_chast` does not
pass the term as a config value. It reads it back off the order's own payment
method code:

```php
if (!is_array($payment) || !preg_match('/^mono_chast\.mono_chast_([345])$/', (string)($payment['code'] ?? ''), $match)) { ... }
```

and then feeds `$parts` into `createPayload(array $order, int $parts)` →
`available_parts_count => [$parts]`. The customer's choice is already persisted on
the order before `confirm()` runs. Live setting:
`payment_mono_chast_parts = [3,4,5]`.

`pumb_credit::confirm()` performs **no** `payment_method` inspection at all.

### Evidence

- Live extension tree pulled from `~/public_html`:
  `pumb-live_2026-08-14.tar.gz` (repository root).
- Re-verified independently against
  `backup-8.16.2026_08-03-55_boosters.tar.gz` →
  `homedir/public_html/extension/pumb_credit/`.
- Live `oc_setting` dump, 2026-08-14 (`pumb-settings.txt`, repository root).
- This gap appears in **no** prior PAY-002 artifact —
  `handoffs/handoff_PAY-002_pumb-credit-skeleton_20260727.md`,
  `diagnostics/PAY-002_pumb-credit-skeleton_review_20260728.md` and
  `plans/PAY-002_pumb-protocol-revision_20260727.md` are all silent on `term`.
  Found by the owner while reviewing the admin panel on 2026-08-24.

### Bank-side constraint

The bank's API accepts
`term ∈ [2,3,4,5,6,7,8,9,10,12,15,18,20,24]`
(`plans/PAY-002_pumb-protocol-revision_20260727.md` §3). Booster Shop's business
rule is **3 / 4 / 5 only**, enforced on our side. An unvalidated value arriving
from the browser would be silently accepted by the bank and would create a real
agreement on a term the shop never offered.

## 3. Goal

The instalment term the customer selected reaches `credit_request.term` in the
PUMB create call, is validated server-side against the shop's allowed set, and is
recorded on the transaction row.

## 4. What to change

**Step 0 — discovery, before writing any code.** Determine how the customer's
term selection is persisted today for the PUMB path. Three possibilities; the
executor must establish which is true against live files and say so in the report:

- (a) the shared «Купити в кредит» modal already writes a PUMB-specific
  `payment_method` code analogous to `mono_chast.mono_chast_{3,4,5}`;
- (b) the modal offers only the monobank provider today, and no PUMB code path
  exists yet (`PAY-001-UI` is `Not started`, and both provider models return `[]`
  from `getMethods()`, so PUMB is not exposed through Simple Checkout at all);
- (c) something else.

If (b) holds, the PUMB side has no persisted customer choice to read yet. In that
case **do not invent a UI**. Implement the server-side half only — the accepting,
validating and forwarding of an explicit term — and state plainly in the report
that the selection UI is `PAY-001-UI` / `PAY-003` scope and remains missing. Do
not silently leave the config fallback in place as if the task were complete.

**Then:**

1. **Carry the term into `confirm()`.** Prefer the mono pattern: derive the term
   from the order's own persisted `payment_method` code rather than from a
   request parameter, so the value cannot be tampered with after the order exists.
   If, and only if, discovery shows there is no such persisted code for PUMB,
   accept an explicit request parameter — and validate it as in step 2.
2. **Validate server-side against the shop's allowed set.** Reject anything
   outside it with the existing error-reply shape. Never forward a
   browser-supplied integer to the bank unchecked. The allowed set must come from
   configuration, not from a literal in the controller.
3. **Pass it through.** Change `createPayload(array $order)` to take the term as
   an explicit argument, mirroring
   `mono_chast::createPayload(array $order, int $parts)`. Do not read the config
   inside `createPayload()`.
4. **Persist it.** Store the term actually sent on the `pumb_credit_transaction`
   row, so an order's real requested term is recoverable without re-reading the
   bank. Adding a column requires the same idempotent-migration care as the
   skeleton patch used for the table itself.
5. **Decide the setting's new meaning.** Convert `payment_pumb_credit_term` into
   an allowed-terms list mirroring `payment_mono_chast_parts = [3,4,5]`, or keep
   it as a single fallback default and add a separate list setting. **Prefer the
   list**, for symmetry with mono. Whichever is chosen, the admin template label
   and the `$keys` array in the admin controller must be updated to match, and an
   existing stored value of `3` must not break on upgrade.

## 5. Do not touch

- `payment_pumb_credit_status` — must remain `0` for the whole of this task.
- The callback routes, Basic-auth check, IP allowlist, and the `is_test`
  separation between test and production state.
- The idempotency guard in `confirm()` / `reserveCreate()` /
  `replyExistingCreate()` (`PAY-002`, 2026-07-29) — the term change must not
  weaken the "one open application per order" property.
- `applyOrderStatus()` and the shared order-status mapping (`PAY-002` §8a scope).
- `extension/mono_chast` — read it as a precedent, change nothing in it.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, the Merchant
  feed, schema / JSON-LD, fiscalization, Nova Poshta, Hutko, Checkbox.
- The checkout templates beyond what step 0 discovery requires reading.
- **Two known, separately-tracked defects that are out of scope here — do not
  fix them in this patch, but report if the code forces you to touch them:**
  - `createPayload()` computes `total` from `oc_order_product` rows only, so
    shipping, coupons and discounts are not reconciled into
    `sum(invoices[].total_amount) == credit_request.amount`
    (`plans/PAY-002_pumb-protocol-revision_20260727.md` §10).
  - Amount units. The live code sends hryvnia decimals; the bank's round-2 answer
    (§7b, Q5) states the authoritative format is **Integer, in kopiykas**
    (1000.00 UAH → `100000`). Unverified against a live response. Flag it, do not
    change it here.

## 6. Likely files / areas

Marked **likely**, not confirmed — the executor must verify against actual
project files and against the newest backup before editing.

- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php`
  — `confirm()`, `createPayload()`, `upsertTransaction()`.
- `extension/pumb_credit/admin/controller/payment/pumb_credit.php`
  — the `$keys` array (line ~9) that whitelists saved settings.
- `extension/pumb_credit/admin/view/template/payment/pumb_credit.twig`
  — the field/label loop.
- `{DB_PREFIX}pumb_credit_transaction` — one added column, idempotent migration.
- `extension/pumb_credit/catalog/language/uk-ua/payment/pumb_credit.php`
  — one new error string for a rejected term.
- Checkout / credit-modal templates — **read-only during discovery** unless step 0
  proves a change is unavoidable; if it is, say so and stop for owner approval
  rather than expanding scope.

Precedent to read, not to modify:
`extension/mono_chast/catalog/controller/payment/mono_chast.php`.

## 7. Acceptance criteria

1. A create call for an order whose selected term is 4 sends
   `credit_request.term = 4` — demonstrated from the stored `payload` JSON on the
   `pumb_credit_transaction` row, not from reasoning about the code.
2. Same for 5.
3. A term outside the configured allowed set is rejected before any bank call:
   JSON error reply, HTTP-level no `POST /sf-credits` issued, no transaction row
   created in a live state.
4. `createPayload()` contains no `payment_pumb_credit_term` lookup.
5. The term actually sent is readable from the transaction row for a given
   `order_id`.
6. Admin panel: the term setting saves and reloads correctly, and a pre-existing
   stored value of `3` does not error after the patch.
7. `payment_pumb_credit_status` is still `0` after the patch runs.
8. The idempotency guard still holds: a second `confirm()` for the same
   `order_id` returns the existing `cap_id` and does not create a second
   application.
9. The patch is idempotent — a second run is a no-op and reports as such.

## 8. QA / smoke test

Payment and checkout are touched, so `bs-checkout-smoke` applies. Because the
method stays disabled, the owner-facing QA for this round is narrow — the full
purchase-flow smoke belongs to `PAY-001-SMOKE`.

Owner runs, after upload:

1. `php <patch>.php` in `~/public_html`; expect an explicit success line and a
   written idempotency marker.
2. Re-run the same command; expect a clean "already applied" no-op.
3. Admin → PUMB module: confirm the term field renders with its new meaning,
   save, reload, value persists.
4. Confirm the enable switch is still off and `Test contour` is still on.
5. Open the storefront checkout and confirm nothing changed visually and no PHP
   error appears — the method is disabled, so the credit modal must behave
   exactly as before.

The real proof of criteria 1–3 comes with the first bank test-contour order
(bank test phone `+380695060051`), which is the next task after this patch and is
not part of this handoff.

## 9. Rollback note

The skeleton patch series writes a timestamped backup into
`~/public_html/_patch_backups/<PATCH-NAME>-<timestamp>/` before editing — the
executor must follow the same convention and name the exact restore path in the
report.

Rollback = restore
`extension/pumb_credit/catalog/controller/payment/pumb_credit.php`, the admin
controller and the admin template from that backup directory, remove the patch's
idempotency marker, and refresh the OpenCart compiled-template/cache.

The added transaction column is additive and may be left in place on rollback; it
must not be dropped as part of an emergency restore. If the setting's storage
format changed, the report must state the exact prior value so the owner can
restore it by hand from the admin panel.

Blast radius: the PUMB extension only. The method is disabled for customers, so a
failed patch cannot affect live orders — but it can affect the admin settings
page, so the owner must confirm that page still loads after deployment.

## 10. Recommended status after execution

`In progress` — the patch alone does not close `PAY-004`. Closure requires the
bank test-contour order proving a 4- or 5-payment term end to end (owner decision
2026-08-24: the first PUMB test runs on a non-default term precisely so this is
covered in one pass). Status is written by Claude (chat) on owner authorization;
the executor never writes Notion.

---

## Delivery

One patch file into `patches/`, named
`PAY-004_pumb-customer-selected-term_20260824.php`. The owner uploads it to
`~/public_html` and runs `php PAY-004_pumb-customer-selected-term_20260824.php`.
The executor does not commit, push, or deploy. A diagnostic report goes to
`diagnostics/PAY-004_pumb-customer-selected-term_report_20260824.md` and must
state which of the step-0 discovery outcomes (a) / (b) / (c) was found true.
