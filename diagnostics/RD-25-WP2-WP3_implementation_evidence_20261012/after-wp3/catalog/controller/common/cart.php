<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Cart
 *
 * Can be called from $this->load->controller('common/cart');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Cart extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/cart');

		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		// Cart
		$this->load->model('checkout/cart');

		// Image
		$this->load->model('tool/image');

		// Display prices
		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

			$price_status = true;
		} else {
			$price_status = false;
		}

				$data['text_items'] = 'Мій кошик - ' . $this->currency->format($total, $this->session->data['currency']);


		// Products
		$data['products'] = [];

		$products = $this->model_checkout_cart->getProducts();

		foreach ($products as $product) {
			if ($product['option']) {
				foreach ($product['option'] as $key => $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$product['option'][$key]['value'] = (oc_strlen($value) > 20 ? oc_substr($value, 0, 20) . '..' : $value);
				}
			}

			$subscription = '';

			if ($product['subscription']) {
				if ($product['subscription']['duration']) {
					$subscription .= sprintf($this->language->get('text_subscription_duration'), $price_status ?? $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency'], $product['subscription']['duration']);
				} else {
					$subscription .= sprintf($this->language->get('text_subscription_cancel'), $price_status ?? $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency']);
				}
			}

			// RD-25-WP3 20261012: use the existing row source; LOCK_NB/budget refusal keeps legacy thumb.
			$bs_small = ['bs_fit_thumb' => '', 'bs_media_kind' => 'legacy', 'bs_media_width' => 120, 'bs_media_height' => 120];
			$bs_fit = $this->model_tool_image->resizeFit($product['image'], 104, 130);
			if ($bs_fit !== '' && ($bs_media = $this->model_tool_image->mediaInfo($product['image']))) {
				$bs_scale = min(1.0, 104 / $bs_media['crop_width'], 130 / $bs_media['crop_height']);
				$bs_small = ['bs_fit_thumb' => $bs_fit, 'bs_media_kind' => $bs_media['kind'],
					'bs_media_width' => max(1, (int)floor($bs_media['crop_width'] * $bs_scale)),
					'bs_media_height' => max(1, (int)floor($bs_media['crop_height'] * $bs_scale))];
			}

			$data['products'][] = [
				'thumb'        => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),
				'subscription' => $subscription,
				'price'        => $price_status ? $product['price_text'] : '',
				'total'        => $price_status ? $product['total_text'] : '',
				'href'         => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id'])
			] + $bs_small + $product;
		}

		// Totals
		$data['totals'] = [];
		$data['shipping_pinta_nova_poshta_free_from'] = is_numeric($this->config->get('shipping_pinta_nova_poshta_free_from')) && (float)$this->config->get('shipping_pinta_nova_poshta_free_from') > 0 ? (float)$this->config->get('shipping_pinta_nova_poshta_free_from') : 2000.0;

		foreach ($totals as $total) {
			if ($total['code'] === 'sub_total') {
				$data['sub_total'] = (float)$total['value'];
			}

			$data['totals'][] = ['text' => $this->currency->format($total['value'], $this->session->data['currency'])] + $total;
		}

		$data['list'] = $this->url->link('common/cart.info', 'language=' . $this->config->get('config_language'));
		$data['remove'] = $this->url->link('common/cart.remove', 'language=' . $this->config->get('config_language'));

		$data['cart'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));
		$data['checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));

		return $this->load->view('common/cart', $data);
	}

	/**
	 * Info
	 *
	 * @return void
	 */
	public function info(): void {
		$this->response->setOutput($this->index());
	}

	/**
	 * Remove Product
	 *
	 * @return void
	 */
	public function remove(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->post['key'])) {
	$key = (string)$this->request->post['key'];
} else {
	$key = '';
}


		if (!$this->cart->has($key)) {
			$json['error'] = $this->language->get('error_product');
		}

		if (!$json) {
			$this->cart->remove($key);

			$json['success'] = $this->language->get('text_remove');

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
