# Codex Handoff — DASH-001: Apps Script summary — warehouse_cost, asset_cost, asset_potential_profit

Date: 2026-06-19 | Tool: Codex | Apps Script only

---

## 1. Task ID
`DASH-001`

---

## 2. Context

Dashboard `booster-dashboard.html` вже читає з `summary` endpoint і очікує три поля:
```js
const warehouseCost = d.warehouse_cost         != null ? Number(d.warehouse_cost)         : null;
const assetCost     = d.asset_cost             != null ? Number(d.asset_cost)             : null;
const assetProfit   = d.asset_potential_profit != null ? Number(d.asset_potential_profit) : null;
```
Якщо поля відсутні — плитки показують `—`. Всі три зараз відсутні в `apiSummary_()`.

`apiSummary_()` вже існує в Apps Script і повертає `potential_profit_warehouse` (потенційний прибуток UA-складу). Це вже є — але потрібні додаткові поля.

Дані беруться з вкладки **`Закупки`**. Кожен лот має статус у колонці Q (або аналог — Codex перевіряє реальний заголовок).

Статуси лотів (з аудиту):
- **UA склад** (враховувати в warehouse_cost): `На складі`, `Частково продано`
- **Активи не в UA** (враховувати в asset_cost): `В дорозі`, `На складі в Японії`, `Виграно`
- **Пропускати**: `Продано`, `Скасовано`

Маржа % в дашборді: `profit / (cost * 1.05 + profit) * 100`

---

## 3. Goal

Додати три нові поля до відповіді `apiSummary_()`:

| Поле | Що рахує |
|------|----------|
| `warehouse_cost` | Собівартість лотів зі статусами `На складі` + `Частково продано` |
| `asset_cost` | Собівартість лотів зі статусами `В дорозі` + `На складі в Японії` + `Виграно` |
| `asset_potential_profit` | Потенційний прибуток по активах: сума `(ціна продажу - собівартість * 1.05) * qty_remaining` по тих самих лотах що в asset_cost |

---

## 4. What to change

> **Лише Apps Script — `apiSummary_()`. Без змін у Sheets структурі та dashboard.**

### 4a. Знайти `apiSummary_()` в Apps Script

Функція вже існує. Codex знаходить її і дивиться:
- як вона читає вкладку `Закупки` (або чи читає взагалі)
- які колонки використовує (особливо колонку статусу та колонку собівартості/ціни)

Якщо `apiSummary_()` вже читає `Закупки` (наприклад для `potential_profit_warehouse`) — **використати ту саму логіку читання**, не дублювати.

### 4b. Логіка `warehouse_cost`

```js
// Перебрати всі рядки Закупки
// Для кожного лота:
//   якщо статус IN ['На складі', 'Частково продано']:
//     warehouse_cost += cost_mgmt * qty_remaining
//     (де qty_remaining = qty_total - qty_sold, якщо є така колонка)
//     або qty_remaining = qty_total якщо окремої колонки немає
```

**Важливо:** Codex дивиться реальні заголовки `Закупки` перед тим як писати індекси колонок. Не вгадувати.

### 4c. Логіка `asset_cost`

```js
// Ті самі рядки Закупки
// якщо статус IN ['В дорозі', 'На складі в Японії', 'Виграно']:
//   asset_cost += cost_mgmt * qty_remaining
```

### 4d. Логіка `asset_potential_profit`

```js
// Для тих самих лотів що в asset_cost:
//   rrc = ціна продажу (РРЦ) з Майстер_Товарів по SKU, або з самого лота якщо є
//   adjusted_cost = cost_mgmt * 1.05
//   lot_profit = (rrc - adjusted_cost) * qty_remaining
//   якщо lot_profit > 0: asset_potential_profit += lot_profit
// Якщо РРЦ для SKU недоступна — пропустити лот (не рахувати)
```

Якщо в `apiSummary_()` вже є логіка `potential_profit_warehouse` — подивитись як вона рахується і застосувати аналогічний підхід для asset.

### 4e. Додати поля до return об'єкта

```js
return {
  // ... існуючі поля ...
  warehouse_cost: warehouseCostTotal,           // число або null
  asset_cost: assetCostTotal,                   // число або null
  asset_potential_profit: assetProfitTotal      // число або null
};
```

Якщо рядків з відповідними статусами немає — повернути `0`, не `null`.

---

## 5. Do not touch

- Існуючі поля `apiSummary_()` — не видаляти, не перейменовувати
- `potential_profit_warehouse` — не чіпати (вже є і працює)
- `doPost`, `doGet` routing — не чіпати
- Всі інші endpoints (`orders`, `stock_alerts`, `sku_list`, `channel_stats`, `monthly_summary`) — не чіпати
- Структура вкладок Sheets — не змінювати
- `dashboard/booster-dashboard.html` — не чіпати
- `sitemap.xml`, `robots.txt`, checkout, payment — не стосується

---

## 6. Likely files / areas

- **Apps Script** (CRM Google Sheet `1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`)
  - Функція `apiSummary_()` — додати три нові поля
  - Вкладки для читання: `Закупки`, можливо `Майстер_Товарів` (для РРЦ)
  - Codex перевіряє реальні заголовки обох вкладок перед написанням індексів

---

## 7. Acceptance criteria

- [ ] GET `?action=summary&token=...` повертає `warehouse_cost` як число (не null, не undefined)
- [ ] GET `?action=summary&token=...` повертає `asset_cost` як число
- [ ] GET `?action=summary&token=...` повертає `asset_potential_profit` як число
- [ ] `warehouse_cost` відповідає сумі cost_mgmt × qty лотів зі статусами `На складі` + `Частково продано`
- [ ] `asset_cost` відповідає сумі cost_mgmt × qty лотів зі статусами `В дорозі` + `На складі в Японії` + `Виграно`
- [ ] Існуючі поля summary (sales, profit, pending_orders тощо) — без змін
- [ ] `potential_profit_warehouse` — без змін
- [ ] Дашборд після оновлення показує числа замість `—` у трьох плитках

---

## 8. QA / smoke test

1. Apps Script → Deploy → Test або скопіювати Web App URL
2. GET `?action=summary&token=<TOKEN>` → перевірити JSON відповідь:
   ```json
   {
     "warehouse_cost": 12345,
     "asset_cost": 67890,
     "asset_potential_profit": 5432,
     ...
   }
   ```
3. Порівняти `warehouse_cost` з ручним підрахунком у вкладці `Закупки` (фільтр по статусах)
4. Відкрити дашборд → Огляд → три плитки показують числа (не `—`)
5. Перевірити Executions в Apps Script — немає помилок

---

## 9. Rollback note

- `apiSummary_()` — зберегти копію функції в коментарі або окремому .gs файлі перед змінами
- Дашборд: якщо поля відсутні — показує `—` (безпечний fallback, вже реалізований)
- Sheets структура не змінюється

---

## 10. Recommended status after execution

Codex повертає звіт з: назвами змінних, логікою читання колонок, результатом тестового GET.
Owner перевіряє числа в дашборді і порівнює з Закупками вручну → якщо ok → `DASH-001` = Done.
