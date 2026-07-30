<?php
/**
 * PAY-002 hotfix — preserve shared installment status IDs when PUMB admin settings save.
 * Upload to public_html and run: php PAY-002_admin-status-settings-preserve_20260728.php
 * No database changes. Rollback: restore the backed-up Twig file.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_admin-status-settings-preserve_20260728';
function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $message): never { fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL); exit(1); }

$root = getcwd() ?: '.';
$target = $root . '/extension/pumb_credit/admin/view/template/payment/pumb_credit.twig';
$marker = $root . '/extension/pumb_credit/.pay002-admin-status-preserve-marker';
if (is_file($marker)) { out('already_applied=yes'); exit(0); }
if (!is_file($target)) fail('Required PAY-002 Twig target is missing: extension/pumb_credit/admin/view/template/payment/pumb_credit.twig');
$source = file_get_contents($target);
if (!is_string($source)) fail('Cannot read Twig target.');
$anchor = '<input type="hidden" name="payment_pumb_credit_sort_order" value="{{ payment_pumb_credit_sort_order }}"><button type="submit" class="btn btn-primary">Зберегти</button> <a href="{{ cancel }}" class="btn btn-light">Скасувати</a>';
if (substr_count($source, $anchor) !== 1) fail('Expected PUMB settings submit anchor exactly once.');
$replacement = '<input type="hidden" name="payment_pumb_credit_sort_order" value="{{ payment_pumb_credit_sort_order }}">' . "\n"
    . '<input type="hidden" name="payment_pumb_credit_status_waiting_client" value="{{ payment_pumb_credit_status_waiting_client }}">' . "\n"
    . '<input type="hidden" name="payment_pumb_credit_status_waiting_store" value="{{ payment_pumb_credit_status_waiting_store }}">' . "\n"
    . '<input type="hidden" name="payment_pumb_credit_status_funded" value="{{ payment_pumb_credit_status_funded }}">' . "\n"
    . '<input type="hidden" name="payment_pumb_credit_status_returned" value="{{ payment_pumb_credit_status_returned }}">' . "\n"
    . '<input type="hidden" name="payment_pumb_credit_status_failed" value="{{ payment_pumb_credit_status_failed }}">' . "\n"
    . '<button type="submit" class="btn btn-primary">Зберегти</button> <a href="{{ cancel }}" class="btn btn-light">Скасувати</a>';
$patched = str_replace($anchor, $replacement, $source);
$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (!mkdir($backup, 0755, true) && !is_dir($backup)) fail('Cannot create backup directory.');
if (!copy($target, $backup . '/pumb_credit.twig.before')) fail('Cannot back up Twig target.');
if (file_put_contents($target, $patched) === false) fail('Cannot write patched Twig target.');
if (file_put_contents($marker, 'PAY-002 admin settings status preservation installed ' . date('c') . PHP_EOL) === false) { copy($backup . '/pumb_credit.twig.before', $target); fail('Cannot write idempotency marker; Twig restored.'); }
out('cwd=' . $root);
out('time=' . date('c'));
out('backup=' . $backup);
out('changed_file=extension/pumb_credit/admin/view/template/payment/pumb_credit.twig');
out('done=ok');
@unlink(__FILE__);
