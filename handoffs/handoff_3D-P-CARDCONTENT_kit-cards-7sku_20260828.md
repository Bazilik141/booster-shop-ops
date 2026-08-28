# Handoff — Booster Shop: Kit Card / фігурки-конструктори Pokémon

**Дата:** 2026-08-28  
**Проєкт:** Booster Shop / CONTENT-QUALITY  
**Призначення:** передача Claude для формування патчу по нових картках товарів  
**Статус контенту:** фінальний polishing pass + batch QA = **PASS**

---

## 0. Правки Claude 2026-08-28 (після QA)

Внесені за прямою вказівкою власника. Затверджений shopper-facing зміст не
змінювався — правились тільки повтори, форма атрибутів і слаги.

| Що | Де | Чому |
|---|---|---|
| Переписані юридичне речення і речення про фото | `FIG-MAGIK-300`, `FIG-SQUIR-300` (дисклеймер); `FIG-JIGGL-300`, `FIG-MEW-300`, `FIG-MAGIK-300` (фото) | дослівні повтори між картками порушували жорстку вимогу варіативності від 2026-08-22/25. Після правки повторів речень у FAQ між сімома картками — нуль |
| Прибрано атрибут `Складання` | усі 7 | атрибута немає в `ocp5_attribute_description`; заводити новий — окрема зміна БД |
| Додано `Призначення: декоративний / колекційний виріб` | усі 7 | є на всіх живих 3D-товарах (атрибут 54) |
| `Матеріал` → `Пластик PLA` | усі 7 | живі товари зберігають саме цю форму; різний регістр дав би два значення атрибута |
| `Маса` → `орієнтовно N г` | усі 7 | форма живих карток; це слайсерні цифри, а не зважування |
| `Розміри` → `N×N×2 мм (kit card до складання)` | усі 7 | уточнення лишається (воно рятує покупця від думки, що це розмір готової фігурки), але береться в дужки — так само, як `орієнтовно` в масі |
| SEO URL приведені до живої схеми | усі 7 | у базі 3D-товари мають `<тип>-<персонаж>-<франшиза>-3d-druk`: `brelok-mew-pokemon-3d-druk`, `figurka-luffy-one-piece-3d-druk`, `pidstavka-dlia-kartky-v-toploaderi-3d-druk`. Було `jigglypuff-figurka-konstruktor` — інший порядок, без франшизи й без суфікса |

**Рішення власника 2026-08-28, зафіксовані тут:**

1. **Імена персонажів — латиниця**, у назві товару теж. Канон приведено:
   `plans/3D-P_sku-naming-convention_20260807.md`, доповнення ред. 5. Похідна
   задача — вирівняти назви 19 наявних 3D-товарів (тільки поле `name`,
   `seo_url` не чіпати) — заведена в тому ж доповненні й **у цю хвилю не входить**.
2. **`Рухомі елементи: немає`** — додано в атрибути всіх семи.
3. **Комерційні поля:** `price = 1.0000` (заглушка), `quantity = 0`,
   `status = 0`, `stock_status_id = 8`, `image` порожній — як у решти 3D-асортименту.
   Виконавець ставить саме ці значення й нічого не вигадує.
4. **Мнемоніки `GENG` і `MAGIK`** внесені в реєстр канону.

**Свідомо не змінено:** структура H2 «`<Name> kit card — …`» однакова на всіх сімох.
Фрази після тире різні, назва спереду — це і є SEO-намір власника, а переписування
заголовків лише заради несхожості CORE прямо називає дефектом.

---

## 1. Що треба зробити в патчі

Створити/оновити картки для 7 нових 3D-друкованих фігурок-конструкторів у форматі **kit card**.

### SKU

1. `FIG-JIGGL-300` — Jigglypuff
2. `FIG-MEW-300` — Mew
3. `FIG-UMBRE-300` — Umbreon
4. `FIG-GENG-300` — Gengar
5. `FIG-MAGIK-300` — Magikarp
6. `FIG-PIKA-300` — Pikachu
7. `FIG-SQUIR-300` — Squirtle

### Категорія

`Pokémon → Фігурки та декор`

### Виробник

`Booster Shop`

---

## 2. Критичні правила імплементації

