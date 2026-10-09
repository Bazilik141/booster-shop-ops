# Хендоф для Codex — CAT-002-5: другорядні плитки «Інші TCG» / «Аксесуари»

**Дата:** 2026-06-28 · **REDO-нотатка:** 2026-06-29 (див. § 0a)
**Стек:** OpenCart 4 · OcStore тема · Twig + `bs-*` дизайн-система (`boostershop-ds.css`)
**Скоуп:** дві нові плитки на головну під існуючими `bs-catcards`, нові CSS-токени, SVG-іконки. Без змін у самих primary-плитках Pokémon / One Piece.

Затверджено власником на дошках:
- `Другорядні плитки - варіанти.html` — обрано напрям **A · Тонкі плитки-«дзеркало»** (84 px, акцент лише як 3 px ліва смужка + 12 % tint у плашці іконки).
- `Іконки другорядних плиток - варіанти.html` — обрано пару **07 «Стопка»** для «Інші TCG» + **10 «Картка-блиск»** для «Аксесуари».

Цей документ — **дизайн-частина** задач CAT-002-5 + CAT-002-5b. БД-міграція, burger-menu лінки/акордеон та структура SEO-урлів — як описано у твоєму ТЗ (`plans/category_tiles_colors_20260628.md`, `handoffs/handoff_CAT-002-5b_burger-menu-new-categories_20260628.md`); цей файл не дублює їх, тільки уточнює візуал.

---

## 0a. REDO — обов'язково для повторного патчу (2026-06-29)

Після першого прогону зафіксовано: у задеплоєному `boostershop-ds.css` **відсутній блок `.bs-subtile*`** (0 правил, 0 CAT-маркерів), тому два `<a class="bs-subtile">` у HTML рендеряться без стилів — а самі SVG лишаються без розмірів, бо їх задає тільки CSS-правило `.bs-subtile__glyph svg { width: 20px; height: 20px; }`. Два обов'язкові фікси в цьому REDO:

### A. Захардкодити розміри SVG прямо в розмітці (defensive)

Додати `width="20" height="20"` **на обидва glyph SVG** у Twig (§ 3). Це гарантує, що навіть якщо CSS не приїхав / не застосувався / закешований старий — іконки матимуть правильний розмір, а не дефолтні 300×150 чи 0×0. Інші SVG-атрибути не чіпати.

Правильна розмітка glyph (фрагмент, **обидві** плитки):
```html
<span class="bs-subtile__glyph" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="2"
       stroke-linecap="round" stroke-linejoin="round">
    …(шлях іконки без змін)…
  </svg>
</span>
```

CSS-правило `.bs-subtile__glyph svg { width: 20px; height: 20px; }` лишається у `boostershop-ds.css` (для consistency + mobile-override на 18 px), але більше не критичне.

Мобільний override (`@media (max-width: 768px) .bs-subtile__glyph svg { width: 18px; height: 18px; }`) **усе ще працює** через CSS specificity — inline `width/height`-атрибути SVG є *презентаційними*, а не inline-style, тож CSS-правило перекриває їх. Перевірити вручну на ≤ 768 px (див. QA § 7).

### B. Cache-bust для `boostershop-ds.css`

У `header.twig` (або де підключається DS-стилі) до src/href додати query-string:
```twig
<link rel="stylesheet" href="{{ server }}catalog/view/template/.../stylesheet/boostershop-ds.css?v=cat002-redo-20260629">
```
Використати `?v=cat002-redo-20260629` як значення. Якщо в темі вже є власна cache-bust змінна (типу `{{ asset_version }}`) — оновити її, а не додавати другу.

### C. Перевірка після деплою (sanity)

Після патчу в DevTools на головній:
```js
getComputedStyle(document.querySelector('.bs-subtile')).height        // → "84px" (desktop)
getComputedStyle(document.querySelector('.bs-subtile__glyph svg')).width  // → "20px"
document.querySelectorAll('.bs-subtile').length                       // → 2
```
Якщо `.bs-subtile` height ≠ 84 px на десктопі — CSS-блок не приїхав (перевірити cache-bust і шлях до файлу).

---

## 0. TL;DR — що робимо

1. **CSS токени:** додати у `:root` чотири змінні: `--bs-other-tcg`, `--bs-accessories`, `--bs-yugioh`, `--bs-mtg`.
2. **CSS компонент:** додати блок `.bs-subtiles` + `.bs-subtile` (slim 84 px tile, 2-up grid, mobile stack).
3. **Twig:** у `home.twig` **одразу після** секції `<section class="bs-home-tiles bs-catcards">` додати другий `<section class="bs-home-tiles bs-subtiles">` з двома `<a class="bs-subtile">` — «Інші TCG» (→ `/catalog/more-tcg`, accent `#065F46`) і «Аксесуари» (→ `/catalog/accessories`, accent `#0D9488`).
4. **Іконки:** inline SVG усередині `<span class="bs-subtile__glyph">` з **обов'язковими `width="20" height="20"`** атрибутами — see § 4 і § 0a-A.
5. **Cache-bust:** `?v=cat002-redo-20260629` на `boostershop-ds.css` у `header.twig` — see § 0a-B.
5. **Burger menu:** залишити поточну реалізацію акордеону. Для нових пунктів використати ті самі акцент-точки, що вже стоять перед Pokémon / One Piece / Акції (див. § 6).

