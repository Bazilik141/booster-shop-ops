# Context Index — Booster Shop

> Джерело: ROADMAP_FLOW у дашборді + handoffs/ + diagnostics/
> Оновлювати при кожній зміні статусу або новому хендофі.

## Як користуватись

При старті задачі `ST-X.X` — одразу grep по цьому файлу:

```bash
grep "ST-3.5" context-index.md
```

Видає: handoff-файл, статус, наявність діагностики.

---

## ST-серія — Checkout / Admin / НП

| Roadmap ID | Назва | Status | Handoff | Diagnostics |
|---|---|---|---|---|
| ST-0 | Checkout preflight | done | handoffs/handoff_ST-0_checkout-preflight_2026-06-12.md | handoffs/st0_checkout_preflight_report_20260612.md |
| ST-1 | НП відділення: синк виправлено | done | handoffs/handoff_ST-1_np-warehouse-sync-fix_2026-06-12.md | — |
| ST-2 | Stock/checkout migration | done | handoffs/handoff_ST-2_stock-checkout-migration_2026-06-12.md | — |
| ST-2a1 | Checkout UX fixes | done | handoffs/handoff_ST-2a1_checkout-ux-fixes_2026-06-12.md | — |
| ST-2a2 | Guest blocker / cards / COD | done | handoffs/handoff_ST-2a2_guest-blocker-cards-cod_2026-06-12.md | — |
| ST-2a4 | Order void noise | done | handoffs/handoff_ST-2a4_order-void-noise_2026-06-12.md | — |
| ST-2a7 | Guest captcha exempt | done | handoffs/handoff_ST-2a7_guest-captcha-exempt_2026-06-13.md | diagnostics/st2a7_guest_captcha_exempt_report_20260613.md |
| ST-2a8 | Guest autosave ref gate | done | handoffs/handoff_ST-2a8_guest-autosave-ref-gate_2026-06-13.md | diagnostics/st2a8_guest_autosave_ref_gate_report_20260613.md |
| ST-2a8b | Dropdown click autosave | done | handoffs/handoff_ST-2a8b_dropdown-click-autosave_2026-06-13.md | — |
| ST-2a8c | Autosave bsSaving stuck | done | handoffs/handoff_ST-2a8c_autosave-bsSaving-stuck_2026-06-13.md | — |
| ST-2a9 | Add to cart cold session UX | done | — | diagnostics/st2a9_add_to_cart_cold_session_ux_report_20260613.md, diagnostics/st2a9_auto_review_2026-06-21.md |
| ST-2a10 | gtag guard | done | handoffs/handoff_ST-2a10_gtag-guard_2026-06-13.md | — |
| ST-2b.1–2b.4 | Чекаут — серія UX-фіксів | done | handoffs/handoff_ST-2b1_defer-confirm-draft-orders_2026-06-13.md + ST-2b2/3/4 | diagnostics/st2b1_checkout_smoke_plan_20260614.md |
| ST-2b2 | Success page / Hutko / fiscal spacing | done | handoffs/handoff_ST-2b2_success-page-hutko-fiscal-spacing_20260614.md | — |
| ST-2b3 | Confirm summary / success button | done | handoffs/handoff_ST-2b3_confirm-summary-success-button_20260614.md | — |
| ST-2b4 | Residual draft order / intermediate summary | done | handoffs/handoff_ST-2b4_residual-draft-order-intermediate-summary_20260614.md | — |
| ST-2b.5 | Промокоди, знижка First15 і GA4 | done | handoffs/handoff_ST-2b5_coupon-first15-agree-ga4-parity_20260614.md | — |
| ST-2c | Переключення всіх клієнтів на новий чекаут | todo | — | — |
| ST-3.5 | Кнопка ТТН в адмінці | **active** | handoffs/handoff_ST-3.5_admin-ttn-button_2026-06-24.md | — |
| ST-3.5-1 | Фікс якоря кнопки (OC 4.1.0.3) | todo (підзадача) | ↑ в тому ж хендофі | — |
| ST-3.5-2 | Тест форми заявки НП | todo (підзадача) | ↑ в тому ж хендофі | — |
| ST-6 | Вимкнення старого чекауту | todo | — | — |

---

## RD-серія — Редизайн