1. **Імена покемонів у назвах, H2, body, FAQ, Meta та keywords — англійською:** Jigglypuff, Mew, Umbreon, Gengar, Magikarp, Pikachu, Squirtle.
2. У **HTML body кожної картки рівно один раз** має бути кириличний IP-місток `Покемон`.
3. Не додавати твердження `офіційний`, `ліцензійний`, `оригінальний мерч`.
4. Це **неофіційні** 3D-вироби Booster Shop. У кожній картці mandatory legal+contents FAQ уже включений.
5. Товар постачається **незібраним у вигляді kit card**. Готова фігурка на фото — лише демонстрація результату.
6. **Клей не потрібен.**
7. Матеріал: **PLA**. Спосіб виготовлення: **пошаровий 3D-друк**.
8. Типовий строк виготовлення при відсутності на складі: **1–2 робочих дні**.
9. Вікове позиціонування: **14+**.
10. Усі 7 товарів: **Може трапитись у Mystery Box = Так**.
11. Не додавати окремий атрибут `Формат: kit card` — термін уже пояснений у body/FAQ.
11a. Так само не заводити атрибут `Складання`. У базі його немає; факт «без клею» лишається в тілі опису та у FAQ.
11b. Значення атрибутів беруться дослівно з розділу Attributes кожної картки. Вони вже приведені до форми, у якій ці самі атрибути записані в живих 3D-товарах (`Пластик PLA`, `орієнтовно N г`, `Призначення`).
12. Не змінювати тексти нижче без необхідності мапінгу в існуючу структуру сайту.
13. Не чіпати інші товари, категорії, FAQ або SEO-поля поза цими 7 SKU.

---

## 3. FAQ — технічний контракт

Використовувати тільки live-site accordion:

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="<unique-card-id>">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-<faq-id>-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-<faq-id>-button-1" type="button"><span>Питання</span></button></h3>
<div aria-labelledby="bs-faq-<faq-id>-button-1" class="bs-faq-panel" hidden="" id="bs-faq-<faq-id>-panel-1" role="region">
<p>Відповідь.</p>
</div>
</div>
</section>
```

Обов'язково зберегти:
- `bs-faq-accordion`;
- `bs-faq-item`;
- `bs-faq-toggle`;
- `bs-faq-panel`;
- `hidden=""`;
- двобічні ARIA-зв'язки;
- унікальні ID;
- текст питання всередині `<span>`.

---

# 4. Картки товарів

---

## 4.1 `FIG-JIGGL-300`

### Назва

**Фігурка-конструктор Jigglypuff (Pokémon) — 3D-друк**

### HTML body

```html
<h2>Jigglypuff kit card — фігурка-конструктор, яку ви збираєте самі</h2>

<p><strong>Фігурка-конструктор Jigglypuff постачається як пласка рожева kit card, а після складання стає окремою 3D-фігуркою.</strong> Деталі надруковані всередині рамки: їх потрібно акуратно відокремити та з’єднати між собою. Клей не потрібен.</p>

<p>У готовій моделі добре читаються великі блакитні очі, завиток на голові, гострі вушка й мікрофон у лапці. Саме тому ця версія цікавіша за просто готовий сувенір: спочатку є короткий процес складання, а вже потім — персонаж для полиці, робочого столу чи колекційного куточка.</p>

<p>Модель друкуємо в Booster Shop в Україні з PLA. Для фаната Покемон це компактний подарунок, у якому є і знайомий персонаж, і сам процес складання — не просто готовий декор із коробки.</p>
```

### FAQ

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-fig-jiggl-300">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-jiggl-300-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-jiggl-300-button-1" type="button"><span>У якому вигляді приїде Jigglypuff і що входить у комплект?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-jiggl-300-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-jiggl-300-panel-1" role="region">
<p>Ви отримуєте одну незібрану kit card з усіма деталями для складання однієї фігурки Jigglypuff. Готова модель на фото показує результат після складання й окремо до комплекту не додається. Це неофіційний 3D-друкований виріб Booster Shop у тематиці Pokémon; Booster Shop не є ліцензіатом, партнером або афілійованою особою правовласника. Усе інше, що потрапило в кадр, до комплекту не входить.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-jiggl-300-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-jiggl-300-button-2" type="button"><span>112×127×2 мм — це розмір готової фігурки?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-jiggl-300-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-jiggl-300-panel-2" role="region">
<p>Ні. Це розмір пласкої kit card до складання. Після відокремлення та з’єднання деталей Jigglypuff стає об’ємною фігуркою.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-jiggl-300-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-jiggl-300-button-3" type="button"><span>Чи можуть на Jigglypuff бути помітні лінії 3D-друку?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-jiggl-300-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-jiggl-300-panel-3" role="region">
<p>Так. Модель виготовляється пошаровим 3D-друком із PLA, тому на поверхні можуть бути помітні характерні лінії шарів. Це нормальна особливість технології, а не дефект виробу.</p>
</div>
</div>
</section>
```

### Attributes

- Тип виробу: `фігурка-конструктор`
- Країна виготовлення: `Україна`
- Спосіб виготовлення: `пошаровий 3D-друк`
- Матеріал: `Пластик PLA`
- Колір: `багатоколірний, основний — рожевий`
- Розміри: `112×127×2 мм (kit card до складання)`
- Маса: `орієнтовно 29,67 г`
- Комплектація: `1 kit card з деталями для складання 1 фігурки`
- Вікове позиціонування: `14+`
- Типовий строк виготовлення при відсутності на складі: `1–2 робочих дні`
- Може трапитись у Mystery Box: `Так`
- Рухомі елементи: `немає`
- Призначення: `декоративний / колекційний виріб`

