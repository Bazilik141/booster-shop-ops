# Повна структура категорій Booster Shop
_Дата: 2026-06-04 | Включає нові + оновлення існуючих_

---

## Цільова структура каталогу

```
Pokémon (ID=59)
  ├── Бустери Pokémon (ID=61)
  ├── Бустер бокси Pokémon (ID=62)
  └── Набори Pokémon (ID=64) ← перейменувати з «Набори»

One Piece Card Game (ID=60)
  ├── Бустери One Piece (ID=63)
  └── Набори One Piece ← НОВА (бокси/дисплеї + спецнабори + mystery box)

Інші TCG ← НОВА головна
  ├── Yu-Gi-Oh! ← НОВА підкатегорія
  └── Magic: The Gathering ← НОВА підкатегорія
```

---

## Зведена таблиця — всі категорії

| # | Назва | Тип | Parent | SEO URL | Дія |
|---|-------|-----|--------|---------|-----|
| 59 | Pokémon | Головна | — | `pokemon` | Оновити meta |
| 60 | One Piece Card Game | Головна | — | `one-piece` | Оновити meta |
| 61 | Бустери Pokémon | Під | 59 | `bustери-pokemon` | Оновити meta |
| 62 | Бустер бокси Pokémon | Під | 59 | `bustер-boksy-pokemon` | Оновити meta |
| 63 | Бустери One Piece | Під | 60 | `bustери-one-piece` | Оновити meta |
| 64 | **Набори Pokémon** | Під | 59 | `nabory-pokemon` | Перейменувати + оновити |
| — | **Набори One Piece** | Під | 60 | `one-piece-nabory` | НОВА |
| — | **Інші TCG** | Головна | — | `inshi-tcg` | НОВА |
| — | **Yu-Gi-Oh!** | Під | Інші TCG | `yu-gi-oh` | НОВА |
| — | **Magic: The Gathering** | Під | Інші TCG | `magic-the-gathering` | НОВА |

---
---

# БЛОК 1 — НОВІ КАТЕГОРІЇ

---

## [НОВА] Інші TCG
**Тип:** Головна категорія (parent = 0)

### Поля OpenCart

**Назва:**
```
Інші TCG
```
**SEO URL:**
```
inshi-tcg
```
**Meta Title (52 chars):**
```
Інші TCG — Yu-Gi-Oh!, MTG та інші ККГ | Booster Shop
```
**Meta Description (156 chars):**
```
Японські бустери Yu-Gi-Oh! OCG, Magic: The Gathering та інших колекційних карткових ігор. Оригінальні sealed-паки, без зважування. Доставка по Україні.
```
**Meta Keywords:**
```
інші ккг бустери, yu-gi-oh ocg japanese, magic the gathering jp booster, інші карткові ігри sealed, other tcg booster ukraine, ккг японські бустери купити
```

### Опис (HTML)

```html
<h2>Інші TCG в Booster Shop</h2>

<p>У цій категорії зібрані оригінальні японські бустери <strong>колекційних карткових ігор</strong>, крім Pokémon та One Piece: <strong>Yu-Gi-Oh! OCG</strong>, <strong>Magic: The Gathering</strong> та інші ККГ, які з'являтимуться в асортименті.</p>

<p>Ми продаємо <strong>sealed-бустери з повноцінних боксів</strong> без ручного відбору. Кожна картка товару містить точну інформацію: назву сету, мову, тип бустера і кількість карт у паку.</p>

<p>Якщо вас цікавить конкретна гра — перейдіть у відповідну підкатегорію нижче.</p>

<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="cat-other-tcg">
  <h2 class="bs-faq-title">FAQ — Інші TCG</h2>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-other-tcg-button-1" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-other-tcg-panel-1">
        <span>Які ігри є в цій категорії?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-other-tcg-panel-1" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-other-tcg-button-1" hidden>
      <p>Зараз у категорії «Інші TCG» є підкатегорії Yu-Gi-Oh! (японське OCG видання від Konami) та Magic: The Gathering (японські Set Boosters від Wizards of the Coast). Асортимент розширюватиметься.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-other-tcg-button-2" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-other-tcg-panel-2">
        <span>Бустери з box чи з розсипу?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-other-tcg-panel-2" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-other-tcg-button-2" hidden>
      <p>З боксу. Усі бустери в цій категорії походять із повноцінних sealed-боксів від постачальника — без розсипних партій і стороннього втручання.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-other-tcg-button-3" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-other-tcg-panel-3">
        <span>Чи є зважування?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-other-tcg-panel-3" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-other-tcg-button-3" hidden>
      <p>Ні. Усі бустери продаються у заводському стані без ручного відбору чи зважування.</p>
    </div>
  </div>
</section>
```

