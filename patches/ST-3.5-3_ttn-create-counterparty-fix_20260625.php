<?php
/**
 * ST-3.5-3 — fix admin TTN creation crash for non-PrivatePerson sender.
 *
 * Scope:
 * - Adds sender phone to prepareSender() call/signature.
 * - Instantiates PintaCounterparty inside prepareSender()/prepareRecipient().
 * - No DB changes. No checkout/payment/totals/COD logic changes.
 */

declare(strict_types=1);

$patch = 'st3.5-3-ttn-create-20260625';
$root = __DIR__;
$target = $root . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';

$oldCall = <<<'PHP'
                $this->prepareSender(
                    $formdata['sender'],
                    $formdata['sender_firstname'],
                    $formdata['sender_middlename'],
                    $formdata['sender_lastname']
                );
PHP;

$newCall = <<<'PHP'
                $this->prepareSender(
                    $formdata['sender'],
                    $formdata['sender_firstname'],
                    $formdata['sender_middlename'],
                    $formdata['sender_lastname'],
                    $formdata['sender_phone']
                );
PHP;

$oldSenderSignature = <<<'PHP'
    public function prepareSender(
        $counterparty_ref,
        $firstname,
        $middlename,
        $lastname
    )
    {
        $has_error = false;
PHP;

$newSenderSignature = <<<'PHP'
    public function prepareSender(
        $counterparty_ref,
        $firstname,
        $middlename,
        $lastname,
        $phone
    )
    {
        $PintaCounterparty = new \Opencart\System\Library\Pintanovaposhta\PintaCounterparty();
        $has_error = false;
PHP;

$oldRecipientSignature = <<<'PHP'
    public function prepareRecipient(
        $counterparty_ref,
        $firstname,
        $middlename,
        $lastname,
        $phone,
        $email
    )
    {
        $has_error = false;
PHP;

$newRecipientSignature = <<<'PHP'
    public function prepareRecipient(
        $counterparty_ref,
        $firstname,
        $middlename,
        $lastname,
        $phone,
        $email
    )
    {
        $PintaCounterparty = new \Opencart\System\Library\Pintanovaposhta\PintaCounterparty();
        $has_error = false;
PHP;

function st353_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "error={$message}\n");
    exit($code);
}

function st353_count(string $haystack, string $needle): int
{
    return substr_count($haystack, $needle);
}

function st353_php_lint(string $file): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    return [$exitCode, implode("\n", $output)];
}

echo "patch={$patch}\n";
echo "cwd=" . getcwd() . "\n";
echo "time=" . date('c') . "\n";
echo "db_changes=no\n";

if (!is_file($target)) {
    st353_fail('target_missing=' . $target);
}

$content = file_get_contents($target);
if ($content === false) {
    st353_fail('target_read_failed=' . $target);
}

$oldCounts = [
    'sender_call' => st353_count($content, $oldCall),
    'sender_signature' => st353_count($content, $oldSenderSignature),
    'recipient_signature' => st353_count($content, $oldRecipientSignature),
];
$newCounts = [
    'sender_call' => st353_count($content, $newCall),
    'sender_signature' => st353_count($content, $newSenderSignature),
    'recipient_signature' => st353_count($content, $newRecipientSignature),
];

foreach ($oldCounts as $key => $count) {
    echo "old_{$key}_count={$count}\n";
}
foreach ($newCounts as $key => $count) {
    echo "new_{$key}_count={$count}\n";
}

if (array_sum($oldCounts) === 0 && $newCounts['sender_call'] === 1 && $newCounts['sender_signature'] === 1 && $newCounts['recipient_signature'] === 1) {
    echo "already_applied=yes\n";
    echo "done=ok\n";
    @unlink(__FILE__);
    exit(0);
}

foreach ($oldCounts as $key => $count) {
    if ($count !== 1) {
        st353_fail("unexpected_old_{$key}_count={$count}");
    }
}
foreach ($newCounts as $key => $count) {
    if ($count !== 0) {
        st353_fail("unexpected_new_{$key}_count={$count}");
    }
}

$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$backupFile = $backupDir . '/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';

if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true)) {
    st353_fail('backup_dir_create_failed=' . dirname($backupFile));
}

if (!copy($target, $backupFile)) {
    st353_fail('backup_copy_failed=' . $backupFile);
}

echo "backup={$backupFile}\n";

$updated = str_replace(
    [$oldCall, $oldSenderSignature, $oldRecipientSignature],
    [$newCall, $newSenderSignature, $newRecipientSignature],
    $content,
    $replaceCount
);

if ($replaceCount !== 3) {
    copy($backupFile, $target);
    st353_fail('replace_count=' . $replaceCount);
}

if (file_put_contents($target, $updated) === false) {
    copy($backupFile, $target);
    st353_fail('target_write_failed_restored_backup');
}

[$lintExit, $lintOutput] = st353_php_lint($target);
echo "php_lint=" . str_replace(["\r", "\n"], ' | ', trim($lintOutput)) . "\n";

if ($lintExit !== 0) {
    copy($backupFile, $target);
    st353_fail('php_lint_failed_restored_backup');
}

$post = file_get_contents($target);
if ($post === false) {
    copy($backupFile, $target);
    st353_fail('post_read_failed_restored_backup');
}

if (st353_count($post, $newCall) !== 1 || st353_count($post, $newSenderSignature) !== 1 || st353_count($post, $newRecipientSignature) !== 1) {
    copy($backupFile, $target);
    st353_fail('postcheck_failed_restored_backup');
}

echo "changed_file=extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php\n";
echo "already_applied=no\n";
echo "done=ok\n";
@unlink(__FILE__);
exit(0);
