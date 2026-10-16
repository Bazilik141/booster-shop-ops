# Independent Claude CLI review — ACC-008 custom-field preservation

Date: 2026-10-15
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `753f0380a35bb8052ce48dc10a93be0d11d1f56abf2a39194a72935ec8d2958b`
Packet SHA-256: `2a72c2e104ac29d96e8cb49df53c61abfdf6e1f4815a822cbf1ad06ffa5441f5`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# Вердикт: **Return for changes**

Основа рішення коректна:
- сирий JSON перечитується з БД під `FOR UPDATE`;
- нечислові ключі відхиляються;
- діє allowlist за location, group і status;
- опущене поле відрізняється від явного очищення;
- перевіряється власник адреси в admin;
- запис і activity-хук виконуються в одній транзакції;
- commit відбувається до зміни сесії;
- r1-ремонти (cleaner, information_schema, host lint, групи ≤0, TEXT, точність) реалізовано по суті.

Але один дефект ламає заявлений контракт admin-редагування НП-адрес. Його приховує недискримінуючий тест. Відкладати його на owner QA не можна.

## Обов'язковий блокер

### B1. Admin-форма зрізає НП-текст із `"`, тому name-only редагування систематично відхиляється

**Де:**
- `adminEvhenii/view/template/customer/address_form.twig`, inputs `address_1`/`address_2`/`city` (~рядки 24–36, `value="{{ address_1 }}"` тощо);
- `system/library/template/twig.php`, `'autoescape' => false`;
- `Customer\Address::form()` передає сирі `$address_info[...]`;
- `BoosterCustomFieldGuard::address()`, цикл порівняння `city/address_1/address_2/country_id/zone_id`.

**Механізм:**
1. `validateBsNpPayload()` зберігає НП-текст сирим: `$warehouse['description']`, street label. Ваша ж фікстура моделює саме такий текст: `Поштомат "Нова Пошта" & магазин`.
2. Twig без escaping рендерить `value="Поштомат "Нова Пошта" …"`.
3. Браузер обрізає значення до `Поштомат `. Після `Request::clean` + trim лишається `Поштомат`.
4. `html_entity_decode` не відновлює відсутній текст, тож guard кидає «Адресу Нової пошти змінюйте…».

**Наслідок:**
- Для типових поштоматів і адрес із `"` (у т.ч. courier `address_2`) admin не може змінити навіть ім'я.
- Це суперечить звіту («Admin edits may change recipient names…») і QA п.2.
- Даних це не псує, бо fail-closed. До патча stock тихо записував обрізаний текст, тож безпечність покращилась. Але заявлена поведінка і ремонт r1-B1 не досягнуті.

**Чому тест не виявив:** `admin_address:np_special_text_*` подає значення рядка прямо в POST (сирі або через cleaner). Шлях render → браузер → POST обходиться.

**Ремонт (на вибір):**
- **(a) Рекомендовано.** У `Customer\Address::form()` для рядків з `bs_np_v1` передавати п'ять location-значень як `htmlspecialchars(html_entity_decode($v, ENT_QUOTES|ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8')`, бажано readonly. Порівняння decode-once з обох боків тоді збігається і для сирого, і для entity-encoded зберігання. Exact restore залишається.
- **(b) Альтернатива.** Сервер ігнорує posted location для НП-рядків і завжди бере persisted. Це змінює семантику «reject» → «ignore», тому потрібен вибір власника.

**Дискримінуючий тест:**
1. Рендерити фактичний `address_form.twig` тим самим Twig (autoescape false).
2. Витягнути значення inputs браузерним HTML-парсером (DOMDocument/html5).
3. Пропустити їх через фактичний `Request::clean` → `save()`.
4. Кейси: warehouse/poshtomat/courier із `"`, `&`, `<`; сире і entity-encoded зберігання.
5. Справжня зміна міста має й далі відхилятися.

## Неблокуючі зауваження (бажано в тому ж ремонті)

