# Booster Shop — Handoff Addendum: Header Burger Menu + Mobile Search

_Companion to `HANDOFF.md`. Recipient: Codex / dev. Built from `Мобільний пошук редизайн.html` (+ `web-menu.jsx`, `search-mobile-variants.jsx`). Extends **Phase 5.1 Header**._

---

## 0. Scope & principles

Two improvements to the **existing** header — **do not redesign it**:

- **A. Mobile search** — stop redirecting to `/search` on tap. The field must be a large, easy-to-hit target that expands in place and shows live results inline (no page change).
- **B. Desktop burger menu** — a compact burger **left of the logo** that opens a left slide-in panel containing the **same** menu used on mobile (account → catalog → info → Telegram). Guest vs authorized states.
- **C. Header right side** — `Увійти/Акаунт` and `Telegram` become secondary **blue ghost-links**; the **cart stays the only green CTA**.

Core rule: **green = cart only.** `Увійти/Акаунт` + `Telegram` are secondary ghost-links in brand blue. Burger is neutral (white + grey border).

**Also remove the utility strip** above the header (the grey announcement line «Оригінальні sealed-бустери… · Доставка ~3 дні / Безпечна оплата · Гарантія оригіналу»). Delete that `<div>` entirely — the header starts directly with the main row.

**Token reference** (already in `tokens.css` / `boostershop-ds.css`, all match the brief):

| Token | Hex | Use |
|---|---|---|
| `--bs-blue` | `#1E3A8A` | ghost-link text/icon |
| `--bs-green` / `--bs-green-hover` | `#16A34A` / `#15803D` | cart CTA |
| `--bs-line` | `#E5E7EB` | burger border, dividers |
| `--bs-bg` | `#F7F7F5` | burger/ghost hover, panel header |
| `--bs-danger` | `#B91C1C` | «Акції» accent |
| `--bs-pokemon` `--bs-onepiece` | `#C68A00` `#1E40AF` | category dots |

> Reference visual: `Мобільний пошук редизайн.html` — open it, scroll to **«Те саме меню · десктоп»**, and toggle **Гість / Авторизований**.

Recommended order: **B → C** (one header pass) → **A** (search, touches the live-search plugin).

---

## Phase B — Desktop burger + left slide-in menu

### B.1 Goal
A burger button **left of the logo** opens a 380px panel sliding from the left over a dark scrim. Same content as mobile. Closes on **✕**, **scrim click**, and **Esc**. The horizontal category nav row stays.

### B.2 Files
- `<THEME>/common/header.twig` — add burger button + the menu markup.
- `<CSS>/boostershop-ds.css` — append the menu CSS below.
- `<JS>/boostershop.js` (or your theme JS) — append the open/close controller.

### B.3 Burger button — place it FIRST inside `.bs-header__inner`

In Phase 5.1's header, insert the burger as the first child of `.bs-header__inner`, **before** the logo `<a>`:

```twig
<button type="button" class="bs-burger" id="bs-menu-open" aria-label="Меню"
        aria-controls="bs-menu" aria-expanded="false">
  <svg width="20" height="20" viewBox="0 0 18 18" fill="none" aria-hidden="true">
    <path d="M2 4.5h14M2 9h14M2 13.5h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
  </svg>
</button>
```

### B.4 Menu markup — append at the END of `header.twig` (sibling of `.bs-header`)

The panel is `position: fixed`, so it can live right after the header. **Guest vs authorized** is the only conditional — everything else is identical. Categories come from your catalog menu loop; keep **Акції last** so future categories slot in above it.

