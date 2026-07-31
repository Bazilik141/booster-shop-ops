<?php
declare(strict_types=1);

/**
 * Run from ~/public_html.
 * Aligns Pinta's post-discount threshold calculation with OpenCart's
 * pass-by-reference model contract and makes checkout progress consume the
 * fresh server-rendered payable total after a mini-cart shipping re-save.
 * No DB, payment, order-write, Hutko or SimpleCheckout changes.
 */
$id = 'ST-2c_minicart_shipping_threshold_alignment_20260729';
$mark = 'ST-2C-MINICART-THRESHOLD-ALIGNMENT-20260729';
$root = getcwd() ?: __DIR__;
$dry = in_array('--dry-run', $argv ?? [], true);
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
$files = [
    'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php' => '2850A40850F8FDB660833067137A52008B3408554437EBC552E242F10B5AB2BF',
    'catalog/view/javascript/checkout-reskin.js' => '93A0FA23D6B403BE9312E698550913E17F4EF29973F4ABE8F248B3A2D6179F0F',
];

function st2ca_out(string $value): void { echo '[' . date('c') . '] ' . $value . PHP_EOL; }
function st2ca_fail(string $value): void { st2ca_out('error=' . $value); st2ca_out('done=failed'); exit(1); }
function st2ca_normalize(string $value): string { return str_replace(["\r\n", "\r"], "\n", $value); }
function st2ca_replace(string $source, string $old, string $new, string $name): string {
    $count = substr_count($source, $old);
    if ($count !== 1) st2ca_fail('anchor_' . $name . '_count=' . $count);
    return str_replace($old, $new, $source);
}
function st2ca_lint(string $path): bool {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $code);
    st2ca_out('php_lint=' . ($code === 0 ? 'ok' : 'failed') . ' file=' . $path);
    return $code === 0;
}

st2ca_out('patch=' . $id);
st2ca_out('cwd=' . $root);
st2ca_out('time=' . date('c'));
$source = []; $eol = []; $all = true;
foreach ($files as $file => $hash) {
    $path = $root . '/' . $file;
    if (!is_file($path)) st2ca_fail('missing_file=' . $file);
    $raw = file_get_contents($path);
    if ($raw === false) st2ca_fail('cannot_read=' . $file);
    $all = $all && str_contains($raw, $mark);
    $eol[$file] = str_contains($raw, "\r\n") ? "\r\n" : "\n";
    if (!str_contains($raw, $mark) && strtoupper(hash('sha256', $raw)) !== $hash) st2ca_fail('sha256_mismatch=' . $file);
    $source[$file] = st2ca_normalize($raw);
}
if ($all) { st2ca_out('already_applied=yes'); st2ca_out('done=ok'); @unlink(__FILE__); exit(0); }
foreach ($source as $file => $raw) if (str_contains($raw, $mark)) st2ca_fail('partial_marker_state=' . $file);

$pinta = 'extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php';
$source[$pinta] = st2ca_replace($source[$pinta],
    '        $this->model_checkout_cart->getTotals($totals, $taxes, $total);',
    "        // ST-2C-MINICART-THRESHOLD-ALIGNMENT-20260729: OpenCart's magic\n" .
    "        // model proxy needs its callable form so referenced totals are populated.\n" .
    '        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);',
    'pinta_get_totals');

$reskin = 'catalog/view/javascript/checkout-reskin.js';
$source[$reskin] = st2ca_replace($source[$reskin], <<<'JS'
    var subtotalAmount = isFinite(serverSubtotal) && serverSubtotal >= 0
      ? serverSubtotal
      : (subtotal ? parseMoney(subtotal.value) : 0);
JS, <<<'JS'
    // ST-2C-MINICART-THRESHOLD-ALIGNMENT-20260729: shipping save returns a
    // current server-rendered grand total. Prefer it over a coupon-summary
    // subtotal cached before a mini-cart update; shipping remains display-only.
    var renderedPayableTotal = grand ? parseMoney(grand.value) : NaN;
    var subtotalAmount = isFinite(renderedPayableTotal) && renderedPayableTotal >= 0
      ? renderedPayableTotal
      : (isFinite(serverSubtotal) && serverSubtotal >= 0
        ? serverSubtotal
        : (subtotal ? parseMoney(subtotal.value) : 0));
JS, 'reskin_total_basis');

foreach ($source as $file => $raw) if (!str_contains($raw, $mark)) st2ca_fail('postcheck_marker_missing=' . $file);
if ($dry) { st2ca_out('dry_run=ok'); st2ca_out('done=ok'); exit(0); }
foreach ($files as $file => $_) {
    $target = $backup . '/' . $file;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) st2ca_fail('cannot_create_backup_dir=' . $file);
    if (!copy($root . '/' . $file, $target)) st2ca_fail('cannot_backup=' . $file);
}
st2ca_out('backup=' . str_replace($root . '/', '', $backup));
$written = [];
foreach ($source as $file => $raw) {
    $output = $eol[$file] === "\r\n" ? str_replace("\n", "\r\n", $raw) : $raw;
    if (file_put_contents($root . '/' . $file, $output) === false) {
        foreach ($written as $restore) copy($backup . '/' . $restore, $root . '/' . $restore);
        st2ca_fail('cannot_write=' . $file);
    }
    $written[] = $file;
    st2ca_out('changed=' . $file);
}
if (!st2ca_lint($root . '/' . $pinta)) {
    foreach ($written as $restore) copy($backup . '/' . $restore, $root . '/' . $restore);
    st2ca_fail('rollback_after_php_lint');
}
st2ca_out('js_syntax=owner_run_required');
st2ca_out('done=ok');
@unlink(__FILE__);
