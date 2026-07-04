# Codex Report — TECH-005-DEEP: sitemap та GSC

Date: 2026-07-01

## Scope

Read-only діагностика без змін сайту, БД, sitemap, robots.txt або GSC:

- звірено `TECH-005-DEEP`, `TECH-029`, `TECH-010/012` у repo;
- проаналізовано 16 GSC ZIP-експортів від 2026-07-01;
- перевірено backup `backup-7.1.2026_10-11-00_boosters.tar.gz`;
- перевірено cron, генератор, sitemap, robots.txt, `.htaccess` та access logs;
- виконано живу HTTP/XML/URL/schema-перевірку.

Notion не читався і не змінювався: за `AGENTS.md` статус та коментарі Notion веде Claude.

## Headline

`/sitemap-full.xml` зараз технічно справний, автоматично оновлюється і вже містить нові активні товари та категорію аксесуарів. У backup-логах від першої появи sitemap 9 червня до 1 липня 10:11 не було запитів Google до цього файла. Після вимкнення ModSecurity приблизно о 13:40 у правильному live access log з 13:41 з'явилися підтверджені запити `Google-InspectionTool`, кожен із HTTP `200`. Така часова кореляція робить ModSecurity/WAF головною гіпотезою, але остаточний висновок потребує повторюваного A/B-тесту з активним логом при станах ON та OFF.

## Sitemap: склад і валідність

- 57 унікальних URL: 40 товарів, 11 категорій, 5 інформаційних сторінок, головна.
- XML валідний; дублі, не-HTTPS URL, сторонні домени й query-параметри відсутні.
- Усі 40 товарів мають `lastmod` та image entry.
- Динамічний `/uk-ua/sitemap.xml` має 63 URL; cron коректно відфільтровує 6 manufacturer URL із `?route=product/manufacturer.info`.
- Cron працює щодня о 04:15: 47 URL 10 червня → 57 URL 1 липня. 30 червня було 60, тому короткочасне зменшення на 3 URL варто спостерігати, але проти repo-baseline жоден старий URL не втрачено.

### Нові URL проти repo-baseline 47 URL

1. `Pokemon-booster-box-Mega-Symphonia`
2. `Pokemon-booster-box-Ninja-Spinner`
3. `Pokemon-boosters-Abyss-Eye`
4. `Pokemon-booster-box-Abyss-Eye`
5. `protektory-games-7-days-63-5x88-premium-50sht`
6. `toploudery-kart-35pt-25sht`
7. `mahnitnyy-keys-kart-35pt`
8. `arkush-dlya-kart-9-games-7-days-premium`
9. `booster-ygo-ocg-blazing-dominion-jp`
10. `/catalog/acsesuary`

ACC-001 і ACC-005 не потрапили в sitemap коректно: у backup БД обидва мають `status=0`. Активні ACC-002/003/004/006 присутні. Категорії `/catalog/more-tcg`, Yu-Gi-Oh і MTG уже були в baseline та залишилися.

## Live HTTP та on-page QA

Перевірка 2026-07-01:

```text
/sitemap-full.xml default:   200, application/xml, gzip, 57 loc, XML OK
/sitemap-full.xml identity:  200, 14318 bytes, 57 loc, XML OK
/sitemap-full.xml Google UA: 200, gzip, той самий SHA-256, XML OK
/uk-ua/sitemap.xml:          200, 63 loc, XML OK
/robots.txt:                 200, Sitemap: https://boostershop.website/sitemap-full.xml
```

Один обмежений прохід по всіх 57 URL:

```text
status_200=57
redirected=0
noindex=0
self_canonical=57
canonical_missing=0
request_errors=0
jsonld_parse_errors=0
product_schema_pages=40
breadcrumb_schema_pages=56
```

Поточний сервер не відтворює старий дефект «compressed body without Content-Encoding».

## Google fetch та гіпотеза ModSecurity

Backup access logs охоплюють червень і 1 липня до 10:11:

- `/sitemap-full.xml`: 164 запити загалом;
- Googlebot / Google-InspectionTool: 0;
- ClaudeBot: 143;
- Ahrefs: 12;
- інші: 9.

