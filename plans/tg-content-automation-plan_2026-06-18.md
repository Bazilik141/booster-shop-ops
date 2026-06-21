# Booster Shop — Telegram Content Automation Plan
_Дата: 18.06.2026 · Серія: MKT-TG-001…006_

---

## 1. Мета

Автоматизувати 80% щоденного контенту в ТГ-групі Booster Shop, зберігши approval власника.
Цільовий ритм: 1 пост/день (новини або рубрика). Без флуду, без сміття.

---

## 2. Загальна архітектура

```
CheckMyName бот
    │ (посилання на статтю → Telegram)
    ▼
Make.com Сценарій #1 — "Новини" (щодня)
    │ Watch Telegram messages → HTTP fetch URL → Claude API → approval message
    │ (ти: ✅ / ❌)
    ▼
@booster_shop_alerts_bot публікує у групу

Make.com Сценарій #2 — "Карта тижня" (щосубота, авто)
    │ Scrydex API → рандомна топ-карта поточного тижня → Claude API → пост
    ▼
@booster_shop_alerts_bot публікує у групу

Make.com Сценарій #3 — "Ціновий дайджест" (щонеділю, approval)
    │ Scrydex API → топ-5 рухів цін за тиждень → Claude API → пост
    │ (ти: ✅ / ❌)
    ▼
@booster_shop_alerts_bot публікує у групу
```

---

## 3. Інфраструктура

| Компонент | Рішення | Вартість |
|---|---|---|
| Automation platform | Make.com (Free: 1000 ops/міс) | $0 |
| AI генерація | Claude API (claude-haiku-4-5) | ~$3–5/міс |
| Ціни карток | Scrydex API | $0–29/міс (see note) |
| Telegram bot | @booster_shop_alerts_bot (вже є) | $0 |
| Новини | CheckMyName бот (вже є) | $0 |

> **Scrydex note:** Безкоштовний план дає 100 запитів/день. Для тижневого дайджесту (1 раз/тиждень, ~20–30 карток) вистачить. Для карти тижня — 1 запит. Загалом ≤50 запитів/тиждень = безкоштовно. Starter $29/міс лише якщо захочеш розширити.

---

## 4. CheckMyName — ключові слова

### Пріоритет 1 — Pokemon TCG (укр + англ)
```
Pokemon TCG
Pokémon card game
Pokemon карти
Pokemon booster
Pokémon TCG tournament
Pokemon World Championship
Pokemon ban list
Pokemon new set
Pokémon ex card
```

### Пріоритет 1 — One Piece TCG
```
One Piece card game
One Piece TCG
One Piece OP-
One Piece Bandai card
OPTCG
Ван Піс карти
```

### Пріоритет 2 — MTG / YGO / загальне TCG
```
Magic: The Gathering ban
MTG ban list
Yu-Gi-Oh ban list
YGO TCG
колекційна картка рекорд
trading card record
TCG tournament
```

### Що НЕ додавати (шум)
- "Pokemon GO" (мобільна гра, не TCG)
- "One Piece anime" (занадто широко)
- "Magic" без "The Gathering" (плутанина)
- імена персонажів без TCG-контексту

---

## 5. Tone of Voice (системний промпт для Claude API)

Файл: `tg-tone-of-voice.md` (окремий документ, підключається в Make.com як system prompt)

---

## 6. Розклад публікацій

| День | Тип контенту | Джерело | Approval |
|---|---|---|---|
| Пн–Пт | Новина дня (TCG) | CheckMyName → Claude | ✅ так |
| Субота | Карта тижня | Scrydex API → Claude | автомат |
| Неділя | Ціновий дайджест | Scrydex API → Claude | ✅ так |
| Будь-який | Нова поставка / акція | Ти вручну | — |

> Якщо за тиждень немає жодної релевантної новини від CheckMyName — Make надсилає тобі нагадування: "Новин немає, постити карту дня?"

---

## 7. Карта тижня — ротація

| Субота місяця | Гра |
|---|---|
| 1-а субота | Pokemon TCG |
| 2-а субота | One Piece TCG |
| 3-я субота | Pokemon TCG |
| 4-а субота | YGO або MTG (чергування) |

Логіка вибору карти: Scrydex → рандомна карта з топ-тренду (найбільший % зростання ціни за 7 днів) у відповідній грі.

---

## 8. Ціновий дайджест — логіка

- Джерело: Scrydex API (Pokemon + One Piece, MTG за бажанням)
- Запит: топ-10 карток за зміною ціни за 7 днів (days_7.percent_change)
- Фільтр: карти з абс. ціною >$5 (прибираємо сміттєві копійчані картки)
- Формат посту: топ-3 зросли + топ-3 впали, з коментарем Claude

---

## 9. Покрокова реалізація (MKT-TG серія)

