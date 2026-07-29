# Codex Report — NCRM-14: ПУМБ payment types + discount_total

Date: 2026-07-26

## Scope
Реалізовано рівно handoff NCRM-14: нова ідемпотентна Supabase-міграція для ПУМБ «Сплата частинами» 3/4/5, мапінг ПУМБ у `order-sync` та пріоритет `payload.discount_total` із сумісним fallback. Статуси, Hutko/acquiring fallback, MONO, round-4 логіка, історичні `sales` і protected zones не змінювались.

## Files touched
```
ncrm/supabase/migrations/0014_ncrm11_pumb_payment_types.sql — ПУМБ app_config + payment_types
ncrm/supabase/functions/order-sync/index.ts — ПУМБ mapping та discount_total payload fix
diagnostics/NCRM-14_order-sync-pumb-discount_report_20260726.md — цей звіт
```

## Dry-run result
```
Passed local validation:
- TypeScript 6.0.3 transpile of the order-sync helper section
- 8 paymentTypeCode fixtures: MONO, PUMB code/UA/part variants, bank, COD, Hutko and non-installment PUMB
- discount_total direct-payload wiring plus legacy fallback presence
- SQL structure: exactly 3 app_config rows, 3 payment_types rows, both upsert clauses
- git diff --check: no whitespace errors (only pre-existing repository CRLF notices)

Deno CLI is not installed locally, so `deno check` remains owner-side/deploy-environment validation.
```

## php -l result
```
Not applicable: this change contains only TypeScript and SQL; no PHP files changed.
```

## Idempotency
Міграція використовує `on conflict ... do update` для кожного config key та payment type. Повторне застосування зберігає ті самі 3+3 довідкові записи без дублювання.

## Rollback
До redeploy Edge Function зберегти робочу копію `ncrm/supabase/functions/order-sync/index.ts` і за потреби redeploy її назад.

SQL rollback (не виконується автоматично):
```sql
delete from public.payment_types where code like 'credit_pumb_%';
delete from public.app_config where key like 'credit_pumb_%_fee_pct';
```

## Run command (owner)
```bash
# 1. Зберегти backup поточного order-sync/index.ts.
# 2. Застосувати 0014_ncrm11_pumb_payment_types.sql у Supabase.
# 3. Redeploy Edge Function order-sync зі сховища NCRM.
```

## Post-deploy QA checklist
- [ ] SQL: `payment_types` має рівно `credit_pumb_3/4/5`; `app_config` має 0.030 / 0.045 / 0.058.
- [ ] Реальне замовлення MONO ПЧ та «за реквізитами» зберігають чинні payment types; Hutko лишається `acquiring`.
- [ ] Нове замовлення з відомою ненульовою знижкою має такий самий `sales.discount_total`.
- [ ] Cron-лог `booster-async-order-sync.log`: `delivered` росте, `retry` не зростає.
- [ ] Після PAY-002: перше реальне ПУМБ-замовлення підтверджує точний `payment_method_code`; до того TODO в коді лишається відкритим.

## Side effects / risks
`order-sync` обробляє всі нові замовлення. Локальна перевірка не замінює owner smoke після redeploy. Міграція торкається лише довідкових даних; наявні продажі та статуси не змінює.