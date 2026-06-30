# Handoff CAT-002-5b — Бургер-меню: нові категорії + фікс URL
**Дата:** 2026-06-28 (оновлено)
**Задача:** CAT-002-5b
**Виконавець:** Codex
**Тип:** PHP patch + Twig edit
**Ризик:** low — навігаційний шар, не зачіпає checkout, оплату або sitemap

---

## Проблема

1. Кнопки Pokémon і One Piece у slide-in бургер-меню (`#bs-menu`) ведуть на сирі OpenCart URL замість SEO-friendly.
2. Нових категорій "Інші TCG" (з дропдауном YGO + MTG) і "Аксесуари" у меню немає.

Поточні URL з бургера:
```
index.php?route=product/category&path=59        ← Pokémon
index.php?route=product/category&path=59_61     ← Pokémon субкатегорія
index.php?route=product/category&path=59_62     ← Pokémon субкатегорія
index.php?route=product/category&path=59_64     ← Pokémon субкатегорія
index.php?route=product/category&path=60        ← One Piece
index.php?route=product/category&path=60_63     ← One Piece субкатегорія
index.php?route=product/special                 ← Акції (залишити як є)
```

---

## Цільова структура бургер-меню

```
Pokémon TCG          → /catalog/pokemon          [accordion: підкатегорії]
One Piece Card Game  → /catalog/one-piece         [accordion: підкатегорія]
Інші TCG             → /catalog/more-tcg          [accordion: YGO + MTG]
  ├── Yu-Gi-Oh! OCG        → /catalog/more-tcg/Yu-Gi-Oh
  └── Magic: The Gathering → /catalog/more-tcg/magic-the-gathering
Аксесуари            → /catalog/accessories       [пряме посилання, без дропдауну]
Акції                → /index.php?route=product/special  [залишити]
```

---

## Context

Burger menu: `catalog/view/template/common/header.twig` → блок `#bs-menu`.  
Дані через `catalog_menu` (Twig loop: `c.name`, `c.href`, `c.accent`, `c.subs[]`).  
JS accordion: `catalog/view/javascript/patch-mobile-search-menu-redesign.js` — **не чіпати**.  
Якщо `c.subs` не порожній → Twig рендерить accordion-кнопку + список; якщо порожній → пряме посилання.  
Accent-dot: `bs-menu__dot` отримує колір через `c.accent`.

---

## Required changes

### 1. Фікс URL Pokémon і One Piece

**Перевірити** в OpenCart Admin → Catalog → Categories:
- Category ID 59 (Pokémon) — вкладка SEO → поле **SEO Keyword**
- Category ID 60 (One Piece) — те саме
- IDs 61, 62, 63, 64 — підкатегорії

Якщо SEO Keywords **вже встановлено** → посилання генеруються автоматично через `$this->url->link()` при увімкненому SEO URL в конфіги.  
Якщо **не встановлено** → виконати INSERT:

```sql
-- Pokémon
INSERT IGNORE INTO oc_seo_url (store_id, language_id, key, value)
SELECT 0, language_id, 'path', '59'
FROM oc_language WHERE status = 1;

UPDATE oc_seo_url SET keyword = 'pokemon'
WHERE `key` = 'path' AND value = '59';

-- One Piece
UPDATE oc_seo_url SET keyword = 'one-piece'
WHERE `key` = 'path' AND value = '60';
```

> Або через Admin UI: Category 59 → SEO tab → keyword = `pokemon`; Category 60 → keyword = `one-piece`.

### 2. Категорія Аксесуари — INSERT у БД

```sql
-- Основна запис
INSERT INTO oc_category (parent_id, image, top, column, sort_order, status, date_added, date_modified)
VALUES (0, '', 1, 1, 4, 1, NOW(), NOW());

SET @acc_id = LAST_INSERT_ID();

-- Назва (UA)
INSERT INTO oc_category_description (category_id, language_id, name, description, meta_title, meta_description, meta_keyword)
SELECT @acc_id, language_id,
  'Аксесуари',
  'Протектори, топлоадери, магнітні кейси та аркуші для колекціонерів і гравців TCG.',
  'Аксесуари для карток — купити в Україні | Booster Shop',
  'Протектори, топлоадери, магнітні кейси для колекційних карток. Доставка по Україні.',
  'протектори для карток, топлоадери, магнітний кейс, аксесуари tcg'
FROM oc_language WHERE status = 1;

-- Store binding
INSERT INTO oc_category_to_store (category_id, store_id) VALUES (@acc_id, 0);

-- SEO URL
INSERT INTO oc_seo_url (store_id, language_id, key, value, keyword)
SELECT 0, language_id, 'path', @acc_id, 'accessories'
FROM oc_language WHERE status = 1;
```

