# header.twig patch — Mobile Search + Burger Menu
# Файл: catalog/view/template/common/header.twig (живий бекап від 30.05.2026)

Три блоки змін. Застосовуй послідовно: B → C → A.

---

## Зміна 0 — Завантажити CSS і JS

В <head>, після рядка з boostershop-ds.css:

```
<link href="catalog/view/stylesheet/patch-mobile-search-menu-redesign.css?v=r-search-menu-01" rel="stylesheet"/>
```

Перед </body> (або defer у <head>):

```
<script src="catalog/view/javascript/patch-mobile-search-menu-redesign.js?v=r-search-menu-01" defer></script>
```

---

## Зміна 1 (Phase B+C) — Бургер + ghost-links

Замінити весь блок <header class="bs-header">…</header> на:

```twig
<header class="bs-header">
  <div class="bs-header__inner">

    {# ---- BURGER — перший дочірній елемент ---- #}
    <button type="button" class="bs-burger" id="bs-menu-open"
            aria-label="Меню" aria-controls="bs-menu" aria-expanded="false">
      <svg width="20" height="20" viewBox="0 0 18 18" fill="none" aria-hidden="true">
        <path d="M2 4.5h14M2 9h14M2 13.5h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
      </svg>
    </button>

    {# ---- LOGO — без змін ---- #}
    <a href="{{ home }}" class="bs-header__logo" aria-label="Booster Shop — на головну">
      {% if logo %}
        <img src="{{ logo }}" title="{{ name }}" alt="{{ name }}" class="img-fluid" width="1498" height="465"/>
      {% else %}
        <span>{{ name }}</span>
      {% endif %}
    </a>

    {# ---- SEARCH — mobile-expand + ps-live-search інтеграція ---- #}
    {# Зберігаємо bs-search як wrapper для ps-live-search-container.            #}
    {# Додаємо bs-msearch для expand-поведінки та id/data для плагіна.          #}
    <div class="bs-msearch" id="bs-msearch">
      <button type="button" class="bs-msearch__back" data-bs-search-close aria-label="Назад" hidden>
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
          <path d="M11 3.5L5.5 9 11 14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <form class="bs-search ps-live-search-container" action="index.php" method="get" role="search">
        <input type="hidden" name="route" value="product/search">
        <input type="hidden" name="language" value="{{ lang }}">
        <span class="bs-search__icon bs-msearch__ic" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
            <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.6"/>
            <path d="M14 14l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </span>
        <input
          id="ps-live-search-input"
          class="bs-search__input"
          type="search"
          name="search"
          data-live-search-target="ps-live-search"
          placeholder="Пошук бустерів, сетів, виробників..."
          value=""
          autocomplete="off"
        >
        <button type="button" class="bs-msearch__clear" data-bs-search-clear aria-label="Очистити" hidden>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
        {# ps-live-search рендерить результати сюди #}
        <ul id="ps-live-search" class="ps-live-search-list" data-lang="{{ lang }}"></ul>
      </form>
    </div>

    {# ---- GHOST-LINKS + CART ---- #}
    <div class="bs-header__actions">
      <a href="{% if logged %}{{ account }}{% else %}{{ login }}{% endif %}" class="bs-ghost" aria-label="{{ text_account }}">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="7" r="3.2" stroke="currentColor" stroke-width="1.6"/>
          <path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        {% if logged %}Акаунт{% else %}Увійти{% endif %}
      </a>

      <a href="https://t.me/boostershop_tcg" class="bs-ghost" target="_blank" rel="noopener" aria-label="Telegram">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M3 9.5L17 4l-2 13-4-2-2 3-1-4 8-7-9 5-4-1.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
        </svg>
        Telegram
      </a>

      <div id="cart" class="bs-header__cart">
        {{ cart|replace({
          'class="btn btn-lg btn-success d-block dropdown-toggle mini-cart-trigger"': 'class="btn bs-btn bs-btn-primary bs-btn-sm d-block dropdown-toggle mini-cart-trigger"'
        }) }}
      </div>
    </div>

  </div>
</header>
```