| Roadmap ID | Назва | Status | Handoff |
|---|---|---|---|
| RD-04 | Картка товару — дизайн-система | done | handoffs/handoff_RD-04_product-card_thumb-twig_2026-06-01.md |
| RD-06/07 | Empty state / category / breadcrumb | done | handoffs/handoff_RD-06-07_empty-state_category_breadcrumb_2026-06-04.md |
| RD-10 | Сторінка товару — редизайн | done | handoffs/handoff_RD-10_product-page-parity_2026-06-09.md |
| RD-10D2 | Breadcrumb mockup fix | done | handoffs/handoff_RD-10D2_breadcrumb-mockup-fix_2026-06-11.md |
| RD-11 | Редизайн сторінки кошика | todo | — |
| RD-01/02/03 | Shell DS parity | done | handoffs/handoff_RD-01-02-03_shell-ds-parity_2026-05-30.md |

---

## TECH-серія — SEO / Технічне

| Roadmap ID | Назва | Status | Handoff | Diagnostics |
|---|---|---|---|---|
| TECH-005-DEEP | Sitemap GSC — Watch Only | **active** | handoffs/handoff_TECH-005-DEEP_sitemap-binary-serving_2026-06-05.md | diagnostics/TECH-005-DEEP_*.md |
| TECH-010/012 | Noindex / canonicals / дублі URL | todo | handoffs/handoff_TECH-010-012_noindex-canonicals_2026-06-09.md | diagnostics/indexation-status-and-sitemap-sync_2026-06-15.md |
| TECH-029 | Sitemap / GSC — site-side | done | handoffs/codex-handoff-TECH029-sitemap.md | — |
| TECH-030/031 | — | done | handoffs/codex-handoff-TECH030-031.md | — |
| TECH-032/033/034 | — | done | handoffs/codex-handoff-TECH032-033-034.md | — |

---

## CRM / Dashboard

| Roadmap ID | Назва | Status | Handoff |
|---|---|---|---|
| DASH-001 | Огляд — три плитки без даних | done | — |
| DASH-002 | Огляд — Потребують уваги | done | — |
| DASH-WRITE-004 | Облік — редагування записів | done | handoffs/handoff_DASH-WRITE-004_edit-records_20260619.md |
| DASH-001 (new) | Огляд — Summary + warehouse | done | handoffs/handoff_DASH-001_summary-warehouse-asset-fields_20260619.md |
| DASH-PERF-001 | Parallel overview fetch | done | handoffs/handoff_DASH-PERF-001_parallel-overview-fetch_20260615.md |
| DASH-WRITE-001 | Dashboard write UI | done | handoffs/handoff_DASH-WRITE-001_dashboard-write-ui_20260617.md |
| DASH-WRITE-002 | Apps Script write API | done | handoffs/handoff_DASH-WRITE-002_apps-script-write-api_20260617.md |
| DASH-WRITE-003 | Dashboard write forms | done | handoffs/handoff_DASH-WRITE-003_dashboard-write-forms_20260618.md |
| WRTPERF-001 | Apps Script write speed | done | handoffs/handoff_WRTPERF-001_apps-script-write-speed_20260616.md |
| CRM-001 | Dashboard — форма мультиканальної закупки | todo | handoffs/CRM-001-dashboard-multichannel-purchase-form.md |
| CRM-002 | Apps Script — multi-channel закупки | todo | handoffs/CRM-002-apps-script-multichannel-purchase.md |

---

## Інші задачі

| Roadmap ID | Назва | Status | Handoff |
|---|---|---|---|
| CHECKOUT-001 | Реєстрація акаунту при замовленні | todo | — |
| CAT-002 | Нова категорія «Аксесуари» | todo | — |
| LEGAL-002 | Публічна оферта + Обмін і повернення | todo | — |
| BRAND-OUTLET-001 | Outlet Booster — опис і SEO | done | — |
| R-13.5 | НП модуль — master log (ST-серія) | ref | handoffs/handoff_R-13.5_nova-poshta-module_2026-06-12.md |

---

## Notion vs Dashboard — де що трекається

**ST-серія (ST-0 … ST-7):** відсутня в Notion. Source of truth = `ROADMAP_FLOW` у `booster-dashboard.html`.
**Інші серії (DASH/CRM/AUTO/TECH/RD/UX):** у Notion database `5aef22c3-048d-4dde-a5b1-ad409de9301c`.

**Notion view URL:** `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4?v=eebb19b11cfb4066a8a3b1b097775818`
**Notion API:** `notion-query-data-sources` і `notion-query-database-view` вимагають Business plan.
Оновлення статусу через MCP — тільки якщо відомий page_id (URL картки в Notion) → `notion-update-page`.
Інакше — вручну в браузері.
