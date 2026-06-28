# CAT-002-5 — Колір-система категорій та HTML тайлів
**Дата:** 2026-06-28
**Задача:** CAT-002-5 (Claude design)
**Scope:** accent-кольори для всіх 5 категорій + готовий HTML bs-catcard для 3 нових

---

## Колір-система категорій (закріплена документація)

| Категорія | CSS var | Accent HEX | Обґрунтування |
|-----------|---------|-----------|---------------|
| Pokémon TCG | `--bs-pokemon` | `#C68A00` | Золотий — логотип Pokémon, впізнаваний |
| One Piece Card Game | `--bs-onepiece` | `#1E40AF` | Темно-синій — фірмовий колір Bandai/OP |
| Yu-Gi-Oh! | `--bs-yugioh` | `#6D28D9` | Фіолетовий — колір зворотного боку карток YGO |
| Magic: The Gathering | `--bs-mtg` | `#B91C1C` | Темно-червоний — класична палітра MTG |
| Аксесуари | `--bs-accessories` | `#0D9488` | Тіл — нейтральний, сучасний, не конкурує з іграми |

### CSS змінні (додати до `boostershop-ds.css`)

```css
:root {
  --bs-pokemon:     #C68A00;
  --bs-onepiece:    #1E40AF;
  --bs-yugioh:      #6D28D9;
  --bs-mtg:         #B91C1C;
  --bs-accessories: #0D9488;
}
```

---

## HTML тайлів для home.twig

### Наявні (reference, не змінювати)

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

### Нові тайли (додати після наявних)

```html
<a class="bs-catcard" href="/catalog/more-tcg/Yu-Gi-Oh" style="--accent:#6D28D9;">
  <span class="bs-catcard__media">
    <img src="{{ base }}image/catalog/Yu-Gi-Oh/YuGiOhC.png" alt="Yu-Gi-Oh! OCG" loading="lazy" width="168" height="168">
  </span>
  <span class="bs-catcard__body">
    <span class="bs-catcard__title">Yu-Gi-Oh! OCG</span>
    <span class="bs-catcard__desc">Оригінальні японські бустери Yu-Gi-Oh! OCG від Konami. Поштучно зі sealed-боксів, без зважування.</span>
    <span class="bs-catcard__more">Переглянути →</span>
  </span>
</a>

<a class="bs-catcard" href="/catalog/more-tcg/magic-the-gathering" style="--accent:#B91C1C;">
  <span class="bs-catcard__media">
    <img src="{{ base }}image/catalog/Magic-The-Gathering/MtGC.png" alt="Magic: The Gathering" loading="lazy" width="168" height="168">
  </span>
  <span class="bs-catcard__body">
    <span class="bs-catcard__title">Magic: The Gathering</span>
    <span class="bs-catcard__desc">Бустери та набори Magic: The Gathering від Wizards of the Coast. Sealed-продукт, оригінал.</span>
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

## Зображення категорій (потрібно завантажити)

| Категорія | Шлях на сервері | Формат |
|-----------|----------------|--------|
| Yu-Gi-Oh! | `image/catalog/Yu-Gi-Oh/YuGiOhC.png` | PNG 168×168px, прозорий фон |
| Magic: The Gathering | `image/catalog/Magic-The-Gathering/MtGC.png` | PNG 168×168px, прозорий фон |
| Аксесуари | `image/catalog/Accessories/AccessoriesC.png` | PNG 168×168px, прозорий фон |

> Рекомендація: використовувати офіційне лого гри на темному фоні з прозорістю, аналогічно `PokemonC.png`.

---

## Де застосувати зміни

| Файл | Що міняти |
|------|-----------|
| `catalog/view/stylesheet/boostershop-ds.css` | Додати CSS-змінні (`--bs-yugioh`, `--bs-mtg`, `--bs-accessories`) |
| `catalog/view/template/common/home.twig` | Додати 3 нових `bs-catcard` блоки після наявних |

---

## Порядок відображення тайлів на головній

1. Pokémon TCG
2. One Piece Card Game
3. Yu-Gi-Oh! OCG
4. Magic: The Gathering
5. Аксесуари
