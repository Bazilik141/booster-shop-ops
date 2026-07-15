# NCRM-07b — RLS + модель ролей: scope-план перед Codex handoff

**Дата:** 2026-07-15 · **Автор:** Claude (strategic assistant) · **Задача:** NCRM-07b, "Enable RLS on public schema"
**Пов'язано:** `context-index.md` L119, ROADMAP_FLOW картка `NCRM-07b` в `dashboard/booster-dashboard.html` (L1707-1712), заблокована на NCRM-07 (owner QA не пройдено)
**Перевірено перед написанням:** усі 9 міграцій `ncrm/supabase/migrations/0001-0009`, `0005_grants_for_app_read.sql`, `lib/supabase/client.ts`, `lib/repositories/_utils.ts`, `ncrm/README.md`, `supabase/config.toml`, `plans/NCRM-financial-model-v2_technical-contract_20260711.md` (RLS/ролі там не згадані — це нова тема), git-історія по `plans/`

## 1. Conclusion

Увімкнути RLS на всіх public-таблицях **deny-by-default** (без жодних policies для `anon`/`authenticated`) — і паралельно закласти легку таблицю ролей (`staff`/`profiles`) вже зараз, як фундамент під майбутній логін. Але **повну матрицю RLS-policies per-role не будувати в цій задачі** — це передчасно.

Чому саме так: `ncrm/README.md` прямо каже — "UI/сторінки не мають робити прямі Supabase query calls напряму", весь read/write йде через репозиторій-шар на `SUPABASE_SERVICE_ROLE_KEY`. Тобто `anon`/`authenticated` ролі додаток сьогодні взагалі не використовує, і `service_role` завжди обходить RLS (це поведінка Supabase, не налаштування). Тому:

