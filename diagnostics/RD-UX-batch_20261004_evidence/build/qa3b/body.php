<?php
declare(strict_types=1);

/**
 * RD-14 / RD-15 / UX-003-005 — owner-QA follow-ups after the 2026-10-04 deploy
 * =============================================================================
 * Chain     : runner 3b. Requires runners 1–3 (RD-14, RD-15, UX-003-005 header) applied — they are live.
 *             Runners 4–7 are built on this runner's output.
 * Handoff   : handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md,
 *             section «Deploy log and runner 3b (owner QA, 2026-10-04)» + review note N2
 *             (diagnostics/RD-UX-batch_runners-1-3_review_20261004.md)
 * Author    : Claude Code · 2026-10-04
 * Risk      : checkout success/failure pages (links only), header burger (one link), mini-cart drawer (one
 *             button's behaviour). No checkout logic, form markup, payment or order code touched.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php RD-UX-qa-followups_20261004.php
 *
 * WHAT CHANGES
 *   1 Support links → bot (owner decision 2026-10-04). https://telegram.me/boostershop_tcg →
 *     https://telegram.me/BoosterShop_Support_bot in:
 *       checkout/success.twig — RD-14 footer link «напишіть у Telegram» and the fallback-state button (2×);
 *       checkout/failure.twig — RD-15 button «напишіть у Telegram» (1×);
 *       extension/ukrainian/.../uk-ua/checkout/failure.php — the Telegram link inside text_message (1×);
 *         php -l before and after the write, and the new text must load and survive the controller's sprintf().
 *     Channel links (header Telegram icon, burger «Наш Telegram-канал», footer) stay on boostershop_tcg.
 *   2 Burger: «Набори та бокси One Piece» → /catalog/One-Piece/one-piece-nabory-ta-boksy, between «Бустери One
 *     Piece» and «Фігурки та декор». Root-relative, class bs-menu__sub (SEO path 60_68, verified by Claude in
 *     the 2026-09-24 backup).
 *   3 Mini-cart empty state «До каталогу»: keeps data-bs-mini-cart-close (the drawer still closes through the
 *     existing handler) and gains data-bs-mini-cart-catalog. A new delegated handler in cart.twig's own script
 *     makes sure the drawer is closed — setMiniCartDrawerState(false) restores body position/top and scrolls
 *     back synchronously, i.e. the drawer's scroll lock is released — then moves focus to #bs-menu-open and
 *     clicks it on the next animation frame, so the burger's own lock (body.bs-menu-lock) starts on a clean
 *     body and closing the burger returns focus to «Каталог». No #bs-menu-open on the page → close only.
 *   4 Review N2: #checkout-success .bs-success-f15-k colour --bs-buy → --bs-buy-hover (#15803D). 13px bold on
 *     --bs-green-soft was 4.32:1; same hue, now above AA. Only ds.css change → ds.css ?v= token in header.twig.
 *
 * UI/CSS DISCIPLINE
 *   N2 edits the source rule (RD-14 section of boostershop-ds.css). No !important, no new override, no
 *   position rules, no magic numbers. JS: requestAnimationFrame (not setTimeout) to open the burger after the
 *   close has run.
 *
 * NOT CHANGED
 *   cart.twig is not touched by runners 4–7; BUG-004 (mobile badge, swipe-down reload) builds on this output.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all six targets (the deployed post-runner-3 state) → marker → anchors → php -l + load +
 *   sprintf check on the language file → hazard scan on added template text → Twig parse gate on success.twig,
 *   failure.twig, header.twig, cart.twig → CSS balance gate → backup of all six → write → post-write php -l →
 *   any failure restores all six.
 *   Idempotent: marker «RD-UX-QA-3B» in all six files. Self-deletes on success.
 *   ROLLBACK: copy the six files back from _patch_backups/RD-UX-qa-followups_20261004-<ts>/, refresh the theme
 *   cache, Ctrl+F5 — only while runners 4–7 are not applied.
 *   TRIGGER: PHP error or blank checkout/success or checkout/failure page; burger or mini-cart won't open or
 *   close; page stays unscrollable after «До каталогу»; JS error from cart.twig's script.
 * =============================================================================
 */

