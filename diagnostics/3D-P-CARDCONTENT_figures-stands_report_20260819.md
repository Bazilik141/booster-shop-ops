# Report — WP4 rev 2 `3D-P-CARDCONTENT`: 7 фігурок + 7 підставок, подвійна категорія + sort_order 8

Date: 2026-08-19 | Task: `3D-P-CARDCONTENT` | Виконавець: Claude Code
Патч: `patches/3D-P-CARDCONTENT_products-figures-stands_20260819.php`
Замінює: `..._20260818.php` — **видалено**, ніколи не запускалося
Попередній звіт: `diagnostics/3D-P-CARDCONTENT_figures-stands_report_20260818.md`

**Статус: не задеплоєно.** Запускати після WP3.

---

## 1. Перевірка сортування

Повний розбір — у звіті WP3, розділ 1. Стисло, бо висновок спільний для обох
патчів:

- `catalog/controller/product/category.php:32` → `$sort = 'p.sort_order'`,
  рядок 38 → `$order = 'ASC'`. Дефолт саме такий, як припускало завдання.
- `config_product_sort` / `config_product_order` у `setting` **відсутні** —
  підтверджено.
- **Але** `catalog/model/catalog/product.php:285` додає перед ним тир наявності
  (прямий правок ядра, не OCMOD — каталогу `storage/modification/` у бекапі
  немає):

```
ORDER BY (CASE WHEN (p.quantity > 0 OR p.subtract = 0) THEN 1
               WHEN (p.subtract = 1 AND p.quantity <= 0 AND p.stock_status_id = 8) THEN 2
               ELSE 3 END) ASC,
         p.sort_order ASC,
         LCASE(pd.name) ASC
```

14 товарів цієї хвилі — `quantity = 0`, `subtract = 1`, `stock_status_id = 8` →
**tier 2**, тобто після всіх наявних, незалежно від `sort_order`.

**8 не є no-op:** наявні `sort_order` — `0…3` у категорії 59, `0…2` у 60, `1` у
70, тому 8 ставить 3D останніми в межах тиру і залишить останніми, якщо товари
згодом перейдуть у tier 1. Тому патч відвантажується, а не зупиняється.

Два уточнення до формулювань завдання:

- 3D-товари сідають у **кінець tier 2**, а не в кінець сторінки — після них іде
  tier 3 (наприклад `PKM-JP-OUTL-BST`, `sort_order = 3`).
- Усередині спільного `sort_order = 8` вони впорядковані **за абеткою назви**
  (`LCASE(pd.name) ASC`), а не «нейтрально».

### 1.1 Другий рядок категорії обов'язковий, а не косметичний

`catalog/controller/product/category.php`, рядки 201 і 246, передають
`'filter_sub_category' => false` жорстко. Лістинг батьківської категорії
показує лише товари, прив'язані напряму, і **ніколи** не підтягує товари
підкатегорій. Без рядка на батька 14 товарів не з'явилися б у `/catalog/Pokemon`,
`/catalog/One-Piece` і `/catalog/acsesuary`.

---

## 2. Діф проти версії 20260818

Тексти, SKU, слаги, набори атрибутів, ціни, видимість, поля доставки, гейт
анкорів і всі статичні контент-перевірки — **байт у байт як у рев'юнутій
версії**. Змінено:

| # | Що | Було | Стало |
|---|---|---|---|
| 1 | `PATCH_NAME` | `..._20260818` | `..._20260819` |
| 2 | нова константа | — | `const SORT_ORDER = 8;` |
| 3 | `ocp5_product.sort_order` | літерал `1` | зв'язаний `SORT_ORDER` |
| 4 | `ocp5_product_to_category` | 1 рядок | **2 рядки: підкатегорія + батько** |

Фактичні призначення:

| SKU | Категорії |
|---|---|
| `FIG-ONIX-500`, `FIG-GEOD-511`, `FIG-MEW-100`, `FIG-PKBL-600` | **73 + 59** |
| `FIG-LUFFY-500`, `FIG-LUFFY-400`, `FIG-LUFFY-410` | **74 + 60** |
| `ACC-3D-PKM-110/120/130/200/300/700/710` | **72 + 70** |

Батьківський ID для кожної з трьох підкатегорій береться з рядка, який патч уже
звіряє проти `TARGET_CATEGORIES`, і не задається вдруге:

```php
$assignCategories[$key] = [$id, (int) $rows[0]['parent_id']];
if (count(array_unique($assignCategories[$key])) !== 2) {
    bs_fail('Subcategory and parent resolved to the same id for ' . $key . ' — stopping');
}
```

Посилені перевірки — ті самі, що у WP3: набір категорій має точно дорівнювати
`[підкатегорія, батько]`, плюс нова перевірка `sort_order === 8`. Обидві
відкочують транзакцію.

Збережено без послаблень: `bs_bind_guard`, `php -l`, JSON-бекапи
(`products_before`, `alt_texts`, `pending_anchors`, `created_ids`), транзакція,
перевірка `NULL` ідентифікаторів, точний набір атрибутів по хвилях, заборона
чужої групи, `rating = 0`, `status = 0`, ідемпотентність — і **гвардія
скасованих токенів** `FIG-PKBL-100`, `ACC-3D-PKM-140`, `ACC-3D-PKM-210`, `BGC`
як у контенті патча, так і в базі.

Рядок типів `bind_param` розширено з 15 до 16 символів
(`ssiiisississsiii`) під новий параметр.

---

## 3. Прогін на локальній копії **поточного** стану

