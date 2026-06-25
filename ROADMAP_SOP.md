# ROADMAP_SOP.md — Roadmap operating procedure (Claude + Codex)

> Канонічний документ з **governance роадмапу**: де живе статус, як синхронізувати Notion ↔ дашборд, хто що оновлює, коли задача = Done.
> Загальні ops-правила — у `AGENTS.md`. Обмін патчами Claude↔Codex — у `CODEX_WORKFLOW.md`. Цей файл головний для всього, що стосується статусу й роадмапу.
> Last updated: 2026-06-24.

---

## 0. Суть (TL;DR для власника)

- **Джерело правди по статусу — Notion roadmap.** Дашборд — зручне дзеркало. Більше ніде статус не дублюється.
- Будь-яка зміна статусу йде в **обидва** (Notion + дашборд) у тому ж сеансі.
- Claude пише в Notion і звіряє. Codex оновлює `ROADMAP_FLOW` дашборда як останній крок патчу. Власник аппрувить і деплоїть.
- Bulk-читання Notion заблоковане планом → статуси читаємо/пишемо **по картці** за `page_id`.

---

## 1. Single source of truth

| Що | Джерело правди | Роль |
|---|---|---|
| **Статус, пріоритет, власник задачі** | **Notion roadmap** | canonical |
| Перегляд статусу «одним екраном» | `booster-dashboard.html` → `ROADMAP_FLOW` | дзеркало Notion |
| Scope, логіка, історія задачі | `handoffs/` + `diagnostics/` | реалізація/історія |
| Мапа `ID → handoff → Notion page_id` | `context-index.md` | індекс (БЕЗ статусу) |
| Код, дифи, патчі | репо | історія реалізації |

**Правило:** статус НЕ зберігається в `context-index.md`, у назвах файлів чи в head хендофів. Тільки Notion (+ дзеркало в дашборді).

Notion roadmap: `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4`
Data source (collection): `5aef22c3-048d-4dde-a5b1-ad409de9301c`
View: `?v=eebb19b11cfb4066a8a3b1b097775818`

---

## 2. Status vocabulary — Notion ↔ дашборд

| Notion `Status` | Dashboard `status` | Значення |
|---|---|---|
| `Not started` | `todo` | ще не почато |
| `In progress` | `active` | у роботі (включно з «handoff готовий, чекає Codex») |
| `Done` | `done` | виконано + QA пройдено |

**Watch-only** (моніторинг без активної роботи, напр. SEO після фіксу): у Notion немає такого статусу → ставимо `Done` + примітка в `Owner Decision`/`Stage` на кшталт «watch-only». У дашборді — `done` або окремий лейн/підпис. (Саме тут був розсинхрон TECH-029.)

---

## 3. Task lifecycle — хто що оновлює

| Етап | Дія | Notion | Дашборд | Хто |
|---|---|---|---|---|
| 1. Створення | новий рядок задачі | create row, `Not started` | додати в `ROADMAP_FLOW` | Claude |
| 2. Взяли в роботу | написано handoff | `In progress` | `active` | Claude |
| 3. Реалізація | Codex патч | — | — | Codex (drop у `patches/`) |
| 4. Review | `bsreview [TASK-ID]` (git diff + handoff + diagnostic → haiku) + scope-check | — | — | Claude / авто |
| 5. Деплой + QA | owner запускає патч | — | — | Owner |
| 6. Закриття | owner каже «закривай» | `Done` | `done` | **один агент** (кому owner сказав) → оновлює ОБИДВА |

**Закриття — без штучного поділу.** Owner каже будь-кому з агентів «закривай задачу виконаною» → той самий агент ставить `Done` у Notion І дзеркалить у `ROADMAP_FLOW` дашборда, однією дією. (Codex оновлює дашборд як крок патчу; статус у Notion — через `bsreview`/`NOTION_TOKEN`, або передає Claude, якщо Notion-доступу нема в той момент.)

---

## 4. Sync procedure (двостороння, з урахуванням обмежень плану)

**Читання статусу** (bulk-query заблоковано — Business plan):
1. `notion-fetch` за `page_id`/URL картки → властивість `Status`.
2. Якщо `page_id` невідомий → `notion-search` за **назвою** (не за точним ID типу «ST-3.5» — семантичний пошук не матчить ID) → взяти `id` з результату → fetch.