const PATCH_ID = 'RD-UX-qa-followups_20261004';
const MARKER   = 'RD-UX-QA-3B';
const TOKEN    = 'rdux3b-20261004';
const SUCCESS  = 'catalog/view/template/checkout/success.twig';
const FAILURE  = 'catalog/view/template/checkout/failure.twig';
const LANG     = 'extension/ukrainian/catalog/language/uk-ua/checkout/failure.php';
const HEADER   = 'catalog/view/template/common/header.twig';
const CART     = 'catalog/view/template/common/cart.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const TG_CHANNEL = 'https://telegram.me/boostershop_tcg';
const TG_BOT     = 'https://telegram.me/BoosterShop_Support_bot';

const EXPECTED_SHA = array(
    SUCCESS => '@@SHA:catalog/view/template/checkout/success.twig@@',
    FAILURE => '@@SHA:catalog/view/template/checkout/failure.twig@@',
    LANG    => '@@SHA:extension/ukrainian/catalog/language/uk-ua/checkout/failure.php@@',
    HEADER  => '@@SHA:catalog/view/template/common/header.twig@@',
    CART    => '@@SHA:catalog/view/template/common/cart.twig@@',
    DSCSS   => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
);

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(SUCCESS, FAILURE, LANG, HEADER, CART, DSCSS) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(SUCCESS, FAILURE, LANG, HEADER, CART, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- 1a success.twig: footer link + fallback button ----------------------- */
$success = $files[SUCCESS]['text'];
$successMustSurvive = array('{{ text_message }}', 'dataLayer.push', "'purchase'", 'CHECKOUT-008', 'bs-success-tg-link', 'bs-success-tg-btn', 'bs-success-fallback');
$successCounts = array();
foreach ($successMustSurvive as $probe) $successCounts[$probe] = substr_count($success, $probe);
count_exact($success, TG_CHANNEL, 2, 'success_channel_links');
count_exact($success, TG_BOT, 0, 'success_bot_links_before');
$success = str_replace('href="' . TG_CHANNEL . '"', 'href="' . TG_BOT . '"', $success);
$success = replace_one($success,
    "        <section class=\"bs-success-footer-msg\">\n",
    "        {# RD-UX-QA-3B (2026-10-04): support links go to the support bot (owner decision); the channel stays in the header/footer. #}\n"
    . "        <section class=\"bs-success-footer-msg\">\n",
    'success_marker');
count_exact($success, TG_CHANNEL, 0, 'success_channel_links_after');
count_exact($success, 'href="' . TG_BOT . '" target="_blank" rel="noopener" class="bs-success-tg-link"', 1, 'success_bot_footer');
count_exact($success, 'href="' . TG_BOT . '" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary bs-success-tg-btn"', 1, 'success_bot_fallback');
foreach ($successCounts as $probe => $n) count_exact($success, $probe, $n, 'success_kept');

/* ---- 1b failure.twig: button ---------------------------------------------- */
$failure = $files[FAILURE]['text'];
count_exact($failure, TG_CHANNEL, 1, 'failure_channel_link');
$failure = replace_one($failure,
    '<a href="' . TG_CHANNEL . '" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary">',
    '<a href="' . TG_BOT . '" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary">',
    'failure_button');
$failure = replace_one($failure,
    "   styles live in boostershop-ds.css (section RD-15). Routing to this page is unchanged (CHECKOUT-012). #}\n",
    "   styles live in boostershop-ds.css (section RD-15). Routing to this page is unchanged (CHECKOUT-012). #}\n"
    . "{# RD-UX-QA-3B (2026-10-04): the Telegram button goes to the support bot (owner decision). #}\n",
    'failure_marker');
count_exact($failure, TG_CHANNEL, 0, 'failure_channel_after');
foreach (array('{{ text_message }}', '{{ heading_title }}', '{{ continue }}') as $probe) count_exact($failure, $probe, 1, 'failure_kept');

/* ---- 1c language file: text_message link ---------------------------------- */
$lang = $files[LANG]['text'];
$lang = replace_one($lang,
    "// RD-15 (2026-10-04): heading_title, text_failure and text_message rewritten (owner copy).\n",
    "// RD-15 (2026-10-04): heading_title, text_failure and text_message rewritten (owner copy).\n"
    . "// RD-UX-QA-3B (2026-10-04): the Telegram link in text_message goes to the support bot (owner decision).\n",
    'lang_marker');
$oldMessageLink = 'напишіть нам у <a href="' . TG_CHANNEL . '" target="_blank" rel="noopener">Telegram</a>';
$newMessageLink = 'напишіть нам у <a href="' . TG_BOT . '" target="_blank" rel="noopener">Telegram</a>';
$lang = replace_one($lang, $oldMessageLink, $newMessageLink, 'lang_text_message_link');
count_exact($lang, TG_CHANNEL, 0, 'lang_channel_after');
$langBytes = encode_text($files[LANG], $lang);
php_lint_bytes($root, LANG, $langBytes);
// Expected strings: what the deployed file holds, with only the link swapped.
$_ = array();
$langTmp = $root . '/' . LANG . '.' . PATCH_ID . '.load.php';
if (file_put_contents($langTmp, $files[LANG]['raw'], LOCK_EX) !== strlen($files[LANG]['raw'])) fail('lang_load_temp_failed');
(static function () use ($langTmp, &$_): void { include $langTmp; })();
$before = $_;
$_ = array();
if (file_put_contents($langTmp, $langBytes, LOCK_EX) !== strlen($langBytes)) { @unlink($langTmp); fail('lang_load_temp_failed'); }
(static function () use ($langTmp, &$_): void { include $langTmp; })();
@unlink($langTmp);
$expectedMessage = str_replace($oldMessageLink, $newMessageLink, (string)($before['text_message'] ?? ''));
if (($_['text_message'] ?? null) !== $expectedMessage || strpos($expectedMessage, TG_BOT) === false) fail('lang_values_check');
if (array_keys($_) !== array_keys($before)) fail('lang_keys_changed');
foreach ($before as $key => $value) if ($key !== 'text_message' && $_[$key] !== $value) fail('lang_other_value_changed=' . $key);
if (sprintf((string)$_['text_message'], 'https://example.invalid/contact') !== $expectedMessage) fail('lang_sprintf_check');
out('lang_values=ok sprintf=ok');

/* ---- 2 header.twig: burger link + ds.css token ---------------------------- */
$h = $files[HEADER]['text'];
$hooks = array('id="ps-live-search-input"', 'id="ps-live-search"', 'id="bs-menu-open"', 'id="bs-menu"', 'data-bs-menu-close',
    'data-bs-accordion', 'class="bs-menu__subs"', 'id="bs-msearch"', 'id="cart"', 'id="alert"', TG_CHANNEL);
$hookCounts = array();
foreach ($hooks as $hook) $hookCounts[$hook] = substr_count($h, $hook);
$h = replace_one($h,
    "          <a href=\"/catalog/One-Piece-Boosters\" class=\"bs-menu__sub\">Бустери One Piece</a>\n"
    . "          <a href=\"/catalog/One-Piece/figurky-ta-dekor-one-piece\" class=\"bs-menu__sub\">Фігурки та декор</a>\n",
    "          <a href=\"/catalog/One-Piece-Boosters\" class=\"bs-menu__sub\">Бустери One Piece</a>\n"
    . "          {# RD-UX-QA-3B (2026-10-04): sets and boxes (SEO path 60_68). #}\n"
    . "          <a href=\"/catalog/One-Piece/one-piece-nabory-ta-boksy\" class=\"bs-menu__sub\">Набори та бокси One Piece</a>\n"
    . "          <a href=\"/catalog/One-Piece/figurky-ta-dekor-one-piece\" class=\"bs-menu__sub\">Фігурки та декор</a>\n",
    'hdr_onepiece_sets');
$h = bust_token($h, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
foreach ($hookCounts as $hook => $n) count_exact($h, $hook, $n, 'hdr_hook_kept');
count_exact($h, TG_BOT, 0, 'hdr_no_bot');

/* ---- 3 cart.twig: «До каталогу» → close, then the burger ------------------ */
$cart = $files[CART]['text'];
$cartHooks = array('data-bs-mini-cart-open', 'data-bs-mini-cart-close', 'data-bs-mini-cart', 'function setMiniCartDrawerState(open)');
$cartCounts = array();
foreach ($cartHooks as $hook) $cartCounts[$hook] = substr_count($cart, $hook);
$cart = replace_one($cart,
    "   navigation, so it uses bs-btn-secondary (green is for purchase actions). #}\n",
    "   navigation, so it uses bs-btn-secondary (green is for purchase actions). #}\n"
    . "{# RD-UX-QA-3B (2026-10-04): «До каталогу» closes the drawer, then opens the burger catalogue (owner decision). #}\n",
    'cart_marker');
$cart = replace_one($cart,
    '<button type="button" class="bs-btn bs-btn-secondary" data-bs-mini-cart-close>До каталогу</button>',
    '<button type="button" class="bs-btn bs-btn-secondary" data-bs-mini-cart-close data-bs-mini-cart-catalog>До каталогу</button>',
    'cart_catalog_button');
$cart = replace_one($cart,
    "\$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {\n"
    . "    setMiniCartDrawerState(false);\n"
    . "});\n",
    <<<'JS'
$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {
    setMiniCartDrawerState(false);
});
// RD-UX-QA-3B (2026-10-04): «До каталогу» opens the burger catalogue once the drawer is closed. The close above
// restores body position/top and the scroll offset synchronously; the burger opens on the next frame, so its own
// lock (body.bs-menu-lock) starts from a clean body. Focus moves to «Каталог» first, so closing the burger
// returns focus there rather than into the closed drawer.
$(document).off('click.miniCartCatalog', '[data-bs-mini-cart-catalog]').on('click.miniCartCatalog', '[data-bs-mini-cart-catalog]', function() {
    var menuButton = document.getElementById('bs-menu-open');

    if ($('body').hasClass('bs-mini-cart-open')) {
        setMiniCartDrawerState(false);
    }

    if (!menuButton) {
        return;
    }

    menuButton.focus({ preventScroll: true });
    requestAnimationFrame(function() {
        menuButton.click();
    });
});

JS
    , 'cart_catalog_handler');
$cartCounts['data-bs-mini-cart'] += 3; // the probe is a prefix of data-bs-mini-cart-catalog: button attribute + selector ×2
foreach ($cartCounts as $hook => $n) count_exact($cart, $hook, $n, 'cart_hook_kept');
count_exact($cart, 'data-bs-mini-cart-catalog', 3, 'cart_catalog_refs');

/* ---- 4 boostershop-ds.css: review N2 -------------------------------------- */
$ds = replace_one($files[DSCSS]['text'],
    "#checkout-success .bs-success-f15-k {\n  display: block;\n  margin-bottom: 2px;\n  color: var(--bs-buy);\n",
    "#checkout-success .bs-success-f15-k {\n  display: block;\n  margin-bottom: 2px;\n"
    . "  color: var(--bs-buy-hover); /* RD-UX-QA-3B (review N2): 13px bold on --bs-green-soft — #15803D clears AA, same hue */\n",
    'ds_f15_colour');
count_exact($ds, '--bs-buy-hover:', 1, 'ds_token_defined');
css_balance_gate($ds, DSCSS);

/* ---- gates ---------------------------------------------------------------- */
foreach (array(SUCCESS => $success, FAILURE => $failure, HEADER => $h, CART => $cart) as $rel => $text) {
    twig_hazard_gate(implode("\n", array_diff(explode("\n", $text), explode("\n", $files[$rel]['text']))), $rel);
}
twig_gate($root, array(
    SUCCESS => array($files[SUCCESS]['text'], $success),
    FAILURE => array($files[FAILURE]['text'], $failure),
    HEADER  => array($files[HEADER]['text'], $h),
    CART    => array($files[CART]['text'], $cart),
));

commit_files($root, $files, array(
    SUCCESS => encode_text($files[SUCCESS], $success),
    FAILURE => encode_text($files[FAILURE], $failure),
    LANG    => $langBytes,
    HEADER  => encode_text($files[HEADER], $h),
    CART    => encode_text($files[CART], $cart),
    DSCSS   => encode_text($files[DSCSS], $ds),
), array(LANG));

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/RD-UX-qa-followups_report_20261004.md');
