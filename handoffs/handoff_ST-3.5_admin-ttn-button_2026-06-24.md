# Codex Handoff — ST-3.5: Кнопка ТТН в адмінці (Pinta Nova Poshta COD)

Date: 2026-06-24
Author: Claude (strategic assistant)
Roadmap: **ST-3.5** «Кнопка ТТН в адмінці» (High, active, Codex). Subtasks: **ST-3.5-1** Фікс якоря кнопки · **ST-3.5-2** Тест форми заявки НП.
Epic: R-13.5 (Nova Poshta module). Diagnostics source: handoff `handoff_ST-2a2_guest-blocker-cards-cod_2026-06-12.md` §B5 + R-13.5 master-log note (2026-06-12, 10PM).
Platform: **OpenCart 4.1.0.3** (corrected from earlier 4.0.2 assumption). Module: **Pinta Webware "Nova Poshta" v1.4.0** at `extension/PintaNovaPoshtaCod/`.

> Scope is intentionally narrow: restore the admin-side TTN button + verify the NP waybill form. This handoff does NOT cover the COD reverse-money logic or auto-TTN (those are separate, out of scope — see §5 / §10).

---

## 1. Task ID

**ST-3.5 — Кнопка ТТН в адмінці.**
- **ST-3.5-1** — Фікс якоря кнопки: у OC 4.1.0.3 змінилась назва опорного елемента в шаблоні `sale/order_info`; патч оновлює рядок-якір у `str_replace` на актуальний.
- **ST-3.5-2** — Тест форми заявки НП: переконатися, що блок/кнопка ТТН рендериться, форма відкривається, і дані (отримувач, місто, відділення, вага, сума COD) підставляються коректно.

Owner intent: без кнопки ТТН в адмін-картці замовлення неможливо сформувати накладну НП напряму з панелі — доводиться робити це руками.

## 2. Context (verified, do not re-derive)

