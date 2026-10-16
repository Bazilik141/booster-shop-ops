# Independent Claude CLI review — ACC-008 custom-field preservation

Date: 2026-10-15
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `670bd27d6be3f41e25d3e5cd77618687c0e23de3409ad1cdaf52a87e393b9156`
Packet SHA-256: `7b1299b2234cae72ed8cb1379d776ec8b429a56434afb9e96cbb5ee75b54fdfb`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Source Review OK**

Вердикт обмежений. Він стосується лише кандидата `670bd27d6be3f41e25d3e5cd77618687c0e23de3409ad1cdaf52a87e393b9156` як **заблокованого read-only кандидата для hosting-preflight**. Apply у коді безумовно відмовляє (`apply_requires_reviewed_runtime_admission`).

Цей вердикт **не** означає:
- дозвіл на deploy;
- прийняття live-метаданих;
- доказ роботи на PHP 8.0.30;
- проходження G2.

Відкритий шлюз runtime-допуску (DB- і file-config events, Pinta, engines) лишається обов'язковою умовою наступної ревізії, яка відкриє apply.

## Обов'язкові блокери

**Немає.** Ремонт r5-B1 перевірено по суті:
- `Customer\Address::form()` декодує значення один раз і HTML-кодує п'ять NP-полів тільки для рядків із `bs_np_v1`.
- `BoosterCustomFieldGuard::address()` порівнює значення після одного decode з обох боків і відновлює точний збережений текст.
- Ланцюг перевірено для сирого і entity-кодованого зберігання, з `"`, `&`, `<`, `'`: decode один раз → кодування атрибута → браузер → `Request::clean` (ENT_COMPAT) → decode один раз. Ланцюг сходиться.
- Справжня зміна міста й далі відхиляється.

## Незалежна оцінка ключових аспектів

**Транзакції.**
- `save()` (wrapper) → `saveAcc008()`. Блок `catch` обробляє тільки Booster-виняток. `finally rollback()` спрацьовує і для будь-яких інших `Throwable`.
- `finish(true)` виконується до зміни сесії (profile і catalog address).
- Збій COMMIT лишає `active=true`, тому `finally` повторює ROLLBACK.
- NP-lookup винесено до `begin()`.
- `FOR UPDATE` — перше читання в транзакції, тож застарілий snapshot неможливий.

**Збереження сирого JSON.**
- `decode()` використовує `json_decode(..., false)`, тому вкладені `{}` і `[]` зберігають свій тип.
- Кореневі форми нормалізуються, це задокументовано.
- `checkRawNumbers()`:
  - possessive-патерн;
  - `=== false` → fail;
  - порівнює сигнатури (перевірено вручну на `1e2`, `100.0`, `-0`, `1.0e-7`).
- `assertWritable()` кодує фінальний масив тими самими прапорами, що й writer, плюс ліміт 65 535 байт.

**Числовий allowlist.**
- Нечислові ключі в `custom_field` і в маркері відхиляються.
- Числові ID поза location/group/status ігноруються, їхні значення зберігаються.
- Для checkbox вимагаються int-ключі та string-елементи.

**Пропуск чи очищення.**
- Пропущене поле зберігає значення.
- `''` очищає скаляр.
- Маркер `'1'` без значень дає `[]`.
- Radio очистити неможливо через обмеження браузера, це не дефект.

**Власність NP.**
- Catalog: текст і метадані виводить сервер, клієнтський `bs_np_v1` відхиляється.
- Admin: локацію змінити не можна, ім'я змінити можна.
- `(customer_id, address_id)` перевіряється під lock до writer, який фільтрує лише за `address_id`.

**Групи.** Значення ≤0 відхиляються у всіх чотирьох формах. Admin-модалка показує поля тієї самої групи, що й при збереженні.

**Runner.**
- Перевірки ідентичності, anchor, link і traversal.
- Порядок: helper встановлюється першим і видаляється останнім.
- Lint виконується до запису, restore — при збої.
- Порожній manifest відхиляється.
- Пароль: `!isset($this->request->post['customer_id'])` відновлює стару семантику.

**Спільні writer'и checkout і реєстрації.** Залишити їх незмінними — правдиво і в межах гранту на чотири форми. Звіт прямо визнає, що незмінені read-modify-write writer'и без lock можуть затерти новіші дані застарілою копією.

**Нові `!important`, `setTimeout`, `position:absolute/fixed`, магічні пікселі.** Відсутні. Наявні `setTimeout(220/160)` і `position:absolute` в catalog `address_form.twig` — історичні.

## Неблокуючі зауваження

Пункти 1–4 обов'язково закрити **до ревізії, що відкриває apply**. Бажано закрити їх уже зараз, щоб не витрачати зайвий хост-раунд.

1. **`acc008_preflight()`, фільтр `$events` неповний.**
   - Наскільки я пам'ятаю, `Event::trigger` в OC4 зіставляє trigger як regex без `$`, тобто як префікс. Тоді trigger на кшталт `catalog/model/account/` без `*` діяв би, але фільтр `LIKE` його не бачить. Це варто підтвердити тілом `system/engine/event.php`.
   - Не охоплені `account/activity` і `localisation/*`.
   - Через це `relevant_events: []` може дати хибне «чисто».
   - Ремонт: виводити **всі** активні events (`code/trigger/action/sort_order` — дані неперсональні, ліміт обмежений) і класифікувати їх під час рев'ю.
