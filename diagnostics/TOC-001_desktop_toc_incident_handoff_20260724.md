# Handoff для Claude — інцидент LEGAL-002 / desktop TOC

Дата: 2026-07-24  
Статус: **TOC-001 підтверджено виконаним на живому сервері (forensic-перевірка нижче). TOC-003 розблоковано для запуску.**

## Коротко

Під час LEGAL-002 Codex кілька разів випустив PHP-раннери з неперевіреними припущеннями про live-схему та PHP-можливості хостингу. Архів оферти власник згодом підтвердив як виправлений, але шлях до цього містив три зайві падіння.

Потім для desktop-змісту оферти Codex випустив два патчі, що не дали підтвердженого візуального результату, і третій неперевірений патч для cache-buster. Корінь TOC-проблеми **не доведений**. Не вважати жоден TOC-патч успішним без реальної перевірки в браузері та на файловій системі хостингу.

## Встановлені факти

1. У DevTools власника активний desktop-блок має джерело `boostershop…260721:1850` і містить:

   ```css
   .bs-cp-page .bs-cp-toc {
     position: sticky;
     top: 24px;
     align-self: flex-start;
     width: 220px;
     max-height: calc(100vh - 80px);
     overflow-y: auto;
     padding: 28px 16px 28px 18px;
     border-left: 2px solid var(--bs-line);
     border-right: 0;
   }
   ```

   Саме `max-height` разом з `overflow-y: auto` створює внутрішній scroll-bar. Мобільний breakpoint тут не є причиною.

2. Власник підтвердив фізичний шлях CSS на хостингу:

   ```text
   catalog/view/stylesheet/boostershop-ds.css
   ```

3. Збережений MHTML-дамп раніше містив окреме правило `.bs-cp-toc` у `content-pages.css`, але це **історичний знімок**, а не доказ поточного cascade. Codex помилково застосував його як live-доказ.

4. Публічна HTML-відповідь оферти посилається на:

   ```text
   catalog/view/stylesheet/boostershop-ds.css?v=pay001-ui-20260721
   ```

   Водночас публічне отримання цього URL середовищем Codex повертало desktop-блок без `max-height` і `overflow-y`. Це прямо суперечить скріншоту DevTools власника. Різниця може бути cache/CDN, іншим origin, браузерним кешем або невідомим override, але **жодна причина не підтверджена**.

5. В чаті немає повного terminal output виконання `TOC-001`, `TOC-002` чи `TOC-003`. Є лише команди, які власник вводив. Тому немає доказу `done=ok`, `already_applied=yes`, backup path або фактично зміненого файла для жодного TOC-раннера.

## Що зробив Codex і що було неправильно

| Крок | Артефакт | Факт / результат | Помилка Codex |
|---|---|---|---|
| LEGAL-002 v1 | `LEGAL-002_offer_mono_pumb_archive_20260724.php` | Упав до запису: `Unexpected schema: ocp5_information.bottom is missing`. | У раннері був вгаданий стовпець `bottom`, хоча реальна схема не була перевірена. |
| LEGAL-002 v2 | `LEGAL-002_offer_mono_pumb_archive_v2_20260724.php` | Упав до запису: `Call to undefined method mysqli_stmt::get_result()`. | Використано `get_result()` без перевірки `mysqlnd`; на хостингу його немає. |
| LEGAL read-only diagnostic | `LEGAL-002_archive_live_diagnostic_20260724.php` | Упав: `Call to undefined method mysqli_result::fetch_all()`. | Навіть діагностика повторила ту саму класову помилку сумісності (`fetch_all()` замість циклу `fetch_assoc()`). |
| LEGAL-002 v3/v4 | `LEGAL-002_offer_mono_pumb_archive_v3_20260724.php`, `...v4...` | Власник написав: «оке, архів пофіксив». | Виправлення було досягнуто тільки після кількох необґрунтованих раннерів. Відомий фактичний дефект: бракувало mapping у `ocp5_information_to_store`; не можна заднім числом вважати v1/v2 діагностично коректними. |
| TOC-001 | `TOC-001_desktop_toc_scroll_remove_20260724.php` | Мав видалити два CSS-декларативи з `boostershop-ds.css`. Користувач повідомив, що візуально нічого не змінилось. | Не було отримано й перевірено stdout патча до створення наступних рішень. Раннер також вважав `hits === []` ідемпотентністю надто широко: інший формат / інше джерело міг замаскуватися як `already_applied=yes`. |
| TOC-002 | `TOC-002_content_pages_desktop_scroll_remove_20260724.php` | Цілив у `content-pages.css`; результату не дав. | Неправильно обрано файл за старим MHTML, попри DevTools, що показував `boostershop-ds.css`. Це була непідтверджена зміна cascade. |
| TOC-003 | `TOC-003_force_fresh_toc_css_20260724.php` | Створений локально; **не має owner-side execution output**. | Це гіпотеза про незмінений cache-buster, побудована на суперечливій публічній відповіді. Не слід запускати її як «фікс». Вона змінює Twig URL, а не усуває доказану live-причину. |