**Запис статусу:**
- Notion: `notion-update-page`, `command: "update_properties"`, напр. `{"Status": "In progress"}`.
- Дашборд: правка `status:` у `ROADMAP_FLOW` активного файла → копія в репо-дзеркало → commit.

**Commit-safety (autosync):** перед `git add`/`commit` агент створює `.autosync-pause` у корені репо, після `push` — видаляє. Це паузить hardened `bs-autosync.ps1` і прибирає гонку за `.git/index`. Якщо забув — hardened-autosync усе одно пропускає pull при брудному дереві та сам відновлює зламаний індекс (страхувальна сітка, не заміна паузі).

**Хто свіжіший, той і виграє** (обидва мають зійтися до реальності):
- Notion свіжіший за реальність дашборда → вирівняти дашборд під Notion.
- Реальність (зроблено/змінено) випереджає Notion → оновити картку Notion, потім дашборд.

**Звірка на старті roadmap-сесії:** пройтися по `active`/`todo` задачах ROADMAP_FLOW, fetch карток, вирівняти розбіжності. Done-задачі — спот-чек за потреби.

---

## 4a. Автоматизація review — `bsreview` (AUTO-002)

`bsreview [TASK-ID]` (PowerShell `bs-review.ps1` → `scripts/auto_review.py`):
- авто-знаходить diagnostic + handoff + `git diff HEAD~1..HEAD` → Claude **haiku** → review (5 секцій: task solved · side effects · AC check · owner manual checks · verdict) → файл `diagnostics/<TASK-ID>_auto_review_<date>.md`.
- `bsreview` без ID — бере останній diagnostic. `bsreview --dry-run` — прев'ю без збереження і без Notion.
- **Notion-інтеграція готова:** з `NOTION_TOKEN` у `.env.review` скрипт знаходить картку за `Roadmap ID` (прямий Notion REST API — обходить MCP-ліміт bulk-read) і постить коментар `🤖 Auto-review`. Токен додати коли зручно: `notion.so/my-integrations` → нова інтеграція → під'єднати до Roadmap DB → вписати в `.env.review`.
- Конфіг `.env.review` (`ANTHROPIC_API_KEY` + `NOTION_TOKEN`) — НЕ комітити.

Це штатний інструмент кроку Review (§3.4). Для глибокого/ризикового патчу — додатково ручний review Claude.

---

## 5. page_id registry (MCP-fallback для статус-write)

ST-серія (заведена в Notion 2026-06-24):

| Roadmap ID | Notion page_id |
|---|---|
| ST-3.5 | `3896bf20-bdb4-8174-8a50-fe3d19f8c9ba` |
| ST-3.6 | `38a6bf20-bdb4-8184-917c-ef3f6c6ca1b1` |
| ST-3.7 | `38a6bf20-bdb4-8153-8538-db353b2f6a34` |
| ST-2c | `3896bf20-bdb4-8119-a13b-c1dc1e078328` |
| ST-6 | `3896bf20-bdb4-81b0-8c67-cb4300ccba9f` |
| ST-1 | `3896bf20-bdb4-819d-bb33-f1ddcc2dd0de` |
| ST-2b.5 | `3896bf20-bdb4-81af-9a30-f9f1c909338c` |
| ST-2b.1–2b.4 | `3896bf20-bdb4-815f-8762-ec1e16c6e146` |

Часто вживані не-ST (станом на 2026-06-24):

| Roadmap ID | Notion page_id |
|---|---|
| CRM-001 | `3876bf20-bdb4-81dc-987d-d119fff4d2e9` |
| CRM-002 | `3876bf20-bdb4-8118-9fc7-d7e702832ec4` |
| TECH-005-DEEP | `3666bf20-bdb4-8175-a429-e48eb7d6ef2d` |
| TECH-012 | `3666bf20-bdb4-812e-8975-df8827efdb16` |
| TECH-029 | `3786bf20-bdb4-8116-8f66-c856e04a11df` |
| RD-11 | `3706bf20-bdb4-81a4-b3fa-f35e7610defa` |
| CAT-002 | `36f6bf20-bdb4-817e-99ec-eecce853778c` |
| CHECKOUT-001 | `3776bf20-bdb4-8130-bcbf-cbb6259d5654` |
| LEGAL-002 | `3666bf20-bdb4-81ea-8fed-ff4773081cdb` |
| R-13.5 | `36c6bf20-bdb4-814c-becb-c451a64b22f8` |

