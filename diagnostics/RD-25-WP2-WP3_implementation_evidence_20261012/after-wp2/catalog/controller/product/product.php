<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Product
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Product extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return ?\Opencart\System\Engine\Action
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->load->language('product/product');

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

				if ($product_info) {
			$product_url = $this->url->link('product/product', 'product_id=' . $product_id);

			$this->document->setTitle($product_info['meta_title']);
			$this->document->setDescription($product_info['meta_description']);
			$this->document->setKeywords($product_info['meta_keyword']);
			$this->document->addLink($product_url, 'canonical');

			$data['breadcrumbs'] = [];


									$data['breadcrumbs'][] = [
				'text' => $this->language->get('text_home') ?: 'Головна',
				'href' => rtrim($this->config->get('config_url'), '/') . '/'
			];



			// Category
$this->load->model('catalog/category');

$breadcrumb_path = $this->request->get['path'] ?? '';

if (!$breadcrumb_path) {
	$product_categories = $this->model_catalog_product->getCategories($product_id);

	if ($product_categories) {
		// CAT-002-5c: rebuild full parent category path for direct product URLs.
		$breadcrumb_category_ids = [];
		$breadcrumb_category_id = (int)$product_categories[0]['category_id'];
		$breadcrumb_seen = [];

		while ($breadcrumb_category_id > 0 && !isset($breadcrumb_seen[$breadcrumb_category_id])) {
			$breadcrumb_seen[$breadcrumb_category_id] = true;
			array_unshift($breadcrumb_category_ids, $breadcrumb_category_id);

			$breadcrumb_category_info = $this->model_catalog_category->getCategory($breadcrumb_category_id);
			if (!$breadcrumb_category_info) {
				break;
			}

			$breadcrumb_category_id = (int)($breadcrumb_category_info['parent_id'] ?? 0);
		}

		$breadcrumb_path = implode('_', $breadcrumb_category_ids);
	}
}

if ($breadcrumb_path) {
	$path = '';

	$parts = explode('_', (string)$breadcrumb_path);


				$category_id = (int)array_pop($parts);

				foreach ($parts as $path_id) {
					if (!$path) {
						$path = $path_id;
					} else {
						$path .= '_' . $path_id;
					}

					$category_info = $this->model_catalog_category->getCategory((int)$path_id);

					if ($category_info) {
						$data['breadcrumbs'][] = [
							'text' => $category_info['name'],
							'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $path)
						];
					}
				}

				// Set the last category breadcrumb
				$category_info = $this->model_catalog_category->getCategory($category_id);

				if ($category_info) {
					$url = '';

					if (isset($this->request->get['sort'])) {
						$url .= '&sort=' . $this->request->get['sort'];
					}

					if (isset($this->request->get['order'])) {
						$url .= '&order=' . $this->request->get['order'];
					}

					if (isset($this->request->get['page'])) {
						$url .= '&page=' . $this->request->get['page'];
					}

					if (isset($this->request->get['limit'])) {
						$url .= '&limit=' . $this->request->get['limit'];
					}

					$data['breadcrumbs'][] = [
						'text' => $category_info['name'],
						'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $breadcrumb_path . $url)

					];
				}
			}

			// Manufacturer
			$this->load->model('catalog/manufacturer');

			if (isset($this->request->get['manufacturer_id'])) {
				$data['breadcrumbs'][] = [
					'text' => $this->language->get('text_brand'),
					'href' => $this->url->link('product/manufacturer', 'language=' . $this->config->get('config_language'))
				];

				$url = '';

				if (isset($this->request->get['sort'])) {
					$url .= '&sort=' . $this->request->get['sort'];
				}

				if (isset($this->request->get['order'])) {
					$url .= '&order=' . $this->request->get['order'];
				}

				if (isset($this->request->get['page'])) {
					$url .= '&page=' . $this->request->get['page'];
				}

				if (isset($this->request->get['limit'])) {
					$url .= '&limit=' . $this->request->get['limit'];
				}

				$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($this->request->get['manufacturer_id']);

				if ($manufacturer_info) {
					$data['breadcrumbs'][] = [
						'text' => $manufacturer_info['name'],
						'href' => $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $this->request->get['manufacturer_id'] . $url)
					];
				}
			}

			if (isset($this->request->get['search']) || isset($this->request->get['tag'])) {
				$url = '';

				if (isset($this->request->get['search'])) {
					$url .= '&search=' . $this->request->get['search'];
				}

				if (isset($this->request->get['tag'])) {
					$url .= '&tag=' . $this->request->get['tag'];
				}

				if (isset($this->request->get['description'])) {
					$url .= '&description=' . $this->request->get['description'];
				}

				if (isset($this->request->get['category_id'])) {
					$url .= '&category_id=' . $this->request->get['category_id'];
				}

				if (isset($this->request->get['sub_category'])) {
					$url .= '&sub_category=' . $this->request->get['sub_category'];
				}

				if (isset($this->request->get['sort'])) {
					$url .= '&sort=' . $this->request->get['sort'];
				}

				if (isset($this->request->get['order'])) {
					$url .= '&order=' . $this->request->get['order'];
				}

				if (isset($this->request->get['page'])) {
					$url .= '&page=' . $this->request->get['page'];
				}

				if (isset($this->request->get['limit'])) {
					$url .= '&limit=' . $this->request->get['limit'];
				}

				$data['breadcrumbs'][] = [
					'text' => $this->language->get('text_search'),
					'href' => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . $url)
				];
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
			}

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . $this->request->get['tag'];
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

						$data['breadcrumbs'][] = [
				'text' => $product_info['name'],
				'href' => $product_url
			];


			$this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
			$this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');

			$data['heading_title'] = $product_info['name'];

			$data['text_minimum'] = sprintf($this->language->get('text_minimum'), $product_info['minimum']);
			$data['text_login'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')), $this->url->link('account/register', 'language=' . $this->config->get('config_language')));
			$data['text_reviews'] = sprintf($this->language->get('text_reviews'), (int)$product_info['reviews']);
			$data['review_count'] = (int)$product_info['reviews'];

			$data['tab_review'] = sprintf($this->language->get('tab_review'), $product_info['reviews']);

			$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));

			$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);

			$this->session->data['upload_token'] = oc_token(32);

			$data['upload'] = $this->url->link('tool/upload', 'language=' . $this->config->get('config_language') . '&upload_token=' . $this->session->data['upload_token']);

			$data['product_id'] = $product_id;

			$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

			if ($manufacturer_info) {
				$data['manufacturer'] = $manufacturer_info['name'];
			} else {
				$data['manufacturer'] = '';
			}

			$data['manufacturers'] = $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $product_info['manufacturer_id']);
			$data['model'] = $product_info['model'];

			$data['product_codes'] = [];

			$results = $this->model_catalog_product->getCodes($product_id);

			foreach ($results as $result) {
				if ($result['status']) {
					$data['product_codes'][] = $result;
				}
			}

			$data['reward'] = $product_info['reward'];
			$data['points'] = $product_info['points'];
			$data['description'] = html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8');

			// Stock Status
			if ($product_info['quantity'] <= 0) {
				$stock_status_id = $product_info['stock_status_id'];
			} elseif (!$this->config->get('config_stock_display')) {
				$stock_status_id = (int)$this->config->get('config_stock_status_id');
			} else {
				$stock_status_id = 0;
			}

			// Stock Status
			$this->load->model('localisation/stock_status');

			$stock_status_info = $this->model_localisation_stock_status->getStockStatus($stock_status_id);

			if ($stock_status_info) {
				$data['stock'] = $stock_status_info['name'];
			} else {
				$data['stock'] = $product_info['quantity'];
			}

			$data['rating'] = (int)$product_info['rating'];
			$date_available = (string)($product_info['date_available'] ?? '');
			$data['bs_is_preorder'] = $date_available !== '' && $date_available > date('Y-m-d');

			$data['review_status'] = (int)$this->config->get('config_review_status');
			$data['review'] = $this->load->controller('product/review');

			$data['wishlist_add'] = $this->url->link('account/wishlist.add', 'language=' . $this->config->get('config_language'));
			$data['compare_add'] = $this->url->link('product/compare.add', 'language=' . $this->config->get('config_language'));

			// Image
			$this->load->model('tool/image');
			// RD-25-WP2 20261012: view-only fit payload; legacy popup/thumb/images stay intact.
			$data['bs_gallery'] = [];
			$bs_gallery_item = function (string $filename, string $popup, string $legacy, int $legacy_width, int $legacy_height): array {
				$item = ['popup' => $popup, 'main' => $legacy, 'small' => $legacy,
					'kind' => 'legacy', 'main_width' => $legacy_width, 'main_height' => $legacy_height,
					'small_width' => $legacy_width, 'small_height' => $legacy_height];
				$main = $this->model_tool_image->resizeFit($filename, 600, 750);
				$small = $this->model_tool_image->resizeFit($filename, 128, 160);
				$media = $this->model_tool_image->mediaInfo($filename);
				if ($media && ($main !== '' || $small !== '')) {
					$item['kind'] = $media['kind'];
					foreach (['main' => [600, 750, $main], 'small' => [128, 160, $small]] as $key => $fit) {
						if ($fit[2] === '') { continue; }
						$scale = min(1.0, $fit[0] / $media['crop_width'], $fit[1] / $media['crop_height']);
						$item[$key] = $fit[2];
						$item[$key . '_width'] = max(1, (int)floor($media['crop_width'] * $scale));
						$item[$key . '_height'] = max(1, (int)floor($media['crop_height'] * $scale));
					}
				}
				return $item;
			};

			if ($product_info['image'] && is_file(DIR_IMAGE . html_entity_decode($product_info['image'], ENT_QUOTES, 'UTF-8'))) {
				$data['popup'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_popup_width'), $this->config->get('config_image_popup_height'));
				$data['thumb'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_thumb_width'), $this->config->get('config_image_thumb_height'));
			} else {
				$data['popup'] = '';
				$data['thumb'] = '';
			}

			if ($data['thumb'] !== '') {
				$data['bs_gallery'][] = $bs_gallery_item($product_info['image'], $data['popup'], $data['thumb'], (int)$this->config->get('config_image_thumb_width'), (int)$this->config->get('config_image_thumb_height'));
			}

			$data['images'] = [];

			$results = $this->model_catalog_product->getImages($product_id);

			foreach ($results as $result) {
				if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$data['images'][] = [
						'popup' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_popup_width'), $this->config->get('config_image_popup_height')),
						'thumb' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_additional_width'), $this->config->get('config_image_additional_height'))
					];
					$bs_legacy_image = $data['images'][count($data['images']) - 1];
					$data['bs_gallery'][] = $bs_gallery_item($result['image'], $bs_legacy_image['popup'], $bs_legacy_image['thumb'], (int)$this->config->get('config_image_additional_width'), (int)$this->config->get('config_image_additional_height'));
				}
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['price'] = false;
			}

			if ((float)$product_info['special']) {
				$data['special'] = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['special'] = false;
			}
			// PAY-001-PHASE2C-CART-CONTRACT-GATES-20260725: keep the credit teaser
			// visible when Mono is configured. Quantity and threshold are interactive
			// client-side gates; checkout repeats both rules against authoritative data.
			$pay001_mono_price = (float)$product_info['special'] ?: (float)$product_info['price'];
			$pay001_mono_currency = strtoupper((string)($this->session->data['currency'] ?? $this->config->get('config_currency')));
			$data['pay001_mono_chast_visible'] =
				(bool)$this->config->get('payment_mono_chast_status') &&
				$pay001_mono_currency === 'UAH' &&
				trim((string)$this->config->get('payment_mono_chast_api_base')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_id')) !== '' &&
				trim((string)$this->config->get('payment_mono_chast_store_secret')) !== '';
			$data['pay001_mono_chast_price'] = round($pay001_mono_price, 2);
			$data['pay001_mono_chast_min_total'] = max(500.0, (float)$this->config->get('payment_mono_chast_min_total'));
			$data['pay001_mono_chast_cart_total'] = round((float)$this->cart->getSubTotal(), 2);
			$data['pay001_mono_chast_in_stock'] = (int)$product_info['quantity'] > 0;
			$pay001_mono_base_url = rtrim((string)$this->config->get('config_url'), '/');
			$data['pay001_mono_chast_checkout'] = $pay001_mono_base_url . '/index.php?route=checkout/checkout&language=' . rawurlencode((string)$this->config->get('config_language'));

			// PAY-002: keep this predicate aligned with extension/pumb_credit and
			// checkout/payment_method.php. Those two files intentionally stay out of WP5.
			$pay002_pumb_configured = (bool)$this->config->get('payment_pumb_credit_status');
			foreach (['payment_pumb_credit_api_base', 'payment_pumb_credit_oauth_url', 'payment_pumb_credit_oauth_username', 'payment_pumb_credit_oauth_password', 'payment_pumb_credit_point_of_sale_code', 'payment_pumb_credit_partner_name'] as $pay002_pumb_key) {
				if (trim((string)$this->config->get($pay002_pumb_key)) === '') {
					$pay002_pumb_configured = false;
					break;
				}
			}
			$data['pay002_pumb_visible'] =
				$pay002_pumb_configured &&
				$pay001_mono_currency === 'UAH' &&
				((bool)$this->config->get('payment_pumb_credit_public') || !empty($this->session->data['pay002_pumb_preview']));
			$pay002_pumb_raw_terms = json_decode((string)$this->config->get('payment_pumb_credit_terms'), true);
			$pay002_pumb_terms = is_array($pay002_pumb_raw_terms) ? array_values(array_unique(array_map('intval', $pay002_pumb_raw_terms))) : [3, 4, 5];
			$pay002_pumb_terms = array_values(array_filter([3, 4, 5], static fn(int $term): bool => in_array($term, $pay002_pumb_terms, true)));
			if (!$pay002_pumb_terms) $pay002_pumb_terms = [3, 4, 5];
			$data['pay002_pumb_terms'] = $pay002_pumb_terms;
			$data['pay002_pumb_price'] = round($pay001_mono_price, 2);
			$data['pay002_pumb_min_total'] = max(500.0, (float)$this->config->get('payment_pumb_credit_min_total'));
			$data['pay002_pumb_max_total'] = max(0.0, (float)$this->config->get('payment_pumb_credit_max_total'));
			$data['pay002_pumb_cart_total'] = round((float)$this->cart->getSubTotal(), 2);
			$data['pay002_pumb_in_stock'] = (int)$product_info['quantity'] > 0;
			$data['pay002_pumb_checkout'] = $pay001_mono_base_url . '/index.php?route=checkout/checkout&language=' . rawurlencode((string)$this->config->get('config_language'));

			if ($this->config->get('config_tax')) {
				$data['tax'] = $this->currency->format((float)$product_info['special'] ? $product_info['special'] : $product_info['price'], $this->session->data['currency']);
			} else {
				$data['tax'] = false;
			}

			$discounts = $this->model_catalog_product->getDiscounts($product_id);

			$data['discounts'] = [];

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				foreach ($discounts as $discount) {
					$data['discounts'][] = ['price' => $this->currency->format($this->tax->calculate($discount['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'])] + $discount;
				}
			}

			$data['options'] = [];

			// Check if product is variant
			if ($product_info['master_id']) {
				$master_id = (int)$product_info['master_id'];
			} else {
				$master_id = (int)$this->request->get['product_id'];
			}

			$product_options = $this->model_catalog_product->getOptions($master_id);

			foreach ($product_options as $option) {
				if ((int)$this->request->get['product_id'] && !isset($product_info['override']['variant'][$option['product_option_id']])) {
					$product_option_value_data = [];

					foreach ($option['product_option_value'] as $option_value) {
						if (!$option_value['subtract'] || ($option_value['quantity'] > 0)) {
							if ((($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) && (float)$option_value['price']) {
								$price = $this->currency->format($this->tax->calculate($option_value['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
							} else {
								$price = false;
							}

							if ($option_value['image'] && is_file(DIR_IMAGE . html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8'))) {
								$image = $option_value['image'];
							} else {
								$image = '';
							}

							$product_option_value_data[] = [
								'image' => $this->model_tool_image->resize($image, 50, 50),
								'price' => $price
							] + $option_value;
						}
					}

					$data['options'][] = ['product_option_value' => $product_option_value_data] + $option;
				}
			}

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

			// Subscriptions
			$data['subscription_plans'] = [];

			$results = $this->model_catalog_product->getSubscriptions($product_id);

			foreach ($results as $result) {
				$description = '';

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					if ($result['duration']) {
						$price = ($product_info['special'] ?: $product_info['price']) / $result['duration'];
					} else {
						$price = ($product_info['special'] ?: $product_info['price']);
					}

					$price = $this->currency->format($this->tax->calculate($price, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					$cycle = $result['cycle'];
					$frequency = $this->language->get('text_' . $result['frequency']);
					$duration = $result['duration'];

					if ($duration) {
						$description = sprintf($this->language->get('text_subscription_duration'), $price, $cycle, $frequency, $duration);
					} else {
						$description = sprintf($this->language->get('text_subscription_cancel'), $price, $cycle, $frequency);
					}
				}

				$data['subscription_plans'][] = ['description' => $description] + $result;
			}

			if ($product_info['minimum']) {
				$data['minimum'] = $product_info['minimum'];
			} else {
				$data['minimum'] = 1;
			}

						$data['share'] = $product_url;


			$data['attribute_groups'] = $this->model_catalog_product->getAttributes($product_id);

			$data['related'] = $this->load->controller('product/related');

			$data['tags'] = [];

			if ($product_info['tag']) {
				$tags = explode(',', $product_info['tag']);

				foreach ($tags as $tag) {
					$data['tags'][] = [
						'tag'  => trim($tag),
						'href' => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&tag=' . trim($tag))
					];
				}
			}

			if ($this->config->get('config_product_report_status')) {
				$this->model_catalog_product->addReport($this->request->get['product_id'], oc_get_ip());
			}

						
			$data['store_home'] = rtrim($this->config->get('config_url'), '/') . '/';

			$data['store_logo'] = '';


			if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
				$data['store_logo'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
			}
			$data['language'] = (string)$this->language->get('code');

			$data['column_left'] = $this->load->controller('common/column_left');

			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/product', $data));
		} else {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		return null;
	}
}
