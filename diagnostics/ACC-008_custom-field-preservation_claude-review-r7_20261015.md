# Independent Claude CLI review вЂ” ACC-008 custom-field preservation

Date: 2026-10-15
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `b5f37f9f82fade7e51f51d2592dc4c0b40f211df2aa499d1f38ae366dfd76da6`
Packet SHA-256: `a63caf8b3bd7668883c96e1ed22402cc5f0ad9581682640f5056c4e0a397680a`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Source Review OK**

Вердикт обмежений. Він стосується лише r7-кандидата `b5f37f9f82fade7e51f51d2592dc4c0b40f211df2aa499d1f38ae366dfd76da6` як **read-only збирача метаданих**, у якому apply безумовно вимкнено (`apply_requires_reviewed_runtime_admission`).

Вердикт **не** означає:
- дозвіл на deploy чи apply;
- прийняття live-метаданих або подій;
- перевірку web-PHP;
- проходження G2;
- будь-який дозвіл на WP3.

Дев'ять payload-тіл я не перечитував. Їх прийнято лише через наданий ланцюг ідентичності r6 (хеші я не звіряв) і в межах обмеженого r6-вердикту.

## Обов'язкові дефекти

**Немає** для межі «безпечний read-only збір».

## Незалежна перевірка ремонтів r7

**1. `theme.language_id`.**
- За наданим кодом catalog `Theme::getTheme` фільтрує за `store_id/route/status`.
- Admin `addTheme/editTheme` пише `store_id/route/code/status/date_added`.
- Catalog event читає `code`.
- Вимога колонок `route/store_id/code/status` відповідає власному контракту. Наявність `language_id` нешкідлива.
- Запит вибирає лише `route, store_id, status`, тож тіло шаблону (`code`) не виводиться.
- Активний override для чотирьох маршрутів і далі блокує.

**2. Події.**
- `Event::trigger` будує regex `'/^' … '/'` без кінцевого якоря, тобто збіг іде за префіксом. Зауваження r6 підтверджено.
- Перехід на повний інвентар `status = 1` без SQL-класифікатора правильний.
- `LIMIT 129` з cap 128 дає fail-closed при переповненні.
- Вибрані колонки (`code/trigger/action/sort_order`) неперсональні.
- `trigger` взято в зворотні лапки.

**3. `acc008_rows()`.** Тепер розрізняються три результати `fetch()`:
- `true` — рядок;
- `null` — нормальний EOF;
- `false` або ненульовий `errno` — помилка → `metadata_fetch_failed`.

`$fetch` завжди визначено, бо присвоєння виконується хоча б раз. `finally` закриває statement на всіх шляхах, зокрема при переповненні cap з незчитаними рядками. Копіювання значень із прив'язаних посилань коректне. Часткова вибірка більше не може стати «повною».

**4. Web-guard.**
- `PHP_SAPI !== 'cli'` → 403 без тіла.
- Спрацьовує до config, DB, payload і будь-якого виводу.
- Оголошення функцій виконання не запускають.
- Хостова CLI-SAPI справді `cli`: r6-guard із тією самою умовою був пройдений на хості.

**5. Відсутність мутацій.**
- Dry-run пише лише тимчасові lint-файли поза webroot і видаляє їх.
- Гілка `already_applied` при `!$dry` видаляє лише сам runner.
- Гілка rollback вимагає наявного маніфесту з хешами payload. Apply ніколи не виконувався, тому такого маніфесту немає.
- Метадані виводяться **до** стоп-винятку, отже збір відбудеться навіть за очікуваної зупинки.

**6. Тести.**
- Метадані-фікстура виконує точні тіла функцій runner (через `eval` діапазону функцій) під `php -n`.
- Тести дискримінують:
  - `language_id`;
  - наявність `LIKE` чи відсутність `LIMIT 129`;
  - усі гілки збою statement;
  - закриття statement.
- Web-SAPI 403 перевірено реальним `php -S`.
- Це не доказ live MySQL, і звіт це правильно визнає.

## Неблокуючі зауваження

Бажано врахувати зараз, щоб не витрачати зайвий хост-раунд.

1. **Очікуваний результат практично завжди — стоп.**
   - Стоковий OC4 має активні DB-події (activity, statistics, theme, language тощо), тому `dry_run=ok` фактично недосяжний.
   - Звіт треба уточнити: замість «може завершитися» написати «очікувано завершиться `active_override_or_event_requires_independent_review` після рядка `metadata=`».
   - Фраза «Coverage includes account/customer, account/custom_field…» описує вже видалений класифікатор і застаріла.