```twig
{# ============ SLIDE-IN MENU ============ #}
<div class="bs-menu" id="bs-menu" hidden>
  <div class="bs-menu__scrim" data-bs-menu-close></div>

  <aside class="bs-menu__panel" role="dialog" aria-modal="true" aria-label="Меню">

    {# ---- account header ---- #}
    <div class="bs-menu__head">
      <div class="bs-menu__brandrow">
        <span class="bs-menu__brand">{{ logo_svg|raw }}</span>
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
          <span class="bs-menu__chev">{{ _chev() }}</span>
        </a>
        <a href="{{ order }}" class="bs-menu__orders">
          <span class="bs-menu__orders-ic"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 5l6-2.6L14 5v6l-6 2.6L2 11V5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M2 5l6 2.6L14 5M8 7.6V13.6" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
          <span>Мої замовлення</span>
        </a>
      {% else %}
        <a href="{{ login }}" class="bs-menu__acct">
          <span class="bs-menu__acct-ic"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
          <span class="bs-menu__acct-label">Увійти або зареєструватися</span>
          <span class="bs-menu__chev">{{ _chev() }}</span>
        </a>
      {% endif %}
    </div>

    {# ---- scrollable body ---- #}
    <div class="bs-menu__body">

      <div class="bs-menu__label">Каталог</div>
      {# Render from your top category menu. Each item may have children (subs). #}
      {% for c in catalog_menu %}
        <div class="bs-menu__cat">
          {% if c.children %}
            <button type="button" class="bs-menu__cat-row" data-bs-accordion>
              <span class="bs-menu__dot" style="background: {{ c.accent|default('var(--bs-ink-3)') }};"></span>
              <span class="bs-menu__cat-name">{{ c.name }}</span>
              <span class="bs-menu__chev bs-menu__chev--acc">{{ _chev() }}</span>
            </button>
            <div class="bs-menu__subs" hidden>
              {% for s in c.children %}
                <a href="{{ s.href }}" class="bs-menu__sub">{{ s.name }}</a>
              {% endfor %}
            </div>
          {% else %}
            <a href="{{ c.href }}" class="bs-menu__cat-row {{ c.sale ? 'is-sale' : '' }}">
              <span class="bs-menu__dot" style="background: {{ c.sale ? 'var(--bs-danger)' : (c.accent|default('var(--bs-ink-3)')) }};"></span>
              <span class="bs-menu__cat-name">{{ c.name }}</span>
              <span class="bs-menu__chev">{{ _chev() }}</span>
            </a>
          {% endif %}
        </div>
      {% endfor %}

      <div class="bs-menu__label bs-menu__label--sep">Інформація</div>
      <a href="{{ information_delivery }}" class="bs-menu__info">{{ _ic_truck() }}<span>Доставка та оплата</span></a>
      <a href="{{ information_authenticity }}" class="bs-menu__info">{{ _ic_shield() }}<span>Гарантія оригіналу</span></a>
      <a href="{{ information_about }}" class="bs-menu__info">{{ _ic_bag() }}<span>Про магазин</span></a>
    </div>

    {# ---- footer ---- #}
    <div class="bs-menu__foot">
      <a href="{{ telegram_url }}" class="bs-menu__tg">{{ _ic_tg() }} Наш Telegram-канал</a>
    </div>
  </aside>
</div>
```

> `_chev()`, `_ic_*()` are just shorthand for the inline SVGs — paste the actual SVG markup (copy from `search-mobile-app.jsx` `Ic` object) or register Twig macros. Chevron path: `M4 2.5L9 7l-5 4.5`.

**Catalog data (controller / `catalog_menu`).** Keep this exact order so future categories drop in correctly:

```text
Pokémon TCG          accent #C68A00   children: [Бустери, Бустер-бокси, Набори]
One Piece Card Game  accent #1E40AF   children: [Бустери, Набори, Mystery Box]
[future category]    ← Аксесуари
[future category]    ← Інші TCG
Акції                sale: true       (always LAST, no children)
```

### B.5 CSS — append to `boostershop-ds.css`

