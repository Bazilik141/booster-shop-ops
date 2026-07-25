<?php
declare(strict_types=1);

/*
 * PAY-001 — include the existing cart subtotal in product-page credit eligibility.
 *
 * Scope:
 * - catalog/controller/product/product.php
 * - catalog/view/template/product/product.twig
 * - display/advisory eligibility only; checkout remains authoritative
 * - no DB/settings/order/payment-provider changes
 *
 * Rollback:
 * restore both targets from the printed _patch_backups directory, then clear
 * OpenCart cache files with the owner command supplied with this patch.
 */

$patchId = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

$specs = [
    [
        'relative' => 'catalog/controller/product/product.php',
        'old_sha256' => '734960a28c50ed9fb1a28a72d7df64a71779cc803901461911b570147005a1d2',
        'new_sha256' => '4c33564176c8363dc1156861a1bfd9eb3647ffd62103c20c7314b420fda232c0',
        'lint_php' => true,
        'replacements' => [
            [
                'name' => 'expose_current_cart_subtotal',
                'old' => <<<'PHP'
			$data['pay001_mono_chast_price'] = round($pay001_mono_price, 2);
			$data['pay001_mono_chast_min_total'] = max(500.0, (float)$this->config->get('payment_mono_chast_min_total'));
			$data['pay001_mono_chast_in_stock'] = (int)$product_info['quantity'] > 0;
PHP,
                'new' => <<<'PHP'
			$data['pay001_mono_chast_price'] = round($pay001_mono_price, 2);
			$data['pay001_mono_chast_min_total'] = max(500.0, (float)$this->config->get('payment_mono_chast_min_total'));
			$data['pay001_mono_chast_cart_total'] = round((float)$this->cart->getSubTotal(), 2);
			$data['pay001_mono_chast_in_stock'] = (int)$product_info['quantity'] > 0;
PHP
            ]
        ]
    ],
    [
        'relative' => 'catalog/view/template/product/product.twig',
        'old_sha256' => '62ca17b0a622163d52c3d50d5ba92d2132c4502ad014e2348621cf914dd2e3ff',
        'new_sha256' => '30a04b13705202a5cf69a80dc7b907de8a22366fc6f2d6fce317328ea44740c3',
        'lint_php' => false,
        'replacements' => [
            [
                'name' => 'cart_total_data_attribute',
                'old' => <<<'TWIG'
                <div data-pay001-product-credit data-pay001-price="{{ pay001_mono_chast_price }}" data-pay001-threshold="{{ pay001_mono_chast_min_total }}" data-pay001-stock="{{ pay001_mono_chast_in_stock ? '1' : '0' }}">
TWIG,
                'new' => <<<'TWIG'
                <div data-pay001-product-credit data-pay001-price="{{ pay001_mono_chast_price }}" data-pay001-threshold="{{ pay001_mono_chast_min_total }}" data-pay001-cart-total="{{ pay001_mono_chast_cart_total }}" data-pay001-stock="{{ pay001_mono_chast_in_stock ? '1' : '0' }}">
TWIG
            ],
            [
                'name' => 'read_cart_total',
                'old' => <<<'TWIG'
  var price = Number(creditRoot.dataset.pay001Price) || 0;
  var threshold = Number(creditRoot.dataset.pay001Threshold) || 500;
  var inStock = creditRoot.dataset.pay001Stock === '1';
TWIG,
                'new' => <<<'TWIG'
  var price = Number(creditRoot.dataset.pay001Price) || 0;
  var threshold = Number(creditRoot.dataset.pay001Threshold) || 500;
  var cartTotal = Number(creditRoot.dataset.pay001CartTotal) || 0;
  var inStock = creditRoot.dataset.pay001Stock === '1';
TWIG
            ],
            [
                'name' => 'combined_eligibility_total',
                'old' => <<<'TWIG'
  function quantity() { return Math.max(1, parseInt(quantityInput.value, 10) || 1); }
  function total() { return price * quantity(); }
  function renderProductState() {
    var available = inStock && total() >= threshold;
TWIG,
                'new' => <<<'TWIG'
  function quantity() { return Math.max(1, parseInt(quantityInput.value, 10) || 1); }
  function total() { return price * quantity(); }
  function eligibilityTotal() { return cartTotal + total(); }
  function renderProductState() {
    var available = inStock && eligibilityTotal() >= threshold;
TWIG
            ],
            [
                'name' => 'combined_remaining_hint',
                'old' => <<<'TWIG'
      hint.textContent = 'Оплата частинами доступна від ' + money(threshold) + ' — додайте ще ' + money(threshold - total()) + '.';
TWIG,
                'new' => <<<'TWIG'
      hint.textContent = 'Оплата частинами доступна від ' + money(threshold) + ' — додайте ще ' + money(Math.max(0, threshold - eligibilityTotal())) + '.';
TWIG
            ]
        ]
    ]
];

function pay001ThresholdFail(string $message, int $code = 1): never {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit($code);
}

function pay001ThresholdLint(string $file): array {
    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    return [$code, implode(' | ', $output)];
}

[$selfLintCode, $selfLintOutput] = pay001ThresholdLint(__FILE__);
if ($selfLintCode !== 0) {
    pay001ThresholdFail('php_l_failed file=' . __FILE__ . ' output=' . $selfLintOutput);
}

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    pay001ThresholdFail('run_from_opencart_root_config_missing');
}

$allNew = true;
$allOld = true;

foreach ($specs as $index => $spec) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec['relative']);

    if (!is_file($target)) {
        pay001ThresholdFail('target_missing file=' . $spec['relative']);
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
            pay001ThresholdFail(
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
        pay001ThresholdFail('read_failed file=' . $spec['relative']);
    }

    foreach ($spec['replacements'] as $replacement) {
        $count = substr_count($source, $replacement['old']);

        if ($count !== 1) {
            pay001ThresholdFail('anchor_count name=' . $replacement['name'] . ' expected=1 actual=' . $count);
        }

        $source = str_replace($replacement['old'], $replacement['new'], $source, $replaceCount);

        if ($replaceCount !== 1) {
            pay001ThresholdFail('replace_count name=' . $replacement['name'] . ' expected=1 actual=' . $replaceCount);
        }
    }

    $generatedSha256 = hash('sha256', $source);

    if (!hash_equals($spec['new_sha256'], $generatedSha256)) {
        pay001ThresholdFail(
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
        pay001ThresholdFail('backup_dir_create_failed path=' . dirname($backup));
    }

    if (!copy($spec['target'], $backup)) {
        pay001ThresholdFail('backup_failed file=' . $spec['relative']);
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

        if (!empty($spec['lint_php'])) {
            [$lintCode, $lintOutput] = pay001ThresholdLint($spec['target']);

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

    pay001ThresholdFail($error->getMessage() . ' restored=yes');
}

foreach ($specs as $spec) {
    echo 'changed_file=' . $spec['relative'] . PHP_EOL;
}

echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