---

## Зміна 2 — Slide-in меню + ps-live-search init

Вставити ПІСЛЯ </header>, ПЕРЕД <main>:

```twig
{# ======= SLIDE-IN MENU ======= #}
<div class="bs-menu" id="bs-menu" hidden>
  <div class="bs-menu__scrim" data-bs-menu-close></div>
  <aside class="bs-menu__panel" role="dialog" aria-modal="true" aria-label="Меню">

    <div class="bs-menu__head">
      <div class="bs-menu__brandrow">
        <a href="{{ home }}" class="bs-menu__brand">
          Booster&nbsp;Shop
          <svg aria-hidden="true" width="12" height="18" viewBox="0 0 12 18" fill="none">
            <polygon points="7.2,0 12,0 4.8,9 10.8,9 1.8,18 6.6,9.9 0,9.9" fill="var(--bs-blue)"/>
          </svg>
        </a>
        <button type="button" class="bs-menu__close" data-bs-menu-close aria-label="Закрити">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      {% if logged %}
        <a href="{{ account }}" class="bs-menu__acct">
          <span class="bs-menu__acct-ic"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
          <span class="bs-menu__acct-label">Акаунт</span>
          <span class="bs-menu__chev"><svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </a>
        <a href="{{ order }}" class="bs-menu__orders">
          <span class="bs-menu__orders-ic"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 5l6-2.6L14 5v6l-6 2.6L2 11V5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M2 5l6 2.6L14 5M8 7.6V13.6" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
          <span>Мої замовлення</span>
        </a>
      {% else %}
        <a href="{{ login }}" class="bs-menu__acct">
          <span class="bs-menu__acct-ic"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
          <span class="bs-menu__acct-label">Увійти або зареєструватися</span>
          <span class="bs-menu__chev"><svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </a>
      {% endif %}
    </div>

    <div class="bs-menu__body">
      <div class="bs-menu__label">Каталог</div>

      <div class="bs-menu__cat">
        <button type="button" class="bs-menu__cat-row" data-bs-accordion>
          <span class="bs-menu__dot" style="background:#C68A00"></span>
          <span class="bs-menu__cat-name">Pokémon TCG</span>
          <span class="bs-menu__chev bs-menu__chev--acc"><svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </button>
        <div class="bs-menu__subs" hidden>
          <a href="index.php?route=product/category&language={{ lang }}&path=59" class="bs-menu__sub">Бустери</a>
          <a href="index.php?route=product/category&language={{ lang }}&path=60" class="bs-menu__sub">Бустер-бокси</a>
          <a href="index.php?route=product/category&language={{ lang }}&path=61" class="bs-menu__sub">Набори</a>
        </div>
      </div>

      <div class="bs-menu__cat">
        <button type="button" class="bs-menu__cat-row" data-bs-accordion>
          <span class="bs-menu__dot" style="background:#1E40AF"></span>
          <span class="bs-menu__cat-name">One Piece Card Game</span>
          <span class="bs-menu__chev bs-menu__chev--acc"><svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </button>
        <div class="bs-menu__subs" hidden>
          <a href="index.php?route=product/category&language={{ lang }}&path=62" class="bs-menu__sub">Бустери</a>
          <a href="index.php?route=product/category&language={{ lang }}&path=63" class="bs-menu__sub">Набори</a>
          <a href="index.php?route=product/category&language={{ lang }}&path=64" class="bs-menu__sub">Mystery Box</a>
        </div>
      </div>

      {# ← нові категорії (Аксесуари, Інші TCG…) додавати ТУТ, перед Акціями #}

      <div class="bs-menu__cat">
        <a href="index.php?route=product/special&language={{ lang }}" class="bs-menu__cat-row is-sale">
          <span class="bs-menu__dot" style="background:var(--bs-danger)"></span>
          <span class="bs-menu__cat-name">Акції</span>
          <span class="bs-menu__chev"><svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </a>
      </div>

      <div class="bs-menu__label bs-menu__label--sep">Інформація</div>

      <a href="index.php?route=information/information&language={{ lang }}&information_id=4" class="bs-menu__info">
        <svg width="17" height="17" viewBox="0 0 18 16" fill="none"><path d="M1 3h10v8H1zM11 6h4l2 3v2h-6z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.4"/><circle cx="13" cy="13" r="1.5" stroke="currentColor" stroke-width="1.4"/></svg>
        <span>Доставка та оплата</span>
      </a>
      <a href="index.php?route=information/information&language={{ lang }}&information_id=5" class="bs-menu__info">
        <svg width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M8 1.5l5.5 2v4c0 4-2.4 6.4-5.5 7-3.1-.6-5.5-3-5.5-7v-4L8 1.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5.5 7.7l2 2 3-3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Гарантія оригіналу</span>
      </a>
      <a href="index.php?route=information/information&language={{ lang }}&information_id=1" class="bs-menu__info">
        <svg width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M3 5h10l-.8 8.5H3.8L3 5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5.5 5V4a2.5 2.5 0 015 0v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        <span>Про магазин</span>
      </a>
    </div>

    <div class="bs-menu__foot">
      <a href="https://t.me/boostershop_tcg" class="bs-menu__tg" target="_blank" rel="noopener">
        <svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M3 9.5L17 4l-2 13-4-2-2 3-1-4 8-7-9 5-4-1.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
        Наш Telegram-канал
      </a>
    </div>
  </aside>
</div>

{# ======= PS-LIVE-SEARCH INIT (кастомна форма, не {{ search }}) ======= #}
{# Плагін не ініціалізується автоматично бо header не використовує {{ search }}. #}
{# Ініціалізуємо вручну з тими самими параметрами що й у ps_live_search model. #}
<script>
$(function () {
  if (typeof $.fn.pslivesearch === 'undefined') return;
  $('#ps-live-search-input').pslivesearch({
    source: function (request, response) {
      $.ajax({
        url: 'index.php?route=extension/ps_live_search/module/ps_live_search.autocomplete&search=' + encodeURIComponent(request),
        dataType: 'json',
        success: function (json) { response(json); }
      });
    },
    translations: {
      heading_products:        'Товари',
      heading_categories:      'Категорії',
      heading_manufacturers:   'Виробники',
      heading_informations:    'Інформація',
      text_showing_results:    'Результати за:',
      text_all_product_results:'Усі результати',
      text_no_results:         'Нічого не знайдено'
    },
    options: { input_delay: 150, input_min_chars: 1 }
  });
});
</script>
```

