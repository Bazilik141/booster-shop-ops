        // PAY-003: validate credit recovery before success can clear any session/cart.
        $pay003_recovery = false;
        $pay003_requested = $this->request->get['credit_order_id'] ?? null;
        $pay003_id = $pay003_requested === null ? (int)($this->session->data['order_id'] ?? 0) : (is_scalar($pay003_requested) && preg_match('/^[1-9][0-9]{0,9}$/D', (string)$pay003_requested) ? (int)$pay003_requested : 0);
        $this->load->model('checkout/credit');
        $pay003_context = $this->model_checkout_credit->context($pay003_id);
        if ($pay003_requested !== null && !$pay003_context) {
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->addHeader('Cache-Control: no-store, private');
            $this->response->setOutput('Посилання недоступне.');
            return;
        }
        if ($pay003_context) {
            $pay003_tx = $this->model_checkout_credit->transaction($pay003_context);
            $pay003_view = $this->model_checkout_credit->present($pay003_context['provider'], $pay003_tx);
            if (!$pay003_view['confirmed']) {
                $this->response->redirect($this->model_checkout_credit->url($pay003_id));
                return;
            }
            $pay003_recovery = $pay003_requested !== null;
            $this->response->addHeader('Cache-Control: no-store, private');
        }
