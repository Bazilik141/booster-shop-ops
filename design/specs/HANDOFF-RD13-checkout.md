# RD-13 — Checkout reskin · Implementation handoff (FINAL)

_Design approved: 05.07.2026 · Source: `RD-13 Checkout reskin.html` (canvas) +
`rd13-checkout.jsx` (source of truth for exact spacing/behaviour — when this
doc and the JSX ever disagree, the JSX wins; open it side by side)._

> **06.07.2026 update:** this doc describes the end-state (real backend for
> shipping-price/free-shipping-threshold/coupon). That backend isn't built
> yet. For what to actually ship in the *current* patch — a temporary,
> clearly-tagged stub for the free-shipping progress bar and the FIRST15/
> coupon UI, plus every other fix from the first implementation round — see
> `handoff/HANDOFF-RD13-checkout-FIXES-round2.md` (§ P1-1, P1-2). That file
> is authoritative for the current patch; this one stays the target spec for
> when the real backend lands.

**Sequencing note (not a design blocker, a workflow one):** per the original
roadmap, checkout ships after RD-11 (cart) and RD-12 (mini-cart) stabilize —
this page reuses their card/CTA patterns. If those are still `todo`, confirm
with the design lead whether to proceed anyway before starting; this doc
itself is complete and ready either way.

---

## 0. Goal

Reskin `checkout/checkout.twig` from OpenCart's default multi-step accordion
(separate "Confirm" button per step) into **one single-page layout, four card
sections, one primary CTA**:

1. **Отримувач** — name (+ patronymic, optional) / email / phone.
2. **Доставка** — shipping method + saved address OR manual entry.
3. **Оплата** — payment method radios (unchanged wiring, new shell).
4. **Замовлення** — items, shipping+progress, totals, promo, comment,
   agreement, guest account-creation offer, and the **single**
   `Підтвердити замовлення →` button.

## 1. Non-negotiable guardrails

- ❌ **Do not touch payment, fiscalization, Hutko, or Checkbox logic.** Markup
  + CSS only. Radios/inputs keep whatever `name`/`value` the payment module
  already expects.
- ❌ Do not change shipping-cost calculation, promo-code validation logic, the
  address cascade endpoint, or the order-submit endpoint — **re-skin them**,
  don't rebuild them. Every dynamic value below already exists somewhere in
  the current stack (per the reviewer: shipping price via API "уже працює";
  address cascade already live per the current production screenshot).
- ❌ Scope is `checkout/checkout.twig` (+ its CSS/JS). Not `cart.twig`, not the
  mini-cart drawer, not `checkout/success.twig`.
- ✅ **Mandatory full-cycle smoke test before deploy** (§7) — this is the page
  where money moves.

## 2. Files

- `<THEME>/checkout/checkout.twig` — main markup.
- `<CSS>/boostershop-ds.css` — append §5 (namespaced `bs-co-*`).
- `<JS>/checkout-reskin.js` — new, vanilla, no framework (§6).

## 3. Content map — every string, verbatim

Use these exact Ukrainian strings — do not paraphrase:

