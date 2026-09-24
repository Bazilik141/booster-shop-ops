# Handoff — PAY-002 WP5: PUMB on the product page and in the credit modal

Date: 2026-08-31
Executor: **Codex** · model=`Sol/xhigh` · effort=high
Justification: three shared live files including a 1273-line product template and
the checkout controller — risky zone, and Codex holds the whole PUMB context from
WP1–WP4. Owner decides; do not swap executor mid-task.

Parent: `handoffs/handoff_PAY-002_pumb-checkout-card-token-gate_20260828.md`
Context: `diagnostics/PAY-002_verification-ledger-and-next-bank-test_20260831.md`
(addendum 2026-08-31)

## 1. Task ID

`PAY-002`. No new roadmap ID unless the owner asks for one.

## 2. Context

WP1–WP4 are deployed. PUMB is selectable in the checkout drawer behind the
preview token, the merged credit row renders correctly, and owner UI QA passed.
The product page was never touched and still shows PUMB as a static
`СКОРО БУДЕ` placeholder in two places.

Live state, read from `backup-8.28.2026_13-26-46_boosters.tar.gz` (the product
files were not modified by WP1–WP4, so this is current — but see §9, hashes must
come from a fresh owner drop):

- `catalog/controller/product/product.php` lines ~383–396 build the credit
  teaser data. `$data['pay001_mono_chast_visible']` is monobank's own predicate:
  `payment_mono_chast_status` + currency UAH + `api_base` + `store_id` +
  `store_secret` all non-empty. It also supplies `pay001_mono_chast_price`,
  `..._min_total`, `..._cart_total`, `..._in_stock`, `..._checkout`.
- `catalog/view/template/product/product.twig`:
  - lines ~329–347: the whole teaser block, wrapped in
    `{% if pay001_mono_chast_visible %}`, containing the «Сплатити частинами»
    button, the monobank provider row, and the PUMB row
    `pay001-provider-row--soon` with `<em>СКОРО БУДЕ</em>`;
  - lines ~1132–1152: the modal, wrapped in the same `{% if %}`, with the
    monobank card (3/4/5 buttons, summary, «Додати й оформити» /
    «Продовжити покупки») and the PUMB card `pay001-modal-provider--soon`;
  - lines ~1220–1262: `addProduct()`. On `action === 'checkout'` it adds to cart
    and navigates to `checkoutUrl + '&mono_chast_parts=' + parts`.
- `catalog/controller/checkout/checkout.php` lines 41–56 consume
  `mono_chast_parts`, validate it against `[3,4,5]`, and write
  `pay001_mono_chast_parts` + `pay001_mono_chast_from_modal` into the session,
  with a guard on the currently selected payment code. **Read this block in full
  and mirror its shape** — do not reimplement from this description.

Owner decision 2026-08-31, recorded here: the owner initially proposed showing
PUMB on the product page to everyone, ungated, since checkout is still closed. It
is not being done that way, and the reason belongs in the file: a customer who
picks PUMB in the modal and lands in a checkout that does not offer it is a
promise broken mid-purchase on a money flow. The alternatives were a disabled
button — which is the placeholder again — or a silent switch to another bank.
Reusing the existing predicate costs nothing extra and makes one admin switch
open both surfaces at once.

## 3. Goal

On the product page and in the credit modal, PUMB appears as a real, selectable
provider under exactly the same visibility condition as checkout, and the
customer's chosen PUMB term survives the jump to checkout. Every other visitor
keeps seeing today's `СКОРО БУДЕ`, unchanged.

## 4. What to change

One patch file, `patches/PAY-002_pumb-product-page-card_20260831.php`.

### 4.1 Product controller — a PUMB visibility flag

