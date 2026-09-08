# Roadmap reconciliation — Notion tasks with no dashboard row

Date: 2026-09-08
Scope: read-only inventory produced during the full Notion↔`ROADMAP_TASKS`
reconciliation. No status in this file is authoritative — Notion is.

## Why this file exists

Notion holds 277 rows. The dashboard `ROADMAP_TASKS` array holds 130. Of the
157 Notion rows with no dashboard row, **110 are not closed**. The owner reads
the dashboard, so those 110 tasks are invisible to him — this is the single
largest inaccuracy found in the sweep.

The owner authorized adding every not-closed row to the mirror on 2026-09-08.
That work is blocked: the Notion **Query Data Source** tool hit its workspace
usage limit mid-sweep, and honest dashboard rows need each task's full `Name`,
`Priority`, `Primary Tool` and `Last Updated`. Recovering those one page at a
time through `notion-search` would cost roughly one call per task.

**Names below are truncated to 50–60 characters** — that is how they came back
before the limit hit. Re-read them from Notion before writing dashboard rows.

## Use this list to triage first

Many of these date from 2026-05-19/05-20, before the RD redesign series and
before the checkout, CRM and 3D-print programmes existed. Some are certainly
dead. Deciding what to kill is cheaper than mirroring 110 rows and then
deciding.

## Not started

| ID | Name (truncated) |
|---|---|
| AUTO-008 | Автоматизація ціноутворення |
| AUTO-010 | Scope guard: що не робити зараз |
| AUTO-011 | Dry-run diff CRM stock vs OpenCart stock без авто |
| AUTO-012 | Аналітика клієнтів і checkout-воронки |
| BUG-001 | Виправити зміщення значень у таблиці характеристик |
| CAT-001 | Нова категорія «Інші TCG» (MTG, Yu-Gi-Oh! та ін.) |
| CAT-003 | Підкатегорія «Окремі картки» (singles) |
| CONTENT-001 | Уніфікувати опис boxes Mega Symphonia і Munics |
| CONTENT-002 | Уніфікувати таблицю характеристик Mega Symphonia |
| CONTENT-003 | Додати таб Характеристики для /product/pokemon… |
| CONTENT-004 | Гайдовий контент + категорія аксесуарів під тр… |
| MKT-003 | SEO-генератор як допоміжний інструмент |
| MKT-004 | Генератор OLX / Monobazar / marketplace-оголошень |
| MKT-005 | Bundle / pack-пропозиції |
| MKT-006 | Visible FAQ accordion (НЕ hidden SEO text) |
| MKT-007 | Механіка збору реальних відгуків покупців |
| MKT-008 | Передзамовлення: релізний календар + рекомендовані SKU |
| OPS-001 | Калькулятор закупівель і маржі |
| OPS-005 | SEO-звіт v1.2: корекція розділу 14 |
| PAY-001-SMOKE | Фінальний спільний QA-гейт кредитної покупки |
| PAY-003 | Спільна проміжна сторінка очікування підтвердження |
| POLISH-001 | Замінити '© 2026' на {{ 'now'\|date('Y') }} |
| POLISH-002 | Завести у Workflow Guide розділ 'Telegram-…' |
| POLISH-003 | Запобігти thin/duplicate content |
| POLISH-004 | A11y + URL fix (прибрати ?route= з лінку) |
| R-13.2 | Фільтр по наявності у сайдбарі категорій |
| RD-08 | Subcategory / leaf page (sibling chips, active…) |
| RD-16 | Account hub + personal data |
| RD-17 | Addresses (list + form) |
| RD-18 | Orders (all orders list + order detail) |
| RD-19 | Authorization (login + forgotten) |
| RD-20 | Registration (step 1 + step 2) |
| SEO-002 | Додати H1 на homepage |
| SEO-003 | Internal search aliases для трьох варіантів назви |
| SEO-005 | 301 redirect з URL з é на ASCII slug |
| SEO-006 | Прибрати 'оригинал', 'карточки покемон', 'нас…' |
| SEO-006-1 | Виправити 'Знижка від 10 шт' → '5+' |
| SEO-007 | Ninja Spinner: SEO push картки і бокса |
| SETUP-001 | OpenCart Settings → мін. сума замовлення |
| TECH-001 | PHP version / security headers |
| TECH-002 | Static assets cache policy |
| TECH-004 | Render-blocking resources |
| TECH-014 | 404 / redirect / crawl log cleanup |
| TECH-019 | Bing Webmaster Tools + Ahrefs Webmaster Tools |
| TECH-020 | Screaming Frog baseline crawl |
| TECH-021 | SEO browser QA toolkit |
| TECH-022 | Paid SEO suite decision |
| TECH-023 | Weekly SEO monitoring routine |
| TECH-026 | Оцінити поточний NP модуль vs paid module |
| TECH-027 | Image naming audit + alt-text audit |
| TECH-028 | Додати meta, H1 з конкретикою, опис категорій |
| TECH-029 (партія 2026-08-04) | Додати category description і FAQ на /catalog/Pokemon/Pokemon-boosters |
| TECH-032 | Robots.txt: block internal search & parametric |
| TECH-033 | Structured data: verify TECH-009 deployment |
| TECH-034 | Image compression (31 images > 100kB) |
| UX-001 | Архітектура каталогу під розширення асортименту |
| UX-002 | Головна сторінка: масштабування без втрати фокусу |
| UX-003 | Header navigation / Каталог |
| UX-005 | Mobile navigation |
| UX-008 | Trust facts на товарних сторінках |
| UX-009 | Search UX/UI: visual + functional improvements |
| UX-010 | Accessories upsell |
| UX-011 | Singles strategy |
| UX-020 | Бейджі на товарах |
| UX-024 | State badge на product card з dual format |
| UX-025 | При logged-in без адреси показувати warning |
| UX-027 | Прибрати native review block, замінити на trust… |
| UX-028 | Cleanup (Замовити знову, hide invoice/billing) |
| UX-029 | Modernize order list (Детальніше, no Продовжити) |
| UX-030 | Card layout, default badge, edit/delete buttons |
| UX-031 | -/+ кнопки на product page |
| UX-032 | Розглянути після UX-024..031 |
| UX-033 | One Piece підкатегорії (deferred until OP Mystery) |
| OPS-003 (партія 2026-05-20) | Return request form з alert до owner + email |
| AUTO-001 (партія 2026-06-21) | OLX/ручні замовлення |
| AUTO-005 (партія 2026-06-21) | Авто-нагадування клієнту: посилка НП не забрана |
| AUTO-006 (партія 2026-06-21) | Telegram/Viber бот для клієнтських нотифікацій |

