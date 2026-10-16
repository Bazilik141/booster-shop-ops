# Independent Claude CLI review вЂ” ACC-008 separate runtime evidence

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `8ed70517c429e30bf6c3ff3b718452ce2ba205dd725d02da7b6fc3eeb409d368`
Packet SHA-256: `1b1effea9edc02e1f49177f8cb42835251c11b44b6f2913ca04b9789ab3a49db`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Source Review OK** (обмежений)

Вердикт стосується лише окремого read-only діагностичного файлу `patches/ACC-008_runtime-evidence_20261016.php`. Його SHA за маніфестом: `8ed70517…d368`. Хеш я не звіряв. Покрито також шаблон, білдер, фікстури й тести в тому вигляді, як їх надано в пакеті.

Висновок: як безпечний збирач метаданих файл придатний. Він не пише в БД чи застосунок, не розкриває облікових даних і не читає рядків клієнтів. Публічний web-кінець розкриває лише версії та прапори OPcache.

Вердикт **не означає**:
- допуск apply чи подій;
- прийняття 112 активних подій;
- прийняття джерел;
- G2 чи WP3;
- ревізію основного runner `b5f37f9f…6da6`.

Будь-яка зміна коду діагностики дає новий хеш і потребує нового рев'ю. Зміни лише звіту чи інструкцій власнику нового рев'ю не потребують.

## Обов'язкові дефекти

**Немає** для межі «безпечний read-only збір».

## Незалежна перевірка ключових меж