Google не заблокований на рівні хоста:

- 4,692 Googlebot/InspectionTool запити загалом у доступних логах;
- 962 Google-запити від 15 до 30 червня;
- 172 запити Google до `robots.txt`, із них 101 отримали `200` після HTTPS-redirect;
- у GSC host status немає недавніх DNS/robots/server-connect помилок.

Початкова live-перевірка читала `~/logs/boostershop.website[-ssl_log]`. На сервері ці файли мають розмір `0`; актуальні domlogs доступні через symlink `~/access-logs -> /etc/apache2/logs/domlogs/boosters`. Тому порожній результат старого `grep` не доводив відсутність live-запитів.

Owner вимкнув ModSecurity приблизно о 13:40 і залишив його вимкненим на час тестів. В актуальному `~/access-logs/boostershop.website-ssl_log` зафіксовано:

```text
13:41:18–13:41:19  Google-InspectionTool  200
13:57:52           Google-InspectionTool  200
14:00:26           Google-InspectionTool  200
14:02:33–14:04:31  Google-InspectionTool  200
14:10:19–14:10:20  Google-InspectionTool  200
source IPs: 66.249.68.1, 66.249.68.7, 66.249.68.8
```

Reverse DNS повертає `crawl-66-249-68-{1,7,8}.googlebot.com`, а forward DNS повертає ті самі початкові IP. Отже, це підтверджені запити Google, а не лише підроблений User-Agent. Записи від `77.239.160.165` з User-Agent Googlebot були локальними `curl`-тестами й не враховуються як Google.

Важливі висновки:

- при вимкненому ModSecurity Google Inspection Tool фізично отримує sitemap з HTTP `200`;
- GSC після цього все одно показує загальну помилку «Якщо проблема не зникне, повторіть спробу за кілька годин», отже HTTP-доступ і подальша обробка GSC — окремі етапи;
- перший підтверджений запит о 13:41 майже збігається з вимкненням ModSecurity о 13:40, тому WAF є головною гіпотезою;
- це ще не доказ причинності: немає синхронної контрольної спроби з ModSecurity ON у правильному live access log;
- `Google-InspectionTool` і автоматичний sitemap fetch/parser не слід вважати одним і тим самим механізмом без окремого лог-доказу.

Операційний висновок: ModSecurity не можна залишати вимкненим назавжди. cPanel рекомендує вимикати його лише для діагностики. Якщо A/B підтвердить блокування, потрібен вузький виняток конкретного ModSecurity Rule ID лише для `/sitemap-full.xml`, а не повне вимкнення WAF або безумовний allowlist усіх IP Google.

Офіційні довідки:

- cPanel ModSecurity: https://docs.cpanel.net/cpanel/security/modsecurity/
- cPanel URI-specific rule exception: https://support.cpanel.net/hc/en-us/articles/32395587667479-How-to-whitelist-a-specific-URI-in-ModSecurity
- Google request verification: https://developers.google.com/crawling/docs/crawlers-fetchers/verify-google-requests

## Поточні показники GSC

### Indexing

Coverage export відстає й закінчується 12 червня:

- 38 проіндексовано;
- 11 не проіндексовано: 2 intended noindex, 2 canonical alternates, 7 crawled-not-indexed;
- 0 duplicate-without-canonical після успішної перевірки.

Цей export не описує стан 1 липня і не охоплює нові аксесуари.

### Search performance, останні 28 днів (2–29 червня)

- 48 кліків, 452 покази, CTR 10.6%, середня позиція 12.68.
- Перша половина: 25 кліків / 197 показів / CTR 12.7% / позиція 12.69.
- Друга половина: 23 кліки / 255 показів / CTR 9.0% / позиція 12.67.

Покази зросли на 29.4% при стабільній позиції; кліки зменшилися на 8%, бо CTR знизився. Це розширення видимості з меншою клікабельністю, а не обвал позицій.

Product result appearance дає 28 із 48 кліків і 357 із 452 показів.

### «Надійні/HTTPS» та rich-result pages

Пік 15 червня → 30 червня:

- HTTPS: 45 → 19 (-57.8%);
- Breadcrumbs: 44 → 18 (-59.1%);
- Product snippets: 29 → 8 (-72.4%);
- Merchant listings: 29 → 8 (-72.4%).