**Файли:**
| Файл | Зміна |
|---|---|
| `catalog/view/template/.../stylesheet/boostershop-ds.css` | + 4 токени, + блок `.bs-subtile*` (~50 рядків) |
| `catalog/view/template/.../common/home.twig` *(або де рендеряться bs-catcards)* | + один `<section>` після наявних bs-catcards |
| `catalog/view/template/.../common/menu.twig` *(burger)* | за CAT-002-5b: SEO-слаги для Pokémon/One Piece, акордеон «Інші TCG» з YGO/MTG, прямий лінк «Аксесуари» |

Іконки — **inline SVG у Twig**, не зовнішні файли. Кешуються разом з HTML, не потребують іконографічного підключення.

---

## 1. CSS токени — `boostershop-ds.css`, секція `:root` / Category accents

Поряд із наявними `--bs-pokemon` / `--bs-onepiece` додати:

```css
/* Category accents (USE ONLY in category banners/tiles, not in UI chrome) */
--bs-pokemon:       #C68A00;
--bs-onepiece:      #1E40AF;

/* + ДОДАТИ: */
--bs-other-tcg:     #065F46;   /* «Інші TCG» — emerald 800 */
--bs-accessories:   #0D9488;   /* «Аксесуари» — teal 600   */
--bs-yugioh:        #7C3AED;   /* для бургер-меню / chips під «Інші TCG» */
--bs-mtg:           #B45309;   /* для бургер-меню / chips під «Інші TCG» */
```

`--bs-yugioh` / `--bs-mtg` поки використовуються лише у бургер-меню (точки перед YGO / MTG в акордеоні «Інші TCG»). На головній **не** з'являються.

---

## 2. CSS компонент — додати у `boostershop-ds.css`

Додати в кінець файлу, після блоку `.bs-catcard*`:

```css
/* -- Home · secondary category tiles (slim) ---------------------------- */
/* Use only under .bs-catcards. ~52% of bs-catcard height; designed to NOT
   compete with the primary Pokémon / One Piece tiles. Accent shows only as
   a 3 px left rule + 12% tint behind the glyph. */
.bs-subtiles {
  display: grid; grid-template-columns: 1fr 1fr; gap: 18px;
  margin-top: 18px;                /* breathing room from primary catcards */
}
.bs-subtile {
  position: relative; display: flex; align-items: center; gap: 14px;
  height: 84px; padding: 0 18px 0 20px;
  background: #fff; border: 1px solid var(--bs-line);
  border-radius: var(--bs-r); text-decoration: none;
  color: var(--bs-ink); overflow: hidden;
  transition: border-color .15s, box-shadow .15s;
}
.bs-subtile:hover {
  border-color: color-mix(in oklab, var(--accent) 35%, var(--bs-line));
  box-shadow: var(--bs-sh-sm);
}
.bs-subtile::before {
  content: ""; position: absolute; left: 0; top: 14px; bottom: 14px;
  width: 3px; background: var(--accent); border-radius: 0 3px 3px 0;
}
.bs-subtile__glyph {
  width: 38px; height: 38px; flex: 0 0 38px; border-radius: 8px;
  display: grid; place-items: center;
  background: color-mix(in oklab, var(--accent) 12%, white);
  color: var(--accent);
}
.bs-subtile__glyph svg { width: 20px; height: 20px; display: block; }
.bs-subtile__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.bs-subtile__title { font-size: 15px; font-weight: 700; letter-spacing: -0.005em; color: var(--bs-ink); }
.bs-subtile__hint {
  font-size: 12.5px; color: var(--bs-ink-3); margin-top: 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.bs-subtile__chev { color: var(--bs-ink-4); flex: 0 0 auto; }

@media (max-width: 768px) {
  .bs-subtiles { grid-template-columns: 1fr; gap: 10px; margin-top: 10px; }
  .bs-subtile { height: 72px; padding: 0 14px 0 18px; gap: 12px; }
  .bs-subtile__glyph { width: 34px; height: 34px; flex-basis: 34px; border-radius: 7px; }
  .bs-subtile__glyph svg { width: 18px; height: 18px; }
  .bs-subtile__title { font-size: 14px; }
  .bs-subtile__hint { font-size: 12px; }
}

/* Fallback for browsers without color-mix() (Safari < 16.4 etc.) */
@supports not (background: color-mix(in oklab, red, white)) {
  .bs-subtile__glyph { background: rgba(0,0,0,0.04); }
  .bs-subtile:hover { border-color: var(--bs-line); }
}
```