### SEO

- **Meta Title:** `Jigglypuff 3D фігурка-конструктор Pokémon | Booster Shop`
- **Meta Description:** `Jigglypuff kit card — 3D-фігурка-конструктор із PLA для складання без клею. Власний 3D-друк Booster Shop в Україні.`
- **Meta Keywords:** `Jigglypuff figure, Jigglypuff 3D figure, Jigglypuff kit card, фігурка Jigglypuff, фігурка-конструктор Jigglypuff, Pokémon фігурки`
- **SEO URL:** `figurka-konstruktor-jigglypuff-pokemon-3d-druk`

### Internal links

- Pokémon → Фігурки та декор
- після публікації серії — Mew як інша компактна kit card

---

## 4.2 `FIG-MEW-300`

### Назва

**Фігурка-конструктор Mew (Pokémon) — 3D-друк**

### HTML body

```html
<h2>Mew kit card — від пласкої рамки до впізнаваної 3D-фігурки</h2>

<p>3D-фігурка Mew добре працює саме за рахунок силуету: велика голова, блакитні очі, тонкі лапи й довгий вигнутий хвіст створюють чистий, впізнаваний образ без зайвого декору.</p>

<p><strong>Спочатку це пласка рожева kit card з окремими деталями.</strong> Ви відокремлюєте їх від рамки та складаєте Mew без клею, а готову модель уже можна поставити біля карт, на полицю чи робочий стіл.</p>

<p>Це наш власний 3D-друк із PLA, виготовлений у Booster Shop в Україні. Для фаната Покемон Mew працює і як невеликий конструктор, і як акуратний декор — особливо якщо хочеться не просто розпакувати готову фігурку, а трохи долучитися до її появи.</p>
```

### FAQ

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-fig-mew-300">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-mew-300-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-mew-300-button-1" type="button"><span>Що саме я отримаю в наборі Mew?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-mew-300-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-mew-300-panel-1" role="region">
<p>У комплекті одна незібрана kit card з деталями для складання однієї фігурки Mew. Зібрана модель на фото потрібна для демонстрації готового результату й не є другим виробом у комплекті. Це неофіційна 3D-фігурка Booster Shop у тематиці Pokémon; ми не є ліцензіатом, партнером чи афілійованою особою правовласника. Реквізит, який видно поруч на знімках, не входить до замовлення.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-mew-300-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-mew-300-button-2" type="button"><span>Чи можна замовити Mew, якщо готової моделі немає на складі?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-mew-300-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-mew-300-panel-2" role="region">
<p>Так. Ми друкуємо цю модель самі в Booster Shop. Якщо готової kit card немає в наявності, типовий строк виготовлення становить 1–2 робочих дні.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-mew-300-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-mew-300-button-3" type="button"><span>Чи є видимі шари на поверхні Mew браком?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-mew-300-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-mew-300-panel-3" role="region">
<p>Ні. Пошаровий 3D-друк залишає характерну фактуру, тому окремі лінії шарів можуть бути видимими. Для виробу, надрукованого з PLA, це нормальна технологічна особливість.</p>
</div>
</div>
</section>
```

### Attributes

- Тип виробу: `фігурка-конструктор`
- Країна виготовлення: `Україна`
- Спосіб виготовлення: `пошаровий 3D-друк`
- Матеріал: `Пластик PLA`
- Колір: `багатоколірний, основний — рожевий`
- Розміри: `154×134×2 мм (kit card до складання)`
- Маса: `орієнтовно 25,17 г`
- Комплектація: `1 kit card з деталями для складання 1 фігурки`
- Вікове позиціонування: `14+`
- Типовий строк виготовлення при відсутності на складі: `1–2 робочих дні`
- Може трапитись у Mystery Box: `Так`
- Рухомі елементи: `немає`
- Призначення: `декоративний / колекційний виріб`

### SEO

- **Meta Title:** `Mew 3D фігурка-конструктор Pokémon | Booster Shop`
- **Meta Description:** `Mew kit card — рожева 3D-фігурка-конструктор із PLA. Самостійне складання без клею, виготовлення Booster Shop в Україні.`
- **Meta Keywords:** `Mew figure, Mew 3D figure, Mew kit card, фігурка Mew, фігурка-конструктор Mew, Pokémon 3D фігурки`
- **SEO URL:** `figurka-konstruktor-mew-pokemon-3d-druk`

### Internal links

- Pokémon → Фігурки та декор
- Jigglypuff як інша компактна модель серії

---

## 4.3 `FIG-UMBRE-300`

### Назва

**Фігурка-конструктор Umbreon (Pokémon) — 3D-друк**

### HTML body

```html
<h2>Umbreon kit card — темна фігурка-конструктор із контрастними акцентами</h2>

