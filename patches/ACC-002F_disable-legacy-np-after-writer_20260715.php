<?php
declare(strict_types=1);

/*
 * ACC-002F — disable the legacy NP address after-writer — 2026-07-15
 *
 * Confirmed live sequence before this patch:
 * 1. catalog/controller/checkout/shipping_address.php validates the NP refs,
 *    persists bs_np_v1, and creates/reuses/updates the structured address;
 * 2. the Pinta after-event then calls createNovaPoshtaAddress(), creates a
 *    second legacy row without bs_np_v1, and overwrites the valid JSON output.
 *
 * This patch makes only that obsolete after-hook a no-op. It does not change
 * the module model, database schema, event rows, or existing customer data.
 */

$patch = 'ACC-002F_disable-legacy-np-after-writer_20260715';
$root = getcwd();
$dryRun = in_array('--dry-run', $argv ?? [], true);
$relative = 'extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php';
$marker = 'ACC-002F structured checkout controller is the sole NP address writer';
$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

function acc002f_fail(string $message): void {
	fwrite(STDERR, "error={$message}\n");
	exit(1);
}

function acc002f_lf(string $value): string {
	return str_replace(["\r\n", "\r"], "\n", $value);
}

function acc002f_eol(string $value): string {
	return str_contains($value, "\r\n") ? "\r\n" : "\n";
}

function acc002f_restore_eol(string $value, string $eol): string {
	return $eol === "\n" ? $value : str_replace("\n", $eol, $value);
}

function acc002f_php_l(string $target): bool {
	$output = [];
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $output, $code);
	return $code === 0;
}

if (!is_file($path)) {
	acc002f_fail("target_missing path={$relative}");
}

$raw = file_get_contents($path);

if ($raw === false) {
	acc002f_fail("read_failed path={$relative}");
}

if (str_contains($raw, $marker)) {
	echo "already_applied=yes patch={$patch}\n";
	exit(0);
}

$eol = acc002f_eol($raw);
$source = acc002f_lf($raw);

$find = <<<'FIND'
    public function alterRedirectShippingAddressSave(&$route, &$args, &$response)
    {
        if ($this->config->get('shipping_pinta_nova_poshta_status')) {
            if (isset($this->request->post['shipping_novaposhta_firstname'])) {
                $json = [];

                $this->load->model('extension/PintaNovaPoshtaCod/shipping/pinta_nova_poshta');
                // This can change values of \$this->request->post['shipping_address'] and \$this->request->post['address_id']
                $json = array_merge($json, $this->model_extension_PintaNovaPoshtaCod_shipping_pinta_nova_poshta->createNovaPoshtaAddress());

                if (empty($json['error'])) {
                    $this->load->language('checkout/shipping_address');
                    $json['success'] = $this->language->get('text_success');
                }

                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
            }
        }
    }
FIND;

$replace = <<<'REPLACE'
    public function alterRedirectShippingAddressSave(&$route, &$args, &$response)
    {
        // ACC-002F structured checkout controller is the sole NP address writer.
        // The stock save action already validates refs, persists bs_np_v1, and
        // returns the canonical address_id. The former after-hook repeated the
        // write through createNovaPoshtaAddress() and replaced that response
        // with a second legacy address, so it must not write or overwrite here.
        return;
    }
REPLACE;

$count = substr_count($source, $find);

if ($count !== 1) {
	acc002f_fail("anchor_count path={$relative} expected=1 actual={$count}");
}

$updated = str_replace($find, $replace, $source);

if (!str_contains($updated, $marker)) {
	acc002f_fail("postcheck_marker_missing path={$relative}");
}

if (substr_count($updated, '->createNovaPoshtaAddress()') !== 0) {
	acc002f_fail("postcheck_legacy_writer_still_referenced path={$relative}");
}

echo 'cwd=' . $root . ' time=' . date('c') . "\n";

if ($dryRun) {
	echo "dry_run=ok patch={$patch} files=1 php_l=deferred\n";
	exit(0);
}

$backup = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . date('Ymd_His');
$backupPath = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
$backupDir = dirname($backupPath);

if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
	acc002f_fail("backup_dir_failed path={$backupDir}");
}

if (!copy($path, $backupPath)) {
	acc002f_fail("backup_failed path={$relative}");
}

if (file_put_contents($path, acc002f_restore_eol($updated, $eol)) === false) {
	@copy($backupPath, $path);
	acc002f_fail("write_failed path={$relative} rollback=attempted");
}

if (!acc002f_php_l($path)) {
	@copy($backupPath, $path);
	acc002f_fail("php_l_failed path={$relative} rollback=ok backup={$backup}");
}

$written = file_get_contents($path);

if (
	$written === false ||
	!str_contains($written, $marker) ||
	str_contains($written, '->createNovaPoshtaAddress()')
) {
	@copy($backupPath, $path);
	acc002f_fail("postwrite_check_failed path={$relative} rollback=ok backup={$backup}");
}

echo "changed={$relative} php_l=ok\n";
echo "done=ok patch={$patch} files=1 backup={$backup}\n";
@unlink(__FILE__);
