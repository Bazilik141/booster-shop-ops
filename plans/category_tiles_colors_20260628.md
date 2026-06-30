# CAT-002-5 — Колір-система категорій та HTML тайлів
**Дата:** 2026-06-28 (оновлено)
**Задача:** CAT-002-5 (Claude design)
**Scope:** 2 нові тайли на головній (Інші TCG + Аксесуари) + повна колір-система для документації

---

## Структура категорій — важливо

```
Головна (тайли):
├── Pokémon TCG          → /catalog/pokemon        [існуючий]
├── One Piece Card Game  → /catalog/one-piece       [існуючий]
├── Інші TCG             → /catalog/more-tcg        [НОВИЙ тайл]
└── Аксесуари            → /catalog/accessories     [НОВИЙ тайл]

Бургер-меню (окрема задача CAT-002-5b):
├── Pokémon TCG          → dropdown з підкатегоріями [існуючий]
├── One Piece Card Game  → dropdown з підкатегорією  [існуючий]
├── Інші TCG             → dropdown                  [НОВИЙ]
│   ├── Yu-Gi-Oh! OCG   → /catalog/more-tcg/Yu-Gi-Oh
│   └── Magic: The Gathering → /catalog/more-tcg/magic-the-gathering
├── Аксесуари            → пряме посилання            [НОВИЙ]
└── Акції                → /index.php?route=product/special [існуючий]
```

---

## Колір-система категорій (закріплена документація)

### Тайли головної сторінки

| Категорія | CSS var | Accent HEX | Обґрунтування |
|-----------|---------|-----------|---------------|
| Pokémon TCG | `--bs-pokemon` | `#C68A00` | Золотий — логотип Pokémon |
| One Piece Card Game | `--bs-onepiece` | `#1E40AF` | Темно-синій — фірмовий Bandai/OP |
| Інші TCG | `--bs-other-tcg` | `#065F46` | Темно-зелений — нейтральний, не конкурує з YGO/MTG |
| Аксесуари | `--bs-accessories` | `#0D9488` | Тіл — сучасний, читається як "утиліта" |

### Акценти підкатегорій (для точок у бургері)

| Підкатегорія | CSS var | Accent HEX | Де використовується |
|---|---|---|---|
| Yu-Gi-Oh! OCG | `--bs-yugioh` | `#6D28D9` | `bs-menu__dot` у бургері |
| Magic: The Gathering | `--bs-mtg` | `#B91C1C` | `bs-menu__dot` у бургері |

### CSS змінні (додати до `boostershop-ds.css`)

```css
:root {
  /* існуючі */
  --bs-pokemon:     #C68A00;
  --bs-onepiece:    #1E40AF;
  /* нові тайли */
  --bs-other-tcg:   #065F46;
  --bs-accessories: #0D9488;
  /* акценти підкатегорій у бургері */
  --bs-yugioh:      #6D28D9;
  --bs-mtg:         #B91C1C;
}
```

---

## HTML тайлів для home.twig

### Наявні (reference — не змінювати)

```html
<a class="bs-catcard" href="/catalog/Pokemon" style="--accent:#C68A00;">
  <span class="bs-catcard__media">
    <img src="{{ base }}image/catalog/Pokemon/PokemonC.png" alt="Pokémon TCG" loading="eager" width="168" height="168">
  </span>
  <span class="bs-catcard__body">
    <span class="bs-catcard__title">Pokémon TCG</span>
    <span class="bs-catcard__desc">Оригінальні бустери, бокси та набори Pokémon TCG. Японські, корейські й англійські видання — sealed, без зважування.</span>
    <span class="bs-catcard__more">Переглянути →</span>
  </span>
</a>

<a class="bs-catcard" href="/catalog/One-Piece" style="--accent:#1E40AF;">
  <span class="bs-catcard__media">
    <img src="{{ base }}image/catalog/One%20Piece/One%20PieceC.png" alt="One Piece Card Game" loading="eager" width="168" height="168">
  </span>
  <span class="bs-catcard__body">
    <span class="bs-catcard__title">One Piece Card Game</span>
    <span class="bs-catcard__desc">Оригінальні бустери та бокси One Piece Card Game від Bandai. Sealed із боксів, без сортування.</span>
    <span class="bs-catcard__more">Переглянути →</span>
  </span>
</a>
```

### Нові тайли (2 шт — додати після наявних)

```html
<a class="bs-catcard" href="/catalog/more-tcg" style="--accent:#065F46;">
  <span class="bs-catcard__media">
    <img src="{{ base }}image/catalog/More-TCG/MoreTCGC.png" alt="Інші карткові ігри" loading="lazy" width="168" height="168">
  </span>
  <span class="bs-catcard__body">
    <span class="bs-catcard__title">Інші TCG</span>
    <span class="bs-catcard__desc">Yu-Gi-Oh! OCG, Magic: The Gathering та інші колекційні карткові ігри. Оригінальні sealed-бустери.</span>
    <span class="bs-catcard__more">Переглянути →</span>
  </span>
</a>

<a class="bs-catcard" href="/catalog/accessories" style="--accent:#0D9488;">
  <span class="bs-catcard__media">
    <img src="{{ base }}image/catalog/Accessories/AccessoriesC.png" alt="Аксесуари для карток" loading="lazy" width="168" height="168">
  </span>
  <span class="bs-catcard__body">
    <span class="bs-catcard__title">Аксесуари</span>
    <span class="bs-catcard__desc">Протектори, топлоадери, магнітні кейси та аркуші для колекціонерів і гравців TCG.</span>
    <span class="bs-catcard__more">Переглянути →</span>
  </span>
</a>
```

---

## Зображення категорій (потрібно завантажити вручну)

| Категорія | Шлях на сервері | Формат |
|-----------|----------------|--------|
| Інші TCG | `image/catalog/More-TCG/MoreTCGC.png` | PNG 168×168px, прозорий фон |
| Аксесуари | `image/catalog/Accessories/AccessoriesC.png` | PNG 168×168px, прозорий фон |

> YGO і MTG зображення потрібні лише для сторінок підкатегорій — не для тайлів головної.

---

## Де застосувати зміни (Codex)

| Файл | Що міняти |
|------|-----------|
| `catalog/view/stylesheet/boostershop-ds.css` | Додати CSS-змінні (блок вище) |
| `catalog/view/template/common/home.twig` | Додати **2** нових `bs-catcard` після наявних |

---

## Порядок відображення тайлів на головній

1. Pokémon TCG
2. One Piece Card Game
3. Інші TCG
4. Аксесуари
