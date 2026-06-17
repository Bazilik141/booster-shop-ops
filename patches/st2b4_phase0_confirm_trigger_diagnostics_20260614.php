<?php
declare(strict_types=1);

// ST-2b.4 Phase 0 only: instrument confirm.confirm triggers. No behavior hardening.

function st2b4p0_log(string $message): void {
	echo '[' . gmdate('c') . '] ' . $message . PHP_EOL;
}

function st2b4p0_fail(string $message): void {
	st2b4p0_log('error=' . $message);
	st2b4p0_log('done=failed');
	exit(1);
}

function st2b4p0_path(string $relative): string {
	return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function st2b4p0_read(string $relative): string {
	$path = st2b4p0_path($relative);

	if (!is_file($path)) {
		st2b4p0_fail('missing_file:' . $relative);
	}

	$content = file_get_contents($path);

	if ($content === false) {
		st2b4p0_fail('read_failed:' . $relative);
	}

	return $content;
}

function st2b4p0_count(string $content, string $needle): int {
	$normalized = str_replace("\r\n", "\n", $needle);

	if ($normalized === $needle) {
		return substr_count($content, $needle);
	}

	return substr_count($content, $needle) + substr_count($content, $normalized);
}

function st2b4p0_assert_count(string $content, string $needle, int $expected, string $label): void {
	$count = st2b4p0_count($content, $needle);

	if ($count !== $expected) {
		st2b4p0_fail('anchor_count_mismatch:' . $label . ':expected=' . $expected . ':actual=' . $count);
	}
}

function st2b4p0_replace_once(string $content, string $search, string $replace, string $label): string {
	$variants = [
		$search => $replace,
		str_replace("\r\n", "\n", $search) => str_replace("\r\n", "\n", $replace),
	];

	foreach ($variants as $needle => $value) {
		if (substr_count($content, $needle) === 1) {
			return str_replace($needle, $value, $content);
		}
	}

	st2b4p0_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . st2b4p0_count($content, $search));
}

function st2b4p0_replace_nth(string $content, string $search, string $replace, int $nth, string $label): string {
	$variants = [
		$search => $replace,
		str_replace("\r\n", "\n", $search) => str_replace("\r\n", "\n", $replace),
	];

	foreach ($variants as $needle => $value) {
		$count = substr_count($content, $needle);

		if ($count >= $nth) {
			$offset = -1;

			for ($i = 0; $i < $nth; $i++) {
				$offset = strpos($content, $needle, $offset + 1);

				if ($offset === false) {
					break;
				}
			}

			if ($offset !== false) {
				return substr_replace($content, $value, $offset, strlen($needle));
			}
		}
	}

	st2b4p0_fail('anchor_count_mismatch:' . $label . ':expected_at_least=' . $nth . ':actual=' . st2b4p0_count($content, $search));
}

function st2b4p0_backup_and_write(string $relative, string $content, array &$backups): void {
	$path = st2b4p0_path($relative);
	$backup = $path . '.st2b4p0-' . date('Ymd-His') . '.bak';

	if (!copy($path, $backup)) {
		st2b4p0_fail('backup_failed:' . $relative);
	}

	if (file_put_contents($path, $content) === false) {
		st2b4p0_fail('write_failed:' . $relative);
	}

	$backups[] = $backup;
	st2b4p0_log('changed=' . $relative);
	st2b4p0_log('backup=' . $backup);
}

$files = [
	'checkout' => 'catalog/view/template/checkout/checkout.twig',
	'payment_method' => 'catalog/view/template/checkout/payment_method.twig',
	'shipping_address' => 'catalog/view/template/checkout/shipping_address.twig',
	'register' => 'catalog/view/template/checkout/register.twig',
	'payment_address' => 'catalog/view/template/checkout/payment_address.twig',
];

st2b4p0_log('patch=st2b4_phase0_confirm_trigger_diagnostics_20260614');
st2b4p0_log('cwd=' . __DIR__);
st2b4p0_log('time=' . gmdate('c'));
st2b4p0_log('scope=Phase 0 diagnostics only; no order-creation behavior hardening');
st2b4p0_log('db_schema_changes=none');
st2b4p0_log('db_data_changes=none');

