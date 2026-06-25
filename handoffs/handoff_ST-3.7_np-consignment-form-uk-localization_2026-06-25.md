# Codex Handoff — ST-3.7: UK-локалізація форми накладної НП (admin)

Date: 2026-06-25
Author: Claude (strategic assistant)
Roadmap: **ST-3.7 — українська локалізація екрана «Форма накладної»** (admin Pinta NP). Low-risk, admin display only.
Platform: OpenCart 4.1.0.3. Module: Pinta NP v1.4.0. Source: `backup-6.23.2026_11-21-43`.

---

## 1. Task ID
**ST-3.7 — форма створення ТТН (`create_internet_document`) має відображатися українською.** Зараз тіло форми англійською ("Consignment note form", "Type of payer", "Sender counterparty", "Seats" тощо), хоча адмінка в UK і `uk-ua` мова модуля існує.

## 2. Context (verified)
- Шаблон `extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig` (1323 рядки) **повністю на мовних ключах** (`{{ entry_type_of_payer }}`, `{{ text_general }}`, `{{ text_internet_document_form }}`, …) — хардкоду англійською немає.
- `admin/language/uk-ua/shipping/pinta_nova_poshta.php` (184 рядки) має ЧАСТИНУ ключів українською (напр. `entry_type_of_payer`='Платник за доставку', `entry_cargo_type`='Тип вантажу', `text_internet_document_form`='Форма накладної', `text_special_cargo`='Спеціальний вантаж', `backward_delivery_payer_type_*`).
- **Парадокс:** breadcrumb «Форма накладної» = UK (ключ є), а тіло форми = EN. Отже або (a) контролер `createInternetDocument` вантажить `en-gb` для цього екрана, або (b) у `uk-ua` бракує багатьох ключів, що використовує twig, і йде fallback на en-gb. Codex має визначити, що саме.

## 3. Goal
Форма «Форма накладної» повністю українською: жодних англійських лейблів і жодних «сирих» ключів.

## 4. What to change (Codex verify against live)
1. **Діагностика:** чому форма рендериться EN при UK-адмінці. Перевірити мовне завантаження у контролері `createInternetDocument` (`internet_document.php`) — який language-файл і код мови вантажиться; чи всі ключі twig присутні в `uk-ua`.
2. **Фікс завантаження** (якщо вантажиться en-gb): забезпечити завантаження `uk-ua` за активною мовою адмінки (як на інших екранах модуля).
3. **Доповнити `uk-ua`** `shipping/pinta_nova_poshta.php`: додати ВСІ ключі, що використовує `create_internet_document.twig`, яких бракує (інвентаризувати з twig). Існуючі UK-ключі не чіпати. en-gb/ru-ru не чіпати.
4. Переклади — за глосарієм нижче (мапити кожен twig-ключ на відповідний UK-термін; для опцій select — теж).

## 5. UK-глосарій (label → переклад)
| English (на екрані) | Українською |
|---|---|
| Consignment note form | Форма накладної |
| General | Загальне |
| Cargo | Вантаж |
| Type of payer | Платник за доставку |
| Payment form | Форма оплати |
| Backward delivery cargo | Зворотна доставка |
| Backward delivery payer | Платник зворотної доставки |
| Backward delivery string | Сума зворотної доставки |
| Internal order number | Внутрішній № замовлення |
| Additional Information | Додаткова інформація |
| Cargo type | Тип вантажу |
| Shipping date | Дата відправлення |
| Cost | Оголошена вартість |
| Description | Опис |
| Sender | Відправник |
| Recipient | Отримувач |
| Sender counterparty | Контрагент-відправник |
| Firstname / Lastname / Middlename | Імʼя / Прізвище / По батькові |
| Phone | Телефон |
| Opencart address | Адреса з OpenCart |
| Shipping to | Тип доставки |
| Area | Область |
| City | Місто |
| Warehouse address | Відділення |
| Seats | Місця |
| Pack | Упаковка |
| Special cargo | Спеціальний вантаж |
| Width / Length / Height / Weight | Ширина / Довжина / Висота / Вага |
| Action | Дія |
| (опції) Cash | Готівка |
| (опції) Recipient / Sender | Одержувач / Відправник |
| (опції) Money / Disabled / Documents | Гроші / Вимкнено / Документи |
| (опції) Warehouse / Doors | Відділення / Адреса (двері) |
| (опції) Private person | Приватна особа |

(Терміни вирівняні з уже наявними UK-ключами модуля. За потреби Codex узгоджує однаковість.)

## 6. Do not touch
- Логіка форми/ТТН/payment/НП-API; ТІЛЬКИ текстові ключі/мовне завантаження.
- `en-gb` / `ru-ru` мовні файли (не чіпати, якщо не потрібно для fallback-фіксу).
- DB, checkout, totals, схема.
- ST-3.5/-3.6 зміни.

## 7. Acceptance criteria
- На `…/createinternetdocument&order_id=192` форма рендериться **повністю українською** (заголовок, секції General/Cargo/Sender/Recipient/Seats, усі лейбли й опції select).
- Немає англійських лейблів і немає «сирих» ключів (типу `entry_xxx`).
- `php -l` clean; діф лише в `admin/language/uk-ua/shipping/pinta_nova_poshta.php` (+ за потреби 1 рядок мовного завантаження в контролері).

## 8. QA / smoke
Admin-display only → без `bs-checkout-smoke`. Owner: відкрити форму ТТН на #192 → переконатися, що все українською, форма функціонує як раніше (поля/опції на місці).

## 9. Rollback
Бекап змінених файлів у `_patch_backups/...`; відкат = відновити мовний файл (+ контролер, якщо чіпали).

## 10. Recommended status after execution
Патч + owner візуальна перевірка → ST-3.7 `Done`. Останній крок: оновити `ROADMAP_FLOW`. Notion (page_id створить Claude) оновлює Claude.
