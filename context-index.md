# Context Index — Booster Shop

> **Map: `task ID → handoff → diagnostics`.** This file never stores task
> status. Notion status is canonical; see `ROADMAP_SOP.md §1`.
> Known Notion page IDs are in `ROADMAP_SOP.md §5`. Update this index when a
> task gains a new handoff or diagnostic.

## Usage

Start task-context lookup with a targeted search:

```bash
grep "ST-3.5" context-index.md
```

The matching row provides the handoff and diagnostic routes. Read canonical
status and priority from the verified Notion card. Use dashboard
`ROADMAP_FLOW` only as the local status mirror.

---

## ST-серія — Checkout / Admin / НП

| Roadmap ID | Назва | Handoff | Diagnostics |
|---|---|---|---|
| ST-0 | Checkout preflight | handoffs/handoff_ST-0_checkout-preflight_2026-06-12.md | handoffs/st0_checkout_preflight_report_20260612.md |
| ST-1 | НП відділення: синк виправлено | handoffs/handoff_ST-1_np-warehouse-sync-fix_2026-06-12.md | — |
| ST-2 | Stock/checkout migration | handoffs/handoff_ST-2_stock-checkout-migration_2026-06-12.md | — |
| ST-2a1 | Checkout UX fixes | handoffs/handoff_ST-2a1_checkout-ux-fixes_2026-06-12.md | — |
| ST-2a2 | Guest blocker / cards / COD | handoffs/handoff_ST-2a2_guest-blocker-cards-cod_2026-06-12.md | — |
| ST-2a4 | Order void noise | handoffs/handoff_ST-2a4_order-void-noise_2026-06-12.md | — |
| ST-2a7 | Guest captcha exempt | handoffs/handoff_ST-2a7_guest-captcha-exempt_2026-06-13.md | diagnostics/st2a7_guest_captcha_exempt_report_20260613.md |
| ST-2a8 | Guest autosave ref gate | handoffs/handoff_ST-2a8_guest-autosave-ref-gate_2026-06-13.md | diagnostics/st2a8_guest_autosave_ref_gate_report_20260613.md |
| ST-2a8b | Dropdown click autosave | handoffs/handoff_ST-2a8b_dropdown-click-autosave_2026-06-13.md | — |
| ST-2a8c | Autosave bsSaving stuck | handoffs/handoff_ST-2a8c_autosave-bsSaving-stuck_2026-06-13.md | — |
| ST-2a9 | Add to cart cold session UX | — | diagnostics/st2a9_add_to_cart_cold_session_ux_report_20260613.md, diagnostics/st2a9_auto_review_2026-06-21.md |
| ST-2a10 | gtag guard | handoffs/handoff_ST-2a10_gtag-guard_2026-06-13.md | — |
| ST-2b.1–2b.4 | Чекаут — серія UX-фіксів | handoffs/handoff_ST-2b1_defer-confirm-draft-orders_2026-06-13.md + ST-2b2/3/4 | diagnostics/st2b1_checkout_smoke_plan_20260614.md |
| ST-2b2 | Success page / Hutko / fiscal spacing | handoffs/handoff_ST-2b2_success-page-hutko-fiscal-spacing_20260614.md | — |
| ST-2b3 | Confirm summary / success button | handoffs/handoff_ST-2b3_confirm-summary-success-button_20260614.md | — |
| ST-2b4 | Residual draft order / intermediate summary | handoffs/handoff_ST-2b4_residual-draft-order-intermediate-summary_20260614.md | — |
| ST-2b.5 | Промокоди, знижка First15 і GA4 | handoffs/handoff_ST-2b5_coupon-first15-agree-ga4-parity_20260614.md | — |
| ST-2b.6 | Фантомне замовлення Hutko після закриття/відкриття вкладки (Phase 0) | handoffs/handoff_ST-2b6_hutko-phantom-order-tab-restore_20260703.md | diagnostics/ST-2b6_hutko-tab-restore-phase0_report_20260703.md |
| ST-2b.6 (0b) | Тихий скид оплати на Hutko + розсинхрон старий/новий чекаут | handoffs/handoff_ST-2b6b_hutko-payment-silent-reset_20260703.md | diagnostics/ST-2b6b_hutko-payment-state-phase0b_report_20260703.md |
| ST-2b.6 (Phase 1) | Фікс: прибрати автовибір/автозбереження Hutko після ресету адреси | handoffs/handoff_ST-2b6c_hutko-autoselect-fix_20260703.md | diagnostics/ST-2b6c_hutko-autoselect-fix_report_20260703.md |
| ST-2b.6 (gate) | Trusted-click гейт на «Оформити» (закриває пропуск з ST-2b.4) | handoffs/handoff_ST-2b6d_deferred-confirm-trusted-click-gate_20260703.md | diagnostics/ST-2b6d_deferred-confirm-trusted-click-gate_report_20260703.md |
| ST-2b.6 (root fix) | ST-2b6e — read-only рендер чекауту, запис замовлення лише через явний confirm() | — (evidence-first, без окремого handoff) | diagnostics/ST-2b6e_server-render-order-write-gate_report_20260712.md |
| RD-13.1J | Гостьовий чекаут: відновити RD-13.1C CAPTCHA POST-payload у checkout.twig (422 на confirm.confirm) | handoffs/handoff_RD-13.1J_guest-captcha-confirm-payload-restore_20260713.md | — |
| CHECKOUT-003 | Помилка валідації адреси одразу при відкритті чекауту (мобайл, авторизований) | handoffs/handoff_CHECKOUT-003_authorized-address-error-on-load_20260713.md | — |
| CHECKOUT-004 | Промокоди (coupon/First15) у новому чекауті — заміна RD13-STUB на реальний endpoint | handoffs/handoff_CHECKOUT-004_promo-code-new-checkout_20260715.md | — |
| CHECKOUT-005 | Гостьова реєстрація під час чекауту: НП-адреса + First15 (задача описана напряму Codex, без хендофа) | — | diagnostics/CHECKOUT-005_guest-account-np-first15_report_20260715.md |
| CHECKOUT-006 | First15 при гостьовій реєстрації під час чекауту → знижка на наступне замовлення (не на поточне) | handoffs/handoff_CHECKOUT-006_first15-next-order-message_20260715.md | — |
| CHECKOUT-007 | First15 автоматично застосовується на справжнє наступне замовлення клієнта (без ручного вводу коду) | handoffs/handoff_CHECKOUT-007_first15-auto-apply-next-order_20260717.md | — |
| CHECKOUT-008 | IBAN-реквізити в листі підтвердження замовлення (лише спосіб оплати IBAN) + кнопка «Скопіювати реквізити» на checkout success — не задеплоєно, потребує Codex-діагностики шаблону листа/payment_code проти найновішого бекапу | handoffs/handoff_CHECKOUT-008_iban-requisites-email-and-success-copy_20260729.md | — |
| CHECKOUT-011 | Передзамовлення 3D-виробів: купівля при нульовому залишку + переписування правила кредитних методів (mono + ПУМБ). **Читати:** `handoffs/handoff_CHECKOUT-011_preorder-3d-diagnostic_20260816.md` — це хендоф на **діагностику**, не на патч; архітектуру власник обирає після звіту. Прапорець — новий статус наявності «Виготовляємо на замовлення», правило читає список дозволених статусів із налаштувань. Блокує ввімкнення п'яти Pokémon-брелоків. Notion: `3be6bf20-bdb4-8128-89d5-e1c6f8653486` |
| CHECKOUT — який саме чекаут живий | **Стоковий `checkout/checkout`**, модифікований під час редизайну. Підтверджено власником 2026-08-16 і кодом у бекапі 16.08: `system/library/url.php` більше не переписує маршрут, лишився тільки коментар «ST-2c cutover: stock checkout is default». Розширення `SimpleCheckout` при цьому досі встановлене й увімкнене (`module_pinta_simple_checkout_status = 1`) — воно поза трактом запиту, але не видалене. Діагностики `CHECKOUT-001`/`CHECKOUT-004` описують докатоверний тракт і читаються як історія, а не як карта коду. Правило: наявність теки чи рядка розширення **не є доказом**, що воно обслуговує чекаут |
| CHECKOUT-009 | **P0 (2026-07-29):** чекаут не реєструє обрану доставку — гість не може оформити замовлення взагалі (кнопка підтвердження заблокована при повністю заповненій доставці), у авторизованого клієнта той самий симптом обходиться вибором іншої збереженої адреси; кредитний спосіб оплати теж заблокований гейтом «заповніть дані отримувача і адресу доставки». Рішення власника 29.07: без латок і без відкату — Codex робить глибокий архітектурний аудит чекауту (карта архітектури + усі гейти + археологія патчів), доводить корінну причину, подає варіанти (точкові виправлення vs консолідація костилів у нормальні процеси); реалізація лише після вибору власника. Орієнтир-гіпотеза — раунди ST-2c 28–29.07 (checkout-state.js / shipping_method.twig / checkout-reskin.js), не доведено | **Читати першим:** handoffs/handoff_CHECKOUT-009_shipping-selection-not-registered_20260729.md (хендофф-аудит) → plans/CHECKOUT-009_checkout-architecture-map_20260729.md (карта архітектури + корінна причина) → plans/CHECKOUT-009_checkout-behaviour-register_20260729.md (реєстр 40 поведінок + інвентар маркерів) → plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md (варіанти A/B/C) → diagnostics/CHECKOUT-009_shipping-selection-not-registered_report_20260729.md (звіт Codex) → diagnostics/CHECKOUT-009_audit_review_20260729.md (рев'ю Claude: аудит прийнято, Option C, 3 блокуючі умови) → **handoffs/handoff_CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.md** (власник обрав Option C 29.07; авторизовано Stage 1, Stage 2 — ні) | Причина: coupon.summary класифікується як мутація (checkout-reskin.js:391 → couponChanged), збиває збереження доставки. Пов'язані: patches/ST-2c_coupon_shipping_threshold_refresh_validated_20260728.php, patches/ST-2c_minicart_shipping_requote_20260728.php, patches/ST-2c_minicart_shipping_threshold_alignment_20260729.php |
| CHECKOUT-010 | Консолідація стану чекауту — Stage 2 після CHECKOUT-009 (знижка First15 → явна серверна дія, `coupon.summary` → справжній запит на читання, міграція відповідальності з прихованих полів у чотири процеси) + 4 відкладені дрібниці з рев'ю Stage 1. Не стартувала, потребує окремої авторизації власника | **Читати першим:** plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md (Option B / Stage 2) → plans/CHECKOUT-009_checkout-behaviour-register_20260729.md (контракт збереження, ціль 25/15/0) → diagnostics/CHECKOUT-009_stage1_review_20260729.md + diagnostics/CHECKOUT-009_stage1_review_round2_20260729.md (відкладені пункти) | Хендофа ще немає |
| ACC-001 | Меню кабінету: дубль на десктопі, без «Вихід» на мобайлі | handoffs/handoff_ACC-001_account-menu-dedup-logout_20260713.md | — |
| ACC-002 | NP-форма адреси в акаунті замість стокової free-text | handoffs/handoff_ACC-002_account-np-address-form_20260713.md | — |
| ACC-003 | **P0 (2026-08-22):** тихий відскок логіну та реєстрації — `login_token` / `register_token` перегенеруються при кожному рендері, будь-який другий рендер убиває відкриту форму, POST повертає голий redirect без повідомлення. Тригер (тег Plerdy) знято з прода 22.08, дефект лишається. Виконавець: Claude Code | handoffs/handoff_ACC-003_login-register-token-rotation_20260822.md · handoffs/handoff_ANALYTICS-001_plerdy-tracking-install_20260805.md (розділ «2026-08-22 — REVERTED») | — |
| ST-2c | Переключення всіх клієнтів на новий чекаут | handoffs/handoff_ST-2c_real-shipping-cost-content-sweep_20260718.md (реальна вартість + текстовий свіп, поріг 2000 грн) + handoffs/handoff_ST-2c_real-shipping-cost-cutover_2026-07-02.md (§url.php cutover — окремий пізніший крок) + handoffs/handoff_ST-2c_coupon-shipping-threshold-review_20260728.md (купон опускає payable нижче порогу безкоштовної доставки — рев'ю патча) | diagnostics/ST-2c_coupon_shipping_threshold_refresh_report_20260728.md (звіт Codex) + diagnostics/ST-2c_coupon_shipping_threshold_refresh_review_20260728.md (рев'ю Claude) + diagnostics/ST-2c_minicart_shipping_requote_report_20260728.md (звіт Codex, без хендофа) + diagnostics/ST-2c_minicart_shipping_requote_review_20260728.md (рев'ю Claude) + diagnostics/ST-2c_minicart_shipping_threshold_alignment_report_20260729.md (звіт Codex, без хендофа) + diagnostics/ST-2c_minicart_shipping_threshold_alignment_review_20260729.md (рев'ю Claude — корекція Round 1) |
| ST-3.5 | Кнопка ТТН в адмінці | handoffs/handoff_ST-3.5_admin-ttn-button_2026-06-24.md | — |
| ST-3.5-1 | Фікс якоря кнопки (OC 4.1.0.3) | ↑ в тому ж хендофі (підзадача) | — |
| ST-3.5-2 | Тест форми заявки НП | ↑ в тому ж хендофі (підзадача) | — |
| ST-6 | Вимкнення старого чекауту | — | — |

---

## RD-серія — Редизайн

| Roadmap ID | Назва | Handoff |
|---|---|---|
| RD-04 | Картка товару — дизайн-система | handoffs/handoff_RD-04_product-card_thumb-twig_2026-06-01.md |
| RD-06/07 | Empty state / category / breadcrumb | handoffs/handoff_RD-06-07_empty-state_category_breadcrumb_2026-06-04.md |
| RD-10 | Сторінка товару — редизайн | handoffs/handoff_RD-10_product-page-parity_2026-06-09.md |
| RD-10D2 | Breadcrumb mockup fix | handoffs/handoff_RD-10D2_breadcrumb-mockup-fix_2026-06-11.md |
| RD-11 | Редизайн сторінки кошика | — |
| RD-13 | Checkout reskin — visual-only stock checkout | handoffs/HANDOFF-RD13-checkoutV2.md · handoffs/HANDOFF-RD13-checkout-FIXES-round2.md · diagnostics/RD-13_checkout-reskin-round2_report_20260706.md |
| RD-01/02/03 | Shell DS parity | handoffs/handoff_RD-01-02-03_shell-ds-parity_2026-05-30.md |

---

## TECH-серія — SEO / Технічне

| Roadmap ID | Назва | Handoff | Diagnostics |
|---|---|---|---|
| TECH-005-DEEP | Sitemap GSC — Watch Only | handoffs/handoff_TECH-005-DEEP_sitemap-binary-serving_2026-06-05.md | diagnostics/TECH-005-DEEP_*.md |
| TECH-010/012 | Noindex / canonicals / дублі URL | handoffs/handoff_TECH-010-012_noindex-canonicals_2026-06-09.md | diagnostics/indexation-status-and-sitemap-sync_2026-06-15.md |
| TECH-012 / legacy-404 | Старі URL товарів → 301 | handoffs/handoff_TECH-012-legacy404-301_2026-07-02.md | diagnostics/TECH-012_legacy404-301_report_20260702.md |
| TECH-029 | Sitemap / GSC — site-side | handoffs/codex-handoff-TECH029-sitemap.md | — |
| TECH-030/031 | — | handoffs/codex-handoff-TECH030-031.md | — |
| TECH-035 | IndexNow (Bing/AI fast discovery) | handoffs/handoff_TECH-035_indexnow_2026-07-04.md | — |
| TECH-032/033/034 | — | handoffs/codex-handoff-TECH032-033-034.md | — |
| TECH-013 | Mobile Core Web Vitals pass, Stage 1 (об'єднує TECH-002/003/004; TECH-003 = підзадача WP2) | **handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md** (Rev. 2026-08-05, канонічний, виконавець Claude Code) · handoffs/handoff_TECH-013_mobile-core-web-vitals_20260716.md (SUPERSEDED, бейзлайн 16.07) | — |
| TECH-042 | Bot-challenge / AI-visibility read-only check | handoffs/handoff_TECH-042_bot-challenge-ai-visibility-check_20260716.md | — |

---

## CRM / Dashboard

| Roadmap ID | Назва | Handoff |
|---|---|---|
| DASH-001 | Огляд — три плитки без даних | — |
| DASH-002 | Огляд — Потребують уваги | — |
| DASH-WRITE-004 | Облік — редагування записів | handoffs/handoff_DASH-WRITE-004_edit-records_20260619.md |
| DASH-001 (new) | Огляд — Summary + warehouse | handoffs/handoff_DASH-001_summary-warehouse-asset-fields_20260619.md |
| DASH-PERF-001 | Parallel overview fetch | handoffs/handoff_DASH-PERF-001_parallel-overview-fetch_20260615.md |
| DASH-WRITE-001 | Dashboard write UI | handoffs/handoff_DASH-WRITE-001_dashboard-write-ui_20260617.md |
| DASH-WRITE-002 | Apps Script write API | handoffs/handoff_DASH-WRITE-002_apps-script-write-api_20260617.md |
| DASH-WRITE-003 | Dashboard write forms | handoffs/handoff_DASH-WRITE-003_dashboard-write-forms_20260618.md |
| WRTPERF-001 | Apps Script write speed | handoffs/handoff_WRTPERF-001_apps-script-write-speed_20260616.md |
| CRM-001 | Dashboard — форма мультиканальної закупки | handoffs/CRM-001-dashboard-multichannel-purchase-form.md |
| CRM-002 | Apps Script — multi-channel закупки | handoffs/CRM-002-apps-script-multichannel-purchase.md |
| CRM-003 | Ротація BOOSTER_CRM_TOKEN (хардкод-знахідка 2026-07-29) | handoffs/handoff_CRM-003_booster-crm-token-rotation_20260729.md |

---

## CRM — нова платформа (NCRM)

> Проєкт міграції CRM на Supabase. Плани: `plans/crm-new-platform-architecture_2026-06-26.md`, `plans/crm-financial-model_2026-06-26.md`, `plans/crm-schema-v1_2026-06-26.md`. page_id-реєстр — `ROADMAP_SOP.md §5`.
>
> **2026-07-11:** `plans/NCRM-financial-model-v2_technical-contract_20260711.md` §7 переномерував послідовність (owner-approved). NCRM-04…07 тепер Inventory foundation → Mystery → Returns/COGS → Reporting/forecast+KPI (останній вбирає колишній NCRM-06 "Витрати+P&L+KPI"). Колишній зміст NCRM-04/05/07 (Read-екрани / Write-форми+FIFO-COGS / OpenCart pipeline) переїхав на нові картки NCRM-08/09/10; колишні NCRM-08/09 (курси/mobile) перенумеровані в NCRM-11/12 без зміни змісту. Синхронізовано в Notion + `booster-dashboard.html` (обидві копії) в тому ж сеансі — деталі й коментарі по кожній картці в Notion.

| Roadmap ID | Назва | Handoff |
|---|---|---|
| NCRM-00 | Архітектура + аудит фінмоделі + schema v1 (Done) | plans/crm-* (вище) |
| NCRM-01 | Supabase проєкт + SQL-міграції + типи (Done) | handoffs/handoff_NCRM-01_supabase-project-sql-migrations_2026-07-05.md |
| NCRM-02 | Repository-шар + Next.js скелет + emulator (Done) | handoffs/handoff_NCRM-02_repository-layer-nextjs-skeleton_2026-07-06.md |
| NCRM-03 | Імпорт історії зі Sheets + звірка KPI (Done, 2026-07-16, round 3 — залишок перенесено в NCRM-13) | handoffs/handoff_NCRM-03_round3_import-history-kpi-reconciliation_2026-07-16.md |
| NCRM-13 | Signed inventory adjustment model (списання з від'ємною к-стю) — виділено з NCRM-03 (Not started); 2026-07-17: додано форму повернень (колишній NCRM-09d) — фізичний restock складу при resellable-поверненні | — |
| NCRM-04 | Inventory ledger foundation (Done, commit 3c98253) | handoffs/handoff_NCRM-04_inventory-ledger-foundation_2026-07-11.md |
| NCRM-05 | Mystery fulfillment (Done, commit cb964cb) | handoffs/handoff_NCRM-05_mystery-fulfillment_2026-07-12.md |
| NCRM-06 | Returns + cost quality (Done, commits 0cd78bd + 4e4a0e6 — owner closed after partial manual QA; Mystery-reversal + `git diff` 0001-0007 not independently re-verified) | handoffs/handoff_NCRM-06_returns-cost-quality_2026-07-14.md |
| NCRM-07 | Reporting/forecast + KPI-вʼюхи (вкл. колишній NCRM-06) (Done, commit c6cc8f3 + parent — owner закрив на основі доказів у звіті, без окремого прогону db reset) | handoffs/handoff_NCRM-07_reporting-forecast-kpi-views_2026-07-14.md |
| NCRM-07b | Enable RLS on public schema | handoffs/handoff_NCRM-07b_rls-multiuser-role-foundation_20260715.md |
| NCRM-08 | Read-екрани (summary/замовлення/склад/SKU/клієнти) — колишній зміст NCRM-04 | handoffs/handoff_NCRM-08_read-screens_2026-07-16.md |
| NCRM-09 | Write-форми + FIFO-COGS + auth (owner/admin) — колишній зміст NCRM-05, розбито на 09a/09b/09c 2026-07-17 | plans/NCRM-09_write-forms-auth-split_20260717.md |
| NCRM-09a | Auth-фундамент (owner/admin) — sub-scope NCRM-09, не окрема Notion-картка | handoffs/handoff_NCRM-09a_auth-foundation_20260717.md |
| NCRM-09b | Write-форми продаж+закупка — sub-scope NCRM-09, 09a owner-confirmed | handoffs/handoff_NCRM-09b_sale-purchase-forms_20260717.md |
| NCRM-09c | Write-форми списання+РРЦ — sub-scope NCRM-09, звужено (повернення/mystery виділено в 09d/09e) | handoffs/handoff_NCRM-09c_writeoff-rrc-forms_20260717.md |
| NCRM-09d | Write-форма повернення (refunds) — ПРИЗУПИНЕНО 2026-07-17, переміщено в NCRM-13 (COGS-reversal рахується правильно, але фізичний restock складу не реалізований без signed inventory adjustments) | — |
| NCRM-09e | Mystery box reservation/assembly UI (reserve/commit/release, без reversal) — sub-scope NCRM-09 | handoffs/handoff_NCRM-09e_mystery-box-fulfillment_20260717.md |
| NCRM-10 | Order pipeline OpenCart→Supabase + smoke — колишній зміст NCRM-07 (Done, 2026-07-26, owner QA пройдено; CHECKOUT-002 async-queue + виправлений cron-розклад; discount_total=0 баг лишається окремо, не блокує) | handoffs/handoff_NCRM-10_order-pipeline-opencart-supabase_20260718.md |
| NCRM-11 | Курси валют — автоматичний фетч Приват/Моно→НБУ (без ПУМБ, owner-рішення 2026-07-26), +1% буфер лише для НБУ; заморозка при закупці — окремо (In progress, план+хендофф готові, передано Codex) | handoffs/handoff_NCRM-11_currency-rates-fetch_20260726.md |
| NCRM-12 | Форма зміни статусу замовлення (order_status/payment_status/ttn/note на /orders/[id]) — звужено 2026-07-26, mobile винесено в NCRM-15. Хендофф готовий, у черзі після NCRM-11 | handoffs/handoff_NCRM-12_order-status-form_20260726.md |
| NCRM-14 | Order-sync: типи оплати ПУМБ ПЧ (credit_pumb_3/4/5) + фікс discount_total=0 — round 1 задеплоєно 2026-07-28 (Claude-review OK). Round 2 (2026-07-29): тимчасовий Telegram-алерт на реальне MONO/ПУМБ-замовлення для закриття задачі, хендофф готовий, ще не передано Codex | handoffs/handoff_NCRM-14_order-sync-pumb-payment-types_20260726.md, diagnostics/NCRM-14_order-sync-pumb-discount_report_20260726.md, handoffs/handoff_NCRM-14_round2_telegram-smoke-alert_20260729.md |
| NCRM-15 | Mobile-версія + поліш — виділено з NCRM-12 2026-07-26, без чіткого обсягу (design-discovery потрібен) | — |
| NCRM-16 | Спосіб оплати «Післяплата monobazar» (2.9%) — новий ФОП-профіль власника, автопідстановка в формі продажу + автопідрахунок комісії. LOW-RISK, незалежна від 11/12/14. 2026-07-27: round2 code-reviewed OK, owner QA неможливе до деплою (див. NCRM-17) — на паузі | handoffs/handoff_NCRM-16_monobazar-postpay-fee_20260726.md → round2: handoffs/handoff_NCRM-16_monobazar-postpay-fee_round2_20260727.md |
| NCRM-17 | Деплой Next.js застосунку — досі лише local-only (`npm run dev`), архітектурний план планував Vercel ще на Phase 0, не сталося. 2026-07-27: власник підтвердив хостинг за планом (Vercel + Supabase); Codex-хендофф ще не писався | — |
| NCRM-18 | Перенесення роадмапу з Notion у NCRM — заведено 2026-07-27 як фінальна задача, старт лише після завершення поточного NCRM-беклогу (11–17). 2026-07-28: scoping-нотатки готові (архітектура вже є, відкриті лише продуктові питання) | plans/NCRM-18_roadmap-in-ncrm_scoping_20260728.md |
| NCRM-19 | Sync-автоматизація Notion ↔ дашборд-дзеркало — заведено 2026-07-27, ближчий за часом замінник NCRM-18. Разовий cleanup зроблено (NCRM-07b дублі, NCRM-11 назва, AUTO-* колізії позначені). 2026-07-28: хендофф на автоматичний тригер готовий — розширення `bsreview`, не новий git-хук/розклад: після кожного review синхронізує status/priority/lastUpdated одного review-нутого таску з Notion у дашборд | handoffs/handoff_NCRM-19_bsreview-dashboard-sync_20260728.md |
| NCRM-20 | Бекфіл замовлень, втрачених у вікні Mono-бага (NCRM-10) — заведено 2026-07-29, власник підтвердив "так, бекфілимо". Джерело даних — стара CRM (Apps Script/Sheets). Ще не заскоуплено: потрібна діагностика (звірка Sheets проти Supabase `sales`, точний список бракуючих order id) і Codex-хендофф перед будь-яким записом | — (заведено, хендофф ще не написано) |

---

## Інші задачі

| Roadmap ID | Назва | Handoff |
|---|---|---|
| PAY-001 | Monobank Покупка Частинами — інтеграція оплати частинами | **Читати першим:** `handoffs/handoff_PAY-001_RESET_checkout-architecture-correction_20260721.md` (виправлення архітектури чекауту + актуальний стан) → `diagnostics/PAY-001_progress-and-preorder-followup_report_20260721.md` (поточний Codex→Claude стан) → потім `handoffs/handoff_PAY-001_monobank-chastyny-integration_20260718.md` (історія раундів 0–9, повна API-довідка) |
| PAY-001-UI | Візуальний дизайн-бриф для Claude Design: кнопка + модалка «Купити в кредит» + стани чекауту; готова специфікація отримана 2026-07-19 | handoffs/handoff_PAY-001-UI_visual-design-brief_20260718.md (бриф) → `handoffs/CODEX - PAY-001-credit-flow.md` (готовий результат Claude Design) |
| PAY-001-DISCLOSURE | Юридична згадка «Покупка частинами» на `information/oplata-i-dostavka` — вимога monobank sales-supervайзера перед тестом оплати (26.07.2026); текст банку копіюється без змін | handoffs/handoff_PAY-001-DISCLOSURE_mono-installment-disclosure_20260726.md |
| PAY-002 | ПУМБ «Сплачуйте частинами» — другий провайдер розстрочки, окремий extension `pumb_credit` | **Читати першим:** `plans/PAY-002_pumb-protocol-revision_20260727.md` (реальний протокол банку + §7a/§7b/§7c відповіді банку) → `plans/PAY_decomposition_mono-pumb-preorder_20260721.md` §5, §6.5, §7, §10 (факти договору, флоу власника, QA, ризики) → `handoffs/handoff_PAY-002_pumb-credit-skeleton_20260727.md` (Codex-хендофф на скелет, 2026-07-27) → `diagnostics/PAY-002_pumb-credit-skeleton_review_20260728.md` (Claude-review, §6 — знайдений дефект) → `handoffs/handoff_PAY-002_confirm-idempotency-guard_20260729.md` (Codex-хендофф на фікс дефекту §6) → `diagnostics/PAY-002_confirm-idempotency-guard_review_20260729.md` (Claude-review раунд 2: **Review OK, cleared to deploy**; §7 — фікси незалежно перевірені та задеплоєні, QA пройдено) → `handoffs/handoff_PAY-002_founded-state-defensive-fix_20260730.md` + `diagnostics/PAY-002_founded-state-defensive-fix_review_20260730.md` (Claude-review: **Review OK, cleared to deploy** — приймає і FUNDED, і FOUNDED) |
| PAY-003 | Спільна проміжна сторінка очікування підтвердження кредиту (mono + ПУМБ), між checkout confirm і checkout success | `plans/PAY_decomposition_mono-pumb-preorder_20260721.md` §10 — blockedBy PAY-002, не стартує до активної розробки ПУМБ |
| PAY-001-SMOKE | Фінальний спільний QA-гейт кредитної покупки (mono + ПУМБ), заведена після закриття PAY-001 | **Читати першим:** `plans/PAY-001-SMOKE_unified-credit-qa_20260727.md` (повний 5-стадійний план) → `plans/PAY_decomposition_mono-pumb-preorder_20260721.md` §9 |
| CHECKOUT-001 | Реєстрація акаунту при замовленні (Done) | handoffs/handoff_CHECKOUT-001_phase1_guest-account-creation_2026-07-04.md |
| CHECKOUT-002 | Швидкість оформлення + редизайн loader | — |
| CAT-002 | Категорії + аксесуари (parent) | — |
| CAT-002-4 | YGO Blazing Dominion SKU | plans/ygo_sku_blazing_dominion_20260628.md |
| CAT-002-5 | Тайлс категорій — кольори і HTML | plans/category_tiles_colors_20260628.md |
| CAT-002-5b | Бургер-меню нові категорії + фікс URL | handoffs/handoff_CAT-002-5b_burger-menu-new-categories_20260628.md |
| LEGAL-002 | Публічна оферта + Обмін і повернення (mono/ПУМБ розстрочка + архів редакцій, фінальний текст власника) — редакція 24.07.2026 задеплоєна, підтверджено побайтною звіркою проти бекапу 2026-08-05 | handoffs/handoff_LEGAL-002_offer-mono-pumb-archive_20260724.md (актуальний) → handoffs/handoff_LEGAL-002_offer-mono-pumb-archive_20260723.md (попередня чернетка Claude, замінена текстом власника) |
| LEGAL-002b-3DP | Продовження LEGAL-002: оферта (розділи 3D-друку/Mystery Box, редакція 06.08.2026) + сторінка «Обмін і повернення» + AirPack/FAQ картки Mystery Box + окремий хендоф атрибутів 3D-карток/USB-світильника. Виконавець — Claude Code (не Codex), робота частинами зі звіркою власника. Notion: `3b46bf20-bdb4-8158-bf9c-d90006a7e592` | Джерело: `Booster_Shop_handoff_offer_3D_mystery_2026-07-30.md` (owner edit 2026-08-05, у Cowork-чаті) → чернетки в Cowork outputs: offer_html_20260806_DRAFT.html, offer_html_archive_20260724_DRAFT.html, return_page_20260806_DRAFT.html, mysterybox_airpack_faq_20260806_DRAFT.html (частини I–III підтверджені власником, ще не скопійовані в repo) → хендоф Частини IV/V і технічний хендоф Claude Code — в роботі |
| BRAND-OUTLET-001 | Outlet Booster — опис і SEO | — |
| R-13.5 | НП модуль — master log (ST-серія) | handoffs/handoff_R-13.5_nova-poshta-module_2026-06-12.md |
| ANALYTICS-001 | Встановлення Plerdy tracking snippet у footer.twig — заведено 2026-08-05, ще не в Notion roadmap (owner to confirm) | handoffs/handoff_ANALYTICS-001_plerdy-tracking-install_20260805.md |

## MKT-TG — Telegram контент-автоматизація

| Roadmap ID | Назва | Handoff / Plan |
|---|---|---|
| MKT-TG-003 | Make TG-пайплайн: фікс RSS→jina→Claude→GPT→Telegram (Done, superseded by MKT-TG-005) | handoffs/MKT-TG-003_make-pipeline-status_20260627.md, handoffs/MKT-TG-003_make-pipeline-handoff_20260626.md |
| MKT-TG-004 | TG контент-автоматизація Phase 2 (мультиджерело+бот+картинки+розклад) — Make-підхід, superseded by MKT-TG-005 | plans/tg-content-automation-phase2-plan_2026-06-27.md |
| MKT-TG-005 | Path A: lean RSS→Telegram news digest (заміна Make-пайплайну, on-demand AI-чернетка) | handoffs/MKT-TG-005_path-A-lean-rss-digest_20260703.md, handoffs/MKT-TG-005_codex-handoff_20260703.md |
| MKT-TG-006 | /post <url> — OpenAI-чернетка за посиланням, паралельно до RSS-дайджесту | handoffs/MKT-TG-006_codex-handoff_openai-url-draft_20260704.md |

---

## 3D-P — 3D-друк мерч (новий напрям)

> Заведено 2026-07-28: друг власника 3D-друкує фігурки/аксесуари за мотивами ККГ, які продає Booster Shop. Два треки: продаж на сайті (% другу) і маркетингові «плюшки» (закупівля з мінімальною націнкою, бонус у великих замовленнях). Лише scoping — жодного продакшн-запису не зроблено.

| Roadmap ID | Назва | Handoff / Plan |
|---|---|---|
| 3D-P-000 | Discovery & scoping (вкл. рев'ю зовнішнього ChatGPT-хендофу, план §11) | plans/3D-P-000_scoping-and-architecture_20260728.md · plans/3D-P_handoff-chatgpt_v1_20260728.md (архів оригіналу) |
| 3D-P-001 | Номенклатура + облік собівартості/РРЦ | 3d-print/3D-P_nomenclature-tracker_v6_20260731.xlsx (найновіша версія — перевір папку `3d-print/` на новіші v#, див. план §5) |
| 3D-P-002 | Розміщення в каталозі — підкатегорія «Фігурки» (Pokémon) | plans/3D-P-000_scoping-and-architecture_20260728.md §4 (SEO risk-gated) · plans/3D-P-002_catalog-placement-admin-guide_20260731.md (виконавчий гайд, у роботі) · **2026-08-19: ВИКОНАНО НА ПРОДІ** — категорії 71/72/73/74 створені, 9 наявних аксесуарів розсортовані власником вручну. Точка входу для продовження: `handoffs/handoff_3D-P_session-continuation_20260819.md`. Категорії 73/74 ще вимкнені. |
| 3D-P-003 | Аналіз ринкових РРЦ і розмірів | plans/3D-P-000_scoping-and-architecture_20260728.md §6 (1 приклад, недостатньо) |
| 3D-P-004 | Маркетингові «плюшки» — потік закупівлі/бонусів | plans/3D-P-000_scoping-and-architecture_20260728.md §5 |
| 3D-P-005 | Майбутній модуль у NCRM (вузький доступ другу) | plans/3D-P-000_scoping-and-architecture_20260728.md §5, узгоджено з NCRM-18 |
| 3D-P-006 | Owner dashboard tab («3D-друк» в booster-dashboard.html) + калькулятор | handoffs/handoff_3D-P-006_owner-dashboard-tab_20260731.md (FINAL calculator spec 2026-08-02; блок доти, поки не відвантажиться addendum з 3D-P-008) |
| 3D-P-007 | Локальний сервер Сергія (споживач API з 3D-P-008) | **Точка входу: `handoffs/handoff_3D-P_session-continuation_20260823.md`.** Межа даних — `plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md`, секція «⚠ РЕВІЗІЯ 2026-08-16» (усе всередині 3D-лінії відкрито Сергію, закрита лише ідентичність замовлення й клієнта); старіші документи описують скасовану модель приховування маржі. API-половина завершена: WP1 rev 2 (`handoffs/handoff_3D-P-007-WP1_role-read-projections_20260816.md`), WP1b (`handoffs/handoff_3D-P-007-WP1b_serhiy-write-rights_20260816.md`), WP1c (`handoffs/handoff_3D-P-007-WP1c_draft-status-and-sku-validator_20260822.md`) — задеплоєно й QA OK, жива версія 3D-P Web App V29 (2026-08-23), дзеркало репо звірене байт-у-байт (`3d-print/apps-script-3dp-api/SOURCE_STATE.md`). У роботі: **WP2** — `handoffs/handoff_3D-P-007-WP2_serhiy-local-server-respec_20260823.md` (re-spec `3d-print/serhiy-local-server/`, виконавець Codex) · **WP2b** — `handoffs/handoff_3D-P-007-WP2b_draft-queue-and-article-editing_20260823.md` (черга чернеток + редагування артикула, дві поверхні: Apps Script і дашборд, виконавець Codex). WP3 (встановлення у Сергія + спільне live QA) хендофу ще не має і є непокритим гейтом закриття `3D-P-015`. Історія: `handoffs/handoff_3D-P-007_serhiy-local-server_20260731.md` (оновлено 2026-08-02 під batch/брак-модель). |
| 3D-P-008 | Apps Script API foundation (read+write) + реконсиляція таблиці | handoffs/handoff_3D-P-008_apps-script-api-foundation_20260731.md (базова частина Done/deployed 01.08; addendum «Schema correction, 2026-08-02» на початку файлу — фінальна формула, ще не імплементована) · diagnostics/3D-P-008_apps-script-api-foundation_report_20260801.md · diagnostics/3D-P-008_reconciliation_diff_20260801.md (очікує owner approval) |
| 3D-P-010 | Автопідтяжка вартості паковання + фурнітури з основної СРМ у 3D-P Продажі | handoffs/handoff_3D-P-010_crm-packaging-cost-pull_20260802.md (розширено 02.08 фурнітурою; CRM risky zone, Фаза 0 = дослідження перед імплементацією) |
| 3D-P-011 | Вибір характеристики товару (розмір) + UI сторінки товару для мультихарактеристичних 3D-товарів (тригер: Онікс 21 см / 15 см) | **Читати:** `diagnostics/3D-P-011_native-variant-feasibility_report_20260806.md` + `diagnostics/3D-P-011_catalog-state-addendum_report_20260806.md` (обидва проти живого cPanel-бекапу). Стан: нативна OpenCart 4.1 master/variant фіча активна на бекенді, фронтенд-селектора в темі немає — задача звужена до bounded frontend-доповнення. ⚠ Ключова знахідка addendum: `ocp5_product_option` порожня для всіх 60 товарів, тобто блок `{% if options %}` у `product.twig` ніколи не рендерився на цьому магазині — це неперевірена розмітка, а не робочий UI; закладати первинну верстку і QA. Префікс БД — `ocp5_`, одна мова (`language_id = 4`). Заведено 01.08, скоуп лише 3D-P, без пріоритету, хендофу немає. Notion: `3af6bf20-bdb4-8119-8158-dccb93c0e5b0` |
| 3D-P-012 | Короткі відео товару (~5/10/15 с) на сторінці 3D-товару окрім фото | Немає хендофа/плану — discovery-стадія. Заведено 01.08, скоуп лише 3D-P, незалежно від 3D-P-011, без пріоритету. Feasibility pre-check (**не перевірено проти живого бекапу**): OC4 core без нативної відео-галереї, ринкові розширення є для 4.x, сумісність із темою `boostershop-ds` не підтверджена. Notion: `3af6bf20-bdb4-819b-bcbd-ff058993dc21` |
| 3D-P-013 | Реструктуризація вкладки «3D-друк» в дашборді (Калькулятор/Вироби/Інформація) | handoffs/handoff_3D-P-013_dashboard-tab-restructure_20260802.md (замінює плоский layout 3D-P-006; формула рекомендованої РРЦ — TBD, окремо; ⚠ початково заведено як 3D-P-011, перенумеровано через колізію ID з 01.08) |
| 3D-P-CARDCONTENT | Наповнення карток товару + категорій для 3D-друкованих SKU (назви, описи, значення атрибутів по SKU) — заведено 2026-08-06 за вказівкою власника під час роботи над LEGAL-002b-3DP; власник опрацює в окремому діалозі. ⚠ Спочатку помилково створено як «3D-P-015» — цей номер вже зайнятий задачею price-model-rebuild (2026-08-03, є page_id в ROADMAP_SOP.md); перейменовано 2026-08-06 на нечисловий ярлик до вирішення прогалини 3D-P-009/наступного вільного номера | 2026-08-07: SKU-конвенція + формула SEO-назв затверджені й зафіксовані — `plans/3D-P_sku-naming-convention_20260807.md` (канонічна версія, репо/Claude Code). Самодостатній хендоф для ChatGPT — `handoffs/handoff_3D-P_sku-naming-principle_chatgpt_20260807.md` (той самий принцип без залежності від репо-шляхів, повна таблиця вже узгоджених SKU/назв). Залежить від 3D-P-002 (структура категорій) і від хендофу атрибутів LEGAL-002b-3DP (Частина IV/V) як вхідних даних. 2026-08-16: **єдиний ChatGPT-документ — `handoffs/handoff_3D-P-CARDCONTENT_chatgpt-master_20260816.md`** (артикули й назви, наповнення картки, категорійні сторінки, вимоги до скіла). Він заміщає три попередні ChatGPT-документи: `handoff_3D-P_sku-naming-principle_chatgpt_20260807.md`, `handoff_3DP-CARDCONTENT_chatgpt-content-brief_20260806.md` і `handoff_3D-P-CARDCONTENT_card-content-rules_chatgpt_20260816.md` — усі три мають банер заміщення в шапці й лишаються лише як історія. Рішення власника від 13.08 внесені в канон: категорія `BR- 4__` (брелок-спінер, `BR-DITTO-200` → `BR-DITTO-400`) — доповнення в `plans/3D-P_sku-naming-convention_20260807.md`; матеріал PLA, маса без фурнітури, Mystery Box завжди Так/Ні, `Сумісність`/`Магніти` не для брелоків — доповнення на початку `handoffs/handoff_LEGAL-002b-3DP_3d-attribute-schema_20260806.md`. Рев'ю раундів 2–3 (п'ять Pokémon-брелоків, з фотографіями товару) — `diagnostics/3D-P-CARDCONTENT_chatgpt-draft-review_20260816.md`; звідти рішення про колір, фурнітуру, латиницю в SEO URL і порядок осей у розмірах. Готові до виконання: `handoffs/handoff_3D-P-CARDCONTENT_five-pokemon-keychains_20260816.md` (п'ять Pokémon-брелоків, фінальні тексти, не переписувати) і `handoffs/handoff_3D-P-002_subcategories-and-content_20260816.md` (чотири підкатегорії з текстами; замінює `plans/3D-P-002_catalog-placement-admin-guide_20260731.md` у частині структури — рішення власника 16.08: каталог ділиться за предметом, один товар = одна категорія). Масштабування наповнення за межі поточних партій блокує `SEO-008` (семантичне ядро). Notion: `3b46bf20-bdb4-8120-a5c3-e51163edf547` · **2026-08-19: 19 товарів заведені на проді** (id 125–143), усі невидимі, ціна-заглушка 1 грн, `sort_order = 8`, подвійна прив'язка підкатегорія + материнська. Атрибути 50–55 у групі 10. Повний стан, борги й що лишилось — `handoffs/handoff_3D-P_session-continuation_20260819.md`. Рев'ю патчів — `diagnostics/3D-P-002_3D-P-CARDCONTENT_patch-review_20260818.md`. Блокер продажу: передзамовлення (`config_stock_checkout = 0`). |
| 3D-P-023 | Час у журналі синхронізації показується в UTC, хоча колонка підписана «Київ» | Знайдено 08.08 під час QA 3D-P-014. Дані правильні, кривий лише вигляд: записаний київський рядок Sheets автоматично перетворює на дату, і читання повертає ISO UTC. Пріоритет низький. Notion: `3b66bf20-bdb4-81da-8569-f1d54a8d94b1` |
| 3D-P-014 (rev 2, 08.08 — **Done**) | Зробити збої синхронізації CRM→3D-P видимими | **Читати:** `handoffs/handoff_3D-P-014_sync-failure-visibility_20260803.md` — блок «REVISION 2026-08-08» на початку файлу є чинним, перша редакція згорнута як історія. Журнал перенесено з 3D-P у **основну CRM** (прихована вкладка `_Журнал_3DP_синхронізації`, локальний запис, read-дія `sync_journal`), бо журнал у 3D-P не міг зафіксувати недоступність самого 3D-P API. Розподіл: `_Аудит_API` = що сталося в таблиці, журнал CRM = що вирішив хук |
| 3D-P-010 WP4 | Підчепити третій шлях запису `updateSaleStatus()` — саме через нього синхронізація CRM→3D-P не працює | **Читати:** `handoffs/handoff_3D-P-010-WP4_updatesalestatus-hook_20260808.md` (08.08, написано по доведених якорях коду з `crm/apps-script/Code.gs`). Один виклик у `updateSaleStatus()`; аргументи вже в скоупі. ⚠ Застосовувати СУВОРО ПІСЛЯ деплою 3D-P-014 — той самий файл, інакше паралельні писачі. Окремий патч-файл, не зливати з 014 |
| 3D-P-015 (rev 2, 08.08) | Перебудова цінової моделі навколо фактичної РРЦ + ціна під викуп + посилання на модель; заморожування собівартості й РРЦ у рядок продажу; перебудова «Аналітики» | **Читати:** `handoffs/handoff_3D-P-015_price-model-rebuild_20260803.md` — блок «⚠ РЕВІЗІЯ 2026-08-08 (rev 2)» на початку файлу є чинним і перекриває тіло від 03.08 там, де вони розходяться. Нові колонки `Номенклатура` = `Q`/`R`/`S` після технічних `O`/`P` (рішення D1); нові заморожені колонки `Продажі` = `U`/`V`/`W` після технічної `T` (рішення 08.08). Схемна частина фурнітури заїжджає сюди (рішення F8): прибрати `+ N` з формули `Номенклатура!K`, `N` лишається довідковою ціною. Проміжне правило до 3D-P-019: платник фурнітури за замовчуванням «власник», вартість із `N`. Також замінює три підміни в дашборді (gap-register §3.2 / C2). Супутнє: `plans/3D-P-019_fixture-payer-model_20260808.md`, `diagnostics/3D-P_gap-register-and-work-plan_20260807.md`, `diagnostics/3D-P_live-schema-audit_20260803.md`. ⚠ Дзеркало основної CRM має незадеплойовані локальні зміни — потрібен свіжий експорт від власника перед патчем хука (`crm/apps-script/SOURCE_STATE.md`). Виконавець: Codex. Notion: `3b16bf20-bdb4-8146-9f06-faaf2b54f67d` |
| 3D-P-024 | Безпечне введення часу друку: `1:39` замість `1,65`, у таблиці, дашборді й сервері Сергія | **Читати:** `handoffs/handoff_3D-P-024_print-time-entry-usability_20260808.md` + звіт `diagnostics/3D-P-024_print-time-entry-usability_report_20260808.md`. Заведено й задеплоєно 08.08. Час скрізь зберігається **десятковими годинами** — одиницю не міняли, бо її читають `Номенклатура!K`, `Аналітика!E/L`, калькулятор партії і сервер Сергія; нормалізація відбувається на вводі. Спільний парсер `3d-print/shared/print-time.js`, у bound Apps Script його логіка **дзеркалиться** (Apps Script не імпортує файли репо) — дві копії можуть розійтися. Перший `onEdit` у цій книзі, обмежений `Номенклатура!G` і `Друк-лог!D`. Попередження поза 0.02–100 год — fail-open. Живо підтверджено власником: `1:39` → `1,65`, поріг спрацював. ⚠ Історичний дефект, який це закриває: у `FIG-CHARM-001` час був введений як час доби, Google зберіг 0.1032 доби, формула прочитала 0,1 год — собівартість була занижена ~у 24 рази непоміченою. Notion: `3b66bf20-bdb4-8132-a8d5-f3078cf95abb` |
| 3D-P-025 | Поле коригування наявності приймає фактичний залишок, а не дельту | **Читати:** `handoffs/handoff_3D-P-025_stock-field-actual-count_20260809.md`. Заведено 09.08 під час QA: власник ввів 99, маючи на увазі «99 на складі», і отримав 196. Журнал коригувань лишається append-only, змінюється лише семантика поля вводу; різниця рахується від свіжого поточного значення на момент відправки. Notion: `3b76bf20-bdb4-81a6-838b-d6a27eff68bc` |
| CRM-005 | Перевірка цілісності основної CRM + правило для структурних змін | **Читати:** `handoffs/handoff_CRM-005_integrity-check-and-rule_20260809.md`. Заведено 09.08 після чергової поломки CRM при додаванні SKU (аркуш РРЦ, рядки 71–75: ціна є, SKU і назви немає). Ключове обмеження власника: перевірка працює **всередині Apps Script** і віддає короткий список проблем, а не вміст аркушів — інакше вилітають токени. Власник має мати змогу запускати її сам з дашборда. Правило `OPS-CRMINTEGRITY` в `AGENTS.md`. НЕ зливати з 3D-P задачами і з `CRM-004`. Notion: `3b76bf20-bdb4-8140-8397-f14d1cc785dd` |
| CRM-006 | Діагностика й ремонт того, що знайшла перевірка цілісності (паси 1-4) | **Читати:** `handoffs/handoff_CRM-006-PASS2-PASS3_price-and-master-active_20260809.md` + `handoffs/handoff_CRM-006-PASS4_formula-restore_20260810.md` (пас 4, виконавець Codex). Діагностики: `CRM-006_bounded-live-diagnosis_report_20260809.md`, `CRM-006_pass1-result-and-master-active-chain_20260809.md`, `CRM-006_pass2-verification_gate_20260809.md`, `CRM-006_pass3_p2-index_report_20260809.md`. Паси 1-3 зняли 150 проблем до 5. ⚠ Пас 4 — усі 5 залишків `formula_column_literal`: `Товари!Коротка назва` (38-39, 49-67, 71-76 літерали; 70 і 77-81 **порожні** — це і блокує CRM-008), `Товари!Поточна ціна продажу` (38-39), `Розхідники` `Надійшло через витрати` (7-15, 17), `Їде через витрати` (6, 8, 10-15, 17), `Використано в продажах` (10-11, 13-15, 17-23). Виконано 10.08, перевірка вперше чиста (`problems: []`, V101): паси 4a і 5, звіти `CRM-006_pass4-formula-restore_20260810.md`, `CRM-006_integrity-manual-short-names_report_20260810.md`, `CRM-006_pass5_formula-preflight_20260810.md`, зведення `CRM_dialogue-actions_summary_20260810.md`. ⚠ ДВІ з пʼяти проблем закриті ВИНЯТКОМ у джерелі (V99/V101) на 15 коротких назв і 11 записів `Використано в продажах` — це доведена ручна історія; у цих 26 записах перевірка більше нічого не ловитиме, виняток іменований, не поколонковий. ⚠ Пас 5 змінив дві живі ціни: `YGO-JP-BDOM-BST` 150 → 90, `YGO-JP-BDOM-BBX` 3000 → 2700, джерело — `РРЦ`, підтверджено власником 10.08. ⚠ Хендофф пасу 4 забороняв правити `Code.gs` і публікувати Web App — це сталось за живою авторизацією власника; враховувати при читанні хендофу. ⚠ Fill-down ЗАБОРОНЕНО — літерал може збігатися з результатом формули й усе одно бути структурно хибним. ⚠ Формули жодної з цих колонок ніде не записані — виконавець зчитує еталон з живого аркуша або зупиняється. ⚠ `Розхідники` рядок 8 = `Стікер лого+QR`, живить `getAutoConsumableInfo_()` і собівартість пакування в майбутніх перерахунках продажів |
| CRM-007 | Подвоєна собівартість після роздербану бокса OP-15 + правило FIFO для внутрішніх перенесень | **Читати:** `diagnostics/CRM-COST-SPLIT_OP15-and-MZERO_claude-audit_20260810.md` (аудит) + `handoffs/handoff_CRM-007_op15-split-cost-repair_20260810.md` (хендофф, виконавець Codex). Заведено 10.08 після `OC-FOP-0314`. `LOT-0063` тримає вартість лоту за ДВА бокси при кількості 1, а роздербан скопіював ту саму суму ще раз у `LOT-0119` як 20/24. ⚠ `diagnostics/CRM-OP15-split-cost-audit_report_20260810.md` **скасовано** — не використовувати жодне число звідти, особливо ₴14 150.69. Ключове: `apiIntegrityCheck_` не покриває `Закупки`/`Продажі`/`Склад`/`Списання`, тому чиста перевірка нічого не говорить про собівартість. Правило власника 10.08: внутрішнє перенесення між SKU бере собівартість найстарішої партії (FIFO). Свідомо поза обсягом — старі рядки продажів до 08.05. Notion: `3b86bf20-bdb4-814d-a838-fcd3e218601a` |
| CRM-008 | П'ять SKU стартових колод OP-16 + закупка лоту `yskh293` | **Читати:** `handoffs/handoff_CRM-008_starter-decks-sku-and-purchase_20260810.md`. Заведено 10.08. Лот `yskh293` (Mercari `m15056144167`): 1 × `OP-JP-OP16-BBX` (SKU уже існує) + 5 нових колод. Рішення власника 10.08: розподіл ₴4 257 = бокс ₴3 000 + по ₴251.40 на колоду; новий формат `Starter Deck`/`STD`; SKU `OP-JP-ST32-STD`…`OP-JP-ST36-STD` без суфікса персонажа; РРЦ ₴700. ⚠ Дві структурні зміни в `Налаштування` (формат + коди сетів ST-32…ST-36) → `OPS-CRMINTEGRITY` діє повністю. ⚠ НЕ ремонтувати `Товари!B/J` — це пас 4 у `CRM-006`. ⚠ Форма закупки бере максимум 3 позиції → два внесення, ¥800 ділиться ¥400/¥400. Виконано 10.08 після зняття блокера пасом 4a: `Товари!77:81` + `РРЦ` + `Майстер_Товарів!75:79`, закупка `Закупки!126:131` (`LOT-0131`…`LOT-0136`), товар 4 257.00 грн, японська комісія 228.58 грн (округлення 38.10/38.10/38.09 у двох тризначних внесеннях). ⚠ ПРИЙНЯТЕ ЗАСТЕРЕЖЕННЯ: формула складу не рахує статус `Замовлено` як очікуване, тому пʼять колод показують нуль в очікуваному, поки лот не піде в дорогу; формулу свідомо не чіпали. ⚠ Довелось розширити кінці діапазонів валідації: `Товари!F3:F201` → `Налаштування!AD4:AD44`, `G3:G201` → `J4:J15`. Далі — `CONTENT-005`. ⚠ ІСТОРІЯ: 10.08 перший захід ЗУПИНЕНО НА PREFLIGHT, жодного запису не зроблено: `Товари!B77:B81` без формули, тому 5 SKU не завести без нових дефектів цілісності — див. `diagnostics/CRM-008_starter-decks-sku-and-purchase_report_20260810.md`. Розблокує `CRM-006` пас 4 (`handoffs/handoff_CRM-006-PASS4_formula-restore_20260810.md`). Блокує тільки колонка `B`; `Товари!J77:J81` формулу зберігають. ⚠ ЗНАЙДЕНО ЖИВЦЕМ 10.08, у хендофі цього немає: джерела списків — `Налаштування!J4:J14` (формати) і `AD4:AD39` (сети), тож `Starter Deck` і `ST-32…ST-36` потребують ще й розширення діапазонів валідації; хендофф треба доповнити перед другим заходом. Rollback-копія: `10 серпня, 15:01 До 008`. Notion: `3b86bf20-bdb4-8129-bddf-e002b9e8cd87` |
| CONTENT-005 (**Done** 10.08, сторінки поки сховані) | Картки товару для 5 стартових колод OP-16 + заливка на сайт | **Читати:** `plans/CONTENT-005_starter-deck-cards_final_20260810.md` (фінальний текст, джерело істини для патча) + `handoffs/handoff_CONTENT-005_claude-code-publish-starter-decks_20260810.md` (хендофф на публікацію). Історія: `handoffs/handoff_CONTENT-005_chatgpt-starter-deck-cards_20260810.md` (бриф) → `diagnostics/CONTENT-005_chatgpt-draft-review-round1_20260810.md` (рев'ю раунду 1, вердикт Return for changes) → раунд 2 доробив Claude на прохання власника. Усі 5 офіційних сторінок Bandai прочитані 10.08: дата релізу, склад, рідкості, офіційні назви підтверджені; недоведені ігрові твердження по ST-35/ST-36 прибрані. ⚠ Раунд 2 писав і перевіряв той самий агент — незалежного рев'ю немає, останній гейт власник. ⚠ Бокс `OP-JP-OP16-BBX` уже має живу активну сторінку в статусі передзамовлення — поза обсягом. Аналог: `plans/new-products-6-skus-2026-06-04.md`. Notion: `3b86bf20-bdb4-81d6-acad-dc7d32b55500` |
| 3D-P-016 | Мінімальна беззбиткова ціна + контроль знижки (Модель B: собівартість Сергія + витрати магазину). Заведено 2026-08-07 з аудиту прогалин, було в первинному ТЗ §5.4-5.5 і ніколи не побудовано | **Читати:** `diagnostics/3D-P_gap-register-and-work-plan_20260807.md` §3.1 G5. Блокується 3D-P-015. Контроль інформаційний, не блокуючий (немає ліміту знижки — рішення 31.07). Notion: `3b56bf20-bdb4-8173-92ad-ca8a9a91d8e8` |
| 3D-P-017 | Повернення як окрема фінансова операція. Заведено 2026-08-07, було в §5.6 і ніколи не побудовано — сьогодні повернення лишає нарахування Сергію в силі | **Читати:** той самий gap-register §3.1 G6. Рішення власника 2026-08-07: відкритий період → зменшує поточне нарахування; виплачений → мінусове коригування наступного. Продаж не видаляється. Блокується 3D-P-015 + 3D-P-014. Notion: `3b56bf20-bdb4-81e4-863e-f7cddaeb752e` |
| 3D-P-018 | Зона «Виробництво» (Друк-лог) у дашборді власника. Заведено 2026-08-07: API вже має `3dp_print_log`/`_update`/`_archive`/`_restore`, дашборд не викликає жодної | **Читати:** gap-register §3.1 G9. Технічних блокерів немає, після owner QA 3D-P-013. Notion: `3b56bf20-bdb4-8103-99ff-cea734c92408` |
| 3D-P-019 | Фурнітура: хто оплатив (власник / Сергій). Нова вимога власника — частину фурнітури купує власник, частину Сергій | **Читати:** `plans/3D-P-019_fixture-payer-model_20260808.md` (дизайн-нота 08.08). Знайдено діючий фінансовий дефект: `Номенклатура!K` уже включає `+ N` (фурнітуру) у собівартість Сергія без платника → фурнітура, куплена власником, компенсується Сергію і спотворює базу 50/50. Схемну частину рекомендовано вести всередині 3D-P-015 (одна міграція замість двох); операційна частина (лоти `Розхідники` з платником, багаторядковий ввід у формах замовлення/списання, закупівлі Сергія через його сервер + підтвердження власником) лишається тут. Спирається на Addendum від 02.08 у хендофі 3D-P-010. Notion: `3b56bf20-bdb4-81f6-8f8a-e4c5842ede7e` |
| 3D-P-020 | Витрата Track-2 (викуп у Сергія + фурнітура) в загальну статтю «Маркетинг» **основної CRM**. Заведено 2026-08-07, закриває відкрите питання 3D-P-004 | Крос-системна: ціль — основна CRM, не 3D-P таблиця. Структуру витрат основної CRM під це ще не дивились. Notion: `3b56bf20-bdb4-8182-8982-e795fde4e9dd` |
| 3D-P-021 | Чистка демо/тестових даних у живій таблиці: `ПРИКЛАД-001` у 6 вкладках (тягнеться в підсумки — Наявність 2 шт, Виплати 165 ₴) + тестова наявність FIG-CHARM-001 (3 шт при 0 продажів) | Рішення власника 2026-08-07: чистити все, спершу іменована версія Sheets як відкат; наявність — тільки через ledger додатка #2. Робити ДО 3D-P-015. Notion: `3b56bf20-bdb4-8125-8e81-eb68f946b69a` |
| 3D-P-022 | Тригерний регекс SKU у CRM суперечить канонічній конвенції — вся родина `ACC-3D-` ніколи не синхронізується. **Передумова QA 3D-P-014** | **Читати:** `handoffs/handoff_3D-P-022_sku-trigger-convention-alignment_20260808.md`. Три розбіжні визначення SKU: тригер CRM, валідатор форми в дашборді, канонічна конвенція. Тригер робимо ліберальним, форму створення — строгою під канон, плюс outcome `skipped_sku_shape`. Нічого не перейменовуємо | Знайдено 08.08 під час owner QA 3D-P-014. `is3dpPackagingSku_` чекає `ACC-3D-` + три цифри, канон (`plans/3D-P_sku-naming-convention_20260807.md`) — `ПРЕФІКС-МНЕМОНІКА-XYZ`. Регекс від 02.08, конвенція від 07.08, ніхто не звіряв. `BR-`/`FIG-` не зачеплені. Журнал покаже `skipped_no_3dp_sku` — оманливо; варто окремий `skipped_sku_shape`. Notion: `3b66bf20-bdb4-81b0-ad36-d2e9fb81cb52` |
| OPS-CODEMIRROR (Done 08.08) | Дзеркало живого Apps Script коду обох проєктів у репо, щоб виконавець не вгадував версію | **Читати перед будь-якою задачею, що торкається Apps Script:** `crm/apps-script/SOURCE_STATE.md` (дата вивантаження, версія, перевірені якорі). Дзеркала: `crm/apps-script/Code.gs` (основна CRM, експорт 08.08 11:41) і `3d-print/apps-script-3dp-api/Code.gs` (3D-P, підтверджено побайтово ідентичним живому). Правило — розділ «Apps Script mirrors» у `AGENTS.md`. CSV від 29.07 лишається лише як історія. Notion: `3b66bf20-bdb4-81f8-8ccb-c58955892365` |
| CRM-004 | Валідації в **основній** CRM: `Паковання` dropdown тягне назву товару в список; нові SKU дають `Недійсне значення` | З Finding 10 у `diagnostics/3D-P_live-schema-audit_20260803.md` (датовано 04.08). Помилки конфігурації, не скрипта. НЕ складати з 3D-P-014/015. Notion: `3b56bf20-bdb4-812c-99a8-ceb7d3ee89fd` |

**СТАРТ НОВОЇ СЕСІЇ ПО КОНТЕНТУ — читати першим:**
`handoffs/handoff_CONTENT-QUALITY_session-continuation_20260822.md` (чинний, 22.08).
Стан: тексти 19 карток 3D і 4 категорій переписані й задеплоєні, 9 нових товарів
(id 144–152) заведені, атрибут 44 перейменований. Усі 28 товарів невидимі.
Замінює `handoffs/handoff_3D-P_session-continuation_20260819.md`.

**СТАРТ НОВОЇ СЕСІЇ ПО АВТОМАТИЗАЦІЇ 3D-P (CRM, ціни, дашборд) — читати першим:**
`handoffs/handoff_3D-P_session-continuation_20260809.md` (чинний, 09.08). Стан:
цінова модель задеплоєна й мігрована на живій таблиці, скрипти CRM V95 і
3D-P V10 звірені з дзеркалами. Готові хендофи: `3D-P-019` (фурнітура,
наступна в роботі), `CRM-005` (перевірка цілісності CRM), `3D-P-025` (поле
наявності). Розблоковані без хендофу: `3D-P-016`, `3D-P-017`.

Попередній брифінг (історія): `handoffs/handoff_3D-P_session-continuation_20260808.md`. Стан на кінець 08.08:
закрито `3D-P-006/010/014/021/022/023`, синхронізація CRM→3D-P працює вперше
(усі три шляхи запису підчеплені й доведені живим QA). Наступна задача —
`3D-P-015`; її хендоф **зревізовано 08.08 (rev 2)** і він чинний — читати блок
«⚠ РЕВІЗІЯ 2026-08-08» на початку файлу перед тілом від 03.08 (колонки Q/R/S за
рішенням D1; колонки U/V/W у «Продажах»; схемна частина фурнітури за рішенням
F8; 021 уже виконано; підміни в дашборді). Там же — пастка трьох каталогів SKU,
невиконані перевірки і відкриті рішення власника.

**Пастка трьох каталогів (кожен новий 3D-SKU):** товар має бути у трьох місцях —
`Товари` (CRM), `Майстер_Товарів` з `Активний = так` (окремий файл
«Booster Shop — Майстер-дашборд автоматизацій») і `Номенклатура` (3D-P).
Найчастіше забувають друге: рядок є, але `Активний` порожній — і товар не
зʼявляється у списку на сторінці «Облік».

**Аудит стану 3D-P (2026-08-07):** два канонічні документи —
`diagnostics/3D-P_state-audit_20260807.md` (що реально задеплоєно, що застаріло,
розсинхрон Notion/дашборд/git) і
`diagnostics/3D-P_gap-register-and-work-plan_20260807.md` (реєстр прогалин
«домовлено vs реалізовано», 17 пакетів робіт, порядок виконання, відкриті
рішення власника). Обидва містять виправлення до Finding 4 попереднього
schema-audit — воно було помилковим.

---

## Notion / дашборд — де що

**Усі серії (ST + DASH/CRM/AUTO/TECH/RD/UX)** тепер у Notion database `35c3f857-2fc5-4a78-96c8-af0efd4cf8d4`. ST заведено 2026-06-24.
**Статус-правда — Notion; дашборд `ROADMAP_FLOW` — дзеркало.** Повні правила, page_id-реєстр, DoD, sync — `ROADMAP_SOP.md`.

Notion view: `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4?v=eebb19b11cfb4066a8a3b1b097775818`
Bulk-query (`notion-query-data-sources` / `notion-query-database-view
