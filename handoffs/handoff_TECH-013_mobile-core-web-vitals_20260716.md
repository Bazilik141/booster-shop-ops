# Codex Handoff — TECH-013: Mobile Core Web Vitals pass

Date: 2026-07-16 | Parent: TECH-013 (умбрела для TECH-002/003/004) | Prepared by: Claude · Recipient: Codex + Owner

---

## SEO / RISK GATE (preflight)
- **Risk: MEDIUM-HIGH** — торкається спільного header/footer (усі сторінки, включно з checkout) і потенційно `.htaccess` (cache headers).
- **.htaccess — risky zone за AGENTS.md.** Будь-яка зміна тільки новим ізольованим блоком (напр. `# BEGIN cwv-static-cache`), НЕ чіпати існуючий `# BEGIN sitemap-no-compression` блок (TECH-005-DEEP, closed/watch-only). Гілка+PR, не прямий патч.
- **Checkout smoke обов'язковий** — якщо міняється порядок/defer скриптів у header/footer, це рендериться і на checkout сторінці. Один регрес у SimpleCheckout/First15/Hutko з цього патчу відкотить те, що щойно закрили (ST-2b6e, RD-13.1J, CHECKOUT-003/004/005).
- Sitemap/robots/canonical/Merchant/schema — поза межами цієї задачі, не чіпати.

---

## 1. Task ID
**TECH-013** — Core Web Vitals technical pass (mobile). Пріоритет High (мобільний performance score 62/100 — під поріг "потребує покращення"). Активує існуючий беклог TECH-002 (cache policy), TECH-003 (image dimensions/CLS — вже частково закрито, CLS=0), TECH-004 (render-blocking).

## 2. Context (grounded — PageSpeed Insights scan, mobile, 16.07.2026)

Джерело: PageSpeed Insights, emulated Moto G Power, Lighthouse 13.4.0, throttled slow 4G, single-page session, знято власником 16.07.2026 21:45 GMT+3.

| Метрика | Значення | Поріг "good" |
|---|---|---|
| Performance score | **62/100** | ≥90 |
| Accessibility | 94/100 | — |
| Best Practices | 100/100 | — |
| SEO | 100/100 | — |
| Agentic web browsing | 2/2 | — |
| First Contentful Paint | **4.1s** | ≤1.8s |
| Largest Contentful Paint | **8.3s** | ≤2.5s |
| Total Blocking Time | 0ms ✅ | ≤200ms |
| Cumulative Layout Shift | 0 ✅ | ≤0.1 |
| Speed Index | **6.2s** | ≤3.4s |

TTFB у LCP breakdown = 0ms — сервер відповідає миттєво, проблема повністю на клієнтському рендері (render-blocking + вага зображень), не на бекенді/хостингу. Це узгоджується з висновком TECH-005-DEEP (сервер/WAF чисті) — інша площина проблеми.

**Знайдені opportunities (мобільний скан):**

