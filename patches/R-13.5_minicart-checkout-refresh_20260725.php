<?php
declare(strict_types=1);

/*
 * R-13.5 — refresh stock checkout after mini-cart mutations.
 *
 * Scope:
 * - catalog/view/template/common/cart.twig
 * - catalog/view/javascript/checkout-state.js
 * - catalog/view/template/checkout/checkout.twig
 * - no DB/settings/order/payment writes
 *
 * Rollback:
 * restore all files from the printed _patch_backups directory, then clear
 * OpenCart cache files with the owner command supplied with this patch.
 */

$patchId = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

$specs = [
    [
        'relative' => 'catalog/view/template/common/cart.twig',
        'old_sha256' => '6bf1eca863aacc847e401ab1c0114d8a2d6b26eb37a318c020404cc88d41a076',
        'new_sha256' => '9b2a8c326604c64ddfe29b8e289712690474cfc2dea59f1de931947d78aa1fa1',
        'replacements' => [
            [
                'name' => 'emit_cart_event_after_fragments',
                'old' => <<<'TWIG'
function reloadMiniCartFragments() {
    $('#cart').load('index.php?route=common/cart.info&language={{ language }}', function() {
        $('#shopping-cart').load('index.php?route=checkout/cart.list&language={{ language }}');
    });
}
TWIG,
                'new' => <<<'TWIG'
function reloadMiniCartFragments(cartChanged) {
    $('#cart').load('index.php?route=common/cart.info&language={{ language }}', function() {
        $('#shopping-cart').load('index.php?route=checkout/cart.list&language={{ language }}');

        if (cartChanged) {
            $(document).trigger('bs:cart-updated');
        }
    });
}
TWIG
            ],
            [
                'name' => 'quantity_success_event',
                'old' => <<<'TWIG'
            input.data('last-known-value', value);
            reloadMiniCartFragments();
        },
        error: function(xhr, ajaxOptions, thrownError) {
            reloadMiniCartFragments();
TWIG,
                'new' => <<<'TWIG'
            input.data('last-known-value', value);
            reloadMiniCartFragments(true);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            reloadMiniCartFragments(false);
TWIG
            ],
            [
                'name' => 'remove_success_event',
                'old' => <<<'TWIG'
            reloadMiniCartFragments();
        },
        error: function(xhr, ajaxOptions, thrownError) {
            reloadMiniCartFragments();

            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
TWIG,
                'new' => <<<'TWIG'
            reloadMiniCartFragments(true);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            reloadMiniCartFragments(false);

            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
TWIG
            ]
        ]
    ],
    [
        'relative' => 'catalog/view/javascript/checkout-state.js',
        'old_sha256' => '955a61f420d1d3a99fbfebabee362acf344c4d6df7998be487248ce61c172252',
        'new_sha256' => '23378071030674f5ccdd34b039cde51cd2d5c40acc07ed2012bd2bb67a49d025',
        'replacements' => [
            [
                'name' => 'cart_changed_transition',
                'old' => <<<'JS'
    return null;
  }

  function shippingSaved(code, label, candidateRevision, summaryHtml) {
JS,
                'new' => <<<'JS'
    return null;
  }

  function cartChanged() {
    return addressSaved({ quietAddressError: true });
  }

  function shippingSaved(code, label, candidateRevision, summaryHtml) {
JS
            ],
            [
                'name' => 'export_cart_changed',
                'old' => <<<'JS'
    isCurrent: isCurrent,
    addressSaved: addressSaved,
    beginShippingSelection: beginShippingSelection,
JS,
                'new' => <<<'JS'
    isCurrent: isCurrent,
    addressSaved: addressSaved,
    cartChanged: cartChanged,
    beginShippingSelection: beginShippingSelection,
JS
            ],
            [
                'name' => 'listen_for_cart_event',
                'old' => <<<'JS'
  };

  $(bootstrap);
})(jQuery);
JS,
                'new' => <<<'JS'
  };

  $(document).off('bs:cart-updated.checkoutState').on('bs:cart-updated.checkoutState', function () {
    window.bsCheckoutState.cartChanged();
  });

  $(bootstrap);
})(jQuery);
JS
            ]
        ]
    ],
    [
        'relative' => 'catalog/view/template/checkout/checkout.twig',
        'old_sha256' => '476d096c2b4e31e3bf18dcc4f91b8e311219d044d2d6b0695920cf9d41cdf73f',
        'new_sha256' => '563697ba196c97f14984a9fa2f82fb9cc2ab5c4847cdb4262d0432ead03709c9',
        'replacements' => [
            [
                'name' => 'checkout_asset_version',
                'old' => <<<'TWIG'
{# PAY-001-PHASE2C-D2-COUPON-TOTALS-20260725: bust both changed checkout JS assets. #}
<script src="catalog/view/javascript/checkout-state.js?v=pay001-phase2c-d2-20260725"></script>
<script src="catalog/view/javascript/checkout-reskin.js?v=pay001-phase2c-d2-20260725"></script>
TWIG,
                'new' => <<<'TWIG'
{# R-13.5: bust checkout state/summary assets after mini-cart and shipping fallback fixes. #}
<script src="catalog/view/javascript/checkout-state.js?v=r135-cart-refresh-20260725"></script>
<script src="catalog/view/javascript/checkout-reskin.js?v=r135-cart-refresh-20260725"></script>
TWIG
            ]
        ]
    ]
];

function r135CartFail(string $message, int $code = 1): never {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit($code);
}

function r135CartLint(string $file): array {
    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    return [$code, implode(' | ', $output)];
}

[$selfLintCode, $selfLintOutput] = r135CartLint(__FILE__);
if ($selfLintCode !== 0) {
    r135CartFail('php_l_failed file=' . __FILE__ . ' output=' . $selfLintOutput);
}

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    r135CartFail('run_from_opencart_root_config_missing');
}

$allNew = true;
$allOld = true;

foreach ($specs as $index => $spec) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec['relative']);

    if (!is_file($target)) {
        r135CartFail('target_missing file=' . $spec['relative']);
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
            r135CartFail(
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
        r135CartFail('read_failed file=' . $spec['relative']);
    }

    foreach ($spec['replacements'] as $replacement) {
        $count = substr_count($source, $replacement['old']);

        if ($count !== 1) {
            r135CartFail('anchor_count name=' . $replacement['name'] . ' expected=1 actual=' . $count);
        }

        $source = str_replace($replacement['old'], $replacement['new'], $source, $replaceCount);

        if ($replaceCount !== 1) {
            r135CartFail('replace_count name=' . $replacement['name'] . ' expected=1 actual=' . $replaceCount);
        }
    }

    $generatedSha256 = hash('sha256', $source);

    if (!hash_equals($spec['new_sha256'], $generatedSha256)) {
        r135CartFail(
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
        r135CartFail('backup_dir_create_failed path=' . dirname($backup));
    }

    if (!copy($spec['target'], $backup)) {
        r135CartFail('backup_failed file=' . $spec['relative']);
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
    }
} catch (Throwable $error) {
    foreach ($written as $index) {
        @copy($specs[$index]['backup'], $specs[$index]['target']);

        if ($specs[$index]['permissions'] !== false) {
            @chmod($specs[$index]['target'], $specs[$index]['permissions'] & 0777);
        }
    }

    r135CartFail($error->getMessage() . ' restored=yes');
}

foreach ($specs as $spec) {
    echo 'changed_file=' . $spec['relative'] . PHP_EOL;
}

echo 'php_l=ok file=' . basename(__FILE__) . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);
