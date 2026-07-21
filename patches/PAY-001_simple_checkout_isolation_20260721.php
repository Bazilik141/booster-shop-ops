<?php
/**
 * PAY-001 hotfix — keep mono_chast out of the legacy Simple Checkout.
 *
 * Scope: one catalog payment-model method. No DB/settings/API changes.
 * Rollback: restore the file saved in the printed backup directory.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-001_simple_checkout_isolation_20260721';

function note(string $value): void {
    echo $value . PHP_EOL;
}

function fail(string $value): never {
    fwrite(STDERR, 'ERROR: ' . $value . PHP_EOL);
    exit(1);
}

$root = __DIR__;
$config = $root . '/config.php';
$target = $root . '/extension/mono_chast/catalog/model/payment/mono_chast.php';
$marker = '// PAY-001-SIMPLE-CHECKOUT-ISOLATION';

note('cwd=' . $root);
note('time=' . date('c'));

if (!is_file($config)) {
    fail('config.php missing; run only from public_html.');
}

if (!is_file($target)) {
    fail('Target catalog payment model is missing.');
}

$contents = file_get_contents($target);
if ($contents === false) {
    fail('Cannot read target catalog payment model.');
}

if (strpos($contents, $marker) !== false) {
    note('already_applied=yes');
    @unlink(__FILE__);
    exit(0);
}

$expectedMethod = <<<'PHP'
    public function getMethods(array $address = []): array {
        if (!$this->config->get('payment_mono_chast_status')) return [];
        if (strtoupper((string)($this->session->data['currency'] ?? 'UAH')) !== 'UAH') return [];
        if ((float)$this->cart->getTotal() < (float)$this->config->get('payment_mono_chast_min_total')) return [];
        $parts = json_decode((string)$this->config->get('payment_mono_chast_parts'), true);
        if (!is_array($parts)) $parts = [3, 4, 5];
        $option = [];
        foreach ($parts as $part) {
            $part = (int)$part;
            if (in_array($part, [3, 4, 5], true)) $option['mono_chast_' . $part] = ['code' => 'mono_chast.mono_chast_' . $part, 'name' => 'Покупка Частинами monobank — ' . $part . ' платежі'];
        }
        return $option ? ['code' => 'mono_chast', 'name' => 'Покупка Частинами monobank', 'option' => $option, 'sort_order' => (int)$this->config->get('payment_mono_chast_sort_order')] : [];
    }
PHP;

$replacementMethod = <<<'PHP'
    public function getMethods(array $address = []): array {
        // PAY-001-SIMPLE-CHECKOUT-ISOLATION
        // This OC4 payment-method enumerator feeds the legacy Simple Checkout.
        // The redesigned checkout will use a dedicated, separately approved Phase 2 entry point.
        return [];
    }
PHP;

$normalized = str_replace("\r\n", "\n", $contents);
$anchorCount = substr_count($normalized, $expectedMethod);
if ($anchorCount !== 1) {
    fail('Expected legacy getMethods() body exactly once; found ' . $anchorCount . '. No files changed.');
}

$backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (!mkdir($backup, 0755, true) && !is_dir($backup)) {
    fail('Cannot create backup directory.');
}

$backupFile = $backup . '/mono_chast.catalog.payment_model.before.php';
if (!copy($target, $backupFile)) {
    fail('Cannot create target-file backup.');
}

$updated = str_replace($expectedMethod, $replacementMethod, $normalized, $replaceCount);
if ($replaceCount !== 1) {
    fail('Replacement count is not exactly 1. No files changed after backup.');
}

if (file_put_contents($target, $updated, LOCK_EX) === false) {
    fail('Cannot write target catalog payment model.');
}

$php = is_executable($root . '/system/bin/php') ? $root . '/system/bin/php' : PHP_BINARY;
exec(escapeshellarg($php) . ' -l ' . escapeshellarg($target), $lintOutput, $lintStatus);
if ($lintStatus !== 0) {
    copy($backupFile, $target);
    fail('php -l failed; target restored from backup.');
}

note('backup=' . $backup);
note('changed_file=extension/mono_chast/catalog/model/payment/mono_chast.php');
note('php_l=ok');
note('done=ok');
@unlink(__FILE__);
