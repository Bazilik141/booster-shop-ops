# SEO-аудит описів + нові картки товарів
_Дата: 2026-06-04 | Джерело: backup-6.3.2026 (boosters_ocart49.sql)_

---

## ЧАСТИНА 1 — SEO-аудит: відповідність шаблону

### Еталонна структура (Mega Brave ID=57 / Mega Symphonia ID=50)

```
H2: "Оригінальний японський sealed-бустер [Name]"
H2: "Чому саме [Name]"
H2: "Chase Cards сету"
H2: "Чому купують у Booster Shop"
H2: "FAQ"
  — Що означає sealed?
  — Що означає unweighed?
  — Чи бустери оригінальні?
  — Чи бустери закуповуються з box/case?
  — Чим [Name] відрізняється від [парного сету]?
```

**Атрибути (обов'язковий набір для бустера):**
- Назва сету
- Мова
- Тип пакування
- Кількість карток у бустері
- Зважування
- Стан
- Походження товару
- Виробник
- Рік випуску

---

## ОГЛЯД ПРОБЛЕМ

### 🔴 КРИТИЧНІ — виправити першочергово

| ID | Назва | Проблема |
|----|-------|----------|
| 79 | Mega Dream EX BST | H3 замість H2 · Немає Chase Cards розділу · **Атрибут "Кількість карток у бустері" = "Японська (Japanese)" — data entry помилка** |
| 80 | OP-15 BOX | H3 замість H2 · Немає `bs-faq` (використано `<details>`/`<summary>`) · Немає Chase Cards · **Немає жодних атрибутів** · Короткий опис (1698 chars) |
| 81 | Black Bolt BST | H3 замість H2 · Немає `bs-faq` (використано `<details>`) · Немає Chase Cards |
| 82 | White Flare BST | H3 замість H2 · Немає Chase Cards |

### 🟡 ВАЖЛИВІ — виправити в поточному спринті

| ID | Назва | Проблема |
|----|-------|----------|
| 58 | Hot Wind Arena BOX | Немає Chase Cards розділу |
| 63 | Ninja Spinner BOX | Немає Chase Cards (бустер ID=74 має — непослідовність між парою) |
| 57 | Mega Brave BST | Meta title 73ch (>70) |
| 50 | Mega Symphonia BST | Meta title 88ch · Meta desc 174ch |

### 🟠 META TITLE/DESC — масова проблема

Google обрізає title в SERP на ~60–65 символах. Більшість тайтлів мають 73–99 chars.
Нижче зведена таблиця з конкретними правками.

---

## ДЕТАЛІ: META TITLE ТА META DESC

### Рекомендований формат

**Бустер-пак:**
```
[Назва сету] бустер [Гра] JP — sealed | Booster Shop   (≤60 chars)
```
**Бустер-бокс:**
```
[Назва сету] бокс [Гра] JP — sealed | Booster Shop
```

### Конкретні правки (від найдовших до нормальних)

| ID | Назва | Поточний title (chars) | ✅ Рекомендований title | Chars |
|----|-------|------------------------|------------------------|-------|
| 78 | OP-15 BST | 99 | `OP-15 Adventure on KAMI's Island бустер One Piece JP \| Booster Shop` | 68 |
| 69 | OP-07 BST | 97 | `OP-07 500 Years in the Future бустер One Piece JP \| Booster Shop` | 65 |
| 70 | OP-11 BST | 95 | `OP-11 A Fist of Divine Speed бустер One Piece JP \| Booster Shop` | 65 |
| 71 | OP-12 BST | 93 | `OP-12 Legacy of the Master бустер One Piece JP \| Booster Shop` | 62 |
| 80 | OP-15 BOX | 91 | `OP-15 Пригоди на острові богів бокс One Piece JP \| Booster Shop` | 65 |
| 59 | Munikis Zero BST | 86 | `Munikis Zero бустер Pokémon TCG JP — sealed \| Booster Shop` | 59 |
| 65 | SEB BOX | 85 | `Super Electric Breaker бокс Pokémon TCG KR — sealed \| Booster Shop` | 68 |
| 68 | OP-10 BST | 84 | `OP-10 Royal Blood бустер One Piece JP — sealed \| Booster Shop` | 62 |
| 73 | Outlet Mix BST | 84 | `Pokémon TCG Outlet Mix бустер JP — sealed \| Booster Shop` | 57 |
| 66 | Promo Vol.7 | 79 | `One Piece Promotion Pack Vol.7 JP — PROMO \| Booster Shop` | 57 |
| 58 | HWA BOX | 77 | `Hot Wind Arena бокс Pokémon TCG KR — sealed \| Booster Shop` | 59 |
| 76 | PTB ex | 77 | `Premium Trainer Box ex Pokémon TCG JP — sealed \| Booster Shop` | 62 |
| 63 | NS BOX | 75 | `Ninja Spinner бокс Pokémon TCG JP — sealed \| Booster Shop` | 58 |
| 56 | MS BOX | 76 | `Mega Symphonia бокс Pokémon TCG JP — sealed \| Booster Shop` | 59 |
| 74 | NS BST | 76 | `Ninja Spinner бустер Pokémon TCG JP — sealed \| Booster Shop` | 60 |
| 72 | OP-14 BST | 72 | `OP-14 Azure Sea's Seven бустер One Piece JP \| Booster Shop` | 59 |
| 61 | MZ BOX | 74 | `Munikis Zero бокс Pokémon TCG JP — sealed \| Booster Shop` | 57 |
| 50 | MS BST | 88 | `Mega Symphonia бустер Pokémon TCG JP — sealed \| Booster Shop` | 61 |
| 57 | MB BST | 73 | `Mega Brave бустер Pokémon TCG JP — sealed \| Booster Shop` | 57 |
| 67 | EB-03 BST | ✅ 66 | Можна залишити або: `EB-03 Heroines Edition бустер One Piece JP \| Booster Shop` | 57 |
| 77 | Mystery Mix | ✅ 69 | ✅ OK |  |
| 79 | Mega Dream EX BST | ✅ 67 | ✅ OK — залишити |  |
| 81 | Black Bolt BST | ✅ 64 | ✅ OK — залишити |  |
| 82 | White Flare BST | ✅ 65 | ✅ OK — залишити |  |

### Meta Description >160 chars → обрізати

| ID | Поточна (chars) | ✅ Правка |
|----|-----------------|----------|
| 78 OP-15 BST | 175 | Прибрати "Japanese Edition" з кінця. Вкоротити до ≤160 |
| 50 MS BST | 174 | Прибрати "Чесні pull rates," → 156 chars |
| 59 MZ BST | 172 | Прибрати "Чесні pull rates," |
| 70 OP-11 BST | 171 | Прибрати "Japanese Edition" з кінця |
| 73 Outlet Mix | 170 | Скоротити текст |
| 65 SEB BOX | 169 | Скоротити текст |
| 71 OP-12 BST | 169 | Прибрати "Japanese Edition" |

---

## ДЕТАЛІ: СТРУКТУРНІ ВІДХИЛЕННЯ

### ID=79 — Mega Dream EX BST
**Статус: Критичний**

Проблеми:
1. **H3 замість H2** — вся структура описів побудована на H3, тоді як шаблон вимагає H2
2. **Відсутній розділ Chase Cards** — замість окремої секції, чейс-карти перемішані з основним текстом у вигляді `<ul>`
3. **Атрибут-помилка**: "Кількість карток у бустері" = `"Японська (Japanese)"` (скопійовано не те поле)

Правки:
- Замінити всі `<h3>` → `<h2>` у тілі опису (крім FAQ, де `<h2 class="bs-faq-title">` правильний)
- Додати секцію `<h2>Chase Cards сету</h2>` з переліком Mega Gengar ex SAR, Mega Dragonite ex MUR, Pikachu ex SAR, Mega Charizard X MA
- Виправити атрибут: "Кількість карток у бустері" → `10`

### ID=80 — OP-15 BOX
**Статус: Критичний**

Проблеми:
1. **H3 замість H2** — вся структура на H3
2. **`<details>`/`<summary>` замість `bs-faq` accordion** — не відповідає поточному markup стандарту
3. **Відсутній Chase Cards розділ**
4. **Немає жодних атрибутів** у таблиці `ocp5_product_attribute`
5. **Короткий опис**: 1698 chars (норма 2500+)
6. **FAQ-питання** відрізняються від стандартних 5 питань шаблону

Правки:
- Повністю переписати FAQ з `<details>` на `bs-faq` accordion markup
- Додати Chase Cards секцію (Enel Manga Rare, Koby Manga Rare, Luffy SEC★ Parallel)
- Змінити H3 → H2 для основних секцій
- Заповнити атрибути (Назва сету: Adventures on KAMI's Island OP-15, Бандай, 2026, 24 бустери, 6 карт, Booster Box)

### ID=81 — Black Bolt BST
**Статус: Критичний**

Проблеми:
1. **H3 замість H2**
2. **`<details>`/`<summary>` замість `bs-faq`**
3. **Відсутній Chase Cards розділ**

Правки:
- Замінити `<details>/<summary>` на стандартний `bs-faq` markup
- Замінити H3 → H2
- Додати `<h2>Chase Cards сету</h2>` з Zekrom ex BWR, N SIR

### ID=82 — White Flare BST
**Статус: Важливий**

Проблеми:
1. **H3 замість H2** (але має правильний `bs-faq` markup — краще за 81)
2. **Відсутній Chase Cards розділ**

Правки:
- Замінити H3 → H2
- Додати Chase Cards секцію: Reshiram ex BWR, Victini BWR, Hilda SIR

### ID=58 — Hot Wind Arena BOX
**Статус: Важливий**

Проблема: Немає розділу Chase Cards.
Правка: Додати H2 "Chase Cards сету" між "Чому саме Hot Wind Arena" і "Чому купують у Booster Shop".

### ID=63 — Ninja Spinner BOX
**Статус: Важливий**

Проблема: Парний бустер (ID=74) має Chase Cards, а бокс — ні. Непослідовність.
Правка: Скопіювати Chase Cards розділ з ID=74 і адаптувати для боксу.

---

## ЧАСТИНА 2 — НОВІ КАРТКИ ТОВАРІВ

---

# КАРТКА 1: YGO-JP-QCAC-BST
# Yu-Gi-Oh! — Quarter Century Art Collection — JP — Booster

---

## SEO-поля

**Keyword (SEO URL):**
```
YGO-JP-QCAC-BST
```

**Meta Title (58 chars):**
```
Quarter Century Art Collection бустер Yu-Gi-Oh! JP | Booster Shop
```

**Meta Description (152 chars):**
```
Японський бустер Yu-Gi-Oh! QCAC — 4 карти, Dark Magician Girl QCSR, Blue-Eyes White Dragon QCSR. Sealed, без зважування, з box. Доставка Новою поштою.
```

**Meta Keywords:**
```
yu-gi-oh quarter century art collection, QCAC бустер, yu-gi-oh ocg japanese booster, dark magician girl qcsr, blue-eyes white dragon qcsr, югіо японський бустер, quarter century secret rare, QCAC-JP купити
```

---

## Назва товару (H1 / OpenCart name)

```
Бустер Yu-Gi-Oh! OCG: Quarter Century Art Collection (Японське видання)
```

---

## Опис — HTML для поля Description в OpenCart

```html
<h2>Оригінальний японський sealed-бустер Quarter Century Art Collection</h2>

<p>
  <strong>Quarter Century Art Collection (QCAC)</strong> — японський OCG-бустер від Konami,
  випущений 22 лютого 2025 року на честь <strong>25-річчя Yu-Gi-Oh!</strong>
  Сет цілком присвячений ювілейним варіантам artwork для найiконічніших карт серії:
  Blue-Eyes White Dragon, Dark Magician, Dark Magician Girl та інших легенд формату.
  Усі 100 карт сету — це оновлені або ексклюзивні зображення класичних монстрів, 
  доступні у рідкісних паралельних варіантах.
</p>

<p>
  Кожен <strong>sealed-бустер містить 4 карти</strong>. Формат сету побудований навколо
  двох ключових рівнів рідкісності — <strong>Ultra Rare</strong> та <strong>Super Rare</strong>,
  а найцінніші карти виходять у версіях <strong>Secret Rare</strong> та
  <strong>Quarter Century Secret Rare (QCSR)</strong> — ексклюзивному ювілейному фінішу
  з характерним золотим текстом і голографічним фоном.
</p>

<p>
  Бустери закуплені з повноцінних боксів і продаються <strong>без зважування</strong>.
  Кожен пак — у заводському стані без ручного відбору.
</p>

<h2>Чому саме Quarter Century Art Collection</h2>

<p>
  QCAC — рідкісний для Yu-Gi-Oh! формат "арт-колекції": тут немає нових ігрових ефектів,
  лише нові ілюстрації для карт, які вже є у кожному стартовому наборі та пам'яті 
  будь-якого гравця. Саме тому сет приваблює одразу два типи покупців — 
  досвідчених колекціонерів OCG і ностальгуючих фанатів оригінального аніме.
  Японське видання виходить раніше та має вищу деталізацію друку порівняно зі 
  світовими версіями.
</p>

<h2>Chase Cards сету</h2>

<p>Серед найбільш очікуваних карт сету колекціонери виділяють:</p>

<ul>
  <li>
    <strong>Dark Magician Girl (3rd Artwork) QCAC-JP019 — Quarter Century Secret Rare</strong> —
    найрідкісніша та найбажаніша позиція сету. Ексклюзивна для QCSR: рожевий текст імені та
    логотипу замість стандартного кольору. Доступна лише в QCSR-версії.
  </li>
  <li>
    <strong>Blue-Eyes White Dragon (3rd Artwork) QCAC-JP021 — Quarter Century Secret Rare</strong> —
    нова ілюстрація для культової карти, доступна виключно у форматі QCSR.
  </li>
  <li>
    <strong>Dark Magician (Arkana Artwork) QCAC-JP018 — Quarter Century Secret Rare</strong> —
    ілюстрація з версією Темного Мага Аркани, відомою фанатам аніме.
  </li>
  <li>
    <strong>Blue-Eyes White Dragon (2nd Artwork) QCAC-JP021 — Ultra/Secret Rare</strong> —
    класична альтернативна ілюстрація у ювілейному виконанні.
  </li>
</ul>

<h2>Чому купують у Booster Shop</h2>

<ul>
  <li>тільки оригінальні японські OCG-бустери</li>
  <li>без зважування та перевідбору</li>
  <li>акуратне пакування для збереження mint condition</li>
  <li>швидка відправка по Україні</li>
  <li>безкоштовна доставка Новою Поштою від 1500 грн</li>
</ul>

<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-ygo-qcac-bst">
  <h2 class="bs-faq-title">FAQ</h2>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-ygo-qcac-button-1" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-ygo-qcac-panel-1">
        <span>Що означає sealed?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-ygo-qcac-panel-1" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-ygo-qcac-button-1" hidden>
      <p>Sealed означає заводське пакування бустера без розкриття, перепакування чи стороннього втручання.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-ygo-qcac-button-2" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-ygo-qcac-panel-2">
        <span>Що означає unweighed?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-ygo-qcac-panel-2" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-ygo-qcac-button-2" hidden>
      <p>Unweighed означає, що бустер не зважувався перед продажем. Це важливо для чесного розподілу шансів на рідкісні карти та довіри колекціонерів.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-ygo-qcac-button-3" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-ygo-qcac-panel-3">
        <span>Чи бустери оригінальні?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-ygo-qcac-panel-3" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-ygo-qcac-button-3" hidden>
      <p>Так, продаємо лише оригінальні японські OCG-бустери Yu-Gi-Oh! від Konami.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-ygo-qcac-button-4" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-ygo-qcac-panel-4">
        <span>Чи бустери закуповуються з box/case?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-ygo-qcac-panel-4" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-ygo-qcac-button-4" hidden>
      <p>Так, бустери закуповуються з повноцінних боксів без перевідбору, що виключає ручне сортування та втручання у розподіл карт.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-ygo-qcac-button-5" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-ygo-qcac-panel-5">
        <span>Чим OCG відрізняється від TCG?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-ygo-qcac-panel-5" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-ygo-qcac-button-5" hidden>
      <p>OCG (Official Card Game) — японська/азійська версія Yu-Gi-Oh!, яку видає Konami Japan. TCG (Trading Card Game) — міжнародна версія для Європи та США. Карти OCG виходять раніше, мають японський текст і часто відрізняються artwork та номерами карт від TCG-версій. Колекціонери цінують OCG за якість друку та ранній доступ до нових сетів.</p>
    </div>
  </div>
</section>
```

---

## Характеристики (атрибути OpenCart)

| Атрибут | Значення |
|---------|----------|
| Назва сету | Quarter Century Art Collection |
| Мова | Японська (Japanese) |
| Тип пакування | Sealed Booster Pack |
| Кількість карток у бустері | 4 |
| Зважування | Без зважування (Unweighed) |
| Стан | Новий, нерозпакований (Sealed) |
| Походження товару | Box / Case sourced (з оригінального боксу / кейсу) |
| Виробник | Konami |
| Рік випуску | 2025 |

---

## Примітки до картки QCAC

- **Keyword/SEO URL:** `YGO-JP-QCAC-BST` (відповідає SKU, формат сайту)
- **Виробник у OpenCart:** Konami (перевірити/додати якщо немає в списку)
- **Перевірити перед публікацією:** Фото продукту. Ціна.
- **Примітка щодо Chase:** QCSR-версії Dark Magician Girl і BEWD виходять **лише** як QCSR — не існує SR/UR версій цих конкретних artwork. Це потрібно зберегти у формулюванні.
- **Related products:** Інші Yu-Gi-Oh! OCG сети (якщо є в каталозі)

---
---

# КАРТКА 2: OP-JP-OP08-BST
# One Piece — OP-08 Two Legends — JP — Booster

---

## SEO-поля

**Keyword (SEO URL):**
```
OP-JP-OP08-BST
```

**Meta Title (62 chars):**
```
OP-08 Two Legends бустер One Piece JP — sealed | Booster Shop
```

**Meta Description (155 chars):**
```
Японський бустер One Piece OP-08 Two Legends — 6 карт, Rayleigh Manga Rare, Nami SP. Sealed, без зважування, box sourced. Доставка Новою поштою.
```

**Meta Keywords:**
```
one piece op-08 two legends, OP-08 бустер японський, one piece tcg op08, two legends booster, rayleigh manga rare op08, nami sp op08-106, ван піс карти японські, one piece card game two legends купити
```

---

## Назва товару (H1 / OpenCart name)

```
Бустер One Piece Card Game OP-08 Two Legends (Японське видання)
```

---

## Опис — HTML для поля Description в OpenCart

```html
<h2>Оригінальний японський sealed-бустер OP-08 Two Legends</h2>

<p>
  <strong>OP-08 Two Legends</strong> — японський сет One Piece Card Game,
  випущений у травні 2024 року. Це один із найбільш ціноване для колекціонерів
  ранніх сетів серії: сет присвячений <strong>Silvers Rayleigh</strong> —
  правій руці короля піратів Gol D. Roger — та іншим легендарним персонажам,
  включно з Whitebeard та командами з арок Drum Kingdom і Zou.
</p>

<p>
  Кожен <strong>sealed-бустер містить 6 карт</strong>. Сет ввів ексклюзивний
  <strong>Treasure Rare (TR)</strong> — найрідкіший рівень рідкісності у форматі
  на той момент. Усього в OP-08 126 унікальних карт + 1 Treasure Rare.
  Оригінальне японське видання виходило на 4 місяці раніше за міжнародний реліз.
</p>

<p>
  Бустери закуплені з повноцінних боксів і продаються <strong>без зважування</strong>.
  Кожен пак — у заводському стані без ручного відбору.
</p>

<h2>Чому саме OP-08 Two Legends</h2>

<p>
  Two Legends — один із перших сетів One Piece TCG із фокусом на персонажах
  <strong>дофлямінгового покоління</strong>. Rayleigh як Warlord-рівень і Whitebeard
  зробили цей реліз дуже бажаним серед колекціонерів, які будують деки або
  тематичні колекції. Сет також примітний тим, що Manga Rare (Comic Parallel)
  Rayleigh став одним із найдорожчих raw-карт раннього One Piece TCG.
</p>

<h2>Chase Cards сету</h2>

<p>Серед найбільш очікуваних карт сету колекціонери виділяють:</p>

<ul>
  <li>
    <strong>Silvers Rayleigh Manga Rare (OP08-118, SEC-SP)</strong> —
    головна chase-карта сету у стилі манга-панелей Ейїтіро Оди.
    Виходить приблизно 1 на 72+ бокси — одна з найрідкісніших карт в усьому
    One Piece TCG на момент релізу.
  </li>
  <li>
    <strong>Nami SP (OP08-106)</strong> — Special card із найвищим
    ринковим попитом серед SP-карт сету. Один із топових пулів OP-08.
  </li>
  <li>
    <strong>Silvers Rayleigh Secret Rare (OP08-118)</strong> — основна
    SEC-версія face-карти сету із стабільним колекційним попитом.
  </li>
  <li>
    <strong>Tony Tony.Chopper Leader Parallel (OP08-001)</strong> —
    паралельна версія Leader Card із сильним попитом у складанні колоди.
  </li>
</ul>

<h2>Чому купують у Booster Shop</h2>

<ul>
  <li>тільки оригінальні японські бустери One Piece TCG</li>
  <li>без зважування та перевідбору</li>
  <li>акуратне пакування для збереження mint condition</li>
  <li>швидка відправка по Україні</li>
  <li>безкоштовна доставка Новою Поштою від 1500 грн</li>
</ul>

<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-op-op08-bst">
  <h2 class="bs-faq-title">FAQ</h2>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-op-op08-button-1" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-op-op08-panel-1">
        <span>Що означає sealed?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-op-op08-panel-1" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-op-op08-button-1" hidden>
      <p>Sealed означає заводське пакування бустера без розкриття, перепакування чи стороннього втручання.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-op-op08-button-2" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-op-op08-panel-2">
        <span>Що означає unweighed?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-op-op08-panel-2" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-op-op08-button-2" hidden>
      <p>Unweighed означає, що бустер не зважувався перед продажем. Це важливо для чесного розподілу шансів на рідкісні карти та довіри колекціонерів.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-op-op08-button-3" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-op-op08-panel-3">
        <span>Чи бустери оригінальні?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-op-op08-panel-3" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-op-op08-button-3" hidden>
      <p>Так, продаємо лише оригінальні японські бустери One Piece Card Game від Bandai.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-op-op08-button-4" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-op-op08-panel-4">
        <span>Чи бустери закуповуються з box/case?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-op-op08-panel-4" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-op-op08-button-4" hidden>
      <p>Так, бустери закуповуються з повноцінних боксів без перевідбору, що виключає ручне сортування та втручання у розподіл карт.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-op-op08-button-5" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-op-op08-panel-5">
        <span>Чим OP-08 відрізняється від інших One Piece сетів?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-op-op08-panel-5" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-op-op08-button-5" hidden>
      <p>OP-08 Two Legends — один із перших сетів із фокусом на легендарних піратах «старого покоління» — Rayleigh та Whitebeard. Сет також ввів рідкість Treasure Rare (TR). Для колекціонерів це раннє видання з сильними chase-картами та стабільним попитом на вторинному ринку.</p>
    </div>
  </div>
</section>
```

---

## Характеристики (атрибути OpenCart)

| Атрибут | Значення |
|---------|----------|
| Назва сету | Two Legends OP-08 |
| Мова | Японська (Japanese) |
| Тип пакування | Sealed Booster Pack |
| Кількість карток у бустері | 6 |
| Зважування | Без зважування (Unweighed) |
| Стан | Новий, нерозпакований (Sealed) |
| Походження товару | Box / Case sourced (з оригінального боксу / кейсу) |
| Виробник | Bandai |
| Рік випуску | 2024 |

---

## Примітки до картки OP-08

- **Keyword/SEO URL:** `OP-JP-OP08-BST` (відповідає SKU та формату сайту)
- **Дата релізу JP:** травень 2024
- **6 карт у бустері** — узгоджено з усіма OP-07–OP-15 на сайті (Japanese format)
- **Перевірити перед публікацією:** Ціна. Фото. Наявність Bandai у списку виробників OpenCart.
- **Related products:** OP-07, OP-10, OP-11, OP-12, OP-14, OP-15, EB-03

---

## ЧАСТИНА 3 — QA CHECKLIST ДЛЯ ВЛАСНИКА

### Нові картки (QCAC, OP-08)
- [ ] Виробник Konami є в OpenCart (Catalog → Manufacturers)
- [ ] Всі атрибути правильно заповнені (особлива увага: Рік випуску, К-сть карток)
- [ ] Keyword/SEO URL вставлений (поле "SEO Keyword" у картці товару)
- [ ] Meta Title і Meta Description скопійовані точно
- [ ] HTML опису вставлений у поле Description (Source/HTML view)
- [ ] Фото товару додані
- [ ] Ціна виставлена
- [ ] Товар в правильній категорії (Yu-Gi-Oh / One Piece)
- [ ] Статус Published після готовності

### Критичні виправлення (старі картки)
- [ ] ID=79 Mega Dream EX: виправити атрибут "Кількість карток" (зараз = "Японська (Japanese)")
- [ ] ID=80 OP-15 BOX: заповнити всі атрибути (зараз порожньо)
- [ ] IDs 79–82: замінити H3→H2 у тілі описів (передати Codex)
- [ ] IDs 79–82: додати Chase Cards секцію (передати Codex)
- [ ] IDs 80–81: замінити `<details>/<summary>` на `bs-faq` markup (передати Codex)

---

_Документ підготовлено автоматично на основі аналізу БД та SEO-аудиту._
_Наступний крок: Codex handoff для структурних правок IDs 79–82._