---

## ВАЖЛИВО — category IDs

information_id верифіковані з БД бекапу (boosters_ocart49.sql):
- **4** = Оплата і доставка
- **5** = Гарантія оригінальності
- **1** = Про магазин

Category path= для Pokémon/One Piece — **перевір в адмін**: Catalog → Categories → скопіюй path з URL категорії.

---

## QA чеклист

### Phase B — Burger
- [ ] Бургер зліва від лого; білий фон + сірий border, НЕ зелений
- [ ] Клік → панель з лівого боку + scrim; Esc / ✕ / scrim закривають
- [ ] Гість: «Увійти або зареєструватися». Авторизований: «Акаунт» + «Мої замовлення»
- [ ] Каталог: Pokémon → One Piece → Акції (Акції завжди останнє)
- [ ] Скрол body заблокований поки меню відкрите

### Phase C — Ghost-links
- [ ] «Увійти/Акаунт» і «Telegram» — сині плоскі посилання, без заливки
- [ ] Кошик — єдиний зелений елемент шапки
- [ ] На мобілі ghost-links сховані (видно в меню)

### Phase A — Mobile search
- [ ] Тап по полю НЕ редіректить на /search — фокус лишається
- [ ] Поле ≥42px заввишки, займає ширину шапки
- [ ] При введенні — ps-live-search показує dropdown під шапкою
- [ ] «Назад» / Esc / scrim ховають dropdown і очищають поле
- [ ] Desktop: поведінка незмінна (form submit → /search)
