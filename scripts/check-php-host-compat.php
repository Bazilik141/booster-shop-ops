<?php
declare(strict_types=1);

/**
 * check-php-host-compat.php — pre-flight gate for Booster Shop PHP patches.
 *
 * Production (`uashared43`) is not the machine any agent develops on. Two
 * constraints have each cost a review round because a local `php -l` cannot see
 * them:
 *
 *   1. LANGUAGE VERSION — production CLI is PHP 8.0.30 and the only other
 *      binary on the host is ea-php72. There is no PHP 8.1+ anywhere. A file
 *      using an 8.1+ construct fails to PARSE, so it dies with a bare fatal
 *      before any of its own guard code or messaging can run.
 *      Hit on 2026-08-24 (PAY-002, PAY-004) and again on 2026-09-03 (UI-FIX).
 *
 *   2. HOST CAPABILITY — mysqli on this host is built WITHOUT mysqlnd, so the
 *      mysqlnd-only result helpers do not exist. This is NOT a syntax problem:
 *      the code parses fine on every PHP version and dies at runtime on the
 *      first read. Evidenced by
 *      diagnostics/LEGAL-002_offer_mono_pumb_archive_v3_report_20260724.md and
 *      ..._v4_report_20260724.md (v2 of that patch died on exactly this).
 *      Hit again on 2026-09-03 (UI-FIX).
 *
 * A syntax scan alone would have missed #2 — that is why there are two lists.
 *
 * This reads tokens, not raw text, so a construct named inside a comment or a
 * string does not produce a false hit the way `grep` does.
 *
 * Usage:
 *   php scripts/check-php-host-compat.php patches/SOME_PATCH.php [more.php ...]
 *   php scripts/check-php-host-compat.php --self-test
 *
 * Exit 0 = clean, exit 1 = findings (or a failed self-test).
 *
 * This is a static check, not a substitute for the real gate: run `php -l` on
 * each patch from ~/public_html in cPanel Terminal before executing it. That is
 * the only real PHP 8.0 available to this project.
 */

const TARGET_PHP = '8.0';

/** Syntax and functions introduced after the production PHP version. */
const NEW_FUNCTIONS = [
    'array_is_list' => '8.1', 'enum_exists' => '8.1', 'fsync' => '8.1', 'fdatasync' => '8.1',
    'memory_reset_peak_usage' => '8.2', 'ini_parse_quantity' => '8.2', 'curl_upkeep' => '8.2',
    'openssl_cipher_key_length' => '8.2',
    'json_validate' => '8.3', 'str_increment' => '8.3', 'str_decrement' => '8.3',
    'mb_str_pad' => '8.3', 'stream_context_set_options' => '8.3',
    'array_find' => '8.4', 'array_any' => '8.4', 'array_all' => '8.4',
];

/** Types that only became legal type declarations after 8.0. */
const NEW_TYPES = ['never' => '8.1', 'true' => '8.2', 'false' => '8.2', 'null' => '8.2'];

/**
 * Calls this host cannot serve, regardless of PHP version.
 * name => [why, what to use instead]
 */
const HOST_FORBIDDEN = [
    'get_result' => ['mysqli built without mysqlnd', 'result_metadata() + bind_result()'],
    'mysqli_stmt_get_result' => ['mysqli built without mysqlnd', 'result_metadata() + bind_result()'],
    'fetch_all' => ['mysqli built without mysqlnd', 'bind_result() + fetch() in a while loop'],
    'mysqli_fetch_all' => ['mysqli built without mysqlnd', 'bind_result() + fetch() in a while loop'],
    'mysqli_get_client_stats' => ['mysqli built without mysqlnd', 'drop it — diagnostics only'],
];

