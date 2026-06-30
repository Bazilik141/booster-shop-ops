# Handoff CAT-002-5 + CAT-002-5b — REDO 2026-06-29
**Дата:** 2026-06-29
**Задачі:** CAT-002-5 (тайли головної), CAT-002-5b (бургер-меню)
**Виконавець:** Codex
**Тип:** PHP patch — DB updates + Twig/CSS edits
**Ризик:** low — навігаційний шар + product descriptions; не зачіпає checkout, оплату, sitemap

> **Чому REDO:** попередній патч (`cat002_5_5b_tiles_burger_20260628.php`) відкатано власником.
> Категорія Аксесуари і 7 товарів вже СТВОРЕНІ ВРУЧНУ в OpenCart Admin.
> Цей патч НЕ створює категорію і НЕ створює товари — тільки заповнює їх описами/атрибутами + править файли.

---

## Підтверджені ID (від власника)

```
category_id = 70  — Аксесуари, SEO keyword = 'acsesuary'
product_id  = 95  — ACC-001  protektory-kart-63x89-100sht
product_id  = 96  — ACC-002  protektory-games-7-days-63-5x88-premium-50sht
product_id  = 97  — ACC-003  toploudery-kart-35pt-25sht
product_id  = 98  — ACC-004  mahnitnyy-keys-kart-35pt
product_id  = 99  — ACC-005  akrylova-pidstavka-dlya-kart
product_id  = 100 — ACC-006  arkush-dlya-kart-9-games-7-days-premium
product_id  = 101 — YGO-JP-BODE-BST  booster-ygo-ocg-blazing-dominion-jp
```

---

## Scope: що робить цей патч

### DB зміни (НЕ РУЙНІВНІ — тільки INSERT/UPDATE в content-таблицях)

1. **Manufacturer INSERT** — додати `Generic` якщо не існує (для ACC-001/003/004/005)
2. **Attribute group INSERT** — `Характеристики аксесуарів` якщо не існує
3. **Attribute INSERT** — 9 атрибутів (якщо не існують):
   - Тип товару
   - Кількість в упаковці
   - Матеріал
   - Розмір / Формат
   - Товщина матеріалу
   - Без ПВХ
   - Сумісність з картками
   - Кількість кишеньок
   - Отворів для кріплення
4. **product_attribute INSERT** — призначити атрибути товарам 95–100 (ACC)
5. **product_description UPDATE** — вставити description HTML для product_id 95–101
6. **category_description UPDATE** — вставити description HTML для category_id 70

### FILE зміни

7. `catalog/view/template/common/home.twig` — додати 2 bs-subtile плитки
8. `catalog/view/template/common/header.twig` — фікс path=59/60 + нові категорії в бургері
9. `catalog/view/stylesheet/boostershop-ds.css` — 4 CSS-токени + компонент bs-subtile

---

## Детальні вимоги

### DB-1: Manufacturer Generic

```sql
INSERT IGNORE INTO `{PREFIX}manufacturer` (name, image, sort_order)
SELECT 'Generic', '', 99
WHERE NOT EXISTS (
  SELECT 1 FROM `{PREFIX}manufacturer` WHERE name='Generic'
);
-- Зберегти manufacturer_id для кроку DB-4
```

### DB-2: Attribute group

```sql
INSERT INTO `{PREFIX}attribute_group` (sort_order)
SELECT 10
WHERE NOT EXISTS (
  SELECT ag.attribute_group_id
  FROM `{PREFIX}attribute_group` ag
  JOIN `{PREFIX}attribute_group_description` agd ON agd.attribute_group_id = ag.attribute_group_id
  WHERE agd.name = 'Характеристики аксесуарів'
);
-- якщо новий — для кожної активної мови:
INSERT INTO `{PREFIX}attribute_group_description`
  (attribute_group_id, language_id, name)
SELECT LAST_INSERT_ID(), language_id, 'Характеристики аксесуарів'
FROM `{PREFIX}language` WHERE status=1;
```

### DB-3: Attributes (9 штук)

Для кожного атрибуту нижче — INSERT якщо не існує у цій групі:

| # | Назва атрибуту | sort_order |
|---|----------------|-----------|
| 1 | Тип товару | 1 |
| 2 | Кількість в упаковці | 2 |
| 3 | Матеріал | 3 |
| 4 | Розмір / Формат | 4 |
| 5 | Товщина матеріалу | 5 |
| 6 | Без ПВХ | 6 |
| 7 | Сумісність з картками | 7 |
| 8 | Кількість кишеньок | 8 |
| 9 | Отворів для кріплення | 9 |

