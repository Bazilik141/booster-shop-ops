# Codex Handoff — ST-3.5-3: createInternetDocument crash (undefined $PintaCounterparty / $phone)

Date: 2026-06-25
Author: Claude (strategic assistant)
Roadmap: **ST-3.5** «Кнопка ТТН в адмінці» → sub **ST-3.5-3** (створення ТТН падає). Слідує за ST-3.5-1 (anchor) + ST-3.5-2 (view/button), які вже на live.
Platform: OpenCart 4.1.0.3. Module: Pinta Webware "Nova Poshta" v1.4.0.
Source verified: `backup-6.23.2026_11-21-43_boosters` (файл не чіпався патчами ST-3.5 → backup == live).

---

## 1. Task ID
**ST-3.5-3 — admin TTN creation fails for non-PrivatePerson (ФОП) sender.**
Кнопка ТТН і форма «Форма накладної» вже працюють і коректно префіняться (owner QA #192). На **збереженні** форми createInternetDocument падає фаталом — ТТН не створюється.

## 2. Context (verified)
Помилка на live (order #192, Warehouse, sender = ФОП, recipient = Приватна особа, COD):
```
Warning: Undefined variable $PintaCounterparty in .../admin/controller/shipping/internet_document.php on line 530
Error: Call to a member function saveContactPerson() on null  (line 530)
  ← prepareSender()  ← createInternetDocument() (line ~88)  ← pinta_nova_poshta.php:427
```
**Root cause (звірено з бекапом):**
- `$PintaCounterparty` інстанціюється локально в `createInternetDocument()` (рядок 56) та в методах 922/950/1149 — але **НЕ** в `prepareSender()` (498) і `prepareRecipient()` (550), хоча вони його використовують у `else`-гілці (рядки 530, 589: `$PintaCounterparty->saveContactPerson(...)`). PHP-методи не успадковують локальні змінні → `$PintaCounterparty` = undefined → null → fatal.
- Додатково в `prepareSender()` `else`-гілка передає `$phone`, якого **немає в сигнатурі** методу (`$counterparty_ref, $firstname, $middlename, $lastname`). У `prepareRecipient()` `$phone` у сигнатурі є.
- Спрацьовує лише коли counterparty_type ≠ `PrivatePerson` (ФОП-відправник). PrivatePerson-гілки (`searchContactPerson` / `savePrivateRecipient`) — робочі, не чіпати.

## 3. Goal
createInternetDocument успішно створює ТТН для Warehouse-доставки з ФОП-відправником + Приватна особа-отримувач: реальний int_doc_number, без PHP-помилок, у картці замовлення зʼявляється блок ТТН з лінками друку.

## 4. What to change (Codex must verify against live code)
Файл: `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`
1. **`prepareSender()` (~498):** на початку методу додати інстанс (як у рядку 56):
   `$PintaCounterparty = new \Opencart\System\Library\Pintanovaposhta\PintaCounterparty();`
   (підтвердити, що конструктор без обовʼязкових аргументів — рядок 56 створює без аргументів).
2. **`prepareSender()` сигнатура:** додати параметр `$phone` і передавати його з виклику в `createInternetDocument()` (~рядок 84: зараз передається 4 аргументи — додати `$formdata['sender_phone']`). Лишити наявне присвоєння `$this->sender_phone = $formdata['sender_phone'];`.
3. **`prepareRecipient()` (~550):** на початку методу додати той самий інстанс `$PintaCounterparty` ( `$phone` уже у сигнатурі).
Мінімальний діф; тільки цей файл (+ одна правка місця виклику для sender-phone).

## 5. Do not touch
- PrivatePerson-гілки `prepareSender`/`prepareRecipient` (працюють).
- Бібліотека `system/library/Pintanovaposhta/PintaCounterparty*` — не рефакторити, лише використовувати.
- Checkout/payment/Hutko/Checkbox/фіскалізація/totals/COD-логіка; NP `cost => 0` костиль.
- DB-схема; CRM/Apps Script payload; формат адреси замовлення.
- `sitemap.xml`/`robots.txt`/canonical/redirects/`.htaccess`/Merchant/schema.
- Anchor-фікс ST-3.5-1 (payment controller) і view-фікс ST-3.5-2 (suffix) — не відкочувати.

## 6. Likely files / areas
- `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`: `prepareSender` (~498), `prepareRecipient` (~550), виклик `prepareSender` у `createInternetDocument` (~84).
- (read-only ref) `system/library/Pintanovaposhta/PintaCounterparty` — метод `saveContactPerson($ref,$firstname,$middlename,$lastname,$phone)`.

## 7. Acceptance criteria (measurable)
1. На #192 (Warehouse, ФОП-sender, COD) сабміт «Форми накладної» → **немає** `Undefined variable $PintaCounterparty` і `$phone`, **немає** `saveContactPerson() on null`.
2. ТТН створюється: повертається int_doc_number; у картці замовлення зʼявляється блок ТТН з лінками друку (printDocument/marking).
3. Контактна особа відправника створюється з коректним **phone** (не null).
4. Recipient = Приватна особа (PrivatePerson) гілка не зачеплена; відділення/поштомат/адресна — без регресій.
5. `php -l` clean; діф лише в `internet_document.php`.

## 8. QA / smoke
Admin-only, frontend-чекаут не зачіпається → повний `bs-checkout-smoke` НЕ потрібен.
- **Owner (needs owner check):** створити **одну реальну ТТН** на тест-замовленні (#192 або нове Warehouse+ФОП+COD) → перевірити, що накладна зʼявилась у кабінеті НП з правильними відправник(ФОП)/отримувач/відділення/COD/вага; лінк друку відкривається.
  ⚠ Це створює **реальну накладну** в НП. Якщо тест — видалити/скасувати її в кабінеті НП після перевірки.
- `storage/logs` без нових PHP-помилок; консоль чиста.

## 9. Rollback
- Бекап перед записом: `_patch_backups/st3.5-3-ttn-create-<ts>/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`.
- Відкат = відновити цей єдиний файл. DB-міграцій нема. Frontend не зачеплено.

## 10. Recommended status after execution
- Патч + owner real-ТТН QA passed → **ST-3.5-3 done**; а оскільки -1/-2 вже ОК → **ST-3.5 `Done`** (кнопка + форма + створення ТТН працюють).
- Останній крок Required changes: оновити `ROADMAP_FLOW` (ST-3.5 + підзадачі) у `dashboard/booster-dashboard.html`. Notion (`page_id 3896bf20-bdb4-8174-8a50-fe3d19f8c9ba`) оновлює Claude.

### Risk
Low frontend (admin-only display/create). Зона уваги — це створення реальної накладної через NP API (зовнішня дія) → QA робить owner на одній тест-ТТН.
