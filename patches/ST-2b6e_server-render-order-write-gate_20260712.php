<?php
declare(strict_types=1);

/**
 * ST-2b.6e — prevent server-rendered checkout previews from creating orders.
 *
 * Root cause confirmed from the 2026-07-12 cPanel backup:
 * checkout/checkout internally renders checkout/confirm::index(), and index()
 * currently calls addOrder()/editOrder() whenever checkout session data is
 * complete. A plain checkout GET/reload therefore bypasses every browser click
 * gate and can create a status-0 order.
 *
 * Target:
 * - catalog/controller/checkout/confirm.php
 *
 * Behavior after patch:
 * - index() remains a read-only cart/totals preview by default;
 * - only public confirm() passes the explicit write flag after its existing
 *   validation, so the normal trusted place-order flow still creates one order;
 * - payment controller HTML is also withheld from read-only preview renders.
 *
 * Database/schema changes: none.
 * Rollback: restore the printed backup file. OpenCart template/cache cleanup is
 * not needed because the target is a PHP controller.
 */

const ST2B6E_TARGET = 'catalog/controller/checkout/confirm.php';
const ST2B6E_SOURCE_SHA256 = '8ceb7aea1e76afc8b494c62af42de168dd0c27e6ec0c0ccaac8a7d6030e7393d';
const ST2B6E_MARKER = 'ST-2b.6e: checkout preview is read-only';

function st2b6e_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function st2b6e_fail(string $message): never {
    st2b6e_out('error', $message);
    st2b6e_out('done', 'error');
    exit(1);
}

function st2b6e_join(string $root, string $relative): string {
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function st2b6e_read(string $path, string $label): string {
    if (!is_file($path)) {
        st2b6e_fail('target_not_found:' . $label);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        st2b6e_fail('cannot_read:' . $label);
    }

    return $content;
}

/** @return array{0:int,1:string} */
function st2b6e_lint(string $path): array {
    $output = [];
    $code = 0;
    $binary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    exec(escapeshellarg($binary) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);

    return [$code, trim(implode(' ', $output))];
}

function st2b6e_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        st2b6e_fail('anchor_count_' . $label . '=' . $count . ':expected=1');
    }

    return str_replace($search, $replace, $content);
}

function st2b6e_is_applied(string $content): bool {
    $checks = [
        ST2B6E_MARKER => 1,
        'public function index(bool $allow_order_write = false): string {' => 1,
        'if ($status && $allow_order_write) {' => 1,
        'if ($status && $allow_order_write && $extension_info) {' => 1,
        '$this->response->setOutput($this->index(true));' => 1,
        '$this->model_checkout_order->addOrder($order_data)' => 1,
        '$this->model_checkout_order->editOrder($order_id, $order_data)' => 1,
    ];

    foreach ($checks as $needle => $expected) {
        if (substr_count($content, $needle) !== $expected) {
            return false;
        }
    }

    return true;
}

function st2b6e_restore(string $backup, string $target): void {
    if (is_file($backup) && @copy($backup, $target)) {
        st2b6e_out('restore_on_fail', 'ok');
        return;
    }

    st2b6e_out('restore_on_fail', 'failed');
}

$root = realpath(getcwd());

if ($root === false) {
    st2b6e_fail('cannot_resolve_cwd');
}

$patchName = pathinfo(__FILE__, PATHINFO_FILENAME);
$targetPath = st2b6e_join($root, ST2B6E_TARGET);

st2b6e_out('patch', $patchName);
st2b6e_out('cwd', $root);
st2b6e_out('time', date(DATE_ATOM));
st2b6e_out('scope', 'gate addOrder/editOrder and payment HTML behind explicit confirm() call');
st2b6e_out('db_schema_changes', 'none');
st2b6e_out('db_data_changes', 'none');

[$patchLintCode, $patchLintOutput] = st2b6e_lint(__FILE__);
st2b6e_out('php_l_patch', $patchLintCode === 0 ? 'ok' : 'failed');

if ($patchLintCode !== 0) {
    st2b6e_fail('patch_php_l_failed:' . $patchLintOutput);
}

$source = st2b6e_read($targetPath, ST2B6E_TARGET);

if (strpos($source, ST2B6E_MARKER) !== false) {
    if (!st2b6e_is_applied($source)) {
        st2b6e_fail('partial_or_drifted_applied_state');
    }

    st2b6e_out('already_applied', 'yes');
    st2b6e_out('changed_files', 'none');
    st2b6e_out('done', 'ok');
    @unlink(__FILE__);
    exit(0);
}

