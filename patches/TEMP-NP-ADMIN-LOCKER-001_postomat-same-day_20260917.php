<?php
/**
 * TEMP-NP-ADMIN-LOCKER-001 — use today's shipping date for sender parcel lockers.
 *
 * Run from the OpenCart web root:
 *   php TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917.php
 *
 * Scope:
 * - identifies the configured sender point by its saved Nova Poshta UUID;
 * - detects the parcel-locker warehouse type in the local Nova Poshta directory;
 * - forces DateTime to the current server date only for parcel-locker senders;
 * - leaves ordinary branches and address senders on the existing date setting.
 *
 * No database writes are performed by this runner.
 *
 * Rollback: restore the two files from the backup directory printed by the run.
 */

const TEMP_NP_DATE_PATCH = 'TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917';
const TEMP_NP_DATE_MARKER = 'TEMP-NP-ADMIN-LOCKER-001-SAME-DAY';

function temp_np_date_out($key, $value)
{
    echo $key . '=' . $value . PHP_EOL;
}

function temp_np_date_fail($message)
{
    throw new RuntimeException($message);
}

function temp_np_date_with_eol($text, $content)
{
    $eol = strpos($content, "\r\n") !== false ? "\r\n" : "\n";
    return str_replace("\n", $eol, $text);
}

function temp_np_date_replace_once($content, $old, $new, $label)
{
    $old = temp_np_date_with_eol($old, $content);
    $new = temp_np_date_with_eol($new, $content);
    $count = substr_count($content, $old);

    if ($count !== 1) {
        temp_np_date_fail('anchor_count[' . $label . ']=' . $count . ';expected=1');
    }

    return str_replace($old, $new, $content);
}

function temp_np_date_write($path, $content)
{
    $written = file_put_contents($path, $content, LOCK_EX);
    if ($written === false || $written !== strlen($content)) {
        temp_np_date_fail('write_failed=' . $path);
    }
}

function temp_np_date_lint($path)
{
    $output = array();
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        temp_np_date_fail('php_lint_failed=' . $path . ';output=' . implode(' | ', $output));
    }

    temp_np_date_out('php_lint[' . $path . ']', 'ok');
}

$root = getcwd();
$relative_files = array(
    'extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php',
    'extension/PintaNovaPoshtaCod/admin/model/module/warehouse.php',
);
$original = array();
$updated = array();
$marker_files = 0;
$backup_dir = '';
$written_files = array();

