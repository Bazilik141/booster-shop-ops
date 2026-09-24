<?php
/**
 * PAY-002 WP3 — merge Mono/PUMB into one credit row and remove stale PUMB "soon" card.
 * Run from ~/public_html after WP1 and WP2:
 * php PAY-002_pumb-checkout-card-ui-hotfix_20260830.php
 *
 * File-only change. No database writes.
 * Safety gate: the current live Twig and generated Twig must match the exact
 * SHA-256 hashes locally verified from the post-WP1/WP2 production file.
 * Rollback: restore catalog/view/template/checkout/payment_method.twig from the
 * printed _patch_backups/PAY-002_pumb-checkout-card-ui-hotfix_20260830-<ts>/ path.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-checkout-card-ui-hotfix_20260830';
const BEFORE_SHA256 = '0d18197b0d58780725cd979ed3d465c198f054cddcb6cc1ee629dffde214e9b2';
const AFTER_SHA256 = '7fde759aa8ca95e627f9b3c579d0f1ca178a8df85b862fe3c7469a35a9b2006e';
function out(string $s): void { echo $s . PHP_EOL; }
function fail(string $s): void { throw new RuntimeException('ERROR: ' . $s); }
function need(bool $ok, string $s): void { if (!$ok) fail($s); }
function replaceRegexOnce(string $source, string $pattern, string $replacement, string $label): string {
    $matches = [];
    $count = preg_match_all($pattern, $source, $matches);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    $changed = 0;
    $result = preg_replace($pattern, $replacement, $source, 1, $changed);
    need(is_string($result) && $changed === 1, 'replacement failed for ' . $label);
    return $result;
}
function replaceExactOnce(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    return str_replace($anchor, $replacement, $source);
}
function writeChecked(string $path, string $content): void {
    $written = file_put_contents($path, $content);
    need($written !== false && $written === strlen($content), 'write failed: ' . $path);
}
try {
    $root = getcwd() ?: '.';
    need(is_file($root . '/config.php'), 'Run from OpenCart public_html (config.php missing).');
    $rel = 'catalog/view/template/checkout/payment_method.twig';
    $path = $root . '/' . $rel;
    need(is_file($path), 'missing live file: ' . $rel);
    $marker = $root . '/extension/pumb_credit/.pay002-ui-hotfix-marker';
    if (is_file($marker)) { out('already_applied=yes'); exit(0); }

    $source = file_get_contents($path);
    need(is_string($source), 'cannot read ' . $rel);
    $beforeHash = hash('sha256', $source);
    need(
        hash_equals(BEFORE_SHA256, $beforeHash),
        'live source SHA256 mismatch expected=' . BEFORE_SHA256 . ' actual=' . $beforeHash . '; write skipped'
    );
    need(strpos($source, 'pay002Credit: !!group.pay002_credit') !== false, 'WP2 combined-provider data missing');
    need(strpos($source, "(isPumb ? '' : '<article class=\"pay001-checkout-provider pay001-checkout-provider--soon\"") !== false, 'WP2 stale soon-card anchor missing');

    $flatten = <<<'JS'
  function flattenPaymentMethods(json) {
    var options = [];
    var creditOption = null;

    if (json && json.payment_methods) {
      $.each(json.payment_methods, function(groupKey, group) {
        if (group && (group.pay001_credit || group.pay002_credit) && group.option) {
          var providerOptions = [];
          var isPumbGroup = !!group.pay002_credit;
          $.each(group.option, function(optionKey, option) {
            var match = option && option.code ? String(option.code).match(/(mono_chast|pumb_credit)_(\d)/) : null;
            if (match && ((isPumbGroup && match[1] === 'pumb_credit') || (!isPumbGroup && match[1] === 'mono_chast'))) {
              providerOptions.push({ code: option.code, count: Number(match[2]) });
            }
          });
          providerOptions.sort(function(left, right) { return left.count - right.count; });
          if (providerOptions.length) {
            var preferred = Number(group[isPumbGroup ? 'pay002_preferred' : 'pay001_preferred']) || providerOptions[0].count;
            var preferredOption = providerOptions[0];
            $.each(providerOptions, function(_, item) {
              if (item.count === preferred) {
                preferredOption = item;
                return false;
              }
            });
            if (!creditOption) {
              creditOption = {
                code: preferredOption.code,
                label: 'Сплатити частинами',
                id: 'credit_installments',
                pay001Credit: false,
                pay002Credit: false,
                fromModal: false,
                total: Number(group.pay002_total || group.pay001_total) || 0,
                monoTotal: 0,
                pumbTotal: 0,
                monoPreferred: 3,
                pumbPreferred: 3,
                monoOptions: [],
                pumbOptions: []
              };
              options.push(creditOption);
            }
            if (isPumbGroup) {
              creditOption.pay002Credit = true;
              creditOption.pumbOptions = providerOptions;
              creditOption.pumbPreferred = preferredOption.count;
              creditOption.pumbTotal = Number(group.pay002_total) || creditOption.total;
            } else {
              creditOption.pay001Credit = true;
              creditOption.code = preferredOption.code;
              creditOption.monoOptions = providerOptions;
              creditOption.monoPreferred = preferredOption.count;
              creditOption.monoTotal = Number(group.pay001_total) || creditOption.total;
              creditOption.fromModal = !!group.pay001_from_modal;
            }
          }
        } else if (group && group.option) {
          $.each(group.option, function(optionKey, option) {
            if (option && option.code) options.push({ code: option.code, label: option.name || option.title || group.name || groupKey, id: optionKey, category: option.booster_category || '' });
          });
        } else if (group && group.code) {
          options.push({ code: group.code, label: group.name || group.title || groupKey, id: groupKey });
        }
      });
    }
    return options;
  }
JS;
    $source = replaceRegexOnce(
        $source,
        '/  function flattenPaymentMethods\(json\) \{.*?\n  \}\n\n  function findPaymentOption/s',
        $flatten . "\n  function findPaymentOption",
        'combined credit flattening'
    );

    $find = <<<'JS'
  function findPaymentOption(options, choice) {
    var found = null;
    var rawChoice = String(choice || '');
    choice = normalizeChoice(choice);

    $.each(options, function(index, option) {
      if (!found && (option.pay001Credit || option.pay002Credit)) {
        $.each([].concat(option.monoOptions || [], option.pumbOptions || []), function(_, creditCode) {
          if (!found && creditCode.code === rawChoice) found = $.extend({}, option, { code: creditCode.code });
        });
      }
      if (!found && (normalizeChoice(option.category) === choice || paymentMatchesChoice(option.code, choice))) {
        found = option;
      }
    });

    return found;
  }
JS;
    $source = replaceRegexOnce(
        $source,
        '/  function findPaymentOption\(options, choice\) \{.*?\n  \}\n\n  function refreshConfirmSummary/s',
        $find . "\n  function refreshConfirmSummary",
        'combined credit lookup'
    );

    $drawer = <<<'JS'
  function pay001Drawer(option, selectedCode) {
    if (option.pay001Blocked) {
      return $('<div class="pay001-checkout-drawer" data-pay001-drawer><div class="pay001-credit-warning" role="alert">' + escapeHtml(pay001GateMessage(option.gate, false)) + '</div></div>');
    }
    var selected = selectedCode || option.code;
    var selectedMatch = String(selected).match(/(mono_chast|pumb_credit)_(\d)/);
    var selectedProvider = selectedMatch ? selectedMatch[1] : '';
    var html = '<div class="pay001-checkout-drawer" data-pay001-drawer>';

    function providerCard(providerKey, termOptions, preferred, total, image, alt, label) {
      if (!termOptions.length) return '';
      var providerSelected = selectedProvider === providerKey;
      var count = providerSelected ? Number(selectedMatch[2]) : (Number(preferred) || termOptions[0].count);
      var card = '<article class="pay001-checkout-provider" data-pay002-provider="' + providerKey + '"><div class="pay001-checkout-provider__head"><img src="' + image + '" alt="' + alt + '"><strong>' + label + '</strong></div><div class="pay001-parts">';
      $.each(termOptions, function(_, item) {
        card += '<button type="button" data-pay001-checkout-part="' + item.count + '" data-pay001-code="' + escapeHtml(item.code) + '"' + (providerSelected && item.count === count ? ' class="is-active"' : '') + '>' + item.count + ' ' + window.pay001PaymentsWord(item.count) + '</button>';
      });
      card += '</div><div class="pay001-summary"><span>Сума в кредит<strong>' + pay001Money(total) + '</strong></span><span>Щомісячний платіж<strong data-pay001-monthly>' + pay001Money(total / count) + '</strong></span><span>Платежів до завершення<strong data-pay001-left>' + Math.max(count - 1, 0) + '</strong></span></div><p>Кредит буде оформлено на номер телефону: <strong data-pay001-phone></strong></p></article>';
      return card;
    }

    html += providerCard('mono_chast', option.monoOptions || [], option.monoPreferred, Number(option.monoTotal || option.total), 'catalog/view/image/payment/pay001-mono-label.png', 'monobank', 'Покупка частинами monobank');
    if ((option.pumbOptions || []).length) {
      html += providerCard('pumb_credit', option.pumbOptions, option.pumbPreferred, Number(option.pumbTotal || option.total), 'catalog/view/image/payment/pay001-pumb.svg', 'ПУМБ', 'Сплачуйте частинами ПУМБ');
    } else if ((option.monoOptions || []).length) {
      html += '<article class="pay001-checkout-provider pay001-checkout-provider--soon"><div class="pay001-checkout-provider__head"><img src="catalog/view/image/payment/pay001-pumb.svg" alt="ПУМБ"><strong>Сплачуйте частинами ПУМБ</strong><em>СКОРО БУДЕ</em></div><small>До 5 платежів</small></article>';
    }
    html += '</div>';
    var $drawer = $(html);
    pay001SyncPhone($drawer);
    return $drawer;
  }
JS;
    $source = replaceRegexOnce(
        $source,
        '/  function pay001Drawer\(option, selectedCode\) \{.*?\n  \}\n  function renderPaymentMethods/s',
        $drawer . "\n  function renderPaymentMethods",
        'combined credit drawer'
    );

    $clickAnchor = <<<'JS'
    var $drawer = $button.closest('[data-pay001-drawer]');
    var total = Number(($drawer.find('.pay001-summary strong').first().text() || '0').replace(/[^\d]/g, '')) || 0;
    $drawer.find('[data-pay001-checkout-part]').removeClass('is-active');
    $button.addClass('is-active');
    $drawer.find('[data-pay001-monthly]').text(pay001Money(total / count));
    $drawer.find('[data-pay001-left]').text(Math.max(count - 1, 0));
JS;
    $clickReplacement = <<<'JS'
    var $drawer = $button.closest('[data-pay001-drawer]');
    var $provider = $button.closest('.pay001-checkout-provider');
    var total = Number(($provider.find('.pay001-summary strong').first().text() || '0').replace(/[^\d]/g, '')) || 0;
    $drawer.find('[data-pay001-checkout-part]').removeClass('is-active');
    $button.addClass('is-active');
    $provider.find('[data-pay001-monthly]').text(pay001Money(total / count));
    $provider.find('[data-pay001-left]').text(Math.max(count - 1, 0));
JS;
    $source = replaceExactOnce($source, $clickAnchor, $clickReplacement, 'provider-local installment summary');

    $blockedAnchor = <<<'JS'
    if (gate.configured && gate.reason) {
      options.push({
JS;
    $blockedReplacement = <<<'JS'
    var hasActiveCreditOption = false;
    $.each(options, function(_, option) {
      if (option.pay001Credit || option.pay002Credit) {
        hasActiveCreditOption = true;
        return false;
      }
    });
    if (gate.configured && gate.reason && !hasActiveCreditOption) {
      options.push({
JS;
    $source = replaceExactOnce($source, $blockedAnchor, $blockedReplacement, 'blocked Mono duplicate guard');

    $stamp = date('Ymd-His');
    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $stamp;
    $backupPath = $backup . '/' . $rel;
    need(is_dir(dirname($backupPath)) || mkdir(dirname($backupPath), 0755, true) || is_dir(dirname($backupPath)), 'cannot create backup directory');
    need(copy($path, $backupPath), 'backup failed: ' . $rel);

    try {
        writeChecked($path, $source);
        $written = file_get_contents($path);
        need(is_string($written), 'cannot read generated Twig');
        $afterHash = hash('sha256', $written);
        need(
            hash_equals(AFTER_SHA256, $afterHash),
            'generated SHA256 mismatch expected=' . AFTER_SHA256 . ' actual=' . $afterHash
        );
        out('sha_gate=ok before=' . substr($beforeHash, 0, 8) . ' after=' . substr($afterHash, 0, 8));
        need(substr_count($written, "id: 'credit_installments'") === 1, 'generated combined credit row assertion failed');
        need(substr_count($written, 'function providerCard(') === 1, 'generated provider drawer assertion failed');
        need(substr_count($written, 'СКОРО БУДЕ') === 1, 'generated conditional soon-card assertion failed');
        need(strpos($written, "(isPumb ? '' :") === false, 'stale provider conditional remains');
        need(substr_count($written, "\$provider.find('[data-pay001-monthly]')") === 1, 'generated provider-local monthly assertion failed');
        need(substr_count($written, "\$provider.find('[data-pay001-left]')") === 1, 'generated provider-local remaining-payments assertion failed');
        need(substr_count($written, 'if (gate.configured && gate.reason && !hasActiveCreditOption)') === 1, 'generated blocked Mono duplicate guard assertion failed');
        out('twig_assert=ok');
        writeChecked($marker, PATCH_ID . ' applied ' . date('c') . PHP_EOL);
        out('cwd=' . $root);
        out('time=' . date('c'));
        out('backup=' . $backup);
        out('changed=' . $rel);
        out('database_touched=no');
        out('done=ok');
    } catch (Throwable $e) {
        @copy($backupPath, $path);
        fail('source restored: ' . $e->getMessage());
    }

    if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes');
    else out('self_delete=ok');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
