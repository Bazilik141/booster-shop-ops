# Independent Claude CLI review вЂ” ACC-008 custom-field preservation

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `89960b4de154496a47136b781aa65a0b74ef2b98e6b1cc4a173adbf74953f606`
Packet SHA-256: `d970b604311d84b861106719e6efe18a44e02898c099a2b6845697162c881a78`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Return for changes**

**Межа вердикту.** Вердикт стосується кандидата r10 `patches/ACC-008_custom-field-preservation-r10_20261016.php`. За маніфестом його SHA-256 `89960b4d…f606`; сам хеш я не звіряв. Перевірено:

- dev-тіла `runner.php` і `runtime_admission.php`;
- повні before-тіла r9 і обидва diff;
- обидва білдери;
- тест-файли `preflight_contract_tests.php`, `run_checks.py`, `runtime_dispatch_tests.php`;
- маніфест admission;
- 21 нове owning-тіло;
- повний вердикт r9 і його closure.

**Що не перечитувалося.** Дев'ять payload-тіл і 80 owning-тіл r9 у пакеті відсутні. Я їх не перечитував і приймаю лише як точне повторне використання за ідентичністю. Глобального позитивного вердикту r9 не існує, і я його не виводжу.

**Підсумок.** M1 r9 закрито коректно. Пов'язані пункти (конфіг-мапінг, кеш, orphan-rollback, startup) теж закрито по суті. Але новий startup-гейт явно спирається на PHP-truthiness admin-завантажувача. Цей самий аргумент не застосовано до інвентаризації подій, де та сама семантика, найімовірніше, діє. Це обов'язковий дефект шлюзу допуску. Він не в payload.

Apply, deploy, G2 і WP3 цим вердиктом не дозволені. Дефект безпечний для даних: runner лише недоперевіряє. Але твердження `event_inventory_scope=all_active` хибне саме для двох admin-форм, які захищає патч.

---

## Обов'язковий дефект

### R10-M1. Інвентар подій ігнорує admin PHP-truthiness, хоча startup-інвентар її враховує

**Факт із пакета.** Admin `startup/startup.php` (повне тіло наведено) робить таке:

- бере **нефільтрований** `getStartups()`, тобто `SELECT * FROM startup` без `WHERE status`;
- реєструє рядок за умовою `&& $result['status']`.

R10 правильно робить висновок, що SQL `status=1` тут недостатньо, і фільтрує `(bool)$r['status']`. Тести `startup_active_truthiness_2` і `startup_active_truthiness_false` це підтверджують.

**Неузгодженість.** Подієвий запит лишився таким:

```
SELECT code, `trigger`, action, sort_order FROM event WHERE status = 1 … LIMIT 129
```

**Що каже upstream.** В OC 4.1.0.3 admin `controller/startup/event.php` побудований за тим самим шаблоном:

- `model_setting_event->getEvents()` в admin повертає всі рядки без фільтра статусу;
- реєстрація йде за `if ($result['status'])` або `&& $result['status']`.

Catalog-модель, натомість, фільтрує `status = '1'` у SQL. Отже, admin-подія зі статусом `2`, `-1` або рядковим `'false'` буде зареєстрована в admin. Наприклад, на `admin/model/customer/customer.editCustomer/after` чи `admin/controller/customer/address.save/before`. При цьому runner її не побачить.

**Наслідок.** Невідомий handler потрапляє в транзакцію admin-збереження. Неінвентаризований handler може:

- зробити неявний COMMIT (DDL);
- писати через інше з'єднання;
- змінити `custom_field`.

Це саме той клас ризику, від якого гейт «точна інвентаризація всіх активних подій» мав захищати. Поточні 112 подій отримано тим самим запитом `status = 1`. Тому вони не доводять відсутності таких рядків.

**Межа контексту.** Тіла admin `controller/startup/event.php` і `model/setting/event.php` повторно використані за ідентичністю й у пакеті відсутні. Шаблон хост-копії я підтверджую лише через upstream і через ідентичний шаблон admin `startup/startup.php`, наданий тут. Будь-який варіант нижче закриває дефект.

**Потрібно (одне з двох):**