```css
/* ---- burger ---- */
.bs-burger {
  width: 42px; height: 42px; flex: 0 0 auto;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: var(--bs-r-sm);
  background: #fff; border: 1px solid var(--bs-line);
  color: #374151; cursor: pointer; transition: background .15s;
}
.bs-burger:hover { background: var(--bs-bg); }

/* ---- slide-in menu ---- */
.bs-menu { position: fixed; inset: 0; z-index: 1000; }
.bs-menu[hidden] { display: none; }
.bs-menu__scrim {
  position: absolute; inset: 0; background: rgba(17,24,39,.42);
  opacity: 0; transition: opacity .22s;
}
.bs-menu__panel {
  position: absolute; top: 0; bottom: 0; left: 0;
  width: 380px; max-width: 86vw;
  background: #fff; display: flex; flex-direction: column;
  box-shadow: 10px 0 50px rgba(17,24,39,.2);
  transform: translateX(-104%); transition: transform .3s cubic-bezier(.32,.72,0,1);
}
.bs-menu.is-open .bs-menu__scrim { opacity: 1; }
.bs-menu.is-open .bs-menu__panel { transform: translateX(0); }

/* head */
.bs-menu__head { padding: 14px; background: var(--bs-bg); border-bottom: 1px solid var(--bs-line); flex: 0 0 auto; }
.bs-menu__brandrow { display: flex; align-items: center; justify-content: space-between; height: 38px; }
.bs-menu__brand { display: inline-flex; }
.bs-menu__close { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
  background: #fff; border: 1px solid var(--bs-line); border-radius: var(--bs-r-sm); color: var(--bs-ink-2); cursor: pointer; }
.bs-menu__acct { margin-top: 12px; height: 46px; display: flex; align-items: center; gap: 11px; padding: 0 14px;
  background: #fff; border: 1px solid var(--bs-line); border-radius: var(--bs-r-sm); text-decoration: none; }
.bs-menu__acct-ic { width: 28px; height: 28px; border-radius: 50%; flex: 0 0 auto;
  background: var(--bs-blue-soft); color: var(--bs-blue); display: inline-flex; align-items: center; justify-content: center; }
.bs-menu__acct-label { flex: 1; font-size: 14px; font-weight: 700; color: var(--bs-ink); }
.bs-menu__orders { display: flex; align-items: center; gap: 11px; height: 40px; padding: 0 14px; margin-top: 6px;
  text-decoration: none; border-radius: var(--bs-r-sm); }
.bs-menu__orders:hover { background: #fff; }
.bs-menu__orders-ic { width: 28px; display: inline-flex; justify-content: center; color: var(--bs-ink-3); flex: 0 0 auto; }
.bs-menu__orders span:last-child { flex: 1; font-size: 13.5px; font-weight: 600; color: var(--bs-ink-2); }

/* body */
.bs-menu__body { flex: 1; overflow-y: auto; }
.bs-menu__label { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10.5px; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; color: var(--bs-ink-4); padding: 12px 16px 6px; }
.bs-menu__label--sep { margin-top: 6px; border-top: 1px solid var(--bs-line-2); padding-top: 16px; }
.bs-menu__cat-row { display: flex; align-items: center; gap: 12px; width: 100%; height: 48px; padding: 0 18px;
  background: transparent; border: 0; cursor: pointer; text-align: left; text-decoration: none; font: inherit; }
.bs-menu__dot { width: 9px; height: 9px; border-radius: 3px; flex: 0 0 auto; }
.bs-menu__cat-name { flex: 1; font-size: 14.5px; font-weight: 600; color: var(--bs-ink); }
.bs-menu__cat-row.is-sale .bs-menu__cat-name { color: var(--bs-danger); font-weight: 800; }
.bs-menu__chev { color: var(--bs-ink-4); display: inline-flex; flex: 0 0 auto; }
.bs-menu__chev--acc { transition: transform .2s; }
.bs-menu__cat.is-open .bs-menu__chev--acc { transform: rotate(90deg); }
.bs-menu__subs { padding-bottom: 6px; }
.bs-menu__subs[hidden] { display: none; }
.bs-menu__sub { display: block; padding: 9px 18px 9px 39px; font-size: 13.5px; color: var(--bs-ink-2); text-decoration: none; }
.bs-menu__info { display: flex; align-items: center; gap: 12px; height: 46px; padding: 0 18px; text-decoration: none; }
.bs-menu__info svg { color: var(--bs-ink-3); flex: 0 0 auto; }
.bs-menu__info span { flex: 1; font-size: 14px; font-weight: 500; color: var(--bs-ink-2); }

/* foot */
.bs-menu__foot { padding: 14px; border-top: 1px solid var(--bs-line); flex: 0 0 auto; }
.bs-menu__tg { display: flex; align-items: center; justify-content: center; gap: 8px; height: 44px;
  background: var(--bs-blue-soft); color: var(--bs-blue); border-radius: var(--bs-r-sm);
  font-size: 13.5px; font-weight: 700; text-decoration: none; }

body.bs-menu-lock { overflow: hidden; }
```

