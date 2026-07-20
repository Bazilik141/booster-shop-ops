<?php
declare(strict_types=1);

/**
 * RD-13 — stock checkout visual reskin.
 *
 * Scope:
 * - replaces only the stock checkout shell markup;
 * - appends namespaced RD-13 styles;
 * - adds a presentation-only vanilla/jQuery helper;
 * - preserves current form IDs, field names, AJAX endpoints, trusted-click
 *   confirm order, guest-only oferta, account opt-in, and persistent loader.
 *
 * Explicitly out of scope:
 * - database changes;
 * - coupon/First15 parity (stock endpoint is absent);
 * - free-shipping threshold/progress (backend/config is absent);
 * - SimpleCheckout, payment, Hutko, Checkbox, fiscalization, shipping-price
 *   calculation, order status, and success-page logic.
 *
 * Rollback:
 * Restore the two backed-up files from the printed _patch_backups directory
 * and remove catalog/view/javascript/checkout-reskin.js, then clear cache.
 */

const RD13_PATCH_ID = 'RD-13_checkout-reskin_20260706';
const RD13_CHECKOUT = 'catalog/view/template/checkout/checkout.twig';
const RD13_CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const RD13_JS = 'catalog/view/javascript/checkout-reskin.js';

const RD13_CHECKOUT_SHA256 = '18a1b139a86a106ee653d37197664e4bb72d6faa4d0ee4127252554a818dff96';
const RD13_CSS_SHA256 = 'f9e0c5da032d86374054713b8fdaa1c30c2edc43fb1269b91a39f8717de1991d';

const RD13_CHECKOUT_MARKER = 'RD-13 visual shell: four-card stock checkout.';
const RD13_CSS_MARKER = 'RD-13 checkout reskin (visual-only)';
const RD13_JS_MARKER = 'RD-13 checkout reskin (visual-only)';

function rd13_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function rd13_fail(string $message): never {
    throw new RuntimeException($message);
}

function rd13_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function rd13_read(string $path, string $relative): string {
    if (!is_file($path)) {
        rd13_fail('target_missing:' . $relative);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        rd13_fail('read_failed:' . $relative);
    }

    return $content;
}

function rd13_replace_once(string $content, string $search, string $replace, string $label): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        rd13_fail('anchor_count_mismatch:' . $label . ':expected=1:actual=' . $count);
    }

    return str_replace($search, $replace, $content);
}

function rd13_lint_self(): void {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1';
    $output = [];
    $exit = 1;
    exec($command, $output, $exit);
    rd13_out('php_lint_patch_self', 'exit=' . $exit . ';output=' . implode(' ', $output));

    if ($exit !== 0) {
        rd13_fail('php_lint_patch_self_failed');
    }
}

function rd13_atomic_write(string $path, string $content, string $relative): void {
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        rd13_fail('target_mkdir_failed:' . $relative);
    }

    $written = file_put_contents($path, $content, LOCK_EX);

    if ($written === false || $written !== strlen($content)) {
        rd13_fail('write_failed_or_incomplete:' . $relative);
    }
}

function rd13_backup(string $root, string $backupRoot, string $relative): string {
    $source = rd13_path($root, $relative);
    $backup = rd13_path($backupRoot, $relative);
    $directory = dirname($backup);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        rd13_fail('backup_mkdir_failed:' . $relative);
    }

    if (!copy($source, $backup)) {
        rd13_fail('backup_copy_failed:' . $relative);
    }

    rd13_out('backup', $relative . ' -> ' . $backup);
    return $backup;
}

