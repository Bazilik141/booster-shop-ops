# Booster Shop — Claude context

@AGENTS.md

## Additional context for Claude

**Key contacts:** Owner = Raccoon (14bezlikiy14@gmail.com)

**Claude's role summary:** strategy · SEO/UX · handoff writing · post-Codex review.
Use `templates/handoff-template.md` for new handoffs.
Use `templates/codex-report-template.md` when reviewing Codex diagnostics.

**Codex review в чаті — жорсткий формат, без винятків:** статус одним рядком (`Review OK` / `Review OK, owner-QA потрібен` / `Повернути в роботу`) + список ручних чек-апів для власника. Без опису diff, без переліку перевірених файлів, без пояснень "чому все ок" — це не показник якості рев'ю, а шум. Деталі рев'ю (що саме перевірено в коді) лишаються лише в `diagnostics/`/пам'яті сесії; в чат — тільки те, що власник має сам перевірити руками. Розгорнутий опис — лише якщо власник явно попросить "детальніше".

**Tools available:** Terminal (Claude Code CLI) and VS Code extension are installed.
Use Terminal for git diff/status/log (read-only) and shell commands. **`git commit`/`push` Claude ніколи не виконує сам** — завжди готує повний PowerShell-блок, власник вставляє його в нове вікно і виконує вручну (див. AGENTS.md → Commit / push policy). Use VS Code for file inspection and diff review after Codex patches. See `AGENTS.md ## Environment` for full guidance.

**For new tasks:** read the relevant handoff in `handoffs/` before writing patches or analysis.
**For reviews:** read `diagnostics/<TASK-ID>_*_report_*.md` + `git diff` output.

## Контекст нової задачі — строгий порядок (3 кроки, не більше)

**При отриманні задачі типу "ST-3.5" — виконувати САМЕ ЦЕЙ порядок:**

**Крок 1 — Репозиторій (bash, ~5 секунд):**
```bash
grep -r "ST-3.5" /path/to/repo/handoffs/ /path/to/repo/diagnostics/ --include="*.md" -l
```
Якщо є хендоф або діагностика — читати її. Це найшвидше джерело.

**Крок 2 — Дашборд (якщо крок 1 недостатній):**
```bash
grep -A5 "ST-3.5" "/sessions/.../mnt/Booster Shop/booster-dashboard.html"
```
`ROADMAP_FLOW` у дашборді = актуальний статус і scope без мережевих запитів.

**Крок 3 — Notion (тільки якщо кроки 1-2 не дали scope):**
Єдиний дозволений метод: `notion-fetch` на URL роадмапу.
URL: `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4`
Сторінка повертає весь вміст одразу — шукати потрібний ID у тексті.

**ЗАБОРОНЕНО на будь-якому кроці:**
- `notion-search` (семантичний, не матчить точні ID типу "ST-3.5")
- SQL у Notion (`notion-query-data-sources` вимагає Business plan — недоступний)
- `notion-query-database-view` без view URL з `?v=<viewId>`
- Відкривати Chrome для перегляду роадмапу (повільно, часто не підключений)
- TaskCreate до завершення збору контексту

**GitHub-інтеграція Notion:**
Якщо в картці задачі є linked commit/PR — це підтверджує, що Codex вже виконував роботу по цій задачі. Перевіряти через `git log --oneline | grep -i "ST-3.5"` ще до Notion.

## Notion: статус і синхронізація

**Канон — `ROADMAP_SOP.md`** (§1 джерело правди, §4 sync, §5 page_id-реєстр, §6 DoD). Коротко:
- Статус-правда — Notion; дашборд `ROADMAP_FLOW` — дзеркало. ST-серія тепер теж у Notion (з 2026-06-24).
- Bulk-read заблоковано планом → читати/писати per-картка: `notion-fetch` за page_id; `notion-update-page` (`update_properties`, напр. `"Status":"In progress"`).
- page_id рядків (ST + часті не-ST) — `ROADMAP_SOP.md §5`. `notion-search` семантичний — шукати за назвою, не за ID.

Database: `35c3f857-2fc5-4a78-96c8-af0efd4cf8d4` · View: `?v=eebb19b11cfb4066a8a3b1b097775818`

## Швидкий індекс задач

Файл `context-index.md` у корені репо — мапа `ID → handoff → Notion page_id` (БЕЗ статусу; статус лише в Notion).
Grep по ньому замість пошуку по всіх handoffs/.

## Dashboard Roadmap Sync Rule

**Канон — `ROADMAP_SOP.md` §3–4.** Notion = правда, дашборд = дзеркало; будь-яка зміна статусу — в ОБИДВА в тому ж сеансі (хто свіжіший за реальність — той і виграє), без окремого прохання власника.

**Active dashboard:** `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html` (sandbox: `/sessions/.../mnt/Booster Shop/booster-dashboard.html`)
**Дзеркало в репо:** `dashboard/booster-dashboard.html` — копіювати активний → дзеркало → commit.

**Codex (roadmap-affecting патчі):** останній пункт Required changes = «оновити ROADMAP_FLOW в booster-dashboard.html». Notion Codex не чіпає — це Claude.

**Commit-safety (autosync):** команда, яку готує Claude, включає `New-Item .autosync-pause` перед `git add`/`commit` і `Remove-Item .autosync-pause` після `push` (паузить hardened `bs-autosync.ps1`, прибирає гонку за `.git/index`). Деталі — `ROADMAP_SOP.md §4/§8`.

**PowerShell git-команди для owner — завжди ручний коміт, без винятків:** Claude сам НІКОЛИ не запускає `git commit`/`push`. Замість цього Claude віддає ОДИН готовий блок команд, який можна вставити з нуля в нове вікно PowerShell. Блок ЗАВЖДИ починається з `cd "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops"` першим рядком (PowerShell 7 у owner відкривається в `C:\Windows\System32`, а не в репо — без явного `cd` усі наступні `git`-команди падають з `fatal: not a git repository`). Власник вставляє блок і виконує сам.
