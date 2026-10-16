# Independent Claude CLI review вЂ” ACC-008 custom-field preservation

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `0b0628010d799cd25011210e60a6e56dd30b369511f3b03dc64b8ce6a9acb122`
Packet SHA-256: `de7ad63b3a9f1525e8a4f5a56a2bda7130db6169ed53d54c69528ea560bb2c6b`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Source Review OK**

Вердикт охоплює лише CODE-межу r11: шлюз допуску в `runner.php` і `runtime_admission.php`. Кандидат: `patches/ACC-008_custom-field-preservation-r11_20261016.php`, SHA-256 за маніфестом `0b062801…acb122`. Сам хеш я не звіряв.

Цей вердикт не є прийняттям apply, deploy чи production. Він не є G2 і не дозволяє WP3. Наступний крок — dry-run на хості власника.

**Обов'язкових знахідок немає.** R10-M1 закрито по суті. Пов'язані неблокуючі пункти r10 (1, 2, 3, 5) теж закрито.

---

## 1. Незалежна перевірка закриття R10-M1

### Фактичні owning-тіла (повні, з пакета)

- **Admin `model/setting/event.php::getEvents()`.** Запит `SELECT * FROM event` без `WHERE status`. Ліміт додається лише тоді, коли задано `start`/`limit`. Startup викликає метод без аргументів, тож ліміту немає.
- **Admin `controller/startup/event.php`.** Реєструє рядки за умовою `if ($result['status'])`, тобто за PHP-truthiness. Реєструються префікси `admin/` та `system/`.
- **Catalog `model/setting/event.php`.** Фільтрує в SQL: `` `status` = '1' ``.
- **Catalog `controller/startup/event.php`.** Додаткового фільтра немає. Реєструються `catalog/` та `system/`.

Висновок r10, зроблений за upstream, тепер підтверджено самими хост-тілами. Запит r10 `WHERE status = 1` справді не охоплював admin-набір.

### Новий інвентар

**Запит.** Сирий, без `WHERE`, з `LIMIT 513` і cap 512 у `acc008_rows`. Переповнення дає `metadata_result_cap`. Statement закривається у `finally` ще до будь-яких записів, тобто це fail-closed.

**Чи це надмножина обох завантажувачів.**
- Для admin фільтр `(bool)$r['status']` збігається з умовою завантажувача точно.
- Для catalog будь-який рядок, що проходить SQL `status='1'`, має числове значення 1 або рядок, рівний `'1'` з урахуванням PAD SPACE. Будь-яке таке значення PHP-truthy.
- Я не знайшов значення, яке SQL-істинне для `='1'`, але PHP-хибне.

**Типи і протоколи.** Prepared-протокол (`bind_result`) повертає int або float. Text-протокол admin-драйвера повертає рядки. Для `0`/`'0'`, `NULL`, `2`/`'2'`, `-1` truthiness однакова в обох. DECIMAL в обох протоколах приходить рядком. Розбіжності між протоколами немає.

**Нормалізація.** `status` тепер входить в ідентичність як `(string)`, плюс `sort_order` як `(int)`.
- Маніфест має явне `status: 1`, що в рядку дає `"1"`. Хост-tinyint дає int 1, теж `"1"`.
- Дрейф `1→2` на відомому рядку відмовляє.
- Нестандартні представлення на кшталт `'1 '` чи `'1.0'` теж відмовляють. Це безпечний бік.

**Порівняння.** Застосовано строге `!==` над відсортованими списками, тож кратність дублікатів враховується. Нетотальність `<=>` для числових рядків може лише хибно відмовити. Хибно допустити вона не може, бо строга рівність масивів не збігається для різних мультимножин.

**Приватність.**
- Друкуються лише truthy-ідентичності та лічильник `event_rows_scanned`.
- Канарка `PRIVATE_DISABLED_EVENT_CANARY` перевіряється для `'0'` і `NULL`.
- Невалідний UTF-8 у неактивному рядку не кодується, бо рядок відфільтровано раніше.
- Невалідний UTF-8 в активному рядку дасть `JsonException`. Його перехоплює зовнішній `Throwable`, і з'єднання закривається у `finally`. Це безпечна зупинка.

### Тести

Тести дискримінують за поведінкою:
- статуси `2`, `-1`, `'false'` дають `active_event_inventory_changed` і видимість у виводі;
- `'0'` і `NULL` дають `null`-помилку без розкриття ідентичності;
- дрейф статусу існуючого рядка відмовляє;
- 513 вимкнених рядків дають cap і закритий statement;
- комбінований startup+event тест показує обидва дрейфи за один прогін.