1. **Виправлення, рекомендовано.**
   - Читати сирий інвентар `code`, `trigger`, `action`, `status`, `sort_order` без `WHERE status`, з `LIMIT N+1` і cap `N`, наприклад 512.
   - Активні рядки визначати за `(bool)$r['status']`.
   - Порівнювати їх нормалізовано з admission, як зараз.
   - Сирі неактивні рядки лише рахувати або обмежувати cap-ом, не друкувати.
   - Тести:
     - статус `2`, `-1` і `'false'` дають `active_event_inventory_changed`;
     - `'0'` і `NULL` проходять;
     - сирий cap fail-closed;
     - мутант із поверненим `WHERE status = 1` має провалити тест.
2. **Або доказ.** Надати в наступному пакеті повні тіла admin `startup/event.php` і `model/setting/event.php` (і catalog-аналоги). Вони мають доводити, що реєстрація відбувається лише для SQL-`status=1`.

Це лише виправлення шлюзу допуску. Payload-тіла не змінюються, і повторне використання дев'яти payload лишається валідним.

---

## Незалежна перевірка закриття r9-M1 і пов'язаних пунктів (прийнято по суті)

### Граматика конфігу (`acc008_config_literals`)

**Граматика.** `VALUE := OPERAND ('.' OPERAND)*`.

**Що допускається як операнд:**
- `T_CONSTANT_ENCAPSED_STRING`;
- канонічний `T_LNUMBER`;
- `T_STRING`, який уже є в `$values`.

`array_key_exists` чутливий до регістру. Це відповідає PHP 8, де регістронезалежних констант немає.

**Що відмовляється:**
- невизначені, пізніші й self-reference імена (`define('A', A.'x')`);
- `__DIR__` (`T_DIR`);
- `$var`;
- `\NAME` (`T_NAME_FULLY_QUALIFIED` як операнд);
- `(`, виклики, `::`, `+`, `?`, `T_DNUMBER`, heredoc.

**Проміжна валідація.** Після кожного операнда дозволено лише `.` або `)` `;`. Пропущений операнд (`A . )` чи `. 'x'`) відмовляється. Пробіли й коментарі між операндами відфільтровано, і це семантично еквівалентно PHP.

**Прогрес і кеп.** Цикл завжди просувається (`$i=$j+2`). Кеп на одне значення перевіряється до конкатенації. Тест на межі 524288/524289 дискримінує.

**Префікси.** `b'…'` і `B"…"` явно відмовляються, як для імені, так і для значення. Це закриває r9-примітку 6.

