# Booster Shop — Implementation Handoff · H1 секції каталогу (Опція A)

_Designed: 01.06.2026 · Recipient: dev. Source mock: `H1 фінал.html` (Основний варіант + Опція A «TCG золотим»)._

Drop-in заміна простого `<h1>` над плитками категорій на головній. Двохрядковий локап: акцентований перший рядок (з «TCG» золотим) + менший приглушений другий рядок. Шрифт — Manrope (шрифт сайту), жодних нових гарнітур. Усі кольори з уже підключених токенів `boostershop-ds.css`.

> Передумова: `boostershop-ds.css` + Manrope вже завантажені, на `<body>` є клас `bs` (Phase 0 основного HANDOFF). Якщо ні — спочатку зробіть Phase 0.

---

## 1. Goal

Замість одного жирного чорного рядка — типографічна ієрархія, що тримається на розмірі й вазі:

- **Рядок 1 (акцент):** `Оригінальні бустери та бокси TCG` — Manrope 800, щільний трекінг, «TCG» золотим (`--bs-pokemon`).
- **Рядок 2 (приглушений):** `Pokémon · One Piece · та інші ігри` — менший, вага 600, сірий (`--bs-ink-3`).

---

## 2. Files

- `<THEME>/common/home_tiles.twig` — додати локап **перед** `<section class="bs-home-tiles">` (блок плиток з Phase 5.10 основного HANDOFF). Якщо заголовок зараз в іншому шаблоні головної (`home.twig` / CMS-блок) — вставляйте там, де він рендериться зараз.
- `<CSS>/boostershop-ds.css` — додати блок CSS із п.4 (append у кінець файлу).

---

## 3. Markup (Twig)

Текст фіксований, тож можна вставити статично. «TCG» винесено в окремий `<span>` для золотого акценту.

```twig
<header class="bs-cat-heading">
  <h1 class="bs-cat-heading__title">
    Оригінальні бустери та бокси <span class="bs-cat-heading__accent">TCG</span>
  </h1>
  <p class="bs-cat-heading__sub">
    Pokémon<span class="bs-cat-heading__sep">·</span>One&nbsp;Piece<span class="bs-cat-heading__sep">·</span>та інші ігри
  </p>
</header>
```

**Рівень заголовка:** якщо це головний заголовок сторінки — лишайте `<h1>`. Якщо на головній уже є інший `<h1>` (напр. прихований/у хедері) — змініть на `<h2>` (клас і вигляд не зміняться, стилі не зав'язані на тег).

**Якщо текст приходить з адмінки/CMS** і ви не можете розбити останнє слово вручну — винесіть «TCG» в окреме поле або відріжте останнє слово у Twig:

```twig
{% set words = heading_title|split(' ') %}
{% set last  = words|last %}
{% set head  = words[0:words|length - 1]|join(' ') %}
<h1 class="bs-cat-heading__title">
  {{ head }} <span class="bs-cat-heading__accent">{{ last }}</span>
</h1>
```

---

## 4. CSS (append to `boostershop-ds.css`)

```css
/* -- Home category section heading (H1, Опція A) ----------------------- */
.bs-cat-heading { margin: 0 0 24px; }

.bs-cat-heading__title {
  margin: 0;
  font-family: 'Manrope', system-ui, sans-serif;
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1.1;
  color: var(--bs-ink);
  text-wrap: balance;
}
.bs-cat-heading__accent { color: var(--bs-pokemon); }

.bs-cat-heading__sub {
  margin: 8px 0 0;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: -0.005em;
  line-height: 1.3;
  color: var(--bs-ink-3);
}
.bs-cat-heading__sep {
  color: var(--bs-ink-4);
  font-weight: 500;
  margin: 0 0.35em;
}

@media (max-width: 640px) {
  .bs-cat-heading { margin-bottom: 18px; }
  .bs-cat-heading__title { font-size: 24px; letter-spacing: -0.02em; }
  .bs-cat-heading__sub   { font-size: 15px; }
}
```

---

## 5. Notes

- **Токени, не хардкод.** Золотий = `--bs-pokemon` (#C68A00), не довільний `#D4A017`. Сірі — `--bs-ink-3` / `--bs-ink-4`. Не вписуйте hex вручну.
- **Без зеленого.** Зелений лишається тільки для дій купівлі (constraint основного HANDOFF) — у заголовку його немає.
- **`·` — це роздільник, не контент.** Він у `<span>`, щоб не псувати читання скрінрідером і легко керувати відступом.
- **`text-wrap: balance`** робить перенос першого рядка рівним на вузьких десктопах; деградує безпечно в старих браузерах.
- **`&nbsp;` у «One Piece»** не дає розірвати назву гри між рядками.

---

## 6. QA checklist

- [ ] Desktop: «TCG» золоте, решта першого рядка — чорнило; другий рядок сірий і менший.
- [ ] Mobile (375px): заголовок 24px, без горизонтального скролу, «One Piece» не розривається.
- [ ] Заголовок стоїть над плитками `bs-home-tiles`, відступ 24px (18px на мобайлі).
- [ ] На сторінці лишається рівно один `<h1>` (перевірити outline сторінки).
- [ ] Золотий колір = computed `--bs-pokemon`, не сторонній hex.

---

## Опційно — інші варіанти акценту (якщо передумаєте)

Усі — той самий локап, міняється лише деталь:

- **Основний (без золота):** прибрати `<span class="bs-cat-heading__accent">` — «TCG» стає кольору чорнила. Решта без змін.
- **+ Надзаголовок (Опція B):** додати перед `<h1>`:
  ```twig
  <span class="bs-cat-heading__kicker"><i></i>Каталог TCG</span>
  ```
  ```css
  .bs-cat-heading__kicker {
    display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 11px; letter-spacing: .16em; text-transform: uppercase;
    color: var(--bs-pokemon); font-weight: 500;
  }
  .bs-cat-heading__kicker i { width: 6px; height: 6px; background: var(--bs-gold); transform: rotate(45deg); }
  ```
