# Booster Shop — Redesign Roadmap (RD-XX) · План реструктуризації
_Дата: 30.05.2026 · scope: тільки visual / UX-UI / layout / states / responsiveness · джерело істини дизайну: `audit.md` + `HANDOFF.md` + `boostershop-ds.css` / `tokens.css` (Claude Design, 21.05.2026)_

> Це **план для затвердження**. Notion ще не змінено. Після твого OK я застосую зміни в roadmap.

---

## 1. Короткий висновок

Поточний редизайн (серія `R-01…R-15`) технічно частково виконаний, але **візуально не дотягнутий до Claude Design reference**, і саме головне — **серія непослідовна**: змішані `R-XX`, `UX-XXX`, `MKT`, дублі (R-03/UX-004, R-04/R-08/UX-006, R-13/UX-013, R-14/UX-014), плюс під-тікети-латки (R-11-UI-1/-2, R-11b). Це заважає вести єдину логіку.

Рішення: завести **чисту серію `RD-01…RD-21`**, де кожна сторінка/компонент = окремий тікет з ціллю «візуальний паритет з reference + всі стани + desktop/mobile», а старі redesign-тікети — **архівувати як superseded** (з посиланням на новий RD). SEO/Merchant/checkout-логіка/контентні тексти у scope **не входять** і лишаються окремими тікетами без змін.

Тип задачі: roadmap restructuring (meta).
Хто виконує: Claude (структура + ТЗ) → Codex (імплементація) → Owner (live-QA).
Статус: чекає рішення власника по 4 пунктах (розділ 6), далі — оновлення Notion.

---

## 2. Що знайдено (grounded, не припущення)

**DS вже встановлено:** `boostershop-ds.css` (48 KB, оновл. 29.05) підключений у `header.twig`, `<body class="bs">`, Manrope активний. Токени резолвляться. База для паритету є — проблема не в інфраструктурі, а в неповному застосуванні компонентів.

**Щільність `bs-` класів у live-шаблонах (індикатор глибини редизайну):**

| Зона | bs-класів | Стан vs reference |
|---|---|---|
| `product/category.twig` | 233 | Зроблено, але власник бачить візуальний розрив → паритет-ревізія |
| `product/product.twig` | 63 | Частково; потребує паритету (gallery, title-block, sticky ATC mobile) |
| `product/thumb.twig` (**картка товару**) | **7** | **Майже не зроблено** — лише бейджі R-08. Немає DS-картки (title min-height, price-hierarchy, full-width green CTA, стани). **Найбільший розрив.** |
| `common/home.twig` | 19 | Тайли є (R-06), легкий; паритет + trust-strip |
| `common/header/footer` | 20 / 9 | Зроблено (R-05); дрібний паритет |
| `checkout/cart.twig` | 8 | **Не редизайнено** (R-12 pending) |
| `common/cart.twig` (**mini-cart**) | 8 | **Не редизайнено**, dropdown а не drawer (R-13 pending) |
| `checkout/checkout.twig` | 37 | Частково; reskin pending (R-14), HIGH-RISK |
| `checkout/success` / `failure` | 30 / 26 | Свіжо зроблено (R-11/R-11b, 29.05); паритет-ревізія |
| `account/*` (account, login, register, address, order_list, order_info) | **0–3** | **Не редизайнено взагалі** (R-15 pending) |
| `information/information.twig` | 25 | Інфраструктура + тексти зроблені (R-09/R-10); потрібен лише layout-паритет |

**Висновок по покриттю:** найбільші діри — **картка товару, cart, mini-cart, увесь account-флоу, checkout**. «Зроблені» зони (category, home, product, header/footer, content) потребують не переробки, а **паритет-ревізії під reference** (radius-шкала 6/10/14, прибрати залишки gold-foam, зелений лише `#16A34A` на purchase-actions, price-hierarchy).

---

## 3. Нова серія RD-XX (запропонована структура)

Логіка послідовності: **фундамент → переюзні компоненти → каталог-сторінки → cart/checkout-флоу → account → контент**. Усе desktop+mobile, якщо не вказано інше.