<p>Фігурка-конструктор Umbreon легко впізнається ще до того, як починаєш роздивлятися дрібні деталі: чорний корпус, жовті кільця, червоні очі, довгі вуха й хвіст дають дуже виразний силует. У готовому вигляді модель помітно контрастує з яскравішими персонажами на полиці.</p>

<p>До складання всі частини розміщені в пласкій kit card. <strong>З рамки поступово формується об’ємний Umbreon, який самостійно стоїть після складання.</strong> Тут добре відчувається сама ідея формату: початкова заготовка й готова фігурка виглядають як два різні стани одного предмета.</p>

<p>Для колекції Покемон це вдалий варіант, якщо хочеться темнішої палітри й самого процесу складання, а не ще однієї готової статуетки.</p>
```

### FAQ

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-fig-umbre-300">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-umbre-300-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-umbre-300-button-1" type="button"><span>Що входить у комплект Umbreon?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-umbre-300-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-umbre-300-panel-1" role="region">
<p>Ви отримуєте одну незібрану kit card з усіма деталями для складання однієї фігурки Umbreon. Готова модель на фото показана як приклад після складання. Це неофіційний 3D-виріб Booster Shop у тематиці Pokémon; Booster Shop не є ліцензіатом, партнером або афілійованою особою правовласника. Декор та інші предмети в кадрі до комплекту не входять.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-umbre-300-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-umbre-300-button-2" type="button"><span>Чи потрібен клей для складання Umbreon?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-umbre-300-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-umbre-300-panel-2" role="region">
<p>Ні. Після відокремлення від рамки деталі з’єднуються між собою без клею.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-umbre-300-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-umbre-300-button-3" type="button"><span>Що робити, якщо Umbreon зараз немає в наявності?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-umbre-300-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-umbre-300-panel-3" role="region">
<p>Модель виготовляємо самі методом 3D-друку в Україні. Типовий строк виготовлення при відсутності готового виробу на складі — 1–2 робочих дні.</p>
</div>
</div>
</section>
```

### Attributes

- Тип виробу: `фігурка-конструктор`
- Країна виготовлення: `Україна`
- Спосіб виготовлення: `пошаровий 3D-друк`
- Матеріал: `Пластик PLA`
- Колір: `чорний із жовтими та червоними акцентами`
- Розміри: `159×147×2 мм (kit card до складання)`
- Маса: `орієнтовно 32,5 г`
- Комплектація: `1 kit card з деталями для складання 1 фігурки`
- Вікове позиціонування: `14+`
- Типовий строк виготовлення при відсутності на складі: `1–2 робочих дні`
- Може трапитись у Mystery Box: `Так`
- Рухомі елементи: `немає`
- Призначення: `декоративний / колекційний виріб`

### SEO

- **Meta Title:** `Umbreon 3D фігурка-конструктор Pokémon | Booster Shop`
- **Meta Description:** `Umbreon kit card — чорна 3D-фігурка-конструктор із жовтими акцентами. Складання без клею, власний друк Booster Shop.`
- **Meta Keywords:** `Umbreon figure, Umbreon 3D figure, Umbreon kit card, фігурка Umbreon, фігурка-конструктор Umbreon, Pokémon figure`
- **SEO URL:** `figurka-konstruktor-umbreon-pokemon-3d-druk`

### Internal links

- Gengar як інша темна модель-конструктор
- Pokémon → Фігурки та декор

---

## 4.4 `FIG-GENG-300`

### Назва

**Фігурка-конструктор Gengar (Pokémon) — 3D-друк**

### HTML body

```html
<h2>Gengar kit card — велика рамка, з якої збирається об’ємна фігурка</h2>

<p>Фігурка-конструктор Gengar починається з великої kit card розміром 208×240 мм. У пласкій рамці окремо розміщені частини корпусу, кінцівок, голови та спини, а після складання вони перетворюються на самостійну 3D-фігурку.</p>

<p><strong>У готовій моделі головне — характер.</strong> Фіолетовий корпус, червоні очі, широка біла усмішка, гострі вуха та виступи на спині роблять Gengar виразним і спереду, і під кутом. Через велику рамку тут особливо наочно видно перехід від набору пласких деталей до об’ємного персонажа.</p>

<p>Для фаната Покемон це вже не просто маленька дрібничка «докинути до замовлення», а помітна фігурка-конструктор, яку спершу цікаво зібрати, а потім залишити в колекції або на робочому столі.</p>
```