### MKT-TG-001 — ToV документ ✅ (готово у `tg-tone-of-voice.md`)
**Тип:** Claude | **Статус:** Done

### MKT-TG-002 — Make.com базова інфра
**Тип:** Manual | **Пріоритет:** тиждень 1

Кроки:
1. Зареєструватись на make.com
2. Підключити @booster_shop_alerts_bot через Telegram module (Bot API token)
3. Створити приватний чат "BS Approval" (тільки ти + бот)
4. Налаштувати Inline Keyboard в боті: кнопки ✅ Публікувати / ❌ Пропустити
5. Отримати Claude API key (console.anthropic.com)

### MKT-TG-003 — Сценарій #1: News pipeline
**Тип:** Mixed (Make + Claude API) | **Пріоритет:** тиждень 2

Make.com flow:
```
[Trigger] Telegram Watch Messages (від CheckMyName)
    → [Filter] Перевірити що це посилання (URL contains http)
    → [HTTP] GET {url} → отримати текст статті
    → [Claude API] system: tg-tone-of-voice.md | user: "Напиши пост по цій статті: {text}"
    → [Telegram] Надіслати в "BS Approval" + inline кнопки
    → [Telegram] Wait for callback
        → якщо ✅: Telegram Send Message → група
        → якщо ❌: нічого не робити
```

### MKT-TG-004 — Сценарій #2: Карта тижня
**Тип:** Mixed | **Пріоритет:** тиждень 3

Make.com flow:
```
[Trigger] Schedule → щосуботи 12:00
    → [Tools] Визначити номер суботи в місяці → вибрати гру
    → [HTTP] GET scrydex.com/api/{game}/cards?sort=price_change_7d&limit=10
    → [Tools] Random → вибрати 1 картку з топ-10
    → [HTTP] GET зображення картки (Scrydex image URL)
    → [Claude API] Написати пост про картку
    → [Telegram] Надіслати фото + пост у групу
```

### MKT-TG-005 — Сценарій #3: Ціновий дайджест
**Тип:** Mixed | **Пріоритет:** тиждень 3

Make.com flow:
```
[Trigger] Schedule → щонеділі 11:00
    → [HTTP] GET scrydex API Pokemon топ-10 зміна 7d
    → [HTTP] GET scrydex API One Piece топ-10 зміна 7d
    → [Tools] Відфільтрувати ціна >$5, взяти топ-3 зросли + топ-3 впали
    → [Claude API] Написати дайджест з коментарем
    → [Telegram] Надіслати в "BS Approval" + кнопки
    → [Telegram] Wait for callback → якщо ✅ → група
```

### MKT-TG-006 — 2-тижневий тест + промпт-тюнінг
**Тип:** Manual | **Пріоритет:** тиждень 4–5
- Переглядати кожен згенерований пост перед публікацією
- Фіксувати проблемні патерни → правити system prompt
- Метрика успіху: 0 підписників відписалось за 2 тижні + ≥2 реакції/пост

---

## 10. Ризики

| Ризик | Ймовірність | Мітигація |
|---|---|---|
| CheckMyName шлє нерелевантні посилання | Середня | Make-фільтр по ключових словах у тексті посилання + approval |
| Claude генерує неточну інформацію про карти | Середня | ToV-промпт: "не вигадуй факти, лише переказуй з джерела" |
| Scrydex API ліміт | Низька | Кешувати результати, робити 1 запит/тиждень |
| Пост виходить занадто рекламним | Середня | ToV: "не згадуй магазин в новинних постах" |
| Make.com Free 1000 ops вичерпується | Низька | ~50 ops/тиждень для 3-х сценаріїв = 200/міс, залишається запас |

---

## 11. Статус реалізації (оновлено 21.06.2026)

### Що зроблено:
- Make.com сценарій створено: RSS → Claude Sonnet → Telegram ✅
- RSS: r/PokemonTCG працює, додати r/OnePieceTCG / r/magicTCG / r/yugioh
- Claude: модель змінено на `claude-sonnet-4-6`, ToV v2 вставлено ✅
- HTTP модуль для Reddit `.json` — **заблокований Cloudflare**, видалено
- Поточне джерело тексту: `{{1.description}}` (RSS HTML, Claude обробляє)
- Telegram: "Send a Text Message" — працює, фото не підключено ще

### Проблема якості:
RSS `description` містить скорочений текст без конкретних деталей → Claude узагальнює.
Рішення: замінити HTTP модуль на альтернативний Reddit API (не прямий `.json`).

### Наступна дія:
→ Дослідити альтернативи для отримання повного тексту Reddit-поста:
  - Pushshift API (архів Reddit)
  - reddit.com/search.json або oauth Reddit API
  - Reveddit або аналоги
→ Підключити фото в Telegram (Send a Photo + Caption)
→ Додати інші RSS джерела (One Piece, MTG, YGO)