Шаблон:
```sql
INSERT INTO `{PREFIX}attribute` (attribute_group_id, sort_order)
SELECT <group_id>, <sort_order>
WHERE NOT EXISTS (
  SELECT ad.attribute_id FROM `{PREFIX}attribute` a
  JOIN `{PREFIX}attribute_description` ad ON ad.attribute_id = a.attribute_id
  WHERE a.attribute_group_id = <group_id> AND ad.name = '<ATTR_NAME>'
);
INSERT INTO `{PREFIX}attribute_description` (attribute_id, language_id, name)
SELECT LAST_INSERT_ID(), language_id, '<ATTR_NAME>'
FROM `{PREFIX}language` WHERE status=1;
```

### DB-4: product_attribute assignments

Для кожного (product_id, attribute_name, text_value) — INSERT IGNORE:

```
product_id=95 (ACC-001):
  Тип товару          → Протектор
  Кількість в упаковці → 100 шт
  Матеріал            → PP (поліпропілен)
  Розмір / Формат     → 63×89 мм
  Товщина матеріалу   → 60–80 мкм
  Без ПВХ             → Так
  Сумісність з картками → Pokemon, MTG, Lorcana, One Piece TCG та інші Standard-size TCG

product_id=96 (ACC-002):
  Тип товару          → Протектор
  Кількість в упаковці → 50 шт
  Матеріал            → PP (поліпропілен)
  Розмір / Формат     → 63.5×88 мм
  Товщина матеріалу   → 110 мкм
  Без ПВХ             → Так
  Сумісність з картками → Pokemon, MTG, Lorcana та інші Standard-size TCG

product_id=97 (ACC-003):
  Тип товару          → Топлоадер
  Кількість в упаковці → 25 шт
  Матеріал            → ПЕТ (жорсткий прозорий пластик)
  Розмір / Формат     → 35PT (~66×92 мм внутрішній)
  Сумісність з картками → Pokemon, MTG, Lorcana, Yu-Gi-Oh! та інші Standard-size TCG

product_id=98 (ACC-004):
  Тип товару          → Магнітний кейс
  Кількість в упаковці → 1 шт
  Матеріал            → Акриловий пластик
  Розмір / Формат     → 35PT
  Сумісність з картками → Standard TCG-картки (~66×92 мм)

product_id=99 (ACC-005):
  Тип товару          → Підставка для кейсу
  Кількість в упаковці → 1 шт
  Матеріал            → Акриловий пластик
  Сумісність з картками → Стандартні магнітні кейси 35PT–100PT

product_id=100 (ACC-006):
  Тип товару          → Аркуш-файл
  Кількість в упаковці → 1 шт
  Матеріал            → PP 100 мкм
  Розмір / Формат     → Кишенька 68×94 мм
  Товщина матеріалу   → 100 мкм
  Без ПВХ             → Так
  Сумісність з картками → Pokemon, MTG, Lorcana та інші Standard-size TCG
  Кількість кишеньок  → 9 (3×3)
  Отворів для кріплення → 11
```

Шаблон INSERT для product_attribute:
```sql
INSERT IGNORE INTO `{PREFIX}product_attribute`
  (product_id, attribute_id, language_id, text)
SELECT <product_id>, <attribute_id>, l.language_id, '<value>'
FROM `{PREFIX}language` l WHERE l.status=1;
```

### DB-5: product_description UPDATE — описи товарів

> ⚠️ UPDATE тільки поля `description`. НЕ чіпати name, meta_title, meta_description, tag — вони вже заповнені вручну.

```sql
UPDATE `{PREFIX}product_description`
SET description = '<HTML>'
WHERE product_id = <ID> AND language_id IN (SELECT language_id FROM `{PREFIX}language` WHERE status=1);
```

#### product_id=95 — ACC-001

