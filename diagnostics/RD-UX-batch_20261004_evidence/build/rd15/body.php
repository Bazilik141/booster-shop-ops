<?php
declare(strict_types=1);

/**
 * RD-15 — checkout failure page «Оплата не пройшла», direction «Дві колонки»
 * =============================================================================
 * Chain     : runner 2 of 7. Requires runner 1 (RD-14_success-steps_20261004) applied first.
 * Handoff   : handoffs/handoff_RD-15_failure_claude-code_20261004.md (+ INDEX, Claude review 2026-10-04)
 * Design    : handoffs/design_20261004_rd14-15_ux003-005-009/RD-14 RD-15 - фінал.html, state «RD-15 failure»
 * Author    : Claude Code · 2026-10-04
 * Risk      : checkout-adjacent. Template + CSS + three uk-ua language strings. Routing to the page,
 *             Hutko, cart clearing and fiscalisation are not touched.
 * DB changes: NONE.  Controller: not touched.
 * RUN FROM  : ~/public_html    ->    php RD-15_failure-two-columns_20261004.php
 *
 * WHAT CHANGES
 *   extension/ukrainian/catalog/language/uk-ua/checkout/failure.php (the Ukrainian language-pack extension —
 *     reinstalling that pack would revert these three strings):
 *       heading_title  'Помилка оплати!'  → 'Оплата не пройшла'   (also the <title>, via setTitle)
 *       text_failure   'Помилка оплати'   → 'Оплата не пройшла'   (last breadcrumb; Claude review overrides §5)
 *       text_message   → the owner's two paragraphs, verbatim, with links Telegram / mailto / tel. The
 *                        controller passes it through sprintf(): the new text has no '%' at all, and the
 *                        dropped '%s' only means the contact-URL argument is ignored.
 *     text_basket, text_checkout and every other language are untouched.
 *   catalog/view/template/checkout/failure.twig — DS card: SVG alert icon (⚠️ removed), H1, message, actions
 *     «На головну» (no «←») + «напишіть у Telegram», both secondary; last breadcrumb without a link.
 *     The inline R-11b <style> is removed (superseded).
 *   catalog/view/stylesheet/boostershop-ds.css — new section «RD-15: Checkout Failure» right after RD-14.
 *   catalog/view/template/common/header.twig — boostershop-ds.css ?v= token only.
 *
 * NOTE   The same page is also the redirect target of the COD / bank-transfer / cheque / free-checkout confirm
 *        actions when the session has lost its order (stock OpenCart). Those paths now also read «Оплата не
 *        пройшла». Routing is out of scope (CHECKOUT-012).
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old look: the inline R-11b <style> in failure.twig; replaced at the source, nothing
 *   stacked. No !important. Breadcrumb pill for the non-link last item comes from RD-14's extension of the
 *   global fallback rule. Magic numbers from the approved design: 600 card, 48 icon column, 4px strip.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all four targets (the RD-14 output for ds.css/header.twig) → marker check → anchors →
 *   php -l on the new language file (before writing, and again after) → Twig parse gate on failure.twig and
 *   header.twig → hazard scan → CSS balance gate → backup of all four → write → any failure restores all.
 *   Idempotent: marker «RD-15» in failure.twig, failure.php and boostershop-ds.css. Self-deletes on success.
 *   ROLLBACK: copy the four files back from the backup folder, refresh the theme cache, Ctrl+F5. Only while
 *   no later runner of the chain is applied.
 *   TRIGGER: PHP error or blank page on checkout/failure, wrong/garbled text, a payment flow no longer
 *   reaching success.
 * =============================================================================
 */

