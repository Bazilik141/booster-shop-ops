    /** Internal loader call only; HTTP dispatch supplies no context arguments. */
    public function pay003Read(array $context = [], array $transaction = []): array {
        if (!$context || !$transaction) return [];
        $this->load->model('checkout/credit');
        $current = $this->model_checkout_credit->context((int)($context['id'] ?? 0));
        if (!$current || $current['provider'] !== '__PROVIDER__') return [];
        $tx = $this->model_checkout_credit->transaction($current);
        if (!$tx || (int)$tx['__PROVIDER___transaction_id'] !== (int)($transaction['__PROVIDER___transaction_id'] ?? 0)) return [];
        __READ__
    }
    public function pay003Apply(array $context = [], array $transaction = []): void {
        if (!$context || !$transaction) return;
        $this->load->model('checkout/credit');
        $current = $this->model_checkout_credit->context((int)($context['id'] ?? 0));
        if (!$current || $current['provider'] !== '__PROVIDER__') return;
        $tx = $this->model_checkout_credit->transaction($current);
        if (!$tx || (int)$tx['__PROVIDER___transaction_id'] !== (int)($transaction['__PROVIDER___transaction_id'] ?? 0) || (string)$tx['state'] !== (string)$transaction['state']) return;
        __APPLY__
    }
    private function pay003Reply(array $json): void {
        $this->load->model('checkout/credit');
        $id = (int)($this->session->data['order_id'] ?? 0);
        $c = $this->model_checkout_credit->context($id);
        if ($c && $c['grant']) $json['redirect'] = $this->model_checkout_credit->url($id);
        $this->reply($json);
    }
