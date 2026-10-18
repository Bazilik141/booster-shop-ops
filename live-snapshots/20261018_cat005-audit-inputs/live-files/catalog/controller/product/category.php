<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Category
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Category extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->load->language('product/category');

		if (isset($this->request->get['path'])) {
			$path = (string)$this->request->get['path'];
		} else {
			$path = '';
		}

		if (isset($this->request->get['filter'])) {
			$filter = $this->request->get['filter'];
		} else {
			$filter = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'p.sort_order';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['limit']) && (int)$this->request->get['limit']) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = $this->config->get('config_pagination');
		}

		// Category
		$parts = explode('_', $path);

		$category_id = (int)array_pop($parts);

		$this->load->model('catalog/category');

		$category_info = $this->model_catalog_category->getCategory($category_id);

				if ($category_info) {
			$category_url = $this->url->link('product/category', 'path=' . $path);

			$this->document->setTitle($category_info['meta_title']);
			$this->document->setDescription($category_info['meta_description']);
			$this->document->setKeywords($category_info['meta_keyword']);

			$data['breadcrumbs'] = [];


			$data['breadcrumbs'][] = [
				'text' => $this->language->get('text_home'),
				'href' => rtrim($this->config->get('config_url'), '/') . '/'
			];

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$path = '';

			foreach ($parts as $path_id) {
				if (!$path) {
					$path = (int)$path_id;
				} else {
					$path .= '_' . (int)$path_id;
				}

				$parent_info = $this->model_catalog_category->getCategory((int)$path_id);

				if ($parent_info) {
					$data['breadcrumbs'][] = [
						'text' => $parent_info['name'],
						'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $path . $url)
					];
				}
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			// Set the last category breadcrumb
			$data['breadcrumbs'][] = [
				'text' => $category_info['name'],
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . $url)
			];

			$data['heading_title'] = $category_info['name'];
			$data['category_heading_short'] = trim(preg_replace('/\s+TCG\b/i', '', $category_info['name']));
			$data['category_is_subcategory'] = !empty($category_info['parent_id']);

			$data['text_compare'] = sprintf($this->language->get('text_compare'), isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0);

			// Image
			$this->load->model('tool/image');

			if (!empty($category_info['image']) && is_file(DIR_IMAGE . html_entity_decode($category_info['image'], ENT_QUOTES, 'UTF-8'))) {
				$data['image'] = $this->model_tool_image->resize($category_info['image'], $this->config->get('config_image_category_width'), $this->config->get('config_image_category_height'));
			} else {
				$data['image'] = '';
			}

			$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');
			$data['compare'] = $this->url->link('product/compare', 'language=' . $this->config->get('config_language'));

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['categories'] = [];

			// Product
			$this->load->model('catalog/product');

			$results = $this->model_catalog_category->getCategories($category_id);

			// R-03 sibling fallback: if the current category has no children,
			// show its parent's children so subcategory navigation stays
			// visible inside leaf subcategories.
			$child_path_prefix = (string) ($this->request->get['path'] ?? '');

			if (empty($results) && !empty($category_info['parent_id'])) {
				$results = $this->model_catalog_category->getCategories((int) $category_info['parent_id']);
				// Siblings share the parent path, not the current path.
				$path_parts_for_siblings = explode('_', $child_path_prefix);
				array_pop($path_parts_for_siblings);
				$child_path_prefix = implode('_', $path_parts_for_siblings);
			}

			foreach ($results as $result) {
				$filter_data = [
					'filter_category_id'  => $result['category_id'],
					'filter_sub_category' => false
				];
				$sub_category_count = (int) $this->model_catalog_product->getTotalProducts($filter_data);

				$href_path = $child_path_prefix !== '' ? $child_path_prefix . '_' . $result['category_id'] : (string) $result['category_id'];

				$data['categories'][] = [
					'name'          => $result['name'] . ($this->config->get('config_product_count') ? ' (' . $sub_category_count . ')' : ''),
					'href'          => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $href_path . $url),
					'category_id'   => $result['category_id'],
					'product_count' => $sub_category_count
				];
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			// Product
			$data['products'] = [];

			$filter_data = [
				'filter_category_id'  => $category_id,
				'filter_sub_category' => false,
				'filter_filter'       => $filter,
				'sort'                => $sort,
				'order'               => $order,
				'start'               => ($page - 1) * $limit,
				'limit'               => $limit
			];

			$results = $this->model_catalog_product->getProducts($filter_data);

			foreach ($results as $result) {
				$description = trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')));

				if (oc_strlen($description) > $this->config->get('config_product_description_length')) {
					$description = oc_substr($description, 0, $this->config->get('config_product_description_length')) . '..';
				}

				if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $result['image'];
				} else {
					$image = 'placeholder.png';
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
				} else {
					$tax = false;
				}

				$product_data = [
					'description' => $description,
					'thumb'       => $this->model_tool_image->resize($image, $this->config->get('config_image_product_width'), $this->config->get('config_image_product_height')),
					'price'       => $price,
					'special'     => $special,
					'price_value'   => (float)$result['price'],
					'special_value' => (float)($result['special'] ?? 0),
					'quantity'      => isset($result['quantity']) ? (int)$result['quantity'] : 1,
					'stock'         => $result['stock_status'] ?? '',
					'stock_status_id' => isset($result['stock_status_id']) ? (int)$result['stock_status_id'] : 0,
					'date_available'  => $result['date_available'] ?? '',
					'tax'         => $tax,
					'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'])

				] + $result;

				$data['products'][] = $this->load->controller('product/thumb', $product_data);
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = [];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.sort_order&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=pd.name&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=pd.name&order=DESC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.price&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.price&order=DESC' . $url)
			];

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = [
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=rating&order=DESC' . $url)
				];

				$data['sorts'][] = [
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=rating&order=ASC' . $url)
				];
			}

			$data['sorts'][] = [
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.model&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.model&order=DESC' . $url)
			];

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			$data['limits'] = [];

			$limits = array_unique([$this->config->get('config_pagination'), 25, 50, 75, 100]);

			sort($limits);

			foreach ($limits as $value) {
				$data['limits'][] = [
					'text'  => $value,
					'value' => $value,
					'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . $url . '&limit=' . $value)
				];
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);

			$data['pagination'] = $this->load->controller('common/pagination', [
				'total' => $product_total,
				'page'  => $page,
				'limit' => $limit,
				'url'   => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . $url . '&page={page}')
			]);

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			$data['product_total'] = $product_total;
			$data['current_sort'] = $sort . '-' . $order;
			$data['products_total_label'] = 'товарів';

			$plain_description = trim(preg_replace('/\s+/', ' ', strip_tags($data['description'])));
			$data['tagline'] = '';

			if ($plain_description !== '') {
				$dot_pos = strpos($plain_description, '.');

				if ($dot_pos !== false && $dot_pos < 140) {
					$data['tagline'] = trim(substr($plain_description, 0, $dot_pos + 1));
				} else {
					$data['tagline'] = trim(oc_substr($plain_description, 0, 120));

					if (oc_strlen($plain_description) > 120) {
						$data['tagline'] .= '...';
					}
				}
			}

			$current_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
			$current_request_path = rtrim($current_request_path, '/');

			$data['sub_categories'] = [];

			foreach ($data['categories'] as $category) {
				$category_href = html_entity_decode($category['href'], ENT_QUOTES, 'UTF-8');
				$category_path = parse_url($category_href, PHP_URL_PATH) ?: '';
				$category_path = rtrim($category_path, '/');
				$category_name_source = trim(preg_replace('/\s*\(\d+\)\s*$/', '', html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8')));
				$category_name = str_ireplace(
					[
						'Pokémon TCG',
						'Pokemon TCG',
						'Pokémon',
						'Pokemon',
						'Покемон TCG',
						'Покемон',
						'One Piece Card Game',
						'One Piece',
						'TCG'
					],
					'',
					$category_name_source
				);
				$category_name = trim(preg_replace('/\s+/', ' ', $category_name));
				$category_name = trim(preg_replace('/(^\s*[-–—|\/]\s*)|(\s*[-–—|\/]\s*$)/u', '', $category_name));

				if ($category_name === '') {
					$category_name = $category_name_source;
				}

				$data['sub_categories'][] = [
					'name'          => $category_name,
					'href'          => $category['href'],
					'active'        => $category_path !== '' && $current_request_path === $category_path,
					'product_count' => (int) ($category['product_count'] ?? 0)
				];
			}

			// CAT-002-5c: category accent routing
			$category_code_source = strtolower($category_info['name'] . ' ' . $path . ' ' . urldecode($_SERVER['REQUEST_URI'] ?? ''));

			if ($category_id === 65 || strpos($category_code_source, 'yu-gi-oh') !== false || strpos($category_code_source, 'yugioh') !== false) {
				$data['category_code'] = 'yugioh';
			} elseif ($category_id === 67 || strpos($category_code_source, 'magic') !== false || strpos($category_code_source, 'mtg') !== false) {
				$data['category_code'] = 'mtg';
			} elseif ($category_id === 70 || strpos($category_code_source, 'acsesuary') !== false || strpos($category_code_source, 'accessories') !== false) {
				$data['category_code'] = 'accessories';
			} elseif ($category_id === 66 || strpos($category_code_source, 'more-tcg') !== false) {
				$data['category_code'] = 'other-tcg';
			} elseif (strpos($category_code_source, 'pokemon') !== false || strpos($category_code_source, 'pokémon') !== false) {
				$data['category_code'] = 'pokemon';
			} elseif (strpos($category_code_source, 'one-piece') !== false || strpos($category_code_source, 'onepiece') !== false || strpos($category_code_source, 'one piece') !== false) {
				$data['category_code'] = 'onepiece';
			} else {
				$data['category_code'] = '';
			}

			$data['reset_url'] = $category_url;
			$data['active_filters'] = [];

						$has_duplicate_params = isset($this->request->get['filter']) || isset($this->request->get['sort']) || isset($this->request->get['order']) || isset($this->request->get['limit']);

			// Canonical always points to the clean base category URL.
			$this->document->addLink($category_url, 'canonical');

			// Paginated navigation links stay available for UX/crawlers, but canonical remains clean.
			$pagination_link_url = 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path'] . $url;

			if ($page > 1) {
				$this->document->addLink($this->url->link('product/category', $pagination_link_url . (($page - 2) ? '&page=' . ($page - 1) : '')), 'prev');
			}

			if ($limit && ceil($product_total / $limit) > $page) {
				$this->document->addLink($this->url->link('product/category', $pagination_link_url . '&page=' . ($page + 1)), 'next');
			}

			$data['robots'] = $has_duplicate_params ? 'noindex,follow' : 'index,follow';


			$data['sort'] = $sort;
			$data['order'] = $order;
			$data['limit'] = $limit;

			$data['continue'] = rtrim($this->config->get('config_url'), '/') . '/';

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/category', $data));
		} else {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		return null;
	}
}