### FAQ

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-fig-geng-300">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-geng-300-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-geng-300-button-1" type="button"><span>На фото є рамка й готовий Gengar. Що саме продається?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-geng-300-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-geng-300-panel-1" role="region">
<p>Продається одна незібрана kit card з усіма деталями для складання однієї фігурки Gengar. Зібраний Gengar на фото демонструє фінальний вигляд і окремо до комплекту не додається. Це неофіційний 3D-друкований виріб Booster Shop у тематиці Pokémon; ми не є ліцензіатом, партнером чи афілійованою особою правовласника. Інші предмети на фотографіях до комплекту не входять.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-geng-300-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-geng-300-button-2" type="button"><span>Для складання Gengar потрібен клей?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-geng-300-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-geng-300-panel-2" role="region">
<p>Ні. Деталі kit card з’єднуються між собою без використання клею.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-geng-300-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-geng-300-button-3" type="button"><span>Чи можуть на поверхні Gengar бути видимі сліди пошарового друку?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-geng-300-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-geng-300-panel-3" role="region">
<p>Так. Gengar друкується з PLA пошарово, тому характерні лінії шарів можуть бути помітними на поверхні. Це нормальна риса 3D-друку.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-geng-300-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-geng-300-button-4" type="button"><span>Як швидко ви надрукуєте Gengar, якщо його немає в наявності?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-geng-300-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-geng-300-panel-4" role="region">
<p>Якщо готової моделі немає на складі, типовий строк виготовлення в Booster Shop становить 1–2 робочих дні.</p>
</div>
</div>
</section>
```

### Attributes

- Тип виробу: `фігурка-конструктор`
- Країна виготовлення: `Україна`
- Спосіб виготовлення: `пошаровий 3D-друк`
- Матеріал: `Пластик PLA`
- Колір: `фіолетовий із червоними та білими акцентами`
- Розміри: `208×240×2 мм (kit card до складання)`
- Маса: `орієнтовно 63,46 г`
- Комплектація: `1 kit card з деталями для складання 1 фігурки`
- Вікове позиціонування: `14+`
- Типовий строк виготовлення при відсутності на складі: `1–2 робочих дні`
- Може трапитись у Mystery Box: `Так`
- Рухомі елементи: `немає`
- Призначення: `декоративний / колекційний виріб`

### SEO

- **Meta Title:** `Gengar 3D фігурка-конструктор Pokémon | Booster Shop`
- **Meta Description:** `Gengar kit card 208×240 мм — велика 3D-фігурка-конструктор із PLA. Складання без клею, друк Booster Shop в Україні.`
- **Meta Keywords:** `Gengar figure, Gengar 3D figure, Gengar kit card, фігурка Gengar, фігурка-конструктор Gengar, Pokémon Gengar`
- **SEO URL:** `figurka-konstruktor-gengar-pokemon-3d-druk`

### Internal links

- Umbreon
- Pokémon → Фігурки та декор

---

## 4.5 `FIG-MAGIK-300`

### Назва

**Фігурка-конструктор Magikarp (Pokémon) — 3D-друк**

### HTML body

```html
<h2>Magikarp kit card — фігурка-конструктор із характером ще до складання</h2>

<p>3D-фігурка Magikarp не потребує складної пози чи десятків дрібних деталей, щоб привертати увагу. Червоний корпус, великі білі плавці, жовтий гребінь і довгі вуса роблять готову модель навмисно кумедною та дуже впізнаваною.</p>

<p><strong>У форматі kit card цей образ спочатку буквально розкладений по рамці.</strong> Ви відокремлюєте елементи, з’єднуєте їх без клею — і пласка заготовка перетворюється на об’ємного Magikarp, якого вже можна поставити окремо.</p>

<p>У колекції Покемон така модель добре працює як яскравий акцент, а як подарунок — ще й має потрібну частку абсурду. Спочатку даруєш рамку з деталями, а трохи згодом на столі вже стоїть Magikarp із виразом обличчя людини, яка точно знає план. Можливо.</p>
```

### FAQ

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-fig-magik-300">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-magik-300-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-magik-300-button-1" type="button"><span>У якому вигляді постачається Magikarp?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-magik-300-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-magik-300-panel-1" role="region">
<p>У комплекті одна незібрана kit card з усіма деталями для складання однієї фігурки Magikarp. Готова модель на фото показує результат після складання й не додається окремо. Модель є неофіційним виробом Booster Shop у тематиці Pokémon: ми не маємо статусу ліцензіата, партнера чи афілійованої особи правовласника. Предмети навколо моделі на знімках використані лише для зйомки й не додаються.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-magik-300-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-magik-300-button-2" type="button"><span>136×121×2 мм — це габарити готового Magikarp?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-magik-300-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-magik-300-panel-2" role="region">
<p>Ні. У характеристиках зазначений розмір kit card до складання. Після складання пласкі деталі формують окрему об’ємну фігурку.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-magik-300-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-magik-300-button-3" type="button"><span>Чи можна замовити Magikarp, якщо його немає готового?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-magik-300-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-magik-300-panel-3" role="region">
<p>Так. Magikarp друкуємо в Booster Shop в Україні з PLA. За відсутності готової kit card типовий строк виготовлення становить 1–2 робочих дні.</p>
</div>
</div>
</section>
```