Це не підтверджує втрату HTTPS або schema:

- у HTTPS report немає жодної non-HTTPS проблеми;
- Breadcrumbs/Product/Merchant reports не мають critical invalid items;
- live audit бачить валідний HTTPS, Breadcrumb schema на 56 сторінках і Product schema на всіх 40 товарах.

Синхронне падіння кількох звітів без появи invalid URL більше схоже на перерахунок/звуження набору URL у GSC після повторного сканування. Підтвердити це можна URL Inspection для 2–3 сторінок, які зникли зі списку.

### Crawl

- 94.50% запитів → `200`;
- 3.88% → `404`;
- discovery лише 7.69%, refresh crawl 92.31%.

1–14 червня проти 15–28 червня:

- crawl requests: 614 → 392 (-36.2%);
- weighted response time: 512 → 680 ms (+32.9%).

Host status не показує недавніх DNS/robots/server-connect збоїв, але повільніша відповідь і низька частка discovery потребують моніторингу.

У Googlebot logs від 15 червня є 15 відповідей `404`; головні:

- 7× `/product/Pokemon-boosters-mix-lowpull`;
- по 1× старі SKU-slug URL `OP-JP-MIX-MBX`, `YGO-JP-QCAC-BST`, `PKM-JP-MZERO-BLR`, `PKM-JP-SVEX-BLR`, `PKM-JP-INFX-BST`;
- 2× `/favicon.ico`;
- 1× старе зображення.

Попередня теза «301 не потрібен, бо старі SKU URL не були проіндексовані» більше не безпечна: Google їх реально обходить.

## Roadmap reconciliation

Repo mirror:

- `TECH-005-DEEP` — `active`, last updated 2026-06-15;
- `TECH-029` — `done`;
- mirror досі каже «47 сторінок», фактично їх 57;
- `TECH-010/012` залишаються blocked by `TECH-005-DEEP`.

TECH-029 site-side частина справді виконана. TECH-005-DEEP не можна вважати повністю закритим за старими acceptance criteria, бо GSC досі не показує Success / discovered URLs > 0.

Потрібна окрема звірка Claude з Notion-карткою та останніми коментарями; Codex за правилами repo не читає/не змінює Notion.

## Рекомендовані підзадачі

### A. Sitemap inventory — виконано

- [x] нові активні SKU та категорії підтягнулися;
- [x] ACC-001/005 пояснені як disabled;
- [x] усі sitemap URL пройшли HTTP/canonical/noindex/schema QA.

### B. ModSecurity A/B — owner + Claude

Виконати 2–3 однакові цикли, не залишаючи ModSecurity вимкненим після тесту:

1. Увімкнути ModSecurity для `boostershop.website`, зафіксувати серверний час.
2. Через 2–3 хвилини виконати один Live Test `/sitemap-full.xml`.
3. Зберегти текст помилки GSC і останні записи `Google-InspectionTool`.
4. Вимкнути ModSecurity лише для `boostershop.website`, зафіксувати час.
5. Через 2–3 хвилини повторити той самий Live Test і зберегти ті самі дані.
6. Одразу знову увімкнути ModSecurity.

Команди збору доказів:

```bash
date
grep 'Google-InspectionTool' \
  ~/access-logs/boostershop.website-ssl_log | tail -n 20
```

Матриця інтерпретації:

- ON → новий `200`: ModSecurity не блокує цей запит;
- ON → `403`, OFF → `200`: блокування ModSecurity підтверджено;
- ON → немає нового запиту, OFF → стабільно є `200`: сильне підтвердження блокування/drop до access log;
- ON і OFF → `200`, а GSC показує помилку: проблема після HTTP fetch або в інтерфейсі/обробці GSC.

Під час серії потрібно записувати: стан ON/OFF, точний час, IP, User-Agent, HTTP status та повідомлення GSC. Не робити багато повторних запитів поспіль, щоб не змішати результати з throttling GSC.

### C. Звернення до хостингу після підтвердження

Попросити хостинг:

