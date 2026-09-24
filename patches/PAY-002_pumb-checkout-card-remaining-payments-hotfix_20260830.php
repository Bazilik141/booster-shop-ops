<?php
/**
 * PAY-002 WP4 — show all deferred PUMB payments as remaining.
 * Run from ~/public_html after PAY-002 WP3:
 * php PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830.php
 *
 * PUMB takes no payment on the purchase date, so a selected 3/4/5-month term
 * has 3/4/5 payments remaining. Existing Mono display remains 2/3/4.
 * File-only change. No database writes.
 * Rollback: restore catalog/view/template/checkout/payment_method.twig from the
 * printed _patch_backups/PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830-<ts>/ path.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830';
const BEFORE_SHA256 = '7fde759aa8ca95e627f9b3c579d0f1ca178a8df85b862fe3c7469a35a9b2006e';
const AFTER_SHA256 = 'ea4b41df3577f656ecc57dc02661246ba65e3f6d3deb63dc593517b05850b56b';

function out(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException('ERROR: ' . $message); }
function need(bool $condition, string $message): void { if (!$condition) fail($message); }
function writeChecked(string $path, string $content): void {
    $written = file_put_contents($path, $content);
    need($written !== false && $written === strlen($content), 'write failed: ' . $path);
}
function replaceExactOnce(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    return str_replace($anchor, $replacement, $source);
}

try {
    $root = getcwd() ?: '.';
    need(is_file($root . '/config.php'), 'Run from OpenCart public_html (config.php missing).');

    $rel = 'catalog/view/template/checkout/payment_method.twig';
    $path = $root . '/' . $rel;
    $marker = $root . '/extension/pumb_credit/.pay002-pumb-remaining-marker';
    need(is_file($path), 'missing live file: ' . $rel);
    if (is_file($marker)) { out('already_applied=yes'); exit(0); }

    $source = file_get_contents($path);
    need(is_string($source), 'cannot read ' . $rel);
    $beforeHash = hash('sha256', $source);
    need(
        hash_equals(BEFORE_SHA256, $beforeHash),
        'live source SHA256 mismatch expected=' . BEFORE_SHA256 . ' actual=' . $beforeHash . '; write skipped'
    );
    need(strpos($source, "data-pay002-provider=\"' + providerKey + '\"") !== false, 'WP3 provider marker missing');
    need(strpos($source, 'if (gate.configured && gate.reason && !hasActiveCreditOption)') !== false, 'WP3 blocked-row guard missing');

    $source = replaceExactOnce(
        $source,
        "      var count = providerSelected ? Number(selectedMatch[2]) : (Number(preferred) || termOptions[0].count);\n      var card =",
        "      var count = providerSelected ? Number(selectedMatch[2]) : (Number(preferred) || termOptions[0].count);\n      var remainingPayments = providerKey === 'pumb_credit' ? count : Math.max(count - 1, 0);\n      var card =",
        'initial provider remaining value'
    );
    $source = replaceExactOnce(
        $source,
        "<strong data-pay001-left>' + Math.max(count - 1, 0) + '</strong>",
        "<strong data-pay001-left>' + remainingPayments + '</strong>",
        'initial provider remaining output'
    );
    $source = replaceExactOnce(
        $source,
        "    \$provider.find('[data-pay001-left]').text(Math.max(count - 1, 0));",
        "    var remainingPayments = String(\$provider.attr('data-pay002-provider') || '') === 'pumb_credit' ? count : Math.max(count - 1, 0);\n    \$provider.find('[data-pay001-left]').text(remainingPayments);",
        'clicked provider remaining output'
    );

    $stamp = date('Ymd-His');
    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $stamp;
    $backupPath = $backup . '/' . $rel;
    need(is_dir(dirname($backupPath)) || mkdir(dirname($backupPath), 0755, true) || is_dir(dirname($backupPath)), 'cannot create backup directory');
    need(copy($path, $backupPath), 'backup failed: ' . $rel);

    try {
        writeChecked($path, $source);
        $written = file_get_contents($path);
        need(is_string($written), 'cannot read generated Twig');
        $afterHash = hash('sha256', $written);
        need(
            hash_equals(AFTER_SHA256, $afterHash),
            'generated SHA256 mismatch expected=' . AFTER_SHA256 . ' actual=' . $afterHash
        );
        need(substr_count($written, "var remainingPayments = providerKey === 'pumb_credit' ? count : Math.max(count - 1, 0);") === 1, 'initial provider formula assertion failed');
        need(substr_count($written, "String(\$provider.attr('data-pay002-provider') || '') === 'pumb_credit' ? count : Math.max(count - 1, 0)") === 1, 'clicked provider formula assertion failed');
        need(substr_count($written, "<strong data-pay001-left>' + remainingPayments + '</strong>") === 1, 'initial remaining output assertion failed');
        need(substr_count($written, "\$provider.find('[data-pay001-left]').text(remainingPayments);") === 1, 'clicked remaining output assertion failed');
        out('sha_gate=ok before=' . substr($beforeHash, 0, 8) . ' after=' . substr($afterHash, 0, 8));
        out('twig_assert=ok');
        writeChecked($marker, PATCH_ID . ' applied ' . date('c') . PHP_EOL);
        out('cwd=' . $root);
        out('time=' . date('c'));
        out('backup=' . $backup);
        out('changed=' . $rel);
        out('database_touched=no');
        out('done=ok');
    } catch (Throwable $exception) {
        $reason = $exception->getMessage();
        need(@copy($backupPath, $path), 'restore copy failed after: ' . $reason);
        $restored = file_get_contents($path);
        need(is_string($restored) && hash_equals(BEFORE_SHA256, hash('sha256', $restored)), 'restore verification failed after: ' . $reason);
        fail('source restored: ' . $reason);
    }

    if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes');
    else out('self_delete=ok');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
