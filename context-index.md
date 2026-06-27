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
| ST-2c | Переключення всіх клієнтів на новий чекаут | — | — |
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
| RD-01/02/03 | Shell DS parity | handoffs/handoff_RD-01-02-03_shell-ds-parity_2026-05-30.md |

---

## TECH-серія — SEO / Технічне

| Roadmap ID | Назва | Handoff | Diagnostics |
|---|---|---|---|
| TECH-005-DEEP | Sitemap GSC — Watch Only | handoffs/handoff_TECH-005-DEEP_sitemap-binary-serving_2026-06-05.md | diagnostics/TECH-005-DEEP_*.md |
| TECH-010/012 | Noindex / canonicals / дублі URL | handoffs/handoff_TECH-010-012_noindex-canonicals_2026-06-09.md | diagnostics/indexation-status-and-sitemap-sync_2026-06-15.md |
| TECH-029 | Sitemap / GSC — site-side | handoffs/codex-handoff-TECH029-sitemap.md | — |
| TECH-030/031 | — | handoffs/codex-handoff-TECH030-031.md | — |
| TECH-032/033/034 | — | handoffs/codex-handoff-TECH032-033-034.md | — |

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

| Roadmap ID | Назва | Handoff |
|---|---|---|
| NCRM-00 | Архітектура + аудит фінмоделі + schema v1 (Done) | plans/crm-* (вище) |
| NCRM-01 | Supabase проєкт + SQL-міграції + типи | — |
| NCRM-02 | Repository-шар + Next.js скелет + emulator | — |
| NCRM-03 | Імпорт історії зі Sheets + звірка KPI | — |
| NCRM-04 | Read-екрани (summary/замовлення/склад/SKU/клієнти) | — |
| NCRM-05 | Write-форми + FIFO-COGS | — |
| NCRM-06 | Витрати + P&L + KPI-вʼюхи | — |
| NCRM-07 | Order pipeline OpenCart→Supabase + smoke | — |
| NCRM-08 | Курси валют (фетч + заморозка) | — |
| NCRM-09 | Mobile-версія + поліш | — |

---

## Інші задачі

| Roadmap ID | Назва | Handoff |
|---|---|---|
| CHECKOUT-001 | Реєстрація акаунту при замовленні | — |
| CAT-002 | Нова категорія «Аксесуари» | — |
| LEGAL-002 | Публічна оферта + Обмін і повернення | — |
| BRAND-OUTLET-001 | Outlet Booster — опис і SEO | — |
| R-13.5 | НП модуль — master log (ST-серія) | handoffs/handoff_R-13.5_nova-poshta-module_2026-06-12.md |

## MKT-TG — Telegram контент-автоматизація

| Roadmap ID | Назва | Handoff / Plan |
|---|---|---|
| MKT-TG-003 | Make TG-пайплайн: фікс RSS→jina→Claude→GPT→Telegram (Done) | handoffs/MKT-TG-003_make-pipeline-status_20260627.md, handoffs/MKT-TG-003_make-pipeline-handoff_20260626.md |
| MKT-TG-004 | TG контент-автоматизація Phase 2 (мультиджерело+бот+картинки+розклад) | plans/tg-content-automation-phase2-plan_2026-06-27.md |

---

## Notion / дашборд — де що

**Усі серії (ST + DASH/CRM/AUTO/TECH/RD/UX)** тепер у Notion database `5aef22c3-048d-4dde-a5b1-ad409de9301c`. ST заведено 2026-06-24.
**Статус-правда — Notion; дашборд `ROADMAP_FLOW` — дзеркало.** Повні правила, page_id-реєстр, DoD, sync — `ROADMAP_SOP.md`.

Notion view: `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4?v=eebb19b11cfb4066a8a3b1b097775818`
Bulk-query (`notion-query-data-sources` / `notion-query-database-view`) — Business plan, недоступно. Per-card: `notion-fetch` / `notion-update-page` за page_id.
