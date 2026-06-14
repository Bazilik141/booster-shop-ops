# ST-2b.1 — Checkout/Payment Smoke Test

**Patch:** `st2b1_defer_confirm_draft_orders_20260614.php` · **Date:** 2026-06-14 · **Risk:** HIGH (checkout/Hutko/fiscalization)

## Preconditions
- Патч задеплоєний: `done=ok`, template/cache cleared.
- Свіжий бекап підтверджено (rollback: `_patch_backups/st2b1-*` + clear cache).
- **Hutko = SANDBOX**, Checkbox = sandbox/staging. **Реальні карткові платежі НЕ запускати.**
- **Baseline B0:** зафіксувати поточний `MAX(order_id)` / count (CRM `action=orders` або admin) **до** тестів. Усі перевірки count — проти B0.

## Матриця (для кроків 5–8)
`{guest, logged-in} × {відділення, поштомат} × {Hutko, COD}` = 8 комбо.
Мінімум: 1 реальне тестове замовлення на кожен payment-метод × тип юзера.

## 11 кроків

| # | Test | Кроки | Expected | Actual | Pass/Fail |
|---|------|-------|----------|--------|-----------|
| 1 | Register → Checkout | Новий юзер, +1 in-stock товар, перейти в checkout | Stock OC4 route рендериться; NP-адреса/контакти є; `#checkout-confirm` **порожній** (order ще нема). Зафіксувати B0 | | |
| 2 | Auto First15 | Перше замовлення нового юзера | First15 авто-застосовано, total зменшено (untouched патчем — regression) | | |
| 3 | Manual First15 reuse | Той самий юзер повторно вводить First15 | Reuse заблоковано, чітке повідомлення, без дублів | | |
| 4 | Invalid coupon | Ввести невалідний код | Один чіткий error; checkout не ламається; placeholder-панель ціла | | |
| 5 | Nova Poshta data | Місто + відділення; потім місто + поштомат | Live NP-дані вибираються; required-валідація; лейбл коректний (відділення vs поштомат); без дубль-полів | | |
| 6 | **Order button + ST-2b.1 defer (core)** | Вибрати доставку + оплату; перевибрати оплату 3× | На вибір оплати → placeholder «Замовлення ще не створено»; **у Network НЕМА `confirm.confirm`**; `oc_order` count == **B0** (не росте навіть після 3 перевиборів). Кнопка «Оформити» в placeholder активна лише коли оплату обрано (інакше warning) | | |
| 7 | **Hutko return / single order (core)** | Клік «Оформити» | `confirm.confirm` спрацьовує **рівно раз** → `oc_order` == **B0+1**; Hutko → редірект у sandbox, amount = total − shipping (з купоном); return → сесія жива, статус paid/pending коректний. COD-варіант: order створено, статус ок. **Double-click «Оформити» → все одно +1** (guard) | | |
| 8 | Success redirect | Після повернення Hutko / COD | Лендинг на success URL з коректним `order_id`; success-сторінка рендериться (гілка First15 якщо є) | | |
| 9 | Clean JSON | `confirm.confirm`, `payment_method` save (comment), shipping/payment AJAX | Валідний JSON/HTML-фрагмент, **без PHP warnings/notices**. Коментар зберігається до створення order → немає `error_order` | | |
| 10 | Email delivery | Підтвердження на тестовий inbox | Лист дійшов, лінки робочі; якщо вводився коментар — він є в order | | |
| 11 | Checkbox / fiscalization | Hutko sandbox order | Чек у Checkbox sandbox; сума **без доставки** (`shipping_include=0`); коректний | | |

## Критичний фокус (де найбільший ризик регресії)
- **Крок 6:** count проти B0 — головна acceptance ST-2b.1 (чернетки усунено).
- **Крок 7:** після створення order патч тригерить `#checkout-confirm #button-confirm`. Якщо id/хендлер у live інші — **order створиться, але оплата не стартує** (unpaid-зависання). Перевірити РЕАЛЬНИЙ редірект Hutko І COD success обовʼязково.
- st2a.4 void-фільтр: нових «Чернетка (системний)» має бути ~0; переконатися, що фільтр досі sane.

## Final summary
- Pass: ___ / 11 · Blockers: ___
- Recommended status: `Готово` лише якщо 6+7+11 PASS на всій матриці; інакше `Повернути в роботу`.
- Sandbox/staging notes: Hutko sandbox, Checkbox sandbox; жодних реальних платежів.