1. **`checkRawNumbers()` — fail-open.** Результат `preg_match_all` не перевіряється. При `PREG_JIT_STACKLIMIT_ERROR` на довгих рядках (`(?:[^"\\]|\\.)*`) повертається `false`, і перевірка точності тихо пропускається. Потрібно `=== false` → `fail()` і possessive-патерн `(?:[^"\\]++|\\.)*+`.
2. **НП-lookup усередині транзакції й row lock.** Pinta `getByName/getByRef/getZoneIdByRef` (тіла не надані) виконуються після `begin()`. Неявний commit (DDL/temp-table) зламав би атомарність, а мережеві виклики тримали б блокування. Варто викликати `validateBsNpPayload()` до `$acc008->address()`, бо він читає лише POST, або надати тіла.
3. **Неточний коментар у catalog `saveAcc008`.** Там написано «customer's row lock», а блокується рядок `address`. Для нової адреси блокування немає взагалі.
4. **Admin address modal показує address-поля всіх груп** (`getCustomFields` без group). Введені значення полів чужої групи тепер тихо відкидаються, а раніше зберігались. Варто фільтрувати показ за групою клієнта або повертати помилку для зміненого недозволеного поля.
5. **Admin customer: побічний ефект cast.** Через `(int)$post_info['customer_id']` умова `!isset(...)` стала мертвою. POST без `customer_id` більше не змушує валідацію пароля (лише для admin). Окремо, поза scope: stock-перевірка унікальності email при редагуванні не спрацьовує.
6. **File-config events невидимі для DB-preflight.** `action_event` з `system/config/catalog.php` (вміст не наданий) і admin-конфігу (не в intake) preflight не бачить. Їх потрібно надати для ревізії, що відкриває apply.
7. **Ризик для персистентного з'єднання.** Обробник помилок OC робить `exit()` на warning, наприклад undefined key у regex-перевірці опущеного поля. Це відбувається всередині транзакції, і `finally` не виконується. При звичайному з'єднанні буде неявний rollback. Але при `DB_HOSTNAME` з `p:` транзакція «протече» (`rollback_on_cached_plink` off). Preflight може відмовляти на `p:`.
8. **Кеш.** `glob(DIR_CACHE."template/*")` не очищає підкаталоги Twig. Це нешкідливо, бо `auto_reload=true`, але не варто стверджувати «cache cleared». OPcache `validate_timestamps` не враховано. Змішаний стан безпечний, але його треба перевірити в QA.
9. **Розміщення runner.** Runner бере root із `cwd`, тож його можна класти поза webroot і запускати з `cd ~/public_html`. У web-SAPI `STDERR` не визначено. Перевірити, що `_patch_backups` (там вихідний код і manifest) не доступний з web.
10. **Інше:**
    - engine `customer_activity` не перевіряється (це лише лог);
    - подвійний docblock і відступи маркера — косметика;
    - якщо рендериться extension-тема, маркер відсутній і очищення checkbox не спрацює (fail-safe).
11. **Нові зміни.** `!important`, `setTimeout`, `position:absolute/fixed` і магічних пікселів у нових змінах немає. Наявні `setTimeout(220/160)` і `position:absolute` в `address_form.twig` — історичні.

## Оцінка scope спільних writer'ів

Залишити checkout і registration моделі незмінними — правдиво і в межах гранту на чотири форми. Звіт коректно визнає, що lock серіалізує лише ці чотири форми. Незмінені writer'и з read-modify-write без lock можуть затерти новіші дані застарілою копією. Між собою захищені форми цього зробити не можуть.

## Покриття та обмеження

**Прочитано повністю:**
- 9 payload-тіл і диффи;
- runner, `build_patch.py`, `roundtrip_tests.php`, `run_checks.py`;
- before-методи `save()`;
- власні методи моделей, `getCustomFields`;
- `framework.php`, `mysqli.php`, `twig.php`, `request.php`, activity controller/model;
- вердикт r1 і репари r2–r5.

**Не виконувалось нічого:** ні PHP, ні хеші, ні base64, ні MySQL, ні НП.

**Відсутні в пакеті:**
- admin `getAddress`;
- Pinta-моделі;
- вміст `system/config/catalog.php`, admin config;
- Loader/Proxy, session, common.js.

**Межі фікстур:** вони не рендерять шаблони, не моделюють реальний InnoDB і не реєструють events. `acc008_rows()`/`acc008_preflight()` локально не виконувались (stub).

## Вимоги до хостингу та owner QA (після ремонту і нового рев'ю)

1. **Перший `--dry-run`** на PHP 8.0.30:
   - очікувано 5×`host_payload_lint ok`;
   - імовірна безпечна зупинка `active_override_or_event_requires_independent_review`;
   - надіслати bounded metadata.
   
   Це гейт, а не пройдений доказ. Apply можливий лише після окремо переглянутої ревізії з точним allowlist events (DB і file-config).
2. Свіжий DB-дамп, який залишається у власника.
3. Apply, cache, rollback точно за звітом, зі збереженням `backup=` виводу.
4. QA:
   - admin name-only редагування для поштомату, відділення й courier із `"` у тексті має зберегтися;
   - admin-зміна міста/точки має повернути попередження і нічого не змінити;
   - збереження `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename`;
   - очищення vs пропуск поля (окремо checkbox);
   - чужа адреса, permission denied, неавторизований доступ;
   - default-адреса;
   - mobile/tablet/desktop;
   - Tier 1 сторінки без оформлення замовлення.
