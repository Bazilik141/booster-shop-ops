# Codex Handoff — ST-2a.4: order draft/void noise + checkout flow polish

Date: 2026-06-12 (evening). Base: `(10PM)backup-6.12.2026_21-54-05` (verified clean diff vs 11-25-41). Stock route only; SimpleCheckout/url.php/Hutko/getQuote cost untouched.

## 1. Task ID
ST-2a.4 — fix order-history void noise (root cause found, see §2), payment reload gap, poshtomat quote display.

## 2. Context — ROOT CAUSE (verified by Claude on backup)
- Platform = OpenCart **4.1.0.3**.
- Stock `catalog/model/checkout/order.php::editOrder()` line ~196: FIRST action = `$this->addHistory($order_id, (int)$this->config->get('config_void_status_id'))`. Setting `config_void_status_id = 7` = «Відмінений».
- Our checkout JS reloads `checkout/confirm.confirm` after EVERY shipping/payment change → each reload with existing session order_id → `editOrder()` → history row «Відмінений». DB proof: orders #156–159 history pattern `status 7 → status 1` (`ocp5_order_history` ids 313–320).
- This is stock 4.1 draft-void semantics polluting admin with fake «Відмінений» rows. NOT antifraud (fraud_ip_status=0), NOT Hutko (its addHistory fires only on pay click), NOT Pinta COD (button-only confirm).
- Side-effect to check: event 64 `telegram_notification` fires on `order.addHistory/after` — verify whether void rows (status 7) trigger Telegram spam; same for CRM order-sync listener.

## 3. Goal
Admin bez fake «Відмінений»; справжні скасування лишаються статусом 7; менше зайвих editOrder-циклів; payment list оновлюється сам після зміни адреси; для поштомат-адреси не показується нерелевантний quote.

## 4. What to change

### A. Dedicated void status (DB-light, no schema change)
1. Insert new order status (all installed languages): name `Чернетка (системний)` — INSERT into `ocp5_order_status` with next free order_status_id (check max; language_id per existing rows).
2. Update setting `config_void_status_id` → new id (UPDATE `ocp5_setting` WHERE key='config_void_status_id'). Backup old value in patch log.
3. Verify CRM order-sync + telegram_notify configs ignore the new status (report their status-filter config; if telegram sends on ALL statuses — report, owner decides).
Result: «Відмінений» знову означає тільки реальне скасування.

### B. Fewer void cycles (JS, checkout.twig)
Reload `#checkout-confirm` via `confirm.confirm` ONLY when checkout is complete enough: payment code chosen (shipping+address already guaranteed by flow). For intermediate steps (address/shipping changes) update totals display via the existing cart/info-light path or simply skip the confirm reload (panel shows «Оберіть спосіб оплати» placeholder state). Acceptance: одна повна сесія чекаута = максимум 1-2 editOrder-цикли, не 5+.

### C. Payment reload gap
After address change (autosave success) → shipping methods reload (already works) → after auto-select ALSO force `bsCheckoutLoadPaymentMethods()` refresh (зараз payments лишаються старі/порожні до ручного перевибору доставки). Make the chain: address saved → shipping loaded+selected → payments reloaded → confirm placeholder/refresh per §B.

### D. Poshtomat display polish (Pinta model, catalog/model/shipping/pinta_nova_poshta.php)
For saved/posted address where `address_1` starts with «Поштомат» → suppress doors quote (isCorrectDoorsAddress → false fast-path) and set warehouse quote title «Нова пошта: доставка у поштомат». For «Відділення №…» address → suppress doors quote, keep current title. Doors quote shows only when address_1 не починається з Відділення/Поштомат. Keep r135 cost=>0 logic untouched.

## 5. Do not touch
SimpleCheckout, url.php, Hutko code, Checkbox, getQuote cost/threshold logic, NP events, DB schema (INSERT status row + UPDATE one setting — only DB writes allowed, both logged with rollback SQL), CRM payload format.

## 6. Likely files
`catalog/view/template/checkout/checkout.twig`, `catalog/view/template/checkout/shipping_method.twig` (chain hook), `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php` (D), DB: `ocp5_order_status` + `ocp5_setting` (A). Patch-runner style: anchors pre-check, backups, php -l, rollback.sql for DB changes, self-delete.

## 7. Acceptance criteria
1. Повна сесія чекаута (адреса → доставка → оплата → Підтвердження) → в історії замовлення НЕМАЄ жодного рядка «Відмінений»; чернеткові рядки мають статус «Чернетка (системний)» і їх ≤2.
2. Зміна адреси на чекауті → методи доставки ТА оплати оновились без ручного перевибору.
3. Поштомат-адреса → один метод «доставка у поштомат»; відділення → один «доставка у відділення»; адресна → «доставка за адресою».
4. Реальне скасування з адмінки → статус «Відмінений» працює як раніше.
5. Telegram/CRM: звіт — чи реагують на новий void-статус (без самовільних змін їх конфігів).
6. Rollback: `UPDATE setting config_void_status_id=7` + JS/model відкат з бекапів.

## 8. QA (owner)
Один повний guest-цикл + один logged-in цикл → перевір історію обох замовлень в адмінці (AC1), перемикання адрес (AC2), три типи адрес (AC3), скасуй тестове замовлення вручну (AC4).

## 9–10. Rollback / Status
`_patch_backups/st2a4-*` + rollback.sql. Після owner QA → ST-2a повністю Done → 2b.
