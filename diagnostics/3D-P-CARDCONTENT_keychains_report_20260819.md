# Report — WP3 rev 2 `3D-P-CARDCONTENT`: п'ять брелоків, подвійна категорія + sort_order 8

Date: 2026-08-19 | Task: `3D-P-CARDCONTENT` | Виконавець: Claude Code
Патч: `patches/3D-P-CARDCONTENT_products-keychains_20260819.php`
Замінює: `..._20260818.php` — **видалено**, ніколи не запускалося
Попередній звіт: `diagnostics/3D-P-CARDCONTENT_keychains_report_20260818.md`

**Статус: не задеплоєно.** Передумови вже виконані: WP1 етап 1 (категорії
71–74) і WP2 (атрибути 50–55) відпрацювали 2026-08-19.

---

## 1. Перевірка сортування — головне, що я мав з'ясувати

Завдання просило підтвердити, що дефолтне сортування — `p.sort_order ASC`, і
**зупинитися**, якщо збірка це перевизначає. Відповідь: дефолт саме такий, але
він **не є первинним ключем сортування**. Це не no-op, тому я не зупиняюсь — але
причина, чому 8 працює, інша, ніж передбачає завдання.

### 1.1 Що каже код

`catalog/controller/product/category.php` — дефолти на місці:

```php
line 29-33:  if (isset($this->request->get['sort'])) { $sort = ...; }
             else { $sort = 'p.sort_order'; }
line 35-39:  if (isset($this->request->get['order'])) { $order = ...; }
             else { $order = 'ASC'; }
```

`config_product_sort` і `config_product_order` у таблиці `setting` **відсутні** —
підтверджено, як і сказано в завданні.

Але `catalog/model/catalog/product.php:285` — **ця збірка не стокова**:

```php
$stock_priority = "(CASE
    WHEN (`p`.`quantity` > 0 OR `p`.`subtract` = 0) THEN 1
    WHEN (`p`.`subtract` = 1 AND `p`.`quantity` <= 0 AND `p`.`stock_status_id` = 8) THEN 2
    ELSE 3 END)";
...
$sql .= " ORDER BY " . $stock_priority . " ASC, `p`.`sort_order`";   // рядок 296
$sql .= " ASC, LCASE(`pd`.`name`) ASC";                              // рядок 303
```

Фактичний порядок у лістингу категорії:

```
ORDER BY <stock tier> ASC, p.sort_order ASC, LCASE(pd.name) ASC
```

Це прямий правок ядра, не OCMOD: у бекапі немає ані каталогу
`storage/modification/`, ані модифікації цього файлу. `.ocmod.zip` у
`storage/marketplace/` — це встановлені розширення, вони цей файл не переписують.

### 1.2 Що з цього випливає для наших товарів

П'ять брелоків мають `quantity = 0`, `subtract = 1`, `stock_status_id = 8` →
**tier 2**. Тобто вони стоять після **всіх** товарів у наявності, скільки б не
було в `sort_order`.

Фактичні `sort_order` у батьківських категоріях (за бекапом):

| Категорія | наявні `sort_order` | tier 1 | tier 2 | tier 3 |
|---|---|---|---|---|
| 59 Pokémon | 0…3 | 20 | 10 | 1 |
| 60 One Piece | 0…2 | 5 | 9 | 5 |
| 70 Аксесуари | 1 | 8 | — | 1 |

**8 все одно робить справжню роботу**, у двох місцях:

1. усередині tier 2 наявні товари мають `sort_order` 0…3, тому 8 ставить 3D
   останніми в межах свого тиру;
2. якщо власник згодом поставить їх у наявність, вони перейдуть у tier 1 — і там
   8 знову поставить їх після наявних 0…3.

Обидва ефекти діють в один бік, тому **8 — правильне значення**. Але поки товари
в передзамовленні, позицію вирішує **тир, а не 8**.

### 1.3 Перевірено на даних, а не в голові

Реальний `ORDER BY` проти реальних рядків, `/catalog/Pokemon` (фільтр `status`
знято, щоб показати майбутній стан):