Add `$data['pay002_pumb_visible']` beside the monobank block, computed with the
**same predicate semantics as checkout**: `payment_pumb_credit_status` enabled,
currency UAH, all six credential settings non-empty — `payment_pumb_credit_api_base`,
`..._oauth_url`, `..._oauth_username`, `..._oauth_password`, `..._point_of_sale_code`,
`..._partner_name` — and then (`payment_pumb_credit_public` **or**
`$this->session->data['pay002_pumb_preview']`).

Also supply what the card needs: `pay002_pumb_min_total`
(`payment_pumb_credit_min_total`, floor 500), `pay002_pumb_max_total`, and
`pay002_pumb_terms` decoded from `payment_pumb_credit_terms`, intersected with
`[3,4,5]` exactly as `pay002PumbMethod()` does in the checkout controller.

This is the third copy of the predicate (extension, checkout controller, now
product controller) and it mirrors how PAY-001 already duplicates monobank's.
Keep the setting-key list and the boolean shape byte-identical across all three
and add a comment in each naming the other two, so a future reader sees the
coupling. Consolidating them into one shared helper is a legitimate follow-up —
do not attempt it in this patch.

### 4.2 Product template — block gate and the two PUMB spots

- Widen both `{% if pay001_mono_chast_visible %}` wrappers to
  `{% if pay001_mono_chast_visible or pay002_pumb_visible %}`.
- When `pay002_pumb_visible` is false, the PUMB row and the PUMB modal card must
  render **exactly** as today, `СКОРО БУДЕ` included. Byte-identical output for
  the ordinary customer is an acceptance criterion, not a nicety.
- When it is true, the PUMB row becomes a normal provider row (drop
  `--soon` and the `<em>`), and the PUMB modal card gains 3/4/5 buttons, a
  summary and its own «Додати й оформити» / «Продовжити покупки», mirroring the
  monobank card. Reuse the existing classes; add no new CSS file and no
  `!important`.
- **The monobank-off case must work.** If `pay001_mono_chast_visible` is false
  and `pay002_pumb_visible` is true, the monobank card and row must not render at
  all, and the block's JS must still initialise. The current script reads
  `creditRoot.dataset.pay001Price` and friends; make sure nothing dereferences
  monobank-only data when monobank is absent. Today monobank is enabled on
  production, so this path is not exercised by QA — it still must not throw.

### 4.3 Term hand-off to checkout

- The PUMB «Додати й оформити» navigates with a PUMB parameter of its own, e.g.
  `&pumb_credit_term=<n>`. Do not overload `mono_chast_parts`.
- In `catalog/controller/checkout/checkout.php`, mirror the existing lines 41–56
  for the new parameter: validate against the configured PUMB terms, write
  `pay002_pumb_credit_term` into the session, and apply the same treatment to the
  currently selected payment code that the monobank block applies. Read that
  block and follow it; do not invent a different shape.
- The two parameters must be mutually exclusive in effect: arriving with one must
  not leave the other's session state stale from a previous visit.

### 4.4 Copy — one thing not to write

The monobank modal card says «Без комісії для вас». For monobank that is a
documented owner decision — the shop absorbs the commission. **There is no
equivalent evidence for PUMB anywhere in this repository.** Do not put any
commission claim on the PUMB card. Use neutral wording, and leave the existing
shared disclaimer «Без комісії для вас — умови кредитування визначає банк.»
alone or scope it so it does not read as a PUMB claim. If the owner wants a
commission line for PUMB, it comes from the contract, in writing, in a later
patch.

## 5. Do not touch

- Monobank behaviour anywhere — teaser, modal, term buttons, `mono_chast_parts`,
  session keys, summary maths.
- `catalog/view/template/checkout/payment_method.twig` and
  `catalog/controller/checkout/payment_method.php` — WP2–WP4 territory, four
  review rounds; the only checkout-side change here is the parameter block in
  `checkout/checkout.php`.
- `extension/pumb_credit/**` — no change needed; the predicate is duplicated, not
  moved.
- `payment_pumb_credit_status`, `..._public`, the preview token, production
  callback credentials, OAuth/API base.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed,
  schema, fiscalization, Nova Poshta, CRM.
