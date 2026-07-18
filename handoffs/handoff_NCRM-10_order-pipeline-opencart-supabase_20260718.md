# Codex Handoff — NCRM-10: Order pipeline OpenCart → Supabase (+ smoke; ретайр Apps Script — окремим кроком пізніше)

Date: 2026-07-18 | Parent: none (top-level roadmap item). Blocker NCRM-09 — Done (2026-07-18).

## 1. Task ID
NCRM-10 — живі замовлення з OpenCart напряму в Supabase (`sales`/`sale_items`), заміна Apps Script `doPost`-інтейку. Owner підтвердив сьогодні (2026-07-18): scope = **лише нові замовлення** (insert), доступ правити код OpenCart є.

## 2. Context
- Архітектура вже спроєктована: `plans/crm-new-platform-architecture_2026-06-26.md` §7 (дизайн) і §13.4 (фінальний scope, 2026-06-26) — прочитати обидва перед стартом, тут лише витяг.
- Зараз: OpenCart шле дані в Apps Script `doPost` → `upsertOpenCartOrder_` (код живе в Apps Script, редагований прямо в Google Sheet — не в цьому репо, не читаний Claude напряму). Ціль NCRM-10 — прямий інтейк в обхід цього шару. **Apps Script лишається паралельним резервом і НЕ вимикається в межах цього хендофу** — вимкнення це окреме owner-рішення після підтвердженого smoke (див. §10).
- Order-creation path у живому магазині (підтверджено патчем ST-2b6e, `diagnostics/ST-2b6e_server-render-order-write-gate_report_20260712.md`): `catalog/controller/checkout/confirm.php::index()` → `addOrder()`/`editOrder()`; публічне оформлення = `confirm()` → `index(true)`. Цей звіт SHA-256-гейтив патч на конкретний хеш `confirm.php` станом на 2026-07-12 — **джерело могло змінитись відтоді**, зняти свіжий хеш і звірити перед новим патчем (стандартна практика цього репо для checkout-файлів, не формальність).
- `sales.opencart_order_id text unique` вже існує (`ncrm/supabase/migrations/0002_stage2_sales.sql:76`) — ідемпотентність через unique-constraint, нова колонка не потрібна.
- `sale_channels` вже містить рядок `('opencart', 'OpenCart', true)` (`0002`, seed).
- Тест-фільтр (Notion-картка NCRM-10 + план §13.4, дослівно): **будь-який окремий тригер → НЕ тягнути замовлення**: прізвище `Леусенко`; телефон `0991119279`; слово `Тест`/`TEST` у назві SKU, в описі SKU, або в примітці до замовлення.
- COGS/FIFO вже автоматично фіксується тригерами `0006`/`0008` (`fn_fix_actual_sale_items`, `fn_fix_new_sale_item`) при insert/update `sales`/`sale_items` — Edge Function НЕ рахує собівартість сама, просто вставляє коректні рядки, тригери роблять решту.
- `ncrm/supabase/functions/` ще не існує — це перша Edge Function у проєкті (є лише `migrations/`, `snippets/`, `config.toml`). Той самий Supabase CLI, що вже використовується для migrations, вміє `functions new`/`serve`/`deploy` — підтвердити версію CLI перед стартом.
- **[КРИТИЧНО, знайдено 2026-07-18 після написання цього хендофу] Cloud-проєкт відстає від local на 6 міграцій.** `ncrm/.env.local` вказує `NEXT_PUBLIC_SUPABASE_URL=http://127.0.0.1:54321` — весь поточний dev/QA йде проти **локального** Supabase (Docker, `supabase start`). Cloud-проєкт існує й лінкований (`diagnostics/NCRM-01_supabase-project-sql-migrations_report_20260705.md`: "linked to the owner's empty cloud Supabase project; pushed the same four migrations"), але з тих пір **жодна наступна міграція на cloud не пушилась** — підтверджено дослівно в звітах `NCRM-04` ("No cloud command was run") і `NCRM-07b` ("Лише локально, cloud push не було"). Тобто на cloud є лише `0001`-`0004` (schema-v1 core+sales+mystery+expenses), а `0005`-`0010` (grants, inventory ledger/FIFO, mystery fulfillment RPC, returns/cost quality, reporting/KPI views, RLS+multi-user) — **лише локально**. Edge Function приймає живі замовлення з реального магазину — фізично мусить писати в cloud (локальний Docker на машині власника недоступний з інтернету для webhook від хостингу магазину). Якщо задеплоїти проти cloud без попереднього `db push` — insert у `sales`/`sale_items` може пройти (ці таблиці є з `0001`-`0004`), але **без FIFO/COGS-тригерів, RLS-посади і решти бізнес-логіки з `0006`-`0010`** — тиха, не одразу помітна розбіжність з локальними даними. **Перед деплоєм Edge Function на cloud — звірити local vs remote міграції і запушити відсутні `0005`-`0010` (owner, окрема ручна дія, потребує `SUPABASE_ACCESS_TOKEN`/лінк проєкту, не Codex-задача сама по собі, але має статись до, не після, деплою order-sync).**
- Секрет для перевірки джерела запиту (OpenCart → Edge Function) — НОВИЙ секрет, окремий від `SUPABASE_SERVICE_ROLE_KEY` (`ncrm/.env.example`). Пропонована назва: `ORDER_SYNC_SHARED_SECRET` (Supabase Edge secret + відповідний OpenCart-side config). Значення генерує й вносить власник, ніколи не в чаті/хендофі/діагностиці/логах.
- `order_no` — існуючі рядки використовують формат `OC-FOP-####` (4-значний zero-pad; приклади з діагностик: OC-FOP-0203, OC-FOP-0217, OC-FOP-0219, OC-FOP-0238), але **історичні імпортовані рядки мають `opencart_order_id = null`** (`ncrm/scripts/import-history/import-history.mjs:519`) — тобто цей номер призначався окремо в Sheets, і НЕ підтверджено, що `####` завжди дорівнює реальному OpenCart `order_id`. Перед хардкодом **звірити**: або знайти живу логіку `upsertOpenCartOrder_` (Apps Script джерело), або порівняти найбільший історичний `OC-FOP-####` з поточним живим OpenCart `order_id` — якщо останній вже більший, формування `order_no = 'OC-FOP-' + opencart_order_id` (4-digit zero-pad) безпечне як нова послідовність вперед; якщо ні — зупинитись і уточнити в owner, не вигадувати мапінг.