---

## [НОВА] Yu-Gi-Oh!
**Тип:** Підкатегорія (parent = Інші TCG)

### Поля OpenCart

**Назва:**
```
Yu-Gi-Oh!
```
**SEO URL:**
```
yu-gi-oh
```
**Meta Title (50 chars):**
```
Yu-Gi-Oh! OCG бустери JP — sealed | Booster Shop
```
**Meta Description (153 chars):**
```
Оригінальні японські OCG-бустери Yu-Gi-Oh! від Konami. QCAC, World Premiere Pack та інші. Sealed, без зважування, з боксу. Доставка Новою поштою.
```
**Meta Keywords:**
```
yu-gi-oh ocg japanese booster, yugioh бустер японський купити, qcac yu-gi-oh, world premiere pack ocg, konami ocg sealed booster, yugioh qcsr, yu-gi-oh україна
```

### Опис (HTML)

```html
<h2>Yu-Gi-Oh! OCG в Booster Shop</h2>

<p>У цій підкатегорії зібрані оригінальні японські бустери <strong>Yu-Gi-Oh! OCG</strong> від Konami. OCG (Official Card Game) — японська й азійська версія гри, яка виходить раніше за міжнародний TCG і має власні ексклюзивні рідкісності: <strong>Quarter Century Secret Rare (QCSR)</strong> та <strong>Extra Secret Rare</strong>.</p>

<p>Японське OCG-видання відрізняється від міжнародного TCG банлістом, ексклюзивними картами та ранішими релізами сетів. Для колекціонерів японські OCG-бустери мають особливу цінність — деякі рідкісності виходять виключно тут.</p>

<p>Ми продаємо <strong>sealed-бустери без зважування</strong> із повноцінних боксів. Кожна картка товару містить назву сету, кількість карт у паку, рік видання та список ключових рідкісностей.</p>

<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="cat-ygo">
  <h2 class="bs-faq-title">FAQ — Yu-Gi-Oh! OCG</h2>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-ygo-button-1" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-ygo-panel-1">
        <span>Чим OCG відрізняється від TCG?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-ygo-panel-1" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-ygo-button-1" hidden>
      <p>OCG — японська/азійська версія від Konami Japan, TCG — міжнародна для Європи та США. Між ними різні банлісти, ексклюзивні карти та деякі правила. OCG-сети виходять раніше і мають ексклюзивні рідкісності: QCSR, Extra Secret Rare та інші, яких немає в TCG.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-ygo-button-2" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-ygo-panel-2">
        <span>Скільки карт у японському OCG-бустері?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-ygo-panel-2" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-ygo-button-2" hidden>
      <p>Стандартний японський OCG-бустер містить 5 карт. Бокс — 15 бустерів. Деякі спеціальні формати мають інший склад — це завжди вказується в картці товару.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-ygo-button-3" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-ygo-panel-3">
        <span>Чи можна грати японськими OCG-картами у TCG-форматі?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-ygo-panel-3" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-ygo-button-3" hidden>
      <p>На неофіційних іграх — без обмежень. На офіційних OCG-турнірах — лише OCG-картами. На TCG-турнірах японські OCG-карти не дозволені. Для колекціонування мова і формат значення не мають.</p>
    </div>
  </div>
</section>
```

---

## [НОВА] Magic: The Gathering
**Тип:** Підкатегорія (parent = Інші TCG)

### Поля OpenCart

