# Хендоф — UX-003 / UX-005: шапка і бургер-меню

Дата: 2026-10-04
Executor: Claude Code · model=Sonnet · thinking=medium-high — *власник призначив Claude Code явно; зміни в шапці CSS-переважні, файли ідентифіковані; sticky зачіпає всі сторінки, тому обов’язкова перевірка оверлеїв.*

---

## 1. Task ID
`UX-003` / `UX-005` (частина пакета B)

## 2. Context
Аудит: `UX-003 UX-005 UX-009 - аудит.html`, пункти 4, 5, 7 + «Поза списком». Макети (погоджено): `UX-003 UX-005 UX-009 - макети.html`, екрани «Шапка» і «Бургер» (`ux-b-shell.jsx`, `ux-b.css`); бренд у бургері — `UX-003 UX-005 UX-009 - етап 3.html`, тема «Бренд у бургері», варіант **A · логотип**.
Шапка рендериться на всіх сторінках, включно з checkout.

## 3. Goal
Шапка фіксується при прокрутці; на desktop видно вхід «Каталог»; бургер має повний склад підкатегорій і логотип замість текстового бренду; мобільне поле пошуку без дубля хрестиків.

## 4. What to change
1. **Sticky (пункт 4):** основний рядок шапки `position:sticky; top:0`, `z-index: var(--bs-z-sticky)`. Тінь `--bs-sh-sm` з’являється після прокрутки (клас через `scroll`/IntersectionObserver). Смуга переваг на головній **прокручується** (не sticky). Увімкнено на всіх сторінках, **включно з checkout** (рішення власника). Перевірити `--bs-header-h`: оверлей мобільного пошуку рахує висоту від низу шапки.
2. **«Каталог» на ≥1024 (пункт 5, варіант a):** у кнопці `#bs-menu-open` поруч з іконкою — текст «Каталог» (700, 14.5 px), кнопка `width:auto`, padding `0 14px 0 12px`, висота 44. На <1024 — лише іконка, як зараз. Поведінка кнопки без змін.
3. **Placeholder (пункт 7):** на ≤768 — «Пошук»; на desktop — поточний. Змінює `patch-mobile-search-menu-redesign.js` (Booster-код).
4. **Дубль × (пункт 7):** `#ps-live-search-input::-webkit-search-cancel-button{-webkit-appearance:none}` (і аналог для `::-ms-clear`). Наш `[data-bs-search-clear]` — зона дотику 44×44 (видиме коло 24 px всередині).
5. **Бургер — посилання:** додати «Фігурки та декор» останнім пунктом у підкатегорії:
   - Pokémon TCG → `https://boostershop.website/catalog/Pokemon/figurky-ta-dekor-pokemon`
   - One Piece Card Game → `https://boostershop.website/catalog/One-Piece/figurky-ta-dekor-one-piece`
   Клас — як у сусідніх `.bs-menu__sub`. Жовта підсвітка з макета — лише для огляду, на сайт не йде. Відносні чи абсолютні URL — як у сусідніх пунктах.
6. **Бургер — бренд (варіант A):** замість текстового «Booster Shop ⚡» — те саме зображення логотипа, що в шапці (`{{ logo }}`, `alt="{{ name }}"`), ширина 110 px, посилання на головну. Кнопка закриття — без змін.

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, редиректи, canonical, `.htaccess`
- Чекаут, оплата, фіскалізація (шапка на checkout — лише візуально sticky, без змін розмітки форм)
- Модуль `ps_live_search` (JS/PHP/twig модуля) — пакет UX-009 робить лише CSS-оверрайди й init
- Хуки: `#ps-live-search-input`, `#ps-live-search`, `.ps-live-search-container`, `data-live-search-target`, `#bs-menu-open`, `#bs-menu`, `[data-bs-menu-close]`, `[data-bs-accordion]`, `.bs-menu__subs`, `#bs-msearch`, `[data-bs-search-close]`, `[data-bs-search-clear]`, `#cart`, `#alert`
- Мінікошик/тост (RD-12), бейдж кількості кошика — окремий баг (пункт 10 аудиту), тут не чинимо
- Merchant-фід, schema

## 6. Likely files / areas
- `catalog/view/template/common/header.twig` (evidence: `uploads/catalog/view/template/common/header.twig`)
- `catalog/view/javascript/patch-mobile-search-menu-redesign.js`
- `catalog/view/stylesheet/boostershop-ds.css` — секції `.bs-header`, `.bs-menu`, `.bs-msearch`
- Виконавець звіряє evidence зі свіжим продакшеном.

## 7. Acceptance criteria
1. Прокрутка будь-якої сторінки (головна, категорія, товар, пошук, checkout) на 390/768/1440 — шапка лишається вгорі; після прокрутки є тінь.
2. Відкриті бургер, мобільний пошук, мінікошик і тост відображаються **над** sticky-шапкою або коректно під нею, нічого не перекрито.
3. На 1440 кнопка бургера показує «Каталог»; на 390/768 — лише іконка.
4. На 390 placeholder — «Пошук»; у полі з текстом видно один ×, зона дотику ≥44 px.
5. Бургер: у Pokémon і One Piece є «Фігурки та декор», посилання відкривають категорії (HTTP 200).
6. Угорі бургера — логотип-зображення, клік веде на головну.
7. Консоль без нових помилок, CLS не погіршився (Lighthouse до/після).

## 8. QA / smoke test
- Сторінки: головна, категорія Pokémon, товар, `product/search`, кошик, checkout (до кроку оплати, без відправки).
- Якорі й `scrollTo` (якщо є на картці товару) — контент не ховається під sticky-шапкою (`scroll-margin-top` при потребі).
- Чекаут зачіпається тільки візуально — пройти кроки 1–4 `bs-checkout-smoke` (шапка не перекриває поля й кнопки).

## 9. Rollback note
Бекап `header.twig`, `patch-mobile-search-menu-redesign.js`, `boostershop-ds.css` у `backups/<дата>-ux003-005/`. Відкат — залити бекап, скинути кеш теми.
Тригер: перекриті оверлеї, не відкривається бургер/пошук, шапка перекриває кнопки checkout.

## 10. Recommended status after execution
`In review` → `Done` після пп. 1–6.

---

**Доставка:** один файл у `patches/`. Виконувати **до** пакета UX-009 «живий пошук» (обидва чіпають `header.twig`). Власник завантажує в `~/public_html` і запускає `php <patch>.php`. Виконавець нічого не комітить, не пушить і не деплоїть.