```html
<h3>Протектори 63×89 мм — базовий захист для вашої колекції</h3>

<p>
  Прозорі протектори стандартного розміру <strong>63×89 мм</strong> підходять для карток Pokemon TCG,
  One Piece TCG, Magic: The Gathering, Lorcana та більшості популярних колекційних карткових ігор.
  В упаковці <strong>100 штук</strong> — достатньо для однієї колоди з запасом.
</p>

<p>
  Матеріал — <strong>поліпропілен (PP) без ПВХ</strong>. PP не виділяє хімічних речовин,
  які могли б пошкодити карти при довгостроковому зберіганні. Прозорий, без відтінку,
  добре зберігає читабельність тексту й зображення.
</p>

<div class="bs-faq-accordion">
  <h3 class="bs-faq-title">FAQ</h3>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc001-1">
        Чи підходять ці протектори для Pokemon і MTG одночасно?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc001-1" class="bs-faq-panel" hidden>
      <p>Так. Розмір 63×89 мм — стандарт для Pokemon TCG, Magic: The Gathering, Lorcana, One Piece TCG та більшості популярних TCG. Картки всіх цих ігор вільно входять у протектор.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc001-2">
        Чому важливо, що без ПВХ?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc001-2" class="bs-faq-panel" hidden>
      <p>ПВХ-матеріал з часом виділяє хімічні сполуки, що можуть пошкодити поверхню карток і вкрасти їхній блиск. PP-протектори без ПВХ безпечні для тривалого зберігання.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc001-3">
        Чи вміщаються карти з протектором у топлоадер?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc001-3" class="bs-faq-panel" hidden>
      <p>Стандартні тонкі протектори (~60–80 мкм), як ці, зазвичай вміщаються у топлоадери 35PT і більше. Якщо протектор товстий (110 мкм+), карта може вже не поміститись — тоді потрібен топлоадер 55PT або більше.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc001-4">
        Скільки протекторів потрібно для однієї колоди?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc001-4" class="bs-faq-panel" hidden>
      <p>Стандартна колода — 60 карток. 100 штук у упаковці вистачить на одну колоду з запасом для заміни зношених протекторів.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc001-5">
        Що таке sleeves і чому протектори так називають?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc001-5" class="bs-faq-panel" hidden>
      <p>Sleeves (від англ. «рукав», «чохол») — загальноприйнята назва протекторів для колекційних карток у міжнародній TCG-спільноті. Розмір 63×89 мм — класичний стандарт Magic: The Gathering, яка першою масово запровадила захисні чохли. Згодом цей розмір став універсальним для більшості сучасних TCG, а термін «sleeves» закріпився як синонім «протекторів» у середовищі гравців і колекціонерів по всьому світу.</p>
    </div>
  </div>

</div>
```

#### product_id=96 — ACC-002

```html
<h3>Games 7 Days Premium+ — преміальний захист від українського бренду</h3>

<p>
  <strong>Games 7 Days Premium+</strong> — протектори підвищеної щільності <strong>110 мкм</strong>
  від українського бренду, що спеціалізується на аксесуарах для TCG.
  Розмір <strong>63.5×88 мм</strong>, в упаковці <strong>50 штук</strong>.
</p>

<p>
  Товщина 110 мкм забезпечує жорсткість рами протектора — карта менше гнеться, ребро
  не деформується при тасуванні. Матеріал <strong>PP без ПВХ</strong>, сертифікат CE.
  Оптимальний вибір для активних гравців і серйозних колекціонерів.
</p>

<div class="bs-faq-accordion">
  <h3 class="bs-faq-title">FAQ</h3>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc002-1">
        У чому різниця між 63×89 мм і 63.5×88 мм?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc002-1" class="bs-faq-panel" hidden>
      <p>Обидва розміри підходять для стандартних TCG-карток. 63.5×88 мм — розмір, орієнтований на Pokemon TCG і дещо щільніше прилягає до карти. На практиці карти обох форматів входять у будь-який із цих протекторів.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc002-2">
        Що дає товщина 110 мкм на практиці?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc002-2" class="bs-faq-panel" hidden>
      <p>Протектор жорсткіший і краще тримає форму при тасуванні. Рідше з'являються складки і загини на кутах. Для ігрових колод — відчутний плюс у порівнянні зі стандартними 60–80 мкм.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc002-3">
        Games 7 Days — це якісний бренд?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc002-3" class="bs-faq-panel" hidden>
      <p>Games 7 Days — Ukrainian brand із сертифікатом CE, відомий серед місцевих гравців у настільні та карткові ігри. Premium+ серія позиціонується як конкурент Dragon Shield у бюджетному сегменті.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc002-4">
        Що таке sleeves і чим вони відрізняються від звичайних протекторів?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc002-4" class="bs-faq-panel" hidden>
      <p>Sleeves — міжнародна назва протекторів для TCG-карток, буквально «рукав». Різниці між «протектором» і «sleeve» немає — це той самий продукт. Різниця між брендами — у товщині матеріалу, прозорості та якості проковзування при тасуванні. Premium+ від Games 7 Days з товщиною 110 мкм відчутно жорсткіші за бюджетні аналоги ~60 мкм.</p>
    </div>
  </div>

</div>
```

#### product_id=97 — ACC-003

