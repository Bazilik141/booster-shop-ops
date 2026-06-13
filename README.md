# Booster Shop Ops

Private working repo for Booster Shop — PHP patches, Claude/Codex handoffs, plans, dashboard, diagnostics.

## Structure

- `handoffs/` — всі handoff-файли для Codex і Claude (HANDOFF-*.md, codex-handoff-*.md, ST-*, RD-*, TECH-*)
- `patches/` — готові PHP/JS/CSS патчі після апруву власника
- `plans/` — плани, аудити, roadmap-документи, контентні файли
- `dashboard/` — booster-dashboard.html (локальний CRM-дашборд)
- `diagnostics/` — діагностичні скрипти і звіти (read-only)
- `templates/` — шаблони для handoff / Codex

## Rules

- Не комітити: hosting backups, DB dumps, customer data, `.bak` файли, архіви `.tar.gz`/`.zip`
- PHP патчі мають бути self-contained і запускатись з `~/public_html`
- Notion roadmap = source of truth для статусів і пріоритетів
- Цей репо = history + review evidence, не замінює Notion

## Workflow

```
Claude (handoff) → Codex (патч у patches/) → git diff → git commit → git push → deploy via FTP
```

### Перед Codex-сесією
```bash
cd booster-shop-ops
git status        # має бути чисто
git pull          # синхронізуй останні зміни
```

### Після Codex-сесії
```bash
git diff                          # перевір що змінилось
git add .
git commit -m "Codex: TASK-ID короткий опис"
git push origin master
```

## Гілки

- `master` — основна, завжди стабільна
- Для великих задач (checkout redesign, major refactor): окрема гілка → merge після QA
