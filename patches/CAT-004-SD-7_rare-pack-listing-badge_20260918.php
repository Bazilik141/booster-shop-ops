<?php
/**
 * CAT-004 / SD-7 — «Rare Pack» badge on the listing tile
 *
 * Run from ~/public_html:
 *   php CAT-004-SD-7_rare-pack-listing-badge_20260918.php
 *
 * Scope: one read-only product-attribute lookup in product/thumb.php, the
 * left-corner badge block in product/thumb.twig, one new badge class plus a
 * flex column on .bs-pcard__badge-tl, and a CSS cache-bust. No database writes.
 *
 * Flag source: attribute «Тип товару» (attribute_id 27, language_id 4 verified
 * in ocp5_attribute_description), value «Rare Pack». Matching is on the value,
 * not on the attribute being present — it is a shared characteristic that
 * carries other values on other products.
 * Canon: plans/CAT-004_op-rare-packs_identifier-canon_20260916.md
 *
 * Cache-bust note (AGENTS.md patch convention 8, 2026-09-18): the current token
 * is read, not assumed — located by its path prefix, shape-validated, then
 * replaced wholesale by this patch's own token. No patch is coupled to another
 * patch's token value, so run order does not matter. header.twig is deliberately
 * not part of the idempotence marker set: the three content markers decide, and
 * a repeat run exits already_applied without touching the token, which is
 * correct — this patch's CSS is already live, so nothing needs busting.
 *
 * Explicitly untouched: .bs-pcard__badge-tr and the discount badge, the price
 * row, the CTA block, the cart form, the TECH-015-WP3B GA4 script, bs_state and
 * bs_eta semantics, every existing .bs-badge--* rule, the legacy
 * body.bs .product-thumb .bs-pcard-badge--* block, product pages, listing
 * controllers, feeds, sitemap, robots, checkout and payments.
 *
 * Rollback: restore the four files from the reported _patch_backups directory
 * and clear the OpenCart cache. Nothing is written to the database.
 */

declare(strict_types=1);

const CAT004SD7_MARKER = 'CAT-004 SD-7';
const CAT004SD7_CSS_MARKER = 'CAT-004 SD-7 Rare Pack listing badge';
const CAT004SD7_CACHE_TOKEN = 'cat004-sd7-20260918';
const CAT004SD7_CSS_HREF_PREFIX = 'catalog/view/stylesheet/boostershop-ds.css?v=';

function cat004sd7_out(string $key, string $value): void {
	echo $key . '=' . $value . PHP_EOL;
}

function cat004sd7_fail(string $message): void {
	throw new RuntimeException($message);
}

function cat004sd7_replace_once(string $source, string $needle, string $replacement, string $name): string {
	if (substr_count($source, $needle) !== 1) {
		cat004sd7_fail('anchor_count_invalid:' . $name);
	}

	return str_replace($needle, $replacement, $source);
}

function cat004sd7_backup(string $backup_dir, string $relative_path): void {
	$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;
	$backup_parent = dirname($backup_path);

	if (!is_dir($backup_parent) && !mkdir($backup_parent, 0755, true) && !is_dir($backup_parent)) {
		cat004sd7_fail('backup_directory_create_failed:' . $relative_path);
	}

	if (!copy($relative_path, $backup_path)) {
		cat004sd7_fail('backup_copy_failed:' . $relative_path);
	}

	clearstatcache(true, $backup_path);

	$original = file_get_contents($relative_path);
	$copied = file_get_contents($backup_path);

	if ($original === false || $copied === false || $original !== $copied) {
		cat004sd7_fail('backup_verify_failed:' . $relative_path);
	}
}

/**
 * Restore every backed-up file and report a verified per-file result.
 *
 * @param array<int, string> $files
 */
function cat004sd7_restore(array $files, string $backup_dir): void {
	foreach ($files as $relative_path) {
		$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;

		if (!is_file($backup_path)) {
			cat004sd7_out('restore:' . $relative_path, 'no_backup');
			continue;
		}

		if (!@copy($backup_path, $relative_path)) {
			cat004sd7_out('restore:' . $relative_path, 'copy_failed');
			continue;
		}

		clearstatcache(true, $relative_path);

		$expected = @file_get_contents($backup_path);
		$actual = @file_get_contents($relative_path);

		if ($expected === false || $actual === false) {
			cat004sd7_out('restore:' . $relative_path, 'unverified');
			continue;
		}

		cat004sd7_out('restore:' . $relative_path, $expected === $actual ? 'ok' : 'mismatch');
	}
}

function cat004sd7_lint(string $relative_path): void {
	if (!function_exists('exec')) {
		cat004sd7_fail('php_l_unavailable');
	}

	$output = [];
	$exit_code = 1;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($relative_path) . ' 2>&1', $output, $exit_code);

	cat004sd7_out('php_l_' . basename($relative_path), $exit_code === 0 ? 'ok' : 'failed');

	if ($exit_code !== 0) {
		cat004sd7_fail('php_l_failed:' . $relative_path);
	}
}

