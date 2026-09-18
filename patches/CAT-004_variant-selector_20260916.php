<?php
/**
 * CAT-004 — master/variant selector on product pages
 *
 * Run from ~/public_html:
 *   php CAT-004_variant-selector_20260916.php
 *
 * Scope: adds a read-only family lookup, controller data, inline Twig selector,
 * selector CSS, and a cache-bust for that CSS only. No database writes.
 * Explicitly untouched: canonical, JSON-LD, aggregateRating, #form-product,
 * generic options, listings, feeds, sitemap, robots, checkout and payments.
 *
 * Rollback: restore the five files from the reported _patch_backups directory.
 */

declare(strict_types=1);

const CAT004_MARKER = 'CAT-004 variant selector';

function cat004_out(string $key, string $value): void {
	echo $key . '=' . $value . PHP_EOL;
}

function cat004_fail(string $message): void {
	throw new RuntimeException($message);
}

function cat004_replace_once(string $source, string $needle, string $replacement, string $name): string {
	if (substr_count($source, $needle) !== 1) {
		cat004_fail('anchor_count_invalid:' . $name);
	}

	return str_replace($needle, $replacement, $source);
}

function cat004_backup(string $backup_dir, string $relative_path): void {
	$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;
	$backup_parent = dirname($backup_path);

	if (!is_dir($backup_parent) && !mkdir($backup_parent, 0755, true) && !is_dir($backup_parent)) {
		cat004_fail('backup_directory_create_failed:' . $relative_path);
	}

	if (!copy($relative_path, $backup_path)) {
		cat004_fail('backup_copy_failed:' . $relative_path);
	}
}

function cat004_restore(array $files, string $backup_dir): void {
	foreach ($files as $relative_path) {
		$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;

		if (is_file($backup_path)) {
			@copy($backup_path, $relative_path);
		}
	}
}

function cat004_lint(string $relative_path): void {
	if (!function_exists('exec')) {
		cat004_fail('php_l_unavailable');
	}

	$output = [];
	$exit_code = 1;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($relative_path) . ' 2>&1', $output, $exit_code);

	cat004_out('php_l_' . basename($relative_path), $exit_code === 0 ? 'ok' : 'failed');

	if ($exit_code !== 0) {
		cat004_fail('php_l_failed:' . $relative_path);
	}
}

$files = [
	'catalog/model/catalog/product.php',
	'catalog/controller/product/product.php',
	'catalog/view/template/product/product.twig',
	'catalog/view/stylesheet/boostershop-ds.css',
	'catalog/view/template/common/header.twig'
];
$backup_dir = '';
$written = false;