**Симптом (B5, замовлення #155):** на сторінці адмін-картки замовлення немає блоку/кнопки Pinta TTN. Замовлення: shipping = НП відділення, payment = `pinta` COD.

**Active event (per DB `ocp5_event`, status=1):** event 68 — `admin/view/sale/order_info/after` → handler `pinta_nova_poshta_cod.alterOrderAddedBtn`. Подія зареєстрована і активна, тобто проблема не в реєстрації події.

**Root cause (вже діагностовано, B5 → R-13.5 note):**
`alterOrderAddedBtn` інжектить блок кнопки через `str_replace` по якорю **`shipping-address-value`**. У адмінці OC **4.1.0.3 цього якоря немає** — у шаблоні `sale/order_info` він тепер **`output-shipping-address`**. `str_replace` не знаходить ціль → нічого не інжектиться → кнопки немає. Фікс — оновити рядок-якір (≈1 рядок).

**Suffix-логіка:** `getOrdersShippingAddressHtmlSuffix` очікує `shipping_code`/`shipping_method` у формі `pinta_nova_poshta.warehouse` / `pinta_nova_poshta.doors`. Це треба звірити з реальним значенням у `ocp5_order` для тестового замовлення (JSON-форма поля в 4.1 може відрізнятись).

**Локальні патчі модуля (KEEP, не відкочувати до marketplace-zip):** серед 3 патчених файлів є `admin/controller/shipping/pinta_nova_poshta.php` — null-safe `shipping_code` у кнопках замовлення. Хендлер `alterOrderAddedBtn` / `getOrdersShippingAddressHtmlSuffix` живе в admin-частині модуля — **Codex має підтвердити точний файл** (контролер shipping або payment у `extension/PintaNovaPoshtaCod/admin/...`).

## 3. Goal

1. (ST-3.5-1) Блок/кнопка ТТН знову рендериться в адмін-картці замовлення для замовлень з доставкою Pinta НП (відділення / поштомат / адресна) + оплатою `pinta` COD.
2. (ST-3.5-2) Кнопка відкриває форму заявки НП; поля (отримувач, телефон, місто, відділення/реф, вага, оголошена вартість / сума COD) підставляються коректно, без PHP-помилок у лозі та без JS-помилок у консолі.

## 4. What to change

**Phase 0 — verify (read-only, на live або свіжому бекапі):**
- Знайти хендлер `alterOrderAddedBtn` і `getOrdersShippingAddressHtmlSuffix` у `extension/PintaNovaPoshtaCod/admin/...`; зафіксувати точний файл і поточний рядок-якір.
- Відрендерити (або grep) шаблон адмінки `admin/view/template/sale/order_info.twig` у 4.1.0.3 і знайти **реальний унікальний якір** навколо блоку адреси доставки (очікується `output-shipping-address` — підтвердити; не покладатися на документ).
- Для тестового замовлення з НП+COD зчитати з `ocp5_order` фактичні `shipping_code` / `shipping_method`, звірити з тим, що очікує suffix-логіка.

**Phase 1 — fix (ST-3.5-1):**
- Оновити рядок-якір у `str_replace` (`shipping-address-value` → підтверджений 4.1.0.3-якір). Якщо інжекція робиться відносно закриваючого тега/обгортки — обрати **унікальний** наявний у рендері рядок, щоб уникнути множинної підстановки.
- Зберегти null-safe обробку `shipping_code` (існуючий локальний патч).
- Діф — мінімальний, в межах admin-частини модуля.

**Phase 2 — verify form (ST-3.5-2):**
- Підтвердити, що кнопка відкриває форму заявки і контролер віддає коректний prefill (отримувач/місто/відділення-реф/вага/сума). Якщо форма падає або поля порожні — полагодити в межах admin-частини модуля; **корінь і фікс описати в звіті**.
- **Створення реальної ТТН (відправка накладної в NP API) — НЕ виконувати в межах QA.** Тестувати лише відкриття форми + prefill; сабміт реальної накладної робить owner на власному тестовому замовленні (див. §8).

## 5. Do not touch

- **Frontend checkout** (`checkout.twig`, кроки, партіали) — ця задача суто адмінна.
- **Payment / Hutko / Checkbox фіскалізація** — read-only.
- **COD payment-логіка** (`pinta_nova_poshta_cod` payment side) поза самим admin-блоком кнопки — не чіпати. Зокрема **інвертована логіка зворотної доставки грошей (COD) — OUT OF SCOPE** для ST-3.5 (окремий майбутній таск).
- **Auto-TTN / авто-генерація накладних — OUT OF SCOPE** (це AUTO-001, окремо).
- **`getQuote` `cost => 0` Hutko-костиль** і totals — не чіпати.
- **NP checkout-події** (shipping_address/register injection) і їхні твіги — не чіпати.
- Інші 2 локальні патчі модуля; не перезаписувати модуль із marketplace-zip.
- DB-схема, індекси; CRM / Apps Script order-sync payload (формат адреси замовлення не міняти).
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, Merchant feed, Product schema / JSON-LD.

## 6. Likely files / areas (likely — Codex must verify against actual live code)

- `extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php` (locally patched; null-safe shipping_code у кнопках — ймовірне місце `alterOrderAddedBtn`).
- `extension/PintaNovaPoshtaCod/admin/controller/payment/pinta_nova_poshta_cod.php` (альтернативне місце хендлера event 68 — підтвердити).
- `extension/PintaNovaPoshtaCod/admin/...` (форма заявки ТТН: контролер + твіг + language).
- `admin/view/template/sale/order_info.twig` (read-only — джерело реального якоря 4.1.0.3).
- DB (read-only): `ocp5_event` (event 68), `ocp5_order` (shipping_code/method тестового замовлення).

## 7. Acceptance criteria (measurable)

1. (ST-3.5-1) На адмін-картці замовлення з shipping `pinta_nova_poshta.warehouse` (або `.doors`/поштомат) + payment `pinta` COD (тест-кейс: замовлення #155 або нове аналогічне) **видно блок/кнопку ТТН**. До патчу — відсутній; після — присутній.
2. Рядок-якір у `str_replace` збігається з рядком, який **реально присутній** у відрендереному HTML адмінки 4.1.0.3 (доказ: grep шаблону + dump фрагмента `$data`/HTML у звіті).
3. (ST-3.5-2) Клік по кнопці відкриває форму заявки НП; підставлені: отримувач (ПІБ), телефон, місто, відділення/реф, вага (дефолт із налаштувань), оголошена вартість / сума COD. Без PHP-помилок у `storage/logs` і без JS-помилок у консолі.
4. Діф обмежений `extension/PintaNovaPoshtaCod/admin/...` (+ language/twig модуля за потреби). Поза цим — нічого, інакше обґрунтувати у звіті.
5. (Регресія) Метод оплати `pinta` COD і доставка НП на frontend-чекауті без змін: тестове замовлення створюється як раніше (без реальної оплати).

## 8. QA / smoke test

Адмінна задача — повний `bs-checkout-smoke` не обов'язковий (frontend-чекаут не чіпаємо). Мінімальний набір:

1. **Admin, до фіксу:** відкрити #155 → кнопки ТТН немає (baseline).
2. **Admin, після фіксу:** відкрити #155 → блок/кнопка ТТН є; для відділення, поштомата і адресної доставки (по одному замовленню кожного типу, якщо є) — блок рендериться.
3. **Форма:** клік → форма відкривається → prefill коректний (отримувач/місто/відділення/вага/COD). **Не сабмітити реальну накладну.**
4. **Лог/консоль:** `storage/logs` без нових PHP-помилок; DevTools console clean на сторінці order_info.
5. **Регресія frontend (light):** гість додає товар → чекаут → метод НП + `pinta` COD доступні → замовлення доходить до оформлення (НЕ оплачувати). Якщо Codex був змушений правити payment/COD-контролер (а не лише admin-блок) → запустити повний **`bs-checkout-smoke`** і позначити **needs owner check**.
6. **Owner manual (needs owner check):** на тестовому замовленні owner один раз створює реальну ТТН і перевіряє, що накладна формується в кабінеті НП з правильними отримувачем/адресою/вагою/COD. Це робить **owner**, не Codex.

## 9. Rollback note

- Перед змінами: бекап `extension/PintaNovaPoshtaCod/admin/` → `_patch_backups/st3.5-admin-ttn-YYYYMMDD_HHMMSS/` (та сама конвенція, що й наявні `_patch_backups/`).
- У звіті зафіксувати **старий рядок-якір** і точний змінений файл/рядок.
- Відкат: відновити єдиний змінений файл із бекапу.
- Аварійний: якщо інжекція ламає рендер адмін-картки замовлення — вимкнути event 68 (`admin/view/sale/order_info/after` → `alterOrderAddedBtn`) у admin → Events → сторінка повертається до стокового вигляду (без кнопки ТТН, як зараз).
- Без DB-міграцій → відкат БД не потрібен.

## 10. Recommended status after execution

- **ST-3.5-1** done (кнопка рендериться, AC 1–2, 4) → ST-3.5 `In progress`.
- **ST-3.5-2** owner QA passed (форма + prefill, AC 3; owner підтвердив створення реальної ТТН) → ST-3.5 `Done`.
- **Останній крок Required changes:** оновити `ROADMAP_FLOW` у `dashboard/booster-dashboard.html` (підзадачі ST-3.5 `1`/`2` → `done`, при потребі статус ST-3.5).
- Out-of-scope follow-ups (НЕ в ST-3.5, зафіксувати в роадмапі окремо): (a) інвертована логіка зворотної доставки грошей COD; (b) авто-ТТН (AUTO-001).

---

### Risk summary
Low-risk для frontend (адмінний display-event, без правок чекауту/оплати/totals). Зона уваги — це той самий модуль, що обслуговує COD/НП на чекауті, тому жорсткий `Do not touch` (§5) + light frontend-регресія (§8.5). Якщо фікс виходить за межі admin-display у payment/COD-логіку → ескалація до `bs-checkout-smoke` + **needs owner check**.
