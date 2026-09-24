<?php
/**
 * CAT-004 — variant families: own table, own admin tab, storefront selector
 *
 * Run from ~/public_html:
 *   php CAT-004_variant-family_20260919.php
 *
 * Model (AGENTS.md → "Variant products — canonical rules", rewritten 2026-09-19):
 * a family is a set of ordinary independent products. No master_id, no product
 * options, no field inheritance. Membership is one row per product in this
 * patch's own table, edited from its own tab on the admin product form.
 * Exactly one axis per family.
 *
 * DATABASE CHANGE — one new table, approved in advance by the owner
 * (handoff §2; AGENTS.md patch convention 6).
 *
 *   Created:  `<DB_PREFIX>product_variant_family`
 *             product_id INT PRIMARY KEY, family_key VARCHAR(64) indexed,
 *             axis_label VARCHAR(64), value_label VARCHAR(64), sort_order INT
 *   Engine and collation are read from the live `<DB_PREFIX>product` table at
 *   run time and echoed, not assumed.
 *
 *   ROLLBACK SQL (run by the owner, only if the table is to go as well):
 *
 *     DROP TABLE IF EXISTS `<DB_PREFIX>product_variant_family`;
 *
 *   No existing table is read for writing and no existing row is touched. The
 *   only rows this feature ever writes are its own. Dropping the table removes
 *   the selector and changes nothing else — members stay ordinary products.
 *
 * ADMIN DIRECTORY. The admin folder on this installation is `adminEvhenii`,
 * not `admin`; verified against the owner's 2026-09-07 cPanel backup, where no
 * `public_html/admin` exists. The handoff's `admin/...` paths are generic.
 *
 * CARRIED ACROSS BYTE FOR BYTE from CAT-004_variant-selector_20260916.php
 * (handoff §4.3): the storefront Twig selector block and the CSS block, with
 * their comments, the link-colour specificity fixes and the
 * .bs-variant__chip.is-active.is-off rule. Both are model-agnostic.
 *
 * PRECONDITION. CAT-004_variant-selector_20260916.php must already be rolled
 * back (handoff §5). This runner checks for its markers and refuses to start
 * while they are present, rather than failing later on a missing anchor.
 *
 * Explicitly untouched: canonical, JSON-LD, aggregateRating, #form-product,
 * the generic options block, thumb.php / thumb.twig and the Rare Pack badge,
 * copyProduct / addVariant / editVariant / editVariants, listings, the feed,
 * sitemap, robots, checkout and payments.
 *
 * Rollback: restore the seven backed-up files from the reported
 * _patch_backups directory, delete the new admin model file, clear the cache,
 * and optionally run the DROP TABLE above.
 */

declare(strict_types=1);

const CAT004F_MARKER = 'CAT-004 variant family';
const CAT004F_CARRIED_MARKER = 'CAT-004 variant selector';
const CAT004F_CACHE_TOKEN = 'cat004-family-20260919';
const CAT004F_CSS_HREF_PREFIX = 'catalog/view/stylesheet/boostershop-ds.css?v=';
const CAT004F_ADMIN_DIR = 'adminEvhenii';
const CAT004F_TABLE_SUFFIX = 'product_variant_family';

function cat004f_out(string $key, string $value): void {
	echo $key . '=' . $value . PHP_EOL;
}

function cat004f_fail(string $message): void {
	throw new RuntimeException($message);
}

function cat004f_replace_once(string $source, string $needle, string $replacement, string $name): string {
	if (substr_count($source, $needle) !== 1) {
		cat004f_fail('anchor_count_invalid:' . $name);
	}

	return str_replace($needle, $replacement, $source);
}

function cat004f_backup(string $backup_dir, string $relative_path): void {
	$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;
	$backup_parent = dirname($backup_path);

	if (!is_dir($backup_parent) && !mkdir($backup_parent, 0755, true) && !is_dir($backup_parent)) {
		cat004f_fail('backup_directory_create_failed:' . $relative_path);
	}

	if (!copy($relative_path, $backup_path)) {
		cat004f_fail('backup_copy_failed:' . $relative_path);
	}
}

/**
 * Restore every backed-up file, remove every file this run created, and report
 * a verified result per path.
 *
 * A restore that silently failed must not be indistinguishable from one that
 * worked, so every copy is checked and read back and the run ends on an
 * explicit restore=ok or restore=incomplete:<paths>.
 *
 * @param array<int, string> $files
 * @param array<int, string> $created_files
 * @param string             $backup_dir
 *
 * @return void
 */
function cat004f_restore(array $files, array $created_files, string $backup_dir): void {
	$incomplete = [];

	foreach ($created_files as $relative_path) {
		if (!is_file($relative_path)) {
			cat004f_out('restore:' . $relative_path, 'absent');
			continue;
		}

		if (@unlink($relative_path)) {
			cat004f_out('restore:' . $relative_path, 'removed');
			continue;
		}

		cat004f_out('restore:' . $relative_path, 'remove_failed');
		$incomplete[] = $relative_path;
	}

	foreach ($files as $relative_path) {
		$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;

		if (!is_file($backup_path)) {
			cat004f_out('restore:' . $relative_path, 'no_backup');
			$incomplete[] = $relative_path;
			continue;
		}

		if (!@copy($backup_path, $relative_path)) {
			cat004f_out('restore:' . $relative_path, 'copy_failed');
			$incomplete[] = $relative_path;
			continue;
		}

		clearstatcache(true, $relative_path);

		$expected = @file_get_contents($backup_path);
		$actual = @file_get_contents($relative_path);

		if ($expected === false || $actual === false) {
			cat004f_out('restore:' . $relative_path, 'unverified');
			$incomplete[] = $relative_path;
			continue;
		}

		if ($expected !== $actual) {
			cat004f_out('restore:' . $relative_path, 'mismatch');
			$incomplete[] = $relative_path;
			continue;
		}

		cat004f_out('restore:' . $relative_path, 'ok');
	}

	cat004f_out('restore', $incomplete ? 'incomplete:' . implode(',', $incomplete) : 'ok');
}

