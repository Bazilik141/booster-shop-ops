    // PAY-002-ORDER-AMOUNT: persisted order total is authoritative, in UAH.
    private function createPayload(array $order, int $term): array {
        if (PHP_INT_SIZE < 8) throw new \RuntimeException('amount_integer_width');
        $target = intdiv($this->pay002Decimal4($order['total'] ?? null) + 50, 100);
        if ($target <= 0) throw new \RuntimeException('amount_nonpositive');
        $products = $this->db->query("SELECT `name`,`quantity`,`price` FROM `" . DB_PREFIX . "order_product` WHERE `order_id`='" . (int)$order['order_id'] . "' ORDER BY `order_product_id` ASC")->rows;
        if (!$products) throw new \RuntimeException('amount_no_goods');
        $lines = []; $weightTotal = 0;
        foreach ($products as $product) {
            $rawQuantity = $product['quantity'] ?? null;
            if ((!is_int($rawQuantity) && !is_string($rawQuantity)) || !preg_match('/^[1-9][0-9]{0,8}$/D', (string)$rawQuantity)) throw new \RuntimeException('amount_quantity');
            $quantity = (int)$rawQuantity;
            $price = $this->pay002Decimal4($product['price'] ?? null);
            $name = (string)($product['name'] ?? '');
            // Keep zero-priced gifts at zero; never invent prices or drop goods.
            if (trim($name) === '' || $price > intdiv(PHP_INT_MAX, $quantity)) throw new \RuntimeException('amount_goods');
            $weight = $price * $quantity;
            if ($weight > PHP_INT_MAX - $weightTotal || $weight > intdiv(PHP_INT_MAX, $target)) throw new \RuntimeException('amount_overflow');
            $weightTotal += $weight;
            $lines[] = ['name' => $name, 'quantity' => $quantity, 'weight' => $weight];
        }
        if ($weightTotal <= 0) throw new \RuntimeException('amount_no_priced_goods');
        // Largest-remainder allocation: exact integer arithmetic, stable row ties.
        $allocated = 0;
        foreach ($lines as &$line) {
            $numerator = $target * $line['weight'];
            $line['cents'] = intdiv($numerator, $weightTotal);
            $line['remainder'] = $numerator % $weightTotal;
            $allocated += $line['cents'];
        }
        unset($line);
        $rank = array_keys($lines);
        usort($rank, static function (int $a, int $b) use ($lines): int {
            return ($lines[$b]['remainder'] <=> $lines[$a]['remainder']) ?: ($a <=> $b);
        });
        $left = $target - $allocated;
        if ($left < 0 || $left >= count($lines)) throw new \RuntimeException('amount_remainder');
        for ($i = 0; $i < $left; $i++) $lines[$rank[$i]]['cents']++;
        $goods = []; $check = 0;
        foreach ($lines as $line) {
            $quantity = $line['quantity'];
            $unit = intdiv($line['cents'], $quantity);
            $extra = $line['cents'] % $quantity;
            if ($unit <= 0 && $line['weight'] > 0) throw new \RuntimeException('amount_unrepresentable_goods');
            // At most two price buckets per product, preserving its unit count.
            // Example: 3 units for 1000 UAH = 2 x 333.33 + 1 x 333.34.
            if ($quantity - $extra > 0) $goods[] = ['name' => $line['name'], 'count' => $quantity - $extra, 'amount' => $unit / 100];
            if ($extra > 0) $goods[] = ['name' => $line['name'], 'count' => $extra, 'amount' => ($unit + 1) / 100];
            $check += $unit * $quantity + $extra;
        }
        if ($check !== $target) throw new \RuntimeException('amount_mismatch');
        $total = $target / 100;
        return ['store_order_id' => 'OC-' . (int)$order['order_id'], 'point_of_sale_code' => (string)$this->config->get('payment_pumb_credit_point_of_sale_code'), 'partner_name' => (string)$this->config->get('payment_pumb_credit_partner_name'), 'channel_type' => 'INTERNET', 'flow' => ['type' => 'DIGITAL_SF'], 'customer' => ['phone' => $this->phone((string)$order['telephone'])], 'invoices' => [['date' => date('Y-m-d'), 'invoice_number' => 'OC-' . (int)$order['order_id'], 'goods' => $goods, 'total_amount' => $total]], 'credit_request' => ['term' => $term, 'amount' => $total]];
    }
    /** Parse OpenCart DECIMAL(...,4) without floating-point money arithmetic. */
    private function pay002Decimal4(mixed $value): int {
        if (is_float($value)) {
            if (!is_finite($value) || $value < 0 || $value >= 1000000000) throw new \RuntimeException('amount_decimal');
            $value = number_format($value, 4, '.', '');
        }
        if (!is_string($value) && !is_int($value)) throw new \RuntimeException('amount_decimal');
        if (!preg_match('/^([0-9]{1,9})(?:\.([0-9]{1,4}))?$/D', (string)$value, $m)) throw new \RuntimeException('amount_decimal');
        return (int)$m[1] * 10000 + (int)str_pad($m[2] ?? '', 4, '0');
    }
