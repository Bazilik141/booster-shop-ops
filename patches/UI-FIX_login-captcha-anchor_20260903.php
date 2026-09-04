<?php
declare(strict_types=1);

/**
 * UI-FIX post-deploy — anchor the account/login reCAPTCHA to the login column.
 *
 * Handoff: handoffs/handoff_UI-FIX_postdeploy-captcha-regression_20260903.md
 * Fixes a regression introduced by UI-FIX_mobile-desktop-polish_20260903.php
 * (task T2), which is already deployed and self-deleted. This is a new patch on
 * top of it, not a resend.
 *
 * WHAT BROKE, AND WHY
 * -------------------
 * The widget is not written by login.twig. The reCAPTCHA extension injects it
 * on the `catalog/view/account/login/before` event by string-replacing the
 * template SOURCE, in
 * extension/ps_google_recaptcha/catalog/model/captcha/ps_google_recaptcha.php,
 * method replaceCatalogViewAccountLoginBefore():
 *
 *     'search'    => '<div class="text-end">',
 *     'replace'   => "\n{{ captcha }}\n<div class=\"text-end\">",
 *     'positions' => [2]
 *
 * It injects before the **2nd** `<div class="text-end">` in the file. There are
 * exactly two: one closing the Реєстрація column, one closing the Увійти
 * column. Before T2 the login column was second, so position 2 was the login
 * column and the widget landed inside <form id="form-login">. T2 swapped the two
 * columns (Увійти first — the requested change, and correct), so position 2
 * became the registration column and the widget moved with it.
 *
 * That is the whole defect: a positional anchor, invisible from login.twig, that
 * silently followed the column order.
 *
 * WHY IT BREAKS LOGIN AND NOT JUST THE LAYOUT
 * -------------------------------------------
 * Verified against the extension template
 * (extension/ps_google_recaptcha/catalog/view/template/captcha/ps_google_recaptcha.twig)
 * and the live page on 2026-09-03. The site runs key_type = v2_checkbox, whose
 * only wiring is:
 *
 *     grecaptcha.render('g-recaptcha-{{ widget_counter }}', {...})
 *
 * Google injects <textarea name="g-recaptcha-response"> INSIDE that container.
 * There is no hidden proxy field, no `form=` attribute and no JS that copies the
 * token anywhere. The value reaches the login POST for exactly one reason: the
 * container is a DOM descendant of <form id="form-login"> and is submitted as an
 * ordinary field. Once the widget moved into the registration column — which has
 * no <form> at all — the response stopped being posted, so server-side
 * validation fails and the customer cannot log in. The same move also left
 *
 *     recaptcha_form1 = document.currentScript.closest('form')   // → null
 *
 * so the post-submit widget reset stopped binding too.
 *
 * Putting the widget back inside the login form restores both. Nothing else
 * carried the value, so nothing else needs preserving.
 *
 * THE FIX
 * -------
 * Replace the positional anchor with a named slot that exists only in the login
 * column, so placement no longer depends on which column renders first:
 *
 *   1. login.twig — the login submit wrapper becomes
 *      <div class="text-end" data-captcha-slot="login">
 *      An attribute on the wrapper div around the Увійти button. The <form>
 *      element, its action, its login/register tokens, the button markup and the
 *      T2 column order are all byte-unchanged (ACC-003 lives on this page).
 *
 *   2. the extension model — searches for that slot and drops `positions`, so
 *      replaceViews() uses a plain str_replace on the single unique match.
 *
 * Scope guardrails from the handoff are respected: only the widget's
 * targeting/anchor changes. No column order, no #form-login, no DB, no captcha
 * behaviour, no other route's injection rules.
 *
 * KNOWN TRADE-OFF
 * ---------------
 * ps_google_recaptcha is a marketplace extension, so a future extension update
 * would overwrite the model and reinstate the positional rule — the captcha
 * would then jump back to the registration column. Editing this extension's
 * files in place is established practice in this repo (RD-13.1D/E/H, 2026-07-12,
 * all patched extension/ps_google_recaptcha/...). The `data-captcha-slot`
 * attribute in login.twig is deliberately self-documenting so the connection is
 * greppable from either side.
 *
 * Files only; no DB writes.
 * Rollback: restore both files from
 * _patch_backups/UI-FIX_login-captcha-anchor_20260903-<timestamp>/.
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_login-captcha-anchor_20260903.php --dry-run
 *   php UI-FIX_login-captcha-anchor_20260903.php
 */