1. перевірити ModSecurity Hits/Audit Log за точними часовими мітками;
2. назвати Rule ID, фазу та дію правила (`deny`, `drop`, `403`);
3. додати виняток лише для цього Rule ID, домену `boostershop.website` та URI `/sitemap-full.xml`;
4. не вимикати весь ruleset і не дозволяти всі IP Google безумовно;
5. якщо в ModSecurity немає hit — перевірити upstream firewall/WAF, LiteSpeed та мережеві журнали.

Готовий текст:

```text
ModSecurity, ймовірно, блокує Google Search Console для
https://boostershop.website/sitemap-full.xml. Після вимкнення ModSecurity
запити Google-InspectionTool з підтверджених IP Google починають надходити
та отримують HTTP 200; контрольні часові мітки A/B додаємо нижче.

Просимо перевірити ModSecurity Hits/Audit Log, визначити конкретний Rule ID
і додати вузький виняток цього правила лише для URI /sitemap-full.xml
на домені boostershop.website. Не вимикати ModSecurity для всього домену.
Якщо ModSecurity hit відсутній, просимо перевірити upstream firewall/WAF
та LiteSpeed logs за тими самими часовими мітками.
```

### D. Google fetch після A/B

Після завершення A/B та повернення ModSecurity у стан ON:

1. повторно подати `/sitemap-full.xml` у розділі GSC Sitemaps;
2. зберегти screenshot одразу після submit і через 24–72 години;
3. перевірити live access log на окремий автоматичний Google fetch;
4. якщо звичайний sitemap URL не обробляється попри стабільний `200`, окремо розглянути новий `sitemap-index.xml` як діагностичний URL; він не обходить WAF.

### E. Нові сторінки — discovery/indexation

Після resubmit перевірити в URL Inspection і, за потреби, вручну подати:

- 4 активні accessories product URL;
- `/catalog/acsesuary`;
- `booster-ygo-ocg-blazing-dominion-jp`.

На момент backup Googlebot ще не відвідував ці 6 URL.

Owner уже успішно відправив на індексацію головну сторінку та кілька нових сторінок товарів аксесуарів. Це підтверджує роботу URL Inspection для HTML-сторінок, але не закриває окрему проблему обробки sitemap.

### F. Visibility diagnostics

- розділити indexed/non-indexed за типом: product/category/info;
- перевірити URL, які зникли з HTTPS/rich-result списків, через live URL Inspection;
- розібрати CTR drop за query/page, не змішуючи його з sitemap;
- перевірити crawl latency і asset-version churn.

### G. Legacy 404 cleanup

Підтвердити мапінг старий SKU URL → поточний canonical URL і підготувати окремий rollbackable 301 patch. Не змішувати з sitemap patch.

## Додаткові дані

Для якісного продовження потрібні лише:

1. таблиця 2–3 повторів A/B: ON/OFF, точний час, GSC result, access-log result;
2. ModSecurity Rule ID / audit-log event або підтвердження хостингу, що hit відсутній;
3. screenshot GSC Sitemaps після повторної подачі й через 24–72 години;
4. latest Notion comments/status по TECH-005-DEEP від Claude;
5. URL Inspection screenshots для 2–3 сторінок, що зникли з HTTPS/rich-result списків.

## Files touched

```text
diagnostics/TECH-005-DEEP_sitemap_gsc_audit_report_20260701.md
```

Сайт, БД, GSC, sitemap і robots.txt не змінювалися. Patch не створювався.

## Rollback

Не потрібен: виконано лише read-only перевірки та створено цей локальний звіт.

## Risks

- Sitemap/robots/canonical/redirects — SEO-risky zone; жодних змін без окремого handoff і owner approval.
- User-Agent сам по собі не є доказом Google; live IP `66.249.68.1/7/8` додатково пройшли reverse + forward DNS verification.
- Часова кореляція ModSecurity OFF → перший Google fetch є сильною гіпотезою, але не замінює повторюваний A/B-контроль.
- ModSecurity має залишатися ON поза коротким контрольованим тестом; постійне вимкнення створює невиправданий ризик для всього OpenCart.
- GSC exports мають різні cutoff dates, тому їх не можна трактувати як один синхронний snapshot 1 липня.
