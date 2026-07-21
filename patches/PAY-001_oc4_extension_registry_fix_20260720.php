<?php
/**
 * PAY-001 round 2 — complete OC4 extension registry for mono_chast.
 *
 * Affects only ocp5_extension_install and ocp5_extension_path.
 * Does not change extension source files, settings, marker files or payment status.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-001_oc4_extension_registry_fix_20260720';
function info(string $value): void { echo $value . PHP_EOL; }
function fail(string $value): never { fwrite(STDERR, 'ERROR: ' . $value . PHP_EOL); exit(1); }
function query(mysqli $db, string $sql): mysqli_result|bool { $result = $db->query($sql); if ($result === false) fail('SQL failed: ' . $db->error); return $result; }
function save(string $path, string $contents): void { if (file_put_contents($path, $contents) === false) fail('Cannot write ' . $path); }
function literal(mysqli $db, mixed $value): string { return $value === null ? 'NULL' : "'" . $db->real_escape_string((string)$value) . "'"; }

$root = __DIR__;
$config = $root . '/config.php';
$extensionRoot = $root . '/extension/mono_chast';
$installJson = $extensionRoot . '/install.json';
$languageFile = $extensionRoot . '/admin/language/uk-ua/payment/mono_chast.php';
$marker = $extensionRoot . '/.pay001-registry-marker';
info('cwd=' . $root); info('time=' . date('c'));
if (!is_file($config)) fail('config.php missing; run only from public_html.');
if (!is_file($installJson) || !is_file($languageFile)) fail('Deployed mono_chast extension anchor files are missing.');
if (is_file($marker)) { info('already_applied=yes'); exit(0); }
require $config;
foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'] as $key) if (!defined($key)) fail($key . ' missing from config.php.');
if (DB_PREFIX !== 'ocp5_') fail('Unexpected DB_PREFIX; expected ocp5_.');

$install = json_decode((string)file_get_contents($installJson), true);
if (!is_array($install) || ($install['code'] ?? '') !== 'mono_chast' || empty($install['name'])) fail('install.json does not match the expected mono_chast extension.');
$timestamp = date('Ymd-His');
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $timestamp;
if (!mkdir($backup, 0755, true) && !is_dir($backup)) fail('Cannot create backup directory.');
save($backup . '/rollback.sql', "-- Restore database snapshots from this backup if rollback is required.\n-- Do not delete extension source files or .pay001-marker.\n");
info('backup=' . $backup);

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($db->connect_errno) fail('Database connection failed before changes.');
$db->set_charset('utf8mb4');
$prefix = DB_PREFIX;
foreach ([$prefix . 'extension', $prefix . 'extension_install', $prefix . 'extension_path'] as $table) {
    $exists = query($db, "SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    if (!$exists->num_rows) { $db->close(); fail('Required OC4 table missing: ' . $table); }
}
$extension = query($db, "SELECT * FROM " . $prefix . "extension WHERE extension='mono_chast' AND type='payment' AND code='mono_chast' LIMIT 1")->fetch_assoc();
if (!$extension) { $db->close(); fail('Classic payment registry row is missing; refusing to repair only a partial registration.'); }
$existingInstall = query($db, "SELECT * FROM " . $prefix . "extension_install WHERE code='mono_chast' LIMIT 1")->fetch_assoc();
save($backup . '/extension_install.before.json', json_encode($existingInstall ?: null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
if ($existingInstall) {
    $installId = (int)$existingInstall['extension_install_id'];
    query($db, "UPDATE " . $prefix . "extension_install SET name='" . $db->real_escape_string((string)$install['name']) . "', version='" . $db->real_escape_string((string)($install['version'] ?? '1.0.0')) . "', author='" . $db->real_escape_string((string)($install['author'] ?? 'Booster Shop')) . "', link='" . $db->real_escape_string((string)($install['link'] ?? '')) . "', status='1' WHERE extension_install_id='" . $installId . "'");
    $createdInstall = false;
} else {
    query($db, "INSERT INTO " . $prefix . "extension_install SET extension_id='0', extension_download_id='0', name='" . $db->real_escape_string((string)$install['name']) . "', description='PAY-001 sandbox extension registry', code='mono_chast', version='" . $db->real_escape_string((string)($install['version'] ?? '1.0.0')) . "', author='" . $db->real_escape_string((string)($install['author'] ?? 'Booster Shop')) . "', link='" . $db->real_escape_string((string)($install['link'] ?? '')) . "', status='1', date_added=NOW()");
    $installId = (int)$db->insert_id;
    $createdInstall = true;
}

$paths = ['mono_chast', 'mono_chast/.pay001-registry-marker'];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extensionRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($iterator as $item) {
    $relative = 'mono_chast/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($extensionRoot) + 1));
    $paths[] = $relative;
}
$paths = array_values(array_unique($paths));
$beforePaths = query($db, "SELECT * FROM " . $prefix . "extension_path WHERE extension_install_id='" . $installId . "' ORDER BY extension_path_id ASC")->fetch_all(MYSQLI_ASSOC);
save($backup . '/extension_path.before.json', json_encode($beforePaths, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
$added = 0;
foreach ($paths as $path) {
    $found = query($db, "SELECT extension_path_id FROM " . $prefix . "extension_path WHERE extension_install_id='" . $installId . "' AND path='" . $db->real_escape_string($path) . "' LIMIT 1");
    if (!$found->num_rows) { query($db, "INSERT INTO " . $prefix . "extension_path SET extension_install_id='" . $installId . "', path='" . $db->real_escape_string($path) . "'"); $added++; }
}
$rollback = "-- PAY-001 OC4 registry rollback. This removes only this registry metadata; it does not remove extension files.\n";
if ($createdInstall) {
    $rollback .= "DELETE FROM " . $prefix . "extension_path WHERE extension_install_id='" . $installId . "';\nDELETE FROM " . $prefix . "extension_install WHERE extension_install_id='" . $installId . "' AND code='mono_chast';\n";
} else {
    $rollback .= "DELETE FROM " . $prefix . "extension_path WHERE extension_install_id='" . $installId . "';\n";
    foreach ($beforePaths as $row) $rollback .= "INSERT INTO " . $prefix . "extension_path (extension_path_id,extension_install_id,path) VALUES (" . (int)$row['extension_path_id'] . "," . (int)$row['extension_install_id'] . "," . literal($db, $row['path']) . ");\n";
    $rollback .= "UPDATE " . $prefix . "extension_install SET name=" . literal($db, $existingInstall['name'] ?? null) . ", description=" . literal($db, $existingInstall['description'] ?? null) . ", version=" . literal($db, $existingInstall['version'] ?? null) . ", author=" . literal($db, $existingInstall['author'] ?? null) . ", link=" . literal($db, $existingInstall['link'] ?? null) . ", status=" . literal($db, $existingInstall['status'] ?? null) . " WHERE extension_install_id='" . $installId . "';\n";
}
save($backup . '/rollback.sql', $rollback);
$db->close();
save($marker, "PAY-001 OC4 registry fix installed 2026-07-20\n");
info('extension_install_id=' . $installId); info('path_rows_added=' . $added); info('done=ok');
@unlink(__FILE__);