**Назва:**
```
Magic: The Gathering
```
**SEO URL:**
```
magic-the-gathering
```
**Meta Title (55 chars):**
```
Magic: The Gathering бустери JP — sealed | Booster Shop
```
**Meta Description (155 chars):**
```
Оригінальні японські бустери Magic: The Gathering від Wizards of the Coast. Set Boosters, sealed, без зважування, з боксу. Доставка Новою поштою.
```
**Meta Keywords:**
```
magic the gathering japanese booster, mtg jp sealed booster, set booster mtg jp, magic gathering купити, adventures forgotten realms japanese, magic the gathering україна, mtg боостер японський
```

### Опис (HTML)

```html
<h2>Magic: The Gathering в Booster Shop</h2>

<p>У цій підкатегорії зібрані оригінальні японські бустери <strong>Magic: The Gathering</strong> від Wizards of the Coast. Magic — найстаріша колекційна карткова гра у світі (з 1993 року). Японське видання MTG виходить паралельно з англійським і містить ті самі карти з японськими назвами та текстом ефектів.</p>

<p>Ми продаємо <strong>Set Boosters</strong> — формат, створений для відкриття і колекціонування. Кожен пак гарантує: фойлову карту, Showcase або Borderless карту з альтернативним артом, а також шанс на карту з <strong>The List</strong> — добірки зі 300 культових карт за всю 30-річну історію гри.</p>

<p>Бустери продаються <strong>sealed без зважування</strong> із повноцінного запечатаного боксу.</p>

<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="cat-mtg">
  <h2 class="bs-faq-title">FAQ — Magic: The Gathering</h2>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-mtg-button-1" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-mtg-panel-1">
        <span>Що таке Set Booster?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-mtg-panel-1" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-mtg-button-1" hidden>
      <p>Set Booster (12 карт + Art Card + Token) — формат для opening і колекціонування, на відміну від Draft Booster (15 карт для ігрового формату Draft). Кожен Set Booster гарантує фойл, Showcase або Borderless карту та має шанс на карту з The List.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-mtg-button-2" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-mtg-panel-2">
        <span>Чи японські карти MTG легальні для гри?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-mtg-panel-2" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-mtg-button-2" hidden>
      <p>Так. Карти MTG легальні незалежно від мови. Японські карти можна використовувати в колодах разом із картами будь-якої іншої мови.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-mtg-button-3" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-mtg-panel-3">
        <span>Бустери з box чи з розсипу?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-mtg-panel-3" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-mtg-button-3" hidden>
      <p>З боксу. Усі бустери походять із повноцінних sealed-боксів без розсипних партій і стороннього втручання.</p>
    </div>
  </div>
</section>
```

---

## [НОВА] Набори One Piece
**Тип:** Підкатегорія (parent = One Piece Card Game, ID=60)
**Scope:** Бустер бокси / дисплеї + спеціальні набори + mystery box — усі «більші» форми крім поштучних бустерів

### Поля OpenCart

**Назва:**
```
Набори One Piece
```
**SEO URL:**
```
one-piece-nabory
```
**Meta Title (60 chars):**
```
Набори та бокси One Piece Card Game JP/EN — sealed | Booster Shop
```
**Meta Description (160 chars):**
```
Бустер бокси, дисплеї та набори One Piece Card Game японського та англомовного видання. Mystery Mix, sealed box. Оригінальні від Bandai. Доставка по Україні.
```
**Meta Keywords:**
```
набори one piece, one piece booster box display, one piece mystery box, one piece card game sets japanese english, mystery mix one piece, bandai one piece box sealed, бокс ван піс купити
```

### Опис (HTML)

