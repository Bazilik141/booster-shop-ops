# Codex Handoff — NCRM-01: Supabase project + SQL migrations (Stage 1–4) + types

Date: 2026-07-05 | Parent: NCRM-00 (Done)

## 1. Task ID
NCRM-01 — Supabase проєкт + SQL-міграції (Stage 1–4) + типи. Notion: `In progress`. Blocker NCRM-00 закрито.

## 2. Context
NCRM-00 завершено: цільова архітектура (Supabase + Next.js/Vercel), повний аудит фінмоделі й фінальна схема `schema-v1` (Stage 1–4) підтверджені власником — джерело: `plans/crm-schema-v1_2026-06-26.md` (канон полів/типів/зв'язків), `plans/crm-new-platform-architecture_2026-06-26.md` (архітектура, §8 план фаз). Перевірено репо й git-історію — реального коду CRM ще не існує, були лише плани (NCRM-00). Кодова база нової CRM живе в **новій папці `ncrm/` всередині цього ж репо** (рішення власника). Supabase-проєкт і CLI власником ще **не створені** — це паралельний ручний крок (див. §9).

## 3. Goal
Локальний Supabase-проєкт (CLI + Docker emulator) з повним набором таблиць/в'юх/функцій Stage 1–4 за `schema-v1`, що застосовується чисто (`supabase db reset`), + авто-згенеровані TypeScript-типи. Після того як власник створить хмарний Supabase-проєкт — ті самі міграції застосувати туди (Phase B, окремий крок, не обов'язково в цьому ж PR).

## 4. What to change (scope)
Усе — нові файли в новій папці, нічого існуюче не редагується.

- `ncrm/` — новий каталог, ініціалізований через `supabase init`
- `ncrm/supabase/migrations/0001_stage1_core.sql` — `app_config`, `currency_rates`, `products`, `product_prices`, `supplier_regions`, `purchases`, `purchase_lots` (поля/типи/констрейнти — точно за `schema-v1` §1.1–1.7, конвенції §0: uuid PK, snake_case, `money4*`-четвірка де вказано, `timestamptz` + `created_at`/`updated_at` тригер, effective-dated `app_config`)
- `ncrm/supabase/migrations/0002_stage2_sales.sql` — `sale_channels`, `payment_types`, `post_methods`, `order_statuses`, `payment_statuses`, `sales`, `sale_items`, `refunds`, функція `fn_fix_sale_cogs()` (§2.1–2.5)
- `ncrm/supabase/migrations/0003_stage3_mystery_consumables.sql` — `mystery_box_types`, `mystery_contents`, `consumables`, `auto_consumable_rules`, `consumable_consumptions`, `writeoffs`, `writeoff_items` (§3.1–3.6)
- `ncrm/supabase/migrations/0004_stage4_expenses_reports.sql` — `expenses`, `v_pnl_monthly`, `v_sales_report`, `v_channel_report`, `v_top_skus`, `v_stock_alerts`, `v_repeat_customers`, `v_data_quality`, `v_below_cost_alert` (§4.1–4.3)
- Seed-дані (тільки довідники/лукапи, підтверджені власником у `schema-v1`, нічого вигаданого):
  - `sale_channels` (5): OpenCart / Telegram / OLX / Monobazar / Інше
  - `payment_types` (4), `post_methods` (5), `order_statuses` (7), `payment_statuses` (4) — точні списки з §2.1
  - `supplier_regions` (4): Японія (ZenMarket) / Україна / Європа / США — дефолти валют з таблиці §1.5
  - `mystery_box_types` (2): MBX (700 РРЦ / 450 провізорна), MBX-XL (950 / 700, холо 75) — §3.2
  - `currency_rates`: стартовий рядок `UAH = 1`
- `ncrm/lib/types/database.ts` — згенерований `supabase gen types typescript --local`, не редагувати руками
- `ncrm/.gitignore` — `.env*`, `node_modules`, `supabase/.branches`, локальні Docker-volume артефакти
- `ncrm/README.md` — короткий локальний setup (`supabase start`, `db reset`, `gen types`)

