# Independent Claude CLI review вЂ” ACC-008 custom-field preservation

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `1fc02c3c519434acdbd2ce24417e69a1e379490fc4d7a68658fadfc320cce40b`
Packet SHA-256: `9756bc5ee2fd0af53e159346d31437b83b192d6a093098a87aad17dd89253426`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Return for changes**

**Межа вердикту.** Вердикт стосується кандидата r9 `patches/ACC-008_custom-field-preservation-r9_20261016.php`. За маніфестом його SHA-256 `1fc02c3c…0b`; сам хеш я не звіряв. Перевірено dev-тіла runner/admission, з яких він збирається, дев'ять повних payload-тіл і надані owning-контракти.

**Payload і guard у межах чотирьох форм.** Знайдено один обов'язковий дефект, і він не в payload. Він у новому шлюзі допуску конфігурації. Дефект безпечний для даних: runner лише зупиниться. Але він робить apply-шлях, імовірно, недосяжним на канонічному OC4-конфігу. Відкладати його до хостового прогону чи QA не можна.

Будь-яке виправлення runner дає новий хеш. Потрібен свіжий незалежний рев'ю, достатньо обмеженого на diff admission і тестів. Apply, deploy, G2 і WP3 цим вердиктом не дозволені.

---

## Обов'язковий дефект

### M1. Строгий парсер конфігу не приймає канонічну граматику OC4, а тести перевіряють лише авторську фікстуру

**Що робить парсер.** `acc008_config_literals()` приймає значення `define` лише як **один** токен: `T_CONSTANT_ENCAPSED_STRING` або `T_LNUMBER`. Після значення одразу має йти `)`.

**Канонічний формат інсталятора.** OC 4 пише `config.php` так: `define('DIR_APPLICATION', DIR_OPENCART . 'catalog/');`. Аналогічно `DIR_EXTENSION`, `DIR_SYSTEM`, `DIR_STORAGE`, `DIR_CONFIG`, `DIR_TEMPLATE`. В admin-конфігу так само `DIR_OPENCART . 'adminEvhenii/'` і `DIR_CATALOG`. На такому файлі парсер зупиниться з `config_literal_grammar_required` ще до DB-preflight. Це станеться на першому ж `DIR_APPLICATION`.

**Доказів зворотного немає:**
- Root-конфіг у r8 **виконувався**, а не парсився. Його токенна форма нічим не підтверджена.
- Тіло r8-парсера для admin у пакеті відсутнє. За r8-рев'ю він відкидав «вирази», але рекомендований тоді allowlist явно включав `.` і `T_STRING` імен констант. Тобто конкатенацію очікували як легітимну. Чи приймав її r8, з пакета не встановити.
- `preflight_contract_tests.php` генерує лише літеральні абсолютні шляхи. Інсталяторну форму не покрито жодним тестом.

**Наслідок.** Перший хостовий dry-run, імовірно, зупиниться. Admission стає недосяжним, а хост-раунд буде змарновано. Ризику для даних немає.

**Потрібно:**
1. Розширити граматику значення:
   - `VALUE := OPERAND ('.' OPERAND)*`;
   - `OPERAND := T_CONSTANT_ENCAPSED_STRING | T_LNUMBER | T_STRING-ім'я`, уже визначене **раніше в цьому ж файлі** тією самою граматикою;
   - значення обчислювати з `$values` без виконання коду.
2. Усе інше й далі відхиляти: невідомі або пізніші імена, `__DIR__`, виклики, оператори, крім `.`, змінні, тернарні вирази, керування потоком.
3. Явно відхиляти літерали з префіксом `b'…'`/`b"…"`. Зараз вони мовчки декодуються як `'…`, див. примітку 6.
4. Додати тести на інсталяторну форму:
   - root і `adminEvhenii/config.php` з ланцюгами `DIR_OPENCART → DIR_SYSTEM → DIR_STORAGE`;
   - `DB_PORT` як рядок `'3306'`;
   - негативні випадки: невизначене ім'я, ім'я, визначене пізніше, `__DIR__ . 'x'`, `CONST . $var`.
5. Канарки приватності `PRIVATE_*` мають і далі не потрапляти у вивід.

Власницьке «підтвердження формату» без байтів не замінює виправлення джерела.

---

