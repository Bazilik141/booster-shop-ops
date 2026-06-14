<?php
declare(strict_types=1);

// ST-2b.3: keep cached order summary in deferred confirm panel and compact success actions.

function st2b3_log(string $message): void {
	echo '[' . gmdate('c') . '] ' . $message . PHP_EOL;
}

function st2b3_fail(string $message): void {
	st2b3_log('error=' . $message);
	st2b3_log('done=failed');
	exit(1);
}

function st2b3_path(string $relative): string {
	return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function st2b3_read(string $relative): string {
	$path = st2b3_path($relative);

	if (!is_file($path)) {
		st2b3_fail('missing_file:' . $relative);
	}

	$content = file_get_contents($path);

	if ($content === false) {
		st2b3_fail('read_failed:' . $relative);
	}

	return $content;
}

function st2b3_count_eol(string $content, string $needle): int {
	$normalized = str_replace("\r\n", "\n", $needle);

	if ($normalized === $needle) {
		return substr_count($content, $needle);
	}

	return substr_count($content, $needle) + substr_count($content, $normalized);
}

function st2b3_assert_count(string $content, string $needle, int $expected, string $label): void {
	$count = st2b3_count_eol($content, $needle);

	if ($count !== $expected) {
		st2b3_fail('anchor_count_mismatch:' . $label . ':expected=' . $expected . ':actual=' . $count);
	}
}

function st2b3_replace_once(string $content, string $search, string $replace, string $label): string {
	$variants = [
		$search => $replace,
		str_replace("\r\n", "\n", $search) => str_replace("\r\n", "\n", $replace),
	];

	foreach ($variants as $needle => $value) {
		if (substr_count($content, $needle) === 1) {
			return str_replace($needle, $value, $content);
		}
	}

	st2b3_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . st2b3_count_eol($content, $search));
}

function st2b3_backup_and_write(string $relative, string $content, array &$backups): void {
	$path = st2b3_path($relative);
	$backup = $path . '.st2b3-' . date('Ymd-His') . '.bak';

	if (!copy($path, $backup)) {
		st2b3_fail('backup_failed:' . $relative);
	}

	if (file_put_contents($path, $content) === false) {
		st2b3_fail('write_failed:' . $relative);
	}

	$backups[] = $backup;
	st2b3_log('changed=' . $relative);
	st2b3_log('backup=' . $backup);
}

$checkout_twig = 'catalog/view/template/checkout/checkout.twig';
$success_twig = 'catalog/view/template/checkout/success.twig';

st2b3_log('patch=st2b3_confirm_summary_success_button_20260614');
st2b3_log('cwd=' . __DIR__);
st2b3_log('time=' . gmdate('c'));
st2b3_log('scope=checkout deferred summary from cached client-side markup; compact success actions');
st2b3_log('db_schema_changes=none');
st2b3_log('db_data_changes=none');

$checkout = st2b3_read($checkout_twig);
$success = st2b3_read($success_twig);

$markers = [
	'checkout' => strpos($checkout, 'ST-2b.3: cache initial confirm table before deferred swaps.') !== false,
	'success' => strpos($success, 'st2b3-success-actions-compact') !== false,
];

$applied = count(array_filter($markers));

if ($applied === count($markers)) {
	st2b3_log('already_applied=yes');
	st2b3_log('done=ok');
	@unlink(__FILE__);
	exit(0);
}

if ($applied > 0) {
	st2b3_fail('partial_st2b3_markers_found');
}

st2b3_assert_count($checkout, 'ST-2b.1: defer real confirm.confirm until explicit place-order click.', 1, 'checkout:st2b1_marker');
st2b3_assert_count($checkout, "$('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}'", 1, 'checkout:single_confirm_confirm_load');
st2b3_assert_count($checkout, 'function bsCheckoutDeferredConfirmHtml() {', 1, 'checkout:deferred_confirm_function');
st2b3_assert_count($success, 'st2b2-success-spacing', 1, 'success:st2b2_spacing_marker');

