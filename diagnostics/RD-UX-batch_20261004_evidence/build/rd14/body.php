<?php
declare(strict_types=1);

/**
 * RD-14 — checkout success page «Замовлення прийнято», direction «Кроки»
 * =============================================================================
 * Chain     : runner 1 of 7 (RD-14 → RD-15 → UX-003/005 header → UX-009 live search → UX-009 search page
 *             → UX-003 grid → UX-003 category filter). Deploy strictly in this order.
 * Handoff   : handoffs/handoff_RD-14_success_claude-code_20261004.md (+ INDEX, Claude review 2026-10-04)
 * Design    : handoffs/design_20261004_rd14-15_ux003-005-009/RD-14 RD-15 - фінал.html (states a–e, First15 A)
 * Author    : Claude Code · 2026-10-04
 * Risk      : checkout-adjacent (template rendered at the end of checkout). Template + CSS only.
 * DB changes: NONE.  Controller: not touched.  Language files: not touched.
 * RUN FROM  : ~/public_html    ->    php RD-14_success-steps_20261004.php
 * Live base : rd-ux-batch-live2-20261004-1036.tar.gz — every target is SHA-256-guarded against it.
 *
 * WHAT CHANGES
 *   catalog/view/template/checkout/success.twig — rewritten markup:
 *     hero (SVG check in a 56px --bs-buy circle) → First15 (variant A) → IBAN card → «Що далі» card →
 *     «Ваше замовлення» (delivery/payment above the table; below 1024px collapsed to delivery, payment and
 *     «Сума» behind «Показати товари (N)» / «Сховати товари»; open without JS) → actions (both secondary) →
 *     footer («Вдалого анпакінгу», Telegram link). Fallback (no order number): text_message unchanged +
 *     «На головну» + «напишіть у Telegram». Last breadcrumb rendered without a link. All emoji → inline SVG,
 *     aria-hidden. The inline <style> (r11 / st2b2 / st2b3) is removed; its rules are superseded.
 *     New copy lives in the template, not the language file: «Що далі», «Фіскальний чек», «Без зайвих
 *     дзвінків», «Показати товари (N)», «Сховати товари».
 *   Unchanged, asserted on the new text: {% if ga4_purchase_payload %} block after both branches (TECH-015);
 *     data-checkout008-copy-requisites / -copy-status hooks and the inline copy script, byte-identical;
 *     conditions show_first15_offer, is_iban_bank_transfer, is_hutko, is_cod, `is_logged and history_url`;
 *     the requisite values.
 *   catalog/view/stylesheet/boostershop-ds.css —
 *     the R-11 «Checkout Success» section (the source rules of this page) is replaced wholesale by the RD-14
 *     section; global breadcrumb fallback: `> span` added beside `> a` in the pill rule and the last-item rule,
 *     so a current page rendered without a link keeps the pill (no template on the site used a span there).
 *   catalog/view/template/common/header.twig — boostershop-ds.css ?v= token only (convention 8).
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old look: R-11 section (ds.css «=== R-11: Checkout Success ===») + inline <style> in
 *   success.twig. Both are replaced at the source — nothing is stacked on top. No !important added; the
 *   inline `#content { padding-bottom: 8px !important }` is dropped and the global R07MOB2 40px is accepted.
 *   Override history checked: TECH-045-WPE (a.bs-btn-primary, success «Переглянути замовлення» — now
 *   secondary, so `.bs a` is answered by a scoped `#checkout-success .bs-btn-secondary` colour rule),
 *   RD-10F breadcrumb fallback (extended, not overridden). No position:absolute/fixed, no setTimeout.
 *   Magic numbers come from the approved design: 620 column, 56 hero circle, 36 step icon, 160 dt column.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all three targets → marker check → anchors → Twig compile gate (control parse first) on
 *   success.twig and header.twig → Twig-hazard scan of the new template → CSS balance gate → backup of all
 *   three to _patch_backups/<patch>-<ts>/ → write → any failure restores all. Idempotent: marker «RD-14» in
 *   success.twig and boostershop-ds.css → already_applied=yes. Self-deletes after success.
 *   ROLLBACK: copy the three files back from the backup folder, refresh the theme cache, Ctrl+F5.
 *   Roll back only this runner, and only while no later runner of the chain is applied.
 *   TRIGGER: GA4 purchase missing or doubled, «Скопіювати реквізити» stops working, a payment branch missing,
 *   any PHP/Twig error on checkout/success.
 * =============================================================================
 */

