# Codex Handoff — CHECKOUT-008: IBAN payment requisites in order-confirmation email + checkout success "copy requisites" button

Date: 2026-07-29. Parent: none.
Codex config: model=Sol · effort=xhigh

**HIGH-RISK zone** (checkout, payment method logic, order-confirmation email) → `bs-checkout-smoke` mandatory before Done.

> Live-file task: this repo holds no local copy of the OpenCart mail template/language files, the payment-method controller list, or `checkout/success.twig` (owner-server-only, same situation noted in CHECKOUT-007). Codex must diagnose against the **newest owner cPanel backup** before writing anything — do not guess file paths, the mail-send mechanism, or the IBAN method's `payment_code`. Per `AGENTS.md`: never guess OpenCart structure.

**Decision context (owner-confirmed 2026-07-29, Cowork chat):** customers who select "За реквізитами на IBAN" at checkout currently get no bank details anywhere in the automated order-confirmation email. Owner wants the requisites block added to that email template, **conditionally, only when the order's payment method is IBAN bank transfer** (not Hutko, not COD, not mono/PUMB credit). In the same message, owner also asked for a "Скопіювати реквізити" (copy requisites) button on the checkout success page, for the same payment-method condition — this is now in scope as part of the same task.

**Open item — owner confirmation needed before final copy is deployed:** the owner supplied the tax-ID line as "ЄДРПОУ 3485903435". The already-approved LEGAL-002 offer text (`handoffs/offer_html_20260724.html`, §7.6) labels the identical digits "РНОКПП 3485903435" for this same ФОП (sole proprietor). Same number, different legal label — ЄДРПОУ is normally used for legal entities, РНОКПП for individuals/ФОП, so РНОКПП is the more likely correct label, but Claude is not resolving this silently. **Codex may proceed with diagnosis and implementation using a placeholder/either label, but the owner must confirm the correct label before this ships to real customer inboxes.** Do not deploy the final copy until this is confirmed.

## 1. Task ID

CHECKOUT-008

## 2. Context

- Live checkout (screenshot reviewed 2026-07-29) lists three payment methods: "Картка, Google Pay / Apple Pay", "Оплата при отриманні (накладений платіж)", and "За реквізитами на IBAN". The IBAN method already exists and is selectable; this task does not add a new payment method, only adds requisites *display* in two existing customer-facing surfaces for that method.
- `handoffs/offer_html_20260724.html` §7.6 (task LEGAL-002, owner-authored, "Готово до Codex, не задеплоєно" as of 2026-07-24) is the only other place in this repo where these requisites appear: recipient, tax ID, IBAN, bank name. It does not include MFO or a payment-purpose line — those two are new, supplied by the owner in this task.
- No prior task in `context-index.md` touches the order-confirmation email template at all — its exact file/mechanism is unconfirmed in this repo (native OpenCart `mail` event vs. a theme override). Confirm before editing.
- `checkout/success.twig` has been touched by prior tasks (ST-2b2 "Success page / Hutko / fiscal spacing", ST-2b3 "Confirm summary / success button", CHECKOUT-006/007 First15 success message) — Codex should check those diffs for the current structure and existing conditional-by-payment-method patterns already used there, rather than inventing a new pattern.

## 3. Goal

1. The automated "order created" customer confirmation email includes a fixed requisites block **only** when the order's payment method is the IBAN bank-transfer method.
2. The checkout success page shows a "Скопіювати реквізити" control that copies the same requisites text to the clipboard, **only** for the same payment method.
3. No other payment method's email content or success-page rendering changes.

## 4. What to change

- **Order-confirmation mail template/language file** (exact file to be confirmed by Codex against the newest backup — likely `catalog/language/uk-ua/mail/order.php` and/or the controller/model that assembles and sends the "new order" customer email, possibly `catalog/model/checkout/order.php` or a theme mail override) — add a conditional block, gated on the order's payment method being the IBAN bank-transfer method (confirm exact `payment_code`/identifier live — do not assume it is called `bank_transfer`, `iban`, or anything else without checking), containing exactly this text:

  ```
  Реквізити для оплати:
  Отримувач: ФОП Леусенко Євгеній Андрійович
  [ТАКС-ID-LABEL — see "Open item" above]: 3485903435
  IBAN: UA063348510000000026003285008
  МФО: 334851
  Банк: АТ «ПУМБ»
  Призначення платежу: оплата за товар
  ```

  Replace `[ТАКС-ID-LABEL]` with whichever label the owner confirms (ЄДРПОУ or РНОКПП) before final deploy. Do not translate, reformat, or add anything to this block beyond what is listed (no invented order number, no invented deadline) unless the owner separately asks for it.
