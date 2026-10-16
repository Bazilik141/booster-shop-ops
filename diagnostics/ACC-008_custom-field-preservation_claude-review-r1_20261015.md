# Independent Claude CLI review — ACC-008 custom-field preservation

Date: 2026-10-15
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `4013008c0a943ee5f7b49c1673032afd406d97b27c84f7c955e4af6d88e4bc09`
Packet SHA-256: `196f5947cee97980b762964a36d9355a19dfeedab7cadff1cb496becda4547d6`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Return for changes**

Ядро рішення має правильну форму. Перед записом сирий JSON повторно читається з БД через `FOR UPDATE`. Нечислові ключі відхиляються. Діє allowlist за location, group і status. Опущене поле відрізняється від явного очищення, для чекбоксів через прихований маркер. Чужу адресу заблоковано. Друга запис у admin обгорнута тією ж транзакцією. Метод `saveAcc008()` приватний, тому його не можна викликати як маршрут. Однак є три дефекти. Один ламає заявлену поведінку admin-адрес Нової пошти. Два інші роблять хостинг-гейт неповним або нездатним пройти. Перекладати їх на owner QA не можна.

## Обов'язкові блокери

### 1. Хибне відхилення admin-редагування адрес НП
- **Де:** `BoosterCustomFieldGuard::address()`, цикл `foreach (['city','address_1','address_2','country_id','zone_id'] …)` з порівнянням `(string)$data[$key] !== (string)$row[$key]`.
- **Причина:**
  - Стоковий OpenCart 4 у `Request::clean()` застосовує до POST `trim(htmlspecialchars(…, ENT_COMPAT))`.
  - Збережений текст НП записує `validateBsNpPayload()` у сирому вигляді: `$warehouse['description']`, `$street_label`.
  - Адреси з `"`, `&`, `<`, `>` ніколи не збіжуться. Наприклад, типові поштомати: `Поштомат "Нова Пошта" №…`.
  - Через це редагування лише імені в admin, яке звіт заявляє як дозволене, буде систематично відхилене.
- **Чому не виявлено:** `system/library/request.php` немає в пакеті. Фікстура не моделює очищення запиту.
- **Ремонт:**
  - Порівнювати нормалізовані значення з обох боків: `html_entity_decode(ENT_QUOTES)` + `trim` для тексту, `(int)` для ID. Або довести, що хостинговий Request не кодує вхід.
  - Додати фікстуру з очищеним POST, що містить `&quot;` і `&amp;`, для варіантів warehouse, poshtomat і courier.
  - Реальна зміна тексту НП має й далі відхилятися.

### 2. Неповне й одночасно надмірне покриття events у preflight
- **Де:** `acc008_preflight()`, запит `$events`.
- **Пропущено:**
  - `account/customer` — це саме писач профілю `model_account_customer->editCustomer`. Сюди ж належить стоковий `activity_customer_edit` (`catalog/model/account/customer.editCustomer/after`).
  - `account/custom_field` і `customer/custom_field` — джерело allowlist, яке before/after-хендлер може змінити.
  - `account/login` — використовується `login.validate`.
- **Надмірно спрацює:** стокові `activity_address_add/edit/delete` (`catalog/model/account/address.*`), найімовірніше, завжди зупинять dry-run. Механізму затвердженого allowlist немає. Тож гейт або нічого не показує, або завжди блокує, і це не задокументовано.
- **Ремонт:**
  - Розширити шаблони trigger.
  - Виводити точні `code/trigger/action/sort_order`.
  - Вбудувати в runner allowlist точних ідентичностей незалежно переглянутих рядків. Будь-який інший збіг — fail.
  - Інакше явно задокументувати, що після першого dry-run знадобиться нова ревізія runner.
- **Запит theme:** спершу перевірити наявність колонок `status` і `language_id`. Помилка «unknown column» не має зливатися з «override знайдено».

### 3. Хостинг-гейт не перевіряє те, що заявлено
- **SQL у `acc008_rows()`:**
  - Вона виконує `prepare()` для `SHOW COLUMNS` і `SHOW INDEX`. Наскільки мені відомо, на MySQL Server таких команд немає у списку дозволених для prepared statements; на MariaDB вони працюють. Тоді dry-run впаде з `read_only_metadata_query_failed` незалежно від стану сайту.
  - Замінити на `SELECT` з `information_schema.COLUMNS`/`STATISTICS`. Перевіряти `DATA_TYPE` і повний склад PRIMARY.
- **Lint і `proc_open`:**
  - Dry-run не лінтує payload на хостинговому PHP 8.0.30 і не перевіряє доступність `proc_open`, яку часто вимикають на shared-хостингу.
  - Lint відбувається лише під час apply. Відновлення там є, тож це безпечно, але PHP 8.0.30-гейт до apply недосяжний.
  - Ремонт: у dry-run викласти PHP-payload у тимчасові файли поза webroot, пролінтувати через `PHP_BINARY`, видалити їх. Перевіряти `function_exists('proc_open')`. Використовувати окремі коди помилок.

