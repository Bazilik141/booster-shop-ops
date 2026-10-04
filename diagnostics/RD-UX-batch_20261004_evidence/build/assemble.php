<?php
// php assemble.php <body.php> <out.php> <workRoot>
// Tokens in body: @@LIB@@, @@FILE:relpath@@ (raw file text from build dir, for nowdoc), @@SHA:relpath@@ (sha256 of workRoot/relpath),
// @@BLOCKSHA:relpath|start|end@@ (sha256 of the text between two markers in workRoot/relpath, markers included).
[$self, $body, $out, $work] = $argv;
$dir = dirname($body);
$s = file_get_contents($body);
$s = str_replace('@@LIB@@', rtrim(file_get_contents(__DIR__ . '/lib.inc.php')), $s);
$s = preg_replace_callback('~@@FILE:([^@]+)@@~', function ($m) use ($dir) {
    $t = file_get_contents($dir . '/' . $m[1]);
    if ($t === false) { fwrite(STDERR, "missing $m[1]\n"); exit(1); }
    return rtrim($t, "\n");
}, $s);
$s = preg_replace_callback('~@@SHA:([^@]+)@@~', function ($m) use ($work) {
    $f = $work . '/' . $m[1];
    if (!is_file($f)) { fwrite(STDERR, "missing work file $m[1]\n"); exit(1); }
    return hash_file('sha256', $f);
}, $s);
$s = preg_replace_callback('~@@BLOCKSHA:([^|]+)\|([^|]+)\|([^@]+)@@~', function ($m) use ($work) {
    $t = file_get_contents($work . '/' . $m[1]);
    $start = stripcslashes($m[2]); $end = stripcslashes($m[3]);
    $a = strpos($t, $start); $b = strpos($t, $end);
    if ($a === false || $b === false) { fwrite(STDERR, "block markers not found in $m[1]\n"); exit(1); }
    return hash('sha256', substr($t, $a, $b + strlen($end) - $a));
}, $s);
if (preg_match('~@@[A-Z]+[:@]~', $s)) { fwrite(STDERR, "unresolved token\n"); exit(1); }
file_put_contents($out, $s);
echo "assembled $out (" . strlen($s) . " bytes)\n";