```
tier so st  model                 name
1    0  1   PKM-EN-CHRS-BST       Бустер Pokémon TCG: Mega Evolution — Chaos…
…    …  …   (20 товарів tier 1, sort_order 0…2)
2    0  1   PKM-JP-SVEX-BLR       Набір Pokémon S&V ex Special Set…
…    …  …   (10 товарів tier 2, sort_order 0…2)
2    8  0   BR-BULB-100           Брелок Бульбазавр (Pokémon) — 3D-друк
2    8  0   BR-MEW-100            Брелок Мью (Pokémon) — 3D-друк
2    8  0   BR-PIKA-100           Брелок Пікачу (Pokémon) — 3D-друк
2    8  0   BR-SQUIR-100          Брелок Сквіртл (Pokémon) — 3D-друк
2    8  0   BR-CHARM-100          Брелок Чармандер (Pokémon) — 3D-друк
2    8  0   FIG-GEOD-511          Фігурка Геодуд (Pokémon) — 3D-друк
…    …  …   (решта фігурок WP4)
3    3  1   PKM-JP-OUTL-BST       Бустер Pokémon TCG: Outlet Mix…
```

Два уточнення до формулювань завдання:

- **«Останніми» — не зовсім.** Після 3D-товарів іде tier 3 (наприклад
  `PKM-JP-OUTL-BST`, `sort_order = 3`). 3D сідають у кінець tier 2, не в кінець
  списку.
- **«Нейтральні один щодо одного» — теж не зовсім.** Третій ключ сортування —
  `LCASE(pd.name) ASC`, тому всередині спільного `sort_order = 8` вони йдуть
  **за абеткою назви**. Це детерміновано й нешкідливо, але не «нейтрально»:
  порядок задає назва.

### 1.4 Знахідка, яка робить другий рядок категорії обов'язковим

`catalog/controller/product/category.php`, рядки 201 і 246, передають у модель
жорстко:

```php
'filter_sub_category' => false
```

Тобто лістинг батьківської категорії показує **тільки** товари, прив'язані до неї
напряму, і ніколи не підтягує товари підкатегорій. Без рядка на батька
брелоки **взагалі не з'явилися б** у `/catalog/Pokemon`.

Це підтверджує рішення власника від 2026-08-19 з технічного боку: другий рядок
несе навантаження, а не косметику.

---

## 2. Діф проти версії 20260818

Змінено рівно чотири речі. Тексти, SKU, слаги, атрибути, ціни, видимість,
поля доставки й гейт анкора `BR-CHARM-200` — **байт у байт як у рев'юнутій
версії**.

| # | Що | Було | Стало |
|---|---|---|---|
| 1 | `PATCH_NAME` | `..._20260818` | `..._20260819` |
| 2 | нова константа | — | `const SORT_ORDER = 8;` |
| 3 | `ocp5_product.sort_order` | літерал `1` у SQL | зв'язаний параметр `SORT_ORDER` |
| 4 | `ocp5_product_to_category` | 1 рядок (73) | **2 рядки (73 + 59)** |

Плюс посилені перевірки:

```php
// було
if (count($categories) !== 1 || (int) $categories[0]['category_id'] !== $categoryId) {
    bs_fail('One product = one category rule broken …');
}

// стало
$haveCat = [...];            // фактичні, ORDER BY category_id
$wantCat = $categoryIds;     // [підкатегорія, батько]
sort($wantCat);
if ($haveCat !== $wantCat) {
    bs_fail($product['sku'] . ' is in categories [' . implode(',', $haveCat)
        . '], expected exactly [' . implode(',', $wantCat) . '] (subcategory + parent) — rolling back');
}
```

і нова перевірка `sort_order`:

```php
if ((int) $row['sort_order'] !== SORT_ORDER) {
    bs_fail($product['sku'] . ' has sort_order ' . $row['sort_order']
        . ', expected ' . SORT_ORDER . ' — rolling back');
}
```

**ID батька не захардкоджений удруге.** Патч бере його з того самого рядка
категорії, який уже звіряє проти `TARGET_PARENT_ID`:

```php
$assignCategories = [$categoryId, (int) $categoryRows[0]['parent_id']];
if (count(array_unique($assignCategories)) !== 2) bs_fail('Subcategory and parent resolved to the same id — stopping');
```

Усі попередні гарантії лишилися на місці: `bs_bind_guard`, `php -l`, JSON-бекапи,
транзакція з відкотом, перевірка `NULL` у `upc/ean/jan/isbn/mpn`, заборона
атрибутів із чужої групи, перевірка `rating = 0` і `status = 0`, ідемпотентність.
Рядок типів `bind_param` розширено з `ssiiisississsii` (15) до
`ssiiisississsiii` (16) під новий параметр — `bs_bind_guard` це перевіряє на
кожному запиті.

---

## 3. Прогін на локальній копії **поточного** стану