1. **Render-blocking requests — очікуване заощадження ~3060ms.** Блокують: `all.min.css` (Font Awesome, 26.2 KiB/1550ms), `bootstrap.css` (30.1 KiB/1350ms), `boostershop-ds.css` (28 KiB/1550ms), `stylesheet.css`, `bs-faq.css`, `content-pages.css`, `booster-typography.css`, `jquery-3.7.1.min.js` (33.6 KiB/1160ms), `ps_live_search.js/css`, `common.js`, `booster-product-polish.js`, `ps-enhanced-measurement.js` + 4 Google Fonts запити (2700ms).
2. **Image weight — очікуване заощадження 773 KiB (найбільший одиночний виграш).** `BS Big logo.png`: віддається 1498×465 (394 KB) при показі 180×56 — 392 KB можна прибрати самим ресайзом. `PokemonC.png`: 1500×585 (196 KB) при показі 184×72 — 193 KB. Плюс не-WebP/AVIF формат для product-карток (mega_gallade_ex, OP-15 box, One-Piece-Photoroom, Mystery box) — ще ~180 KB.
3. **Cache TTL — очікуване заощадження 327 KiB.** Власні статичні файли (`jquery-3.7.1.min.js`, `bootstrap.bundle.min.js`, `common.js`, `ps_live_search.js`, `booster-product-polish.js`, `patch-mobile-search-menu-redesign.js`) і **FontAwesome `fa-solid-900.woff2` (155 KB!)** віддаються з `Cache-Control: None` — при повторному візиті качаються заново. Частина CSS/картинок вже має 7-денний TTL — патерн вибірковий, не глобальний.
4. **Unused CSS/JS.** `bootstrap.css` ~28 KB невикористано з 30 KB, `all.min.css` (FontAwesome) ~25 KB невикористано (повний іконсет під кілька іконок), `boostershop-ds.css` ~21 KB невикористано. jQuery: ~23 KB невикористаного коду з 33 KB.
5. **CSS minify** — 13 KB (`boostershop-ds.css`, `bootstrap.css` не мінімізовані).
6. **Font-display** — `fa-solid-900.woff2` без `font-display: swap`, 50ms втрати (дрібниця, дешевий фікс разом з cache TTL).
7. **Preconnect** — `gstatic.com` preconnect не використовується, зайвий (>4 preconnect — застереження PageSpeed).
8. **LCP breakdown:** "затримка відображення елемента" 2520ms — LCP-елементом визначено текст cookie-банера (`Ми використовуємо файли cookie...`). Це побічний ефект повільного першого рендеру, не сам банер — після виправлення render-blocking LCP-елемент, найімовірніше, зміститься на реальний контент (hero/logo). Перевірити повторним сканом після патчу.

**Побічна знахідка (не з цієї задачі, для довідки):** скрипт `clarity.js` (Microsoft Clarity, scripts.clarity.ms) вже присутній на живому сайті, хоча в роадмапі TECH-018 має статус "Заплановано" — розбіжність, звірити окремо з Notion, не чіпати в цьому патчі.

## 3. Goal
Підняти mobile Performance score з 62 до **≥80** без регресії TBT/CLS (лишити 0ms/0) і без жодного побічного ефекту на checkout, sitemap чи robots.

## 4. What to change (Codex verify проти реальних файлів — тут тільки likely-скоуп)

- **Defer/async нерендер-критичні скрипти** в header/footer шаблоні (не на checkout-critical шляху): `ps_live_search.js`, `common.js`, `ps-enhanced-measurement.js`, `booster-product-polish.js` → `defer` або перенести перед `</body>`.
- **Consolidate/lazy non-critical CSS**: розглянути об'єднання дрібних CSS (`bs-faq.css`, `content-pages.css`, `booster-typography.css` — по 2-4 KB кожен) в один файл АБО завантажувати asynchronously там, де вони не критичні для above-the-fold.
- **Resize + re-export**: `BS Big logo.png` → фактичний розмір показу (180×56, ×2 для retina = 360×112) замість 1498×465; `PokemonC.png` → 184×72 (×2 = 368×144) замість 1500×585.
- **WebP/AVIF конвертація** для product-card зображень (mega_gallade_ex, OP-15 box, One-Piece-Photoroom, Mystery box) з PNG-фолбеком через `<picture>` або перевірити чи OpenCart image resizer вже це вміє і просто не викликається для цих asset-ів.
- **Cache headers** — НОВИЙ ізольований `.htaccess`-блок (не займати sitemap-блок): `Cache-Control`/`Expires` 30d-1y для fingerprinted static JS/CSS/fonts/images, залишити `no-cache` для HTML/cart/checkout/session responses (як і в TECH-002 нотатці).
- **Font-display: swap** для `fa-solid-900.woff2`.
- **Прибрати невикористаний preconnect** до `gstatic.com`.
- (Нижчий пріоритет, окремо оцінити) — **trim FontAwesome icon subset** замість повного `all.min.css`, якщо іконки завантажуються з CDN-набору, а не кастомного спрайту.

## 5. Do not touch
- **`sitemap.xml` / `robots.txt` / canonical / redirects** — поза скоупом, TECH-005-DEEP watch-only статус не чіпати.
- **Існуючий `.htaccess` блок `# BEGIN sitemap-no-compression`** — новий cache-блок додається окремо, без реордеру/злиття зі старим.
- **Checkout/payment/fiscalization/Nova Poshta скрипти** — не defer/async нічого, що checkout-флоу вантажить синхронно, без окремого підтвердження залежності. Скоуп defer-змін — тільки catalog/home/product templates, не checkout.
- **CLS = 0 зараз** — при ресайзі зображень зберегти явні `width`/`height` атрибути (вже є в HTML), не давати layout shift з'явитися.
- **jQuery як бібліотека** — не видаляти, тільки тримати обсяг використання під контролем; live search і, ймовірно, інші віджети на ній залежні — Codex перевірити залежність перед будь-яким трімом.
- **Merchant feed / schema JSON-LD** — poza скоупом.