Жодних `!important`. Жодних правок існуючого `.bs-catcard*`.

---

## 3. Twig — `home.twig` (або де рендеряться bs-catcards)

**Одразу після** наявної секції з двома bs-catcards вставити:

```twig
{# CAT-002-5 · secondary tiles — quieter than primary, accent only on left rule + glyph chip #}
<section class="bs-home-tiles bs-subtiles" aria-label="Інші категорії">
  <a class="bs-subtile" href="{{ server }}index.php?route=product/category&path=XX_OTHER_TCG_PATH_XX"
     style="--accent: var(--bs-other-tcg);">
    <span class="bs-subtile__glyph" aria-hidden="true">
      {# 07 «Стопка» — обрана іконка · width/height ОБОВ'ЯЗКОВІ (§ 0a-A) #}
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4" y="3" width="13" height="18" rx="2"/>
        <path d="M20 7v14a2 2 0 0 1-2 2H8"/>
      </svg>
    </span>
    <span class="bs-subtile__body">
      <span class="bs-subtile__title">Інші TCG</span>
      <span class="bs-subtile__hint">Yu-Gi-Oh! · Magic: The Gathering</span>
    </span>
    <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2.4"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="9 6 15 12 9 18"/>
    </svg>
  </a>

  <a class="bs-subtile" href="{{ server }}index.php?route=product/category&path=XX_ACCESSORIES_PATH_XX"
     style="--accent: var(--bs-accessories);">
    <span class="bs-subtile__glyph" aria-hidden="true">
      {# 10 «Картка-блиск» — обрана іконка · width/height ОБОВ'ЯЗКОВІ (§ 0a-A) #}
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="5" y="4" width="12" height="16" rx="1.5"/>
        <path d="M19 5.5l.7 1.6 1.8.3-1.4 1.2.4 1.8-1.5-.9-1.5.9.4-1.8L16.5 7.4l1.8-.3z"
              fill="currentColor" fill-opacity=".15"/>
      </svg>
    </span>
    <span class="bs-subtile__body">
      <span class="bs-subtile__title">Аксесуари</span>
      <span class="bs-subtile__hint">Sleeves, deck boxes, playmats, binders</span>
    </span>
    <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2.4"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="9 6 15 12 9 18"/>
    </svg>
  </a>
</section>
```

**Що замінити перед коммітом:**
- `XX_OTHER_TCG_PATH_XX` → реальний path/SEO-keyword до агрегатної категорії «Інші TCG» (`/catalog/more-tcg` за ТЗ). Якщо у темі hreflang/canonical беруться з `oc_seo_url` — `<a href="{{ url('product/category', { path: '...' }) }}">` як зазвичай.
- `XX_ACCESSORIES_PATH_XX` → ID нової категорії «Аксесуари» (її ж створює CAT-002-5 INSERT) з `keyword=accessories`.

**href можна замінити прямими SEO-URL** (`{{ server }}catalog/more-tcg`, `{{ server }}catalog/accessories`) якщо тема дозволяє bypassing route= — головне, щоб збігалося з тим, що видасть `oc_seo_url`.

---

## 4. SVG-іконки — еталон (бекап)

На випадок, якщо інлайн в Twig вийде «забрудненим» автоформатером — нижче чисті оригінали. Обидві: `viewBox="0 0 24 24"`, `stroke-width="2"`, `stroke-linecap="round"`, `stroke-linejoin="round"`, `fill="none"`. Колір — `currentColor` (підхоплює `.bs-subtile__glyph { color: var(--accent); }`).

**Інші TCG · 07 «Стопка»**
```svg
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <rect x="4" y="3" width="13" height="18" rx="2"/>
  <path d="M20 7v14a2 2 0 0 1-2 2H8"/>
</svg>
```

**Аксесуари · 10 «Картка-блиск»**
```svg
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <rect x="5" y="4" width="12" height="16" rx="1.5"/>
  <path d="M19 5.5l.7 1.6 1.8.3-1.4 1.2.4 1.8-1.5-.9-1.5.9.4-1.8L16.5 7.4l1.8-.3z"
        fill="currentColor" fill-opacity=".15"/>
</svg>
```

**Chevron (обидва)**
```svg
<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <polyline points="9 6 15 12 9 18"/>
</svg>
```

---

## 5. Hint-рядок (другий рядок під назвою)