```html
<h3>Топлоадери 35PT — жорсткий захист для цінних карток</h3>

<p>
  Топлоадери — жорсткі прозорі футляри з ПЕТ-пластику для зберігання та демонстрації
  колекційних карток. На відміну від м'яких протекторів, вони <strong>не гнуться і не деформуються</strong>,
  забезпечуючи максимальний захист для цінних позицій.
</p>

<p>
  Розмір <strong>35PT</strong> підходить для стандартних карток Pokemon TCG, One Piece TCG,
  Magic: The Gathering, Lorcana, Yu-Gi-Oh! та інших TCG <strong>без протектора</strong>. В упаковці <strong>25 штук</strong>.
  Тип: відкритий зверху (top-loading).
</p>

<div class="bs-faq-accordion">
  <h3 class="bs-faq-title">FAQ</h3>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc003-1">
        Що означає 35PT?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc003-1" class="bs-faq-panel" hidden>
      <p>PT (points) — одиниця товщини карти. 35PT = приблизно 0,9 мм — стандартна TCG-картка без протектора. Якщо картка у протекторі — потрібен більший типорозмір: 55PT або 130PT.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc003-2">
        Для чого топлоадери, якщо є протектори?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc003-2" class="bs-faq-panel" hidden>
      <p>Протектори захищають від подряпин, але гнуться. Топлоадери — жорсткі і не дозволяють карті зігнутися або зламатися. Їх використовують для рейдерів, рідкісних карт і позицій, що зберігаються довгостроково або відправляються поштою.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc003-3">
        Чи підходять для відправки картки поштою?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc003-3" class="bs-faq-panel" hidden>
      <p>Топлоадер + протектор усередині — стандартна комбінація для безпечного відправлення цінної картки поштою. Додатково рекомендуємо обернути у bubble wrap або помістити між двома шматками картону.</p>
    </div>
  </div>

</div>
```

#### product_id=98 — ACC-004

```html
<h3>Магнітний кейс 35PT — преміальне зберігання однієї найціннішої картки</h3>

<p>
  <strong>Magnetic Card Case</strong> — жорсткий акриловий кейс із вбудованим магнітним замком
  для зберігання та демонстрації однієї колекційної картки.
  Прозорий корпус дозволяє оглядати картку <strong>з обох сторін</strong> без виймання.
</p>

<p>
  Підходить для карток товщиною до <strong>35PT</strong> (стандартна картка без протектора або
  у тонкому протекторі). Ідеальний варіант для рейдерів, топ-карт і подарунків.
</p>

<div class="bs-faq-accordion">
  <h3 class="bs-faq-title">FAQ</h3>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc004-1">
        Магніт не пошкодить картку?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc004-1" class="bs-faq-panel" hidden>
      <p>Ні. TCG-картки є паперовими з фойловим покриттям — вони не є магнітними носіями і не піддаються впливу постійних магнітів такої сили. Магнітні кейси стандартно використовуються колекціонерами по всьому світу саме для TCG-карток.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc004-2">
        Чи поміщається карта з протектором?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc004-2" class="bs-faq-panel" hidden>
      <p>35PT розрахований на картку без протектора. Картка у тонкому протекторі (~60 мкм) зазвичай теж вміщається. З товстим протектором (110 мкм+) — може не закритись; в такому випадку потрібен кейс 55PT або 130PT.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc004-3">
        Можна поставити на підставку?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc004-3" class="bs-faq-panel" hidden>
      <p>Так. Для цього є окремий аксесуар — акрилова підставка (ACC-005). Кейс ставиться у підставку вертикально під оптимальним кутом для огляду.</p>
    </div>
  </div>

</div>
```

#### product_id=99 — ACC-005

```html
<h3>Акрилова підставка — виставте найкращу картку на полиці</h3>

<p>
  Прозора акрилова підставка-тримач для магнітних кейсів із колекційними картками.
  Дозволяє поставити кейс <strong>вертикально під оптимальним кутом</strong> для огляду
  — на полиці, робочому столі або вітрині.
</p>

<p>
  Трикутна конструкція стабільна без клею та додаткових кріплень. Підходить для
  стандартних магнітних кейсів розміром від <strong>35PT до 100PT</strong>.
  Прозорий матеріал не відволікає увагу від картки.
</p>

<div class="bs-faq-accordion">
  <h3 class="bs-faq-title">FAQ</h3>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc005-1">
        Підставка підходить до будь-якого магнітного кейсу?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc005-1" class="bs-faq-panel" hidden>
      <p>Підходить до стандартних магнітних кейсів від 35PT до 100PT. Кейси нестандартних розмірів можуть не підійти.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc005-2">
        Чи продається підставка разом із кейсом?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc005-2" class="bs-faq-panel" hidden>
      <p>Ні, підставка продається окремо. Магнітний кейс — окремий товар (ACC-004).</p>
    </div>
  </div>

</div>
```

