<?php
/**
 * CHECKOUT-008 — add exact-code-gated IBAN requisites to the order-created
 * customer email and checkout success page.
 *
 * Files changed:
 * - catalog/controller/mail/order.php
 * - catalog/view/template/mail/order_add.twig
 * - catalog/controller/checkout/success.php
 * - catalog/view/template/checkout/success.twig
 *
 * No database or schema changes.
 * Rollback: restore the files from _patch_backups/CHECKOUT-008_iban-requisites-email-success-copy_20260729-<timestamp>/.
 *
 * Safety gate: this patch requires the owner-confirmed tax-ID label:
 *   --tax-id-label=edrpou  (renders: ЄДРПОУ)
 *   --tax-id-label=rnokpp  (renders: РНОКПП)
 * Use --dry-run first; it performs all file/anchor checks without writing.
 */
declare(strict_types=1);

const PATCH_ID = 'CHECKOUT-008_iban-requisites-email-success-copy_20260729';

function out(string $line): void {
	echo $line . PHP_EOL;
}

function fail(string $line): void {
	out('error=' . $line);
	exit(1);
}

function readFileStrict(string $file): string {
	if (!is_file($file)) {
		fail('missing_file=' . $file);
	}

	$contents = file_get_contents($file);

	if ($contents === false) {
		fail('read_failed=' . $file);
	}

	return $contents;
}

function replaceOnce(string $contents, string $anchor, string $replacement, string $file): string {
	$eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
	$anchor = str_replace("\n", $eol, $anchor);
	$replacement = str_replace("\n", $eol, $replacement);
	$count = substr_count($contents, $anchor);

	if ($count !== 1) {
		fail('anchor_count=' . $count . ' expected=1 file=' . $file);
	}

	return str_replace($anchor, $replacement, $contents);
}

function backupAndWrite(string $file, string $contents, string $backupDir, array &$written): void {
	$backup = $backupDir . DIRECTORY_SEPARATOR . $file;
	$parent = dirname($backup);

	if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
		fail('backup_dir_create_failed=' . $parent);
	}

	if (!copy($file, $backup)) {
		fail('backup_copy_failed=' . $file);
	}

	if (file_put_contents($file, $contents) === false) {
		@copy($backup, $file);
		fail('write_failed=' . $file);
	}

	$written[] = $file;
}

function restoreWritten(array $written, string $backupDir): void {
	foreach ($written as $file) {
		$backup = $backupDir . DIRECTORY_SEPARATOR . $file;

		if (is_file($backup)) {
			@copy($backup, $file);
		}
	}
}

function lintPhp(string $file): void {
	$output = [];
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

	if ($code !== 0) {
		throw new RuntimeException('php_lint_failed file=' . $file . ' output=' . implode(' | ', $output));
	}

	out('php_lint=ok file=' . $file);
}

$dryRun = in_array('--dry-run', $argv, true);
$taxIdLabel = null;

foreach ($argv as $arg) {
	if (!str_starts_with($arg, '--tax-id-label=')) {
		continue;
	}

	$value = strtolower(substr($arg, strlen('--tax-id-label=')));
	$taxIdLabel = match ($value) {
		'edrpou' => 'ЄДРПОУ',
		'rnokpp' => 'РНОКПП',
		default => null
	};

	if ($taxIdLabel === null) {
		fail('invalid_tax_id_label=use_edrpou_or_rnokpp');
	}
}

if (!$dryRun && $taxIdLabel === null) {
	fail('owner_confirmation_required=rerun_with_--tax-id-label=edrpou_or_rnokpp');
}

if ($taxIdLabel === null) {
	$taxIdLabel = '__OWNER_CONFIRMATION_REQUIRED__';
}

$mailController = 'catalog/controller/mail/order.php';
$mailTemplate = 'catalog/view/template/mail/order_add.twig';
$successController = 'catalog/controller/checkout/success.php';
$successTemplate = 'catalog/view/template/checkout/success.twig';
$files = [$mailController, $mailTemplate, $successController, $successTemplate];

out('cwd=' . getcwd());
out('time=' . date('c'));
out('mode=' . ($dryRun ? 'dry-run' : 'apply'));

$contents = [];
foreach ($files as $file) {
	$contents[$file] = readFileStrict($file);
}

$markers = [
	$mailController => 'CHECKOUT-008: exact payment-code gate for IBAN requisites in the order-created email.',
	$mailTemplate => '{# CHECKOUT-008: exact-code-gated IBAN requisites. #}',
	$successController => 'CHECKOUT-008: exact payment-code gate for IBAN requisites on success.',
	$successTemplate => '{# CHECKOUT-008: copyable IBAN requisites for the just-created order. #}'
];
$markerCount = 0;

