<?php
namespace Opencart\Catalog\Model\Checkout;

/** PAY-003: ownership, presentation and bounded read-state fallback. No create API. */
class Credit extends \Opencart\System\Engine\Model {
    public function url(int $id, string $action = ''): string {
        return str_replace('&amp;', '&', $this->url->link('checkout/credit' . $action,
            'language=' . rawurlencode((string)$this->config->get('config_language')) . '&order_id=' . $id, true));
    }

    public function context(int $id): array {
        if ($id < 1) return [];
        // Read only the ownership/payment fields, never customer contact details.
        $order = $this->db->query("SELECT order_id, customer_id, store_id, payment_method FROM `" . DB_PREFIX . "order` WHERE order_id='" . $id . "' LIMIT 1")->row ?? [];
        if (!$order || (int)$order['store_id'] !== (int)$this->config->get('config_store_id')) return [];
        $customer = $this->customer->isLogged() ? (int)$this->customer->getId() : 0;
        if ($customer && $customer !== (int)$order['customer_id']) return [];
        $grant = $this->session->data['pay003_orders'][$id] ?? [];
        $owned = $customer > 0 && $customer === (int)$order['customer_id'];
        $active = (int)($this->session->data['order_id'] ?? 0) === $id;
        $remembered = (int)($grant['expires'] ?? 0) > time();
        if (!$owned && !$active && !$remembered) return [];
        $payment = $order['payment_method'];
        if (is_string($payment)) $payment = json_decode($payment, true);
        if (!is_array($payment) || !is_string($payment['code'] ?? null) || !preg_match('/^(mono_chast|pumb_credit)\.(?:mono_chast|pumb_credit)_[345]$/D', $payment['code'], $m)) return [];
        $provider = $m[1];
        // Reject mixed provider prefixes even if the string otherwise has a valid shape.
        if (strpos((string)$payment['code'], $provider . '.' . $provider . '_') !== 0) return [];
        if ($remembered && ($grant['provider'] ?? '') !== $provider) return [];
        return ['id' => $id, 'provider' => $provider, 'order' => $order,
            'grant' => $remembered ? $grant : [], 'active' => $active];
    }

    /** Called only after the provider has validated a checkout request. */
    public function remember(int $id): void {
        $c = $this->context($id);
        if (!$c || !$c['active']) return;
        $grants = $this->session->data['pay003_orders'] ?? [];
        foreach ($grants as $key => $grant) if ((int)($grant['expires'] ?? 0) <= time()) unset($grants[$key]);
        if (!isset($grants[$id])) $grants[$id] = ['provider' => $c['provider'],
            'mode' => $c['provider'] === 'pumb_credit' ? (int)(bool)$this->config->get('payment_pumb_credit_test_mode') : -1,
            'endpoint' => $this->endpoint($c['provider']), 'cart' => $this->cartHash(), 'expires' => time() + 86400];
        // Session-only guest recovery: no bearer token or customer data in URLs.
        while (count($grants) > 20) unset($grants[array_key_first($grants)]);
        $this->session->data['pay003_orders'] = $grants;
    }

    public function transaction(array $c): array {
        if (!$c) return [];
        $provider = $c['provider'];
        $table = $provider === 'pumb_credit' ? 'pumb_credit_transaction' : 'mono_chast_transaction';
        $rows = $this->db->query("SELECT * FROM `" . DB_PREFIX . $table . "` WHERE order_id='" . (int)$c['id'] . "' ORDER BY " . $table . "_id DESC LIMIT 3")->rows;
        if ($provider === 'pumb_credit' && isset($c['grant']['mode'])) {
            $rows = array_values(array_filter($rows, static function ($r) use ($c) { return (int)$r['is_test'] === (int)$c['grant']['mode']; }));
        }
        // Never guess between multiple environments or historical applications.
        return count($rows) === 1 ? $rows[0] : [];
    }

    public function endpoint(string $provider): string {
        $identity = [(string)$this->config->get('payment_' . $provider . '_api_base')];
        if ($provider === 'mono_chast') $identity[] = (string)$this->config->get('payment_mono_chast_store_id');
        else {
            $identity[] = (string)$this->config->get('payment_pumb_credit_point_of_sale_code');
            $identity[] = (string)$this->config->get('payment_pumb_credit_oauth_url');
            $identity[] = (int)(bool)$this->config->get('payment_pumb_credit_test_mode');
        }
        return hash('sha256', (string)json_encode($identity));
    }

    private function cartHash(): string {
        $items = [];
        foreach ($this->cart->getProducts() as $p) $items[] = [(int)$p['product_id'], (int)$p['quantity'], $p['option'] ?? [], $p['subscription_plan_id'] ?? 0];
        return hash('sha256', (string)json_encode($items));
    }

