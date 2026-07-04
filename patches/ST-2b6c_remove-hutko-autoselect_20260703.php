<?php
declare(strict_types=1);

/*
 * ST-2b.6 Phase 1: require an explicit payment-method click.
 * DB changes: none.
 * Diagnostics remain installed for post-deploy evidence.
 */

const BS6C_ID = 'ST-2b6c_remove-hutko-autoselect_20260703';

$targets = [
    'stock_payment' => [
        'path' => 'catalog/view/template/checkout/payment_method.twig',
        'sha' => '5b3fc6f9e3e6d075eec0e18ead3e9f0b98208c6cdd6d5621ae981d24420e031e',
        'marker' => 'ST-2b.6 Phase 1: explicit payment selection only.',
    ],
    'simple_checkout' => [
        'path' => 'extension/SimpleCheckout/catalog/view/template/module/checkout.twig',
        'sha' => '9fd90efcc822a86078fc3b3d5949ae7dc4d4484551a931d2c0d97982b61d6411',
        'marker' => 'ST-2b.6 Phase 1: SimpleCheckout explicit payment only.',
    ],
    'simple_payment' => [
        'path' => 'extension/SimpleCheckout/catalog/view/template/module/payment_method.twig',
        'sha' => '34d29391bd24a162a2d6cc653b92dcede919bc17331902612a06419a704d7168',
        'marker' => 'ST-2b.6 Phase 1: do not pre-check a payment method.',
    ],
    'simple_controller' => [
        'path' => 'extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php',
        'sha' => 'eb1e2209befedfb4ee4824cb2544378be1258ab20aa5d12cc21d96e075fb5d8e',
        'marker' => 'ST-2b.6 Phase 1: empty payment must remain invalid.',
    ],
];

function bs6c_out(string $key, string $value = ''): void {
    echo $key . ($value !== '' ? '=' . $value : '') . PHP_EOL;
}
function bs6c_fail(string $message): void {
    throw new RuntimeException($message);
}
function bs6c_path(string $relative): string {
    return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
function bs6c_read(string $relative): string {
    $path = bs6c_path($relative);
    if (!is_file($path)) bs6c_fail('missing_file:' . $relative);
    $content = file_get_contents($path);
    if ($content === false) bs6c_fail('read_failed:' . $relative);
    return $content;
}
function bs6c_replace(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);
    if ($count !== 1) bs6c_fail("anchor_count_mismatch:$label:expected=1:actual=$count");
    return str_replace($search, $replace, $content);
}
function bs6c_lint(string $file, string $label): void {
    if (!function_exists('exec')) bs6c_fail('php_lint_unavailable:exec_disabled');
    $out = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) bs6c_fail('php_lint_failed:' . $label . ':' . implode(' | ', $out));
    bs6c_out('php_l_' . $label, 'ok');
}
function bs6c_backup(string $relative, string $root): string {
    $backup = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true) && !is_dir(dirname($backup))) {
        bs6c_fail('backup_directory_failed:' . dirname($backup));
    }
    if (!copy(bs6c_path($relative), $backup)) bs6c_fail('backup_failed:' . $relative);
    bs6c_out('backup', $backup);
    return $backup;
}
function bs6c_write(string $relative, string $content): void {
    $target = bs6c_path($relative);
    $temp = $target . '.st2b6c-tmp-' . getmypid();
    if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) {
        @unlink($temp);
        bs6c_fail('temporary_write_failed:' . $relative);
    }
    if (!@rename($temp, $target)) {
        $written = file_put_contents($target, $content, LOCK_EX);
        @unlink($temp);
        if ($written !== strlen($content)) bs6c_fail('target_write_failed:' . $relative);
    }
}

$original = [];
$patched = [];
$backups = [];

