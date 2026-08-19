# Report — WP1 `3D-P-002`: чотири підкатегорії + перенесення дев'яти аксесуарів

Date: 2026-08-18 | Task: `3D-P-002` | Виконавець: Claude Code
Патч: `patches/3D-P-002_catalog-subcategories_20260818.php`
Хендоф: `handoffs/handoff_3D-P-002_subcategories-and-content_20260816.md` (ред. 2)
Preflight: `diagnostics/3D-P-002_3D-P-CARDCONTENT_db-preflight_20260818.md`

**Статус: не задеплоєно.** Патч лежить у `patches/`, заливає й запускає власник.

---

## 1. Що звірено з бекапом і що фактично знайдено

Джерело: `backup-8.16.2026_08-03-55_boosters.tar.gz`, `mysql/boosters_ocart49.sql`,
префікс `ocp5_`. Повна доказова база — у preflight-документі; тут підсумок.

| Перевірка (розділ 7.1 хендофу) | Знайдено |
|---|---|
| Чи існують уже чотири підкатегорії | **Ні.** `ocp5_category` має 11 рядків (59–68, 70), `AUTO_INCREMENT = 71`, під `70` підкатегорій немає |
| Батьківські ID | **59 / 60 / 70** підтверджені за назвою в `category_description` |
| `product_to_category` для дев'яти | рівно 9 рядків у категорії `70`: 95, 96, 97, 98, 99, 100, 112, 113, 114 — і **по одному рядку на товар**, дубляжу немає |
| Розподіл 8 / 1 | зіставлено поіменно з розділом 4 хендофу — збігається повністю |
| Колізії `seo_url` | **0** для всіх чотирьох слагів, перевірено і точний збіг, і збіг останнього сегмента |
| `category_to_layout` | **таблиця порожня** — патч її не чіпає |

### 1.1 Знахідка, яка змінила зміст патча проти букви хендофу

Хендоф, розділ 5, задає SEO URL підкатегорії голим слагом
(`protektory-ta-zberihannia`). На цій збірці це дало б неправильний URL.

`catalog/model/design/seo_url.php` шукає ключ так:

```sql
WHERE (`keyword` = '<сегмент>' OR `keyword` LIKE '%/<сегмент>') AND store_id = 0
```

а `catalog/controller/startup/seo_url.php` ріже маршрут по `/` і шукає кожен
сегмент окремо. Тому підкатегорія зберігає в `keyword` **повний шлях**. Усі
наявні підкатегорії саме такі: `Pokemon/Pokemon-booster-box`,
`more-tcg/Yu-Gi-Oh`, `One-Piece/One-Piece-Boosters`.

Патч пише повний шлях, а слаг із хендофу використовує як останній сегмент:

| Категорія | Фактичний URL |
|---|---|
| Протектори та зберігання | `/catalog/acsesuary/protektory-ta-zberihannia` |
| Підставки та декор | `/catalog/acsesuary/pidstavky-ta-dekor` |
| Фігурки та декор (Pokémon) | `/catalog/Pokemon/figurky-ta-dekor-pokemon` |
| Фігурки та декор (One Piece) | `/catalog/One-Piece/figurky-ta-dekor-one-piece` |

Ключ батька не захардкоджений — патч читає його з бази й **зупиняється**, якщо
той не збігається з очікуваним.

---

## 2. Що робить патч — по таблицях

Запис лише в ці таблиці, лише нові рядки, крім `product_to_category`:

| Таблиця | Дія | Рядків |
|---|---|---|
| `ocp5_category` | INSERT | 4 |
| `ocp5_category_description` | INSERT (`language_id = 4`) | 4 |
| `ocp5_category_path` | INSERT — `(child, parent, 0)` + `(child, child, 1)` | 8 |
| `ocp5_category_to_store` | INSERT (`store_id = 0`) | 4 |
| `ocp5_seo_url` | INSERT (`key = 'path'`) | 4 |
| `ocp5_product_to_category` | **UPDATE** рівно 9 наявних рядків | 9 |

Створювані категорії:

| Назва | parent | sort_order | status |
|---|---|---|---|
| Протектори та зберігання | 70 | 1 | **1** (Enabled) |
| Підставки та декор | 70 | 2 | **1** (Enabled) |
| Фігурки та декор | 59 | 4 | **0** (Disabled) |
| Фігурки та декор | 60 | 2 | **0** (Disabled) |

`sort_order` дібрано так, щоб не колідувати з наявними дітьми (59 має 1/2/3,
60 має 1). Дві однойменні «Фігурки та декор» під різними батьками — свідоме
рішення хендофу, слаги різні.

**Контент.** Тексти, Meta Title/Description/Keywords і FAQ узяті з розділу 5
хендофу **дослівно**. Опис зібрано як `<h2>` + абзаци + FAQ-акордеон у тій самій
розмітці, що вже стоїть у категоріях 60–68 (`<section class="bs-faq-accordion">`,
`bs-faq-icon`, `hidden`), і збережено HTML-ентіті (`&lt;`, `&quot;`) —
конвенція цієї бази. Апостроф лишається сирим `'`: `&#039;` у базі не
трапляється жодного разу, тому кодування — `ENT_COMPAT`, не `ENT_QUOTES`.

### 2.1 Два етапи — це гейт розділу 4.1, а не зручність

