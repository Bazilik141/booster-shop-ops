# Handoff — AUTO-003: Content Pipeline + SEO Monitor

Date: 2026-06-21 | Notion: AUTO-003 (merged AUTO-004)
Type: Claude task (Python scripts, не PHP-патч)

---

## Context

Дві пов'язані задачі об'єднано в один модуль:

1. **Content pipeline** — власник каже "зроби сторінку для X" → скрипт генерує бриф + SEO-мета + текст → зберігає у `plans/` → створює задачу в Notion
2. **SEO monitor** — раз на місяць: читає Search Console API → порівнює з попереднім місяцем → якщо сторінка просіла, створює задачу в Notion

Обидва скрипти працюють за тією ж схемою, що й `auto_review.py` (Claude API + Notion API + `.env.review`).

---

## Scope

### Файли до створення

```
scripts/content_pipeline.py   — content pipeline
scripts/seo_monitor.py        — SEO monitor
plans/seo-snapshot/           — папка для щомісячних JSON-знімків
```

### PowerShell-хелпери (додати в $PROFILE)

```powershell
function bscontent {
    param([string]$topic)
    Push-Location $BS
    python scripts/content_pipeline.py $topic
    Pop-Location
}

function bsseo {
    Push-Location $BS
    python scripts/seo_monitor.py
    Pop-Location
}
```

---

## scripts/content_pipeline.py — специфікація

### Вхід
```
python scripts/content_pipeline.py "Картки Magic: The Gathering"
python scripts/content_pipeline.py "категорія покемон бустери японські"
```

### Що робить

1. Читає контекст сайту з `plans/PROJECT_CONTEXT.md` (якщо є)
2. Викликає Claude API (claude-sonnet-4-6) з промптом:
   - Тема: аргумент командного рядка
   - Завдання: згенерувати для сторінки інтернет-магазину Booster Shop
3. Генерує (один API-запит):
   ```
   H1: ...
   Meta title: ... (≤60 символів)
   Meta description: ... (≤160 символів)
   Slug: ...
   Intro: ... (2-3 речення)
   Основний текст: ... (400-600 слів, без keyword stuffing)
   ```
4. Зберігає у `plans/content-YYYY-MM-DD-{slug}.md`
5. Виводить у термінал
6. Якщо є `NOTION_TOKEN`: створює задачу в Notion
   - Name: `CONTENT: {slug} — ready for review`
   - Status: Not started
   - Priority: Medium
   - Category: Content / SEO
   - Task Type: Claude
   - Roadmap ID: `CONTENT-{YYYYMMDD}-{slug[:20]}`

### Промпт для Claude (базовий)

```
Ти SEO-копірайтер для українського інтернет-магазину колекційних карток Booster Shop (boosterok.com.ua).
Тематика: {topic}

Згенеруй:
1. H1 (природний, конкретний, без "купити" на початку якщо недоречно)
2. Meta title ≤60 символів (включає ключове слово + бренд)
3. Meta description ≤160 символів (заклик + ключове слово, без крапок в кінці)
4. URL-slug (латиниця, через дефіс)
5. Intro-текст 2-3 речення (живою мовою, без SEO-штампів)
6. Основний текст 400-600 слів (структурований, без вигаданих цін і GTIN)

Правила:
- Мова: українська
- Без keyword stuffing
- Без вигаданих відгуків, рейтингів, GTIN
- Без гарантій типу "найкращий в Україні"
- Тон: дружній, експертний
```

---

## scripts/seo_monitor.py — специфікація

### Вхід
```
python scripts/seo_monitor.py              # повна перевірка
python scripts/seo_monitor.py --dry-run   # без Notion-задач
```

### Що робить

1. Читає credentials для Search Console API з `.env.review`:
   ```
   GSC_CLIENT_ID=...
   GSC_CLIENT_SECRET=...
   GSC_REFRESH_TOKEN=...
   GSC_SITE_URL=https://boosterok.com.ua/
   ```
2. Запитує Search Console API:
   - Метрики: `clicks`, `impressions`, `position`
   - Вимір: `page`
   - Діапазон: поточний місяць vs попередній місяць
3. Завантажує попередній знімок: `plans/seo-snapshot/YYYY-MM.json`
4. Порівнює сторінки. Критерії тригера:
   - Position знизилась > 5 позицій, АБО
   - Clicks знизились > 30% (при clicks > 10 минулого місяця)
5. Для кожної проблемної сторінки:
   - Виводить у термінал
   - Якщо `NOTION_TOKEN`: створює задачу в Notion
     - Name: `SEO drop: {url} (pos {old}→{new})`
     - Priority: High якщо drop > 10 позицій, інакше Medium
     - Category: SEO
     - Status: Not started
6. Зберігає новий знімок: `plans/seo-snapshot/YYYY-MM.json`
7. Виводить summary: кількість сторінок перевірено / проблемних / задач створено

### Search Console OAuth setup (one-time, власник)

```
1. console.cloud.google.com → New Project → Enable Search Console API
2. Credentials → OAuth 2.0 Client ID → Desktop app
3. Завантажити client_secret.json → покласти у repo root (gitignored)
4. python scripts/gsc_auth.py   # допоміжний скрипт для отримання refresh_token
5. Скопіювати refresh_token у .env.review
```

Допоміжний скрипт `scripts/gsc_auth.py` — окремий файл для one-time auth:
```python
# Запускається один раз для отримання refresh_token
# Відкриває браузер → власник авторизує → виводить refresh_token у термінал
```

---

## .env.review — нові змінні

Додати до існуючого файлу:

```env
# Search Console (для seo_monitor.py)
GSC_CLIENT_ID=...
GSC_CLIENT_SECRET=...
GSC_REFRESH_TOKEN=...
GSC_SITE_URL=https://boosterok.com.ua/
```

---

## Acceptance Criteria

- [ ] `bscontent "покемон бустери"` → файл у `plans/content-*.md` + задача в Notion
- [ ] Meta title ≤ 60 символів, meta description ≤ 160
- [ ] Текст без вигаданих фактів, рейтингів, GTIN
- [ ] `bsseo` → читає GSC, порівнює, виводить summary
- [ ] При drop > поріг → задача в Notion з правильним пріоритетом
- [ ] `bsseo --dry-run` → без Notion-задач, тільки вивід
- [ ] JSON-знімок зберігається у `plans/seo-snapshot/`

## QA Checklist (власник)

- [ ] `bscontent "тест"` → файл створено, читається
- [ ] Запустив `bsseo --dry-run` → помилок немає, є summary
- [ ] Після real run → перевірив що задачі в Notion правильно заповнені
- [ ] Meta title і description вкладаються в ліміти (перевірити вручну)

## Risks

- Search Console API повертає дані з затримкою ~2 дні — нормально, враховувати при порівнянні
- Перший місяць без baseline snapshot → тільки зберегти знімок, без порівняння
- Claude може вигадати характеристики товарів — промпт має явну заборону, але потрібна ручна перевірка тексту
- GSC OAuth refresh_token може протухнути — тоді треба повторна авторизація

## Dependencies

- `auto_review.py` вже в репо — використовувати як патерн для API-дзвінків
- `.env.review` вже створено — додати GSC-змінні
- `plans/PROJECT_CONTEXT.md` — бажано створити з описом сайту для контексту генерації