База відтворена з фактичного продакшену на 2026-08-19: дамп 08-16 → WP1 етап 1 →
ручне пересортування власника → WP2 → WP3 rev 2 → WP4 rev 2.

```
category_verified=pokemon   -> id=73 «Фігурки та декор» parent=59 | assign=73+59
category_verified=one_piece -> id=74 «Фігурки та декор» parent=60 | assign=74+60
category_verified=stands    -> id=72 «Підставки та декор» parent=70 | assign=72+70
sort_order=8
retired_skus_absent=FIG-PKBL-100, ACC-3D-PKM-140, ACC-3D-PKM-210
created_product=FIG-ONIX-500 id=130 cats=73+59 … ACC-3D-PKM-710 id=143 cats=72+70
created_products=14   attribute_rows_written=182
category_product_count: pokemon(73)=9, one_piece(74)=3, stands(72)=8
```

Контрольні запити після прогону:

| Перевірка | Результат |
|---|---|
| Усі 14 мають рівно 2 рядки категорій, правильну пару | ✔ |
| `sort_order` | ✔ `8` у всіх 14 |
| `status` / `rating` | ✔ `0` / `0` |
| `upc/ean/jan/isbn/mpn` | ✔ усі `NULL` |
| 13 атрибутів, 0 із чужої групи, набори різні по хвилях | ✔ |
| **Стан аксесуарів власника** | ✔ **не змінився**: `95…114 -> 70,71`, `99 -> 70,71,72` |
| Підсумки категорій | 59 = 40, 60 = 22, 70 = 16, 71 = 9, 72 = 8, 73 = 9, 74 = 3 |
| Повторний запуск | ✔ `already_applied=yes`, self-delete |

`cat 72 = 8` — сім підставок плюс акрилова 99, як і вимагає чеклист хвилі
підставок. `cat 70 = 16` — дев'ять аксесуарів власника плюс сім підставок.

---

## 4. Rollback

Фактичні ID — `_patch_backups/<patch>-<ts>/db/created_ids.json`
(тепер із `assigned_category_ids` і `sort_order`).
**Очікувані: 130…143** — підтверджено прогоном.

```sql
DELETE FROM ocp5_product_attribute   WHERE product_id BETWEEN 130 AND 143;
DELETE FROM ocp5_seo_url             WHERE `key` = 'product_id' AND CAST(`value` AS UNSIGNED) BETWEEN 130 AND 143;
DELETE FROM ocp5_product_code        WHERE product_id BETWEEN 130 AND 143;
DELETE FROM ocp5_product_to_category WHERE product_id BETWEEN 130 AND 143;  -- знімає ОБИДВА рядки на товар
DELETE FROM ocp5_product_to_store    WHERE product_id BETWEEN 130 AND 143;
DELETE FROM ocp5_product_description WHERE product_id BETWEEN 130 AND 143;
DELETE FROM ocp5_product             WHERE product_id BETWEEN 130 AND 143;
```

⚠ `BETWEEN` вірний лише якщо ID справді підряд — звірити з `created_ids.json`.
Жоден наявний рядок `product_to_category` не чіпається, тому ручний стан
аксесуарів відкат не зачіпає.

---

## 5. Припущення, які довелося зробити

1. **Ефект `sort_order = 8` інший, ніж описано в завданні** — розділ 1. Не
   зупинявся, бо це не no-op, але позицію в лістингу зараз вирішує тир наявності.
2. **Порядок усередині 3D — за абеткою назви.** Якщо потрібен інший, `sort_order`
   має відрізнятися по SKU.
3. Решта — без змін проти рев'ю 18.08: ціна-заглушка `1.00` (у цій хвилі
   **рішення власника про ціну немає взагалі**), габарити доставки потребують
   підтвердження, `quantity = 0` / `stock_status_id = 8`, alt-тексти темою не
   записуються, чотири з семи підставок ніколи не друкувалися.

---

## 6. Owner QA — зміни проти чеклиста 18.08

Додано:

- [ ] чотири Pokémon-фігурки видно **і** в `/catalog/Pokemon`, **і** в
      `/catalog/Pokemon/figurky-ta-dekor-pokemon`;
- [ ] три вироби Луффі — **і** в `/catalog/One-Piece`, **і** в
      `/catalog/One-Piece/figurky-ta-dekor-one-piece`;
- [ ] сім підставок — **і** в `/catalog/acsesuary`, **і** в
      `/catalog/acsesuary/pidstavky-ta-dekor`;
- [ ] у кожній картці в адмінці відмічені обидві категорії;
- [ ] `sort_order` = `8` у всіх чотирнадцяти;
- [ ] у лістингу батьківських категорій 3D стоять у кінці блоку передзамовлення,
      **перед** товарами tier 3;
- [ ] ручний стан аксесуарів не змінився: `95–98,100,112–114` у `70+71`,
      `99` у `70+71+72`.

Решта чеклиста §6 обох хвиль — без змін, разом із відомими обмеженнями:
порядок рядків атрибутів (звіт WP2, розділ 3) і alt-тексти.

---

## 7. Борги — без змін

`CRM-005` (14 SKU у CRM до ввімкнення; перевірити, що `FIG-PKBL-100`,
`ACC-3D-PKM-140`, `ACC-3D-PKM-210` ніде не лишилися, а `201/202/711/712` не
заводяться), анкори після ввімкнення цілей, alt-тексти, оновлення канону SKU під
два розходження (розділ 2.4 звіту 18.08), ціни, фото, тестовий друк,
передзамовлення, `bs-merchant-schema-qa`, `bs-seo-risk-gate`, sitemap.
**Notion пише Claude (chat), не я.**