$content = [];

foreach ($files as $key => $relative) {
	$content[$key] = st2b4p0_read($relative);
}

if (strpos($content['checkout'], 'ST-2b.4 Phase 0 confirm diagnostics') !== false) {
	st2b4p0_log('already_applied=yes');
	st2b4p0_log('done=ok');
	@unlink(__FILE__);
	exit(0);
}

foreach ($content as $key => $item) {
	if (strpos($item, 'bsSt2b4ConfirmLoad') !== false || strpos($item, 'ST-2b.4 Phase 0 confirm diagnostics') !== false) {
		st2b4p0_fail('partial_phase0_markers_found:' . $files[$key]);
	}
}

st2b4p0_assert_count($content['checkout'], 'ST-2b.3: cache initial confirm table before deferred swaps.', 1, 'checkout:st2b3_marker');
st2b4p0_assert_count($content['checkout'], 'window.bsCheckoutLoadConfirmAndSubmit = function(trigger) {', 1, 'checkout:load_function_signature');
st2b4p0_assert_count($content['checkout'], "window.bsCheckoutLoadConfirmAndSubmit(this);", 1, 'checkout:deferred_handler_call');

$helper = <<<'TWIG'
  function bsSt2b4DescribeNode(node) {
    if (!node) {
      return '';
    }

    var parts = [];

    if (node.tagName) {
      parts.push(String(node.tagName).toLowerCase());
    }

    if (node.id) {
      parts.push('#' + node.id);
    }

    if (node.name) {
      parts.push('[name="' + node.name + '"]');
    }

    if (node.type) {
      parts.push('[type="' + node.type + '"]');
    }

    if (node.value) {
      parts.push('[value="' + String(node.value).slice(0, 80) + '"]');
    }

    return parts.join('');
  }

  function bsSt2b4NativeEvent(event) {
    return event && event.originalEvent ? event.originalEvent : event;
  }

  // ST-2b.4 Phase 0 confirm diagnostics.
  window.__bsSt2b4ConfirmDiag = window.__bsSt2b4ConfirmDiag || [];

  window.bsSt2b4LogConfirm = function(source, event, trigger) {
    var nativeEvent = bsSt2b4NativeEvent(event);
    var entry = {
      time: new Date().toISOString(),
      source: source || '',
      eventType: nativeEvent && nativeEvent.type ? nativeEvent.type : (event && event.type ? event.type : ''),
      isTrusted: nativeEvent && typeof nativeEvent.isTrusted !== 'undefined' ? nativeEvent.isTrusted : null,
      activeElement: bsSt2b4DescribeNode(document.activeElement),
      target: bsSt2b4DescribeNode(nativeEvent && nativeEvent.target ? nativeEvent.target : (event && event.target ? event.target : null)),
      currentTarget: bsSt2b4DescribeNode(nativeEvent && nativeEvent.currentTarget ? nativeEvent.currentTarget : (event && event.currentTarget ? event.currentTarget : null)),
      trigger: bsSt2b4DescribeNode(trigger),
      paymentCode: $('#input-payment-code').val() || '',
      shippingCode: $('#input-shipping-code').val() || '',
      stack: (new Error('ST-2b.4 confirm.confirm diagnostic')).stack || ''
    };

    window.__bsSt2b4ConfirmDiag.push(entry);

    if (window.console && console.warn) {
      console.warn('[ST-2b.4 confirm.confirm]', entry);
    }

    return entry;
  };

  window.bsSt2b4ConfirmLoad = function(source, event, callback) {
    window.bsSt2b4LogConfirm(source, event || null, null);
    return $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', callback);
  };

  $(document).on('ajaxSend.bsSt2b4ConfirmDiag', function(event, xhr, settings) {
    var url = settings && settings.url ? settings.url : '';

    if (url.indexOf('checkout/confirm.confirm') !== -1 && window.bsSt2b4LogConfirm) {
      window.bsSt2b4LogConfirm('ajaxSend:' + url, event || null, null);
    }
  });

TWIG;

$old_escape = <<<'TWIG'
  function bsCheckoutEscapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(chr) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[chr];
    });
  }

TWIG;