**Декодування рядків.** Декодування одинарних лапок r9 не змінилося. Я перевірив його послідовні `str_replace` на випадках `\\\'` і `\\\\`: результат коректний. Подвійні лапки з `\` чи `$` відмовляються.

**Покриття канонічної форми.** Тест `installer_root_and_admin_constant_chains` проганяє обидва конфіги з ланцюгами:
- `DIR_OPENCART → DIR_SYSTEM → DIR_STORAGE → DIR_CACHE`;
- `DIR_APPLICATION → DIR_TEMPLATE`;
- `DIR_SYSTEM → DIR_CONFIG`.

У ньому ж є рядковий `DB_PORT` і перевірка відсутності `PRIVATE_*`. Канонічна форма інсталятора OC 4.1 цим покрита.

### Мапінг і кеш (`acc008_config_targets`)

- Перевіряється наявність `APPLICATION`, `DIR_CONFIG`, `DIR_TEMPLATE` і `DIR_CACHE`.
- `APPLICATION` має бути точно `Catalog` або `Admin`.
- `DIR_CONFIG` і `DIR_TEMPLATE` перевіряються через `realpath` для обох застосунків.
- Негативні тести є для admin `DIR_CONFIG`, admin `DIR_TEMPLATE` і catalog `APPLICATION`.
- `DIR_CACHE` береться через `realpath`, має бути `is_dir`, і в лог іде відносний шлях. Значення секретів не друкуються, повідомлення відмов містять лише ключі.

### Orphan-rollback

Порядок перевірки:
- спрацьовує лише якщо в manifest є helper із `new=true`;
- перебираються payload-контролери поза manifest з `after`-хешем на диску;
- для таких контролерів rollback відмовляє ще до будь-якого видалення.

**Що підтверджено:**
- Шаблони виключено правильно, бо helper їм не потрібен.
- Звичайна композиція helper-first (helper поза manifest) не блокується.
- Тест `rollback_refuses_orphaning_controller_outside_pending_manifest` дискримінує: без перевірки helper було б видалено, а тест перевіряє, що він лишився.

### Startup-гейт

**Запит і кеп:**
- `startup` без `WHERE`, з `LIMIT 65` і cap 64. Сирі рядки обмежено.
- Опису в запиті немає.
- Перевіряється схема з чотирьох колонок.

**Чи це надмножина обох завантажувачів:**
- Catalog бере SQL `status='1'`, admin бере PHP-truthy.
- Фільтр `(bool)` покриває обидва.
- Я не знайшов значення, яке було б SQL-істинним для `='1'`, але PHP-хибним.

**Поведінка:**
- Будь-який активний рядок друкує ідентичності й зупиняє runner.
- Нічого не вимикається.
- Rollback і already-applied не проходять через preflight, тож startup-гейт їх не блокує. Це правильно.

### 21 нове owning-тіло

Жодне не викликає `$this->event->register`.

- Неявного COMMIT немає: admin `startup/setting` виконує лише `SET time_zone`.
- Записи catalog `marketing` і `sass` відбуваються до контролера, поза транзакцією guard.
- `error`-обробники роблять `exit`. Це вже оцінено в r9: non-persistent з'єднання відкочує транзакцію.

### Білдери і пакет

`build_patch.py`/`build_admission.py` відтворювані. `prepare_repair_review_r10.py` перевіряє:
- точний rebuild патча;
- 9 payload і 80 джерел, байт-ідентичних r9;
- 21 нове повне тіло.

Тести 60, 13 і 6 за складом відповідають заявленому.

---

## Неблокуючі зауваження

1. **Порядок відмов у preflight.** `active_startup_requires_independent_review` спрацьовує раніше, ніж друкується `metadata` і порівнюються події та теми. Якщо на хості є і startup, і дрейф подій, знадобиться два хост-раунди. Варто спершу зібрати й надрукувати всі інвентарі, а потім відмовити за першою або всіма причинами.
2. **Admin `DIR_CACHE` перевіряється на наявність, але не логиться.** Admin `customer_form` і `address_form.twig` змінюються. Варто друкувати й admin-шлях або вимагати рівності з catalog.
3. **`DIR_OPENCART` не перевіряється.** Upstream Twig-адаптер, найімовірніше, бере корінь із `DIR_OPENCART`. Тіло `template/twig.php` тут не надано. Дешево додати: якщо константа визначена, вимагати `realpath`, що дорівнює root.
4. **Bootstrap не повністю закріплено.** Обидва `index.php` закріплено, але `system/startup.php` (який вони підключають), `helper/general.php` і vendor-autoload — ні. Формулювання звіту «closes the unpinned pre_action-source gap» варто обмежити: «закрито всі action_pre_action-контролери». Ризик для транзакції низький.
5. **Агрегатна пам'ять парсера не обмежена.** Кеп діє на одне значення, а не на суму. Патологічний конфіг на 512 КБ із самопосиланнями може вичерпати `memory_limit`. Це fatal до будь-яких записів, тобто безпечна зупинка. Можна додати сумарний кеп.
6. **Тести `installer_reject_*` не перевіряють конкретний код відмови.** Вони приймають будь-який `RuntimeException`. Варто вимагати очікуване повідомлення.
7. **Очікувані безпечні зупинки граматики на хості.** До них належать UTF-8 BOM перед `<?php`, вміст після `?>`, а також `$_SERVER`, `__DIR__` чи `getenv` у конфігу. Їх треба оцінювати, а не обходити.
8. **Застарілі коментарі.** Це docstring «for r9» у `build_admission.py` і «51 candidates» у `acc008_runtime_files`.
9. **Немає механізму admission для відомого startup.** Будь-який непорожній інвентар потребуватиме нової ревізії й нового рев'ю. Це правильно за правилом власника, але варто врахувати в плануванні.
10. **Чинні примітки r9 без змін:**
    - будь-який згенерований OCMOD зупиняє;
    - admin-escape і пріоритет warning;
    - спільні writer-и First15 і checkout;
    - захищено лише чотири форми;
    - `prepare_review.py` exec-фрагмент.

---

## Покриття та межі

**Прочитано повністю:**
- звіт r10;
- вердикт і closure r9;
- `local-checks.json`, dispatch-JSON;
- `runner.php`, `runtime_admission.php` разом із before r9 і diff;
- `build_admission.py`, `build_patch.py`;
- маніфест (101 хеш, 112 подій, метадані полів);
- `preflight_contract_tests.php`, `run_checks.py`, `runtime_dispatch_tests.php`;
- `r9-payload-reuse-for-r10.json`, `prepare_repair_review_r10.py`;
- 21 нове owning-тіло;
- політику рев'ю і handoff.

**Не перечитувалося (лише ідентичність):**
- 9 payload-тіл;
- 80 owning-тіл r9, зокрема admin і catalog `startup/event.php`, `model/setting/event.php`, `framework.php`, `template/twig.php`;
- `roundtrip_tests.php`, `render_bootstrap.php`;
- хост-JSON r7 і r8.

Висновок R10-M1 щодо admin event-завантажувача спирається на upstream OC 4.1.0.3 і на наведений тут ідентичний шаблон admin `startup/startup.php`, а не на тіло хост-копії.

**Нічого не виконувалося:** ні PHP, ні хешування, ні DB, ні браузер. Локальні тести працюють на PHP 8.3 з фіктивним mysqli. Хостового прогону r10 немає. 34 нові хеші взято з бекапу, а не з поточного хоста.

---

## Вимоги до хоста (лише після виправлення R10-M1 і свіжого незалежного рев'ю)

1. **Dry-run.**
   - Завантажити точний новий файл поза `public_html` і звірити SHA-256.
   - Запустити `cd ~/public_html && php ../<файл> --dry-run`.
   - Очікуваний вивід:
     - `config_admission=literal_nonpersistent_same_target_and_paths`;
     - `cache_dir_config_relative=<шлях>`;
     - 5×`host_payload_lint …:ok`;
     - `runtime_sources=exact_reviewed_sources_and_no_generated_ocmod`;
     - `active_startups=[]`;
     - `metadata=…`;
     - `db_preflight=read_only_ok`;
     - `dry_run=ok`, `files_pending=9`.
   - Повернути весь обмежений вивід. Секретів у ньому немає.
2. **Будь-яка зупинка є вхідними даними для незалежної оцінки, а не для обходу.** Зокрема:
   - граматика конфігу, `APPLICATION`, мапінг, відсутній кеш;
   - непорожній startup;
   - будь-який із 34 нових хешів;
   - інвентар подій, зокрема нові truthy-статуси після виправлення;
   - тема, метадані, engine, OCMOD.

   Handler-и не вимикати.
3. **Apply.**
   - Перед apply мати свіжий DB-дамп у власника.
   - Запустити `php ../<файл> --db-backup-confirmed` і зберегти рядок `backup=…`.
   - Очікувано: `files_changed=9`, `done=ok`, runner видалено.
   - Кеш чистити лише всередині надрукованого `cache_dir_config_relative`, наприклад підкаталог `template/`, і не видаляти сам каталог.
4. **Rollback.**
   - Повторно завантажити runner і запустити `--rollback=<name>`. Це відкат лише джерел.
   - Відмова `rollback_would_orphan_applied_controller:<path>` означає: зупинитися й повернути вивід. Видаляти helper вручну не можна.
5. **QA (лише власник).** Без змін щодо r9:
   - чотири форми;
   - збереження `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename` і невідомих ключів;
   - пропуск поля проти очищення;
   - admin NP-warning;
   - чужа адреса, відмова в правах;
   - First15 і вхід у checkout без оформлення;
   - mobile, tablet, desktop;
   - валідний поточний 3D-товар замість `FIG-CHARM-001`;
   - рядки `customer_activity`.