**1. Web-гілка**
- Виконується до будь-якого config, DB чи сесії. Перед нею лише оголошення.
- Відповідає 403 без тіла, якщо метод не GET, nonce відсутній, є масивом або не відповідає `^[a-f0-9]{32}$`.
- JSON віддається з `no-store` і `nosniff`. Nonce валідований як hex, тож відбиття безпечне (немає XSS чи ін'єкції).
- Розкриваються лише версія PHP, SAPI, клієнт mysqli і прапори OPcache. Це низькочутливі дані, і власник свідомо їх допускає.

**2. CLI-гілка**
- Аргументи відхиляються.
- Файл має лежати саме в cwd.
- Root config виконується під `ob_start`. Будь-який вивід → помилка без друку вмісту.
- Persistent-з'єднання catalog відхиляється до підключення.
- `MYSQLI_REPORT_OFF` і `@new mysqli` пригнічують повідомлення з логіном чи хостом.

**3. DB**
- Лише `SELECT`: `information_schema.TABLES` для 5 таблиць і один ключ `config_customer_activity` з `LIMIT 33` та cap 32.
- `DB_PREFIX` валідується до інтерполяції.
- `value` назовні не виходить, лише булеві значення. Канарка `PRIVATE_SETTING_CANARY` це дискримінує.
- `acc008re_rows` ідентичний за логікою прийнятому в r7: розрізняє `true`/`null`/`false`/`errno` і закриває statement у `finally`.
- Сумісний з libmysqlclient: використовує `bind_result`, без `get_result`/`fetch_all`.

**4. Транспорт**
- Фіксований origin, лише HTTPS (`PROTOCOLS`/`REDIR_PROTOCOLS`).
- `VERIFYPEER` увімкнено, `VERIFYHOST=2`, без редиректів.
- Таймаути 5/10 с.
- Ліміт 16 KiB у `WRITEFUNCTION` і повторно у валідаторі.
- Строга схема: порядок ключів, типи, `sapi≠cli`, точний nonce.
- Сирі відповіді та тексти помилок cURL не друкуються, лише `errno`.
- Метадані серіалізуються через `json_encode`, тому вставити рядок `done=ok` з відповіді неможливо.

**5. Парсер admin-config**
- Не виконує файл.
- Відкидає `if`/`else`/`switch`/цикли/`function`/`class`/`namespace`/`include`/`require`/`eval`, методи `->define`, дублікати, вирази, змінні та невідповідні літерали.
- Повертає лише 7 булевих прапорів. Значення знищуються через `unset`.

**6. Життєвий цикл**
- Метадані друкуються до `unlink`.
- Файл видаляється лише при `verified`. Інакше `done=partial` і `runner_kept=yes`.
- Інших записів немає. Знімок файлів до і після в тестах це підтверджує.

**7. PHP 8.0**
- Синтаксис і API сумісні: стрілкові функції, `JSON_THROW_ON_ERROR`, `random_bytes`, без `never`, `str_contains` чи `match`.
- `$argv` використовується в глобальній області.

## Неблокуючі зауваження

1. **Твердження «парсер fail-safe на неоднозначності» виконане лише частково.** Мовчки пропускаються:
   - `const DB_X = …;`
   - `\define(...)` (у PHP 8 це `T_NAME_FULLY_QUALIFIED`, не `T_STRING`);
   - `defined(..) || define(..)` / `or`;
   - тернарні оператори;
   - `return`/`exit`/`goto` перед визначеннями;
   - octal-літерал порту `03306`.

   Наслідок обмежений: лише хибний булевий прапор `same_*`, без витоку чи мутації. Стоковий конфіг OC4 цього не містить.

   Надійніше зробити allowlist токенів:
   - `T_OPEN_TAG`/`T_CLOSE_TAG`;
   - `define`, `( , ) ; .`;
   - `T_CONSTANT_ENCAPSED_STRING`, `T_LNUMBER`;
   - `T_STRING` для імен констант.

   Решту відхиляти. Якщо не виправлено, у звіті треба писати «fail-safe для типових форм» і зберігати прапори як непідтверджені доказ, доки власник візуально не підтвердить простий формат файлу.

2. **Публічна поверхня після невдачі.**
   - При `done=partial` і при будь-якому `error=` файл лишається в `public_html` без строку дії.
   - Гілка `error=` навіть не друкує `runner_kept`.

   Що зробити:
   - В інструкцію власнику явно додати: «якщо немає `done=ok`, видалити файл вручну».
   - Після запуску перевірити, що URL повертає 404.
   - Як дешеве посилення: web-гілка повертає 403, якщо `filemtime(__FILE__)` старший за ~1 год.

3. **OPcache і видалений файл.** Якщо web покаже `validate_timestamps=false`, окремо перевірити, що видалений скрипт не віддається з кешу. Той самий факт критичний для майбутнього apply основного runner, бо патчені файли можуть не підхопитися.

4. **Кандидати OCMOD — слабкий доказ.**
   - Відображення `adminEvhenii/` → `extension/ocmod/admin/…` лише припущення. Перевірити, що використовує OC4 після перейменування адмінки, можна за `event/modification.php`. Тіло цього файлу я не бачив.
   - `system/engine/*` і `system/config/*` не мають кандидатів.

   Краще додати:
   - перевірку обох варіантів (`admin/` і `adminEvhenii/`);
   - обмежений перелік файлів під `extension/ocmod/` (шляхи коду неперсональні);
   - read-only запит активних рядків таблиці `modification`: `code`/`status`, з cap.

   Інакше `present:false` не можна трактувати як відсутність override, як і визнає звіт.

5. **Прапор `extension_root_matches_public_extension` дає хибний `true`.** Якщо обидва `realpath` повертають `false`, `false===false` дає `true`. Потрібно вимагати, щоб обидва шляхи були не `false`.

6. **CLI-поле `opcache.enabled` читає `opcache.enable`, а не `opcache.enable_cli`.** Для CLI це вводить в оману. Для web поле коректне.

7. **Діагностованість відмов.**
   - Немає явної перевірки `function_exists('token_get_all')`. Розширення tokenizer на хості не гарантоване, а без нього буде лише `unexpected_runtime_evidence_failure`.
   - Корисно друкувати клас винятку без тексту повідомлення.

8. **Білдер.** `read_text()` без `encoding='utf-8'` на Windows залежить від локалі. Зараз вміст ASCII, але це крихко.

9. **Гігієна тестів.**
   - Абсолютні шляхи.
   - Тимчасові корені не прибираються.
   - Невикористана змінна `$scratch`.
   - Немає тестів для: відхилення symlink у `acc008re_path`, хибного `true` з п. 5, `const`/`\define`, відсутності tokenizer.
   - CLI-сценарії перевіряють лише стан «усі джерела відсутні». Збіг хешів покрито тільки юніт-тестом.

10. **Неточність звіту.** «67 identities … of the newest owner backup» не зовсім так. Перші дві intake-партії (28+3 файли) походять з окремих owner-пакетів від 15.10, а не з `backup-10.15.2026_13-09-55`. Дрейф буде видно у виводі, але формулювання варто виправити.

11. **libmysqlclient.** Якщо `setting.value` має тип не `text`, а `LONGTEXT`, `bind_result` без `store_result` спробує виділити ~4 ГБ. Це fatal, і вивід буде відсутній. Відмова все одно безпечна, але це варто знати при діагностиці.

## Покриття та межі доказів

**Прочитано повністю:**
- згенерований діагностичний файл і шаблон;
- `build_runtime_evidence.py`;
- `runtime_fixture_transport.php`, `runtime_evidence_tests.php`;
- `run_runtime_evidence_checks.py`, `prepare_runtime_review.py`;
- маніфест, JSON локальних перевірок, звіт;
- r7-вердикт, closure, хостовий r7 JSON;
- шість intake-маніфестів;
- політику рев'ю та handoff.

**Не читалося:**
- тіла основного runner і `work/.../runner.php`. Хелпер рядків я перевірив безпосередньо в згенерованому файлі;
- `prepare_review.py` (exec-хвіст адаптера);
- 67 файлів-джерел;
- реальні config;
- `event/modification.php`.

**Нічого не виконувалось:** ні PHP, ні DB, ні HTTPS, ні звірка хешів. Фікстури моделюють протокол mysqli і cURL, а не реальний сервер, libmysqlclient чи хостовий web-SAPI. Підтвердження з хостингу (web, DB, OPcache, OCMOD) все ще відсутнє.

## Далі

- Видати власнику один блок дій:
  1. upload у `public_html`;
  2. `sha256 = 8ed70517…d368`;
  3. `cd ~/public_html && php ACC-008_runtime-evidence_20261016.php`;
  4. повернути санітизований вивід;
  5. якщо немає `done=ok` — видалити файл;
  6. перевірити 404 за URL.
- Отримані метадані лише живлять окремий, заново рецензований етап допуску. Жодна подія не allowlist-ується. Для apply потрібні свіжий DB-дамп у власника та owner QA. WP3 не починається.