try {
	cat004_out('cwd', (string)getcwd());
	cat004_out('time', gmdate('c'));

	foreach ($files as $relative_path) {
		if (!is_file($relative_path) || !is_readable($relative_path) || !is_writable($relative_path)) {
			cat004_fail('target_unavailable:' . $relative_path);
		}
	}

	if (!function_exists('exec')) {
		cat004_fail('php_l_unavailable');
	}

	$sources = [];
	foreach ($files as $relative_path) {
		$contents = file_get_contents($relative_path);

		if ($contents === false) {
			cat004_fail('target_read_failed:' . $relative_path);
		}

		$sources[$relative_path] = $contents;
	}

	$marker_states = [
		strpos($sources['catalog/model/catalog/product.php'], CAT004_MARKER) !== false,
		strpos($sources['catalog/controller/product/product.php'], CAT004_MARKER) !== false,
		strpos($sources['catalog/view/template/product/product.twig'], 'CAT-004 variant selector') !== false,
		strpos($sources['catalog/view/stylesheet/boostershop-ds.css'], CAT004_MARKER) !== false,
		strpos($sources['catalog/view/template/common/header.twig'], 'cat004-variant-20260916') !== false
	];

	if (!array_filter($marker_states)) {
		// Continue with strict anchor checks below.
	} elseif (count(array_filter($marker_states)) === count($marker_states)) {
		cat004_out('already_applied', 'yes');
		cat004_out('done', 'ok');
		cat004_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
		exit(0);
	} else {
		cat004_fail('partial_marker_state');
	}

	$model_insert = <<<'PHP'
	/**
	 * CAT-004 variant selector: get the visible master and child products.
	 *
	 * @param int $master_id master product ID
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getVariantFamily(int $master_id): array {
		$query = $this->db->query("SELECT DISTINCT *, `pd`.`name`, `p`.`image`, " . $this->statement['discount'] . ", " . $this->statement['special'] . " FROM `" . DB_PREFIX . "product_to_store` `p2s` LEFT JOIN `" . DB_PREFIX . "product` `p` ON (`p`.`product_id` = `p2s`.`product_id` AND `p`.`status` = '1' AND `p`.`date_available` <= NOW()) LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) WHERE `p2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "' AND (`p`.`product_id` = '" . (int)$master_id . "' OR `p`.`master_id` = '" . (int)$master_id . "') AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' ORDER BY (`p`.`product_id` = '" . (int)$master_id . "') DESC, `p`.`sort_order` ASC, `p`.`product_id` ASC");

		$product_data = [];

		foreach ($query->rows as $row) {
			$row['variant'] = $row['variant'] ? json_decode($row['variant'], true) : [];
			$row['price'] = (float)($row['discount'] ?: $row['price']);
			$product_data[] = $row;
		}

		return $product_data;
	}

PHP;

	$model_anchor = "\n\t/**\n\t * Get Products\n";
	$sources['catalog/model/catalog/product.php'] = cat004_replace_once(
		$sources['catalog/model/catalog/product.php'],
		$model_anchor,
		"\n\t" . $model_insert . $model_anchor,
		'model_get_products'
	);

	$controller_insert = <<<'PHP'
			// CAT-004 variant selector: values are links to their own product pages.
			// A master normally has no variant JSON in OpenCart. When the family occupies
			// every master-option combination except one, that sole remaining combination
			// identifies the master without changing catalogue data.
			$data['variant_groups'] = [];
			$variant_family = $this->model_catalog_product->getVariantFamily($master_id);

			if (count($variant_family) > 1) {
				$variant_option_ids = [];
				$family_by_product_id = [];
				$master_family_product = [];

				foreach ($variant_family as $family_product) {
					$family_product_id = (int)$family_product['product_id'];
					$family_by_product_id[$family_product_id] = $family_product;

					if ($family_product_id === $master_id) {
						$master_family_product = $family_product;
					}

					if (is_array($family_product['variant'])) {
						foreach ($family_product['variant'] as $option_id => $option_value_id) {
							if ((int)$option_id > 0 && (int)$option_value_id > 0) {
								$variant_option_ids[(int)$option_id] = true;
							}
						}
					}
				}

				$variant_options = [];
				foreach ($product_options as $option) {
					$option_id = (int)$option['product_option_id'];

					if (!isset($variant_option_ids[$option_id])) {
						continue;
					}

					$option_value_ids = [];
					foreach ($option['product_option_value'] as $option_value) {
						$option_value_id = (int)$option_value['product_option_value_id'];
						if ($option_value_id > 0) {
							$option_value_ids[] = $option_value_id;
						}
					}

					if ($option_value_ids) {
						$variant_options[$option_id] = $option_value_ids;
					}
				}

				$master_variant = is_array($master_family_product['variant'] ?? null) ? $master_family_product['variant'] : [];

				if (!$master_variant && $variant_options) {
					$combination_keys = [''];
					foreach ($variant_options as $option_id => $option_value_ids) {
						$next_combination_keys = [];
						foreach ($combination_keys as $combination_key) {
							foreach ($option_value_ids as $option_value_id) {
								$next_combination_keys[] = $combination_key . $option_id . ':' . $option_value_id . ';';
							}
						}
						$combination_keys = $next_combination_keys;
					}

					$claimed_combination_keys = [];
					foreach ($variant_family as $family_product) {
						if ((int)$family_product['product_id'] === $master_id || !is_array($family_product['variant'])) {
							continue;
						}

						$combination_key = '';
						$complete_combination = true;
						foreach ($variant_options as $option_id => $option_value_ids) {
							if (!isset($family_product['variant'][$option_id]) || !in_array((int)$family_product['variant'][$option_id], $option_value_ids, true)) {
								$complete_combination = false;
								break;
							}
							$combination_key .= $option_id . ':' . (int)$family_product['variant'][$option_id] . ';';
						}

						if ($complete_combination) {
							$claimed_combination_keys[$combination_key] = true;
						}
					}

					$unclaimed_combination_keys = array_values(array_filter($combination_keys, static function (string $combination_key) use ($claimed_combination_keys): bool {
						return !isset($claimed_combination_keys[$combination_key]);
					}));

					if (count($unclaimed_combination_keys) === 1) {
						foreach (explode(';', trim($unclaimed_combination_keys[0], ';')) as $part) {
							[$option_id, $option_value_id] = array_map('intval', explode(':', $part, 2));
							$master_variant[$option_id] = $option_value_id;
						}
						$family_by_product_id[$master_id]['variant'] = $master_variant;
					}
				}

				$variant_value_map = [];
				foreach ($family_by_product_id as $family_product) {
					if (!is_array($family_product['variant'])) {
						continue;
					}

					foreach ($family_product['variant'] as $option_id => $option_value_id) {
						$option_id = (int)$option_id;
						$option_value_id = (int)$option_value_id;

						if (isset($variant_options[$option_id]) && in_array($option_value_id, $variant_options[$option_id], true) && !isset($variant_value_map[$option_id][$option_value_id])) {
							$variant_value_map[$option_id][$option_value_id] = $family_product;
						}
					}
				}

				foreach ($product_options as $option) {
					$option_id = (int)$option['product_option_id'];
					if (!isset($variant_value_map[$option_id])) {
						continue;
					}

					$values = [];
					$available_prices = [];

					foreach ($option['product_option_value'] as $option_value) {
						$option_value_id = (int)$option_value['product_option_value_id'];
						$sibling = $variant_value_map[$option_id][$option_value_id] ?? [];
						$available = !empty($sibling) && (int)$sibling['quantity'] > 0;
						$formatted_price = null;

						if ($available && ($this->customer->isLogged() || !$this->config->get('config_customer_price'))) {
							$sibling_price = (float)$sibling['special'] ? (float)$sibling['special'] : (float)$sibling['price'];
							$formatted_price = $this->currency->format($this->tax->calculate($sibling_price, $sibling['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
							$available_prices[$formatted_price] = true;
						}

						$thumb = null;
						if (!empty($option_value['image']) && is_file(DIR_IMAGE . html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8'))) {
							$thumb = $this->model_tool_image->resize($option_value['image'], 50, 50);
						}

						$values[] = [
							'label' => (string)$option_value['name'],
							'href' => $sibling ? $this->url->link('product/product', 'product_id=' . (int)$sibling['product_id']) : '',
							'available' => $available,
							'is_current' => !empty($sibling) && (int)$sibling['product_id'] === $product_id,
							'price' => $formatted_price,
							'thumb' => $thumb
						];
					}

					$show_price = count($available_prices) > 1;
					foreach ($values as $value_key => $value) {
						if (!$show_price || !$value['available']) {
							$values[$value_key]['price'] = null;
						}
					}

					$data['variant_groups'][] = [
						'option_id' => $option_id,
						'name' => (string)$option['name'],
						'show_price' => $show_price,
						'values' => $values
					];
				}
			}

PHP;

	$controller_anchor = "\n\t\t\t// Subscriptions\n";
	$sources['catalog/controller/product/product.php'] = cat004_replace_once(
		$sources['catalog/controller/product/product.php'],
		$controller_anchor,
		"\n" . $controller_insert . $controller_anchor,
		'controller_subscriptions'
	);

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
                      {% if value.available and value.href %}
                        <a href="{{ value.href }}" class="bs-variant__chip{% if value.is_current %} is-active{% endif %}">
                          {% if value.thumb %}<img class="bs-variant__thumb" src="{{ value.thumb }}" alt=""/>{% endif %}
                          <span class="bs-variant__text">
                            <span class="bs-variant__value">{{ value.label }}</span>
                            {% if group.show_price and value.price %}<span class="bs-variant__price">{{ value.price }}</span>{% endif %}
                          </span>
                        </a>
                      {% else %}
                        <span class="bs-variant__chip is-off{% if value.is_current %} is-active{% endif %}">
                          {% if value.thumb %}<img class="bs-variant__thumb" src="{{ value.thumb }}" alt=""/>{% endif %}
                          <span class="bs-variant__text"><span class="bs-variant__value">{{ value.label }}</span></span>
                        </span>
                      {% endif %}
                    {% endfor %}
                  </div>
                </div>
              {% endfor %}
            {% endif %}

TWIG;

	$twig_anchor = "          <div id=\"product\">\n            <form id=\"form-product\">";
	$sources['catalog/view/template/product/product.twig'] = cat004_replace_once(
		$sources['catalog/view/template/product/product.twig'],
		$twig_anchor,
		"          <div id=\"product\">\n" . $twig_insert . "            <form id=\"form-product\">",
		'twig_product_form'
	);

	$css_insert = <<<'CSS'

/* CAT-004 variant selector */
.bs-variant { margin: 20px 0; }
.bs-variant__label { display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px; color: var(--bs-ink-2); font-size: 14px; font-weight: 600; }
.bs-variant__current { color: var(--bs-ink); font-weight: 700; }
.bs-variant__row { display: flex; gap: 8px; flex-wrap: wrap; }
.bs-variant__chip { display: inline-flex; align-items: center; gap: 7px; min-height: 44px; padding: 6px 10px; background: var(--bs-paper); border: 1px solid var(--bs-line); border-radius: var(--bs-r-sm); color: var(--bs-ink-2); font-family: inherit; font-size: 14px; font-weight: 600; line-height: 1.2; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
.bs-variant__chip:hover { background: var(--bs-paper); border-color: var(--bs-ink-3); color: var(--bs-ink-2); text-decoration: none; }
.bs-variant__chip:focus-visible { outline: 2px solid var(--bs-blue); outline-offset: 2px; }
.bs-variant__chip.is-active { background: var(--bs-blue-soft); border-color: var(--bs-blue); box-shadow: 0 0 0 2px rgba(30, 58, 138, .08); color: var(--bs-blue); font-weight: 700; }
.bs-variant__chip.is-off { background-color: var(--bs-bg); background-image: linear-gradient(135deg, transparent calc(50% - 1px), var(--bs-line-2) calc(50% - 1px), var(--bs-line-2) calc(50% + 1px), transparent calc(50% + 1px)); border-color: var(--bs-line-2); color: var(--bs-ink-4); cursor: not-allowed; }
.bs-variant__text { display: grid; gap: 2px; }
.bs-variant__thumb { width: 28px; height: 28px; border-radius: var(--bs-r-sm); object-fit: cover; }
.bs-variant__chip.is-off .bs-variant__thumb { opacity: .35; }
.bs-variant__price { display: block; color: inherit; font-size: 12px; font-weight: 700; }
@media (max-width: 767.98px) { .bs-variant__chip { min-height: 46px; } }
/* /CAT-004 variant selector */
CSS;

	$css_anchor = '/* /UI-FIX-20260903-TILES */';
	$sources['catalog/view/stylesheet/boostershop-ds.css'] = cat004_replace_once(
		$sources['catalog/view/stylesheet/boostershop-ds.css'],
		$css_anchor,
		$css_anchor . $css_insert,
		'css_terminal_marker'
	);

	$header_old = 'catalog/view/stylesheet/boostershop-ds.css?v=uifix-tiles-20260904';
	$header_new = 'catalog/view/stylesheet/boostershop-ds.css?v=cat004-variant-20260916';
	$sources['catalog/view/template/common/header.twig'] = cat004_replace_once(
		$sources['catalog/view/template/common/header.twig'],
		$header_old,
		$header_new,
		'header_css_cache_bust'
	);

	$backup_dir = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . 'CAT-004_variant-selector_' . gmdate('Ymd_His');
	if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) {
		cat004_fail('backup_root_create_failed');
	}

	foreach ($files as $relative_path) {
		cat004_backup($backup_dir, $relative_path);
	}
	cat004_out('backup', $backup_dir);

	foreach ($files as $relative_path) {
		if (file_put_contents($relative_path, $sources[$relative_path]) === false) {
			cat004_fail('target_write_failed:' . $relative_path);
		}
		cat004_out('changed', $relative_path);
		$written = true;
	}

	cat004_lint('catalog/model/catalog/product.php');
	cat004_lint('catalog/controller/product/product.php');
	cat004_out('done', 'ok');
	cat004_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $exception) {
	if ($written && $backup_dir !== '') {
		cat004_restore($files, $backup_dir);
		cat004_out('restore', 'attempted');
	}

	fwrite(STDERR, 'ERROR=' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