## In progress

| ID | Name (truncated) |
|---|---|
| AUTO-002 (партія 2026-05-20) | Master automation table / dashboard |
| AUTO-003 (партія 2026-05-20) | Звіти продажів за 7 днів і місяць |
| AUTO-004 (партія 2026-05-20) | Аналітика каналів продажів у звітах |
| AUTO-005 (партія 2026-05-20) | Моніторинг конкурентів: MVP |
| AUTO-006 (партія 2026-05-20) | Класифікація конкурентів |
| AUTO-007 | Ринкова ціна: аналітика і рекомендація |
| MKT-001 | Контент-план Telegram / Instagram / OLX / сайт |
| MKT-002 | Повторні продажі і промокоди |
| OPS-002 | План задач на місяць |
| R-13.1 | Сортування товарів у каталозі з урахуванням наявності |
| TECH-007 | Google Merchant Center setup |
| UX-012 | Footer refinement |
| UX-015 | Hutko return/session reliability |
| UX-016 | Checkbox / fiscalization reliability |
| UX-019 | Back-to-top / cookie polish |

## Owner answers

- **TECH-005** — resolved 2026-09-08. Search Console still reports the fetch
  error; the owner chose to accept it rather than keep the row open. Closed
  `Done` as a watch-only close under `ROADMAP_SOP.md` §6, with the acceptance
  recorded on the Notion page. Removed from the In progress list above. Do not
  cite that closure as evidence the sitemap error is fixed.
- **TECH-007** — still open. Merchant Center account setup is external; no
  repository or production artefact can prove it. TECH-008 (the feed itself)
  was closed on 2026-09-08 against the live `merchant-feed.tsv`.

## Related structural findings

`ROADMAP_SOP.md` §5 now carries the recovered page-ID table, the known
Roadmap ID collision table, and a standing note about this mirror gap.