### Attributes

- Тип виробу: `фігурка-конструктор`
- Країна виготовлення: `Україна`
- Спосіб виготовлення: `пошаровий 3D-друк`
- Матеріал: `Пластик PLA`
- Колір: `червоний із білими та жовтими акцентами`
- Розміри: `136×121×2 мм (kit card до складання)`
- Маса: `орієнтовно 28,75 г`
- Комплектація: `1 kit card з деталями для складання 1 фігурки`
- Вікове позиціонування: `14+`
- Типовий строк виготовлення при відсутності на складі: `1–2 робочих дні`
- Може трапитись у Mystery Box: `Так`
- Рухомі елементи: `немає`
- Призначення: `декоративний / колекційний виріб`

### SEO

- **Meta Title:** `Magikarp 3D фігурка-конструктор Pokémon | Booster Shop`
- **Meta Description:** `Magikarp kit card — яскрава 3D-фігурка-конструктор із плавцями та вусами. Складання без клею, власний 3D-друк в Україні.`
- **Meta Keywords:** `Magikarp figure, Magikarp 3D figure, Magikarp kit card, фігурка Magikarp, фігурка-конструктор Magikarp, Pokémon декор`
- **SEO URL:** `figurka-konstruktor-magikarp-pokemon-3d-druk`

### Internal links

- Squirtle
- Pokémon → Фігурки та декор

---

## 4.6 `FIG-PIKA-300`

### Назва

**Фігурка-конструктор Pikachu (Pokémon) — 3D-друк**

### HTML body

```html
<h2>Pikachu kit card — знайомий персонаж у форматі 3D-конструктора</h2>

<p>Фігурок Pikachu існує безліч, тому тут головна відмінність не в самому персонажі, а в способі взаємодії з ним. Ви отримуєте жовту kit card з окремими деталями й самі складаєте сидячу 3D-фігурку Pikachu замість того, щоб просто дістати готову модель.</p>

<p>Після складання зберігаються всі деталі, які роблять образ миттєво впізнаваним: <strong>червоні щоки, чорні кінчики довгих вух і великий хвіст-блискавка</strong>. Клей не потрібен, а готова фігурка підходить для полиці, робочого столу або місця поруч із колекцією карт.</p>

<p>Для подарунка фанату Покемон це ще й безпечний вибір, коли не хочеться вгадувати конкретний набір карт чи окрему карту: персонаж знайомий майже всім, а формат конструктора додає до сувеніра невеликий процес складання.</p>
```

### FAQ

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-fig-pika-300">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-pika-300-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-pika-300-button-1" type="button"><span>Що саме входить у комплект Pikachu?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-pika-300-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-pika-300-panel-1" role="region">
<p>Ви отримуєте одну незібрану kit card з усіма деталями для складання однієї фігурки Pikachu. Зібраний Pikachu на фото демонструє результат і не є другою фігуркою в наборі. Це неофіційний 3D-друкований товар Booster Shop у тематиці Pokémon; ми не є ліцензіатом, партнером або афілійованою особою правовласника. Інші предмети в кадрі до комплекту не входять.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-pika-300-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-pika-300-button-2" type="button"><span>Для якого віку позиціонується фігурка-конструктор Pikachu?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-pika-300-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-pika-300-panel-2" role="region">
<p>Вікове позиціонування цієї моделі — 14+.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-pika-300-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-pika-300-button-3" type="button"><span>Чи виготовляєте Pikachu під замовлення?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-pika-300-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-pika-300-panel-3" role="region">
<p>Якщо готової kit card немає на складі, ми можемо надрукувати її в Booster Shop. Типовий строк виготовлення — 1–2 робочих дні.</p>
</div>
</div>
</section>
```

### Attributes

- Тип виробу: `фігурка-конструктор`
- Країна виготовлення: `Україна`
- Спосіб виготовлення: `пошаровий 3D-друк`
- Матеріал: `Пластик PLA`
- Колір: `жовтий із чорними, червоними та коричневими акцентами`
- Розміри: `154×109×2 мм (kit card до складання)`
- Маса: `орієнтовно 30,27 г`
- Комплектація: `1 kit card з деталями для складання 1 фігурки`
- Вікове позиціонування: `14+`
- Типовий строк виготовлення при відсутності на складі: `1–2 робочих дні`
- Може трапитись у Mystery Box: `Так`
- Рухомі елементи: `немає`
- Призначення: `декоративний / колекційний виріб`

### SEO

- **Meta Title:** `Pikachu 3D фігурка-конструктор Pokémon | Booster Shop`
- **Meta Description:** `Pikachu kit card — 3D-фігурка-конструктор для колекції або подарунка. Складання без клею, PLA, виготовлення в Україні.`
- **Meta Keywords:** `Pikachu figure, Pikachu 3D figure, Pikachu kit card, фігурка Pikachu, фігурка-конструктор Pikachu, подарунок Pokémon`
- **SEO URL:** `figurka-konstruktor-pikachu-pokemon-3d-druk`

### Internal links

- інші товари з Pikachu
- Squirtle як сусідня kit card серії

---

## 4.7 `FIG-SQUIR-300`

### Назва

**Фігурка-конструктор Squirtle (Pokémon) — 3D-друк**

### HTML body

```html
<h2>Squirtle kit card — пласкі деталі, з яких формується об’ємний панцир</h2>