#### product_id=100 — ACC-006

```html
<h3>Аркуш-файл 9 кишеньок Games 7 Days Premium — систематизуйте колекцію</h3>

<p>
  <strong>Games 7 Days Premium Series</strong> — захисний аркуш на <strong>9 кишеньок (3×3)</strong>
  для організації та зберігання колекційних карток у альбомі-кільцях.
  Матеріал — <strong>PP 100 мкм без ПВХ</strong>, відмінна прозорість.
</p>

<p>
  <strong>11 отворів</strong> для кріплення сумісні з усіма стандартними альбомами формату А4
  (11-hole ring binder). Розмір кишеньки <strong>~68×94 мм</strong> вміщає картку
  з протектором або без. Продається <strong>поштучно</strong>.
</p>

<div class="bs-faq-accordion">
  <h3 class="bs-faq-title">FAQ</h3>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc006-1">
        Скільки карток вміщає один аркуш?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc006-1" class="bs-faq-panel" hidden>
      <p>9 карток — по одній в кожній кишеньці. Якщо вкладати з обох сторін (double-sided), то 18, але для цінних карток рекомендуємо використовувати тільки одну сторону.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc006-2">
        Чи підходить для карток з протектором?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc006-2" class="bs-faq-panel" hidden>
      <p>Так. Розмір кишеньки 68×94 мм дозволяє вкласти стандартну картку з тонким протектором (63×89 мм або 63.5×88 мм). З товстими протекторами 110 мкм+ — може бути трохи тісно.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc006-3">
        До якого альбому підходить?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc006-3" class="bs-faq-panel" hidden>
      <p>До будь-якого стандартного альбому А4 з 11 кільцями (D-ring або O-ring binder). Більшість офісних і колекційних альбомів А4 мають цей стандарт.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-acc006-4">
        Скільки аркушів потрібно для колекції сету?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-acc006-4" class="bs-faq-panel" hidden>
      <p>Залежить від розміру сету. Для 100-картного сету (без дублів) потрібно 12 аркушів; для 60-картної ігрової колоди — 7 аркушів.</p>
    </div>
  </div>

</div>
```

#### product_id=101 — YGO-JP-BODE-BST

> ⚠️ Codex: перед INSERT атрибутів перевірити існуючі attribute_group для YGO-продуктів у БД
> (інші YGO: product_id у таблиці product_description де model='YGO-JP-QCAC-BST' або 'YGO-JP-WPP5-BST').
> Якщо attribute_group "Характеристики TCG бустеру" вже існує — використати його, не створювати новий.

Атрибути YGO-JP-BODE-BST:
```
Назва сету          → Blazing Dominion
Код сету            → BODE
Мова                → Японська (Japanese)
Тип пакування       → Sealed Booster Pack
Карток у паку       → 5
Зважування          → Без зважування (Unweighed)
Стан                → Новий, нерозпакований (Sealed)
Походження товару   → Box / Case sourced
Рік випуску         → 2022
```

```html
<h3>Blazing Dominion — японський бустер Yu-Gi-Oh! OCG зі сету BODE</h3>

<p>
  <strong>Blazing Dominion</strong> (ブレイジング・ドミニオン, BODE) — японський бустер-сет OCG від Konami,
  випущений 15 січня 2022 року. Сет містить <strong>101 унікальну картку</strong>:
  Commons, Super Rares, Ultra Rares та Secret Rares. У кожному паку <strong>5 карток</strong>.
</p>

<p>
  Сет вводить нових монстрів Xyz та Link, а також підсилення для популярних архетипів.
  Бустери продаються <strong>поштучно</strong> — ідеально для доповнення колекції
  або відкриття у форматі одиночних паків.
</p>

<p>
  <strong>Оригінал гарантовано:</strong> усі бустери зберігаються в запечатаному вигляді до продажу,
  без зважування чи попереднього огляду.
</p>

<div class="bs-faq-accordion">
  <h3 class="bs-faq-title">FAQ</h3>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-bode-1">
        Що таке OCG і чим відрізняється від TCG?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-bode-1" class="bs-faq-panel" hidden>
      <p>OCG (Official Card Game) — японська версія Yu-Gi-Oh!, що виходить у Японії, Кореї та Азії. TCG (Trading Card Game) — версія для США і Європи. Карти ідентичні за ефектами, але мають різний дизайн рубашки, мову і нумерацію. В Україні OCG зазвичай доступна раніше і коштує дешевше.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-bode-2">
        Бустери зважувались?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-bode-2" class="bs-faq-panel" hidden>
      <p>Ні. Продаємо unweighed — без ручного відбору за вагою. Кожен пак у тому стані, в якому прийшов від постачальника.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-bode-3">
        Чи сумісні OCG-карти з TCG-колодами?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-bode-3" class="bs-faq-panel" hidden>
      <p>Для колекціонування — так, повністю. Для офіційних TCG-турнірів в Україні — уточнюйте правила конкретного організатора: деякі турніри дозволяють OCG, деякі — ні. Для домашніх ігор і casual play різниці немає.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h4 class="bs-faq-question">
      <button class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="faq-bode-4">
        Карти японською — чи можна грати, не знаючи мови?
        <span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h4>
    <div id="faq-bode-4" class="bs-faq-panel" hidden>
      <p>Для колекціонування — без проблем. Для гри можна звіряти ефекти з Yugipedia або англійськими аналогами. Більшість ефектів BODE мають точний аналог у TCG-виданні.</p>
    </div>
  </div>

</div>
```