2. **Переповнення подій не дає жодних метаданих.** Вивід формується після всіх запитів, тому при більш ніж 128 подіях власник отримає лише `metadata_result_cap`. Варто спершу вивести `COUNT(*)` активних подій або підняти cap з окремим обґрунтуванням.
3. **Overrides перевіряються лише за точним збігом чотирьох маршрутів.**
   - Нестандартний формат `route` (legacy-префікс, розширення) було б пропущено.
   - Для узгодженості з принципом «без класифікатора» краще виводити всі активні `route/store_id` з обмеженим cap.
   - На безпеку це не впливає: подія `theme` і так зупинить.
4. **Застаріла копія r6 у `public_html`.** Власник раніше завантажив runner у webroot, а dry-run не видаляє файл.
   - Під web-SAPI r6-guard падає на невизначеній `STDERR` (fatal) і нічого не виконує, але це прибирання.
   - В інструкції варто прямо вказати: видалити або замінити копію в `public_html` і звірити хеш файлу, який реально запускається.
5. **`MYSQL_OPT_RECONNECT is deprecated` вказує на mysqli, зібраний із libmysqlclient, а не mysqlnd.**
   - Runner сумісний (`bind_result`, без `get_result/fetch_all`). Запити до information_schema на хості r6 уже виконалися.
   - Для apply-допуску варто зафіксувати `mysqli_get_client_info()` для CLI і web. У libmysql-режимі семантика `fetch()` (усічення) і поведінка OpenCart-драйвера можуть відрізнятися.
6. **Дешево зібрати зараз, щоб скоротити runtime-шлюз.** Усе нижче — неперсональні read-only дані:
   - `ENGINE` для `customer_activity` та інших таблиць, яких торкаються чотири save-потоки;
   - значення `config_customer_activity`;
   - булевий прапор `p:` для `adminEvhenii/config.php`. Читати його текстовим regex, **не** через `require`, бо це дало б повторне визначення констант і warnings.
7. **Гігієна.**
   - `preflight_contract_tests.php` створює `fixture-config/` всередині каталогу витягнутого backup-джерела. Краще використати окремий scratch-каталог.
   - Фікстура не перевіряє, що SELECT теми не включає `code` і що в подіях є `status = 1`.
   - У вивід потрапляє абсолютний `cwd` (успадковано з r6; санітизується вручну).

## Покриття та обмеження

**Прочитано повністю:**
- поточний `runner.php` і diff проти r6;
- `preflight_contract_tests.php`, `run_checks.py`, `build_patch.py`, `prepare_preflight_review.py`, `repair-r7.md`;
- reuse-JSON, звіт, r6-вердикт і closure;
- host-evidence r6;
- три файли теми;
- `system/engine/event.php`;
- політику рев'ю та handoff.

**Не читалося:**
- дев'ять payload-тіл (прийнято лише через ідентичність r6);
- startup-реєстрація подій і file-config `action_event`;
- Loader/Proxy, Pinta;
- admin `config.php`;
- session/login/First15.

Для межі read-only збору цього достатньо. Для apply — недостатньо.

**Нічого не виконувалось:** ні PHP, ні MySQL, ні хеші, ні браузер. Фікстури моделюють протокол mysqli, а не реальний сервер чи libmysqlclient.

## Наступні докази від власника

1. Завантажити r7 над `public_html`, а копію в `public_html` видалити або замінити. Звірити `sha256 = b5f37f9f…6da6` і запустити:
   ```
   cd ~/public_html && php ../ACC-008_custom-field-preservation_20261015.php --dry-run
   ```
   Повернути санітизований вивід: 5 lint-рядків, `php_version`, рядок `metadata=` і фінальну помилку. **Apply не виконувати.**
2. Запит на web-URL runner (якщо файл ще у webroot) має дати 403 з порожнім тілом. Це перевірка guard, а не версії web-PHP.
3. Ревізія, що відкриє apply, потребує:
   - повного виконання переліку r6 runtime-admission: класифікація всіх подій, file-config `action_event`, Loader/Proxy/Pinta, admin `p:`, версії CLI і web PHP та client-library, engines включно з `customer_activity`, session/login/First15/checkout, HTTP-захист `_patch_backups`;
   - свіжого незалежного Opus/high рев'ю;
   - свіжого DB-дампу у власника;
   - owner QA.

Жодна подія не allowlist-ується і не вимикається цим вердиктом. WP3 не розпочинається.