const PATCH_ID = 'RD-15_failure-two-columns_20261004';
const MARKER   = 'RD-15';
const TOKEN    = 'rd15-20261004';
const FAILURE  = 'catalog/view/template/checkout/failure.twig';
const LANG     = 'extension/ukrainian/catalog/language/uk-ua/checkout/failure.php';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    FAILURE => '@@SHA:catalog/view/template/checkout/failure.twig@@',
    LANG    => '@@SHA:extension/ukrainian/catalog/language/uk-ua/checkout/failure.php@@',
    DSCSS   => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
    HEADER  => '@@SHA:catalog/view/template/common/header.twig@@',
);

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(FAILURE, LANG, DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(FAILURE, LANG, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- language file ------------------------------------------------------- */
$lang = $files[LANG]['text'];
$lang = replace_one($lang, "<?php\n// Heading\n", "<?php\n// RD-15 (2026-10-04): heading_title, text_failure and text_message rewritten (owner copy).\n// Heading\n", 'lang_marker');
$lang = replace_one($lang, "\$_['heading_title'] = 'Помилка оплати!';", "\$_['heading_title'] = 'Оплата не пройшла';", 'lang_heading');
$lang = replace_one($lang, "\$_['text_failure'] = 'Помилка оплати';", "\$_['text_failure'] = 'Оплата не пройшла';", 'lang_text_failure');
$newMessage = '<p>Платіж не було завершено. Так буває, коли банк відхилив операцію або сторінку оплати закрили трохи раніше, ніж потрібно.</p>'
    . '<p>Якщо кошти списались або ви не впевнені, чи створено замовлення, напишіть нам у <a href="https://telegram.me/boostershop_tcg" target="_blank" rel="noopener">Telegram</a>, '
    . 'на <a href="mailto:helpbs@boostershop.website">електронну пошту</a> або подзвоніть за номером <a href="tel:+380636743252" class="bs-failure-nowrap">+380636743252</a>.</p>';
if (strpos($newMessage, '%') !== false || strpos($newMessage, "'") !== false) fail('lang_new_message_unsafe');
$oldLine = "\$_['text_message'] = '<p>У процесі оплати виникла помилка.";
count_exact($lang, $oldLine, 1, 'lang_text_message');
$a = strpos($lang, $oldLine);
$b = strpos($lang, "';", $a + strlen($oldLine));
if ($b === false) fail('lang_text_message_end');
$lang = substr($lang, 0, $a) . "\$_['text_message'] = '" . $newMessage . substr($lang, $b);
count_exact($lang, "\$_['text_basket'] = 'Кошик покупок';", 1, 'lang_basket_kept');
count_exact($lang, "\$_['text_checkout'] = 'Оформлення замовлення';", 1, 'lang_checkout_kept');
$langBytes = encode_text($files[LANG], $lang);
php_lint_bytes($root, LANG, $langBytes);
// The new strings must load and survive the controller's sprintf() unchanged.
$_ = array();
$langTmp = $root . '/' . LANG . '.' . PATCH_ID . '.load.php';
if (file_put_contents($langTmp, $langBytes, LOCK_EX) !== strlen($langBytes)) fail('lang_load_temp_failed');
(static function () use ($langTmp, &$_): void { include $langTmp; })();
@unlink($langTmp);
if (($_['heading_title'] ?? '') !== 'Оплата не пройшла' || ($_['text_failure'] ?? '') !== 'Оплата не пройшла') fail('lang_values_check');
if (sprintf((string)($_['text_message'] ?? ''), 'https://example.invalid/contact') !== $newMessage) fail('lang_sprintf_check');
out('lang_values=ok sprintf=ok');

/* ---- failure.twig -------------------------------------------------------- */
$failure = <<<'BS_RD15_TWIG'
@@FILE:failure.twig@@
BS_RD15_TWIG;
$failure .= "\n";
count_exact($failure, '{{ text_message }}', 1, 'new_text_message');
count_exact($failure, '{{ heading_title }}', 1, 'new_heading');
count_exact($failure, '{{ continue }}', 1, 'new_continue');
foreach (array('⚠', '←', '<style') as $gone) count_exact($failure, $gone, 0, 'new_removed');
twig_hazard_gate($failure, FAILURE);

/* ---- boostershop-ds.css -------------------------------------------------- */
$section = <<<'BS_RD15_CSS'
@@FILE:section.css@@
BS_RD15_CSS;
count_exact($files[DSCSS]['text'], '#checkout-failure', 0, 'ds_no_failure_rules_yet');
$anchor = "/* === /RD-14: Checkout Success === */\n";
$ds = replace_one($files[DSCSS]['text'], $anchor, $anchor . "\n" . $section . "\n", 'ds_after_rd14');
css_balance_gate($ds, DSCSS);

/* ---- header.twig: cache token -------------------------------------------- */
$header = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(FAILURE => array($files[FAILURE]['text'], $failure), HEADER => array($files[HEADER]['text'], $header)));

commit_files($root, $files, array(
    LANG    => $langBytes,
    FAILURE => encode_text($files[FAILURE], $failure),
    DSCSS   => encode_text($files[DSCSS], $ds),
    HEADER  => encode_text($files[HEADER], $header),
), array(LANG));

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, open index.php?route=checkout/failure at 390 / 768 / 1440');