### B.6 JS — append to your theme JS

```js
(function () {
  var menu  = document.getElementById('bs-menu');
  var open  = document.getElementById('bs-menu-open');
  if (!menu || !open) return;
  var panel = menu.querySelector('.bs-menu__panel');
  var lastFocus = null;

  function openMenu() {
    lastFocus = document.activeElement;
    menu.hidden = false;
    // next frame so the transition runs
    requestAnimationFrame(function () { menu.classList.add('is-open'); });
    open.setAttribute('aria-expanded', 'true');
    document.body.classList.add('bs-menu-lock');
    (panel.querySelector('a,button') || panel).focus();
  }
  function closeMenu() {
    menu.classList.remove('is-open');
    open.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('bs-menu-lock');
    setTimeout(function () { menu.hidden = true; }, 300); // after transition
    if (lastFocus) lastFocus.focus();
  }

  open.addEventListener('click', openMenu);
  menu.addEventListener('click', function (e) {
    if (e.target.closest('[data-bs-menu-close]')) closeMenu();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu.classList.contains('is-open')) closeMenu();
  });

  // category accordions
  menu.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-bs-accordion]');
    if (!btn) return;
    var cat = btn.closest('.bs-menu__cat');
    var subs = cat.querySelector('.bs-menu__subs');
    var isOpen = cat.classList.toggle('is-open');
    if (subs) subs.hidden = !isOpen;
  });
})();
```

### B.7 Notes
- On **mobile** the same `#bs-menu` + button is reused — the panel is already `max-width: 86vw`. If the mobile theme has a separate burger, point it at `openMenu()` and delete the duplicate.
- **Focus trap** is optional but recommended (loop Tab within `.bs-menu__panel`); minimum is the focus-in / focus-restore above.
- The panel is `position: fixed`, so it overlays the whole viewport regardless of where you place the markup.

### B.8 QA
- [ ] Burger sits left of logo; white bg, grey border, hover `#F7F7F5`; **not green**.
- [ ] Click opens panel from left with scrim; **Esc / ✕ / scrim** all close.
- [ ] Guest: top shows **«Увійти або зареєструватися»** only.
- [ ] Authorized (`{% if logged %}`): top shows **«Акаунт» + «Мої замовлення»**, no login button.
- [ ] Catalog order Pokémon → One Piece → … → **Акції last**; accordions expand subs.
- [ ] Body scroll locks while open; restores on close.

---

## Phase C — Header right side: ghost-links + green-only cart

### C.1 Goal
`Увійти/Акаунт` and `Telegram` become compact **blue ghost-links** (no fill, no heavy border). Cart stays the **only green CTA**. Final desktop row:

```text
[burger] [logo] [search] [user icon] Увійти/Акаунт   [tg icon] Telegram   [🛒 Кошик · ₴700]
```

### C.2 Replace the two account/telegram `<a>` in Phase 5.1 with ghost-links

```twig
<a href="{{ logged ? account : login }}" class="bs-ghost">
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="7" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
  {{ logged ? 'Акаунт' : 'Увійти' }}
</a>
<a href="{{ telegram_url }}" class="bs-ghost">
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 9.5L17 4l-2 13-4-2-2 3-1-4 8-7-9 5-4-1.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
  Telegram
</a>
```

Leave the **cart** `<a class="bs-btn bs-btn-primary bs-btn-sm">` exactly as in Phase 5.1 — `bs-btn-primary` is already `--bs-green` with `--bs-green-hover`. Label: `🛒 Кошик · ₴{{ cart_total }}` (or keep your current cart text).

### C.3 CSS — append

```css
.bs-ghost {
  display: inline-flex; align-items: center; gap: 7px; flex: 0 0 auto;
  padding: 8px 10px; border-radius: var(--bs-r-sm);
  background: transparent; border: 0; cursor: pointer;
  color: var(--bs-blue); font: inherit; font-size: 13.5px; font-weight: 600;
  text-decoration: none; white-space: nowrap; transition: background .15s, color .15s;
}
.bs-ghost:hover { background: var(--bs-bg); color: #16245C; }

@media (max-width: 768px) {
  .bs-ghost { display: none; }   /* fold into the burger menu on mobile */
}
```

