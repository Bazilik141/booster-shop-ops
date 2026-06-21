# Handoff — AUTO-002: Codex patch auto-review

Date: 2026-06-21 | Notion: AUTO-002

## Context

Після кожної Codex-сесії власник вручну просить Claude зробити review. AUTO-002 замінює цей крок автоматичним скриптом: `scripts/auto_review.py` + PowerShell-обгортка `bs-review.ps1`.

Скрипт уже написаний і готовий до роботи. Цей документ описує налаштування та flow.

## Що реалізовано

- `scripts/auto_review.py` — основний скрипт (Python 3.10+)
- `scripts/requirements.txt` — залежності (тільки `anthropic`)
- `scripts/.env.example` — шаблон для секретів
- `bs-review.ps1` — PowerShell-обгортка для запуску з кореня репо
- `.gitignore` — `scripts/.env` виключено з git

## Flow

```
Codex завершив сесію → поклав diagnostics/TASK-ID_*_report_*.md
         ↓
Власник запускає: .\bs-review.ps1 TASK-ID
         ↓
auto_review.py:
  1. Знаходить diagnostics/TASK-ID_*_report_*.md
  2. Знаходить handoffs/TASK-ID*.md
  3. Робить git diff HEAD~1..HEAD
  4. Викликає Claude API (claude-haiku-4-5)
  5. Зберігає diagnostics/TASK-ID_auto_review_DATE.md
  6. Постить коментар до задачі в Notion (якщо NOTION_TOKEN є)
  7. Виводить review в термінал
```

## Налаштування (one-time, власник)

### 1. Встанови Python (якщо немає)
```
winget install Python.Python.3.12
```

### 2. Створи scripts/.env
```
copy scripts\.env.example scripts\.env
```
Заповни:
- `ANTHROPIC_API_KEY` — з console.anthropic.com → API Keys
- `NOTION_TOKEN` — з notion.so/my-integrations → "Create new integration" → Copy token
  - Після створення: відкрий Booster Shop Roadmap у Notion → ... → Connections → додай інтеграцію

### 3. Перший запуск
```powershell
.\bs-review.ps1 --dry-run
```
(встановить anthropic SDK автоматично, виведе review без запису)

## Використання

```powershell
# Auto-detect latest diagnostic:
.\bs-review.ps1

# Конкретна задача:
.\bs-review.ps1 R-13.1

# Без збереження і Notion (тест):
.\bs-review.ps1 R-13.1 --dry-run
```

## Acceptance Criteria
- [ ] `.\bs-review.ps1 --dry-run` виводить структурований review
- [ ] Review містить: task solved / side effects / AC check / owner checks / verdict
- [ ] `diagnostics/TASK-ID_auto_review_DATE.md` зберігається
- [ ] Коментар з'являється в Notion на сторінці задачі

## QA Checklist (власник)
- [ ] Запустив `.\bs-review.ps1 TASK-ID --dry-run` — review отримано
- [ ] Запустив без `--dry-run` — файл у diagnostics/ з'явився
- [ ] Перевірив Notion — коментар є
- [ ] Запустив без жодної діагностики — скрипт виводить помилку, не падає

## Risks
- Якщо diagnostic-файл не знайдено — скрипт завершується з помилкою (не краш)
- Великий diff (>20 000 символів) — автоматично обрізається
- NOTION_TOKEN не встановлений → Notion-крок пропускається, решта працює
- ANTHROPIC_API_KEY не встановлений → виводить помилку у review-блоці