$checkout = st2b3_replace_once(
	$checkout,
	"  var bsCheckoutConfirmSubmitting = false;\r\n",
	"  var bsCheckoutConfirmSubmitting = false;\r\n  var bsCheckoutInitialSummaryHtml = '';\r\n  var bsCheckoutInitialSummaryCaptured = false;\r\n",
	'checkout:variables'
);

$checkout = st2b3_replace_once(
	$checkout,
	"#checkout-checkout .bs-table-model-hidden {\r\n  display: none !important;\r\n}\r\n",
	"#checkout-checkout .bs-table-model-hidden {\r\n  display: none !important;\r\n}\r\n\r\n#checkout-checkout .bs-confirm-deferred-summary {\r\n  margin-bottom: 14px;\r\n}\r\n\r\n#checkout-checkout .bs-confirm-deferred-summary .table-responsive,\r\n#checkout-checkout .bs-confirm-deferred-summary table {\r\n  margin-bottom: 0;\r\n}\r\n",
	'checkout:deferred_summary_css'
);

$checkout = st2b3_replace_once(
	$checkout,
	"  function hideModelColumns() {\r\n    $('#checkout-confirm table').each(function() {\r\n      var table = $(this);\r\n\r\n      table.find('th, td').removeClass('bs-table-model-hidden');\r\n\r\n      table.find('tr').first().children('th, td').each(function(index) {\r\n        if (normalizeText($(this).text()).toLowerCase() === 'модель') {\r\n          var column = index + 1;\r\n\r\n          table.find('tr').each(function() {\r\n            $(this).children('th, td').eq(column - 1).addClass('bs-table-model-hidden');\r\n          });\r\n        }\r\n      });\r\n    });\r\n  }\r\n",
	"  function hideModelColumns() {\r\n    $('#checkout-confirm table').each(function() {\r\n      var table = $(this);\r\n\r\n      table.find('th, td').removeClass('bs-table-model-hidden');\r\n\r\n      table.find('tr').first().children('th, td').each(function(index) {\r\n        if (normalizeText($(this).text()).toLowerCase() === 'модель') {\r\n          var column = index + 1;\r\n\r\n          table.find('tr').each(function() {\r\n            $(this).children('th, td').eq(column - 1).addClass('bs-table-model-hidden');\r\n          });\r\n        }\r\n      });\r\n    });\r\n  }\r\n\r\n  function bsCheckoutCaptureInitialSummary() {\r\n    if (bsCheckoutInitialSummaryCaptured) {\r\n      return;\r\n    }\r\n\r\n    var summary = $('#checkout-confirm > .table-responsive').first();\r\n\r\n    if (!summary.length || $('#checkout-confirm [data-bs-confirm-deferred]').length) {\r\n      return;\r\n    }\r\n\r\n    // ST-2b.3: cache initial confirm table before deferred swaps.\r\n    bsCheckoutInitialSummaryHtml = $('<div>').append(summary.clone()).html();\r\n    bsCheckoutInitialSummaryCaptured = true;\r\n  }\r\n\r\n  function bsCheckoutCachedSummaryHtml() {\r\n    bsCheckoutCaptureInitialSummary();\r\n\r\n    if (!bsCheckoutInitialSummaryHtml) {\r\n      return '';\r\n    }\r\n\r\n    return '<div class=\"bs-confirm-deferred-summary\">' + bsCheckoutInitialSummaryHtml + '</div>';\r\n  }\r\n",
	'checkout:capture_helpers'
);