## 6. Likely files / areas
| Область | Зміна | Впевненість |
|---|---|---|
| theme header/footer twig (спільний, катали/home/product) | defer/reorder script tags, прибрати зайвий preconnect | likely |
| `.htaccess` (новий ізольований блок) | cache headers для static asset patterns | likely — risky zone, branch+PR |
| `image/catalog/.../BS Big logo.png`, `PokemonC.png` | ресайз до реального display-розміру ×2 | likely |
| product card images (кілька файлів) | WebP/AVIF з фолбеком | likely |
| `boostershop-ds.css`, `bootstrap.css` | мінімізація, перевірка unused-правил | conditional — verify blast radius per UI/CSS patch discipline (AGENTS.md) перед видаленням будь-яких CSS-правил |

Codex перевіряє проти реальних файлів на сервері (немає локальної копії theme у цьому репо) — не гадати з `.htaccess`-фрагментів у минулих handoffs.

## 7. Acceptance criteria (measurable)
- Повторний PageSpeed Insights mobile scan для boostershop.website: **Performance ≥ 80** (з 62).
- LCP ≤ 4.0s (перший milestone; ≤2.5s — stretch), FCP ≤ 2.0s, Speed Index ≤ 4.0s.
- TBT лишається 0ms, CLS лишається 0 — жодної регресії.
- `curl -sI` на `jquery-3.7.1.min.js`, `bootstrap.css`, `fa-solid-900.woff2` → `Cache-Control: max-age=2592000` (30d) або довше.
- `BS Big logo.png` + `PokemonC.png` разом: падіння ваги ≥ 500 KB, видимо не розмиті на 3 breakpoints.
- `curl -sI /sitemap-full.xml` та `/robots.txt` — заголовки байт-в-байт ідентичні бейзлайну до патчу (нуль побічного ефекту на sitemap-справу).
- **bs-checkout-smoke повний прогін чистий** (SimpleCheckout, First15, Hutko, Nova Poshta) після деплою.

## 8. QA / smoke test
- bs-checkout-smoke — обов'язково, патч чіпає спільний header/footer.
- Візуальна перевірка: home + 1 категорія + 1 товар на mobile/tablet/desktop — лого і плитки не розмиті після ресайзу/WebP.
- Повторний PageSpeed scan mobile + desktop, порівняти з цим бейзлайном (score 62, скріншот від власника 16.07.2026).
- Перевірити, що Microsoft Clarity (`clarity.js`) не зламався після reorder/defer.

## 9. Rollback note
- `.htaccess`: backup перед правкою (`cp .htaccess .htaccess.bak-tech013-20260716`), новий блок іменований — rollback = видалити блок.
- Twig/theme зміни: стандартний PHP-patch runner (file-exists check, anchor pre-check, авто-backup у `_patch_backups/`, `php -l` gate, self-delete) за AGENTS.md.
- Зображення: оригінали копіюються в `_patch_backups/` перед перезаписом; WebP — адитивний (PNG fallback лишається до підтвердження QA).

## 10. Recommended status after execution
- Після деплою + PageSpeed re-scan + checkout smoke чистий → **TECH-013 = "На перевірці"**.
- **"Готово"** тільки після підтвердження acceptance criteria цифрами і чистого checkout smoke.
- Notion-статус ставить Claude (Codex Notion не чіпає) — оновити `ROADMAP_FLOW` в дашборді останнім кроком патчу.

---
_References: AGENTS.md (risky zones, patch conventions, UI/CSS patch discipline), bs-seo-risk-gate (MEDIUM-HIGH, .htaccess touch), bs-checkout-smoke (обов'язковий після деплою). TECH-005-DEEP (closed, watch-only) — sitemap/robots/.htaccess sitemap-блок не чіпати._
