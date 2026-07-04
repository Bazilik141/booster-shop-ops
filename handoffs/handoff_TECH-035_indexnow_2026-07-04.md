# Codex Handoff — TECH-035
## IndexNow integration (key file + ping on sitemap change)

_Date: 2026-07-04. Author: Claude. Risk gate: **Low** (additive only: 2 new files + 1 crontab line; no existing file modified)._

---

## 1. Task ID

TECH-035 — IndexNow for Booster Shop (Bing / AI-crawler fast discovery)

## 2. Context

- GSC Sitemaps processor is stuck property-level (TECH-005-DEEP, diagnostics 2026-07-03); indexation itself works (52/57), Google discovery is fine organically. IndexNow does NOT affect Google — Google не підтримує протокол.
- Goal channel: **Bing** (sitemap-full.xml зараз в обробці BWT), DuckDuckGo, Yandex, AI-краулери, що читають IndexNow.
- Server: shared cPanel (HostIQ), `/home2/boosters/public_html/`, bash + curl + python3 available, cron working (див. `sitemap-regen.sh`, щодня 04:15, atomic publish `sitemap-full.xml` + mirror `sitemap_index.xml`).
- Canonical URL list = `<loc>` елементи `/home2/boosters/public_html/sitemap-full.xml` (59 URL на 2026-07-04, зростає).

## 3. Goal

Після кожного добового regen автоматично сповіщати IndexNow API про **нові/змінені** URL з sitemap. Перший запуск — bulk submit усіх поточних URL.

## 4. What to change

1. **Generate key (one-time):** `KEY=$(openssl rand -hex 16)`. Створити `/home2/boosters/public_html/${KEY}.txt` з вмістом = KEY (один рядок, без переносу зайвого, chmod 644).
2. **New script `~/indexnow-ping.sh`** (Codex пише код; спека):
   - Витягнути всі `<loc>` з `/home2/boosters/public_html/sitemap-full.xml` (python3 або grep/sed — як у `sitemap-regen.sh` стилістично).
   - Порівняти зі state-файлом `~/logs/indexnow-state.txt` (список URL попереднього успішного сабміту). Нові URL (відсутні в state) → сабмітити. State відсутній → сабмітити всі (initial bulk).
   - Submit: `POST https://api.indexnow.org/indexnow`, `Content-Type: application/json; charset=utf-8`, body:
     ```json
     {"host":"boostershop.website","key":"<KEY>","keyLocation":"https://boostershop.website/<KEY>.txt","urlList":[...]}
     ```
   - HTTP 200 або 202 = успіх → перезаписати state поточним повним списком. Інший код → state НЕ чіпати, залогувати помилку.
   - Лог: `~/logs/indexnow.log` — рядок `TS OK submitted=N http=202` / `TS SKIP no-new-urls` / `TS FAIL http=4xx`.
   - Нема нових URL → нічого не слати (IndexNow не любить порожні/повторні сабміти).
   - `set -u`; timeout curl 30s; KEY зберігати змінною в скрипті (не в git — у репо-копії плейсхолдер `<KEY>`).
3. **Crontab:** додати рядок `20 4 * * * /home2/boosters/indexnow-ping.sh >> /home2/boosters/logs/indexnow.log 2>&1` (05 хв після regen). НЕ редагувати існуючий рядок regen.
4. **Repo mirror:** копію скрипта (з `<KEY>` плейсхолдером) покласти в `patches/indexnow-ping.sh`.

## 5. Do not touch

- `sitemap-regen.sh` (проверений робочий скрипт — не вбудовувати ping всередину)
- `sitemap-full.xml`, `sitemap_index.xml`, `/uk-ua/sitemap.xml` (генерація/вміст)
- `robots.txt`, `.htaccess`, canonical, redirects
- checkout / payment / fiscalization, `merchant-feed.tsv`, schema/JSON-LD
- OpenCart PHP/Twig/DB — задача суто bash/cron
- Існуючі crontab-рядки

## 6. Likely files / areas

- NEW: `/home2/boosters/public_html/<KEY>.txt`, `/home2/boosters/indexnow-ping.sh`, `~/logs/indexnow-state.txt`, `~/logs/indexnow.log` (likely, not confirmed — Codex should verify actual home layout: script path у crontab, наявність `~/logs/`)
- crontab користувача `boosters` (+1 рядок)
- Repo: `patches/indexnow-ping.sh`

## 7. Acceptance criteria

- `curl -s https://boostershop.website/<KEY>.txt` → 200, body == KEY
- Перший ручний запуск `bash ~/indexnow-ping.sh` → лог `OK submitted=59 http=200|202` (число = поточна к-сть locs)
- Повторний запуск одразу → лог `SKIP no-new-urls`, нічого не надіслано
- state-файл містить рівно всі поточні `<loc>`
- `crontab -l` містить новий рядок 04:20; наступного ранку в `indexnow.log` є свіжий запис
- Жоден існуючий файл не змінено (`git status` чистий по protected-зонах; md5 sitemap-файлів незмінні)

## 8. QA / smoke test

1. Key file 200 + вміст (крит. 1).
2. Ручний запуск ×2 (крит. 2–3).
3. Негативний тест: тимчасово зіпсувати KEY у скрипті → запуск → `FAIL http=403`, state незмінний → повернути KEY.
4. Через 2–4 дні: Bing Webmaster → розділ IndexNow → лічильник поданих URL > 0.
5. Смоук по сайту не потрібен (фронт не зачеплено), але перевірити що `https://boostershop.website/` 200 після crontab-змін (тривіально).

## 9. Rollback note

`crontab -e` → видалити рядок 04:20; `rm ~/indexnow-ping.sh ~/logs/indexnow-state.txt /home2/boosters/public_html/<KEY>.txt`. Жодного впливу на сайт/індексацію — протокол advisory-only.

## 10. Recommended status after execution

Codex report → Claude review → Notion `Done` + watch-note «стежимо за лічильником IndexNow у BWT» (per ROADMAP_SOP DoD для SEO: серверна частина закрита, зовнішнє підтвердження — watch-only). Dashboard mirror sync обов'язково.
