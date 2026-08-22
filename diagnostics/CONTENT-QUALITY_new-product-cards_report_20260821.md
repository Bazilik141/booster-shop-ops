# Claude Code Report — CONTENT-QUALITY WP2: дев'ять нових карток (8 sealed TCG + 1 аксесуар)

Date: 2026-08-22 (файл названий `…_20260821` за §9 хендофу, разом із патчем)
Виконавець: Claude Code · model=Opus · thinking=high
Хендоф: `handoffs/handoff_CONTENT-QUALITY_card-content-patch_20260821.md` §3
Payload: `CONTENT-QUALITY_3D-card-fixes_20260821.md`, «Додаток 2026-08-21» + `diagnostics/CONTENT-QUALITY_corrections_20260821.md` (перекриває)
База фактів: `backup-8.21.2026_22-06-47_boosters.tar.gz` → `mysql/boosters_ocart49.sql`

⚠ `CONTENT-QUALITY` — робоча мітка. Roadmap-ID не заведений. Патч не торкається
Notion і не торкається `ROADMAP_FLOW`.

---

## 1. Scope

Дев'ять нових товарів, лише `INSERT`, жодного `UPDATE` наявних рядків.

На товар: `product`, `product_description` (`language_id = 4`),
`product_to_store` (`store_id = 0`), `product_code` (`SKU`), `seo_url`
(`key = 'product_id'`) і **два** рядки `product_to_category` — підкатегорія та
материнська, як у всіх 85 наявних товарів (corrections §1.1: 85 із 85).

## 2. Files touched

```
patches/CONTENT-QUALITY_new-product-cards_20260821.php   — патч (INSERT only)
```

## 3. Фактичні `product_id`

Локальний прогін проти копії бекапу 2026-08-21 дав `144…152`. На проді буде те
саме: `ocp5_product.AUTO_INCREMENT = 144` у дампі 2026-08-21.

| SKU | product_id (очікуваний) | категорії | атрибутів | SEO URL | ціна |
|---|---:|---|---:|---|---:|
| `ACC-007-400` | 144 | 70 + 71 | 5 | `albom-dlia-kartok-na-400-slotiv` | 550 |
| `YGO-JP-QCAC-BBX` | 145 | 66 + 65 | 9 | `YuGiOh-booster-box-Quarter-Century-Art-Collection` | 2800 |
| `PKM-JP-STES-BBX` | 146 | 59 + 62 | 9 | `Pokemon-booster-box-Storm-Emeralda` | 5900 |
| `PKM-JP-STES-BST` | 147 | 59 + 61 | 9 | `Pokemon-boosters-Storm-Emeralda` | 220 |
| `PKM-JP-INFX-BBX` | 148 | 59 + 62 | 9 | `Pokemon-booster-box-Inferno-X` | 6500 |
| `PKM-JP-TGTR-BBX` | 149 | 59 + 62 | 9 | `Pokemon-booster-box-The-Glory-of-Team-Rocket` | 12000 |
| `PKM-JP-TGTR-BST` | 150 | 59 + 61 | 9 | `Pokemon-boosters-The-Glory-of-Team-Rocket` | 320 |
| `YGO-JP-BETB-BBX` | 151 | 66 + 65 | 9 | `YuGiOh-booster-box-Beyond-the-Brave` | 2500 |
| `YGO-JP-BETB-BST` | 152 | 66 + 65 | 9 | `Yu-Gi-Oh-boosters-Beyond-the-Brave` | 75 |

Разом: 9 товарів, 18 рядків категорій, **77 рядків атрибутів**, 9 `seo_url`.
Фактичні id після запуску — у `_patch_backups/<patch>-<ts>/db/created_ids.json`
і в логу патча.

## 4. Категорія 61 резолвиться за ID — §3.3 хендофу

Підтверджено з бекапу: `ocp5_seo_url` має **два** рядки `key='path'` для
`value='59_61'` — `(1056, 'Pokemon/bustery-pokemon')` і
`(1068, 'Pokemon-boosters')`. Резолвер за keyword із патча `3D-P-002` на цьому
впав би.

Тому **всі сім категорій резолвляться за `category_id`** і звіряються за
`name` **і** `parent_id`:

```
category_verified=59 «Pokémon» parent=0 status=1
category_verified=61 «Бустери Pokémon» parent=59 status=1
category_verified=62 «Бустер бокси Pokémon» parent=59 status=1
category_verified=65 «Yu-Gi-Oh!» parent=66 status=1
category_verified=66 «Інші TCG» parent=0 status=1
category_verified=70 «Аксесуари» parent=0 status=1
category_verified=71 «Протектори та зберігання» parent=70 status=1
```

Дубль не чіпається. Патч рахує ці рядки до і після запису й **відкочується**,
якщо їхня кількість змінилась. Діагностика дубля — WP3.