Це **не SEO-важливе поле**, просто візуальна підказка. Закладаю поки текст:
- «Інші TCG» → `Yu-Gi-Oh! · Magic: The Gathering`
- «Аксесуари» → `Sleeves, deck boxes, playmats, binders`

Якщо колись з'явиться окремий description у БД — підставити з `category.description` (truncate 60 chars). Поки — захардкодити рядок у Twig, як показано.

---

## 6. Burger menu (CAT-002-5b) — узгодження візуалу

Реалізація акордеону **залишається як зараз** (див. твій скрін у `uploads/pasted-1782662212173-0.png`). Уточнення лише по візуалу нових пунктів:

| Пункт | Точка перед назвою | Поведінка |
|---|---|---|
| `Pokémon TCG` | `background: var(--bs-pokemon)` | accordion (як зараз) |
| `One Piece Card Game` | `background: var(--bs-onepiece)` | accordion (як зараз) |
| **`Інші TCG`** *(новий)* | `background: var(--bs-other-tcg)` | accordion з 2 sub-items: `Yu-Gi-Oh!` (точка `--bs-yugioh`), `Magic: The Gathering` (точка `--bs-mtg`) |
| **`Аксесуари`** *(новий)* | `background: var(--bs-accessories)` | прямий лінк, без розкривання |
| `Акції` | `background: var(--bs-danger)` | прямий лінк (як зараз), лишається **останнім** у каталозі |

Порядок у списку каталогу: Pokémon → One Piece → **Інші TCG** → **Аксесуари** → Акції.

Точки — той самий компонент, що вже стоїть перед Pokémon/One Piece (8×8 квадрат / dot перед текстом). Жодних інших змін у бургер-меню.

---

## 7. Acceptance / QA

- [ ] **REDO-A:** в DOM кожен `.bs-subtile__glyph > svg` має inline-атрибути `width="20" height="20"`.
- [ ] **REDO-B:** в `<head>` лінк на `boostershop-ds.css` має `?v=cat002-redo-20260629` (або новіший); відкрити URL у новій вкладці й переконатись, що в файлі є рядки `.bs-subtile` і `--bs-other-tcg`.
- [ ] **REDO-C:** `getComputedStyle(document.querySelector('.bs-subtile')).height === "84px"` на десктопі та `"72px"` на ≤ 768 px.
- [ ] Десктоп: під двома primary catcards (168 px) з'явився другий ряд із двох тонших плиток (84 px) у 2 колонки, gap 18 px, з відступом 18 px від primary.
- [ ] Висота secondary плитки **не перевищує половини** primary — основна ієрархія не порушена.
- [ ] Акцент `#065F46` / `#0D9488` присутній **тільки** як 3 px ліва смужка і як 12 % tint у плашці іконки. Жодних великих площин кольору, жодного outline кольору.
- [ ] Кліки відкривають `/catalog/more-tcg` і `/catalog/accessories` відповідно.
- [ ] Mobile (≤ 768 px): плитки стекаються в один стовпчик, висота 72 px, gap 10 px. Hint-рядок не вилазить за межі (text-ellipsis).
- [ ] Hover на плитці: border темнішає до 35 % акценту, з'являється `--bs-sh-sm`. Без шуму.
- [ ] Контраст тексту title vs фон ≥ 4.5:1 (поточний `--bs-ink` на #fff — пасує).
- [ ] Бургер-меню: точки перед усіма категоріями каталогу видимі та відповідають таблиці § 6. `Інші TCG` розкривається в YGO + MTG. `Аксесуари` — прямий лінк. `Акції` — останнім.
- [ ] SEO-урли `/catalog/more-tcg` і `/catalog/accessories` повертають 200 (а не route=…).
- [ ] Print / reduced-motion: жодних анімацій, що блокують перегляд (компонент статичний).

---

## 8. Що **не** робимо в цьому патчі

- **Не** чіпаємо primary `.bs-catcard` (висота, лого, accent strip) — лишається як у Phase D.
- **Не** додаємо image_slot / лого в нові плитки — це навмисно, щоб вони були тихі.
- **Не** додаємо count товарів (`24 товари` тощо) — секція ще порожня, цифра 0 виглядатиме гірше за її відсутність. Повернемось, коли «Інші TCG» / «Аксесуари» наповнимо.
- **Не** додаємо новий шрифт-розмір. 15 px title + 12.5 px hint — у наявних DS-сходах.

---

## 9. Референс макетів

- `Другорядні плитки - варіанти.html` — обрання напряму A та порівняння з primary.
- `Іконки другорядних плиток - варіанти.html` — голий гліф (плашка 56 px) + плитка 84 px для всіх 18 варіантів; фінальна пара в самому низу канвасу.

Frame з фінальною парою в `Іконки…html` має `data-screen-label="Final pair"` — можна використати для скрінів у Codex commit.