| Key | Text |
|---|---|
| Title | Оформити замовлення |
| Breadcrumb | Головна › Мій кошик › Оформити замовлення |
| Auth nudge (full) | Маєш акаунт? **Авторизуйся**, щоб не вводити дані заново. / button: Увійти в акаунт → |
| Auth nudge (compact, mobile) | Маєш акаунт? / button: Увійти → |
| Card titles | Отримувач · Доставка · Оплата · Замовлення · N товари |
| Отримувач fields | Ім'я * · Прізвище * · По батькові (hint: Необов'язково) · Телефон * · Електронна пошта * |
| Delivery radios | Нова пошта — у відділення (sub: За тарифами Нової пошти · ~2–3 дні) · Нова пошта — кур'єром (sub: Адресна доставка) · Нова пошта — поштомат |
| Saved-address label | Відділення |
| Manual-entry fields | Область * (placeholder: Почніть вводити область) · Місто * (placeholder until parent set: Спочатку оберіть область) · Тип доставки * (options: Відділення / Поштомат / Кур'єр) · Відділення або поштомат * (placeholder until parent set: Спочатку оберіть місто) |
| Address mode links | + Інша адреса  /  ← Використати збережену адресу |
| Captcha | Перевірка безпеки / Я не робот |
| Newsletter toggle | Хочу отримувати новини Booster Shop |
| Payment radios | Картка, Google Pay / Apple Pay (sub: Безпечно через еквайринг) · Оплата при отриманні (накладений платіж) · За реквізитами на IBAN |
| Shipping block (not free) | Доставка / ₴{price} · До безкоштовної доставки лишилось ₴{remaining} |
| Shipping block (free) | Доставка / Безкоштовно · Безкоштовна доставка застосована ✓ |
| Totals | Сума товарів · Знижка ({CODE} −{pct}%) [only if promo applied] · До сплати |
| Promo (empty) | label: Промокод · placeholder: Введіть промокод · button: Застосувати |
| Promo (applied) | {CODE} · −{pct}%  ·  Промокод застосовано  ·  button: Прибрати |
| Comment | label: Коментар до замовлення · placeholder: Наприклад: бустери для подарунка, упакуйте, будь ласка. |
| Agreement | Погоджуюсь з умовами **Публічної оферти**, включно з положеннями про обробку персональних даних. |
| Agreement error (submit attempted, unchecked) | Погодьтесь з умовами, щоб продовжити |
| Guest save-account toggle | Зберегти дані та зареєструватись — hint: Створимо обліковий запис і надішлемо одноразове посилання для встановлення пароля. |
| Field errors (examples) | Це поле обов'язкове · Введіть номер у форматі 0XXXXXXXXX |
| CTA | Підтвердити замовлення → |
| Mobile collapsed summaries | Отримувач: "{Ім'я} {Прізвище} · {телефон}" · Доставка: "Нова пошта · відділення №{N}" (or "…кур'єром" / "…поштомат №{N}" depending on method) |

**Do not add** any "вміст бустерів випадковий…" disclaimer — reviewer cut it
explicitly; don't reintroduce it.

## 4. Markup (Twig)

```twig
<div id="checkout-cart" class="container bs-co">
  {{ breadcrumb }}
  <h1>Оформити замовлення</h1>

  {% if not logged %}
    <div class="bs-auth-nudge bs-co-mb20">
      {{ icon_user|raw }}
      <div class="bs-co-flex1">Маєш акаунт? <strong>Авторизуйся</strong>, щоб не вводити дані заново.</div>
      <a class="bs-btn bs-btn-ghost" href="{{ login_url }}">Увійти в акаунт →</a>
    </div>
  {% endif %}

  <div class="bs-co-grid">
    <div class="bs-co-col">

      {# ============ 1. Отримувач ============ #}
      <section class="bs-card bs-co-card" data-co-card="receiver">
        <div class="bs-co-card__head">{{ icon_user|raw }}<h3>Отримувач</h3></div>
        <div class="bs-co-row2">
          <div class="bs-field"><label>Ім'я *</label>
            <input class="bs-input" name="firstname" value="{{ firstname }}" required></div>
          <div class="bs-field"><label>Прізвище *</label>
            <input class="bs-input{% if error_lastname %} bs-input--error{% endif %}" name="lastname" value="{{ lastname }}" required>
            {% if error_lastname %}<div class="bs-co-field-error">{{ error_lastname }}</div>{% endif %}</div>
        </div>
        <div class="bs-co-row2">
          <div class="bs-field"><label>По батькові<span class="bs-co-hint">Необов'язково</span></label>
            <input class="bs-input" name="middlename" value="{{ middlename }}"></div>
          <div class="bs-field"><label>Телефон *</label>
            <input class="bs-input{% if error_telephone %} bs-input--error{% endif %}" name="telephone" value="{{ telephone }}" required>
            {% if error_telephone %}<div class="bs-co-field-error">{{ error_telephone }}</div>{% endif %}</div>
        </div>
        <div class="bs-field"><label>Електронна пошта *</label>
          <input class="bs-input" type="email" name="email" value="{{ email }}" required></div>
      </section>

      {# ============ 2. Доставка ============ #}
      <section class="bs-card bs-co-card" data-co-card="delivery">
        <div class="bs-co-card__head">{{ icon_truck|raw }}<h3>Доставка</h3></div>

        {% for method in shipping_methods %}
          <label class="bs-radio-row{% if method.selected %} is-active{% endif %}">
            <input type="radio" name="shipping_method" value="{{ method.code }}" {% if method.selected %}checked{% endif %}>
            <div><div class="bs-radio-row__label">{{ method.title }}</div>
              {% if method.sub %}<div class="bs-radio-row__sub">{{ method.sub }}</div>{% endif %}</div>
          </label>
        {% endfor %}

        {# Two modes — `has_saved_address` decides which renders server-side;
           data-co-address-mode toggle switches client-side without reload. #}
        <div data-co-address-mode="{{ has_saved_address ? 'saved' : 'manual' }}">

          <div class="bs-co-mode-saved"{% if not has_saved_address %} hidden{% endif %}>
            <div class="bs-field"><label>Відділення</label>
              <select class="bs-select" name="branch_id">
                {% for b in saved_branches %}<option value="{{ b.id }}">{{ b.name }}</option>{% endfor %}
              </select>
            </div>
            <button type="button" class="bs-co-link" data-co-address-toggle="manual">+ Інша адреса</button>
          </div>

          <div class="bs-co-mode-manual"{% if has_saved_address %} hidden{% endif %}>
            <div class="bs-co-row2">
              <div class="bs-field"><label>Область *</label>
                <input class="bs-input" name="region" placeholder="Почніть вводити область" data-co-region required></div>
              <div class="bs-field"><label>Місто *</label>
                <input class="bs-input" name="city" placeholder="Спочатку оберіть область" data-co-city disabled required></div>
            </div>
            <div class="bs-co-row2">
              <div class="bs-field"><label>Тип доставки *</label>
                <select class="bs-select" name="delivery_type" required>
                  <option value="branch">Відділення</option>
                  <option value="postomat">Поштомат</option>
                  <option value="courier">Кур'єр</option>
                </select>
              </div>
              <div class="bs-field"><label>Відділення або поштомат *</label>
                <input class="bs-input" name="branch" placeholder="Спочатку оберіть місто" data-co-branch disabled required></div>
            </div>

            {% if has_saved_address %}
              <button type="button" class="bs-co-link" data-co-address-toggle="saved">← Використати збережену адресу</button>
            {% endif %}

            {% if not logged %}
              <div class="bs-field">
                <label>Перевірка безпеки</label>
                {{ captcha_widget|raw }} {# existing captcha module render call — do not rebuild, just place it here #}
              </div>
              <label class="bs-co-toggle-row">
                <span class="bs-toggle"><input type="checkbox" name="subscribe" data-co-toggle></span>
                <span>Хочу отримувати новини Booster Shop</span>
              </label>
            {% endif %}
          </div>
        </div>
      </section>

      {# ============ 3. Оплата ============ #}
      <section class="bs-card bs-co-card" data-co-card="payment">
        <div class="bs-co-card__head">{{ icon_pay|raw }}<h3>Оплата</h3></div>
        {% for method in payment_methods %}
          <label class="bs-radio-row{% if method.selected %} is-active{% endif %}">
            <input type="radio" name="payment_method" value="{{ method.code }}" {% if method.selected %}checked{% endif %}>
            <div><div class="bs-radio-row__label">{{ method.title }}</div>
              {% if method.sub %}<div class="bs-radio-row__sub">{{ method.sub }}</div>{% endif %}</div>
          </label>
        {% endfor %}
      </section>

    </div>

    {# ============ 4. Замовлення (sticky desktop / collapsible mobile) ============ #}
    <aside class="bs-co-aside">
      <section class="bs-card bs-co-card bs-co-summary" data-co-card="summary">
        <button type="button" class="bs-co-summary__toggle" data-co-summary-toggle>
          {{ icon_receipt|raw }}
          <span class="bs-co-flex1">Замовлення · {{ cart_qty }} товари</span>
          <span class="bs-co-summary__total" data-co-payable>₴{{ payable_total }}</span>
          <svg class="bs-co-chevron" data-co-chevron>...</svg>
        </button>

        <div class="bs-co-summary__body" data-co-summary-body>
          <div class="bs-co-items{% if cart_items|length > 3 %} bs-co-items--scroll{% endif %}">
            {% for item in cart_items %}
              <div class="bs-co-item">
                <img src="{{ item.thumb }}" alt="">
                <div class="bs-co-item__title">{{ item.name }}</div>
                <div class="bs-co-item__qty">× {{ item.qty }}</div>
                <div class="bs-co-item__price">₴{{ item.total }}</div>
              </div>
            {% endfor %}
          </div>

          {# ---- Доставка + free-shipping progress, ONE merged block ---- #}
          <div class="bs-co-shipblock{% if free_shipping %} is-free{% endif %}">
            <div class="bs-co-shipblock__row">
              <span>Доставка</span>
              <span class="bs-co-shipblock__price">{{ free_shipping ? 'Безкоштовно' : ('₴' ~ shipping_price) }}</span>
            </div>
            <div class="bs-co-shipblock__msg">
              {{ free_shipping ? 'Безкоштовна доставка застосована ✓' : ('До безкоштовної доставки лишилось ₴' ~ free_shipping_remaining) }}
            </div>
            <div class="bs-co-shipblock__track"><i style="width:{{ free_shipping_pct }}%"></i></div>
          </div>

          <div class="bs-co-totals">
            <div><span>Сума товарів</span><span>₴{{ items_subtotal }}</span></div>
            {% if promo_code %}
              <div class="bs-co-totals__discount"><span>Знижка ({{ promo_code }} −{{ promo_pct }}%)</span><span>−₴{{ promo_discount }}</span></div>
            {% endif %}
            <div class="bs-co-totals__grand"><span>До сплати</span><span>₴{{ payable_total }}</span></div>
          </div>
          {# `payable_total` = items_subtotal − promo_discount. Shipping is NEVER
             added into this number — it's tracked only in bs-co-shipblock above. #}

          <div class="bs-field">
            <label>Промокод</label>
            {% if promo_code %}
              <div class="bs-co-promo-applied">
                <span class="bs-co-promo-chip">{{ promo_code }} · −{{ promo_pct }}%</span>
                <span class="bs-co-flex1">Промокод застосовано</span>
                <button type="button" class="bs-co-link bs-co-link--muted" data-co-promo-remove>Прибрати</button>
              </div>
            {% else %}
              <div class="bs-co-promo-input">
                <input class="bs-input" name="coupon" placeholder="Введіть промокод">
                <button type="button" class="bs-btn bs-btn-secondary" data-co-apply-coupon>Застосувати</button>
              </div>
            {% endif %}
          </div>

          <div class="bs-field"><label>Коментар до замовлення</label>
            <textarea class="bs-input" name="comment" rows="2" placeholder="Наприклад: бустери для подарунка, упакуйте, будь ласка.">{{ comment }}</textarea></div>

          <label class="bs-co-agree">
            <input type="checkbox" name="agree" required data-co-agree>
            <span>Погоджуюсь з умовами <a href="{{ offer_url }}">Публічної оферти</a>, включно з положеннями про обробку персональних даних.</span>
          </label>

          {% if not logged %}
            <label class="bs-co-toggle-row">
              <span class="bs-toggle"><input type="checkbox" name="save_account" checked data-co-toggle></span>
              <span>
                <strong>Зберегти дані та зареєструватись</strong>
                <small>Створимо обліковий запис і надішлемо одноразове посилання для встановлення пароля.</small>
              </span>
            </label>
          {% endif %}

          <div class="bs-co-field-error" data-co-agree-error hidden>Погодьтесь з умовами, щоб продовжити</div>

          <button type="submit" class="bs-btn bs-btn-primary bs-co-submit" data-co-submit disabled>
            Підтвердити замовлення →
          </button>
        </div>
      </section>
    </aside>
  </div>
</div>
```

**Controller/data contract — what must be passed in (beyond what already
exists today):**

- `has_saved_address` (bool) — drives which Доставка mode renders by default
  AND (on mobile) whether Отримувач/Доставка start collapsed.
- `shipping_price` — real number from the carrier API (**already wired**;
  just render it instead of a static label).
- `free_shipping_threshold` — pull from store config, **not hardcoded**. The
  reviewer is changing the live value from ₴1500 → ₴2000 separately in the
  backend; the template must read whatever that config returns, never a
  literal number.
- `free_shipping_remaining` = `max(0, threshold − payable_total)`,
  `free_shipping_pct` = `min(100, round(payable_total / threshold * 100))`.
  **Both computed off `payable_total` (post-promo-discount), not the raw
  items subtotal.**
- `promo_code` / `promo_pct` / `promo_discount` — from the existing coupon
  endpoint; only the presentation (chip vs input) is new.
- `payable_total` = `items_subtotal − promo_discount`. **Never add shipping
  into this number** — shipping is shown only in `bs-co-shipblock`.
- `captcha_widget` — render whatever the current captcha module already
  outputs (screenshot shows a reCAPTCHA-style checkbox) inside the marked
  slot; do not rebuild the captcha itself, only its position/spacing.

## 5. CSS (append to `boostershop-ds.css`)

```css
.bs-co-mb20   { margin-bottom: 20px; }
.bs-co-grid   { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: flex-start; }
.bs-co-col    { display: flex; flex-direction: column; gap: 18px; }
.bs-co-aside  { position: sticky; top: 16px; }
.bs-co-card   { padding: 22px; }
.bs-co-card__head { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.bs-co-card__head svg { width: 30px; height: 30px; padding: 7px; box-sizing: border-box; border-radius: var(--bs-r-sm); background: var(--bs-bg); color: var(--bs-ink-2); flex: 0 0 auto; }
.bs-co-card__head h3 { flex: 1; margin: 0; font-size: 16px; }
.bs-co-flex1  { flex: 1; }
.bs-co-row2   { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.bs-co-hint   { font-weight: 400; color: var(--bs-ink-3); margin-left: 6px; }

.bs-co-link { background: transparent; border: 0; padding: 0; color: var(--bs-blue); font-size: 13px; font-weight: 600; cursor: pointer; font: inherit; }
.bs-co-link--muted { color: var(--bs-ink-3); }

.bs-input--error { border-color: var(--bs-danger) !important; background: #FEF4F3; }
.bs-co-field-error { display: flex; align-items: center; gap: 5px; margin-top: 6px; font-size: 12px; color: var(--bs-danger); font-weight: 500; }

/* Toggle switch (newsletter / save-account) — distinct from the agreement
   checkbox, which stays a plain checkbox. */
.bs-toggle { display: inline-flex; align-items: center; flex: 0 0 auto; }
.bs-toggle input { appearance: none; width: 38px; height: 21px; border-radius: 11px; background: var(--bs-line); position: relative; cursor: pointer; transition: background .15s; margin: 0; }
.bs-toggle input:checked { background: var(--bs-blue); }
.bs-toggle input::before { content: ''; position: absolute; top: 2px; left: 2px; width: 17px; height: 17px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.25); transition: transform .15s; }
.bs-toggle input:checked::before { transform: translateX(17px); }
.bs-co-toggle-row { display: flex; align-items: flex-start; gap: 12px; cursor: pointer; font-size: 13px; color: var(--bs-ink-2); }
.bs-co-toggle-row strong { display: block; font-weight: 600; color: var(--bs-ink); font-size: 13px; }
.bs-co-toggle-row small { display: block; color: var(--bs-ink-3); font-size: 11.5px; margin-top: 3px; line-height: 1.5; }

/* Captcha slot — just spacing; the widget itself is the existing module's output. */
.bs-field:has(> [data-co-captcha]) { margin-bottom: 4px; }

.bs-co-items { display: flex; flex-direction: column; gap: 12px; }
.bs-co-items--scroll { max-height: 268px; overflow-y: auto; padding-right: 4px; }
.bs-co-item   { display: grid; grid-template-columns: 48px 1fr auto; gap: 12px; align-items: center; }
.bs-co-item img { width: 48px; height: 48px; border-radius: var(--bs-r-sm); border: 1px solid var(--bs-line); object-fit: contain; }
.bs-co-item__title { font-size: 13px; font-weight: 600; color: var(--bs-ink); line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bs-co-item__qty   { font-size: 11px; color: var(--bs-ink-3); }
.bs-co-item__price { font-size: 13.5px; font-weight: 700; }

/* Shipping + free-shipping progress — ONE merged, visually distinct block. */
.bs-co-shipblock { padding: 12px 14px; border-radius: var(--bs-r-sm); background: var(--bs-blue-soft); }
.bs-co-shipblock.is-free { background: #EAF7EE; }
.bs-co-shipblock__row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 9px; }
.bs-co-shipblock__row span:first-child { font-size: 13px; font-weight: 600; color: var(--bs-ink-2); }
.bs-co-shipblock__price { font-size: 14.5px; font-weight: 800; color: var(--bs-ink); }
.bs-co-shipblock.is-free .bs-co-shipblock__price { color: var(--bs-green); }
.bs-co-shipblock__msg { font-size: 12px; font-weight: 600; color: var(--bs-blue); margin-bottom: 8px; }
.bs-co-shipblock.is-free .bs-co-shipblock__msg { color: var(--bs-green); }
.bs-co-shipblock__track { height: 5px; background: #fff; border-radius: 999px; overflow: hidden; }
.bs-co-shipblock__track i { display: block; height: 100%; background: var(--bs-blue); border-radius: 999px; }
.bs-co-shipblock.is-free .bs-co-shipblock__track i { background: var(--bs-green); }

.bs-co-totals > div { display: flex; justify-content: space-between; font-size: 13.5px; color: var(--bs-ink-2); padding: 3px 0; }
.bs-co-totals__discount { color: var(--bs-green) !important; font-weight: 600; }
.bs-co-totals__grand { border-top: 1px solid var(--bs-line); margin-top: 6px; padding-top: 10px !important; font-size: 18px !important; font-weight: 800; color: var(--bs-ink); }

.bs-co-promo-input   { display: flex; gap: 8px; }
.bs-co-promo-applied { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--bs-r-sm); background: #EAF7EE; border: 1px solid #BBE8CB; }
.bs-co-promo-chip     { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 700; color: var(--bs-green); background: #fff; border: 1px solid #BBE8CB; border-radius: 999px; padding: 4px 10px; }

.bs-co-agree  { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; font-size: 12px; color: var(--bs-ink-2); line-height: 1.55; }
.bs-co-submit { width: 100%; padding: 15px; font-size: 15px; margin-top: 4px; opacity: .5; cursor: not-allowed; }
.bs-co-submit:not([disabled]) { opacity: 1; cursor: pointer; }

/* Mobile summary toggle (Замовлення) */
.bs-co-summary__toggle { display: none; width: 100%; align-items: center; gap: 10px; padding: 15px 16px; background: transparent; border: 0; cursor: pointer; font: inherit; text-align: left; }
.bs-co-summary__total { font-size: 15px; font-weight: 800; color: var(--bs-ink); }
.bs-co-chevron { color: var(--bs-ink-3); transition: transform .2s; flex: 0 0 auto; }
.bs-co-summary.is-open .bs-co-chevron { transform: rotate(180deg); }

/* Mobile collapsible (Отримувач / Доставка) — same visual language as the
   order-summary toggle but with a one-line summary instead of a total. */
.bs-co-card[data-co-collapsible] .bs-co-card__head { cursor: pointer; }
.bs-co-card__summary { display: none; font-size: 12px; color: var(--bs-ink-3); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bs-co-card.is-collapsed .bs-co-card__summary { display: block; }
.bs-co-card.is-collapsed .bs-co-card__body { display: none; }

@media (max-width: 900px) {
  .bs-co-grid { grid-template-columns: 1fr; }
  .bs-co-aside { position: static; }
  .bs-co-summary__toggle { display: flex; }
  .bs-co-summary .bs-co-card__head { display: none; }
  .bs-co-summary__body { display: none; padding: 2px 16px 18px; border-top: 1px solid var(--bs-line); }
  .bs-co-summary.is-open .bs-co-summary__body { display: block; }
  .bs-co-row2 { grid-template-columns: 1fr; }
}
```

## 6. JS (vanilla — `checkout-reskin.js`)

```js
// ---- Mobile collapsible: Замовлення / Отримувач / Доставка ----
document.querySelectorAll('[data-co-summary-toggle]').forEach(btn => {
  btn.addEventListener('click', () => btn.closest('[data-co-card]').classList.toggle('is-open'));
});
document.querySelectorAll('.bs-co-card[data-co-collapsible] .bs-co-card__head').forEach(head => {
  head.addEventListener('click', () => head.closest('.bs-co-card').classList.toggle('is-collapsed'));
});

// On mobile load: collapse Отримувач/Доставка ONLY if the server rendered
// them with saved data (data-has-data="1" on the card) AND viewport ≤900px.
// If either field group is empty, leave expanded — don't hide required work.
if (window.matchMedia('(max-width: 900px)').matches) {
  document.querySelectorAll('.bs-co-card[data-co-collapsible][data-has-data="1"]').forEach(card => {
    card.classList.add('is-collapsed');
  });
}

// ---- Доставка: saved ⇄ manual address mode ----
document.querySelectorAll('[data-co-address-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const wrap = btn.closest('[data-co-address-mode]');
    const target = btn.dataset.coAddressToggle; // 'manual' | 'saved'
    wrap.dataset.coAddressMode = target;
    wrap.querySelector('.bs-co-mode-saved').hidden = target !== 'saved';
    wrap.querySelector('.bs-co-mode-manual').hidden = target !== 'manual';
  });
});

// ---- Address cascade (Область → Місто → Тип доставки → Відділення) ----
// Re-skin ONLY — wire these three listeners to whatever endpoint the current
// production cascade already calls (see live checkout screenshot); do not
// invent new endpoints.
document.querySelectorAll('[data-co-region]').forEach(el => {
  el.addEventListener('input', () => {
    const city = el.closest('.bs-co-row2').querySelector('[data-co-city]');
    // existing region→city lookup call here; on result:
    city.disabled = false;
  });
});
document.querySelectorAll('[data-co-city]').forEach(el => {
  el.addEventListener('change', () => {
    const branch = el.closest('.bs-co-mode-manual').querySelector('[data-co-branch]');
    // existing city→branch/postomat lookup call here; on result:
    branch.disabled = false;
  });
});

// ---- Promo apply/remove — hits the EXISTING coupon endpoint; this just
// swaps which markup renders (input+button ⇄ applied chip) on response. ----
document.querySelectorAll('[data-co-apply-coupon]').forEach(btn => {
  btn.addEventListener('click', () => {
    // existing coupon-apply AJAX call; on success, re-render the promo field
    // partial as the applied-chip variant and refresh totals from the
    // response (do not recompute discount client-side).
  });
});
document.querySelectorAll('[data-co-promo-remove]').forEach(btn => {
  btn.addEventListener('click', () => {
    // existing coupon-remove AJAX call; on success, re-render as the empty
    // input+button variant and refresh totals.
  });
});

// ---- Agreement-gated submit ----
const agree = document.querySelector('[data-co-agree]');
const submit = document.querySelector('[data-co-submit]');
const agreeErr = document.querySelector('[data-co-agree-error]');
function syncSubmit() { if (submit) submit.disabled = !(agree && agree.checked); }
if (agree) { agree.addEventListener('change', () => { syncSubmit(); if (agree.checked) agreeErr.hidden = true; }); syncSubmit(); }
if (submit) {
  submit.addEventListener('click', (e) => {
    if (agree && !agree.checked) { e.preventDefault(); agreeErr.hidden = false; agree.closest('label').scrollIntoView({ block: 'center' }); }
  });
}
```

This JS is presentational gating only — **server-side validation for every
field (agreement included) must still run exactly as it does today.**

## 7. Mandatory smoke test (before deploy — do not skip)

Run the **full real cycle** in staging for every payment method before this
goes live:

- [ ] Card / Google Pay / Apple Pay → real (sandbox) charge → success page.
- [ ] Оплата при отриманні (COD) → order created, correct status.
- [ ] IBAN реквізити → order created, correct instructions shown.
- [ ] Fiscalization receipt (Checkbox) fires exactly as before restyle.
- [ ] Hutko integration unaffected — network payload byte-identical to pre-restyle.
- [ ] Shipping price matches the carrier API response exactly; free-shipping
      threshold reads from config (test both just-under and just-over).
- [ ] Promo apply → discount reflected in Знижка row and До сплати; remove →
      reverts cleanly; До сплати never includes shipping.
- [ ] Guest: captcha blocks submission until solved (existing behaviour,
      confirm it still fires from the new position).
  - [ ] Guest + "Зберегти дані та зареєструватись" ON → account created,
        magic-link email sent, order still completes.
  - [ ] Guest + toggle OFF → order completes, no account created.
- [ ] Authorized + saved address → "+ Інша адреса" → manual entry → order
      uses the NEW address, not the saved one.
- [ ] Mobile: collapse/expand Отримувач & Доставка does not lose entered
      values.
- [ ] Mobile: mismatch check — a returning customer whose account is MISSING
      phone (or any required Отримувач field) loads with that card expanded,
      not collapsed (never hide a gap the customer must fill).
- [ ] Server-side validation still blocks submission even if the client JS
      says "looks fine."

## 8. QA checklist (visual)

- [ ] Exactly one primary-green button on the page (final submit).
- [ ] Auth nudge is blue-soft, never the old red banner.
- [ ] Desktop ≥900px: Замовлення sticky, no toggle chevron shown.
- [ ] ≤900px: Замовлення/Отримувач/Доставка show toggle + chevron; Оплата and
      the promo/comment/agree/CTA tail never collapse.
- [ ] Shipping price + free-shipping progress render as ONE box (not two
      separate elements) — green background + ✓ once free, blue otherwise.
- [ ] "До сплати" = items minus promo discount, **never** + shipping.
- [ ] Item list past 3 rows scrolls internally — card height doesn't grow.
- [ ] Agreement checkbox starts unchecked; CTA visibly dimmed until checked;
      no red error text until a submit was attempted.
- [ ] No "вміст бустерів випадковий…" text anywhere on this page.
- [ ] Captcha + newsletter + "Зберегти дані та зареєструватись" show for
      guest only — never for an authenticated session.
- [ ] All radii/spacing pull from `--bs-r*`/`--bs-s*` tokens.

## 9. Reference files

| File | Purpose |
|---|---|
| `RD-13 Checkout reskin.html` | Canvas — 6 artboards: desktop default/errors/guest/promo-applied, mobile collapsed/editing/guest. Open alongside this doc. |
| `rd13-checkout.jsx` | React reference — exact source of truth for spacing, copy, and conditional logic. |
| `audit.md` | Original UX-014 rationale for the 4-card breakdown. |
| `HANDOFF.md` §5.7 | `.bs-radio-row` / `.bs-auth-nudge` base styles — reused here unchanged. |
