<?php
declare(strict_types=1);

/*
 * PAY-001 — allow explicit preorder products through normal checkout stock gates.
 *
 * Root cause:
 * - stock status 8 is "Передзамовлення", but Cart::hasStock() returns false for
 *   every insufficient-stock row;
 * - shipping/payment/confirm endpoints therefore redirect before normal payment
 *   providers are loaded.
 *
 * Scope:
 * - system/library/cart/cart.php
 * - no DB/settings/order/payment-provider changes
 *
 * Rollback:
 * restore the target from the printed _patch_backups directory.
 */

$patchId = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

$specs = [
    [
        'relative' => 'system/library/cart/cart.php',
        'old_sha256' => '9ae2146795e0e4e3e5126212cbec2bf237c07bb0fb4dad1896f165c08cc4f774',
        'new_sha256' => 'ce081c4f46d9cdbea72df547f942e7324f461f289f1c7c5aa2ada8ff3f6f9203',
        'lint_php' => true,
        'replacements' => [
            [
                'name' => 'preorder_aware_cart_stock_gate',
                'old' => <<<'PHP'
	public function hasStock(): bool {
		foreach ($this->getProducts() as $product) {
			if (!$product['stock_status']) {
				return false;
			}
		}

		return true;
	}
PHP,
                'new' => <<<'PHP'
	public function hasStock(): bool {
		// PAY-001-PREORDER-STOCK-GATE-20260725:
		// stock status 8 is the store's explicit preorder state. It must not
		// block checkout as an out-of-stock item; payment-specific credit
		// eligibility remains enforced separately at the payment boundary.
		$preorder_stock_status_id = 8;

		foreach ($this->getProducts() as $product) {
			$is_preorder = (int)($product['stock_status_id'] ?? 0) === $preorder_stock_status_id;

			if (!$product['stock_status'] && !$is_preorder) {
				return false;
			}
		}

		return true;
	}
PHP
            ]
        ]
    ]
];

function pay001PreorderFail(string $message, int $code = 1): never {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit($code);
}

function pay001PreorderLint(string $file): array {
    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    return [$code, implode(' | ', $output)];
}

[$selfLintCode, $selfLintOutput] = pay001PreorderLint(__FILE__);
if ($selfLintCode !== 0) {
    pay001PreorderFail('php_l_failed file=' . __FILE__ . ' output=' . $selfLintOutput);
}

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    pay001PreorderFail('run_from_opencart_root_config_missing');
}

$allNew = true;
$allOld = true;

foreach ($specs as $index => $spec) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec['relative']);

    if (!is_file($target)) {
        pay001PreorderFail('target_missing file=' . $spec['relative']);
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
            pay001PreorderFail(
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
        pay001PreorderFail('read_failed file=' . $spec['relative']);
    }

    foreach ($spec['replacements'] as $replacement) {
        $count = substr_count($source, $replacement['old']);

        if ($count !== 1) {
            pay001PreorderFail('anchor_count name=' . $replacement['name'] . ' expected=1 actual=' . $count);
        }

        $source = str_replace($replacement['old'], $replacement['new'], $source, $replaceCount);

        if ($replaceCount !== 1) {
            pay001PreorderFail('replace_count name=' . $replacement['name'] . ' expected=1 actual=' . $replaceCount);
        }
    }

    $generatedSha256 = hash('sha256', $source);

    if (!hash_equals($spec['new_sha256'], $generatedSha256)) {
        pay001PreorderFail(
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
        pay001PreorderFail('backup_dir_create_failed path=' . dirname($backup));
    }

    if (!copy($spec['target'], $backup)) {
        pay001PreorderFail('backup_failed file=' . $spec['relative']);
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
            [$lintCode, $lintOutput] = pay001PreorderLint($spec['target']);

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

    pay001PreorderFail($error->getMessage() . ' restored=yes');
}

foreach ($specs as $spec) {
    echo 'changed_file=' . $spec['relative'] . PHP_EOL;
}

echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