try {
    temp_np_date_out('patch', TEMP_NP_DATE_PATCH);
    temp_np_date_out('cwd', $root);
    temp_np_date_out('time_utc', gmdate('c'));

    foreach ($relative_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            temp_np_date_fail('missing_file=' . $relative);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            temp_np_date_fail('read_failed=' . $relative);
        }

        $original[$relative] = $content;
        $marker_count = substr_count($content, TEMP_NP_DATE_MARKER);
        if ($marker_count > 1) {
            temp_np_date_fail('marker_count[' . $relative . ']=' . $marker_count . ';expected=0_or_1');
        }
        if ($marker_count === 1) {
            $marker_files++;
        }
    }

    if ($marker_files === count($relative_files)) {
        temp_np_date_out('already_applied', 'yes');
        temp_np_date_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if ($marker_files !== 0) {
        temp_np_date_fail('partial_state_marker_files=' . $marker_files . ';expected=0_or_' . count($relative_files));
    }

    $relative = 'extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php';
    $content = $original[$relative];
    $content = temp_np_date_replace_once(
        $content,
        <<<'OLD'
class InternetDocument extends Controller
{
    use \PintaValidateApiResultTrait;
OLD,
        <<<'NEW'
class InternetDocument extends Controller
{
    use \PintaValidateApiResultTrait;

    // TEMP-NP-ADMIN-LOCKER-001-SAME-DAY: Nova Poshta requires today's DateTime for sender parcel lockers.
    private const POSTOMAT_TYPE_REF = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';
NEW,
        'controller_postomat_type'
    );
    $content = temp_np_date_replace_once(
        $content,
        <<<'OLD'
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $formdata = $this->request->post;
            $formdata = $this->trimFormdata($formdata);
            $this->validateFormdata($formdata);
OLD,
        <<<'NEW'
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $formdata = $this->request->post;
            $formdata = $this->trimFormdata($formdata);
            $formdata = $this->normaliseSenderPostomatShippingDate($formdata);
            $this->validateFormdata($formdata);
NEW,
        'controller_post_normalisation'
    );
    $content = temp_np_date_replace_once(
        $content,
        <<<'OLD'
        $formdata['service_type'] = $formdata['sender_service_from'] . $formdata['recipient_service_to'];

        return $formdata;
    }

    // TEMP-NP-ADMIN-LOCKER-001: use the canonical checkout point ref instead of a mutable label.
OLD,
        <<<'NEW'
        $formdata['service_type'] = $formdata['sender_service_from'] . $formdata['recipient_service_to'];

        $formdata = $this->normaliseSenderPostomatShippingDate($formdata);

        return $formdata;
    }

    private function normaliseSenderPostomatShippingDate($formdata)
    {
        if (($formdata['sender_service_from'] ?? '') !== 'Warehouse') {
            return $formdata;
        }

        $warehouse = null;
        $warehouse_ref = $this->normaliseWarehouseRef($formdata['sender_address_warehouse_ref'] ?? '');

        if ($warehouse_ref !== '') {
            $warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByRef($warehouse_ref);
        }

        if (!$warehouse && !empty($formdata['sender_address_warehouse'])) {
            $warehouse = $this->model_extension_PintaNovaPoshtaCod_module_warehouse->getByName($formdata['sender_address_warehouse']);
        }

        if (($warehouse['type_of_warehouse'] ?? '') === self::POSTOMAT_TYPE_REF) {
            $formdata['shipping_date'] = date('d.m.Y');
        }

        return $formdata;
    }

    // TEMP-NP-ADMIN-LOCKER-001: use the canonical checkout point ref instead of a mutable label.
NEW,
        'controller_default_and_helper'
    );
    $updated[$relative] = $content;

    $relative = 'extension/PintaNovaPoshtaCod/admin/model/module/warehouse.php';
    $content = $original[$relative];
    $content = temp_np_date_replace_once(
        $content,
        <<<'OLD'
	public function deleteAll() {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "pinta_nova_poshta_warehouse`");
	}
OLD,
        <<<'NEW'
	// TEMP-NP-ADMIN-LOCKER-001-SAME-DAY: resolve a sender point by its stable Nova Poshta UUID.
	public function getByRef($ref) {
		$query = $this->db->query("
			SELECT * FROM `" . DB_PREFIX . "pinta_nova_poshta_warehouse`
			WHERE ref = '" . $this->db->escape($ref) . "'
			LIMIT 1
		");

		if (!empty($query->rows)) {
			return $query->rows[0];
		} else {
			return null;
		}
	}

	public function deleteAll() {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "pinta_nova_poshta_warehouse`");
	}
NEW,
        'warehouse_get_by_ref'
    );
    $updated[$relative] = $content;

    foreach ($updated as $relative => $content) {
        if (substr_count($content, TEMP_NP_DATE_MARKER) !== 1) {
            temp_np_date_fail('post_transform_marker_count[' . $relative . ']=' . substr_count($content, TEMP_NP_DATE_MARKER));
        }
    }

    $backup_dir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . TEMP_NP_DATE_PATCH . '-' . gmdate('Ymd-His');
    foreach ($relative_files as $relative) {
        $source = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $backup = $backup_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $backup_parent = dirname($backup);

        if (!is_dir($backup_parent) && !mkdir($backup_parent, 0775, true) && !is_dir($backup_parent)) {
            temp_np_date_fail('backup_dir_create_failed=' . $backup_parent);
        }
        if (!copy($source, $backup)) {
            temp_np_date_fail('backup_copy_failed=' . $relative);
        }
    }
    temp_np_date_out('backup', $backup_dir);

    foreach ($relative_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $written_files[] = $relative;
        temp_np_date_write($path, $updated[$relative]);
    }

    foreach ($relative_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        temp_np_date_lint($path);
    }

    foreach ($relative_files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $final = file_get_contents($path);
        if ($final === false || substr_count($final, TEMP_NP_DATE_MARKER) !== 1) {
            temp_np_date_fail('postcheck_failed=' . $relative);
        }
        temp_np_date_out('changed_file', $relative);
    }

    temp_np_date_out('changed_files', count($relative_files));
    temp_np_date_out('database_changes', 'none');
    temp_np_date_out('cache_clear', 'not_required');
    temp_np_date_out('already_applied', 'no');
    temp_np_date_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($backup_dir !== '' && $written_files) {
        foreach ($written_files as $relative) {
            $backup = $backup_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($backup)) {
                @copy($backup, $target);
            }
        }
        temp_np_date_out('rollback', 'attempted');
    }

    temp_np_date_out('error', $error->getMessage());
    temp_np_date_out('done', 'error');
    exit(1);
}