## 5. Дзеркальовані технічні поля — §3.4, фактичні значення з бекапу

| Сусід | manufacturer | weight | wcls | L | W | H | lcls | sort_order |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| 50 `PKM-JP-MSYM-BST` | 11 The Pokémon Company | 10 | 2 | 15 | 7 | 1 | 1 | 1 |
| 56 `PKM-MEGA-BOX` | 11 | 300 | 2 | 150 | 150 | 50 | 2 | 1 |
| 83 `YGO-JP-QCAC-BST` | 13 Konami | 10 | 2 | 14 | 7 | 1 | 1 | 1 |
| 102 `YGO-JP-WPP5-BBX` | 13 | 250 | 2 | 14 | 9 | 8 | 1 | 1 |
| 112 `ACC-007-360` | 16 Generic | 125 | 2 | 200 | 200 | 15 | 2 | 1 |

Далі: `tax_class_id = 0`, `points = 0`, `shipping = 1`, `subtract = 1`,
`minimum = 1`, `rating = 0`, `master_id = 0`, `upc/ean/jan/isbn/mpn = NULL`,
`location/variant/override = ''`, `date_available` = дата запуску патча.

⚠ **Два box-сусіди суперечать один одному:** 56 має 150×150×50 при
`length_class_id = 2` (мм), 102 — 14×9×8 при `length_class_id = 1` (см).
Обидва віддзеркалені як є, за §3.4. Привести габарити боксів до одного вигляду —
окреме рішення власника; на невидимих товарах це поки ні на що не впливає.

## 6. Комерційні поля — §3.5

`status = 0` · `quantity = 0` · `stock_status_id = 8` («Передзамовлення»,
перевірено за назвою) · `image = ''` · `sort_order = 1` (дзеркало) · ціни з CRM
за таблицею в §3. Патч читає ці значення назад після вставки й відкочується
при розбіжності.

## 7. Атрибути — тільки наявні id, дослівні живі формулювання

Corrections §3.1: формулювання взяті з наявних товарів, не з чернетки.
Домінантність перевірена по всій базі:

| id | Значення | Скільки товарів уже так пишуть |
|---|---|---:|
| 12 Мова | `Японська (Japanese)` | 45 |
| 17 Стан | `Новий, нерозпакований (Sealed)` | 50 |
| 18 Походження, box | `Оригінальний sealed box в заводській плівці` | 6 |
| 18 Походження, pack | `Box / Case sourced (з оригінального боксу / кейсу)` | 19 |
| 19 Зважування | `Без зважування (Unweighed)` | 29 |
| 21 Тип пакування | `Sealed Booster Box` / `Sealed Booster Pack` | 11 / 24 |

Формулювання з чернетки («у заводському шрінку», «Без зважування») **не**
використані: у таблиці характеристик поруч зі старими товарами розбіжність була б
видно.

Набори:

- **box** (9): 12, 13, 14, 15, **16**, 17, 18, 20, 21 — без 19;
- **pack** (9): 12, 13, 14, 15, 17, 18, **19**, 20, 21 — без 16;
- **`ACC-007-400`** (5, група 9): 27, 28, 30, 33, 34.

Патч має статичну перевірку box/pack-симетрії і падає, якщо box отримає 19 або
pack отримає 16. Жоден атрибут, категорія чи виробник не створюється.

### 7.1 `ACC-007-400` має **п'ять** полів, а не шість

Рішення власника 2026-08-21: атрибут `29 Матеріал` не заводиться взагалі —
матеріал невідомий. Corrections §3.2 перелічують шість рядків, шостий — саме
`29` із плейсхолдером. Після зняття `29` лишається п'ять.

⚠ **Пункт 7 owner-QA в хендофі каже «шість полів»** — це число списане з живого
товару 112, який має `Матеріал`. Після рішення власника правильне число —
**п'ять**. Критерій приймання («`ACC-007-400` не має атрибута `29 Матеріал`»)
і рішення власника однозначні, тож патч пише п'ять і асертить відсутність 29.

## 8. Рішення по тексту — одне власника, два виконавця

### 8.1 QCAC / QCSR — рішення власника 2026-08-22 скасовує corrections §4.9

**Чинна редакція — payload, обидва фрагменти.**

```
body §2:  Офіційний пул налічує 100 типів карт: 40 Ultra Rare і 60 Super Rare.
          Для карт передбачені Secret Rare та Quarter Century Secret Rare
          варіанти, а частина позицій має альтернативні ілюстрації.

FAQ [3]:  Чи гарантується конкретна QCSR або альтернативна ілюстрація?
          Ні. Офіційна сторінка описує доступні варіанти карт, але не гарантує
          конкретну карту або варіант в окремому боксі.
```

`meta_keyword` лишається з токеном `QCSR QCAC`, як у payload.