    public function detach(array $c, array $tx): void {
        if (!$c['active'] || !$tx || !$c['grant']) return;
        // Creation may still own this checkout in another request. Complete()
        // detaches once a real application is visible, including callback races.
        if ((string)$tx['state'] === 'CREATING') return;
        $payload = json_decode((string)($tx['payload'] ?? ''), true);
        if (is_array($payload) && !isset($payload['pay003'])) {
            $payload['pay003'] = ['endpoint'=>$c['grant']['endpoint'], 'mode'=>$c['grant']['mode']];
            $table = $c['provider'] . '_transaction';
            $this->db->query("UPDATE `" . DB_PREFIX . $table . "` SET payload='" . $this->db->escape((string)json_encode($payload, JSON_UNESCAPED_UNICODE)) . "' WHERE " . $table . "_id='" . (int)$tx[$table . '_id'] . "' AND MD5(COALESCE(payload,''))='" . md5((string)($tx['payload'] ?? '')) . "'");
        }
        // Freeze checkout ownership after an application exists. Preserve a cart
        // changed in another tab; never clear new purchases during recovery.
        if (!in_array((string)$tx['state'], ['CREATE_FAILED', 'CREATING'], true)) {
            if (hash_equals((string)$c['grant']['cart'], $this->cartHash())) $this->cart->clear();
        }
        unset($this->session->data['order_id']);
        foreach (['payment_method','payment_methods','shipping_method','shipping_methods','comment','agree','rd13_receiver_override','coupon','reward','welcome_coupon_pending','welcome_coupon_applied','welcome_coupon_error'] as $key) unset($this->session->data[$key]);
    }

    /** Pure allowlisted state projection; unknown states are never success. */
    public function present(string $provider, array $tx): array {
        $state = (string)($tx['state'] ?? ''); $sub = (string)($tx['order_sub_state'] ?? '');
        $result = ['kind' => 'review', 'confirmed' => false, 'poll' => false,
            'title' => 'Потрібна перевірка заявки',
            'message' => 'Не вдалося однозначно визначити стан заявки. Не оформлюйте її повторно — зверніться до підтримки магазину.'];
        if ($state === 'CREATING') return ['kind'=>'creating','confirmed'=>false,'poll'=>true,'title'=>'Заявка створюється','message'=>'Очікуємо результат від банку. Не оформлюйте заявку повторно; стан оновиться автоматично.'];
        if (!$tx || $state === 'CREATE_FAILED') return $result;
        $waiting = $provider === 'pumb_credit' ? in_array($state, ['IN_PROGRESS','WAITING_CLIENT'], true) : ($state === 'IN_PROCESS' && in_array($sub, ['WAITING_FOR_CLIENT',''], true));
        $confirmed = $provider === 'pumb_credit' ? in_array($state, ['WAITING_STORE_CONFIRM','FUNDED'], true) : (($state === 'IN_PROCESS' && $sub === 'WAITING_FOR_STORE_CONFIRM') || ($state === 'SUCCESS' && in_array($sub, ['ACTIVE','DONE'], true)));
        $returned = $provider === 'pumb_credit' ? $state === 'REFUND_FINISHED' : ($state === 'SUCCESS' && $sub === 'RETURNED');
        $failed = $provider === 'pumb_credit' ? in_array($state, ['CLIENT_NOT_FOUND','REJECTED','OVER_LIMIT','NO_LIMIT','IDENTIFICATION_FAILED','PUSH_TIMEOUT','FAIL_OTP','CONFIRM_TIME_EXPIRED','CANCELED_BY_CLIENT','CANCELED_BY_STORE','FAIL'], true) : str_starts_with($state, 'FAIL');
        if ($waiting) return ['kind'=>'waiting','confirmed'=>false,'poll'=>true,'title'=>'Підтвердьте покупку в застосунку банку','message'=>'Відкрийте застосунок ' . ($provider === 'pumb_credit' ? 'ПУМБ' : 'monobank') . ' та перевірте заявку. Після підтвердження ми покажемо замовлення. Не потрібно оформлювати його повторно.'];
        if ($confirmed) return ['kind'=>'confirmed','confirmed'=>true,'poll'=>false,'title'=>'Підтвердження отримано','message'=>'Замовлення прийнято. Магазин готує наступний крок оформлення та відправлення.'];
        if ($returned) return ['kind'=>'returned','confirmed'=>false,'poll'=>false,'title'=>'Повернення завершено','message'=>'Повернення за цією заявкою завершено. Якщо маєте запитання, зверніться до підтримки магазину.'];
        if ($failed) {
            $message = 'Оформлення оплати частинами не завершено. Зверніться до підтримки магазину, щоб узгодити подальшу оплату замовлення.';
            if (in_array($state, ['PUSH_TIMEOUT','CONFIRM_TIME_EXPIRED'], true) || strpos($sub, 'TIMEOUT') !== false) $message = 'Час підтвердження заявки минув. Зверніться до підтримки магазину — перевіримо замовлення та подальші дії.';
            if (in_array($state, ['NO_LIMIT','OVER_LIMIT'], true)) $message = 'Банк не підтвердив доступний ліміт для цієї покупки. Перевірте ліміт у застосунку та зверніться до підтримки магазину щодо іншого способу оплати.';
            if ($state === 'CLIENT_NOT_FOUND') $message = 'Банк не знайшов клієнта за переданими даними. Перевірте номер у застосунку банку та зверніться до підтримки магазину.';
            return ['kind'=>'failed','confirmed'=>false,'poll'=>false,'title'=>'Заявку не підтверджено','message'=>$message];
        }
        return $result;
    }

