# Handoff: MKT-TG-003 — Make.com Telegram Pipeline (RSS → Jina → Claude → GPT → Telegram)
**Date:** 2026-06-26  
**Status:** In Progress — pipeline built, XML parsing path broken  
**Next agent:** Fix Iterator array path + date filter + Jina URL + verify full run

---

## Goal
Automated daily Telegram post for Booster Shop (Ukrainian TCG store).  
Pipeline: Google News RSS → Jina.ai (full text) → Claude Sonnet (Ukrainian post) → GPT-4o-mini (filter/polish) → Telegram.

---

## Current Make.com Scenario: "Integration RSS"
**Module chain (with IDs):**

| # | Module | ID | Notes |
|---|--------|----|-------|
| 1 | HTTP – Make a request | 19 | Fetches Google News RSS |
| 2 | XML – Parse XML | 21 | Parses RSS XML |
| 3 | Iterator (Flow Control) | 20 | Iterates over RSS items — **BROKEN: array empty** |
| 4 | jina.ai – Read a URL | 11 | Reads full article — **URL field needs fix** |
| 5 | Anthropic Claude – Create a Prompt | 3 | Generates Ukrainian TG post |
| 6 | OpenAI – Generate a completion | 17 | Filters (SKIP) + polishes text |
| 7 | Filter (SKIP) | — | Does not contain "Skip" |
| 8 | Telegram Bot – Send a Text Message | 6 | Sends to group |

**Date filter** was deleted (it was in wrong position — between HTTP and XML). Needs to be re-added AFTER Iterator.

---

## What Works
- HTTP module fetches RSS → returns `Data` as Long String ✅ (`Parse response: false`)
- XML module parses the string → returns `rssCollection` ✅
- Claude module: system prompt (ToV v2) set correctly ✅
- OpenAI module: gpt-4o-mini, SKIP filter prompt set ✅
- Telegram module: connected ✅
- SKIP filter: `Choices[]: Message.Content` does not contain "Skip" ✅

---

## What's Broken

### 1. Iterator array empty
**Problem:** Iterator (20) has `Array: {{21.rss.channel.item}}` but it returns empty.  
**Cause:** Unknown — XML output is `rssCollection` but inner path is unconfirmed.  
**Fix needed:** Expand XML module output (Operation 1 → rssCollection) to find actual path to items array. Likely one of:
- `{{21.rss.channel.item[]}}`
- `{{21.rss.channel[].item[]}}`
- Something else — need to inspect actual structure

### 2. Jina URL field wrong
**Problem:** `URL to Read` field has `{{20.link}}` — won't work until Iterator outputs correctly.  
**Fix needed:** Once Iterator is fixed and we know field names, use correct path for article URL. In Google News RSS, URL is in `<link>` tag → likely `{{20.link}}` (correct IF Iterator exposes it directly).  
**Note:** Google News RSS links are redirects (`https://news.google.com/rss/articles/CBMi...`). Test if Jina handles redirects — it should.

### 3. Claude Content field references
**Current state:**
```
Ось стаття з RSS:
Заголовок: {{20.value.title}}  ← needs verification
Текст: {{11.content}}          ← correct (Jina output)
Джерело: {{20.value.link}}     ← needs verification

Напиши пост для Telegram-групи українського TCG магазину.
```
**Fix needed:** After Iterator is fixed, verify field names. Might be `{{20.title}}` not `{{20.value.title}}`.

### 4. Date filter missing
**Fix needed:** Add filter between Iterator (20) and Jina (11):  
- Condition: `{{20.pubDate}}` (or whatever pubDate field is named after XML parse)  
- Operator: Later than  
- Value: `addDays(now; -3)`

---

## RSS Source
```
https://news.google.com/rss/search?q=pokemon+tcg+-pocket&hl=en&gl=US&ceid=US:en
```
Confirmed fresh in browser (articles from June 21-26, 2026). HTTP module fetches it correctly.

---

## System Prompts

### Claude (Anthropic) — System Prompt (ToV v2):
```
Ти редактор Telegram-групи магазину колекційних карт Booster Shop. Пиши лише українською, без русизмів. TCG-терміни залишай англійською (SAR, SIR, PSA 10, alt art, reprint тощо).

Якщо текст про одного випадкового користувача Reddit — пост має бути про явище або феномен, який він ілюструє, не про самого користувача.

Формат: 3-4 абзаци, 100-180 слів, 2-4 емодзі як акценти в тексті. Без markdown, без реклами магазину, без вигаданих деталей. Не починай з "На Reddit", "Хтось", "Якийсь". Якщо є конкретні деталі (назва карти, ціна, дата) — обов'язково використовуй їх. Не узагальнюй. Короткі речення. Максимум 2 речення на абзац. Не пояснюй явище повністю — зачіпляй думку і залишай читача думати.

Початок поста — чергуй кожен раз: спостереження, конкретний факт, запитання або дія.

Уникай такого стилю:
"Vintage holo — це карти ранніх сетів Pokémon TCG..." Це стаття Вікіпедії. Не пост.

Не рекламуй магазин, не вигадуй факти, не пиши "підписуйтесь/ставте лайки".

Приклад якісного поста:
Іноді забуваєш, наскільки вузькими бувають колекції Pokémon-карт 👀

Наприклад, існують колекціонери, які роками збирають лише одного покемона. Цього разу в центрі уваги — Bulbasaur 🌱

Нова 30th Promo вже з'явилась у китайській версії приблизно за 18€, а японська ще навіть не вийшла. Серед тематичних колекціонерів вже почалися звичні роздуми: брати зараз чи чекати японський реліз? 🤔

І найцікавіше тут навіть не питання ціни. Хтось збирає SAR ✨ Хтось полює на повні сети 📚 А хтось настільки сфокусований на одному покемоні, що нова версія Bulbasaur стає окремою подією для колекції 🌱
```

### OpenAI (GPT-4o-mini) — System Prompt:
```
Ти фінальний редактор українськомовного Telegram-каналу про TCG: Pokémon, One Piece Card Game, Magic: The Gathering, Yu-Gi-Oh! та суміжні колекційні карткові продукти.
Тобі надходить чернетка поста та/або текст зі сторінки-джерела. Твоє завдання — вирішити, чи з цього можна зробити корисний пост для аудиторії колекціонерів і покупців TCG в Україні.
Головний принцип: не роби пост заради поста. Якщо в матеріалі немає конкретної цінності — поверни тільки: SKIP

[повний промпт зберігається в OpenAI модулі Make.com]
```
**OpenAI Message 2 (User):** `{{3.text_response}}` (Claude output)

---

## Suggested Fix Order for Next Agent
1. Run XML module alone → expand `rssCollection` output → find actual path to items
2. Update Iterator Array field with correct path
3. Run Iterator → confirm items come through with title/link/pubDate fields
4. Check exact field names → fix Jina URL + Claude Content mappings
5. Add date filter after Iterator (pubDate > addDays(now; -3))
6. Run full pipeline → check output at each step
7. Set scenario schedule to "Every 4 hours" or "Once a day at 10:00"
8. Switch from "Run once" to active

## After Pipeline Works
- Add YGO scenario (source: https://www.ygorganization.com/feed/ — confirmed working, good content)
- Add One Piece TCG scenario (source TBD)
- Phase 2: photo support (extract og:image from Jina output, Router: has photo → Send Photo, no photo → Send Text)

---

## Files
- `plans/tg-tone-of-voice.md` — ToV v2, system prompt history
- `plans/tg-content-automation-plan_2026-06-18.md` — full plan + status
