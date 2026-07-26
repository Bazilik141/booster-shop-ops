# Codex Report — NCRM-11: автоматичний фетч курсів валют

Date: 2026-07-26

## Scope

Реалізовано лише контракт handoff: additive-міграція конфігурації буфера та нова Supabase Edge Function для щоденного запису USD/EUR/JPY у `currency_rates`. Форми закупки, заморозка курсу в закупці, `order-sync`, NCRM-14, OpenCart і checkout не змінювались.

Для USD/EUR функція використовує підтверджені живими API поля курсу продажу валюти: ПриватБанк `sale`, Monobank `rateSell`; це сума UAH, яку магазин сплачує за одиницю валюти. JPY завжди читається з НБУ `rate`. Буфер `+1%` додається лише до гілки НБУ.

## Files touched

```
ncrm/supabase/migrations/0015_ncrm11_currency_rate_buffer_config.sql
ncrm/supabase/functions/currency-rates-fetch/index.ts
diagnostics/NCRM-11_currency-rates-fetch_report_20260726.md
```

## Dry-run result

Не виконувався: у локальному середовищі немає Supabase CLI (`supabase` не знайдено), а `db reset` змінює локальну БД і не є безпечним на поточному спільному worktree. Жодних cloud/deploy-команд не виконувалось.

Живі read-only перевірки 2026-07-26 підтвердили структури API:

```
ПриватБанк: ccy, base_ccy, buy, sale
Monobank: currencyCodeA, currencyCodeB, rateBuy, rateSell, rateCross
НБУ: cc, rate
```

## Static checks

Запустити локально перед review:

```bash
node --check ncrm/supabase/functions/currency-rates-fetch/index.ts
git diff --check
```

## Idempotency

- `0015`: `on conflict (key, effective_from) do update`.
- Edge Function: `upsert(..., { onConflict: "currency,as_of" })`; повторний запуск у ту саму київську дату оновлює рівно три цільові рядки без дублю.

## Scheduling / deployment (owner)

Функція не потребує нового секрету та лишається зі стандартною JWT-перевіркою Supabase. Після застосування міграції й деплою функції в Supabase Dashboard створити Cron Job типу **Edge Function**:

- Name: `ncrm-currency-rates-fetch-daily`
- Function: `currency-rates-fetch`
- Schedule: `5 7 * * *` (щодня 07:05 UTC; 10:05 Europe/Kyiv у літній час)
- Method: `POST`
- Body: `{}`

Альтернатива для SQL-процесу: увімкнути `pg_cron` та `pg_net`, зберегти URL проєкту і publishable key у Supabase Vault, після чого створити іменований job через `cron.schedule`. Не комітити project URL, keys або Vault secrets у репозиторій. Перевіряти виконання у Dashboard Cron Job History / `cron.job_run_details`.

## Rollback

1. У Dashboard deactivate/delete `ncrm-currency-rates-fetch-daily`.
2. Видалити функцію: `npx supabase functions delete currency-rates-fetch`.
3. За потреби прибрати лише конфігурацію: `delete from public.app_config where key = 'nbu_rate_buffer_pct';`.
4. Рядки `currency_rates` є історією; тестові можна видалити вузько за `as_of`.

## Post-deploy QA checklist

- [ ] Переконатися, що `0015` застосована: `nbu_rate_buffer_pct = 0.01` і `unit = ratio`.
- [ ] Задеплоїти `currency-rates-fetch` та виконати один ручний `POST` з Dashboard.
- [ ] Побачити рівно USD/EUR/JPY для київської дати; JPY має `source = 'НБУ'`.
- [ ] Для рядка з `source = 'НБУ'` звірити `rate_to_uah = НБУ rate × 1.01`; для ПриватБанку/Monobank буфер відсутній.
- [ ] Повторити ручний виклик: кількість рядків на `(currency, as_of)` не збільшується.
- [ ] Створити Cron Job, перевірити перший run у History та наступного дня підтвердити автономний запис без ручного виклику.

## Side effects / risks

Зміни обмежені `app_config` і `currency_rates`. Публічні API можуть бути тимчасово недоступні або змінити контракт: функція переходить по fallback-ланцюжку, не пише невалідні значення та ізолює помилки кожної валюти. NCRM-11 не можна вважати Done до підтвердженого автономного циклу cron через 24–48 годин.
