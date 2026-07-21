<?php
/**
 * PAY-001 round 3 — define the admin controller error bag.
 * No DB, registry, settings or frontend changes.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-001_admin_error_property_fix_20260721';
function note(string $value): void { echo $value . PHP_EOL; }
function fail(string $value): never { fwrite(STDERR, 'ERROR: ' . $value . PHP_EOL); exit(1); }

$root = __DIR__;
$target = $root . '/extension/mono_chast/admin/controller/payment/mono_chast.php';
$config = $root . '/config.php';
note('cwd=' . $root); note('time=' . date('c'));
if (!is_file($config)) fail('config.php missing; run only from public_html.');
if (!is_file($target)) fail('Target admin controller is missing.');
$contents = (string)file_get_contents($target);
$property = '    protected array $error = [];';
if (substr_count($contents, $property) === 1) { note('already_applied=yes'); exit(0); }
if (substr_count($contents, $property) !== 0) fail('Unexpected error-property anchor count.');
$anchor = 'class MonoChast extends \\Opencart\\System\\Engine\\Controller {';
if (substr_count($contents, $anchor) !== 1) fail('Class anchor count is not exactly 1.');
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (!mkdir($backup, 0755, true) && !is_dir($backup)) fail('Cannot create backup directory.');
if (!copy($target, $backup . '/mono_chast.admin.controller.before.php')) fail('Cannot create controller backup.');
$updated = str_replace($anchor, $anchor . PHP_EOL . $property, $contents);
if (file_put_contents($target, $updated) === false) fail('Cannot write target controller.');
$php = is_executable($root . '/system/bin/php') ? $root . '/system/bin/php' : PHP_BINARY;
exec(escapeshellarg($php) . ' -l ' . escapeshellarg($target), $lint, $status);
if ($status !== 0) { copy($backup . '/mono_chast.admin.controller.before.php', $target); fail('php -l failed; controller restored from backup.'); }
note('backup=' . $backup); note('changed_file=extension/mono_chast/admin/controller/payment/mono_chast.php'); note('php_l=ok'); note('done=ok');
@unlink(__FILE__);