> The old Phase 5.1 rule `.bs-header .bs-btn-ghost { display:none }` (mobile) can be dropped — ghost-links are now `.bs-ghost` and already hidden under 768px here.

### C.4 QA
- [ ] `Увійти/Акаунт` + `Telegram` render as flat blue links, no border/fill.
- [ ] Label flips `Увійти`↔`Акаунт` with `{% if logged %}`.
- [ ] Cart is the **only** green element in the header; hover darkens to `#15803D`.
- [ ] Under 768px both ghost-links hide (accessible via burger).

---

## Phase A — Mobile search: no redirect, live results in place

### A.1 Goal
Tapping the mobile search field must **not** navigate to `/search`. The field is a full-width, ≥44px-tall target; on focus it expands and the **live-search dropdown renders in place** under the header. A dimming scrim + back affordance; results come from the existing live-search AJAX.

### A.2 Why it happens now
On mobile the live-search field is shrunk to ~85px and the tap target either: (a) is the tiny input that's hard to hit, and/or (b) the theme wraps/handles the mobile field so focus/submit jumps to `index.php?route=product/search`. We make the input itself the full-width target and keep the plugin's AJAX dropdown anchored under the header instead of redirecting.

### A.3 Files
- `<THEME>/common/header.twig` — mobile search markup (or the live-search module template, e.g. `extension/<ps-live-search>/catalog/view/template/...`).
- `<CSS>/boostershop-ds.css` — append.
- Theme JS — append the expand controller.

### A.4 Find & remove the redirect
Search the theme/plugin JS for any of these and **remove the navigation**, keeping only the AJAX-dropdown behaviour:

```js
// examples to look for and KILL on mobile:
$('.search input').on('focus', function(){ location = '...route=product/search'; });
// or a mobile <a href="...route=product/search"> wrapping the field
// or form submit redirect on the first keystroke
```

The live-search plugin already returns a results list via AJAX (it renders a dropdown on desktop). The fix is to **let that same dropdown render on mobile** anchored to the header, rather than redirecting.

### A.5 Markup — the field is the tap target

```twig
<div class="bs-msearch" id="bs-msearch">
  <button type="button" class="bs-msearch__back" data-bs-search-close aria-label="Назад" hidden>
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M11 3.5L5.5 9 11 14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
  <div class="bs-msearch__field">
    <svg class="bs-msearch__ic" width="17" height="17" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M14 14l4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
    <input class="bs-msearch__input" type="search" name="search" autocomplete="off"
           placeholder="Пошук бустерів, сетів…" value="{{ search_query }}">
    <button type="button" class="bs-msearch__clear" data-bs-search-clear aria-label="Очистити" hidden>✕</button>
  </div>
</div>

{# results dropdown — the live-search plugin renders its list INTO #bs-msearch-results #}
<div class="bs-msearch__results" id="bs-msearch-results" hidden></div>
<div class="bs-msearch__scrim" data-bs-search-close hidden></div>
```

Point the live-search plugin's output container at `#bs-msearch-results` (most builds accept a results-selector option, or you can move its generated `<ul>` into this node on `input`).

### A.6 CSS — append