```html
<h2>Набори та бокси One Piece Card Game в Booster Shop</h2>

<p>У цій підкатегорії зібрані <strong>бустер бокси, дисплеї та спеціальні набори One Piece Card Game</strong> японського та англомовного видання від Bandai. Тут усе, крім поштучних бустерів: запечатані бокси на 24 паки, містері бокси та спеціальні сети з ексклюзивними промо-картами.</p>

<p><strong>Бустер бокс (дисплей)</strong> — це повна коробка з 24 запечатаними бустерами в заводській стрічці. Найкращий вибір для повноцінної сесії відкриття одного сету або для sealed-зберігання. Японські бокси містять 6 карт у паку та виходять раніше за міжнародне видання; англомовні бокси дають 12 карт у паку і підходять для гри в англійському TCG-середовищі.</p>

<p><strong>Mystery Mix</strong> — наш власний формат сліпого боксу: 5 оригінальних Japanese sealed-бустерів із мінімум 2 різних сетів One Piece + 1 паралельна карта бонусом. Ідеальний варіант для першого знайомства з грою або як подарунок.</p>

<p>Кожна картка товару містить точний вміст: видання (JP/EN), кількість бустерів, стан та наявність додаткових компонентів.</p>

<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="cat-op-sets">
  <h2 class="bs-faq-title">FAQ — Набори та бокси One Piece</h2>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-op-sets-button-1" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-op-sets-panel-1">
        <span>Чим бокс відрізняється від поштучного бустера?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-op-sets-panel-1" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-op-sets-button-1" hidden>
      <p>Бокс — це заводська коробка з 24 бустерами у заводській стрічці. Поштучний бустер — 1 пакунок окремо. Бокс вигідніший за ціною на пак і дає ширше покриття пулу сету за одне відкриття. Поштучний бустер — для тих, хто хоче спробувати сет без повного боксу.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-op-sets-button-2" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-op-sets-panel-2">
        <span>Чим JP бокс відрізняється від EN?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-op-sets-panel-2" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-op-sets-button-2" hidden>
      <p>Японські бокси (JP): 24 бустери по 6 карт, виходять раніше за міжнародні версії, ексклюзивні Japanese Parallel і Manga Rare карти. Англомовні бокси (EN): 24 бустери по 12 карт, підходять для гри в EN TCG-форматі та мають англійський текст ефектів.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-op-sets-button-3" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-op-sets-panel-3">
        <span>Що таке Mystery Mix?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-op-sets-panel-3" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-op-sets-button-3" hidden>
      <p>Mystery Mix — наш формат сліпого боксу: 5 японських sealed-бустерів One Piece з мінімум 2 різних сетів + 1 паралельна карта. Конкретні сети у складі не вказуються наперед. Гарантуємо бустери з актуальних релізів.</p>
    </div>
  </div>

  <div class="bs-faq-item">
    <h3 class="bs-faq-question">
      <button type="button" id="bs-faq-cat-op-sets-button-4" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-op-sets-panel-4">
        <span>Бокси продаються sealed?</span><span class="bs-faq-icon" aria-hidden="true"></span>
      </button>
    </h3>
    <div id="bs-faq-cat-op-sets-panel-4" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-op-sets-button-4" hidden>
      <p>Так. Усі бокси продаються у заводській стрічці без стороннього втручання — у тому стані, в якому прийшли від постачальника.</p>
    </div>
  </div>
</section>
```

---
---

# БЛОК 2 — ОНОВЛЕННЯ ІСНУЮЧИХ КАТЕГОРІЙ

_Тільки поля Meta Title, Meta Description, Meta Keywords і SEO URL — описи (HTML) залишаються без змін._

---

## ID=59 — Pokémon (головна)

**SEO URL:**
```
pokemon
```
**Meta Title (56 chars):**
```
Pokémon TCG бустери JP / KR / EN — sealed | Booster Shop
```
**Meta Description (158 chars):**
```
Бустери Pokémon TCG японського, корейського та англомовного видання — sealed, без зважування, з боксу. Паки, бокси, набори. Швидка доставка по Україні.
```
**Meta Keywords:**
```
бустери pokémon tcg, купити бустер покемон, japanese pokemon booster, korean pokemon booster, english pokemon booster, sealed pokemon booster pack, pokemon booster box, pokemon tcg ukraine
```

---

## ID=60 — One Piece Card Game (головна)

**SEO URL:**
```
one-piece
```
**Meta Title (58 chars):**
```
One Piece Card Game бустери JP / EN — sealed | Booster Shop
```
**Meta Description (158 chars):**
```
Бустери One Piece Card Game японського та англомовного видання від Bandai. Sealed, без зважування, box sourced. Великий вибір сетів. Доставка по Україні.
```
**Meta Keywords:**
```
бустери one piece card game, купити бустер ван піс, one piece tcg japanese, one piece english booster, bandai one piece sealed booster, ван піс карти японські, one piece card game ukraine
```