Джерело істини для точних полів/типів/зв'язків — **`plans/crm-schema-v1_2026-06-26.md`** (Stage 1–4 підтверджені власником). Codex реалізує рівно те, що там написано; нічого не додає й не спрощує без позначки.

## 5. What NOT to touch
- Сайт/OpenCart код, `patches/`, `dashboard/`, будь-які інші файли `handoffs/` — не стосується цієї задачі
- `sitemap.xml`, `robots.txt`, редіректи, canonical, `.htaccess`, checkout, payment, фіскалізація, Merchant feed, Product schema сайту — захищені зони, тут не зачіпаються в принципі (окрема кодова база)
- Не створювати й не лінкувати реальний хмарний Supabase-проєкт, не пушити міграції на remote — лише локальний emulator, доки власник не дасть project ref/ключі
- Поля/значення, ще позначені `[OWNER]` у `schema-v1` (форми Європа/США, стікер для змішаних Pokémon+One Piece замовлень, фінальний список типів списань) — НЕ вигадувати; лишити мінімальний робочий дефолт і позначити TODO в коментарі міграції
- Секрети (service-role key, DB password, project ref) — ніде в чаті/коміті/логах

## 6. Likely files/areas
- Весь новий каталог `ncrm/` (не існує — Codex створює з нуля)
- Можливо корінь репо `.gitignore` — перевірити, чи вже покриває `ncrm/node_modules`, `ncrm/.env*`; якщо ні, додати. Codex має звірити з реальним поточним `.gitignore`, не припускати вміст.

## 7. Acceptance criteria
- [ ] `ncrm/supabase/migrations/0001…0004*.sql` існують, застосовуються по порядку
- [ ] `supabase start` + `supabase db reset` (локально, Docker) — exit code 0, без помилок
- [ ] Усі таблиці/в'юхи/функції зі Stage 1–4 `schema-v1` присутні в локальній Postgres (перевірка через `\dt+`/`\dv+` або SQL-скрипт звірки)
- [ ] `supabase gen types typescript --local > ncrm/lib/types/database.ts` — файл непорожній, містить TS-тип для кожної таблиці
- [ ] Seed-рядки лукапів точно збігаються зі списками з `schema-v1` (кількість і назви) — без зайвого й без пропусків
- [ ] Жодних даних поза довідниками/seed (жодних вигаданих products/sales/purchases)

## 8. QA / smoke test (owner)
Це НЕ checkout/payment/сайт-схема — `bs-checkout-smoke`/`bs-merchant-schema-qa` не застосовні. Smoke — локальний, для нової CRM-інфраструктури:

- [ ] `cd ncrm && supabase start` піднімається без помилок (потрібен Docker)
- [ ] `supabase db reset` — 0 помилок у виводі
- [ ] `supabase db diff` — порожній (немає розбіжності міграцій і реального стану)
- [ ] `supabase gen types typescript --local` — відкрити файл, звірити назви таблиць на очі
- [ ] `git status` перед комітом — переконатися, що жодного `.env`/ключа немає в staged файлах

## 9. Rollback note
Усе локальне (Docker emulator), реальний хмарний Supabase ще не створений — на live-сайт це жодним чином не впливає. Rollback = `supabase stop --no-backup` + `rm -rf ncrm/supabase/.branches` (або просто видалити гілку/PR, якщо щось пішло не так). Нічого поза папкою `ncrm/` не редагується, тож відкат тривіальний.

**Паралельно (не блокує Codex, але потрібно до Phase B):** власник створює Supabase-проєкт (Free tier) на supabase.com, ставить Supabase CLI локально, тримає `project ref`/ключі при собі — ніде їх не вписувати в чат/код/коміт.

## 10. Recommended status after execution
**`In progress`**, не `Done`. DoD з Notion («міграції застосовуються чисто, типи генеруються») закривається лише після Phase B — коли ті самі міграції застосовані до реального хмарного Supabase-проєкту й звірені власником. Локальна частина (цей хендоф) — необхідна, але не достатня умова закриття NCRM-01.
