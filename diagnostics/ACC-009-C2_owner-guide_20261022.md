# ACC-009 C2 — запуск власником

C2 підставляє кур’єрську адресу зі знімка замовлення у форму ТТН: область,
місто, вулицю, будинок і квартиру. Неоднозначні адреси отримують попередження;
створення ТТН із неперевіреною кур’єрською адресою блокується. Відправник і
прийнята логіка імен залишаються без змін. Старі неоднозначні адреси потребують
ручної перевірки; це не виправлення мережевих помилок НП.

Фінальне незалежне рев’ю: REVIEW_OK, запит
e00b1d8d-92de-4423-9ca8-7b6548980952. Обов’язкових виправлень немає.
Прийняття: `diagnostics/ACC-009_C2-release_evidence_20261022/release-accepted.json`.
Рев’ю й локальні перевірки не заміняють запуск на сервері та перевірку форми.

## Перший блок: Upload → Verify → Preflight

Виконати у звичайному PowerShell без прав адміністратора. Кожен крок запускає
пакет окремо. Після будь-якої помилки блок зупиняється. Ключ повторно відкривати
не потрібно, якщо він доступний; обов’язкового Lock після роботи немає.

```powershell
$root = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$packet = Join-Path $root 'diagnostics\ACC-009-C2_owner-PS_20261022.ps1'
if ((Get-FileHash -LiteralPath $packet -Algorithm SHA256).Hash.ToLowerInvariant() -cne 'bc920b06508ee2332ef30120e5f7d8eb715d2ede15d0b01ae216c604dda8a2be') { throw 'STOP: неправильний SHA256 пакета' }

powershell.exe -NoProfile -ExecutionPolicy Bypass -File $packet -Step Upload
if ($LASTEXITCODE -ne 0) { throw 'STOP: Upload' }

powershell.exe -NoProfile -ExecutionPolicy Bypass -File $packet -Step Verify
if ($LASTEXITCODE -ne 0) { throw 'STOP: Verify' }

powershell.exe -NoProfile -ExecutionPolicy Bypass -File $packet -Step Preflight
if ($LASTEXITCODE -ne 0) { throw 'STOP: Preflight' }
```

Очікуємо `step=Upload status=OK exit=0`, потім аналогічні Verify і Preflight.
Preflight: `action=planned`, `changed_files=0`, `probe_cleanup=ok`,
`directory_schema=required_local_address_columns_textual`, `done=ok`.
Надіслати повний технічний вивід. Apply — наступний окремий крок після перевірки
цих квитанцій, у межах 30 хвилин від Preflight. При простроченні потрібна окрема
оцінка перед продовженням. Не запускати Apply або відкат навмання.

## Точні артефакти й очікуваний стан

- Runner: `patches/ACC-009-C2_recipient-address-prefill-private-release_20261022.php`.
  SHA256: `8645e8f247681529d0a8ec9e42b6a01fa1a656e246fa949a4fb08796206fb9d2`.
- Пакет: `diagnostics/ACC-009-C2_owner-PS_20261022.ps1`.
  SHA256: `bc920b06508ee2332ef30120e5f7d8eb715d2ede15d0b01ae216c604dda8a2be`.
- Єдиний цільовий файл:
  `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`.
- До застосування: `2138e15643ce4bced1b49698955c78f443617e4b59877325113261042d1c7b93`.
- Після застосування: `4d84bb039d7c43ede234d76deef698b8e0e7886184800e6bf6eeaaa7a19ca0af`.
- Preflight не створює резервної копії цільового файлу: `backup=none`.
  Apply створить власну копію:
  `/home2/boosters/_patch_backups/ACC-009-C2-<UTC>-<8hex>/internet_document.php`.
  Фактичний повний шлях приймається лише з успішної квитанції Apply.
- Пакет заливає runner у перевірену приватну папку поза webroot; звідти запускає
  його з робочою папкою public_html. Після успішного Apply runner видаляє себе.
- Runner перевіряє PHP, джерела, метадані та колонки довідників, створює короткий
  захищений тимчасовий web-probe і прибирає його за точними власними байтами.
  Бізнес-викликів НП, змін БД і очищення кешу немає. Native lint обов’язковий;
  помилка застосування відновлює початкові байти або зупиняється з відомим станом.
- Відкат потребує окремого перевіреного пакета: спочатку перевірити точний
  цільовий файл і власну резервну копію. C2 відкатується перед N3. Відкат коду
  не скасовує вже виконаних власником дій у НП.

Квитанції порівнюються як planned → applied, із рівно однією власною копією та
відсутнім staged runner. Unexpected already_applied, зміна runtime або будь-який
cleanup STOP потребують окремої оцінки. Повтор Apply заборонений після спроби.
Очікуваний runtime: PHP8.0.30/litespeed/OPcache enabled=true,
validate_timestamps=true/revalidate_freq=2. Допустиме попередження stderr лише
точне: `WARNING: MYSQL_OPT_RECONNECT is deprecated and will be removed in a future version.`

Після Apply перевірити підстановку у формі реального кур’єрського замовлення.
Окрему посилку лише заради тесту не створювати; збереження перевіряється під час
законної робочої відправки. Не надсилати імен, телефонів, адрес чи номерів замовлень.
