<?php
/**
 * PAY-002: select the actual modal bank/term in the unified installment option.
 * Requires the successful product-autoselect hotfix from 2026-08-31.
 * Root cause: flattenPaymentMethods kept Mono's code even with PUMB fromModal.
 * Scope: checkout/payment_method.twig only. No DB, CSS, timers, or bank calls.
 * Run a COPY from public_html. The runner deletes itself after success.
 * Rollback: restore catalog/view/template/checkout/payment_method.twig from the
 * printed backup directory, then clear OpenCart template cache. No DB rollback.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-provider-selection_20260831';
const TARGET = 'catalog/view/template/checkout/payment_method.twig';
const BEFORE_SHA256 = 'fb57eef71fe3141d4b7d2713ecc2c465ab371476786f491399a4d195764d0f3b';
const AFTER_SHA256 = 'efee1a60c2cecc7547787646690cc00fc01a428f9a2e9a64d9f08d464db6d396';
const PREREQUISITES = [
    'catalog/controller/checkout/checkout.php' => '96ef34ad3c0c2b3c980d6ffe54953da3f66e2cd1320f9f549305f5c3f98289c9',
    'catalog/controller/checkout/payment_method.php' => '0497216e9267f92c385ea43b43e09fc0a9531ff5df71a6e7d954d6f816c9b79c'
];

function need(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function replaceOnce(string $source, string $anchor, string $replacement): string {
    need(substr_count($source, $anchor) === 1, 'anchor count is not 1; write skipped');
    return str_replace($anchor, $replacement, $source);
}
function selfDelete(): void {
    echo @unlink(__FILE__) ? "self_delete=ok\n" : "self_delete=failed remove_uploaded_patch_manually=yes\n";
}

try {
    need(PHP_SAPI === 'cli', 'CLI only');
    $root = getcwd();
    need(is_string($root) && realpath($root) === realpath(__DIR__) && is_file($root . '/config.php'), 'Run an uploaded copy from OpenCart public_html');
    echo 'cwd=' . $root . "\ntime=" . date('c') . "\n";
    foreach (PREREQUISITES as $rel => $expected) {
        need(is_file($root . '/' . $rel), 'missing prerequisite: ' . $rel);
        need(hash_file('sha256', $root . '/' . $rel) === $expected, 'prerequisite SHA mismatch: ' . $rel . '; write skipped');
    }
    $path = $root . '/' . TARGET;
    need(is_file($path), 'missing target: ' . TARGET);
    $source = file_get_contents($path);
    need(is_string($source), 'cannot read target');
    $before = hash('sha256', $source);
    if ($before === AFTER_SHA256) {
        echo "already_applied=yes\n";
        selfDelete();
        exit(0);
    }
    need($before === BEFORE_SHA256, 'source SHA mismatch; expected=' . BEFORE_SHA256 . ' actual=' . $before . '; write skipped');
    // Use source line endings, never PHP_EOL (Windows and Linux must agree).
    $eol = strpos($source, "\r\n") !== false ? "\r\n" : "\n";
    $pumbAnchor = '              creditOption.fromModal = creditOption.fromModal || !!group.pay002_from_modal;';
    $pumbReplacement = $pumbAnchor . $eol
        . '              // PAY-002-PUMB-PROVIDER-SELECTION-20260831: carry the bank and term, not just the open-drawer flag.' . $eol
        . '              if (group.pay002_from_modal) creditOption.code = preferredOption.code;';
    $result = replaceOnce($source, $pumbAnchor, $pumbReplacement);
    $result = replaceOnce($result,
        '              creditOption.code = preferredOption.code;',
        '              if (!creditOption.fromModal) creditOption.code = preferredOption.code;');
    $after = hash('sha256', $result);
    need($after === AFTER_SHA256, 'generated SHA mismatch; actual=' . $after . '; write skipped');

    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $backupPath = $backup . '/' . TARGET;
    need(mkdir(dirname($backupPath), 0755, true), 'cannot create backup directory');
    need(copy($path, $backupPath) && hash_file('sha256', $backupPath) === BEFORE_SHA256, 'backup verification failed; write skipped');
    echo 'backup=' . $backup . "\n";
    try {
        need(file_put_contents($path, $result, LOCK_EX) === strlen($result), 'target write failed');
        need(hash_file('sha256', $path) === AFTER_SHA256, 'written target SHA mismatch');
    } catch (Throwable $error) {
        need(copy($backupPath, $path) && hash_file('sha256', $path) === BEFORE_SHA256, 'ROLLBACK FAILED; restore target from ' . $backup);
        throw new RuntimeException('source restored: ' . $error->getMessage());
    }
    echo 'after_sha256=' . TARGET . ':' . AFTER_SHA256 . "\n";
    echo 'changed=' . TARGET . "\nphp_l=not_applicable target=twig_only\nassertions=ok\ndatabase_touched=no\ndone=ok\n";
    selfDelete();
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(1);
}
