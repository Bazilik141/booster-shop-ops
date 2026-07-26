# Codex Handoff — NCRM-11: автоматичний фетч курсів валют

Date: 2026-07-26 | Owner: Raccoon | Планувальник: Claude | Виконавець: Codex
Пов'язаний план: `plans/NCRM-11_currency-rates-fetch_plan_20260726.md`

> MEDIUM-RISK: нова зовнішня залежність (3 публічні API банків) + перший scheduled job у цьому Supabase-проєкті. Не чіпає checkout/order-sync/CRM UI.

## 1. Task ID
NCRM-11 — щоденний автоматичний фетч курсів USD/EUR/JPY у таблицю `currency_rates`. Джерела і буфер уже задокументовані в `plans/crm-schema-v1_2026-06-26.md` §D3b/§D3c; власник підтвердив 2026-07-26: без ПУМБ, автоматичний cron (не ручне введення).

## 2. Context
`currency_rates` (міграція `0001_stage1_core.sql`) існує, порожня, `unique(currency, as_of)`, CHECK на `currency ~ '^[A-Z]{3}$'` і `rate_to_uah > 0`. `app_config` ще не має ключа `nbu_rate_buffer_pct` (задокументований у схемі як `0.01`, ще не вставлений жодною міграцією). У проєкті поки немає жодного scheduled job/cron — pattern треба ввести вперше (не плутати з окремим PHP-cron воркером на хостингу OpenCart із CHECKOUT-002/NCRM-10, це інша інфраструктура — Supabase).

## 3. Goal
1. `app_config` отримує рядок `nbu_rate_buffer_pct=0.01`.
2. Нова Edge Function фетчить USD/EUR (пріоритет ПриватБанк → Monobank → НБУ) і JPY (завжди НБУ), пише по одному рядку на валюту на день у `currency_rates`.
3. Коли джерело = НБУ → `rate_to_uah = nbu_rate × (1 + nbu_rate_buffer_pct)`. Коли джерело = Приват/Моно → без буфера.
4. Функція викликається сама, раз на добу, без ручного тригера (після підтвердженого циклу).

## 4. What to change

**Фаза 1 — міграція** `ncrm/supabase/migrations/0015_ncrm11_currency_rate_buffer_config.sql` (за зразком `0013`/`0014`, ідемпотентна `on conflict (key, effective_from) do update`):
- `app_config`: `nbu_rate_buffer_pct=0.01`, unit `ratio`, `effective_from` = дата деплою, `is_active=true`, опис укр. («Буфер +1% до курсу НБУ, коли банківське API недоступне»).

**Фаза 2 — нова Edge Function** `ncrm/supabase/functions/currency-rates-fetch/index.ts` (новий каталог, за зразком структури `order-sync`):
- Для кожної з `USD`, `EUR`: спробувати ПриватБанк (`api.privatbank.ua/p24api/pubinfo?json&exchange&coursid=5` або ідентичний публічний ендпоінт курсів) → якщо недоступний/поле не парситься → Monobank (`api.monobank.ua/bank/currency`, **цифрові ISO 4217 коди**, мапінг `840→USD, 978→EUR, 392→JPY`) → якщо і це недоступне → НБУ (`bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json&valcode=USD`).
- Для `JPY`: завжди НБУ, той самий ендпоінт з `valcode=JPY`.
- ⚠️ **MUST-VERIFY:** точні назви полів відповіді (buy/sale/rate) кожного API — Claude їх з цієї сесії живим викликом не перевіряв. Codex звіряє на реальному виклику перед фіналом коду, не вгадує з пам'яті.
- НБУ-гілка → `rate_to_uah = nbu_rate * (1 + app_config['nbu_rate_buffer_pct'])`. Приват/Моно-гілка → `rate_to_uah = rate` як є.
- `source` пишеться реальним джерелом: `'ПриватБанк'` / `'Monobank'` / `'НБУ'` (точні рядки як у `plans/crm-schema-v1_2026-06-26.md` §D3c, не вигадувати нові).
- Insert в `currency_rates` (`currency, rate_to_uah, as_of=сьогодні, source, note=null`), `on conflict (currency, as_of) do update` — повторний запуск того самого дня оновлює, не дублює й не падає.
- Одне джерело/валюта впала → `try/catch` навколо кожної валюти окремо, `console.error` з деталями (без секретів — тут секретів і нема, публічні API), інші валюти продовжують писатись. Ніколи не інсертити `rate_to_uah <= 0` або `NaN`.