```css
.bs-msearch { display: flex; align-items: center; gap: 8px; flex: 1; }
.bs-msearch__back { width: 30px; height: 42px; display: none; align-items: center; justify-content: center;
  background: transparent; border: 0; color: var(--bs-ink-2); cursor: pointer; flex: 0 0 auto; }
.bs-msearch.is-open .bs-msearch__back { display: inline-flex; }
.bs-msearch__field { flex: 1; display: flex; align-items: center; gap: 8px; height: 42px; padding: 0 12px;
  background: var(--bs-bg); border: 1.5px solid var(--bs-line); border-radius: var(--bs-r-sm);
  transition: border-color .15s, box-shadow .15s, background .15s; }
.bs-msearch.is-open .bs-msearch__field { background: #fff; border-color: var(--bs-blue); box-shadow: 0 0 0 3px rgba(30,58,138,.10); }
.bs-msearch__ic { color: var(--bs-ink-3); flex: 0 0 auto; }
.bs-msearch.is-open .bs-msearch__ic { color: var(--bs-blue); }
.bs-msearch__input { flex: 1; min-width: 0; border: 0; background: transparent; outline: none; font: inherit; font-size: 14.5px; color: var(--bs-ink); }
.bs-msearch__clear { width: 24px; height: 24px; border: 0; border-radius: 50%; background: var(--bs-line-2);
  color: var(--bs-ink-2); cursor: pointer; flex: 0 0 auto; }

.bs-msearch__results { position: fixed; left: 0; right: 0; z-index: 60; background: #fff;
  border-bottom: 1px solid var(--bs-line); box-shadow: var(--bs-sh-pop); max-height: calc(100vh - var(--bs-header-h, 112px));
  overflow-y: auto; }
.bs-msearch__results[hidden] { display: none; }
.bs-msearch__scrim { position: fixed; inset: var(--bs-header-h, 112px) 0 0; z-index: 55; background: rgba(17,24,39,.32); }
.bs-msearch__scrim[hidden] { display: none; }

/* desktop: keep the normal inline behaviour, no expand needed */
@media (min-width: 769px) { .bs-msearch__back, .bs-msearch__scrim { display: none !important; } }
```

> Set `--bs-header-h` to your sticky header height so the dropdown + scrim sit just below it (e.g. `:root { --bs-header-h: 112px; }`, or compute in JS).

### A.7 JS — expand controller (no navigation)

```js
(function () {
  var wrap   = document.getElementById('bs-msearch');
  if (!wrap) return;
  var input  = wrap.querySelector('.bs-msearch__input');
  var clear  = wrap.querySelector('[data-bs-search-clear]');
  var results= document.getElementById('bs-msearch-results');
  var scrim  = document.querySelector('.bs-msearch__scrim');

  function openSearch() {
    wrap.classList.add('is-open');
    wrap.querySelector('.bs-msearch__back').hidden = false;
    if (scrim) scrim.hidden = false;
  }
  function closeSearch() {
    wrap.classList.remove('is-open');
    wrap.querySelector('.bs-msearch__back').hidden = true;
    if (scrim) scrim.hidden = true;
    if (results) results.hidden = true;
    input.value = ''; if (clear) clear.hidden = true;
    input.blur();
  }

  // open on focus — DO NOT redirect
  input.addEventListener('focus', openSearch);
  input.addEventListener('input', function () {
    if (clear) clear.hidden = !input.value;
    if (results) results.hidden = !input.value;   // plugin fills #bs-msearch-results
  });
  // prevent the form (if any) from submitting/redirecting on Enter while typing inline
  var form = input.closest('form');
  if (form) form.addEventListener('submit', function (e) {
    // allow full submit only if you still want an "all results" page; otherwise:
    // e.preventDefault();
  });

  if (clear) clear.addEventListener('click', function () { input.value=''; input.focus(); clear.hidden=true; if(results) results.hidden=true; });
  document.querySelectorAll('[data-bs-search-close]').forEach(function (b) { b.addEventListener('click', closeSearch); });
  document.addEventListener('keydown', function (e) { if (e.key==='Escape' && wrap.classList.contains('is-open')) closeSearch(); });
})();
```

> Keep the **«Усі результати за…»** link inside the dropdown pointing to `route=product/search&search=…` — that's the **only** place a full search-page navigation should happen, and only on explicit tap (not on focus).

### A.8 QA
- [ ] Tapping the field **stays on the page** (no `/search` redirect) and focuses the input.
- [ ] Field is ≥44px tall, full header width — easy to hit one-handed.
- [ ] Typing shows the live dropdown anchored under the header; scrim dims the page.
- [ ] **Назад / Esc / scrim** close the field and clear it.
- [ ] Desktop behaviour unchanged (inline field, plugin dropdown as before).
- [ ] «Усі результати за…» is the only thing that opens the full search page.

---

## Phase D — Category promo cards: enlarged full-bleed logo

### D.1 Goal
Keep the current category cards (Pokémon TCG / One Piece Card Game — top accent bar, title, description, «Переглянути →»). Only change: the **logo image becomes a large full-bleed panel** on the left, the **full height of the card**. It scales up and is **cropped vertically** (`object-fit: cover`) so the tile height never grows.