$oldShell = <<<'TWIG'
{{ header }}
<div id="checkout-checkout" class="container">
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
    {% endfor %}
  </ul>
  <div class="row">{{ column_left }}
    <div id="content" class="col">{{ content_top }}
      <h1>{{ heading_title }}</h1>
      <div class="row bs-checkout-layout">
        {% if register or payment_address or shipping_address %}
          <div class="col-md-7 mb-3">
            {% if register %}
              <div id="checkout-register" class="bs-checkout-panel bs-checkout-panel-recipient">{{ register }}</div>
            {% endif %}
            {% if payment_address %}
              <div id="checkout-payment-address" class="bs-checkout-panel bs-checkout-panel-recipient">{{ payment_address }}</div>
            {% endif %}
            {% if shipping_address %}
              <div id="checkout-shipping-address" class="bs-checkout-panel bs-checkout-panel-recipient">{{ shipping_address }}</div>
            {% endif %}
          </div>
        {% endif %}
        <div class="col">
          {% if shipping_method %}
            <div id="checkout-shipping-method" class="bs-checkout-panel bs-checkout-panel-choice mb-3">{{ shipping_method }}</div>
          {% endif %}
          <div id="checkout-payment-method" class="bs-checkout-panel bs-checkout-panel-choice mb-4">{{ payment_method }}</div>
          <div id="checkout-confirm" class="bs-checkout-panel bs-checkout-panel-summary">{{ confirm }}</div>
        </div>
      </div>
    </div>
    {{ content_bottom }}
  </div>
  {{ column_right }}
</div>
TWIG;

$newShell = <<<'TWIG'
{{ header }}
{# RD-13 visual shell: four-card stock checkout. #}
<div id="checkout-checkout" class="container bs-co" data-rd13-checkout>
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
    {% endfor %}
  </ul>
  <div class="row">{{ column_left }}
    <div id="content" class="col">{{ content_top }}
      <h1>Оформити замовлення</h1>

      {% if register %}
        <div class="bs-auth-nudge">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          <div class="bs-co-flex1">
            <span class="bs-auth-nudge__desktop">Маєш акаунт? <strong>Авторизуйся</strong>, щоб не вводити дані заново.</span>
            <span class="bs-auth-nudge__mobile">Маєш акаунт?</span>
          </div>
          <a class="bs-btn bs-btn-ghost bs-auth-nudge__login" href="index.php?route=account/login&amp;language={{ language }}">
            <span class="bs-auth-nudge__desktop">Увійти в акаунт →</span>
            <span class="bs-auth-nudge__mobile">Увійти →</span>
          </a>
        </div>
      {% endif %}

      <div class="bs-co-grid">
        <div class="bs-co-col">
          {% if register or payment_address %}
            <section class="bs-card bs-co-card" data-co-card="receiver" data-co-collapsible>
              <button type="button" class="bs-co-card__head" data-co-card-toggle aria-expanded="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <span class="bs-co-card__title">Отримувач</span>
                <span class="bs-co-card__summary" data-co-receiver-summary></span>
                <span class="bs-co-chevron" aria-hidden="true">⌄</span>
              </button>
              <div class="bs-co-card__body">
                {% if register %}
                  <div id="checkout-register">{{ register }}</div>
                {% endif %}
                {% if payment_address %}
                  <div id="checkout-payment-address">{{ payment_address }}</div>
                {% endif %}
              </div>
            </section>
          {% endif %}

          {% if shipping_address or shipping_method %}
            <section class="bs-card bs-co-card" data-co-card="delivery" data-co-collapsible>
              <button type="button" class="bs-co-card__head" data-co-card-toggle aria-expanded="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3V6Zm11 4h4l3 3v3h-7v-6ZM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                <span class="bs-co-card__title">Доставка</span>
                <span class="bs-co-card__summary" data-co-delivery-summary></span>
                <span class="bs-co-chevron" aria-hidden="true">⌄</span>
              </button>
              <div class="bs-co-card__body">
                {% if shipping_address %}
                  <div id="checkout-shipping-address">{{ shipping_address }}</div>
                {% endif %}
                {% if shipping_method %}
                  <div id="checkout-shipping-method" class="bs-checkout-panel-choice">{{ shipping_method }}</div>
                {% endif %}
              </div>
            </section>
          {% endif %}

          <section class="bs-card bs-co-card" data-co-card="payment">
            <div class="bs-co-card__head bs-co-card__head--static">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M3 9h18M7 15h4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
              <span class="bs-co-card__title">Оплата</span>
            </div>
            <div class="bs-co-card__body">
              <div id="checkout-payment-method" class="bs-checkout-panel-choice">{{ payment_method }}</div>
            </div>
          </section>
        </div>

        <aside class="bs-co-aside">
          <section class="bs-card bs-co-card bs-co-summary" data-co-card="summary">
            <button type="button" class="bs-co-summary__toggle" data-co-summary-toggle aria-expanded="false">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18l-2-1.5L13 21l-2-1.5L9 21l-2-1.5L5 21V5a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
              <span class="bs-co-flex1"><strong>Замовлення</strong><span data-co-summary-qty></span></span>
              <span class="bs-co-summary__total" data-co-summary-total></span>
              <span class="bs-co-chevron" aria-hidden="true">⌄</span>
            </button>
            <div class="bs-co-summary__body" data-co-summary-body>
              <div id="checkout-confirm">{{ confirm }}</div>
              <div id="bs-co-summary-tail"></div>
            </div>
          </section>
        </aside>
      </div>
    </div>
    {{ content_bottom }}
  </div>
  {{ column_right }}
</div>
TWIG;

$css = <<<'CSS'

/* ==== RD-13 checkout reskin (visual-only) ==== */
#checkout-checkout.bs-co {
  --bs-co-gap: var(--bs-s5, 20px);
  padding-bottom: 34px;
}