$content['checkout'] = st2b4p0_replace_once($content['checkout'], $old_escape, $old_escape . $helper, 'checkout:diagnostic_helper');

$content['checkout'] = st2b4p0_replace_once(
	$content['checkout'],
	"  window.bsCheckoutLoadConfirmAndSubmit = function(trigger) {\r\n",
	"  window.bsCheckoutLoadConfirmAndSubmit = function(trigger, event) {\r\n    if (window.bsSt2b4LogConfirm) {\r\n      window.bsSt2b4LogConfirm('bsCheckoutLoadConfirmAndSubmit:entry', event || null, trigger || null);\r\n    }\r\n",
	'checkout:function_entry'
);

$content['checkout'] = st2b4p0_replace_once(
	$content['checkout'],
	"    $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {\r\n",
	"    if (window.bsSt2b4LogConfirm) {\r\n      window.bsSt2b4LogConfirm('checkout.twig:bsCheckoutLoadConfirmAndSubmit.load', event || null, trigger || null);\r\n    }\r\n\r\n    $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}', function(response, status) {\r\n",
	'checkout:load_log'
);

$content['checkout'] = st2b4p0_replace_once(
	$content['checkout'],
	"      window.bsCheckoutLoadConfirmAndSubmit(this);\r\n",
	"      window.bsCheckoutLoadConfirmAndSubmit(this, event);\r\n",
	'checkout:pass_event'
);

$payment_method_save_search = "            } else if ($('#input-payment-code').val()) {\r\n              $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n            } else {\r\n";

if (st2b4p0_count($content['payment_method'], $payment_method_save_search) === 1) {
	$content['payment_method'] = st2b4p0_replace_once(
		$content['payment_method'],
		$payment_method_save_search,
		"            } else if ($('#input-payment-code').val()) {\r\n              if (window.bsSt2b4ConfirmLoad) {\r\n                window.bsSt2b4ConfirmLoad('payment_method.twig:savePayment.fallback', null);\r\n              } else {\r\n                $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n              }\r\n            } else {\r\n",
		'payment_method:savePayment_fallback'
	);
}

$payment_method_agree_search = "          } else if ($('#input-payment-code').val()) {\r\n            $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n          }\r\n";

if (st2b4p0_count($content['payment_method'], $payment_method_agree_search) === 1) {
	$content['payment_method'] = st2b4p0_replace_once(
		$content['payment_method'],
		$payment_method_agree_search,
		"          } else if ($('#input-payment-code').val()) {\r\n            if (window.bsSt2b4ConfirmLoad) {\r\n              window.bsSt2b4ConfirmLoad('payment_method.twig:agree.fallback', null);\r\n            } else {\r\n              $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n            }\r\n          }\r\n",
		'payment_method:agree_fallback'
	);
}

$legacy_load_line = "                    $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n";

$shipping_address_legacy_count = st2b4p0_count($content['shipping_address'], $legacy_load_line);

if ($shipping_address_legacy_count === 2) {
	$content['shipping_address'] = st2b4p0_replace_nth(
		$content['shipping_address'],
		$legacy_load_line,
		"                    if (window.bsSt2b4ConfirmLoad) {\r\n                        window.bsSt2b4ConfirmLoad('shipping_address.twig:saveAddress', null);\r\n                    } else {\r\n                        $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n                    }\r\n",
		1,
		'shipping_address:saveAddress'
	);

	$content['shipping_address'] = st2b4p0_replace_nth(
		$content['shipping_address'],
		$legacy_load_line,
		"                    if (window.bsSt2b4ConfirmLoad) {\r\n                        window.bsSt2b4ConfirmLoad('shipping_address.twig:existingAddress', null);\r\n                    } else {\r\n                        $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n                    }\r\n",
		2,
		'shipping_address:existingAddress'
	);
} elseif ($shipping_address_legacy_count !== 0) {
	st2b4p0_fail('unexpected_legacy_load_count:shipping_address=' . $shipping_address_legacy_count);
}

$register_legacy_count = st2b4p0_count($content['register'], $legacy_load_line);