function cat004f_lint(string $relative_path): void {
	if (!function_exists('exec')) {
		cat004f_fail('php_l_unavailable');
	}

	$output = [];
	$exit_code = 1;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($relative_path) . ' 2>&1', $output, $exit_code);

	cat004f_out('php_l:' . $relative_path, $exit_code === 0 ? 'ok' : 'failed');

	if ($exit_code !== 0) {
		cat004f_fail('php_l_failed:' . $relative_path);
	}
}

$admin_model_path = CAT004F_ADMIN_DIR . '/model/catalog/variant_family.php';

$files = [
	CAT004F_ADMIN_DIR . '/controller/catalog/product.php',
	CAT004F_ADMIN_DIR . '/view/template/catalog/product_form.twig',
	'catalog/model/catalog/product.php',
	'catalog/controller/product/product.php',
	'catalog/view/template/product/product.twig',
	'catalog/view/stylesheet/boostershop-ds.css',
	'catalog/view/template/common/header.twig'
];
$backup_dir = '';
$written = false;
$created_files = [];

try {
	cat004f_out('cwd', (string)getcwd());
	cat004f_out('time', gmdate('c'));

	if (!function_exists('exec')) {
		cat004f_fail('php_l_unavailable');
	}

	if (!extension_loaded('mysqli')) {
		cat004f_fail('mysqli_not_loaded');
	}

	if (!is_file('config.php')) {
		cat004f_fail('config_php_not_found_run_from_public_html');
	}

	foreach ($files as $relative_path) {
		if (!is_file($relative_path) || !is_readable($relative_path) || !is_writable($relative_path)) {
			cat004f_fail('target_unavailable:' . $relative_path);
		}
	}

	$admin_model_dir = dirname($admin_model_path);

	if (!is_dir($admin_model_dir) || !is_writable($admin_model_dir)) {
		cat004f_fail('admin_model_directory_unavailable:' . $admin_model_dir);
	}

	$sources = [];
	foreach ($files as $relative_path) {
		$contents = file_get_contents($relative_path);

		if ($contents === false) {
			cat004f_fail('target_read_failed:' . $relative_path);
		}

		$sources[$relative_path] = $contents;
	}

	// --- precondition: the superseded selector patch must be rolled back ------
	// Its controller block and its model method belong to the rejected
	// master/variant model. Left in place they would collide with this patch's
	// Twig anchor and leave two family lookups in the catalog model. Checked by
	// name so the failure says what to do instead of "anchor_count_invalid".

	if (strpos($sources['catalog/controller/product/product.php'], CAT004F_CARRIED_MARKER . ': values are links') !== false
		|| strpos($sources['catalog/model/catalog/product.php'], 'getVariantFamily(int $master_id)') !== false) {
		cat004f_fail('previous_patch_still_applied:restore_CAT-004_variant-selector_backup_first');
	}

	// --- idempotence markers (AGENTS.md patch conventions 5 and 8) ------------
	// Content markers only. The header cache-bust token is a shared value that
	// CAT-004/SD-7 and UI-PCARD-D rewrite as well, so it can never stand as this
	// patch's marker. The two carried-across blocks keep their original
	// "variant selector" comment, which is why the precondition above runs
	// first: after it, that string can only have come from this patch.

	$marker_states = [
		is_file($admin_model_path),
		strpos($sources[CAT004F_ADMIN_DIR . '/controller/catalog/product.php'], CAT004F_MARKER) !== false,
		strpos($sources[CAT004F_ADMIN_DIR . '/view/template/catalog/product_form.twig'], CAT004F_MARKER) !== false,
		strpos($sources['catalog/model/catalog/product.php'], CAT004F_MARKER) !== false,
		strpos($sources['catalog/controller/product/product.php'], CAT004F_MARKER) !== false,
		strpos($sources['catalog/view/template/product/product.twig'], CAT004F_CARRIED_MARKER) !== false,
		strpos($sources['catalog/view/stylesheet/boostershop-ds.css'], CAT004F_CARRIED_MARKER) !== false
	];

	if (!array_filter($marker_states)) {
		// Continue with strict anchor checks below.
	} elseif (count(array_filter($marker_states)) === count($marker_states)) {
		cat004f_out('already_applied', 'yes');
		cat004f_out('done', 'ok');
		cat004f_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
		exit(0);
	} else {
		cat004f_fail('partial_marker_state');
	}

	// --- 0/8 · the new table --------------------------------------------------
	// Done before any file is written: a storefront that queries a table which
	// does not exist errors on every product page, so the schema leads and the
	// code follows. CREATE TABLE IF NOT EXISTS is idempotent on its own.
	// No prepared-statement result is read through get_result() and no
	// fetch_all() is used — this host's mysqli has no mysqlnd.

	require_once 'config.php';

	foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
		if (!defined($constant)) {
			cat004f_fail('missing_config_constant:' . $constant);
		}
	}

	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

	$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int)DB_PORT : 3306);
	$db->set_charset('utf8mb4');

	$family_table = DB_PREFIX . CAT004F_TABLE_SUFFIX;
	$product_table = DB_PREFIX . 'product';

	// Engine and collation are copied from the live product table, not assumed.
	$engine = '';
	$collation = '';

	$statement = $db->prepare('SELECT `ENGINE`, `TABLE_COLLATION` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? LIMIT 1');
	$statement->bind_param('s', $product_table);
	$statement->execute();
	$statement->bind_result($engine, $collation);
	$statement->fetch();
	$statement->close();

	$engine = (string)$engine;
	$collation = (string)$collation;

	if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $engine)) {
		cat004f_fail('product_table_engine_unreadable:' . $product_table);
	}

	if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $collation)) {
		cat004f_fail('product_table_collation_unreadable:' . $product_table);
	}

	$charset = substr($collation, 0, (int)strpos($collation, '_'));

	if (!preg_match('/^[A-Za-z0-9]{1,32}$/', $charset)) {
		cat004f_fail('product_table_charset_unreadable:' . $collation);
	}

	cat004f_out('db_product_engine', $engine);
	cat004f_out('db_product_collation', $collation);

	$existing = 0;
	$statement = $db->prepare('SELECT COUNT(*) FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?');
	$statement->bind_param('s', $family_table);
	$statement->execute();
	$statement->bind_result($existing);
	$statement->fetch();
	$statement->close();

	$db->query(
		'CREATE TABLE IF NOT EXISTS `' . $family_table . '` ('
		. '`product_id` int(11) NOT NULL,'
		. '`family_key` varchar(64) NOT NULL DEFAULT \'\','
		. '`axis_label` varchar(64) NOT NULL DEFAULT \'\','
		. '`value_label` varchar(64) NOT NULL DEFAULT \'\','
		. '`sort_order` int(11) NOT NULL DEFAULT 0,'
		. 'PRIMARY KEY (`product_id`),'
		. 'KEY `family_key` (`family_key`)'
		. ') ENGINE=' . $engine . ' DEFAULT CHARSET=' . $charset . ' COLLATE=' . $collation
	);

	cat004f_out('db_table', $family_table);
	cat004f_out('db_table_state', (int)$existing ? 'already_present' : 'created');

	$db->close();

	// --- 1/8 · <admin>/model/catalog/variant_family.php (new file) ------------

	$admin_model_source = <<<'PHP'