    public function refresh(array $c, array $tx): bool {
        if (!$this->present($c['provider'], $tx)['poll']) return false;
        $p = $c['provider'];
        if (!$this->config->get('payment_' . $p . '_status')) return false;
        if ($p === 'pumb_credit' && (int)$tx['is_test'] !== (int)(bool)$this->config->get('payment_pumb_credit_test_mode')) return false;
        $saved = json_decode((string)($tx['payload'] ?? ''), true);
        $endpoint = $c['grant']['endpoint'] ?? $saved['pay003']['endpoint'] ?? '';
        // Historical rows without environment evidence stay callback/read-only.
        if (!is_string($endpoint) || !hash_equals($endpoint, $this->endpoint($p))) return false;
        $bankId = (string)($p === 'pumb_credit' ? ($tx['cap_id'] ?? '') : ($tx['mono_order_id'] ?? ''));
        if ($bankId === '' || str_starts_with($bankId, 'PENDING-')) return false;
        // Lock and timestamp span browser tabs AND PHP sessions; no schema change.
        $key = hash('sha256', $p . ':' . $this->endpoint($p) . ':' . $bankId);
        $handle = @fopen(DIR_CACHE . 'pay003-poll-' . $key . '.lock', 'c+');
        if (!$handle) return false;
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return false; }
        $started = false;
        try {
            $last = (int)stream_get_contents($handle);
            if (time() - $last < 30) return false;
            $started = true;
            rewind($handle); ftruncate($handle, 0); fwrite($handle, (string)time()); fflush($handle);
            $response = $this->load->controller('extension/' . $p . '/payment/' . $p . '.pay003Read', $c, $tx);
            if (!is_array($response) || (int)($response['http'] ?? 0) !== 200 || !is_array($response['body'] ?? null)) throw new \RuntimeException('bank_state_unavailable');
            return $this->persist($c, $tx, $response);
        } finally {
            // Even failures and timeouts consume the interval; no rapid retries.
            if ($started) { rewind($handle); ftruncate($handle, 0); fwrite($handle, (string)time()); fflush($handle); }
            flock($handle, LOCK_UN); fclose($handle);
        }
    }

    private function persist(array $c, array $tx, array $response): bool {
        $p = $c['provider']; $body = $response['body'];
        if (!is_string($body['state'] ?? null) || strlen($body['state']) > 64) return false;
        $state = $body['state']; $sub = is_string($body['order_sub_state'] ?? null) ? $body['order_sub_state'] : '';
        if (strlen($sub) > 128) return false;
        $next = array_merge($tx, ['state'=>$state,'order_sub_state'=>$sub]);
        if ($this->present($p, $next)['kind'] === 'review') return false;
        $table = $p . '_transaction'; $pk = $table . '_id';
        $previous = json_decode((string)($tx['payload'] ?? ''), true);
        $payload = is_array($previous) ? $previous : [];
        $payload['poll'] = $response;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) return false;
        $fields = "state='" . $this->db->escape($state) . "', payload='" . $this->db->escape($json) . "', date_modified=NOW()";
        if ($p === 'mono_chast') $fields .= ",order_sub_state='" . $this->db->escape($sub) . "'";
        if ($p === 'pumb_credit' && is_array($body['guarantee_letter'] ?? null)) {
            $letter = json_encode($body['guarantee_letter'], JSON_UNESCAPED_UNICODE);
            if (is_string($letter)) $fields .= ",guarantee_letter='" . $this->db->escape($letter) . "'";
            $number = $body['guarantee_letter']['content']['customer_agreement']['number'] ?? '';
            if (is_string($number) && $number !== '') $fields .= ",agreement_number='" . $this->db->escape($number) . "'";
        }
        // Compare-and-swap: a callback arriving while GET is in flight wins.
        $this->db->query("UPDATE `" . DB_PREFIX . $table . "` SET " . $fields . " WHERE " . $pk . "='" . (int)$tx[$pk] . "' AND state='" . $this->db->escape((string)$tx['state']) . "' AND date_modified='" . $this->db->escape((string)$tx['date_modified']) . "' AND MD5(COALESCE(payload,''))='" . md5((string)($tx['payload'] ?? '')) . "'");
        if (!$this->db->countAffected()) return false;
        if ($p === 'mono_chast') {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "mono_chast_event` SET mono_chast_transaction_id='" . (int)$tx[$pk] . "',order_id='" . (int)$c['id'] . "',store_order_id='" . $this->db->escape((string)$tx['store_order_id']) . "',mono_order_id='" . $this->db->escape((string)$tx['mono_order_id']) . "',event_source='pay003_poll',state='" . $this->db->escape($state) . "',order_sub_state='" . $this->db->escape($sub) . "',trace_id='',http_status='200',payload='{}',date_added=NOW()");
        }
        $this->load->controller('extension/' . $p . '/payment/' . $p . '.pay003Apply', $c, $next);
        return true;
    }
}