if ($register_legacy_count === 1) {
	$content['register'] = st2b4p0_replace_once(
		$content['register'],
		$legacy_load_line,
		"                    if (window.bsSt2b4ConfirmLoad) {\r\n                        window.bsSt2b4ConfirmLoad('register.twig:registerSave', null);\r\n                    } else {\r\n                        $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n                    }\r\n",
		'register:registerSave'
	);
} elseif ($register_legacy_count !== 0) {
	st2b4p0_fail('unexpected_legacy_load_count:register=' . $register_legacy_count);
}

$payment_address_legacy_count = st2b4p0_count($content['payment_address'], $legacy_load_line);

if ($payment_address_legacy_count === 2) {
	$content['payment_address'] = st2b4p0_replace_nth(
		$content['payment_address'],
		$legacy_load_line,
		"                    if (window.bsSt2b4ConfirmLoad) {\r\n                        window.bsSt2b4ConfirmLoad('payment_address.twig:saveAddress', null);\r\n                    } else {\r\n                        $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n                    }\r\n",
		1,
		'payment_address:saveAddress'
	);

	$content['payment_address'] = st2b4p0_replace_nth(
		$content['payment_address'],
		$legacy_load_line,
		"                    if (window.bsSt2b4ConfirmLoad) {\r\n                        window.bsSt2b4ConfirmLoad('payment_address.twig:existingAddress', null);\r\n                    } else {\r\n                        $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language={{ language }}');\r\n                    }\r\n",
		2,
		'payment_address:existingAddress'
	);
} elseif ($payment_address_legacy_count !== 0) {
	st2b4p0_fail('unexpected_legacy_load_count:payment_address=' . $payment_address_legacy_count);
}

st2b4p0_assert_count($content['checkout'], 'ST-2b.4 Phase 0 confirm diagnostics', 1, 'checkout:marker_after');
st2b4p0_assert_count($content['checkout'], 'window.bsCheckoutLoadConfirmAndSubmit(this, event);', 1, 'checkout:event_after');

if (st2b4p0_count($content['payment_method'], $payment_method_save_search) === 0 && strpos($content['payment_method'], 'payment_method.twig:savePayment.fallback') !== false) {
	st2b4p0_assert_count($content['payment_method'], 'payment_method.twig:savePayment.fallback', 1, 'payment_method:savePayment_marker');
}

if (st2b4p0_count($content['payment_method'], $payment_method_agree_search) === 0 && strpos($content['payment_method'], 'payment_method.twig:agree.fallback') !== false) {
	st2b4p0_assert_count($content['payment_method'], 'payment_method.twig:agree.fallback', 1, 'payment_method:agree_marker');
}

if ($shipping_address_legacy_count === 2) {
	st2b4p0_assert_count($content['shipping_address'], 'shipping_address.twig:saveAddress', 1, 'shipping_address:save_marker');
	st2b4p0_assert_count($content['shipping_address'], 'shipping_address.twig:existingAddress', 1, 'shipping_address:existing_marker');
}

if ($register_legacy_count === 1) {
	st2b4p0_assert_count($content['register'], 'register.twig:registerSave', 1, 'register:marker');
}

if ($payment_address_legacy_count === 2) {
	st2b4p0_assert_count($content['payment_address'], 'payment_address.twig:saveAddress', 1, 'payment_address:save_marker');
	st2b4p0_assert_count($content['payment_address'], 'payment_address.twig:existingAddress', 1, 'payment_address:existing_marker');
}

$writes = [];

foreach ($files as $key => $relative) {
	if ($content[$key] !== st2b4p0_read($relative)) {
		$writes[$relative] = $content[$key];
	}
}

if (!$writes) {
	st2b4p0_log('already_applied=yes');
	st2b4p0_log('done=ok');
	@unlink(__FILE__);
	exit(0);
}

$backups = [];

foreach ($writes as $relative => $new_content) {
	st2b4p0_backup_and_write($relative, $new_content, $backups);
}

st2b4p0_log('target_php_lint=none');
st2b4p0_log('changed_files=' . implode(',', array_keys($writes)));
st2b4p0_log('diagnostic_readout=copy window.__bsSt2b4ConfirmDiag from browser console after repro');
st2b4p0_log('rollback=restore .st2b4p0 backup files and clear OpenCart template/cache');
st2b4p0_log('done=ok');

@unlink(__FILE__);