<?php
namespace Opencart\Admin\Model\Catalog;
/**
 * Class VariantFamily
 *
 * CAT-004 variant family: membership of a product in a variant family.
 * One row per product; a product belongs to at most one family and carries
 * exactly one value along that family's single axis.
 *
 * Can be loaded using $this->load->model('catalog/variant_family');
 *
 * @package Opencart\Admin\Model\Catalog
 */
class VariantFamily extends \Opencart\System\Engine\Model {
	/**
	 * Column width of family_key, axis_label and value_label.
	 */
	private const LABEL_LIMIT = 64;

	/**
	 * Get the family row of one product.
	 *
	 * @param int $product_id
	 *
	 * @return array<string, mixed> empty when the product is in no family
	 */
	public function getFamily(int $product_id): array {
		$query = $this->db->query("SELECT `family_key`, `axis_label`, `value_label`, `sort_order` FROM `" . DB_PREFIX . "product_variant_family` WHERE `product_id` = '" . (int)$product_id . "' LIMIT 1");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * Create or replace the family row of one product.
	 *
	 * Every text field is truncated to the column width before it reaches SQL:
	 * a strict-mode server rejects an over-long value outright, and the admin
	 * field is free text.
	 *
	 * @param int    $product_id
	 * @param string $family_key
	 * @param string $axis_label
	 * @param string $value_label
	 * @param int    $sort_order
	 *
	 * @return void
	 */
	public function setFamily(int $product_id, string $family_key, string $axis_label, string $value_label, int $sort_order): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "product_variant_family` SET `product_id` = '" . (int)$product_id . "', `family_key` = '" . $this->db->escape($this->clip($family_key)) . "', `axis_label` = '" . $this->db->escape($this->clip($axis_label)) . "', `value_label` = '" . $this->db->escape($this->clip($value_label)) . "', `sort_order` = '" . (int)$sort_order . "' ON DUPLICATE KEY UPDATE `family_key` = VALUES(`family_key`), `axis_label` = VALUES(`axis_label`), `value_label` = VALUES(`value_label`), `sort_order` = VALUES(`sort_order`)");
	}

	/**
	 * Remove the family row of one product.
	 *
	 * @param int $product_id
	 *
	 * @return void
	 */
	public function deleteFamily(int $product_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_variant_family` WHERE `product_id` = '" . (int)$product_id . "'");
	}

	/**
	 * Distinct family keys already in use, for the admin autocomplete.
	 *
	 * A mistyped key is the one way a product silently drops out of its family,
	 * so the admin picks from this list instead of retyping.
	 *
	 * @param string $filter_family_key
	 * @param int    $limit
	 *
	 * @return array<int, array<string, string>>
	 */
	public function getFamilyKeys(string $filter_family_key = '', int $limit = 20): array {
		$sql = "SELECT DISTINCT `family_key` FROM `" . DB_PREFIX . "product_variant_family` WHERE `family_key` != ''";

		if (trim($filter_family_key) !== '') {
			$sql .= " AND `family_key` LIKE '" . $this->db->escape($this->clip($filter_family_key)) . "%'";
		}

		$sql .= " ORDER BY `family_key` ASC LIMIT " . ($limit > 0 ? (int)$limit : 20);

		$query = $this->db->query($sql);

		$family_keys = [];

		foreach ($query->rows as $row) {
			$family_keys[] = ['family_key' => (string)$row['family_key']];
		}

		return $family_keys;
	}

	/**
	 * Trim and cut a free-text field to the column width.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function clip(string $value): string {
		$value = trim($value);

		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, self::LABEL_LIMIT, 'UTF-8');
		}

		return substr($value, 0, self::LABEL_LIMIT);
	}
}

PHP;

	// --- 2/8 · <admin>/controller/catalog/product.php -------------------------

	$admin_controller_path = CAT004F_ADMIN_DIR . '/controller/catalog/product.php';

	$admin_form_insert = <<<'PHP'
		// CAT-004 variant family
		$this->load->model('catalog/variant_family');

		$variant_family_info = $product_id ? $this->model_catalog_variant_family->getFamily($product_id) : [];

		$data['variant_family_key'] = $variant_family_info ? (string)$variant_family_info['family_key'] : '';
		$data['variant_axis_label'] = $variant_family_info ? (string)$variant_family_info['axis_label'] : '';
		$data['variant_value_label'] = $variant_family_info ? (string)$variant_family_info['value_label'] : '';
		$data['variant_sort_order'] = $variant_family_info ? (int)$variant_family_info['sort_order'] : 0;

PHP;

	$admin_form_anchor = "\n\t\t// Customer Group\n";
	$sources[$admin_controller_path] = cat004f_replace_once(
		$sources[$admin_controller_path],
		$admin_form_anchor,
		"\n" . $admin_form_insert . $admin_form_anchor,
		'admin_form_customer_group'
	);

	$admin_save_anchor = <<<'PHP'
				// Variant products edit if master product is edited
				$this->model_catalog_product->editVariants($post_info['product_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
PHP;

	$admin_save_replacement = <<<'PHP'
				// Variant products edit if master product is edited
				$this->model_catalog_product->editVariants($post_info['product_id'], $post_info);
			}

			// CAT-004 variant family. The add path has just put the new id into
			// $json['product_id']; the edit path has it in $post_info. Keyed on the
			// field being present in the post rather than on its value, so a caller
			// that never sends the field cannot silently wipe an existing family —
			// only an operator who cleared the field can, which is what clearing it
			// means.
			if (array_key_exists('variant_family_key', $this->request->post)) {
				$this->load->model('catalog/variant_family');

				$variant_family_product_id = (int)($post_info['product_id'] ?: ($json['product_id'] ?? 0));
				$variant_family_key = trim((string)$this->request->post['variant_family_key']);

				if ($variant_family_product_id) {
					if ($variant_family_key !== '') {
						$this->model_catalog_variant_family->setFamily(
							$variant_family_product_id,
							$variant_family_key,
							trim((string)($this->request->post['variant_axis_label'] ?? '')),
							trim((string)($this->request->post['variant_value_label'] ?? '')),
							(int)($this->request->post['variant_sort_order'] ?? 0)
						);
					} else {
						$this->model_catalog_variant_family->deleteFamily($variant_family_product_id);
					}
				}
			}

			$json['success'] = $this->language->get('text_success');
PHP;

	$sources[$admin_controller_path] = cat004f_replace_once(
		$sources[$admin_controller_path],
		$admin_save_anchor,
		$admin_save_replacement,
		'admin_save_tail'
	);

	$admin_delete_anchor = <<<'PHP'
			foreach ($selected as $product_id) {
				$this->model_catalog_product->deleteProduct($product_id);
			}
PHP;

	$admin_delete_replacement = <<<'PHP'
			// CAT-004 variant family
			$this->load->model('catalog/variant_family');

			foreach ($selected as $product_id) {
				$this->model_catalog_product->deleteProduct($product_id);

				$this->model_catalog_variant_family->deleteFamily((int)$product_id);
			}
PHP;

	$sources[$admin_controller_path] = cat004f_replace_once(
		$sources[$admin_controller_path],
		$admin_delete_anchor,
		$admin_delete_replacement,
		'admin_delete_loop'
	);

	$admin_autocomplete_insert = <<<'PHP'
	/**
	 * CAT-004 variant family: autocomplete over the family keys already in use.
	 *
	 * Route: catalog/product.familyKey
	 *
	 * @return void
	 */
	public function familyKey(): void {
		$this->load->model('catalog/variant_family');

		if (isset($this->request->get['filter_family_key'])) {
			$filter_family_key = (string)$this->request->get['filter_family_key'];
		} else {
			$filter_family_key = '';
		}

		if (isset($this->request->get['limit'])) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = (int)$this->config->get('config_autocomplete_limit');
		}

		$json = $this->model_catalog_variant_family->getFamilyKeys($filter_family_key, $limit > 0 ? $limit : 20);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

PHP;

	$admin_autocomplete_anchor = "\tpublic function autocomplete(): void {";
	$sources[$admin_controller_path] = cat004f_replace_once(
		$sources[$admin_controller_path],
		$admin_autocomplete_anchor,
		$admin_autocomplete_insert . $admin_autocomplete_anchor,
		'admin_autocomplete_method'
	);

	// --- 3/8 · <admin>/view/template/catalog/product_form.twig ----------------

	$admin_twig_path = CAT004F_ADMIN_DIR . '/view/template/catalog/product_form.twig';

	$admin_tab_link_anchor = "            <li class=\"nav-item\"><a href=\"#tab-attribute\" data-bs-toggle=\"tab\" class=\"nav-link\">{{ tab_attribute }}</a></li>\n";
	$admin_tab_link_insert = "            {# CAT-004 variant family #}\n            <li class=\"nav-item\"><a href=\"#tab-variant-family\" data-bs-toggle=\"tab\" class=\"nav-link\">Родина варіантів</a></li>\n";

	$sources[$admin_twig_path] = cat004f_replace_once(
		$sources[$admin_twig_path],
		$admin_tab_link_anchor,
		$admin_tab_link_anchor . $admin_tab_link_insert,
		'admin_tab_link'
	);

	// Labels are literal Ukrainian: the admin is single-language here and an
	// undefined {{ tab_… }} variable renders as an empty string, which is worse
	// than a hardcoded word (handoff §3.1).
	$admin_tab_pane_insert = <<<'TWIG'

            {# CAT-004 variant family: membership of this product in a variant family.
               One row per product, exactly one axis per family. Clearing the key
               removes the product from its family on save. #}
            <div id="tab-variant-family" class="tab-pane">
              <div class="row mb-3">
                <label for="input-variant-family-key" class="col-sm-2 col-form-label">Ключ родини</label>
                <div class="col-sm-10">
                  <input type="text" name="variant_family_key" value="{{ variant_family_key }}" placeholder="Ключ родини" id="input-variant-family-key" data-oc-target="autocomplete-variant-family-key" maxlength="64" class="form-control" autocomplete="off"/>
                  <ul id="autocomplete-variant-family-key" class="dropdown-menu"></ul>
                  <div class="form-text">Однаковий у всіх товарів родини. Обирайте зі списку — помилка в ключі тихо виключає товар з родини. Порожнє поле означає, що товар у родину не входить, і його запис видаляється при збереженні.</div>
                </div>
              </div>
              <div class="row mb-3">
                <label for="input-variant-axis-label" class="col-sm-2 col-form-label">Назва характеристики</label>
                <div class="col-sm-10">
                  <input type="text" name="variant_axis_label" value="{{ variant_axis_label }}" placeholder="Розмір" id="input-variant-axis-label" maxlength="64" class="form-control"/>
                  <div class="form-text">Підпис над рядом чипів на сторінці товару. Однаковий у всіх товарів родини.</div>
                </div>
              </div>
              <div class="row mb-3">
                <label for="input-variant-value-label" class="col-sm-2 col-form-label">Значення</label>
                <div class="col-sm-10">
                  <input type="text" name="variant_value_label" value="{{ variant_value_label }}" placeholder="21 см" id="input-variant-value-label" maxlength="64" class="form-control"/>
                  <div class="form-text">Напис на чипі саме цього товару. Унікальний у межах родини. Порожнє значення прибирає товар з селектора.</div>
                </div>
              </div>
              <div class="row mb-3">
                <label for="input-variant-sort-order" class="col-sm-2 col-form-label">Порядок чипа</label>
                <div class="col-sm-10">
                  <input type="text" name="variant_sort_order" value="{{ variant_sort_order }}" placeholder="0" id="input-variant-sort-order" class="form-control"/>
                  <div class="form-text">Порядок цього чипа в ряду. Це не «Порядок сортування» з вкладки «Дані» — той керує позицією в категорії.</div>
                </div>
              </div>
            </div>
TWIG;

	$admin_tab_pane_anchor = "\n            {% if not master_id %}\n              <div id=\"tab-option\" class=\"tab-pane\">\n";
	$sources[$admin_twig_path] = cat004f_replace_once(
		$sources[$admin_twig_path],
		$admin_tab_pane_anchor,
		$admin_tab_pane_insert . $admin_tab_pane_anchor,
		'admin_tab_pane'
	);

	$admin_js_insert = <<<'JS'

// CAT-004 variant family
$('#input-variant-family-key').autocomplete({
    'source': function(request, response) {
        $.ajax({
            url: 'index.php?route=catalog/product.familyKey&user_token={{ user_token }}&filter_family_key=' + encodeURIComponent(request),
            dataType: 'json',
            success: function(json) {
                response($.map(json, function(item) {
                    return {
                        label: item['family_key'],
                        value: item['family_key']
                    }
                }));
            }
        });
    },
    'select': function(item) {
        $('#input-variant-family-key').val(decodeHTMLEntities(item['label']));
    }
});
JS;

	$admin_js_anchor = "\n// Category\n$('#input-category').autocomplete({\n";
	$sources[$admin_twig_path] = cat004f_replace_once(
		$sources[$admin_twig_path],
		$admin_js_anchor,
		$admin_js_insert . $admin_js_anchor,
		'admin_autocomplete_js'
	);

	// --- 4/8 · catalog/model/catalog/product.php ------------------------------

	$model_insert = <<<'PHP'
	/**
	 * CAT-004 variant family: the family row of one product.
	 *
	 * @param int $product_id
	 *
	 * @return array<string, mixed> empty when the product is in no family
	 */
	public function getVariantFamilyMembership(int $product_id): array {
		$query = $this->db->query("SELECT `family_key`, `axis_label`, `value_label`, `sort_order` FROM `" . DB_PREFIX . "product_variant_family` WHERE `product_id` = '" . (int)$product_id . "' LIMIT 1");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * CAT-004 variant family: the visible members of one family.
	 *
	 * Same join shape, store, language, status and date_available filters as
	 * getProduct(), so a member that cannot render its own page cannot appear as
	 * a chip either. A member with no chip label is excluded: the storefront
	 * never invents one.
	 *
	 * @param string $family_key
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getVariantFamilyMembers(string $family_key): array {
		if (trim($family_key) === '') {
			return [];
		}

		$query = $this->db->query("SELECT DISTINCT `p`.`product_id`, `p`.`price`, `p`.`quantity`, `p`.`stock_status_id`, `p`.`date_available`, `p`.`tax_class_id`, `pvf`.`value_label`, `pvf`.`sort_order` AS `variant_sort_order`, " . $this->statement['discount'] . ", " . $this->statement['special'] . " FROM `" . DB_PREFIX . "product_to_store` `p2s` LEFT JOIN `" . DB_PREFIX . "product` `p` ON (`p`.`product_id` = `p2s`.`product_id` AND `p`.`status` = '1' AND `p`.`date_available` <= NOW()) LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) LEFT JOIN `" . DB_PREFIX . "product_variant_family` `pvf` ON (`pvf`.`product_id` = `p2s`.`product_id`) WHERE `p2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND `pvf`.`family_key` = '" . $this->db->escape($family_key) . "' AND TRIM(`pvf`.`value_label`) != '' ORDER BY `pvf`.`sort_order` ASC, `p`.`product_id` ASC");

		$product_data = [];

		foreach ($query->rows as $row) {
			$row['price'] = (float)($row['discount'] ?: $row['price']);
			$product_data[] = $row;
		}

		return $product_data;
	}

