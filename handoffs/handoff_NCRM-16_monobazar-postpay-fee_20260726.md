# Codex Handoff — NCRM-16: спосіб оплати «Післяплата monobazar»

Date: 2026-07-26 | Owner: Raccoon | Планувальник: Claude | Виконавець: Codex

> LOW-RISK: нова довідникова міграція + невелика UX-правка в уже готовій формі продажу. Не чіпає order-sync/checkout/OpenCart.

## 1. Task ID
NCRM-16 — новий спосіб оплати `postpay_monobazar` («Післяплата monobazar», комісія 2.9%) і автопідстановка цього способу у формі продажу, коли канал = Monobazar.

## 2. Context
Власник тепер веде продажі через Monobazar під бізнес-профілем ФОП — тарифи й домовленості Monobank з поштою інші, ніж загальна «Контроль оплати ФОП» (`fop_control`) чи «Післяплата фіз» (`cod_personal`). Наявні `payment_types` (`0002_stage2_sales.sql`): `fop_control`, `cod_personal`, `bank_details`, `acquiring` — жодного під Monobazar. `sale_channels` вже містить `monobazar` (seed з `0001`). Форма продажу (`ncrm/app/orders/new/sale-form.tsx:35-36`) зараз має два незалежні дропдауни — канал і тип оплати — без жодного зв'язку між ними.

Побічна знахідка (не блокує цю задачу, лише контекст): `app_config`-ключі `fop_control_pct`, `fop_control_min`, `payback_fiz_pct`, `payback_fiz_fix`, `acquiring_pct`, на які посилаються існуючі `payment_types`, самі ніде не засіяні жодною міграцією — це вже наявна прогалина, не створена цією задачею і не в її скоупі виправляти.

## 3. Goal
1. Новий `payment_types` рядок `postpay_monobazar` / «Післяплата monobazar» з комісією 2.9%.
2. У формі нового продажу: коли обрано канал «Monobazar», тип оплати автоматично підставляється як «Післяплата monobazar» (але лишається змінним — власник може обрати інший тип вручну, якщо треба).

## 4. What to change
**Фаза 1 — міграція** `ncrm/supabase/migrations/0016_ncrm16_monobazar_postpay_fee.sql` (за зразком `0013`/`0014`/`0015`, ідемпотентна):
- `app_config`: `postpay_monobazar_pct=0.029`, unit `ratio`, `effective_from`=дата деплою, `is_active=true`, опис укр. («Післяплата monobazar: комісія від вартості товару»).
- `payment_types`: `postpay_monobazar`, «Післяплата monobazar», `fee_pct_config_key='postpay_monobazar_pct'`, `fee_fixed_config_key=null`, `fee_min_config_key=null`, `is_active=true`.

**Фаза 2 — автопідстановка** в `ncrm/app/orders/new/sale-form.tsx`:
- При зміні дропдауна каналу (`channelId`) на канал з кодом `monobazar` (звірити код каналу через `references.channels`, не хардкодити id) — автоматично виставити `paymentTypeId` на `postpay_monobazar` (звірити код через `references.paymentTypes`).
- Це саме **дефолт**, не жорстке блокування: власник і далі може вручну змінити тип оплати після автопідстановки.
- Мінімальна клієнтська логіка (`onChange`/`useEffect` за наявним патерном компонента), без нового server action — `addSale` вже приймає `paymentTypeId` як є.

## 5. Do not touch
- `order-sync/index.ts`, NCRM-14 (ПУМБ/discount_total) — Monobazar-продажі вносяться вручну, не йдуть через OpenCart-синк, інший файл.
- NCRM-12 (форма зміни статусу замовлення, `/orders/[id]`) — інша форма, інший файл, не займаємо.
- Наявні `payment_types`/`app_config` (`0013`/`0014`/`0015`) — не редагувати, лише додати нову `0016`.
- Не досіювати відсутні `fop_control_pct`/`payback_fiz_pct`/`acquiring_pct` в `app_config` — окрема прогалина, не ця задача.
- `addSale`, FIFO/COGS-тригери, auth — не чіпати.

## 6. Likely files / areas
- `ncrm/supabase/migrations/0016_ncrm16_monobazar_postpay_fee.sql` (новий).
- `ncrm/app/orders/new/sale-form.tsx` (~рядки 35-36 і форма-стейт довкола).
- Codex should verify against actual project files before writing.

## 7. Acceptance criteria
- [ ] `select code, name_uk from payment_types where code='postpay_monobazar'` → 1 рядок з правильною назвою.
- [ ] `select value_num from app_config where key='postpay_monobazar_pct'` → `0.029`.
- [ ] У формі продажу: вибір каналу «Monobazar» автоматично ставить тип оплати «Післяплата monobazar».
- [ ] Ручна зміна типу оплати після автопідстановки далі працює (не заблокована).
- [ ] Вибір іншого каналу (не Monobazar) НЕ чіпає вже обраний тип оплати автоматично.
- [ ] `git diff` лише у файлах §6.

## 8. QA / smoke test (owner)
- [ ] Застосувати міграцію 0016, перевірити рядок у `payment_types`+`app_config`.
- [ ] Локально відкрити форму нового продажу, обрати канал Monobazar — переконатись, що тип оплати підставився сам.
- [ ] Створити тестовий продаж з цим типом оплати — переконатись, що зберігається коректно.

## 9. Rollback note
- Міграція: `delete from payment_types where code='postpay_monobazar'; delete from app_config where key='postpay_monobazar_pct';` — безпечно, нічого іншого не залежить.
- Форма: чистий revert доданого блоку логіки автопідстановки.

## 10. Recommended status after execution
NCRM-16 → «Done» одразу після QA §8 — задача маленька, без залежності від live-checkout чи order-sync.