### DB-6: category_description UPDATE — категорія Аксесуари (category_id=70)

```sql
UPDATE `{PREFIX}category_description`
SET description = '<HTML>'
WHERE category_id = 70
  AND language_id IN (SELECT language_id FROM `{PREFIX}language` WHERE status=1);
```

```html
<p>
  Протектори, топлоадери, магнітні кейси та аркуші для колекціонерів і гравців TCG.
  Все необхідне для захисту та організації карток Pokemon, Magic: The Gathering,
  Lorcana, Yu-Gi-Oh! та інших ігор — в одному місці.
</p>
```

---

## FILE-1: home.twig — 2 нові bs-subtile плитки

**Anchor (шукати ОДИН раз у файлі):**
```twig
      </section>
      
      {{ content_top }}
```

**Замінити на:**
```twig
      </section>

      {# CAT-002-5 · Claude Design secondary tiles #}
      <section class="bs-home-tiles bs-subtiles" aria-label="Інші категорії">
        <a class="bs-subtile" href="/catalog/more-tcg" style="--accent: var(--bs-other-tcg);">
          <span class="bs-subtile__glyph" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="3" width="13" height="18" rx="2"/>
              <path d="M20 7v14a2 2 0 0 1-2 2H8"/>
            </svg>
          </span>
          <span class="bs-subtile__body">
            <span class="bs-subtile__title">Інші TCG</span>
            <span class="bs-subtile__hint">Yu-Gi-Oh! · Magic: The Gathering</span>
          </span>
          <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
          </svg>
        </a>

        <a class="bs-subtile" href="/catalog/acsesuary" style="--accent: var(--bs-accessories);">
          <span class="bs-subtile__glyph" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="5" y="4" width="12" height="16" rx="1.5"/>
              <path d="M19 5.5l.7 1.6 1.8.3-1.4 1.2.4 1.8-1.5-.9-1.5.9.4-1.8L16.5 7.4l1.8-.3z" fill="currentColor" fill-opacity=".15"/>
            </svg>
          </span>
          <span class="bs-subtile__body">
            <span class="bs-subtile__title">Аксесуари</span>
            <span class="bs-subtile__hint">Sleeves, deck boxes, playmats, binders</span>
          </span>
          <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
          </svg>
        </a>
      </section>
      
      {{ content_top }}
```

**Assertion:** після патчу — маркер `CAT-002-5 · Claude Design secondary tiles` є рівно 1 раз; `href="/catalog/acsesuary"` є рівно 1 раз; `href="/catalog/more-tcg"` є рівно 1 раз.

---

## FILE-2: header.twig — фікс URL + нові категорії

**Крок A — замінити 6 path= URL (КОЖЕН рівно 1 раз):**
```
href="index.php?route=product/category&path=59"     →  href="/catalog/pokemon"
href="index.php?route=product/category&path=60"     →  href="/catalog/one-piece"
href="index.php?route=product/category&path=59_61"  →  href="/catalog/bustery-pokemon"
href="index.php?route=product/category&path=59_62"  →  href="/catalog/Pokemon-booster-box"
href="index.php?route=product/category&path=59_64"  →  href="/catalog/pokemon-tcg-nabory"
href="index.php?route=product/category&path=60_63"  →  href="/catalog/One-Piece-Boosters"
```

**Крок B — знайти anchor (рівно 1 раз):**
```twig
      <div class="bs-menu__cat">
        <a href="index.php?route=product/special" class="bs-menu__cat-row is-sale">
```