<p>Фігурка-конструктор Squirtle особливо добре показує, навіщо взагалі потрібен формат kit card. На рамці блакитні, жовті та коричневі елементи існують окремо, а після складання формують голову, лапи, живіт, закручений хвіст і об’ємний панцир.</p>

<p><strong>Саме панцир найбільше змінює сприйняття моделі.</strong> З набору тонких деталей виходить 3D-фігурка, яку вже можна розглядати з різних боків і поставити окремо. Для складання не потрібен клей.</p>

<p>Для колекції Покемон Squirtle цікавий не лише готовим виглядом, а й самим перетворенням: починаєте з майже двовимірної рамки, а закінчуєте невеликою моделлю з добре помітним об’ємом.</p>
```

### FAQ

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-fig-squir-300">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-squir-300-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-squir-300-button-1" type="button"><span>Що я отримаю разом із Squirtle kit card?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-squir-300-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-squir-300-panel-1" role="region">
<p>Комплект містить одну незібрану kit card з усіма деталями для складання однієї фігурки Squirtle. Готова модель на фотографіях показує результат після складання та не входить окремим другим виробом. Kit card виготовляє Booster Shop, і це неофіційний виріб у тематиці Pokémon: ми не є ліцензіатом, партнером чи афілійованою особою правовласника. Інші предмети на фото до комплекту не входять.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-squir-300-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-squir-300-button-2" type="button"><span>157×99×2 мм — це розмір зібраного Squirtle?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-squir-300-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-squir-300-panel-2" role="region">
<p>Ні. Це габарити пласкої kit card до складання. Після з’єднання деталей модель стає об’ємною.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-squir-300-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-squir-300-button-3" type="button"><span>Чи є лінії шарів на Squirtle дефектом?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-squir-300-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-squir-300-panel-3" role="region">
<p>Ні. Фігурка виготовляється пошаровим 3D-друком із PLA, тому характерна фактура може залишатися видимою. Це нормальна особливість способу виготовлення.</p>
</div>
</div>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-squir-300-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-squir-300-button-4" type="button"><span>Скільки чекати, якщо Squirtle немає на складі?</span></button></h3>
<div aria-labelledby="bs-faq-prod-fig-squir-300-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-squir-300-panel-4" role="region">
<p>Типовий строк виготовлення при відсутності готової моделі — 1–2 робочих дні. Squirtle друкуємо самі в Booster Shop в Україні.</p>
</div>
</div>
</section>
```

### Attributes

- Тип виробу: `фігурка-конструктор`
- Країна виготовлення: `Україна`
- Спосіб виготовлення: `пошаровий 3D-друк`
- Матеріал: `Пластик PLA`
- Колір: `блакитний, жовтий та коричневий`
- Розміри: `157×99×2 мм (kit card до складання)`
- Маса: `орієнтовно 28,7 г`
- Комплектація: `1 kit card з деталями для складання 1 фігурки`
- Вікове позиціонування: `14+`
- Типовий строк виготовлення при відсутності на складі: `1–2 робочих дні`
- Може трапитись у Mystery Box: `Так`
- Рухомі елементи: `немає`
- Призначення: `декоративний / колекційний виріб`

### SEO

- **Meta Title:** `Squirtle 3D фігурка-конструктор Pokémon | Booster Shop`
- **Meta Description:** `Squirtle kit card — 3D-фігурка-конструктор із панциром і закрученим хвостом. PLA, складання без клею, друк в Україні.`
- **Meta Keywords:** `Squirtle figure, Squirtle 3D figure, Squirtle kit card, фігурка Squirtle, фігурка-конструктор Squirtle, Pokémon 3D`
- **SEO URL:** `figurka-konstruktor-squirtle-pokemon-3d-druk`

### Internal links

- Pikachu
- Pokémon → Фігурки та декор

---

# 5. Дані для звірки з «Розрахунок друку»

