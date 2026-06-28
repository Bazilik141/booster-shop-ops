# Handoff CAT-002-5b — Бургер-меню: нові категорії + фікс URL
**Дата:** 2026-06-28
**Задача:** CAT-002-5b
**Виконавець:** Codex
**Тип:** PHP patch + Twig edit
**Ризик:** low — навігаційний шар, не зачіпає checkout, оплату або sitemap

---

## Проблема

Кнопки Pokémon і One Piece у slide-in бургер-меню (`#bs-menu`) ведуть на сирі OpenCart URL типу:

```
https://boostershop.website/index.php?route=product/category&path=59
https://boostershop.website/index.php?route=product/category&path=60
https://boostershop.website/index.php?route=product/category&path=60_63
```

Нових категорій (Yu-Gi-Oh!, MTG, Аксесуари) у меню немає.

---

## Context

Burger menu визначається у `catalog/view/template/common/header.twig`.  
Дані передаються через `catalog_menu` (Twig loop — `c.name`, `c.href`, `c.accent`, `c.subs`).  
JS: `catalog/view/javascript/patch-mobile-search-menu-redesign.js` — не чіпати.  

Поточні `path=` ID з бургера:
| URL | Категорія (implication) |
|-----|------------------------|
| `path=59` | Pokémon (батьківська) |
| `path=59_61` | Pokémon субкатегорія |
| `path=59_62` | Pokémon субкатегорія |
| `path=59_64` | Pokémon субкатегорія |
| `path=60` | One Piece (батьківська) |
| `path=60_63` | One Piece субкатегорія |
| `route=product/special` | Акції/Outlet |

---

## Required changes

### 1. Визначити SEO URL для Pokémon і One Piece

У OpenCart Admin → Catalog → Categories знайти категорії з ID 59 і 60.
Перевірити поле **SEO Keyword** (вкладка SEO):
- якщо встановлено slug → SEO URL = `/catalog/<slug>`
- якщо не встановлено → встановити: `pokemon` для 59, `one-piece` для 60

Аналогічно для підкатегорій 61, 62, 63, 64.

### 2. Оновити href у бургер-меню

У `catalog/view/template/common/header.twig` знайти блок `bs-menu__body` → `{% for c in catalog_menu %}`.

**Варіант A (рекомендований):** якщо `catalog_menu` генерується контролером з `url()->link(...)` — перевірити, чи в контролері увімкнено SEO-режим (`$this->config->get('config_seo_url')`). Якщо ні — увімкнути або замінити виклик.

**Варіант B (хардкод fallback):** якщо catalog_menu не підтримує SEO slug — замінити href безпосередньо у Twig через mapping:

```twig
{% set cat_seo = {
  59: '/catalog/pokemon',
  60: '/catalog/one-piece'
} %}
{# використати cat_seo[c.id] ?? c.href #}
```

> Codex: обери варіант залежно від того, як побудований catalog_menu у контролері.

### 3. Додати нові категорії в бургер

У контролері `CatalogControllerCommonHeader` (або де будується `catalog_menu`) додати вручну записи для нових категорій після наявних, **перед "Акції"**:

```php
// Yu-Gi-Oh!
$catalog_menu[] = [
    'name'   => 'Yu-Gi-Oh! OCG',
    'href'   => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=<YGO_CATEGORY_ID>'),
    'accent' => '#6D28D9',
    'subs'   => [], // підкатегорій поки немає
];

// Magic: The Gathering
$catalog_menu[] = [
    'name'   => 'Magic: The Gathering',
    'href'   => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=<MTG_CATEGORY_ID>'),
    'accent' => '#B91C1C',
    'subs'   => [],
];

// Аксесуари
$catalog_menu[] = [
    'name'   => 'Аксесуари',
    'href'   => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=<ACC_CATEGORY_ID>'),
    'accent' => '#0D9488',
    'subs'   => [],
];
```

> `<YGO_CATEGORY_ID>`, `<MTG_CATEGORY_ID>`, `<ACC_CATEGORY_ID>` — ID нових категорій з OpenCart Admin.
> Категорія Аксесуари ще не створена: спочатку створити вручну в Admin → Catalog → Categories з SEO keyword = `accessories`.

### 4. Аксесуари — створити категорію в Admin (Manual)

> ⚠️ Це ручна дія власника, не Codex.

1. Admin → Catalog → Categories → Add New
2. Name: `Аксесуари`
3. SEO Keyword: `accessories`
4. Parent: (root / без батька)
5. Зберегти → отримати ID → підставити в крок 3

Після створення SEO URL буде: `https://boostershop.website/catalog/accessories`

### 5. Оновити ROADMAP_FLOW у booster-dashboard.html

Після успішного deploy оновити статус CAT-002-5b в `dashboard/booster-dashboard.html` → `ROADMAP_FLOW` → `Done`.

---

## Acceptance criteria

- [ ] Бургер-меню: кнопки Pokémon і One Piece ведуть на SEO URL (`/catalog/pokemon`, `/catalog/one-piece`), а не на `index.php?path=`
- [ ] Бургер-меню: додані кнопки Yu-Gi-Oh! (фіолет #6D28D9), Magic: The Gathering (червоний #B91C1C), Аксесуари (тіл #0D9488)
- [ ] Нові кнопки відкривають відповідні сторінки категорій (не 404)
- [ ] Slide-in анімація і Esc-close продовжують працювати (JS не змінювався)
- [ ] `bs-menu__dot` кольори нових елементів відповідають accent

---

## QA checklist (smoke test)

1. Відкрити мобільну версію (або devtools < 768px)
2. Натиснути бургер → меню відкрилось
3. Клік Pokémon → URL = `/catalog/pokemon` (або slug, що налаштований) — не `index.php?path=`
4. Back → клік One Piece → URL = `/catalog/one-piece`
5. Перевірити Yu-Gi-Oh!, MTG, Аксесуари → кожен відкриває свою сторінку
6. Акції — все ще в меню, нижче нових категорій
7. Esc / клік на scrim → меню закривається

---

## Ризики

| Ризик | Рівень | Мітигація |
|-------|--------|-----------|
| SEO URL не налаштований для категорій | low | Встановити slug в Admin перед deploy |
| catalog_menu не підтримує custom поля `accent` | medium | Перевірити Twig template і контролер; додати поле якщо потрібно |
| Аксесуари ще не існують — href 404 | low | Створити категорію вручну до deploy |