**Замінити на:**
```twig
      {# CAT-002-5b · burger categories #}
      <div class="bs-menu__cat">
        <button type="button" class="bs-menu__cat-row" data-bs-accordion>
          <span class="bs-menu__dot" style="background:var(--bs-other-tcg)"></span>
          <span class="bs-menu__cat-name">Інші TCG</span>
          <span class="bs-menu__chev bs-menu__chev--acc">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
        <div class="bs-menu__subs" hidden>
          <a href="/catalog/more-tcg" class="bs-menu__sub">Усі Інші TCG</a>
          <a href="/catalog/more-tcg/Yu-Gi-Oh" class="bs-menu__sub bs-menu__sub--accent">
            <span class="bs-menu__dot" style="background:var(--bs-yugioh)"></span>
            <span>Yu-Gi-Oh! OCG</span>
          </a>
          <a href="/catalog/more-tcg/magic-the-gathering" class="bs-menu__sub bs-menu__sub--accent">
            <span class="bs-menu__dot" style="background:var(--bs-mtg)"></span>
            <span>Magic: The Gathering</span>
          </a>
        </div>
      </div>

      <div class="bs-menu__cat">
        <a href="/catalog/acsesuary" class="bs-menu__cat-row">
          <span class="bs-menu__dot" style="background:var(--bs-accessories)"></span>
          <span class="bs-menu__cat-name">Аксесуари</span>
          <span class="bs-menu__chev">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </a>
      </div>

      <div class="bs-menu__cat">
        <a href="index.php?route=product/special" class="bs-menu__cat-row is-sale">
```

**Assertions після патчу:**
- `/catalog/pokemon` присутній (крок A)
- `/catalog/one-piece` присутній (крок A)
- `/catalog/acsesuary` присутній (крок B)
- `/catalog/more-tcg` присутній (крок B)
- `/catalog/bustery-pokemon`, `/catalog/Pokemon-booster-box`, `/catalog/pokemon-tcg-nabory`, `/catalog/One-Piece-Boosters` — присутні (крок A)
- `index.php?route=product/special` — ЗБЕРЕЖЕНИЙ

---

## FILE-3: boostershop-ds.css — токени + компонент

### Крок A — remove old tokens (якщо є):
Regex-видалення рядків `--bs-yugioh: #...;` і `--bs-mtg: #...;` якщо вже присутні у :root (захист від часткового попереднього патчу).

### Крок B — anchor для нових токенів (рівно 1 раз):
```css
  --bs-pokemon:    #C68A00;
  --bs-onepiece:   #1E40AF;
```

Замінити на:
```css
  --bs-pokemon:     #C68A00;
  --bs-onepiece:    #1E40AF;
  --bs-other-tcg:   #065F46;
  --bs-accessories: #0D9488;
  --bs-yugioh:      #7C3AED;
  --bs-mtg:         #B45309;
```

### Крок C — anchor для компонента (рівно 1 раз):
```css
/* ==== /RD-10D3 ==== */
```

Замінити на:
```css
/* ==== /RD-10D3 ==== */

/* ==== CAT-002-5 · Claude Design secondary category tiles ==== */
.bs-subtiles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-top: 18px;
}
.bs-subtile {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  height: 84px;
  padding: 0 18px 0 20px;
  background: #fff;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  color: var(--bs-ink);
  overflow: hidden;
  text-decoration: none;
  transition: border-color .15s, box-shadow .15s;
}
.bs-subtile:hover {
  border-color: color-mix(in oklab, var(--accent) 35%, var(--bs-line));
  box-shadow: var(--bs-sh-sm);
  text-decoration: none;
}
.bs-subtile::before {
  content: "";
  position: absolute;
  left: 0;
  top: 14px;
  bottom: 14px;
  width: 3px;
  background: var(--accent);
  border-radius: 0 3px 3px 0;
}
.bs-subtile__glyph {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  background: color-mix(in oklab, var(--accent) 12%, white);
  color: var(--accent);
}
.bs-subtile__glyph svg { width: 20px; height: 20px; display: block; }
.bs-subtile__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.bs-subtile__title {
  font-size: 15px;
  font-weight: 700;
  letter-spacing: -0.005em;
  color: var(--bs-ink);
}
.bs-subtile__hint {
  font-size: 12.5px;
  color: var(--bs-ink-3);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.bs-subtile__chev { color: var(--bs-ink-4); flex: 0 0 auto; }

.bs-menu__sub--accent {
  display: flex;
  align-items: center;
  gap: 9px;
}
.bs-menu__sub--accent .bs-menu__dot {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
}

@media (max-width: 768px) {
  .bs-subtiles { grid-template-columns: 1fr; gap: 10px; margin-top: 10px; }
  .bs-subtile { height: 72px; padding: 0 14px 0 18px; gap: 12px; }
  .bs-subtile__glyph { width: 34px; height: 34px; flex-basis: 34px; border-radius: 7px; }
  .bs-subtile__glyph svg { width: 18px; height: 18px; }
  .bs-subtile__title { font-size: 14px; }
  .bs-subtile__hint { font-size: 12px; }
}

@supports not (background: color-mix(in oklab, red, white)) {
  .bs-subtile__glyph { background: rgba(0,0,0,0.04); }
  .bs-subtile:hover { border-color: var(--bs-line); }
}
/* ==== /CAT-002-5 ==== */
```