| SKU | Вага | Розмір kit card |
|---|---:|---:|
| FIG-JIGGL-300 | 29,67 г | 112×127×2 мм |
| FIG-MEW-300 | 25,17 г | 154×134×2 мм |
| FIG-UMBRE-300 | 32,5 г | 159×147×2 мм |
| FIG-GENG-300 | 63,46 г | 208×240×2 мм |
| FIG-MAGIK-300 | 28,75 г | 136×121×2 мм |
| FIG-PIKA-300 | 30,27 г | 154×109×2 мм |
| FIG-SQUIR-300 | 28,7 г | 157×99×2 мм |

Усі 7:
- `Може трапитись у Mystery Box = Так`
- `Вікове позиціонування = 14+`
- `Матеріал = Пластик PLA`
- `Країна виготовлення = Україна`
- `Призначення = декоративний / колекційний виріб`

Складання без клею — факт картки, а не атрибут: він звучить у тілі кожного опису
та у FAQ. Окремого атрибута `Складання` в базі немає й заводити його не треба
(рішення власника 2026-08-28).

---

# 6. Patch requirements для Claude

Патч має:

1. знайти існуючу модель зберігання product content / SEO / attributes для Booster Shop;
2. створити або оновити тільки ці 7 SKU;
3. не міняти інші товари;
4. зберегти HTML body без автоперефразування;
5. зберегти FAQ accordion без спрощення або переходу на legacy markup;
6. правильно замапити атрибути у чинні attribute IDs / groups замість створення дублікатів;
7. `Виробник = Booster Shop` використовувати як нативне поле, якщо воно так реалізовано на сайті;
8. не створювати атрибут `Формат`;
9. Meta Title, Meta Description, Meta Keywords та SEO URL записати в існуючі SEO-поля;
10. не додавати автоматично `official`, `licensed`, `original merch` або подібні формулювання;
11. якщо потрібне створення SEO alias/route — перевірити на конфлікт перед insert;
12. якщо SKU вже існує — робити idempotent update, а не duplicate insert;
13. ціна, залишок і статус задані власником 2026-08-28: `price = 1.0000` (заглушка), `quantity = 0`, `status = 0`, `stock_status_id = 8`, `image` порожній. Закупівельну собівартість та інші комерційні поля не чіпати й не вигадувати;
14. не використовувати внутрішні колонки `P/Q` з «Розрахунок друку» — вони не стосуються рядків цих товарів.

---

# 7. Acceptance checks після патчу

Для кожного SKU перевірити:

- [ ] товар відкривається без 404;
- [ ] H1 = затверджена назва;
- [ ] категорія = Pokémon → Фігурки та декор;
- [ ] виробник = Booster Shop;
- [ ] body відображається без зламаного HTML;
- [ ] `Покемон` у body рівно 1 раз;
- [ ] FAQ відкривається/закривається;
- [ ] усі `aria-controls` мають відповідний panel ID;
- [ ] усі `aria-labelledby` мають відповідний button ID;
- [ ] ID не дублюються між картками;
- [ ] Meta Title ≤ 63 символи;
- [ ] Meta Description ≤ 155 символів;
- [ ] SEO URL унікальний;
- [ ] атрибути не створили дублікати вже існуючих attribute definitions;
- [ ] `Може трапитись у Mystery Box = Так`;
- [ ] `14+`;
- [ ] строк виготовлення = `1–2 робочих дні`;
- [ ] на сторінці немає тверджень про офіційність/ліцензійність;
- [ ] інші товари/категорії не змінені.

---

# 8. Batch QA перед передачею

Контентний пакет після polishing pass:

- 7 карток;
- 23 FAQ;
- exact duplicate H2 = 0;
- exact duplicate body sentences = 0;
- exact duplicate FAQ questions = 0;
- exact duplicate FAQ answers = 0;
- англомовні імена персонажів = 7/7;
- кириличний `Покемон` у body = рівно 1 × 7;
- mandatory legal+contents FAQ = 7/7;
- FAQ IDs унікальні;
- Meta Title / Description у межах 63 / 155;
- internal client lexicon у shopper-facing copy = 0;
- фінальний контентний verdict = **PASS**.

Перерахунок Claude 2026-08-28 на виправленому пакеті: 7 карток, 23 FAQ, 46 id
(усі унікальні), дослівних повторів речень у FAQ між картками — 0, дослівних
повторів речень у тілах — 0, дублікатів питань і відповідей — 0, `Покемон` у тілі
рівно 1× на кожній картці, Meta Title 49–56, Meta Description 115–120, атрибутів
12 на картку, усі імена атрибутів існують у базі.

---

# 9. Що не треба переосмислювати в Claude

Це **handoff для імплементації**, а не запрошення переписати контент.

Якщо Claude бачить технічну проблему з мапінгом полів або схемою БД — нехай адаптує спосіб запису, але **не змінює затверджений shopper-facing copy**, FAQ semantics, SEO intent або naming без окремого погодження.

