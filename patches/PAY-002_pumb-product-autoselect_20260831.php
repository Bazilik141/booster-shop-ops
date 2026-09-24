<?php
/**
 * PAY-002 hotfix — auto-select PUMB and its product-modal term in checkout.
 * File-only. Run from ~/public_html. No database changes.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-product-autoselect_20260831';
const BEFORE_SHA256 = [
    'catalog/controller/checkout/checkout.php' => '39bb445651806fde1451bd8d47531c9040fc7d4f926fda123e24e55ee5027c10',
    'catalog/controller/checkout/payment_method.php' => 'f70a32e2b66b7b7e14053199e1713879be626d28da166b9b11bee0a55d9e8ae4',
    'catalog/view/template/checkout/payment_method.twig' => 'ea4b41df3577f656ecc57dc02661246ba65e3f6d3deb63dc593517b05850b56b'
];
const AFTER_SHA256 = [
    'catalog/controller/checkout/checkout.php' => '96ef34ad3c0c2b3c980d6ffe54953da3f66e2cd1320f9f549305f5c3f98289c9',
    'catalog/controller/checkout/payment_method.php' => '0497216e9267f92c385ea43b43e09fc0a9531ff5df71a6e7d954d6f816c9b79c',
    'catalog/view/template/checkout/payment_method.twig' => 'fb57eef71fe3141d4b7d2713ecc2c465ab371476786f491399a4d195764d0f3b'
];

function out(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException('ERROR: ' . $message); }
function need(bool $condition, string $message): void { if (!$condition) fail($message); }
function readChecked(string $path): string { $value = file_get_contents($path); need(is_string($value), 'cannot read ' . $path); return $value; }
function writeChecked(string $path, string $content): void { $written = file_put_contents($path, $content); need($written !== false && $written === strlen($content), 'write failed: ' . $path); }
function replaceExactOnce(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    return str_replace($anchor, $replacement, $source);
}
function replaceRegexOnce(string $source, string $pattern, string $replacement, string $label): string {
    $count = preg_match_all($pattern, $source, $matches);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    $changed = 0;
    $result = preg_replace($pattern, $replacement, $source, 1, $changed);
    need(is_string($result) && $changed === 1, 'replacement failed for ' . $label);
    return $result;
}
function backupFile(string $root, string $backup, string $rel): void {
    $destination = $backup . '/' . $rel;
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0755, true) && !is_dir(dirname($destination))) fail('cannot create backup directory');
    need(copy($root . '/' . $rel, $destination), 'backup failed: ' . $rel);
}
function restoreVerified(string $root, string $backup, array $files): void {
    foreach ($files as $rel) {
        $backupPath = $backup . '/' . $rel;
        need(is_file($backupPath) && copy($backupPath, $root . '/' . $rel), 'restore copy failed: ' . $rel);
        need(hash_equals(BEFORE_SHA256[$rel], hash_file('sha256', $root . '/' . $rel)), 'restore SHA256 mismatch: ' . $rel);
    }
}
function lint(string $path): void {
    $lines = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lines, $status);
    need($status === 0, 'php -l failed for ' . $path . ': ' . implode(' ', $lines));
    out('php_l=ok file=' . $path);
}

try {
    $root = getcwd() ?: '.';
    need(is_file($root . '/config.php'), 'Run from OpenCart public_html (config.php missing).');
    $files = array_keys(BEFORE_SHA256);
    foreach ($files as $rel) need(is_file($root . '/' . $rel), 'missing live file: ' . $rel);
    $marker = $root . '/extension/pumb_credit/.pay002-product-autoselect-marker';
    if (is_file($marker)) { out('already_applied=yes'); exit(0); }
    need(is_dir(dirname($marker)), 'missing deployed PUMB extension directory for marker');

    $source = [];
    foreach ($files as $rel) {
        $source[$rel] = readChecked($root . '/' . $rel);
        $actual = hash('sha256', $source[$rel]);
        need(hash_equals(BEFORE_SHA256[$rel], $actual), 'live source SHA256 mismatch for ' . $rel . ' expected=' . BEFORE_SHA256[$rel] . ' actual=' . $actual . '; write skipped');
    }

    $checkoutAnchor = <<<'PHP'
		if ($pay001_valid && !$pay002_valid) {
			// PAY-001-PHASE2C-D3-CREDIT-TERM-20260725:
			// a fresh modal redirect is authoritative over a credit method saved
			// by an earlier product. Clear either credit provider so the requested
			// Mono term is selected again by payment_method.twig.
			if (str_starts_with($current_credit_code, 'mono_chast.') || str_starts_with($current_credit_code, 'pumb_credit.')) {
				unset($this->session->data['payment_method']);
			}
			$this->session->data['pay001_mono_chast_parts'] = $pay001_parts;
			$this->session->data['pay001_mono_chast_from_modal'] = 1;
			unset($this->session->data['pay002_pumb_credit_term']);
		} elseif ($pay002_valid && !$pay001_valid) {
			// PAY-002: the PUMB product modal uses its own URL parameter and must
			// clear any earlier Mono modal preference before checkout renders.
			if (str_starts_with($current_credit_code, 'mono_chast.') || str_starts_with($current_credit_code, 'pumb_credit.')) {
				unset($this->session->data['payment_method']);
			}
			$this->session->data['pay002_pumb_credit_term'] = $pay002_pumb_term;
			unset($this->session->data['pay001_mono_chast_parts'], $this->session->data['pay001_mono_chast_from_modal']);
		} else {
			// Direct, malformed, or ambiguous URLs must not inherit either modal choice.
			unset($this->session->data['pay001_mono_chast_parts'], $this->session->data['pay001_mono_chast_from_modal'], $this->session->data['pay002_pumb_credit_term']);
		}
PHP;
    $checkoutReplacement = <<<'PHP'
		if ($pay001_valid && !$pay002_valid) {
			// PAY-001-PHASE2C-D3-CREDIT-TERM-20260725:
			// a fresh modal redirect is authoritative over a credit method saved
			// by an earlier product. Clear either credit provider so the requested
			// Mono term is selected again by payment_method.twig.
			if (str_starts_with($current_credit_code, 'mono_chast.') || str_starts_with($current_credit_code, 'pumb_credit.')) {
				unset($this->session->data['payment_method']);
			}
			$this->session->data['pay001_mono_chast_parts'] = $pay001_parts;
			$this->session->data['pay001_mono_chast_from_modal'] = 1;
			unset($this->session->data['pay002_pumb_credit_term'], $this->session->data['pay002_pumb_credit_from_modal']);
		} elseif ($pay002_valid && !$pay001_valid) {
			// PAY-002: PUMB must carry the same modal-origin signal as Mono so
			// payment_method.twig saves the requested PUMB virtual option automatically.
			if (str_starts_with($current_credit_code, 'mono_chast.') || str_starts_with($current_credit_code, 'pumb_credit.')) {
				unset($this->session->data['payment_method']);
			}
			$this->session->data['pay002_pumb_credit_term'] = $pay002_pumb_term;
			$this->session->data['pay002_pumb_credit_from_modal'] = 1;
			unset($this->session->data['pay001_mono_chast_parts'], $this->session->data['pay001_mono_chast_from_modal']);
		} else {
			// Direct, malformed, or ambiguous URLs must not inherit either modal choice.
			unset($this->session->data['pay001_mono_chast_parts'], $this->session->data['pay001_mono_chast_from_modal'], $this->session->data['pay002_pumb_credit_term'], $this->session->data['pay002_pumb_credit_from_modal']);
		}
PHP;
    $checkout = replaceExactOnce($source['catalog/controller/checkout/checkout.php'], $checkoutAnchor, $checkoutReplacement, 'product-modal session hand-off');

    $method = replaceExactOnce(
        $source['catalog/controller/checkout/payment_method.php'],
        "'pay002_credit'=>true,'pay002_preferred'=>\$preferred,'pay002_total'=>(float)\$g['payable']",
        "'pay002_credit'=>true,'pay002_preferred'=>\$preferred,'pay002_from_modal'=>!empty(\$this->session->data['pay002_pumb_credit_from_modal']),'pay002_total'=>(float)\$g['payable']",
        'PUMB modal-origin metadata'
    );
    // Keep the Twig file's own line ending; PHP_EOL differs between local Windows
    // validation and the production Linux host and would invalidate the SHA gate.
    $twigLineEnding = str_contains($source['catalog/view/template/checkout/payment_method.twig'], "\r\n") ? "\r\n" : "\n";
    $twig = replaceRegexOnce(
        $source['catalog/view/template/checkout/payment_method.twig'],
        '/(creditOption\\.pumbTotal = Number\\(group\\.pay002_total\\) \\|\\| creditOption\\.total;)\\s*}/',
        '$1' . $twigLineEnding . '              creditOption.fromModal = creditOption.fromModal || !!group.pay002_from_modal;' . $twigLineEnding . '            }',
        'combined credit modal-origin metadata'
    );
    $twig = replaceRegexOnce(
        $twig,
        '/creditOption\\.fromModal\\s*=\\s*!!group\\.pay001_from_modal;/',
        'creditOption.fromModal = creditOption.fromModal || !!group.pay001_from_modal;',
        'Mono modal-origin metadata preservation'
    );

    $changed = [
        'catalog/controller/checkout/checkout.php' => $checkout,
        'catalog/controller/checkout/payment_method.php' => $method,
        'catalog/view/template/checkout/payment_method.twig' => $twig
    ];
    $stamp = date('Ymd-His');
    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . $stamp;
    foreach ($files as $rel) backupFile($root, $backup, $rel);

    try {
        foreach ($changed as $rel => $content) writeChecked($root . '/' . $rel, $content);
        lint($root . '/catalog/controller/checkout/checkout.php');
        lint($root . '/catalog/controller/checkout/payment_method.php');
        foreach ($files as $rel) {
            $actual = hash_file('sha256', $root . '/' . $rel);
            need(is_string($actual), 'cannot hash generated file: ' . $rel);
            if (AFTER_SHA256[$rel] !== '') need(hash_equals(AFTER_SHA256[$rel], $actual), 'generated SHA256 mismatch for ' . $rel . ' expected=' . AFTER_SHA256[$rel] . ' actual=' . $actual);
            out('after_sha256=' . $rel . ':' . $actual);
        }
        need(substr_count($checkout, 'pay002_pumb_credit_from_modal') === 3, 'generated PUMB session flag assertion failed');
        need(substr_count($method, "'pay002_from_modal'=>!empty(\$this->session->data['pay002_pumb_credit_from_modal'])") === 1, 'generated PUMB method metadata assertion failed');
        need(substr_count($twig, 'creditOption.fromModal = creditOption.fromModal || !!group.pay002_from_modal;') === 1, 'generated PUMB Twig metadata assertion failed');
        need(substr_count($twig, 'creditOption.fromModal = creditOption.fromModal || !!group.pay001_from_modal;') === 1, 'generated Mono Twig metadata assertion failed');
        out('assertions=ok');
        writeChecked($marker, PATCH_ID . ' applied ' . date('c') . PHP_EOL);
        out('cwd=' . $root);
        out('time=' . date('c'));
        out('backup=' . $backup);
        out('changed=' . implode(',', $files));
        out('database_touched=no');
        out('done=ok');
    } catch (Throwable $exception) {
        $reason = $exception->getMessage();
        restoreVerified($root, $backup, $files);
        fail('source restored: ' . $reason);
    }

    if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes');
    else out('self_delete=ok');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