- `boostershop-ds.css`, `stylesheet.css`, any design-system token file.
- The database — this patch writes none.

## 6. Likely files / areas

| File | Note |
|---|---|
| `catalog/controller/product/product.php` | new visibility flag + card data — **shared live file** |
| `catalog/view/template/product/product.twig` | block gate, provider row, modal card, modal JS — **shared live file, 1273 lines, actively edited by other series** |
| `catalog/controller/checkout/checkout.php` | PUMB term parameter, mirroring lines 41–56 — **shared live file, risky zone** |

## 7. Acceptance criteria

With `payment_pumb_credit_status` enabled and `payment_pumb_credit_public = 0`:

1. Ordinary visitor, no preview token: the product page and modal render
   byte-identically to the pre-patch output, `СКОРО БУДЕ` included. Compare the
   rendered HTML, not the screenshot.
2. After opening the preview-token URL, the same product page shows PUMB as a
   real provider row, and the modal shows a PUMB card with 3/4/5.
3. Choosing PUMB, term 4, «Додати й оформити» lands on checkout with the item in
   the cart and **term 4 preselected in the PUMB card of the drawer**.
4. Placing that order sends `credit_request.term = 4` and stores
   `requested_term = 4`. (Bank-side proof is the owner's test, not the patch's.)
5. Monobank regression: the modal still works exactly as before — 3/4/5, summary
   values, «Додати й оформити», `mono_chast_parts` in the URL, term preselected
   in the drawer.
6. With `payment_mono_chast_status` temporarily disabled and PUMB visible, the
   block renders with PUMB only, no monobank markup, and no JS error in the
   console.
7. `payment_pumb_credit_public = 1` makes PUMB visible on the product page with
   no token; back to `0` hides it. No file edit involved.
8. No commission claim appears on the PUMB card.

## 8. QA / smoke test

Purchase flow is touched, so the owner runs `bs-checkout-smoke` in full before
PUMB is left enabled for any length of time. Add the monobank regression path
(criterion 5) explicitly — the modal is the entry point for both providers now.

`bs-seo-risk-gate` is not required: no new URL, no indexable content change; the
product page markup changes only for gated sessions. State that in the report
rather than skipping it silently.

## 9. Patch conventions — read this before writing the runner

Production has **no Node**. The WP3/WP4 pattern is what works and must be reused:

- ask the owner for the current live copies before computing anything:
  `tar -czf booster-debug-files.tar.gz catalog/controller/product/product.php catalog/view/template/product/product.twig catalog/controller/checkout/checkout.php`
- embed `BEFORE_SHA256` / `AFTER_SHA256` **per file**, assert the before-hashes
  before any write and the after-hashes after, restore and verify the restore by
  hash on any failure;
- parse the generated template JavaScript **locally** with `node --check` and
  record the result — never as a production dependency;
- exact anchor counts, timestamped backup, idempotency marker, self-delete on
  success, retained on failure, one `ERROR: …` line and exit 1 on any failure;
- PHP 8.0 only: no `readonly`, `enum`, `never`, first-class callable syntax.

## 10. Rollback note

Restore the three files from
`_patch_backups/PAY-002_pumb-product-page-card_20260831-<ts>/` and clear the
template cache. No database change to undo. Kill switch with no rollback: disable
`payment_pumb_credit_status` in admin — that hides PUMB on both surfaces at once.

## 11. Recommended status after execution

`PAY-002` stays `In progress`. This patch does not close anything on its own; the
outstanding gates remain the owner's bank test, `PAY-005`, §8a, `NCRM-14`, brand
layout approval, production cutover and `PAY-001-SMOKE`.

Diagnostics report required:
`diagnostics/PAY-002_pumb-product-page-card_report_20260831.md`.

## Delivery

Patch file into `patches/`, report into `diagnostics/`. The executor does not
commit, push, upload or deploy. The owner runs it after review.
