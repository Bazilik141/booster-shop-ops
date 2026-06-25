# Codex Handoff — ST-3.6: COD «Контроль оплати» (AfterpaymentOnGoodsCost) для ФОП-відправника

Date: 2026-06-25 (Phase 1 — implementation; spec підтверджено офіційною докою НП)
Author: Claude (strategic assistant)
Roadmap: **ST-3.6 — Зворотна доставка грошей COD.** ⚠ **HIGH-RISK — реальні гроші.**
Combine: **зробити ОДНИМ патчем разом із ST-3.7** (UK-локалізація) — обидва правлять той самий файл `internet_document.php`. Standalone ST-3.7 патч окремо НЕ деплоїти.
Platform: OpenCart 4.1.0.3. Module: Pinta NP v1.4.0. Source: `backup-6.23.2026_11-21-43`.

---

## 1. Task ID
**ST-3.6 — додати NP-послугу «Контроль оплати» (`AfterpaymentOnGoodsCost`) для ФОП/юр-відправника** при зворотній доставці грошей; для приватної особи лишити чинну «Післяплату» (`BackwardDeliveryData/Money`).

## 2. Context
- ST-3.5-3 усунув креш; на сабміті NP API повертає: «Скористайтесь послугою "Контроль оплати" для бізнес-клієнтів. "Післяплата" доступна тільки для приватних осіб відправників.»
- Модуль будує лише `BackwardDeliveryData {CargoType:Money, PayerType:Recipient, RedeliveryString:cost}` (`internet_document.php` payload-метод ~445–450; backward-поля ~348). `AfterpaymentOnGoodsCost`/«Контроль оплати» не будує ніде.

## 3. NP API spec — CONFIRMED (офіційна дока, файли в репо/uploads 2026-06-25)
- **«Контроль оплати» = параметр `AfterpaymentOnGoodsCost`** у `InternetDocumentGeneral/save` → `methodProperties`.
  - save: «створення ЕН із послугою «Контроль оплати». Приклад: `"AfterpaymentOnGoodsCost": "1005"`» (скаляр-рядок = сума).
  - getStatusDocuments: `AfterpaymentOnGoodsCost string[36] — Контроль оплати`.
- За правилом НП: «Післяплата» (`BackwardDeliveryData/CargoType=Money`) — ТІЛЬКИ приватна особа; бізнес → `AfterpaymentOnGoodsCost`.
- `AfterpaymentOnGoodsCostInfo` (масив) існує (коди помилок 20000200040), але **прикладу нема → використовувати скалярний `AfterpaymentOnGoodsCost`**.
- Релевантні коди помилок НП (для обробки): 20000200031–20000200040 (Afterpayment… disabled/invalid/too high/unavailable/must be filled).

## 4. What to change (Codex verify against live)
Файл: `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`
0. **Префіл за способом оплати (виправити інверсію, ~348–355):** ключ = payment method замовлення. Якщо `paymentCode` ∈ {`pinta_nova_poshta_cod`, `pinta_nova_poshta_cod.pinta_nova_poshta_cod`} (клієнт обрав післяплату) → `backward_delivery_cargo_type='Money'`, `payer_type='Recipient'`, `cargo_string=$cost` (сума замовлення). Інакше (картка / реквізити — передоплата) → `'Disabled'`. Фактично — прибрати `!` з поточної умови (поміняти гілки місцями).
1. У `prepareSender()` після завантаження `$counterparty` зберегти тип на контролер:
   `$this->sender_counterparty_type = $counterparty['counterparty_type'];`
2. У методі складання payload (де зараз будується `$internet_document_properties['BackwardDeliveryData']`, ~445–450), коли зворотна доставка грошей увімкнена (`$backward_delivery_cargo_type !== 'Disabled'` і це Money):
   - **PrivatePerson-відправник** → лишити чинний `BackwardDeliveryData` (Післяплата).
   - **Бізнес/ФОП-відправник** (`sender_counterparty_type !== 'PrivatePerson'`) → **НЕ додавати** `BackwardDeliveryData`, натомість:
     `$internet_document_properties['AfterpaymentOnGoodsCost'] = (string)$backward_delivery_cargo_string;`
3. Сума = поточний `$backward_delivery_cargo_string` (= `$cost`). Підтвердити, що це сума замовлення.
4. Бажано: обробити коди помилок 20000200031–40 у `validatePintaNovaPoshtaApiResult` для зрозумілого повідомлення (опційно).
5. **Combine ST-3.7:** у цьому ж патчі застосувати UK-overlay з `patches/ST-3.7_np-consignment-form-uk-localization_20260625.php` (той самий файл, інший anchor — послідовні str_replace, без колізій; постчек на обидва маркери).

## 5. Do not touch
- (Інверсію COD→backward тепер **ВИПРАВЛЯЄМО** — §4 крок 0; owner-рішення 2026-06-25: ключ = спосіб оплати клієнта.)
- Checkout/payment/Hutko/Checkbox/фіскалізація/totals; NP `cost=>0`; PrivatePerson-гілка; DB; CRM payload; sitemap/robots/тощо; ST-3.5 зміни.

## 6. Likely files
- `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php` (`prepareSender` ~498; payload-метод ~416–450; `$cost`/`$backward_delivery_cargo_string`).
- Бібліотека `…Pintanovaposhta/PintaInternetDocument` (read-only — переконатися, що `AfterpaymentOnGoodsCost` проходить у payload як є).

## 7. Acceptance criteria
1. **ФОП-відправник + Money** → ТТН створюється **без** помилки «Післяплата доступна тільки для приватних осіб»; у payload іде `AfterpaymentOnGoodsCost=<сума>`, `BackwardDeliveryData` відсутній; у кабінеті НП на ЕН видно «Контроль оплати» на правильну суму.
2. **PrivatePerson + Money** → лишається `BackwardDeliveryData/Money`, працює як раніше.
3. **Disabled** → ні `BackwardDeliveryData`, ні `AfterpaymentOnGoodsCost`.
4. Форма ТТН відображається українською (ST-3.7 у тому ж патчі).
5. `php -l` clean; діф лише в `internet_document.php`.

## 8. QA / smoke (⚠ HIGH-RISK money — owner)
- Admin-only → повний `bs-checkout-smoke` не потрібен.
- Owner створює **одну реальну ТТН** (ФОП + COD, order #192/нове), вмикає Money+суму → перевіряє: ЕН створено, у кабінеті НП «Контроль оплати» = сума замовлення, отримувач/відділення коректні. Якщо тест — скасувати ЕН у кабінеті.
- `storage/logs` чисто.

## 9. Rollback
Бекап `internet_document.php` → `_patch_backups/...`; відкат = відновити файл. Без DB. Frontend не зачеплено.

## 10. Owner decisions — РЕЗОЛЮЦІЯ (2026-06-25)
- (a) Сума Контролю оплати = **сума замовлення** (`$cost`). ✅
- (b) ФОП → `AfterpaymentOnGoodsCost` **замість** `BackwardDeliveryData` (не обидва). ✅
- (c) Префіл **за способом оплати клієнта**: післяплата (`pinta_nova_poshta_cod`) → Контроль оплати = сума замовлення; картка/реквізити (передоплата) → `Disabled`. ✅ (виправляємо інверсію — §4 крок 0).

## 11. Status after execution
Combined патч + owner real-ТТН QA passed → **ST-3.6 `Done`** і (якщо QA ок) **ST-3.5 `Done`**. Останній крок: оновити `ROADMAP_FLOW`. Notion (page_id) — Claude.
