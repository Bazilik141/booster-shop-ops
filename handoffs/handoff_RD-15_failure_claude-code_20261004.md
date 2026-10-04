# Хендоф — RD-15: сторінка «Оплата не пройшла»

Дата: 2026-10-04
Executor: Claude Code · model=Sonnet · thinking=medium-high — *власник призначив Claude Code явно; Sonnet — маленький обмежений шаблон + рядки мовного файлу, логіку оплати не зачіпає.*

---

## Claude review 2026-10-04 — binding corrections

Where a correction below conflicts with the Ukrainian text, the correction wins. Cross-cutting rules are in the INDEX handoff and are not repeated here.

- **Language file:** `extension/ukrainian/catalog/language/uk-ua/checkout/failure.php` (verified in the 2026-09-24 backup; re-check in the fresh pull).
  - It belongs to the Ukrainian language-pack extension, so a pack reinstall would revert it. Say so in the report.
- **`sprintf`:** `catalog/controller/checkout/failure.php` passes `text_message` through `sprintf()` with the contact URL.
  - The new text must contain no bare `%` (write `%%`).
  - Dropping `%s` is fine: the extra argument is ignored.
- **Breadcrumb:** the last item uses `text_failure` (now «Помилка оплати»). Change it to «Оплата не пройшла» as well. This overrides §5.
- **Contacts:** phone and e-mail match the store settings (`config_telephone`, `config_email`).
- **§8 QA:** drop the "cancel a Hutko payment" step. By code, `hutko.response` always redirects to success (`CHECKOUT-012`). QA here is a direct visit to `index.php?route=checkout/failure`.

---


## 1. Task ID
`RD-15`

## 2. Context
`catalog/view/template/checkout/failure.twig` — стоковий шаблон (`container`, H1, `text_message`, кнопка «← На головну», ⚠️). Дизайн-джерело (погоджено): `RD-14 RD-15 - фінал.html`, стан «RD-15 failure» (`FinalFailure` у `rd14-final.jsx`). Напрямок — «Дві колонки».

## 3. Goal
Спокійна сторінка помилки в стилі DS з новим текстом власника і двома діями: «На головну» і Telegram.

## 4. What to change
**Мовний файл uk-ua (checkout/failure):**
- `heading_title` = `Оплата не пройшла`
- `text_message` (дослівно, два абзаци):
  > Платіж не було завершено. Так буває, коли банк відхилив операцію або сторінку оплати закрили трохи раніше, ніж потрібно.
  >
  > Якщо кошти списались або ви не впевнені, чи створено замовлення, напишіть нам у Telegram, на електронну пошту або подзвоніть за номером +380636743252.
  Посилання всередині тексту: «Telegram» → `https://telegram.me/boostershop_tcg` (`target="_blank" rel="noopener"`), «електронну пошту» → `mailto:helpbs@boostershop.website`, номер → `tel:+380636743252` (`white-space:nowrap`). Адреса пошти в тексті не показується.
- Перевірити, чи `heading_title` використовується в `<title>` і хлібних крихтах — нове значення має з’явитись і там.

**Шаблон + CSS:**
- Картка 600 px по центру, фон `--bs-paper`, рамка `--bs-line`, радіус `--bs-r-lg`, **верхня смуга 4 px `--bs-warning-line`**.
- Іконка: SVG «коло зі знаком оклику» 24 px у колі 48 px, фон `--bs-warning-bg`, рамка `--bs-warning-line`, колір `--bs-warning-fg`. ⚠️ прибрати.
- ≥768: grid `48px | 1fr`, gap 20 px (іконка ліворуч від H1 і тексту). <768: іконка над H1.
- H1 22 px, 800. Текст — `--bs-ink-2`, line-height 1.65.
- Дії (під текстом, ліворуч на ≥768, на всю ширину стовпчиком на моб.): «На головну» (без «←») і «напишіть у Telegram» — обидві secondary, у кнопки Telegram іконка `#229ED9`, текст `--bs-ink`.
- Хлібні крихти — таблетки DS, останній пункт «Оплата не пройшла».

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, редиректи, canonical, `.htaccess`
- Чекаут, оплата, `hutko.response` і маршрутизація на success/failure (CHECKOUT-012), фіскалізація
- Очищення кошика після переходу до оплати
- Інші мовні рядки, крім `heading_title` і `text_message` для failure
- Merchant-фід, schema
- `success.twig` — пакет RD-14

## 6. Likely files / areas
- `catalog/view/template/checkout/failure.twig` (evidence: `live-snapshots/20261004_rd-ux-batch-live2/catalog/view/template/checkout/failure.twig`)
- `catalog/language/uk-ua/checkout/failure.php` — **шлях орієнтовний, виконавець звіряє з деревом**; якщо є en-gb/ru — не чіпати
- `boostershop-ds.css` — нова секція `#checkout-failure`

## 7. Acceptance criteria
1. `/index.php?route=checkout/failure` — H1 «Оплата не пройшла», текст дослівно як у §4.
2. Три посилання в тексті працюють (Telegram, mailto, tel).
3. Немає ⚠️ і «←»; іконка — SVG з `aria-hidden`.
4. На ≥768 іконка ліворуч від тексту; на 390 — над H1, кнопки на всю ширину.
5. Кнопки: «На головну» → головна, «напишіть у Telegram» → Telegram у новій вкладці.
6. Консоль без нових помилок.

## 8. QA / smoke test
- Відкрити `checkout/failure` напряму (390 / 768 / 1440).
- Скасувати оплату на сторінці Hutko тестовим замовленням → потрапити на failure (якщо CHECKOUT-012 це дозволяє; інакше — лише прямий захід). Сума/замовлення не змінюються.
- Шаблон суміжний з checkout — пройти кроки `bs-checkout-smoke`, що стосуються неуспішної оплати.

## 9. Rollback note
Бекап робить сам раннер у `_patch_backups/<PATCH-ID>-<timestamp>/` (конвенції AGENTS.md); він має містити `failure.twig`, мовного файлу і `boostershop-ds.css`. Відкат — залити бекап, скинути кеш теми.
Тригер: PHP-помилка на failure, зламаний редирект з Hutko, некоректний текст.

## 10. Recommended status after execution
`In progress` (до QA власника) → `Done` (пише Claude chat) після пп. 1–5.

---

**Доставка:** один файл у `patches/` (тільки RD-15). Власник завантажує в `~/public_html` і запускає `php <patch>.php`. Виконавець нічого не комітить, не пушить і не деплоїть.
