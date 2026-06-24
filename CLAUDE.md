# Booster Shop — Claude context

@AGENTS.md

## Additional context for Claude

**Key contacts:** Owner = Raccoon (14bezlikiy14@gmail.com)

**Claude's role summary:** strategy · SEO/UX · handoff writing · post-Codex review.
Use `templates/handoff-template.md` for new handoffs.
Use `templates/codex-report-template.md` when reviewing Codex diagnostics.

**Tools available:** Terminal (Claude Code CLI) and VS Code extension are installed.
Use Terminal for git diff/commit/push and shell commands. Use VS Code for file inspection and diff review after Codex patches. See `AGENTS.md ## Environment` for full guidance.

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

## Notion: оновлення статусу рядка

**Database collection ID:** `5aef22c3-048d-4dde-a5b1-ad409de9301c`

**View URL роадмапу:** *(власник повинен вставити — копіювати з браузера при відкритому роадмапі)*
```
https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4?v=TODO
```

**Алгоритм оновлення статусу:**
1. `notion-query-database-view` з view URL → отримати рядки → знайти рядок з `Roadmap ID = "ST-X.X"` → взяти його `url`
2. `notion-update-page` з `page_id` = url рядка, `command: "update_properties"`, `"Status": "In progress"` (або `"Done"`)

**Без view URL:** оновлення статусу через API неможливе на поточному плані. Статус оновлювати вручну в браузері Notion.

## Швидкий індекс задач

Файл `context-index.md` у корені репо — таблиця `ID → handoff-файл → статус`.
Grep по ньому замість пошуку по всіх handoffs/.

## Dashboard Roadmap Sync Rule

**RULE:** Будь-яка зміна в Notion Roadmap (новий таск, зміна статусу) = оновлення `ROADMAP_FLOW`
в активному дашборді в тому ж сеансі. Без окремого прохання власника.

**Active dashboard:** `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html`
Sandbox path: `/sessions/.../mnt/Booster Shop/booster-dashboard.html`

**Workflow:**
1. Зробити зміну в Notion через MCP.
2. Фетчнути актуальні задачі з Notion.
3. Перегенерувати `ROADMAP_FLOW` в дашборді — писати напряму через bash у Booster Shop mount.

**For Codex handoffs** that include roadmap changes: додавати крок
"Оновити ROADMAP_FLOW в booster-dashboard.html" як останній пункт Required changes.
