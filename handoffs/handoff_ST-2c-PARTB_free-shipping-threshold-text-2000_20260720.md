# Handoff — ST-2c Part B: оновити текст порогу безкоштовної доставки 1500 → 2000

## 1. Task ID
ST-2c-PARTB_free-shipping-threshold-text-1500to2000 (контентна частина ST-2c; окремо від cutover).

## 2. Context
Реальний поріг безкоштовної доставки в налаштуваннях/коді вже **2000 грн** (закрито в ST-2c). Але тексти на сайті досі кажуть «1500 грн» — клієнт бачить розбіжність між текстом і фактичним правилом.

Аудит зроблено по повному бекапу `backup-7.19.2026_09-58-50_boosters.tar.gz` → `mysql/boosters_ocart49.sql` (знімок 2026-07-19). Префікс таблиць — `ocp5_`. Знайдено (снапшот, не жива БД):
- `ocp5_product_description` — **49** входжень «1500 грн» (44 повна фраза в тілі опису «безкоштовна доставка Новою Поштою від 1500 грн» + ~5 коротших у meta-описах «безкоштовна доставка від 1500 грн»);
- `ocp5_information_description`, `information_id=4` («Оплата і доставка») — **2** входження (тіло + meta);
- `ocp5_order_history` — «повернення коштів (1500 грн)» по замовленню #379 + згадка доставки в історії. **Історичні записи — не чіпати.**
- Варіанта «1 500» (з пробілом/нерозривним пробілом) у знімку нема — скрізь рівно `1500 грн`.

Числа з бекапу орієнтовні; на живій БД можуть трохи відрізнятись. Codex має звірити прямо по живій базі до і після.

## 3. Goal
У текстах товарів і сторінки «Оплата і доставка» замінити поріг `1500 грн` → `2000 грн`, не зачепивши історію замовлень і жодних сум замовлень. Після зміни в цих двох таблицях не лишається жодного «1500 грн».

## 4. What to change
Точковий SQL з підстрокою, а не ручна адмінка (забагато місць) і не сліпий глобальний replace (зачепив би `order_history`). Заміна тільки підрядка `1500 грн` → `2000 грн`:

```sql
-- 1) описи товарів
UPDATE `ocp5_product_description`
   SET name             = REPLACE(name,'1500 грн','2000 грн'),
       description      = REPLACE(description,'1500 грн','2000 грн'),
       meta_title       = REPLACE(meta_title,'1500 грн','2000 грн'),
       meta_description = REPLACE(meta_description,'1500 грн','2000 грн'),
       meta_keyword     = REPLACE(meta_keyword,'1500 грн','2000 грн')
 WHERE description LIKE '%1500 грн%'
    OR meta_description LIKE '%1500 грн%'
    OR meta_keyword LIKE '%1500 грн%'
    OR meta_title LIKE '%1500 грн%'
    OR name LIKE '%1500 грн%';

-- 2) сторінка «Оплата і доставка» (information_id = 4)
UPDATE `ocp5_information_description`
   SET title            = REPLACE(title,'1500 грн','2000 грн'),
       description      = REPLACE(description,'1500 грн','2000 грн'),
       meta_title       = REPLACE(meta_title,'1500 грн','2000 грн'),
       meta_description = REPLACE(meta_description,'1500 грн','2000 грн'),
       meta_keyword     = REPLACE(meta_keyword,'1500 грн','2000 грн')
 WHERE information_id = 4;
```

Порядок дій: бекап двох таблиць → pre-count → UPDATE → post-count → очистити кеш OpenCart (`system/storage/cache/*`, `system/storage/modification/*` за потреби). Codex має підтвердити реальну назву префікса і колонок по живій схемі перед запуском (список колонок вище — за стоковою OC4, звірити).

## 5. Do not touch
- `ocp5_order_history` та будь-які таблиці замовлень (`order`, `order_total`, `order_product`) — історичний «1500 грн» по #379 лишається як є.
- Суми, ціни, будь-які інші числа — міняється тільки підрядок `1500 грн`.
- Значення порогу в налаштуваннях доставки (вже 2000) — тільки читання.
- checkout / payment / Hutko / фіскалізація / Checkbox.
- `sitemap.xml`, `robots.txt`, canonical, редіректи, `.htaccess`.
- JSON-LD схема, Merchant feed (окрема перевірка налаштувань фіда — не в цій задачі).
- Схема БД (жодних ALTER), кількість рядків у таблицях не змінюється.

## 6. Likely files / areas
- Жива БД OpenCart, таблиці `ocp5_product_description` і `ocp5_information_description` (information_id=4). Файли сайту не зачіпаються.
- Codex готує окремий датований SQL-раннер/скрипт з бекапом і readback (за патерном попередніх патчів). Список колонок — likely, не остаточний: звірити по живій схемі.

## 7. Acceptance criteria
- Pre-count (жива БД): зафіксувати кількість `1500 грн` у `ocp5_product_description` і `ocp5_information_description` (очікувано ~49 і ~2; допускається інша цифра — база могла змінитись).
- Після UPDATE: `SELECT` по всіх текстових колонках цих двох таблиць на `1500 грн` = **0**.
- `ocp5_order_history`: кількість `1500` **не змінилась** (запис по #379 цілий).
- Кількість рядків у `ocp5_product_description` і `ocp5_information_description` до і після — однакова (нічого не додано/видалено).
- Візуально на живому: 2–3 випадкові сторінки товарів показують «від 2000 грн»; сторінка `index.php?route=information/information&information_id=4` («Оплата і доставка») показує 2000, HTML-розмітка (`<li>`, `<strong>`) ціла.

## 8. QA / smoke test
- Відкрити 3 випадкові товарні сторінки + «Оплата і доставка» → скрізь 2000, текст не поламаний.
- Grep по свіжому дампу/живій БД: `1500 грн` у двох цільових таблицях відсутній, в `order_history` присутній.
- Це контент/SEO-правка індексованих сторінок, але без структурних змін (sitemap/canonical/schema не чіпаються) — ризик низький. Checkout/оплату не чіпає, тож `bs-checkout-smoke` не потрібен.

## 9. Rollback note
Перед UPDATE — `mysqldump` двох таблиць `ocp5_product_description` і `ocp5_information_description` у датований файл (напр. `_db_backups/ST-2c-PARTB_20260720/`). Відкат = відновити ці дві таблиці з цього дампу + очистити кеш OpenCart. Інші таблиці не бекапляться, бо не змінюються.

## 10. Recommended status after execution
«На перевірці» — не Done, поки власник не гляне на 2–3 живі товарні сторінки й сторінку «Оплата і доставка», що там 2000 і текст цілий.