Не в списку → знайти через `notion-search` за назвою, потім додати сюди.
Старіші виконані ST (ST-0 / ST-2 / ST-2a*) у Notion ще не заведені — backfill за потреби.

---

## 6. Definition of Done (по типах)

- **Default:** реалізовано + git diff у scope + дашборд/Notion = Done.
- **Checkout / payment / Hutko / Checkbox / NP / order:** `bs-checkout-smoke` пройдено + **owner manual QA** (оплата/фіскал/CRM readback) → лише тоді Done.
- **SEO / sitemap / robots / canonical:** серверна частина закрита + (де доречно) підтвердження в GSC. Якщо чекаємо Google — `Done` + «watch-only» примітка, НЕ тримати в `In progress` нескінченно.
- **CRM / dashboard / Apps Script:** деплой нової версії + readback через Apps Script (`action=summary`/`orders`) або вузький діапазон Sheets.
- **Content / Legal:** текст готовий — це ще НЕ Done; Done лише після owner-публікації + (для legal) юр. перевірки та реальних реквізитів.

---

## 7. Roles (roadmap-specific; загальні — в AGENTS.md)

- **Закриває той, кому owner сказав** — оновлює Notion + дашборд разом, без поділу на підпроцеси.
- **Claude:** звірка Notion↔дашборд, handoffs, review; Notion-write через MCP (`notion-update-page`).
- **Codex:** патчі + `ROADMAP_FLOW` дашборда (останній крок roadmap-affecting патчу); Notion-write через `bsreview`/`NOTION_TOKEN` або передає Claude; commit/push лише на прохання owner.
- **Owner:** аппрув у чаті, деплой, фінальне QA, рішення «Done» по ризикових/legal.

---

## 8. Known constraints / guardrails

- **Notion bulk-read через MCP заблокований** (`notion-query-data-sources` SQL і `notion-query-database-view` вимагають Business + Notion AI) → per-card workflow (§4–5). **АЛЕ** прямий Notion REST API через `NOTION_TOKEN` (`.env.review`, AUTO-002 / §4a) вміє query за точним `Roadmap ID` — коли токен налаштовано, це обходить ліміт і дає пошук за ID поза MCP.
- **`notion-search` семантичний** — не матчить точні ID. Шукати за назвою.
- **git `index.lock` / autosync (вирішено 2026-06-24):** історично `bs-autosync.ps1` ганяв `git pull` паралельно з коммітами Claude → гонка за `.git/index` (завислий лок або `index corrupt`). Hardened-версія скрипта: пауза-сентинел `.autosync-pause`, прибирання застарілого локу (>120с, без активного git), авто-відновлення індексу (`del index; git reset`), skip-pull-when-dirty. Правило: агент ставить `.autosync-pause` перед git-операціями і прибирає після push. Аварійне ручне відновлення: `del .git\index.lock` → `del .git\index` → `git reset`.
- **Дві копії дашборда:** активна (`Booster Shop/booster-dashboard.html`) і репо-дзеркало (`dashboard/booster-dashboard.html`) — після правок копіювати активну → дзеркало → commit.
- **Path drift (вирішено 2026-06-25):** канонічний локальний шлях — `C:\Users\14bez\Downloads\Booster Shop`; `E:\Personal Files\...` вважається retired для нової роботи.

---

## 9. Цей документ vs інші

- `AGENTS.md` — загальні ops, ролі, патч-конвенції, ризикові зони.
- `CODEX_WORKFLOW.md` — механіка обміну патчами через репо.
- `CLAUDE.md` — контекст Claude; посилається сюди по governance роадмапу.
- `ROADMAP_SOP.md` (цей) — **канон по статусу/синхронізації/DoD**. При конфлікті правил щодо роадмапу — виграє цей файл.
