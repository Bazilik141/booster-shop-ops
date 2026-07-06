# Codex Handoff — NCRM-02: Repository layer + Next.js skeleton + local emulator

Date: 2026-07-06 | Parent: NCRM-01 (Done)

## 1. Task ID
NCRM-02 — Repository-шар + Next.js скелет + локальний emulator. Notion: `In progress`. Blocker NCRM-01 закрито.

## 2. Context
NCRM-01 завершено й звірено (локально + на реальному Supabase): 4 SQL-міграції Stage 1–4, seed-довідники, `lib/types/database.ts`. Наступний крок Фази 0 — Next.js (App Router, TS) скелет на Vercel, що читає ці дані виключно через Repository Pattern (`plans/crm-new-platform-architecture_2026-06-26.md` §6), плюс підключення до вже піднятого локального Supabase emulator. Notion DoD буквально: «застосунок читає Supabase; emulator піднімається».

## 3. Goal
Мінімальний Next.js застосунок у `ncrm/` (той самий каталог, що й `ncrm/supabase/` з NCRM-01), який через `lib/repositories/*` реально читає дані з локального Supabase emulator і компілюється без помилок. Deploy на Vercel — окремий ручний крок власника (не в скоупі цього хендофа).

## 4. What to change (scope)
`ncrm/` вже існує (`supabase/`, `lib/types/database.ts`, `README.md`, `.gitignore` з NCRM-01) — не перезаписувати, доповнювати.

- `ncrm/package.json` + Next.js App Router скелет (TS): `next`, `react`, `@supabase/supabase-js`, `@supabase/ssr`
- `ncrm/lib/supabase/client.ts` — єдина точка ініціалізації Supabase-клієнта (server + browser), за архітектурою §2/§6
- `ncrm/lib/domain/*.ts` — чисті доменні типи (Sale, PurchaseLot, Product, Summary тощо), незалежні від згенерованого `database.ts`
- `ncrm/lib/repositories/sales.repo.ts` — `listSales()`, `getSale()`, `addSale(payload)`, `updateSaleStatus()`
- `ncrm/lib/repositories/purchases.repo.ts` — лоти, `addPurchase()`, `batchUpdateStatus()`
- `ncrm/lib/repositories/writeoffs.repo.ts`
- `ncrm/lib/repositories/products.repo.ts` — редагування РРЦ, список SKU
- `ncrm/lib/repositories/analytics.repo.ts` — `getSummary()`, `getStock()`, `getSkuMetrics()` — читають в'юхи/RPC з NCRM-01 (`v_pnl_monthly`, `v_stock_alerts`, `v_sku_metrics`-еквівалент)
- `ncrm/app/` — мінімальна root-сторінка, що рендерить реальний результат `analytics.repo` з локального emulator (доказ read-шляху; без дизайну — це пізніші фази)
- `ncrm/.env.example` — тільки назви змінних (`NEXT_PUBLIC_SUPABASE_URL`, `NEXT_PUBLIC_SUPABASE_ANON_KEY` тощо), без значень

Точна специфікація репо-шару — `plans/crm-new-platform-architecture_2026-06-26.md` §6 (не додавати методи понад перелічені без потреби для read-демо).

## 5. What NOT to touch
- `ncrm/supabase/migrations/*` — закрито в NCRM-01, тут не редагувати й не додавати нові міграції (це NCRM-03+)
- Сайт/OpenCart, `patches/`, `dashboard/`, інші файли `handoffs/`
- `sitemap.xml`, `robots.txt`, редіректи, canonical, `.htaccess`, checkout, payment, фіскалізація, Merchant feed, Product schema сайту — не стосується (окрема кодова база)
- Повний login UI / magic-link auth-флоу — поза скоупом цього завдання. Якщо RLS блокує read-демо локально, використати anon/service-key лише в **локальному** `.env.local` (не комітити) і **позначити рішення в PR**, а не вигадувати обхід
- Реальний Vercel-проєкт/деплой/секрети — власник робить перший деплой сам (окремий крок, не тут)

## 6. Likely files/areas
Каталог `ncrm/` (розширення поточного стану — Codex має спершу подивитись, що вже є: `supabase/`, `lib/types/database.ts`, `README.md`, `.gitignore`, не перезаписувати їх).

## 7. Acceptance criteria
- [ ] `ncrm/package.json` існує, `npm install && npm run dev` стартує без помилок
- [ ] `npm run build` — TypeScript компілюється чисто
- [ ] Root route рендерить реальні дані з локального Supabase emulator через `analytics.repo.ts` (не мок)
- [ ] Усі 5 repo-файлів існують із зазначеними методами й реально звертаються до Supabase
- [ ] Жоден компонент/сторінка не викликає `supabase.from(...)` напряму поза `lib/repositories/*`/`lib/supabase/client.ts`
- [ ] `ncrm/.env.example` — тільки назви змінних; `.env.local` в gitignore
- [ ] `npx supabase start` + `db reset` (NCRM-01) і далі проходять без змін/поломок

## 8. QA / smoke test (owner)
Не checkout/payment/сайт-схема — `bs-checkout-smoke`/`bs-merchant-schema-qa` не застосовні.

- [ ] `cd ncrm && npm install` — без помилок
- [ ] `npx supabase start` (якщо не піднято) + `npm run dev` — застосунок стартує
- [ ] Відкрити root route — реальні дані з локального Supabase, не заглушка
- [ ] `npm run build` — без помилок
- [ ] Жодного прямого `supabase.from()` поза repo-шаром (візуальна перевірка)
- [ ] `git status` перед комітом — `.env.local`/ключі не потрапили в staged файли

## 9. Rollback note
Усе нове — в `ncrm/app`, `ncrm/lib` (крім вже існуючого `lib/types`), `ncrm/package.json`. `ncrm/supabase/` з NCRM-01 не займається. Rollback = видалення нових файлів/папок; на live-сайт і на вже верифіковану Supabase-схему це не впливає.

## 10. Recommended status after execution
**`In progress`** до підтвердження власником локального прогону (`npm run dev`/`build` + emulator, за буквальним DoD з Notion). Після підтвердження → `Done`. Перший деплой на Vercel — окрема дія власника (архітектура §8, Phase 0 «M»), не блокує закриття NCRM-02 за поточним формулюванням DoD у Notion, але варто зробити найближчим часом.
