# Handoff — AUTO-003: Notion token setup (рішення прийнято, чекає ручної дії)

Date: 2026-07-20
Status: DECIDED — Варіант A (перемістити базу в Private). Чекає ручної дії власника в Notion.

---

## 1. Conclusion

Обрано Варіант A. Перевірка репо і Notion підтвердила: переміщення бази безпечне для коду й документації — ID бази/колекції/view не змінюються при "Move to" в Notion, тому жодних правок у скриптах чи CLAUDE.md/ROADMAP_SOP.md не потрібно. Залишається один ручний крок від власника в Notion UI.

## 2. Task type

Manual (дія тільки в Notion UI) + документаційний апдейт (цей файл).

## 3. Owner

Manual — переміщення бази і надання доступу інтеграції робить лише власник вручну.

## 4. Status

Unblocked — план готовий, чекає виконання власником.

## 5. Next action (власник, Notion UI)

1. Відкрити базу https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4
2. Перемістити її в Private (Move to → Private) — без "•••", варіант меню залежить від версії інтерфейсу власника; шукати "Move to" будь-яким доступним способом (бічна панель / клавіатурне скорочення / контекстне меню сторінки).
3. Після переміщення: Settings → My integrations → "Booster Review" → Access → додати базу "Booster Shop Roadmap" (тепер має з'явитись у списку).

## 6. Codex handoff

Не потрібен. Codex не працює з Notion (CODEX_INSTRUCTIONS.md §5: "Claude reads/writes Notion; Codex does NOT touch Notion") — переміщення бази й підключення інтеграції жодним чином не стосується Codex-скоупу.

## 7. QA checklist (власник, після кроків §5)

- [ ] Notion: база "Booster Shop Roadmap" видима в Private, доступна за тим самим URL
- [ ] Notion: "Booster Review" підключена до бази через Content access
- [ ] Termінал: `bscontent "тест"` → в кінці виводу `[AUTO-003] Notion: <url>` замість `NOTION_TOKEN not set`
- [ ] Termінал: `bsseo --dry-run` → без помилок (не залежить від токена, але перевірка що нічого не зламалось)
- [ ] Notion: нова тестова задача з `bscontent` дійсно з'явилась у базі з правильними Status/Priority/Category
- [ ] Якщо база раніше була вбудована на сторінці "Home" — перевірити, що там більше не залишилось "порожнього" місця/помилки; за бажанням додати назад як Linked view

## 8. Risks

- **Layout-ризик (низький, не технічний):** база була вбудована inline прямо на персональній сторінці "Home" (поруч з "My Tasks"). Після переносу в Private вона зникне з Home — доведеться заходити напряму в Private або додати назад як Linked database view.
- **Доступ інших учасників workspace (перевірити вручну):** якщо базу зараз бачить хтось окрім власника через Home — після переносу в Private вони втратять видимість, поки їх не додати явно. Склад учасників workspace я перевірити не можу — власник має підтвердити сам.
- **Код/ID — ризику немає:** `NOTION_DB_ID = "5aef22c3-048d-4dde-a5b1-ad409de9301c"` захардкожено однаково в `scripts/auto_review.py` (AUTO-002), `scripts/content_pipeline.py` і `scripts/seo_monitor.py` (AUTO-003); той самий ID і URL — у `CLAUDE.md`, `ROADMAP_SOP.md §1/§5`, `context-index.md`, `templates/booster-shop-notion-templates.md`. Жодних згадок токена/ID більше ніде в репо не знайдено (перевірено `grep -rli notion` по всьому репо, окрім `node_modules`/`.next`/`.git`). Notion не змінює page/collection/view ID при переміщенні — вся інфраструктура зверху продовжує працювati без правок.
- **Побічний ефект (плюс):** той самий `NOTION_TOKEN` одразу розблокує і AUTO-002 (`bsreview` — автопост коментаря в Notion), не тільки AUTO-003 — обидва скрипти читають один і той самий `.env.review`.

## Старий контекст (для історії)

Оригінальна проблема, гіпотеза і варіанти A/B/C — без змін, див. git-історію цього файлу (перший коміт AUTO-003-notion-token-setup.md).