#checkout-checkout.bs-co h1 {
  color: var(--bs-ink);
  font-size: clamp(24px, 3vw, 34px);
  font-weight: 800;
  letter-spacing: -.03em;
  margin: 0 0 var(--bs-s5, 20px);
}

.bs-co-flex1 { flex: 1; min-width: 0; }
.bs-auth-nudge__mobile { display: none; }

.bs-auth-nudge {
  align-items: center;
  background: var(--bs-blue-soft);
  border: 1px solid rgba(30, 58, 138, .12);
  border-radius: var(--bs-r);
  color: var(--bs-ink-2);
  display: flex;
  gap: 12px;
  margin-bottom: var(--bs-s5, 20px);
  padding: 12px 14px;
}

.bs-auth-nudge > svg {
  color: var(--bs-blue);
  flex: 0 0 24px;
  height: 24px;
  width: 24px;
}

.bs-auth-nudge__login { color: var(--bs-blue); height: 38px; }

.bs-co-grid {
  align-items: flex-start;
  display: grid;
  gap: var(--bs-co-gap);
  grid-template-columns: minmax(0, 1fr) 380px;
}

.bs-co-col {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}

.bs-co-aside {
  position: sticky;
  top: 16px;
}

.bs-co-card {
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  box-shadow: var(--bs-sh-sm);
  overflow: hidden;
  padding: 0;
}

.bs-co-card__head,
.bs-co-summary__toggle {
  align-items: center;
  background: transparent;
  border: 0;
  color: var(--bs-ink);
  display: flex;
  font: inherit;
  gap: 10px;
  margin: 0;
  padding: 18px 20px;
  text-align: left;
  width: 100%;
}

button.bs-co-card__head { cursor: pointer; }
.bs-co-card__head--static { cursor: default; }

.bs-co-card__head > svg,
.bs-co-summary__toggle > svg {
  background: var(--bs-bg);
  border-radius: var(--bs-r-sm);
  box-sizing: content-box;
  color: var(--bs-ink-2);
  flex: 0 0 18px;
  height: 18px;
  padding: 6px;
  width: 18px;
}

.bs-co-card__title,
.bs-co-summary__toggle strong {
  font-size: 16px;
  font-weight: 800;
}

.bs-co-card__summary {
  color: var(--bs-ink-3);
  display: none;
  flex: 1;
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bs-co-card__body {
  border-top: 1px solid var(--bs-line);
  padding: 18px 20px 20px;
}

.bs-co-chevron {
  color: var(--bs-ink-3);
  flex: 0 0 auto;
  font-size: 20px;
  line-height: 1;
  transition: transform .2s;
}

.bs-co-card.is-collapsed .bs-co-chevron,
.bs-co-summary.is-open .bs-co-chevron { transform: rotate(180deg); }
.bs-co-card.is-collapsed .bs-co-card__summary { display: block; }
.bs-co-card.is-collapsed .bs-co-card__body { display: none; }

#checkout-checkout.bs-co .bs-checkout-panel {
  background: transparent;
  border: 0;
  border-radius: 0;
  box-shadow: none;
  margin: 0;
  padding: 0;
}