const PATCH_ID = 'UI-FIX_login-captcha-anchor_20260903';
const MARKER = 'UI-FIX-20260903-CAPTCHA';
const SLOT = '<div class="text-end" data-captcha-slot="login">';

const FILES = [
    'login_twig' => 'catalog/view/template/account/login.twig',
    'recaptcha_model' => 'extension/ps_google_recaptcha/catalog/model/captcha/ps_google_recaptcha.php',
];

function out(string $message) {
    echo $message . PHP_EOL;
}

function fail(string $message) {
    throw new RuntimeException($message);
}

function normalize(string $text): string {
    return str_replace(["\r\n", "\r"], "\n", $text);
}

function assert_count(string $haystack, string $needle, int $expected, string $label) {
    $actual = substr_count($haystack, normalize($needle));
    if ($actual !== $expected) {
        fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function replace_once(string $content, string $needle, string $replacement, string $label): string {
    $needle = normalize($needle);
    assert_count($content, $needle, 1, $label);

    return str_replace($needle, normalize($replacement), $content);
}

function write_atomic(string $path, string $content, bool $crlf) {
    if ($crlf) {
        $content = str_replace("\n", "\r\n", $content);
    }
    $temporary = $path . '.uifixcap.tmp.' . getmypid();
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        fail('temporary_write_failed=' . $path);
    }
    if (!@rename($temporary, $path)) {
        if (!copy($temporary, $path)) {
            @unlink($temporary);
            fail('file_replace_failed=' . $path);
        }
        @unlink($temporary);
    }
}

function php_lint_file(string $path, string $label) {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fail('php_lint_failed=' . $label . ':' . implode(' | ', $output));
    }
    out('php_lint=ok:' . $label);
}

function self_lint() {
    php_lint_file(__FILE__, 'self');
}

$arguments = array_slice($argv ?? [], 1);
if ($arguments !== [] && $arguments !== ['--dry-run']) {
    fail('usage:php ' . basename(__FILE__) . ' [--dry-run]');
}
$dryRun = $arguments === ['--dry-run'];

self_lint();

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
if (!is_file($root . '/config.php')) {
    fail('run_from_public_html_required');
}

$original = [];
$crlf = [];
foreach (FILES as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fail('missing_file=' . $relative);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        fail('read_failed=' . $relative);
    }
    $crlf[$key] = strpos($raw, "\r\n") !== false;
    $original[$key] = normalize($raw);
}

$alreadyApplied = strpos($original['login_twig'], MARKER) !== false
    && strpos($original['recaptcha_model'], MARKER) !== false;

if ($alreadyApplied && !$dryRun) {
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}
if ($alreadyApplied) {
    out('already_applied=yes');
    out('dry_run=ok');
    exit(0);
}

$content = $original;

/* ---------------------------------------------------------- login.twig --- */

// Anchored on the submit button line as well as the wrapper, so this cannot
// latch onto the registration column's own <div class="text-end"> by accident.
$content['login_twig'] = replace_once(
    $content['login_twig'],
    "              <div class=\"text-end\">\n"
    . "                <button type=\"submit\" class=\"btn btn-primary\">{{ button_login }}</button>",
    "              {# " . MARKER . ": named slot for the reCAPTCHA widget. The\n"
    . "                 extension injects {{ captcha }} immediately before this div,\n"
    . "                 which keeps the widget inside #form-login whichever order the\n"
    . "                 two columns render in. Removing this attribute stops the\n"
    . "                 captcha from rendering and blocks login — see\n"
    . "                 patches/UI-FIX_login-captcha-anchor_20260903.php. #}\n"
    . "              " . SLOT . "\n"
    . "                <button type=\"submit\" class=\"btn btn-primary\">{{ button_login }}</button>",
    'login_slot'
);

/* ----------------------------------------------------- extension model --- */

$content['recaptcha_model'] = replace_once(
    $content['recaptcha_model'],
    "        \$views[] = [\n"
    . "            'search' => '<div class=\"text-end\">',\n"
    . "            'replace' => '\n"
    . "            {{ captcha }}\n"
    . "            <div class=\"text-end\">',\n"
    . "            'positions' => [2]\n"
    . "        ];",
    "        // " . MARKER . ": anchored to a named slot in the login column instead\n"
    . "        // of \"the 2nd <div class=text-end> in the file\". The positional rule\n"
    . "        // silently followed the column order when UX-036 put Увійти first,\n"
    . "        // moving the widget into the Реєстрація column and out of\n"
    . "        // #form-login — which stopped g-recaptcha-response being posted and\n"
    . "        // blocked login. The slot exists only in the login column, so a\n"
    . "        // single str_replace lands it correctly whatever the column order.\n"
    . "        \$views[] = [\n"
    . "            'search' => '" . SLOT . "',\n"
    . "            'replace' => '\n"
    . "            {{ captcha }}\n"
    . "            " . SLOT . "',\n"
    . "        ];",
    'model_anchor'
);