## 3. Goal
Нове замовлення в живому OpenCart (не тестове, за фільтром вище) з'являється в Supabase `sales`+`sale_items` автоматично, без ручного втручання, без дублів при повторній доставці, і без жодного впливу на швидкість/надійність checkout — навіть якщо Edge Function тимчасово недоступна.

## 4. What to change (scope)
- **OpenCart hook (PHP, patch-runner конвенція з `AGENTS.md` → Patch conventions):** новий патч `patches/NCRM-10_opencart-order-sync-hook_20260718.php`. Виклик одразу ПІСЛЯ успішного `addOrder()` у storefront-шляху нового замовлення (`catalog/controller/checkout/confirm.php` або модель, куди він делегує — підтвердити точний файл/рядок на свіжому бекапі, не на застарілому хеші з ST-2b6e). Hook формує JSON (opencart order_id, дата, ім'я/телефон клієнта, товари: sku/qty/ціна, знижка, доставка/пакування якщо доступні, спосіб оплати, спосіб доставки, примітка) і робить **fire-and-forget** `POST` на Edge Function з заголовком `ORDER_SYNC_SHARED_SECRET`. Fire-and-forget = короткий timeout (рекомендація ≤2s connect+total), обгорнутий у try/catch; результат виклику (успіх/помилка/таймаут) **ніколи не впливає** на подальший хід `addOrder()` чи redirect на success-сторінку — лише лог помилки. Патч проходить стандартний runner-гейт: file-exists check, anchor pre-check, backup до `_patch_backups/`, `php -l`, ідемпотентний маркер, self-delete.
- **Edge Function `order-sync` (Deno/TS, нова):** `ncrm/supabase/functions/order-sync/`. Перевіряє секрет-заголовок (401 при невідповідності), валідує обов'язкові поля payload (400 якщо відсутні). Застосовує тест-фільтр (§2) — при збігу будь-якого тригера **нічого не вставляти**, повернути 200 `{skipped: true, reason: 'test-filter'}`. Інакше, в одній транзакції: `INSERT INTO sales (...) ON CONFLICT (opencart_order_id) DO NOTHING RETURNING id`; якщо рядок не повернувся (вже існує) → нічого далі не вставляти, повернути 200 `{ok: true, duplicate: true}`; якщо повернувся → на кожен товар резолвити `product_id` через `products.sku`, вставити `sale_items`, `channel_id` через `sale_channels.code = 'opencart'`. Повертає 200 швидко в обох випадках.
- **Мапінг статусів для нового замовлення:** `order_status_id` → код `'new'`, `payment_status_id` → `'unpaid'`, якщо OpenCart явно не сигналізує інакше (напр. миттєва оплата карткою) — **звірити на 2-3 реальних свіжих замовленнях** (різні способи оплати) під час smoke, не хардкодити наосліп. `post_method_id` — мапити OpenCart shipping-метод на `post_methods.code` (`nova_poshta`/`ukrposhta`/`meest`/`pickup`/`other`) — підтвердити точну назву поля payload на реальних даних.

## 5. What NOT to touch
- Apps Script / Google Sheet `doPost`/`upsertOpenCartOrder_` — лишається живим паралельним резервом. НЕ вимикати, НЕ редагувати в межах цього хендофу.
- Жодної іншої checkout-логіки: Hutko, Checkbox/фіскалізація, First15/купони, форма Нової Пошти, `checkout.twig`, логіка ST-2b6e/ST-2c/ST-3.6/RD-13 — hook лише додає виклик ПІСЛЯ успішного `addOrder()`, нічого в існуючому flow не змінює й не переставляє.
- `editOrder()` / зміни статусу, оплати, ТТН після створення — pipeline їх не чіпає (owner-підтверджений scope, план §13.4). Не підписуватись на статус-events.
- `ncrm/supabase/migrations/0001`–`0005` — не editable (репо-правило). Нова функціональність — нова міграція з наступним вільним номером, не правка старих.
- Жодних RLS-policy змін — `service_role` в Edge Function і так обходить RLS за дизайном (`0010`).
- `sync_failures`/ретрай-черга — опційна за архітектурним планом, **не обов'язкова для цього хендофу**; якщо не робиться зараз — зафіксувати в звіті як свідомо відкладене, не мовчки пропущене.
- Реальне значення `ORDER_SYNC_SHARED_SECRET` — ніде в диффі, звіті, коментарях коду; лише назва env-змінної.

## 6. Likely files/areas
- `patches/NCRM-10_opencart-order-sync-hook_20260718.php` (нове)
- `catalog/controller/checkout/confirm.php` (server target патчу — verify exact hook point на живих даних, не довіряти сліпо хешу з ST-2b6e)
- `ncrm/supabase/functions/order-sync/index.ts` (+ shared helpers за потреби, нове)
- `ncrm/supabase/migrations/00NN_*.sql` — **лише якщо** дійсно потрібна допоміжна функція/індекс; не додавати міграцію заради міграції
- `diagnostics/NCRM-10_order-pipeline-opencart-supabase_report_<date>.md` (новий Codex-звіт)
- Не очікується жодних змін у `ncrm/app/*` чи `ncrm/lib/repositories/*` — Edge Function пише напряму через service_role, минаючи repo-шар UI. Це навмисно (Edge Function ≠ UI, repo-шар-правило стосується лише `ncrm/app/*`), не помилка.

## 7. Acceptance criteria
- [ ] **Передумова перед деплоєм:** `supabase migration list` (local vs linked remote) показує cloud на рівні `0010` (не `0004`) — власник запушив `0005`-`0010` окремо від цього хендофу; Codex не деплоїть order-sync проти cloud, поки це не підтверджено
- [ ] Тестове замовлення через реальний checkout з тест-фільтр-даними (прізвище Леусенко АБО телефон 0991119279 АБО "TEST" у примітці) → checkout проходить штатно, рядка в `sales` **немає** (`select * from sales where opencart_order_id = '<id>'` → 0 рядків)
- [ ] Реальне (не тестове) замовлення через checkout → рядок з'являється в `sales`+`sale_items` протягом кількох секунд, з коректним `opencart_order_id`, `channel_id` (opencart), товарними позиціями
- [ ] Повторна доставка того самого payload (ручний retry) → рядок не дублюється (`opencart_order_id` unique, ON CONFLICT DO NOTHING підтверджено)
- [ ] Edge Function штучно недоступна / секрет невірний → checkout все одно завершується нормально й швидко — головний ризик-тест
- [ ] Apps Script паралельно продовжує писати в Sheet без змін поведінки (не зачеплений)
- [ ] `git diff` на `ncrm/supabase/migrations/0001-0005` — порожній
- [ ] OpenCart-патч пройшов `php -l`, anchor pre-check, має backup і self-delete (стандартний runner-контракт)
- [ ] Жодного реального значення секрету в диффі/звіті/коментарях

## 8. QA / smoke test (owner) — bs-checkout-smoke, повний набір + доповнення для order-sync
Ризик — **високий** (зачіпає живий checkout і потік реальних замовлень). Обов'язковий прогін перед закриттям Codex-роботи, і окремо — перед майбутнім вимкненням Apps Script.

| # | Тест | Кроки | Очікування | Actual | Pass/Fail |
|---|---|---|---|---|---|
| 1 | Реєстрація → Checkout | Новий юзер, товар у кошик, оформлення | Проходить штатно, як і зараз | | |
| 2 | Auto First15 | Перше замовлення нового юзера | First15 автозастосовується (hook не втручається) | | |
| 3 | Повторний First15 | Той самий юзер повторно | Заблоковано (не зачеплено цим патчем) | | |
| 4 | Невірний купон | Ввести неіснуючий код | Чітка помилка, checkout не ламається | | |
| 5 | Дані Нової Пошти | Місто + відділення | Валідація й фолбек як і зараз | | |
| 6 | Видимість кнопки "Оформити" | Заповнити форму | Активна лише при валідних полях | | |
| 7 | Hutko return/сесія | Повернення від провайдера | Сесія жива, статус коректний | | |
| 8 | Success redirect | Після оформлення | `order_id` коректний, сторінка рендериться | | |
| 9 | Чистий JSON з AJAX | Перевірити відповіді ендпоінтів | Без PHP warnings/HTML у JSON | | |
| 10 | Email-доставка | Підтвердження замовлення | Лист приходить, посилання робочі | | |
| 11 | Checkbox/фіскалізація | — | n/a — патч не чіпає оплату/статус/фіскалізацію, лише читає дані ПІСЛЯ addOrder() | | n/a |
| 12 | **[NCRM-10] Тест-фільтр** | Оформити замовлення з даними Леусенко/0991119279/TEST | Checkout ОК, рядка в `sales` немає | | |
| 13 | **[NCRM-10] Реальний sync** | Звичайне не-тестове замовлення | Рядок з'являється в `sales`/`sale_items` коректно | | |
| 14 | **[NCRM-10] Ідемпотентність** | Повторно відправити той самий payload вручну на Edge Function | Другий виклик → `duplicate: true`, без дубля | | |
| 15 | **[NCRM-10] Fail-safe** | Тимчасово зламати секрет/URL у hook, оформити замовлення | Checkout завершується нормально й швидко, лог показує помилку sync, order все одно створено в OpenCart | | |

Sandbox/staging: п.1–11 — як у стандартному bs-checkout-smoke (Hutko sandbox, без реальних платежів). П.12–15 — можна на живому магазині з тест-фільтр-даними (навмисно виключені з обліку) або на staging-копії, якщо є.

Підсумок (pass count / total, блокери, рекомендований статус) — заповнює власник після прогону.

## 9. Rollback note
- **OpenCart-сторона:** патч додає лише один виклик ПІСЛЯ вже успішного `addOrder()` — нічого в існуючій логіці підтвердження замовлення не змінює. Rollback = відновити backup з `_patch_backups/` (runner-конвенція) або прибрати доданий блок коду; Apps Script як був живий, так і лишається, тож видалення hook нічого не ламає.
- **Edge Function:** rollback = `supabase functions delete order-sync` (або просто не викликати — hook fire-and-forget, відсутність функції на іншому кінці лише пише в лог, checkout не ламає).
- Жодної зміни існуючої схеми в цьому хендофі; якщо міграція зі §6 таки знадобиться — вона additive, не чіпає `0001-0005`.
- Жодних живих замовлень не видаляти/не відкатувати заднім числом. Якщо тестові дані помилково потрапили в `sales` під час QA — прибрати вручну через Studio, не через `db reset` (історія імпорту має зберегтись).

## 10. Recommended status after execution
Лишається `In progress` (Notion + дашборд вже виставлено 2026-07-18) до: (0) **owner запушив `0005`-`0010` на cloud Supabase** (передумова §2/§7, окрема ручна дія — без неї деплой order-sync проти cloud не має сенсу), (а) Claude review диффу, (б) owner прогнав §8 QA — мінімум п. 1, 8, 12–15 як критичні для цього ризику, (в) підтверджена паралельна робота протягом періоду, який визначає owner (контракт термін не фіксує). **Вимкнення Apps Script — НЕ частина цієї задачі**: окрема owner-команда після впевненості, лише тоді NCRM-10 → Done.