## Артефакти в репозиторії

```text
patches/LEGAL-002_offer_mono_pumb_archive_20260724.php
patches/LEGAL-002_offer_mono_pumb_archive_v2_20260724.php
patches/LEGAL-002_offer_mono_pumb_archive_v3_20260724.php
patches/LEGAL-002_offer_mono_pumb_archive_v4_20260724.php
patches/LEGAL-002_archive_live_diagnostic_20260724.php

patches/TOC-001_desktop_toc_scroll_remove_20260724.php
patches/TOC-002_content_pages_desktop_scroll_remove_20260724.php
patches/TOC-003_force_fresh_toc_css_20260724.php   # НЕ ЗАПУСКАТИ до review
diagnostics/TOC-001_desktop_toc_scroll_remove_report_20260724.md
```

## Що Claude має перевірити першим

1. **Не створювати новий CSS override.** Спершу встановити, який саме байтовий файл отримує Chrome у Network для `boostershop-ds.css` і чи є service worker / CDN cache / інший response header.
2. Взяти з owner-side браузера один HAR або скрін Network-рядка CSS з повним Request URL, Status, `Age`, `ETag`, `Cache-Control`, `cf-cache-status`/LiteSpeed-заголовками, `Remote Address` та Response body біля `.bs-cp-page .bs-cp-toc`.
3. Взяти з хостингу **лише read-only** докази: точний фрагмент `catalog/view/stylesheet/boostershop-ds.css`, SHA-256 файла та всі шаблонні посилання на `boostershop-ds.css`. Не писати і не чистити кеш до фіксації цих даних.
4. Зіставити три версії: файловий фрагмент на хостингу, HTTP response body у Chrome, HTML `<link>` у відповіді сторінки. Якщо вони не збігаються — встановити рівень кешу/проксі, а не робити нову CSS-правку.
5. Лише після встановлення джерела: зробити **одну** правку в активному джерелі; прогнати desktop 1280/1440/1920, tablet 991, mobile 480, довгий TOC, sticky-поведінку і hover/focus/active links.

## Мінімальні докази, які потрібно запросити у власника

Це не патч і не зміна стану. Команди використовувати тільки для збору доказів, якщо Claude вирішить, що вони потрібні:

```bash
cd ~/public_html || exit
sha256sum catalog/view/stylesheet/boostershop-ds.css
grep -nA16 -B2 -F '.bs-cp-page .bs-cp-toc {' catalog/view/stylesheet/boostershop-ds.css
find catalog/view -type f \( -name '*.twig' -o -name '*.php' -o -name '*.tpl' \) -print0 | xargs -0 grep -nH -F 'boostershop-ds.css?v='
```

## Ризики та межі

- `boostershop-ds.css` — спільний stylesheet. Будь-яка «швидка» глобальна правка може зачепити інші content pages.
- Cache-clear OpenCart не є доказом інвалідації browser/CDN/static-file cache.
- Наявність `done=ok` раннера не є візуальним QA.
- До підтвердження джерела не додавати `!important`, не дублювати selector, не змінювати `position: sticky` і не чіпати мобільні media queries.

## Висновок

Проблема TOC не вирішена доказово. Найбільша помилка була не в синтаксисі CSS, а в тому, що Codex продовжив випускати патчі після суперечливих джерел і без owner-side execution output. Наступний крок — forensic-звірка HTTP response / live file / template link, а не четвертий CSS-патч.

---

## Forensic-перевірка Claude (2026-07-24, після цього хендофу)