$checkout = st2b3_replace_once(
	$checkout,
	"  function bsCheckoutDeferredConfirmHtml() {\r\n    var shippingLabel = normalizeText($('#input-shipping-method').val());\r\n    var paymentLabel = normalizeText($('#input-payment-method').val());\r\n    var summary = '';\r\n\r\n    if (shippingLabel) {\r\n      summary += '<div class=\"small text-muted mb-1\">Доставка: <strong>' + bsCheckoutEscapeHtml(shippingLabel) + '</strong></div>';\r\n    }\r\n\r\n    if (paymentLabel) {\r\n      summary += '<div class=\"small text-muted mb-3\">Оплата: <strong>' + bsCheckoutEscapeHtml(paymentLabel) + '</strong></div>';\r\n    }\r\n\r\n    return '<div class=\"bs-confirm-deferred\" data-bs-confirm-deferred=\"1\">' +\r\n      '<h2 class=\"h5 mb-2\">Підтвердження замовлення</h2>' +\r\n      '<p class=\"text-muted mb-3\">Замовлення ще не створено. Після кліку перевіримо кошик, створимо замовлення і перейдемо до оплати.</p>' +\r\n      summary +\r\n      '<div class=\"text-end\"><button type=\"button\" id=\"bs-button-confirm-deferred\" class=\"btn btn-primary\" data-bs-deferred-confirm=\"1\">Оформити замовлення</button></div>' +\r\n      '</div>';\r\n  }\r\n",
	"  function bsCheckoutDeferredConfirmHtml() {\r\n    var shippingLabel = normalizeText($('#input-shipping-method').val());\r\n    var paymentLabel = normalizeText($('#input-payment-method').val());\r\n    var methodSummary = '';\r\n\r\n    if (shippingLabel) {\r\n      methodSummary += '<div class=\"small text-muted mb-1\">Доставка: <strong>' + bsCheckoutEscapeHtml(shippingLabel) + '</strong></div>';\r\n    }\r\n\r\n    if (paymentLabel) {\r\n      methodSummary += '<div class=\"small text-muted mb-3\">Оплата: <strong>' + bsCheckoutEscapeHtml(paymentLabel) + '</strong></div>';\r\n    }\r\n\r\n    return '<div class=\"bs-confirm-deferred\" data-bs-confirm-deferred=\"1\">' +\r\n      '<h2 class=\"h5 mb-2\">Підтвердження замовлення</h2>' +\r\n      bsCheckoutCachedSummaryHtml() +\r\n      '<p class=\"text-muted mb-3\">Замовлення ще не створено. Після кліку перевіримо кошик, створимо замовлення і перейдемо до оплати.</p>' +\r\n      methodSummary +\r\n      '<div class=\"text-end\"><button type=\"button\" id=\"bs-button-confirm-deferred\" class=\"btn btn-primary\" data-bs-deferred-confirm=\"1\">Оформити замовлення</button></div>' +\r\n      '</div>';\r\n  }\r\n",
	'checkout:deferred_html'
);

$checkout = st2b3_replace_once(
	$checkout,
	"  function enhanceCheckout() {\r\n    renameRecipientHeadings();\r\n    markSelectedChoices();\r\n    hideModelColumns();\r\n    prepareNpCheckout();\r\n  }\r\n",
	"  function enhanceCheckout() {\r\n    renameRecipientHeadings();\r\n    markSelectedChoices();\r\n    hideModelColumns();\r\n    bsCheckoutCaptureInitialSummary();\r\n    prepareNpCheckout();\r\n  }\r\n",
	'checkout:enhance_capture'
);