foreach ($markers as $file => $marker) {
	if (str_contains($contents[$file], $marker)) {
		$markerCount++;
	}
}

if ($markerCount === count($markers)) {
	out('already_applied=yes');
	out('done=ok');
	@unlink(__FILE__);
	exit(0);
}

if ($markerCount > 0) {
	fail('partial_markers_found=count=' . $markerCount);
}

$mailControllerOld = <<<'PHP'
		$data['payment_method'] = $order_info['payment_method']['name'] ?? '';
		$data['shipping_method'] = $order_info['shipping_method']['name'] ?? '';
		$data['email'] = $order_info['email'];
		$data['telephone'] = $order_info['telephone'];
		$data['ip'] = $order_info['ip'];
		$data['show_bank_details'] = false;

		$payment_method_name = oc_strtolower(html_entity_decode($data['payment_method'], ENT_QUOTES, 'UTF-8'));

		if (strpos($payment_method_name, 'реквізит') !== false || strpos($payment_method_name, 'iban') !== false) {
			$data['show_bank_details'] = true;
		}
PHP;
$mailControllerNew = <<<'PHP'
		$data['payment_method'] = $order_info['payment_method']['name'] ?? '';
		$data['shipping_method'] = $order_info['shipping_method']['name'] ?? '';
		$data['email'] = $order_info['email'];
		$data['telephone'] = $order_info['telephone'];
		$data['ip'] = $order_info['ip'];

		// CHECKOUT-008: exact payment-code gate for IBAN requisites in the order-created email.
		// Names are presentation text and may change; only the selected extension option is authoritative.
		$payment_code = strtolower(trim((string)($order_info['payment_method']['code'] ?? '')));
		$data['show_bank_details'] = $payment_code === 'bank_transfer.bank_transfer';
PHP;
$contents[$mailController] = replaceOnce($contents[$mailController], $mailControllerOld, $mailControllerNew, $mailController);

