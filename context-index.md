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
| CHECKOUT-009 | **P0 (2026-07-29):** чекаут не реєструє обрану доставку — гість не може оформити замовлення взагалі (кнопка підтвердження заблокована при повністю заповненій доставці), у авторизованого клієнта той самий симптом обходиться вибором іншої збереженої адреси; кредитний спосіб оплати теж заблокований гейтом «заповніть дані отримувача і адресу доставки». Рішення власника 29.07: без латок і без відкату — Codex робить глибокий архітектурний аудит чекауту (карта архітектури + усі гейти + археологія патчів), доводить корінну причину, подає варіанти (точкові виправлення vs консолідація костилів у нормальні процеси); реалізація лише після вибору власника. Орієнтир-гіпотеза — раунди ST-2c 28–29.07 (checkout-state.js / shipping_method.twig / checkout-reskin.js), не доведено | **Читати першим:** handoffs/handoff_CHECKOUT-009_shipping-selection-not-registered_20260729.md (хендофф-аудит) → plans/CHECKOUT-009_checkout-architecture-map_20260729.md (карта архітектури + корінна причина) → plans/CHECKOUT-009_checkout-behaviour-register_20260729.md (реєстр 40 поведінок + інвентар маркерів) → plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md (варіанти A/B/C) → diagnostics/CHECKOUT-009_shipping-selection-not-registered_report_20260729.md (звіт Codex) → diagnostics/CHECKOUT-009_audit_review_20260729.md (рев'ю Claude: аудит прийнято, Option C, 3 блокуючі умови) → **handoffs/handoff_CHECKOUT-009_stage1_coupon-classification-single-writer_20260729.md** (власник обрав Option C 29.07; авторизовано Stage 1, Stage 2 — ні) | Причина: coupon.summary класифікується як мутація (checkout-reskin.js:391 → couponChanged), збиває збереження доставки. Пов'язані: patches/ST-2c_coupon_shipping_threshold_refresh_validated_20260728.php, patches/ST-2c_minicart_shipping_requote_20260728.php, patches/ST-2c_minicart_shipping_threshold_alignment_20260729.php |
| CHECKOUT-010 | Консолідація стану чекауту — Stage 2 після CHECKOUT-009 (знижка First15 → явна серверна дія, `coupon.summary` → справжній запит на читання, міграція відповідальності з прихованих полів у чотири процеси) + 4 відкладені дрібниці з рев'ю Stage 1. Не стартувала, потребує окремої авторизації власника | **Читати першим:** plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md (Option B / Stage 2) → plans/CHECKOUT-009_checkout-behaviour-register_20260729.md (контракт збереження, ціль 25/15/0) → diagnostics/CHECKOUT-009_stage1_review_20260729.md + diagnostics/CHECKOUT-009_stage1_review_round2_20260729.md (відкладені пункти) | Хендофа ще немає |
| ACC-001 | Меню кабінету: дубль на десктопі, без «Вихід» на мобайлі | handoffs/handoff_ACC-001_account-menu-dedup-logout_20260713.md | — |
| ACC-002 | NP-форма адреси в акаунті замість стокової free-text | handoffs/handoff_ACC-002_account-np-address-form_20260713.md | — |
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
| 3D-P-002 | Розміщення в каталозі — підкатегорія «Фігурки» (Pokémon) | plans/3D-P-000_scoping-and-architecture_20260728.md §4 (SEO risk-gated) · plans/3D-P-002_catalog-placement-admin-guide_20260731.md (виконавчий гайд, у роботі) |
| 3D-P-003 | Аналіз ринкових РРЦ і розмірів | plans/3D-P-000_scoping-and-architecture_20260728.md §6 (1 приклад, недостатньо) |
| 3D-P-004 | Маркетингові «плюшки» — потік закупівлі/бонусів | plans/3D-P-000_scoping-and-architecture_20260728.md §5 |
| 3D-P-005 | Майбутній модуль у NCRM (вузький доступ другу) | plans/3D-P-000_scoping-and-architecture_20260728.md §5, узгоджено з NCRM-18 |
| 3D-P-006 | Owner dashboard tab («3D-друк» в booster-dashboard.html) + калькулятор | handoffs/handoff_3D-P-006_owner-dashboard-tab_20260731.md (FINAL calculator spec 2026-08-02; блок доти, поки не відвантажиться addendum з 3D-P-008) |
| 3D-P-007 | Локальний сервер Сергія (споживач API з 3D-P-008) | handoffs/handoff_3D-P-007_serhiy-local-server_20260731.md (оновлено 2026-08-02 під batch/брак-модель) |
| 3D-P-008 | Apps Script API foundation (read+write) + реконсиляція таблиці | handoffs/handoff_3D-P-008_apps-script-api-foundation_20260731.md (базова частина Done/deployed 01.08; addendum «Schema correction, 2026-08-02» на початку файлу — фінальна формула, ще не імплементована) · diagnostics/3D-P-008_apps-script-api-foundation_report_20260801.md · diagnostics/3D-P-008_reconciliation_diff_20260801.md (очікує owner approval) |
| 3D-P-010 | Автопідтяжка вартості паковання + фурнітури з основної СРМ у 3D-P Продажі | handoffs/handoff_3D-P-010_crm-packaging-cost-pull_20260802.md (розширено 02.08 фурнітурою; CRM risky zone, Фаза 0 = дослідження перед імплементацією) |
| 3D-P-011 | PDP-селектор характеристик (розмір тощо) для мультиваріантних 3D-товарів («Onyx 21/15см») | ROADMAP_SOP.md §3D-P series (додано 01.08, окрема сесія; discovery stage, хендофу Кодексу ще немає) |
| 3D-P-012 | Короткі відео товару (~5/10/15с) на сторінках 3D-товарів | ROADMAP_SOP.md §3D-P series (додано 01.08, окрема сесія; discovery stage) |
| 3D-P-013 | Реструктуризація вкладки «3D-друк» в дашборді (Калькулятор/Вироби/Інформація) | handoffs/handoff_3D-P-013_dashboard-tab-restructure_20260802.md (замінює плоский layout 3D-P-006; формула рекомендованої РРЦ — TBD, окремо; ⚠ початково заведено як 3D-P-011, перенумеровано через колізію ID з 01.08) |
| 3D-P-011 (дубль, потребує звірки) | Вибір характеристики товару (розмір) + UI сторінки товару для мультихарактеристичних 3D-товарів (тригер: Онікс 21 см / 15 см) | **Читати:** diagnostics/3D-P-011_native-variant-feasibility_report_20260806.md (проти живого cPanel-бекапу: нативна OpenCart 4.1 master/variant фіча активна на бекенді, фронтенд-селектора в темі немає — задача звужена до bounded frontend-доповнення). Скоуп лише 3D-P, без пріоритету. ⚠ Рядок вище (§3D-P series) вже містив запис 3D-P-011 з іншим формулюванням до цього редагування — рядки не звірені, потребують консолідації власником |
| 3D-P-012 (дубль, потребує звірки) | Короткі відео товару (~5/10/15 с) на сторінці 3D-товару окрім фото | Немає хендоффа/плану — заведено 2026-08-06, discovery-стадія. Скоуп лише 3D-P, незалежно від 3D-P-011. Без пріоритету. Feasibility pre-check (не перевірено проти живого бекапу): OC4 core без нативної відео-галереї, ринкові розширення є для 4.x, сумісність із boostershop-ds темою не підтверджена. ⚠ Рядок вище (§3D-P series) вже містив запис 3D-P-012 до цього редагування — потребує консолідації власником |
| 3D-P-CARDCONTENT | Наповнення карток товару + категорій для 3D-друкованих SKU (назви, описи, значення атрибутів по SKU) — заведено 2026-08-06 за вказівкою власника під час роботи над LEGAL-002b-3DP; власник опрацює в окремому діалозі. ⚠ Спочатку помилково створено як «3D-P-015» — цей номер вже зайнятий задачею price-model-rebuild (2026-08-03, є page_id в ROADMAP_SOP.md); перейменовано 2026-08-06 на нечисловий ярлик до вирішення прогалини 3D-P-009/наступного вільного номера | 2026-08-07: SKU-конвенція + формула SEO-назв затверджені й зафіксовані — `plans/3D-P_sku-naming-convention_20260807.md` (канонічна версія, репо/Claude Code). Самодостатній хендоф для ChatGPT — `handoffs/handoff_3D-P_sku-naming-principle_chatgpt_20260807.md` (той самий принцип без залежності від репо-шляхів, повна таблиця вже узгоджених SKU/назв). Залежить від 3D-P-002 (структура категорій) і від хендофу атрибутів LEGAL-002b-3DP (Частина IV/V) як вхідних даних. Notion: `3b46bf20-bdb4-8120-a5c3-e51163edf547` |

---

## Notion / дашборд — де що

**Усі серії (ST + DASH/CRM/AUTO/TECH/RD/UX)** тепер у Notion database `35c3f857-2fc5-4a78-96c8-af0efd4cf8d4`. ST заведено 2026-06-24.
**Статус-правда — Notion; дашборд `ROADMAP_FLOW` — дзеркало.** Повні правила, page_id-реєстр, DoD, sync — `ROADMAP_SOP.md`.

Notion view: `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4?v=eebb19b11cfb4066a8a3b1b097775818`
Bulk-query (`notion-query-data-sources` / `notion-query-database-view