- Просте "ENABLE ROW LEVEL SECURITY" без policies нічого не ламає (додаток працює як і працював) і закриває попередження Security Advisor (проєкт **вже прив'язаний до cloud** — `supabase/.temp/project-ref` існує, тобто Advisor реально бачить ці таблиці).
- Це **не single-owner shortcut**: ми не просто "вимикаємо галочку", а свідомо лишаємо deny-by-default базу, готову приймати policies, коли з'явиться реальний логін.
- Повний RBAC (roles-таблиця + policy на кожну дію) без існуючого UI/логіну — over-engineering: NCRM-08 (read-екрани) і NCRM-09 (write-форми) ще навіть не почались, тому неможливо точно спроєктувати policies під екрани, яких не існує.

owner_id-на-рядках (варіант з питання) тут концептуально не підходить: Booster Shop — один бізнес з кількома можливими співробітниками, а не multi-tenant SaaS з різними власниками даних. owner_id-per-row вирішує ізоляцію між різними клієнтами/компаніями; тут потрібне розмежування за **роллю** (хто що може робити в одних і тих самих даних), тобто roles-таблиця — правильний примітив, не owner_id.

## 2. Task type

Architecture/scope decision (pre-handoff) — DB security foundation. Не патч і не хендоф — проміжний документ, що розблоковує написання Codex handoff, коли NCRM-07 закриється.

## 3. Owner

Mixed: Claude (цей план → потім Codex handoff через `bs-codex-handoff`), Codex (сама міграція, після підтвердження), Owner/Raccoon (відповісти на 3 відкриті питання нижче + за бажанням глянути Security Advisor в Supabase dashboard).

## 4. Status

Not started (без змін — цей план не займає статус картки в Notion/дашборді). NCRM-07b лишається заблокованою на NCRM-07. План лише готує scope наперед, щоб хендоф можна було писати одразу після закриття NCRM-07.

## 5. Next action — CONFIRMED (owner, 2026-07-15)

1. **Ролі** — 4: `owner`, `admin`, `user_plus`, `user`. `owner` = лише Raccoon, всі доступи без винятків (найпростіше — спецкейс у коді: `if role === 'owner' return true`, без рядків у permissions-таблиці). `admin`/`user` — конкретний набір прав розписуємо пізніше (власні слова власника: "потім розкидаємо"), це не блокує міграцію-скелет. `user_plus` = базові права `user` + точкові індивідуальні доступи поверх (override-таблиця, див. п.6).
2. **Row-level фільтр** — підтверджено, потрібен, вбудовується в логіку доступів.
3. **Логін** — рекомендація Claude (owner не заперечив): Supabase Auth, email/password. Найпростіше для одного власника зараз, легко розширити інвайтами пізніше; magic link не дає користі при 1 юзері й додає зайве email-налаштування. Не layout-critical рішення — легко змінити пізніше.

Далі: Claude пише Codex handoff на скелет нижче через `bs-codex-handoff`, коли скажеш. NCRM-07b формально й далі заблокована на NCRM-07 (owner QA не пройдено) — можна підготувати хендоф заздалегідь і тримати напоготові до розблокування.

## 6. Codex handoff (чернетка скелету — узгоджено з owner, готова до відправки після п.5)

Орієнтовний склад міграції `0010_enable_rls_baseline.sql`:

**RLS deny-by-default:**
- `ALTER TABLE ... ENABLE ROW LEVEL SECURITY` на всіх 36 таблицях public-схеми з `0001-0008` (без жодної policy; `service_role` продовжує працювати як зараз).
- Виправити 7 view з `0009_reporting_forecast_kpi.sql` (`v_current_rrc`, `v_pnl_monthly`, `v_cost_quality_exposure`, `v_unpriced_inventory`, `v_forecast_margin`, `v_inventory_dashboard_guardrails`, `v_data_quality`): додати `security_invoker = true`. Легко пропустити деталь — без цього view виконуються з правами власника, а не того, хто питає, тобто навіть після ENABLE RLS на таблицях ці 7 view можуть тихо лишитися дірою.

**Таблиця ролей `public.staff`:**
```sql
create table public.staff (
  id uuid primary key references auth.users(id) on delete cascade,
  role text not null check (role in ('owner','admin','user_plus','user')),
  display_name text,
  created_at timestamptz not null default now()
);
```

**Точкові доступи для `user_plus` (override поверх базових прав ролі):**
```sql
create table public.staff_permission_overrides (
  id bigint generated always as identity primary key,
  staff_id uuid not null references public.staff(id) on delete cascade,
  permission_key text not null,
  granted boolean not null default true,
  created_at timestamptz not null default now(),
  unique (staff_id, permission_key)
);
```
`permission_key` лишити вільним text, без enum/фіксованого списку — конкретні права визначає owner окремо (розписані по ролях пізніше), Codex не повинен вигадувати таксономію прав.

**Row-level filter — скелет.** Додати `created_by uuid references auth.users(id)` на таблицях, де є природний "хто це зробив" і зараз такої колонки немає (перевірено по всіх чотирьох — немає): `sales`, `purchases`, `writeoffs`, `mystery_fulfillments`. Найочевидніші кандидати з NCRM-09 ("серце CRM" — форми внесення). Фінальний список і те, яка роль який фільтр отримує — окремо, коли owner розпише права по ролях.

**Де живе логіка доступів.** НЕ Postgres RLS-policy per-role (UI й далі не ходить у Supabase напряму, все через service_role — `ncrm/README.md`), а application-layer у репозиторій-шарі: helper на кшталт `can(staffRole, permissionKey, overrides)` перед кожним запитом + автододавання `.eq('created_by', currentUser.id)`, коли роль вимагає рядковий фільтр. RLS на БД лишається deny-by-default як defense-in-depth і Security Advisor compliance, не як основний механізм доступу.

**Auth:** Supabase Auth, email/password (п.5.3).

**Без змін:** гранти з `0005` (`anon`/`authenticated` і далі без доступу до таблиць). Стандартний крок з README після міграції: `db reset` + перегенерувати `lib/types/database.ts`.

**Не входить у цю міграцію (окремо, пізніше):** конкретні права `admin`/`user`, UI логіну, реальні RLS-policies під конкретні ролі — усе це NCRM-08/09, коли з'явиться екран.

## 7. QA checklist

- `npx supabase db reset` — без помилок.
- `npx supabase db diff --local` — чисто.
- `npm run dev` — додаток працює як раніше (весь read/write через service_role, RLS на нього не діє — нічого не мало зламатись).
- **Smoke test:** ручний запит до будь-якої таблиці з `NEXT_PUBLIC_SUPABASE_ANON_KEY` (без сесії) — має повернутись порожньо/403, а не дані. Це доказ, що RLS реально блокує, а не просто "увімкнено для галочки".
- Supabase Dashboard → Security Advisor: попередження про відсутній RLS на public-таблицях зникають.
- Seed: рядок owner (`Raccoon`) в `auth.users` + відповідний `staff` з `role='owner'` — щоб таблиця не лишалась порожньою до NCRM-08/09 (не блокер для цієї міграції, бо доступ поки й так через service_role, але без цього рядка нічого перевірити вручну).

## 8. Risks

Задача чіпає ядро нової CRM (Supabase/Postgres), тому ризик називаю прямо: якщо замість deny-by-default хтось додасть "тимчасову" policy типу `USING (true)` для `authenticated`, це фактично відкриє всі бізнес-дані будь-кому з anon-ключем — і буде гірше, ніж просто вимкнений RLS, бо виглядатиме захищеним, а насправді ні. Тому в хендофі Codex явно зафіксувати "без policies" як вимогу, не залишати на розсуд. Другий ризик — 7 view з `0009` без `security_invoker`, про які легко забути. Обидва ризики закриваються smoke test-ом з п.7 (anon-запит має провалитись).

---
*Репо вже містить незбережені зміни в інших файлах (context-index.md, dashboard/booster-dashboard.html, staged handoff CHECKOUT-004, кілька нових diagnostics) — цей план їх не чіпає і комітиться окремо.*