## Неблокуючі зауваження
- **Межі блокування.** Row lock серіалізує тільки блокуючі писачі. Незмінені checkout/registration/NP-писачі з read-modify-write без `FOR UPDATE` можуть затерти зміни застарілою копією (lost update). Звіт має прямо це сказати й не створювати враження загального захисту.
- **Залишені спільні моделі.** Залишити спільні моделі без змін — правдиве рішення в межах гранту на чотири форми. Залишковий ризик для checkout і реєстрації названо коректно.
- **Сесія профілю.** `session->data['customer']['custom_field']` отримує вкладені `stdClass`. Для узгодження з контрактом `getCustomer` варто нормалізувати через `json_decode(json_encode(...), true)`.
- **Порядок commit і сесії.** У profile і catalog address сесія змінюється до `finish(true)`. Якщо COMMIT впаде, сесія міститиме незакомічені дані. Краще викликати `finish` перед зміною сесії.
- **Фінальна перевірка JSON у catalog address.** Перевірка кодування не повторюється після `$post_info['custom_field']['bs_np_v1'] = …`. Варто перевіряти фінальний масив.
- **Ліміт TEXT.** OC mysqli ставить нестрогий `sql_mode`. Тоді JSON понад 65535 байт буде тихо обрізаний і зіпсований. Варто додати перевірку `strlen`.
- **Точність і форма JSON.**
  - Перевіряється лише `|x| > 2^53`. Високоточні дробові значення й хостинговий `serialize_precision` не охоплені.
  - Корінь `{}` стає `[]`, список після злиття стає об'єктом, змінюється Unicode-escaping. Семантично це рівнозначно, але має бути задокументовано.
- **Нормалізація ID.** Guard отримує `(int)$post_info['address_id']` і `customer_id`, а писач — сирі значення. Варто нормалізувати один раз до `int`, до виклику guard.
- **Deadlock.** Можливий deadlock 1213 при паралельній зміні default-адреси одного клієнта. Він закінчиться rollback і 500. Це fail-closed, але варто згадати.
- **Шаблони.**
  - Явно очищене текстове поле зі стоковим `custom_field.value` знову надсилається як default. Поведінка історична.
  - Коли є інші помилки валідації, merge пропускається. Через це можлива зайва помилка «required».
- **Кеш.** `glob(DIR_CACHE."template/*")` не заходить у підкаталоги Twig. Це нешкідливо, якщо ключ ArrayLoader включає код (`twig.php` немає в пакеті). Але не можна стверджувати, що скомпільований кеш очищено.
- **Життєвий цикл runner.**
  - `--dry-run` на вже застосованому стані самовидаляє runner.
  - Після перерваного часткового apply відкат треба робити найранішим backup. Це варто задокументувати.
- **Нові зміни.** `!important`, `setTimeout`, `position:absolute/fixed` і магічних пікселів немає. Наявні `setTimeout(220/160)` і `position:absolute` в `address_form.twig` — історичні. Відступи маркера та подвійний docblock — косметика.

## Покриття та обмеження
- **Що прочитано:** усі 9 декодованих тіл, диффи, runner, `build_patch`, фікстури, `run_checks`, власні методи моделей і `getCustomFields`.
- **Що не перевірено:** base64-payload і хеші я не звіряв. Нічого не виконувалося: ні PHP, ні MySQL, ні НП.
- **Відсутні в пакеті:** `request.php`, `framework.php` (обробник помилок), `db/mysqli.php`, `twig.php`, моделі Pinta НП, бібліотека сесій. Твердження про стокову поведінку OC4 базуються на знанні коду й потребують підтвердження.
- **Межі фікстур:** вони не моделюють очищення Request, реальні блокування InnoDB чи autocommit, обробник помилок OC або реальні events.

## Вимоги до хостингу та QA власника (після ремонту й нового рев'ю)
1. Перед dry-run звірити sha256 завантаженого runner.
2. Чистий dry-run на PHP 8.0.30: InnoDB, схема, metadata, lint payload. Events — лише ті, що з затвердженого allowlist.
3. Мати свіжий дамп БД на руках у власника.
4. Перевірити apply, cache і rollback командами зі звіту.
5. QA сценарії:
   - редагування лише імені в admin для адреси poshtomat і для адреси з `"` у назві;
   - відхилення admin-зміни тексту НП;
   - очищення чекбокса і окремо пропуск поля;
   - збереження `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename`;
   - доступ до чужої адреси;
   - Tier 1 сторінки без оформлення замовлення.