---

## ID=61 — Бустери Pokémon (підкатегорія)

**SEO URL:**
```
bustери-pokemon
```
**Meta Title (62 chars):**
```
Бустери Pokémon TCG JP / KR / EN — sealed, unweighed | Booster Shop
```

_Це 68 chars — скорочена версія:_
```
Бустери Pokémon TCG JP/KR/EN — sealed | Booster Shop
```
*(53 chars)*

**Meta Description (160 chars):**
```
Поштучні бустери Pokémon TCG японського, корейського та англомовного видання. Sealed, без зважування, з боксу. Mega Evolution, Scarlet & Violet. Доставка НП.
```
**Meta Keywords:**
```
бустер pokémon tcg, купити бустер покемон поштучно, japanese pokemon booster pack, korean pokemon booster pack, english pokemon booster pack, sealed pokemon booster unweighed, mega evolution booster
```

---

## ID=62 — Бустер бокси Pokémon (підкатегорія)

**SEO URL:**
```
bustер-boksy-pokemon
```
**Meta Title (55 chars):**
```
Бустер бокси Pokémon TCG JP/EN — sealed | Booster Shop
```
**Meta Description (158 chars):**
```
Sealed бустер бокси Pokémon TCG японського та англомовного видання у заводській плівці. Mega Evolution, Scarlet & Violet та інші. Доставка по Україні.
```
**Meta Keywords:**
```
бустер бокс pokémon tcg, booster box pokemon japanese, english pokemon booster box, sealed pokemon booster box, купити бокс покемон, pokemon box japan, mega evolution booster box
```

---

## ID=63 — Бустери One Piece (підкатегорія)

**SEO URL:**
```
bustери-one-piece
```
**Meta Title (58 chars):**
```
Бустери One Piece Card Game JP/EN — sealed | Booster Shop
```
**Meta Description (158 chars):**
```
Поштучні бустери One Piece Card Game японського та англомовного видання. OP-07 до OP-15, EB-03. Sealed, без зважування, box sourced. Доставка по Україні.
```
**Meta Keywords:**
```
бустер one piece card game, купити бустер ван піс, one piece tcg japanese booster, one piece english booster, op07 op08 op10 op11 op12 op14 op15, sealed one piece booster
```

### Опис (HTML) — повна заміна поточного "огризка"

```html
<h2>Бустери One Piece Card Game</h2>

<p>Бустер — найпростіший спосіб зайти у One Piece Card Game: один запечатаний пак, кілька карт і момент відкриття. У цій підкатегорії — оригінальні поштучні бустери One Piece Card Game японського та англомовного видання від Bandai.</p>

<p>Японські бустери (JP) виходять раніше за міжнародні версії, містять 6 карт у паку і мають ексклюзивні рідкісності: Japanese Parallel карти та Manga Rare — найрідкісніший рівень у форматі з артом у стилі оригінальної манги Ейїтіро Оди. Англомовні бустери (EN) містять 12 карт у паку, підходять для гри в EN TCG-форматі та мають англійський текст ефектів.</p>

<p>У картці кожного бустера вказано сет, мову, кількість карт, тип пакування і стан. Усі бустери — sealed, без зважування та ручного перевідбору, закуплені з повноцінних боксів.</p>

<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="cat-one-piece-boosters">
  <h2 class="bs-faq-title">FAQ</h2>
  <div class="bs-faq-item">
    <h3 class="bs-faq-question"><button type="button" id="bs-faq-cat-op-boosters-button-1" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-op-boosters-panel-1"><span>Скільки карт у бустері One Piece?</span><span class="bs-faq-icon" aria-hidden="true"></span></button></h3>
    <div id="bs-faq-cat-op-boosters-panel-1" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-op-boosters-button-1" hidden><p>Японські бустери (JP) — 6 карт у паку. Англомовні бустери (EN) — 12 карт у паку. Точна кількість завжди вказана в характеристиках конкретного товару.</p></div>
  </div>
  <div class="bs-faq-item">
    <h3 class="bs-faq-question"><button type="button" id="bs-faq-cat-op-boosters-button-2" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-op-boosters-panel-2"><span>Що означає sealed і unweighed?</span><span class="bs-faq-icon" aria-hidden="true"></span></button></h3>
    <div id="bs-faq-cat-op-boosters-panel-2" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-op-boosters-button-2" hidden><p>Sealed — заводське пакування без розкриття. Unweighed — бустер не зважували і не перебирали перед продажем, тож шанси на рідкісні карти лишаються заводськими.</p></div>
  </div>
  <div class="bs-faq-item">
    <h3 class="bs-faq-question"><button type="button" id="bs-faq-cat-op-boosters-button-3" class="bs-faq-toggle" data-bs-faq-toggle aria-expanded="false" aria-controls="bs-faq-cat-op-boosters-panel-3"><span>Чим JP бустер відрізняється від EN?</span><span class="bs-faq-icon" aria-hidden="true"></span></button></h3>
    <div id="bs-faq-cat-op-boosters-panel-3" class="bs-faq-panel" role="region" aria-labelledby="bs-faq-cat-op-boosters-button-3" hidden><p>JP: 6 карт у паку, ранній реліз, ексклюзивні Japanese Parallel і Manga Rare, японський текст. EN: 12 карт у паку, англійський текст для гри в EN-форматі. Ігрові ефекти карт однакові в обох версіях.</p></div>
  </div>
</section>
```