**Історія рішення.** Corrections §4.9 зняли речення про QCSR з опису, бо
варіанти не підтвердились на офіційній сторінці Konami. Проміжна редакція цього
патча застосувала те саме правило до FAQ [3] тієї ж картки, щоб клієнтський
текст не припускав непідтверджений варіант. Обидві правки скасовані власником
2026-08-22.

**Що це узгоджує.** Живий товар 83 (`YGO-JP-QCAC-BST`, бустер того самого сету,
`status = 1`, індексується) уже описує QCSR у видимому тексті — з назвами карт і
номерами `QCAC-JP018/019/021` — і несе `dark magician girl qcsr`,
`blue-eyes white dragon qcsr`, `quarter century secret rare` у
`meta name="keywords"`. Після повернення payload-редакції бокс і бустер кажуть
про сет одне й те саме. Товар 83 не чіпаємо (хендоф §3.6).

Побічне уточнення до мого попереднього формулювання і до рев'ю: `meta_keyword`
**рендериться** — продуктова сторінка віддає `<meta name="keywords" content="…"/>`.
Для ранжування поле інертне, але воно є в HTML.

### 8.2 Апостроф — ASCII

Payload написаний з U+2019. Хвиля 2026-08-19 і тексти категорій використовують
ASCII `'`. Дев'ять нових карток записані з ASCII, як і WP1. Патч падає, якщо
U+2019 з'явиться в тексті.

### 8.3 Розмітка FAQ — поточна конвенція, не легасі товару 112

Товар 112 (альбом на 360) використовує стару розмітку
(`<div class="bs-faq-accordion">`, `h3.bs-faq-title`, `h4.bs-faq-question`, без
`data-bs-faq-id` і `role`). Її мають 12 товарів із 85. Поточна конвенція —
`<section class="bs-faq-accordion" data-bs-faq-id="prod-<sku>">` — у 71 із 85,
зокрема в усіх п'яти товарах-сусідах (50, 56, 83, 102) і в усій хвилі 125–143.

Дев'ять нових карток отримали **поточну** розмітку, включно з `ACC-007-400`.
Дзеркалення сусіда стосується технічних полів (§3.4), не розмітки.

## 9. Передпольотні перевірки — усі пройшли

| Перевірка | Результат |
|---|---|
| жоден із 9 SKU не існує в `ocp5_product_code` | 0 збігів |
| жоден із 9 SKU не існує як `ocp5_product.model` | 0 збігів |
| жоден із 9 `seo_url` keyword не резолвиться (точно або як хвіст шляху) | 0 збігів |
| категорії 59/61/62/65/66/70/71 існують, назви й `parent_id` збігаються | 7/7 |
| атрибути 12–21, 27, 28, 30, 33, 34 існують, назви й групи збігаються | 15/15 |
| `language_id = 4` існує і ввімкнена | `uk-ua`, status 1 |
| `store_id`: усі наявні рядки `product_to_store` — 0 | 85/85 |
| виробники 11 / 13 / 16 = `The Pokémon Company` / `Konami` / `Generic` | 3/3 |
| `stock_status_id = 8` = `Передзамовлення` | так |

Сусідні факти, які варто знати: `PKM-JP-INFX-BST` (товар 87) і
`YGO-JP-QCAC-BST` (товар 83) уже на сайті — це бустери тих самих сетів. Нові
SKU — бокси, не дублі. Товар 83 не чіпаємо (§3.6).

## 10. Локальний прогін

Патч виконаний по-справжньому проти локальної MySQL 8.4.3 з дампом бекапу
2026-08-21, **після** WP1, у тому самому порядку, що й на проді.

```
php_l=ok
content_guards=ok — 9 cards, one <h2> + one <strong> + 3 FAQ each, 9/5 attributes, ASCII apostrophes
language_verified=4 «uk-ua»
store_verified=every existing product_to_store row is store_id 0
manufacturers_verified=11=The Pokémon Company, 13=Konami, 16=Generic
stock_status_verified=8 «Передзамовлення»
attributes_verified=15 ids checked by id, name and group
preflight=9 SKUs and slugs are free
created_product=ACC-007-400      id=144  cats=70+71  attrs=5  /product/albom-dlia-kartok-na-400-slotiv
…
created_product=YGO-JP-BETB-BST  id=152  cats=66+65  attrs=9  /product/Yu-Gi-Oh-boosters-Beyond-the-Brave
created_products=9
attribute_rows_written=77
visibility=all nine created with status=0 — NOT visible, by design
done=ok
self_delete=ok
```

Кількість товарів у категоріях після вставки: 59→45, 61→16, 62→13, 65→7, 66→8,
70→17, 71→9.

Прочитано назад із бази: `status`/`quantity`/`stock_status_id` = 0/0/8 у всіх
дев'яти, `image` порожній, ціни збігаються з CRM, довжини `meta_description`
124 / 109 / 125 для трьох pack-SKU — тобто саме ті, що виправлені в
corrections §4.10.