---

## Порядок виконання (важливо)

1. Backup файлів + DB snapshot (як завжди)
2. DB-1: manufacturer Generic
3. DB-2: attribute group
4. DB-3: attributes × 9
5. DB-4: product_attribute для product_id 95–100
6. DB-5: UPDATE product_description × 7
7. DB-6: UPDATE category_description category_id=70
8. FILE-3: CSS patch (кроки A→B→C)
9. FILE-1: home.twig patch
10. FILE-2: header.twig patch (кроки A→B)
11. Final assertions
12. Self-delete patch file

---

## Acceptance criteria

- [ ] `/catalog/more-tcg` відкривається, не 404
- [ ] `/catalog/acsesuary` відкривається, не 404
- [ ] Головна: 2 нові плитки Інші TCG + Аксесуари після наявних catcard-тайлів
- [ ] Бургер: Pokémon → `/catalog/pokemon`, не `index.php?path=59`
- [ ] Бургер: One Piece → `/catalog/one-piece`, не `index.php?path=60`
- [ ] Бургер: "Інші TCG" → accordion → YGO sub + MTG sub
- [ ] Бургер: "Аксесуари" → пряме посилання `/catalog/acsesuary`
- [ ] Акції у бургері — не зникли
- [ ] Сторінка `/catalog/acsesuary` — є назва "Аксесуари" і опис категорії
- [ ] Сторінки товарів 95–100 — є опис з FAQ-акордеоном
- [ ] Сторінка товару 101 (YGO BODE) — є опис з FAQ
- [ ] Атрибути товарів 95–100 видно на сторінці товару

---

## QA checklist

1. Десктоп + моб < 768px: відкрити головну → перевірити 2 нові плитки
2. Клік "Інші TCG" → `/catalog/more-tcg` → не 404
3. Клік "Аксесуари" → `/catalog/acsesuary` → є список товарів
4. Бургер → Покемон → URL `/catalog/pokemon`
5. Бургер → One Piece → URL `/catalog/one-piece`
6. Бургер → "Інші TCG" → accordion → клік YGO sub → `/catalog/more-tcg/Yu-Gi-Oh` → не 404
7. Бургер → "Аксесуари" → `/catalog/acsesuary`
8. Сторінка ACC-001 (95) → видно опис + FAQ-акордеон (клік розкриває)
9. Сторінка YGO-BODE (101) → видно опис + FAQ
10. Admin → Catalog → Products: 95–101 мають атрибути у вкладці Attribute

---

## Ризики

| Ризик | Рівень | Мітигація |
|-------|--------|-----------|
| SEO slugs для `more-tcg`, `more-tcg/Yu-Gi-Oh`, `more-tcg/magic-the-gathering` не налаштовані в БД | medium | Codex перевіряє `oc_seo_url` де `keyword` IN цих значень; якщо відсутні — fail з повідомленням |
| `/* ==== /RD-10D3 ==== */` відсутній у CSS (якщо rd10 не застосовувався) | low | Codex: перевірити наявність anchor; якщо відсутній — append компонент в кінець файлу замість replace |
| YGO attribute_group конфліктує з існуючим | low | Codex: перед INSERT перевірити існуючі groups по назві |
| `path=59_61` і аналоги — SEO slug не збігається з реальним keyword у БД | low | Codex: кожна заміна через `cat002_replace_once` — якщо slug не знайдено в header.twig, patch fail з чітким повідомленням |

---

## NOT IN SCOPE (деferred)

- Фікс підкатегорій — **включено** в крок A (FILE-2), SEO slugs підтверджено власником
- Завантаження зображень категорій (MoreTCGC.png, AccessoriesC.png) → вручну власником
- Завантаження зображень товарів 95–101 → вручну власником
