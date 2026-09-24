<?php
namespace Opencart\Catalog\Controller\Checkout;

/** PAY-003 shared waiting page. No create, cancel, shipment or refund actions. */
class Credit extends \Opencart\System\Engine\Controller {
    private function context(): array {
        $this->load->model('checkout/credit');
        $raw = $this->request->get['order_id'] ?? $this->session->data['order_id'] ?? 0;
        if (!is_scalar($raw) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$raw)) return [];
        return $this->model_checkout_credit->context((int)$raw);
    }
    private function headers(): void {
        $this->response->addHeader('Cache-Control: no-store, private');
        $this->response->addHeader('X-Robots-Tag: noindex, nofollow');
        $this->response->addHeader('Referrer-Policy: same-origin');
    }
    public function index(): void {
        $this->headers();
        $c = $this->context();
        $data = ['allowed'=>false, 'title'=>'Посилання на заявку недоступне',
            'message'=>'Увійдіть у свій акаунт і відкрийте замовлення в історії. Якщо оформлювали без акаунта та сесія вже завершилась — зверніться до підтримки магазину.'];
        if ($c) {
            $tx = $this->model_checkout_credit->transaction($c);
            $this->model_checkout_credit->detach($c, $tx);
            $view = $this->model_checkout_credit->present($c['provider'], $tx);
            if (empty($this->session->data['pay003_csrf'])) $this->session->data['pay003_csrf'] = bin2hex(random_bytes(24));
            $data = $view + ['allowed'=>true, 'order_id'=>$c['id'],
                'provider'=>$c['provider'] === 'pumb_credit' ? 'ПУМБ' : 'monobank',
                'state_url'=>$this->model_checkout_credit->url($c['id'], '.state'),
                'complete_url'=>$this->model_checkout_credit->url($c['id'], '.complete'),
                'csrf'=>$this->session->data['pay003_csrf'],
                'test'=>$c['provider'] === 'pumb_credit' && !empty($tx['is_test'])];
        } else $this->response->addHeader('HTTP/1.1 404 Not Found');
        $data['account'] = $this->url->link('account/order', 'language=' . rawurlencode((string)$this->config->get('config_language')) . '&customer_token=' . rawurlencode((string)($this->session->data['customer_token'] ?? '')), true);
        $data['home'] = $this->url->link('common/home', 'language=' . rawurlencode((string)$this->config->get('config_language')), true);
        $this->document->setTitle('Статус оплати частинами');
        $this->document->addStyle('catalog/view/stylesheet/pay003-credit.css?v=20260831');
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('checkout/credit', $data));
    }
    public function state(): void {
        $this->headers();
        $c = $this->context();
        $this->response->addHeader('Content-Type: application/json');
        if (!$c) { $this->response->addHeader('HTTP/1.1 404 Not Found'); $this->response->setOutput('{"error":"access_denied"}'); return; }
        $tx = $this->model_checkout_credit->transaction($c);
        $refresh = ($this->request->server['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $token = $this->request->post['csrf'] ?? '';
        if ($refresh && (!is_string($token) || $token === '' || !hash_equals((string)($this->session->data['pay003_csrf'] ?? ''), $token))) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden'); $this->response->setOutput('{"error":"access_denied"}'); return;
        }
        $offline = false;
        if ($refresh) {
            try { $this->model_checkout_credit->refresh($c, $tx); }
            catch (\Throwable $e) { $offline = true; }
            $tx = $this->model_checkout_credit->transaction($c);
        }
        $view = $this->model_checkout_credit->present($c['provider'], $tx);
        $view['offline'] = $offline;
        $view['redirect'] = $view['confirmed'] ? $this->model_checkout_credit->url($c['id'], '.complete') : null;
        // Only display text and booleans leave this endpoint, never bank payloads.
        $this->response->setOutput(json_encode($view, JSON_UNESCAPED_UNICODE));
    }
    public function complete(): void {
        $this->headers(); $c = $this->context();
        if (!$c) { $this->response->addHeader('HTTP/1.1 404 Not Found'); $this->response->setOutput('Посилання недоступне.'); return; }
        $tx = $this->model_checkout_credit->transaction($c);
        $view = $this->model_checkout_credit->present($c['provider'], $tx);
        if (!$view['confirmed']) { $this->response->redirect($this->model_checkout_credit->url($c['id'])); return; }
        $this->model_checkout_credit->detach($c, $tx);
        $this->response->redirect($this->url->link('checkout/success', 'language=' . rawurlencode((string)$this->config->get('config_language')) . '&credit_order_id=' . $c['id'], true));
    }
}