### Фундамент
| RD | Назва | Опис (ціль = паритет з reference) | Зони | D/M | Замінює |
|---|---|---|---|---|---|
| **RD-01** | Design System reconciliation | Звести deployed `boostershop-ds.css` до `tokens.css`: radius-шкала 6/10/14, shadow sm/md, z-індекси, єдиний green `#16A34A`/hover `#15803D` **тільки на purchase**, прибрати залишки gold-foam як декор-фон. Базовий чеклист «де DS порушено». | global CSS, header | Both | UX-034, R-01 |

### Переюзні компоненти
| RD | Назва | Опис | Зони | D/M | Замінює |
|---|---|---|---|---|---|
| **RD-02** | Header + utility bar parity | Білий хедер, компактний cart-pill (іконка+к-сть, ціна другорядно), breadcrumbs нейтральні `#6B7280`/`#111827`, search-поле DS. | `common/header.twig` | Both | R-05 (header) |
| **RD-03** | Footer parity | Каталог / Інформація / Покупцю / Контакти, DS-типографіка, Telegram-лінки. | `common/footer.twig` | Both | R-05 (footer) |
| **RD-04** | Product card system — всі стани | Повна DS-картка в `thumb.twig`: 1:1 фото (+placeholder `#F7F7F5`), title 14px/600 min-height 2 рядки ellipsis, метадані-рядок, price-row (нова `#B91C1C` + стара `#6B7280` strikethrough), full-width green «Купити». Стани: default / sale (−NN% pill TR) / out-of-stock / preorder (`#3B82F6`) / low-pull / no-photo. | `product/thumb.twig` (+CSS) | Both | **UX-006, R-04, R-08** |
| **RD-05** | FAQ accordion parity | Variant B (soft cards) + chevron, активна картка blue border+chevron, білий фон завжди, keyboard a11y. | усі сторінки з FAQ | Both | R-07, MKT-006 |
| **RD-06** | Empty-state компонент | Єдиний `<EmptyState>` (іконка+текст+CTA): cart, search, категорія з 0 товарів. | shared | Both | UX-037 (новий) |

### Каталог-сторінки
| RD | Назва | Опис | Зони | D/M | Замінює |
|---|---|---|---|---|---|
| **RD-07** | Category page parity | Header-card (4px брендова смуга, title+count+tagline+sort у hero-row), toolbar-row (chips + active filters), minimal `<select>` для sort/limit, sidebar-фільтр без gold-header. | `product/category.twig` | Both | R-03, UX-004 |
| **RD-08** | Subcategory / leaf page | Sibling-chips для leaf-підкатегорій (blue-soft active `#E8EEFB`/`#1E3A8A`), active-filter chips, mobile → стиснений wrap/vertical list. | `category.twig` (leaf-гілка) | Both | R-03 (частина) |
| **RD-09** | Home page parity | Category tiles Variant D (4px смуга, лого+опис горизонтально), trust-strip (4 факти), порядок промо-блоків, related-carousel зі swipe-progress. | `common/home.twig` | Both | R-06 |
| **RD-10** | Product page parity | Gallery, title-block без артикулу, price-block, специфікації, FAQ-chevron, Reviews-tab = Telegram+OLX cards, ПУМБ «оплата частинами» placeholder, **sticky add-to-cart (mobile)**. | `product/product.twig` | Both (ATC=mobile) | R-02, UX-007(prod), UX-038 |