$files = [
	'catalog/controller/product/thumb.php',
	'catalog/view/template/product/thumb.twig',
	'catalog/view/stylesheet/boostershop-ds.css',
	'catalog/view/template/common/header.twig'
];
$backup_dir = '';
$written = false;

try {
	cat004sd7_out('cwd', (string)getcwd());
	cat004sd7_out('time', gmdate('c'));

	foreach ($files as $relative_path) {
		if (!is_file($relative_path) || !is_readable($relative_path) || !is_writable($relative_path)) {
			cat004sd7_fail('target_unavailable:' . $relative_path);
		}
	}

	if (!function_exists('exec')) {
		cat004sd7_fail('php_l_unavailable');
	}

	$sources = [];
	foreach ($files as $relative_path) {
		$contents = file_get_contents($relative_path);

		if ($contents === false) {
			cat004sd7_fail('target_read_failed:' . $relative_path);
		}

		$sources[$relative_path] = $contents;
	}

	// Content markers only. The cache-bust token is a shared value other patches
	// rewrite too, so it can never stand as this patch's idempotence marker
	// (AGENTS.md patch conventions 5 and 8).
	$marker_states = [
		strpos($sources['catalog/controller/product/thumb.php'], CAT004SD7_MARKER) !== false,
		strpos($sources['catalog/view/template/product/thumb.twig'], CAT004SD7_MARKER) !== false,
		strpos($sources['catalog/view/stylesheet/boostershop-ds.css'], CAT004SD7_CSS_MARKER) !== false
	];

	if (!array_filter($marker_states)) {
		// Continue with strict anchor checks below.
	} elseif (count(array_filter($marker_states)) === count($marker_states)) {
		cat004sd7_out('already_applied', 'yes');
		cat004sd7_out('done', 'ok');
		cat004sd7_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
		exit(0);
	} else {
		cat004sd7_fail('partial_marker_state');
	}

	// --- 1/4 · catalog/controller/product/thumb.php -------------------------

	$controller_const_insert = <<<'PHPBLOCK'
	/**
	 * CAT-004 SD-7 — «Rare Pack» listing flag.
	 * Canon: plans/CAT-004_op-rare-packs_identifier-canon_20260916.md
	 *
	 * BS_CAT004_RARE_ATTRIBUTE_ID is the shared «Тип товару» characteristic, so the
	 * match is on the value and never on the attribute being present.
	 */
	private const BS_CAT004_RARE_ATTRIBUTE_ID = 27;
	private const BS_CAT004_RARE_ATTRIBUTE_VALUE = 'rare pack';


PHPBLOCK;

	$controller_const_anchor = "class Thumb extends \\Opencart\\System\\Engine\\Controller {\n";
	$sources['catalog/controller/product/thumb.php'] = cat004sd7_replace_once(
		$sources['catalog/controller/product/thumb.php'],
		$controller_const_anchor,
		$controller_const_anchor . $controller_const_insert,
		'controller_class_constants'
	);

	$controller_lookup_insert = <<<'PHPBLOCK'

		// CAT-004 SD-7: «Rare Pack» listing flag. Constants at the top of this class.
		$data['bs_is_rare'] = false;

		$bs_rare_product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;

		if ($bs_rare_product_id > 0) {
			static $bs_rare_cache = [];

			$bs_rare_language_id = (int)$this->config->get('config_language_id');
			$bs_rare_cache_key = $bs_rare_language_id . ':' . $bs_rare_product_id;

			if (!array_key_exists($bs_rare_cache_key, $bs_rare_cache)) {
				$query = $this->db->query("SELECT `text` FROM `" . DB_PREFIX . "product_attribute` WHERE `product_id` = '" . $bs_rare_product_id . "' AND `attribute_id` = '" . (int)self::BS_CAT004_RARE_ATTRIBUTE_ID . "' AND `language_id` = '" . $bs_rare_language_id . "' LIMIT 1");
				$bs_rare_cache[$bs_rare_cache_key] = $query->num_rows ? (string)$query->row['text'] : '';
			}

			$bs_rare_value = trim($bs_rare_cache[$bs_rare_cache_key]);
			$bs_rare_value_lc = function_exists('mb_strtolower') ? mb_strtolower($bs_rare_value, 'UTF-8') : strtolower($bs_rare_value);

			$data['bs_is_rare'] = $bs_rare_value_lc === self::BS_CAT004_RARE_ATTRIBUTE_VALUE;
		}

PHPBLOCK;

	$controller_lookup_anchor = "\n\t\t// --- /BoosterShop RD-04f state normalization ---\n";
	$sources['catalog/controller/product/thumb.php'] = cat004sd7_replace_once(
		$sources['catalog/controller/product/thumb.php'],
		$controller_lookup_anchor,
		"\n" . $controller_lookup_insert . $controller_lookup_anchor,
		'controller_rd04f_tail'
	);

	// --- 2/4 · catalog/view/template/product/thumb.twig ---------------------

	$twig_set_anchor = "{% set bs_is_out = bs_state_value == 'out' %}\n";
	$sources['catalog/view/template/product/thumb.twig'] = cat004sd7_replace_once(
		$sources['catalog/view/template/product/thumb.twig'],
		$twig_set_anchor,
		$twig_set_anchor . "{% set bs_is_rare = bs_is_rare|default(false) %}\n",
		'twig_state_flags'
	);

	$twig_badge_anchor = <<<'TWIGBLOCK'
    {% if bs_is_pre %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--preorder">Передзамовлення</span>
      </span>
    {% elseif bs_is_out %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--out">Немає в наявності</span>
      </span>
    {% endif %}

TWIGBLOCK;

	$twig_badge_insert = <<<'TWIGBLOCK'
{# CAT-004 SD-7: the left corner is a badge column — pre-order or out-of-stock
   first, «Rare Pack» under it. The inner tags start at column 0 on purpose:
   Twig emits the whitespace around a tag and swallows only the newline directly
   after %}, so a tile without the attribute renders byte-identically to the
   pre-patch template. Do not re-indent them. #}
    {% if bs_is_pre or bs_is_out or bs_is_rare %}
      <span class="bs-pcard__badge-tl">
{% if bs_is_pre %}        <span class="bs-badge bs-badge--preorder">Передзамовлення</span>
{% elseif bs_is_out %}        <span class="bs-badge bs-badge--out">Немає в наявності</span>
{% endif %}{% if bs_is_rare %}        <span class="bs-badge bs-badge--rare">Rare Pack</span>
{% endif %}      </span>
    {% endif %}

TWIGBLOCK;

	$sources['catalog/view/template/product/thumb.twig'] = cat004sd7_replace_once(
		$sources['catalog/view/template/product/thumb.twig'],
		$twig_badge_anchor,
		$twig_badge_insert,
		'twig_badge_tl_block'
	);

	// --- 3/4 · catalog/view/stylesheet/boostershop-ds.css -------------------

	$css_insert = <<<'CSSBLOCK'


/* CAT-004 SD-7 Rare Pack listing badge */
/* Size, weight, letter-spacing, radius, padding and uppercase come from .bs-badge. */
.bs-badge--rare { background: #4C0519; color: #FDE68A; }
/* The corner becomes a column; position/top/left stay on the base rule above. */
.bs-pcard__badge-tl { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
/* /CAT-004 SD-7 Rare Pack listing badge */
CSSBLOCK;

	$css_anchor = '/* /UI-FIX-20260903-TILES */';
	$sources['catalog/view/stylesheet/boostershop-ds.css'] = cat004sd7_replace_once(
		$sources['catalog/view/stylesheet/boostershop-ds.css'],
		$css_anchor,
		$css_anchor . $css_insert,
		'css_terminal_marker'
	);

	// --- 4/4 · catalog/view/template/common/header.twig ---------------------

	$header_source = $sources['catalog/view/template/common/header.twig'];

	if (substr_count($header_source, CAT004SD7_CSS_HREF_PREFIX) !== 1) {
		cat004sd7_fail('anchor_count_invalid:header_css_cache_bust');
	}

	$header_token_offset = strpos($header_source, CAT004SD7_CSS_HREF_PREFIX) + strlen(CAT004SD7_CSS_HREF_PREFIX);
	$header_token = substr($header_source, $header_token_offset, strcspn($header_source, "\"'?& \t\r\n<>", $header_token_offset));

	if ($header_token === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $header_token)) {
		cat004sd7_fail('header_cache_bust_token_invalid');
	}

	cat004sd7_out('header_cache_bust_from', $header_token);
	cat004sd7_out('header_cache_bust_to', CAT004SD7_CACHE_TOKEN);

	$sources['catalog/view/template/common/header.twig'] = cat004sd7_replace_once(
		$header_source,
		CAT004SD7_CSS_HREF_PREFIX . $header_token,
		CAT004SD7_CSS_HREF_PREFIX . CAT004SD7_CACHE_TOKEN,
		'header_css_cache_bust'
	);

	// --- backup, write, verify ---------------------------------------------

	$backup_dir = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . 'CAT-004-SD-7_rare-pack-listing-badge_' . gmdate('Ymd_His');
	if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) {
		cat004sd7_fail('backup_root_create_failed');
	}

	foreach ($files as $relative_path) {
		cat004sd7_backup($backup_dir, $relative_path);
	}
	cat004sd7_out('backup', $backup_dir);

	foreach ($files as $relative_path) {
		if (file_put_contents($relative_path, $sources[$relative_path]) === false) {
			cat004sd7_fail('target_write_failed:' . $relative_path);
		}

		$written = true;
		clearstatcache(true, $relative_path);

		$readback = file_get_contents($relative_path);

		if ($readback === false || $readback !== $sources[$relative_path]) {
			cat004sd7_fail('target_write_verify_failed:' . $relative_path);
		}

		cat004sd7_out('changed', $relative_path);
	}

	cat004sd7_lint('catalog/controller/product/thumb.php');
	cat004sd7_out('done', 'ok');
	cat004sd7_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $exception) {
	if ($written && $backup_dir !== '') {
		cat004sd7_restore($files, $backup_dir);
	}

	fwrite(STDERR, 'ERROR=' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