const PATCH_ID = 'RD-14_success-steps_20261004';
const MARKER   = 'RD-14';
const TOKEN    = 'rd14-20261004';
const SUCCESS  = 'catalog/view/template/checkout/success.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    SUCCESS => '@@SHA:catalog/view/template/checkout/success.twig@@',
    DSCSS   => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
    HEADER  => '@@SHA:catalog/view/template/common/header.twig@@',
);
const R11_START = "/* === R-11: Checkout Success === */\n";
const R11_END   = "/* === /R-11: Checkout Success === */\n";
const R11_SHA   = '@@BLOCKSHA:catalog/view/stylesheet/boostershop-ds.css|/* === R-11: Checkout Success === */\n|/* === /R-11: Checkout Success === */\n@@';

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(SUCCESS, DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(SUCCESS, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- success.twig ------------------------------------------------------- */
$success = <<<'BS_RD14_TWIG'
@@FILE:success.twig@@
BS_RD14_TWIG;
$success .= "\n";

// Must-survive assertions on the new template (handoff «Claude review», RD-14).
count_exact($success, '{% if ga4_purchase_payload %}', 1, 'new_ga4_block');
count_exact($success, "<script>ps_dataLayer.pushEventData('purchase', {{ ga4_purchase_payload|raw }});</script>", 1, 'new_ga4_push');
count_exact($success, 'data-checkout008-copy-requisites', 2, 'new_copy_hook');
count_exact($success, 'data-checkout008-copy-status', 2, 'new_status_hook');
count_exact($success, 'order_data.show_first15_offer|default(false)', 1, 'new_first15');
count_exact($success, 'order_data.is_iban_bank_transfer|default(false)', 1, 'new_iban');
count_exact($success, 'order_data.is_hutko|default(false)', 1, 'new_hutko');
count_exact($success, 'order_data.is_cod|default(false)', 1, 'new_cod');
count_exact($success, '{% if is_logged and history_url %}', 1, 'new_history');
count_exact($success, 'UA063348510000000026003285008', 2, 'new_iban_value');
// The CHECKOUT-008 copy script must be byte-identical to the live one.
$old = $files[SUCCESS]['text'];
$scriptStart = "          <script>\n          (function () {\n            var button = document.querySelector('[data-checkout008-copy-requisites]');";
$scriptEnd = "          }());\n          </script>\n";
count_exact($old, $scriptStart, 1, 'old_copy_script');
count_exact($success, $scriptStart, 1, 'new_copy_script');
$oldScript = substr($old, strpos($old, $scriptStart), strpos($old, $scriptEnd, strpos($old, $scriptStart)) + strlen($scriptEnd) - strpos($old, $scriptStart));
$newScript = substr($success, strpos($success, $scriptStart), strpos($success, $scriptEnd, strpos($success, $scriptStart)) + strlen($scriptEnd) - strpos($success, $scriptStart));
if ($oldScript !== $newScript) fail('checkout008_copy_script_changed');
out('checkout008_copy_script=identical');
foreach (array('✓', '🎴', '🙂', '🎁', '⚠') as $emoji) count_exact($success, $emoji, 0, 'new_emoji');
count_exact($success, '<style', 0, 'new_inline_style');
twig_hazard_gate($success, SUCCESS);

/* ---- boostershop-ds.css ------------------------------------------------- */
$ds = $files[DSCSS]['text'];
$section = <<<'BS_RD14_CSS'
@@FILE:section.css@@
BS_RD14_CSS;
$ds = replace_block($ds, R11_START, R11_END, R11_SHA, $section . "\n", 'ds_r11_section');
$ds = replace_one(
    $ds,
    ".breadcrumb .breadcrumb-item > a {\n  display: inline-flex; align-items: center; justify-content: center;\n",
    "/* RD-14: `> span` is a current page rendered without a link (checkout success/failure). */\n"
    . ".breadcrumb .breadcrumb-item > a,\n.breadcrumb .breadcrumb-item > span {\n  display: inline-flex; align-items: center; justify-content: center;\n",
    'ds_crumb_pill'
);
$ds = replace_one(
    $ds,
    ".breadcrumb .breadcrumb-item:last-child > a,\n.breadcrumb .breadcrumb-item.active {\n",
    ".breadcrumb .breadcrumb-item:last-child > a,\n.breadcrumb .breadcrumb-item:last-child > span,\n.breadcrumb .breadcrumb-item.active {\n",
    'ds_crumb_current'
);
count_exact($ds, '#checkout-success', substr_count($section, '#checkout-success'), 'ds_success_rules_only_in_section');
css_balance_gate($ds, DSCSS);

/* ---- header.twig: cache token ------------------------------------------- */
$header = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(SUCCESS => array($files[SUCCESS]['text'], $success), HEADER => array($files[HEADER]['text'], $header)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    SUCCESS => encode_text($files[SUCCESS], $success),
    DSCSS   => encode_text($files[DSCSS], $ds),
    HEADER  => encode_text($files[HEADER], $header),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the RD-14 checks in diagnostics/RD-14_success-steps_report_20261004.md and bs-checkout-smoke');