2. **`acc008_rows()`.** Цикл `while ($statement->fetch())` мовчки закінчується, якщо `fetch()` поверне `false` (помилка). Неповна вибірка тоді вважатиметься повною. Після циклу потрібна перевірка `$statement->errno`.
3. **Persistent-з'єднання.** Перевірка `p:` читає лише `public_html/config.php`. Admin використовує `adminEvhenii/config.php` зі своїм `DB_HOSTNAME`, який теж треба перевірити.
4. **PHP CLI чи web.** `PHP_BINARY` — це CLI-інтерпретатор. На cPanel (MultiPHP) він може відрізнятися від FPM-версії сайту. Рядок `php_version=` має показати 8.0.30, а версію web-SAPI треба підтвердити окремо.
5. **Колонка `theme.language_id`.** Її може не бути в цій версії OC. Тоді спрацює безпечна зупинка `metadata_schema_requires_review`, тобто зайвий раунд, а не небезпека.
6. **Блокування редагування.** Рядки з валідним, але непідтримуваним JSON (`null`, `0`, `""`, дублікати, з'їдені дробові числа, ключ із `\u0000`) fail-closed. Їх не можна редагувати жодною з чотирьох форм, включно з admin, тож виправлення потребує окремого авторизованого рішення.
   - Дублікати ключів не виявляються: останній ключ виграє.
   - Варто додати агрегований `COUNT` таких рядків без персональних даних.
7. **Admin NP name-only: можлива безпечна відмова, яку треба перевірити в QA.**
   - `zone_id` приходить з AJAX. Якщо збереження відбулося до відповіді, або збережена зона відсутня чи вимкнена серед зон країни 220, posted значення буде `''`, і збереження відхилиться.
   - Браузер видаляє `\n` зі значення `input`, тому NP-текст із переносами рядків теж відхилиться.
8. **Сесія профілю тепер містить повний JSON**, включно з `bs_first15_pending` та іншими службовими ключами.
   - Це узгоджено з БД.
   - Треба перевірити, що login формує ту саму форму сесії, і що SimpleCheckout/First15 не змінюють поведінку через ці ключі (наприклад, snapshot `custom_field` замовлення).
9. **Розбіжність повідомлень помилок.**
   - Catalog address: при помилці NP-валідації `$post_info['custom_field']` більше не нормалізується до `[]`. Crafted-POST зі скалярним `custom_field` дасть Warning, а не JSON-помилку. Запису немає, є лише регресія повідомлення.
   - Admin: якщо guard кидає виняток, його warning замінює попередні field-помилки.
10. **Non-NP admin-адреси.** Рядки без `bs_np_v1` і далі рендеряться неекранованими (історична поведінка). Лапки в тексті обріжуться при збереженні. Це поза preservation-контрактом, але стоїть поруч.
11. **Backup-шлях майбутнього apply.** `_patch_backups` знаходиться в webroot. Ревізія, що відкриє apply, має забезпечити HTTP-захист цього каталогу.
12. **Косметика.** Подвійний docblock, відступи маркера.

## Покриття та обмеження

**Прочитано повністю:**
- 9 payload-тіл і диффи;
- runner;
- `build_patch.py`, `roundtrip_tests.php`, `run_checks.py`, `render_bootstrap.php`, `intake_render_deps.py`;
- before-методи `save()`;
- власні методи моделей, обидві реалізації `getCustomFields`;
- `framework.php`, `db/mysqli.php`, `template/twig.php`, `request.php`;
- activity controller і model;
- вердикти r1 і r5, ремонти r2, r3, r6;
- evidence JSON.

**Нічого не виконувалось:** ні PHP, ні хеші, ні base64, ні MySQL, ні NP, ні браузер. Ідентичність payload я не звіряв.

**Відсутні в пакеті:**
- `system/engine/event.php` і Loader/Proxy;
- Pinta-моделі;
- вміст file-config `action_event` (catalog і admin);
- admin `config.php`, session, login controller;
- тіла Twig vendor (є лише хеші).

**Межі фікстур:** не моделюються реальний InnoDB, нативний dispatch events, AJAX-зони і санітизація `value` у браузері.

## Вимоги до хостингу та owner QA

1. Завантажити runner **над** `public_html`, звірити sha256, запустити `cd ~/public_html && php ../ACC-008_custom-field-preservation_20261015.php --dry-run`.
   - Очікувано: 5×`host_payload_lint ... :ok`, `php_version=8.0.30`.
   - Потім або `active_override_or_event_requires_independent_review`, або `dry_run=ok`. Обидва результати лише збирають метадані.
   - Повернути bounded-вивід. **Apply не виконувати.** Цей preflight — шлюз, а не пройдений доказ.
2. Для ревізії, що відкриє apply, надати:
   - тіла `event.php` і Loader;
   - file-config `action_event` (catalog і admin);
   - admin `DB_HOSTNAME` (без облікових даних);
   - engine `customer_activity`;
   - Pinta-залежності;
   - версію web-PHP;
   - захист `_patch_backups`.

   Ця ревізія потребує свіжого незалежного рев'ю.
3. Перед apply мати свіжий DB-дамп у власника. Apply, кеш і rollback виконувати точно за звітом тієї ревізії.
4. QA:
   - admin name-only для поштомату, відділення й courier із `"` і `&` після повного завантаження зон;
   - admin-зміна міста чи точки має повернути warning і нічого не змінити;
   - збереження `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename`;
   - очищення vs пропуск поля (окремо checkbox);
   - чужа адреса, permission denied, неавторизований доступ;
   - default-адреса;
   - First15/checkout після редагування профілю;
   - mobile, tablet, desktop;
   - Tier 1 сторінки без оформлення замовлення.