$actualHash = hash('sha256', $source);

if (!hash_equals(ST2B6E_SOURCE_SHA256, $actualHash)) {
    st2b6e_fail('source_sha256_mismatch:' . ST2B6E_TARGET . ':expected=' . ST2B6E_SOURCE_SHA256 . ':actual=' . $actualHash);
}

$eol = str_contains($source, "\r\n") ? "\r\n" : "\n";
$patched = $source;

$patched = st2b6e_replace_once(
    $patched,
    "\tpublic function index(): string {",
    "\t// " . ST2B6E_MARKER . ". Internal checkout renders must never write." . $eol .
    "\tpublic function index(bool \$allow_order_write = false): string {",
    'index_signature'
);

$patched = st2b6e_replace_once(
    $patched,
    "\t\tif (\$status) {",
    "\t\tif (\$status && \$allow_order_write) {",
    'order_write_gate'
);

$patched = st2b6e_replace_once(
    $patched,
    "\t\tif (\$status && \$extension_info) {",
    "\t\tif (\$status && \$allow_order_write && \$extension_info) {",
    'payment_html_gate'
);

$patched = st2b6e_replace_once(
    $patched,
    "\t\t\$this->response->setOutput(\$this->index());",
    "\t\t// Explicit endpoint only: permit the existing order write after validation." . $eol .
    "\t\t\$this->response->setOutput(\$this->index(true));",
    'explicit_confirm_write_flag'
);

if (!st2b6e_is_applied($patched)) {
    st2b6e_fail('postbuild_invariants_failed');
}

if (substr_count($patched, 'public function confirm(): void {') !== 1 ||
    substr_count($patched, 'data-bs-captcha-error') !== 1) {
    st2b6e_fail('postbuild_existing_confirm_validation_changed');
}

$timestamp = date('Ymd-His');
$backupDir = st2b6e_join($root, '_patch_backups/' . $patchName . '-' . $timestamp);
$backupPath = st2b6e_join($backupDir, ST2B6E_TARGET);
$backupParent = dirname($backupPath);

if (!is_dir($backupParent) && !mkdir($backupParent, 0775, true) && !is_dir($backupParent)) {
    st2b6e_fail('cannot_create_backup_dir:' . $backupParent);
}

if (!copy($targetPath, $backupPath)) {
    st2b6e_fail('cannot_create_backup:' . ST2B6E_TARGET);
}

st2b6e_out('backup', $backupPath);

$tempPath = $targetPath . '.' . $patchName . '.tmp';

if (file_put_contents($tempPath, $patched, LOCK_EX) === false) {
    st2b6e_restore($backupPath, $targetPath);
    st2b6e_fail('cannot_write_temp_target');
}

[$tempLintCode, $tempLintOutput] = st2b6e_lint($tempPath);
st2b6e_out('php_l_candidate', $tempLintCode === 0 ? 'ok' : 'failed');

if ($tempLintCode !== 0) {
    @unlink($tempPath);
    st2b6e_restore($backupPath, $targetPath);
    st2b6e_fail('candidate_php_l_failed:' . $tempLintOutput);
}

if (!copy($tempPath, $targetPath)) {
    @unlink($tempPath);
    st2b6e_restore($backupPath, $targetPath);
    st2b6e_fail('cannot_replace_target');
}

@unlink($tempPath);

[$targetLintCode, $targetLintOutput] = st2b6e_lint($targetPath);
st2b6e_out('php_l_target', $targetLintCode === 0 ? 'ok' : 'failed');

if ($targetLintCode !== 0) {
    st2b6e_restore($backupPath, $targetPath);
    st2b6e_fail('target_php_l_failed:' . $targetLintOutput);
}

$final = st2b6e_read($targetPath, ST2B6E_TARGET);

if (!st2b6e_is_applied($final)) {
    st2b6e_restore($backupPath, $targetPath);
    st2b6e_fail('postwrite_invariants_failed');
}

st2b6e_out('changed', ST2B6E_TARGET);
st2b6e_out('server_render_order_write', 'blocked');
st2b6e_out('explicit_confirm_order_write', 'preserved');
st2b6e_out('done', 'ok');
@unlink(__FILE__);
