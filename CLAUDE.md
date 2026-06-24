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

## Notion Lookup Protocol

**Roadmap URL:** `https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4`

**Знайти задачу по ID (напр. ST-3.5):**
1. `notion-fetch` на URL роадмапу → парсити список задач зі сторінки.
2. АБО `notion-query-database-view` з відомим database ID — якщо є фільтр по полю Name/Roadmap ID.
3. **НЕ використовувати:** SQL (не підтримується на цьому плані), `notion-search` для пошуку по точному ID (семантичний, не матчить "ST-3.5").

**Швидкий шлях без SQL:** `notion-fetch` → одразу видає весь вміст сторінки роадмапу, з якого можна витягти потрібний таск без додаткових запитів.

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