## 2. Комбіновані метадані

Нова послідовність така:

1. запити подій, startup і тем;
2. `active_startups`;
3. `metadata`, де тепер є `active_startups`;
4. лише потім відмови за startup, темою, подіями й custom-field.

Будь-який звичайний дрейф видно за один хост-раунд. Помилки запиту, схеми чи cap і далі зупиняють раніше. Вони не підміняють повного інвентарю, і `db_preflight=read_only_ok` у такому разі не друкується.

## 3. Мапінг кореня й обидва кеші

**Twig-адаптер (повне тіло):**
- корінь береться як `substr(DIR_OPENCART, 0, -1)`;
- кеш — `DIR_CACHE . 'template/'`;
- `auto_reload => true`.

**`DIR_OPENCART`:**
- тепер обов'язковий в обох конфігах;
- перевіряється за `realpath`, що має дорівнювати `realpath($root)`;
- негативний тест `opencart_root_mapping_refused` перевіряє admin-шлях.

**Обидва `DIR_CACHE`:**
- розв'язуються через `realpath`, `is_dir`;
- логуються окремо: `cache_dir_config_relative` і `admin_cache_dir_config_relative`;
- рівність не вимагається, це правильно;
- тест `both_cache_paths_resolved` дискримінує.

**Секрети.** Нові повідомлення містять лише ключі або мітки застосунку. Значення не друкуються.

## 4. Агрегатний cap

`$storedBytes` накопичується після кожного визначення. Перевищення 2 097 152 байт дає `config_aggregate_value_size_cap`.

**Тест `config_aggregate_growth_cap`:**
- значення: 256 КіБ, далі 512, 512, 512, 512 КіБ;
- накопичено 2 304 КіБ, що більше за 2 МіБ;
- спрацьовує саме агрегатний код, бо кожне окреме значення не перевищує 512 КіБ.

**Тест `config_evaluation_growth_cap`:**
- `C = B . 'x'` дає 524 289 байт, і спрацьовує per-value cap;
- агрегат на цей момент становить 768 КіБ, тож порядок перевірок дискриміновано.

## 5. Збірка

`build_admission.py` додає `status=1` до 112 рядків, отриманих з хост-запиту r7 `WHERE status = 1`. Це коректно за побудовою.

Мутант `WHERE status = 1` у run_checks провалює тести. Рядкова перевірка в `build_patch` відтворювана. Нового коду, несумісного з PHP 8.0, я не побачив.

---

## Неблокуючі зауваження

1. **Гейт тем лишився на SQL `WHERE status = 1`, той самий клас семантики.**
   - Тіла `catalog/model/design/theme.php` і `catalog/controller/event/theme.php` у цьому пакеті відсутні. Я не перевіряв, як catalog-завантажувач фільтрує статус.
   - Ризик для даних низький: override змінює лише рендер, а запис усе одно йде через серверний guard. Відсутній маркер присутності трактується як «пропущено, зберегти».
   - Варто зробити одне з двох:
     - читати теми для чотирьох маршрутів без фільтра статусу (сирий запит, cap) і відмовляти на будь-якому рядку;
     - або подати ці два тіла в наступному пакеті, якщо буде новий раунд.
2. **Мутант статус-фільтра виявляється синтаксично.** Fixture кидає виняток на `WHERE`. Семантично мутант теж упав би, бо рядок зі статусом 2 зник би з вибірки. Чистіше, щоб fixture реально виконував `WHERE status = 1`, а провал ішов з тестів truthiness.
3. **`DIR_OPENCART` без кінцевого `/`.** Twig неявно вимагає кінцевий слеш, а `realpath`-рівність його не перевіряє. Живий сайт уже рендериться, тож це лише дешеве посилення: `substr($v['DIR_OPENCART'], -1) === '/'`.
4. **Лише перша причина відмови в рядку `error=`.** Інвентарі надруковано, але перелік усіх причин спростив би оцінку.
5. **Невиправлене з r10:**
   - тести `installer_reject_*` приймають будь-який `RuntimeException`;
   - лишився застарілий коментар «51 candidates»;
   - немає механізму admission для відомого startup.
6. **Cap 512 на сирі події.** Якщо на хості понад 512 рядків разом із вимкненими, dry-run безпечно зупиниться. Тоді потрібна ревізія cap і нове рев'ю, а не обхід.
7. **Чинні обмеження r9/r10 без змін:**
   - будь-який згенерований OCMOD зупиняє;
   - спільні writer-и checkout і First15 можуть перезаписати застарілий JSON;
   - захищено лише чотири форми;
   - `system/startup.php`, helper і vendor не закріплені універсально.