PHP;

	$model_anchor = "\n\t/**\n\t * Get Products\n";
	$sources['catalog/model/catalog/product.php'] = cat004f_replace_once(
		$sources['catalog/model/catalog/product.php'],
		$model_anchor,
		"\n\t" . $model_insert . $model_anchor,
		'model_get_products'
	);

	// --- 5/8 · catalog/controller/product/product.php -------------------------

	$controller_insert = <<<'PHP'
			// CAT-004 variant family: sibling chips for a declared variant family.
			// Membership is one row per product in the shop's own table, and the
			// members are ordinary independent products — no master_id, no options,
			// no field inheritance. Exactly one axis per family.
			//
			// state: '' a buyable member, 'out' a member that is not buyable. The
			// template's third value, 'none', cannot arise from this source — a
			// declared member with no visible product is simply not returned — but
			// the three-value contract is kept so the template needs no variant.
			$data['variant_groups'] = [];

			$variant_membership = $this->model_catalog_product->getVariantFamilyMembership($product_id);

			if (!empty($variant_membership['family_key'])) {
				$variant_members = $this->model_catalog_product->getVariantFamilyMembers((string)$variant_membership['family_key']);

				// One chip is not a selector.
				if (count($variant_members) > 1) {
					// Mirror of the state predicate in
					// catalog/controller/product/thumb.php, block "BoosterShop RD-04f
					// state normalization 20260601" — the shop's one definition of this
					// state. A pre-order product carries quantity <= 0 and is fully
					// purchasable, so it is an ordinary chip here too. Only the
					// predicate is mirrored; that block's bs_eta month formatting is not.
					$cat004_variant_state = function (array $member): string {
						if ((int)($member['quantity'] ?? 0) > 0) {
							return '';
						}

						static $cat004_stock_status_names = [];

						$stock_status_id = (int)($member['stock_status_id'] ?? 0);
						$language_id = (int)$this->config->get('config_language_id');
						$cache_key = $language_id . ':' . $stock_status_id;

						if (!array_key_exists($cache_key, $cat004_stock_status_names)) {
							$stock_status_query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "stock_status` WHERE `stock_status_id` = '" . $stock_status_id . "' AND `language_id` = '" . $language_id . "' LIMIT 1");
							$cat004_stock_status_names[$cache_key] = $stock_status_query->num_rows ? (string)$stock_status_query->row['name'] : '';
						}

						$stock = trim($cat004_stock_status_names[$cache_key]);
						$stock_lc = function_exists('mb_strtolower') ? mb_strtolower($stock, 'UTF-8') : $stock;

						$has_preorder_signal = strpos($stock_lc, 'передзамов') !== false
							|| strpos($stock, 'Передзамов') !== false
							|| strpos($stock_lc, 'preorder') !== false
							|| strpos($stock_lc, 'pre-order') !== false;

						if (!$has_preorder_signal) {
							$date_available = !empty($member['date_available']) ? substr((string)$member['date_available'], 0, 10) : '';

							if ($date_available !== '' && $date_available !== '0000-00-00' && $date_available > date('Y-m-d')) {
								$has_preorder_signal = true;
							}
						}

						return $has_preorder_signal ? '' : 'out';
					};

					$values = [];
					$member_prices = [];

					foreach ($variant_members as $variant_member) {
						$formatted_price = null;

						// Every member with a visible product feeds the group price rule,
						// in stock or not. Otherwise a family stops showing prices the
						// moment its one differently-priced member sells out and starts
						// again on restock — the page changes shape for an invisible reason.
						if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
							$member_price = (float)$variant_member['special'] ? (float)$variant_member['special'] : (float)$variant_member['price'];
							$formatted_price = $this->currency->format($this->tax->calculate($member_price, $variant_member['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
							$member_prices[$formatted_price] = true;
						}

						$values[] = [
							'label' => (string)$variant_member['value_label'],
							'href' => $this->url->link('product/product', 'product_id=' . (int)$variant_member['product_id']),
							'state' => $cat004_variant_state($variant_member),
							'is_current' => (int)$variant_member['product_id'] === $product_id,
							'price' => $formatted_price,
							'thumb' => null
						];
					}

					$show_price = count($member_prices) > 1;

					if (!$show_price) {
						foreach ($values as $value_key => $value) {
							$values[$value_key]['price'] = null;
						}
					}

					$data['variant_groups'][] = [
						'name' => (string)($variant_membership['axis_label'] ?? ''),
						'show_price' => $show_price,
						'values' => $values
					];
				}
			}

PHP;

	$controller_anchor = "\n\t\t\t// Subscriptions\n";
	$sources['catalog/controller/product/product.php'] = cat004f_replace_once(
		$sources['catalog/controller/product/product.php'],
		$controller_anchor,
		"\n" . $controller_insert . $controller_anchor,
		'controller_subscriptions'
	);

	// --- 6/8 · catalog/view/template/product/product.twig ---------------------
	// Carried across byte for byte from CAT-004_variant-selector_20260916.php.

	$twig_insert = <<<'TWIG'
            {# CAT-004 variant selector: normal links; no in-place product swap. #}
            {% if variant_groups %}
              {% for group in variant_groups %}
                <div class="bs-variant">
                  <div class="bs-variant__label">
                    {{ group.name }}
                    {% for value in group.values %}
                      {% if value.is_current %}<span class="bs-variant__current">{{ value.label }}</span>{% endif %}
                    {% endfor %}
                  </div>
                  <div class="bs-variant__row">
                    {% for value in group.values %}
                      {% if value.state != 'none' and value.href %}
                        <a href="{{ value.href }}" class="bs-variant__chip{% if value.is_current %} is-active{% endif %}{% if value.state == 'out' %} is-off{% endif %}"{% if value.is_current %} aria-current="page"{% endif %}>
                          {% if value.thumb %}<img class="bs-variant__thumb" src="{{ value.thumb }}" alt=""/>{% endif %}
                          <span class="bs-variant__text">
                            <span class="bs-variant__value">{{ value.label }}</span>
                            {% if group.show_price and value.price %}<span class="bs-variant__price">{{ value.price }}</span>{% endif %}
                          </span>
                          {% if value.state == 'out' %}<span class="visually-hidden">немає в наявності</span>{% endif %}
                        </a>
                      {% else %}
                        <span class="bs-variant__chip is-off{% if value.is_current %} is-active{% endif %}" aria-disabled="true"{% if value.is_current %} aria-current="page"{% endif %}>
                          {% if value.thumb %}<img class="bs-variant__thumb" src="{{ value.thumb }}" alt=""/>{% endif %}
                          <span class="bs-variant__text"><span class="bs-variant__value">{{ value.label }}</span></span>
                          <span class="visually-hidden">недоступно</span>
                        </span>
                      {% endif %}
                    {% endfor %}
                  </div>
                </div>
              {% endfor %}
            {% endif %}

TWIG;

	$twig_anchor = "          <div id=\"product\">\n            <form id=\"form-product\">";
	$sources['catalog/view/template/product/product.twig'] = cat004f_replace_once(
		$sources['catalog/view/template/product/product.twig'],
		$twig_anchor,
		"          <div id=\"product\">\n" . $twig_insert . "            <form id=\"form-product\">",
		'twig_product_form'
	);

	// --- 7/8 · catalog/view/stylesheet/boostershop-ds.css ---------------------
	// Carried across byte for byte from CAT-004_variant-selector_20260916.php.

	$css_insert = <<<'CSS'

/* CAT-004 variant selector */
.bs-variant { margin: 20px 0; }
.bs-variant__label { display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px; color: var(--bs-ink-2); font-size: 14px; font-weight: 600; }
.bs-variant__current { color: var(--bs-ink); font-weight: 700; }
.bs-variant__row { display: flex; gap: 8px; flex-wrap: wrap; }
.bs-variant__chip { display: inline-flex; align-items: center; gap: 7px; min-height: 44px; padding: 6px 10px; background: var(--bs-paper); border: 1px solid var(--bs-line); border-radius: var(--bs-r-sm); color: var(--bs-ink-2); font-family: inherit; font-size: 14px; font-weight: 600; line-height: 1.2; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
.bs-variant__chip:hover { background: var(--bs-paper); border-color: var(--bs-ink-3); color: var(--bs-ink-2); text-decoration: none; }
/* Root cause, measured: `.bs a` at line 94 of this file paints every link
   var(--bs-blue) at (0,1,1) and outranks the class-only chip rule above, and
   `.bs a:hover` at line 95 adds an underline at (0,2,1) that outranks the chip
   hover rule. Left alone, an available chip renders blue instead of the approved
   ink and grows an underline on hover. Both are corrected for the chip alone by
   selector specificity; the global link rules are not touched and no priority
   flag is used anywhere in this block. The element-qualified colour deliberately
   stays at (0,1,1) so is-active and is-off keep winning it. */
a.bs-variant__chip { color: var(--bs-ink-2); }
a.bs-variant__chip:hover { text-decoration: none; }
.bs-variant__chip:focus-visible { outline: 2px solid var(--bs-blue); outline-offset: 2px; }
.bs-variant__chip.is-active { background: var(--bs-blue-soft); border-color: var(--bs-blue); box-shadow: 0 0 0 2px rgba(30, 58, 138, .08); color: var(--bs-blue); font-weight: 700; }
/* is-off is styling only. A value whose sibling exists stays a link whether or not
   that sibling is buyable, so the not-allowed cursor belongs to the span form alone
   and the anchor form keeps its pointer, its hover affordance and its focus ring. */
.bs-variant__chip.is-off { background-color: var(--bs-bg); background-image: linear-gradient(135deg, transparent calc(50% - 1px), var(--bs-line-2) calc(50% - 1px), var(--bs-line-2) calc(50% + 1px), transparent calc(50% + 1px)); border-color: var(--bs-line-2); color: var(--bs-ink-4); }
span.bs-variant__chip.is-off { cursor: not-allowed; }
/* Owner decision 2026-09-18: on a sold-out variant's own page the chip kept the
   is-off grey and the visitor lost every cue to where they were standing. The
   frame and the ink go back to blue at (0,3,0) while the grey ground and the
   strike stay, so the chip reads "you are here, and it is out of stock". The
   :not() below keeps that true on hover too: the out-of-stock hover affordance
   belongs to the siblings you can travel to, not to the page you are on. */
.bs-variant__chip.is-active.is-off { border-color: var(--bs-blue); color: var(--bs-blue); }
a.bs-variant__chip.is-off:not(.is-active):hover { border-color: var(--bs-ink-3); color: var(--bs-ink-3); }
.bs-variant__text { display: grid; gap: 2px; }
.bs-variant__thumb { width: 28px; height: 28px; border-radius: var(--bs-r-sm); object-fit: cover; }
.bs-variant__chip.is-off .bs-variant__thumb { opacity: .35; }
.bs-variant__price { display: block; color: inherit; font-size: 12px; font-weight: 700; }
@media (max-width: 767.98px) { .bs-variant__chip { min-height: 46px; } }
/* /CAT-004 variant selector */
CSS;

	$css_anchor = '/* /UI-FIX-20260903-TILES */';
	$sources['catalog/view/stylesheet/boostershop-ds.css'] = cat004f_replace_once(
		$sources['catalog/view/stylesheet/boostershop-ds.css'],
		$css_anchor,
		$css_anchor . $css_insert,
		'css_terminal_marker'
	);

	// --- 8/8 · catalog/view/template/common/header.twig -----------------------
	// Locate the stylesheet by its path prefix, read whatever token is there and
	// replace it wholesale. Never anchor on a token value: CAT-004/SD-7 and
	// UI-PCARD-D rewrite the same one, and whichever patch runs last owns it.

	$header_path = 'catalog/view/template/common/header.twig';
	$header_source = $sources[$header_path];

	if (substr_count($header_source, CAT004F_CSS_HREF_PREFIX) !== 1) {
		cat004f_fail('anchor_count_invalid:header_css_cache_bust');
	}

	$header_token_offset = strpos($header_source, CAT004F_CSS_HREF_PREFIX) + strlen(CAT004F_CSS_HREF_PREFIX);
	$header_token = substr($header_source, $header_token_offset, strcspn($header_source, "\"'?& \t\r\n<>", $header_token_offset));

	if ($header_token === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $header_token)) {
		cat004f_fail('header_cache_bust_token_invalid');
	}

	cat004f_out('header_cache_bust_from', $header_token);
	cat004f_out('header_cache_bust_to', CAT004F_CACHE_TOKEN);

	$sources[$header_path] = cat004f_replace_once(
		$header_source,
		CAT004F_CSS_HREF_PREFIX . $header_token,
		CAT004F_CSS_HREF_PREFIX . CAT004F_CACHE_TOKEN,
		'header_css_cache_bust'
	);

	// --- backup, write, lint --------------------------------------------------

	$backup_dir = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . 'CAT-004_variant-family_' . gmdate('Ymd_His');
	if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) {
		cat004f_fail('backup_root_create_failed');
	}

	foreach ($files as $relative_path) {
		cat004f_backup($backup_dir, $relative_path);
	}
	cat004f_out('backup', $backup_dir);

	// The new file has nothing to back up; the restore path deletes it instead.
	if (file_put_contents($admin_model_path, $admin_model_source) === false) {
		cat004f_fail('target_write_failed:' . $admin_model_path);
	}
	$written = true;
	$created_files[] = $admin_model_path;
	cat004f_out('created', $admin_model_path);

	foreach ($files as $relative_path) {
		if (file_put_contents($relative_path, $sources[$relative_path]) === false) {
			cat004f_fail('target_write_failed:' . $relative_path);
		}
		cat004f_out('changed', $relative_path);
	}

	cat004f_lint($admin_model_path);
	cat004f_lint(CAT004F_ADMIN_DIR . '/controller/catalog/product.php');
	cat004f_lint('catalog/model/catalog/product.php');
	cat004f_lint('catalog/controller/product/product.php');
	cat004f_out('done', 'ok');
	cat004f_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $exception) {
	if ($written && $backup_dir !== '') {
		cat004f_restore($files, $created_files, $backup_dir);
	}

	fwrite(STDERR, 'ERROR=' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