/* ------------------------------------------------------------- verify --- */

foreach (FILES as $key => $relative) {
    if ($content[$key] === $original[$key]) {
        fail('no_change_produced=' . $relative);
    }
}

// The slot must exist exactly once, on both sides of the contract.
assert_count($content['login_twig'], SLOT, 1, 'twig_slot_unique');
assert_count($content['recaptcha_model'], SLOT, 2, 'model_slot_search_and_replace');
assert_count($content['recaptcha_model'], "'positions' => [2]", 0, 'model_positional_rule_gone');

// The button rule in the same method must keep matching the untouched button.
assert_count(
    $content['recaptcha_model'],
    '\'search\' =>  \'<button type="submit" class="btn btn-primary">{{ button_login }}</button>\'',
    1,
    'model_button_rule_intact'
);
assert_count(
    $content['login_twig'],
    '<button type="submit" class="btn btn-primary">{{ button_login }}</button>',
    1,
    'twig_button_untouched'
);

// T2's column order and the form itself must be exactly as deployed.
assert_count($content['login_twig'], '<div class="col mb-3">', 2, 'twig_two_columns');
assert_count($content['login_twig'], '<form id="form-login" action="{{ login }}" method="post" data-oc-toggle="ajax">', 1, 'twig_form_untouched');
$formStart = strpos($content['login_twig'], '<form id="form-login"');
$formEnd = strpos($content['login_twig'], '</form>', $formStart === false ? 0 : $formStart);
$slotAt = strpos($content['login_twig'], SLOT);
if ($formStart === false || $formEnd === false || $slotAt === false || $slotAt < $formStart || $slotAt > $formEnd) {
    fail('slot_not_inside_login_form');
}
out('verified=slot_is_inside_form-login');

// The login column must still be the one rendered first (T2 is not undone).
$loginColumn = strpos($content['login_twig'], '<form id="form-login"');
$registerHeading = strpos($content['login_twig'], '<h2>{{ text_register }}</h2>');
if ($registerHeading === false || $loginColumn > $registerHeading) {
    fail('t2_column_order_changed');
}
out('verified=t2_column_order_unchanged');

if ($dryRun) {
    foreach (FILES as $key => $relative) {
        out(sprintf('plan=%s %d -> %d bytes', $relative, strlen($original[$key]), strlen($content[$key])));
    }
    out('dry_run=ok');
    exit(0);
}

/* -------------------------------------------------------------- write --- */

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (file_exists($backupDir)) {
    fail('backup_path_exists');
}
if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fail('backup_create_failed');
}

$restore = function () use ($root, $backupDir) {
    foreach (FILES as $relative) {
        $backup = $backupDir . '/' . $relative;
        if (is_file($backup)) {
            @copy($backup, $root . '/' . $relative);
        }
    }
};

try {
    foreach (FILES as $relative) {
        $target = $backupDir . '/' . $relative;
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            fail('backup_dir_create_failed=' . $directory);
        }
        if (!copy($root . '/' . $relative, $target)) {
            fail('backup_copy_failed=' . $relative);
        }
        out('backup=' . $relative);
    }

    foreach (FILES as $key => $relative) {
        write_atomic($root . '/' . $relative, $content[$key], $crlf[$key]);
        out('written=' . $relative);
    }

    // C4 — syntax gate on the only PHP file this patch writes, restore on fail.
    php_lint_file($root . '/' . FILES['recaptcha_model'], 'recaptcha_model');

    foreach (FILES as $key => $relative) {
        $readback = file_get_contents($root . '/' . $relative);
        if ($readback === false || normalize($readback) !== $content[$key]) {
            fail('readback_mismatch=' . $relative);
        }
    }
    out('readback=ok');
} catch (Throwable $error) {
    $restore();
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . ' (files restored from ' . $backupDir . ')' . PHP_EOL);
    exit(1);
}

out('backup_dir=' . str_replace($root . '/', '', $backupDir));
out('done=ok');
@unlink(__FILE__);