---

## Покриття й межі доказів

**Прочитано повністю:**
- `repair-r11.md`;
- вердикт r10 і closure;
- `local-checks.json`, dispatch-JSON;
- `runner.php` і `runtime_admission.php` r11, разом із повними before r10 і обома diff;
- `build_admission.py`, `build_patch.py`;
- маніфест: 101 ідентичність, 112 подій, 27 полів і групи;
- `preflight_contract_tests.php`, `run_checks.py`, `runtime_dispatch_tests.php`;
- `prepare_repair_review_r11.py`;
- п'ять фактичних тіл: admin і catalog `startup/event.php`, admin і catalog `model/setting/event.php`, `template/twig.php`;
- політика рев'ю і handoff.

**Лише за ідентичністю, не перечитувалося:**
- 9 payload-тіл;
- решта 96 owning-тіл, зокрема `design/theme.php`, `event/theme.php`, `db/mysqli.php`, `template.php`;
- `roundtrip_tests.php`, `render_bootstrap.php`;
- хост-JSON r7 і r8.

Глобального позитивного вердикту r9 чи r10 немає. Цей вердикт його не виводить. Payload-логіка лишається прийнятою по суті в r9, і в r11 вона не змінювалася.

**Нічого не виконувалося:** ні PHP, ні хешування, ні DB, ні браузер. Локальні тести — це PHP 8.3.30 і фіктивний mysqli. Хост-прогону r11 немає. 34 з 101 хешу ще не доведені на поточному хості.

---

## Гейт хоста (лише власник)

### 1. Dry-run

- Завантажити точний файл r11 поза `public_html`.
- Звірити SHA-256 `0b0628010d799cd25011210e60a6e56dd30b369511f3b03dc64b8ce6a9acb122`.
- Запустити `cd ~/public_html && php ../ACC-008_custom-field-preservation-r11_20261016.php --dry-run`.

Очікуваний вивід:
- 5×`host_payload_lint …:ok`;
- `runtime_sources=exact_reviewed_sources_and_no_generated_ocmod`;
- `cache_dir_config_relative=…` і `admin_cache_dir_config_relative=…`;
- `config_admission=literal_nonpersistent_same_target_and_paths`;
- `event_rows_scanned=N`, де 112 ≤ N ≤ 512;
- `active_startups=[]`;
- `metadata=…` з `event_inventory_scope=all_php_truthy_active_raw_inventory_capped` і рівно 112 подіями;
- `db_preflight=read_only_ok`, `dry_run=ok`, `files_pending=9`.

Повернути весь обмежений вивід. Секретів у ньому немає.

### 2. Будь-яка зупинка — це дані для незалежної оцінки, не для обходу

Зокрема:
- нові truthy-події чи нестандартний статус;
- `metadata_result_cap`;
- непорожній startup;
- тема;
- будь-який із 34 хешів;
- граматика конфігу або мапінг, у тому числі `DIR_OPENCART`;
- кеш, engine, OCMOD.

Handler-и не вимикати, рядки event/startup не змінювати.

Чистий очікуваний вивід Codex звіряє з цим списком. Будь-яке відхилення означає новий незалежний раунд.

### 3. Apply

- Мати свіжий DB-дамп у власника.
- Запустити `php ../<файл> --db-backup-confirmed` і зберегти рядок `backup=…`.
- Очікувано: `files_changed=9`, `done=ok`, runner видалено.
- Кеш чистити лише в підкаталозі `template/` обох надрукованих кеш-каталогів, самі каталоги не видаляти. Через `auto_reload` очищення страхувальне.

### 4. Rollback

- Повторно завантажити runner і запустити `--rollback=<name>`. Це відкат лише джерел.
- Відмова `rollback_would_orphan_applied_controller` означає: зупинитися, нічого вручну не видаляти.

### 5. QA (лише власник)

- чотири форми;
- збереження `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename` і невідомих ключів;
- пропуск поля проти очищення;
- admin NP-warning;
- чужа адреса, відмова в правах;
- First15 і вхід у checkout без оформлення;
- mobile, tablet, desktop;
- валідний поточний 3D-товар замість `FIG-CHARM-001`;
- рядки `customer_activity`.

WP3 чекає, доки власник підтвердить, що ACC-008 live і пройшов QA.
