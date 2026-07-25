<?php
declare(strict_types=1);

/*
 * R-13.5 — explicit Nova Poshta display fallback.
 *
 * Scope:
 * - extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
 * - catalog/view/javascript/checkout-reskin.js
 * - no DB/settings/order/payment writes
 *
 * Rollback:
 * restore both files from the printed _patch_backups directory, then clear
 * OpenCart cache files with the owner command supplied with this patch.
 */

$patchId = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

$specs = [
    [
        'relative' => 'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php',
        'old_sha256' => '75066e88678d6ba3d887ba056e8a027a32c39bd4f2cc8f4ead23e535657d2b9b',
        'new_sha256' => '766c70e4963541ab10585c783cd5dbc52e7070cd95fed973bec36d7194625d34',
        'lint' => true,
        'replacements' => [
            [
                'name' => 'np_display_fallback',
                'old' => <<<'PHP'
        if (!is_numeric($shipping_cost_session_currency) || (float)$shipping_cost_session_currency <= 0) {
            return '';
        }
PHP,
                'new' => <<<'PHP'
        if (!is_numeric($shipping_cost_session_currency) || (float)$shipping_cost_session_currency <= 0) {
            return 'За тарифами Нової пошти';
        }
PHP
            ]
        ]
    ],
    [
        'relative' => 'catalog/view/javascript/checkout-reskin.js',
        'old_sha256' => '7585847b4614ed4cf10281a7c78444ae7dbf847969eb6a9caf92173d097deb8b',
        'new_sha256' => '93a0fa23d6b403be9312e698550913e17f4ef29973f4abe8f248b3a2d6179f0f',
        'lint' => false,
        'replacements' => [
            [
                'name' => 'checkout_summary_fallback',
                'old' => <<<'JS'
    var shippingPrice = free
      ? 'За наш кошт'
      : (shippingDisplayText ? escapeHtml(shippingDisplayText) : '—');
JS,
                'new' => <<<'JS'
    var shippingPrice = free
      ? 'За наш кошт'
      : (shippingDisplayText ? escapeHtml(shippingDisplayText) : 'За тарифами Нової пошти');
JS
            ]
        ]
    ]
];

function r135ShippingFail(string $message, int $code = 1): never {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit($code);
}

function r135ShippingLint(string $file): array {
    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    return [$code, implode(' | ', $output)];
}

[$selfLintCode, $selfLintOutput] = r135ShippingLint(__FILE__);
if ($selfLintCode !== 0) {
    r135ShippingFail('php_l_failed file=' . __FILE__ . ' output=' . $selfLintOutput);
}

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    r135ShippingFail('run_from_opencart_root_config_missing');
}

$allNew = true;
$allOld = true;

foreach ($specs as $index => $spec) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec['relative']);

    if (!is_file($target)) {
        r135ShippingFail('target_missing file=' . $spec['relative']);
    }

    $currentSha256 = hash_file('sha256', $target);
    $specs[$index]['target'] = $target;
    $specs[$index]['current_sha256'] = $currentSha256;
    $allNew = $allNew && hash_equals($spec['new_sha256'], $currentSha256);
    $allOld = $allOld && hash_equals($spec['old_sha256'], $currentSha256);
}

echo 'cwd=' . $root . PHP_EOL;
echo 'time=' . date(DATE_ATOM) . PHP_EOL;

if ($allNew) {
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}

if (!$allOld) {
    foreach ($specs as $spec) {
        if (!hash_equals($spec['old_sha256'], $spec['current_sha256'])) {
            r135ShippingFail(
                'source_sha256_mismatch file=' . $spec['relative'] .
                ' expected=' . $spec['old_sha256'] .
                ' actual=' . $spec['current_sha256']
            );
        }
    }
}

foreach ($specs as $index => $spec) {
    $source = file_get_contents($spec['target']);

    if ($source === false) {
        r135ShippingFail('read_failed file=' . $spec['relative']);
    }

    foreach ($spec['replacements'] as $replacement) {
        $count = substr_count($source, $replacement['old']);

        if ($count !== 1) {
            r135ShippingFail('anchor_count name=' . $replacement['name'] . ' expected=1 actual=' . $count);
        }

        $source = str_replace($replacement['old'], $replacement['new'], $source, $replaceCount);

        if ($replaceCount !== 1) {
            r135ShippingFail('replace_count name=' . $replacement['name'] . ' expected=1 actual=' . $replaceCount);
        }
    }

    $generatedSha256 = hash('sha256', $source);

    if (!hash_equals($spec['new_sha256'], $generatedSha256)) {
        r135ShippingFail(
            'generated_sha256_mismatch file=' . $spec['relative'] .
            ' expected=' . $spec['new_sha256'] .
            ' actual=' . $generatedSha256
        );
    }

    $specs[$index]['patched'] = $source;
}

$timestamp = date('Ymd-His');
$backupRoot = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patchId . '-' . $timestamp;

foreach ($specs as $index => $spec) {
    $backup = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec['relative']);

    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true) && !is_dir(dirname($backup))) {
        r135ShippingFail('backup_dir_create_failed path=' . dirname($backup));
    }

    if (!copy($spec['target'], $backup)) {
        r135ShippingFail('backup_failed file=' . $spec['relative']);
    }

    $specs[$index]['backup'] = $backup;
    $specs[$index]['permissions'] = fileperms($spec['target']);
}

echo 'backup=' . $backupRoot . PHP_EOL;
$written = [];

try {
    foreach ($specs as $index => $spec) {
        if (file_put_contents($spec['target'], $spec['patched'], LOCK_EX) !== strlen($spec['patched'])) {
            throw new RuntimeException('write_failed file=' . $spec['relative']);
        }

        $written[] = $index;

        if ($spec['permissions'] !== false) {
            @chmod($spec['target'], $spec['permissions'] & 0777);
        }

        if (!hash_equals($spec['new_sha256'], hash_file('sha256', $spec['target']))) {
            throw new RuntimeException('post_write_sha256_mismatch file=' . $spec['relative']);
        }

        if ($spec['lint']) {
            [$lintCode, $lintOutput] = r135ShippingLint($spec['target']);

            if ($lintCode !== 0) {
                throw new RuntimeException('php_l_failed file=' . $spec['relative'] . ' output=' . $lintOutput);
            }
        }
    }
} catch (Throwable $error) {
    foreach ($written as $index) {
        @copy($specs[$index]['backup'], $specs[$index]['target']);

        if ($specs[$index]['permissions'] !== false) {
            @chmod($specs[$index]['target'], $specs[$index]['permissions'] & 0777);
        }
    }

    r135ShippingFail($error->getMessage() . ' restored=yes');
}

foreach ($specs as $spec) {
    echo 'changed_file=' . $spec['relative'] . PHP_EOL;
}

echo 'php_l=ok files=patch,target_php' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