---

## ID=64 — Набори Pokémon (підкатегорія)
_Перейменувати з «Набори» → «Набори Pokémon»_

**Назва:** _(змінити)_
```
Набори Pokémon
```
**SEO URL:**
```
nabory-pokemon
```
**Meta Title (57 chars):**
```
Набори Pokémon TCG JP / EN — sealed-комплекти | Booster Shop
```
**Meta Description (159 chars):**
```
Набори Pokémon TCG японського та англомовного видання: Premium Trainer Box, Mystery Mix, спеціальні сети. Sealed-комплекти для відкриття, колекції, подарунка.
```
**Meta Keywords:**
```
набори pokémon tcg, pokemon trainer box japanese, pokemon trainer box english, mystery mix pokemon, pokemon special set jp en, набори покемон sealed, pokemon набір подарунок
```

---

## Підсумок дій в OpenCart Admin

### Нові категорії — створити в такому порядку:
1. **Інші TCG** (головна, parent=0) → зберегти → отримати ID
2. **Yu-Gi-Oh!** (підкатегорія, parent = ID Інші TCG)
3. **Magic: The Gathering** (підкатегорія, parent = ID Інші TCG)
4. **Набори One Piece** (підкатегорія, parent = 60)

### Існуючі категорії — оновити:
| ID | Дія |
|----|-----|
| 59 | Оновити Meta Title + Meta Desc + Meta KW + SEO URL |
| 60 | Оновити Meta Title + Meta Desc + Meta KW + SEO URL |
| 61 | Оновити Meta Title + Meta Desc + Meta KW + SEO URL |
| 62 | Оновити Meta Title + Meta Desc + Meta KW + SEO URL |
| 63 | Оновити Meta Title + Meta Desc + Meta KW + SEO URL |
| 64 | **Перейменувати** + Оновити Meta Title + Meta Desc + Meta KW + SEO URL |

### Прив'язка товарів до нових категорій:
| Товар SKU | Категорія |
|-----------|-----------|
| YGO-JP-QCAC-BST | Yu-Gi-Oh! |
| YGO-JP-WPP5-BST | Yu-Gi-Oh! |
| MTG-JP-AFRS-BST | Magic: The Gathering |
| OP-JP-MIX-MBX | Набори One Piece |

---

_SEO URL для існуючих категорій вводиться у вкладці Data картки категорії OpenCart. Якщо категорії вже мають URL і є живий трафік — спочатку налаштувати 301-редирект зі старого URL на новий._

---
---

# БЛОК 3 — ОНОВЛЕННЯ ОПИСІВ HTML ІСНУЮЧИХ КАТЕГОРІЙ
_Конкретні заміни параграфів у полі «Опис» в OpenCart Admin_