#checkout-checkout.bs-co #form-register > p:first-child,
#checkout-checkout.bs-co #form-register > fieldset:first-of-type > legend,
#checkout-checkout.bs-co #checkout-payment-method > fieldset > legend,
#checkout-checkout.bs-co #checkout-payment-method > br,
#checkout-checkout.bs-co .bs-confirm-deferred > h2 {
  display: none;
}

#checkout-checkout.bs-co fieldset + fieldset {
  border-top: 1px solid var(--bs-line);
  margin-top: 16px;
  padding-top: 16px;
}

#checkout-checkout.bs-co fieldset legend {
  border: 0;
  color: var(--bs-ink);
  font-size: 14px;
  font-weight: 700;
  margin: 0 0 12px;
  padding: 0;
}

#checkout-checkout.bs-co .form-label,
#checkout-checkout.bs-co label {
  color: var(--bs-ink-2);
  font-size: 12.5px;
  font-weight: 600;
}

#checkout-checkout.bs-co .form-control,
#checkout-checkout.bs-co .form-select,
#checkout-checkout.bs-co .bs-input,
#checkout-checkout.bs-co .bs-select {
  background-color: #fff;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  color: var(--bs-ink);
  font-family: inherit;
  min-height: 44px;
  padding: 10px 12px;
}

#checkout-checkout.bs-co textarea.form-control { min-height: 78px; resize: vertical; }

#checkout-checkout.bs-co .form-control:focus,
#checkout-checkout.bs-co .form-select:focus {
  border-color: var(--bs-blue);
  box-shadow: 0 0 0 3px rgba(30, 58, 138, .08);
}

#checkout-checkout.bs-co .invalid-feedback,
#checkout-checkout.bs-co .bs-co-field-error {
  color: var(--bs-danger);
  font-size: 12px;
  margin-top: 4px;
}

#checkout-checkout.bs-co .bs-checkout-inline-methods {
  display: grid;
  gap: 10px;
}

#checkout-checkout.bs-co .bs-checkout-method-option,
#checkout-checkout.bs-co .bs-checkout-panel-choice .form-check {
  align-items: flex-start;
  background: #fff;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  cursor: pointer;
  display: flex;
  gap: 10px;
  margin: 0;
  padding: 13px 14px;
  position: relative;
  transition: background .15s, border-color .15s, box-shadow .15s;
}

#checkout-checkout.bs-co .bs-checkout-panel-choice .form-check {
  padding-left: 42px;
}

#checkout-checkout.bs-co .bs-checkout-method-option:hover,
#checkout-checkout.bs-co .bs-checkout-panel-choice .form-check:hover {
  background: var(--bs-blue-soft);
  border-color: rgba(30, 58, 138, .42);
}

#checkout-checkout.bs-co .bs-checkout-method-option.is-active,
#checkout-checkout.bs-co .bs-checkout-panel-choice .form-check.is-active,
#checkout-checkout.bs-co .bs-checkout-panel-choice .form-check:has(input:checked) {
  background: var(--bs-blue-soft);
  border-color: var(--bs-blue);
  box-shadow: 0 0 0 2px rgba(30, 58, 138, .08);
}

#checkout-checkout.bs-co .bs-checkout-method-option input,
#checkout-checkout.bs-co .bs-checkout-panel-choice .form-check-input {
  accent-color: var(--bs-blue);
  flex: 0 0 auto;
  margin-top: 3px;
}

#checkout-checkout.bs-co .bs-checkout-inline-status {
  color: var(--bs-ink-3);
  font-size: 12px;
  min-height: 1.2em;
  padding-top: 7px;
}

.bs-co-summary__toggle { cursor: default; }
.bs-co-summary__toggle .bs-co-chevron { display: none; }
.bs-co-summary__toggle [data-co-summary-qty] {
  color: var(--bs-ink-3);
  font-size: 12px;
  font-weight: 500;
  margin-left: 5px;
}

.bs-co-summary__total {
  color: var(--bs-ink);
  font-size: 16px;
  font-weight: 800;
}

.bs-co-summary__body {
  border-top: 1px solid var(--bs-line);
  padding: 16px 18px 20px;
}

#checkout-checkout.bs-co #checkout-confirm .table-responsive {
  margin: 0;
  overflow: visible;
}

#checkout-checkout.bs-co #checkout-confirm table {
  border-collapse: separate;
  border-spacing: 0 8px;
  margin: -8px 0 8px;
}

