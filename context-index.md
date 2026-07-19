# Context Index — Booster Shop

> **Мапа `ID → handoff → diagnostics`.** Статусу тут НЕМАЄ — статус лише в Notion (див. `ROADMAP_SOP.md §1`).
> page_id рядків Notion — `ROADMAP_SOP.md §5`. Оновлювати при новому хендофі/діагностиці.

## Як користуватись

При старті задачі `ST-X.X` — одразу grep по цьому файлу:

```bash
grep "ST-3.5" context-index.md
```

Видає: handoff-файл і наявність діагностики. Статус/scope — у Notion-картці (page_id у `ROADMAP_SOP.md §5`) та `ROADMAP_FLOW` дашборда.

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
| ACC-001 | Меню кабінету: дубль на десктопі, без «Вихід» на мобайлі | handoffs/handoff_ACC-001_account-menu-dedup-logout_20260713.md | — |
| ACC-002 | NP-форма адреси в акаунті замість стокової free-text | handoffs/handoff_ACC-002_account-np-address-form_20260713.md | — |
| ST-2c | Переключення всіх клієнтів на новий чекаут | handoffs/handoff_ST-2c_real-shipping-cost-content-sweep_20260718.md (реальна вартість + текстовий свіп, поріг 2000 грн) + handoffs/handoff_ST-2c_real-shipping-cost-cutover_2026-07-02.md (§url.php cutover — окремий пізніший крок) | — |
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
| TECH-013 | Mobile Core Web Vitals pass (об'єднує TECH-002/003/004) | handoffs/handoff_TECH-013_mobile-core-web-vitals_20260716.md | — |
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
| NCRM-10 | Order pipeline OpenCart→Supabase + smoke — колишній зміст NCRM-07; 2026-07-18 In progress, scope підтверджено (лише нові замовлення, hook-доступ є) | handoffs/handoff_NCRM-10_order-pipeline-opencart-supabase_20260718.md |
| NCRM-11 | Курси валют (фетч + заморозка) — перенумеровано з NCRM-08, зміст той самий | — |
| NCRM-12 | Mobile-версія + поліш — перенумеровано з NCRM-09, зміст той самий | — |

---

## Інші задачі

| Roadmap ID | Назва | Handoff |
|---|---|---|
| PAY-001 | Monobank Покупка Частинами — інтеграція оплати частинами (Phase 1 sandbox, In progress) | handoffs/handoff_PAY-001_monobank-chastyny-integration_20260718.md |
| PAY-001-UI | Візуальний дизайн-бриф для Claude Design: кнопка + модалка «Купити в кредит» + стани чекауту | handoffs/handoff_PAY-001-UI_visual-design-brief_20260718.md |
| CHECKOUT-001 | Реєстрація акаунту при замовленні (Done) | handoffs/handoff_CHECKOUT-001_phase1_guest-account-creation_2026-07-04.md |
| CHECKOUT-002 | Швидкість оформлення + редизайн loader | — |
| CAT-002 | Категорії + аксесуари (parent) | — |
| CAT-002-4 | YGO Blazing Dominion SKU | plans/ygo_sku_blazing_dominion_20260628.md |
| CAT-002-5 | Тайлс категорій — кольори і HTML | plans/category_tiles_colors_20260628.md |
| CAT-002-5b | Бургер-меню нові категорії + фікс URL | handoffs/handoff_CAT-002-5b_burger-menu-new-categories_20260628.md |
| LEGAL-002 | Публічна оферта + Обмін і повернення | — |
| BRAND-OUTLET-001 | Outlet Booster — опис і SEO | — |
| R-13.5 | НП модуль — master log (ST-серія) | handoffs/handoff_R-13.5_nova-poshta-module_2026-06-12.md |

## MKT-TG — Telegram контент-автоматизація

| Roadmap ID | Назва | Handoff / Plan |
|---|---|---|
| MKT-TG-003 | Make TG-пайплайн: фікс RSS→jina→Claude→GPT→Telegram (Done, superseded by MKT-TG-005) | handoffs/MKT-TG-003_make-pipeline-status_20260627.md, handoffs/MKT-TG-003_make-pipeline-handoff_20260626.md |
| MKT-TG-004 | TG контент-автоматизація Phase 2 (мультиджерело+бот+картинки+розклад) — Make-підхід, superseded by MKT-TG-005 | plans/tg-content-automation-phase2-plan_2026-06-27.md |
| MKT-TG-005 | Path A: lean RSS→Telegram news digest (заміна Make-пайплайну, on-demand AI-чернетка) | handoffs/MKT-TG-005_path-A-lean-rss-digest_20260703.md, handoffs/MKT-TG-005_codex-handoff_20260703.md |
| MKT-TG-006 | /post <url> — OpenAI-чернетка за посиланням, паралельно до RSS-дайджесту | handoffs/MKT-TG-006_codex-handoff_openai-url-draft_20260704.md |

---

## Notion / дашборд — де що

**Усі серії (ST + DASH/CRM/AUTO/TECH/RD/UX)** тепер у Notion database `5aef22c3-048d-4dde-a5b1-ad409de9301c`. ST заведено 2026-06-24.
**Статус-правда — Notion; дашборд `ROADMAP_FLOW` — дзеркало.** Повні правила, page_id-реєстр, DoD, sync — `ROADMAP_SOP.md`.

Notion view: `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4?v=eebb19b11cfb4066a8a3b1b097775818`
Bulk-query (`notion-query-data-sources` / `notion-query-database-view
