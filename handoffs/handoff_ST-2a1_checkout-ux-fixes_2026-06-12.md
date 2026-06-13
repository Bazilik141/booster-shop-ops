# Codex Handoff — ST-2a.1: stock checkout UX fixes (owner QA findings)

Date: 2026-06-12. Parent: ST-2 (Path B), after st2a patch executed. Stock route only — clients still on SimpleCheckout, cutover untouched.
Owner QA confirmed working: NP dropdowns on stock route, logged-in autosave + auto shipping method, saved-address radio. Issues below.

## 1. Task ID
ST-2a.1 — fix 6 QA findings on stock checkout before 2b.

## 2–3. Context / Goal
Owner QA 2026-06-12 (stock route, guest + logged-in). Goal: guest can complete an order end-to-end with minimum clicks; no premature saves; no duplicated name fields.

## 4. What to change

### F1 — Autosave fires on partial input (BUG)
Observed: «Зберігаємо адресу...» while typing warehouse query («лозов»). `bsNpIsComplete()` counts raw text as complete.
Fix: a field counts as complete ONLY when value was picked from dropdown (`data-ref` non-empty) — for area/city/warehouse/street. Manual street fallback: complete on blur with non-empty street+house. Clear `data-ref` on input change. Autosave/auto-quote only after ref-confirmed completeness.

### F2 — Cascade enable/disable (owner request)
«Місто» disabled until область picked (data-ref set); «Відділення/поштомат» (і «Вулиця» для doors) disabled until місто picked. On область change → clear city+warehouse+street values/refs; on місто change → clear warehouse/street. Visual disabled state + placeholder «Спочатку оберіть область/місто».

### F3 — Duplicate name fields (owner request)
Default: «Я — отримувач». Hide перс.інфо «Ім'я»/«Прізвище» inputs (NOT remove — keep synced hidden, register.save requires them); NP «Ім'я/Прізвище отримувача» стають єдиними видимими, sync NP→register firstname/lastname. Checkbox «Отримувач — інша особа»: shows перс.інфо name fields back (customer ≠ receiver), sync stops overwriting them. E-Mail/Телефон завжди видимі в перс.інфо.

### F4 — Guest cannot finish order (BLOCKER)
Stock «Спосіб доставки/оплати» = readonly input + «Вибрати» button, errors «Потрібна адреса доставки!». Fix flow:
1. Guest: auto-submit register.save via AJAX once form complete (F1-ref-confirmed + name/email/phone filled + agree if required) — no visible «Продовжити»; show status line as in NP autosave.
2. After address exists (guest register.save or logged-in autosave): auto-load shipping quotes and render as **radio list** inline (replace readonly-input+button UI in shipping_method.twig), auto-select single/first NP quote; user can switch.
3. After shipping chosen: auto-load payment methods as radio list (payment_method.twig), NOT auto-selected — owner wants explicit payment choice. Agree checkbox/text per current logic stays.
4. Confirm block refreshes automatically after each save (already partially wired via bsCheckoutAutoShipping → extend to payment).
Keep stock save endpoints; UI-only restructure + JS orchestration.

### F5 — Browser autofill swaps names (observed: Ім'я=Леусенко, Прізвище=Євгеній)
Add proper autocomplete attrs: NP receiver firstname `autocomplete="given-name"`, lastname `autocomplete="family-name"`, area/city/warehouse `autocomplete="off"` (вже є) + `name` attrs already non-standard; verify Chrome doesn't autofill swapped. If persists: `autocomplete="new-password"`-style suppression on receiver fields.

### F6 — Performance (investigate, report-first)
Symptoms: first add-to-cart in fresh anonymous session hangs (after F5 works); dropdowns intermittently slow.
Check in this order, report findings before fixing:
1. Template/cache recompile after cache clear (expected one-off).
2. PHP session lock serialization: parallel AJAX (search + autosave + quote) queue on session file. If confirmed → add `session_write_close()` candidates ONLY with explicit separate approval (touches core behavior).
3. Slow query log / NP API calls on cart add (should be none — getQuote runs on checkout, not cart add; verify nothing触 NP API during add-to-cart).
4. searchCity/searchWarehouse SQL timing on live (LIKE on 52k rows — check indexes on description columns; report EXPLAIN, do not add indexes without approval).

## 5. Do not touch
SimpleCheckout, `system/library/url.php`, Hutko/Checkbox, getQuote cost logic (still 2c), NP events, DB schema/indexes (report-only in F6), CRM payload, sitemap/robots/canonical/.htaccess/feed/schema.

## 6. Likely files
`catalog/view/template/checkout/checkout.twig`, `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/*.twig` (3 files from 2a), `catalog/view/template/checkout/shipping_method.twig`, `catalog/view/template/checkout/payment_method.twig`, `catalog/view/template/checkout/register.twig` (F3 hide/checkbox). Controllers only if a render flag is strictly needed.

## 7. Acceptance criteria
1. Typing partial text ніколи не тригерить save (network tab: no shipping_address|save until ref-confirmed complete).
2. Cascade: місто заблоковане до вибору області; відділення/вулиця — до вибору міста; зміна області чистить місто+відділення.
3. Guest end-to-end: перс.інфо (email, телефон) + НП-адреса + оплата → «Оформити замовлення» працює; замовлення в admin. Без жодного проміжного «Продовжити»/«Вибрати».
4. «Я — отримувач» default: одна пара полів імені; «інша особа» розкриває другу пару; обидва варіанти зберігають коректні імена в замовленні (customer vs shipping firstname/lastname).
5. Способи доставки/оплати — радіо-списки, доставка авто-обрана, оплата — ручний вибір.
6. F6: звіт з причиною повільності + пропозиція фіксу (без самовільних змін ядра/індексів).
7. Logged-in flow з 2a не зламаний (saved address preselect + autosave + auto-quote).

## 8. QA / smoke
Owner matrix on stock route: {guest, logged-in} × {відділення, поштомат, адресна} + повторне замовлення logged-in (мінімум кліків: оплата + кнопка) + mobile. Admin order shows correct receiver names both modes.

## 9. Rollback
`_patch_backups/st2a1-*` усіх змінених файлів; відновлення повертає стан після 2a. Клієнти не зачеплені (pre-cutover).

## 10. Status after execution
Owner QA passes → ST-2a `Done`, переходимо до 2b (coupon/First15, agree, phone, GA4).