### D.2 Files
- `<THEME>/common/home category module template` (e.g. `module/category_promo.twig` or wherever these two cards render).
- `<CSS>/boostershop-ds.css` — append.

### D.3 Markup

```twig
<a href="{{ cat.href }}" class="bs-catcard" style="--accent: {{ cat.accent }};">
  <span class="bs-catcard__media">
    <img src="{{ cat.image }}" alt="{{ cat.name }}" loading="lazy">
  </span>
  <span class="bs-catcard__body">
    <span class="bs-catcard__title">{{ cat.name }}</span>
    <span class="bs-catcard__desc">{{ cat.description }}</span>
    <span class="bs-catcard__more">Переглянути →</span>
  </span>
</a>
```

Accent per card: Pokémon `#C68A00`, One Piece `#1E40AF`.

### D.4 CSS — append

```css
.bs-catcard {
  position: relative; display: flex; height: 168px; overflow: hidden;
  background: #fff; border: 1px solid var(--bs-line);
  border-radius: var(--bs-r); box-shadow: var(--bs-sh-sm); text-decoration: none;
}
.bs-catcard::before {            /* top accent bar */
  content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px;
  background: var(--accent); z-index: 2;
}
.bs-catcard__media {
  flex: 0 0 168px; width: 168px; height: 168px;   /* = card height → square, full-bleed */
  background: var(--bs-bg); border-right: 1px solid var(--bs-line);
}
.bs-catcard__media img {
  width: 100%; height: 100%;
  object-fit: cover;            /* ENLARGE + crop vertically; never grows the tile */
  object-position: 50% 50%;     /* nudge per-logo if a crop clips the wordmark */
  display: block;
}
.bs-catcard__body { flex: 1; min-width: 0; padding: 18px 20px; display: flex; flex-direction: column; }
.bs-catcard__title { font-size: 18px; font-weight: 800; color: var(--bs-ink); letter-spacing: -0.01em; }
.bs-catcard__desc {
  font-size: 13.5px; line-height: 1.5; color: var(--bs-ink-3); margin-top: 6px;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.bs-catcard__more { margin-top: auto; padding-top: 10px; font-size: 13.5px; font-weight: 700; color: var(--bs-blue); }

@media (max-width: 768px) {
  /* stack full width; slightly shorter tile + narrower media */
  .bs-catcard { height: 132px; }
  .bs-catcard__media { flex-basis: 124px; width: 124px; height: 132px; }
  .bs-catcard__desc { -webkit-line-clamp: 2; }
}
```

> Desktop wraps the two cards in a 2-col grid (`display:grid; grid-template-columns:1fr 1fr; gap:18px`); mobile is a single column (`grid-template-columns:1fr`). The media is sized to the card height, so a tall/wide logo is scaled to fill and the overflow is clipped — exactly the requested vertical crop. If a logo's wordmark gets clipped, tune `object-position` for that card only (e.g. `object-position: 50% 35%`).

### D.5 QA
- [ ] Logo panel is the **full height** of the card, flush to the left/top/bottom edges.
- [ ] Card height is unchanged vs. now — the bigger logo is cropped, not the tile.
- [ ] Top accent bar present (gold / blue); title, 2–3 line description, «Переглянути →» intact.
- [ ] Desktop 2-up, mobile stacked full-width.

---

## Acceptance summary

| # | Outcome |
|---|---|
| B | Burger left of logo → left slide-in panel; same menu as mobile; Esc/✕/scrim close. |
| B | Guest = «Увійти або зареєструватися»; Authorized = «Акаунт» + «Мої замовлення». |
| B | Catalog data-driven, **Акції last**, room for Аксесуари / Інші TCG above it. |
| C | `Увійти/Акаунт` + `Telegram` = blue ghost-links; cart = only green CTA. |
| A | Mobile search no longer redirects; live results render in place; big tap target. |
| D | Category cards: logo is full-bleed full-height, cropped vertically; tile height unchanged. |
| — | Utility/announcement strip above the header removed. |

_All hexes, radii and shadows come from `tokens.css` / `boostershop-ds.css` — do not hard-code new colours._