### Cart / Mini-cart / Checkout
| RD | Назва | Опис | Зони | D/M | Замінює |
|---|---|---|---|---|---|
| **RD-11** | Cart page redesign | Рядки товарів, qty-контрол 44×44, summary, shipping-рядок («За тарифами НП» <₴1500 / «За наш кошт» ≥₴1500), empty-state, CTA green. **Без зміни price/checkout-логіки.** | `checkout/cart.twig` | Both | R-12, UX-026 |
| **RD-12** | Mini-cart drawer | Right-side drawer (380px desktop / 100% mobile) замість dropdown: header «Кошик · N», thumb 56×56, qty −1+, subtotal sticky, 2 CTA («Продовжити» + green «Оформити»), empty-state, green `#16A34A`. | `common/cart.twig` (+JS) | Both | R-13, UX-013 |
| **RD-13** | Checkout reskin ⚠️ HIGH-RISK | Секції-картки Доставка/Оплата/Контакт/Замовлення, order-summary sticky (desktop) / collapsible (mobile), inline-помилки, **одна** primary-green CTA. Тільки markup/CSS — **payment/fiscalization/Hutko/Checkbox НЕ чіпати**. Обов'язковий smoke-test. | `checkout/checkout.twig` | Both | R-14, UX-014 |
| **RD-14** | Checkout success parity | DS-паритет success-сторінки, прибрати прилипання блоку до breadcrumbs, OC4-структура. | `checkout/success.twig` | Both | R-11, R-11-UI-2 |
| **RD-15** | Checkout failure parity | DS-паритет failure, центрування, OC4-структура. | `checkout/failure.twig` | Both | R-11b, R-11-UI-1 |

### Personal cabinet (account)
| RD | Назва | Опис | Зони | D/M | Замінює |
|---|---|---|---|---|---|
| **RD-16** | Account hub + personal data | DS-shell кабінету (sidebar/nav), сторінка особистих даних (edit), card-форма, labels над input. | `account/account.twig`, `account/edit` | Both | R-15 (частина) |
| **RD-17** | Addresses (list + form) | Список адрес + add/edit як компактна card-форма (порядок полів за UX-021), NP-відділення/поштомат/адресна. **Backend save / hidden postcode НЕ чіпати.** | `account/address*` | Both | R-15, UX-021 (visual) |
| **RD-18** | Orders (list + detail) | All orders list (картки/таблиця) + сторінка конкретного замовлення (позиції, статуси, сума). | `account/order_list.twig`, `order_info.twig` | Both | R-15 (частина) |
| **RD-19** | Authorization (login) | DS-паритет login + forgotten, card-форма, success-copy → Telegram support. | `account/login.twig`, `forgotten.twig` | Both | R-15 (частина) |
| **RD-20** | Registration (step 1 + step 2) | Перша сторінка реєстрації (контакт+phone UX) + друга (адреса/доставка), First15 у сесії — поведінка незмінна, лише візуал. | `account/register.twig`, address-flow | Both | R-15, UX-018, UX-021 |

### Контент
| RD | Назва | Опис | Зони | D/M | Замінює |
|---|---|---|---|---|---|
| **RD-21** | Content pages visual parity | Layout/TOC/card-паритет 5 сторінок: Гарантія автентичності, Про нас, Оплата і доставка, Обмін і повернення, Публічна оферта. **Тільки верстка — тексти НЕ чіпати.** | `information/information.twig` | Both | R-09 (visual) |

---

## 4. Залежності (порядок виконання)

1. **RD-01** (DS reconciliation) — блокує все інше (єдина база токенів).
2. **RD-04** (картка товару) — блокує RD-07/08/09/10 (картка переюзна).
3. **RD-05/RD-06** (FAQ, EmptyState) — переюзні, рано.
4. RD-07 → RD-08 → RD-09 → RD-10 (каталог).
5. **RD-11** (cart) → **RD-12** (mini-cart, ділить патерни) → **RD-13** (checkout, HIGH-RISK, після стабільного cart).
6. RD-14/RD-15 (success/failure) — паралельно, не блокують.
7. **RD-16** (account shell) → RD-17/18/19/20 (ділять shell).
8. RD-21 (content) — паралельно будь-коли після RD-01.

Рекомендований лінійний порядок: RD-01 → 04 → 05 → 06 → 02 → 03 → 07 → 08 → 09 → 10 → 11 → 12 → 14 → 15 → 16 → 17 → 18 → 19 → 20 → 21 → **13** (checkout останнім, окремий smoke-test).

---

## 5. Міграційна мапа старих тікетів