Хендоф вимагає перенести **один** товар, переконатися, що його URL не змінився,
і лише тоді рухати решту вісім. Патч не може відкрити живий URL, тому гейт
відданий власнику:

```bash
php 3D-P-002_catalog-subcategories_20260818.php
```

створює чотири категорії, переносить **тільки** товар 99 (`ACC-005`, «Акрилова
підставка для карток»), друкує URL для перевірки і **не видаляє себе**.

Після перевірки:

```bash
php 3D-P-002_catalog-subcategories_20260818.php --move-remaining
```

переносить решту вісім і видаляє файл. Обидва етапи ідемпотентні.

### 2.2 Доказ незалежності URL товару від категорії

Крім живої перевірки власником, патч доводить це на рівні бази: для кожного
товару він знімає рядок `seo_url` до і після `UPDATE` і **відкочує транзакцію**,
якщо хоч байт відрізняється. На локальному прогоні всі дев'ять рядків
побайтово незмінні.

---

## 3. Прогін на локальній копії бази

Патч не «прочитаний очима» — він виконаний на локальному MySQL 8.4.3, куди
залито той самий дамп (178 таблиць). Результат:

```
created_category=protektory-ta-zberihannia  (id=71, parent=70, status=1)
created_category=pidstavky-ta-dekor         (id=72, parent=70, status=1)
created_category=figurky-ta-dekor-pokemon   (id=73, parent=59, status=0)
created_category=figurky-ta-dekor-one-piece (id=74, parent=60, status=0)
moved_product=99 -> 72 | /product/akrylova-pidstavka-dlya-kart unchanged
...
category_product_count: 71 = 8, 72 = 1
accessories_root_remaining=0
```

Повторний запуск етапу 1 → `already_applied=yes`, файл не видаляється.
Повторний запуск етапу 2 після завершення → `already_applied=yes` + self-delete.

---

## 4. Rollback

Фактичні ID пише сам патч у
`_patch_backups/<patch>-<ts>/db/created_ids.json`, стан «до» —
у `subcategories_before.json` і `stage2_products_before.json`.
**Очікувані ID: 71, 72, 73, 74** — підтверджено локальним прогоном, але в патчі
не захардкоджені.

**Крок 1 — повернути товари в корінь (безпечно завжди):**

```sql
UPDATE ocp5_product_to_category SET category_id = 70
 WHERE product_id IN (95,96,97,98,99,100,112,113,114)
   AND category_id IN (71,72);
```

**Крок 2 — видалити категорії (тільки після кроку 1):**

```sql
DELETE FROM ocp5_seo_url              WHERE `key` = 'path' AND `value` IN ('70_71','70_72','59_73','60_74');
DELETE FROM ocp5_category_to_store    WHERE category_id IN (71,72,73,74);
DELETE FROM ocp5_category_path        WHERE category_id IN (71,72,73,74) OR path_id IN (71,72,73,74);
DELETE FROM ocp5_category_description WHERE category_id IN (71,72,73,74);
DELETE FROM ocp5_category             WHERE category_id IN (71,72,73,74);
```

Якщо фактичні ID відрізнятимуться — узяти їх із `created_ids.json`.

**Відкат без SQL:** категорії вимикаються прапорцем `status`, перенесення
оборотне через адмінку. SQL потрібен лише для повного видалення.

---

## 5. На чому довелося зупинитися або зробити припущення

1. **Формат `keyword`** — розділ 1.1 вище. Це не припущення, а виправлення за
   доказом із коду; фіксую як свідоме розходження з буквою хендофу.
2. **`sort_order` нових категорій** хендоф не задає. Обрано 1/2 під «Аксесуарами»
   (дітей не було), 4 під Pokémon (зайняті 1–3), 2 під One Piece (зайнята 1).
   Змінюється в адмінці одним полем.
3. **Розділ 8 хендофу — не моє рішення.** Чи вмикати One Piece разом із Луффі,
   чи тримати вимкненою до `BR-OP*`, вирішує власник на кроці видимості. Патч
   створює обидві франшизні категорії `Disabled`.

---

## 6. Що власник має зробити

1. Залити патч у `~/public_html`, виконати **без аргументів**.
2. Відкрити `https://boostershop.website/product/akrylova-pidstavka-dlya-kart` —
   має віддати 200 за тим самим URL; хлібні крихти тепер
   `Аксесуари → Підставки та декор`.
3. Якщо URL змінився — **не запускати етап 2**, повернутися з цим.
4. Якщо все гаразд — виконати з `--move-remaining`.
5. Очистити кеш і компільовані шаблони OpenCart, зробити hard refresh.
6. Пройти чеклист розділу 9 хендофу.
7. Регенерація sitemap — окремий прохід, у цей патч не входить.

---

## 7. Борги, які цей патч не закриває

- `CRM-005` / `OPS-CRMINTEGRITY` — 19 нових SKU у CRM до ввімкнення видимості.
- Подвійний `path`-рядок категорії 61 (`Pokemon/bustery-pokemon` і
  `Pokemon-boosters` на одне значення `59_61`) — наявний дефект, не мій.
- Регенерація sitemap, `bs-merchant-schema-qa`, `bs-seo-risk-gate`.
- **Notion.** Claude Code не пише властивості та статуси. Якщо після деплою
  потрібна зміна статусу `3D-P-002` — це робить Claude (chat).
