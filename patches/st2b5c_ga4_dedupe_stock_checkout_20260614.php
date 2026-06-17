<?php
/**
 * ST-2b.5C: GA4 begin_checkout / purchase dedupe guards.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$patchId = 'st2b5c_ga4_dedupe_stock_checkout_20260614';
$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$backupDir = rtrim($root, "/\\") . '/_patch_backups/' . $patchId . '-' . date('Ymd-His');
$changed = [];
$alreadyApplied = true;

function bs5c_fail(string $message): void {
    fwrite(STDERR, "error=$message" . PHP_EOL);
    exit(1);
}

function bs5c_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . '/' . ltrim($relative, "/\\");
}

function bs5c_read(string $root, string $relative): string {
    $path = bs5c_path($root, $relative);
    if (!is_file($path)) {
        bs5c_fail("missing_file:$relative");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        bs5c_fail("read_failed:$relative");
    }
    return $content;
}

function bs5c_backup(string $root, string $relative, string $backupDir): void {
    $src = bs5c_path($root, $relative);
    $dst = rtrim($backupDir, "/\\") . '/' . $relative;
    $dir = dirname($dst);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        bs5c_fail("backup_mkdir_failed:$dir");
    }
    if (!copy($src, $dst)) {
        bs5c_fail("backup_failed:$relative");
    }
}

function bs5c_write(string $root, string $relative, string $content, string $backupDir, array &$changed, bool &$alreadyApplied): void {
    $current = bs5c_read($root, $relative);
    if ($current === $content) {
        return;
    }
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
        bs5c_fail("backup_root_mkdir_failed:$backupDir");
    }
    bs5c_backup($root, $relative, $backupDir);
    if (file_put_contents(bs5c_path($root, $relative), $content) === false) {
        bs5c_fail("write_failed:$relative");
    }
    $changed[] = $relative;
    $alreadyApplied = false;
}

function bs5c_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);
    if ($count !== 1) {
        bs5c_fail("anchor_count_failed:$label:$count");
    }
    return str_replace($search, $replace, $content);
}

function bs5c_patch_model(string $content): string {
    if (strpos($content, 'ST-2b.5C begin_checkout dedupe') !== false) {
        return $content;
    }

    $old = <<<'PHP'
            {{ text_message }}
            {% if ps_track_purchase and ps_purchase %}<script>ps_dataLayer.pushEventData('purchase', {{ ps_purchase }});</script>{% endif %}
            HTML
PHP;

    $new = <<<'PHP'
            {{ text_message }}
            {% if ps_track_purchase and ps_purchase %}<script>(function(payload){ var tx = payload && payload.ecommerce ? String(payload.ecommerce.transaction_id || '') : ''; var key = tx ? '__bsGa4PurchaseSent_' + tx : '__bsGa4PurchaseSent_no_tx'; if (!window[key]) { window[key] = true; ps_dataLayer.pushEventData('purchase', payload); } })({{ ps_purchase }});</script>{% endif %}
            HTML
PHP;

    $content = bs5c_replace_once($content, $old, $new, 'model_purchase_client_dedupe');

    $old = <<<'PHP'
            'replace' => '<h1>{{ heading_title }}</h1>
            {% if ps_track_begin_checkout and ps_begin_checkout %}<script>ps_dataLayer.pushEventData(\'begin_checkout\', {{ ps_begin_checkout }});</script>{% endif %}
            {% if ps_track_qualify_lead and ps_qualify_lead %}<script>ps_dataLayer.pushEventData(\'qualify_lead\', {{ ps_qualify_lead }});</script>{% endif %}'
PHP;

    $new = <<<'PHP'
            'replace' => '<h1>{{ heading_title }}</h1>
            {% if ps_track_begin_checkout and ps_begin_checkout %}<script>if (!window.__bsGa4BeginCheckoutSent) { window.__bsGa4BeginCheckoutSent = true; ps_dataLayer.pushEventData(\'begin_checkout\', {{ ps_begin_checkout }}); }</script>{% endif %}{# ST-2b.5C begin_checkout dedupe #}
            {% if ps_track_qualify_lead and ps_qualify_lead %}<script>ps_dataLayer.pushEventData(\'qualify_lead\', {{ ps_qualify_lead }});</script>{% endif %}'
PHP;

    return bs5c_replace_once($content, $old, $new, 'model_begin_checkout_client_dedupe');
}

function bs5c_patch_controller(string $content): string {
    if (strpos($content, 'ST-2b.5C: one-shot purchase dedupe') !== false) {
        return $content;
    }

    $old = <<<'PHP'
        $args['ps_track_purchase'] = $ps_config_track_purchase;

        $args['ps_purchase'] = isset($this->session->data['ps_purchase']) ? $this->session->data['ps_purchase'] : null;

        unset($this->session->data['ps_purchase']);
PHP;

    $new = <<<'PHP'
        $args['ps_track_purchase'] = $ps_config_track_purchase;

        // ST-2b.5C: one-shot purchase dedupe for reload-resilient checkout/success.
        $ps_purchase_payload = isset($this->session->data['ps_purchase']) ? $this->session->data['ps_purchase'] : null;

        if ($ps_purchase_payload) {
            $ps_purchase_decoded = json_decode((string)$ps_purchase_payload, true);
            $ps_purchase_order_id = (string)($ps_purchase_decoded['ecommerce']['transaction_id'] ?? '');
            $ps_purchase_key = $ps_purchase_order_id !== '' ? 'bs_ga4_purchase_sent_' . $ps_purchase_order_id : '';

            if ($ps_purchase_key !== '' && !empty($this->session->data[$ps_purchase_key])) {
                $ps_purchase_payload = null;
            } elseif ($ps_purchase_key !== '') {
                $this->session->data[$ps_purchase_key] = time();
            }
        }

        $args['ps_purchase'] = $ps_purchase_payload;

        unset($this->session->data['ps_purchase']);
PHP;

    return bs5c_replace_once($content, $old, $new, 'controller_purchase_session_dedupe');
}

$modelFile = 'extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
$controllerFile = 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php';

bs5c_read($root, $modelFile);
bs5c_read($root, $controllerFile);

$model = bs5c_patch_model(bs5c_read($root, $modelFile));
bs5c_write($root, $modelFile, $model, $backupDir, $changed, $alreadyApplied);

$controller = bs5c_patch_controller(bs5c_read($root, $controllerFile));
bs5c_write($root, $controllerFile, $controller, $backupDir, $changed, $alreadyApplied);

foreach ([$modelFile, $controllerFile] as $phpFile) {
    $lintOutput = [];
    $lintCode = 0;
    exec('php -l ' . escapeshellarg(bs5c_path($root, $phpFile)), $lintOutput, $lintCode);
    echo 'php_lint=' . $phpFile . ' exit=' . $lintCode . PHP_EOL;
    if ($lintOutput) {
        echo 'php_lint_output=' . implode(' | ', $lintOutput) . PHP_EOL;
    }
    if ($lintCode !== 0) {
        bs5c_fail('php_lint_failed:' . $phpFile);
    }
}

$modelFinal = bs5c_read($root, $modelFile);
$controllerFinal = bs5c_read($root, $controllerFile);

if (strpos($modelFinal, '__bsGa4BeginCheckoutSent') === false || strpos($modelFinal, '__bsGa4PurchaseSent_') === false) {
    bs5c_fail('postcheck_model_dedupe_missing');
}

if (strpos($controllerFinal, 'bs_ga4_purchase_sent_') === false) {
    bs5c_fail('postcheck_controller_dedupe_missing');
}

echo 'patch=' . $patchId . PHP_EOL;
echo 'cwd=' . getcwd() . PHP_EOL;
echo 'time=' . date('c') . PHP_EOL;
echo 'db_schema_changes=none' . PHP_EOL;
echo 'db_data_changes=none_by_patch; runtime sets session-only GA4 dedupe flag keyed by order_id' . PHP_EOL;
echo 'already_applied=' . ($alreadyApplied ? 'yes' : 'no') . PHP_EOL;
echo 'changed_files=' . count($changed) . PHP_EOL;
foreach ($changed as $file) {
    echo 'changed=' . $file . PHP_EOL;
}
if (!$alreadyApplied) {
    echo 'backup_dir=' . $backupDir . PHP_EOL;
}
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