---

## ID=59 — Pokémon (головна)

**Знайти і замінити перший `<h2>` і наступні 2 параграфи:**

Знайти:
```html
<h2>Бустери Pokémon TCG в Booster Shop</h2>

<p>У цій категорії зібрані оригінальні товари <strong>Pokémon TCG</strong>: японські та корейські бустери, sealed booster packs, booster boxes і окремі колекційні формати.</p>
```
_(або схожий варіант — перший абзац)_

Замінити H2 і перші 2 параграфи на:
```html
<h2>Pokémon TCG в Booster Shop</h2>

<p>У цій категорії зібрані оригінальні товари <strong>Pokémon TCG</strong>: японські, корейські та англомовні бустери, sealed booster packs, booster boxes і колекційні набори. Вони підходять для відкриття, пошуку рідкісних карт, binder collection, подарунка фанату або поповнення sealed-колекції.</p>

<p><strong>Японські видання</strong> виходять раніше за решту регіонів і мають ексклюзивні рідкісності (SAR, MUR, MA). <strong>Корейські видання</strong> — доступніші за ціною, популярні для недорогого відкриття або збору базових карт. <strong>Англомовні видання</strong> підходять для гри в EN-форматі та гравців, яким важливий англійський текст ефектів.</p>
```
Решту опису (параграфи про Unweighed, Outlet Mix, FAQ) — залишити без змін.

---

## ID=60 — One Piece Card Game (головна)

**Знайти і замінити перший `<h2>` і перший параграф:**

Знайти:
```html
<h2>Бустери One Piece Card Game в Booster Shop</h2>

<p>У цій категорії зібрані оригінальні товари <strong>One Piece Card Game</strong> від Bandai: японські sealed-бустери та promo packs.</p>
```

Замінити на:
```html
<h2>One Piece Card Game в Booster Shop</h2>

<p>У цій категорії зібрані оригінальні товари <strong>One Piece Card Game</strong> від Bandai: японські та англомовні sealed-бустери, бустер бокси, promo packs і набори. Японські видання (JP) виходять раніше і мають ексклюзивні Manga Rare та Japanese Parallel карти. Англомовні видання (EN) підходять для гри в міжнародному TCG-форматі.</p>
```
Решту опису — залишити без змін.

---

## ID=61 — Бустери Pokémon

**Знайти і замінити перший `<h2>` та перший параграф:**

Знайти:
```html
<h2>Бустери Pokémon TCG</h2>
```

Замінити рядок H2 на:
```html
<h2>Бустери Pokémon TCG — Japanese, Korean, English</h2>
```

Перший параграф — знайти текст про «японські та корейські» і додати «та англомовні»:
```
Знайти: «японські та корейські бустери»
Замінити: «японські, корейські та англомовні бустери»
```

---

## ID=62 — Бустер бокси Pokémon

**Знайти і замінити перший `<h2>`:**

Знайти:
```html
<h2>Бустер бокси Pokémon TCG</h2>
```
Замінити на:
```html
<h2>Бустер бокси Pokémon TCG — Japanese, Korean, English</h2>
```

У першому параграфі додати після «японський»:
```
Знайти: «японський sealed booster box»
Замінити: «японський або англомовний sealed booster box»
```

---

## ID=63 — Бустери One Piece

**Знайти і замінити перший `<h2>` та перший параграф:**

Знайти:
```html
<h2>Оригінальний японський sealed-бустер</h2>
```
_(або схожий H2 — перший у списку категорії)_

У вступному тексті:
```
Знайти: «японські sealed-бустери»
Замінити: «японські та англомовні sealed-бустери»
```

---

## ID=64 — Набори Pokémon

**Знайти і замінити перший `<h2>`:**

Знайти:
```html
<h2>Японські набори Pokémon TCG</h2>
```
Замінити на:
```html
<h2>Набори Pokémon TCG — Japanese &amp; English</h2>
```

У першому параграфі:
```
Знайти: «оригінальні японські комплекти»
Замінити: «оригінальні японські та англомовні комплекти»
```