try {
    bs6c_out('patch', BS6C_ID);
    bs6c_out('cwd', __DIR__);
    bs6c_out('time', gmdate('c'));
    bs6c_out('scope', 'remove payment auto-select/auto-save only');
    bs6c_out('db_schema_changes', 'none');
    bs6c_out('db_data_changes', 'none');

    $markers = 0;
    foreach ($targets as $key => $meta) {
        $original[$key] = bs6c_read($meta['path']);
        if (strpos($original[$key], $meta['marker']) !== false) $markers++;
    }
    if ($markers === count($targets)) {
        bs6c_out('already_applied', 'yes');
        bs6c_out('changed_files', 'none');
        bs6c_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }
    if ($markers !== 0) bs6c_fail('partial_phase1_markers_found:' . $markers);

    foreach ($targets as $key => $meta) {
        $actual = hash('sha256', $original[$key]);
        if (!hash_equals($meta['sha'], $actual)) {
            bs6c_fail("live_sha256_mismatch:$key:expected={$meta['sha']}:actual=$actual");
        }
    }
    bs6c_lint(__FILE__, 'patch');

    $patched['stock_payment'] = bs6c_replace(
        $original['stock_payment'],
        "  var bsPaymentPendingChoice = 'hutko';\n",
        "  // ST-2b.6 Phase 1: explicit payment selection only.\n  var bsPaymentPendingChoice = '';\n",
        'stock_default'
    );
    $patched['stock_payment'] = bs6c_replace(
        $patched['stock_payment'],
        "    return choice || 'hutko';\n",
        "    return choice || '';\n",
        'stock_normalize_fallback'
    );
    $patched['stock_payment'] = bs6c_replace(
        $patched['stock_payment'],
        "    if (!selected) {\n" .
        "      selected = findPaymentOption(options, bsPaymentPendingChoice) || findPaymentOption(options, 'hutko') || options[0] || null;\n" .
        "      bsSt2b6bPaymentLog('phase0b:new:default-payment-candidate', null, {\n" .
        "        currentPaymentCode: current || '', pendingChoice: bsPaymentPendingChoice || '', selectedCode: selected ? selected.code : '', optionCodes: \$.map(options, function(option) { return option.code || ''; })\n" .
        "      });\n" .
        "    }\n",
        "    if (!selected && window.bsSt2b6Log) {\n" .
        "      window.bsSt2b6Log('phase1:new:payment-awaiting-explicit-click', null, null, { currentPaymentCode: current || '', optionCodes: \$.map(options, function(option) { return option.code || ''; }) });\n" .
        "    }\n",
        'stock_selection_fallback'
    );
    $patched['stock_payment'] = bs6c_replace(
        $patched['stock_payment'],
        "    if (selected && !current) {\n" .
        "      bsSt2b6bPaymentLog('phase0b:new:auto-save-payment', null, { oldPaymentCode: '', newPaymentCode: selected.code || '', pendingChoice: bsPaymentPendingChoice || '' });\n" .
        "      savePayment(selected.code, selected.label, true, null);\n" .
        "      return;\n" .
        "    }\n\n",
        '',
        'stock_auto_save'
    );

    $patched['simple_checkout'] = bs6c_replace(
        $original['simple_checkout'],
        "function selectPreferredPaymentMethod() {\n" .
        "    var \$methods = \$('.payment-method input[name=\"payment_method\"]');\n\n" .
        "    if (!\$methods.length || \$methods.filter(':checked').length) {\n" .
        "        return;\n" .
        "    }\n\n" .
        "    var \$preferred = \$methods.filter('[value^=\"hutko\"]');\n" .
        "    window.bsSt2b6Log('phase0b:simple:select-preferred-payment', null, null, { oldPaymentCode: '', preferredPrefix: 'hutko', candidateCode: \$preferred.length ? String(\$preferred.first().val() || '') : '' });\n\n" .
        "    if (!\$preferred.length) {\n" .
        "        \$preferred = \$methods.first();\n" .
        "    }\n\n" .
        "    \$preferred.first().prop('checked', true);\n" .
        "}\n",
        "function selectPreferredPaymentMethod() {\n" .
        "    // ST-2b.6 Phase 1: SimpleCheckout explicit payment only.\n" .
        "    return \$('.payment-method input[name=\"payment_method\"]:checked').first();\n" .
        "}\n",
        'simple_preferred_function'
    );
    $patched['simple_checkout'] = bs6c_replace(
        $patched['simple_checkout'],
        "    ensureCheckoutMethodSelection('shipping_method', '');\n" .
        "    ensureCheckoutMethodSelection('payment_method', 'hutko');\n" .
        "    window.bsSt2b6Log('phase0b:simple:build-request:after', null, null, { selectedPaymentAfter: String(\$('input[name=\"payment_method\"]:checked').first().val() || '') });\n",
        "    ensureCheckoutMethodSelection('shipping_method', '');\n" .
        "    window.bsSt2b6Log('phase1:simple:payment-requires-click', null, null, { selectedPaymentAfter: String(\$('input[name=\"payment_method\"]:checked').first().val() || '') });\n",
        'simple_build_request'
    );
    $patched['simple_checkout'] = bs6c_replace(
        $patched['simple_checkout'],
        "                {% if payment_method['code'] == code or (not code) %}\r\n" .
        "                    {% set code = payment_method['code'] %}\r\n",
        "                {# ST-2b.6 Phase 1 inline payment: no pre-check. #}\r\n" .
        "                {% if code and payment_method['code'] == code %}\r\n",
        'simple_inline_payment_precheck'
    );

    $patched['simple_payment'] = bs6c_replace(
        $original['simple_payment'],
        "                {% if payment_method['code'] == code or (not code) %}\r\n" .
        "                    {% set code = payment_method['code'] %}\r\n",
        "                {# ST-2b.6 Phase 1: do not pre-check a payment method. #}\r\n" .
        "                {% if code and payment_method['code'] == code %}\r\n",
        'simple_template_precheck'
    );

    $patched['simple_controller'] = bs6c_replace(
        $original['simple_controller'],
        "        if (\$selected_code === '') {\n" .
        "            if (isset(\$payment_methods['hutko']) && is_array(\$payment_methods['hutko'])) {\n" .
        "                return \$this->normalizeSimpleCheckoutPaymentMethod(\$payment_methods['hutko'], 'hutko.hutko', 'hutko');\n" .
        "            }\n\n" .
        "            \$first_key = array_key_first(\$payment_methods);\n\n" .
        "            if (\$first_key !== null && is_array(\$payment_methods[\$first_key])) {\n" .
        "                return \$this->normalizeSimpleCheckoutPaymentMethod(\$payment_methods[\$first_key], '', (string)\$first_key);\n" .
        "            }\n" .
        "        }\n",
        "        // ST-2b.6 Phase 1: empty payment must remain invalid.\n" .
        "        if (\$selected_code === '') {\n" .
        "            return array();\n" .
        "        }\n",
        'simple_controller_fallback'
    );

    foreach ($targets as $key => $meta) {
        if (substr_count($patched[$key], $meta['marker']) !== 1) {
            bs6c_fail('marker_check_failed:' . $key);
        }
    }
    if (strpos($patched['stock_payment'], 'savePayment(selected.code, selected.label, true') !== false) {
        bs6c_fail('stock_auto_save_still_present');
    }
    if (strpos($patched['simple_checkout'], "ensureCheckoutMethodSelection('payment_method'") !== false) {
        bs6c_fail('simple_auto_select_still_present');
    }
    if (strpos($patched['simple_checkout'], "payment_method['code'] == code or (not code)") !== false) {
        bs6c_fail('simple_inline_precheck_still_present');
    }

    $backupRoot = __DIR__ . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . BS6C_ID . '-' . date('Ymd-His');
    foreach ($targets as $key => $meta) $backups[$key] = bs6c_backup($meta['path'], $backupRoot);
    foreach ($targets as $key => $meta) bs6c_write($meta['path'], $patched[$key]);

    bs6c_lint(bs6c_path($targets['simple_controller']['path']), 'simple_controller');
    foreach ($targets as $key => $meta) {
        if (substr_count(bs6c_read($meta['path']), $meta['marker']) !== 1) {
            bs6c_fail('written_marker_failed:' . $key);
        }
    }

    bs6c_out('changed_files', implode(',', array_column($targets, 'path')));
    bs6c_out('address_refresh_logic', 'unchanged');
    bs6c_out('diagnostics', 'preserved');
    bs6c_out('rollback', 'restore_printed_backups_then_clear_template_cache');
    bs6c_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    bs6c_out('error', $error->getMessage());
    if ($backups) {
        $ok = true;
        foreach ($backups as $key => $backup) {
            if (!copy($backup, bs6c_path($targets[$key]['path']))) $ok = false;
        }
        bs6c_out('restore_on_fail', $ok ? 'ok' : 'failed');
    } else {
        bs6c_out('restore_on_fail', 'not_needed');
    }
    bs6c_out('done', 'failed');
    exit(1);
}
