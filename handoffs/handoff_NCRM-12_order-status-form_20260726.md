# Codex Handoff — NCRM-12: форма зміни статусу замовлення

Date: 2026-07-26 | Owner: Raccoon | Планувальник: Claude | Виконавець: Codex

> ⏸️ **НЕ віддавати Codex зараз.** Власник свідомо ставить цю задачу в чергу після закриття NCRM-11 (24-48г підтвердження cron). Хендофф готовий заздалегідь, щоб не гаяти час, коли черга дійде.
> LOW-RISK: чиста UI-надбудова над вже готовою repo-функцією, без нової міграції, не live-checkout.

## 1. Task ID
NCRM-12 (звужено 2026-07-26 — "Mobile-версія" винесена окремою карткою NCRM-15, тут лишається лише форма зміни статусу замовлення). UI на `/orders/[id]` для зміни `order_status`/`payment_status`/`ttn`/`note` існуючого замовлення.

## 2. Context
`updateSaleStatus()` (`ncrm/lib/repositories/sales.repo.ts:167`) вже реалізована й приймає частковий payload (`orderStatusId`, `paymentStatusId`, `ttn`, `note` — оновлює лише передані поля). Довідники для дропдаунів (`order_statuses`, `payment_statuses`) вже читаються через `listNamedReference()` (`reference.repo.ts`), той самий патерн, що в формах NCRM-09b/09c. `/orders/[id]/page.tsx` вже існує як read-only деталка замовлення — форми редагування там нема.

Знайдено при QA NCRM-09e (2026-07-18): без цієї форми неможливо протестувати автозвільнення mystery-резерву — `fn_release_mystery_fulfillment` спрацьовує лише коли `order_status` переходить у `cancelled`/`refund`, а це зараз ніде не виставити через UI.

## 3. Goal
1. На `/orders/[id]` з'являється форма/секція редагування: дропдаун `order_status`, дропдаун `payment_status`, текстове поле `ttn`, текстове поле `note`.
2. Сабміт викликає `updateSaleStatus(id, payload)` через server action (за зразком `orders/new/actions.ts`), оновлює лише змінені поля.
3. Owner може вперше протестувати `cancelled`/`refund` на замовленні з активним mystery-резервом і побачити автозвільнення.

## 4. What to change
- `ncrm/app/orders/[id]/page.tsx` — додати форму/клієнтський компонент редагування статусу (окремий файл на кшталт `orders/[id]/status-form.tsx`, за стилем `orders/new/sale-form.tsx`).
- Новий server action (окремий файл або доповнення існуючого `actions.ts` в `orders/[id]/`), що викликає `updateSaleStatus` з `createdBy`/сесією поточного staff, де це доречно (звірити, чи `updateSaleStatus` взагалі пише `updated_by` — якщо ні, не вигадувати нове поле без потреби).
- Дропдауни — `listNamedReference("order_statuses")` / `("payment_statuses")`, той самий виклик що вже є в інших формах.

## 5. Do not touch
- `addSale`/`addPurchase`/`addWriteoff`/mystery repo-функції — не займаємо.
- Жодної нової міграції — `updateSaleStatus` і довідники вже готові, схема не змінюється.
- `ncrm/middleware.ts`, `ncrm/lib/auth/*` — auth готовий (09a), не чіпати.
- Order-sync (`index.ts`), NCRM-14/NCRM-11 — інші файли, інший скоуп.
- Mobile/responsive-поліш — це тепер NCRM-15, окрема картка.

## 6. Likely files / areas
- `ncrm/app/orders/[id]/page.tsx`
- Новий: `ncrm/app/orders/[id]/status-form.tsx` (чи аналогічна назва)
- Новий/доповнений: server action у `ncrm/app/orders/[id]/actions.ts`
- Codex should verify against actual project files before writing.

## 7. Acceptance criteria
- [ ] Зміна лише `order_status` не чіпає `payment_status`/`ttn`/`note` (і навпаки) — партиальний update підтверджено.
- [ ] Тестове замовлення з активним mystery-резервом → `order_status → cancelled` → `fn_release_mystery_fulfillment` спрацьовує (звірити в Studio/psql, не лише "форма не впала").
- [ ] Порожній сабміт без змін не падає і не пише зайвих запитів.
- [ ] Дропдауни показують усі активні `order_statuses`/`payment_statuses` з довідника, не хардкод-список.
- [ ] `git diff` лише у файлах §6.

## 8. QA / smoke test (owner)
- [ ] Змінити статус на тестовому замовленні без mystery-резерву — переконатись, що просто працює.
- [ ] Змінити на `cancelled` замовлення з активним mystery-резервом — підтвердити автозвільнення (той самий тест, що не вдалось зробити при закритті NCRM-09e).
- [ ] Перевірити `ttn`/`note` окремо від статусів.

## 9. Rollback note
Чиста UI-надбудова, без міграцій і без змін existing repo-функцій — відкат = revert доданих файлів.

## 10. Recommended status after execution
NCRM-12 → «In progress» одразу після передачі Codex (за чергою власника, після NCRM-11); «Done» після QA §8, включно з підтвердженим mystery auto-release тестом.