## Незалежна перевірка ключових меж (прийнято по суті)

### Guard `checkRawKeys` (єдина зміна payload)

- Викликається лише після успішного `json_decode`, тож JSON уже валідний.
- Байтовий сканер правильно пропускає вміст рядків, включно з `\"`, `\\`, `\uXXXX`. UTF-8 continuation-байти не можуть збігтися з `"` чи `\`.
- Ключем вважається рядок, за яким після JSON-whitespace (`space`, `\t`, `\r`, `\n`) іде `:`.
- Ідентичність ключа береться після `json_decode` самого ключа. Тому `"x"`/`"\u0078"` і `"1"`/`"\u0031"` виявляються як дублікати.
- Рівень стека відокремлює однакові ключі в різних об'єктах і в елементах списків.
- Канонічні рядки на кшталт `"1"`/`"01"` у PHP-масиві після `get_object_vars` не колізують. Пропущеної колізії немає.
- `\0` у ключі відкидається.
- Мутант із видаленням перевірки дискримінується тестом `duplicates:no_write_or_loss`.

### Вісім незмінних тіл

Я перечитав їх повністю, вони збігаються з прийнятою в r6 логікою:
- one-shot `FOR UPDATE` як перше читання;
- ROLLBACK у `finally` для будь-якого `Throwable`;
- `finish(true)` до зміни сесії;
- NP-lookup до `begin()`;
- allowlist лише для numeric `[1-9][0-9]*` з `location`/`status`/групою;
- семантика маркера: пропуск поля / порожній рядок / `[]`;
- серверний `bs_np_v1` у catalog;
- точне відновлення NP-тексту й відмова при зміні в admin;
- вирівнювання груп у admin-модалці;
- відновлена семантика пароля через `isset($this->request->post['customer_id'])`.

### Помилки, `exit` і fatal

Обробник помилок OC у production робить `exit` на warning. Fatal і `exit` теж оминають `finally`. В усіх цих випадках незакомічена транзакція відкочується при закритті **non-persistent** з'єднання. Тому перевірка `p:` для обох конфігів по суті критична, і вона є.

Shutdown-запис сесії в DB-engine у такому шляху теж відкотиться. Це лише втрата сесійних змін цього запиту, без втрати даних клієнта.

### Event, Loader і prefix-поведінка

- `Event::trigger` має regex без `$`, тобто це prefix-збіг. Тому повна інвентаризація 112 ідентичностей з порівнянням `code`/`trigger`/`action`/`sort_order` — правильний підхід.
- Для `account/customer.editCustomer`, `account/address.addAddress` і `account/address.editAddress` із DB-подій збігаються лише три `activity_*` after-події.
- Їхній `addActivity` пише через той самий `$this->db` у тій самій транзакції, а таблиця підтверджено InnoDB.
- `Loader::controller` резолвить callable до before-події. `Loader::callback` резолвить модель після before-події. Catalog file-config не має `model/*/before`, admin має `event/modification.model`.
- При порожньому дереві `extension/ocmod` reroute-цілей немає, а whole-tree перевірка це закриває для обох застосунків.
- Theme-подія є лише в catalog view. Фільтр маршрутів адекватний.

### Метадані

- Лише SELECT через `bind_result`, без `get_result`/`fetch_all`. Сумісно з libmysqlclient 8.0.35.
- Розрізняється `fetch` true/null/false плюс `errno`. Statement закривається у `finally`. Cap-и відмовляють, а не обрізають.
- Порівняння полів і груп суворе, з int-нормалізацією та тим самим порядком ключів.
- Перевіряються engine 5 таблиць, тип `custom_field` і PK.

### Runner

**Порядок кроків:**
1. Identity, anchor і link/traversal-гейти.
2. Host-lint payload поза webroot.
3. Runtime-джерела (80 хешів) і OCMOD-дерево.
4. DB-preflight.
5. Лише потім вимога `--db-backup-confirmed`.

**Backup:** зберігається в `../_patch_backups/<name>`. `mkdir` виконується до першого запису, копії звіряються з `before_sha`.

**Запис:** atomic rename, lint після rename, restore усіх записаних файлів при збої. Helper встановлюється першим, rollback знімає його останнім.

**Rollback:** звіряє manifest проти payload, ціль проти before/after, backup проти `before`. Не вимагає права запису в parent. Self-delete лише при `done=ok`.

**Захист від web:** `PHP_SAPI !== 'cli'` дає 403 до будь-якої роботи.

### PHP 8.0

`T_NAME_FULLY_QUALIFIED`, typed properties і `JSON_THROW_ON_ERROR` доступні у 8.0. `match`, `str_contains`, `never` у runner, admission і guard не використано.

`str_contains` і `str_starts_with` є лише в незмінних owning-файлах ядра. Це історичний код, і на хості вже PHP 8.0.30.

### Приватність

Тіла конфігів і значення не друкуються. Повідомлення відмов містять лише ключі. `unset($config)` виконується після з'єднання. Метадані неперсональні.

### UI

Нових `!important`, `setTimeout`, `position:absolute/fixed` чи magic pixels немає. Єдина зміна шаблонів — прихований `input` без стилів. `setTimeout(220/160)` і `position:absolute` у catalog `address_form.twig` історичні.

---

## Неблокуючі зауваження

Бажано закрити разом із M1, бо ревізія все одно нова.

1. **Startup-реєстрації не інвентаризовані.**
   - Catalog і admin `startup/startup` виконують активні рядки DB-таблиці `startup`. Ці контролери можуть викликати `$this->event->register()` поза таблицею `event`.
   - Не закріплені також інші `action_pre_action`:
     - catalog: `seo_url`, `currency`, `tax`, `marketing`, `error`, `sass`, `api`, `maintenance`, `authorize`;
     - admin: `setting`, `application`, `startup`, `error`, `sass`, `login`, `authorize`, `permission`.
   - Регресії від патча це не створює: невідомий handler поводився б так само і до патча, а транзакція лише додає rollback.
   - Але твердження звіту «only three … match» треба обмежити формулюванням «DB event table + file-config». Краще додати read-only інвентар `startup` (`code`/`action`/`status`/`sort_order`) з блокуючим порівнянням.
2. **Не перевіряються `DIR_CONFIG`, `DIR_TEMPLATE` і `APPLICATION`.** Закріплені `system/config/catalog.php`/`admin.php`, тобто admission `action_event`, і чотири шаблони мають сенс лише тоді, коли ці константи вказують на перевірені шляхи. Після M1 перевірка тривіальна.
3. **Rollback із частковим станом.** Manifest містить лише pending-файли. Можливий сценарій:
   - контролер був уже «applied» до запуску, тому не потрапив у manifest;
   - helper тим часом був pending;
   - rollback видаляє helper, а контролер лишається новим;
   - контролер робить `require_once` відсутнього файлу і дає fatal.

   Рекомендовано відмовляти в rollback, якщо поза manifest лишається payload-файл з `after`-хешем, а helper видаляється.
4. **Whole-tree OCMOD.** Будь-які згенеровані PHP/Twig навіть для нерелевантних модулів зупинять dry-run. Це очікувана безпечна зупинка, яку треба оцінити, а не обійти.
5. **Кеш.** Runner не друкує шлях кешу, похідний від конфігу. Інструкція «config-derived cache cleanup» без такого виводу ризикує призвести до вгаданого шляху.
   - Для коректності очищення, найімовірніше, не обов'язкове: OPcache має `validate_timestamps=on` і `revalidate_freq=2`, Twig має `auto_reload`, а mtime після rename новий.
   - Рекомендовано друкувати relative realpath `DIR_CACHE` (без секретів).
6. **Префікс `b'…'`.** `acc008_config_string()` трактує його як подвійні лапки й повертає `'…`. Для імен і шляхів це закінчиться відмовою, для облікових даних — збоєм з'єднання. Ризику немає, але відмову варто зробити явною (входить у M1).
7. **Admin-попередження.** Попередження guard в admin-адресі замінює field-помилки. Non-NP admin-поля рендеряться без екранування, це історична поведінка. Зараз усі 27 полів вимкнені. Для WP3b це передумова.
8. **Спільний writer First15.** `clearPendingFirst15ForCustomer` поза scope:
   - читає з assoc-декодом і пише без lock;
   - нормалізує вкладені `{}` у `[]`;
   - може затерти новіший JSON.

   Це правдиво зафіксовано як обмеження, а не захист.
9. **Гігієна інструментів.** У тестах абсолютні scratch-шляхи. `prepare_admission_review.py` виконує фрагменти `prepare_review.py`. Це не впливає на payload.

---

## Покриття та межі

**Прочитано повністю:**
- 9 payload-тіл і diff guard;
- `runner.php`, `runtime_admission.php`, `build_admission.py`, `build_patch.py`, admission-маніфест;
- `roundtrip_tests.php`, `preflight_contract_tests.php`, `runtime_dispatch_tests.php`, `run_checks.py`, `render_bootstrap.php`, `prepare_admission_review.py`;
- before-методи чотирьох `save()` і надані owning-методи моделей;
- `framework`, `event`, `loader`, `proxy`, `action`, `factory`, `registry`, `config`, `autoloader`, `model`, `controller`;
- `system/config` (`default`, `catalog`, `admin`);
- обидва `modification`, `language` і `startup/event`/`session`/`extension`/`language`;
- `startup/application`, `startup/customer`, `startup/setting`, catalog `startup/startup`;
- `theme` (catalog і admin);
- `db`, `mysqli`, `session`/`db`/`file`, `request`, `cart/customer`, `template`/`twig`, `language`;
- `login`, `activity` (controller і model), `booster_coupon`, `coupon`;
- checkout: `shipping_address`, `payment_address`, `receiver`, `confirm`;
- Pinta: `area`, `city`, `warehouse`, `street`;
- r6, r8 і r7-closure, host JSON r7 і r8 (CLI і web), `local-checks`, `dispatch`-evidence;
- звіт r9, політику рев'ю, handoff.

**Не читалося (лише ідентичності або відсутні):**
- Twig vendor;
- оригінальні тіла шаблонів і language-файлів;
- інші методи моделей і контролерів;
- catalog і admin `model/setting/startup.php`, admin `startup/startup.php` та інші pre_action startups;
- тіло r8-парсера;
- `prepare_review.py`;
- реальні конфіги.

**Нічого не виконувалося:** ні PHP, ні хеші, ні DB, ні браузер.

Локальні тести працюють на PHP 8.3 з фіктивною БД. Нативний InnoDB rollback, libmysqlclient на хості, AJAX-зони й NP не доведені.

---

## Вимоги до хосту, apply і QA (після виправлення M1 і свіжого рев'ю)

1. **Dry-run.**
   - Завантажити точний новий файл **поза** `public_html`, звірити SHA-256.
   - Запустити `cd ~/public_html && php ../<файл> --dry-run`.
   - Очікуваний вивід:
     - `config_admission=literal_nonpersistent_same_target_and_paths`;
     - 5×`host_payload_lint …:ok`;
     - `runtime_sources=exact_reviewed_sources_and_no_generated_ocmod`;
     - `metadata=…`;
     - `db_preflight=read_only_ok`;
     - `dry_run=ok`, `files_pending=9`.
   - Повернути обмежений вивід. Будь-яка зупинка (config, source, зокрема 13 нових хешів, event, theme, metadata, engine, OCMOD) потребує оцінки, обхід заборонено.
2. **Apply.**
   - Перед apply мати свіжий DB-дамп у власника.
   - Запустити `php ../<файл> --db-backup-confirmed` і зберегти рядок `backup=../_patch_backups/<name>`.
   - Очікувано: `files_changed=9`, `done=ok`, runner видалено.
   - Кеш чистити лише за шляхом, похідним від конфігу.
3. **Rollback.** Повторно завантажити той самий runner і запустити `--rollback=<name>`. Це відкат лише джерел: дані, перезаписані після deploy, так не повертаються.
4. **QA (лише власник):**
   - Збереження `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename` і невідомих ключів після кожної з чотирьох форм.
   - Пропуск поля чи очищення.
   - Admin name-only для відділення, поштомата й courier з `"`/`&` після завантаження зон. Admin-зміна міста має повернути warning без запису.
   - Чужа адреса, permission denied, неавторизований доступ.
   - Default-адреса.
   - First15 і вхід у checkout без оформлення замовлення.
   - mobile, tablet, desktop.
   - Tier 1: валідний поточний 3D-товар замість `FIG-CHARM-001`.
   - Перевірити, що рядок `customer_activity` для `edit` і `address_edit` з'являється.
