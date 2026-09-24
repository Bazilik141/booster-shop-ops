<?php
/** PAY-003 shared waiting/recovery. PHP 8.0. CLI-only, no deployment DB/bank calls.
 * Runtime: authorized status reads, throttled bank-state fallback, existing status sync.
 * Backup includes rollback.php and generated files. Never run this source in the repo.
 */
declare(strict_types=1);
const PAY003_ID = 'PAY-003_credit-wait-recovery_20260831';
function pay003Need(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function pay003Write(string $path, string $content): void {
    if (!is_dir(dirname($path))) pay003Need(mkdir(dirname($path), 0755, true), 'mkdir failed');
    pay003Need(file_put_contents($path, $content, LOCK_EX) === strlen($content), 'write failed: ' . $path);
    pay003Need(hash_file('sha256', $path) === hash('sha256', $content), 'written hash failed');
}
function pay003Safe(string $root, string $file): string {
    pay003Need((bool)preg_match('~^(catalog|extension)/[a-zA-Z0-9_./-]+$~D', $file) && strpos($file, '..') === false, 'invalid relative path');
    $target = $root . '/' . $file;
    $part = $target;
    while ($part !== $root) {
        pay003Need(!is_link($part), 'symlink target refused');
        $part = str_replace('\\', '/', dirname($part));
        pay003Need(strlen($part) >= strlen($root), 'path escaped root');
    }
    return $target;
}
function pay003Lint(string $path): void {
    if (substr($path, -4) !== '.php') return;
    $output=[]; $code=1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    pay003Need($code === 0, 'PHP lint failed: ' . $path);
}
try {
    pay003Need(PHP_SAPI === 'cli' && PHP_VERSION_ID >= 80000, 'CLI PHP 8.0+ required');
    $root = str_replace('\\', '/', (string)realpath(getcwd()));
    pay003Need(is_file($root . '/config.php') && is_dir($root . '/catalog') && !is_dir($root . '/.git'), 'run uploaded copy from public_html only');
    $spec = json_decode(base64_decode('__SPEC__', true), true, 512, JSON_THROW_ON_ERROR);
    $files = $spec['files']; $allAfter = true;
    foreach ($spec['guards'] as $file=>$hash) pay003Need(is_file(pay003Safe($root,$file)) && hash_file('sha256',$root.'/'.$file)===$hash, 'guard mismatch: '.$file);
    foreach ($files as $file=>$entry) {
        $target=pay003Safe($root,$file);
        $allAfter = $allAfter && is_file($target) && hash_file('sha256',$target)===$entry['after'];
    }
    if ($allAfter) { echo "already_applied=yes\n"; echo 'self_delete=' . (@unlink(__FILE__)?'ok':'failed') . "\n"; exit(0); }
    $candidates=[];
    foreach ($files as $file=>$entry) {
        $target=$root.'/'.$file;
        if ($entry['before']===null) { pay003Need(!file_exists($target),'new target already exists: '.$file); $candidate=base64_decode($entry['content'],true); }
        else {
            pay003Need(is_file($target) && hash_file('sha256',$target)===$entry['before'],'source SHA mismatch: '.$file);
            $candidate=file_get_contents($target);
            foreach ($entry['edits'] as $edit) {
                pay003Need(substr_count($candidate,$edit['before'])===1,'anchor mismatch: '.$file);
                $candidate=str_replace($edit['before'],$edit['after'],$candidate);
            }
        }
        pay003Need(is_string($candidate) && hash('sha256',$candidate)===$entry['after'],'candidate SHA mismatch: '.$file);
        $candidates[$file]=$candidate;
    }
    $backup=$root.'/_patch_backups/'.PAY003_ID.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
    pay003Need(!is_link($root.'/_patch_backups'),'backup symlink refused');
    echo 'cwd='.$root."\ntime=".date('c')."\nbackup=".$backup."\n";
    foreach ($files as $file=>$entry) {
        if ($entry['before']!==null) pay003Write($backup.'/original/'.$file,(string)file_get_contents($root.'/'.$file));
        pay003Write($backup.'/generated/'.$file,$candidates[$file]);
        pay003Lint($backup.'/generated/'.$file);
    }
    $manifest=[];
    foreach ($files as $file=>$entry) $manifest[$file]=['before'=>$entry['before'],'after'=>$entry['after']];
    pay003Write($backup.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    pay003Write($backup.'/rollback.php',base64_decode('__ROLLBACK__',true));
    $written=[];
    try {
        foreach ($files as $file=>$entry) {
            $target=pay003Safe($root,$file);
            pay003Need($entry['before']===null?!file_exists($target):(is_file($target)&&hash_file('sha256',$target)===$entry['before']),'source changed during apply: '.$file);
            $written[]=$file;
            pay003Write($target,$candidates[$file]); pay003Lint($target);
            echo 'after_sha256='.$file.':'.$entry['after']."\n";
        }
    } catch (Throwable $error) {
        foreach (array_reverse($written) as $file) {
            if ($files[$file]['before']===null) pay003Need(!file_exists($root.'/'.$file)||unlink($root.'/'.$file),'rollback removal failed: '.$file);
            else pay003Write($root.'/'.$file,(string)file_get_contents($backup.'/original/'.$file));
        }
        throw new RuntimeException('source restored: '.$error->getMessage());
    }
    echo 'changed='.implode(',',array_keys($files))."\nphp_l=ok\nassertions=ok\ndatabase_touched=no\ndone=ok\n";
    echo 'self_delete=' . (@unlink(__FILE__)?'ok':'failed remove_uploaded_patch_manually=yes') . "\n";
} catch (Throwable $error) { fwrite(STDERR,'ERROR: '.$error->getMessage()."\n"); exit(1); }