$success = st2b3_replace_once(
	$success,
	"        <section class=\"bs-success-footer-msg\">\r\n          {% if order_data.is_hutko|default(false) %}\r\n            <p>Фіскальний чек відправлено на ваш номер або на E-mail.</p>\r\n          {% elseif order_data.is_cod|default(false) %}\r\n            <p>Фіскальний чек буде відправлено на ваш номер або на E-mail в день отримання замовлення.</p>\r\n          {% else %}\r\n            <p>Фіскальний чек буде відправлено на ваш номер або на E-mail при відправці замовлення.</p>\r\n          {% endif %}\r\n          <p>Ми не телефонуємо і не пишемо для підтвердження без потреби. Якщо всі дані заповнені коректно — просто тихо і швидко відправляємо 🙂</p>\r\n          <p>Якщо є питання — <a href=\"https://t.me/boostershop_tcg\" target=\"_blank\" rel=\"noopener\" class=\"bs-telegram-button\">напишіть у Telegram</a>.</p>\r\n          <p><strong>Вдалого анпакінгу 🎁</strong></p>\r\n        </section>\r\n\r\n        <nav class=\"bs-success-actions\" aria-label=\"Дії після замовлення\">\r\n          <a href=\"{{ continue }}\" class=\"bs-btn bs-btn-secondary\">На головну</a>\r\n          {% if is_logged and history_url %}\r\n            <a href=\"{{ history_url }}\" class=\"bs-btn bs-btn-primary\">Переглянути замовлення</a>\r\n          {% endif %}\r\n        </nav>",
	"        <nav class=\"bs-success-actions\" aria-label=\"Дії після замовлення\">\r\n          <a href=\"{{ continue }}\" class=\"bs-btn bs-btn-secondary\">На головну</a>\r\n          {% if is_logged and history_url %}\r\n            <a href=\"{{ history_url }}\" class=\"bs-btn bs-btn-primary\">Переглянути замовлення</a>\r\n          {% endif %}\r\n        </nav>\r\n\r\n        <section class=\"bs-success-footer-msg\">\r\n          {% if order_data.is_hutko|default(false) %}\r\n            <p>Фіскальний чек відправлено на ваш номер або на E-mail.</p>\r\n          {% elseif order_data.is_cod|default(false) %}\r\n            <p>Фіскальний чек буде відправлено на ваш номер або на E-mail в день отримання замовлення.</p>\r\n          {% else %}\r\n            <p>Фіскальний чек буде відправлено на ваш номер або на E-mail при відправці замовлення.</p>\r\n          {% endif %}\r\n          <p>Ми не телефонуємо і не пишемо для підтвердження без потреби. Якщо всі дані заповнені коректно — просто тихо і швидко відправляємо 🙂</p>\r\n          <p>Якщо є питання — <a href=\"https://t.me/boostershop_tcg\" target=\"_blank\" rel=\"noopener\" class=\"bs-telegram-button\">напишіть у Telegram</a>.</p>\r\n          <p><strong>Вдалого анпакінгу 🎁</strong></p>\r\n        </section>",
	'success:move_actions'
);

$success = st2b3_replace_once(
	$success,
	"/* st2b2-success-spacing */\r\n#checkout-success { padding-bottom: 24px; }\r\n#checkout-success #content { padding-bottom: 16px !important; }\r\n#checkout-success .bs-success-actions { margin-bottom: 0; }\r\n@media (max-width: 480px) {\r\n  #checkout-success { padding-bottom: 20px; }\r\n}\r\n",
	"/* st2b2-success-spacing */\r\n#checkout-success { padding-bottom: 16px; }\r\n#checkout-success #content { padding-bottom: 8px !important; }\r\n/* st2b3-success-actions-compact */\r\n#checkout-success .bs-success-actions {\r\n  justify-content: center;\r\n  margin: 12px 0 14px;\r\n}\r\n#checkout-success .bs-success-footer-msg { margin: 10px 0 0; }\r\n@media (max-width: 480px) {\r\n  #checkout-success { padding-bottom: 14px; }\r\n  #checkout-success .bs-success-actions { margin: 10px 0 12px; }\r\n}\r\n",
	'success:compact_css'
);

st2b3_assert_count($checkout, "$('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}'", 1, 'checkout:post_single_confirm_confirm_load');
st2b3_assert_count($checkout, 'bsCheckoutCachedSummaryHtml()', 2, 'checkout:cached_summary_usage');
st2b3_assert_count($success, 'bs-success-actions" aria-label="Дії після замовлення"', 1, 'success:actions_nav');

$writes = [];

if ($checkout !== st2b3_read($checkout_twig)) {
	$writes[$checkout_twig] = $checkout;
}

if ($success !== st2b3_read($success_twig)) {
	$writes[$success_twig] = $success;
}

if (!$writes) {
	st2b3_log('already_applied=yes');
	st2b3_log('done=ok');
	@unlink(__FILE__);
	exit(0);
}

$backups = [];

foreach ($writes as $relative => $content) {
	st2b3_backup_and_write($relative, $content, $backups);
}

st2b3_log('target_php_lint=none');
st2b3_log('changed_files=' . implode(',', array_keys($writes)));
st2b3_log('rollback=restore .st2b3 backup files and clear OpenCart template/cache');
st2b3_log('done=ok');

@unlink(__FILE__);