База відтворена не з бекапу «як є», а з фактичного стану продакшену на
2026-08-19: дамп 08-16 → WP1 етап 1 → **ручне пересортування власника** → WP2.

Стан аксесуарів перед запуском, відтворений точно як описано:

```
95,96,97,98,100,112,113,114 -> 70,71
99                          -> 70,71,72
cat 70 = 9, cat 71 = 9, cat 72 = 1
```

Результат WP3:

```
category_verified=73 «Фігурки та декор» parent=59
assign_categories=73 + 59 (subcategory + parent)
sort_order=8
created_product=BR-MEW-100   (id=125) … BR-PIKA-100 (id=129)
created_products=5   attribute_rows_written=65
```

Контрольні запити після прогону:

| Перевірка | Результат |
|---|---|
| Кожен із 5 товарів має рівно 2 рядки категорій | ✔ `59,73` у всіх |
| `sort_order` | ✔ `8` у всіх |
| `status` / `rating` | ✔ `0` / `0` |
| `upc/ean/jan/isbn/mpn` | ✔ усі `NULL` |
| 13 атрибутів, 0 із чужої групи | ✔ |
| **Стан аксесуарів власника** | ✔ **не змінився**: `95…114 -> 70,71`, `99 -> 70,71,72` |
| Повторний запуск | ✔ `already_applied=yes`, self-delete |

---

## 4. Rollback

Фактичні ID — `_patch_backups/<patch>-<ts>/db/created_ids.json`
(тепер містить також `assigned_category_ids` і `sort_order`).
**Очікувані: 125…129** — підтверджено прогоном.

```sql
DELETE FROM ocp5_product_attribute   WHERE product_id IN (125,126,127,128,129);
DELETE FROM ocp5_seo_url             WHERE `key` = 'product_id' AND `value` IN ('125','126','127','128','129');
DELETE FROM ocp5_product_code        WHERE product_id IN (125,126,127,128,129);
DELETE FROM ocp5_product_to_category WHERE product_id IN (125,126,127,128,129);  -- знімає ОБИДВА рядки (73 і 59)
DELETE FROM ocp5_product_to_store    WHERE product_id IN (125,126,127,128,129);
DELETE FROM ocp5_product_description WHERE product_id IN (125,126,127,128,129);
DELETE FROM ocp5_product             WHERE product_id IN (125,126,127,128,129);
```

Видалення за `product_id` знімає обидва рядки категорій одразу. Жоден наявний
рядок `product_to_category` не чіпається, тому ручний стан аксесуарів
(`70+71`, у 99 — `70+71+72`) відкат не зачіпає.

---

## 5. Припущення, які довелося зробити

1. **`sort_order = 8` подано як факт, не як гіпотезу — але його ефект інший, ніж
   у формулюванні завдання.** Розділ 1. Я не зупинився, бо це не no-op; але якщо
   мета була «показати 3D раніше за щось», tier 2 цього не дасть, доки товари в
   передзамовленні.
2. **Порядок усередині 3D — за абеткою назви**, не нейтральний (розділ 1.3).
   Якщо потрібен інший порядок між самими 3D-товарами, `sort_order` має
   відрізнятися по SKU — скажіть, і це одна правка в масиві.
3. Решта припущень — без змін проти рев'ю 18.08: `quantity = 0` /
   `stock_status_id = 8`, ціна-заглушка `1.00`, alt-тексти не записуються темою.

---

## 6. Owner QA — зміни проти чеклиста 18.08

Додано:

- [ ] кожен із п'яти брелоків показується **і** в `/catalog/Pokemon`, **і** в
      `/catalog/Pokemon/figurky-ta-dekor-pokemon`;
- [ ] у картці товару обидві категорії відмічені в адмінці (Links → Categories);
- [ ] `sort_order` товару = `8` (Data → Sort Order);
- [ ] у лістингу `/catalog/Pokemon` 3D-товари стоять у кінці блоку
      передзамовлення — **перед** товарами tier 3, не в самому кінці сторінки.

Решта чеклиста §6 хендофу — без змін, разом із двома відомими обмеженнями:
порядок рядків атрибутів (звіт WP2, розділ 3) і alt-тексти (розділ 4.2 звіту
18.08).

---

## 7. Борги — без змін

`CRM-005` (SKU у CRM до ввімкнення), логіка передзамовлення, ціни, фото,
alt-тексти, `bs-merchant-schema-qa`, `bs-seo-risk-gate`, sitemap.
**Notion пише Claude (chat), не я.**
