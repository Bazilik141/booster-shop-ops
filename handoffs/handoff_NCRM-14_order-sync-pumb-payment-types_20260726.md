# Codex Handoff — NCRM-14: ПУМБ ПЧ payment types + order-sync мапінг + discount_total fix

Date: 2026-07-26 | Owner: Raccoon | Планувальник: Claude | Виконавець: Codex
Пов'язаний план: `plans/NCRM-14_order-sync-payment-type-capture_plan_20260726.md`

> HIGH-RISK: торкається живого order-sync (той самий шлях, що впав мовчки 2026-07-19).
> Payment-суміжне → див. `bs-checkout-smoke`. Schema/reference-дані → див. `bs-merchant-schema-qa` (тут — лише внутрішній Supabase-довідник, не Merchant feed).
> 2026-07-26: приєднано другий, незалежний баг того самого файлу — `discount_total` завжди 0. Різні причини, той самий файл і той самий деплой/смоук — тому один хендофф, не два.

## 1. Task ID
NCRM-14 — (a) додати типи оплати «ПУМБ Сплата частинами» 3/4/5 у нову CRM і навчити OpenCart→Supabase sync (`order-sync/index.ts`) коректно їх розпізнавати при створенні замовлення; (b) виправити `sales.discount_total`, який зараз завжди 0 для кожного замовлення. Скоуп статусів мінімальний: `payment_status`/`order_status` не чіпати.

## 2. Context
Новий чекаут живий для всіх; MONO ПЧ готове, ПУМБ ПЧ у роботі (PAY-002, договір №SF1/21.3.2/4510 підписано 2026-07-21). Зараз `paymentTypeCode()` в `index.ts` (рядки ~118-146) мапить MONO ПЧ (`mono_chast.mono_chast_[345]` → `credit_mono_[345]`), `bank_details`, `fop_control`, а **все інше повертає `acquiring`** — тобто ПУМБ, коли з'явиться, потрапить у купу з Hutko-карткою. MONO-типи+комісії вже є в міграції `0013_pay001_mono_payment_types.sql`. Комісії ПУМБ відомі з Додатку №2 договору. Бізнес-рішення закриті: обидва провайдери лише 3/4/5 платежів, лише «Сплата частинами».

Окремо (виявлено при review CHECKOUT-002, `diagnostics/NCRM-10_discount-total-not-mapped_note_20260719.md`): кожен рядок `sales`, який створює цей самий pipeline, має `discount_total=0.00`, незалежно від реальної знижки замовлення. `system/library/booster_crm_sync.php` вже рахує правильну знижку і шле її як `payload.discount_total`. `index.ts` цей ключ ніколи не читає — натомість рахує `discountTotal(validated.totals)` над полем `payload.totals`, якого PHP-міст не шле взагалі → `validated.totals=[]` → завжди 0. Підтверджено на живих рядках OC-FOP-0256–0259.

## 3. Goal
1. У Supabase з'являються 3 нові `payment_types`: `credit_pumb_3/4/5` з коректними комісіями через `app_config`, за тим самим патерном, що `0013` для MONO.
2. `paymentTypeCode()` повертає `credit_pumb_[345]` для ПУМБ-замовлення і не ламає жоден існуючий мапінг.
3. Реальне замовлення кожного типу оплати дає правильний рядок `payment_type` у `sales` (або чіткий лог-фейл, ніколи не мовчазний).
4. Нове замовлення з реальною знижкою (купон/бонус/ваучер) дає в `sales.discount_total` те саме число, що вже правильно рахує PHP — не 0.

## 4. What to change
**Фаза 1 — нова міграція** `ncrm/supabase/migrations/0014_ncrm11_pumb_payment_types.sql` (за зразком `0013`, ідемпотентна `on conflict ... do update`):
- `app_config`: `credit_pumb_3_fee_pct=0.030`, `credit_pumb_4_fee_pct=0.045`, `credit_pumb_5_fee_pct=0.058`, unit `ratio`, `effective_from date '2026-07-26'`, `is_active=true`, description укр. («ПУМБ Сплата частинами: N платежів, комісія магазину»).
- `payment_types`: `credit_pumb_3` («Сплата частинами ПУМБ — 3 платежі»), `_4` («…4 платежі»), `_5` («…5 платежів»), кожен з відповідним `fee_pct_config_key`, `fee_fixed_config_key=null`, `fee_min_config_key=null`, `is_active=true`.