- **`catalog/view/template/checkout/success.twig`** (or wherever the live success page template actually is — confirm) — add a small requisites panel + a "Скопіювати реквізити" button, shown only when the just-placed order's payment method is IBAN. Button copies the same text block above (with order-specific data if the template already has it available, otherwise the static block) to the clipboard via a simple JS clipboard call. Match existing success-page markup/DS classes already used for other conditional payment-method blocks on that page (e.g. the existing Hutko/fiscal blocks) rather than introducing new ad-hoc styling. Per `AGENTS.md` UI/CSS discipline: this is a copy-utility control, not a purchase/cart/checkout/success primary action — do not use the DS's reserved purchase-green button style for it unless the current design-system source explicitly allows secondary utility buttons in that color; use whatever secondary/neutral button class the page already uses elsewhere.
- If the mail body turns out to be stored in the database (e.g. an `information`-style row, similar to the LEGAL-002 offer mechanism) rather than a flat language file, follow that task's backup+verify pattern (`patches/patch-r09fix-toc-offer-20260526-v3.php`: base64-decode → `UPDATE ... WHERE id=? AND language_id=?` → hash verify → JSON backup of the old row) instead of a flat-file edit. Confirm which mechanism actually applies before choosing an approach.

## 5. Do not touch

- Hutko, COD, and mono/PUMB credit payment method logic, their email content, or their success-page blocks — this task adds a new conditional branch only, it must not alter any existing branch's output.
- Order-write boundary, `confirm.confirm`, Hutko `buildRequest`/amount/sign, Checkbox/fiscalization, NP shipping/cost logic, order statuses.
- `handoffs/offer_html_20260724.html` / the live public offer page (LEGAL-002) — separate task, do not deploy or alter it as part of this one, even though it shares the IBAN requisites facts.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, Product schema/JSON-LD.
- Any other `information` page, navigation menu, or footer.
- Do not invent or add an order number, payment deadline, or cancellation-window text to the email/success block — the owner specified an exact, fixed text; do not extend it without a separate owner decision (note: the approved offer text §7.7 does mention a 24h non-payment cancellation window — that is out of scope here unless the owner asks for it separately).

## 6. Likely files / areas (unconfirmed — verify against newest backup first)

- Order-confirmation mail trigger/template: likely `catalog/model/checkout/order.php` (or wherever `addHistory`/order-add mail is sent) + `catalog/language/uk-ua/mail/order.php`, or a theme-level mail override if one exists. Confirm which.
- Payment method identifier for IBAN: check `oc_order.payment_code` values on a few recent real orders placed with "За реквізитами на IBAN", or the relevant payment extension's config/settings table.
- `catalog/view/template/checkout/success.twig` — success-page button; verify against ST-2b2/ST-2b3/CHECKOUT-006/007 diffs for existing conditional-block patterns.
- If OpenCart 4's `ArrayLoader` is in play for mail templates: per `AGENTS.md`, do not prescribe a new `{% include %}` partial without first proving the active loader supports it for this template.

## 7. Acceptance criteria (measurable)

1. A test order placed with payment method = IBAN bank transfer → the actual received confirmation email contains the exact requisites block from §4 (owner verifies the real inbox, not just template source).
2. A test order placed with COD, Hutko, or mono/PUMB credit → the received confirmation email does **not** contain the IBAN block; content otherwise unchanged from before this patch.
3. Checkout success page for an IBAN order shows the "Скопіювати реквізити" control; clicking it and pasting elsewhere reproduces the exact text block.
4. Success page for a non-IBAN order shows no such control; page otherwise renders unchanged.
5. No new JS console errors on the success page.
6. No change to order-write behavior, totals, or any other payment method's flow.

## 8. QA / smoke test

Full `bs-checkout-smoke` 11-step run, plus: place one test order per payment method (IBAN, COD, Hutko — mono/PUMB credit only if currently enabled in prod) and for each, inspect (a) the actual received confirmation email and (b) the success page, confirming the new block/button appears only on the IBAN order and is completely absent from the others.

## 9. Rollback note

Back up every changed file (mail template/language file, `success.twig`, and any controller/model touched) to `_patch_backups/CHECKOUT-008_<slug>-<timestamp>/` before writing. If the mail body turns out to be DB-stored, take a JSON backup of the pre-change row (same pattern as the LEGAL-002 offer patch) before any `UPDATE`. Restore from backup and clear `cache.*`/compiled templates on any failure. No schema changes are expected; if diagnosis reveals otherwise, stop and report before proceeding.

## 10. Recommended status after execution

`In progress` (current Notion status) until Codex diagnosis + patch are ready → Claude reviews the bounded diff → owner deploys, confirms the ЄДРПОУ/РНОКПП label from the "Open item" above, runs `bs-checkout-smoke`, and places one real IBAN test order to inspect the actual received email and success page → `Done` only after that live QA, per `ROADMAP_SOP.md` §6 (checkout/payment/order-flow tasks require `bs-checkout-smoke` + owner manual QA before `Done`).