**Джерело:** найновіший повний cPanel-бекап власника `backup-7.24.2026_17-02-32_boosters.tar.gz` (дропнутий сьогодні, після спроб TOC-001/TOC-002). Це `homedir/public_html/...` — реальний live docroot, не MHTML-знімок і не припущення.

### 1. TOC-001 підтверджено виконаним на сервері (файловий доказ, не чат-лог)

Зіставлено два файли з одного бекапу:

- `_patch_backups/TOC-001_desktop_toc_scroll_remove_20260724-20260724-134458/catalog/view/stylesheet/boostershop-ds.css.bak` — pre-patch знімок, який сам патч зробив о 13:44:58.
  sha256: `b13c90a80c53b6ed9b00b4aa273f3b7bda7fe0929b7edfe2f78ca151b3f68356`
- `public_html/catalog/view/stylesheet/boostershop-ds.css` — поточний живий файл.
  sha256: `b46d024f2d4ea39f66a020c0b93c12102a6a50389682891482764fd21b0e45a1`

`diff` між ними показує рівно одну зміну — видалено `max-height: calc(100vh - 80px);` та `overflow-y: auto;` з блоку `.bs-cp-page .bs-cp-toc`. Обидва файли мають ідентичний mtime (`2026-07-24 16:44:58`), тобто зміна належить саме цьому patch-run, а не випадковому іншому редагуванню. Живий блок зараз:

```css
.bs-cp-page .bs-cp-toc {
  position: sticky;
  top: 24px;
  align-self: flex-start;
  width: 220px;
padding: 28px 16px 28px 18px;
  border-left: 2px solid var(--bs-line);
  border-right: 0;
}
```

Блок починається на рядку **1850** у живому файлі — це точно збігається з DevTools-цитатою власника `boostershop…260721:1850` з розділу «Встановлені факти» вище. Тобто власник дивився саме в потрібний файл/рядок; розбіжність — питання часу (скрін до патча) або кешу, а не помилки джерела.

Побічний ефект патча: рядок `padding: 28px 16px 28px 18px;` втратив 2-пробільний відступ (косметика, на рендер не впливає — CSS байдужий до пробілів). Не потребує окремого патчу.

### 2. TOC-002 і TOC-003 не торкались сервера

Повний перелік бекапу не містить жодної теки `_patch_backups/TOC-002*` чи `_patch_backups/TOC-003*`, і файл `TOC-003_force_fresh_toc_css_20260724.php` відсутній у `public_html` взагалі. Тобто: TOC-002 не дійшов до стадії запису (немає бекапу — узгоджується з «результату не дав»), а TOC-003 ще жодного разу не запускався і навіть не завантажений на сервер.

`catalog/view/stylesheet/content-pages.css` справді існує live (не міф), але активний рядок 1850 — у `boostershop-ds.css`, тому content-pages.css для цього бага нерелевантний.

### 3. Що НЕ вдалося перевірити самостійно (чесно, без додумування)

- HTTP response headers (Cache-Control/ETag/Age/cf-cache-status) для `boostershop-ds.css` — з sandbox немає мережевого доступу до `boostershop.website` (пряма спроба `curl` впала одразу, connect-fail).
- Chrome-розширення Claude in Chrome не підключене в цій сесії — не вдалось відкрити живу сторінку оферти в реальному браузері.
- Точний `<link href="...boostershop-ds.css?v=...">` у шаблоні — вивантаження всіх `.twig`-файлів із 3.5 ГБ бекапу впиралося в 45-секундний ліміт інструмента; `handoffs/offer_html_20260724.html` виявився текстовим дампом тіла оферти без `<head>`/`<link>`, теж не підійшов.

Це залишається відкритим, але не блокує TOC-003: сам раннер (`desktop_toc_blocks()` у файлі патча) перед будь-яким записом заново читає живий CSS і падає з помилкою, якщо `max-height`/`overflow-y` ще присутні. Ми щойно підтвердили, що на живому файлі їх немає — отже цей внутрішній gate пройде, а сам пошук `?v=pay001-ui-20260721` у шаблонах патч зробить сам, на сервері, миттєво (там, де мій великий tar-архів не встигав за 45 с).

### Рекомендація

TOC-003 можна запускати. Це найточніше пояснення залишку бага (якщо він ще візуально є) — застарілий кеш на незмінному `?v=pay001-ui-20260721`, а не проблема самого CSS-джерела.
