<?php
declare(strict_types=1);

// ST-2b.2: checkout/success reload resilience, fiscal copy detection, and success spacing.

function st2b2_fail(string $message): void {
	fwrite(STDERR, "error=" . $message . PHP_EOL);
	exit(1);
}

function st2b2_path(string $relative): string {
	return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function st2b2_read(string $relative): string {
	$path = st2b2_path($relative);

	if (!is_file($path)) {
		st2b2_fail("missing_file:" . $relative);
	}

	$content = file_get_contents($path);

	if ($content === false) {
		st2b2_fail("read_failed:" . $relative);
	}

	return $content;
}

function st2b2_assert_count(string $content, string $needle, int $expected, string $label): void {
	$count = substr_count($content, $needle);

	if ($count !== $expected) {
		st2b2_fail("anchor_count_mismatch:" . $label . ":expected=" . $expected . ":actual=" . $count);
	}
}

function st2b2_replace_once(string $content, string $search, string $replace, string $label): string {
	$variants = [
		$search => $replace,
		str_replace("\r\n", "\n", $search) => str_replace("\r\n", "\n", $replace),
	];

	foreach ($variants as $needle => $value) {
		if (substr_count($content, $needle) === 1) {
			return str_replace($needle, $value, $content);
		}
	}

	$count = substr_count($content, $search) + substr_count($content, str_replace("\r\n", "\n", $search));
	st2b2_fail("anchor_count_mismatch:" . $label . ":expected=1:actual=" . $count);
}

function st2b2_backup_and_write(string $relative, string $content, array &$backups): void {
	$path = st2b2_path($relative);
	$backup = $path . '.st2b2-' . date('Ymd-His') . '.bak';

	if (!copy($path, $backup)) {
		st2b2_fail("backup_failed:" . $relative);
	}

	if (file_put_contents($path, $content) === false) {
		st2b2_fail("write_failed:" . $relative);
	}

	$backups[] = $backup;
}

function st2b2_php_lint(string $relative): void {
	$path = st2b2_path($relative);
	$cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1';
	$output = [];
	$status = 0;

	exec($cmd, $output, $status);

	if ($status !== 0) {
		st2b2_fail("php_lint_failed:" . $relative . ":" . implode(" | ", $output));
	}
}

$success_php = 'catalog/controller/checkout/success.php';
$success_twig = 'catalog/view/template/checkout/success.twig';
$cookie_twig = 'catalog/view/template/common/cookie.twig';

$success_php_content = st2b2_read($success_php);
$success_twig_content = st2b2_read($success_twig);
$cookie_twig_content = st2b2_read($cookie_twig);

$markers = [
	'success_php' => strpos($success_php_content, 'ST-2b.2: keep success order id available for safe re-render after cookie/F5 reload.') !== false,
	'success_twig' => strpos($success_twig_content, 'st2b2-success-spacing') !== false,
	'cookie_twig' => strpos($cookie_twig_content, 'st2b2-success-cookie-fallback') !== false,
];

$applied_count = count(array_filter($markers));

if ($applied_count === count($markers)) {
	echo "done=ok" . PHP_EOL;
	echo "already_applied=yes" . PHP_EOL;
	@unlink(__FILE__);
	exit(0);
}

if ($applied_count > 0) {
	st2b2_fail("partial_st2b2_markers_found");
}

st2b2_assert_count($success_php_content, '$order_id = $this->session->data[\'order_id\'] ?? 0;', 1, 'success_php:order_id_capture');
st2b2_assert_count($success_php_content, '\'payment_code\'    => strtolower(trim($order_info[\'payment_code\'] ?? \'\')),', 1, 'success_php:payment_code_missing_source');
st2b2_assert_count($success_twig_content, "{% if payment_code == 'hutko' %}", 1, 'success_twig:hutko_branch');
st2b2_assert_count($cookie_twig_content, 'window.location.href = link.href;', 2, 'cookie_twig:fallback_navigation');

$success_php_content = st2b2_replace_once(
	$success_php_content,
	"\t\t// R-11: capture order_id BEFORE session clear.\r\n\t\t\$order_id = \$this->session->data['order_id'] ?? 0;",
	"\t\t// ST-2b.2: keep success order id available for safe re-render after cookie/F5 reload.\r\n\t\t\$order_id = (int)(\$this->session->data['order_id'] ?? 0);\r\n\t\t\$session_success_order_id = \$this->getSessionSuccessOrderId();\r\n\t\t\$hutko_return_context = \$this->getHutkoReturnContext();\r\n\t\t\$candidate_order_ids = [];\r\n\r\n\t\tif (\$order_id > 0) {\r\n\t\t\t\$candidate_order_ids[] = \$order_id;\r\n\t\t} else {\r\n\t\t\tif (\$session_success_order_id > 0) {\r\n\t\t\t\t\$candidate_order_ids[] = \$session_success_order_id;\r\n\t\t\t}\r\n\r\n\t\t\tif (!empty(\$hutko_return_context['order_id'])) {\r\n\t\t\t\t\$candidate_order_ids[] = (int)\$hutko_return_context['order_id'];\r\n\t\t\t}\r\n\t\t}\r\n\r\n\t\t\$candidate_order_ids = array_values(array_unique(array_filter(\$candidate_order_ids)));",
	'success_php:order_id_capture_replace'
);

$success_php_content = st2b2_replace_once(
	$success_php_content,
	"\t\tif (isset(\$this->session->data['order_id'])) {\r\n\t\t\t\$this->cart->clear();\r\n",
	"\t\tif (isset(\$this->session->data['order_id'])) {\r\n\t\t\t\$this->cart->clear();\r\n\r\n\t\t\tif (\$order_id > 0) {\r\n\t\t\t\t\$this->session->data['bs_success_order_id'] = \$order_id;\r\n\t\t\t\t\$this->session->data['bs_success_order_at'] = time();\r\n\t\t\t}\r\n",
	'success_php:session_key_set'
);

$success_php_content = st2b2_replace_once(
	$success_php_content,
	"\t\t\$get_method_name = static function (\$method): string {\r\n\t\t\tif (is_array(\$method)) {\r\n\t\t\t\treturn trim((string)(\$method['name'] ?? \$method['title'] ?? \$method['code'] ?? ''));\r\n\t\t\t}\r\n\r\n\t\t\tif (is_string(\$method) && \$method !== '') {\r\n\t\t\t\t\$decoded = json_decode(\$method, true);\r\n\r\n\t\t\t\tif (is_array(\$decoded)) {\r\n\t\t\t\t\treturn trim((string)(\$decoded['name'] ?? \$decoded['title'] ?? \$decoded['code'] ?? ''));\r\n\t\t\t\t}\r\n\r\n\t\t\t\treturn trim(\$method);\r\n\t\t\t}\r\n\r\n\t\t\treturn '';\r\n\t\t};",
	"\t\t\$get_method_name = static function (\$method): string {\r\n\t\t\tif (is_array(\$method)) {\r\n\t\t\t\treturn trim((string)(\$method['name'] ?? \$method['title'] ?? \$method['code'] ?? ''));\r\n\t\t\t}\r\n\r\n\t\t\tif (is_string(\$method) && \$method !== '') {\r\n\t\t\t\t\$decoded = json_decode(\$method, true);\r\n\r\n\t\t\t\tif (is_array(\$decoded)) {\r\n\t\t\t\t\treturn trim((string)(\$decoded['name'] ?? \$decoded['title'] ?? \$decoded['code'] ?? ''));\r\n\t\t\t\t}\r\n\r\n\t\t\t\treturn trim(\$method);\r\n\t\t\t}\r\n\r\n\t\t\treturn '';\r\n\t\t};\r\n\r\n\t\t\$get_method_code = static function (\$method): string {\r\n\t\t\tif (is_array(\$method)) {\r\n\t\t\t\treturn strtolower(trim((string)(\$method['code'] ?? '')));\r\n\t\t\t}\r\n\r\n\t\t\tif (is_string(\$method) && \$method !== '') {\r\n\t\t\t\t\$decoded = json_decode(\$method, true);\r\n\r\n\t\t\t\tif (is_array(\$decoded)) {\r\n\t\t\t\t\treturn strtolower(trim((string)(\$decoded['code'] ?? '')));\r\n\t\t\t\t}\r\n\r\n\t\t\t\treturn strtolower(trim(\$method));\r\n\t\t\t}\r\n\r\n\t\t\treturn '';\r\n\t\t};",
	'success_php:method_code_helper'
);

$success_php_content = st2b2_replace_once(
	$success_php_content,
	"\t\tif (\$order_id) {\r\n\t\t\t\$this->load->model('checkout/order');\r\n\t\t\t\$order_info = \$this->model_checkout_order->getOrder((int)\$order_id);\r\n\r\n\t\t\tif (\$order_info) {",
	"\t\tif (\$candidate_order_ids) {\r\n\t\t\t\$this->load->model('checkout/order');\r\n\t\t\t\$order_info = [];\r\n\r\n\t\t\tforeach (\$candidate_order_ids as \$candidate_order_id) {\r\n\t\t\t\t\$candidate_order_info = \$this->model_checkout_order->getOrder((int)\$candidate_order_id);\r\n\r\n\t\t\t\tif (\$candidate_order_info && \$this->canShowSuccessOrder(\$candidate_order_info, (int)\$candidate_order_id === \$order_id, \$hutko_return_context)) {\r\n\t\t\t\t\t\$order_id = (int)\$candidate_order_id;\r\n\t\t\t\t\t\$order_info = \$candidate_order_info;\r\n\t\t\t\t\t\$this->session->data['bs_success_order_id'] = \$order_id;\r\n\t\t\t\t\t\$this->session->data['bs_success_order_at'] = time();\r\n\t\t\t\t\tbreak;\r\n\t\t\t\t}\r\n\t\t\t}\r\n\r\n\t\t\tif (\$order_info) {",
	'success_php:safe_candidate_loop'
);

$success_php_content = st2b2_replace_once(
	$success_php_content,
	"\t\t\t\t\$currency_code = \$order_info['currency_code'] ?? (\$this->session->data['currency'] ?? \$this->config->get('config_currency'));\r\n\t\t\t\t\$currency_value = (float)(\$order_info['currency_value'] ?? 1);\r\n\r\n\t\t\t\t\$order_data = [\r\n\t\t\t\t\t'order_id'        => (int)\$order_id,\r\n\t\t\t\t\t'shipping_method' => \$get_method_name(\$order_info['shipping_method'] ?? ''),\r\n\t\t\t\t\t'payment_method'  => \$get_method_name(\$order_info['payment_method'] ?? ''),\r\n\t\t\t\t'payment_code'    => strtolower(trim(\$order_info['payment_code'] ?? '')),\r\n\t\t\t\t];",
	"\t\t\t\t\$currency_code = \$order_info['currency_code'] ?? (\$this->session->data['currency'] ?? \$this->config->get('config_currency'));\r\n\t\t\t\t\$currency_value = (float)(\$order_info['currency_value'] ?? 1);\r\n\t\t\t\t\$payment_code = \$get_method_code(\$order_info['payment_method'] ?? '');\r\n\t\t\t\t\$payment_name = \$get_method_name(\$order_info['payment_method'] ?? '');\r\n\t\t\t\t\$is_hutko = \$payment_code === 'hutko' || strpos(\$payment_code, 'hutko.') === 0;\r\n\t\t\t\t\$is_cod = \$payment_code === 'cod' || strpos(\$payment_code, 'cod.') === 0 || strpos(\$payment_code, 'pinta_nova_poshta_cod') !== false;\r\n\r\n\t\t\t\t\$order_data = [\r\n\t\t\t\t\t'order_id'        => (int)\$order_id,\r\n\t\t\t\t\t'shipping_method' => \$get_method_name(\$order_info['shipping_method'] ?? ''),\r\n\t\t\t\t\t'payment_method'  => \$payment_name,\r\n\t\t\t\t\t'payment_code'    => \$payment_code,\r\n\t\t\t\t\t'is_hutko'        => \$is_hutko,\r\n\t\t\t\t\t'is_cod'          => \$is_cod,\r\n\t\t\t\t];",
	'success_php:payment_flags'
);

$success_php_content = st2b2_replace_once(
	$success_php_content,
	"\t\t\$this->response->setOutput(\$this->load->view('checkout/success', \$data));\r\n\t}\r\n}",
	"\t\t\$this->response->setOutput(\$this->load->view('checkout/success', \$data));\r\n\t}\r\n\r\n\tprivate function getSessionSuccessOrderId(): int {\r\n\t\t\$order_id = (int)(\$this->session->data['bs_success_order_id'] ?? 0);\r\n\t\t\$created_at = (int)(\$this->session->data['bs_success_order_at'] ?? 0);\r\n\r\n\t\tif (\$order_id <= 0 || \$created_at <= 0) {\r\n\t\t\treturn 0;\r\n\t\t}\r\n\r\n\t\t\$age = time() - \$created_at;\r\n\r\n\t\tif (\$age < 0 || \$age > 1800) {\r\n\t\t\treturn 0;\r\n\t\t}\r\n\r\n\t\treturn \$order_id;\r\n\t}\r\n\r\n\tprivate function getHutkoReturnContext(): array {\r\n\t\tif (empty(\$this->request->cookie['bs_hutko_return'])) {\r\n\t\t\treturn [];\r\n\t\t}\r\n\r\n\t\t\$parts = explode('.', (string)\$this->request->cookie['bs_hutko_return'], 2);\r\n\r\n\t\tif (count(\$parts) !== 2) {\r\n\t\t\treturn [];\r\n\t\t}\r\n\r\n\t\t[\$encoded, \$signature] = \$parts;\r\n\t\t\$expected = hash_hmac('sha256', \$encoded, \$this->getHutkoReturnCookieSecret());\r\n\r\n\t\tif (!hash_equals(\$expected, \$signature)) {\r\n\t\t\treturn [];\r\n\t\t}\r\n\r\n\t\t\$decoded = base64_decode(\$encoded, true);\r\n\r\n\t\tif (\$decoded === false) {\r\n\t\t\treturn [];\r\n\t\t}\r\n\r\n\t\t\$data = json_decode(\$decoded, true);\r\n\r\n\t\tif (!is_array(\$data) || empty(\$data['order_id']) || empty(\$data['expires']) || (int)\$data['expires'] < time()) {\r\n\t\t\treturn [];\r\n\t\t}\r\n\r\n\t\treturn \$data;\r\n\t}\r\n\r\n\tprivate function canShowSuccessOrder(array \$order_info, bool \$from_order_session, array \$hutko_return_context): bool {\r\n\t\t\$order_id = (int)(\$order_info['order_id'] ?? 0);\r\n\r\n\t\tif (\$order_id <= 0) {\r\n\t\t\treturn false;\r\n\t\t}\r\n\r\n\t\tif (\$from_order_session) {\r\n\t\t\treturn true;\r\n\t\t}\r\n\r\n\t\tif (\$this->customer->isLogged()) {\r\n\t\t\treturn (int)(\$order_info['customer_id'] ?? 0) === (int)\$this->customer->getId();\r\n\t\t}\r\n\r\n\t\t\$session_success_order_id = \$this->getSessionSuccessOrderId();\r\n\r\n\t\tif (\$session_success_order_id === \$order_id && (int)(\$order_info['customer_id'] ?? 0) === 0) {\r\n\t\t\treturn true;\r\n\t\t}\r\n\r\n\t\tif (!empty(\$hutko_return_context['order_id']) && (int)\$hutko_return_context['order_id'] === \$order_id) {\r\n\t\t\t\$context_customer_id = (int)(\$hutko_return_context['customer_id'] ?? 0);\r\n\t\t\t\$order_customer_id = (int)(\$order_info['customer_id'] ?? 0);\r\n\r\n\t\t\treturn \$context_customer_id === \$order_customer_id || (\$context_customer_id === 0 && \$order_customer_id === 0);\r\n\t\t}\r\n\r\n\t\treturn false;\r\n\t}\r\n\r\n\tprivate function getHutkoReturnCookieSecret(): string {\r\n\t\treturn (string)(\$this->config->get('payment_hutko_secret_key') ?: \$this->config->get('config_encryption') ?: \$this->config->get('config_hash') ?: DIR_STORAGE);\r\n\t}\r\n}",
	'success_php:helper_methods'
);

$success_twig_content = st2b2_replace_once(
	$success_twig_content,
	"          {% set payment_name = order_data.payment_method|default('')|lower %}\r\n{% set payment_code = order_data.payment_code|default('')|lower %}\r\n\r\n{% if payment_code == 'hutko' %}\r\n  <p>Фіскальний чек відправлено на ваш номер або на E-mail.</p>\r\n{% elseif payment_code == 'pinta_nova_poshta_cod' or payment_code == 'cod' or payment_code == 'cod.cod' or 'післяплат' in payment_name or 'при доставці' in payment_name %}\r\n  <p>Фіскальний чек буде відправлено на ваш номер або на E-mail в день отримання замовлення.</p>\r\n{% else %}\r\n  <p>Фіскальний чек буде відправлено на ваш номер або на E-mail при відправці замовлення.</p>\r\n{% endif %}",
	"          {% if order_data.is_hutko|default(false) %}\r\n            <p>Фіскальний чек відправлено на ваш номер або на E-mail.</p>\r\n          {% elseif order_data.is_cod|default(false) %}\r\n            <p>Фіскальний чек буде відправлено на ваш номер або на E-mail в день отримання замовлення.</p>\r\n          {% else %}\r\n            <p>Фіскальний чек буде відправлено на ваш номер або на E-mail при відправці замовлення.</p>\r\n          {% endif %}",
	'success_twig:fiscal_flags'
);

$success_twig_content = st2b2_replace_once(
	$success_twig_content,
	"<style>\r\n/* r11-spacing-fix */\r\n.bs-success-hero { margin-top: 1.5rem; }\r\n</style>",
	"<style>\r\n/* r11-spacing-fix */\r\n.bs-success-hero { margin-top: 1.5rem; }\r\n/* st2b2-success-spacing */\r\n#checkout-success { padding-bottom: 24px; }\r\n#checkout-success #content { padding-bottom: 16px !important; }\r\n#checkout-success .bs-success-actions { margin-bottom: 0; }\r\n@media (max-width: 480px) {\r\n  #checkout-success { padding-bottom: 20px; }\r\n}\r\n</style>",
	'success_twig:spacing'
);

$cookie_twig_content = st2b2_replace_once(
	$cookie_twig_content,
	"  cookie.querySelectorAll('[data-cookie-action]').forEach(function (link) {\r\n  link.addEventListener('click', function (event) {",
	"  // st2b2-success-cookie-fallback: never leave checkout/success for a cookie fallback.\r\n  var isSuccessPage = !!document.getElementById('checkout-success');\r\n  var fallback = function (href) {\r\n    if (isSuccessPage && cookie) {\r\n      cookie.remove();\r\n      return;\r\n    }\r\n\r\n    window.location.href = href;\r\n  };\r\n\r\n  cookie.querySelectorAll('[data-cookie-action]').forEach(function (link) {\r\n  link.addEventListener('click', function (event) {",
	'cookie_twig:fallback_helper'
);

$cookie_twig_content = str_replace('window.location.href = link.href;', 'fallback(link.href);', $cookie_twig_content);

$backups = [];
st2b2_backup_and_write($success_php, $success_php_content, $backups);
st2b2_backup_and_write($success_twig, $success_twig_content, $backups);
st2b2_backup_and_write($cookie_twig, $cookie_twig_content, $backups);

st2b2_php_lint($success_php);

echo "done=ok" . PHP_EOL;
echo "already_applied=no" . PHP_EOL;
foreach ($backups as $backup) {
	echo "backup=" . $backup . PHP_EOL;
}

@unlink(__FILE__);