function scan_file(string $file): array {
    $code = file_get_contents($file);
    if ($code === false) {
        return [[$file, 0, 'unreadable', 'file could not be read']];
    }

    $findings = [];
    $tokens = token_get_all($code);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }
        list($id, $text, $line) = $token;

        // Comments and strings are not code — this is the whole point of using
        // the tokenizer instead of grep.
        if ($id === T_COMMENT || $id === T_DOC_COMMENT || $id === T_CONSTANT_ENCAPSED_STRING
            || $id === T_ENCAPSED_AND_WHITESPACE || $id === T_INLINE_HTML) {
            continue;
        }

        $lower = strtolower($text);

        if (defined('T_ENUM') && $id === constant('T_ENUM')) {
            $findings[] = [$file, $line, 'syntax', 'enum declaration (8.1)'];
        }
        if (defined('T_READONLY') && $id === constant('T_READONLY')) {
            $findings[] = [$file, $line, 'syntax', 'readonly (8.1/8.2)'];
        }
        if ($id === T_FINAL) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_CONST) {
                    $findings[] = [$file, $line, 'syntax', 'final class constant (8.1)'];
                }
                break;
            }
        }
        if ($id === T_STRING && isset(NEW_TYPES[$lower])) {
            for ($j = $i - 1; $j >= 0; $j--) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if ($tokens[$j] === ':') {
                    $findings[] = [$file, $line, 'syntax', "`{$lower}` used as a type (" . NEW_TYPES[$lower] . ')'];
                }
                break;
            }
        }
        if ($id === T_STRING && isset(NEW_FUNCTIONS[$lower])) {
            $findings[] = [$file, $line, 'syntax', "{$lower}() (" . NEW_FUNCTIONS[$lower] . ')'];
        }
        if ($id === T_ELLIPSIS) {
            $previous = isset($tokens[$i - 1]) ? $tokens[$i - 1] : null;
            $next = isset($tokens[$i + 1]) ? $tokens[$i + 1] : null;
            if ($previous === '(' && $next === ')') {
                $findings[] = [$file, $line, 'syntax', 'first-class callable syntax (8.1)'];
            }
        }
        if ($id === T_LNUMBER && preg_match('/^0o/i', $text) === 1) {
            $findings[] = [$file, $line, 'syntax', 'explicit octal 0o (8.1)'];
        }
        if ($id === T_STRING && isset(HOST_FORBIDDEN[$lower])) {
            list($why, $instead) = HOST_FORBIDDEN[$lower];
            $findings[] = [$file, $line, 'host', "{$lower}() — {$why}; use {$instead}"];
        }
    }

    if (preg_match('~\)\s*:\s*[A-Za-z_]+\s*&\s*[A-Za-z_]+~', $code) === 1) {
        $findings[] = [$file, 0, 'syntax', 'intersection return type (8.1)'];
    }

    return $findings;
}

function self_test(): int {
    $canary = <<<'CANARY'
<?php
enum Suit { case Hearts; }
class C { public readonly int $x; final const Y = 1; }
function boom(string $m): never { throw new RuntimeException($m); }
function t(): true { return true; }
$f = strlen(...);
$ok = array_is_list([1, 2]);
$o = 0o17;
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// get_result() and never inside a comment must NOT be reported
$s = 'fetch_all in a string must NOT be reported';
CANARY;

    $path = tempnam(sys_get_temp_dir(), 'phpcompat') . '.php';
    file_put_contents($path, $canary);
    $found = scan_file($path);
    unlink($path);

    $expected = ['enum', 'readonly', 'final class constant', '`never`', '`true`',
        'first-class callable', 'array_is_list', 'explicit octal', 'get_result', 'fetch_all'];
    $blob = '';
    foreach ($found as $f) {
        $blob .= $f[3] . "\n";
    }

    $missing = [];
    foreach ($expected as $needle) {
        if (strpos($blob, $needle) === false) {
            $missing[] = $needle;
        }
    }

    echo 'self-test: ' . count($found) . " findings on the canary\n";
    if ($missing !== []) {
        echo "SELF-TEST FAILED — the scanner no longer detects:\n";
        foreach ($missing as $m) {
            echo "  {$m}\n";
        }
        return 1;
    }
    echo "self-test: OK — every planted construct detected, and the ones inside a\n";
    echo "comment and a string were correctly ignored.\n";

    return 0;
}

$arguments = array_slice($argv, 1);

if ($arguments === [] || $arguments === ['--help'] || $arguments === ['-h']) {
    echo "usage: php scripts/check-php-host-compat.php <file.php> [...]\n";
    echo "       php scripts/check-php-host-compat.php --self-test\n";
    exit($arguments === [] ? 1 : 0);
}

if ($arguments === ['--self-test']) {
    exit(self_test());
}

$all = [];
foreach ($arguments as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "not a file: {$file}\n");
        exit(1);
    }
    echo "scanned: {$file}\n";
    $all = array_merge($all, scan_file($file));
}

if ($all === []) {
    echo "\nOK — nothing newer than PHP " . TARGET_PHP . ", and no call this host cannot serve.\n";
    echo "Still run `php -l` on each file from ~/public_html before executing it.\n";
    exit(0);
}

$syntax = 0;
$host = 0;
echo "\n" . count($all) . " finding(s):\n";
foreach ($all as $finding) {
    list($file, $line, $kind, $message) = $finding;
    $where = $line > 0 ? "{$file}:{$line}" : $file;
    echo "  [{$kind}] {$where}  {$message}\n";
    if ($kind === 'host') {
        $host++;
    } else {
        $syntax++;
    }
}
echo "\nsyntax findings (would not parse on PHP " . TARGET_PHP . "): {$syntax}\n";
echo "host findings (parse fine, die at runtime on this host): {$host}\n";
exit(1);