## 11. php -l

```
No syntax errors detected in patches/CONTENT-QUALITY_new-product-cards_20260821.php
```

## 12. Idempotency

```
already_applied=yes
existing_product_ids={"ACC-007-400":144,…,"YGO-JP-BETB-BST":152}
done=ok
```

Часткове застосування теж покрите: вже наявні SKU пропускаються, решта
створюється, у лог іде `partially_applied`.

## 13. Rollback

```
_patch_backups/CONTENT-QUALITY_new-product-cards_20260821-<ts>/db/before.json
_patch_backups/CONTENT-QUALITY_new-product-cards_20260821-<ts>/db/created_ids.json
_patch_backups/CONTENT-QUALITY_new-product-cards_20260821-<ts>/db/rollback.sql
```

`rollback.sql` генерується **після** вставки з фактичними id і містить лише
`DELETE` по семи таблицях. Перевірено на живій копії: після відкату лічильники
повернулись рівно до початкових (85 товарів, 122 `seo_url`, 787 атрибутів,
169 `product_to_category`, 85 `product_to_store`, 84 `product_code`).
Порядок і повний SQL продубльовані в шапці патча.

⚠ **Дамп БД перед запуском — крок власника** (cPanel → Backup → Download a
MySQL Database Backup).

## 14. Run command (owner)

```bash
php CONTENT-QUALITY_new-product-cards_20260821.php
```

Запускати **після** WP1, із `~/public_html`. Патч самовидаляється після успіху.
Далі — очистити кеш OpenCart і скомпільовані шаблони.

## 15. Post-deploy QA (власник, §7 хендофу)

- [ ] Catalog → Products, фільтр за моделлю `PKM-JP-`: нові товари на місці,
      усі **невидимі**, ціни збігаються з CRM;
- [ ] те саме для `YGO-JP-` і `ACC-007-400` — разом дев'ять нових;
- [ ] `PKM-JP-STES-BBX` → Links: категорії `Pokémon` і `Бустер бокси Pokémon`;
- [ ] `YGO-JP-BETB-BBX` → Links: `Інші TCG` і `Yu-Gi-Oh!`;
- [ ] `ACC-007-400` → Attribute: **п'ять** полів, серед них немає `Матеріал`
      (у хендофі помилково «шість» — див. §7.1);
- [ ] `YGO-JP-BETB-BBX` → Attribute: немає `Додатковий вміст`;
- [ ] живі сторінки, яких патч не мав чіпати, віддають 200:
      `/catalog/Pokemon/bustery-pokemon`,
      `/catalog/acsesuary/protektory-ta-zberihannia`,
      `/product/albom-dlia-kartok-na-360-slotiv`;
- [ ] нові сторінки поки **не перевіряти на вітрині** — вони `status = 0` і
      мають віддавати 404, це нормально.

## 16. Side effects / risks

- **Зона ризику — БД, прод без staging.** Вставка йде однією транзакцією з
  перевіркою кожного товару всередині неї; будь-яка розбіжність відкочує все.
- **Фото немає.** `image` порожній у всіх дев'яти — власник додає перед
  увімкненням.
- **CRM-005.** Дев'ять SKU мають існувати в CRM до того, як товари стануть
  видимими.
- **`YGO-JP-QCAC-BBX` і товар 83** — той самий сет (бокс і бустер). Після
  рішення власника 2026-08-22 (§8.1) обидві картки описують QCSR однаково;
  розбіжність, яка була ризиком, знята. Товар 83 патч не чіпав. Лишається
  звірити числа, яких патч не торкається: 15 бустерів × 4 карти в боксі проти
  того, що написано на бустері.
- **Два жовті альбоми.** `ACC-007-360` (112) і новий `ACC-007-400` мають
  Meta Title, що відрізняються однією цифрою. Нове FAQ [1] розводить їх з боку
  400; зворотного посилання зі старої картки немає. Вхідні для
  `bs-keyword-map` / `SEO-008` (corrections §5.1).
- **`+1 Expansion Pack`** для `YGO-JP-BETB-BBX` не вказаний; атрибут `24`
  існує, якщо first-print колись підтвердиться.
- Смоук-тест checkout не потрібен: патч не торкається кошика, оплат, схеми,
  sitemap, robots, `.htaccess`, Merchant-фіда.
- Notion і `ROADMAP_FLOW` не змінювались.

## 17. Що потрібно від власника далі

1. Заведення Roadmap-ID для цієї роботи (робить Claude (chat) за вказівкою).
2. Фото для дев'яти карток.
3. Підтвердження цін і залишків у CRM перед увімкненням.
4. Рішення по габаритах box-SKU (§5) — чи зводити 56 і 102 до одного формату.