$mailTemplateOld = <<<'TWIG'
          {% if show_bank_details %}
          <tr>
            <td style="padding:18px 28px 0 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background:#FEF3C7; border:1px solid #E5E7EB; border-collapse:collapse;">
                <tr>
                  <td style="padding:16px 18px;">
                    <div style="margin:0 0 10px 0; color:#111827; font-size:16px; line-height:22px; font-weight:700;">
                      Реквізити для оплати
                    </div>
                    <div style="margin:0; color:#1F2937; font-size:14px; line-height:22px;">
                      Отримувач: ФОП Леусенко Євгеній Андрійович<br>
                      ЄДРПОУ: 3485903435<br>
                      IBAN: UA063348510000000026003285008<br>
                      МФО: 334851<br>
                      Банк отримувача: АТ «ПУМБ»<br>
                      Призначення платежу: оплата за товар
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          {% endif %}
TWIG;
$mailTemplateNew = str_replace('__TAX_ID_LABEL__', $taxIdLabel, <<<'TWIG'
          {% if show_bank_details %}
          {# CHECKOUT-008: exact-code-gated IBAN requisites. #}
          <tr>
            <td style="padding:18px 28px 0 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background:#FEF3C7; border:1px solid #E5E7EB; border-collapse:collapse;">
                <tr>
                  <td style="padding:16px 18px;">
                    <div style="margin:0 0 10px 0; color:#111827; font-size:16px; line-height:22px; font-weight:700;">
                      Реквізити для оплати
                    </div>
                    <div style="margin:0; color:#1F2937; font-size:14px; line-height:22px;">
                      Отримувач: ФОП Леусенко Євгеній Андрійович<br>
                      __TAX_ID_LABEL__: 3485903435<br>
                      IBAN: UA063348510000000026003285008<br>
                      МФО: 334851<br>
                      Банк: АТ «ПУМБ»<br>
                      Призначення платежу: оплата за товар
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          {% endif %}
TWIG);
$contents[$mailTemplate] = replaceOnce($contents[$mailTemplate], $mailTemplateOld, $mailTemplateNew, $mailTemplate);

$successControllerOld = <<<'PHP'
				$is_hutko = $payment_code === 'hutko' || strpos($payment_code, 'hutko.') === 0;
				$is_cod = $payment_code === 'cod' || strpos($payment_code, 'cod.') === 0 || strpos($payment_code, 'pinta_nova_poshta_cod') !== false;
PHP;
$successControllerNew = <<<'PHP'
				$is_hutko = $payment_code === 'hutko' || strpos($payment_code, 'hutko.') === 0;
				$is_cod = $payment_code === 'cod' || strpos($payment_code, 'cod.') === 0 || strpos($payment_code, 'pinta_nova_poshta_cod') !== false;
				// CHECKOUT-008: exact payment-code gate for IBAN requisites on success.
				$is_iban_bank_transfer = $payment_code === 'bank_transfer.bank_transfer';
PHP;
$contents[$successController] = replaceOnce($contents[$successController], $successControllerOld, $successControllerNew, $successController);

$successDataOld = <<<'PHP'
					'is_hutko'        => $is_hutko,
					'is_cod'          => $is_cod,
					'show_first15_offer' => $show_first15_offer,
PHP;
$successDataNew = <<<'PHP'
					'is_hutko'        => $is_hutko,
					'is_cod'          => $is_cod,
					'is_iban_bank_transfer' => $is_iban_bank_transfer,
					'show_first15_offer' => $show_first15_offer,
PHP;
$contents[$successController] = replaceOnce($contents[$successController], $successDataOld, $successDataNew, $successController);

$successTemplateAnchor = <<<'TWIG'
          </section>
        {% endif %}

        {% if order_items %}
TWIG;
$successRequisites = str_replace('__TAX_ID_LABEL__', $taxIdLabel, <<<'TWIG'
          </section>
        {% endif %}

        {% if order_data.is_iban_bank_transfer|default(false) %}
          {# CHECKOUT-008: copyable IBAN requisites for the just-created order. #}
          <section class="bs-success-meta bs-card" aria-labelledby="checkout008-iban-title">
            <h2 id="checkout008-iban-title" class="bs-success-section-title">Реквізити для оплати</h2>
            <p>Отримувач: ФОП Леусенко Євгеній Андрійович<br>
              __TAX_ID_LABEL__: 3485903435<br>
              IBAN: UA063348510000000026003285008<br>
              МФО: 334851<br>
              Банк: АТ «ПУМБ»<br>
              Призначення платежу: оплата за товар</p>
            <button type="button" class="bs-btn bs-btn-secondary" data-checkout008-copy-requisites>Скопіювати реквізити</button>
            <span data-checkout008-copy-status role="status" aria-live="polite" hidden></span>
          </section>
          <script>
          (function () {
            var button = document.querySelector('[data-checkout008-copy-requisites]');
            var status = document.querySelector('[data-checkout008-copy-status]');
            var requisites = 'Реквізити для оплати:\nОтримувач: ФОП Леусенко Євгеній Андрійович\n__TAX_ID_LABEL__: 3485903435\nIBAN: UA063348510000000026003285008\nМФО: 334851\nБанк: АТ «ПУМБ»\nПризначення платежу: оплата за товар';

            if (!button || !status) {
              return;
            }

            function setStatus(message) {
              status.textContent = message;
              status.hidden = false;
            }

            function fallbackCopy() {
              var textarea = document.createElement('textarea');
              textarea.value = requisites;
              textarea.setAttribute('readonly', '');
              // A fixed invisible textarea prevents a visual jump while supporting browsers without Clipboard API.
              textarea.style.position = 'fixed';
              textarea.style.opacity = '0';
              document.body.appendChild(textarea);
              textarea.select();
              var copied = document.execCommand('copy');
              document.body.removeChild(textarea);
              return copied;
            }

            button.addEventListener('click', function () {
              if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(requisites).then(function () {
                  setStatus('Реквізити скопійовано.');
                }).catch(function () {
                  setStatus(fallbackCopy() ? 'Реквізити скопійовано.' : 'Не вдалося скопіювати реквізити.');
                });
              } else {
                setStatus(fallbackCopy() ? 'Реквізити скопійовано.' : 'Не вдалося скопіювати реквізити.');
              }
            });
          }());
          </script>
        {% endif %}

        {% if order_items %}
TWIG);
$contents[$successTemplate] = replaceOnce($contents[$successTemplate], $successTemplateAnchor, $successRequisites, $successTemplate);

if ($dryRun) {
	out('owner_confirmation_required=yes');
	out('tax_id_label=not_selected');
	out('preflight=ok');
	out('changed_files=4');
	out('done=ok');
	exit(0);
}

$backupDir = '_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$written = [];

foreach ($files as $file) {
	backupAndWrite($file, $contents[$file], $backupDir, $written);
}

try {
	lintPhp($mailController);
	lintPhp($successController);
} catch (Throwable $e) {
	restoreWritten($written, $backupDir);
	fail('restored_after_validation_failure=' . $e->getMessage());
}

out('backup=' . $backupDir);
out('changed_files=4');
out('cache_clear=required');
out('done=ok');
@unlink(__FILE__);