### Замінюються новими RD → **архівувати як Superseded** (mark, не видаляти — зберегти історію)
| Старий | Статус зараз | → Новий RD |
|---|---|---|
| UX-034 Design System | — | RD-01 |
| R-01 (body/CSS bootstrap) | Done | RD-01 (поглинуто) |
| R-05 Header+Footer | Done | RD-02 + RD-03 |
| UX-006 Product cards | — | RD-04 |
| R-04 Compact card | Done(redef) | RD-04 |
| R-08 Card badges | Done | RD-04 |
| R-07 FAQ accordion | Done | RD-05 |
| MKT-006 FAQ variant | — | RD-05 |
| UX-037 Empty states | (recommended) | RD-06 |
| R-03 Category header | Done | RD-07 + RD-08 |
| UX-004 Subcat tiles | Done(via R-03) | RD-07 |
| R-02 Special/FAQ reorder | Done | RD-10 (+RD-05) |
| R-06 Home tiles | Done | RD-09 |
| UX-007 Cart/checkout flow | — | RD-10 / RD-11 (split) |
| UX-038 Sticky ATC | (recommended) | RD-10 |
| R-12 Cart page | Pending | RD-11 |
| UX-026 Qty on cart | — | RD-11 |
| R-13 Mini-cart drawer | Pending | RD-12 |
| UX-013 Mini-cart colors | Deferred | RD-12 |
| R-14 Checkout reskin | Pending | RD-13 |
| UX-014 Checkout polish | — | RD-13 |
| R-11 Success | Done | RD-14 |
| R-11-UI-2 Success fix | Done | RD-14 |
| R-11b Failure | Done | RD-15 |
| R-11-UI-1 Failure fix | Done | RD-15 |
| R-15 Account pages | Pending | RD-16/17/18/19/20 |
| UX-018 Register phone | Done | RD-20 |
| UX-021 Address+register | Done | RD-17 + RD-20 (visual частина) |
| R-09 Content infra | Done | RD-21 (visual) |

### Лишаються без змін (поза scope редизайну — **не чіпати**)
- **SEO/Merchant/Tech:** TECH-005, TECH-005-DEEP, TECH-007, TECH-008, TECH-009, TECH-013, TECH-015, TECH-017, TECH-024.
- **Функціональні/контентні:** R-08.5 (cart perf audit — як контекст для RD-11/12), R-13.5 (Nova Poshta модуль — функціонал, контекст для RD-13), R-10 (тексти контент-сторінок — done, content), LEGAL-002 (оферта/повернення — текст+юр.), BRAND-OUTLET-001 (репозиціонування товару — content).
- **UX-009 Search UX** — функціональна; візуальну частину можна влити в RD-02 (рішення нижче).

---

## 6. Рішення власника (потрібні до оновлення Notion)

1. **Старі redesign-тікети:** архівувати (позначити «Superseded by RD-XX», лишити в базі) чи **видалити** повністю?
2. **UX-009 Search:** окремий функціональний тікет чи влити візуал у RD-02?
3. **Нумерація:** підтверджуєш `RD-01…RD-21` як єдину серію (старі R-/UX redesign ID виводимо з обігу)?
4. **Послідовність/пріоритети:** ок з порядком розділу 4 (RD-01 фундамент → … → RD-13 checkout останнім)?

Після відповідей: створю RD-01…RD-21 у Notion з повними полями (Roadmap ID, Page/Zone, Priority, Status, Task Type, Order), проставлю «Superseded» на старих і збережу єдину логіку нумерації.

---

## 7. Scope-guard (нагадування для всіх RD)
- ✅ тільки visual / layout / components / states / responsiveness / DS-паритет.
- ❌ НЕ міняти: product attributes, описи товарів, тексти контент-сторінок, SEO-контент.
- ❌ НЕ міняти business-логіку, крім випадків, де це прямо потрібно для UX-імплементації.
- ⚠️ RD-11/12/13 + account-форми: будь-який дотик до cart/checkout/payment/fiscalization/NP/save-логіки → назвати ризик + smoke-test, патч тільки markup/CSS.
