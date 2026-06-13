<?php
declare(strict_types=1);

/**
 * ST-2a.8 - guest autosave ref gate.
 *
 * Scope: catalog/view/template/checkout/checkout.twig JS only.
 * No DB, no controllers, no POST field shape changes.
 */

$patch = 'st2a8_guest_autosave_ref_gate_20260613';
$root = getcwd() ?: __DIR__;
$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$file = 'catalog/view/template/checkout/checkout.twig';
$path = $root . '/' . $file;
$marker = 'ST-2a.8: canonical hidden NP refs are authoritative for autosave completeness';

function out(string $message): void {
    echo '[' . date('c') . '] ' . $message . PHP_EOL;
}

function fail(string $message): void {
    out('error=' . $message);
    out('done=failed');
    exit(1);
}

function replace_once(string $content, string $old, string $new, string $label): string {
    $count = substr_count($content, $old);
    if ($count !== 1) {
        fail('pre-check failed for ' . $label . ': expected 1 match, got ' . $count);
    }

    return str_replace($old, $new, $content);
}

out('patch=' . $patch);
out('cwd=' . $root);
out('scope=checkout.twig JS: use canonical hidden NP refs for autosave completeness; no DB');
out('target=' . $file);

if (!is_file($path)) {
    fail('missing ' . $file . '; upload patch to OpenCart public_html root');
}

$content = file_get_contents($path);
if ($content === false) {
    fail('cannot read ' . $file);
}

if (strpos($content, $marker) !== false) {
    out('already_applied=yes');
    out('changed=none');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old = <<<'TWIG'
  function fieldRef(selector) {
    return normalizeText(field(selector).attr('data-ref'));
  }

  function refFieldName(id) {
    var map = {
      'input-shipping-novaposhta-area': 'shipping_novaposhta_area_ref',
      'input-shipping-novaposhta-city': 'shipping_novaposhta_city_ref',
      'input-shipping-novaposhta-warehouse-address': 'shipping_novaposhta_warehouse_ref',
      'input-shipping-novaposhta-doors-street': 'shipping_novaposhta_street_ref'
    };

    return map[id] || '';
  }

  function setHiddenRef(id, ref) {
    var name = refFieldName(id);

    if (name) {
      $('input[name="' + name + '"]').val(ref || '');
    }
  }
TWIG;

$new = <<<'TWIG'
  // ST-2a.8: canonical hidden NP refs are authoritative for autosave completeness.
  function refFieldName(id) {
    var map = {
      'input-shipping-novaposhta-area': 'shipping_novaposhta_area_ref',
      'input-shipping-novaposhta-city': 'shipping_novaposhta_city_ref',
      'input-shipping-novaposhta-warehouse-address': 'shipping_novaposhta_warehouse_ref',
      'input-shipping-novaposhta-doors-street': 'shipping_novaposhta_street_ref'
    };

    return map[id] || '';
  }

  function hiddenRef(id) {
    var name = refFieldName(id);

    return name ? normalizeText($('input[name="' + name + '"]').val()) : '';
  }

  function fieldRef(selector) {
    var element = field(selector);
    var id = element.attr('id') || '';

    return normalizeText(element.attr('data-ref')) || hiddenRef(id);
  }

  function setHiddenRef(id, ref) {
    var name = refFieldName(id);

    if (name) {
      $('input[name="' + name + '"]').val(ref || '');
    }
  }

  function hydrateNpRefsFromHidden() {
    var hasHiddenRef = false;

    $.each([
      'input-shipping-novaposhta-area',
      'input-shipping-novaposhta-city',
      'input-shipping-novaposhta-warehouse-address',
      'input-shipping-novaposhta-doors-street'
    ], function(index, id) {
      var ref = hiddenRef(id);
      var element = $('#' + id);

      if (!ref || !element.length) {
        return;
      }

      hasHiddenRef = true;

      if (!normalizeText(element.attr('data-ref'))) {
        element.attr('data-ref', ref);
      }
    });

    return hasHiddenRef;
  }
TWIG;

$content = replace_once($content, $old, $new, 'NP ref helpers');

$old = <<<'TWIG'
    prepareRegisterFlow();
    window.bsCheckoutUpdateNpCascade();
    window.bsCheckoutSyncNpFields();

    if (!bsAutoShippingStarted && $('#input-shipping-address').val() && !$('#input-shipping-code').val() && typeof window.bsCheckoutLoadShippingMethods === 'function') {
TWIG;

$new = <<<'TWIG'
    prepareRegisterFlow();
    var hasHydratedNpRefs = hydrateNpRefsFromHidden();
    window.bsCheckoutUpdateNpCascade();
    window.bsCheckoutSyncNpFields();

    if (hasHydratedNpRefs && window.bsCheckoutNpIsComplete()) {
      window.clearTimeout(bsRegisterTimer);
      bsRegisterTimer = window.setTimeout(triggerRegisterAutosave, 250);
    }

    if (!bsAutoShippingStarted && $('#input-shipping-address').val() && !$('#input-shipping-code').val() && typeof window.bsCheckoutLoadShippingMethods === 'function') {
TWIG;

$content = replace_once($content, $old, $new, 'NP hydration autosave trigger');

$backupFile = $backupDir . '/' . $file;
if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true) && !is_dir(dirname($backupFile))) {
    fail('cannot create backup dir');
}
if (!copy($path, $backupFile)) {
    fail('cannot backup ' . $file);
}
out('backup=' . str_replace($root . '/', '', $backupFile));

if (file_put_contents($path, $content) === false) {
    fail('cannot write ' . $file);
}
out('changed=' . $file);
out('php_modified=none');
out('rollback=restore ' . str_replace($root . '/', '', $backupFile));
out('done=ok');

@unlink(__FILE__);
out('self_delete=' . (file_exists(__FILE__) ? 'failed' : 'ok'));