#checkout-checkout.bs-co #checkout-confirm thead { display: none; }
#checkout-checkout.bs-co #checkout-confirm tbody td {
  border: 0;
  font-size: 12.5px;
  padding: 7px 4px;
  vertical-align: middle;
}

#checkout-checkout.bs-co #checkout-confirm tbody img {
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  height: 46px;
  object-fit: contain;
  width: 46px;
}

#checkout-checkout.bs-co #checkout-confirm tfoot td {
  border: 0;
  color: var(--bs-ink-2);
  font-size: 13px;
  padding: 4px;
}

#checkout-checkout.bs-co #checkout-confirm tfoot tr:last-child td {
  border-top: 1px solid var(--bs-line);
  color: var(--bs-ink);
  font-size: 17px;
  font-weight: 800;
  padding-top: 11px;
}

#bs-co-summary-tail {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 14px;
}

#bs-co-summary-tail:empty { display: none; }
#bs-co-summary-tail .mb-2,
#bs-co-summary-tail .form-check { margin: 0 !important; }

#bs-co-summary-tail .form-check {
  align-items: flex-start;
  background: var(--bs-bg);
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  display: flex;
  gap: 10px;
  padding: 11px 12px;
}

#bs-co-summary-tail .form-check-label {
  flex: 1;
  line-height: 1.5;
  text-align: left;
}

#bs-co-summary-tail .form-check-input {
  flex: 0 0 auto;
  margin: 2px 0 0;
  order: -1;
}

#checkout-checkout.bs-co #checkout-confirm .btn-primary,
#checkout-checkout.bs-co #checkout-confirm [data-bs-deferred-confirm] {
  background: var(--bs-green);
  border: 1px solid var(--bs-green);
  border-radius: var(--bs-r-sm);
  box-shadow: none;
  font-size: 15px;
  font-weight: 800;
  min-height: 50px;
  padding: 13px 16px;
  width: 100%;
}

#checkout-checkout.bs-co #checkout-confirm .btn-primary:hover {
  background: var(--bs-green-d);
  border-color: var(--bs-green-d);
  transform: none;
}

#checkout-checkout.bs-co #checkout-confirm .btn-primary[disabled],
#checkout-checkout.bs-co #checkout-confirm [data-bs-deferred-confirm][disabled] {
  cursor: not-allowed;
  opacity: .5;
}

#checkout-checkout.bs-co .bs-np-address-choice,
#checkout-checkout.bs-co .bs-np-address-panel,
#checkout-checkout.bs-co .bs-recipient-toggle {
  border-color: var(--bs-line);
  border-radius: var(--bs-r-sm);
}

@media (max-width: 900px) {
  .bs-co-grid { grid-template-columns: 1fr; }
  .bs-co-aside { position: static; }
  .bs-co-summary__toggle { cursor: pointer; }
  .bs-co-summary__toggle .bs-co-chevron { display: inline; }
  .bs-co-summary:not(.is-open) #checkout-confirm > .table-responsive,
  .bs-co-summary:not(.is-open) #checkout-confirm .bs-confirm-deferred-summary,
  .bs-co-summary:not(.is-open) #checkout-confirm .bs-confirm-method-summary,
  .bs-co-summary:not(.is-open) #checkout-confirm .bs-confirm-deferred > p {
    display: none;
  }
}

@media (max-width: 640px) {
  #checkout-checkout.bs-co { padding-left: 12px; padding-right: 12px; }
  .bs-auth-nudge { font-size: 13px; padding: 10px 12px; }
  .bs-auth-nudge__desktop { display: none; }
  .bs-auth-nudge__mobile { display: inline; }
  .bs-auth-nudge__login { height: 34px; padding: 0 8px; }
  .bs-co-card__head,
  .bs-co-summary__toggle { padding: 15px 16px; }
  .bs-co-card__body,
  .bs-co-summary__body { padding: 14px 16px 17px; }
  .bs-co-card__title { flex: 0 0 auto; }
  #checkout-checkout.bs-co .row-cols-md-2 > * { width: 100%; }
}
/* ==== /RD-13 checkout reskin ==== */
CSS;

