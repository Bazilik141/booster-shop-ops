<?php
declare(strict_types=1);

/*
 * ACC-002C — compact delivery-card summary — 2026-07-14
 *
 * No DB changes. Keeps the collapsed checkout delivery card concise: delivery
 * type plus only the warehouse/poshtomat number. Full point/address labels
 * remain available inside the expanded delivery section.
 */

$patch = 'ACC-002C_delivery-card-compact-summary_20260714';
$root = getcwd();
$dryRun = in_array('--dry-run', $argv ?? [], true);

function acc002c_fail(string $message): void {
	fwrite(STDERR, "error={$message}\n");
	exit(1);
}

function acc002c_lf(string $value): string {
	return str_replace(["\r\n", "\r"], "\n", $value);
}

function acc002c_eol(string $value): string {
	return str_contains($value, "\r\n") ? "\r\n" : "\n";
}

function acc002c_restore_eol(string $value, string $eol): string {
	return $eol === "\n" ? $value : str_replace("\n", $eol, $value);
}

function acc002c_replace_once(string $source, string $find, string $replace, string $relative): string {
	$count = substr_count($source, $find);

	if ($count !== 1) {
		acc002c_fail("anchor_count path={$relative} expected=1 actual={$count}");
	}

	return str_replace($find, $replace, $source);
}

$relative = 'catalog/view/javascript/checkout-reskin.js';
$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

if (!is_file($path)) {
	acc002c_fail("target_missing path={$relative}");
}

$raw = file_get_contents($path);
if ($raw === false) {
	acc002c_fail("read_failed path={$relative}");
}

$marker = 'ACC-002C compact delivery-card summary';
if (str_contains($raw, $marker)) {
	echo "already_applied=yes patch={$patch}\n";
	exit(0);
}

$eol = acc002c_eol($raw);
$source = acc002c_lf($raw);
$changed = acc002c_replace_once(
	$source,
	<<<'FIND'
  function deliverySummaryText() {
    // Saved address: "Нова пошта · поштомат №49489" / "… · відділення №22" /
    // "… · адреса вул. …". Manual mode: from the currently filled NP fields.
    if (savedAddressMode()) {
      var metadata = savedNpMetadataForSelectedAddress();

      if (metadata) {
        var metadataType = moduleTypeFromSavedMetadata(metadata);
        var metadataDetail = metadata.labels && metadata.labels.point ? metadata.labels.point : '';
        return text('Нова пошта · ' + (TYPE_SUMMARY_LABEL[metadataType] || 'збережена адреса') + (metadataDetail ? ' ' + metadataDetail : ''));
      }

      // Legacy addresses retain text parsing for presentation only. Hydration
      // is never decided from this fallback.
      var parsed = parseAddressText(selectedSavedAddressText());

      if (parsed && parsed.type) {
        var typeLabel = TYPE_SUMMARY_LABEL[parsed.type] || '';
        var detail = parsed.type === 'doors' ? parsed.street : (parsed.number ? '№' + parsed.number : '');
        return text('Нова пошта · ' + typeLabel + (detail ? ' ' + detail : ''));
      }

      return selectedSavedAddressText() ? 'Нова пошта · збережена адреса' : '';
    }

    var select = npTypeSelect();

    if (select) {
      var typeName = TYPE_SUMMARY_LABEL[select.value] || '';
      var detailNode = select.value === 'doors'
        ? document.getElementById('input-shipping-novaposhta-doors-street')
        : document.getElementById('input-shipping-novaposhta-warehouse-address');
      var detailText = detailNode ? text(detailNode.value) : '';
      var numberMatch = detailText.match(/№\s*(\d+)/);

      if (detailText) {
        detailText = numberMatch ? '№' + numberMatch[1] : detailText;
        return text('Нова пошта · ' + typeName + ' ' + detailText);
      }

      return typeName ? 'Нова пошта · ' + typeName : '';
    }
FIND,
	<<<'REPLACE'
  // ACC-002C compact delivery-card summary: the collapsed card is a status
  // recap, not an address label. Detailed NP data stays in the expanded form.
  function deliverySummaryText() {
    function compactNpSummary(type, point) {
      var typeLabel = TYPE_SUMMARY_LABEL[type] || 'збережена адреса';
      var numberMatch = text(point).match(/№\s*(\d+)/);
      var detail = (type === 'warehouse' || type === 'poshtoma') && numberMatch ? ' №' + numberMatch[1] : '';

      return text('Нова пошта · ' + typeLabel + detail);
    }

    if (savedAddressMode()) {
      var metadata = savedNpMetadataForSelectedAddress();

      if (metadata) {
        return compactNpSummary(moduleTypeFromSavedMetadata(metadata), metadata.labels && metadata.labels.point ? metadata.labels.point : '');
      }

      // Legacy parsing is presentation-only. Never use it to hydrate an address.
      var parsed = parseAddressText(selectedSavedAddressText());

      if (parsed && parsed.type) {
        return compactNpSummary(parsed.type, parsed.number ? '№' + parsed.number : '');
      }

      return selectedSavedAddressText() ? 'Нова пошта · збережена адреса' : '';
    }

    var select = npTypeSelect();

    if (select) {
      var detailNode = select.value === 'doors'
        ? document.getElementById('input-shipping-novaposhta-doors-street')
        : document.getElementById('input-shipping-novaposhta-warehouse-address');
      var detailText = detailNode ? text(detailNode.value) : '';

      return compactNpSummary(select.value, detailText);
    }
REPLACE,
	$relative
);

if (!str_contains($changed, $marker)) {
	acc002c_fail("postcheck_marker_missing path={$relative}");
}

echo 'cwd=' . $root . ' time=' . date('c') . "\n";

if ($dryRun) {
	echo "dry_run=ok patch={$patch} files=1 php_l=not_applicable\n";
	exit(0);
}

$backup = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . date('Ymd_His');
$backupPath = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

if (!is_dir(dirname($backupPath)) && !mkdir(dirname($backupPath), 0775, true) && !is_dir(dirname($backupPath))) {
	acc002c_fail('backup_dir_failed');
}

if (!copy($path, $backupPath)) {
	acc002c_fail("backup_failed path={$relative}");
}

if (file_put_contents($path, acc002c_restore_eol($changed, $eol)) === false) {
	@copy($backupPath, $path);
	acc002c_fail("write_failed path={$relative} rollback=attempted");
}

$written = file_get_contents($path);
if ($written === false || !str_contains($written, $marker)) {
	@copy($backupPath, $path);
	acc002c_fail("postwrite_check_failed path={$relative} rollback=ok backup={$backup}");
}

echo "done=ok patch={$patch} files=1 php_l=not_applicable backup={$backup}\n";
@unlink(__FILE__);