**Фаза 2 — мапінг** у `ncrm/supabase/functions/order-sync/index.ts`, функція `paymentTypeCode()`: додати гілку ПУМБ **перед** фінальним `return "acquiring"` і бажано поруч з mono-гілкою:
- Очікуваний патерн коду ПУМБ-модуля (за аналогією з `mono_chast.mono_chast_3`): щось на кшталт `pumb_credit.pumb_credit_[345]`. Гілка має витягти цифру платежів і повернути `credit_pumb_${n}`.
- ⚠️ **MUST-VERIFY:** реальний рядок `payment_method_code`/`payment_method_name`, який шле ПУМБ-extension, ще НЕ підтверджено (extension `pumb_credit` з'явиться в PAY-002). Codex має зробити регекс стійким (варіанти `pumb`+`chast`/`part`/`_3`) АЛЕ лишити явний `// TODO NCRM-14: verify against real pumb_credit payment_method_code from PAY-002` і не видаляти fallback `acquiring`.
- Комбінований саніті-матч, наприклад: якщо `value` містить `pumb` і одну з цифр 3/4/5 у контексті платежів — мапити; інакше не чіпати наявну логіку.

Не писати фінальний код у цьому хендоффі — Codex звіряє з реальним `index.ts` і `0013`.

**Фаза 2b — discount_total** у тому ж `index.ts`, місце, де будується рядок `sales` (шукати `discountTotal(` і `validated.totals`):
- Читати знижку з `payload.discount_total` (число, вже порахований PHP), а не з `discountTotal(validated.totals)`.
- Якщо `payload.discount_total` відсутнє/не число — fallback на існуючу `discountTotal(validated.totals)` логіку (не падати, не мовчати; лог-попередження прийнятне).
- Не чіпати `validated.totals`/`discountTotal()` саму функцію — вона лишається як fallback, не видаляти.
- Ретроактивно 4 вже вставлені рядки (OC-FOP-0256–0259) НЕ виправляти — окреме рішення власника.

## 5. Do not touch
- `0013_pay001_mono_payment_types.sql` — не редагувати, лише додати нову 0014.
- Round-4 логіка `order_id`/`unit_price`, `isTestOrder()`, таймаути `postJson()` — не чіпати.
- `payment_status`/`order_status` мапінг (лишаються `unpaid`/`new`).
- Hutko-гілка/`acquiring` fallback — лишити як є.
- Protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout/payment/fiscalization UI, Merchant feed, schema, `booster_async_queue*.php`, cron.

## 6. Likely files / areas
- `ncrm/supabase/migrations/0014_ncrm11_pumb_payment_types.sql` (новий — likely, звірити нумерацію: остання є 0013).
- `ncrm/supabase/functions/order-sync/index.ts` → `paymentTypeCode()` (~рядки 118-146).
- Зразок для копіювання: `ncrm/supabase/migrations/0013_pay001_mono_payment_types.sql`.
Codex should verify against actual project files before writing.

## 7. Acceptance criteria
- [ ] `select code, name_uk from payment_types where code like 'credit_pumb_%'` → рівно 3 рядки з правильними назвами.
- [ ] `select key, value_num from app_config where key like 'credit_pumb_%_fee_pct'` → 0.030 / 0.045 / 0.058.
- [ ] Для payload з ПУМБ-кодом `paymentTypeCode()` повертає `credit_pumb_3|4|5` (юніт-фікстура з очікуваним рядком).
- [ ] Регресія: payload MONO ПЧ → `credit_mono_[345]`; реквізити → `bank_details`; післяплата → `fop_control`; Hutko → `acquiring` (незмінно).
- [ ] Для payload з `discount_total>0` рядок `sales.discount_total` дорівнює цьому числу (не 0).
- [ ] Регресія: payload без `discount_total` (або з `totals`) поводиться як раніше через fallback, без падіння.
- [ ] `git diff` лише у файлах §6, без дрейфу в protected zones, без секретів.
- [ ] У коді лишено TODO про верифікацію реального ПУМБ-коду.

## 8. QA / smoke test (owner) — див. `bs-checkout-smoke`
- [ ] Застосувати міграцію 0014 (безпечно, лише довідкові дані), перевірити 3+3 рядки (критерії §7).
- [ ] Редеплой Edge Function; **бекап попередньої версії перед деплоєм**.
- [ ] Поставити одне реальне замовлення MONO ПЧ і одне «за реквізитами» → переконатись, що sync живий і типи правильні (регресія не зламана).
- [ ] Поставити одне реальне замовлення з ненульовою знижкою (купон/бонус) → `sales.discount_total` у Supabase збігається з реальною знижкою замовлення.
- [ ] ПУМБ-гілка: вважати НЕперевіреною до першого реального ПУМБ-замовлення (залежить від PAY-002). Коли пройде перше ПУМБ-замовлення — звірити реальний `payment_method_code` з регексом і зняти TODO.
- [ ] Перевірити cron-воркер: `booster-async-order-sync.log` — `delivered` росте, `retry` ≈ 0.

## 9. Rollback note
- Edge Function: відновити з бекапу попередньої версії `index.ts` (зберегти перед деплоєм) + редеплой.
- Міграція 0014: безпечна (лише insert довідкових рядків); за потреби `delete from payment_types where code like 'credit_pumb_%'` та відповідні `app_config`-ключі. БД-даних замовлень не торкається.
- discount_total fix: чистий revert файлу `index.ts` до бекапної версії відкочує й цю зміну; БД не чіпається.

## 10. Recommended status after execution
NCRM-14 → «In progress»: Фаза 1 (міграція) + Фаза 2 (ПУМБ-мапінг) + Фаза 2b (discount_total) можна закрити після регрес-smoke MONO/реквізити/знижка; **повне закриття NCRM-14 — лише після верифікації ПУМБ-гілки на реальному замовленні** (разом з релізом PAY-002). PAY-002 — окрема відкрита задача.