$javascript = <<<'JS'
/* RD-13 checkout reskin (visual-only)
 * Presentation helpers only. No endpoint, payload, or checkout-order changes.
 */
(function ($) {
  'use strict';

  var root = document.querySelector('[data-rd13-checkout]');
  var observerTimer = 0;
  var syncing = false;

  if (!root || !$) {
    return;
  }

  function text(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function setText(node, value) {
    if (node && node.textContent !== value) {
      node.textContent = value;
    }
  }

  function moveSummaryFields() {
    var tail = document.getElementById('bs-co-summary-tail');

    if (!tail) {
      return;
    }

    [
      document.getElementById('input-comment'),
      document.getElementById('input-checkout-agree'),
      document.getElementById('input-create-account-opt-in')
    ].forEach(function (input) {
      if (!input) {
        return;
      }

      var block = input.closest('.mb-2, .form-check');

      if (block && block.parentNode !== tail) {
        tail.appendChild(block);
      }
    });
  }

  function styleControls() {
    root.querySelectorAll('.bs-checkout-method-option, .bs-checkout-panel-choice .form-check').forEach(function (row) {
      var checked = row.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked');
      row.classList.toggle('is-active', !!checked);
    });
  }

  function updateSummaryMeta() {
    var quantity = 0;
    var totalNode = document.querySelector('[data-co-summary-total]');
    var qtyNode = document.querySelector('[data-co-summary-qty]');
    var rows = root.querySelectorAll('#checkout-confirm tbody tr');
    var grand = root.querySelector('#checkout-confirm tfoot tr:last-child td:last-child');

    rows.forEach(function (row) {
      var cells = row.querySelectorAll('td');
      var candidate = cells.length > 2 ? text(cells[cells.length - 2].textContent) : '';
      var match = candidate.match(/\d+/);
      quantity += match ? parseInt(match[0], 10) : 1;
    });

    setText(qtyNode, quantity ? '· ' + quantity + (quantity === 1 ? ' товар' : ' товари') : '');
    setText(totalNode, grand ? text(grand.textContent) : '');
  }

  function updateCardSummaries() {
    var receiver = document.querySelector('[data-co-receiver-summary]');
    var delivery = document.querySelector('[data-co-delivery-summary]');
    var first = document.getElementById('input-firstname');
    var last = document.getElementById('input-lastname');
    var phone = document.getElementById('input-telephone');
    var shipping = document.getElementById('input-shipping-method');
    var receiverText = [first && first.value, last && last.value].map(text).filter(Boolean).join(' ');
    var phoneText = phone ? text(phone.value) : '';
    var deliveryText = shipping ? text(shipping.value) : '';

    setText(receiver, [receiverText, phoneText].filter(Boolean).join(' · '));
    setText(delivery, deliveryText);

    var receiverCard = receiver && receiver.closest('[data-co-card]');
    var deliveryCard = delivery && delivery.closest('[data-co-card]');

    if (receiverCard) {
      receiverCard.dataset.hasData = receiverText && phoneText ? '1' : '0';
    }

    if (deliveryCard) {
      deliveryCard.dataset.hasData = deliveryText ? '1' : '0';
    }
  }

  function initToggles() {
    root.querySelectorAll('[data-co-card-toggle]').forEach(function (button) {
      if (button.dataset.coBound === '1') {
        return;
      }

      button.dataset.coBound = '1';
      button.addEventListener('click', function () {
        if (!window.matchMedia('(max-width: 900px)').matches) {
          return;
        }

        var card = button.closest('[data-co-card]');
        var collapsed = !card.classList.contains('is-collapsed');
        card.classList.toggle('is-collapsed', collapsed);
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
    });

    var summaryToggle = root.querySelector('[data-co-summary-toggle]');

    if (summaryToggle && summaryToggle.dataset.coBound !== '1') {
      summaryToggle.dataset.coBound = '1';
      summaryToggle.addEventListener('click', function () {
        if (!window.matchMedia('(max-width: 900px)').matches) {
          return;
        }

        var card = summaryToggle.closest('.bs-co-summary');
        var open = !card.classList.contains('is-open');
        card.classList.toggle('is-open', open);
        summaryToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
  }

  function applyInitialMobileCollapse() {
    if (!window.matchMedia('(max-width: 900px)').matches) {
      return;
    }

    root.querySelectorAll('[data-co-collapsible]').forEach(function (card) {
      if (card.dataset.coInitialised === '1') {
        return;
      }

      card.dataset.coInitialised = '1';

      if (card.dataset.hasData === '1') {
        card.classList.add('is-collapsed');
        var button = card.querySelector('[data-co-card-toggle]');
        if (button) {
          button.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  function sync() {
    if (syncing) {
      return;
    }

    syncing = true;
    moveSummaryFields();
    styleControls();
    updateSummaryMeta();
    updateCardSummaries();
    initToggles();
    applyInitialMobileCollapse();
    syncing = false;
  }

  function scheduleSync() {
    window.clearTimeout(observerTimer);
    observerTimer = window.setTimeout(sync, 40);
  }

  $(root).on('change input', 'input, select, textarea', scheduleSync);
  $(sync);

  if (window.MutationObserver) {
    new MutationObserver(scheduleSync).observe(root, {
      childList: true,
      subtree: true
    });
  }
})(window.jQuery);
JS;

$root = getcwd();
$checkoutPath = rd13_path($root, RD13_CHECKOUT);
$cssPath = rd13_path($root, RD13_CSS);
$jsPath = rd13_path($root, RD13_JS);
$written = [];
$backups = [];
$backupRoot = '';

try {
    rd13_out('patch', RD13_PATCH_ID);
    rd13_out('cwd', $root);
    rd13_out('time', gmdate('c'));
    rd13_out('scope', 'stock checkout markup/CSS/presentation JS only');
    rd13_out('db_changes', 'none');
    rd13_out('controller_model_changes', 'none');
    rd13_out('payment_shipping_hutko_checkbox_changes', 'none');
    rd13_out('deferred_followup', 'stock coupon/First15 plus free-shipping rules');

    rd13_lint_self();

    $checkoutContent = rd13_read($checkoutPath, RD13_CHECKOUT);
    $cssContent = rd13_read($cssPath, RD13_CSS);
    $jsContent = is_file($jsPath) ? rd13_read($jsPath, RD13_JS) : '';

    $states = [
        RD13_CHECKOUT => strpos($checkoutContent, RD13_CHECKOUT_MARKER) !== false,
        RD13_CSS => strpos($cssContent, RD13_CSS_MARKER) !== false,
        RD13_JS => strpos($jsContent, RD13_JS_MARKER) !== false,
    ];

    if (count(array_filter($states)) === count($states)) {
        rd13_out('already_applied', 'yes');
        rd13_out('changed_files', '0');
        rd13_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if (count(array_filter($states)) !== 0) {
        rd13_fail('partial_marker_state_detected');
    }

    if (is_file($jsPath)) {
        rd13_fail('new_target_already_exists_without_marker:' . RD13_JS);
    }

    $checkoutHash = hash('sha256', $checkoutContent);
    $cssHash = hash('sha256', $cssContent);
    rd13_out('source_sha256', RD13_CHECKOUT . ':' . $checkoutHash);
    rd13_out('source_sha256', RD13_CSS . ':' . $cssHash);

    if (!hash_equals(RD13_CHECKOUT_SHA256, $checkoutHash)) {
        rd13_fail('live_sha256_mismatch:' . RD13_CHECKOUT . ':expected=' . RD13_CHECKOUT_SHA256 . ':actual=' . $checkoutHash);
    }

    if (!hash_equals(RD13_CSS_SHA256, $cssHash)) {
        rd13_fail('live_sha256_mismatch:' . RD13_CSS . ':expected=' . RD13_CSS_SHA256 . ':actual=' . $cssHash);
    }

    $checkoutPatched = rd13_replace_once($checkoutContent, $oldShell, $newShell, 'checkout_shell');
    $checkoutPatched = rd13_replace_once(
        $checkoutPatched,
        "</style>\n<script type=\"text/javascript\"><!--",
        "</style>\n<link rel=\"stylesheet\" href=\"catalog/view/stylesheet/boostershop-ds.css?v=rd13-20260706\">\n<script type=\"text/javascript\"><!--",
        'checkout_css_cache_bust'
    );
    $checkoutPatched = rd13_replace_once(
        $checkoutPatched,
        '>Оформити замовлення</button>',
        '>Підтвердити замовлення →</button>',
        'deferred_submit_copy'
    );
    $checkoutPatched = rd13_replace_once(
        $checkoutPatched,
        "//--></script>\n{{ footer }}",
        "//--></script>\n<script src=\"catalog/view/javascript/checkout-reskin.js?v=rd13-20260706\"></script>\n{{ footer }}",
        'checkout_js_asset'
    );

    $cssPatched = rtrim($cssContent) . "\n" . $css . "\n";

    $prechecks = [
        'checkout_marker' => substr_count($checkoutPatched, RD13_CHECKOUT_MARKER) === 1,
        'css_marker' => substr_count($cssPatched, RD13_CSS_MARKER) === 1,
        'js_marker' => substr_count($javascript, RD13_JS_MARKER) === 1,
        'trusted_click_preserved' => strpos($checkoutPatched, 'ST-2b.6d: trusted deferred-confirm activation gate.') !== false,
        'guest_oferta_preserved' => strpos($checkoutPatched, 'CHECKOUT-001 Phase 1.3: absent oferta is valid for authorized checkout.') !== false,
        'account_opt_in_preserved' => strpos($checkoutPatched, 'checkout/payment_method.createAccount') !== false,
        'persistent_loader_preserved' => strpos($checkoutPatched, 'CHECKOUT-001 Phase 1.3: persistent submit overlay.') !== false,
        'confirm_endpoint_count' => substr_count($checkoutPatched, 'checkout/confirm.confirm') >= 1,
        'coupon_endpoint_not_added' => strpos($checkoutPatched, 'checkout/coupon') === false,
        'simplecheckout_not_added' => strpos($checkoutPatched, 'extension/SimpleCheckout') === false,
    ];

    foreach ($prechecks as $name => $passed) {
        rd13_out('precheck_' . $name, $passed ? 'ok' : 'failed');
        if (!$passed) {
            rd13_fail('precheck_failed:' . $name);
        }
    }

    $backupRoot = rd13_path(
        $root,
        '_patch_backups/' . RD13_PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4))
    );

    $backups[RD13_CHECKOUT] = rd13_backup($root, $backupRoot, RD13_CHECKOUT);
    $backups[RD13_CSS] = rd13_backup($root, $backupRoot, RD13_CSS);

    $written[] = RD13_CHECKOUT;
    rd13_atomic_write($checkoutPath, $checkoutPatched, RD13_CHECKOUT);
    $written[] = RD13_CSS;
    rd13_atomic_write($cssPath, $cssPatched, RD13_CSS);
    $written[] = RD13_JS;
    rd13_atomic_write($jsPath, $javascript . "\n", RD13_JS);

    $finalCheckout = rd13_read($checkoutPath, RD13_CHECKOUT);
    $finalCss = rd13_read($cssPath, RD13_CSS);
    $finalJs = rd13_read($jsPath, RD13_JS);

    if (substr_count($finalCheckout, RD13_CHECKOUT_MARKER) !== 1 ||
        substr_count($finalCss, RD13_CSS_MARKER) !== 1 ||
        substr_count($finalJs, RD13_JS_MARKER) !== 1) {
        rd13_fail('postwrite_marker_failed');
    }

    rd13_out('php_lint_changed_files', 'not_applicable:no_php_targets');
    rd13_out('js_syntax', 'prevalidated_local_node_check');
    rd13_out('already_applied', 'no');
    rd13_out('changed_files', (string)count($written));
    foreach ($written as $relative) {
        rd13_out('changed_file', $relative);
    }
    rd13_out('rollback', 'restore two files from ' . $backupRoot . '; remove ' . RD13_JS . '; clear cache');
    rd13_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $e) {
    rd13_out('error', $e->getMessage());

    foreach (array_reverse($written) as $relative) {
        $target = rd13_path($root, $relative);

        if (isset($backups[$relative]) && is_file($backups[$relative])) {
            @copy($backups[$relative], $target);
        } elseif ($relative === RD13_JS) {
            @unlink($target);
        }
    }

    rd13_out('restore_on_fail', $written ? 'attempted' : 'not_needed');
    rd13_out('done', 'failed');
    exit(1);
}