**Фаза 3 — scheduled trigger:** налаштувати щоденний виклик Edge Function (`pg_cron` + `pg_net`, або Supabase Dashboard Cron Jobs — обрати ідіоматичний спосіб для поточної версії Supabase CLI в проєкті). Задокументувати обраний спосіб і команди в звіті — це перший scheduled job тут, немає з чим звіряти в репо.

## 5. Do not touch
- Форми закупки (`sale.repo.ts`/`purchase`-логіка NCRM-09b) — не читають і не повинні починати читати `currency_rates` в цьому раунді.
- `order-sync/index.ts`, NCRM-14 (ПУМБ/discount_total) — інший файл, не чіпати.
- Наявні `payment_types`/`app_config`-ключі з `0013`/`0014` — не редагувати, лише додати нову `0015`.
- Protected zones: sitemap/robots/canonical, checkout/payment/fiscalization UI, Merchant feed, schema, `booster_async_queue*.php`/cPanel cron (це інша інфраструктура, PHP-хостинг, не Supabase).

## 6. Likely files / areas
- `ncrm/supabase/migrations/0015_ncrm11_currency_rate_buffer_config.sql` (новий).
- `ncrm/supabase/functions/currency-rates-fetch/index.ts` (новий каталог+файл).
- `ncrm/supabase/config.toml` — якщо обраний спосіб шедулінгу вимагає запису там (звірити з поточною версією Supabase CLI).
- Codex should verify against actual project files/CLI version before writing.

## 7. Acceptance criteria
- [ ] `select key, value_num from app_config where key='nbu_rate_buffer_pct'` → `0.01`.
- [ ] Ручний виклик Edge Function → рівно 3 нові/оновлені рядки в `currency_rates` (USD/EUR/JPY) з `as_of=сьогодні`.
- [ ] `source` кожного рядка — реальне джерело, що спрацювало (не хардкод-заглушка).
- [ ] JPY завжди `source='НБУ'`.
- [ ] Буфер застосований лише коли `source='НБУ'`; для Приват/Моно `rate_to_uah` = курс банку без множення.
- [ ] Повторний виклик того самого дня не створює дубль (перевірити `unique(currency, as_of)` не порушено).
- [ ] Одна валюта штучно "зламана" (напр. таймаут) → інші дві все одно пишуться, помилка залогована.
- [ ] `git diff` лише у файлах §6, без дрейфу в protected zones.

## 8. QA / smoke test (owner)
- [ ] Застосувати міграцію 0015, перевірити `nbu_rate_buffer_pct`.
- [ ] Задеплоїти Edge Function, викликати вручну раз — звірити 3 рядки в `currency_rates` (Studio/psql).
- [ ] Перевірити, що обраний scheduling-механізм реально налаштований (не лише код готовий).
- [ ] Через ~24-48 год перевірити, що з'явився новий рядок на нову дату БЕЗ ручного виклику — це підтверджує, що cron сам спрацював (той самий клас перевірки, що з cron-розкладом CHECKOUT-002/NCRM-10).

## 9. Rollback note
- Edge Function: видалити/відключити (`supabase functions delete currency-rates-fetch`) + прибрати scheduled trigger.
- Міграція 0015: `delete from app_config where key='nbu_rate_buffer_pct'` — безпечно, нічого іншого не залежить.
- Записані рядки `currency_rates` можна лишити (історія курсів, шкоди немає) або видалити за датою, якщо тестові дані.

## 10. Recommended status after execution
NCRM-11 → «In progress» одразу після Фази 1-2 (міграція + функція живі, ручний виклик підтверджено); **повне закриття — лише після підтвердженого самостійного циклу cron** (§8, 24-48 год без ручного втручання).
