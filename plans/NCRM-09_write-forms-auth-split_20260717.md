# NCRM-09 — Write-форми + FIFO-COGS + auth: scope-план і розбиття

**Дата:** 2026-07-17 · **Автор:** Claude (strategic assistant) · **Задача:** NCRM-09, "Write-форми + FIFO-COGS"
**Пов'язано:** `context-index.md`, ROADMAP_FLOW картка `NCRM-09` в `dashboard/booster-dashboard.html`, Notion page_id `39a6bf20-bdb4-81da-81fc-c3bb866981b4` (`ROADMAP_SOP.md §5`), blocker NCRM-08 (CLOSED Done, 2026-07-17), `plans/NCRM-financial-model-v2_technical-contract_20260711.md §10`, `plans/NCRM-07b_rls-multiuser-role-model_20260715.md`
**Перевірено перед написанням:** `ncrm/supabase/migrations/0006`–`0010`, `ncrm/lib/repositories/{sales,purchases,writeoffs,products}.repo.ts`, `ncrm/lib/supabase/client.ts`, `ncrm/app/layout.tsx`, `ncrm/README.md`, обидва handoff NCRM-08, dashboard warn-нотатки NCRM-07b/08/09

## 1. Conclusion

Розбити NCRM-09 на три послідовні хендофи замість одного великого:

- **NCRM-09a — auth-фундамент** (owner/admin): Supabase Auth email/password, сесія, route-gate, seed owner-акаунта, `created_by` wiring в уже існуючі repo-мутації. Це тепер готовий Codex handoff (`handoffs/handoff_NCRM-09a_auth-foundation_20260717.md`).
- **NCRM-09b — форми продаж + закупка** (наступний хендоф, після QA на 09a): найчастіші операції, repo-шар для обох вже написаний.
- **NCRM-09c — форми списання + РРЦ + повернення + mystery box** (окремий хендоф пізніше): списання й РРЦ мають готовий repo-шар (легше), повернення й mystery box — ні (потрібен новий repo-код поверх наявних DB RPC).

Рішення власника (2026-07-17): підтверджено обидва пункти — розбити 09a/09b окремо, і всередині форм спершу продаж+закупка (09b), решта окремо (09c).

Чому саме так, а не один хендоф: auth — єдина справді нова, критична ділянка (сесії, логін, гейт доступу до фінансових write-операцій) — саме такі задачі в цьому репо завжди йдуть окремим фокусованим хендофом (див. NCRM-07b). Змішувати її в одному diff-і з шістьма формами внесення підвищує ризик і ускладнює review. Крім того, auth — жорсткий передумова для решти: без реальної сесії `created_by` неможливо проставити коректно (сьогодні цієї колонки взагалі ніхто не заповнює).

## 2. Task type

Scope-план (pre-handoff), аналогічно до `plans/NCRM-07b_...md`. Не патч. Розблоковує написання NCRM-09a Codex handoff, який додається цим же сеансом.

## 3. Owner

Mixed: Claude (цей план + `bs-codex-handoff` для 09a), Codex (виконання 09a, потім 09b/09c після власних хендофів), Owner/Raccoon (QA кожного етапу, підтвердження перед стартом наступного).

## 4. Status

NCRM-09 (Notion + дашборд): `Not started` → `In progress` цим сеансом (робота реально почалась — scope-рішення прийнято, перший хендоф готовий). NCRM-09a/09b/09c — внутрішнє розбиття цього плану, окремих карток у Notion/дашборді не заводжу (як ST-2a/ST-2b — sub-scope живе в handoffs/, не в Notion).

## 5. Next action

1. Owner переглядає `handoffs/handoff_NCRM-09a_auth-foundation_20260717.md`, підтверджує старт.
2. Codex виконує 09a.
3. Claude review (`bs-codex-review`) + owner smoke-test (логін, route-gate, `created_by` на тестовому sale/purchase/writeoff).
4. Після підтвердження — Claude пише хендоф NCRM-09b (продаж+закупка форми).
5. NCRM-09c (списання/РРЦ/повернення/mystery) — окремим хендофом після 09b.

## 6. Codex handoff

Готовий і відправлений окремим файлом: `handoffs/handoff_NCRM-09a_auth-foundation_20260717.md`. Тут — лише знахідка, важлива для 09a і майбутніх 09b/09c:

**Repo-шар для мутацій частково вже існує** (написаний раніше, ще без auth/UI, орфанний — нічого його не викликає):

| Форма | Repo-функція | Стан |
|---|---|---|
| Продаж | `sales.repo.ts` → `addSale`, `updateSaleStatus` | Готово, без `created_by` |
| Закупка | `purchases.repo.ts` → insert `purchases`+`purchase_lots`, update lot | Готово, без `created_by` |
| Списання | `writeoffs.repo.ts` → `addWriteoff` | Готово, без `created_by` |
| РРЦ | `products.repo.ts` → `updateRrc` (upsert `product_prices`) | Готово, `created_by` не застосовується (немає колонки на `product_prices`) |
| Повернення | — | Немає `refunds.repo.ts`, треба писати з нуля (09c) |
| Mystery box | — | Немає repo-обгортки над `fn_reserve_mystery_fulfillment`/`fn_commit_mystery_fulfillment` (09c) |

FIFO-COGS сам розрахунок **не потрібно писати** — тригери `fn_fix_sale_cogs`/`fn_fix_new_sale_item`/`fn_fifo_cost_for_product` з `0006` вже спрацьовують автоматично на insert у `sale_items`. Форми NCRM-09b/09c мають лише коректно вставляти рядки — DB рахує COGS сама.

## 7. QA checklist

- [ ] `handoffs/handoff_NCRM-09a_auth-foundation_20260717.md` існує і відповідає 10-секційному формату `bs-codex-handoff`.
- [ ] `context-index.md` — рядок NCRM-09 оновлено (посилання на план + 09a handoff).
- [ ] Дашборд (обидві копії, той самий сеанс) — статус NCRM-09 → `in-progress`, warn-нотатка описує розбиття.
- [ ] Notion картка NCRM-09 — статус `In progress`, коментар з поясненням розбиття.
- [ ] Жодного коду не написано/не змінено цим планом — лише документи.

## 8. Risks

Це фінансове ядро CRM (продажі/закупки/собівартість) + перший реальний auth у застосунку, який раніше був повністю `service_role`-only без сесій. Головний ризик — не сам код 09a, а **що станеться, якщо 09b/09c почнуться до QA 09a**: без підтвердженого логіну/сесії `created_by` й гейт доступу можуть виявитись зламаними одразу в кількох формах замість однієї контрольованої точки. Тому Next action (§5) явно послідовний — не паралелити 09a з 09b/09c. Другий ризик — знайдений орфанний repo-код (§6): він писався раніше без auth-контексту, тому Codex у 09a має явно перевірити, чи ці функції взагалі відповідають поточній схемі (`0006`–`0010`) перед тим, як на них спиратись у 09b — сам NCRM-09a код не чіпає, лише додає `created_by`, але 09b-хендоф має це перевірити заново, не вважати готовим без перегляду.

---
*Owner-рішення зафіксовано в чаті 2026-07-17: розбити 09a/09b, і продаж+закупка (09b) окремо від списання/РРЦ/повернення/mystery (09c).*