> Після INSERT зберегти значення `@acc_id` — потрібне в кроці 3.

### 3. Оновити catalog_menu у контролері

Знайти контролер, що будує `catalog_menu` (зазвичай `catalog/controller/common/header.php`).  
Додати нові елементи **після One Piece і перед Акціями**:

```php
// Інші TCG — з дропдауном
$catalog_menu[] = [
    'name'   => 'Інші TCG',
    'href'   => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=<MORE_TCG_CATEGORY_ID>'),
    'accent' => '#065F46',
    'subs'   => [
        [
            'name' => 'Yu-Gi-Oh! OCG',
            'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=<YGO_CATEGORY_ID>'),
        ],
        [
            'name' => 'Magic: The Gathering',
            'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=<MTG_CATEGORY_ID>'),
        ],
    ],
];

// Аксесуари — без дропдауну
$catalog_menu[] = [
    'name'   => 'Аксесуари',
    'href'   => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=<ACC_CATEGORY_ID>'),
    'accent' => '#0D9488',
    'subs'   => [],
];
```

**Підставити:**
- `<MORE_TCG_CATEGORY_ID>` — ID батьківської категорії "Інші TCG" (вже існує в БД, знайти через Admin)
- `<YGO_CATEGORY_ID>` — ID підкатегорії Yu-Gi-Oh! (вже існує)
- `<MTG_CATEGORY_ID>` — ID підкатегорії Magic: The Gathering (вже існує)
- `<ACC_CATEGORY_ID>` — `@acc_id` з кроку 2

> Якщо `catalog_menu` будується через Twig loop з oc_category — альтернативно додати записи через Admin і переконатися що `top = 1` (показувати у навігації).

### 4. Фікс href у Twig (fallback якщо URL все ще ugly)

Якщо після кроків 1–3 Pokémon/One Piece все ще генерують `index.php?path=`:

```twig
{% set seo_override = {
    59: '/catalog/pokemon',
    60: '/catalog/one-piece'
} %}

{# у циклі: #}
<a href="{{ seo_override[c.id] ?? c.href }}" ...>
```

---

## Acceptance criteria

- [ ] Бургер: Pokémon → `/catalog/pokemon`, не `index.php?path=59`
- [ ] Бургер: One Piece → `/catalog/one-piece`, не `index.php?path=60`
- [ ] Бургер: "Інші TCG" — accordion з YGO + MTG підпунктами (той самий паттерн що Pokémon)
- [ ] YGO sub → `/catalog/more-tcg/Yu-Gi-Oh`, MTG sub → `/catalog/more-tcg/magic-the-gathering`
- [ ] Бургер: "Аксесуари" — пряме посилання без дропдауну → `/catalog/accessories`
- [ ] Акції — залишилися, не зникли
- [ ] Accordion JS (Esc, scrim, анімація) — не зламаний

---

## QA checklist

1. Мобільний viewport або devtools < 768px
2. Бургер → меню відкрилось
3. Клік Pokémon → URL `/catalog/pokemon` (не `index.php`)
4. Назад → клік One Piece → URL `/catalog/one-piece`
5. Клік "Інші TCG" → accordion розкрився → видно YGO і MTG
6. Клік YGO → `/catalog/more-tcg/Yu-Gi-Oh` — сторінка відкрилась, не 404
7. Назад → Клік MTG → `/catalog/more-tcg/magic-the-gathering` — не 404
8. Клік Аксесуари → `/catalog/accessories` — не 404
9. Акції присутні в меню
10. Esc / клік scrim → меню закрилось

---

## Ризики

| Ризик | Рівень | Мітигація |
|-------|--------|-----------|
| SEO URL не налаштовано для категорій 59/60 | low | SQL UPDATE або Admin UI (крок 1) |
| catalog_menu будується інакше (не через PHP array) | medium | Codex перевіряє контролер і адаптує підхід |
| Аксесуари — 404 до deploy | low | Категорія створюється в кроці 2 у тому ж патчі |
| `c.subs` не підтримується у Twig шаблоні | low | Перевірити header.twig — паттерн вже є для Pokémon |
